<?php

return [
    'default_payment_method' => env('DEFAULT_PAYMENT_METHOD', 'manual'),
    'payment_methods'        => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_METHODS', 'manual,easykash'))
    ))),

    // بوابة الشحن الافتراضية (manual = نفس السلوك الحالي)
    'shipping_gateway' => env('SHIPPING_GATEWAY', 'manual'),
    'shipping_webhook_secret' => env('SHIPPING_WEBHOOK_SECRET'),
    'shipping_signature_header' => env('SHIPPING_SIGNATURE_HEADER', 'X-Shipping-Signature'),

    // الطلب اللي فضل 'new' من غير دفع أكتر من المدة دي بيتلغي والمخزون بيرجع (0 = معطّل)
    'unpaid_order_ttl_hours' => (int) env('UNPAID_ORDER_TTL_HOURS', 24),

    /*
     * طرق الدفع المؤهلة للإلغاء التلقائي عند عدم الدفع.
     * الدفع اليدوي/COD لا يُلغى تلقائيًا لأن التحصيل بيحصل عند التسليم.
     */
    'expire_unpaid_payment_methods' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EXPIRE_UNPAID_PAYMENT_METHODS', 'easykash'))
    ))),

    'shipping' => [
        'flat_rate'  => (float) env('SHIPPING_FLAT_RATE', 50),
        // الشحن مجاني لو الطلب (بعد الخصم) وصل المبلغ ده. اتركه فاضي لإلغاء الميزة
        'free_over'  => env('SHIPPING_FREE_OVER') !== null ? (float) env('SHIPPING_FREE_OVER') : null,
        // أسعار خاصة بالمدن: 'القاهرة' => 40
        'city_rates' => [],

        // NEW: طرق الشحن
        'methods' => [
            'standard' => [
                'key'      => 'standard',
                'label'    => env('SHIPPING_STANDARD_LABEL', 'Standard'),
                'cost'     => (float) env('SHIPPING_STANDARD_COST', 60),
                'eta_days' => (int) env('SHIPPING_STANDARD_ETA_DAYS', 4),
            ],
            'express' => [
                'key'      => 'express',
                'label'    => env('SHIPPING_EXPRESS_LABEL', 'Express'),
                'cost'     => (float) env('SHIPPING_EXPRESS_COST', 120),
                'eta_days' => (int) env('SHIPPING_EXPRESS_ETA_DAYS', 1),
            ],
        ],
        'default_method' => env('SHIPPING_DEFAULT_METHOD', 'standard'),

        // NEW: COD
        'cod_fee'            => (float) env('SHIPPING_COD_FEE', 10),
        'cod_payment_method' => env('SHIPPING_COD_PAYMENT_METHOD', 'manual'),
    ],

    // لازم اللي وافق على السحب غير اللي نفّذ التحويل (مبدأ الشخصين). خليه false لو عندك أدمن واحد بس
    'withdrawal_four_eyes' => env('WITHDRAWAL_FOUR_EYES', true),

    // لازم الدفع يتأكد قبل إنشاء الشحنة
    'require_payment_before_shipping' => env('REQUIRE_PAYMENT_BEFORE_SHIPPING', true),

    // نفس الزائر مايتحسبش click تاني لنفس الرابط قبل مرور المدة دي
    'link_click_ttl_minutes' => (int) env('LINK_CLICK_TTL_MINUTES', 60),

    // تفعيل التحقق من الإيميل للعملاء (skeleton — افتراضيًا معطّل)
    'email_verification' => [
        'enabled' => (bool) env('EMAIL_VERIFICATION_ENABLED', false),
    ],

    // ===== قصص المؤثرين (Stories) =====
    // أقصى عدد قصص للمؤثر الواحد خلال فترة story_ttl_hours
    'story_daily_limit'      => (int) env('STORY_DAILY_LIMIT', 10),
    // مدة حياة القصة قبل ما تختفي وتتمسح
    'story_ttl_hours'        => (int) env('STORY_TTL_HOURS', 24),
    // مدة عرض القصة الواحدة قبل الانتقال للي بعدها (بالثواني)
    'story_duration_seconds' => (int) env('STORY_DURATION_SECONDS', 5),
    // أقصى عدد منتجات في القصة الواحدة
    'story_max_products'     => (int) env('STORY_MAX_PRODUCTS', 10),
];