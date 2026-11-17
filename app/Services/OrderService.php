<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromotionalLink;
use App\Models\User;
use App\Models\Coupon;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\NewCommission;

class OrderService
{
    protected const FLOW = ['new', 'confirmed', 'processing', 'ready_for_shipping', 'shipped', 'delivered'];

    protected const TRANSITIONS = [
        'new'                => ['confirmed', 'cancelled'],
        'confirmed'          => ['processing', 'cancelled'],
        'processing'         => ['ready_for_shipping', 'cancelled'],
        'ready_for_shipping' => ['shipped', 'cancelled'],
        'shipped'            => ['delivered', 'returned'],
        'delivered'          => ['returned'],
        'cancelled'          => [],
        'returned'           => [],
    ];

    public function __construct(
        protected InfluencerCommissionService $commissionService,
        protected PromotionalLinkService $linkService,
        protected PricingService $pricingService,
        protected VariantService $variantService,
    ) {}

    public function quote(User $customer, array $data): array
    {
        [$lines, $subtotal] = $this->buildLines($data['items'], false);

        $pricing = $this->pricingService->calculate(
            $subtotal,
            $data['coupon_code'] ?? null,
            $data['city'] ?? null,
            $customer,
            false, // is_checkout = false
            $data['shipping_method'] ?? null,
            $data['payment_method'] ?? null
        );

        return [
            'items' => array_map(fn ($l) => [
                'product_id'    => $l['product']->id,
                'variant_id'    => $l['variant']?->id,
                'variant_label' => $l['variant']?->label,
                'name'          => $l['product']->localized('name'),
                'quantity'      => $l['qty'],
                'unit_price'    => $l['unitPrice'],
                'line_total'    => $l['lineTotal'],
            ], $lines),
            'subtotal'          => $pricing['subtotal'],
            'discount'          => $pricing['discount'],
            'shipping_method'   => $pricing['shipping_method'],
            'shipping_cost'     => $pricing['shipping_cost'],
            'cod_fee'           => $pricing['cod_fee'],
            'total'             => $pricing['total'],
            'coupon_code'       => $pricing['coupon']?->code,
            'available_methods' => $this->availableShippingMethods(),
        ];
    }

