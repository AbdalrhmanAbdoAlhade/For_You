<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'color',
        'sku',
        'stock',
        'price',
        'discount_price',
        'sort_order',
        'is_active',
    ];

    // combination_key بيحسبه MySQL، ومينفعش يتكتب من الـ app
    protected $guarded = ['combination_key'];

    protected $casts = [
        'stock'          => 'integer',
        'price'          => 'decimal:2',
        'discount_price' => 'decimal:2',
        'sort_order'     => 'integer',
        'is_active'      => 'boolean',
    ];

    // ============ Relations ============

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    // ============ Scopes ============

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where('stock', '>', 0);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    // ============ Helpers ============

    public function getLabelAttribute(): ?string
    {
        $parts = array_filter([$this->size, $this->color], fn ($v) => $v !== null && $v !== '');
        return $parts === [] ? null : implode(' / ', $parts);
    }

    public function getEffectivePriceAttribute(): float
    {
        $price = $this->price ?? $this->product?->price ?? 0;
        return (float) ($this->discount_price ?? $price);
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->is_active && $this->stock > 0;
    }
}