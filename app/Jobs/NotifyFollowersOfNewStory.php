<?php

namespace App\Jobs;

use App\Models\InfluencerStory;
use App\Models\User;
use App\Notifications\NewStoryFromCreator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyFollowersOfNewStory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(public int $storyId) {}

    public function handle(): void
    {
        $story = InfluencerStory::find($this->storyId);

        if (!$story) {
            return;
        }

        // محاولة influencer relation (لو موجودة)، وfallback لـ query
        $influencer = null;
        try {
            $influencer = $story->influencer;
        } catch (\Throwable $e) {
            // relation مش موجودة أو فشلت — نكمل للـ fallback
        }

        $influencer ??= User::find($story->influencer_id);

        if (!$influencer) {
            return;
        }

        $influencer->followers()
            ->where('users.is_active', true)
            ->chunk(500, function ($followers) use ($story) {
                Notification::send($followers, new NewStoryFromCreator($story));
                Log::info('Notified followers of new story', [
                    'story_id'   => $story->id,
                    'chunk_size' => $followers->count(),
                ]);
            });
    }
}
