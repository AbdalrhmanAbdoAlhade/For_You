<?php

namespace App\Services;

use App\Models\Order;
use App\Models\InfluencerCommission;
use App\Models\InfluencerWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InfluencerCommissionService
{
    protected function defaultPercentage(): float
    {
        return (float) config('services.influencer.default_commission', 10);
    }

    /**
     * يحسب عمولة كل item ويضيفها للمحفظة.
     * Idempotent: لو الطلب اتحسبله عمولة قبل كده بيرجع collection فاضية.
     *
     * @return Collection<int, InfluencerCommission>
     */
    public function calculateAndStore(Order $order): Collection
    {
        if (!$order->influencer_id) {
            return collect();
        }

        return DB::transaction(function () use ($order) {

            // lock على الطلب عشان طلبين متزامنين ميحسبوش العمولة مرتين
            Order::whereKey($order->id)->lockForUpdate()->first();

            if (InfluencerCommission::where('order_id', $order->id)->exists()) {
                return collect();
            }

            $created = collect();

            foreach ($order->items as $item) {

                $pivot = $order->influencer
                    ->influencerProducts()
                    ->where('product_id', $item->product_id)
                    ->first();

                $percentage = $pivot?->pivot?->commission_percentage ?? $this->defaultPercentage();

                $commissionAmount = round(($item->total * $percentage) / 100, 2);

                $commission = InfluencerCommission::create([
                    'influencer_id'         => $order->influencer_id,
                    'order_id'              => $order->id,
                    'order_item_id'         => $item->id,
                    'product_id'            => $item->product_id,
                    'order_total'           => $item->total,
                    'commission_percentage' => $percentage,
                    'commission_amount'     => $commissionAmount,
                    'status'                => 'approved',
                ]);

                $this->addToWallet($order->influencer_id, $commissionAmount, $order->id);

                $created->push($commission);
            }

            return $created;
        });
    }

    protected function addToWallet(int $influencerId, float $amount, int $orderId): void
    {
        $wallet = InfluencerWallet::firstOrCreate(
            ['influencer_id' => $influencerId],
            ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );

        $wallet->increment('balance', $amount);
        $wallet->increment('total_earned', $amount);

        WalletTransaction::create([
            'wallet_id'     => $wallet->id,
            'order_id'      => $orderId,
            'type'          => 'credit',
            'amount'        => $amount,
            'balance_after' => $wallet->fresh()->balance,
            'description'   => "عمولة من الطلب #{$orderId}",
            'status'        => 'completed',
        ]);
    }

        /**
     * عند إلغاء أو إرجاع الطلب: نلغي العمولات ونخصمها من المحفظة.
     * ملحوظة: لو المؤثر سحب فلوسه قبل الإرجاع الرصيد ممكن يبقى بالسالب (ده مقصود، بيتخصم من عمولاته الجاية).
     */
    public function reverseForOrder(Order $order): void
    {
        if (!$order->influencer_id) {
            return;
        }

        DB::transaction(function () use ($order) {

            $commissions = InfluencerCommission::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'approved'])
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty()) {
                return;
            }

            $total = round((float) $commissions->sum('commission_amount'), 2);

            InfluencerCommission::whereIn('id', $commissions->pluck('id'))
                ->update(['status' => 'cancelled']);

            $wallet = InfluencerWallet::where('influencer_id', $order->influencer_id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                return;
            }

            $wallet->decrement('balance', $total);
            $wallet->decrement('total_earned', $total);

            WalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'order_id'      => $order->id,
                'type'          => 'debit',
                'amount'        => $total,
                'balance_after' => $wallet->fresh()->balance,
                'description'   => "استرجاع عمولة الطلب #{$order->id} ({$order->status})",
                'status'        => 'completed',
            ]);
        });
    }
}
