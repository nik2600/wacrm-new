<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Connected WhatsApp device — paired phone or cloud number.
 * Mirrors the old project's `Devices` model but encrypts
 * device_name + phone_number at rest the same way Contact and
 * MetaCampaign do.
 */
class Device extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'devices';

    /**
     * Webhook: device_status_updated — fired whenever a device's connection
     * status changes via an instance save (connect / disconnect / pair). The
     * bulk stale-sweep in WaConnectController::nodeHeartbeat uses a mass
     * UPDATE which bypasses model events, so that path emits explicitly.
     * emit() is deferred + guarded, so it can never break the save.
     */
    protected static function booted(): void
    {
        static::updated(function (self $d) {
            if (!$d->wasChanged('status')) return;
            \App\Services\WebhookService::emit('device_status_updated', [
                'workspace_id'    => $d->workspace_id,
                'user_id'         => $d->user_id,
                'device_id'       => $d->id,
                'device_name'     => $d->device_name,
                'phone_number'    => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) ?: null,
                'status'          => $d->status,
                'previous_status' => $d->getOriginal('status'),
                'timestamp'       => now()->timestamp,
            ], $d->user_id);
        });
    }

    protected $fillable = [
        'user_id',
        'assigned_user_id',
        // workspace_id MUST be fillable — DevicesController::store() passes it
        // to Device::create(), but without it here mass-assignment silently
        // dropped it, leaving every freshly-connected device with a NULL
        // workspace_id (rescued only later by the connect-heartbeat backfill).
        'workspace_id',
        'activate_after_pairing',
        'device_name',
        'country_code',
        'phone_number',
        'region',
        'active',
        'status',
        'sent_24h',
        'failed_24h',
        'last_seen_at',
        'warmer_config',
        'warm_day',
        'warm_day_count',
        // Per-number proxy / IP isolation (Unofficial-API only)
        'proxy_enabled',
        'proxy_type',
        'proxy_host',
        'proxy_port',
        'proxy_username',
        'proxy_password',
        'proxy_status',
        'proxy_egress_ip',
        'proxy_checked_at',
    ];

    protected $casts = [
        // Encrypted-at-rest. Phone PII (name + dial code + national
        // number) all gets ciphertext on disk; `region` stays plain
        // because it's an ISO country code used for SQL filtering
        // and isn't sensitive on its own.
        'device_name'  => 'encrypted',
        'country_code' => 'encrypted',
        'phone_number' => 'encrypted',
        'active'       => 'boolean',
        'sent_24h'     => 'integer',
        'failed_24h'   => 'integer',
        'last_seen_at' => 'datetime',
        'warmer_config'  => 'array',
        'warm_day'       => 'date',
        // Proxy: creds encrypted at rest (same pattern as WABA/Slack secrets).
        'proxy_enabled'   => 'boolean',
        'proxy_port'      => 'integer',
        'proxy_username'  => 'encrypted',
        'proxy_password'  => 'encrypted',
        'proxy_checked_at' => 'datetime',
        'warm_day_count' => 'integer',
    ];

    public const STATUSES = ['connected', 'disconnected', 'needs_pair', 'failed'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /**
     * Owning workspace. Needed by the platform-admin device list, which spans
     * every workspace and must show who a device belongs to. withDefault()
     * keeps legacy NULL-workspace rows from blowing up the view.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class)->withDefault();
    }

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    /**
     * Workspace-scoped variant. Devices now carry `workspace_id` (added
     * in the 2026_05_19 backfill migration). Use this in place of
     * `forUser($id)` on any /devices listing or plan-limit count so
     * the visible set switches with the workspace dropdown.
     *
     * Legacy rows with NULL `workspace_id` fall back to user ownership
     * so pre-migration devices still appear until they're re-paired.
     */
    /**
     * Like `forCurrentWorkspace` but accepts an explicit workspace id.
     * Use this in queue jobs / services where there's no authed user
     * to read `current_workspace_id` off — e.g. the dispatcher picks
     * a device based on the message's `workspace_id` column.
     */
    public function scopeForWorkspace(Builder $q, ?int $workspaceId, ?int $fallbackUserId = null): Builder
    {
        if (!$workspaceId && !$fallbackUserId) return $q->whereRaw('1=0');
        return $q->where(function ($qq) use ($workspaceId, $fallbackUserId) {
            if ($workspaceId) $qq->where('workspace_id', $workspaceId);
            // Legacy fallback to user_id when workspace_id is missing.
            if ($fallbackUserId) {
                $qq->orWhere(function ($qqq) use ($fallbackUserId) {
                    $qqq->whereNull('workspace_id')->where('user_id', $fallbackUserId);
                });
            }
        });
    }

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // Devices belong to the workspace (per the Option B decision).
        // Every member of workspace Acme sees Acme's phones — `user_id`
        // is just a record of who paired the device, not a visibility
        // filter. Legacy NULL-workspace devices still visible to their
        // original pairer so pre-migration data isn't lost.
        //
        // EXCEPTION — impersonation: while a platform admin impersonates a
        // workspace, auth()->user() is STILL the admin (only current_workspace_id
        // is swapped to the client's workspace). The NULL-workspace fallback would
        // then match the ADMIN's own orphan devices (user_id = admin) and leak them
        // into the client's device list — e.g. the admin's "Hivera" number showing
        // under the client. So during impersonation we show ONLY the workspace's
        // real devices and drop the personal-orphan fallback.
        $impersonating = (bool) session('impersonation_session_id');

        return $q->where(function ($qq) use ($wsId, $uId, $impersonating) {
            $qq->where('workspace_id', $wsId);
            if (!$impersonating) {
                $qq->orWhere(function ($qqq) use ($uId) {
                    $qqq->whereNull('workspace_id')->where('user_id', $uId);
                });
            }
        });
    }

    public function scopeWithStatus(Builder $q, ?string $status): Builder
    {
        if (!$status || $status === 'all') return $q;
        return $q->where('status', $status);
    }

    public function scopeWithRegion(Builder $q, ?string $region): Builder
    {
        if (!$region || $region === 'all') return $q;
        return $q->where('region', $region);
    }

    /**
     * In-memory name search since `device_name` and
     * `phone_number` are encrypted (LIKE on ciphertext is
     * useless). Caller hydrates the indexable rows first, then
     * passes the collection through this method.
     */
    public static function filterBySearch($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') return $items;
        return $items->filter(function (self $d) use ($term) {
            return str_contains(mb_strtolower((string) $d->device_name),  $term)
                || str_contains(mb_strtolower((string) $d->phone_number), $term);
        })->values();
    }

    public function getDisplayPhoneAttribute(): string
    {
        $cc = $this->country_code ?: '';
        $ph = $this->phone_number ?: '';
        // Old project stored the country code already concatenated
        // onto phone_number — handle both shapes.
        return str_starts_with($ph, $cc) ? $ph : trim($cc . ' ' . $ph);
    }
}
