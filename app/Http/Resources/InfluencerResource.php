<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InfluencerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'is_active'  => $this->is_active,
            'avatar'     => $this->avatar,
            'created_at' => $this->created_at?->toDateTimeString(),

            'wallet'             => new WalletResource($this->whenLoaded('wallet')),
            'products'           => ProductResource::collection($this->whenLoaded('influencerProducts')),
            'promotional_links'  => PromotionalLinkResource::collection($this->whenLoaded('promotionalLinks')),
            'commissions'        => CommissionResource::collection($this->whenLoaded('commissions')),
        ];
    }
}
