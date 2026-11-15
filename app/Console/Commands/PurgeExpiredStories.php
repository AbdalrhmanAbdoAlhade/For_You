<?php

namespace App\Console\Commands;

use App\Services\InfluencerStoryService;
use Illuminate\Console\Command;

class PurgeExpiredStories extends Command
{
    protected $signature = 'stories:purge';

    protected $description = 'مسح قصص المؤثرين المنتهية (بعد 24 ساعة) مع صورها';

    public function handle(InfluencerStoryService $stories): int
    {
        $count = $stories->purgeExpired();

        $this->info("تم مسح {$count} قصة منتهية.");

        return self::SUCCESS;
    }
}