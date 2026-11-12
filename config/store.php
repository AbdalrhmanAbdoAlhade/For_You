<?php

return [
    'default_payment_method' => env('DEFAULT_PAYMENT_METHOD', 'easykash'),
    'payment_methods'        => ['easykash'],

    // الطلب اللي فضل 'new' من غير دفع أكتر من المدة دي بيتلغي والمخزون بيرجع (0 = معطّل)
    'unpaid_order_ttl_hours' => (int) env('UNPAID_ORDER_TTL_HOURS', 24),

    'shipping' => [
        'flat_rate'  => (float) env('SHIPPING_FLAT_RATE', 50),
        // الشحن مجاني لو الطلب (بعد الخصم) وصل المبلغ ده. اتركه فاضي لإلغاء الميزة
        'free_over'  => env('SHIPPING_FREE_OVER') !== null ? (float) env('SHIPPING_FREE_OVER') : null,
        // أسعار خاصة بالمدن: 'القاهرة' => 40
        'city_rates' => [],
    ],

    // لازم اللي وافق على السحب غير اللي نفّذ التحويل (مبدأ الشخصين). خليه false لو عندك أدمن واحد بس
    'withdrawal_four_eyes' => env('WITHDRAWAL_FOUR_EYES', true),

    // لازم الدفع يتأكد قبل إنشاء الشحنة
    'require_payment_before_shipping' => env('REQUIRE_PAYMENT_BEFORE_SHIPPING', true),
];
