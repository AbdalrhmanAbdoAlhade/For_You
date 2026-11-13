<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookLateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.gateways.easykash.secret' => 'test-webhook-secret',
            'payments.gateways.easykash.signature_header' => 'X-EasyKash-Signature',
            'payments.gateways.easykash.webhook_tolerance' => 0,
        ]);
    }

    public function test_paid_webhook_for_cancelled_order_is_recorded_for_refund(): void
    {
        [$order, $payment] = $this->orderWithPayment('cancelled');

        $this->signedWebhook([
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-LATE-001',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'needs_refund');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.webhook.late_paid',
        ]);
    }

    public function test_paid_webhook_for_returned_order_is_recorded_for_refund(): void
    {
        [$order, $payment] = $this->orderWithPayment('returned');

        $this->signedWebhook([
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-LATE-002',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'needs_refund');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('returned', $order->fresh()->status);
    }

    public function test_stale_webhook_timestamp_is_rejected_when_tolerance_enabled(): void
    {
        config(['payments.gateways.easykash.webhook_tolerance' => 300]);

        [$order, $payment] = $this->orderWithPayment('new');

        $this->signedWebhook([
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-REPLAY-001',
            'timestamp'      => now()->subHour()->timestamp,
        ])->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('new', $order->fresh()->status);
    }

    public function test_fresh_webhook_timestamp_is_accepted_when_tolerance_enabled(): void
    {
        config(['payments.gateways.easykash.webhook_tolerance' => 300]);

        [$order, $payment] = $this->orderWithPayment('new');

        $this->signedWebhook([
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-REPLAY-002',
            'timestamp'      => now()->timestamp,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    protected function orderWithPayment(string $status): array
    {
        $order = Order::factory()->create([
            'status' => $status,
            'total'  => 100,
        ]);

        $payment = Payment::factory()->create([
            'order_id'       => $order->id,
            'payment_method' => 'easykash',
            'amount'         => 100,
            'status'         => 'pending',
        ]);

        return [$order, $payment];
    }

    protected function signedWebhook(array $payload)
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-webhook-secret');

        return $this->call('POST', '/api/webhooks/payments/easykash', [], [], [], [
            'CONTENT_TYPE'              => 'application/json',
            'HTTP_ACCEPT'               => 'application/json',
            'HTTP_ACCEPT_LANGUAGE'      => 'ar',
            'HTTP_X_EASYKASH_SIGNATURE' => $signature,
        ], $body);
    }
}
