<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    /** الـ history immutable: بنسجّل created_at بس */
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'status',
        'previous_status',
        'changed_by',
        'note',
        'internal_note',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ============ Relations ============

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ============ Scopes ============

    public function scopeChronological(Builder $q): Builder
    {
        return $q->orderBy('created_at')->orderBy('id');
    }

    // ============ Boot ============

    protected static function booted(): void
    {
        // تظبيط created_at تلقائياً لو مش مبعوت
        static::creating(function (self $h) {
            $h->created_at ??= now();
        });
    }
}