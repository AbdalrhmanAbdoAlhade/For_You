<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{
    use SoftDeletes, HasLocalizedAttributes;

    // rating_avg و reviews_count مش في الـ fillable: بيتحسبوا من ReviewService بس
    protected $fillable = [
        'name',
        'name_en',
        'slug',
        'logo',
        'description',
        'description_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order'    => 'integer',
        'is_active'     => 'boolean',
        'rating_avg'    => 'float',
        'reviews_count' => 'integer',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** تقييمات كل منتجات البراند (صفحة brand profile reviews) */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(ProductReview::class, Product::class);
    }

  /** المؤثرون المرتبطون بالبراند (Many-to-Many) */
public function influencers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'brand_influencer', 'brand_id', 'influencer_id')
        ->withPivot('is_active')
        ->withTimestamps();
}
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}