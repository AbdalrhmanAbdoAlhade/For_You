<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * عنصر في صف "Creators you follow".
 * has_unseen = حلقة ذهبية | has_stories فقط = حلقة عادية | من غير قصص = رمادي.
 */
class StoryRailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'avatar'          => $this->avatar,
            'has_stories'     => (bool) $this->has_stories,
            'has_unseen'      => (bool) $this->has_unseen,
            'stories_count'   => (int) $this->stories_count,
            'latest_story_at' => $this->latest_story_at
                ? \Illuminate\Support\Carbon::parse($this->latest_story_at)->toIso8601String()
                : null,
        ];
    }
}