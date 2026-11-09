<?php

namespace App\Services;

use App\Models\Order;
use App\Models\InfluencerCommission;
use App\Models\InfluencerWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class InfluencerCommissionService
{
    public function calculateAndStore(Order $order): void
    {
        if (!$order->influencer_id) {
            return;
        }

        DB::transaction(function () use ($order) {

            foreach ($order->items as $item) {

                // نجيب نسبة العمولة (من المنتج المرتبط بالمؤثر أو نسبة عامة)
                $pivot = $order->influencer
                    ->influencerProducts()
                    ->where('product_id', $item->product_id)
                    ->first();

                $percentage = $pivot?->pivot?->commission_percentage ?? 10; // افتراضي 10%

                $commissionAmount = ($item->total * $percentage) / 100;

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

                // إضافة المبلغ لمحفظة المؤثر
                $this->addToWallet($order->influencer_id, $commissionAmount, $order->id, $commission->id);
            }
        });
    }

    protected function addToWallet(int $influencerId, float $amount, int $orderId, int $commissionId): void
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
}
