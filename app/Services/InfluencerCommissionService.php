
<?php

namespace App\Services;

use App\Models\InfluencerCommission;
use App\Models\InfluencerWallet;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InfluencerCommissionService
{
    /**
     * النسبة الافتراضية، وتُستخدم فقط للمنتجات المرتبطة
     * بالمؤثر عندما لا تكون لها نسبة خاصة.
     */
    protected function defaultPercentage(): float
    {
        return (float) config('services.influencer.default_commission', 10);
    }

    /**
     * إنشاء عمولات الطلب وإضافتها إلى محفظة المؤثر.
     *
     * يجب استدعاء هذه العملية عند تحقق البيع، وليس بمجرد إنشاء الطلب.
     *
     * @return Collection<int, InfluencerCommission>
     */
    public function calculateAndStore(Order $order): Collection
    {
        if (!$order->influencer_id) {
            return collect();
        }

        return DB::transaction(function () use ($order) {
            // قفل الطلب لمنع احتساب العمولة مرتين بالتزامن.
            $lockedOrder = Order::with(['items', 'promotionalLink'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedOrder->influencer_id) {
                return collect();
            }

            $items = $lockedOrder->items;

            if ($items->isEmpty()) {
                return collect();
            }

            $productIds = $items
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            if ($productIds->isEmpty()) {
                return collect();
            }

            /*
             * لا نعتبر المنتج مؤهلاً للعمولة إلا إذا كان مربوطاً
             * بالمؤثر والربط مفعّلاً.
             */
            $eligibleProducts = DB::table('influencer_product')
                ->where('influencer_id', $lockedOrder->influencer_id)
                ->where('is_active', true)
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

            /*
             * إذا كان رابط الإحالة مرتبطاً بمنتج محدد،
             * فلا تُحسب العمولة إلا لهذا المنتج.
             */
            $promotionalLink = $lockedOrder->promotionalLink;

            $linkProductId = $promotionalLink?->product_id;

            // العناصر التي سبق إنشاء عمولة لها لا تُكرر.
            $existingItemIds = InfluencerCommission::query()
                ->where('order_id', $lockedOrder->id)
                ->whereNotNull('order_item_id')
                ->pluck('order_item_id')
                ->all();

            $created = collect();

            foreach ($items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                if (in_array($item->id, $existingItemIds, true)) {
                    continue;
                }

                if (
                    $linkProductId !== null
                    && (int) $linkProductId !== (int) $item->product_id
                ) {
                    continue;
                }

                $pivot = $eligibleProducts->get($item->product_id);

                // المنتج غير مربوط بالمؤثر أو الربط غير مفعّل.
                if (!$pivot) {
                    continue;
                }

                $percentage = $pivot->commission_percentage !== null
                    ? (float) $pivot->commission_percentage
                    : $this->defaultPercentage();

                if ($percentage < 0 || $percentage > 100) {
                    throw new RuntimeException(
                        "نسبة العمولة غير صحيحة للمنتج رقم {$item->product_id}"
                    );
                }

                $itemTotal = round((float) $item->total, 2);

                if ($itemTotal < 0) {
                    throw new RuntimeException(
                        "قيمة عنصر الطلب غير صحيحة: {$item->id}"
                    );
                }

                $commissionAmount = round(
                    ($itemTotal * $percentage) / 100,
                    2
                );

                $commission = InfluencerCommission::create([
                    'influencer_id'         => $lockedOrder->influencer_id,
                    'order_id'              => $lockedOrder->id,
                    'order_item_id'         => $item->id,
                    'product_id'            => $item->product_id,
                    'order_total'           => $itemTotal,
                    'commission_percentage' => $percentage,
                    'commission_amount'     => $commissionAmount,
                    'status'                => 'approved',
                ]);

                $this->addToWallet(
                    (int) $lockedOrder->influencer_id,
                    $commissionAmount,
                    (int) $lockedOrder->id
                );

                $created->push($commission);
            }

            return $created;
        });
    }

    /**
     * إضافة العمولة إلى محفظة المؤثر وتسجيل الحركة المالية.
     */
    protected function addToWallet(
        int $influencerId,
        float $amount,
        int $orderId
    ): void {
        $wallet = InfluencerWallet::firstOrCreate(
            ['influencer_id' => $influencerId],
            [
                'balance' => 0,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]
        );

        // قفل المحفظة لتجنب تضارب تحديثات الرصيد.
        $wallet = InfluencerWallet::whereKey($wallet->id)
            ->lockForUpdate()
            ->firstOrFail();

        $wallet->balance = round(
            (float) $wallet->balance + $amount,
            2
        );

        $wallet->total_earned = round(
            (float) $wallet->total_earned + $amount,
            2
        );

        $wallet->save();

        WalletTransaction::create([
            'wallet_id'     => $wallet->id,
            'order_id'      => $orderId,
            'type'          => 'credit',
            'amount'        => $amount,
            'balance_after' => $wallet->balance,
            'description'   => "عمولة من الطلب #{$orderId}",
            'status'        => 'completed',
        ]);
    }

    /**
     * عكس العمولات عند إلغاء الطلب أو إرجاعه.
     *
     * العمولات المدفوعة لا يتم عكسها تلقائياً؛
     * تحتاج إلى سياسة تسوية مستقلة.
     */
    public function reverseForOrder(Order $order): void
    {
        if (!$order->influencer_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $commissions = InfluencerCommission::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['pending', 'approved'])
                ->lockForUpdate()
                ->get();

            if ($commissions->isEmpty()) {
                return;
            }

            // نعكس المبالغ التي سبق اعتمادها وإضافتها للمحفظة فقط.
            $approvedTotal = round(
                (float) $commissions
                    ->where('status', 'approved')
                    ->sum('commission_amount'),
                2
            );

            $wallet = null;

            if ($approvedTotal > 0) {
                $wallet = InfluencerWallet::where(
                    'influencer_id',
                    $lockedOrder->influencer_id
                )
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    // فشل العملية بالكامل بدلاً من إلغاء العمولات
                    // دون تسجيل الخصم المقابل في المحفظة.
                    throw new RuntimeException(
                        "محفظة المؤثر غير موجودة للطلب رقم {$lockedOrder->id}"
                    );
                }
            }

            foreach ($commissions as $commission) {
                $commission->update([
                    'status' => 'cancelled',
                ]);
            }

            if ($approvedTotal <= 0 || !$wallet) {
                return;
            }

            // قد يصبح الرصيد سالباً إذا سبق للمؤثر سحب العمولة.
            $wallet->balance = round(
                (float) $wallet->balance - $approvedTotal,
                2
            );

            $wallet->total_earned = round(
                max(0, (float) $wallet->total_earned - $approvedTotal),
                2
            );

            $wallet->save();

            WalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'order_id'      => $lockedOrder->id,
                'type'          => 'debit',
                'amount'        => $approvedTotal,
                'balance_after' => $wallet->balance,
                'description'   => "عكس عمولة الطلب #{$lockedOrder->id}",
                'status'        => 'completed',
            ]);
        });
    }
}
