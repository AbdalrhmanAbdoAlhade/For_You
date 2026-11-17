<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp,
        public int $expiresInMinutes = 10,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('كود إعادة تعيين كلمة المرور'))
            ->greeting(__('مرحبًا :name', ['name' => $notifiable->name ?? '']))
            ->line(__('استخدم الكود التالي لإعادة تعيين كلمة المرور:'))
            ->line(new \Illuminate\Support\HtmlString(
                '<div style="font-size:24px;font-weight:bold;letter-spacing:4px;text-align:center;padding:12px;background:#f3f4f6;border-radius:8px;">' . e($this->otp) . '</div>'
            ))
            ->line(__('هذا الكود صالح لمدة :min دقائق.', ['min' => $this->expiresInMinutes]))
            ->line(__('لو مش إنت اللي طلبت ده، تجاهل الرسالة.'));
    }
}
