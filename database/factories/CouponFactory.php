<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'             => 'CPN-' . Str::upper(Str::random(6)),
            'type'             => 'fixed',
            'value'            => 10,
            'min_order_amount' => null,
            'max_discount'     => null,
            'usage_limit'      => null,
            'per_user_limit'   => null,
            'used_count'       => 0,
            'is_active'        => true,
        ];
    }

    public function percent(float $value = 10): static
    {
        return $this->state(fn () => ['type' => 'percent', 'value' => $value]);
    }

    public function fixed(float $value = 10): static
    {
        return $this->state(fn () => ['type' => 'fixed', 'value' => $value]);
    }
}
