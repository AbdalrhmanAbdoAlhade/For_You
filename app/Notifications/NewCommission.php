
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCommission extends Notification
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public float $totalAmount,
        public ?string $availableAt = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isPending = $this->availableAt !== null;

        return [
            'title' => __('orders.commission_title', [], 'ar'),
            'title_en' => __('orders.commission_title', [], 'en'),

            'message' => $isPending
                ? __('orders.commission_pending', [
                    'amount' => number_format($this->totalAmount, 2),
                    'date' => $this->availableAt,
                ], 'ar')
                : __('orders.commission_added', [
                    'amount' => number_format($this->totalAmount, 2),
                ], 'ar'),

            'message_en' => $isPending
                ? __('orders.commission_pending', [
                    'amount' => number_format($this->totalAmount, 2),
                    'date' => $this->availableAt,
                ], 'en')
                : __('orders.commission_added', [
                    'amount' => number_format($this->totalAmount, 2),
                ], 'en'),

            'amount' => number_format($this->totalAmount, 2),
            'order_id' => $this->orderId,
            'available_at' => $this->availableAt,
            'status' => $isPending ? 'pending' : 'approved',
            'type' => 'new_commission',
        ];
    }
}
