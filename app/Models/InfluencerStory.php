<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfluencerStory extends Model
{
    protected $fillable = [
        'influencer_id',
        'image',
        'caption',
        'label',
        'duration_seconds',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'is_active'        => 'boolean',
        'expires_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        // لو الـ expires_at مبعوتش، القصة بتنتهي بعد المدة المحددة (الافتراضي 24 ساعة)
        static::creating(function (InfluencerStory $story) {
            if ($story->expires_at === null) {
                $story->expires_at = now()->addHours(
                    max(1, (int) config('store.story_ttl_hours', 24))
                );
            }
        });
    }

    // ================= Relations =================

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'influencer_id');
    }

    public function storyProducts(): HasMany
    {
        return $this->hasMany(InfluencerStoryProduct::class, 'story_id')->orderBy('sort_order');
    }

    /** اللي شافوا القصة (مشاهدة واحدة لكل مستخدم) */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'influencer_story_views', 'story_id', 'user_id')
            ->withPivot('viewed_at');
    }

    /** اللي عملوا لايك للقصة */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'influencer_story_likes', 'story_id', 'user_id')
            ->withTimestamps();
    }

    // ================= Scopes =================

    /** قصة مفعّلة ولسه ما انتهتش (الاختفاء بيحصل بالثانية من غير انتظار الـ purge) */
    public function scopeLive($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    /** قصة حيّة + صاحبها مؤثر مفعّل وغير محذوف */
    public function scopeVisible($query)
    {
        return $query->live()
            ->whereHas('influencer', fn ($q) => $q->where('is_active', true));
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /** القصص اللي المستخدم ده لسه ما شافهاش (للحلقة الذهبية) */
    public function scopeUnseenBy($query, int $userId)
    {
        return $query->whereDoesntHave('viewers', fn ($q) => $q->where('users.id', $userId));
    }

    // ================= Helpers =================

    public function isLive(): bool
    {
        return $this->is_active
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}