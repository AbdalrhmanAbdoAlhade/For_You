<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\NewCommission;

class OrderService
{
    /** ترتيب الدورة الطبيعية للطلب */
    protected const FLOW = ['new', 'confirmed', 'processing', 'ready_for_shipping', 'shipped', 'delivered'];

    public function __construct(
        protected InfluencerCommissionService $commissionService,
        protected PromotionalLinkService $linkService
    ) {}

    public function createOrder(User $customer, array $data): Order
    {
        // دمج المنتجات المكررة في الطلب
        $quantities = [];
        foreach ($data['items'] as $item) {
            $id = (int) $item['product_id'];
            $quantities[$id] = ($quantities[$id] ?? 0) + (int) $item['quantity'];
        }
        ksort($quantities); // ترتيب ثابت للـ lock لتفادي deadlock

        return DB::transaction(function () use ($customer, $data, $quantities) {

            // lock على المنتجات لحد ما الـ transaction تخلص
            $products = Product::whereIn('id', array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($quantities as $productId => $qty) {
                $product = $products->get($productId);

                if (!$product || !$product->is_active) {
                    throw new \DomainException("المنتج رقم {$productId} غير متاح");
                }

                if ($product->stock < $qty) {
                    throw new \DomainException(
                        "الكمية المطلوبة من \"{$product->name}\" غير متوفرة (المتاح: {$product->stock})"
                    );
                }

                $unitPrice = $product->discount_price ?? $product->price;
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $lines[] = compact('product', 'qty', 'lineTotal');
            }

            // الرابط الترويجي
            $link = null;
            if (!empty($data['ref'])) {
                $link = $this->linkService->findActive($data['ref']);

                if ($link) {
                    $influencer = $link->influencer;
                    $valid = $influencer
                        && $influencer->type === 'influencer'
                        && $influencer->is_active
                        && $influencer->id !== $customer->id; // المؤثر ميكسبش من مشترياته

                    if (!$valid) {
                        $link = null;
                    }
                }
            }

            $subtotal = round($subtotal, 2);

            $order = Order::create([
                'order_number'        => $this->generateOrderNumber(),
                'customer_id'         => $customer->id,
                'influencer_id'       => $link?->influencer_id,
                'promotional_link_id' => $link?->id,
                'status'              => 'new',
                'subtotal'            => $subtotal,
                'discount'            => 0,
                'shipping_cost'       => 0,
                'total'               => $subtotal,
                'customer_name'       => $data['customer_name'],
                'customer_phone'      => $data['customer_phone'],
                'customer_email'      => $data['customer_email'] ?? null,
                'shipping_address'    => $data['shipping_address'],
                'city'                => $data['city'] ?? null,
                'country'             => $data['country'] ?? null,
                'notes'               => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];

                OrderItem::create([
                    'order_id'       => $order->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'price'          => $product->price,
                    'discount_price' => $product->discount_price,
                    'quantity'       => $line['qty'],
                    'total'          => $line['lineTotal'],
                ]);

                $product->decrement('stock', $line['qty']);
            }

            if ($link) {
                $link->increment('conversions');
            }

            return $order->load('items');
        });
    }

    /**
     * @throws \DomainException لو الانتقال مش مسموح
     */
    public function updateStatus(Order $order, string $status): Order
    {
        $changed = false;
        $commissions = collect();

        $order = DB::transaction(function () use ($order, $status, &$changed, &$commissions) {

            // lock على الطلب عشان طلبين متزامنين ميعملوش نفس الانتقال مرتين
            $order = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === $status) {
                return $order;
            }

            $this->assertTransitionAllowed($order->status, $status);

            $order->update(['status' => $status]);
            $changed = true;

            if (in_array($status, ['cancelled', 'returned'], true)) {
                $this->restoreStock($order);
                $this->commissionService->reverseForOrder($order);
            }

            if ($status === 'delivered' && $order->influencer_id) {
                $commissions = $this->commissionService->calculateAndStore($order);
            }

            return $order;
        });

        // الإشعارات بعد نجاح الـ transaction
        if ($changed) {
            $order->customer->notify(new OrderStatusUpdated($order));

            if ($commissions->isNotEmpty()) {
                $order->influencer->notify(new NewCommission(
                    $order->id,
                    (float) $commissions->sum('commission_amount')
                ));
            }
        }

        return $order;
    }

    protected function assertTransitionAllowed(string $from, string $to): void
    {
        if (in_array($from, ['cancelled', 'returned'], true)) {
            throw new \DomainException('لا يمكن تغيير حالة طلب ملغي أو مرتجع');
        }

        $shippedIndex = array_search('shipped', self::FLOW, true);
        $fromIndex    = array_search($from, self::FLOW, true);

        $allowed = match ($to) {
            'cancelled' => $fromIndex < $shippedIndex,   // الإلغاء قبل الشحن بس
            'returned'  => $fromIndex >= $shippedIndex,  // الإرجاع بعد الشحن بس
            default     => ($toIndex = array_search($to, self::FLOW, true)) !== false
                           && $toIndex > $fromIndex,     // للأمام بس
        };

        if (!$allowed) {
            throw new \DomainException("لا يمكن نقل الطلب من الحالة ({$from}) إلى ({$to})");
        }
    }

    protected function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }
        }
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(Str::random(8));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
