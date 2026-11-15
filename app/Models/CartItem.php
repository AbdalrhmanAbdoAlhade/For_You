<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

      /** المنتج موجود ونشط؟ (المحذوف soft delete بيرجع null) */
    public function getIsAvailableAttribute(): bool
    {
        return $this->product !== null && $this->product->is_active;
    }

    /** null = تمام، غير كده سبب المشكلة */
    public function getIssueAttribute(): ?string
    {
        if (!$this->is_available) {
            return 'unavailable';
        }

        if ($this->product->stock < $this->quantity) {
            return 'insufficient_stock';
        }

        return null;
    }

       public function getTotalAttribute()
    {
        if (!$this->is_available) {
            return 0; // العنصر غير المتاح مبيتحسبش في الإجمالي
        }

        return round($this->product->effective_price * $this->quantity, 2);
    }
}
