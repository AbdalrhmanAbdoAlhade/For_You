<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'order_number'       => $this->order_number,
            'status'             => $this->status,
            'status_label'       => __('orders.statuses.' . $this->status),
            'subtotal'           => (float) $this->subtotal,
            'discount'           => (float) $this->discount,
            'shipping_cost'      => (float) $this->shipping_cost,
            'total'              => (float) $this->total,
            'customer_name'      => $this->customer_name,
            'customer_phone'     => $this->customer_phone,
            'customer_email'     => $this->customer_email,
            'shipping_address'   => $this->shipping_address,
            'city'               => $this->city,
            'country'            => $this->country,
            'notes'              => $this->notes,
            'created_at'         => $this->created_at?->toDateTimeString(),

            // العلاقات
            'customer'           => new UserResource($this->whenLoaded('customer')),
            'influencer'         => new UserResource($this->whenLoaded('influencer')),
            'items'              => OrderItemResource::collection($this->whenLoaded('items')),
            'payment'            => new PaymentResource($this->whenLoaded('payment')),
            'shipping'           => new ShippingResource($this->whenLoaded('shipping')),
            'commissions'        => CommissionResource::collection($this->whenLoaded('commissions')),
        ];
    }
}
