<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'order_id'              => $this->order_id,
            'product_id'            => $this->product_id,
            'order_total'           => (float) $this->order_total,
            'commission_percentage' => (float) $this->commission_percentage,
            'commission_amount'     => (float) $this->commission_amount,
            'status'                => $this->status,
            'paid_at'               => $this->paid_at?->toDateTimeString(),
            'created_at'            => $this->created_at?->toDateTimeString(),

            'order'   => new OrderResource($this->whenLoaded('order')),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
