<?php

namespace App\Services;

use App\Models\InfluencerWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public const MIN_WITHDRAWAL = 50;

    public function getOrCreateWallet(int $influencerId): InfluencerWallet
    {
        return InfluencerWallet::firstOrCreate(
            ['influencer_id' => $influencerId],
            ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );
    }

    /** مجموع طلبات السحب المعلقة لمحفظة */
    public function pendingWithdrawals(InfluencerWallet $wallet): float
    {
        return (float) WithdrawalRequest::where('wallet_id', $wallet->id)
            ->where('status', 'pending')
            ->sum('amount');
    }

    /**
     * @throws \DomainException
     */
    public function requestWithdrawal(User $influencer, float $amount, string $paymentMethod, string $accountDetails): WithdrawalRequest
    {
        if ($amount < self::MIN_WITHDRAWAL) {
            throw new \DomainException('الحد الأدنى للسحب هو ' . self::MIN_WITHDRAWAL . ' ج.م');
        }

        return DB::transaction(function () use ($influencer, $amount, $paymentMethod, $accountDetails) {

            $wallet = $this->getOrCreateWallet($influencer->id);

            // lock على المحفظة عشان طلبين متزامنين ميعدوش الفحص مع بعض
            $wallet = InfluencerWallet::whereKey($wallet->id)->lockForUpdate()->first();

            $available = (float) $wallet->balance - $this->pendingWithdrawals($wallet);

            if ($amount > $available) {
                throw new \DomainException(
                    'الرصيد المتاح للسحب غير كافٍ (بعد خصم طلبات السحب المعلقة)'
                );
            }

            return WithdrawalRequest::create([
                'influencer_id'   => $influencer->id,
                'wallet_id'       => $wallet->id,
                'amount'          => $amount,
                'payment_method'  => $paymentMethod,
                'account_details' => $accountDetails,
                'status'          => 'pending',
            ]);
        });
    }

    /**
     * الانتقالات المسموحة:
     *   pending  → approved | rejected | paid
     *   approved → paid
     * الخصم من المحفظة بيتم مرة واحدة فقط، عند الخروج من pending للموافقة أو الدفع.
     *
     * @throws \DomainException
     */
    public function processWithdrawal(WithdrawalRequest $request, string $status, ?string $adminNote = null): WithdrawalRequest
    {
        return DB::transaction(function () use ($request, $status, $adminNote) {

            $request = WithdrawalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            $allowed = match ($request->status) {
                'pending'  => ['approved', 'rejected', 'paid'],
                'approved' => ['paid'],
                default    => [],
            };

            if (!in_array($status, $allowed, true)) {
                throw new \DomainException("لا يمكن نقل طلب السحب من ({$request->status}) إلى ({$status})");
            }

            // الخصم: عند الخروج من pending للموافقة أو الدفع بس
            if ($request->status === 'pending' && in_array($status, ['approved', 'paid'], true)) {
                $this->withdraw(
                    $request->influencer_id,
                    (float) $request->amount,
                    "سحب طلب رقم #{$request->id}"
                );
            }

            $request->update([
                'status'       => $status,
                'admin_note'   => $adminNote ?? $request->admin_note,
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * @throws \DomainException
     */
    public function withdraw(int $influencerId, float $amount, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($influencerId, $amount, $description) {

            $wallet = $this->getOrCreateWallet($influencerId);
            $wallet = InfluencerWallet::whereKey($wallet->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw new \DomainException('رصيد المحفظة غير كافٍ');
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