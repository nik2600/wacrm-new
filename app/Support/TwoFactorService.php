<?php

namespace App\Support;

use App\Models\User;

/**
 * Minimal TOTP (RFC 6238) implementation ported from SnapNest.
 * Generates / verifies 6-digit codes with a 30-second time slice
 * and a default ±1 slice window (so users near the boundary
 * still authenticate).
 *
 * Secrets are base32-encoded so they slot directly into Google
 * Authenticator / 1Password / Authy via the otpauth:// URL.
 */
class TwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 10): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function buildOtpAuthUrl(User $user, string $secret, ?string $issuer = null): string
    {
        $issuer = $issuer ?: config('app.name', 'WaDesk');
        $label  = rawurlencode($issuer . ':' . $user->email);
        $iparam = rawurlencode($issuer);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$iparam}";
    }

    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', (string) $code);
        if ($code === '' || $code === null) return false;

        $slice = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::generateCode($secret, $slice + $offset), $code)) return true;
        }
        return false;
    }

    private static function generateCode(string $secret, int $timeSlice): string
    {
        $key  = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $binary = '';
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= self::ALPHABET[bindec($chunk)];
        }
        return $out;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = preg_replace('/[^A-Z2-7]/', '', strtoupper($secret));
        $binary = '';
        for ($i = 0, $n = strlen($secret); $i < $n; $i++) {
            $idx = strpos(self::ALPHABET, $secret[$i]);
            if ($idx === false) continue;
            $binary .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }
}
