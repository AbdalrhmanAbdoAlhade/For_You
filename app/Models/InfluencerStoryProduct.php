<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerStoryProduct extends Model
{
    protected $fillable = [
        'story_id',
        'product_id',
        'promotional_link_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ================= Relations =================

    public function story(): BelongsTo
    {
        return $this->belongsTo(InfluencerStory::class, 'story_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promotionalLink(): BelongsTo
    {
        return $this->belongsTo(PromotionalLink::class, 'promotional_link_id');
    }

    // ================= Scopes =================

    /**
     * المنتج لسه ينفع يتشترى من القصة دي:
     * - المنتج مفعّل وغير محذوف
     * - رابط الترويج فعّال وغير منتهي
     * - المنتج لسه مربوط بالمؤثر وربطه مفعّل (الأدمن ممكن يفصله أو يعطّله بعد النشر)
     */
    public function scopeShoppable($query)
    {
        return $query
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereHas('promotionalLink', function ($q) {
                $q->where('is_active', true)
                    ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            })
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('influencer_product as ip')
                    ->join('influencer_stories as s', 's.influencer_id', '=', 'ip.influencer_id')
                    ->whereColumn('s.id', 'influencer_story_products.story_id')
                    ->whereColumn('ip.product_id', 'influencer_story_products.product_id')
                    ->where('ip.is_active', true);
            });
    }
}