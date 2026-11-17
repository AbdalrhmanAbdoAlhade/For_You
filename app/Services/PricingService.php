<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class PricingService
{
    public function calculate(
    float $subtotal,
    ?string $couponCode,
    ?string $city,
    User $customer,
    bool $lock = false,
    ?string $shippingMethod = null,
    ?string $paymentMethod = null
): array {
    $coupon   = $this->resolveCoupon($couponCode, $subtotal, $customer, $lock);
    $discount = $this->discountFor($coupon, $subtotal);
    $shipping = $this->shippingCost($subtotal - $discount, $city, $shippingMethod);
    $codFee   = $this->codFee($paymentMethod);

    $total = round($subtotal - $discount + $shipping + $codFee, 2);

    return [
        'subtotal'        => round($subtotal, 2),
        'discount'        => $discount,
        'shipping_method' => $shippingMethod,
        'shipping_cost'   => $shipping,
        'cod_fee'         => $codFee,
        'total'           => $total,
        'coupon'          => $coupon,
    ];
}

/**
 * تكلفة الشحن.
 * - لو shipping_method محدد: methods[$method]['cost'] (مع free_over)
 * - لو null: السلوك القديم (free_over > city_rates > flat_rate) للتوافق
 */
public function shippingCost(float $amountAfterDiscount, ?string $city, ?string $shippingMethod = null): float
{
    $freeOver = config('store.shipping.free_over');
    if ($freeOver !== null && $amountAfterDiscount >= $freeOver) {
        return 0.0;
    }

    if ($shippingMethod !== null) {
        $methods = config('store.shipping.methods', []);
        if (!isset($methods[$shippingMethod])) {
            throw new \DomainException(__('طريقة الشحن غير صالحة'));
        }
        return round((float) ($methods[$shippingMethod]['cost'] ?? 0), 2);
    }

    // backward compat
    $rates = config('store.shipping.city_rates', []);
    if ($city && isset($rates[$city])) {
        return round((float) $rates[$city], 2);
    }

    return round((float) config('store.shipping.flat_rate', 0), 2);
}

/**
 * رسوم COD — تتفعل فقط لو payment_method يطابق cod_payment_method (default: manual)
 */
public function codFee(?string $paymentMethod): float
{
    if ($paymentMethod === null) {
        return 0.0;
    }

    $codMethod = (string) config('store.shipping.cod_payment_method', 'manual');
    if ($paymentMethod !== $codMethod) {
        return 0.0;
    }

    return round((float) config('store.shipping.cod_fee', 0), 2);
}

    public function resolveCoupon(?string $code, float $subtotal, User $customer, bool $lock = false): ?Coupon
    {
        if (!$code) {
            return null;
        }

        $query = Coupon::where('code', strtoupper(trim($code)));
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();

        if (!$coupon || !$coupon->is_active) {
            throw new \DomainException('كود الخصم غير صالح');
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new \DomainException('كود الخصم لم يبدأ بعد');
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw new \DomainException('كود الخصم منتهي');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new \DomainException('تم استنفاد كود الخصم');
        }
        
if (
    $coupon->min_order_amount !== null
    && $subtotal < (float) $coupon->min_order_amount
) {
    throw new \DomainException(
        __('الحد الأدنى للطلب لاستخدام الكود هو :amount', [
            'amount' => $coupon->min_order_amount,
        ])
    );
}

        if ($coupon->per_user_limit !== null) {
            $used = Order::where('customer_id', $customer->id)
                ->where('coupon_id', $coupon->id)
                ->whereNotIn('status', Order::NON_SALE_STATUSES)
                ->count();

            if ($used >= $coupon->per_user_limit) {
                throw new \DomainException('لقد استخدمت هذا الكود من قبل');
            }
        }

        return $coupon;
    }

    public function discountFor(?Coupon $coupon, float $subtotal): float
    {
        if (!$coupon) {
            return 0.0;
        }

        $discount = $coupon->type === 'percent'
            ? $subtotal * (float) $coupon->value / 100
            : (float) $coupon->value;

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
