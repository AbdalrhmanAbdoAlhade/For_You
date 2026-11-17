<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(protected FcmService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = method_exists($notifiable, 'routeNotificationForFcm')
            ? $notifiable->routeNotificationForFcm()
            : [];

        if (empty($tokens)) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        if (empty($payload)) {
            return;
        }

        $this->fcm->send($tokens, $payload);
    }
}
