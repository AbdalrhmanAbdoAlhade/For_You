<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'avatar'          => $this->avatar,
            'type'            => $this->type,
            'followers_count' => (int) ($this->followers_count ?? 0),
            'following_count' => (int) ($this->following_count ?? 0),
            'created_at'      => $this->created_at?->toDateTimeString(),
        ];
    }
}
