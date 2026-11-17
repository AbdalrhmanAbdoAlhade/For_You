<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'rating'               => $this->rating,
            'body'                 => $this->body,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'is_creator'           => (bool) $this->is_creator,
            'created_at'           => $this->created_at?->toIso8601String(),

            'user' => $this->whenLoaded('user', fn () => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'avatar' => $this->user->avatar,
                'type'   => $this->user->type,
            ]),

            'images' => $this->whenLoaded('images', fn () =>
                $this->images->map(fn ($img) => [
                    'id'    => $img->id,
                    'image' => $img->image,
                ])->values()
            ),

            // بيظهر في تقييمات البراند وبروفايل الكريتور
            'product' => $this->whenLoaded('product', fn () => [
                'id'     => $this->product->id,
                'name'   => $this->product->localized('name'),
                'images' => $this->product->images,
            ]),
        ];
    }
}