<?php

namespace App\Console\Commands;

use App\Models\PasswordResetOtp;
use Illuminate\Console\Command;

class CleanupExpiredOtps extends Command
{
    protected $signature = 'otp:cleanup';
    protected $description = 'Delete OTP records that expired more than 24 hours ago';

    public function handle(): int
    {
        $count = PasswordResetOtp::where('expires_at', '<', now()->subDay())->delete();

        $this->info("Deleted {$count} expired OTP records");

        return self::SUCCESS;
    }
}
