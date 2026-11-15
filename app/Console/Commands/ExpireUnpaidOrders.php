<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'إلغاء الطلبات الجديدة غير المدفوعة بعد المدة المحددة وإرجاع المخزون والكوبون';

    public function handle(OrderService $orders): int
    {
        $hours = (int) config('store.unpaid_order_ttl_hours', 24);

        if ($hours <= 0) {
            $this->info('إلغاء الطلبات غير المدفوعة معطّل (UNPAID_ORDER_TTL_HOURS=0).');
            return self::SUCCESS;
        }

        $cancelled = 0;

        /*
         * الإلغاء التلقائي لطرق الدفع الإلكترونية فقط. طلبات الدفع اليدوي/COD
         * بتفضل 'new' لحد ما الإدمن يأكد التحصيل، فماينفعش تتلغى بالكرون.
         */
        $methods = (array) config('store.expire_unpaid_payment_methods', []);

        if ($methods === []) {
            $this->info('مفيش طرق دفع مؤهلة للإلغاء التلقائي (EXPIRE_UNPAID_PAYMENT_METHODS).');
            return self::SUCCESS;
        }

        Order::query()
            ->where('status', 'new')
            ->where('created_at', '<=', now()->subHours($hours))
            ->whereHas('payment', fn ($q) => $q->whereIn('payment_method', $methods))
            ->whereDoesntHave('payment', fn ($q) => $q->where('status', 'paid'))
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($orders, &$cancelled) {
                foreach ($chunk as $order) {
                    try {
                        $done = DB::transaction(function () use ($orders, $order) {
                            // نعيد الفحص بعد القفل: ممكن الدفع يتأكد في نفس اللحظة
                            // والطلب يبقى confirmed، وساعتها ماينفعش نلغيه.
                            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                            if (!$locked || $locked->status !== 'new') {
                                return false;
                            }

                            if ($locked->payment()->where('status', 'paid')->exists()) {
                                return false;
                            }

                            // بيرجّع المخزون والكوبون، ويقفل الدفعة المعلقة، ويبعت إشعار للعميل
                            $orders->updateStatus($locked, 'cancelled');

                            AuditLog::record('order.auto_cancelled', $locked, [
                                'reason'   => 'unpaid_expired',
                                'hours'    => (int) config('store.unpaid_order_ttl_hours', 24),
                                'gateway'  => $locked->payment?->payment_method,
                            ]);

                            return true;
                        });

                        if ($done) {
                            $cancelled++;
                        }
                    } catch (\Throwable $e) {
                        report($e); // فشل طلب واحد ما يوقفش الباقي
                    }
                }
            });

        $this->info("تم إلغاء {$cancelled} طلب غير مدفوع.");

        return self::SUCCESS;
    }
}
