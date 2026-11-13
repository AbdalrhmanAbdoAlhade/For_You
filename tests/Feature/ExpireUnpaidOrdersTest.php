<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'store.unpaid_order_ttl_hours' => 24,
            'store.expire_unpaid_payment_methods' => ['easykash'],
        ]);
    }

    public function test_manual_orders_are_not_auto_cancelled(): void
    {
        $order = Order::factory()->create([
            'status'     => 'new',
            'created_at' => now()->subDays(2),
        ]);

        Payment::factory()->create([
            'order_id'       => $order->id,
            'payment_method' => 'manual',
            'status'         => 'pending',
        ]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('new', $order->fresh()->status);
    }

    public function test_eligible_unpaid_orders_are_cancelled_and_audited(): void
    {
        $order = Order::factory()->create([
            'status'     => 'new',
            'created_at' => now()->subDays(2),
        ]);

        Payment::factory()->create([
            'order_id'       => $order->id,
            'payment_method' => 'easykash',
            'status'         => 'pending',
        ]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'order.auto_cancelled',
            'subject_type' => 'Order',
            'subject_id'   => $order->id,
        ]);
    }

    public function test_paid_orders_are_never_auto_cancelled(): void
    {
        $order = Order::factory()->create([
            'status'     => 'new',
            'created_at' => now()->subDays(2),
        ]);

        Payment::factory()->paid('TXN-EXPIRE-001')->create([
            'order_id'       => $order->id,
            'payment_method' => 'easykash',
            'amount'         => $order->total,
        ]);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('new', $order->fresh()->status);
    }
}
