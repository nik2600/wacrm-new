<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Builds the password-strength validation rule from the admin's
 * /admin/security policy (security.password_*). Used everywhere a NEW
 * password is set — registration, change-password, admin reset. It is
 * never applied to a login attempt, so tightening the policy can only
 * affect passwords created from now on; no existing user is ever locked
 * out by it.
 */
class PasswordPolicy
{
    /** The Password rule reflecting current admin settings. */
    public static function rule(): Password
    {
        $rule = Password::min(max(6, SecurityPolicy::int('password_min_length', 8)));

        if (SecurityPolicy::bool('password_require_upper', true)) {
            $rule->mixedCase();          // at least one upper + one lower
        }
        if (SecurityPolicy::bool('password_require_number', true)) {
            $rule->numbers();
        }
        if (SecurityPolicy::bool('password_require_symbol', false)) {
            $rule->symbols();
        }
        return $rule;
    }

    public static function maxAgeDays(): int
    {
        return SecurityPolicy::int('password_max_age_days', 0);
    }

    /** Is this password past the max-age window? false when the policy is off (0). */
    public static function isStale($changedAt): bool
    {
        $days = self::maxAgeDays();
        if ($days <= 0) return false;
        if (empty($changedAt)) return true; // never recorded → stale once policy is on
        try {
            return \Illuminate\Support\Carbon::parse($changedAt)->lt(now()->subDays($days));
        } catch (\Throwable $e) {
            return false; // fail-open — never block a valid login on a parse error
        }
    }
}
