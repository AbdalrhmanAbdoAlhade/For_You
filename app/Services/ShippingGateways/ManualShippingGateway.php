<?php

namespace App\Services\ShippingGateways;

use App\Contracts\ShippingGatewayInterface;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * الشحن اليدوي (السلوك الحالي). الإدمن يدخل الشركة ورقم التتبع،
 * وwebhook الشحن لاحقًا يحدّث الحالة بنفس مسار ShippingService.
 */
class ManualShippingGateway implements ShippingGatewayInterface
{
    public function name(): string
    {
        return 'manual';
    }

    public function createShipment(Order $order, array $data): array
    {
        return [
            'gateway'          => $this->name(),
            'shipping_company' => (string) ($data['shipping_company'] ?? 'Manual'),
            'tracking_number'  => $data['tracking_number'] ?? null,
            'status'           => 'pending',
        ];
    }

    public function handleWebhook(Request $request): ShippingResult
    {
        $payload = $request->json()->all();

        if (!is_array($payload) || empty($payload['status'])) {
            return ShippingResult::invalid('payload الشحن غير صالح', []);
        }

        $tracking = isset($payload['tracking_number']) ? (string) $payload['tracking_number'] : null;
        $orderNumber = isset($payload['order_number']) ? (string) $payload['order_number'] : null;

        if (!$tracking && !$orderNumber) {
            return ShippingResult::invalid('tracking_number أو order_number مطلوب', $payload);
        }

        return ShippingResult::ok(
            $tracking,
            $orderNumber,
            strtolower((string) $payload['status']),
            $payload
        );
    }

    public function verifySignature(Request $request): bool
    {
        $secret = (string) config('store.shipping_webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('X-Shipping-Signature', '');
        $signature = preg_replace('/^sha256=/i', '', $signature) ?? '';

        if ($signature === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            strtolower($signature)
        );
    }
}
