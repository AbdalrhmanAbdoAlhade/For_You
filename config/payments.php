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
            // سر توقيع رابط الدفع (يرجع للـ webhook secret لو مش مضبوط)
            'initiate_secret'  => env('EASYKASH_INITIATE_SECRET'),
            /*
             * نافذة قبول timestamp الـ webhook بالثواني لمنع إعادة الإرسال.
             * 0 = معطّلة (السلوك القديم). فعّلها في الإنتاج (مثلاً 300).
             */
            'webhook_tolerance' => (int) env('EASYKASH_WEBHOOK_TOLERANCE', 0),
        ],
    ],

    // فرق مسموح به عند مطابقة المبلغ (بسبب تقريب العملات)
    'amount_tolerance' => 0.01,
];
