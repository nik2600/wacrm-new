<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chat conversation / send-queue. The hot operations are:
 *   - list (filtered by status, search, sort)
 *   - open (load messages)
 *   - send (append a message + bump preview/last_message_at)
 *   - archive / unarchive / destroy
 *
 * Use the provided scopes (`forUser`, `notArchived`, `withStatus`,
 * `search`, `sorted`) instead of repeating the same where-clauses
 * across the controller.
 */
class Conversation extends Model
{
    use HasFactory;
    // Multi-engine: forCurrentEngine() now scopes to the workspace's ENABLED
    // ENGINE SET (whereIn enginesFor), shared with the other engine-scoped
    // models, so the Team Inbox queue/badges/unread show every enabled
    // engine's conversations — not just the single default.
    use HasEngineScope;

    /**
     * Channels that are NOT tied to a WhatsApp send engine — their replies go
     * back to the origin surface (the chat widget polls /api/history for them),
     * NOT through baileys/waba/twilio. forCurrentEngine() must never hide these.
     * Without the exemption, a widget conversation stamped with the workspace's
     * (possibly disconnected) PRIMARY engine — e.g. provider='waba' while the
     * workspace's CONNECTED engine is 'baileys' — was filtered out of the inbox.
     */
    // 'instagram' threads arrive from the separate Instaflow deployment and have
    // no WhatsApp engine — like the chat widget, they must always show in the
    // unified inbox regardless of which WA engine the workspace has connected.
    public const ENGINE_AGNOSTIC_CHANNELS = ['chatbot_widget', 'instagram'];

    protected $fillable = [
        'user_id', 'workspace_id',
        'device_id', 'contact_group_id',
        'title', 'preview',
        'status', 'platform', 'provider', 'customer_language', 'archived', 'pinned_at', 'muted_at', 'origin',
        'recipients_count', 'last_message_at', 'scheduled_at', 'scheduled_timezone', 'raw_jid', 'alt_jid', 'contact_digits',
        // inbox lifecycle (separate from send-queue `status`)
        'inbox_status', 'priority',
        'assignee_user_id', 'assignee_team_id', 'assignee_agent_id',
        'first_response_at', 'snoozed_until', 'resolved_at', 'resolved_by', 'resolved_by_agent_id',
        'sla_policy_id', 'sla_first_response_due', 'sla_resolution_due', 'sla_breached',
        'channel', 'is_spam', 'routing_meta',
        'last_inbound_at', 'last_outbound_at', 'unread_count',
        'escalation_due_at', 'escalation_action',
    ];

    protected $casts = [
        // title/preview are PII the operator wrote (queue label,
        // last-message snippet) — encrypt them at rest the same
        // way the Contact model encrypts name/mobile/email.
        'title'            => \App\Casts\SafeEncrypted::class,
        'preview'          => \App\Casts\SafeEncrypted::class,
        'archived'         => 'boolean',
        'pinned_at'        => 'datetime',
        'muted_at'         => 'datetime',
        'recipients_count' => 'integer',
        'last_message_at'  => 'datetime',
        'scheduled_at'     => 'datetime',
        // inbox lifecycle
        'first_response_at'      => 'datetime',
        'snoozed_until'          => 'datetime',
        'resolved_at'            => 'datetime',
        'sla_first_response_due' => 'datetime',
        'sla_resolution_due'     => 'datetime',
        'sla_breached'           => 'boolean',
        'is_spam'                => 'boolean',
        'routing_meta'           => 'array',
        'last_inbound_at'        => 'datetime',
        'last_outbound_at'       => 'datetime',
        'unread_count'           => 'integer',
        'escalation_due_at'      => 'datetime',
        'escalation_action'      => 'array',
    ];

    public const STATUSES        = ['pending', 'sent', 'failed', 'scheduled'];
    public const INBOX_STATUSES  = ['open', 'pending', 'snoozed', 'resolved', 'closed', 'spam'];
    public const PRIORITIES      = ['low', 'normal', 'high', 'urgent'];

