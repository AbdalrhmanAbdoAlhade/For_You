<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'discount_price',
        'quantity',
        'total',
    'commission_percentage',
    ];

    protected $casts = [
     'commission_percentage' => 'float',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
