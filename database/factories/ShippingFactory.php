<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipping>
 */
class ShippingFactory extends Factory
{
    protected $model = Shipping::class;

    public function definition(): array
    {
        return [
            'order_id'         => Order::factory(),
            'shipping_company' => 'Aramex',
            'tracking_number'  => 'TRK-' . fake()->unique()->numerify('########'),
            'status'           => 'pending',
            'shipping_address' => fake()->address(),
        ];
    }
}
