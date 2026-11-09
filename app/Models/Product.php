<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
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
        return $this->discount_price ?? $this->price;
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
