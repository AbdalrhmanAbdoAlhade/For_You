<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'name_en'          => $this->name_en ?? null,
            'slug'             => $this->slug,
            'logo'             => $this->logo,
            'description'      => $this->description,
            'description_en'   => $this->description_en ?? null,
            'is_active'        => (bool) $this->is_active,
            'is_brand_of_week' => (bool) $this->is_brand_of_week,
            'is_following'     => $this->is_following ?? null,
            'preview_images'   => $this->preview_images ?? [],
            'category_id'      => $this->category_id ?? null,
            'sort_order'       => $this->sort_order ?? 0,
            'rating_avg'       => $this->rating_avg ?? 0.0,
            'reviews_count'    => $this->reviews_count ?? 0,
        ];
    }
}
