<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'otp_hash',
        'expires_at',
        'attempts',
        'verified_at',
        'reset_token_hash',
        'reset_token_expires_at',
    ];

    protected $casts = [
        'expires_at'             => 'datetime',
        'verified_at'            => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'attempts'               => 'integer',
    ];

    protected $hidden = [
        'otp_hash',
        'reset_token_hash',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isResetTokenExpired(): bool
    {
        return $this->reset_token_expires_at?->isPast() ?? true;
    }
}
