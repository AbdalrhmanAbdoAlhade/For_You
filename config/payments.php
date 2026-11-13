<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    | البوابة الافتراضية للطلبات الجديدة. القيمة الحالية skeleton للتطوير.
    | لتفعيل بوابة حقيقية: أضف driver جديد في PaymentGatewayManager وحدد
    | الـ secret وبيانات الـ merchant من الـ environment.
    */
    'default' => env('PAYMENT_GATEWAY', 'manual'),

    'gateways' => [
        'manual' => [
            // الدفع اليدوي: يؤكده الإدمن من لوحة التحكم، بلا webhooks.
        ],
        'easykash' => [
            // سر توقيع الـ webhook (HMAC SHA-256 على الـ raw body)
            'secret'           => env('EASYKASH_WEBHOOK_SECRET'),
            'signature_header' => env('EASYKASH_SIGNATURE_HEADER', 'X-EasyKash-Signature'),
            'merchant_id'      => env('EASYKASH_MERCHANT_ID'),
            'initiate_url'     => env('EASYKASH_INITIATE_URL'),
            'mode'             => env('EASYKASH_MODE', 'sandbox'),
        ],
    ],

    // فرق مسموح به عند مطابقة المبلغ (بسبب تقريب العملات)
    'amount_tolerance' => 0.01,
];
