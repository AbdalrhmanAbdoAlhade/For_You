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
      'variant_id',
    ];
  
   protected $casts = [
      'variant_id' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
	public function variant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class, 'variant_id');
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
/** null = تمام، غير كده سبب المشكلة */
public function getIssueAttribute(): ?string
{
    if (!$this->is_available) {
        return 'unavailable';
    }

    // المنتج مبقى عنده variant والـ cart item مش محدد — لازم العميل يختار
    if (!$this->variant_id) {
        if ($this->product->variants()->exists()) {
            return 'variant_required';
        }

        // منتج بسيط
        if ($this->product->stock < $this->quantity) {
            return 'insufficient_stock';
        }

        return null;
    }

    // عنده variant لكن اتحذف أو اتعطّل
    if (!$this->variant || !$this->variant->is_active) {
        return 'variant_unavailable';
    }

    if ($this->variant->stock < $this->quantity) {
        return 'insufficient_stock';
    }

    return null;
}

public function getTotalAttribute()
{
    if (!$this->is_available) {
        return 0;
    }

    if ($this->variant) {
        $pricing = $this->product->resolvePrice($this->variant);
        return round($pricing['final'] * $this->quantity, 2);
    }

    return round($this->product->effective_price * $this->quantity, 2);
}
}
