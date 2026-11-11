<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCommission extends Notification
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public float $totalAmount
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
          'title'      => __('orders.commission_title', [], 'ar'),
            'title_en'   => __('orders.commission_title', [], 'en'),
            'message'    => __('orders.commission_added', ['amount' => $this->totalAmount], 'ar'),
            'message_en' => __('orders.commission_added', ['amount' => $this->totalAmount], 'en'),
            'amount'   => $this->totalAmount,
            'order_id' => $this->orderId,
            'type'     => 'new_commission',
        ];
    }
}
