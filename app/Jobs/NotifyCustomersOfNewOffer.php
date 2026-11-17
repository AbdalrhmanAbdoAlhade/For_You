<?php

namespace App\Jobs;

use App\Models\Coupon;
use App\Models\User;
use App\Notifications\NewOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class NotifyCustomersOfNewOffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(public int $couponId) {}

    public function handle(): void
    {
        $coupon = Coupon::find($this->couponId);
        if (!$coupon) {
            return;
        }

        User::query()
            ->where('type', 'customer')
            ->where('is_active', true)
            ->chunkById(500, function ($customers) use ($coupon) {
                Notification::send($customers, new NewOffer($coupon));
            });
    }
}
