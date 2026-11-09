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
            'title'    => 'عمولة جديدة',
            'message'  => "تم إضافة عمولة بقيمة {$this->totalAmount} ج.م إلى محفظتك",
            'amount'   => $this->totalAmount,
            'order_id' => $this->orderId,
            'type'     => 'new_commission',
        ];
    }
}
