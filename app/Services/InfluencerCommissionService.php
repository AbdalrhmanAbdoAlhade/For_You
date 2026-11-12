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
     * إنشاء عمولات الطلب وإضافتها إلى محفظة المؤثر.
     *
     * تعتمد الخدمة على commission_percentage المحفوظة داخل order_items
     * ولا تعيد قراءة نسبة العمولة من جدول influencer_product.
     *
     * يجب استدعاء هذه العملية عند تحقق البيع، وليس بمجرد إنشاء الطلب.
     *
     * NULL في commission_percentage تعني أن المنتج غير مؤهل للعمولة.
     * أما 0 فتعني أن المنتج مؤهل لكن نسبة العمولة صفر.
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

            /*
             * إذا كان رابط الإحالة مرتبطاً بمنتج محدد،
             * فلا تُحسب العمولة إلا لهذا المنتج.
             */
            $linkProductId = $lockedOrder->promotionalLink?->product_id;

            // العناصر التي سبق إنشاء عمولة لها لا تُكرر.
            $existingItemIds = InfluencerCommission::query()
                ->where('order_id', $lockedOrder->id)
                ->whereNotNull('order_item_id')
                ->pluck('order_item_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // توزيع خصم الكوبون على كل أصناف الطلب بالتناسب.
            $discountShares = $this->allocateDiscount(
                $items,
                (float) $lockedOrder->discount
            );

            $holdDays = $this->holdDays();

            $created = collect();

            foreach ($items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                if (in_array((int) $item->id, $existingItemIds, true)) {
                    continue;
                }

                // احترام قيد رابط الإحالة إذا كان مرتبطاً بمنتج محدد.
                if (
                    $linkProductId !== null
                    && (int) $linkProductId !== (int) $item->product_id
                ) {
                    continue;
                }

                /*
                 * قراءة النسبة المجمدة داخل عنصر الطلب.
                 *
                 * نستخدم getRawOriginal للتأكد من التمييز بين:
                 * NULL = غير مؤهل.
                 * 0    = مؤهل بنسبة صفر.
                 */
                $rawPercentage = $item->getRawOriginal('commission_percentage');

                if ($rawPercentage === null) {
                    continue;
                }

                if (!is_numeric($rawPercentage)) {
                    throw new RuntimeException(
                        "نسبة العمولة غير صحيحة لعنصر الطلب رقم {$item->id}"
                    );
                }

                $percentage = (float) $rawPercentage;

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

                // العمولة على صافي الصنف بعد خصم نصيبه من كوبون الطلب، وبدون الشحن.
                $netTotal = round(
                    max(0, $itemTotal - ($discountShares[$item->id] ?? 0)),
                    2
                );

                $commissionAmount = round(
                    ($netTotal * $percentage) / 100,
                    2
                );

                $commission = InfluencerCommission::create([
                    'influencer_id'         => $lockedOrder->influencer_id,
                    'order_id'              => $lockedOrder->id,
                    'order_item_id'         => $item->id,
                    'product_id'            => $item->product_id,
                    'order_total'           => $netTotal,
                    'commission_percentage' => $percentage,
                    'commission_amount'     => $commissionAmount,
                    'status'                => $holdDays > 0 ? 'pending' : 'approved',
                    'available_at'          => $holdDays > 0
                        ? now()->addDays($holdDays)
                        : null,
                ]);

                /*
                 * مع وجود فترة انتظار، لا تدخل العمولة المحفظة الآن،
                 * وإنما تدخل عند releaseDue بعد انتهاء الفترة.
                 */
                if ($holdDays === 0 && $commissionAmount > 0) {
                    $this->addToWallet(
                        (int) $lockedOrder->influencer_id,
                        $commissionAmount,
                        (int) $lockedOrder->id
                    );
                }

                $created->push($commission);
            }

            return $created;
        });
    }

    /**
     * عدد أيام الانتظار قبل إتاحة العمولة.
     */
    protected function holdDays(): int
    {
        return max(
            0,
            (int) config('services.influencer.commission_hold_days', 7)
        );
    }

    /**
     * توزيع خصم الطلب على الأصناف بالتناسب (بالقروش، مع توزيع الباقي)
     * بحيث مجموع الحصص = الخصم بالضبط.
     *
     * @return array<int, float> [order_item_id => discount share]
     */
    protected function allocateDiscount(Collection $items, float $discount): array
    {
        $discountCents = (int) round($discount * 100);

        $totals = [];

        foreach ($items as $item) {
            $totals[$item->id] = (int) round(
                ((float) $item->total) * 100
            );
        }

        $baseCents = array_sum($totals);

        if ($discountCents <= 0 || $baseCents <= 0) {
            return [];
        }

        $discountCents = min($discountCents, $baseCents);

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($totals as $id => $cents) {
            $raw = ($cents * $discountCents) / $baseCents;

            $floor = (int) floor($raw);

            $shares[$id] = $floor;
            $remainders[$id] = $raw - $floor;

            $allocated += $floor;
        }

        $left = $discountCents - $allocated;

        arsort($remainders);

        foreach (array_keys($remainders) as $id) {
            if ($left <= 0) {
                break;
            }

            $shares[$id]++;
            $left--;
        }

        return array_map(
            fn (int $cents) => $cents / 100,
            $shares
        );
    }

    /**
     * إتاحة كل العمولات المعلقة التي انتهت فترة انتظارها.
     */
    public function releaseDue(): int
    {
        $released = 0;

        InfluencerCommission::query()
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$released) {
                foreach ($chunk as $commission) {
                    try {
                        if ($this->release($commission)) {
                            $released++;
                        }
                    } catch (\Throwable $e) {
                        report($e);

                        // فشل عمولة واحدة لا يوقف معالجة باقي العمولات.
                    }
                }
            });

        return $released;
    }

    /**
     * إتاحة عمولة واحدة: تتحول إلى approved وتدخل المحفظة،
     * بشرط أن يكون الطلب ما زال delivered.
     */
    public function release(InfluencerCommission $commission): bool
    {
        return DB::transaction(function () use ($commission) {
            // نفس ترتيب القفل في reverseForOrder: الطلب أولاً ثم العمولة.
            $order = Order::whereKey($commission->order_id)
                ->lockForUpdate()
                ->first();

            $locked = InfluencerCommission::whereKey($commission->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || $locked->status !== 'pending') {
                return false;
            }

            if (!$order || $order->status !== 'delivered') {
                return false;
            }

            $locked->update([
                'status' => 'approved',
            ]);

            if ((float) $locked->commission_amount > 0) {
                $this->addToWallet(
                    (int) $locked->influencer_id,
                    (float) $locked->commission_amount,
                    (int) $locked->order_id
                );
            }

            return true;
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
     * - العمولات pending: تُلغى فقط لأنها لم تدخل المحفظة بعد.
     * - العمولات approved: تُخصم من المحفظة.
     * - لو الرصيد غير كافٍ: يصبح سالباً (دين على المؤثر).
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

            // المبلغ الذي دخل المحفظة فعلاً (approved فقط).
            $approvedTotal = round(
                (float) $commissions
                    ->where('status', 'approved')
                    ->sum('commission_amount'),
                2
            );

            // إلغاء كل العمولات أولاً.
            foreach ($commissions as $commission) {
                $commission->update([
                    'status' => 'cancelled',
                ]);
            }

            if ($approvedTotal <= 0) {
                return;
            }

            $wallet = InfluencerWallet::where(
                'influencer_id',
                $lockedOrder->influencer_id
            )
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException(
                    "محفظة المؤثر غير موجودة للطلب رقم {$lockedOrder->id}"
                );
            }

            // قد يصبح الرصيد سالباً إذا سبق للمؤثر سحب العمولة.
            $balanceBefore = (float) $wallet->balance;

            $wallet->balance = round(
                $balanceBefore - $approvedTotal,
                2
            );

            $wallet->total_earned = round(
                max(
                    0,
                    (float) $wallet->total_earned - $approvedTotal
                ),
                2
            );

            $wallet->save();

            $description = $wallet->balance < 0
                ? "عكس عمولة الطلب #{$lockedOrder->id} (رصيد سالب / مديونية)"
                : "عكس عمولة الطلب #{$lockedOrder->id}";

            WalletTransaction::create([
                'wallet_id'     => $wallet->id,
                'order_id'      => $lockedOrder->id,
                'type'          => 'debit',
                'amount'        => $approvedTotal,
                'balance_after' => $wallet->balance,
                'description'   => $description,
                'status'        => 'completed',
            ]);
        });
    }
}
