<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // مفيش Accept-Language صريح = رجّع اللغتين
        $bothLangs = ! $request->hasHeader('Accept-Language');

        return [
            'id'             => $this->id,

            'name'           => $bothLangs ? $this->name : $this->localized('name'),
            $this->mergeWhen($bothLangs, [
                'name_en'        => $this->name_en,
                'description_en' => $this->description_en,
            ]),

            'slug'            => $this->slug,
            'logo'            => $this->logo,
            'description'     => $bothLangs ? $this->description : $this->localized('description'),
            'sort_order'      => $this->sort_order,
            'is_active'       => $this->is_active,
            'products_count'  => $this->whenCounted('products'),
            'followers_count' => $this->whenCounted('followers'),
            'is_following'    => $this->is_following ?? null,
            'rating_avg'      => (float) $this->rating_avg,
            'reviews_count'   => (int) $this->reviews_count,
        ];
    }
}