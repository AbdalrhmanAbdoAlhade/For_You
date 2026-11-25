<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->relationLoaded('influencerProfile')
            ? $this->influencerProfile
            : null;

        $stats = $this->stats ?? [];

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'avatar'      => $this->avatar,
            'handle'      => $profile?->handle,
            'bio'         => $profile?->bio,
            'social_links'=> $profile?->social_links ?? [],
            'cover_image'   => $profile?->cover_image,
            'instagram_url' => $profile?->instagram_url,
            'is_verified' => (bool) $this->is_verified,

            'stats' => [
                'followers_count' => (int) ($stats['followers_count'] ?? 0),
                'following_count' => (int) ($stats['following_count'] ?? 0),
                'picks_count'     => (int) ($stats['picks_count'] ?? 0),
                'products_count'  => (int) ($stats['products_count'] ?? 0),
                'reviews_count'   => (int) ($stats['reviews_count'] ?? 0),
                'reviews_avg'     => (float) ($stats['reviews_avg'] ?? 0),
            ],

            'is_following' => $this->is_following ?? null,

            'brands' => $this->relationLoaded('brands')
                ? BrandResource::collection($this->brands)
                : [],

            // placeholder — مفيش نظام campaigns حالياً
            'campaigns' => [],
        ];
    }
}