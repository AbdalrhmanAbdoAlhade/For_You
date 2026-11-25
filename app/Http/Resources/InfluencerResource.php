<?php

namespace App\Http\Resources;

use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InfluencerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug ?? null,
            'avatar'         => $this->avatar ?? null,
            'type'           => $this->type,
            'is_active'      => (bool) $this->is_active,
            'preview_images' => $this->preview_images ?? [],
            'picks' => ProductResource::collection(
                $this->whenLoaded('influencerProducts')
            ),
            // يمكن إضافة أي حقول أخرى كانت موجودة مسبقاً هنا
        ];
    }
}
