<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_idempotency_key_returns_same_order_without_duplicating(): void
    {
        $user = User::factory()->customer()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        Sanctum::actingAs($user);

        $payload = [
            'items'            => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name'    => 'Buyer',
            'customer_phone'   => '01012345678',
            'shipping_address' => 'Cairo',
        ];

        $headers = ['Idempotency-Key' => 'order-key-123'];

        $first = $this->postJson('/api/orders', $payload, $headers)->assertCreated();
        $orderId = $first->json('id');

        $second = $this->postJson('/api/orders', $payload, $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($orderId, $second->json('id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_same_key_with_different_payload_is_rejected(): void
    {
        $user = User::factory()->customer()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        Sanctum::actingAs($user);

        $base = [
            'items'            => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name'    => 'Buyer',
            'customer_phone'   => '01012345678',
            'shipping_address' => 'Cairo',
        ];

        $headers = ['Idempotency-Key' => 'order-key-456'];

        $this->withHeaders(['Accept-Language' => 'ar']);
        $this->postJson('/api/orders', $base, $headers)->assertCreated();

        $this->postJson('/api/orders', array_merge($base, [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]), $headers)->assertStatus(422)
            ->assertJsonPath('message', 'نفس الـ Idempotency-Key اتستخدم مع بيانات مختلفة');
    }

    public function test_order_creation_requires_idempotency_key(): void
    {
        $user = User::factory()->customer()->create();
        $product = Product::factory()->create(['stock' => 10]);

        Sanctum::actingAs($user);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/orders', [
                'items'            => [['product_id' => $product->id, 'quantity' => 1]],
                'customer_name'    => 'Buyer',
                'customer_phone'   => '01012345678',
                'shipping_address' => 'Cairo',
            ])->assertStatus(422)
            ->assertJsonPath('message', 'الهيدر Idempotency-Key مطلوب');
    }
}
