<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->localized('name'),
            'slug'           => $this->slug,
            'description'    => $this->localized('description'),
            'image'          => $this->image,
            'sort_order'     => $this->sort_order,
            'is_active'      => $this->is_active,
            'parent_id'      => $this->parent_id,
            'parent'         => new CategoryResource($this->whenLoaded('parent')),
            'children'       => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->whenCounted('products'),
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
