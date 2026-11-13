<?php

namespace App\Contracts;

use App\Models\Order;
use App\Services\PaymentGateways\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /** اسم البوابة كما يُستخدم في المسار /webhooks/payments/{gateway} */
    public function name(): string;

    /**
     * بدء عملية دفع. يرجّع بيانات يحتاجها الفرونت (payment_url / reference).
     * لا يغيّر حالة الدفع إلى paid.
     */
    public function initiate(Order $order): array;

    /**
     * قراءة وتحليل الـ webhook فقط (بدون تعديل الدفع).
     * التحقق من التوقيع مسؤولية verifySignature.
     */
    public function handleWebhook(Request $request): PaymentResult;

    /** التحقق من توقيع البوابة على الـ raw body */
    public function verifySignature(Request $request): bool;
}
