<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipping;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingReturnPaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['store.require_payment_before_shipping' => true]);
    }

    public function test_returning_paid_shipment_is_accepted_and_flagged_for_refund(): void
    {
        $order = Order::factory()->create(['status' => 'shipped', 'total' => 100]);

        Payment::factory()->paid('TXN-RET-001')->create([
            'order_id' => $order->id,
            'amount'   => 100,
        ]);

        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'status'   => 'out_for_delivery',
        ]);

        $shipping = app(ShippingService::class)->updateStatus($shipping, 'returned');

        // الشحنة اتحدثت، والطلب بيفضل زي ما هو لحد استرداد الدفعة.
        $this->assertSame('returned', $shipping->status);
        $this->assertSame('shipped', $order->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action'       => 'shipping.returned.needs_refund',
            'subject_type' => 'Order',
            'subject_id'   => $order->id,
        ]);
    }

    public function test_returning_unpaid_shipment_still_returns_the_order(): void
    {
        $order = Order::factory()->create(['status' => 'shipped', 'total' => 100]);

        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'status'   => 'out_for_delivery',
        ]);

        $shipping = app(ShippingService::class)->updateStatus($shipping, 'returned');

        $this->assertSame('returned', $shipping->status);
        $this->assertSame('returned', $order->fresh()->status);
    }
}
