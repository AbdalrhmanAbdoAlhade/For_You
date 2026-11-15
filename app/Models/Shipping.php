<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipping extends Model
{
    use HasFactory;

protected $fillable = [
        'order_id',
        'shipping_company',
        'tracking_number',
        'status',
        'shipping_address',
        'gateway_response',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
