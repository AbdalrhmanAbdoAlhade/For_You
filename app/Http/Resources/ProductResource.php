<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStaff = $request->user()
            && in_array($request->user()->type, ['admin', 'employee'], true);

        return [
            'id'                => $this->id,
            'name'              => $this->localized('name'),
            'slug'              => $this->slug,
            'description'       => $this->localized('description'),
            'short_description' => $this->localized('short_description'),
            'price'             => (float) $this->price,
            'discount_price'    => $this->has_discount ? (float) $this->effective_price : null,
            'final_price'       => (float) $this->final_price,
            // المخزون والـ SKU للأدمن/الموظف فقط
            'stock'             => $this->when($isStaff, $this->stock),
            'sku'               => $this->when($isStaff, $this->sku),
            // للجمهور: in_stock بس
            'in_stock'          => $this->when(!$isStaff, $this->stock > 0),
            'images'            => $this->images ?? [],
            'is_featured'       => $this->is_featured,
            'is_active'         => $this->when($isStaff, $this->is_active),
            'category'          => new CategoryResource($this->whenLoaded('category')),
            'influencers'       => $this->when(
                $isStaff && $this->relationLoaded('influencers'),
                UserResource::collection($this->whenLoaded('influencers'))
            ),
            'created_at'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
