<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Skeleton لبوابة EasyKash.
 *
 * - initiate(): في local يرجّع مرجع وهمي وحالة pending (بدون اتصال خارجي).
 * - handleWebhook(): يقرأ payload موحّد:
 *   { order_number, amount, status: paid|failed, transaction_id }
 * - verifySignature(): HMAC SHA-256 على الـ raw body باستخدام EASYKASH_WEBHOOK_SECRET.
 */
class EasyKashGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'easykash';
    }

    public function initiate(Order $order): array
    {
        $reference = 'EK-' . strtoupper(Str::random(14));
        $payment = $order->payment;

        if ($payment) {
            $response = (array) ($payment->gateway_response ?? []);
            $response['initiated_reference'] = $reference;
            $response['gateway'] = $this->name();
            $payment->update(['gateway_response' => $response]);
        }

        // رابط الدفع يُبنى من EASYKASH_INITIATE_URL (لو مضبوط).
        $baseUrl = (string) config('payments.gateways.easykash.initiate_url', '');
        $paymentUrl = null;

        if ($baseUrl !== '') {
            $params = [
                'merchant_id'  => (string) config('payments.gateways.easykash.merchant_id', ''),
                'order_number' => $order->order_number,
                'amount'       => number_format((float) $order->total, 2, '.', ''),
                'currency'     => 'EGP',
                'reference'    => $reference,
                'timestamp'    => now()->timestamp,
            ];

            // توقيع الرابط حتى لا يقدر حد يعبث بالمبلغ/رقم الطلب.
            $secret = (string) (
                config('payments.gateways.easykash.initiate_secret')
                ?: config('payments.gateways.easykash.secret')
            );

            if ($secret !== '') {
                $params['signature'] = $this->signParams($params, $secret);
            }

            $paymentUrl = $baseUrl
                . (str_contains($baseUrl, '?') ? '&' : '?')
                . http_build_query($params);
        }

        return [
            'gateway'              => $this->name(),
            'status'               => 'pending',
            'transaction_reference' => $reference,
            'payment_url'          => $paymentUrl,
            'amount'               => (float) $order->total,
            'currency'             => 'EGP',
            'mode'                 => config('payments.gateways.easykash.mode', 'sandbox'),
        ];
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        $payload = $request->json()->all();

        if (!is_array($payload) || $payload === []) {
            return PaymentResult::ignored('payload فارغ أو غير صالح', []);
        }

        $status = strtolower((string) ($payload['status'] ?? ''));
        $orderNumber = isset($payload['order_number']) ? (string) $payload['order_number'] : null;
        $orderId = isset($payload['order_id']) ? (int) $payload['order_id'] : null;
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        $transactionId = isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null;

        if ($status === 'failed' || $status === 'cancelled') {
            return PaymentResult::failed(
                $orderNumber,
                $orderId,
                $amount,
                (string) ($payload['message'] ?? 'الدفع فشل من البوابة'),
                $payload
            );
        }

        if (!in_array($status, ['paid', 'success', 'succeeded', 'completed'], true)) {
            return PaymentResult::ignored('حالة الدفع غير معروفة: ' . $status, $payload);
        }

        if (!$orderNumber && !$orderId) {
            return PaymentResult::ignored('الـ webhook لا يحتوي على order_number أو order_id', $payload);
        }

        if ($amount === null || $amount <= 0) {
            return PaymentResult::ignored('مبلغ الدفع غير صالح', $payload);
        }

        if (!$transactionId) {
            return PaymentResult::ignored('رقم عملية الدفع مطلوب', $payload);
        }

        return PaymentResult::paid($orderNumber, $orderId, $amount, $transactionId, $payload);
    }

    public function verifySignature(Request $request): bool
    {
        $secret = (string) config('payments.gateways.easykash.secret');

        // بدون secret لا نقبل أي webhook (إلا في بيئة testing مع secret مضبوط في الاختبار).
        if ($secret === '') {
            return false;
        }

        $header = (string) config(
            'payments.gateways.easykash.signature_header',
            'X-EasyKash-Signature'
        );

        $signature = (string) $request->header($header, '');
        $signature = preg_replace('/^sha256=/i', '', $signature) ?? '';

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, strtolower($signature))) {
            return false;
        }

        /*
         * حماية اختيارية من إعادة الإرسال (replay): لو webhook_tolerance > 0
         * لازم الـ payload يحتوي timestamp قريب من الوقت الحالي.
         */
        $tolerance = (int) config('payments.gateways.easykash.webhook_tolerance', 0);

        if ($tolerance > 0) {
            $payload = $request->json()->all();
            $timestamp = is_array($payload) ? ($payload['timestamp'] ?? null) : null;

            if (!is_numeric($timestamp)
                || abs(now()->timestamp - (int) $timestamp) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    /**
     * توقيع معاملات رابط الدفع: ترتيب أبجدي ثم HMAC SHA-256.
     *
     * @param array<string, mixed> $params
     */
    protected function signParams(array $params, string $secret): string
    {
        unset($params['signature']);
        ksort($params);

        return hash_hmac(
            'sha256',
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            $secret
        );
    }
}
