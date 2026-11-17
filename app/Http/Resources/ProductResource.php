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

        $brand = $this->brand;
        $showBrand = $brand && ($isStaff || $brand->is_active);

        return [
            'id'                => $this->id,
            'name'              => $this->localized('name'),
            'slug'              => $this->slug,
            'description'       => $this->localized('description'),
            'short_description' => $this->localized('short_description'),
            'price'             => (float) $this->price,
            'discount_price'    => $this->has_discount ? (float) $this->effective_price : null,
            'final_price'       => (float) $this->final_price,
            'stock'             => $this->when($isStaff, $this->stock),
            'sku'               => $this->when($isStaff, $this->sku),
            'rating_avg'        => (float) $this->rating_avg,
            'reviews_count'     => (int) $this->reviews_count,
            'has_variants'      => (bool) (
                $this->relationLoaded('variants')
                    ? $this->variants->isNotEmpty()
                    : ($this->variants_count ?? 0) > 0
            ),
            'variants'          => ProductVariantResource::collection($this->whenLoaded('variants')),
            'in_stock'          => $this->when(!$isStaff, $this->stock > 0),
            'images'            => $this->images ?? [],
            'is_featured'       => $this->is_featured,
            'is_active'         => $this->when($isStaff, $this->is_active),
            'category'          => new CategoryResource($this->whenLoaded('category')),
            'brand'             => $showBrand ? new BrandResource($brand) : null,
            'likes_count'       => $this->whenCounted('likers'),
            'influencers'       => $this->when(
                $isStaff && $this->relationLoaded('influencers'),
                UserResource::collection($this->whenLoaded('influencers'))
            ),
            'created_at'        => $this->created_at?->toDateTimeString(),
        ];
    }
}
