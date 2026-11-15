<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id'       => Category::factory(),
            'name'              => $name,
            'slug'              => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'description'       => fake()->sentence(),
            'short_description' => fake()->sentence(),
            'price'             => 100,
            'discount_price'    => null,
            'stock'             => 100,
            'sku'               => 'SKU-' . Str::upper(Str::random(8)),
            'images'            => [],
            'is_featured'       => false,
            'is_active'         => true,
        ];
    }

    public function discounted(float $price = 80): static
    {
        return $this->state(fn () => ['discount_price' => $price]);
    }
}
