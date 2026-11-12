<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'type'       => $this->type,
            'is_active'  => $this->is_active,
            'avatar'     => $this->avatar,
            'created_at' => $this->created_at?->toDateTimeString(),

            // علاقات اختيارية
            'roles'      => RoleResource::collection($this->whenLoaded('roles')),
            'wallet'     => new WalletResource($this->whenLoaded('wallet')),
        ];
    }
}
