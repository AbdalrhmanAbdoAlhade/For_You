<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryProductGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/categories/{$parent->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_category_parent_cycle_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Category::factory()->create();
        $b = Category::factory()->create(['parent_id' => $a->id]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/categories/{$a->id}", [
            'parent_id' => $b->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_public_products_per_page_is_capped_at_50(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(55)->create(['category_id' => $category->id]);

        $response = $this->getJson('/api/products?per_page=100')->assertOk();

        $response->assertJsonPath('meta.per_page', 50);
        $this->assertCount(50, $response->json('data'));
    }

    public function test_stock_and_sku_are_hidden_from_customers(): void
    {
        $product = Product::factory()->create([
            'stock' => 7,
            'sku'   => 'SECRET-SKU',
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.stock')
            ->assertJsonMissingPath('data.sku')
            ->assertJsonPath('data.in_stock', true);
    }

    public function test_stock_and_sku_are_visible_to_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'stock' => 7,
            'sku'   => 'STAFF-SKU',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.stock', 7)
            ->assertJsonPath('data.sku', 'STAFF-SKU');
    }
}
