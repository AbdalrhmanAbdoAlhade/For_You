<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_show_hides_internal_attribution_and_gateway_payload(): void
    {
        $customer = User::factory()->customer()->create();
        $influencer = User::factory()->influencer()->create();

        $order = Order::factory()->create([
            'customer_id'         => $customer->id,
            'influencer_id'       => $influencer->id,
            'promotional_link_id' => null,
        ]);

        Payment::factory()->create([
            'order_id'         => $order->id,
            'gateway_response' => ['secret_payload' => 'must-not-leak'],
        ]);

        Sanctum::actingAs($customer);

        $json = $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('influencer_id', $json);
        $this->assertArrayNotHasKey('promotional_link_id', $json);
        $this->assertArrayNotHasKey('gateway_response', $json['payment']);
    }

    public function test_order_list_uses_the_safe_resource_but_keeps_pagination_shape(): void
    {
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'   => $customer->id,
            'influencer_id' => User::factory()->influencer()->create()->id,
        ]);

        Payment::factory()->create(['order_id' => $order->id]);

        Sanctum::actingAs($customer);

        $json = $this->getJson('/api/orders')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('current_page', $json);
        $this->assertArrayNotHasKey('influencer_id', $json['data'][0]);
        $this->assertArrayNotHasKey('promotional_link_id', $json['data'][0]);
        $this->assertArrayNotHasKey('gateway_response', $json['data'][0]['payment']);
    }
}
