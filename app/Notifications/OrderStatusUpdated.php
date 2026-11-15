<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
       return [
    'title'        => __('orders.status_updated_title', [], 'ar'),
    'title_en'     => __('orders.status_updated_title', [], 'en'),
    'message'      => $this->message('ar'),
    'message_en'   => $this->message('en'),
    'order_id'     => $this->order->id,
    'order_number' => $this->order->order_number,
    'status'       => $this->order->status,
    'type'         => 'order_status',
];
    }
    protected function message(string $locale): string
{
    return __('orders.status_updated', [
        'number' => $this->order->order_number,
        'status' => __('orders.statuses.' . $this->order->status, [], $locale),
    ], $locale);
}
}
