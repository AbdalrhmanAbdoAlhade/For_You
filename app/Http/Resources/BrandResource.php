<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->localized('name'),
            'slug'           => $this->slug,
            'logo'           => $this->logo,
            'description'    => $this->localized('description'),
            'sort_order'     => $this->sort_order,
            'is_active'      => $this->is_active,
            'products_count' => $this->whenCounted('products'),
          'rating_avg'    => (float) $this->rating_avg,
'reviews_count' => (int) $this->reviews_count,
        ];
    }
}