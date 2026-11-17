<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReview extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'body',
        'is_verified_purchase',
        'is_creator',
        'is_active',
    ];

    protected $casts = [
        'rating'               => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_creator'           => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductReviewImage::class, 'review_id')->orderBy('sort_order');
    }

       /** التقييمات الظاهرة للجمهور */
    public function scopeActive($query)
    {
        return $query->where('product_reviews.is_active', true);
    }

    /** تقييمات بصور (فلتر "With photos") */
    public function scopeWithPhotos($query)
    {
        return $query->whereHas('images');
    }

    /** تقييمات المؤثرين (فلتر "Creators") */
    public function scopeFromCreators($query)
    {
        return $query->where('product_reviews.is_creator', true);
    }

    /** فلتر على عدد النجوم */
    public function scopeOfRating($query, int $rating)
    {
        return $query->where('product_reviews.rating', $rating);
    }
}