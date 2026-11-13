<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'shipping_company'  => $this->shipping_company,
            'tracking_number'   => $this->tracking_number,
            'status'            => $this->status,
            'status_label'      => __('orders.shipping_statuses.' . $this->status),
            'shipping_address'  => $this->shipping_address,
            'shipped_at'        => $this->shipped_at?->toDateTimeString(),
            'delivered_at'      => $this->delivered_at?->toDateTimeString(),
        ];
    }
}