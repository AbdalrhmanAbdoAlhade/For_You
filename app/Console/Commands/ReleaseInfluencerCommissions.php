<?php

namespace App\Console\Commands;

use App\Services\InfluencerCommissionService;
use Illuminate\Console\Command;

class ReleaseInfluencerCommissions extends Command
{
    protected $signature = 'commissions:release';

    protected $description = 'إتاحة العمولات المعلّقة اللي انتهت فترة الانتظار بتاعتها في محافظ المؤثرين';

    public function handle(InfluencerCommissionService $service): int
    {
        $released = $service->releaseDue();

        $this->info("تم إتاحة {$released} عمولة.");

        return self::SUCCESS;
    }
}
