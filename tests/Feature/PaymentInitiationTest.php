<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_initiate_manual_payment_for_unpaid_order(): void
    {
        [$customer, $order, $payment] = $this->orderForCustomer('manual');

        Sanctum::actingAs($customer);

        $this->postJson("/api/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('payment.gateway', 'manual')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.payment_url', null)
            ->assertJsonStructure([
                'message',
                'payment' => [
                    'order_id',
                    'order_number',
                    'payment_id',
                    'gateway',
                    'status',
                    'transaction_reference',
                    'instructions',
                    'amount',
                ],
            ]);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('manual', $payment->fresh()->payment_method);
        $this->assertNotNull($payment->fresh()->gateway_response['initiated_reference']);
    }

    public function test_customer_can_switch_to_easykash_and_receive_payment_url(): void
    {
        config([
            'payments.gateways.easykash.initiate_url' => 'https://sandbox.easykash.test/pay',
            'payments.gateways.easykash.merchant_id'  => 'merchant-1',
        ]);

        [$customer, $order, $payment] = $this->orderForCustomer('manual');

        Sanctum::actingAs($customer);

        $response = $this->postJson("/api/orders/{$order->id}/pay", [
            'gateway' => 'easykash',
        ])->assertOk()
            ->assertJsonPath('payment.gateway', 'easykash');

        $paymentUrl = $response->json('payment.payment_url');

        $this->assertIsString($paymentUrl);
        $this->assertStringStartsWith('https://sandbox.easykash.test/pay?', $paymentUrl);
        $this->assertStringContainsString(rawurlencode($order->order_number), $paymentUrl);
        $this->assertSame('easykash', $payment->fresh()->payment_method);
    }

    public function test_paid_order_cannot_be_paid_again(): void
    {
        [$customer, $order] = $this->orderForCustomer('manual', 'paid');

        Sanctum::actingAs($customer);

        $this->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(422)
            ->assertJsonPath('message', 'هذا الطلب مدفوع بالفعل');
    }

    public function test_failed_payment_can_be_reinitiated(): void
    {
        [$customer, $order, $payment] = $this->orderForCustomer('easykash', 'failed');

        Sanctum::actingAs($customer);

        $this->postJson("/api/orders/{$order->id}/pay", ['gateway' => 'easykash'])
            ->assertOk()
            ->assertJsonPath('payment.gateway', 'easykash');

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_customer_cannot_pay_another_customers_order(): void
    {
        [$customer, $order] = $this->orderForCustomer('manual');
        $other = User::factory()->customer()->create();

        Sanctum::actingAs($other);

        $this->postJson("/api/orders/{$order->id}/pay")->assertNotFound();
    }

    public function test_unsupported_gateway_is_rejected(): void
    {
        [$customer, $order] = $this->orderForCustomer('manual');

        Sanctum::actingAs($customer);

        $this->postJson("/api/orders/{$order->id}/pay", ['gateway' => 'unknown'])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Order, 2: Payment}
     */
    protected function orderForCustomer(string $method, string $paymentStatus = 'pending'): array
    {
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status'      => 'new',
            'total'       => 100,
        ]);

        $payment = Payment::factory()->create([
            'order_id'       => $order->id,
            'payment_method' => $method,
            'amount'         => 100,
            'status'         => $paymentStatus,
        ]);

        return [$customer, $order, $payment];
    }
}
