<?php

namespace App\Models;

use App\Enums\WaProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-workspace provider config — a connected WhatsApp sender + its
 * credentials. Multi-engine: a workspace may hold MANY rows at once —
 * one per engine (baileys pointer / waba / twilio), multiple WABA
 * numbers, plus a meta_ads credential row. There is NO unique constraint
 * on workspace_id (dropped 2026_05_29). The workspace's DEFAULT engine is
 * the `is_primary` row (resolved via scopePrimaryForWorkspace /
 * WorkspaceEngine::for); the full enabled set is WorkspaceEngine::enginesFor.
 *
 * The `credentials_json` column is encrypted-at-rest. The shape
 * differs per provider:
 *
 *   provider=waba    : {
 *       app_id, app_secret, waba_id, business_id, phone_number_id,
 *       access_token, webhook_verify_token, catalog_id, register_pin
 *   }
 *   provider=baileys : { server_url, device_id, qr_paired_at }
 *   provider=twilio  : { account_sid, auth_token, from_number, sandbox }
 *
 * Use the typed helpers (`creds()`, `setCreds()`) instead of touching
 * the column directly so encryption stays consistent.
 */
class WaProviderConfig extends Model
{
    protected $fillable = [
        'workspace_id', 'provider', 'status',
        'credentials_json', 'meta_json',
        'phone_number', 'display_label',
        'connected_at', 'last_health_at',
        // WABA voice calling — local mirror of Meta's calling state.
        // Operator toggles via Workspace Settings → WhatsApp Calling;
        // service POSTs to /<phone_id>/settings then flips this.
        'calling_enabled', 'calling_enabled_at', 'calling_enabled_meta',
        // Multi-WABA — Phase 1. A workspace can have several rows
        // (one per phone number); the primary one is the default
        // sender when an outbound has no explicit from_number.
        'is_primary',
        // WhatsApp Warmer — per-number ramp profile, shared with the Device
        // model so the warmer treats Official/Twilio numbers identically.
        'warmer_config', 'warm_day', 'warm_day_count',
    ];

    protected $casts = [
        'meta_json'            => 'array',
        'warmer_config'        => 'array',
        'warm_day'             => 'date',
        'connected_at'         => 'datetime',
        'last_health_at'       => 'datetime',
        'calling_enabled'      => 'boolean',
        'calling_enabled_at'   => 'datetime',
        'calling_enabled_meta' => 'array',
        'is_primary'           => 'boolean',
    ];

    public const STATUS_PENDING      = 'pending';
    public const STATUS_CONNECTED    = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_FAILED       = 'failed';

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function providerEnum(): WaProvider
    {
        return WaProvider::tryFrom((string) $this->provider) ?? WaProvider::Baileys;
    }

    /** Decrypted credentials array. */
    public function creds(): array
    {
        if (empty($this->credentials_json)) return [];
        try {
            return Crypt::decrypt($this->credentials_json) ?: [];
        } catch (\Throwable $e) {
            // Was silent — a decrypt failure looked identical to "no creds", which
            // hid the real cause (credentials_json column not migrated to TEXT so
            // the ciphertext truncated, or APP_KEY rotated on this install). Log it
            // so "missing access_token" errors can be traced to the actual reason.
            \Illuminate\Support\Facades\Log::warning('[WA-PROVIDER] creds decrypt FAILED', [
                'config_id' => $this->id,
                'provider'  => $this->provider,
                'raw_len'   => strlen((string) $this->credentials_json),
                'error'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    /** Persist a credentials array (encrypted at rest). */
    public function setCreds(array $creds): self
    {
        $this->credentials_json = Crypt::encrypt($creds);
        return $this;
    }

    public function scopeForWorkspace(Builder $q, ?int $workspaceId): Builder
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q->whereRaw('1=0');
    }

    public function scopeConnected(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_CONNECTED);
    }

    /**
     * Primary row first. Multi-WABA — when a workspace has several
     * provider rows, the primary one is the default sender. Falls
     * back to the most recently connected, then the most recently
     * created so we always pick *something* deterministic.
     */
    public function scopePrimaryForWorkspace(Builder $q, ?int $workspaceId): Builder
    {
        // provider=meta_ads rows carry Click-to-WhatsApp ad credentials,
        // not a messaging sender — never let one be picked as the
        // default send config.
        return $q->forWorkspace($workspaceId)
            ->where('provider', '!=', 'meta_ads')
            ->orderByDesc('is_primary')
            ->orderByDesc('connected_at')
            ->orderByDesc('id');
    }

    /** Resolver: pick the row whose phone_number matches an outbound
     *  message's from_number; null if no row matches. Strips '+' and
     *  whitespace on both sides so '+15551234567' matches '15551234567'. */
    public function scopeMatchingPhoneNumber(Builder $q, ?int $workspaceId, ?string $fromNumber): Builder
    {
        $normalized = preg_replace('/\D+/', '', (string) $fromNumber);
        if ($normalized === '') return $q->whereRaw('1=0');
        return $q->forWorkspace($workspaceId)
            ->whereRaw("REPLACE(REPLACE(phone_number, '+', ''), ' ', '') = ?", [$normalized]);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /**
     * Mark this row as the primary sender for its workspace and demote
     * every sibling in one transaction. Use this from controllers when
     * the user clicks "Set as default" on a WABA account row.
     */
    public function setAsPrimary(): void
    {
        \DB::transaction(function () {
            static::query()
                ->where('workspace_id', $this->workspace_id)
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false]);
            $this->forceFill(['is_primary' => true])->save();
        });
    }
}
