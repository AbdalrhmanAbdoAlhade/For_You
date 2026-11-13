<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateways\EasyKashGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EasyKashGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_returns_a_signed_payment_url(): void
    {
        config([
            'payments.gateways.easykash.initiate_url'    => 'https://pay.easykash.test/checkout',
            'payments.gateways.easykash.merchant_id'     => 'MERCHANT-1',
            'payments.gateways.easykash.initiate_secret' => 'url-secret',
        ]);

        $order = Order::factory()->create(['total' => 100]);

        $payment = Payment::factory()->create([
            'order_id'       => $order->id,
            'payment_method' => 'easykash',
            'amount'         => 100,
        ]);

        $order->setRelation('payment', $payment);

        $result = (new EasyKashGateway())->initiate($order);

        $this->assertNotNull($result['payment_url']);
        $this->assertStringStartsWith('https://pay.easykash.test/checkout?', $result['payment_url']);

        parse_str((string) parse_url($result['payment_url'], PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('signature', $query);
        $this->assertArrayHasKey('timestamp', $query);
        $this->assertSame('100.00', $query['amount']);
        $this->assertSame('MERCHANT-1', $query['merchant_id']);
    }
}
