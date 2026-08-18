<?php

namespace App\Services;

use App\Models\CreditPackage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single funnel for every wallet movement. The send pipeline calls
 * `chargeForSend()` before committing a Message::create(). Success
 * paths get back a transaction id they can pass to `refund()` if the
 * downstream bot reports a delivery failure.
 *
 * Two parallel balances live on `users`:
 *   - wallet_credits         : message credits (the send-time gate)
 *   - wallet_currency_minor  : INR/etc. in minor units (paise/cents)
 *
 * Every method runs inside a DB transaction with `lockForUpdate()` on
 * the user row so concurrent sends from the same account can't both
 * see "credits available, deduct" and end up negative. The mirror
 * column on `users` and the ledger row are written together — they
 * cannot diverge.
 */
class WalletService
{
    public function creditBalance(User $user): int
    {
        return (int) (User::query()->whereKey($user->id)->value('wallet_credits') ?? 0);
    }

    public function currencyBalance(User $user): int
    {
        return (int) (User::query()->whereKey($user->id)->value('wallet_currency_minor') ?? 0);
    }

    public function priceForMessages(int $count): int
    {
        $perMessage = max(1, (int) SystemSetting::get('credits_per_message', 1));
        return max(0, $count) * $perMessage;
    }

    public function canSend(User $user, int $count = 1): bool
    {
        return $this->creditBalance($user) >= $this->priceForMessages($count);
    }

