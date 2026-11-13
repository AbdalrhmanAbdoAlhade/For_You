<?php

return [
    'status_updated_title' => 'تحديث حالة الطلب',
    'status_updated'       => 'تم تحديث حالة طلبك رقم :number إلى: :status',
    'commission_title'     => 'عمولة جديدة',
    'commission_added'     => 'تم إضافة عمولة بقيمة :amount ج.م إلى محفظتك',
    'commission_pending'   => 'تم تسجيل عمولة بقيمة :amount ج.م وستكون متاحة في :date',

    'statuses' => [
        'new'                => 'جديد',
        'confirmed'          => 'تم التأكيد',
        'processing'         => 'قيد التجهيز',
        'ready_for_shipping' => 'جاهز للشحن',
        'shipped'            => 'تم الشحن',
        'delivered'          => 'تم التسليم',
        'cancelled'          => 'ملغي',
        'returned'           => 'مرتجع',
    ],

    'payment_statuses' => [
        'pending'  => 'قيد الانتظار',
        'paid'     => 'مدفوع',
        'failed'   => 'فشل',
        'refunded' => 'مسترد',
    ],

    'shipping_statuses' => [
        'pending'           => 'قيد الانتظار',
        'picked_up'         => 'تم الاستلام',
        'in_transit'        => 'في الطريق',
        'out_for_delivery'  => 'خارج للتسليم',
        'delivered'         => 'تم التسليم',
        'failed'            => 'فشل',
        'returned'          => 'مرتجع',
    ],

    'commission_statuses' => [
        'pending'   => 'قيد الانتظار',
        'approved'  => 'معتمدة',
        'paid'      => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ],
];