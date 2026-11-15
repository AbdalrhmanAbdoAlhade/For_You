<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'product_id'      => $this->product_id,
            'quantity'        => $this->quantity,
            'total'           => (float) $this->total,
            'issue'           => $this->issue,                       // null | unavailable | insufficient_stock
            'available_stock' => $this->product?->stock,
            // المنتج المحذوف بيرجع null
            'product'         => $this->relationLoaded('product') && $this->product
                                    ? new ProductResource($this->product)
                                    : null,
        ];
    }
}