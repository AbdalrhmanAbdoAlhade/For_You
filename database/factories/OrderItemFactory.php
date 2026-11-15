<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = 100;

        return [
            'order_id'              => Order::factory(),
            'product_id'            => Product::factory(),
            'product_name'          => fake()->words(3, true),
            'price'                 => $price,
            'discount_price'        => null,
            'quantity'              => 1,
            'total'                 => $price,
            'commission_percentage' => null,
        ];
    }
}
