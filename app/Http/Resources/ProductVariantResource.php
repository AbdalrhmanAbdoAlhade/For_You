<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStaff = $request->user()
            && in_array($request->user()->type, ['admin', 'employee'], true);

        $product = $this->relationLoaded('product') ? $this->product : null;

        return [
            'id'    => $this->id,
            'size'  => $this->size,
            'color' => $this->color,
            'label' => $this->label,
            'sku'   => $this->when($isStaff, $this->sku),

            'price'          => $this->price !== null ? (float) $this->price : null,
            'discount_price' => $this->discount_price !== null ? (float) $this->discount_price : null,

            'stock'    => $this->when($isStaff, $this->stock),
            'in_stock' => $this->is_in_stock,

            'is_active'  => $this->when($isStaff, $this->is_active),
            'sort_order' => $this->sort_order,
        ];
    }
}