<?php

namespace App\Services;

use App\Models\InfluencerWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(int $influencerId): InfluencerWallet
    {
        return InfluencerWallet::firstOrCreate(
            ['influencer_id' => $influencerId],
            ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );
    }
public function requestWithdrawal(User $influencer, float $amount, string $paymentMethod, string $accountDetails): WithdrawalRequest
{
    $wallet = $this->getOrCreateWallet($influencer->id);

    if ($wallet->balance < $amount) {
        throw new \Exception('رصيد المحفظة غير كافٍ');
    }

    if ($amount < 50) { // حد أدنى للسحب
        throw new \Exception('الحد الأدنى للسحب هو 50 ج.م');
    }

    return WithdrawalRequest::create([
        'influencer_id'   => $influencer->id,
        'wallet_id'       => $wallet->id,
        'amount'          => $amount,
        'payment_method'  => $paymentMethod,
        'account_details' => $accountDetails,
        'status'          => 'pending',
    ]);
}

public function processWithdrawal(WithdrawalRequest $request, string $status, ?string $adminNote = null): void
{
    DB::transaction(function () use ($request, $status, $adminNote) {

        $request->update([
            'status'       => $status,
            'admin_note'   => $adminNote,
            'processed_at' => now(),
        ]);

        if ($status === 'approved' || $status === 'paid') {
            $this->withdraw(
                $request->influencer_id,
                $request->amount,
                "سحب طلب رقم #{$request->id}"
            );
        }
    });
}
    public function withdraw(int $influencerId, float $amount, string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($influencerId, $amount, $description) {

            $wallet = $this->getOrCreateWallet($influencerId);

            if ($wallet->balance < $amount) {
                throw new \Exception('رصيد المحفظة غير كافٍ');
            }

            $wallet->decrement('balance', $amount);
            $wallet->increment('total_withdrawn', $amount);

            return WalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'type'          => 'debit',
                'amount'        => $amount,
                'balance_after' => $wallet->fresh()->balance,
                'description'   => $description ?? 'سحب من المحفظة',
                'status'        => 'completed',
            ]);
        });
    }
}
