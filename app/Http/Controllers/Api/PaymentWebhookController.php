<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\PaymentGateways\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected PaymentService $payments
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        try {
            $driver = $this->gateways->driver($gateway);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        if (!$driver->verifySignature($request)) {
            return response()->json(['message' => 'توقيع الـ webhook غير صالح'], 401);
        }

        $result = $driver->handleWebhook($request);

        if ($result->isFailed()) {
            return $this->handleFailure($result, $request);
        }

        if (!$result->isPaid()) {
            // أحداث غير مدعومة (مثل pending) — نرجّع 200 حتى لا تعيد البوابة الإرسال.
            return response()->json([
                'message' => $result->message ?? 'تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        $order = $this->findOrder($result->orderNumber, $result->orderId);

        if (!$order) {
            // طلب غير معروف: نرجّع 200 حتى لا تعيد البوابة الإرسال بلا نهاية.
            AuditLog::record('payment.webhook.unknown_order', null, [
                'gateway'      => $gateway,
                'order_number' => $result->orderNumber,
                'order_id'     => $result->orderId,
            ]);

            return response()->json([
                'message' => 'الطلب غير موجود، تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        $tolerance = (float) config('payments.amount_tolerance', 0.01);

        if ($result->amount === null || abs((float) $order->total - (float) $result->amount) > $tolerance) {
            AuditLog::record('payment.webhook.amount_mismatch', $order, [
                'expected' => (float) $order->total,
                'received' => (float) $result->amount,
                'gateway'  => $gateway,
            ]);

            return response()->json([
                'message' => 'مبلغ الـ webhook لا يطابق إجمالي الطلب',
            ], 422);
        }

        $payment = $order->payment()->first();

        if (!$payment) {
            AuditLog::record('payment.webhook.missing_payment', $order, [
                'gateway' => $gateway,
            ]);

            return response()->json([
                'message' => 'لا يوجد سجل دفع لهذا الطلب، تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        if ($payment->payment_method !== $gateway) {
            AuditLog::record('payment.webhook.gateway_mismatch', $payment, [
                'expected_gateway' => $payment->payment_method,
                'received_gateway' => $gateway,
            ]);

            return response()->json([
                'message' => 'بوابة الـ webhook لا تطابق طريقة دفع الطلب، تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        // Idempotency: نفس الحدث قد يصل أكثر من مرة.
        if ($payment->status === 'paid') {
            return response()->json([
                'message' => 'الدفعة مؤكدة بالفعل',
                'status'  => 'already_paid',
                'payment' => $payment,
            ]);
        }

        try {
            $payment = $this->payments->markPaid(
                $payment,
                $result->transactionId,
                $result->raw
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record('payment.webhook.paid', $payment, [
            'gateway'        => $gateway,
            'order_id'       => $order->id,
            'transaction_id' => $result->transactionId,
            'amount'         => (float) $order->total,
        ]);

        return response()->json([
            'message' => 'تم تأكيد الدفع',
            'status'  => 'paid',
            'payment' => $payment->load('order:id,order_number,status'),
        ]);
    }

    protected function handleFailure($result, Request $request): JsonResponse
    {
        $order = $this->findOrder($result->orderNumber, $result->orderId);

        if (!$order) {
            AuditLog::record('payment.webhook.unknown_order', null, [
                'order_number' => $result->orderNumber,
                'order_id'     => $result->orderId,
                'status'       => 'failed',
            ]);

            return response()->json([
                'message' => 'الطلب غير موجود، تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        $tolerance = (float) config('payments.amount_tolerance', 0.01);

        // لو البوابة بعتت مبلغًا، لازم يطابق إجمالي الطلب.
        if ($result->amount !== null && abs((float) $order->total - (float) $result->amount) > $tolerance) {
            AuditLog::record('payment.webhook.amount_mismatch', $order, [
                'expected' => (float) $order->total,
                'received' => (float) $result->amount,
                'status'   => 'failed',
            ]);

            return response()->json([
                'message' => 'مبلغ الـ webhook لا يطابق إجمالي الطلب',
            ], 422);
        }

        $payment = $order->payment()->first();

        if (!$payment) {
            AuditLog::record('payment.webhook.missing_payment', $order, [
                'status' => 'failed',
            ]);

            return response()->json([
                'message' => 'لا يوجد سجل دفع لهذا الطلب، تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        if ($payment->payment_method !== $request->route('gateway')) {
            AuditLog::record('payment.webhook.gateway_mismatch', $payment, [
                'expected_gateway' => $payment->payment_method,
                'received_gateway' => $request->route('gateway'),
                'status'           => 'failed',
            ]);

            return response()->json([
                'message' => 'بوابة الـ webhook لا تطابق طريقة دفع الطلب، تم تجاهل الحدث',
                'status'  => 'ignored',
            ]);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'message' => 'الدفعة مؤكدة بالفعل، تم تجاهل حدث الفشل',
                'status'  => 'already_paid',
            ]);
        }

        try {
            $payment = $this->payments->markFailed($payment, $result->raw);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record('payment.webhook.failed', $payment, [
            'order_id' => $order->id,
            'message'  => $result->message,
        ]);

        return response()->json([
            'message' => 'تم تسجيل فشل الدفع',
            'status'  => 'failed',
            'payment' => $payment,
        ]);
    }

    protected function findOrder(?string $orderNumber, ?int $orderId): ?Order
    {
        if ($orderNumber) {
            return Order::where('order_number', $orderNumber)->first();
        }

        return $orderId ? Order::find($orderId) : null;
    }
}
