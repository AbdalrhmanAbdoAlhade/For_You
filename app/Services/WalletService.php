
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
            [
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]
        );
    }

    /**
     * المبالغ المحجوزة تشمل الطلبات pending و approved.
     */
    public function pendingWithdrawals(InfluencerWallet $wallet): float
    {
        return (float) WithdrawalRequest::where('wallet_id', $wallet->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');
    }

    public function requestWithdrawal(
        User $influencer,
        float $amount,
        string $paymentMethod,
        string $accountDetails
    ): WithdrawalRequest {
        $amount = round($amount, 2);

        if (!is_finite($amount) || $amount < self::MIN_WITHDRAWAL) {
            throw new \DomainException(
                'الحد الأدنى للسحب هو ' . self::MIN_WITHDRAWAL . ' ج.م'
            );
        }

        return DB::transaction(function () use (
            $influencer,
            $amount,
            $paymentMethod,
            $accountDetails
        ) {
            $wallet = $this->getOrCreateWallet($influencer->id);

            $wallet = InfluencerWallet::whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reserved = $this->pendingWithdrawals($wallet);

            $available = round(
                (float) $wallet->balance - $reserved,
                2
            );

            if ($amount > $available) {
                throw new \DomainException(
                    'الرصيد المتاح للسحب غير كافٍ بعد خصم المبالغ المحجوزة'
                );
            }

            return WithdrawalRequest::create([
                'influencer_id' => $influencer->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'account_details' => $accountDetails,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * دورة السحب:
     *
     * pending  -> approved | rejected | paid
     * approved -> paid | rejected
     *
     * approved: حجز فقط.
     * paid: خصم نهائي وتسجيل حركة مالية.
     * rejected: تحرير الحجز دون خصم.
     */
    public function processWithdrawal(
        WithdrawalRequest $request,
        string $status,
        ?string $adminNote = null
    ): WithdrawalRequest {
        return DB::transaction(function () use (
            $request,
            $status,
            $adminNote
        ) {
            $request = WithdrawalRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: لا نكرر تنفيذ نفس الانتقال.
            if ($request->status === $status) {
                return $request;
            }

            $allowed = match ($request->status) {
                'pending' => ['approved', 'rejected', 'paid'],
                'approved' => ['paid', 'rejected'],
                default => [],
            };

            if (!in_array($status, $allowed, true)) {
                throw new \DomainException(
                    "لا يمكن نقل طلب السحب من ({$request->status}) إلى ({$status})"
                );
            }

            $wallet = InfluencerWallet::whereKey($request->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $wallet->influencer_id !== (int) $request->influencer_id) {
                throw new \DomainException(
                    'المحفظة غير مطابقة لصاحب طلب السحب'
                );
            }

            // الخصم يحدث فقط عند إثبات التحويل الفعلي.
            if ($status === 'paid') {
                $amount = round((float) $request->amount, 2);

                if ((float) $wallet->balance < $amount) {
                    throw new \DomainException(
                        'رصيد المحفظة غير كافٍ لتنفيذ السحب'
                    );
                }

                $wallet->balance = round(
                    (float) $wallet->balance - $amount,
                    2
                );

                $wallet->total_withdrawn = round(
                    (float) $wallet->total_withdrawn + $amount,
                    2
                );

                $wallet->save();

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'withdrawal_request_id' => $request->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_after' => $wallet->balance,
                    'description' => "تنفيذ طلب السحب رقم #{$request->id}",
                    'status' => 'completed',
                ]);
            }

            $request->update([
                'status' => $status,
                'admin_note' => $adminNote ?? $request->admin_note,
                'processed_at' => now(),
            ]);

            return $request->fresh();
        });
    }
}
