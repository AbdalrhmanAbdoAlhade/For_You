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
    'variant_id',
    'variant_size',
    'variant_color',
    'variant_sku',
    ];

    protected $casts = [
      'variant_id' => 'integer',
     'commission_percentage' => 'float',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
	
  	public function variant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class, 'variant_id');
}
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
  
  /** النص اللي بيتعرض للعميل: "M / Camel" أو null لو مفيش variants */
public function getVariantLabelAttribute(): ?string
{
    $parts = array_filter([$this->variant_size, $this->variant_color], fn ($v) => $v !== null && $v !== '');
    return $parts === [] ? null : implode(' / ', $parts);
}
}
