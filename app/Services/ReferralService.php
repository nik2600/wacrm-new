<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Referral attribution + payout. Called from AuthController on
 * register, and only there — there's no admin "re-attribute" path
 * (the unique constraint on `referrals.referred_user_id` enforces
 * one-and-done).
 *
 * Payout amount is read from `system_settings.referral_signup_credits`,
 * which the admin tunes from /admin/settings. Defaults to 100.
 */
class ReferralService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Look up the referrer user from a code captured at signup-time.
     * Self-referrals (code belongs to the same user) are ignored —
     * defensive against a clever user pasting their own link into
     * incognito.
     */
    public function findReferrer(?string $code, ?int $excludeUserId = null): ?User
    {
        $code = trim((string) $code);
        if ($code === '') return null;
        $code = strtoupper($code);
        return User::query()
            ->where('referral_code', $code)
            ->when($excludeUserId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->first();
    }

    /**
     * Attribute a referee to a referrer + award the configured
     * signup credits in one transaction. Idempotent — a second call
     * for the same referee no-ops thanks to the unique constraint
     * on `referrals.referred_user_id`.
     *
     * Returns the Referral row (existing or new).
     */
    public function attribute(User $referrer, User $referee, string $codeUsed): ?Referral
    {
        if ($referrer->id === $referee->id) return null;

        $existing = Referral::where('referred_user_id', $referee->id)->first();
        if ($existing) return $existing;

        $payout = max(0, (int) SystemSetting::get('referral_signup_credits', 100));

        return DB::transaction(function () use ($referrer, $referee, $codeUsed, $payout) {
            // Persist the referee → referrer link on `users` first so
            // any subsequent reads (admin views, audit) reflect the
            // graph even if the credit grant fails.
            $referee->forceFill(['referred_by_user_id' => $referrer->id])->save();

            $awardTx = null;
            if ($payout > 0) {
                $awardTx = $this->wallet->creditAccount(
                    $referrer,
                    $payout,
                    'referral.signup',
                    $referee,
                    "Referral bonus — {$referee->email} signed up with your code",
                    ['referee_id' => $referee->id, 'code_used' => $codeUsed]
                );
            }

            return Referral::create([
                'referrer_user_id'     => $referrer->id,
                'referred_user_id'     => $referee->id,
                'code_used'            => $codeUsed,
                'credits_awarded'      => $payout,
                'award_transaction_id' => $awardTx?->id,
                'created_at'           => now(),
            ]);
        });
    }
}
