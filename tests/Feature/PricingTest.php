<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\PromotionalLink;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'store.shipping.flat_rate' => 0,
            'store.shipping.free_over' => null,
            'services.influencer.commission_hold_days' => 0,
        ]);
    }

    public function test_valid_discount_price_becomes_final_price(): void
    {
        $product = Product::factory()->create([
            'price'          => 100,
            'discount_price' => 80,
        ]);

        $this->assertSame(80.0, $product->effective_price);
        $this->assertSame(80.0, $product->final_price);
        $this->assertTrue($product->has_discount);
    }

    public function test_discount_greater_or_equal_to_price_is_ignored(): void
    {
        $equal = Product::factory()->create(['price' => 100, 'discount_price' => 100]);
        $higher = Product::factory()->create(['price' => 100, 'discount_price' => 120]);
        $zero = Product::factory()->create(['price' => 100, 'discount_price' => 0]);
        $negative = Product::factory()->create(['price' => 100, 'discount_price' => -5]);

        foreach ([$equal, $higher, $zero, $negative] as $product) {
            $this->assertSame(100.0, $product->effective_price);
            $this->assertFalse($product->has_discount);
        }
    }

    public function test_fixed_coupon_is_allocated_to_items_in_piasters_before_commission(): void
    {
        [$influencer, $link, $customer, $a, $b, $coupon] = $this->commissionScenario('fixed', 10);

        $order = $this->createOrder($customer, [$a, $b], $coupon, $link);

        $this->assertSame(150.0, (float) $order->subtotal);
        $this->assertSame(10.0, (float) $order->discount);
        $this->assertSame(140.0, (float) $order->total);

        $commissions = $this->deliverAndGetCommissions($order);

        // 10 ج.م على 100 و50: 6.67 و3.33 (الباقي قرش واحد على الصنف الأكبر)
        $this->assertEqualsWithDelta(9.33, (float) $commissions->firstWhere('product_id', $a->id)->commission_amount, 0.001);
        $this->assertEqualsWithDelta(9.33, (float) $commissions->firstWhere('product_id', $b->id)->commission_amount, 0.001);
        $this->assertEqualsWithDelta(18.66, (float) $commissions->sum('commission_amount'), 0.001);
    }

    public function test_percent_coupon_is_allocated_proportionally(): void
    {
        [$influencer, $link, $customer, $a, $b, $coupon] = $this->commissionScenario('percent', 10);

        $order = $this->createOrder($customer, [$a, $b], $coupon, $link);

        $this->assertSame(15.0, (float) $order->discount);

        $commissions = $this->deliverAndGetCommissions($order);

        // 15% من 100 = 15، و10% من 50 = 5
        $this->assertEqualsWithDelta(9.00, (float) $commissions->firstWhere('product_id', $a->id)->commission_amount, 0.001);
        $this->assertEqualsWithDelta(9.00, (float) $commissions->firstWhere('product_id', $b->id)->commission_amount, 0.001);
    }

    public function test_cart_quote_and_checkout_calculate_the_same_total(): void
    {
        $user = User::factory()->customer()->create();
        $product = Product::factory()->create([
            'price'          => 100,
            'discount_price' => 80,
            'stock'          => 20,
        ]);

        app(CartService::class)->addItem($user, $product->id, 2);

        Sanctum::actingAs($user);

        $quote = $this->postJson('/api/checkout/quote', [])
            ->assertOk()
            ->json();

        $checkout = $this->postJson('/api/checkout', [
            'customer_name'    => 'Test Customer',
            'customer_phone'   => '01012345678',
            'shipping_address' => 'Cairo, Egypt',
            'city'             => 'Cairo',
        ])->assertCreated()->json();

        $this->assertSame(160.0, (float) $quote['total']);
        $this->assertSame((float) $quote['subtotal'], (float) $checkout['subtotal']);
        $this->assertSame((float) $quote['total'], (float) $checkout['total']);
    }

    /**
     * @return array{0: User, 1: PromotionalLink, 2: User, 3: Product, 4: Product, 5: Coupon}
     */
    protected function commissionScenario(string $couponType, float $couponValue): array
    {
        $influencer = User::factory()->influencer()->create([
            'phone' => '01111111111',
            'email' => 'influencer@example.com',
        ]);

        $a = Product::factory()->create(['price' => 100, 'stock' => 50]);
        $b = Product::factory()->create(['price' => 50, 'stock' => 50]);

        $influencer->influencerProducts()->attach($a->id, [
            'commission_percentage' => 10,
            'is_active'             => true,
        ]);
        $influencer->influencerProducts()->attach($b->id, [
            'commission_percentage' => 20,
            'is_active'             => true,
        ]);

        $link = PromotionalLink::factory()->create([
            'influencer_id' => $influencer->id,
            'product_id'    => null,
        ]);

        $customer = User::factory()->customer()->create([
            'phone' => '01222222222',
            'email' => 'customer@example.com',
        ]);

        $coupon = Coupon::factory()->create([
            'type'  => $couponType,
            'value' => $couponValue,
        ]);

        return [$influencer, $link, $customer, $a, $b, $coupon];
    }

    protected function createOrder(User $customer, array $products, Coupon $coupon, PromotionalLink $link)
    {
        return app(OrderService::class)->createOrder($customer, [
            'items' => array_map(fn ($p) => [
                'product_id' => $p->id,
                'quantity'   => 1,
            ], $products),
            'coupon_code'      => $coupon->code,
            'ref'              => $link->code,
            'customer_name'    => 'Test Customer',
            'customer_phone'   => '01012345678',
            'customer_email'   => 'buyer@example.com',
            'shipping_address' => 'Cairo, Egypt',
            'city'             => 'Cairo',
        ]);
    }

    protected function deliverAndGetCommissions($order)
    {
        $payments = app(PaymentService::class);
        $orders = app(OrderService::class);

        $payments->markPaid($order->payment, 'TXN-PRICING-1');

        foreach (['processing', 'ready_for_shipping', 'shipped', 'delivered'] as $status) {
            $order = $orders->updateStatus($order->fresh(), $status);
        }

        return $order->commissions()->get();
    }
}
