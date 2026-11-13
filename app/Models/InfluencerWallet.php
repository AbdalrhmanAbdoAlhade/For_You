<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfluencerWallet extends Model
{
    use HasFactory;

protected $fillable = [
        'influencer_id',
        'balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
    ];

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'influencer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }
}
