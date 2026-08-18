<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cross-source message aggregator for /message-history.
 *
 * WaDesk stores message records in 7 different tables, each owned by
 * a different feature (team inbox, legacy direct send, auto-replies,
 * campaigns, broadcasts, scheduled sends). The history page surfaces
 * EVERY one — so a user can answer "did anyone get a reply to this
 * customer's hi at 3pm" regardless of which subsystem produced it.
 *
 * Strategy: fetch the top-N most-recent from each source filtered to
 * the workspace, normalize into a common row shape, sort by `when`
 * desc in PHP, paginate. The per-source LIMIT keeps the total
 * hydrated set bounded even when one source is overwhelming.
 *
 * Output row shape (every source maps into this):
 *
 *   [
 *     'id'           => 'inbox-1234',     // source-prefixed unique
 *     'source'       => 'inbox',          // taxonomy key
 *     'source_label' => 'Team inbox',     // display label
 *     'direction'    => 'in' | 'out',
 *     'body'         => string,
 *     'contact_name' => ?string,
 *     'phone'        => ?string,
 *     'status'       => string,
 *     'when'         => Carbon,
 *     'meta'         => array,
 *   ]
 */
class UnifiedMessageStream
{
    /** Max rows to hydrate from EACH source per page render. */
    public const PER_SOURCE_LIMIT = 200;

    /** Taxonomy keys (UI filters use these). */
    public const SOURCES = [
        'inbox'      => 'Team inbox',
        'auto_reply' => 'Auto-reply',
        'campaign'   => 'Campaign',
        'broadcast'  => 'Broadcast',
        'scheduled'  => 'Scheduled',
        'legacy'     => 'Direct send',
    ];

    /**
     * Returns a LengthAwarePaginator of normalized rows. Filters:
     *
     *   workspace_id  required
     *   sources       array<string> — limit to these source keys
     *   direction     'all' | 'in' | 'out'
     *   from / to     Carbon
     *   q             free-text needle (matches body / phone / contact)
     *   page          1-indexed
     *   per_page      default 25
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $workspaceId = (int) ($filters['workspace_id'] ?? 0);
        $userIds = $this->workspaceUserIds($workspaceId);
        $sources = !empty($filters['sources']) ? array_intersect($filters['sources'], array_keys(self::SOURCES)) : array_keys(self::SOURCES);
        $direction = $filters['direction'] ?? 'all';
        $from = $filters['from'] ?? null;
        $to   = $filters['to']   ?? null;
        $q    = trim((string) ($filters['q'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 25)));

        $rows = collect();
        foreach ($sources as $source) {
            $method = 'pull' . ucfirst(str_replace('_', '', $source));
            if (method_exists($this, $method)) {
                $rows = $rows->merge($this->{$method}($workspaceId, $userIds, $from, $to));
            }
        }

        // Filter direction + free-text (decrypted strings — PHP only).
        if ($direction === 'in' || $direction === 'out') {
            $rows = $rows->where('direction', $direction);
        }
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function ($r) use ($needle) {
                $hay = mb_strtolower(($r['body'] ?? '') . ' ' . ($r['phone'] ?? '') . ' ' . ($r['contact_name'] ?? ''));
                return str_contains($hay, $needle);
            });
        }

        $rows = $rows->sortByDesc(fn ($r) => optional($r['when'])->getTimestamp())->values();

        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
            'query' => request()->query(),
        ]);
    }

    /**
     * Headline counts (NOT paginated) used for the KPI strip. Cheap
     * SQL COUNTs per source so this stays fast even with millions of
     * rows — pure rowcount, no hydration.
     */
    public function counts(int $workspaceId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $userIds = $this->workspaceUserIds($workspaceId);

        $cnt = [
            'inbox'      => $this->countInbox($workspaceId, $userIds, $from, $to),
            'auto_reply' => $this->countAutoReply($workspaceId, $userIds, $from, $to),
            'campaign'   => $this->countCampaign($workspaceId, $userIds, $from, $to),
            'broadcast'  => $this->countBroadcast($workspaceId, $userIds, $from, $to),
            'scheduled'  => $this->countScheduled($workspaceId, $userIds, $from, $to),
            'legacy'     => $this->countLegacy($workspaceId, $userIds, $from, $to),
        ];
        $cnt['total'] = array_sum($cnt);
        return $cnt;
    }

