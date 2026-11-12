<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_only_delivered_orders_as_sales(): void
    {
        $admin = User::factory()->admin()->create();

        Order::factory()->create(['status' => 'delivered', 'total' => 300]);
        Order::factory()->create(['status' => 'shipped', 'total' => 200]);
        Order::factory()->create(['status' => 'cancelled', 'total' => 500]);
        Order::factory()->create(['status' => 'returned', 'total' => 400]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports/overview')
            ->assertOk()
            ->assertJsonPath('total_sales', 300)
            ->assertJsonPath('total_orders', 4);
    }

    public function test_sales_report_uses_sale_statuses_and_timezone(): void
    {
        $admin = User::factory()->admin()->create();

        Order::factory()->create([
            'status'     => 'delivered',
            'total'      => 250,
            'created_at' => '2026-02-10 12:00:00',
        ]);
        Order::factory()->create([
            'status'     => 'cancelled',
            'total'      => 999,
            'created_at' => '2026-02-10 13:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reports/sales?from=2026-02-01&to=2026-02-28')
            ->assertOk()
            ->assertJsonPath('summary.total_orders', 1)
            ->assertJsonPath('summary.total_sales', 250);

        $this->assertSame('2026-02-10', $response->json('sales.0.date'));
    }

    public function test_top_products_excludes_cancelled_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Report Product']);

        $delivered = Order::factory()->create(['status' => 'delivered']);
        OrderItem::factory()->create([
            'order_id'     => $delivered->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'quantity'     => 3,
            'total'        => 300,
        ]);

        $cancelled = Order::factory()->create(['status' => 'cancelled']);
        OrderItem::factory()->create([
            'order_id'     => $cancelled->id,
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'quantity'     => 10,
            'total'        => 1000,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports/top-products')
            ->assertOk()
            ->assertJsonPath('0.product_id', $product->id)
            ->assertJsonPath('0.sold_quantity', 3)
            ->assertJsonPath('0.total_revenue', 300);
    }
}
