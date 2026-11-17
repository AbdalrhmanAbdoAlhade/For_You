<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        $variant = $this->relationLoaded('variant') ? $this->variant : null;

        $pricing = $product ? $product->resolvePrice($variant) : null;
        $availableStock = null;

        if ($product) {
            $availableStock = $variant ? $variant->stock : $product->stock;
        }

        return [
            'id'              => $this->id,
            'product_id'      => $this->product_id,
            'variant_id'      => $this->variant_id,
            'variant'         => $variant ? [
                'id'    => $variant->id,
                'size'  => $variant->size,
                'color' => $variant->color,
                'sku'   => $variant->sku,
                'label' => $variant->label,
            ] : null,
            'quantity'        => $this->quantity,
            'unit_price'      => $pricing ? $pricing['final'] : null,
            'total'           => (float) $this->total,
            'issue'           => $this->issue,
            'available_stock' => $availableStock,
            'product'         => $product ? new ProductResource($product) : null,
        ];
    }
}