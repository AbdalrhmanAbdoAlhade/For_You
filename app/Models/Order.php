<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'influencer_id',
        'promotional_link_id',
        'status',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'customer_name',
        'customer_phone',
        'customer_email',
        'shipping_address',
        'city',
        'country',
        'notes',
        'coupon_id',
        'coupon_code',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
    ];

      /** الحالات اللي مبتتحسبش مبيعات */
    public const NON_SALE_STATUSES = ['cancelled', 'returned'];
    public const SALE_STATUSES = ['delivered'];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'influencer_id');
    }

    public function promotionalLink(): BelongsTo
    {
        return $this->belongsTo(PromotionalLink::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(Shipping::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(InfluencerCommission::class);
    }
}
