<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per referred-user-attribution. We attribute exactly once,
 * at signup, and never again (the unique key on `referred_user_id`
 * enforces this at the DB level — race-safe). If a referee later
 * changes their plan / makes a purchase, we do NOT re-credit on this
 * row; instead we'd add a separate ledger entry pointing back to
 * this referral.
 */
class Referral extends Model
{
    public $timestamps = false; // only created_at

    protected $fillable = [
        'referrer_user_id', 'referred_user_id', 'code_used',
        'credits_awarded', 'award_transaction_id', 'created_at',
    ];

    protected $casts = [
        'credits_awarded' => 'integer',
        'created_at'      => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function awardTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'award_transaction_id');
    }

    public function scopeForReferrer(Builder $q, int $userId): Builder
    {
        return $q->where('referrer_user_id', $userId);
    }
}