    /**
     * Device-scoped paginator — same row shape as paginate() but every
     * source is filtered to a single device. Used by /devices/{id} to
     * surface "every message that flowed through this number" without
     * the workspace-wide noise. Broadcasts are intentionally excluded
     * because the broadcasts table has no device_id — a broadcast
     * fans out across every connected number, so attributing it to a
     * single device would be a lie.
     */
    public function paginateForDevice(int $deviceId, array $filters): LengthAwarePaginator
    {
        $sources   = !empty($filters['sources'])
            ? array_intersect($filters['sources'], ['inbox','auto_reply','campaign','scheduled','legacy'])
            : ['inbox','auto_reply','campaign','scheduled','legacy'];
        $direction = $filters['direction'] ?? 'all';
        $from      = $filters['from'] ?? null;
        $to        = $filters['to']   ?? null;
        $q         = trim((string) ($filters['q'] ?? ''));
        $page      = max(1, (int) ($filters['page'] ?? 1));
        $perPage   = max(5, min(100, (int) ($filters['per_page'] ?? 25)));

        $rows = collect();
        $methodMap = [
            'inbox'      => 'pullInboxForDevice',
            'auto_reply' => 'pullAutoreplyForDevice',
            'campaign'   => 'pullCampaignForDevice',
            'scheduled'  => 'pullScheduledForDevice',
            'legacy'     => 'pullLegacyForDevice',
        ];
        foreach ($sources as $src) {
            if (isset($methodMap[$src]) && method_exists($this, $methodMap[$src])) {
                $rows = $rows->merge($this->{$methodMap[$src]}($deviceId, $from, $to));
            }
        }

        if ($direction === 'in' || $direction === 'out') {
            $rows = $rows->where('direction', $direction);
        } elseif ($direction === 'fail') {
            $rows = $rows->where('direction', 'out')
                ->filter(fn ($r) => in_array($r['status'] ?? '', ['failed','error'], true));
        }
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function ($r) use ($needle) {
                $hay = mb_strtolower(($r['body'] ?? '') . ' ' . ($r['phone'] ?? '') . ' ' . ($r['contact_name'] ?? ''));
                return str_contains($hay, $needle);
            });
        }

        $rows = $rows->sortByDesc(fn ($r) => optional($r['when'])->getTimestamp())->values();

        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path'     => request()->url(),
            'pageName' => 'page',
            'query'    => request()->query(),
        ]);
    }

    /**
     * Total messages per source for a single device. Used by the
     * device-detail KPI tiles + source-filter pills so they can show a
     * count next to each label.
     */
    public function countsForDevice(int $deviceId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $convoIds = DB::table('conversations')->where('device_id', $deviceId)->pluck('id');

        $cnt = [
            'inbox'      => (int) (clone $this->dateScoped(DB::table('inbox_messages')->whereIn('conversation_id', $convoIds), 'created_at', $from, $to))->count(),
            'auto_reply' => (int) $this->dateScoped(
                DB::table('keyword_reply_logs as l')->join('keyword_replies as r', 'r.id', '=', 'l.keyword_reply_id')
                    ->where('r.device_id', $deviceId),
                'l.fired_at', $from, $to
            )->count(),
            'campaign'   => (int) $this->dateScoped(
                DB::table('wp_campaign_contacts as wc')->join('wpcampaigns as cmp', 'cmp.id', '=', 'wc.campaign_id')
                    ->where('cmp.device_id', $deviceId),
                'wc.created_at', $from, $to
            )->count(),
            'scheduled'  => (int) $this->dateScoped(
                DB::table('scheduled_messages')->where('device_id', $deviceId)->whereNull('deleted_at'),
                'created_at', $from, $to
            )->count(),
            'legacy'     => (int) $this->dateScoped(
                DB::table('messages')->whereIn('conversation_id', $convoIds),
                'created_at', $from, $to
            )->count(),
        ];
        $cnt['total'] = array_sum($cnt);
        return $cnt;
    }

    /* ────────────── device-scoped source pullers ────────────── */

    private function pullInboxForDevice(int $deviceId, ?Carbon $from, ?Carbon $to): Collection
    {
        // Device-scoped views (e.g. /devices/{id} message log) keep the
        // engine filter too — the conversation provider must match the
        // workspace's active engine so a Baileys workspace doesn't
        // surface WABA legacy history under "this Baileys phone".
        $device = \App\Models\Device::find($deviceId);
        $engine = $device ? \App\Services\WorkspaceEngine::for((int) $device->workspace_id) : null;
        $convoQ = DB::table('conversations')->where('device_id', $deviceId);
        if ($engine) $convoQ->where('provider', $engine);
        $convoIds = $convoQ->pluck('id');
        if ($convoIds->isEmpty()) return collect();
        $q = \App\Models\InboxMessage::query()->whereIn('conversation_id', $convoIds);
        if ($engine) $q->where('provider', $engine);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return $q->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($m) => [
                'id'           => 'inbox-' . $m->id,
                'source'       => 'inbox',
                'source_label' => 'Team inbox',
                'direction'    => $m->direction ?? 'in',
                'body'         => $this->dec($m->body),
                'contact_name' => optional($m->contact ?? null)->name,
                'phone'        => $m->direction === 'out' ? $this->dec($m->to_number) : $this->dec($m->from_number),
                'status'       => (string) $m->status,
                'when'         => $m->created_at,
                'meta'         => ['conversation_id' => $m->conversation_id, 'media_type' => $m->media_type],
            ]);
    }

    private function pullAutoreplyForDevice(int $deviceId, ?Carbon $from, ?Carbon $to): Collection
    {
        $device = \App\Models\Device::find($deviceId);
        $engine = $device ? \App\Services\WorkspaceEngine::for((int) $device->workspace_id) : null;
        $kqQ = DB::table('keyword_replies')->where('device_id', $deviceId);
        if ($engine) $kqQ->where('provider', $engine);
        $keywordIds = $kqQ->pluck('id');
        if ($keywordIds->isEmpty()) return collect();
        $q = \App\Models\KeywordReplyLog::query()
            ->with(['keywordReply', 'content'])
            ->whereIn('keyword_reply_id', $keywordIds);
        $this->applyDateRange($q, 'fired_at', $from, $to);
        return $q->orderByDesc('fired_at')->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(function ($log) {
                $variant = $log->content;
                $body    = $variant ? (string) $variant->content : '';
                return [
                    'id'           => 'auto-' . $log->id,
                    'source'       => 'auto_reply',
                    'source_label' => 'Auto-reply',
                    'direction'    => 'out',
                    'body'         => $body !== '' ? $body : ('Triggered by "' . $log->matched_text . '"'),
                    'contact_name' => null,
                    'phone'        => $log->contact_phone,
                    'status'       => 'fired',
                    'when'         => $log->fired_at,
                    'meta'         => [
                        'keyword'    => optional($log->keywordReply)->keyword,
                        'matched'    => $log->matched_text,
                        'language'   => $log->detected_language,
                        'reply_type' => optional($log->keywordReply)->reply_type,
                    ],
                ];
            });
    }

    private function pullCampaignForDevice(int $deviceId, ?Carbon $from, ?Carbon $to): Collection
    {
        $device = \App\Models\Device::find($deviceId);
        $engine = $device ? \App\Services\WorkspaceEngine::for((int) $device->workspace_id) : null;
        $cQ = DB::table('wpcampaigns')->where('device_id', $deviceId);
        if ($engine) $cQ->where('provider', $engine);
        $campaignIds = $cQ->pluck('id');
        if ($campaignIds->isEmpty()) return collect();
        $q = DB::table('wp_campaign_contacts as wc')
            ->leftJoin('wpcampaigns as cmp', 'cmp.id', '=', 'wc.campaign_id')
            ->whereIn('wc.campaign_id', $campaignIds)
            ->select('wc.id', 'wc.phone_number', 'wc.recipient_name', 'wc.status',
                     'wc.sent_at', 'wc.delivered_at', 'wc.read_at', 'wc.response',
                     'wc.error_message', 'wc.created_at',
                     'cmp.campaign_name');
        $this->applyDateRange($q, 'wc.created_at', $from, $to);
        return $q->orderByDesc('wc.sent_at')->orderByDesc('wc.created_at')
            ->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($r) => [
                'id'           => 'camp-' . $r->id,
                'source'       => 'campaign',
                'source_label' => 'Campaign',
                'direction'    => 'out',
                'body'         => trim('Campaign send · ' . ($r->campaign_name ?? '')),
                'contact_name' => $r->recipient_name,
                'phone'        => $r->phone_number,
                'status'       => (string) $r->status,
                'when'         => $r->sent_at ? Carbon::parse($r->sent_at) : ($r->created_at ? Carbon::parse($r->created_at) : null),
                'meta'         => [
                    'campaign_name' => $r->campaign_name,
                    'error'         => $r->error_message,
                    'response'      => $r->response,
                ],
            ]);
    }

    private function pullScheduledForDevice(int $deviceId, ?Carbon $from, ?Carbon $to): Collection
    {
        $device = \App\Models\Device::find($deviceId);
        $engine = $device ? \App\Services\WorkspaceEngine::for((int) $device->workspace_id) : null;
        $q = DB::table('scheduled_messages')
            ->where('device_id', $deviceId)
            ->when($engine, fn ($qq) => $qq->where('provider', $engine))
            ->whereNull('deleted_at')
            ->select('id', 'schedule_name', 'message_content', 'status', 'send_date',
                     'send_time', 'next_run_at', 'last_run_at', 'completed_at',
                     'failed_at', 'failure_reason', 'total_sent', 'total_delivered',
                     'total_failed', 'from_number', 'created_at');
        $this->applyDateRange($q, 'created_at', $from, $to);
        return $q->orderByDesc('last_run_at')->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($r) => [
                'id'           => 'sch-' . $r->id,
                'source'       => 'scheduled',
                'source_label' => 'Scheduled',
                'direction'    => 'out',
                'body'         => trim('Scheduled · ' . $this->dec($r->schedule_name)) . ($this->dec($r->message_content) !== '' ? "\n" . $this->dec($r->message_content) : ''),
                'contact_name' => null,
                'phone'        => $r->from_number,
                'status'       => (string) $r->status,
                'when'         => $r->last_run_at ? Carbon::parse($r->last_run_at) : ($r->next_run_at ? Carbon::parse($r->next_run_at) : Carbon::parse($r->created_at)),
                'meta'         => [
                    'sent' => (int) $r->total_sent, 'delivered' => (int) $r->total_delivered,
                    'failed' => (int) $r->total_failed,
                    'next_run_at' => $r->next_run_at, 'last_run_at' => $r->last_run_at,
                    'error' => $r->failure_reason,
                ],
            ]);
    }

    /**
     * Tolerantly decrypt a raw column value. This service reads via DB::table
     * for speed, which BYPASSES the Eloquent `encrypted` casts — so fields like
     * messages.body / to_number / scheduled_messages.message_content come back
     * as Laravel ciphertext (base64 "eyJpdiI6..."). Decrypt it here; anything
     * that is not our ciphertext (plain text, or a wrong-key blob) is returned
     * unchanged so the row never breaks.
     */
    private function dec($value): string
    {
        $s = (string) ($value ?? '');
        if ($s === '' || !str_starts_with($s, 'eyJ')) return $s;
        try {
            return (string) \Illuminate\Support\Facades\Crypt::decryptString($s);
        } catch (\Throwable $e) {
            return $s;
        }
    }

    private function pullLegacyForDevice(int $deviceId, ?Carbon $from, ?Carbon $to): Collection
    {
        $device = \App\Models\Device::find($deviceId);
        $engine = $device ? \App\Services\WorkspaceEngine::for((int) $device->workspace_id) : null;
        $convoQ = DB::table('conversations')->where('device_id', $deviceId);
        if ($engine) $convoQ->where('provider', $engine);
        $convoIds = $convoQ->pluck('id');
        if ($convoIds->isEmpty()) return collect();
        $q = \App\Models\Message::query()->whereIn('conversation_id', $convoIds);
        if ($engine) $q->where('provider', $engine);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return $q->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($m) => [
                'id'           => 'leg-' . $m->id,
                'source'       => 'legacy',
                'source_label' => 'Direct send',
                'direction'    => $m->direction ?? 'out',
                'body'         => $this->dec($m->body),
                'contact_name' => optional($m->contact)->name,
                'phone'        => $m->direction === 'out' ? $this->dec($m->to_number) : $this->dec($m->from_number),
                'status'       => (string) $m->status,
                'when'         => $m->created_at,
                'meta'         => ['media_type' => $m->media_type, 'template_id' => $m->template_id],
            ]);
    }

    private function dateScoped($q, string $col, ?Carbon $from, ?Carbon $to)
    {
        if ($from) $q->where($col, '>=', $from);
        if ($to)   $q->where($col, '<=', $to);
        return $q;
    }

    /* ────────────── source pullers ────────────── */

    private function pullInbox(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): Collection
    {
        // Filter conversations + messages by the workspace's ENABLED
        // engine SET so a multi-engine workspace (e.g. Baileys + WABA +
        // Twilio at once) surfaces history from every enabled engine,
        // not just the default. Single-engine workspaces resolve to
        // [default] so whereIn([default]) == the old where(default).
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $convoIds = DB::table('conversations')->where('workspace_id', $wsId)
            ->whereIn('provider', $engines)->pluck('id');
        $q = \App\Models\InboxMessage::query()
            ->whereIn('conversation_id', $convoIds)
            ->whereIn('provider', $engines);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return $q->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($m) => [
                'id'           => 'inbox-' . $m->id,
                'source'       => 'inbox',
                'source_label' => 'Team inbox',
                'direction'    => $m->direction ?? 'in',
                'body'         => $this->dec($m->body),
                'contact_name' => optional($m->contact ?? null)->name,
                'phone'        => $m->direction === 'out' ? $this->dec($m->to_number) : $this->dec($m->from_number),
                'status'       => (string) $m->status,
                'when'         => $m->created_at,
                'meta'         => ['conversation_id' => $m->conversation_id, 'media_type' => $m->media_type],
            ]);
    }

    private function pullAutoreply(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): Collection
    {
        // keyword_reply_logs has encrypted `contact_phone` and
        // `matched_text` columns — we MUST go through the Eloquent
        // model so the casts decrypt them. Raw DB::table returns the
        // ciphertext blob and that lands ugly in the UI.
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $keywordIds = DB::table('keyword_replies')
            ->where('workspace_id', $wsId)
            ->whereIn('provider', $engines)
            ->pluck('id');
        if ($keywordIds->isEmpty()) return collect();

        $q = \App\Models\KeywordReplyLog::query()
            ->with(['keywordReply', 'content'])
            ->whereIn('keyword_reply_id', $keywordIds);
        $this->applyDateRange($q, 'fired_at', $from, $to);

        return $q->orderByDesc('fired_at')->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(function ($log) {
                $variant = $log->content;
                $body    = $variant ? (string) $variant->content : '';
                return [
                    'id'           => 'auto-' . $log->id,
                    'source'       => 'auto_reply',
                    'source_label' => 'Auto-reply',
                    'direction'    => 'out',
                    'body'         => $body !== '' ? $body : ('Triggered by "' . $log->matched_text . '"'),
                    'contact_name' => null,
                    'phone'        => $log->contact_phone,
                    'status'       => 'fired',
                    'when'         => $log->fired_at,
                    'meta'         => [
                        'keyword'    => optional($log->keywordReply)->keyword,
                        'matched'    => $log->matched_text,
                        'language'   => $log->detected_language,
                        'reply_type' => optional($log->keywordReply)->reply_type,
                    ],
                ];
            });
    }

    private function pullCampaign(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): Collection
    {
        // `wpcampaigns.campaign_name` is encrypted, so we pre-decrypt
        // by loading through the Eloquent model into an id→name map.
        // Per-recipient rows come from the wp_campaign_contacts pivot
        // (non-encrypted) and the map gives us the human-readable name.
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $campaigns = \App\Models\WpCampaign::query()
            ->join('devices as d', 'd.id', '=', 'wpcampaigns.device_id')
            ->whereIn('d.user_id', $userIds)
            ->whereIn('wpcampaigns.provider', $engines)
            ->get(['wpcampaigns.id', 'wpcampaigns.campaign_name']);
        if ($campaigns->isEmpty()) return collect();
        $nameById = $campaigns->pluck('campaign_name', 'id');

        $q = DB::table('wp_campaign_contacts as wc')
            ->whereIn('wc.campaign_id', $nameById->keys())
            ->select('wc.id', 'wc.campaign_id', 'wc.phone_number', 'wc.recipient_name', 'wc.status',
                     'wc.sent_at', 'wc.delivered_at', 'wc.read_at', 'wc.response',
                     'wc.error_message', 'wc.created_at');
        $this->applyDateRange($q, 'wc.created_at', $from, $to);
        return $q->orderByDesc('wc.sent_at')->orderByDesc('wc.created_at')
            ->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($r) => [
                'id'           => 'camp-' . $r->id,
                'source'       => 'campaign',
                'source_label' => 'Campaign',
                'direction'    => 'out',
                'body'         => trim('Campaign send · ' . ($nameById[$r->campaign_id] ?? '')),
                'contact_name' => $r->recipient_name,
                'phone'        => $r->phone_number,
                'status'       => (string) $r->status,
                'when'         => $r->sent_at ? Carbon::parse($r->sent_at) : ($r->created_at ? Carbon::parse($r->created_at) : null),
                'meta'         => [
                    'campaign_name' => $nameById[$r->campaign_id] ?? null,
                    'campaign_id'   => $r->campaign_id,
                    'error'         => $r->error_message,
                    'response'      => $r->response,
                ],
            ]);
    }

    private function pullBroadcast(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): Collection
    {
        // `broadcasts.name` is encrypted, so we pre-decrypt by loading
        // through the Eloquent model into a phpid→name map, then JOIN
        // raw contact rows for the per-recipient breakdown. Two queries
        // total, neither returns ciphertext to the UI.
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $broadcasts = \App\Models\Broadcast::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('provider', $engines)
            ->get(['id', 'name']);
        if ($broadcasts->isEmpty()) return collect();
        $nameById = $broadcasts->pluck('name', 'id');

        $q = DB::table('broadcast_contacts as bc')
            ->whereIn('bc.broadcast_id', $nameById->keys())
            ->select('bc.id', 'bc.broadcast_id', 'bc.status', 'bc.error_message', 'bc.sent_at',
                     'bc.delivered_at', 'bc.read_at', 'bc.created_at');
        $this->applyDateRange($q, 'bc.created_at', $from, $to);
        return $q->orderByDesc('bc.sent_at')->orderByDesc('bc.created_at')
            ->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($r) => [
                'id'           => 'brd-' . $r->id,
                'source'       => 'broadcast',
                'source_label' => 'Broadcast',
                'direction'    => 'out',
                'body'         => trim('Broadcast · ' . ($nameById[$r->broadcast_id] ?? '')),
                'contact_name' => null,
                'phone'        => null,
                'status'       => (string) $r->status,
                'when'         => $r->sent_at ? Carbon::parse($r->sent_at) : ($r->created_at ? Carbon::parse($r->created_at) : null),
                'meta'         => ['broadcast_name' => $nameById[$r->broadcast_id] ?? null, 'error' => $r->error_message],
            ]);
    }

    private function pullScheduled(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): Collection
    {
        // Must go through the Eloquent model — `schedule_name`,
        // `message_content`, and `target_numbers` are all encrypted at
        // rest. Raw DB::table returns the ciphertext blob, which lands
        // in the UI as `eyJpdiI6...` base64 noise. The model's $casts
        // decrypt automatically.
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $q = \App\Models\ScheduledMessage::query()
            ->where('workspace_id', $wsId)
            ->whereIn('provider', $engines);
        // Date range matches the displayed `when` column: prefer the
        // actual fire timestamp (last_run_at) for completed rows, fall
        // back to created_at for never-fired rows. We OR them in a
        // grouped where so a row qualifies if EITHER column falls in
        // the window.
        if ($from || $to) {
            $q->where(function ($qq) use ($from, $to) {
                $qq->where(function ($a) use ($from, $to) {
                    if ($from) $a->where('last_run_at', '>=', $from);
                    if ($to)   $a->where('last_run_at', '<=', $to);
                })->orWhere(function ($b) use ($from, $to) {
                    $b->whereNull('last_run_at');
                    if ($from) $b->where('created_at', '>=', $from);
                    if ($to)   $b->where('created_at', '<=', $to);
                });
            });
        }
        return $q->orderByDesc('last_run_at')->orderByDesc('created_at')
            ->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($r) => [
                'id'           => 'sch-' . $r->id,
                'source'       => 'scheduled',
                'source_label' => 'Scheduled',
                'direction'    => 'out',
                'body'         => trim('Scheduled · ' . $this->dec($r->schedule_name)) . ($this->dec($r->message_content) !== '' ? "\n" . $this->dec($r->message_content) : ''),
                'contact_name' => null,
                'phone'        => $r->from_number,
                'status'       => (string) $r->status,
                'when'         => $r->last_run_at ?: ($r->next_run_at ?: $r->created_at),
                'meta'         => [
                    'sent' => (int) $r->total_sent, 'delivered' => (int) $r->total_delivered,
                    'failed' => (int) $r->total_failed,
                    'next_run_at' => optional($r->next_run_at)->toIso8601String(),
                    'last_run_at' => optional($r->last_run_at)->toIso8601String(),
                    'error' => $r->failure_reason,
                ],
            ]);
    }

    private function pullLegacy(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): Collection
    {
        // Legacy `messages` table — keep visible for installs that
        // still use it. Scoped by user_id (workspace member) AND
        // engine (provider column added in migration 2026_05_24_120000)
        // so a Baileys workspace doesn't see WABA history in the
        // unified stream after engine switch.
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $q = \App\Models\Message::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('provider', $engines);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return $q->orderByDesc('created_at')->limit(self::PER_SOURCE_LIMIT)->get()
            ->map(fn ($m) => [
                'id'           => 'leg-' . $m->id,
                'source'       => 'legacy',
                'source_label' => 'Direct send',
                'direction'    => $m->direction ?? 'out',
                'body'         => $this->dec($m->body),
                'contact_name' => optional($m->contact)->name,
                'phone'        => $m->direction === 'out' ? $this->dec($m->to_number) : $this->dec($m->from_number),
                'status'       => (string) $m->status,
                'when'         => $m->created_at,
                'meta'         => ['media_type' => $m->media_type, 'template_id' => $m->template_id],
            ]);
    }

    /* ────────────── counts (cheap, COUNT only) ────────────── */
    //
    // Every count* method below filters on the workspace's ENABLED
    // engine SET (WorkspaceEngine::enginesFor) so the KPI strip totals
    // match the engine-scoped paginated table exactly — a multi-engine
    // workspace (Baileys + WABA + Twilio at once) sums every enabled
    // engine, not just the default. Single-engine workspaces resolve to
    // [default] so whereIn([default]) == the old where(default), leaving
    // their counts byte-identical. Same provider-routing principle we
    // enforce on every other feature (templates, broadcasts, scheduled,
    // auto-reply).

    private function countInbox(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): int
    {
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $convoIds = DB::table('conversations')->where('workspace_id', $wsId)
            ->whereIn('provider', $engines)->pluck('id');
        if ($convoIds->isEmpty()) return 0;
        $q = DB::table('inbox_messages')
            ->whereIn('conversation_id', $convoIds)
            ->whereIn('provider', $engines);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return (int) $q->count();
    }

    private function countAutoReply(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): int
    {
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $q = DB::table('keyword_reply_logs as l')
            ->join('keyword_replies as r', 'r.id', '=', 'l.keyword_reply_id')
            ->where('r.workspace_id', $wsId)
            ->whereIn('r.provider', $engines);
        $this->applyDateRange($q, 'l.fired_at', $from, $to);
        return (int) $q->count();
    }

    private function countCampaign(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): int
    {
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $campaignIds = DB::table('wpcampaigns as cmp')
            ->join('devices as d', 'd.id', '=', 'cmp.device_id')
            ->whereIn('d.user_id', $userIds)
            ->whereIn('cmp.provider', $engines)
            ->pluck('cmp.id');
        if ($campaignIds->isEmpty()) return 0;
        $q = DB::table('wp_campaign_contacts')->whereIn('campaign_id', $campaignIds);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return (int) $q->count();
    }

    private function countBroadcast(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): int
    {
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $broadcastIds = DB::table('broadcasts')
            ->whereIn('user_id', $userIds)
            ->whereIn('provider', $engines)
            ->pluck('id');
        if ($broadcastIds->isEmpty()) return 0;
        $q = DB::table('broadcast_contacts')->whereIn('broadcast_id', $broadcastIds);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return (int) $q->count();
    }

    private function countScheduled(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): int
    {
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $q = DB::table('scheduled_messages')
            ->where('workspace_id', $wsId)
            ->whereIn('provider', $engines)
            ->whereNull('deleted_at');
        $this->applyDateRange($q, 'created_at', $from, $to);
        return (int) $q->count();
    }

    private function countLegacy(int $wsId, $userIds, ?Carbon $from, ?Carbon $to): int
    {
        $engines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $q = DB::table('messages')
            ->whereIn('user_id', $userIds)
            ->whereIn('provider', $engines);
        $this->applyDateRange($q, 'created_at', $from, $to);
        return (int) $q->count();
    }

    /* ────────────── helpers ────────────── */

    private function workspaceUserIds(int $wsId)
    {
        return DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id');
    }

    private function applyDateRange($q, string $col, ?Carbon $from, ?Carbon $to): void
    {
        if ($from) $q->where($col, '>=', $from);
        if ($to)   $q->where($col, '<=', $to);
    }
}
