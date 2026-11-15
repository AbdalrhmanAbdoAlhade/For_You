<?php

namespace App\Contracts;

use App\Models\Order;
use App\Services\ShippingGateways\ShippingResult;
use Illuminate\Http\Request;

interface ShippingGatewayInterface
{
    public function name(): string;

    /**
     * إنشاء شحنة عند البوابة. manual بيرجّع نفس بيانات الإدخال.
     *
     * @return array{gateway: string, shipping_company: string, tracking_number: ?string, status: string}
     */
    public function createShipment(Order $order, array $data): array;

    /** قراءة webhook الشحن فقط (بدون تعديل الحالة). */
    public function handleWebhook(Request $request): ShippingResult;

    public function verifySignature(Request $request): bool;
}
