<?php

namespace App\Notifications;

use App\Models\InfluencerStory;
use App\Notifications\Channels\FcmChannel;
use App\Support\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewStoryFromCreator extends Notification
{
    use Queueable;

    public function __construct(public InfluencerStory $story) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** يجيب المؤثر — بيدعم influencer() أو user() relation، fallback لـ query مباشر */
   protected function resolveCreatorName(): string
{
    try {
        $creator = $this->story->influencer;
        if ($creator) {
            return $creator->name ?? '';
        }
    } catch (\Throwable $e) {
        // relation مش موجودة
    }

    return \App\Models\User::find($this->story->influencer_id)?->name ?? __('مؤثر');
}
    public function toArray(object $notifiable): array
    {
        $creatorName = $this->resolveCreatorName();

        return [
            'title'        => __('ستوري جديدة'),
            'title_en'     => __('New Story'),
            'message'      => __(':name نشر ستوري جديدة', ['name' => $creatorName]),
            'message_en'   => __(':name posted a new story', ['name' => $creatorName]),
            'type'         => NotificationType::CREATOR_STORY,
            'story_id'     => $this->story->id,
            'creator_id'   => $this->story->influencer_id,
            'creator_name' => $creatorName,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => __('ستوري جديدة'),
            'body'  => __(':name نشر ستوري جديدة', ['name' => $this->resolveCreatorName()]),
            'data'  => [
                'type'       => NotificationType::CREATOR_STORY,
                'story_id'   => (string) $this->story->id,
                'creator_id' => (string) $this->story->influencer_id,
            ],
        ];
    }
}
