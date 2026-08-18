<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Thin, memoised reader for the `security.*` SystemSettings that the
 * /admin/security page writes. Centralises access so every enforcement
 * point reads the same source of truth, and keeps the lookups cheap
 * (one DB read per key per request) so guards can run inside send loops.
 *
 * Design rule for everything that consumes this: FAIL-OPEN. A security
 * convenience must never take down delivery or login because of a bug —
 * callers wrap their checks and, on any error, allow the action.
 */
class SecurityPolicy
{
    private static array $cache = [];

    public static function get(string $key, $default = null)
    {
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = SystemSetting::get('security.' . $key, $default);
        }
        return self::$cache[$key];
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function str(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function arr(string $key, array $default = []): array
    {
        $v = self::get($key, $default);
        return is_array($v) ? $v : $default;
    }

    /** Tests / admin tools call this after changing a setting mid-request. */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
