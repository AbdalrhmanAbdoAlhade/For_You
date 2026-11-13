<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateways\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentGatewayManager $gateways
    ) {}

    /**
     * بدء أو إعادة محاولة الدفع لطلب عبر بوابة معينة.
     *
     * @return array<string, mixed> بيانات البوابة (payment_url / reference / …)
     */
    public function initiate(Order $order, ?string $gateway = null): array
    {
        return DB::transaction(function () use ($order, $gateway) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($order->status, ['cancelled', 'returned'], true)) {
                throw new \DomainException('لا يمكن الدفع لطلب ملغي أو مرتجع');
            }

            $payment = Payment::where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new \DomainException('لا يوجد سجل دفع لهذا الطلب');
            }

            if (in_array($payment->status, ['paid', 'refunded'], true)) {
                throw new \DomainException('هذا الطلب مدفوع بالفعل');
            }

            // الافتراضي: نفس طريقة الدفع المسجلة على الطلب.
            $gateway = $gateway
                ?: ($payment->payment_method ?: (string) config('payments.default'));

            $driver = $this->gateways->driver($gateway);

            if ($payment->payment_method !== $driver->name()) {
                $payment->update(['payment_method' => $driver->name()]);
            }

            $result = $driver->initiate($order);

            return [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'payment_id'   => $payment->id,
                ...$result,
            ];
        });
    }

    /**
     * نقطة دخول واحدة لتحديث حالة الدفع.
     */
    public function updateStatus(
        Payment $payment,
        string $status,
        ?string $transactionId = null,
        ?array $gatewayResponse = null
    ): Payment {
        return match ($status) {
            'paid' => $this->markPaid(
                $payment,
                $transactionId,
                $gatewayResponse
            ),
            'failed' => $this->markFailed($payment, $gatewayResponse),
            'refunded' => $this->markRefunded($payment, $gatewayResponse),
            default => throw new \DomainException('حالة دفع غير مدعومة'),
        };
    }

    /**
     * تأكيد الدفع ثم تأكيد الطلب إذا كان جديدًا.
     * ترتيب الأقفال: Order ثم Payment.
     */
    public function markPaid(
        Payment $payment,
        ?string $transactionId = null,
        ?array $gatewayResponse = null
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $transactionId,
            $gatewayResponse
        ) {
            $order = Order::whereKey($payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::whereKey($payment->id)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'paid') {
                return $payment;
            }

            if ($payment->status === 'refunded') {
                throw new \DomainException('لا يمكن تأكيد دفعة مستردة');
            }

            if (in_array($order->status, ['cancelled', 'returned'], true)) {
                throw new \DomainException(
                    'الطلب ملغي أو مرتجع، لا يمكن تأكيد الدفع'
                );
            }

            // نسمح بإعادة المحاولة: failed → paid.
            if (!in_array($payment->status, ['pending', 'failed'], true)) {
                throw new \DomainException(
                    'يمكن تأكيد الدفعات المعلقة أو الفاشلة فقط'
                );
            }

            if (
                $transactionId !== null
                && Payment::where('transaction_id', $transactionId)
                    ->whereKeyNot($payment->id)
                    ->exists()
            ) {
                throw new \DomainException('رقم عملية الدفع مستخدم بالفعل');
            }

            $payment->update([
                'status' => 'paid',
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'gateway_response' => $gatewayResponse ?? $payment->gateway_response,
                'paid_at' => now(),
            ]);

            if ($order->status === 'new') {
                $this->orderService->updateStatus($order, 'confirmed');
            }

            return $payment->fresh();
        });
    }

    /**
     * تسجيل فشل محاولة دفع:
     * - يغير حالة الدفع إلى failed فقط.
     * - لا يلغي الطلب؛ العميل قد يعيد المحاولة، والطلب غير المدفوع
     *   يُلغى تلقائيًا لاحقًا عبر orders:expire-unpaid بعد انتهاء المهلة.
     *
     * يجب عدم استدعاء هذه الدالة عند فشل محاولة دفع مؤقتة
     * إذا كان مسموحًا للعميل إعادة المحاولة.
     */
    public function markFailed(
        Payment $payment,
        ?array $gatewayResponse = null
    ): Payment {
        return DB::transaction(function () use ($payment, $gatewayResponse) {
            // ترتيب الأقفال موحد: الطلب أولًا ثم الدفع.
            $order = Order::whereKey($payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::whereKey($payment->id)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($order->status, ['shipped', 'delivered'], true)) {
                throw new \DomainException(
                    'لا يمكن اعتبار الدفع فاشلًا بعد شحن أو تسليم الطلب؛ يلزم إجراء مراجعة يدوية'
                );
            }

            if ($payment->status === 'paid' || $payment->status === 'refunded') {
                throw new \DomainException(
                    'لا يمكن تحويل دفعة مؤكدة أو مستردة إلى فاشلة'
                );
            }

            if (!in_array($payment->status, ['pending', 'failed'], true)) {
                throw new \DomainException(
                    'لا يمكن تغيير حالة الدفع الحالية إلى failed'
                );
            }

            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'failed',
                    'gateway_response' => $gatewayResponse ?? $payment->gateway_response,
                ]);
            } elseif ($gatewayResponse !== null) {
                // يسمح بتحديث تفاصيل الفشل عند إعادة استدعاء العملية.
                $payment->update([
                    'gateway_response' => $gatewayResponse,
                ]);
            }

            return $payment->fresh();
        });
    }

    public function markRefunded(
    Payment $payment,
    ?array $gatewayResponse = null
): Payment {
    return DB::transaction(function () use ($payment, $gatewayResponse) {
        // نفس ترتيب القفل: الطلب ثم الدفعة.
        $order = Order::whereKey($payment->order_id)
            ->lockForUpdate()
            ->firstOrFail();

        $payment = Payment::whereKey($payment->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($payment->status === 'refunded') {
            return $payment;
        }

        if ($payment->status !== 'paid') {
            throw new \DomainException('يمكن استرداد الدفعات المؤكدة فقط');
        }

        $payment->update([
            'status' => 'refunded',
            'gateway_response' => $gatewayResponse ?? $payment->gateway_response,
        ]);

        // قبل الشحن = إلغاء، بعد الشحن = مرتجع. OrderService بيرجّع
        // المخزون والكوبون ويعكس العمولات (المعلّقة تتلغي والمتاحة تتخصم).
        if (!in_array($order->status, ['cancelled', 'returned'], true)) {
            $target = in_array($order->status, ['shipped', 'delivered'], true)
                ? 'returned'
                : 'cancelled';

            $this->orderService->updateStatus($order, $target);
        }

        return $payment->fresh();
    });
}

  
}
