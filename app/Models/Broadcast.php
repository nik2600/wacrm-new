<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template broadcast — one row per send-out, recipient-level
 * fanout sits in `broadcast_contacts`. Mirrors the old project's
 * Broadcast model with two adjustments:
 *   - `name` and `timezone` are encrypted-at-rest (Contact pattern)
 *   - the relation set drops `template` / `creator` until the
 *     Templates model and per-user creator FK land in this codebase
 */
class Broadcast extends Model
{
    use HasEngineScope, HasFactory, LogsNotifications;

    /**
     * Auto-stamp `provider` on create from the workspace's active engine.
     * Defensive cover for callsites that forgot to pass it explicitly.
     */
    protected static function booted(): void
    {
        static::creating(function (self $b) {
            if (empty($b->provider) && !empty($b->workspace_id)) {
                try {
                    $b->provider = \App\Services\WorkspaceEngine::for((int) $b->workspace_id);
                } catch (\Throwable $e) {}
            }
        });

        // Webhook: broadcast_created / broadcast_status_updated. Fired from
        // the model so every status write (controller + Node callbacks +
        // failure paths) is covered. emit() is deferred + guarded.
        static::created(function (self $b) {
            \App\Services\WebhookService::emit('broadcast_created', [
                'workspace_id'     => $b->workspace_id,
                'user_id'          => $b->user_id,
                'broadcast_id'     => $b->id,
                'broadcast_name'   => $b->name,
                'template_id'      => $b->template_id,
                'status'           => $b->status,
                'total_recipients' => (int) $b->total_recipients,
                'timestamp'        => now()->timestamp,
            ], $b->user_id);
        });

        static::updated(function (self $b) {
            if (!$b->wasChanged('status')) return;
            \App\Services\WebhookService::emit('broadcast_status_updated', [
                'workspace_id'     => $b->workspace_id,
                'user_id'          => $b->user_id,
                'broadcast_id'     => $b->id,
                'broadcast_name'   => $b->name,
                'template_id'      => $b->template_id,
                'status'           => $b->status,
                'previous_status'  => $b->getOriginal('status'),
                'total_recipients' => (int) $b->total_recipients,
                'success_count'    => (int) $b->success_count,
                'delivered_count'  => (int) $b->delivered_count,
                'read_count'       => (int) $b->read_count,
                'fail_count'       => (int) $b->fail_count,
                'timestamp'        => now()->timestamp,
            ], $b->user_id);
        });
    }

    protected $fillable = [
        'user_id',
        'workspace_id',
        'provider',
        'device_id',
        'template_id',
        // Per-send overrides for the selected template's variables /
        // header media / button URLs. See TemplateOverrideResolver.
        'template_overrides',
        'name',
        'timezone',
        'status',
        // Arbitrary message content for the mobile-app queue (custom sends).
        'temp_caption',
        'template_type',
        'temp_image',
        'button_text',
        'latitude',
        'longitude',
        'pinned',
        'archived',
        'scheduled_at',
        'completed_at',
        'node_schedule_id',
        'total_recipients',
        'success_count',
        'fail_count',
        'send_attempts',
        'next_attempt_at',
    ];

    protected $casts = [
        // PII — operator-authored title + timezone, plus the
        // counter columns kept as proper integers for arithmetic.
        'name'             => 'encrypted',
        'timezone'         => 'encrypted',
        'template_overrides' => 'encrypted:array',
        'pinned'           => 'boolean',
        'archived'         => 'boolean',
        'scheduled_at'     => 'datetime',
        'completed_at'     => 'datetime',
        'next_attempt_at'  => 'datetime',
        'send_attempts'    => 'integer',
        'total_recipients' => 'integer',
        'success_count'    => 'integer',
        'fail_count'       => 'integer',
    ];

