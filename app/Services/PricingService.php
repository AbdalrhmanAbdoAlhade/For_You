<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class PricingService
{
    /**
     * @return array{subtotal: float, discount: float, shipping_cost: float, total: float, coupon: ?Coupon}
     * @throws \DomainException لو الكوبون مش صالح
     */
    public function calculate(float $subtotal, ?string $couponCode, ?string $city, User $customer, bool $lock = false): array
    {
        $coupon   = $this->resolveCoupon($couponCode, $subtotal, $customer, $lock);
        $discount = $this->discountFor($coupon, $subtotal);
        $shipping = $this->shippingCost($subtotal - $discount, $city);

        return [
            'subtotal'      => round($subtotal, 2),
            'discount'      => $discount,
            'shipping_cost' => $shipping,
            'total'         => round($subtotal - $discount + $shipping, 2),
            'coupon'        => $coupon,
        ];
    }

    public function shippingCost(float $amountAfterDiscount, ?string $city): float
    {
        $freeOver = config('store.shipping.free_over');
        if ($freeOver !== null && $amountAfterDiscount >= $freeOver) {
            return 0.0;
        }

        $rates = config('store.shipping.city_rates', []);
        if ($city && isset($rates[$city])) {
            return round((float) $rates[$city], 2);
        }

        return round((float) config('store.shipping.flat_rate', 0), 2);
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
