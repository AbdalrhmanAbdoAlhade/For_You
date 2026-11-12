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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

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

        public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}
