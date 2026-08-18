<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One-row-per-user e-mail OTP used by the mobile app's 2FA verify flow.
 * Replaces (updateOrCreate) on each (re)send; deleted once verified.
 */
class UserOtp extends Model
{
    protected $fillable = ['user_id', 'otp', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
