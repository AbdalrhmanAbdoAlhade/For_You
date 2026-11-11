<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromotionalLink;
use App\Models\User;
use App\Models\Coupon;
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
        protected PromotionalLinkService $linkService,
        protected PricingService $pricingService
    ) {}

    /**
     * عرض الأسعار قبل التنفيذ (مفيش حاجة بتتحفظ ولا مخزون بيتخصم).
     */
    public function quote(User $customer, array $data): array
    {
        [$lines, $subtotal] = $this->buildLines($data['items'], false);

        $pricing = $this->pricingService->calculate(
            $subtotal,
            $data['coupon_code'] ?? null,
            $data['city'] ?? null,
            $customer
        );

        return [
            'items' => array_map(fn ($l) => [
                'product_id' => $l['product']->id,
                'name'       => $l['product']->localized('name'),
                'quantity'   => $l['qty'],
                'unit_price' => $l['unitPrice'],
                'line_total' => $l['lineTotal'],
            ], $lines),
            'subtotal'      => $pricing['subtotal'],
            'discount'      => $pricing['discount'],
            'shipping_cost' => $pricing['shipping_cost'],
            'total'         => $pricing['total'],
            'coupon_code'   => $pricing['coupon']?->code,
        ];
    }
public function isRefApplicable(User $customer, ?string $ref, array $items): bool
{
    if (!$ref) {
        return false;
    }

    $ids = array_map(fn ($i) => (int) $i['product_id'], $items);

    return $this->resolveLink($ref, $customer, $ids) !== null;
}
    public function createOrder(User $customer, array $data): Order
    {
        return DB::transaction(function () use ($customer, $data) {

            [$lines, $subtotal] = $this->buildLines($data['items'], true);

            $pricing = $this->pricingService->calculate(
                $subtotal,
                $data['coupon_code'] ?? null,
                $data['city'] ?? null,
                $customer,
                true
            );

            $coupon = $pricing['coupon'];
            $orderedProductIds = array_map(
    fn ($line) => (int) $line['product']->id,
    $lines
);

$link = $this->resolveLink(
    $data['ref'] ?? null,
    $customer,
    $orderedProductIds
);

            $order = Order::create([
                'order_number'        => $this->generateOrderNumber(),
                'customer_id'         => $customer->id,
                'influencer_id'       => $link?->influencer_id,
                'promotional_link_id' => $link?->id,
                'coupon_id'           => $coupon?->id,
                'coupon_code'         => $coupon?->code,
                'status'              => 'new',
                'subtotal'            => $pricing['subtotal'],
                'discount'            => $pricing['discount'],
                'shipping_cost'       => $pricing['shipping_cost'],
                'total'               => $pricing['total'],
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

          
            $coupon?->increment('used_count');

            // سجل الدفع (pending لحد ما الأدمن أو البوابة تأكده)
            Payment::create([
                'order_id'       => $order->id,
                'payment_method' => $data['payment_method'] ?? config('store.default_payment_method'),
                'amount'         => $pricing['total'],
                'status'         => 'pending',
            ]);

            return $order->load(['items', 'payment']);
        });
    }   

        /**
     * إلغاء العميل لطلبه: مسموح قبل التأكيد والدفع بس.
     *
     * @throws \DomainException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException لو الطلب مش بتاعه (404)
     */
    public function cancelByCustomer(User $customer, int $orderId): Order
    {
        return DB::transaction(function () use ($customer, $orderId) {

            $order = Order::where('customer_id', $customer->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'new') {
                throw new \DomainException('لا يمكن إلغاء الطلب بعد تأكيده، تواصل مع الدعم');
            }

            if ($order->payment()->where('status', 'paid')->exists()) {
                throw new \DomainException('تم دفع الطلب، تواصل مع الدعم لإلغائه واسترداد المبلغ');
            }

            return $this->updateStatus($order, 'cancelled')->load(['items', 'payment']);
        });
    }

    public function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        try {
            $this->assertTransitionAllowed($from, $to);
            return true;
        } catch (\DomainException) {
            return false;
        }
    }

    /**
     * @throws \DomainException لو الانتقال مش مسموح
     */
    public function updateStatus(Order $order, string $status): Order
    {
        $changed = false;
        $commissions = collect();

        $order = DB::transaction(function () use ($order, $status, &$changed, &$commissions) {

            $order = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === $status) {
                return $order;
            }

            $this->assertTransitionAllowed($order->status, $status);
            
                            // منع الشحن أو التوصيل قبل تأكيد الدفع، حسب إعدادات المتجر.
                if (
                    config('store.require_payment_before_shipping', true)
                    && in_array($status, ['shipped', 'delivered'], true)
                ) {
                    $payment = $order->payment()
                        ->lockForUpdate()
                        ->first();
                
                    if (!$payment || $payment->status !== 'paid') {
                        throw new \DomainException(
                            'لا يمكن شحن أو تسليم الطلب قبل تأكيد الدفع'
                        );
                    }
                }
            
            $previousStatus = $order->status;

            $order->update(['status' => $status]);
            $changed = true;

                            // تسجيل التحويل عند اكتمال توصيل الطلب
                if ($status === 'delivered' && $order->promotional_link_id) {
                    PromotionalLink::whereKey($order->promotional_link_id)
                        ->increment('conversions');
                }

                // استبعاد الطلب المرتجع من التحويلات إذا كان قد وصل بالفعل
                if (
                    $status === 'returned'
                    && $previousStatus === 'delivered'
                    && $order->promotional_link_id
                ) {
                    PromotionalLink::whereKey($order->promotional_link_id)
                        ->where('conversions', '>', 0)
                        ->decrement('conversions');
                }
                        if ($status === 'cancelled') {
                // نرجّع استخدام الكوبون ونقفل الدفعات المعلقة
                if ($order->coupon_id) {
                    Coupon::whereKey($order->coupon_id)
                        ->where('used_count', '>', 0)
                        ->decrement('used_count');
                }

                Payment::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);
            }

            if (in_array($status, ['cancelled', 'returned'], true)) {
                $this->restoreStock($order);
                $this->commissionService->reverseForOrder($order);
            }

            if ($status === 'delivered' && $order->influencer_id) {
                $commissions = $this->commissionService->calculateAndStore($order);
            }

            return $order;
        });

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

    // ========================= helpers =========================

        /**
     * @return array{0: array, 1: float} [الأسطر, المجموع الفرعي]
     * @throws \DomainException لو منتج غير متاح أو الكمية غير متوفرة
     */
    protected function buildLines(array $items, bool $lock): array
    {
        $quantities = [];
        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $quantities[$id] = ($quantities[$id] ?? 0) + (int) $item['quantity'];
        }
        ksort($quantities); // ترتيب ثابت للـ lock لتفادي deadlock

        $query = Product::whereIn('id', array_keys($quantities));
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id');

        $lines = [];
        $subtotal = 0.0;

        foreach ($quantities as $productId => $qty) {
            $product = $products->get($productId);

           
if (!$product || !$product->is_active) {
    // المنتج المحذوف Soft Delete لا يظهر في الاستعلام العادي.
    $label = $product?->localized('name')
        ?? __('رقم :id', ['id' => $productId]);

    throw new \DomainException(
        __('المنتج :label غير متاح', [
            'label' => $label,
        ])
    );
}

if ($product->stock < $qty) {
    throw new \DomainException(
        __('الكمية المطلوبة من ":name" غير متوفرة (المتاح: :stock)', [
            'name'  => $product->localized('name'),
            'stock' => $product->stock,
        ])
    );
}


            $unitPrice = (float) ($product->discount_price ?? $product->price);
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = compact('product', 'qty', 'unitPrice', 'lineTotal');
        }

        return [$lines, round($subtotal, 2)];
    }

 protected function resolveLink(
    ?string $ref,
    User $customer,
    array $orderedProductIds = []
): ?PromotionalLink {
    if (!$ref) {
        return null;
    }

    $link = $this->linkService->findActive($ref);

    if (!$link) {
        return null;
    }

    $influencer = $link->influencer;

    $validInfluencer = $influencer
        && $influencer->type === 'influencer'
        && $influencer->is_active
        && $influencer->id !== $customer->id;

    if (!$validInfluencer) {
        return null;
    }

    // الرابط الخاص بمنتج لا يُنسب إلا لطلب يحتوي على هذا المنتج.
    if (
        $link->product_id !== null
        && !in_array(
            (int) $link->product_id,
            $orderedProductIds,
            true
        )
    ) {
        return null;
    }

    return $link;
}

    protected function assertTransitionAllowed(string $from, string $to): void
    {
        if (in_array($from, ['cancelled', 'returned'], true)) {
            throw new \DomainException('لا يمكن تغيير حالة طلب ملغي أو مرتجع');
        }

        $shippedIndex = array_search('shipped', self::FLOW, true);
        $fromIndex    = array_search($from, self::FLOW, true);

        $allowed = match ($to) {
            'cancelled' => $fromIndex < $shippedIndex,
            'returned'  => $fromIndex >= $shippedIndex,
            default     => ($toIndex = array_search($to, self::FLOW, true)) !== false
                           && $toIndex > $fromIndex,
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
