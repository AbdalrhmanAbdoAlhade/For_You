<?php

namespace App\Notifications;

use App\Models\Coupon;
use App\Notifications\Channels\FcmChannel;
use App\Support\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOffer extends Notification
{
    use Queueable;

    public function __construct(public Coupon $coupon) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $label = $this->coupon->code ?? $this->coupon->name ?? '#' . $this->coupon->id;

        return [
            'title'        => __('عرض جديد'),
            'title_en'     => __('New Offer'),
            'message'      => __('كوبون خصم جديد متاح: :code', ['code' => $label]),
            'message_en'   => __('New coupon available: :code', ['code' => $label]),
            'type'         => NotificationType::NEW_OFFER,
            'coupon_id'    => $this->coupon->id,
            'coupon_code'  => $this->coupon->code,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $label = $this->coupon->code ?? '#' . $this->coupon->id;

        return [
            'title' => __('عرض جديد'),
            'body'  => __('كوبون خصم جديد متاح: :code', ['code' => $label]),
            'data'  => [
                'type'        => NotificationType::NEW_OFFER,
                'coupon_id'   => (string) $this->coupon->id,
                'coupon_code' => (string) ($this->coupon->code ?? ''),
            ],
        ];
    }
}