    /**
     * Keep `contact_digits` — the normalised one-thread-per-number identity —
     * in sync with whatever JID shape the writer happened to use.
     *
     * Deliberately a model hook rather than something each caller remembers to
     * do: this is the invariant the whole duplicate-thread fix rests on, and a
     * path that forgets it would silently become invisible to
     * ConversationResolver and start forking threads again. Doing it here means
     * every write — including ones added later — is covered.
     */
    protected static function booted(): void
    {
        static::saving(function (self $c) {
            if (!$c->isDirty(['raw_jid', 'alt_jid']) && $c->contact_digits !== null) {
                return;
            }

            $digits = \App\Services\Inbox\ConversationResolver::digitsFor($c->raw_jid)
                ?? \App\Services\Inbox\ConversationResolver::digitsFor($c->alt_jid);

            // Never blank an identity we already resolved — a later write that
            // only touches, say, `preview` must not orphan the row.
            if ($digits !== null) {
                $c->contact_digits = $digits;
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Team-inbox bubbles live in their own table (`inbox_messages`)
     * so the inbox UI isn't mixed up with campaign / broadcast rows.
     * Use this relation everywhere team-inbox code path — list view,
     * thread show, per-message actions, AI agent attribution.
     */
    public function inboxMessages(): HasMany
    {
        return $this->hasMany(InboxMessage::class)->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latestOfMany();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'contact_group_id')->withDefault();
    }

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    /**
     * Scope to the conversations owned by the authenticated user
     * AND living in the workspace they currently have selected from
     * the top-bar switcher. Use this in place of `forUser(Auth::id())`
     * on /chat and any other personal-inbox surface so the row set
     * actually changes when the workspace flips.
     *
     * Legacy rows with NULL `workspace_id` fall back to plain user
     * ownership — protects against pre-multi-workspace conversations
     * that haven't been backfilled yet.
     */
    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // Workspace-shared visibility: every conversation in the
        // current workspace, regardless of which teammate created it.
        // Mike (member of workspace Acme) should see conversations
        // Sara started in Acme. `user_id` is just a "who started it"
        // record — not a visibility filter.
        //
        // Legacy fallback: rows with NULL workspace_id (pre-multi-
        // workspace migration) stay visible to their original owner
        // only, so they're not lost while we backfill.
        return $q->where(function ($qq) use ($wsId, $uId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }

    /**
     * Limit to conversations created by the chat composer (the user-facing
     * inbox) — i.e. exclude rows the campaign dispatcher created. /chat
     * uses this; /wa-campaigns/{id} doesn't care because it reads from
     * the WpCampaignContact log instead.
     */
    public function scopeChatOnly(Builder $q): Builder
    {
        // /chat now shows the same per-number threads as Team Inbox — Quick Send
        // and inbound replies share ONE conversation per number (origin 'inbox'),
        // so a queue you start and the customer's reply stay together. We still
        // hide campaign-origin convos (they live at /wa-campaigns).
        return $q->whereIn('origin', ['chat', 'inbox', 'chatbot']);
    }

    /**
     * Scope conversations to a specific engine via the canonical
     * `provider` column ('waba' | 'baileys' | 'twilio'). Added by
     * migration 2026_05_26_140000 alongside a backfill of every
     * existing row to 'baileys'. The legacy `platform` column ('W' /
     * 'WB' / 'T') was historically mis-stamped — all Baileys + WABA
     * inbound got 'W' regardless — so it can't be trusted here.
     */
    public function scopeForEngine(Builder $q, string $engine): Builder
    {
        return $q->where('provider', $engine);
    }

    // scopeForCurrentEngine() is provided by the HasEngineScope trait — it
    // whereIn's the workspace's enabled engine set so a multi-engine workspace
    // sees every engine's conversations in the queue/badges/unread. (The old
    // local single-engine override was removed so the two can never drift.)

    public function scopeForWorkspace(Builder $q, ?int $workspaceId): Builder
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q;
    }

    public function scopeAssignedTo(Builder $q, int $userId): Builder
    {
        return $q->where('assignee_user_id', $userId);
    }

    public function scopeUnassigned(Builder $q): Builder
    {
        return $q->whereNull('assignee_user_id');
    }

    public function scopeWithInboxStatus(Builder $q, string|array $status): Builder
    {
        return is_array($status) ? $q->whereIn('inbox_status', $status) : $q->where('inbox_status', $status);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('inbox_status', ['open', 'pending']);
    }

    /**
     * Override the shared HasEngineScope filter for CONVERSATIONS only.
     * WhatsApp threads stay restricted to the workspace's connected engines,
     * but WEB channels (chat widget) are engine-agnostic and must always show.
     *
     * Root cause this fixes: the widget stamps provider = WorkspaceEngine::for()
     * = the workspace's PRIMARY provider config, which can be a WABA row that is
     * marked primary but NOT connected. The inbox filters by enginesFor() (the
     * CONNECTED set, e.g. ['baileys']), so a provider='waba' widget chat was
     * excluded → 6 saved, 0 visible. The channel exemption makes it independent
     * of the (mismatched) provider, and needs no backfill of existing rows.
     */
    public function scopeForCurrentEngine(Builder $q): Builder
    {
        $wsId = (int) (auth()->user()?->current_workspace_id ?? 0);
        if (!$wsId) return $q;
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $table   = $this->getTable();

        // Instagram threads are engine-agnostic (they arrive from the separate
        // Instaflow deployment, not a local send engine) — but ONLY while the
        // workspace actually has a connected Instagram account. Disconnect the
        // last account and 'instagram' drops out of the exempt list, so its
        // threads stop showing across the whole inbox until one reconnects. The
        // chat widget stays always-visible.
        $agnostic = ['chatbot_widget'];
        if (\App\Models\WorkspaceIgAccount::hasConnected($wsId)) {
            $agnostic[] = 'instagram';
        }

        return $q->where(function (Builder $w) use ($engines, $table, $agnostic) {
            $w->whereIn($table . '.provider', $engines)
              ->orWhereIn($table . '.channel', $agnostic);
        });
    }

    public function scopeSlaBreached(Builder $q): Builder
    {
        return $q->where('sla_breached', true);
    }

    public function scopeForTeam(Builder $q, int $teamId): Builder
    {
        return $q->where('assignee_team_id', $teamId);
    }

    /**
     * Hide conversations whose Unofficial-API (Baileys) number has been
     * DELETED — the devices row is gone, so the thread can never be replied to
     * and just clutters the inbox. The data is NOT removed; it simply stops
     * showing, and reappears the moment a device with that id exists again
     * (i.e. the number is reconnected). WABA / Twilio conversations key
     * device_id to wa_provider_configs (a different table), and device-less /
     * legacy rows have no device_id — both are left untouched.
     */
    public function scopeDeviceAlive(Builder $q): Builder
    {
        return $q->where(function (Builder $w) {
            $w->whereNull('device_id')
              ->orWhereIn('provider', ['waba', 'twilio'])
              ->orWhereExists(function ($sub) {
                  $sub->selectRaw('1')->from('devices')
                      ->whereColumn('devices.id', 'conversations.device_id');
              });
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id')->withDefault();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assignee_team_id')->withDefault();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AiAgent::class, 'assignee_agent_id')->withDefault();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class)->orderByDesc('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class)->orderByDesc('created_at');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag')->withTimestamps()->withPivot('added_by');
    }

    public function scopeNotArchived(Builder $q): Builder
    {
        return $q->where('archived', false);
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->where('archived', true);
    }

    /** Pinned conversations sort above everything else in the queue. */
    public function scopePinned(Builder $q): Builder
    {
        return $q->whereNotNull('pinned_at');
    }

    /** Muted conversations never ping / pop a notification. */
    public function scopeMuted(Builder $q): Builder
    {
        return $q->whereNotNull('muted_at');
    }

    public function scopeWithStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    /**
     * Search is intentionally NOT a SQL scope because the columns it
     * looks at (title / preview) are encrypted-at-rest — `LIKE` over
     * ciphertext matches nothing. Callers should hydrate the
     * collection (with the SQL-side filters applied) and then run
     * Conversation::filterBySearch($items, $term) to do a plaintext
     * pass in PHP.
     */
    public static function filterBySearch($items, ?string $term)
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') {
            return $items;
        }
        return $items->filter(function (self $c) use ($term) {
            // Tolerate plaintext leftovers in the encrypted columns —
            // a single corrupt row would otherwise throw DecryptException
            // and crash the entire search.
            return str_contains(mb_strtolower((string) self::safeRead($c, 'title')),   $term)
                || str_contains(mb_strtolower((string) self::safeRead($c, 'preview')), $term);
        })->values();
    }

