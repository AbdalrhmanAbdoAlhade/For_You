<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productName = $this->relationLoaded('product') && $this->product
            ? $this->product->localized('name')
            : $this->product_name;

        return [
            'id'             => $this->id,
            'product_id'     => $this->product_id,
            'variant_id'     => $this->variant_id,
            'variant'        => [
                'id'    => $this->variant_id,
                'size'  => $this->variant_size,
                'color' => $this->variant_color,
                'sku'   => $this->variant_sku,
                'label' => $this->variant_label,
            ],
            'product_name'   => $productName,
            'price'          => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'quantity'       => $this->quantity,
            'total'          => (float) $this->total,
            'product'        => new ProductResource($this->whenLoaded('product')),
        ];
    }
}