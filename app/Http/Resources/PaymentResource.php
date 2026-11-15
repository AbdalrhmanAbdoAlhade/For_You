<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'payment_method'   => $this->payment_method,
            'transaction_id'   => $this->transaction_id,
            'amount'           => (float) $this->amount,
            'status'           => $this->status,
            'status_label'     => __('orders.payment_statuses.' . $this->status),
            'paid_at'          => $this->paid_at?->toDateTimeString(),
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}