<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.gateways.easykash.secret' => 'test-webhook-secret',
            'payments.gateways.easykash.signature_header' => 'X-EasyKash-Signature',
        ]);
    }

    public function test_valid_webhook_marks_payment_paid_and_confirms_order(): void
    {
        [$order, $payment] = $this->pendingOrder();

        $payload = [
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-TXN-001',
        ];

        $this->signedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('EK-TXN-001', $payment->fresh()->transaction_id);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_repeated_webhook_is_idempotent(): void
    {
        [$order] = $this->pendingOrder();

        $payload = [
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-TXN-002',
        ];

        $this->signedWebhook($payload)->assertOk();
        $this->signedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'already_paid');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $order->fresh()->payment->status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        [$order, $payment] = $this->pendingOrder();

        $payload = [
            'order_number'   => $order->order_number,
            'amount'         => 90,
            'status'         => 'paid',
            'transaction_id' => 'EK-TXN-003',
        ];

        $this->signedWebhook($payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'مبلغ الـ webhook لا يطابق إجمالي الطلب');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('new', $order->fresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        [$order, $payment] = $this->pendingOrder();

        $body = json_encode([
            'order_number'   => $order->order_number,
            'amount'         => 100,
            'status'         => 'paid',
            'transaction_id' => 'EK-TXN-004',
        ]);

        $this->call('POST', '/api/webhooks/payments/easykash', [], [], [], [
            'CONTENT_TYPE'         => 'application/json',
            'HTTP_ACCEPT'          => 'application/json',
            'HTTP_X_EASYKASH_SIGNATURE' => 'invalid-signature',
        ], $body)->assertStatus(401);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_failed_webhook_marks_payment_failed_and_cancels_order(): void
    {
        [$order, $payment] = $this->pendingOrder();

        $payload = [
            'order_number' => $order->order_number,
            'amount'       => 100,
            'status'       => 'failed',
            'message'      => 'Card declined',
        ];

        $this->signedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    protected function pendingOrder(): array
    {
        $order = Order::factory()->create([
            'status' => 'new',
            'total'  => 100,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount'   => 100,
            'status'   => 'pending',
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
