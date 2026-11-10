<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loaded = $this->relationLoaded('items');

        return [
            'id'          => $this->id,
            'items_count' => $this->items_count,
            'total'       => (float) $this->total,
            // لو true الفرونت يوضح للعميل ويمنع الـ Checkout لحد ما يصلحها
            'has_issues'  => $loaded && $this->items->contains(fn ($i) => $i->issue !== null),
            'items'       => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}