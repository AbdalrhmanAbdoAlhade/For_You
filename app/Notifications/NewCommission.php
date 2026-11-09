<?php

namespace App\Notifications;

use App\Models\InfluencerCommission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCommission extends Notification
{
    use Queueable;

    public function __construct(
        public InfluencerCommission $commission
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'             => 'عمولة جديدة',
            'message'           => "تم إضافة عمولة بقيمة {$this->commission->commission_amount} ج.م إلى محفظتك",
            'commission_id'     => $this->commission->id,
            'amount'            => $this->commission->commission_amount,
            'order_id'          => $this->commission->order_id,
            'type'              => 'new_commission',
        ];
    }
}
