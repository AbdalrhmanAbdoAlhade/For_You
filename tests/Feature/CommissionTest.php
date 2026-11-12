<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\InfluencerCommission;
use App\Models\InfluencerWallet;
use App\Models\Product;
use App\Models\PromotionalLink;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
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

    public function test_commission_percentage_is_frozen_on_order_creation(): void
    {
        [$influencer, $product, $link, $customer] = $this->scenario(15);

        $order = $this->createOrder($customer, $product, $link);

        $item = $order->items()->first();
        $this->assertSame(15.0, (float) $item->commission_percentage);

        // تغيير نسبة المؤثر بعد إنشاء الطلب لا يجب أن يؤثر
        $influencer->influencerProducts()->updateExistingPivot($product->id, [
            'commission_percentage' => 50,
        ]);

        $commissions = $this->deliver($order);

        $this->assertCount(1, $commissions);
        $this->assertSame(15.0, (float) $commissions->first()->commission_percentage);
        $this->assertSame(15.0, (float) $commissions->first()->commission_amount);
    }

    public function test_commission_is_calculated_on_net_item_total_after_coupon_share(): void
    {
        [$influencer, $product, $link, $customer] = $this->scenario(10);
        $coupon = Coupon::factory()->fixed(20)->create();

        $order = $this->createOrder($customer, $product, $link, $coupon);

        $this->assertSame(20.0, (float) $order->discount);

        $commissions = $this->deliver($order);

        // 100 - 20 = 80 صافي، والعمولة 10% = 8
        $this->assertSame(8.0, (float) $commissions->first()->commission_amount);
        $this->assertSame(80.0, (float) $commissions->first()->order_total);
    }

    public function test_returning_a_delivered_order_reverses_approved_commission(): void
    {
        [$influencer, $product, $link, $customer] = $this->scenario(10);

        $order = $this->createOrder($customer, $product, $link);
        $commissions = $this->deliver($order);

        $wallet = InfluencerWallet::where('influencer_id', $influencer->id)->firstOrFail();
        $this->assertSame(10.0, (float) $wallet->balance);
        $this->assertSame('approved', $commissions->first()->status);

        app(PaymentService::class)->markRefunded($order->payment()->firstOrFail());

        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame('cancelled', $commissions->first()->fresh()->status);
        $this->assertSame(0.0, (float) $wallet->fresh()->balance);
        $this->assertSame(0.0, (float) $wallet->fresh()->total_earned);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type'      => 'debit',
            'amount'    => 10.00,
        ]);
    }

    public function test_self_referral_by_phone_creates_no_commission(): void
    {
        [$influencer, $product, $link, $customer] = $this->scenario(20);

        $order = app(OrderService::class)->createOrder($customer, [
            'items'            => [['product_id' => $product->id, 'quantity' => 1]],
            'ref'              => $link->code,
            'customer_name'    => 'Same Phone Buyer',
            'customer_phone'   => $influencer->phone,
            'shipping_address' => 'Cairo',
        ]);

        $this->assertNull($order->influencer_id);
        $this->assertNull($order->items()->first()->commission_percentage);
        $this->assertSame(0, InfluencerCommission::count());
    }

    public function test_influencer_cannot_refer_their_own_account(): void
    {
        $influencer = User::factory()->influencer()->create([
            'phone' => '01111111111',
            'email' => 'self@example.com',
        ]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        $influencer->influencerProducts()->attach($product->id, [
            'commission_percentage' => 10,
            'is_active'             => true,
        ]);
        $link = PromotionalLink::factory()->create([
            'influencer_id' => $influencer->id,
            'product_id'    => null,
        ]);

        $order = app(OrderService::class)->createOrder($influencer, [
            'items'            => [['product_id' => $product->id, 'quantity' => 1]],
            'ref'              => $link->code,
            'customer_name'    => 'Influencer Self',
            'customer_phone'   => $influencer->phone,
            'shipping_address' => 'Cairo',
        ]);

        $this->assertNull($order->influencer_id);
        $this->assertNull($order->items()->first()->commission_percentage);
    }

    /**
     * @return array{0: User, 1: Product, 2: PromotionalLink, 3: User}
     */
    protected function scenario(float $percentage): array
    {
        $influencer = User::factory()->influencer()->create([
            'phone' => '01111111111',
            'email' => 'inf@example.com',
        ]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 50]);

        $influencer->influencerProducts()->attach($product->id, [
            'commission_percentage' => $percentage,
            'is_active'             => true,
        ]);

        $link = PromotionalLink::factory()->create([
            'influencer_id' => $influencer->id,
            'product_id'    => null,
        ]);

        $customer = User::factory()->customer()->create([
            'phone' => '01222222222',
            'email' => 'buyer@example.com',
        ]);

        return [$influencer, $product, $link, $customer];
    }

    protected function createOrder(User $customer, Product $product, PromotionalLink $link, ?Coupon $coupon = null)
    {
        return app(OrderService::class)->createOrder($customer, [
            'items'            => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code'      => $coupon?->code,
            'ref'              => $link->code,
            'customer_name'    => 'Buyer',
            'customer_phone'   => '01012345678',
            'shipping_address' => 'Cairo',
        ]);
    }

    protected function deliver($order)
    {
        $orders = app(OrderService::class);

        app(PaymentService::class)->markPaid($order->payment()->firstOrFail(), 'TXN-COM-' . $order->id);

        foreach (['processing', 'ready_for_shipping', 'shipped', 'delivered'] as $status) {
            $order = $orders->updateStatus($order->fresh(), $status);
        }

        return $order->commissions()->get();
    }
}
