<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * نقطة دخول واحدة: الأدمن دلوقتي، وwebhook بوابة الدفع بعدين.
     */
    public function updateStatus(Payment $payment, string $status, ?string $transactionId = null, ?array $gatewayResponse = null): Payment
    {
        return match ($status) {
            'paid'     => $this->markPaid($payment, $transactionId, $gatewayResponse),
            'failed'   => $this->markFailed($payment, $gatewayResponse),
            'refunded' => $this->markRefunded($payment, $gatewayResponse),
            default    => throw new \DomainException('حالة دفع غير مدعومة'),
        };
    }

    public function markPaid(Payment $payment, ?string $transactionId = null, ?array $gatewayResponse = null): Payment
    {
        return DB::transaction(function () use ($payment, $transactionId, $gatewayResponse) {

            $payment = Payment::with('order')->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid') {
                return $payment; // idempotent
            }

            if ($payment->status === 'refunded') {
                throw new \DomainException('لا يمكن تأكيد دفعة مستردة');
            }

            if (in_array($payment->order->status, ['cancelled', 'returned'], true)) {
                throw new \DomainException('الطلب ملغي أو مرتجع، لا يمكن تأكيد الدفع');
            }

            $payment->update([
                'status'           => 'paid',
                'transaction_id'   => $transactionId ?? $payment->transaction_id,
                'gateway_response' => $gatewayResponse ?? $payment->gateway_response,
                'paid_at'          => now(),
            ]);

            // الدفع اتأكد → الطلب يتأكد
            if ($payment->order->status === 'new') {
                $this->orderService->updateStatus($payment->order, 'confirmed');
            }

            return $payment->fresh();
        });
    }

    public function markFailed(Payment $payment, ?array $gatewayResponse = null): Payment
    {
        return $this->transition($payment, 'pending', 'failed', 'يمكن فشل الدفعات المعلقة فقط', $gatewayResponse);
    }

    public function markRefunded(Payment $payment, ?array $gatewayResponse = null): Payment
    {
        return $this->transition($payment, 'paid', 'refunded', 'يمكن استرداد الدفعات المؤكدة فقط', $gatewayResponse);
    }

    protected function transition(Payment $payment, string $from, string $to, string $error, ?array $gatewayResponse): Payment
    {
        return DB::transaction(function () use ($payment, $from, $to, $error, $gatewayResponse) {

            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === $to) {
                return $payment;
            }

            if ($payment->status !== $from) {
                throw new \DomainException($error);
            }

            $payment->update([
                'status'           => $to,
                'gateway_response' => $gatewayResponse ?? $payment->gateway_response,
            ]);

            return $payment->fresh();
        });
    }
}
