<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasLocalizedAttributes;

class Product extends Model
{
    use HasFactory;

    use SoftDeletes, HasLocalizedAttributes;
      // البراند بيتحمّل تلقائيًا مع أي Product (استعلام واحد لكل مجموعة، من غير N+1 في القوائم والهوم)
    protected $with = ['brand'];

    protected $fillable = [
        'category_id',
        'brand_id',
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
        'rating_avg' => 'float',
        'reviews_count' => 'integer',
          'variants_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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

    public function storyProducts(): HasMany
    {
        return $this->hasMany(InfluencerStoryProduct::class);
    }
      /** الأعلى تقييماً: بالمتوسط وبعدين بعدد التقييمات */
    public function scopeTopRated($query)
    {
        return $query->orderByDesc('rating_avg')->orderByDesc('reviews_count');
    }
  	/**
 * هل المنتج عنده variants نشطة؟
 * - لو variants_count محمّل مسبقاً (withCount): استخدمه من غير query
 * - غير كده: بيسأل الـ DB (استخدمه بحرص في القوائم، استعمل withCount هناك)
 */
public function hasVariants(): bool
{
    if (array_key_exists('variants_count', $this->attributes)) {
        return (int) $this->variants_count > 0;
    }

    return $this->variants()->active()->exists();
}/** أول Influencer نشط مرتبط بالمنتج (لـ "Styled by" في ProductResource) */
public function styledBy(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'influencer_product', 'product_id', 'influencer_id')
        ->where('users.type', 'influencer')
        ->where('users.is_active', true)
        ->wherePivot('is_active', true);
}
public function resolvePrice(?ProductVariant $variant = null): array
{
    // لو الـ variant محدد سعره بنفسه (price أو discount_price مش null)
    // → استخدمه (مع سعر المنتج كـ fallback لو الـ price بس null)
    // غير كده → استخدم سعر المنتج كامل (مع خصمه)
    if ($variant && ($variant->price !== null || $variant->discount_price !== null)) {
        $price    = $variant->price ?? $this->price;
        $discount = $variant->discount_price;
    } else {
        $price    = $this->price;
        $discount = $this->discount_price;
    }

    return [
        'price'          => (float) $price,
        'discount_price' => $discount !== null ? (float) $discount : null,
        'final'          => (float) ($discount ?? $price),
    ];
}

    /** المستخدمين اللي عملوا لايك (wishlist) للمنتج */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_likes', 'product_id', 'user_id')
            ->withTimestamps();
    }
      /** تقييمات المنتج */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
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
  
          /** الـ variants بتاعة المنتج (size/color) */
      public function variants(): HasMany
      {
          return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
      }
}