    /**
     * Deduct credits for $count outbound messages. Returns the
     * transaction so the caller can refund if the bot fails.
     *
     * Throws RuntimeException if the user is short on credits — the
     * caller MUST catch and translate this to a user-facing 402 / UI
     * banner. We never ship a half-sent broadcast.
     */
    public function chargeForSend(User $user, int $count, string $source = 'message.sent', mixed $subject = null, ?string $description = null, array $meta = []): WalletTransaction
    {
        $price = $this->priceForMessages($count);

        return DB::transaction(function () use ($user, $count, $price, $source, $subject, $description, $meta) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($fresh->wallet_credits < $price) {
                throw new RuntimeException("Out of credits: need $price, have {$fresh->wallet_credits}.");
            }

            $newBal = $fresh->wallet_credits - $price;
            $fresh->update(['wallet_credits' => $newBal]);
            // Keep the in-memory user model fresh for callers that
            // re-read $user immediately after this call.
            $user->wallet_credits = $newBal;

            return WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CREDIT,
                'type'          => WalletTransaction::TYPE_SPEND,
                'amount'        => -$price,
                'balance_after' => $newBal,
                'source'        => $source,
                'subject_type'  => $this->subjectType($subject),
                'subject_id'    => $this->subjectId($subject),
                'description'   => mb_substr($description ?: ("Sent $count message" . ($count === 1 ? '' : 's')), 0, 191),
                'meta'          => $meta + ['count' => $count, 'price' => $price],
                'created_at'    => now(),
            ]);
        });
    }

    /**
     * Refund a previously-spent transaction. Idempotent — calling
     * twice on the same transaction creates a single refund row;
     * subsequent calls become no-ops.
     */
    public function refund(WalletTransaction $tx, ?string $reason = null): ?WalletTransaction
    {
        if ($tx->kind !== WalletTransaction::KIND_CREDIT || $tx->type !== WalletTransaction::TYPE_SPEND) {
            return null;
        }
        // Already-refunded?
        $already = WalletTransaction::query()
            ->where('user_id', $tx->user_id)
            ->where('source', 'refund')
            ->where('subject_type', WalletTransaction::class)
            ->where('subject_id', $tx->id)
            ->exists();
        if ($already) return null;

        $refundAmount = abs((int) $tx->amount);

        return DB::transaction(function () use ($tx, $refundAmount, $reason) {
            $fresh = User::query()->whereKey($tx->user_id)->lockForUpdate()->firstOrFail();
            $newBal = $fresh->wallet_credits + $refundAmount;
            $fresh->update(['wallet_credits' => $newBal]);

            return WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CREDIT,
                'type'          => WalletTransaction::TYPE_REFUND,
                'amount'        => $refundAmount,
                'balance_after' => $newBal,
                'source'        => 'refund',
                'subject_type'  => WalletTransaction::class,
                'subject_id'    => $tx->id,
                'description'   => mb_substr($reason ?: 'Refund for failed send', 0, 191),
                'meta'          => ['refunded_tx_id' => $tx->id],
                'created_at'    => now(),
            ]);
        });
    }

    /**
     * Add credits to a user's account (referral payout, admin grant,
     * top-up conversion). Returns the ledger row.
     */
    public function creditAccount(User $user, int $amount, string $source, mixed $subject = null, ?string $description = null, array $meta = [], string $type = WalletTransaction::TYPE_EARN): WalletTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException("creditAccount called with non-positive amount: $amount");
        }

        return DB::transaction(function () use ($user, $amount, $source, $subject, $description, $meta, $type) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $newBal = $fresh->wallet_credits + $amount;
            $fresh->update(['wallet_credits' => $newBal]);
            $user->wallet_credits = $newBal;

            return WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CREDIT,
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => $newBal,
                'source'        => $source,
                'subject_type'  => $this->subjectType($subject),
                'subject_id'    => $this->subjectId($subject),
                'description'   => $description ?: ucfirst(str_replace('.', ' ', $source)),
                'meta'          => $meta,
                'created_at'    => now(),
            ]);
        });
    }

    /**
     * Add money to the currency wallet AND auto-convert to credits
     * inside the same transaction. Returns ['currencyTx', 'creditTx'].
     *
     * `credits_per_currency_minor` controls the conversion rate:
     * 0.1 means each minor unit (1 paise) buys 0.1 credits, i.e.
     * ₹1 = 10 credits. Admins can tune this in /admin/settings.
     */
    public function topup(User $user, int $minor, string $sourceCurrency = 'razorpay.topup', string $sourceCredit = 'topup.conversion', ?string $description = null, array $meta = []): array
    {
        if ($minor <= 0) {
            throw new RuntimeException("topup called with non-positive minor amount: $minor");
        }
        $rate = (float) SystemSetting::get('credits_per_currency_minor', 0.1);
        $creditsToAdd = (int) floor($minor * $rate);

        return DB::transaction(function () use ($user, $minor, $sourceCurrency, $sourceCredit, $description, $meta, $creditsToAdd, $rate) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $newCurrency = $fresh->wallet_currency_minor + $minor;
            $newCredits  = $fresh->wallet_credits + $creditsToAdd;
            $fresh->update([
                'wallet_currency_minor' => $newCurrency,
                'wallet_credits'        => $newCredits,
            ]);
            $user->wallet_currency_minor = $newCurrency;
            $user->wallet_credits = $newCredits;

            $currencyTx = WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CURRENCY,
                'type'          => WalletTransaction::TYPE_TOPUP,
                'amount'        => $minor,
                'balance_after' => $newCurrency,
                'source'        => $sourceCurrency,
                'description'   => $description ?: 'Wallet top-up',
                'meta'          => $meta,
                'created_at'    => now(),
            ]);

            $creditTx = $creditsToAdd > 0
                ? WalletTransaction::create([
                    'user_id'       => $fresh->id,
                    'kind'          => WalletTransaction::KIND_CREDIT,
                    'type'          => WalletTransaction::TYPE_EARN,
                    'amount'        => $creditsToAdd,
                    'balance_after' => $newCredits,
                    'source'        => $sourceCredit,
                    'subject_type'  => WalletTransaction::class,
                    'subject_id'    => $currencyTx->id,
                    'description'   => "Top-up converted to $creditsToAdd credit" . ($creditsToAdd === 1 ? '' : 's'),
                    'meta'          => ['rate' => $rate, 'minor' => $minor],
                    'created_at'    => now(),
                ])
                : null;

            return ['currencyTx' => $currencyTx, 'creditTx' => $creditTx];
        });
    }

    /**
     * Top-up with a fixed admin-curated credit package. Differs from
     * topup() in that the credit count is taken straight from the
     * package row (admin-set bundle) rather than computed from a
     * conversion rate. Use this from the checkout success path.
     *
     * Inside one DB transaction we:
     *   1. add `package.price_minor` to the user's currency balance
     *   2. add `package.credits` to the user's credit balance
     *   3. write a 'currency' topup row + a 'credit' earn row that
     *      references the currency row
     */
    public function topupViaPackage(User $user, CreditPackage $package, ?string $paymentRef = null): array
    {
        if (!$package->is_active) {
            throw new RuntimeException("Credit package '$package->slug' is not active.");
        }

        return DB::transaction(function () use ($user, $package, $paymentRef) {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $newCurrency = $fresh->wallet_currency_minor + $package->price_minor;
            $newCredits  = $fresh->wallet_credits + $package->credits;
            $fresh->update([
                'wallet_currency_minor' => $newCurrency,
                'wallet_credits'        => $newCredits,
                'wallet_currency_code'  => $fresh->wallet_currency_code ?: $package->currency_code,
            ]);
            $user->wallet_currency_minor = $newCurrency;
            $user->wallet_credits = $newCredits;

            $currencyTx = WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CURRENCY,
                'type'          => WalletTransaction::TYPE_TOPUP,
                'amount'        => $package->price_minor,
                'balance_after' => $newCurrency,
                'source'        => 'package.purchase',
                'subject_type'  => CreditPackage::class,
                'subject_id'    => $package->id,
                'description'   => "Top-up · $package->name ($package->credits credits)",
                'meta'          => [
                    'package_slug' => $package->slug,
                    'currency'     => $package->currency_code,
                    'payment_ref'  => $paymentRef,
                ],
                'created_at'    => now(),
            ]);

            $creditTx = WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CREDIT,
                'type'          => WalletTransaction::TYPE_EARN,
                'amount'        => $package->credits,
                'balance_after' => $newCredits,
                'source'        => 'package.purchase',
                'subject_type'  => CreditPackage::class,
                'subject_id'    => $package->id,
                'description'   => "Top-up · $package->name",
                'meta'          => [
                    'package_slug' => $package->slug,
                    'price_minor'  => $package->price_minor,
                    'currency'     => $package->currency_code,
                    'payment_ref'  => $paymentRef,
                    'currency_tx_id' => $currencyTx->id,
                ],
                'created_at'    => now(),
            ]);

            return ['currencyTx' => $currencyTx, 'creditTx' => $creditTx];
        });
    }

    /**
     * Admin-driven credit adjustment (positive or negative). Negative
     * adjustments are clamped at zero — we don't allow admins to push
     * users into the red. The audit row records the attempted amount
     * in `meta.requested` so support can still reconstruct intent.
     */
    public function adminAdjustCredits(User $user, int $delta, ?int $byAdminId = null, ?string $reason = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $delta, $byAdminId, $reason) {
            $fresh  = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $newBal = max(0, $fresh->wallet_credits + $delta);
            $applied = $newBal - $fresh->wallet_credits;
            $fresh->update(['wallet_credits' => $newBal]);
            $user->wallet_credits = $newBal;

            return WalletTransaction::create([
                'user_id'       => $fresh->id,
                'kind'          => WalletTransaction::KIND_CREDIT,
                'type'          => WalletTransaction::TYPE_ADMIN_ADJUST,
                'amount'        => $applied,
                'balance_after' => $newBal,
                'source'        => 'admin.adjust',
                'description'   => $reason ?: 'Admin adjustment',
                'meta'          => ['by_admin' => $byAdminId, 'requested' => $delta, 'applied' => $applied],
                'created_at'    => now(),
            ]);
        });
    }

    private function subjectType(mixed $subject): ?string
    {
        if ($subject === null) return null;
        if (is_object($subject)) return get_class($subject);
        return null;
    }

    private function subjectId(mixed $subject): ?int
    {
        if ($subject === null) return null;
        if (is_object($subject) && property_exists($subject, 'id')) return (int) $subject->id;
        if (is_object($subject) && method_exists($subject, 'getKey')) return (int) $subject->getKey();
        return null;
    }
}
