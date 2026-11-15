<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'balance'         => (float) $this->balance,
            'total_earned'    => (float) $this->total_earned,
            'total_withdrawn' => (float) $this->total_withdrawn,
            'transactions'    => WalletTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
