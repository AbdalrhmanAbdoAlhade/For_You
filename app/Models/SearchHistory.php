<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'query', 'type', 'results_count', 'created_at'];

    protected $casts = [
        'results_count' => 'integer',
        'created_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $h) {
            $h->created_at ??= now();
        });
    }
}
