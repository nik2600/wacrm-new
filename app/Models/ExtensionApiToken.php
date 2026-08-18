<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A bearer token issued to the WaDesk browser extension.
 * Tokens are stored hashed; the plaintext is shown to the client once
 * at login time and never persisted.
 */
class ExtensionApiToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'label', 'last_used_at', 'expires_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a fresh token for a user; returns the plaintext (caller must send
     * it back once). Pass $ttlMinutes to bound the token's lifetime; null keeps
     * the legacy behaviour (never expires) so existing callers are unaffected.
     */
    public static function issue(int $userId, ?string $label = null, ?int $ttlMinutes = null): string
    {
        $plain = Str::random(60);
        self::create([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $plain),
            'label'      => $label,
            'expires_at' => $ttlMinutes ? now()->addMinutes($ttlMinutes) : null,
        ]);
        return $plain;
    }

    /** Resolve a plaintext bearer token to its user, or null. Touches last_used_at. */
    public static function resolveUser(string $plain): ?User
    {
        if ($plain === '') return null;
        $row = self::where('token_hash', hash('sha256', $plain))->first();
        if (!$row) return null;
        // Enforce expiry when set. Legacy tokens carry a NULL expires_at and
        // are treated as non-expiring so we never lock out existing sessions.
        if ($row->expires_at !== null && $row->expires_at->isPast()) {
            return null;
        }
        $row->forceFill(['last_used_at' => now()])->saveQuietly();
        return $row->user;
    }
}
