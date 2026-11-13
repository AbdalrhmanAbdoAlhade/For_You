<?php

namespace Tests\Feature;

use App\Models\InfluencerCommission;
use App\Models\InfluencerWallet;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfluencerDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_influencer_with_zero_balance_can_be_deleted_and_identifiers_released(): void
    {
        $admin = User::factory()->admin()->create();
        $influencer = User::factory()->influencer()->create([
            'email' => 'influencer@example.com',
            'phone' => '01055555555',
        ]);

        InfluencerWallet::factory()->create([
            'influencer_id'   => $influencer->id,
            'balance'         => 0,
            'total_earned'    => 0,
            'total_withdrawn' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/influencers/{$influencer->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $influencer->id]);

        $trashed = User::withTrashed()->findOrFail($influencer->id);
        $this->assertSame('influencer@example.com', $trashed->deleted_email);
        $this->assertSame('01055555555', $trashed->deleted_phone);

        // البريد الأصلي صار متاحًا لحساب جديد.
        $replacement = User::factory()->create([
            'email' => 'influencer@example.com',
            'phone' => '01055555555',
        ]);

        $this->assertSame('influencer@example.com', $replacement->email);
    }

    public function test_influencer_with_negative_wallet_balance_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $influencer = User::factory()->influencer()->create();

        InfluencerWallet::factory()->create([
            'influencer_id' => $influencer->id,
            'balance'       => -10,
            'total_earned'  => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/influencers/{$influencer->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $influencer->id]);
    }

    public function test_influencer_with_positive_wallet_balance_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $influencer = User::factory()->influencer()->create();

        InfluencerWallet::factory()->create([
            'influencer_id' => $influencer->id,
            'balance'       => 100,
            'total_earned'  => 100,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/influencers/{$influencer->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $influencer->id]);
    }

    public function test_influencer_with_pending_commission_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $influencer = User::factory()->influencer()->create();
        $order = Order::factory()->create(['influencer_id' => $influencer->id]);

        InfluencerCommission::create([
            'influencer_id'         => $influencer->id,
            'order_id'              => $order->id,
            'order_total'           => 100,
            'commission_percentage' => 10,
            'commission_amount'     => 10,
            'status'                => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/influencers/{$influencer->id}")
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $influencer->id]);
    }
}
