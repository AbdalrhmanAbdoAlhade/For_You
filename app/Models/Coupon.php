<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

protected $fillable = [
        'code', 'type', 'value', 'min_order_amount', 'max_discount',
        'usage_limit', 'per_user_limit', 'used_count',
        'is_active', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'value'            => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount'     => 'decimal:2',
        'is_active'        => 'boolean',
        'starts_at'        => 'datetime',
        'expires_at'       => 'datetime',
    ];
}
