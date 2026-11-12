<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipping;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['store.require_payment_before_shipping' => true]);
    }

    public function test_happy_path_syncs_order_status(): void
    {
        [$order, $shipping] = $this->orderWithShipping('ready_for_shipping', 'pending');
        $service = app(ShippingService::class);

        foreach (['picked_up', 'in_transit', 'out_for_delivery', 'delivered'] as $status) {
            $shipping = $service->updateStatus($shipping->fresh(), $status);
        }

        $this->assertSame('delivered', $shipping->status);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNotNull($shipping->fresh()->delivered_at);
    }

    public function test_skipping_statuses_is_rejected(): void
    {
        [, $shipping] = $this->orderWithShipping('ready_for_shipping', 'pending');

        $this->expectException(\DomainException::class);

        app(ShippingService::class)->updateStatus($shipping, 'delivered');
    }

    public function test_returned_shipment_is_terminal(): void
    {
        [, $shipping] = $this->orderWithShipping('shipped', 'returned');

        $this->expectException(\DomainException::class);

        app(ShippingService::class)->updateStatus($shipping, 'pending');
    }

    public function test_delivered_shipment_can_be_returned_after_payment_refund(): void
    {
        [$order, $shipping] = $this->orderWithShipping('delivered', 'delivered');

        // A paid order must be refunded before it can be returned.
        app(\App\Services\PaymentService::class)->markRefunded($order->payment);

        $shipping = app(ShippingService::class)->updateStatus($shipping, 'returned');

        $this->assertSame('returned', $shipping->status);
        $this->assertSame('returned', $order->fresh()->status);
    }

    public function test_webhook_hook_uses_the_same_transition_rules(): void
    {
        [$order, $shipping] = $this->orderWithShipping('ready_for_shipping', 'pending');

        $shipping = app(ShippingService::class)->applyWebhookStatus($shipping, 'picked_up');

        $this->assertSame('picked_up', $shipping->status);
        $this->assertSame('shipped', $order->fresh()->status);
    }

    /**
     * @return array{0: Order, 1: Shipping}
     */
    protected function orderWithShipping(string $orderStatus, string $shippingStatus): array
    {
        $order = Order::factory()->create(['status' => $orderStatus]);

        Payment::factory()->paid('TXN-SHIP-' . $order->id)->create([
            'order_id' => $order->id,
            'amount'   => $order->total,
        ]);

        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'status'   => $shippingStatus,
        ]);

        return [$order, $shipping];
    }
}
