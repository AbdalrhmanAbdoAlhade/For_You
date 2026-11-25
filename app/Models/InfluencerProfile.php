<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'handle',
        'bio',
        'social_links',
        'cover_image',
        'instagram_url',
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}