<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shipping;
use App\Services\ShippingGateways\ShippingGatewayManager;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingWebhookController extends Controller
{
    public function __construct(
        protected ShippingGatewayManager $gateways,
        protected ShippingService $shippingService
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

        if (!$result->valid || !$result->status) {
            return response()->json([
                'message' => $result->message ?? 'حدث شحن غير صالح',
            ], 422);
        }

        $shipping = $result->trackingNumber
            ? Shipping::where('tracking_number', $result->trackingNumber)->first()
            : null;

        if (!$shipping && $result->orderNumber) {
            $shipping = Shipping::whereHas(
                'order',
                fn ($q) => $q->where('order_number', $result->orderNumber)
            )->first();
        }

        if (!$shipping) {
            return response()->json(['message' => 'الشحنة غير موجودة'], 404);
        }

        try {
            $shipping = $this->shippingService->applyWebhookStatus($shipping, $result->status);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        AuditLog::record('shipping.webhook', $shipping, [
            'gateway' => $gateway,
            'status'  => $result->status,
        ]);

        return response()->json([
            'message'  => 'تم تحديث حالة الشحنة',
            'shipping' => $shipping->load('order:id,order_number,status'),
        ]);
    }
}