    /**
     * قائمة طرق الشحن المتاحة من config (مش hardcoded في controller)
     */
    protected function availableShippingMethods(): array
    {
        $methods = config('store.shipping.methods', []);
        $out = [];
        foreach ($methods as $key => $m) {
            $out[] = [
                'key'      => $key,
                'label'    => $m['label'] ?? $key,
                'cost'     => round((float) ($m['cost'] ?? 0), 2),
                'eta_days' => (int) ($m['eta_days'] ?? 0),
            ];
        }
        return $out;
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

            // تم إضافة shipping_method و payment_method هنا
            $pricing = $this->pricingService->calculate(
                $subtotal,
                $data['coupon_code'] ?? null,
                $data['city'] ?? null,
                $customer,
                true, // is_checkout = true
                $data['shipping_method'] ?? null,
                $data['payment_method'] ?? null
            );

            $coupon = $pricing['coupon'];

            $orderedProductIds = array_values(array_unique(
                array_map(fn ($line) => (int) $line['product']->id, $lines)
            ));

            $link = $this->resolveLink(
                $data['ref'] ?? null,
                $customer,
                $orderedProductIds,
                [
                    'phone' => $data['customer_phone'] ?? null,
                    'email' => $data['customer_email'] ?? null,
                ]
            );

            $linkProductId = $link?->product_id;

            $commissionPercentages = collect();

            if ($link && $link->influencer_id) {
                $commissionPercentages = DB::table('influencer_product')
                    ->where('influencer_id', $link->influencer_id)
                    ->where('is_active', true)
                    ->whereIn('product_id', $orderedProductIds)
                    ->pluck('commission_percentage', 'product_id');
            }

            // تم إضافة shipping_method, shipping_cost, cod_fee هنا
            $order = $this->createOrderRecord([
                'customer_id'         => $customer->id,
                'influencer_id'       => $link?->influencer_id,
                'promotional_link_id' => $link?->id,
                'coupon_id'           => $coupon?->id,
                'coupon_code'         => $coupon?->code,
                'status'              => 'new',
                'subtotal'            => $pricing['subtotal'],
                'discount'            => $pricing['discount'],
                'shipping_method'     => $pricing['shipping_method'],
                'shipping_cost'       => $pricing['shipping_cost'],
                'cod_fee'             => $pricing['cod_fee'],
                'total'               => $pricing['total'],
                'customer_name'       => $data['customer_name'],
                'customer_phone'      => $data['customer_phone'],
                'customer_email'      => $data['customer_email'] ?? null,
                'shipping_address'    => $data['shipping_address'],
                'city'                => $data['city'] ?? null,
                'country'             => $data['country'] ?? null,
                'notes'               => $data['notes'] ?? null,
            ]);

            // 1) تسجيل الحالة الأولى عند الإنشاء
            OrderStatusHistory::create([
                'order_id'        => $order->id,
                'status'          => 'new',
                'previous_status' => null,
                'changed_by'      => $customer->id,
                'note'            => null,
            ]);
          
            $affectedProductIds = [];

            foreach ($lines as $line) {
                $product = $line['product'];
                $variant = $line['variant'];

                $frozenPercentage = null;

                if ($link && $link->influencer_id) {
                    $eligible = $linkProductId === null
                        || (int) $linkProductId === (int) $product->id;

                    if ($eligible && $commissionPercentages->has($product->id)) {
                        $frozenPercentage = self::resolveCommissionPercentage(
                            $commissionPercentages->get($product->id)
                        );
                    }
                }

                $pricingLine = $product->resolvePrice($variant);

                OrderItem::create([
                    'order_id'              => $order->id,
                    'product_id'            => $product->id,
                    'variant_id'            => $variant?->id,
                    'variant_size'          => $variant?->size,
                    'variant_color'         => $variant?->color,
                    'variant_sku'           => $variant?->sku,
                    'product_name'          => $product->localized('name'),
                    'price'                 => $pricingLine['price'],
                    'discount_price'        => $pricingLine['discount_price'],
                    'commission_percentage' => $frozenPercentage,
                    'quantity'              => $line['qty'],
                    'total'                 => $line['lineTotal'],
                ]);

                if ($variant) {
                    $variant->decrement('stock', $line['qty']);
                    $affectedProductIds[$product->id] = true;
                } else {
                    $product->decrement('stock', $line['qty']);
                }
            }

            // مزامنة products.stock مع مجموع variants (للمنتجات اللي عندها variants)
            foreach (array_keys($affectedProductIds) as $productId) {
                $fresh = Product::find($productId);
                if ($fresh) {
                    $this->variantService->syncProductStock($fresh);
                }
            }

            $coupon?->increment('used_count');

            Payment::create([
                'order_id'       => $order->id,
                'payment_method' => $data['payment_method'] ?? config('store.default_payment_method'),
                'amount'         => $pricing['total'],
                'status'         => 'pending',
            ]);

            return $order->load(['items', 'payment']);
        });
    }

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

            // 3) إضافة ملاحظة عند الإلغاء بواسطة العميل
            return $this->updateStatus(
                $order,
                'cancelled',
                note: __('تم إلغاء الطلب بواسطة العميل'),
            )->load(['items', 'payment']);
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

    // 2) تحديث الـ Signature لإضافة الملاحظات
    public function updateStatus(
        Order $order,
        string $status,
        ?string $note = null,
        ?string $internalNote = null
    ): Order {
        $changed = false;
        $commissions = collect();

        $order = DB::transaction(function () use ($order, $status, &$changed, &$commissions, $note, $internalNote) {

            $order = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === $status) {
                return $order;
            }

            $this->assertTransitionAllowed($order->status, $status);

            if (
                in_array($status, ['cancelled', 'returned'], true)
                && $order->payment()->where('status', 'paid')->exists()
            ) {
                throw new \DomainException(
                    'الطلب مدفوع: استرجع الدفعة الأول من شاشة المدفوعات وهيتم إلغاء/إرجاع الطلب تلقائيًا'
                );
            }

            if (
                config('store.require_payment_before_shipping', true)
                && in_array($status, ['shipped', 'delivered'], true)
            ) {
                $payment = $order->payment()->lockForUpdate()->first();

                if (!$payment || $payment->status !== 'paid') {
                    throw new \DomainException('لا يمكن شحن أو تسليم الطلب قبل تأكيد الدفع');
                }
            }

            $previousStatus = $order->status;

            $order->update(['status' => $status]);
            $changed = true;

            // تسجيل تاريخ تغيير الحالة
            OrderStatusHistory::create([
                'order_id'        => $order->id,
                'status'          => $status,
                'previous_status' => $previousStatus,
                'changed_by'      => auth()->id(), // customer أو admin أو null (system)
                'note'            => $note,
                'internal_note'   => $internalNote,
            ]);

            if ($status === 'delivered' && $order->promotional_link_id) {
                PromotionalLink::whereKey($order->promotional_link_id)
                    ->increment('conversions');
            }

            if (
                $status === 'returned'
                && $previousStatus === 'delivered'
                && $order->promotional_link_id
            ) {
                PromotionalLink::whereKey($order->promotional_link_id)
                    ->where('conversions', '>', 0)
                    ->decrement('conversions');
            }

            if (in_array($status, ['cancelled', 'returned'], true)) {
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
            $orderId = $order->id;
            $commissionTotal = (float) $commissions->sum('commission_amount');
            $availableAt = $commissions->where('status', 'pending')->min('available_at');

            DB::afterCommit(function () use ($orderId, $commissions, $commissionTotal, $availableAt) {
                $fresh = Order::with(['customer', 'influencer'])->find($orderId);

                if (!$fresh) {
                    return;
                }

                try {
                    $fresh->customer?->notify(new OrderStatusUpdated($fresh));

                    if ($commissions->isNotEmpty() && $fresh->influencer) {
                        $fresh->influencer->notify(new NewCommission(
                            $fresh->id,
                            $commissionTotal,
                            $availableAt?->toDateTimeString()
                        ));
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return $order;
    }

    /**
     * @return array{0: array, 1: float}
     * @throws \DomainException
     */
    protected function buildLines(array $items, bool $lock): array
    {
        // 1) تجميع بالزوج (product_id, variant_id)
        $combined = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $variantId = isset($item['variant_id']) && $item['variant_id'] !== null
                ? (int) $item['variant_id']
                : null;
            $key = $productId . ':' . ($variantId ?? 'null');

            if (!isset($combined[$key])) {
                $combined[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'qty' => 0];
            }
            $combined[$key]['qty'] += (int) $item['quantity'];
        }
        ksort($combined); // ترتيب ثابت لتفادي deadlock

        // 2) جلب المنتجات مرتبة
        $productIds = array_unique(array_column($combined, 'product_id'));
        sort($productIds);

        $productQuery = Product::whereIn('id', $productIds);
        if ($lock) {
            $productQuery->lockForUpdate();
        }
        $products = $productQuery->get()->keyBy('id');

        // 3) جلب الـ variants مرتبة
        $variantIds = array_values(array_filter(array_column($combined, 'variant_id')));
        sort($variantIds);

        $variants = collect();
        if (!empty($variantIds)) {
            $variantQuery = ProductVariant::whereIn('id', $variantIds);
            if ($lock) {
                $variantQuery->lockForUpdate();
            }
            $variants = $variantQuery->get()->keyBy('id');
        }

        $lines = [];
        $subtotal = 0.0;

        foreach ($combined as $row) {
            $product = $products->get($row['product_id']);
            $variant = $row['variant_id'] ? $variants->get($row['variant_id']) : null;

            if (!$product || !$product->is_active) {
                $label = $product?->localized('name')
                    ?? __('رقم :id', ['id' => $row['product_id']]);

                throw new \DomainException(
                    __('المنتج :label غير متاح', ['label' => $label])
                );
            }

            $hasVariants = ProductVariant::where('product_id', $product->id)->exists();

            if ($hasVariants) {
                if (!$variant) {
                    throw new \DomainException(
                        __('لازم تختار المقاس أو اللون للمنتج ":name"', [
                            'name' => $product->localized('name'),
                        ])
                    );
                }

                if ((int) $variant->product_id !== (int) $product->id) {
                    throw new \DomainException(__('الـ variant المختار لا يتبع هذا المنتج'));
                }

                if (!$variant->is_active) {
                    throw new \DomainException(__('الـ variant المختار غير متاح'));
                }

                $available = (int) $variant->stock;
            } else {
                if ($variant) {
                    throw new \DomainException(__('هذا المنتج ليس له variants'));
                }
                $available = (int) $product->stock;
            }

            if ($available < $row['qty']) {
                throw new \DomainException(
                    __('الكمية المطلوبة من ":name" غير متوفرة (المتاح: :stock)', [
                        'name'  => $product->localized('name'),
                        'stock' => $available,
                    ])
                );
            }

            $pricing = $product->resolvePrice($variant);
            $unitPrice = $pricing['final'];
            $lineTotal = round($unitPrice * $row['qty'], 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product'   => $product,
                'variant'   => $variant,
                'qty'       => $row['qty'],
                'unitPrice' => $unitPrice,
                'lineTotal' => $lineTotal,
            ];
        }

        return [$lines, round($subtotal, 2)];
    }

    protected function resolveLink(
        ?string $ref,
        User $customer,
        array $orderedProductIds = [],
        array $contact = []
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

        if ($this->looksLikeSelfReferral($influencer, $customer, $contact)) {
            return null;
        }

        if (
            $link->product_id !== null
            && !in_array((int) $link->product_id, $orderedProductIds, true)
        ) {
            return null;
        }

        return $link;
    }

    protected function looksLikeSelfReferral(User $influencer, User $customer, array $contact): bool
    {
        $influencerPhone = $this->normalizePhone($influencer->phone);
        $influencerEmail = $influencer->email ? mb_strtolower(trim($influencer->email)) : null;

        $phones = array_filter([
            $this->normalizePhone($customer->phone),
            $this->normalizePhone($contact['phone'] ?? null),
        ]);

        $emails = array_filter([
            $customer->email ? mb_strtolower(trim($customer->email)) : null,
            !empty($contact['email']) ? mb_strtolower(trim($contact['email'])) : null,
        ]);

        return ($influencerPhone && in_array($influencerPhone, $phones, true))
            || ($influencerEmail && in_array($influencerEmail, $emails, true));
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 8 ? substr($digits, -10) : null;
    }

    protected function assertTransitionAllowed(string $from, string $to): void
    {
        if (in_array($from, ['cancelled', 'returned'], true)) {
            throw new \DomainException('لا يمكن تغيير حالة طلب ملغي أو مرتجع');
        }

        $allowed = self::TRANSITIONS[$from] ?? [];

        if (!in_array($to, $allowed, true)) {
            throw new \DomainException(
                __('لا يمكن نقل الطلب من الحالة (:from) إلى (:to)', [
                    'from' => $from,
                    'to'   => $to,
                ])
            );
        }
    }

    protected function restoreStock(Order $order): void
    {
        $affectedProductIds = [];

        foreach ($order->items as $item) {
            if ($item->variant_id) {
                ProductVariant::whereKey($item->variant_id)->increment('stock', $item->quantity);
                if ($item->product_id) {
                    $affectedProductIds[$item->product_id] = true;
                }
            } elseif ($item->product_id) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }
        }

        foreach (array_keys($affectedProductIds) as $productId) {
            $product = Product::find($productId);
            if ($product) {
                $this->variantService->syncProductStock($product);
            }
        }
    }

    protected static function resolveCommissionPercentage(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return InfluencerCommissionService::resolvePercentage(null);
        }

        if (!is_numeric($value)) {
            return null;
        }

        $percentage = (float) $value;

        if ($percentage < 0 || $percentage > 100) {
            return null;
        }

        return round($percentage, 2);
    }

    protected function createOrderRecord(array $attributes): Order
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(fn () => Order::create([
                    ...$attributes,
                    'order_number' => $this->generateOrderNumber(),
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('تعذر إنشاء رقم طلب فريد.');
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(Str::random(8));
        } while (Order::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }
}