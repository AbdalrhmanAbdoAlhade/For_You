<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected OrderService $orderService
    ) {}

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

            if ($payment->status !== 'pending') {
                throw new \DomainException(
                    'يمكن تأكيد الدفعات المعلقة فقط'
                );
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
     * الفشل هنا نهائي:
     * - يغير حالة الدفع إلى failed.
     * - يلغي الطلب إذا لم يصل لمرحلة الشحن.
     * - يعيد المخزون من خلال OrderService داخل نفس Transaction.
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

            // الطلب سبق إلغاؤه أو إرجاعه؛ لا نعيد المخزون مرة أخرى.
            if (in_array($order->status, ['cancelled', 'returned'], true)) {
                return $payment->fresh();
            }

            // الإلغاء يستعيد المخزون ويعيد استخدام الكوبون
            // ويعكس العمولات من خلال OrderService.
            $this->orderService->updateStatus($order, 'cancelled');

            return $payment->fresh();
        });
    }

    public function markRefunded(
        Payment $payment,
        ?array $gatewayResponse = null
    ): Payment {
        return $this->transition(
            $payment,
            'paid',
            'refunded',
            'يمكن استرداد الدفعات المؤكدة فقط',
            $gatewayResponse
        );
    }

    protected function transition(
        Payment $payment,
        string $from,
        string $to,
        string $error,
        ?array $gatewayResponse
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $from,
            $to,
            $error,
            $gatewayResponse
        ) {
            $payment = Payment::whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === $to) {
                return $payment;
            }

            if ($payment->status !== $from) {
                throw new \DomainException($error);
            }

            $payment->update([
                'status' => $to,
                'gateway_response' => $gatewayResponse ?? $payment->gateway_response,
            ]);

            return $payment->fresh();
        });
    }
}
