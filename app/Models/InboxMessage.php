<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InboxMessage — a single bubble inside a team-inbox / chat conversation.
 *
 * Lives in its own `inbox_messages` table (separate from `messages` which
 * carries campaigns / broadcasts / scheduled blasts). Same wire-shape as
 * Message for everything the WhatsAppDispatcher reads, so the dispatcher
 * works with either via duck-typing.
 *
 * What's HERE that's not on Message:
 *   - agent_id, reaction, pinned, starred, quality_score, quality_note —
 *     all the inbox-only state.
 *
 * What's intentionally NOT here:
 *   - scheduled_at, scheduled_timezone — inbox doesn't schedule replies.
 *
 * Discrimination from old code is by table, not by column — `messages`
 * stays for the legacy /chat + campaigns + broadcasts surfaces.
 */
class InboxMessage extends Model
{
    use HasEngineScope, SoftDeletes;

    protected $table = 'inbox_messages';

    /**
     * Auto-stamp `provider` on create from the parent Conversation's
     * workspace engine. InboxMessage has no direct workspace_id column,
     * so we resolve via conversation_id → conversations.workspace_id →
     * WorkspaceEngine. Skipped only if the caller already supplied a
     * value. This covers every InboxMessage::create() callsite without
     * having to touch them all (20+ sites across controllers).
     */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!empty($m->provider) || empty($m->conversation_id)) return;
            try {
                // Read the conversation's provider directly — it was
                // stamped from the actual send/receive dispatch context.
                // Going through WorkspaceEngine::for() instead falls
                // through to system_settings.default_send_method when
                // the workspace has no WaProviderConfig, which produced
                // wrong stamps (every Baileys send got 'waba' if the
                // global default was 'waba'). The conversation stamp
                // is the source of truth.
                $conv = \App\Models\Conversation::query()->find($m->conversation_id);
                if ($conv && $conv->provider) {
                    $m->provider = $conv->provider;
                    return;
                }
                if ($conv && $conv->workspace_id) {
                    $m->provider = \App\Services\WorkspaceEngine::for((int) $conv->workspace_id);
                }
            } catch (\Throwable $e) { /* never block creates on resolver hiccup */ }
        });

        // Real-time translation — on every INBOUND message, detect the
        // customer's language, pin it on the conversation, and store the
        // agent-language translation. One observer covers all inbound
        // create-sites (WABA / Baileys / Twilio / widget). Best-effort.
        static::created(function (self $m) {
            if (($m->direction ?? '') !== 'in') return;
            $id = $m->id;
            // Defer the (HTTP) detect+translate OFF the inbound webhook request
            // so message reception is never blocked / back-pressured. Runs after
            // the response is flushed; the translation lands on the row a beat
            // later and shows on the next thread load.
            app()->terminating(function () use ($id) {
                try {
                    $fresh = self::find($id);
                    if ($fresh) {
                        app(\App\Services\Inbox\ConversationTranslationService::class)->ingestInbound($fresh);
                    }
                } catch (\Throwable $e) { /* best-effort, never surface */ }
            });

            // Team-Inbox PWA push — ring the assigned agent's (or opted-in
            // agents') devices even when the app is closed. Deferred like the
            // translation above so it never back-pressures message reception;
            // no-ops when the feature is off / VAPID keys aren't set.
            app()->terminating(function () use ($id) {
                try {
                    $fresh = self::find($id);
                    if ($fresh) {
                        app(\App\Services\Push\TeamInboxPushNotifier::class)->notifyInbound($fresh);
                    }
                } catch (\Throwable $e) { /* best-effort, never surface */ }
            });
        });
    }

    protected $fillable = [
        'conversation_id',
        'user_id',
        'agent_id',
        'contact_id',
        'template_id',
        'provider',
        'direction',
        'to_number',
        'from_number',
        'body',
        'detected_language',
        'translated_body',
        'is_translated',
        'media_path',
        'media_type',
        'latitude',
        'longitude',
        'status',
        'failure_reason',
        'pinned',
        'starred',
        'reaction',
        'quality_score',
        'quality_note',
        'meta',
        'sent_at',
        'delivered_at',
        'read_at',
        'edited_at',
    ];

    protected $casts = [
        // Same encryption pattern as Message — keeps customer PII safe.
        // SafeEncrypted (not the stock 'encrypted' cast) so a row written under
        // a different APP_KEY / a plaintext leftover returns raw instead of
        // throwing DecryptException and 500-ing the whole inbox queue.
        'to_number'      => \App\Casts\SafeEncrypted::class,
        'from_number'    => \App\Casts\SafeEncrypted::class,
        'body'           => \App\Casts\SafeEncrypted::class,
        'translated_body'=> \App\Casts\SafeEncrypted::class,
        'is_translated'  => 'boolean',
        'failure_reason' => \App\Casts\SafeEncrypted::class,
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
        'pinned'         => 'boolean',
        'starred'        => 'boolean',
        'meta'           => 'array',
        'sent_at'        => 'datetime',
        'delivered_at'   => 'datetime',
        'read_at'        => 'datetime',
        'edited_at'      => 'datetime',
    ];

    /**
     * WhatsApp's own edit window is 15 minutes. We mirror that exactly so
     * a customer's WhatsApp client accepts our edit instead of silently
     * showing the original.
     */
    public const EDIT_WINDOW_MINUTES = 15;

    /**
     * True iff this message can still be edited. WhatsApp's rule is just
     * "your own message, within 15 minutes" — so the UI should mirror
     * that exactly. The wa_message_id requirement is enforced at edit
     * time by the controller (which returns a clear 422 if it's
     * genuinely missing), not here — otherwise legitimate fresh sends
     * disappear from the UI when the provider id arrives a beat late.
     *
     *  - direction must be outbound
     *  - status must NOT be sending/failed (only sent/delivered/read editable)
     *  - within EDIT_WINDOW_MINUTES of when the message was created
     */
    public function isEditable(): bool
    {
        if ($this->direction !== 'out') return false;
        if (in_array($this->status, ['sending', 'failed', 'pending'], true)) return false;
        $anchor = $this->sent_at ?: $this->created_at;
        if (!$anchor) return false;
        return $anchor->gt(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    public const DIRECTIONS = ['out', 'in'];
    public const STATUSES   = ['pending', 'sent', 'delivered', 'read', 'failed'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id')->withDefault();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function scopeOut(Builder $q): Builder { return $q->where('direction', 'out'); }
    public function scopeIn(Builder $q):  Builder { return $q->where('direction', 'in'); }

    /**
     * Workspace-shared visibility — inbox_messages has NO workspace_id
     * column of its own (it's on the parent Conversation), so we
     * filter through the relationship instead.
     */
    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->whereHas('conversation', function ($cq) use ($wsId, $uId) {
            $cq->where(function ($qq) use ($wsId, $uId) {
                $qq->where('workspace_id', $wsId)
                   ->orWhere(function ($qqq) use ($uId) {
                       $qqq->whereNull('workspace_id')->where('user_id', $uId);
                   });
            });
        });
    }

    /** Human-friendly time label — matches Message::display_time for parity. */
    public function getDisplayTimeAttribute(): string
    {
        $ts = $this->sent_at ?: $this->created_at;
        if (!$ts) return '';
        return $ts->isToday() ? $ts->format('H:i') : $ts->format('M d');
    }
}
