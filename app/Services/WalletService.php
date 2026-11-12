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

    /**
     * إنشاء طلب سحب جديد.
     *
     * لا يُسمح بالسحب إذا كان الرصيد صفرًا أو بالسالب.
     * يتم احتساب المبالغ المحجوزة قبل السماح بالسحب.
     */
    public function requestWithdrawal(
        User $influencer,
        float $amount,
        string $paymentMethod,
        string $accountDetails
    ): WithdrawalRequest {
        $amount = round($amount, 2);

        if (!is_finite($amount) || $amount < self::MIN_WITHDRAWAL) {
            throw new \DomainException(
                __('الحد الأدنى للسحب هو :amount ج.م', [
                    'amount' => self::MIN_WITHDRAWAL,
                ])
            );
        }

        return DB::transaction(function () use (
            $influencer,
            $amount,
            $paymentMethod,
            $accountDetails
        ) {
            $wallet = $this->getOrCreateWallet($influencer->id);

            // قفل المحفظة لمنع عمليات السحب المتزامنة.
            $wallet = InfluencerWallet::whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            // منع السحب عند وجود مديونية أو عدم وجود رصيد موجب.
            if ((float) $wallet->balance <= 0) {
                throw new \DomainException(
                    'لا يمكنك طلب سحب بسبب وجود مديونية أو عدم وجود رصيد موجب في محفظتك.'
                );
            }

          $reserved = $this->pendingWithdrawals($wallet);

$available = round(
    (float) $wallet->balance - $reserved,
    2
);

if ($available <= 0) {
    throw new \DomainException(
        (float) $wallet->balance < 0
            ? 'لا يمكن السحب، يوجد مديونية على المحفظة'
            : 'لا يمكن السحب، الرصيد غير متاح حالياً'
    );
}

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
     * pending  -> approved | rejected
     * approved -> paid | rejected
     *
     * approved: حجز فقط (بيتسجل مين وافق).
     * paid: خصم نهائي بعد التحويل الفعلي (بيتسجل مين دفع + رقم العملية).
     * rejected: تحرير الحجز دون خصم.
     */
    public function processWithdrawal(
        WithdrawalRequest $request,
        string $status,
        User $actor,
        ?string $adminNote = null,
        ?string $transferReference = null
    ): WithdrawalRequest {
        return DB::transaction(function () use (
            $request,
            $status,
            $actor,
            $adminNote,
            $transferReference
        ) {
            // قفل طلب السحب لمنع تنفيذ نفس العملية أكثر من مرة.
            $request = WithdrawalRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            // منع تكرار نفس الانتقال.
            if ($request->status === $status) {
                return $request;
            }

            $allowed = match ($request->status) {
                'pending'  => ['approved', 'rejected'],
                'approved' => ['paid', 'rejected'],
                default    => [],
            };

            if (!in_array($status, $allowed, true)) {
                throw new \DomainException(
                    __('لا يمكن نقل طلب السحب من (:from) إلى (:to)', [
                        'from' => $request->status,
                        'to'   => $status,
                    ])
                );
            }

            // مبدأ الشخصين: اللي وافق مينفذش التحويل بنفسه
            if (
                $status === 'paid'
                && config('store.withdrawal_four_eyes', true)
                && (int) $request->approved_by === (int) $actor->id
            ) {
                throw new \DomainException(
                    'لازم شخص تاني غير اللي وافق على الطلب ينفّذ التحويل'
                );
            }

            // قفل المحفظة قبل أي تعديل مالي.
            $wallet = InfluencerWallet::whereKey($request->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $wallet->influencer_id !== (int) $request->influencer_id) {
                throw new \DomainException(
                    'المحفظة غير مطابقة لصاحب طلب السحب.'
                );
            }

            $updates = [
                'status'       => $status,
                'admin_note'   => $adminNote ?? $request->admin_note,
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ];

            if ($status === 'approved') {
                $updates['approved_by'] = $actor->id;
                $updates['approved_at'] = now();
            }

            // الخصم يحدث فقط عند إثبات التحويل الفعلي.
            if ($status === 'paid') {
                if (!$transferReference) {
                    throw new \DomainException('رقم عملية التحويل مطلوب');
                }

                $amount = round((float) $request->amount, 2);

                // منع تنفيذ السحب إذا أصبحت المحفظة مدينة.
                if ((float) $wallet->balance <= 0) {
                    throw new \DomainException(
                        'لا يمكن تنفيذ السحب بسبب وجود مديونية على المؤثر.'
                    );
                }

                // التأكد من كفاية الرصيد قبل الخصم.
                if ((float) $wallet->balance < $amount) {
                    throw new \DomainException(
                        'رصيد المحفظة غير كافٍ لتنفيذ السحب.'
                    );
                }

                $wallet->balance = round((float) $wallet->balance - $amount, 2);
                $wallet->total_withdrawn = round((float) $wallet->total_withdrawn + $amount, 2);
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id'             => $wallet->id,
                    'withdrawal_request_id' => $request->id,
                    'type'                  => 'debit',
                    'amount'                => $amount,
                    'balance_after'         => $wallet->balance,
                    'description'           => "تنفيذ طلب السحب رقم #{$request->id}",
                    'status'                => 'completed',
                ]);

                $updates['paid_by']            = $actor->id;
                $updates['paid_at']            = now();
                $updates['transfer_reference'] = $transferReference;
            }

            $request->update($updates);

            return $request->fresh();
        });
    }
}
