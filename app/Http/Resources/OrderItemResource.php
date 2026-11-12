<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'product_id'     => $this->product_id,
            'product_name'   => $this->product_name,
            'price'          => (float) $this->price,
            'discount_price' => $this->discount_price ? (float) $this->discount_price : null,
            'quantity'       => $this->quantity,
            'total'          => (float) $this->total,
            'product'        => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
