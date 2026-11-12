<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InfluencerWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['store.withdrawal_four_eyes' => true]);
    }

    public function test_withdrawal_below_minimum_is_rejected(): void
    {
        $influencer = $this->influencerWithBalance(100);

        Sanctum::actingAs($influencer);

        $this->postJson('/api/influencer/wallet/withdraw', [
            'amount'          => 10,
            'payment_method'  => 'vodafone_cash',
            'account_details' => '01000000000',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_withdrawal_above_available_balance_is_rejected(): void
    {
        $influencer = $this->influencerWithBalance(100);

        Sanctum::actingAs($influencer);

        $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/influencer/wallet/withdraw', [
                'amount'          => 200,
                'payment_method'  => 'vodafone_cash',
                'account_details' => '01000000000',
            ])->assertStatus(422)
            ->assertJsonPath('message', 'الرصيد المتاح للسحب غير كافٍ بعد خصم المبالغ المحجوزة');
    }

    public function test_four_eyes_prevents_same_admin_from_approving_and_paying(): void
    {
        $influencer = $this->influencerWithBalance(500);
        $withdrawal = app(WalletService::class)->requestWithdrawal(
            $influencer,
            100,
            'vodafone_cash',
            '01000000000'
        );

        $approver = User::factory()->admin()->create();
        $payer = User::factory()->admin()->create();

        Sanctum::actingAs($approver);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/process", [
            'status' => 'approved',
        ])->assertOk();

        // نفس الشخص لا يمكنه تنفيذ التحويل
        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/process", [
            'status'             => 'paid',
            'transfer_reference' => 'TRX-001',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'لازم شخص تاني غير اللي وافق على الطلب ينفّذ التحويل');

        Sanctum::actingAs($payer);

        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/process", [
            'status'             => 'paid',
            'transfer_reference' => 'TRX-002',
        ])->assertOk()
            ->assertJsonPath('withdrawal.status', 'paid')
            ->assertJsonPath('withdrawal.transfer_reference', 'TRX-002');

        $withdrawal->refresh();
        $this->assertSame($payer->id, (int) $withdrawal->processed_by);
        $this->assertSame($payer->id, (int) $withdrawal->paid_by);
        $this->assertSame('TRX-002', $withdrawal->transfer_reference);

        // المحفظة تخصم مرة واحدة فقط
        $wallet = InfluencerWallet::where('influencer_id', $influencer->id)->firstOrFail();
        $this->assertSame(400.0, (float) $wallet->balance);
        $this->assertSame(100.0, (float) $wallet->total_withdrawn);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'withdrawal.paid',
            'subject_id' => $withdrawal->id,
            'user_id'    => $payer->id,
        ]);
    }

    public function test_paying_without_transfer_reference_is_rejected(): void
    {
        $influencer = $this->influencerWithBalance(500);
        $withdrawal = app(WalletService::class)->requestWithdrawal(
            $influencer,
            100,
            'vodafone_cash',
            '01000000000'
        );

        $approver = User::factory()->admin()->create();
        $payer = User::factory()->admin()->create();

        Sanctum::actingAs($approver);
        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/process", [
            'status' => 'approved',
        ])->assertOk();

        Sanctum::actingAs($payer);
        $this->patchJson("/api/admin/withdrawals/{$withdrawal->id}/process", [
            'status' => 'paid',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('transfer_reference');
    }

    public function test_pending_withdrawals_reserve_available_balance(): void
    {
        $influencer = $this->influencerWithBalance(100);

        app(WalletService::class)->requestWithdrawal($influencer, 80, 'vodafone_cash', '01000000000');

        $this->expectException(\DomainException::class);

        app(WalletService::class)->requestWithdrawal($influencer, 50, 'vodafone_cash', '01000000000');
    }

    protected function influencerWithBalance(float $balance): User
    {
        $influencer = User::factory()->influencer()->create();

        InfluencerWallet::create([
            'influencer_id'   => $influencer->id,
            'balance'         => $balance,
            'total_earned'    => $balance,
            'total_withdrawn' => 0,
        ]);

        return $influencer;
    }
}
