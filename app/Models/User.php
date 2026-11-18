<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'type',
        'is_active',
        'avatar',
        'provider',
        'provider_id',
       'is_verified', 
       'language',
    ];

 protected $hidden = [
    'password',
    'remember_token',
    'deleted_email',
    'deleted_phone',
    'provider_id',
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'is_active'         => 'boolean',
    'is_verified'       => 'boolean',
    'password'          => 'hashed',
];

    /**
     * عند الحذف الناعم نحرر البريد/الهاتف للاستخدام مرة أخرى
     * ونحتفظ بالقيم الأصلية في deleted_email / deleted_phone.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isForceDeleting()) {
                return;
            }

            $user->forceFill([
                'deleted_email' => $user->email,
                'deleted_phone' => $user->phone,
                'email'         => 'deleted+' . $user->id . '@deleted.invalid',
                'phone'         => $user->phone !== null ? 'deleted-' . $user->id : null,
            ])->saveQuietly();
        });
    }

    // ================= Relations =================

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function permissions()
    {
        return $this->roles()->with('permissions')->get()
            ->pluck('permissions')
            ->flatten()
            ->unique('id');
    }

    protected ?\Illuminate\Support\Collection $permissionSlugsCache = null;

    public function permissionSlugs(): \Illuminate\Support\Collection
    {
        return $this->permissionSlugsCache ??= Permission::query()
            ->whereHas('roles', function ($q) {
                $q->where('is_active', true)
                  ->whereHas('users', fn ($u) => $u->where('users.id', $this->id));
            })
            ->pluck('slug');
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->type === 'admin') {
            return true; // الأدمن عنده كل الصلاحيات
        }

        if ($this->type !== 'employee') {
            return false;
        }

        return $this->permissionSlugs()->contains($slug);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    // Influencer Relations
    public function influencerProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'influencer_product', 'influencer_id', 'product_id')
            ->withPivot('commission_percentage', 'is_active')
            ->withTimestamps();
    }

    public function promotionalLinks(): HasMany
    {
        return $this->hasMany(PromotionalLink::class, 'influencer_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(InfluencerWallet::class, 'influencer_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(InfluencerCommission::class, 'influencer_id');
    }

    public function ordersAsCustomer(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function ordersAsInfluencer(): HasMany
    {
        return $this->hasMany(Order::class, 'influencer_id');
    }

    // Stories (للمؤثر)
    public function stories(): HasMany
    {
        return $this->hasMany(InfluencerStory::class, 'influencer_id');
    }

    /** اللي بيتابعوا المؤثر ده */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'influencer_follows', 'influencer_id', 'user_id')
            ->withTimestamps();
    }

    /** المؤثرين اللي المستخدم ده بيتابعهم */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'influencer_follows', 'user_id', 'influencer_id')
            ->withTimestamps();
    }

    /** المنتجات اللي عملها لايك (wishlist) */
    public function likedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_likes', 'user_id', 'product_id')
            ->withTimestamps();
    }

    /** القصص اللي عملها لايك */
    public function likedStories(): BelongsToMany
    {
        return $this->belongsToMany(InfluencerStory::class, 'influencer_story_likes', 'user_id', 'story_id')
            ->withTimestamps();
    }

    /** القصص اللي شافها */
    public function viewedStories(): BelongsToMany
    {
        return $this->belongsToMany(InfluencerStory::class, 'influencer_story_views', 'user_id', 'story_id')
            ->withPivot('viewed_at');
    }

    // ================= Scopes =================

    public function scopeAdmins($query)
    {
        return $query->where('type', 'admin');
    }

    public function scopeEmployees($query)
    {
        return $query->where('type', 'employee');
    }

    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeInfluencers($query)
    {
        return $query->where('type', 'influencer');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')->latest();
    }
  	/** أجهزة المستخدم (FCM tokens) */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** FCM channel بيستخدم الدالة دي تلقائياً */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }
/** بروفايل المؤثر (handle, bio, social_links) */
public function influencerProfile(): HasOne
{
    return $this->hasOne(InfluencerProfile::class);
}

/** البراندات المرتبطة بالمؤثر (Many-to-Many) */
public function brands(): BelongsToMany
{
    return $this->belongsToMany(Brand::class, 'brand_influencer', 'influencer_id', 'brand_id')
        ->withPivot('is_active')
        ->withTimestamps();
}
  
      /** تقييمات المستخدم على المنتجات (tab Reviews في بروفايل الكريتور) */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}