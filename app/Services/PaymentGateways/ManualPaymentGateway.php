<?php

namespace App\Services\PaymentGateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * الدفع اليدوي (السلوك الافتراضي): لا توجد بوابة خارجية.
 *
 * - initiate(): يرجّع مرجعًا داخليًا وتعليمات الدفع، ويظل الدفع pending
 *   حتى يؤكده الإدمن من لوحة التحكم.
 * - handleWebhook(): غير مدعوم؛ أي webhook يُتجاهل.
 * - verifySignature(): يرفض كل الطلبات لأن البوابة لا تستقبل webhooks.
 */
class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function initiate(Order $order): array
    {
        $reference = 'MAN-' . strtoupper(Str::random(14));
        $payment = $order->payment;

        if ($payment) {
            $response = (array) ($payment->gateway_response ?? []);
            $response['initiated_reference'] = $reference;
            $response['gateway'] = $this->name();
            $payment->update(['gateway_response' => $response]);
        }

        return [
            'gateway'               => $this->name(),
            'status'                => 'pending',
            'transaction_reference' => $reference,
            'payment_url'           => null,
            'instructions'          => 'سيتم تأكيد الدفع يدويًا من إدارة المتجر بعد التحويل.',
            'amount'                => (float) $order->total,
            'currency'              => 'EGP',
        ];
    }

    public function handleWebhook(Request $request): PaymentResult
    {
        return PaymentResult::ignored('بوابة الدفع اليدوي لا تستقبل webhooks', []);
    }

    public function verifySignature(Request $request): bool
    {
        return false;
    }
}
