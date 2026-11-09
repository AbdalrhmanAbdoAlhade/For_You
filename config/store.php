<?php

return [
    'default_payment_method' => env('DEFAULT_PAYMENT_METHOD', 'easykash'),
    'payment_methods'        => ['easykash'],

    'shipping' => [
        'flat_rate'  => (float) env('SHIPPING_FLAT_RATE', 50),
        // الشحن مجاني لو الطلب (بعد الخصم) وصل المبلغ ده. اتركه فاضي لإلغاء الميزة
        'free_over'  => env('SHIPPING_FREE_OVER') !== null ? (float) env('SHIPPING_FREE_OVER') : null,
        // أسعار خاصة بالمدن: 'القاهرة' => 40
        'city_rates' => [],
    ],

    // لازم الدفع يتأكد قبل إنشاء الشحنة
    'require_payment_before_shipping' => env('REQUIRE_PAYMENT_BEFORE_SHIPPING', true),
];
