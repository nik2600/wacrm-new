<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Flow — auto-replies & chatbot graph saved by the React builder.
 *
 * Adapted from D:\wadesk_2806\New folder\app\Models\Flow.php with two
 * adjustments to match the rest of this app:
 *   - flow_name + flow_data are encrypted-at-rest like every other PII
 *     column (Contact / Broadcast / WaTemplate pattern)
 *   - LogsNotifications writes a Notification row on each create/update/delete
 *
 * `flow_data` is stored as encrypted JSON. The accessor returns the
 * decoded array. The optional `flow_file_path` mirrors the old project's
 * uploads/flows/flow_{id}.json fallback so the Node.js bridge can read
 * the graph from disk if it doesn't talk to the DB directly.
 */
class Flow extends Model
{
    use HasEngineScope, HasFactory, SoftDeletes, LogsNotifications;

    /**
     * Auto-stamp `provider` on create from the workspace's active engine.
     */
    protected static function booted(): void
    {
        static::creating(function (self $f) {
            if (empty($f->provider) && !empty($f->workspace_id)) {
                try {
                    $f->provider = \App\Services\WorkspaceEngine::for((int) $f->workspace_id);
                } catch (\Throwable $e) {}
            }
        });

        // Keep the managed keyword-trigger auto-reply row in lock-step with the
        // flow on EVERY state change — create, edit, pause/resume, publish,
        // unpublish. This is what makes a Trigger-node keyword actually start
        // the flow on inbound (the bot's /api/keyword-replies matcher only
        // reads keyword_replies, never the encrypted flow_data).
        static::saved(function (self $f) {
            $f->syncKeywordTriggerReply();
        });

        // Deleting a flow must take its managed trigger rule with it. Without
        // this the rule outlives the flow and keeps showing (and matching) in
        // /auto-reply or the Instagram automations list, pointing at a flow
        // that can never run.
        static::deleted(function (self $f) {
            try {
                \App\Models\KeywordReply::withTrashed()
                    ->where('flow_id', $f->id)->where('is_flow_trigger', true)->forceDelete();
                \App\Models\InstagramAutomation::where('flow_id', $f->id)
                    ->where('type', 'flow')->delete();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[FLOW] trigger-rule cleanup failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Mirror the flow's keyword Trigger node into a managed keyword_replies row
     * (reply_type='flow') so the existing inbound auto-reply matcher fires this
     * flow when a customer messages one of the keywords. Idempotent: it wipes
     * any prior managed row for this flow and recreates it from current state.
     * The row is ACTIVE only while the flow is published + active.
     */
    public function syncKeywordTriggerReply(): void
    {
        try {
            \Illuminate\Support\Facades\Log::info('[FLOW-KW-SYNC] save', [
                'flow_id'      => $this->id,
                'workspace_id' => $this->workspace_id,
                'trigger_kind' => $this->trigger_kind,
                'keywords'     => $this->trigger_keywords,
                'device_id'    => $this->trigger_device_id,
                'is_published' => (bool) $this->is_published,
                'is_active'    => (bool) $this->is_active,
            ]);

            // Drop the previous managed row(s) — disposable, regenerated here.
            \App\Models\KeywordReply::withTrashed()
                ->where('flow_id', $this->id)
                ->where('is_flow_trigger', true)
                ->forceDelete();

            if ($this->trigger_kind !== 'keyword') {
                \Illuminate\Support\Facades\Log::info('[FLOW-KW-SYNC] skip — trigger is not "keyword", no rule created', [
                    'flow_id' => $this->id, 'trigger_kind' => $this->trigger_kind,
                ]);
                return;
            }

            // Instagram flows must NOT get a keyword_replies row. That table is
            // the WhatsApp inbound matcher, and its device_id would be carrying
            // an instagram_accounts id — which can collide with a real
            // devices.id in the same workspace and fire this IG flow on a
            // WhatsApp message.
            //
            // They get the Instagram EQUIVALENT instead: a managed
            // instagram_automations row (type='flow'), so an Instagram flow
            // shows up in the Instagram automations list exactly like a
            // WhatsApp flow shows up in /auto-reply.
            if ($this->flow_type === 'instagram') {
                $this->syncInstagramTriggerAutomation();
                return;
            }
            $keywords = trim((string) ($this->trigger_keywords ?? ''));
            if ($keywords === '' || empty($this->workspace_id)) {
                \Illuminate\Support\Facades\Log::warning('[FLOW-KW-SYNC] skip — NO keyword rule created', [
                    'flow_id' => $this->id,
                    'reason'  => $keywords === '' ? 'trigger has no keywords' : 'flow has no workspace_id',
                ]);
                return;
            }

            // "Any inbound message" — the DEFAULT ROUTE.
            //
            // Historically the only way to express this was typing `*` / `.*`,
            // which became a regex rule sitting in the same candidate pile as
            // real keywords — and since the matcher had no ordering, whichever
            // rule was saved first won. A catch-all created before a `book`
            // flow silently swallowed it.
            //
            // Now it is a real column. The rule is flagged `is_catch_all` and
            // the matcher resolves it as a separate, lower tier, so it CANNOT
            // outrank a keyword. The legacy `*` / `.*` spellings are still
            // accepted as input so existing flows keep working — they just set
            // the flag now instead of producing a regex.
            $isCatchAll = in_array($keywords, ['*', '.*', '.+', 'any'], true);

            // Stamp the engine on the rule. WITHOUT this the row lands with
            // provider=NULL, and the /auto-reply list's forCurrentEngine() scope
            // (whereIn('provider', enabledEngines)) can NEVER match a NULL — so
            // the flow's keyword rule fires fine (the matcher is device-scoped,
            // provider-agnostic) but is INVISIBLE in the auto-reply table. Mirror
            // the flow's own provider (already stamped on the flow) so the rule
            // shows under the same engine the flow runs on.
            $ruleProvider = $this->provider
                ?: \App\Services\WorkspaceEngine::for((int) $this->workspace_id);

            $rule = \App\Models\KeywordReply::create([
                'user_id'          => $this->user_id,
                'workspace_id'     => $this->workspace_id,
                'device_id'        => $this->trigger_device_id,
                'provider'         => $ruleProvider,
                // Catch-all rules carry NO pattern — the flag is the whole
                // condition, so there is nothing to preg_match on every
                // inbound and nothing a customer can type to collide with it.
                'keyword'          => $isCatchAll ? '' : $keywords,
                'matching_method'  => $isCatchAll ? 'exact' : 'contains',
                'is_catch_all'     => $isCatchAll,
                'fuzzy_similarity' => 80,
                'reply_type'       => 'flow',
                'flow_id'          => $this->id,
                'is_flow_trigger'  => true,
                'message_type'     => 'text',
                // Only live once the flow is published AND active.
                'status'           => (bool) ($this->is_published && $this->is_active),
            ]);

            \Illuminate\Support\Facades\Log::info('[FLOW-KW-SYNC] keyword_replies row created', [
                'flow_id'   => $this->id,
                'rule_id'   => $rule->id,
                'keyword'   => $rule->keyword,
                'device_id' => $rule->device_id,
                'status'    => (bool) $rule->status,
                'note'      => $rule->device_id
                    ? ((bool) $rule->status ? 'LIVE — will fire on this device' : 'INACTIVE — publish + activate the flow to make it fire')
                    : 'device_id is NULL — the inbound matcher is device-scoped; set a Device on the Trigger node or it will not match any inbound',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[FLOW] keyword-trigger sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Instagram counterpart of syncKeywordTriggerReply().
     *
     * Mirrors an Instagram flow's keyword Trigger node into a managed
     * instagram_automations row (type='flow') so it behaves — and is VISIBLE —
     * exactly like a WhatsApp flow's auto-reply rule. Same contract as the
     * WhatsApp one: idempotent, wipes its own prior row, and the rule is only
     * ACTIVE while the flow is published + active.
     *
     * The row is bound to the account picked on the Trigger node. Without an
     * account there is nothing to bind to (Instagram has no "any number"
     * fallback), so we skip and say why — the builder already warns about this.
     */
    public function syncInstagramTriggerAutomation(): void
    {
        try {
            \App\Models\InstagramAutomation::where('flow_id', $this->id)
                ->where('type', 'flow')
                ->delete();

            if ($this->trigger_kind !== 'keyword') return;

            $keywords = trim((string) ($this->trigger_keywords ?? ''));
            $acctId   = (int) ($this->trigger_device_id ?? 0);
            if ($keywords === '' || !$this->workspace_id || $acctId <= 0) {
                \Illuminate\Support\Facades\Log::info('[FLOW-IG-SYNC] skip — no rule created', [
                    'flow_id' => $this->id,
                    'reason'  => $keywords === '' ? 'no keywords'
                        : (!$this->workspace_id ? 'no workspace_id' : 'no Instagram account on the Trigger node'),
                ]);
                return;
            }

            // Only bind to an account this workspace actually owns — the id
            // comes from flow_data, so never trust it for cross-tenant reach.
            $acct = \App\Models\InstagramAccount::where('id', $acctId)
                ->where('workspace_id', $this->workspace_id)->first();
            if (!$acct) {
                \Illuminate\Support\Facades\Log::warning('[FLOW-IG-SYNC] skip — Instagram account not in this workspace', [
                    'flow_id' => $this->id, 'account_id' => $acctId, 'workspace_id' => $this->workspace_id,
                ]);
                return;
            }

            $isCatchAll = in_array($keywords, ['*', '.*', '.+'], true);

            $rule = \App\Models\InstagramAutomation::create([
                'workspace_id'         => $this->workspace_id,
                'instagram_account_id' => $acct->id,
                'type'                 => 'flow',
                'name'                 => $this->flow_name,
                'trigger_keyword'      => $isCatchAll ? '' : $keywords,
                'match_mode'           => $isCatchAll ? 'any' : 'contains',
                'flow_id'              => $this->id,
                'is_active'            => (bool) ($this->is_published && $this->is_active),
            ]);

            \Illuminate\Support\Facades\Log::info('[FLOW-IG-SYNC] automation synced', [
                'flow_id'    => $this->id,
                'rule_id'    => $rule->id,
                'account'    => $acct->id,
                'match_mode' => $rule->match_mode,
                'is_active'  => (bool) $rule->is_active,
                'note'       => $rule->is_active
                    ? 'LIVE — will fire on this Instagram account'
                    : 'INACTIVE — publish + activate the flow to make it fire',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[FLOW] Instagram trigger sync failed: ' . $e->getMessage());
        }
    }

    protected $fillable = [
        'user_id',
        'workspace_id',
        'provider',
        // 'chat' (default) or 'call' — call flows are AI-voice IVR walked by
        // node/services/callFlowRuntime.js on WABA Business Calling.
        'flow_type',
        // Set when this flow was imported from an Instaflow (Instagram) flow —
        // keeps the local copy linked so re-import reuses it and saves round-trip.
        'instaflow_flow_id',
        'flow_name',
        'flow_file_path',
        'flow_data',
        'category',
        'is_published',
        'is_active',
        'published_at',
        // Audience trigger — replaces the old drip_campaigns table. The
        // trigger node inside flow_data drives keyword matching at runtime;
        // these columns let Laravel query "which flows want this tag /
        // group" without decrypting flow_data.
        'trigger_kind',
        'trigger_value',
        'trigger_device_id',
        // Raw keyword string for trigger_kind='keyword' (mirrored from the
        // trigger node so the inbound matcher can fire the flow on a keyword).
        'trigger_keywords',
    ];

    protected $casts = [
        'flow_name'    => 'encrypted',
        'flow_data'    => 'encrypted',
        'is_published' => 'boolean',
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
    ];

    public const TRIGGER_KINDS = [
        'keyword', 'tag_added', 'group_join', 'manual_enroll',
        // Event triggers (hook existing observers/handlers)
        'contact_created', 'opt_in', 'order_placed', 'appointment_booked',
        // Sales Pipeline bridge — fire when a deal enters a stage (value = stage_id)
        'deal_stage_changed',
        // Inbound-condition triggers — launch on any inbound message while the
        // workspace is in Away mode (workspaces.inbox_away) or outside its
        // Business Hours. trigger_value unused (0); enrols each contact once.
        'away', 'out_of_hours',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribers()
    {
        return $this->hasMany(FlowSubscriber::class);
    }

    public function connectedDevices()
    {
        return $this->hasMany(FlowConnectedDevice::class);
    }

    public function activeDevices()
    {
        return $this->hasMany(FlowConnectedDevice::class)->where('status', 'active');
    }

    /**
     * Legacy per-user scope — kept for backward compatibility. Prefer
     * `scopeForCurrentWorkspace` for any new caller. NULL-owner rows
     * are NOT returned (no admin-seeded global flows exist), so a row
     * with NULL user_id can no longer leak to every user.
     */
    public function scopeForUser($q, ?int $userId)
    {
        return $q->where('user_id', $userId);
    }

    /**
     * Workspace-shared visibility — every member of the current
     * workspace sees every flow in it. Pre-migration rows fall back
     * to the original creator only.
     */
    public function scopeForCurrentWorkspace($q)
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
     * Return flow_data decoded to an array. Falls back to the file
     * mirror if the DB column is empty (matches the old behaviour).
     */
    public function getDecodedFlowDataAttribute(): array
    {
        $raw = $this->flow_data;
        if (is_array($raw))  return $raw;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        if ($this->flow_file_path) {
            $abs = $this->flowFileAbsolutePath();
            if ($abs && is_file($abs)) {
                $decoded = json_decode((string) file_get_contents($abs), true);
                if (is_array($decoded)) return $decoded;
            }
        }
        return ['flowNodes' => [], 'flowEdges' => []];
    }

    /**
     * Resolve flow_file_path to an absolute path. New rows live under the
     * NON-public storage/app/flows (so the graph is never web-served); legacy
     * rows carry an 'uploads/flows/...' path that resolved under public_path().
     */
    private function flowFileAbsolutePath(): ?string
    {
        $p = (string) $this->flow_file_path;
        if ($p === '') return null;
        if (str_starts_with($p, 'flows/')) {
            return storage_path('app/' . $p);
        }
        // Legacy public-web-root mirror.
        return public_path($p);
    }

    /**
     * Mirror the flow JSON to disk for the Node bridge.
     *
     * Written under storage/app/flows (NON-public) — the full node graph must
     * never be served as a static asset from the web root, where it was
     * enumerable cross-tenant via /uploads/flows/flow_{id}.json. Node reads the
     * flow over the X-Node-Token-gated /api/flows/:id bridge, not from this file,
     * so relocating it does not affect the runtime.
     */
    public function saveFlowFile($flowData): string
    {
        $directory = storage_path('app/flows');
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        $relativePath = 'flows/flow_' . $this->id . '.json';
        $absolute     = storage_path('app/' . $relativePath);
        @file_put_contents($absolute, json_encode($flowData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->forceFill(['flow_file_path' => $relativePath])->saveQuietly();

        // Best-effort: purge any legacy public-web-root copy so it stops being
        // served as a static asset the moment the flow is re-saved.
        $legacy = public_path('uploads/flows/flow_' . $this->id . '.json');
        if (is_file($legacy)) @unlink($legacy);

        return $relativePath;
    }

    public function deleteFlowFile(): void
    {
        if ($this->flow_file_path) {
            $abs = $this->flowFileAbsolutePath();
            if ($abs && is_file($abs)) @unlink($abs);
        }
        // Also clear any legacy public copy regardless of the stored path.
        $legacy = public_path('uploads/flows/flow_' . $this->id . '.json');
        if (is_file($legacy)) @unlink($legacy);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::deleting(function (Flow $flow) {
            $flow->deleteFlowFile();
            // Tear down the managed keyword-trigger auto-reply row.
            try {
                \App\Models\KeywordReply::withTrashed()
                    ->where('flow_id', $flow->id)
                    ->where('is_flow_trigger', true)
                    ->forceDelete();
            } catch (\Throwable $e) { /* best-effort */ }
        });
    }
}
