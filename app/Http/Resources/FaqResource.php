<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'question'   => $this->localized('question'),
            'answer'     => $this->localized('answer'),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
