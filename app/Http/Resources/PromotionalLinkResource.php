<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionalLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'full_url'    => $this->full_url,
            'clicks'      => $this->clicks,
            'conversions' => $this->conversions,
            'is_active'   => $this->is_active,
            'expires_at'  => $this->expires_at?->toDateTimeString(),
            'product'     => new ProductResource($this->whenLoaded('product')),
            'created_at'  => $this->created_at?->toDateTimeString(),
        ];
    }
}
