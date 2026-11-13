<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

protected $fillable = [
        'influencer_id',
        'wallet_id',
        'amount',
        'status',
        'payment_method',
        'account_details',
        'admin_note',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'processed_by',
        'processed_at',
        'transfer_reference',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'approved_at'  => 'datetime',
        'paid_at'      => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'influencer_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(InfluencerWallet::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
