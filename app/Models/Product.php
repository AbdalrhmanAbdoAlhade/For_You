<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasLocalizedAttributes;

class Product extends Model
{
    use SoftDeletes, HasLocalizedAttributes;

    protected $fillable = [
        'category_id',
        'name',
        'name_en',
        'slug',
        'description',
        'description_en',
        'short_description',
        'short_description_en',
        'price',
        'discount_price',
        'stock',
        'sku',
        'images',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'images' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function influencers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'influencer_product', 'product_id', 'influencer_id')
            ->withPivot('commission_percentage', 'is_active')
            ->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function promotionalLinks(): HasMany
    {
        return $this->hasMany(PromotionalLink::class);
    }

       public function getFinalPriceAttribute()
    {
        return $this->effective_price;
    }

     /**
     * السعر النهائي الفعّال: الخصم بيشتغل بس لو 0 < discount < price
     */
    public function getEffectivePriceAttribute(): float
    {
        $price = (float) $this->price;
        $discount = $this->discount_price !== null ? (float) $this->discount_price : null;

        if ($discount !== null && $discount > 0 && $discount < $price) {
            return $discount;
        }

        return $price;
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->effective_price < (float) $this->price;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
        public function scopeOnSale($query)
    {
        return $query->whereNotNull('discount_price')->whereColumn('discount_price', '<', 'price');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