    /**
     * Read an encrypted-cast attribute without crashing on a legacy
     * plaintext row. Eloquent's encrypted cast throws DecryptException
     * when the column value isn't a valid ciphertext (pre-cast rows or
     * raw inserts that bypassed the cast); we fall back to the raw
     * column value so the inbox doesn't 500 on one bad row.
     */
    public static function safeRead(self $c, string $field): ?string
    {
        try {
            return $c->{$field};
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            $raw = $c->getRawOriginal($field);
            return is_string($raw) ? $raw : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * SQL-side sort. `title` is encrypted (non-deterministic
     * ciphertext) so `name-asc` / `name-desc` would order on
     * ciphertext if we let the DB handle them — we apply those in
     * PHP via sortBySorted() after hydration.
     */
    public function scopeSorted(Builder $q, ?string $sort): Builder
    {
        // `name-asc` / `name-desc` deliberately fall through to the
        // default date-desc — once rows are decrypted in PHP,
        // sortByKey() will reorder them by title.
        return $sort === 'date-asc'
            ? $q->orderBy('last_message_at')->orderBy('id')
            : $q->orderByDesc('last_message_at')->orderByDesc('id');
    }

    /**
     * Apply name-based sort in PHP (after rows are decrypted into
     * the collection). For date sorts the DB has already done the
     * right thing, so this is a no-op for those.
     */
    public static function sortByKey($items, ?string $sort)
    {
        return match ($sort) {
            'name-asc'  => $items->sortBy(fn ($c)        => mb_strtolower((string) self::safeRead($c, 'title')))->values(),
            'name-desc' => $items->sortByDesc(fn ($c)    => mb_strtolower((string) self::safeRead($c, 'title')))->values(),
            default     => $items,
        };
    }

    /**
     * Filter to one of: all, scheduled, archived, sent, pending, failed.
     */
    public function scopeFiltered(Builder $q, ?string $filter): Builder
    {
        return match ($filter) {
            'archived'  => $q->archived(),
            'scheduled' => $q->notArchived()->withStatus('scheduled'),
            'sent'      => $q->notArchived()->withStatus('sent'),
            'pending'   => $q->notArchived()->withStatus('pending'),
            'failed'    => $q->notArchived()->withStatus('failed'),
            default     => $q->notArchived(),
        };
    }
}
