<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_payload_is_cached_and_flushed_by_admin_product_changes(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        Product::factory()->create([
            'category_id' => $category->id,
            'is_active'   => true,
            'stock'       => 5,
        ]);

        $initial = count($this->getJson('/api/home')->assertOk()->json('new_arrivals'));

        // إنشاء مباشر في قاعدة البيانات لا يمسح الكاش.
        Product::factory()->create([
            'category_id' => $category->id,
            'is_active'   => true,
            'stock'       => 5,
        ]);

        $stale = count($this->getJson('/api/home')->json('new_arrivals'));
        $this->assertSame($initial, $stale);

        // تعديل إداري للمنتجات لازم يمسح الكاش.
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name'        => 'Fresh Product',
            'price'       => 50,
            'stock'       => 5,
            'is_active'   => true,
        ])->assertCreated();

        $fresh = count($this->getJson('/api/home')->json('new_arrivals'));
        $this->assertGreaterThan($stale, $fresh);
    }
}
