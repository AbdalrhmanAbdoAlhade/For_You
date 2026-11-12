<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'amount'        => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'description'   => $this->description,
            'status'        => $this->status,
            'order_id'      => $this->order_id,
            'created_at'    => $this->created_at?->toDateTimeString(),
        ];
    }
}
