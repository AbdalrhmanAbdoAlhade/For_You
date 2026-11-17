<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use App\Support\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
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
            'type'         => NotificationType::ORDER_STATUS,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => __('orders.status_updated_title', [], 'ar'),
            'body'  => $this->message('ar'),
            'data'  => [
                'type'         => NotificationType::ORDER_STATUS,
                'order_id'     => (string) $this->order->id,
                'order_number' => $this->order->order_number,
                'status'       => $this->order->status,
            ],
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
