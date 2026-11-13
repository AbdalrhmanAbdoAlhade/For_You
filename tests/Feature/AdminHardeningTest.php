<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromotionalLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_coupon_can_update_expiry_only_without_resending_start_date(): void
    {
        $coupon = Coupon::factory()->create([
            'starts_at'  => now()->addDay(),
            'expires_at' => now()->addDays(3),
        ]);

        $this->patchJson("/api/admin/coupons/{$coupon->id}", [
            'expires_at' => now()->addDays(5)->toDateTimeString(),
        ])
            ->assertOk()
            ->assertJsonPath('id', $coupon->id);

        $this->assertNotNull($coupon->fresh()->expires_at);
    }

    public function test_coupon_expiry_before_start_is_rejected(): void
    {
        $coupon = Coupon::factory()->create([
            'starts_at'  => now()->addDays(5),
            'expires_at' => now()->addDays(10),
        ]);

        $this->patchJson("/api/admin/coupons/{$coupon->id}", [
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_banner_can_update_end_date_only_and_rejects_non_http_links(): void
    {
        $banner = Banner::create([
            'title'      => 'Banner',
            'image'      => 'banners/x.jpg',
            'starts_at'  => now()->addDay(),
            'ends_at'    => now()->addDays(3),
            'is_active'  => true,
        ]);

        $this->patchJson("/api/admin/banners/{$banner->id}", [
            'ends_at' => now()->addDays(6)->toDateTimeString(),
        ])->assertOk();

        $this->patchJson("/api/admin/banners/{$banner->id}", [
            'link_url' => 'ftp://example.com/file',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('link_url');
    }

    public function test_banner_end_date_before_start_is_rejected(): void
    {
        $banner = Banner::create([
            'title'     => 'Banner',
            'image'     => 'banners/x.jpg',
            'starts_at' => now()->addDays(5),
            'ends_at'   => now()->addDays(10),
            'is_active' => true,
        ]);

        $this->patchJson("/api/admin/banners/{$banner->id}", [
            'ends_at' => now()->addDay()->toDateTimeString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_product_slug_stays_stable_on_rename_and_changes_only_when_sent(): void
    {
        $product = Product::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);

        $this->patchJson("/api/admin/products/{$product->id}", ['name' => 'New Name'])
            ->assertOk();

        $this->assertSame('old-name', $product->fresh()->slug);

        $this->patchJson("/api/admin/products/{$product->id}", ['slug' => 'custom-slug'])
            ->assertOk();

        $this->assertSame('custom-slug', $product->fresh()->slug);
    }

    public function test_admin_dashboard_counts_only_delivered_orders_as_sales(): void
    {
        Order::factory()->create(['status' => 'delivered', 'total' => 300]);
        Order::factory()->create(['status' => 'shipped', 'total' => 200]);
        Order::factory()->create(['status' => 'cancelled', 'total' => 500]);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.total_sales', 300);
    }

    public function test_promotional_link_sales_total_counts_only_delivered_orders(): void
    {
        $link = PromotionalLink::factory()->create();

        Order::factory()->create([
            'promotional_link_id' => $link->id,
            'status'              => 'delivered',
            'total'               => 100,
        ]);
        Order::factory()->create([
            'promotional_link_id' => $link->id,
            'status'              => 'shipped',
            'total'               => 50,
        ]);

        $this->getJson('/api/admin/links')
            ->assertOk()
            ->assertJsonPath('data.0.sales_total', 100);
    }

    public function test_provider_id_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create(['provider' => 'google', 'provider_id' => 'g-123']);

        $this->assertArrayNotHasKey('provider_id', $user->toArray());
    }
}
