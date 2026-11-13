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
use Illuminate\Database\UniqueConstraintViolationException;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\NewCommission;

class OrderService
{
    /** ترتيب الدورة الطبيعية للطلب */
    protected const FLOW = ['new', 'confirmed', 'processing', 'ready_for_shipping', 'shipped', 'delivered'];
        /** الانتقالات المسموحة: من حالة لحالة واحدة تالية بالظبط (أو إلغاء/إرجاع) */
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

            // 1. تجهيز المنتجات وحساب المجموع الفرعي
            [$lines, $subtotal] = $this->buildLines($data['items'], true);

            // 2. حساب الأسعار والخصومات والشحن والكوبون
            $pricing = $this->pricingService->calculate(
                $subtotal,
                $data['coupon_code'] ?? null,
                $data['city'] ?? null,
                $customer,
                true
            );

            $coupon = $pricing['coupon'];

            // 3. استخراج المنتجات الموجودة في الطلب
            $orderedProductIds = array_map(
                fn ($line) => (int) $line['product']->id,
                $lines
            );

            // 4. تحديد رابط الإحالة (مع منع الإحالة الذاتية بالتليفون/الإيميل)
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

            // 5. جلب نسب عمولة المؤثر الفعالة
            $commissionPercentages = collect();

            if ($link && $link->influencer_id) {
                $commissionPercentages = DB::table('influencer_product')
                    ->where('influencer_id', $link->influencer_id)
                    ->where('is_active', true)
                    ->whereIn('product_id', $orderedProductIds)
                    ->pluck('commission_percentage', 'product_id');
            }

            // 6. إنشاء الطلب أولاً
            $order = $this->createOrderRecord([
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

            // 7. إنشاء عناصر الطلب وتجميد عمولة كل منتج
            foreach ($lines as $line) {
                $product = $line['product'];

                $frozenPercentage = null;

                if ($link && $link->influencer_id) {
                    // إذا كان الرابط خاصاً بمنتج معين فلا عمولة على المنتجات الأخرى
                    $eligible = $linkProductId === null
                        || (int) $linkProductId === (int) $product->id;

                    if ($eligible && $commissionPercentages->has($product->id)) {
                        $frozenPercentage = self::resolveCommissionPercentage(
                            $commissionPercentages->get($product->id)
                        );
                    }
                }

                OrderItem::create([
                    'order_id'              => $order->id,
                    'product_id'            => $product->id,
                    'product_name'          => $product->localized('name'),
                    'price'                 => $product->price,
                    'discount_price'        => $product->has_discount
                        ? $product->effective_price
                        : null,
                    'commission_percentage' => $frozenPercentage,
                    'quantity'              => $line['qty'],
                    'total'                 => $line['lineTotal'],
                ]);

                // خصم المخزون مرة واحدة فقط
                $product->decrement('stock', $line['qty']);
            }

            // 8. تحديث عدد استخدامات الكوبون
            $coupon?->increment('used_count');

            // 9. إنشاء سجل الدفع بحالة pending
            Payment::create([
                'order_id'       => $order->id,
                'payment_method' => $data['payment_method']
                    ?? config('store.default_payment_method'),
                'amount'         => $pricing['total'],
                'status'         => 'pending',
            ]);

            // 10. إرجاع الطلب مع المنتجات والدفع
            return $order->load([
                'items',
                'payment',
            ]);
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

            // طلب مدفوع: الإلغاء/الإرجاع لازم يتم عن طريق استرداد الدفع
            if (
                in_array($status, ['cancelled', 'returned'], true)
                && $order->payment()->where('status', 'paid')->exists()
            ) {
                throw new \DomainException(
                    'الطلب مدفوع: استرجع الدفعة الأول من شاشة المدفوعات وهيتم إلغاء/إرجاع الطلب تلقائيًا'
                );
            }

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

            if (in_array($status, ['cancelled', 'returned'], true)) {
                // نرجّع استخدام الكوبون (إلغاء أو إرجاع) ونقفل الدفعات المعلقة
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
            try {
                $order->customer?->notify(new OrderStatusUpdated($order));

                if ($commissions->isNotEmpty() && $order->influencer) {
                    // لو في عمولات معلّقة، نبعت تاريخ إتاحتها في الإشعار
                    $availableAt = $commissions->where('status', 'pending')->min('available_at');

                    $order->influencer->notify(new NewCommission(
                        $order->id,
                        (float) $commissions->sum('commission_amount'),
                        $availableAt?->toDateTimeString()
                    ));
                }
            } catch (\Throwable $e) {
                // تغيير الحالة تم بالفعل؛ فشل الإشعار لا يجب أن يرجع 500 للعميل.
                report($e);
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


            $unitPrice = $product->effective_price;
            $lineTotal = round($unitPrice * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = compact('product', 'qty', 'unitPrice', 'lineTotal');
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

        // المؤثر بيشتري بحساب تاني بنفس التليفون/الإيميل
        if ($this->looksLikeSelfReferral($influencer, $customer, $contact)) {
            return null;
        }

        // الرابط الخاص بمنتج لا يُنسب إلا لطلب يحتوي على هذا المنتج.
        if (
            $link->product_id !== null
            && !in_array((int) $link->product_id, $orderedProductIds, true)
        ) {
            return null;
        }

        return $link;
    }

    /** نفس تليفون/إيميل المؤثر في حساب العميل أو في بيانات الطلب */
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

    /** أرقام بس، وآخر 10 خانات (يتجاوز +20 و0 في الأول) */
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
        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }
        }
    }

    /**
     * نسبة العمولة القادمة من pivot influencer_product.
     * null = غير مؤهل، وأي قيمة خارج 0..100 تُعتبر غير صالحة.
     */
    protected static function resolveCommissionPercentage(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
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
                // transaction متداخلة = savepoint، تسمح بإعادة المحاولة بأمان.
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
