<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'items_count' => $this->items_count,
            'total'       => (float) $this->total,
            'items'       => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
