<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->localized('name'),
            'slug'              => $this->slug,
            'description'       => $this->localized('description'),
            'short_description' => $this->localized('short_description'),
            'price'             => (float) $this->price,
             'discount_price'    => $this->has_discount ? (float) $this->effective_price : null,
            'final_price'       => (float) $this->final_price,
            'stock'             => $this->stock,
            'sku'               => $this->sku,
            'images'            => $this->images ?? [],
            'is_featured'       => $this->is_featured,
            'is_active'         => $this->is_active,
            'category'          => new CategoryResource($this->whenLoaded('category')),
            'influencers'       => UserResource::collection($this->whenLoaded('influencers')),
            'created_at'        => $this->created_at?->toDateTimeString(),
            
        ];
    }
}
