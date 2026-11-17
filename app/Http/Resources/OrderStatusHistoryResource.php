<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isStaff = $request->user()
            && in_array($request->user()->type, ['admin', 'employee'], true);

        return [
            'id'    => $this->id,
            'status'=> $this->status,
            'status_label' => __('orders.statuses.' . $this->status),
            'previous_status' => $this->previous_status,
            'previous_status_label' => $this->previous_status
                ? __('orders.statuses.' . $this->previous_status)
                : null,
            'note' => $this->note, // دايماً ظاهر

            // للأدمن/الموظف فقط
            'internal_note' => $this->when($isStaff, $this->internal_note),

            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? [
                'id'   => $this->changedBy->id,
                'name' => $this->changedBy->name,
                'type' => $this->changedBy->type,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}