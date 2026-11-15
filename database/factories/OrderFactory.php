<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number'     => 'ORD-' . Str::upper(Str::random(8)),
            'customer_id'      => User::factory(),
            'influencer_id'    => null,
            'status'           => 'new',
            'subtotal'         => 100,
            'discount'         => 0,
            'shipping_cost'    => 0,
            'total'            => 100,
            'customer_name'    => fake()->name(),
            'customer_phone'   => fake()->unique()->numerify('010########'),
            'customer_email'   => fake()->safeEmail(),
            'shipping_address' => fake()->address(),
            'city'             => 'Cairo',
            'country'          => 'EG',
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => 'delivered']);
    }
}
