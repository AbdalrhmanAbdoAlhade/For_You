<?php

return [
    'status_updated_title' => 'Order status updated',
    'status_updated'       => 'Your order #:number is now: :status',
    'commission_title'     => 'New commission',
    'commission_added'     => 'A commission of :amount EGP has been added to your wallet',
    'commission_pending'   => 'A commission of :amount EGP has been recorded and will be available on :date',

    'statuses' => [
        'new'                => 'New',
        'confirmed'          => 'Confirmed',
        'processing'         => 'Processing',
        'ready_for_shipping' => 'Ready for shipping',
        'shipped'            => 'Shipped',
        'delivered'          => 'Delivered',
        'cancelled'          => 'Cancelled',
        'returned'           => 'Returned',
    ],

    'payment_statuses' => [
        'pending'  => 'Pending',
        'paid'     => 'Paid',
        'failed'   => 'Failed',
        'refunded' => 'Refunded',
    ],

    'shipping_statuses' => [
        'pending'           => 'Pending',
        'picked_up'         => 'Picked up',
        'in_transit'        => 'In transit',
        'out_for_delivery'  => 'Out for delivery',
        'delivered'         => 'Delivered',
        'failed'            => 'Failed',
        'returned'          => 'Returned',
    ],

    'commission_statuses' => [
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'paid'      => 'Paid',
        'cancelled' => 'Cancelled',
    ],
];