<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit ledger for wallet movements. WalletService is the only thing
 * that should `create()` rows here — direct callers will skip the
 * `users.wallet_credits` mirror update and the two will diverge.
 *
 * `kind` separates the two parallel balances:
 *  - 'credit'   = message credits (the balance the send-path checks)
 *  - 'currency' = paid top-ups in INR (or whatever wallet_currency_code
 *                 is on the user). A 'currency' top-up is paired with
 *                 a 'credit' earn row inside the same DB transaction.
 *
 * `balance_after` is denormalised so the wallet history page renders
 * "after each row" balances without re-summing the whole ledger.
 * WalletService recomputes this from `users.wallet_credits` after
 * applying the delta — never trust client-supplied values.
 */
class WalletTransaction extends Model
{
    public $timestamps = false; // only created_at; no updates

    protected $fillable = [
        'user_id', 'kind', 'type', 'amount', 'balance_after',
        'source', 'subject_type', 'subject_id', 'description', 'meta',
        'created_at',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'balance_after' => 'integer',
        'meta'          => 'array',
        'created_at'    => 'datetime',
    ];

    public const KIND_CREDIT   = 'credit';
    public const KIND_CURRENCY = 'currency';

    public const TYPE_EARN         = 'earn';
    public const TYPE_SPEND        = 'spend';
    public const TYPE_REFUND       = 'refund';
    public const TYPE_ADMIN_ADJUST = 'admin_adjust';
    public const TYPE_TOPUP        = 'topup';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCredit(Builder $q): Builder      { return $q->where('kind', self::KIND_CREDIT); }
    public function scopeCurrency(Builder $q): Builder    { return $q->where('kind', self::KIND_CURRENCY); }
    public function scopeForUser(Builder $q, int $u): Builder { return $q->where('user_id', $u); }
}
