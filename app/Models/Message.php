<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single message inside a conversation. Outgoing messages
 * have direction='out'; incoming customer replies are 'in'.
 *
 * `status` uses string enum values (pending|scheduled|sent|
 * delivered|read|failed) — the old project used integer codes
 * (0/1/2) which were a constant source of bugs whenever you
 * read the DB without the controller's mapping.
 */
class Message extends Model
{
    use HasEngineScope, HasFactory, SoftDeletes;

    /**
     * Auto-stamp `provider` from the parent Conversation's engine (the
     * thread already knows which engine sent it). Fall back to the
     * workspace's active engine only when no conversation context
     * exists (rare — most Message rows are conversation-scoped).
     *
     * Earlier code resolved via `WorkspaceEngine::for($workspace_id)`
     * which uses `system_settings.default_send_method` as a fallback
     * when the workspace has no WaProviderConfig. That fallback flips
     * the auto-stamp to whatever the platform admin set as the global
     * default, even if the actual send went through Baileys. Reading
     * the conversation's stamp avoids that drift — conversations are
     * stamped from the actual inbound/outbound dispatch context.
     */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (!empty($m->provider)) return;
            try {
                if (!empty($m->conversation_id)) {
                    $conv = \App\Models\Conversation::query()->find($m->conversation_id);
                    if ($conv && $conv->provider) {
                        $m->provider = $conv->provider;
                        return;
                    }
                }
                if (!empty($m->workspace_id)) {
                    $m->provider = \App\Services\WorkspaceEngine::for((int) $m->workspace_id);
                }
            } catch (\Throwable $e) { /* never block creates on resolver hiccup */ }
        });

        // Mirror every chat message into inbox_messages so the team inbox
        // shows the SAME history as /chat.
        //
        // /chat writes `messages`; the team inbox reads `inbox_messages`.
        // They share the `conversations` table, so a /chat thread appeared in
        // the inbox list but rendered EMPTY — 5 live conversations were in
        // that state. Mirroring here rather than at each Message::create call
        // site means every writer (chat, extension, mobile API, widget) is
        // covered by one rule instead of four that can drift apart.
        static::created(function (self $m) {
            if (self::$skipInboxMirror) return;                 // caller writes both itself
            if (empty($m->conversation_id)) return;             // nothing to attach to
            try {
                // Same wamid de-dupe as InboxMirror. A campaign on Unofficial
                // or Twilio can reach the thread from BOTH directions — the
                // mirror when PHP records the send, and this observer when
                // Node writes the Message. Whichever lands first wins; the
                // second is a no-op instead of a duplicate bubble.
                $wamid = is_array($m->meta) ? ($m->meta['wa_message_id'] ?? null) : null;
                $wamid = $wamid ?: ($m->whatsapp_message_id ?? null);
                if ($wamid && \App\Models\InboxMessage::where('conversation_id', $m->conversation_id)
                        ->where('meta->wa_message_id', $wamid)->exists()) {
                    return;
                }

                \App\Models\InboxMessage::create([
                    'conversation_id' => $m->conversation_id,
                    'user_id'         => $m->user_id ?? null,
                    'direction'       => $m->direction ?: 'out',
                    'to_number'       => $m->to_number ?? null,
                    'from_number'     => $m->from_number ?? null,
                    'body'            => (string) ($m->body ?? ''),
                    'status'          => $m->status ?: 'sent',
                    'provider'        => $m->provider ?: null,
                    'media_path'      => $m->media_path ?? null,
                    'media_type'      => $m->media_type ?? null,
                    'template_id'     => $m->template_id ?? null,
                    'meta'            => array_merge(
                        is_array($m->meta) ? $m->meta : [],
                        ['mirrored_from_message_id' => $m->id],
                    ),
                    'sent_at'         => $m->sent_at ?? $m->created_at,
                ]);
            } catch (\Throwable $e) {
                // Best-effort: a mirror failure must never fail the send.
                \Illuminate\Support\Facades\Log::warning(
                    '[MSG-MIRROR] inbox mirror failed for message ' . $m->id . ': ' . $e->getMessage()
                );
            }
        });
    }

    /**
     * Set while a caller creates BOTH a Message and its InboxMessage itself
     * (QuickMessageController, ExtensionApiController,
     * ChatbotWidgetPublicController). Without it the observer above would add
     * a second, duplicate bubble to those threads.
     */
    public static bool $skipInboxMirror = false;

    /** Run $fn with the inbox mirror suppressed, restoring the flag after. */
    public static function withoutInboxMirror(callable $fn)
    {
        $prev = self::$skipInboxMirror;
        self::$skipInboxMirror = true;
        try { return $fn(); } finally { self::$skipInboxMirror = $prev; }
    }

    protected $fillable = [
        'conversation_id',
        'user_id',
        'workspace_id',
        'provider',
        'agent_id',
        'contact_id',
        'template_id',
        'direction',
        'to_number',
        'from_number',
        'body',
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
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        // Encrypt-at-rest the customer-facing PII (numbers + message
        // body + any failure reason that may quote the recipient).
        // Ciphertext is non-deterministic so these columns are NOT
        // indexable — see ChatController@searchNumbers for the
        // in-memory filter pattern.
        'to_number'      => 'encrypted',
        'from_number'    => 'encrypted',
        'body'           => 'encrypted',
        'failure_reason' => 'encrypted',
        'latitude'       => 'decimal:7',
        'longitude'      => 'decimal:7',
        'pinned'         => 'boolean',
        'starred'        => 'boolean',
        'meta'           => 'array',
        'scheduled_at'   => 'datetime',
        'sent_at'        => 'datetime',
        'delivered_at'   => 'datetime',
        'read_at'        => 'datetime',
    ];

    public const DIRECTIONS = ['out', 'in'];

    public const STATUSES = [
        'pending', 'scheduled', 'sent', 'delivered', 'read', 'failed',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id')->withDefault();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withDefault();
    }

    public function scopeOutgoing(Builder $q): Builder
    {
        return $q->where('direction', 'out');
    }

    public function scopeIncoming(Builder $q): Builder
    {
        return $q->where('direction', 'in');
    }

    /**
     * Workspace-shared visibility — every member of the current
     * workspace sees every message in it (e.g. for /message-history
     * and analytics). Pre-migration rows with NULL workspace_id fall
     * back to original-sender visibility.
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

    /**
     * Format the timestamp for the chat bubble. Same display
     * logic the JS used previously (HH:MM today, "Mon dd"
     * earlier in the year, year for older messages).
     */
    public function getDisplayTimeAttribute(): string
    {
        $when = $this->sent_at ?: $this->created_at;
        if (!$when) {
            return '';
        }
        $now = now();
        if ($when->isToday())     return $when->format('H:i');
        if ($when->isYesterday()) return 'Yesterday';
        if ($when->year === $now->year) return $when->format('M d');
        return $when->format('M d, Y');
    }
}