    public const STATUSES = ['scheduled', 'processing', 'completed', 'completed_with_errors', 'failed'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'broadcast_contacts')
            ->withPivot(['status', 'error_message', 'whatsapp_message_id', 'sent_at', 'delivered_at', 'read_at'])
            ->withTimestamps();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastContact::class);
    }

    /**
     * Roll the per-recipient `broadcast_contacts` rows up into the
     * cached aggregate columns `getStatusCountsAttribute()` reads.
     *
     * Every engine funnels through here: WABA lands status webhooks in
     * WaWebhookController, Unofficial (Baileys) and Twilio land them in
     * BroadcastsController::nodeMessageStatus. That last path used to
     * write only success_count/fail_count, so a Baileys or Twilio
     * broadcast showed Delivered 0 / Read 0 forever — the fast path in
     * getStatusCounts engages as soon as success_count > 0, so the
     * live-count fallback never got a chance to correct it.
     *
     * The hierarchy cascades: a `read` row is also delivered and sent.
     * Without that the counters would drop back to zero the moment
     * WhatsApp confirms a read and the row leaves the `sent` bucket.
     *
     * `clicked_count` is deliberately untouched — LinkRedirectController
     * increments it directly and it has no per-recipient status to
     * derive from.
     */
    public function recomputeAggregates(): self
    {
        $rows = \DB::table('broadcast_contacts')
            ->select('status', \DB::raw('COUNT(*) as c'))
            ->where('broadcast_id', $this->id)
            ->groupBy('status')
            ->pluck('c', 'status');

        $sent      = (int) ($rows['sent']      ?? 0);
        $delivered = (int) ($rows['delivered'] ?? 0);
        $read      = (int) ($rows['read']      ?? 0);
        $failed    = (int) ($rows['failed']    ?? 0);

        $this->forceFill([
            'success_count'   => $sent + $delivered + $read,
            'delivered_count' => $delivered + $read,
            'read_count'      => $read,
            'fail_count'      => $failed,
        ])->saveQuietly();

        return $this;
    }

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    /**
     * Workspace-shared visibility. Every member of the current
     * workspace sees every broadcast in it (regardless of who created
     * it) — `user_id` stays as a "created by" record, not a filter.
     * Pre-migration rows with NULL workspace_id fall back to their
     * original creator only.
     */
    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }

    public function scopeWithStatus(Builder $q, ?string $status): Builder
    {
        if (!$status || $status === 'all') return $q;
        return $q->where('status', $status);
    }

    /**
     * Search by encrypted broadcast name — runs in PHP after
     * hydration since LIKE on ciphertext matches nothing. Caller
     * passes the already-fetched collection in.
     */
    public static function filterByName($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') return $items;
        return $items->filter(fn ($b) => str_contains(mb_strtolower((string) $b->name), $term))->values();
    }

    /**
     * Recipient-level rollups for one broadcast.
     *
     * Two paths: (1) FAST — read the cached aggregate columns
     * (`success_count`, `delivered_count`, `read_count`,
     * `fail_count`, `clicked_count`) maintained by
     * WaWebhookController::recomputeBroadcastAggregates on every
     * status webhook + by LinkRedirectController on every click;
     * (2) SLOW — live-count from `scheduled_message_contacts` for
     * broadcasts predating the aggregate columns. Either way the
     * sent/delivered/read hierarchy cascades so a `read` row is also
     * counted as `delivered` and `sent` (without that, the columns
     * would zero-out the moment WhatsApp confirms a read).
     */
    public function getStatusCountsAttribute(): array
    {
        // Fast path — cached columns populated by webhook handler.
        // `success_count` is sent+delivered+read; `delivered_count`
        // is delivered+read; `read_count` is read-only. Use them
        // when at least one is non-zero (avoids stale-zero on fresh
        // broadcasts that haven't seen a webhook yet).
        if ((int) $this->success_count > 0
            || (int) $this->fail_count > 0
            || (int) $this->delivered_count > 0
            || (int) $this->read_count > 0
            || (int) $this->clicked_count > 0) {
            return [
                'sent'       => (int) $this->success_count,
                'delivered'  => (int) $this->delivered_count,
                'read'       => (int) $this->read_count,
                'failed'     => (int) $this->fail_count,
                'clicked'    => (int) $this->clicked_count,
                'pending'    => max(0, (int) $this->total_recipients - (int) $this->success_count - (int) $this->fail_count),
                'processing' => 0,
            ];
        }

        // Slow path — pre-aggregate-column broadcasts.
        $base    = $this->recipients();
        $clicked = \DB::table('wa_link_clicks')
            ->where('broadcast_id', $this->id)
            ->where('clicks', '>', 0)
            ->distinct('contact_id')
            ->count('contact_id');

        return [
            'sent'       => (clone $base)->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered'  => (clone $base)->whereIn('status', ['delivered', 'read'])->count(),
            'read'       => (clone $base)->where('status', 'read')->count(),
            'failed'     => (clone $base)->where('status', 'failed')->count(),
            'clicked'    => $clicked,
            'pending'    => (clone $base)->where('status', 'pending')->count(),
            'processing' => (clone $base)->where('status', 'processing')->count(),
        ];
    }
}
