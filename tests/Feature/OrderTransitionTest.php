<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['store.require_payment_before_shipping' => true]);
    }

    public function test_full_happy_path_from_new_to_delivered(): void
    {
        $order = $this->orderWithPayment('new');
        $service = app(OrderService::class);

        app(PaymentService::class)->markPaid($order->payment, 'TXN-ORDER-1');

        foreach (['processing', 'ready_for_shipping', 'shipped', 'delivered'] as $status) {
            $order = $service->updateStatus($order->fresh(), $status);
        }

        $this->assertSame('delivered', $order->status);
    }

    public function test_new_to_delivered_directly_is_rejected(): void
    {
        $order = $this->orderWithPayment('new');

        $this->expectException(\DomainException::class);

        app(OrderService::class)->updateStatus($order, 'delivered');
    }

    public function test_cancelled_and_returned_are_terminal(): void
    {
        $cancelled = Order::factory()->create(['status' => 'cancelled']);
        $returned = Order::factory()->create(['status' => 'returned']);

        foreach ([$cancelled, $returned] as $order) {
            try {
                app(OrderService::class)->updateStatus($order, 'confirmed');
                $this->fail('Expected DomainException for terminal order');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('لا يمكن تغيير حالة', $e->getMessage());
            }
        }
    }

    public function test_shipping_before_payment_is_rejected_when_enabled(): void
    {
        $order = $this->orderWithPayment('ready_for_shipping', 'pending');

        $this->expectException(\DomainException::class);

        app(OrderService::class)->updateStatus($order, 'shipped');
    }

    public function test_delivery_before_payment_is_rejected_when_enabled(): void
    {
        $order = $this->orderWithPayment('shipped', 'pending');

        $this->expectException(\DomainException::class);

        app(OrderService::class)->updateStatus($order, 'delivered');
    }

    public function test_customer_cannot_cancel_paid_order_without_refund(): void
    {
        $order = $this->orderWithPayment('new', 'paid');

        $this->expectException(\DomainException::class);

        app(OrderService::class)->cancelByCustomer($order->customer, $order->id);
    }

    public function test_admin_cannot_cancel_paid_order_without_refund(): void
    {
        $order = $this->orderWithPayment('confirmed', 'paid');

        $this->expectException(\DomainException::class);

        app(OrderService::class)->updateStatus($order, 'cancelled');
    }

    public function test_customer_can_cancel_unpaid_new_order_and_stock_is_restored(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $customer = User::factory()->customer()->create();

        $order = app(OrderService::class)->createOrder($customer, [
            'items'            => [['product_id' => $product->id, 'quantity' => 2]],
            'customer_name'    => 'Buyer',
            'customer_phone'   => '01012345678',
            'shipping_address' => 'Cairo',
        ]);

        $this->assertSame(8, $product->fresh()->stock);

        app(OrderService::class)->cancelByCustomer($customer, $order->id);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
    }

    protected function orderWithPayment(string $status, string $paymentStatus = 'pending'): Order
    {
        $order = Order::factory()->create(['status' => $status]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount'   => $order->total,
            'status'   => $paymentStatus,
            'transaction_id' => $paymentStatus === 'paid' ? 'TXN-' . $order->id : null,
            'paid_at'  => $paymentStatus === 'paid' ? now() : null,
        ]);

        return $order->load('payment');
    }
}
