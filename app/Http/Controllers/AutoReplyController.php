<?php

namespace App\Http\Controllers;

use App\Models\AutoReplyLookup;
use App\Models\Device;
use App\Models\Flow;
use App\Models\KeywordReply;
use App\Models\KeywordReplyContent;
use App\Models\KeywordReplyLog;
use App\Models\WaTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * /auto-reply — clean rewrite of D:\wadesk_2806\New folder's
 * KeywordReplyController (813 lines, three migrations of column churn).
 *
 * Pages:
 *   GET  /auto-reply          — list page with stats
 *   GET  /auto-reply/create   — composer form
 *   GET  /auto-reply/keyword  — legacy keyword preview page (kept)
 *
 * JSON API (workspace-scoped):
 *   POST   /auto-reply             — create
 *   PATCH  /auto-reply/{id}        — update
 *   POST   /auto-reply/{id}/toggle — flip status
 *   DELETE /auto-reply/{id}        — soft-delete
 *   POST   /auto-reply/bulk        — bulk delete
 *
 * Public bot lookup (no auth, the bot calls it on every inbound):
 *   GET /api/keyword-replies?keyword=…&phone=…&mobile=…
 *     → returns the first matching row in the legacy shape the bot already
 *       expects: [{ response, reply, reply_type, flow_id, cooldown, timeout }]
 */
class AutoReplyController extends Controller
{
    /* ============================== Pages ============================== */

    public function index(Request $request): View|JsonResponse
    {
        $wsId      = (int) $request->user()->current_workspace_id;
        $memberIds = DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id');

        $search      = trim((string) $request->query('q', ''));
        $device      = trim((string) $request->query('device', 'all')) ?: 'all';
        $status      = trim((string) $request->query('status', 'all')) ?: 'all';
        $type        = trim((string) $request->query('type', 'all')) ?: 'all';
        $currentView = trim((string) $request->query('view', 'list')) ?: 'list';

        // Engine-aware listing, PLUS Instagram. forCurrentEngine() scopes to the
        // workspace's enabled WhatsApp engines (enginesFor: baileys/waba/twilio) —
        // but Instagram is engine-agnostic and NEVER in that set, so a first-class
        // Instagram auto-reply (provider='instagram', dispatched via Instaflow)
        // would be hidden from its own manager. Include it explicitly.
        $listEngines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        // …but only while an Instagram account is actually connected. Disconnect
        // the last one and Instagram auto-replies stop listing (and stop being an
        // option), reappearing when an account reconnects.
        if (\App\Models\WorkspaceIgAccount::hasConnected($wsId)) {
            $listEngines[] = 'instagram';
        }
        $allRows = KeywordReply::query()
            ->forWorkspace($wsId)
            ->whereIn('provider', array_values(array_unique($listEngines)))
            ->orderByDesc('created_at')
            ->with(['device:id,phone_number,device_name', 'selectedContents', 'flow:id,flow_name'])
            ->limit(500)
            ->get();

        // DIAGNOSTIC — is the page fetching data or not? Compares rows AFTER the
        // engine filter (what the UI shows) vs ALL rows in the workspace, so an
        // empty list caused by an engine mismatch (rule.provider ≠ current
        // engine) or a flow-trigger row is obvious in laravel.log.
        try {
            \Log::info('[AUTOREPLY-INDEX] fetch', [
                'workspace_id'   => $wsId,
                'current_engine' => \App\Services\WorkspaceEngine::for($wsId),
                'shown'          => $allRows->count(),                                  // after forCurrentEngine()
                'all_in_ws'      => (int) KeywordReply::query()->forWorkspace($wsId)->count(), // ignores engine filter
                'flow_trigger'   => $allRows->where('is_flow_trigger', true)->count(),
                'active'         => $allRows->where('status', true)->count(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[AUTOREPLY-INDEX] log failed: ' . $e->getMessage());
        }

        // All-real stats. The "today/24h" stats use last_triggered_at so we
        // don't need a separate trigger log table — every fire updates that
        // column via the increment() in lookup().
        $now24h     = now()->subHours(24);
        $totalTrig  = (int) $allRows->sum('trigger_count');
        $rules24h   = $allRows->where('last_triggered_at', '>=', $now24h);
        $totals = [
            'total'           => $allRows->count(),
            'active'          => $allRows->where('status', true)->count(),
            'inactive'        => $allRows->where('status', false)->count(),
            'total_triggers'  => $totalTrig,
            'rules_fired_24h' => $rules24h->count(),
            // Top 4 active rules by trigger_count → real "top performers".
            'top_performers'  => $allRows->where('status', true)
                                      ->sortByDesc('trigger_count')
                                      ->take(4)
                                      ->values(),
        ];

        $devices = Device::forCurrentWorkspace()
            ->where('active', 1)
            ->get(['id', 'phone_number', 'device_name']);

        $rows = $allRows;
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (KeywordReply $row) use ($needle) {
                $deviceText = trim((string) optional($row->device)->phone_number . ' ' . (string) optional($row->device)->device_name);
                $contentText = $row->selectedContents
                    ->map(fn ($content) => (string) ($content->content ?? ''))
                    ->implode(' ');

                return str_contains(mb_strtolower((string) $row->keyword), $needle)
                    || str_contains(mb_strtolower($deviceText), $needle)
                    || str_contains(mb_strtolower($contentText), $needle);
            });
        }
        if ($device !== 'all') {
            $rows = $rows->where('device_id', (int) $device);
        }
        if ($status === 'active') {
            $rows = $rows->where('status', true);
        } elseif ($status === 'paused') {
            $rows = $rows->where('status', false);
        }
        if ($type !== 'all') {
            $rows = match ($type) {
                'custom' => $rows->where('reply_type', 'custom'),
                'flow'   => $rows->where('reply_type', 'flow'),
                default  => in_array($type, KeywordReply::MESSAGE_TYPES, true)
                    ? $rows->where('message_type', $type)
                    : $rows,
            };
        }

        $rows = $this->paginateCollection($rows->values(), $request, 12);

        if ($request->wantsJson() || $request->boolean('partial')) {
            return response()->json([
                'ok'         => true,
                'rows'       => view('user.auto-reply._rows', ['rows' => $rows])->render(),
                'grid'       => view('user.auto-reply._grid', ['rows' => $rows])->render(),
                'pagination' => view('user.partials.pagination', [
                    'paginator' => $rows,
                    'dataAttr'  => 'data-ar-page',
                    'label'     => 'auto replies',
                ])->render(),
                'shown'      => $rows->count(),
                'total'      => $rows->total(),
                'page'       => $rows->currentPage(),
                'totals'     => [
                    'total'           => $totals['total'],
                    'active'          => $totals['active'],
                    'inactive'        => $totals['inactive'],
                    'total_triggers'  => $totals['total_triggers'],
                    'rules_fired_24h' => $totals['rules_fired_24h'],
                ],
            ]);
        }

        return view('user.auto-reply.index', [
            'rows'          => $rows,
            'totals'        => $totals,
            'devices'       => $devices,
            'currentSearch' => $search,
            'currentDevice' => $device,
            'currentStatus' => $status,
            'currentType'   => $type,
            'currentView'   => in_array($currentView, ['list', 'grid'], true) ? $currentView : 'list',
            // Manual "Away mode" switch state, for the header toggle.
            'awayMode'      => (bool) optional($request->user()->currentWorkspace)->inbox_away,
        ]);
    }

    public function demoCsv(): Response
    {
        $csv = implode("\n", [
            'keyword,matching_method,fuzzy_similarity,device_id,reply_type,message_type,reply_text,status,cooldown,timeout',
            'pricing,contains,80,,custom,text,"Here are our plans. Reply with your preferred package and we will help.",active,60,300',
            'support,exact,80,,custom,text,"Thanks for reaching out. Our support team will reply shortly.",active,60,300',
        ]) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="auto-reply-demo.csv"',
        ]);
    }

    public function import(Request $request): JsonResponse|RedirectResponse
    {
        // MIME + extension allowlist — without `mimes:csv,txt` the only
        // gate was `file|max:5120`, which let `.exe`/`.php` uploads
        // through. The downstream parser does string ops over the bytes,
        // so a non-CSV would never persist as a rule but the upload
        // surface is still public-facing and shouldn't accept arbitrary
        // binaries.
        $request->validate([
            'file' => 'required|file|max:5120|mimes:csv,txt|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/octet-stream',
        ]);

        $user = $request->user();
        // Same plan feature gate as store() — admin can disable
        // `access_keyword_replies` mid-plan and the import path should
        // refuse like the other mutators.
        \App\Services\PlanLimitGuard::feature($user->currentWorkspace, 'access_keyword_replies');
        $wsId = (int) $user->current_workspace_id;
        $memberIds = DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id');

        $devices = Device::forCurrentWorkspace()
            ->where('active', 1)
            ->get(['id', 'phone_number', 'device_name']);

        if ($devices->isEmpty()) {
            return $this->importCsvResponse($request, 'Add an active device before importing auto replies.', 0, 0, 422);
        }

        $validDeviceIds = $devices->pluck('id')->map(fn ($id) => (int) $id)->all();
        $defaultDevice = $devices->first();
        $deviceByPhone = $devices
            ->filter(fn ($device) => (string) $device->phone_number !== '')
            ->keyBy(fn ($device) => preg_replace('/\D+/', '', (string) $device->phone_number));
        $validFlowIds = Flow::whereIn('user_id', $memberIds)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) {
            return $this->importCsvResponse($request, 'Could not read uploaded CSV file.', 0, 0, 422);
        }

        $headers = null;
        $imported = 0;
        $skipped = 0;

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $this->normaliseCsvHeaders($line);
                continue;
            }
            if (count(array_filter($line, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $data = array_combine(
                array_pad($headers, count($line), ''),
                array_pad($line, count($headers), '')
            ) ?: [];

            $keyword = trim($this->csvValue($data, ['keyword', 'keywords', 'trigger', 'trigger_keyword']));
            if ($keyword === '') {
                $skipped++;
                continue;
            }

            $matchingMethod = mb_strtolower(trim($this->csvValue($data, ['matching_method', 'match', 'match_type']))) ?: 'exact';
            if (!in_array($matchingMethod, KeywordReply::MATCHING_METHODS, true)) {
                $matchingMethod = 'exact';
            }

            $similarity = $this->csvNullableInt($this->csvValue($data, ['fuzzy_similarity', 'similarity']));
            $similarity = $similarity === null ? 80 : min(100, max(0, $similarity));

            $deviceId = $this->csvNullableInt($this->csvValue($data, ['device_id', 'sender_id']));
            $device = $deviceId && in_array($deviceId, $validDeviceIds, true)
                ? $devices->firstWhere('id', $deviceId)
                : null;
            if (!$device) {
                $phoneKey = preg_replace('/\D+/', '', $this->csvValue($data, ['device_phone', 'phone_number', 'sender_phone']));
                $device = $phoneKey !== '' ? $deviceByPhone->get($phoneKey) : null;
            }
            $device = $device ?: $defaultDevice;

            $replyType = mb_strtolower(trim($this->csvValue($data, ['reply_type', 'type']))) ?: 'custom';
            if (!in_array($replyType, KeywordReply::REPLY_TYPES, true)) {
                $replyType = 'custom';
            }

            $flowId = null;
            if ($replyType === 'flow') {
                $flowId = $this->csvNullableInt($this->csvValue($data, ['flow_id']));
                if (!$flowId || !in_array($flowId, $validFlowIds, true)) {
                    $skipped++;
                    continue;
                }
            }

            $messageType = mb_strtolower(trim($this->csvValue($data, ['message_type', 'content_type']))) ?: 'text';
            if (!in_array($messageType, KeywordReply::MESSAGE_TYPES, true)) {
                $messageType = 'text';
            }

            $content = trim($this->csvValue($data, ['reply_text', 'message', 'content', 'body']));
            $templateId = $this->csvNullableInt($this->csvValue($data, ['template_id']));
            if ($replyType === 'custom' && $content === '' && $messageType !== 'template') {
                $skipped++;
                continue;
            }

            $cooldown = $this->csvNullableInt($this->csvValue($data, ['cooldown']));
            $timeout = $this->csvNullableInt($this->csvValue($data, ['timeout']));
            $isActive = $this->csvBool($this->csvValue($data, ['status', 'active', 'enabled']), true);

            DB::transaction(function () use (
                $user,
                $wsId,
                $keyword,
                $matchingMethod,
                $similarity,
                $device,
                $replyType,
                $flowId,
                $messageType,
                $cooldown,
                $timeout,
                $isActive,
                $content,
                $templateId
            ) {
                $row = KeywordReply::updateOrCreate(
                    [
                        'workspace_id' => $wsId,
                        'device_id'    => $device->id,
                        'keyword'      => $keyword,
                    ],
                    [
                        'user_id'          => $user->id,
                        'matching_method'  => $matchingMethod,
                        'fuzzy_similarity' => $similarity,
                        'reply_type'       => $replyType,
                        'flow_id'          => $replyType === 'flow' ? $flowId : null,
                        'cooldown'         => $cooldown,
                        'timeout'          => $timeout,
                        'message_type'     => $messageType,
                        'status'           => $isActive,
                    ]
                );

                if ($replyType === 'custom') {
                    $row->loadMissing('contents');
                    $this->deleteContentFiles($row);
                    $row->contents()->delete();
                    KeywordReplyContent::create([
                        'keyword_reply_id' => $row->id,
                        'content_type'     => $messageType,
                        'content'          => $content,
                        'template_id'      => $messageType === 'template' ? $templateId : null,
                        'is_selected'      => true,
                        'sort_order'       => 0,
                    ]);
                } else {
                    $row->loadMissing('contents');
                    $this->deleteContentFiles($row);
                    $row->contents()->delete();
                }
            });

            $imported++;
        }

        fclose($handle);

        return $this->importCsvResponse($request, "Imported {$imported} auto replies ({$skipped} skipped).", $imported, $skipped);
    }

    public function create(Request $request): View
    {
        $wsId      = (int) $request->user()->current_workspace_id;
        $memberIds = DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id');

        // Edit mode: same view, pre-loaded row. The blade reads $row when
        // present and the JS pre-fills every field. Submit handler then
        // POSTs to PATCH /auto-reply/{id} when $row is set. Load first so
        // the device picker can keep an already-bound (now-disconnected)
        // device visible — see the picker query below.
        $row = null;
        if ($id = $request->query('id')) {
            $row = KeywordReply::forWorkspace($wsId)->with('contents')->find((int) $id);
        }

        // Sender picker — only CONNECTED phones, since an auto-reply
        // can't fire from a disconnected device. Exception: on an edit
        // where the rule is already bound to a now-disconnected device,
        // still surface that one so the selection isn't silently dropped.
        $selectedDeviceId = $row?->device_id;
        $devices   = Device::forCurrentWorkspace()
            ->where('active', 1)
            ->where(function ($q) use ($selectedDeviceId) {
                $q->where('status', 'connected');
                if ($selectedDeviceId) {
                    $q->orWhere('id', (int) $selectedDeviceId);
                }
            })
            ->get(['id', 'phone_number', 'device_name']);
        // Workspace-scoped (NOT just the current user's own rows) so EVERY
        // template the workspace owns — plus admin-seeded globals — shows in
        // the picker, matching Broadcasts / Scheduled / Chat. Previously this
        // used whereIn('user_id', $memberIds), so templates created by a
        // non-member user_id (or the admin globals) never appeared.
        $templates = WaTemplate::query()->forCurrentWorkspace()->approved()
            ->with('provider')->orderByDesc('id')->get();
        $flows     = Flow::whereIn('user_id', $memberIds)->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get(['id', 'flow_name', 'is_published', 'updated_at']);

        // Multi-engine: every connected sender across ALL enabled engines
        // (Unofficial API + WABA + Twilio), surfaced through the unified
        // <x-sender-picker> as composite `engine:id` keys. On a single-engine
        // workspace this is exactly the workspace's own devices, so the picker
        // renders identically to the legacy device list. $devices is kept for
        // the empty-state copy + the legacy fallback path.
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        // Instagram is a first-class auto-reply channel — append any IG accounts
        // linked from Instaflow. senders() omits Instagram from the default set
        // (to keep it out of WhatsApp-only pickers elsewhere), so request it
        // explicitly and merge. Empty collection when the workspace has no IG
        // account linked, so WhatsApp-only workspaces see no change.
        $igSenders = \App\Services\WorkspaceEngine::senders($wsId, [\App\Services\WorkspaceEngine::ENGINE_INSTAGRAM]);
        if ($igSenders->isNotEmpty()) {
            $senders = $senders->concat($igSenders)->values();
        }

        // Pre-tick EVERY sender this keyword already fires on (not just the one
        // row being edited), so an edit shows all its numbers ticked and saving
        // keeps them. Keyed engine:device to match the unified sender picker.
        $selectedSenderKeys = [];
        if ($row && $row->keyword) {
            $selectedSenderKeys = KeywordReply::where('workspace_id', $wsId)
                ->where('keyword', $row->keyword)
                ->get(['device_id', 'provider'])
                ->map(fn ($r) => ($r->provider ?: \App\Services\WorkspaceEngine::for($wsId)) . ':' . $r->device_id)
                ->unique()->values()->all();
        }

        return view('user.auto-reply.create', compact('devices', 'senders', 'templates', 'flows', 'row', 'selectedSenderKeys'));
    }

    public function keyword(Request $request): View
    {
        $wsId = (int) $request->user()->current_workspace_id;

        // Resolve which rule the operator is viewing — preferred via ?id=,
        // fallback to ?k= (keyword string) for the legacy URL pattern, and
        // last-resort fall back to the most-triggered rule in the workspace
        // so the page renders something useful instead of a blank shell.
        $row = null;
        if ($id = $request->query('id')) {
            $row = KeywordReply::forWorkspace($wsId)->with('contents', 'device:id,phone_number', 'flow:id,flow_name')->find((int) $id);
        }
        if (!$row && ($k = $request->query('k'))) {
            $row = KeywordReply::forWorkspace($wsId)
                ->whereRaw('LOWER(keyword) LIKE ?', ['%' . mb_strtolower($k) . '%'])
                ->with('contents', 'device:id,phone_number', 'flow:id,flow_name')
                ->first();
        }
        if (!$row) {
            $row = KeywordReply::forWorkspace($wsId)
                ->orderByDesc('trigger_count')
                ->with('contents', 'device:id,phone_number', 'flow:id,flow_name')
                ->first();
        }

        // ── Analytics aggregates (all from keyword_reply_logs) ────────────
        $analytics = [
            'recent'        => collect(),
            'topUsers'      => collect(),
            'variantStats'  => collect(),
            'hourBuckets'   => array_fill(0, 24, 0),
            'dayBuckets'    => collect(),   // last 30 days
            'fired7d'       => 0,
            'fired30d'      => 0,
            'uniqueUsers'   => 0,
        ];

        if ($row) {
            $logs = KeywordReplyLog::forKeywordReply($row->id)
                ->where('fired_at', '>=', now()->subDays(30))
                ->orderByDesc('fired_at')
                ->limit(2000)
                ->get();

            // Recent feed — last 8 fires.
            $analytics['recent'] = $logs->take(8);

            // Top users — group by encrypted phone (decrypted on cast,
            // so we group in memory).
            $analytics['topUsers'] = $logs->groupBy('contact_phone')
                ->map(fn ($g) => [
                    'phone' => $g->first()->contact_phone,
                    'count' => $g->count(),
                ])->sortByDesc('count')->take(5)->values();

            // Variant breakdown — which keyword token matched.
            $analytics['variantStats'] = $logs->whereNotNull('matched_variant')
                ->groupBy('matched_variant')
                ->map(fn ($g) => $g->count())
                ->sortDesc()
                ->take(5);

            // Hour-of-day heatmap (24 buckets).
            foreach ($logs as $l) {
                $h = (int) optional($l->fired_at)->format('G');
                if ($h >= 0 && $h < 24) $analytics['hourBuckets'][$h]++;
            }

            // Daily series for last 30 days. Fill zero days so the chart
            // shows continuity.
            $byDay = $logs->groupBy(fn ($l) => optional($l->fired_at)->toDateString());
            $days  = collect();
            for ($i = 29; $i >= 0; $i--) {
                $key = now()->subDays($i)->toDateString();
                $days->push(['date' => $key, 'count' => $byDay->get($key, collect())->count()]);
            }
            $analytics['dayBuckets'] = $days;

            $analytics['fired7d']     = $logs->where('fired_at', '>=', now()->subDays(7))->count();
            $analytics['fired30d']    = $logs->count();
            $analytics['uniqueUsers'] = $logs->pluck('contact_phone')->unique()->count();

            // Conversion funnel — from auto_reply_lookups (every inbound
            // attempt, matched or not) on this rule's device, last 30 days.
            // We can compute incoming → matched-any → matched-this-rule
            // exactly. "Delivered" / "Read" stages still need bot-side
            // delivery callbacks; for now we fall back to "matched count"
            // for both so the bars stay honest (= the reply did go out).
            $deviceLookups = $row->device_id
                ? AutoReplyLookup::forDevice($row->device_id)->recent(30)->get(['matched_keyword_reply_id','latency_ms'])
                : collect();

            $incoming     = $deviceLookups->count();
            $matchedAny   = $deviceLookups->whereNotNull('matched_keyword_reply_id')->count();
            $matchedThis  = $deviceLookups->where('matched_keyword_reply_id', $row->id)->count();
            $analytics['funnel'] = [
                ['label' => 'Incoming messages',  'count' => $incoming,    'pct' => 100.0],
                ['label' => 'Matched any rule',   'count' => $matchedAny,  'pct' => $incoming    ? round($matchedAny  / $incoming * 100, 1) : 0],
                ['label' => 'Matched this rule',  'count' => $matchedThis, 'pct' => $incoming    ? round($matchedThis / $incoming * 100, 1) : 0],
                ['label' => 'Reply sent',         'count' => $matchedThis, 'pct' => $incoming    ? round($matchedThis / $incoming * 100, 1) : 0],
            ];

            // Latency averaged over the matches we made. The bot+Baileys
            // round-trip isn't included here — this is server-side lookup
            // time only, but it's a real ceiling.
            $latencies = $deviceLookups->where('matched_keyword_reply_id', $row->id)
                ->pluck('latency_ms')->filter()->values();
            $analytics['latencyAvgMs'] = $latencies->isNotEmpty() ? (int) round($latencies->avg()) : null;
            $analytics['latencyP95Ms'] = $latencies->isNotEmpty() ? (int) $latencies->sort()->values()->get((int) floor($latencies->count() * 0.95)) : null;
        }

        return view('user.auto-reply.keyword', compact('row', 'analytics'));
    }

    /* ============================ JSON API ============================ */

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;
        $memberIds = DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id');

        // Plan: feature flag + numeric cap.
        // Count by workspace_id (not user_id) so a user belonging to two
        // workspaces doesn't get their second workspace's cap blocked
        // by the first workspace's existing rules. The original
        // `whereIn('user_id', $memberIds)` aggregated across every
        // workspace the user touched.
        \App\Services\PlanLimitGuard::feature($user->currentWorkspace, 'access_keyword_replies');
        \App\Services\PlanLimitGuard::check(
            $user->currentWorkspace,
            'autoreply_limit',
            KeywordReply::where('workspace_id', $user->currentWorkspace->id)->count(),
        );

        $data = $request->validate([
            // A DEFAULT-ROUTE rule ("Any message") carries no keyword by
            // definition — the is_catch_all flag IS the condition. Everything
            // else still requires one, so a blank keyword on a normal rule is
            // rejected instead of silently creating a rule that matches nothing.
            'keyword'          => 'required_unless:is_catch_all,1,true|nullable|string|max:255',
            'is_catch_all'     => 'nullable|boolean',
            'matching_method'  => ['required', Rule::in(KeywordReply::MATCHING_METHODS)],
            'fuzzy_similarity' => 'nullable|integer|min:0|max:100',
            // Multi-engine: the unified <x-sender-picker> posts composite
            // `engine:id` keys via `sender[]`. When present these win and
            // each chosen sender stamps the rule's provider = its OWN engine
            // (so a workspace can run Unofficial-API rules on one number and
            // WABA rules on another). When absent we fall back to the legacy
            // single `device_id` / `device_ids[]` path so single-engine forms
            // + any un-migrated caller stay byte-identical.
            'sender'           => 'sometimes|array|min:1',
            'sender.*'         => 'string|max:64',
            // Multi-device: accept either the legacy single `device_id`
            // OR the new `device_ids[]` array. When the array is sent
            // with 2+ values, the controller fans out into one
            // KeywordReply row per device (since each row's lookup
            // query keys on `device_id` — fanning out is simpler than
            // refactoring lookup to handle a JSON column).
            'device_id'        => 'required_without_all:device_ids,sender|integer',
            'device_ids'       => 'required_without_all:device_id,sender|array|min:1',
            'device_ids.*'     => 'integer',

            'reply_type'       => ['required', Rule::in(KeywordReply::REPLY_TYPES)],
            'flow_id'          => 'required_if:reply_type,flow|nullable|integer',
            'target_contact_id'=> 'required_if:reply_type,share_contact|nullable|integer',
            'target_catalog_id'=> 'required_if:reply_type,send_catalog|nullable|integer',

            'cooldown'         => 'nullable|integer|min:0|max:86400',
            'timeout'          => 'nullable|integer|min:0|max:86400',

            'status'           => 'nullable|boolean',

            // ── Auto Responder / Welcome Message (2026-07-31) — all OPTIONAL, so
            //    a classic keyword rule that posts none of these is unchanged. ──
            // 'welcome' fires on the first message of a new conversation (no
            // keyword); it reuses `keyword` as its display name.
            'trigger_type'         => ['nullable', Rule::in(KeywordReply::TRIGGER_TYPES)],
            // Instagram-native trigger — only present when the chosen sender is an
            // IG account. WhatsApp rows never post it.
            'ig_trigger'           => ['nullable', Rule::in(KeywordReply::IG_TRIGGERS)],
            // Priority — lower number wins when several auto-responder rules could
            // fire on the same inbound.
            'priority'             => 'nullable|integer|min:0|max:9999',
            // Step 2 — Resend (value + Minutes/Hours/Days) → stored as seconds in cooldown.
            'resend_value'         => 'nullable|integer|min:0|max:100000',
            'resend_unit'          => ['nullable', Rule::in(['minutes', 'hours', 'days'])],
            // Step 2 — Agent override + Resume after.
            'stop_on_agent_reply'  => 'nullable|boolean',
            'resume_value'         => 'nullable|integer|min:0|max:100000',
            'resume_unit'          => ['nullable', Rule::in(['minutes', 'hours', 'days'])],
            // Step 3 — Working hours + outside-hours behaviour.
            'wh_enabled'           => 'nullable|boolean',
            'wh_days'              => 'nullable|array',
            'wh_days.*'            => ['nullable', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'wh_from'              => 'nullable|string|max:5',
            'wh_to'                => 'nullable|string|max:5',
            'wh_timezone'          => 'nullable|string|max:64',
            'wh_outside_action'    => ['nullable', Rule::in(['send', 'none'])],
            // Step 1 — Excluded recipients (numbers textarea + contact groups).
            'excluded_numbers'     => 'nullable',
            'excluded_group_ids'   => 'nullable|array',
            'excluded_group_ids.*' => 'integer',
            // Step 3 — outside-hours message composer (same shape as contents).
            'outside_contents'                => 'nullable|array',
            'outside_contents.*.content_type' => ['nullable', Rule::in(KeywordReply::MESSAGE_TYPES)],
            'outside_contents.*.content'      => 'nullable|string|max:4096',
            'outside_contents.*.template_id'  => 'nullable|integer',

            // contents — at least one item when reply_type=custom
            'contents'                  => 'array|nullable',
            'contents.*.content_type'   => ['nullable', Rule::in(KeywordReply::MESSAGE_TYPES)],
            'contents.*.content'        => 'nullable|string|max:4096',
            'contents.*.template_id'    => 'nullable|integer',
            'contents.*.is_selected'    => 'nullable|boolean',
            'contents.*.sort_order'     => 'nullable|integer',
            // Files come up as `contents[<i>][media]` from a multipart form.
            // We leave them out of the array validator since they're File
            // objects — handled below.
        ]);

        // Regex rules must compile, or they'd silently never fire — reject
        // a broken pattern at save time with a clear message.
        if (($data['matching_method'] ?? '') === 'regex' && !KeywordReply::isValidRegex($data['keyword'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'keyword' => 'That regular expression is not valid. Check your pattern (no delimiters needed) and try again.',
            ]);
        }

        // Resolve which senders this rule should fire on, as a list of
        // ['id' => int, 'engine' => string] pairs. The fan-out loop below
        // creates one KeywordReply per pair, stamping provider = engine.
        //
        // PREFERRED: the unified picker's `sender[]` composite `engine:id`
        // keys. Each is validated against WorkspaceEngine::senders() so a
        // forged/stale key for a sender this workspace can't use is dropped
        // (never trusted blindly). The CHOSEN engine — not the workspace
        // default — is what gets stamped.
        $targets = collect();
        if (!empty($data['sender'])) {
            foreach ($data['sender'] as $key) {
                $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, (string) $key);
                if ($picked) {
                    $targets->push(['id' => (int) $picked['id'], 'engine' => (string) $picked['engine']]);
                }
            }
            $targets = $targets->unique(fn ($t) => $t['engine'] . ':' . $t['id'])->values();
            if ($targets->isEmpty()) {
                return response()->json(['ok' => false, 'errors' => ['sender' => ['No valid senders in this workspace.']]], 422);
            }
        } else {
            // LEGACY fallback: single `device_id` OR `device_ids[]` — bare
            // Device ids that resolve to the workspace's current engine. The
            // single id collapses to a 1-element list so the downstream code
            // path is identical for single + multi.
            $requestedIds = !empty($data['device_ids'])
                ? array_values(array_unique(array_map('intval', $data['device_ids'])))
                : [(int) $data['device_id']];

            // Verify EVERY device belongs to a workspace member. Forged
            // ids get dropped silently — we don't 422 the whole submit
            // because the operator's expected behavior is "create rules
            // for the devices I do own", not "fail loudly on partial".
            $devices = Device::forCurrentWorkspace()
                ->whereIn('id', $requestedIds)
                ->get();
            if ($devices->isEmpty()) {
                return response()->json(['ok' => false, 'errors' => ['device_id' => ['No valid devices in this workspace.']]], 422);
            }
            // Preserve picker order so the resulting rows feel deterministic.
            // A legacy bare Device id is, by definition, a Baileys sender —
            // carry its per-row provider when set, else fall back below.
            $devicesOrdered = collect($requestedIds)
                ->map(fn ($id) => $devices->firstWhere('id', $id))
                ->filter()
                ->values();
            $targets = $devicesOrdered->map(fn ($d) => [
                'id'     => (int) $d->id,
                'engine' => (string) ($d->provider ?? '') !== '' ? strtolower((string) $d->provider) : null,
            ])->values();
        }

        if ($data['reply_type'] === 'flow' && !empty($data['flow_id'])) {
            $flowOk = Flow::whereIn('user_id', $memberIds)->where('id', $data['flow_id'])->exists();
            if (!$flowOk) {
                return response()->json(['ok' => false, 'errors' => ['flow_id' => ['Flow not found in this workspace.']]], 422);
            }
        }

        // #19 — share_contact target must be a Contact owned by the workspace.
        if ($data['reply_type'] === 'share_contact' && !empty($data['target_contact_id'])) {
            $ok = \App\Models\Contact::whereIn('user_id', $memberIds)->where('id', $data['target_contact_id'])->exists();
            if (!$ok) {
                return response()->json(['ok' => false, 'errors' => ['target_contact_id' => ['Contact not found in this workspace.']]], 422);
            }
        }
        // #20 — send_catalog target must be a WaCatalog row owned by the workspace.
        if ($data['reply_type'] === 'send_catalog' && !empty($data['target_catalog_id'])) {
            $ok = \App\Models\WaCatalog::where('workspace_id', $wsId)->where('id', $data['target_catalog_id'])->exists();
            if (!$ok) {
                return response()->json(['ok' => false, 'errors' => ['target_catalog_id' => ['Catalog not found in this workspace.']]], 422);
            }
        }

        return DB::transaction(function () use ($request, $user, $wsId, $data, $targets) {
            // Fan out to one row per picked sender. With a single
            // sender this loops exactly once → identical behaviour to
            // the old single-device codepath. Multi-sender picks
            // produce N independent rows that the lookup query keys
            // on naturally (no schema change required).
            //
            // Stamp the rule with the engine it should fire on. Prefer
            // the CHOSEN engine from the sender picker (so a multi-engine
            // workspace can have Unofficial-API rules on one number and
            // WABA rules on another); on the legacy bare-id path fall back
            // to the device's own provider, then the workspace default,
            // when the picker engine isn't known.
            $workspaceEngine = \App\Services\WorkspaceEngine::for($wsId);

            $createdRows = [];
            foreach ($targets as $t) {
                $rowProvider = (string) ($t['engine'] ?? '') !== ''
                    ? strtolower((string) $t['engine'])
                    : $workspaceEngine;
                $row = KeywordReply::create([
                    'user_id'          => $user->id,
                    'workspace_id'     => $wsId,
                    'device_id'        => (int) $t['id'],
                    'provider'         => $rowProvider,
                    'keyword'          => $data['keyword'] ?? '',
                    'matching_method'  => $data['matching_method'],
                    'is_catch_all'     => (bool) ($data['is_catch_all'] ?? false),
                    'fuzzy_similarity' => $data['fuzzy_similarity'] ?? 80,
                    'reply_type'       => $data['reply_type'],
                    'flow_id'          => $data['reply_type'] === 'flow' ? ($data['flow_id'] ?? null) : null,
                    'target_contact_id'=> $data['reply_type'] === 'share_contact' ? ($data['target_contact_id'] ?? null) : null,
                    'target_catalog_id'=> $data['reply_type'] === 'send_catalog' ? ($data['target_catalog_id'] ?? null) : null,
                    'timeout'          => $data['timeout']  ?? null,
                    'status'           => $data['status']   ?? true,
                    'message_type'     => $this->guessPrimaryMessageType($request),
                    // cooldown is provided by responderColumns() — a welcome rule's
                    // "Resend after" maps to it; a keyword rule keeps its own value.
                ] + $this->responderColumns($data));

                if ($data['reply_type'] === 'custom') {
                    $this->syncContents($request, $row);
                    // Outside-hours composer (Step 3) — same media pipeline,
                    // tagged variant_role='outside_hours' + is_selected=false so
                    // it never leaks into the normal reply.
                    $this->syncOutsideContents($request, $row);
                }
                $createdRows[] = $row;
            }

            // Sprint 7 — multilingual: SAVE IS INSTANT. We do NOT
            // pre-translate at save time; instead the runtime lazy
            // path in lookup() detects the customer's language and
            // translates only the phrases that actually get hit, with
            // a 24h cache. First customer in a new language pays
            // ~500ms once; every subsequent customer in that language
            // pays zero. The `keyword_translations` column stays null
            // for new rows and is populated lazily only when needed.

            $firstRow = $createdRows[0] ?? null;
            return response()->json([
                'ok' => true,
                'message' => count($createdRows) > 1
                    ? 'Auto reply created on ' . count($createdRows) . ' senders.'
                    : 'Auto reply saved.',
                'data' => [
                    'id'           => $firstRow?->id,
                    'created_ids'  => array_map(fn ($r) => $r->id, $createdRows),
                    'devices_count'=> count($createdRows),
                    'redirect_url' => route('user.auto-reply.index'),
                ],
            ], 201);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        $row  = KeywordReply::forWorkspace($wsId)->findOrFail($id);

        // Plan feature gate — admin can disable `access_keyword_replies`
        // mid-plan, in which case the operator should not be able to keep
        // editing rules. The store() path already gates; mirror here.
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'access_keyword_replies');

        $data = $request->validate([
            'keyword'          => 'sometimes|string|max:255',
            'matching_method'  => ['sometimes', Rule::in(KeywordReply::MATCHING_METHODS)],
            // Without this the "Any message" flag could never be toggled on edit —
            // $row->update($data) simply never saw it, so a rule created as a
            // keyword rule could never become catch-all (and vice-versa) and the
            // segment silently reverted on every save.
            'is_catch_all'     => 'sometimes|boolean',
            'fuzzy_similarity' => 'sometimes|nullable|integer|min:0|max:100',
            // Update is a single-row mutation, so we accept either
            // `device_id` (single) or `device_ids[]` (first wins). The
            // JS edit form may send the checkbox array if the workspace
            // has multi-device — collapse it here.
            'device_id'        => 'sometimes|integer',
            'device_ids'       => 'sometimes|array',
            'device_ids.*'     => 'integer',
            // Multi-engine: the unified picker always posts composite engine:id
            // keys via sender[]. Accept + resolve them below.
            'sender'           => 'sometimes|array',
            'sender.*'         => 'string|max:64',
            'reply_type'       => ['sometimes', Rule::in(KeywordReply::REPLY_TYPES)],
            'flow_id'          => 'sometimes|nullable|integer',
            'target_contact_id'=> 'sometimes|nullable|integer',
            'target_catalog_id'=> 'sometimes|nullable|integer',
            'cooldown'         => 'sometimes|nullable|integer|min:0|max:86400',
            'timeout'          => 'sometimes|nullable|integer|min:0|max:86400',
            'status'           => 'sometimes|boolean',

            // Auto Responder / Welcome Message — same optional fields as store().
            'trigger_type'         => ['sometimes', Rule::in(KeywordReply::TRIGGER_TYPES)],
            'ig_trigger'           => ['sometimes', 'nullable', Rule::in(KeywordReply::IG_TRIGGERS)],
            'priority'             => 'sometimes|nullable|integer|min:0|max:9999',
            'resend_value'         => 'sometimes|nullable|integer|min:0|max:100000',
            'resend_unit'          => ['sometimes', 'nullable', Rule::in(['minutes', 'hours', 'days'])],
            'stop_on_agent_reply'  => 'sometimes|boolean',
            'resume_value'         => 'sometimes|nullable|integer|min:0|max:100000',
            'resume_unit'          => ['sometimes', 'nullable', Rule::in(['minutes', 'hours', 'days'])],
            'wh_enabled'           => 'sometimes|boolean',
            'wh_days'              => 'sometimes|nullable|array',
            'wh_days.*'            => ['nullable', Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])],
            'wh_from'              => 'sometimes|nullable|string|max:5',
            'wh_to'                => 'sometimes|nullable|string|max:5',
            'wh_timezone'          => 'sometimes|nullable|string|max:64',
            'wh_outside_action'    => ['sometimes', 'nullable', Rule::in(['send', 'none'])],
            'excluded_numbers'     => 'sometimes|nullable',
            'excluded_group_ids'   => 'sometimes|nullable|array',
            'excluded_group_ids.*' => 'integer',
            'outside_contents'                => 'sometimes|nullable|array',
            'outside_contents.*.content_type' => ['nullable', Rule::in(KeywordReply::MESSAGE_TYPES)],
            'outside_contents.*.content'      => 'nullable|string|max:4096',
            'outside_contents.*.template_id'  => 'nullable|integer',
        ]);

        // A full wizard save carries trigger_type — recompute the responder
        // columns from it. A partial mutation (e.g. a status toggle) omits it,
        // so we DON'T touch those columns and never wipe an existing welcome
        // rule's schedule/resend/exclusions.
        if ($request->has('trigger_type')) {
            foreach ([
                'trigger_type', 'ig_trigger', 'priority', 'resend_value', 'resend_unit', 'stop_on_agent_reply',
                'resume_value', 'resume_unit', 'wh_enabled', 'wh_days', 'wh_from', 'wh_to',
                'wh_timezone', 'wh_outside_action', 'excluded_numbers', 'excluded_group_ids',
                'outside_contents',
            ] as $k) {
                unset($data[$k]);
            }
            $data = array_merge($data, $this->responderColumns($request->all()));
        }

        // Multi-engine: the picker always posts a composite engine:id key via
        // sender[]. Resolve the first to device_id + provider so an edit that
        // switches engine/sender actually persists (update() previously only
        // read device_id/device_ids[] and silently dropped the change).
        // senderForKey validates the key against senders($wsId) — workspace-
        // scoped + connected — so it doubles as the ownership check, letting us
        // skip the Device-table guard below (which only knows Baileys devices).
        // Multi-engine: resolve ALL ticked senders. THIS row becomes the first;
        // any extras fan out into sibling rows after the update so one edit can
        // put the rule on several numbers (matches create() + the "tick all that
        // apply" UI). Additive — deselecting a sender does NOT delete its row
        // (delete it from the list instead), so an edit never silently drops a
        // number's rule.
        $senderOwnershipVerified = false;
        $extraTargets = [];
        if (!empty($data['sender'])) {
            $resolved = [];
            foreach ((array) $data['sender'] as $key) {
                $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, (string) $key);
                if ($picked) $resolved[] = ['id' => (int) $picked['id'], 'engine' => (string) $picked['engine']];
            }
            $resolved = collect($resolved)->unique(fn ($t) => $t['engine'] . ':' . $t['id'])->values()->all();
            if (!empty($resolved)) {
                $data['device_id'] = $resolved[0]['id'];
                $data['provider']  = $resolved[0]['engine'];
                $senderOwnershipVerified = true;
                $extraTargets = array_slice($resolved, 1);
            }
            unset($data['sender']);
        }

        // Reject an invalid regex on edit too (same guard as store()).
        if (($data['matching_method'] ?? '') === 'regex'
            && array_key_exists('keyword', $data)
            && !KeywordReply::isValidRegex((string) $data['keyword'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'keyword' => 'That regular expression is not valid. Check your pattern (no delimiters needed) and try again.',
            ]);
        }

        // Legacy bare device_ids[] (only when the sender[] picker wasn't used):
        // first id → this row, the rest → sibling rows (bare ids are Baileys).
        if (!$senderOwnershipVerified && !empty($data['device_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $data['device_ids'])));
            $data['device_id'] = $ids[0];
            foreach (array_slice($ids, 1) as $eid) $extraTargets[] = ['id' => $eid, 'engine' => null];
        }
        unset($data['device_ids']);

        // Verify the new device_id belongs to a member of THIS
        // workspace. Without this guard a workspace admin could
        // PATCH /auto-reply/{id} {"device_id": <foreign>} and bind
        // their rule to a device owned by another tenant.
        if (isset($data['device_id']) && !$senderOwnershipVerified) {
            $newDeviceId = (int) $data['device_id'];
            $memberIds   = DB::table('workspace_user')->where('workspace_id', $wsId)->pluck('user_id');
            $owned = Device::query()
                ->where('id', $newDeviceId)
                ->where(function ($q) use ($wsId, $memberIds) {
                    $q->where('workspace_id', $wsId)
                      ->orWhere(function ($qq) use ($memberIds) {
                          $qq->whereNull('workspace_id')->whereIn('user_id', $memberIds);
                      });
                })
                ->exists();
            if (!$owned) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Selected device is not in this workspace.',
                ], 403);
            }
        }

        $row->update($data);

        // Replace contents if the form sent them. Otherwise leave existing rows.
        if ($request->has('contents')) {
            $row->contents()->delete();
            $this->syncContents($request, $row);
        }
        // Replace the outside-hours composer variant(s) when the wizard sent them.
        if ($request->has('outside_contents')) {
            $row->outsideHoursContents()->delete();
            $this->syncOutsideContents($request, $row);
        }

        // Fan out to any ADDITIONAL ticked senders as sibling rows so one edit
        // can put the rule on several numbers. find-or-create by (workspace,
        // device, keyword) so re-saving the same edit never duplicates a row.
        $fannedOut = 0;
        foreach ($extraTargets as $t) {
            $sib = KeywordReply::firstOrNew([
                'workspace_id' => $wsId,
                'device_id'    => (int) $t['id'],
                'keyword'      => $row->keyword,
            ]);
            $sib->fill([
                'user_id'           => $row->user_id,
                'provider'          => !empty($t['engine']) ? strtolower((string) $t['engine']) : ($sib->provider ?: $row->provider),
                'matching_method'   => $row->matching_method,
                'fuzzy_similarity'  => $row->fuzzy_similarity,
                'reply_type'        => $row->reply_type,
                'flow_id'           => $row->flow_id,
                'target_contact_id' => $row->target_contact_id,
                'target_catalog_id' => $row->target_catalog_id,
                'cooldown'          => $row->cooldown,
                'timeout'           => $row->timeout,
                'status'            => $row->status,
                'message_type'      => $row->message_type,
            ]);
            $sib->save();
            if ($row->reply_type === 'custom') {
                $sib->contents()->delete();
                foreach ($row->contents as $c) {
                    $cc = $c->replicate(['id', 'keyword_reply_id']);
                    $cc->keyword_reply_id = $sib->id;
                    $cc->save();
                }
            }
            $fannedOut++;
        }

        return response()->json([
            'ok'      => true,
            'message' => $fannedOut > 0 ? ('Auto reply updated — now on ' . ($fannedOut + 1) . ' senders.') : 'Auto reply updated.',
            'data'    => [
                'id'           => $row->id,
                'redirect_url' => route('user.auto-reply.index'),
                'row'          => $row->fresh()->load('contents'),
            ],
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'access_keyword_replies');
        $row  = KeywordReply::forWorkspace($wsId)->findOrFail($id);
        $row->forceFill(['status' => !$row->status])->save();
        return response()->json(['ok' => true, 'status' => $row->status]);
    }

    /**
     * Flip the workspace's manual "Away mode" switch (workspaces.inbox_away).
     * When ON, `away` trigger-type auto-replies fire on every inbound until it is
     * switched off — the WhatsApp-Business-style away message. Accepts an explicit
     * `away` boolean (the header switch) or toggles the current value when omitted.
     */
    public function toggleAway(Request $request): JsonResponse|RedirectResponse
    {
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'access_keyword_replies');
        $ws = $request->user()->currentWorkspace;
        abort_unless($ws, 404);

        $away = $request->has('away')
            ? $request->boolean('away')
            : ! (bool) $ws->inbox_away;
        $ws->forceFill(['inbox_away' => $away])->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'away' => $away]);
        }
        return back()->with('status', $away
            ? __('Away mode is ON — away auto-replies will fire until you switch it off.')
            : __('Away mode is OFF.'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'access_keyword_replies');
        $row  = KeywordReply::forWorkspace($wsId)->findOrFail($id);
        $this->deleteContentFiles($row);
        $row->contents()->delete();
        $row->delete();
        return response()->json(['ok' => true]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'action' => 'required|in:delete,activate,deactivate',
        ]);
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'access_keyword_replies');
        $wsId = (int) $request->user()->current_workspace_id;
        $rows = KeywordReply::forWorkspace($wsId)->whereIn('id', $data['ids'])->get();

        foreach ($rows as $row) {
            if ($data['action'] === 'delete') {
                $this->deleteContentFiles($row);
                $row->contents()->delete();
                $row->delete();
            } else {
                $row->forceFill(['status' => $data['action'] === 'activate'])->save();
            }
        }
        return response()->json(['ok' => true, 'touched' => $rows->count()]);
    }

    /* ============================ Public bot endpoint ============================ */

    /**
     * Public lookup the bot calls on every inbound message.
     *   GET /api/keyword-replies?keyword=hi&phone=+91…&mobile=…
     *
     * No auth — the bot has no session. The endpoint is read-only and
     * scoped to "the device's workspace" so it can't leak across tenants.
     *
     * Response shape mirrors the legacy controller exactly so the existing
     * BaileysClientManager.checkKeywordReply doesn't need a code change:
     *   [{ response: '200', reply: <text|json>, reply_type, flow_id,
     *      cooldown, timeout }]
     */
    /**
     * Resolve the WELCOME auto-responder reply for an Unofficial/Baileys inbound.
     * Returns the KeywordReply to render (reused by lookup()'s response builder +
     * fire-logging), or null when no welcome rule should fire. When the rule's
     * Step-3 working hours say "outside → send", the outside-hours content
     * variant is swapped onto selectedContents so the SAME builder renders it.
     * All gating is delegated to AutoResponderEvaluator, shared with the
     * WABA/Twilio/Instagram dispatcher, so every engine decides identically.
     */
    private function autoResponderReplyForBaileys(int $workspaceId, $device, string $phoneDigits, string $mobile): ?KeywordReply
    {
        if ($workspaceId <= 0) {
            return null;
        }
        try {
            $provider  = \App\Services\WorkspaceEngine::for($workspaceId);
            $evaluator = app(\App\Services\Inbox\AutoResponderEvaluator::class);

            // Flow-builder inbound-condition triggers — launch away/out_of_hours
            // flows (idempotent per contact), independent of the auto-reply passes
            // below. Fast-paths out when the workspace is in-hours and not away.
            try {
                app(\App\Services\Flow\FlowEnrollmentService::class)->onInboundMessage($workspaceId, $phoneDigits);
            } catch (\Throwable $e) { /* best-effort */ }

            // Sender scoping shared by all three passes: a rule bound to a device
            // fires only for that device; a workspace-wide one (device_id null)
            // fires for any inbound on this workspace.
            $senderScope = function ($q) use ($device) {
                $q->whereNull('device_id');
                if (! empty($device->id)) {
                    $q->orWhere('device_id', (int) $device->id);
                }
            };

            // Conversation + contact resolved ONCE and reused across every pass.
            // (mobile is encrypted → a decrypt-scan, so we never repeat it.) A null
            // convo (brand-new contact) is fine — the evaluator treats it as "allow".
            $convo = \App\Models\Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('contact_digits', $phoneDigits)
                ->orderByDesc('id')->first();

            $contact = null;
            \App\Models\Contact::query()->where('workspace_id', $workspaceId)->orderByDesc('id')
                ->chunk(200, function ($chunk) use (&$contact, $phoneDigits) {
                    foreach ($chunk as $c) {
                        if (preg_replace('/\D+/', '', (string) $c->mobile) === $phoneDigits) {
                            $contact = $c;
                            return false;
                        }
                    }
                    return true;
                });

            // ── WELCOME pass ───────────────────────────────────────────────
            $welcomeRules = KeywordReply::query()
                ->welcomeFor($workspaceId, $provider)
                ->where($senderScope)
                ->with(['selectedContents', 'outsideHoursContents', 'flow'])
                ->orderBy('id')->get();
            foreach ($welcomeRules as $rule) {
                $v = $evaluator->evaluateWelcome($rule, $convo, $phoneDigits, $contact);
                if (! $v['fire']) {
                    continue;
                }
                if ($v['use_outside']) {
                    // Render the outside-hours composer through the same builder.
                    $rule->setRelation('selectedContents', $rule->outsideHoursContents ?? collect());
                    $rule->reply_type   = 'custom';
                    $rule->message_type = optional($rule->outsideHoursContents->first())->content_type ?: 'text';
                }
                $evaluator->stampFire($rule, $convo);
                \Log::info('[KW-LOOKUP] WELCOME fired', [
                    'rule' => $rule->id, 'ws' => $workspaceId,
                    'outside' => $v['use_outside'], 'reason' => $v['reason'],
                ]);
                return $rule;
            }

            // ── AWAY pass ──────────────────────────────────────────────────
            // Manual "Away mode" switch — a deliberate operator signal, so it
            // fronts the automatic out-of-hours pass. One workspace lookup, reused.
            $isAway = $evaluator->isWorkspaceAway($workspaceId);
            if ($isAway) {
                $awayRules = KeywordReply::query()
                    ->awayFor($workspaceId, $provider)
                    ->where($senderScope)
                    ->with(['selectedContents', 'outsideHoursContents', 'flow'])
                    ->get();
                foreach ($awayRules as $rule) {
                    $v = $evaluator->evaluateAway($rule, $convo, $phoneDigits, $contact, $isAway);
                    if (! $v['fire']) {
                        continue;
                    }
                    $evaluator->stampFire($rule, $convo);
                    \Log::info('[KW-LOOKUP] AWAY fired', ['rule' => $rule->id, 'ws' => $workspaceId]);
                    return $rule;
                }
            }

            // ── OUT-OF-HOURS pass ──────────────────────────────────────────
            // Fires on ANY inbound outside the rule's own working_hours (no keyword).
            $oohRules = KeywordReply::query()
                ->outOfHoursFor($workspaceId, $provider)
                ->where($senderScope)
                ->with(['selectedContents', 'outsideHoursContents', 'flow'])
                ->get();
            foreach ($oohRules as $rule) {
                $v = $evaluator->evaluateOutOfHours($rule, $convo, $phoneDigits, $contact);
                if (! $v['fire']) {
                    continue;
                }
                $evaluator->stampFire($rule, $convo);
                \Log::info('[KW-LOOKUP] OUT-OF-HOURS fired', ['rule' => $rule->id, 'ws' => $workspaceId]);
                return $rule;
            }
        } catch (\Throwable $e) {
            \Log::warning('[KW-LOOKUP] auto-responder pass failed: ' . $e->getMessage());
        }
        return null;
    }

    public function lookup(Request $request): JsonResponse
    {
        // Node-bridge auth. Before this gate any public probe could
        // hit /api/keyword-replies?keyword=hi&phone=+15551234567 and
        // get a $audit() row written + wallet charged + trigger_count
        // incremented — a denial-of-wallet primitive. Every other
        // Node-bridge route uses the same X-Node-Token check.
        $expectedToken = node_token();
        $gotToken      = (string) $request->header('X-Node-Token', '');
        // FAIL CLOSED — an empty/unconfigured token must REJECT (not open) the
        // endpoint, matching every other Node-bridge route. Otherwise, when
        // node_token() resolves to '', this public route would leak a
        // workspace's keyword automations to any unauthenticated caller.
        if ($expectedToken === '' || !hash_equals($expectedToken, $gotToken)) {
            // This rejection used to be SILENT — it returns before the
            // '[KW-LOOKUP] 1.in' line below, so a token mismatch produced no log
            // entry at all. The only visible symptom was Laravel's
            // '[WABA-INBOUND] flow bridge consumed:false', which looks identical
            // to "no flow matched this keyword" and sent us hunting through
            // correctly-configured flows. Never log the tokens themselves; the
            // presence/length pair is enough to tell the two causes apart:
            //   expected_set=false  -> Laravel has no token configured
            //                          (set NODE_WEBHOOK_TOKEN, then config:clear)
            //   both set, lengths differ or equal but rejected
            //                       -> Laravel and node/.env hold DIFFERENT values
            \Log::warning('[KW-LOOKUP] REJECTED 401 — Node token mismatch; every keyword + flow trigger is dead until this matches', [
                'expected_set' => $expectedToken !== '',
                'expected_len' => strlen($expectedToken),
                'got_set'      => $gotToken !== '',
                'got_len'      => strlen($gotToken),
                'phone'        => mask_phone((string) $request->query('phone', '')),
                'ip'           => $request->ip(),
            ]);
            return response()->json([['response' => '401', 'reply' => 'unauthorized', 'reply_type' => null, 'flow_id' => null]], 401);
        }

        // Mark t=0 so we can record server-side lookup latency. Every return
        // path below funnels through $audit() which writes one row to
        // auto_reply_lookups — that table powers the analytics page's funnel
        // (incoming → matched → matched-this-rule) and the latency KPI.
        $startedAt = microtime(true);
        $phone     = (string) $request->query('phone', '');
        // Preserve the original (case + script intact) for language
        // detection, while keeping a lowercase copy for SQL matching.
        $keywordRaw = trim((string) $request->query('keyword', ''));
        $keyword    = mb_strtolower($keywordRaw);
        $mobile     = (string) $request->query('mobile', $phone);

        // PII hygiene: never write raw customer numbers or full inbound
        // keyword text to logs. Mask the phone (mask_phone honours the admin
        // toggle used everywhere else) and truncate the keyword.
        \Log::info('[KW-LOOKUP] 1.in', [
            'phone'  => mask_phone($phone),
            'keyword' => \Illuminate\Support\Str::limit($keyword, 24),
            'mobile' => mask_phone($mobile),
        ]);

        $audit = function (?int $deviceId, ?int $matchedReplyId) use ($startedAt, $mobile, $request) {
            try {
                AutoReplyLookup::create([
                    'device_id'                => $deviceId,
                    'matched_keyword_reply_id' => $matchedReplyId,
                    'contact_phone'            => $mobile,
                    'query_text'               => (string) $request->query('keyword', ''),
                    'latency_ms'               => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
                    'created_at'               => now(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('auto_reply_lookups insert failed: ' . $e->getMessage());
            }
        };

        // Empty keyword normally means nothing to match → bail. EXCEPTION: a
        // PHOTO / VOICE order carries no typed text but must still launch the
        // device's order flow (handled by the media-order fallback further down,
        // where the parse node then reads the image via vision / voice via
        // transcript). So only bail on empty text when it is NOT an image/voice.
        $isMediaOrder = ($keyword === '' && in_array((string) $request->query('media_type'), ['image', 'audio'], true));
        if ($phone === '' || ($keyword === '' && !$isMediaOrder)) {
            $audit(null, null);
            return response()->json([['response' => '200', 'reply' => 'notallow', 'reply_type' => null, 'flow_id' => null]]);
        }

        // Storefront cart-message capture — every inbound text is run
        // through the parser; if it matches the deterministic cart
        // shape, a wa_orders row is created. We do this BEFORE the
        // auto-reply lookup so cart messages always land even if no
        // keyword matches. Best-effort — never blocks the bot.
        try {
            // Group the phone-match OR inside the provider filter so the
            // orWhere doesn't escape the engine constraint. Without the
            // closure, `->orWhere('phone_number', $phone)` matched ANY
            // provider's row with that phone digits — cross-engine leak.
            $owner = \App\Models\WaProviderConfig::query()
                ->where('provider', 'baileys')
                ->where(function ($q) use ($phone) {
                    $q->where('phone_number', preg_replace('/\D+/', '', $phone))
                      ->orWhere('phone_number', $phone);
                })
                ->first();
            if ($owner && $owner->workspace_id) {
                app(\App\Services\StorefrontOrderParser::class)
                    ->parse($owner->workspace_id, $keyword, $mobile, null);
            }
        } catch (\Throwable $e) {
            \Log::warning('storefront cart parse failed: ' . $e->getMessage());
        }

        // devices.phone_number is encrypted at rest (non-deterministic
        // ciphertext) so a plain `where('phone_number', X)` never matches.
        // Hydrate the active devices and compare the decrypted value in PHP.
        //
        // The canonical WaDesk shape is `country_code + phone_number` —
        // every other endpoint (inbound, chat, team-inbox) compares
        // against the digits-only concatenation, so we do the same here.
        // We ALSO check phone_number alone for legacy rows that stored
        // the full E.164 in phone_number with country_code empty.
        $needleDigits = preg_replace('/\D+/', '', $phone);
        // Scope to the workspace that owns this phone number — without
        // this, the device lookup walks the entire platform-wide active
        // devices set and could match a same-digits phone in another
        // tenant. The $owner WaProviderConfig resolved above carries
        // the workspace; use it when available.
        $deviceQuery = Device::where('active', 1);
        if (isset($owner) && $owner && $owner->workspace_id) {
            $deviceQuery->where(function ($q) use ($owner) {
                $q->where('workspace_id', $owner->workspace_id)->orWhereNull('workspace_id');
            });
        }
        $device = $deviceQuery->get()->first(function ($d) use ($needleDigits) {
            $concat = preg_replace('/\D+/', '', (string) (($d->country_code ?? '') . $d->phone_number));
            $plain  = preg_replace('/\D+/', '', (string) $d->phone_number);
            return $concat === $needleDigits || $plain === $needleDigits;
        });
        // No Baileys `devices` row matched. WABA/Twilio numbers live in
        // wa_provider_configs (NOT the devices table), so resolve there too —
        // otherwise a keyword/flow rule created on an official number could
        // NEVER fire (this endpoint would always return notallow for it). A
        // lightweight stand-in carrying id + workspace_id is enough for the
        // candidate query's workspace scoping below.
        if (!$device) {
            try {
                $cfg = \App\Models\WaProviderConfig::query()
                    ->whereIn('provider', ['waba', 'twilio'])
                    ->get()
                    ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->phone_number) === $needleDigits);
                if ($cfg) {
                    $device = (object) [
                        'id'           => (int) $cfg->id,
                        'workspace_id' => $cfg->workspace_id,
                        'user_id'      => $cfg->user_id ?? null,
                    ];
                    \Log::info('[KW-LOOKUP] resolved via wa_provider_configs (WABA/Twilio)', [
                        'phone' => $phone, 'cfg_id' => $cfg->id, 'provider' => $cfg->provider, 'ws' => $cfg->workspace_id,
                    ]);
                }
            } catch (\Throwable $e) {}
        }
        if (!$device) {
            $audit(null, null);
            return response()->json([['response' => '200', 'reply' => 'notallow', 'reply_type' => null, 'flow_id' => null]]);
        }

        // Resolve the WORKSPACE that owns this inbound's number + its member
        // user_ids, so a keyword rule created by ANY teammate in the same
        // workspace (possibly on a sibling/duplicate device row from a re-pair)
        // still matches — not just rules bound to the single device row this
        // inbound happened to resolve to. NULL workspace falls back to pure
        // device scoping (never an unscoped/cross-tenant query).
        $lookupWsId = (isset($owner) && $owner && $owner->workspace_id) ? (int) $owner->workspace_id : null;
        if (!$lookupWsId && $device->workspace_id) $lookupWsId = (int) $device->workspace_id;
        if (!$lookupWsId && $device->user_id) {
            $lookupWsId = (int) (\App\Models\User::whereKey($device->user_id)->value('current_workspace_id') ?: 0) ?: null;
        }
        $memberIds = $lookupWsId
            ? \DB::table('workspace_user')->where('workspace_id', $lookupWsId)->pluck('user_id')->all()
            : [];
        if ($device->user_id) $memberIds[] = $device->user_id;
        $memberIds = array_values(array_unique(array_filter($memberIds)));

        // 1. Exact / contains / fuzzy match against the canonical
        //    keyword field. The SQL is a coarse `LIKE '%needle%'`
        //    pre-filter that catches multi-keyword rows; we then
        //    confirm in PHP via matchesNeedle() so a candidate row
        //    whose stored "pricng" doesn't fire on a customer's
        //    unrelated "ping" inbound. Candidate set per device is
        //    tens of rows max — PHP filtering is fine.
        //
        // Candidate scoping — CONFINE every rule to the RESOLVED workspace. The
        // device_id branch used to match ANY rule pointing at this device_id, so
        // a stale rule from a DIFFERENT workspace that still references this
        // device (e.g. the number was reconnected into another workspace) leaked
        // in, fired the WRONG workspace's flow, and billed the WRONG workspace
        // (observed: ws-3 inbound firing ws-2 rule 41 → flow 3). Guard the device
        // branch with the workspace too, still allowing legacy null-workspace
        // rows owned by a workspace member.
        $scopeCandidates = function ($q) use ($lookupWsId, $memberIds, $device) {
            $q->where(function ($dq) use ($device, $lookupWsId, $memberIds) {
                $dq->where('device_id', $device->id);
                if ($lookupWsId) {
                    $dq->where(function ($w) use ($lookupWsId, $memberIds) {
                        $w->where('workspace_id', $lookupWsId)
                          ->orWhere(function ($n) use ($memberIds) {
                              $n->whereNull('workspace_id');
                              !empty($memberIds) ? $n->whereIn('user_id', $memberIds) : $n->whereRaw('1=0');
                          });
                    });
                } elseif (!empty($memberIds)) {
                    $dq->whereIn('user_id', $memberIds);
                } else {
                    $dq->whereRaw('1=0');
                }
            });
            $q->orWhere(function ($w) use ($lookupWsId, $memberIds) {
                $w->whereNull('device_id');
                if ($lookupWsId) { $w->where('workspace_id', $lookupWsId); }
                elseif (!empty($memberIds)) { $w->whereIn('user_id', $memberIds); }
                else { $w->whereRaw('1=0'); }
            });
        };
        // ── AUTO-RESPONDER pass (Unofficial/Baileys) ──────────────────────
        // Runs the welcome → away → out-of-hours passes (all keyword-less) BEFORE
        // the keyword race so the right greeting/away/after-hours message fronts
        // the thread, and falls through to keyword matching when none fire. It
        // reuses the SAME response + fire-log path below (KeywordReplyLog is
        // written for $reply), so these rules render + record exactly like a
        // keyword rule. All gating lives in AutoResponderEvaluator, shared with
        // the WABA/Twilio/IG dispatcher, so every engine decides identically.
        $reply = $this->autoResponderReplyForBaileys((int) ($lookupWsId ?: 0), $device, $needleDigits, $mobile);

        $candidates = KeywordReply::query()
            ->where($scopeCandidates)
            ->where('status', true)
            // Keyword-less auto-responder rules never join the keyword race.
            ->whereNotIn('trigger_type', ['welcome', 'away', 'out_of_hours'])
            ->matchKeyword($keyword)
            ->with(['selectedContents'])
            ->get();
        // Keyword match only fills $reply when the welcome pass didn't already.
        $reply = $reply ?: $candidates->first(fn ($c) => $c->matchesNeedle($keyword));

        // DIAGNOSTIC — the single line that explains "keyword match=NONE".
        // sql_candidates>0 but matched=null → matchesNeedle rejected it.
        // sql_candidates=0 but all_active_rules_in_ws>0 → scoping miss (the rule
        // is bound to a different device_id / engine than this inbound resolved,
        // and lookup_ws didn't catch it). all_*=0 → the rule isn't in this
        // workspace at all. Lists every active rule's id/keyword/device/ws so we
        // can see the exact binding mismatch.
        try {
            $rulesInWs = $lookupWsId ? KeywordReply::where('workspace_id', $lookupWsId)->where('status', true)->count() : null;
            // Plain-English reason the lookup did/didn't match, so the exact
            // binding mismatch is obvious without cross-reading all_active_rules.
            $hint = $reply
                ? 'MATCHED rule ' . $reply->id
                : ($candidates->count() > 0
                    ? 'SQL candidate found but matchesNeedle() rejected it (fuzzy/contains failed on this exact keyword)'
                    : (($rulesInWs ?? 0) > 0
                        ? 'rules EXIST in this workspace but are bound to a DIFFERENT device_id than this inbound (device ' . $device->id . ' / ' . ($device->phone_number ?? '?') . ')'
                        : 'NO active rule for this device/workspace — the rule lives in a DIFFERENT workspace, or this number is connected in a different workspace than where the rules were built'));

            \Log::info('[KW-LOOKUP] 2.scope', [
                'keyword'        => $keyword,
                'device_id'      => $device->id,
                'device_phone'   => $device->phone_number,
                'device_ws'      => $device->workspace_id,
                'device_user'    => $device->user_id,
                'lookup_ws'      => $lookupWsId,
                'member_ids'     => $memberIds,
                'sql_candidates' => $candidates->count(),
                'matched'        => $reply?->id,
                'hint'           => $hint,
                'rules_in_ws'    => $rulesInWs,
                'rules_on_dev'   => KeywordReply::where('device_id', $device->id)->where('status', true)->count(),
                'all_active_rules' => KeywordReply::where('status', true)->get(['id', 'keyword', 'reply_type', 'flow_id', 'device_id', 'workspace_id', 'user_id'])->toArray(),
            ]);
        } catch (\Throwable $e) {}

        // 1.5. Sprint 7 — multilingual fallback in 2 stages.
        //   (a) walk the pre-stored translations on every row for this
        //       device. Cheap (tens of rows). Covers any of the ~80
        //       languages we fan-out at save time.
        //   (b) if still no hit, translate the inbound → canonical
        //       English via Google's auto-detect endpoint (cached 24h)
        //       and retry the canonical SQL match. This catches ANY
        //       language Google supports — Welsh, Tatar, Esperanto.
        $translationLang = null;
        if (!$reply) {
            $candidates = KeywordReply::query()
                ->where($scopeCandidates)
                ->where('status', true)
                ->whereNotNull('keyword_translations')
                ->with(['selectedContents'])
                ->get();
            foreach ($candidates as $c) {
                $hit = $c->matchesTranslation($keywordRaw);
                if ($hit !== null) {
                    $reply = $c;
                    $translationLang = $hit;
                    break;
                }
            }
        }
        if (!$reply && $keywordRaw !== '') {
            // (b) — true universal fallback for any language not pre-stored.
            // ONE Google call returns both the detected source language
            // and the English translation. Cached 24h per (text, target)
            // so the second customer in the same language pays zero.
            $det = \App\Services\Translator::detectAndTranslate($keywordRaw, 'en');
            $detectedSrc = $det['language'] ?? null;
            $canonical   = $det['text']     ?? null;
            if ($detectedSrc && $detectedSrc !== 'en' && $canonical !== null) {
                $reply = KeywordReply::query()
                    ->where($scopeCandidates)
                    ->where('status', true)
                    ->matchKeyword(mb_strtolower($canonical))
                    ->with(['selectedContents'])
                    ->first();
                if ($reply) $translationLang = $detectedSrc;
            }
        }

        // 2. Fuzzy fallback — hydrate fuzzy rows in this device's workspace
        //    and pick the first whose similar_text % >= fuzzy_similarity.
        if (!$reply) {
            $candidates = KeywordReply::query()
                ->where($scopeCandidates)
                ->where('status', true)
                ->where('matching_method', 'fuzzy')
                ->with(['selectedContents'])
                ->get();
            foreach ($candidates as $c) {
                similar_text(mb_strtolower($c->keyword), $keyword, $percent);
                if ((int) round($percent) >= (int) $c->fuzzy_similarity) {
                    $reply = $c;
                    break;
                }
            }
        }

        // Media-order fallback — a customer can send a PHOTO of their list or a
        // VOICE note with NO typed text. There's no keyword to match, but they
        // still want to order, so launch the device's order flow; its parse node
        // resolves the image (vision) / the voice (transcript). Only fires for an
        // image/audio inbound with empty text, and picks the flow keyword rule
        // scoped to THIS device/workspace (preferring a device-bound rule).
        // Product choice: any media may start an order — an unrelated photo just
        // yields "sorry, couldn't find those products".
        if (!$reply && $keyword === '' && in_array((string) $request->query('media_type'), ['image', 'audio'], true)) {
            $reply = KeywordReply::query()
                ->where($scopeCandidates)
                ->where('status', true)
                ->where('reply_type', 'flow')
                ->whereNotNull('flow_id')
                // A DEFAULT-ROUTE flow must NOT be eligible here. This branch
                // grabs "any flow rule on this device" for a text-less photo or
                // voice note, which is how the order flow picks up a photo of a
                // shopping list. If the catch-all joined this pool it would
                // outrank that order flow on media and quietly break it — the
                // default route is a TEXT fallback only.
                ->where('is_catch_all', false)
                ->orderByRaw('device_id IS NULL')
                ->with(['selectedContents'])
                ->first();
            if ($reply) {
                \Log::info('[KW-LOOKUP] media-order → launching flow', [
                    'ws' => $lookupWsId, 'flow_id' => $reply->flow_id, 'media' => (string) $request->query('media_type'),
                ]);
            }
        }

        // PASS 2 — THE DEFAULT ROUTE ("Any inbound message").
        //
        // Everything above is the keyword race: exact, contains, translated,
        // fuzzy, media-order. Only once ALL of them have come back empty do we
        // fall through to the workspace's catch-all rule. That ordering is the
        // whole point — it is why a default-route flow can never swallow a
        // `book` flow. `book me` matches in pass 1 and returns long before we
        // get here; `hello` matches nothing and lands here.
        //
        // TEXT ONLY. A photo or voice note with no caption is already handled
        // by the media-order branch above (which now excludes catch-alls), so
        // letting the default route claim media too would re-break the order
        // flow through the back door.
        if (!$reply && $keyword !== '') {
            $reply = KeywordReply::query()
                ->where($scopeCandidates)
                ->where('status', true)
                ->where('is_catch_all', true)
                // A rule bound to THIS device beats a workspace-wide one, same
                // precedence the media branch uses.
                ->orderByRaw('device_id IS NULL')
                ->orderBy('id')
                ->with(['selectedContents'])
                ->first();

            if ($reply) {
                \Log::info('[KW-LOOKUP] no keyword matched → DEFAULT ROUTE', [
                    'rule_id'   => $reply->id,
                    'flow_id'   => $reply->flow_id,
                    'device_id' => $device->id,
                    'ws'        => $lookupWsId,
                ]);
            }
        }

        if (!$reply) {
            $audit($device->id, null);
            return response()->json([['response' => '200', 'reply' => 'notallow', 'reply_type' => null, 'flow_id' => null]]);
        }

        // A keyword rule that launches a FLOW must only fire while that flow is
        // LIVE. Pausing a flow (is_active=false) or unpublishing it should also
        // silence its keyword trigger — otherwise a "paused" flow keeps starting
        // and replying. The KeywordReply row's own status being true is NOT
        // enough; check the target flow's state too.
        if ($reply->reply_type === 'flow' && $reply->flow_id) {
            $flowLive = \App\Models\Flow::whereKey($reply->flow_id)
                ->where('is_active', true)
                ->where('is_published', true)
                ->exists();
            if (!$flowLive) {
                \Log::info('[KW-LOOKUP] flow paused/unpublished — not firing', [
                    'flow_id' => $reply->flow_id, 'kw_rule' => $reply->id,
                ]);
                $audit($device->id, null);
                return response()->json([['response' => '200', 'reply' => 'notallow', 'reply_type' => null, 'flow_id' => null]]);
            }
        }

        // Plan-first billing. The Node bot delivers this canned reply itself —
        // it does NOT route through WhatsAppDispatcher/InboxDispatcher, so the
        // dispatcher's OverflowBilling meter never sees it. Meter it here:
        // free under the workspace's monthly_messages_limit, 1 wallet credit
        // only on overflow, 'notallow' when over-cap with an empty wallet (so
        // an active plan is NEVER blocked by a zero wallet). We still record
        // the lookup (audit + trigger_count) so analytics reflect the would-fire.
        $wsForBilling = $reply->workspace
            ?: ($reply->workspace_id ? \App\Models\Workspace::find($reply->workspace_id) : null);
        if ($wsForBilling) {
            try {
                $monthStart = now()->startOfMonth();
                $billUserIds = \DB::table('workspace_user')->where('workspace_id', $wsForBilling->id)->pluck('user_id');
                $used = \App\Models\InboxMessage::query()
                    ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $wsForBilling->id))
                    ->where('direction', 'out')
                    ->where('created_at', '>=', $monthStart)
                    ->count();
                if ($billUserIds->isNotEmpty()) {
                    $used += \DB::table('messages')
                        ->whereIn('user_id', $billUserIds)
                        ->where('direction', 'out')
                        ->where('created_at', '>=', $monthStart)
                        ->count();
                }
                \Log::info('[KW-LOOKUP] 3.billing-in', [
                    'ws'          => $wsForBilling->id,
                    'used'        => $used,
                    'plan'        => $wsForBilling->plan,
                    'plan_active' => $wsForBilling->planIsActive(),
                    'msg_limit'   => $wsForBilling->effectiveLimit('monthly_messages_limit', null),
                    'owner_id'    => $wsForBilling->owner_user_id,
                    'wallet'      => optional($wsForBilling->owner)->wallet_credits,
                ]);
                \App\Services\OverflowBilling::consumeOne($wsForBilling, $used);
                \Log::info('[KW-LOOKUP] 4.billing PASSED → returning flow', ['ws' => $wsForBilling->id, 'flow_id' => $reply->flow_id]);
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                \Log::warning('[KW-LOOKUP] 4.billing BLOCKED', ['ws' => $wsForBilling->id, 'reason' => $e->getMessage(), 'used' => $used ?? null]);
                $audit($device->id, $reply->id);
                return response()->json([[
                    'response'   => '200',
                    'reply'      => 'notallow',
                    'reply_type' => null,
                    'flow_id'    => null,
                    'reason'     => 'plan_limit',
                ]]);
            }
        }

        // Bump the trigger counter (atomic via increment()) so the index
        // page's stats / top performers are real. Then write a log row for
        // the analytics page — recent triggers feed, top users, hour
        // heatmap and the daily firing chart all read from this table.
        $reply->forceFill(['last_triggered_at' => now()])->save();
        $reply->increment('trigger_count');
        $audit($device->id, $reply->id);

        // Sprint 7 — detect inbound language so we can pick the right
        // reply variant and persist it for analytics. If the
        // translation-pass matched, trust THAT language over the
        // codepoint detector (Latin-script messages like "Hola" land
        // here when the Spanish translation matched).
        $ownerWorkspace = $reply->workspace;
        $detectedLang = $translationLang ?? \App\Services\LanguageDetector::detect(
            $keywordRaw,
            $ownerWorkspace?->default_language ?: 'en',
        );

        try {
            $variant = $reply->selectedContents->first();
            KeywordReplyLog::create([
                'keyword_reply_id'  => $reply->id,
                'content_id'        => $variant?->id,
                'contact_phone'     => (string) $request->query('mobile', ''),
                'matched_text'      => (string) $request->query('keyword', ''),
                'matched_variant'   => mb_substr($keyword, 0, 64),
                'detected_language' => $detectedLang,
                'fired_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging the fire shouldn't ever break the reply path itself.
            \Log::warning('keyword_reply_logs insert failed: ' . $e->getMessage());
        }

        // Flow-type → tell the bot to start the flow.
        if ($reply->reply_type === 'flow') {
            return response()->json([[
                'response'   => '200',
                'reply'      => 'flow_triggered',
                'reply_type' => 'flow',
                'flow_id'    => $reply->flow_id,
                'cooldown'   => $reply->cooldown,
                'timeout'    => $reply->timeout,
            ]]);
        }

        // #19 — share_contact: return the target Contact's vCard fields so
        // the Node bot can send it back as a contactMessage. Scoped to
        // the keyword-reply's own workspace_id so a malicious admin can't
        // craft a row pointing at another workspace's contact, AND so
        // any teammate in the same workspace can trigger this reply.
        if ($reply->reply_type === 'share_contact' && $reply->target_contact_id) {
            $contact = \App\Models\Contact::query()
                ->where('id', $reply->target_contact_id)
                ->where('workspace_id', $reply->workspace_id)
                ->first();
            if ($contact) {
                $fullPhone = preg_replace('/\D+/', '', (string) (($contact->country_code ?? '') . $contact->mobile));
                return response()->json([[
                    'response'   => '200',
                    'reply'      => 'contact_shared',
                    'reply_type' => 'share_contact',
                    'contact'    => [
                        'name'  => $contact->name ?: trim((string) ($contact->first_name . ' ' . $contact->last_name)),
                        'phone' => $fullPhone,
                        'email' => $contact->email,
                    ],
                    'cooldown'   => $reply->cooldown,
                    'timeout'    => $reply->timeout,
                ]]);
            }
        }

        // #20 — send_catalog: return the catalog handle so the bot can
        // send a Product List (MPM) or single-product (SPM) message via
        // the existing catalog dispatch path. Scoped to the keyword-reply's
        // own workspace_id so a leaked target_catalog_id can't escape.
        if ($reply->reply_type === 'send_catalog' && $reply->target_catalog_id) {
            $cat = \App\Models\WaCatalog::query()
                ->where('id', $reply->target_catalog_id)
                ->where('workspace_id', $reply->workspace_id)
                ->first();
            if ($cat) {
                return response()->json([[
                    'response'   => '200',
                    'reply'      => 'catalog_sent',
                    'reply_type' => 'send_catalog',
                    'catalog'    => [
                        'catalog_id' => $cat->catalog_id,
                        'provider'   => $cat->provider,
                        'mode'       => 'mpm',
                    ],
                    'cooldown'   => $reply->cooldown,
                    'timeout'    => $reply->timeout,
                ]]);
            }
        }

        // #23 — request_location: tell the bot to send the WhatsApp
        // "send your location" prompt. No payload needed — the bot
        // composes the locationRequestMessage itself.
        if ($reply->reply_type === 'request_location') {
            return response()->json([[
                'response'   => '200',
                'reply'      => 'location_requested',
                'reply_type' => 'request_location',
                'cooldown'   => $reply->cooldown,
                'timeout'    => $reply->timeout,
            ]]);
        }

        // Custom — pick first selected content variant.
        $variant = $reply->selectedContents->first();
        if (!$variant) {
            return response()->json([['response' => '200', 'reply' => 'notallow', 'reply_type' => null, 'flow_id' => null]]);
        }

        // Resolve workspace attributes + contact placeholders BEFORE
        // sending back to the bot — same rule team-inbox follows. The
        // bot can't see the workspace attribute table, so {{promo_key}}
        // would otherwise reach the customer literally.
        $contactRow = \App\Models\Contact::query()
            ->where('workspace_id', $reply->workspace_id)
            ->get()
            ->first(function ($c) use ($mobile) {
                $digits = preg_replace('/\D+/', '', (string) $mobile);
                $ccmobile = preg_replace('/\D+/', '', (string) (($c->country_code ?? '') . $c->mobile));
                return $ccmobile === $digits || preg_replace('/\D+/', '', (string) $c->mobile) === $digits;
            });

        // Text/caption with workspace + contact placeholders resolved.
        $resolved = $this->formatVariant($variant, $detectedLang, (int) $reply->workspace_id, $contactRow);

        // Media variant (image / document / video) → hand the bot a JSON
        // payload it already knows how to send (BaileysClientManager
        // .handleCustomAutoReply → sendMediaReply downloads `url` and sends
        // image/document/video with the caption). file_path is the public
        // upload path saved by syncContents(); url() makes it reachable.
        $ctype = $variant->content_type ?: 'text';
        $replyPayload = $resolved;
        if (in_array($ctype, ['image', 'document', 'video'], true) && !empty($variant->file_path)) {
            $replyPayload = json_encode([
                'type'     => $ctype,
                // Legacy rows keep a `uploads/…` path served locally; new rows keep
                // a bare object path resolved against the active media disk (R2 when
                // cloud storage is on, local /storage otherwise).
                'url'      => str_starts_with($variant->file_path, 'uploads/')
                    ? url($variant->file_path)
                    : media_url($variant->file_path),
                'filename' => $variant->original_name ?: basename($variant->file_path),
                'mimetype' => $variant->mime_type,
                'caption'  => $resolved,
            ]);
        }

        return response()->json([[
            'response'         => '200',
            'reply'            => $replyPayload,
            'reply_type'       => 'custom',
            'flow_id'          => null,
            'cooldown'         => $reply->cooldown,
            'timeout'          => $reply->timeout,
            // Real send-delay (the "Reply delay" form field). Separate from
            // the flow-session `timeout`. 0/null = send instantly (unchanged).
            'reply_delay'      => (int) ($reply->timeout ?? 0),
            'detected_language'=> $detectedLang,
        ]]);
    }

    /* ============================ Internal ============================ */

    private function importCsvResponse(Request $request, string $message, int $imported, int $skipped, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => $status < 400,
                'message'  => $message,
                'imported' => $imported,
                'skipped'  => $skipped,
            ], $status);
        }

        return redirect()
            ->route('user.auto-reply.index')
            ->with($status < 400 ? 'status' : 'error', $message);
    }

    private function normaliseCsvHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = mb_strtolower(trim($header));
            $header = preg_replace('/[^a-z0-9]+/', '_', $header);
            return trim((string) $header, '_');
        }, $headers);
    }

    private function csvValue(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    private function csvNullableInt(string $value): ?int
    {
        $value = trim($value);
        return $value === '' ? null : (int) $value;
    }

    private function csvBool(string $value, bool $default): bool
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') return $default;
        if (in_array($value, ['1', 'true', 'yes', 'y', 'on', 'active', 'enabled'], true)) return true;
        if (in_array($value, ['0', 'false', 'no', 'n', 'off', 'paused', 'inactive', 'disabled'], true)) return false;
        return $default;
    }

    /**
     * Persist (or replace) the content variants. Files are uploaded under
     * public/uploads/auto-reply/. We store them per-workspace via the
     * keyword_reply_id since contents are owned by the parent row.
     */
    private function syncContents(Request $request, KeywordReply $row): void
    {
        $items = $request->input('contents', []);
        if (!is_array($items) || empty($items)) return;

        $hasSelected = false;
        foreach ($items as $i => $item) {
            $type      = $item['content_type'] ?? 'text';
            $isSel     = (bool) ($item['is_selected'] ?? false);
            $hasSelected = $hasSelected || $isSel;

            $filePath = $originalName = $mimeType = null;
            $fileSize = null;

            // multipart form: contents[<i>][media] is a File when uploaded
            $key = "contents.$i.media";
            if ($request->hasFile($key)) {
                $f = $request->file($key);
                // Secure-upload guard: the file itself is not covered by the
                // request validation rules, so enforce the strict extension +
                // real-MIME allowlist here. Without it, contents[i][media]
                // named shell.php would be written to the web root => RCE.
                if ($problem = \App\Support\SecureUpload::problem($f)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$key => [$problem]]);
                }
                $name = \App\Support\SecureUpload::safeName($f);
                // Read size + metadata BEFORE move() — move() deletes the /tmp
                // upload, so a getSize() after it fails with "stat failed for
                // /tmp/php…". Capture everything, then move.
                $originalName = $f->getClientOriginalName();
                $mimeType     = $f->getClientMimeType();
                $fileSize     = $f->getSize();
                // Store on the ACTIVE media disk — Cloudflare R2 when cloud storage
                // is enabled, else the local `public` disk. Returns a bare object
                // path (e.g. "auto-reply/<name>") rendered later via media_url().
                // Old behaviour (move to public/uploads) put media on the app
                // server even when R2 was on.
                $filePath     = $f->storeAs('auto-reply', $name, media_disk());
            }

            KeywordReplyContent::create([
                'keyword_reply_id' => $row->id,
                'content_type'     => $type,
                'content'          => $item['content']      ?? null,
                'file_path'        => $filePath,
                'original_name'    => $originalName,
                'mime_type'        => $mimeType,
                'file_size'        => $fileSize,
                'template_id'      => $item['template_id']  ?? null,
                'is_selected'      => $isSel,
                'sort_order'       => (int) ($item['sort_order'] ?? $i),
            ]);
        }

        // Make sure at least one variant is selected, else the lookup
        // endpoint would return notallow even though contents exist.
        if (!$hasSelected) {
            $row->contents()->orderBy('sort_order')->limit(1)->update(['is_selected' => true]);
        }
    }

    private function deleteContentFiles(KeywordReply $row): void
    {
        foreach ($row->contents as $c) {
            if (!$c->file_path) continue;
            // Legacy rows: physical file under public/uploads/…  New rows: a bare
            // object path on the active media disk (local `public` OR R2).
            if (str_starts_with($c->file_path, 'uploads/')) {
                $abs = public_path($c->file_path);
                if (is_file($abs)) @unlink($abs);
            } else {
                try { media_storage()->delete($c->file_path); } catch (\Throwable $e) {}
            }
        }
    }

    /**
     * Bot expects `reply` to be a string. For text it's the body; for media
     * we json-encode the same blob the legacy controller built (the bot
     * already parses this shape — see classes/BaileysClientManager.js).
     *
     * Sprint 7 — when $lang is provided AND a translation exists for that
     * language in $v->content_translations, the translated text replaces
     * the canonical text + media captions. Media files themselves are
     * language-neutral; only their captions get swapped.
     */
    private function formatVariant(
        KeywordReplyContent $v,
        ?string $lang = null,
        int $workspaceId = 0,
        ?\App\Models\Contact $contact = null,
    ): string {
        $url  = $v->file_path
            ? (str_starts_with($v->file_path, 'uploads/') ? asset($v->file_path) : media_url($v->file_path))
            : null;
        $text = (string) \App\Services\KeywordTranslationManager::pickContent($v, $lang);
        $text = $this->resolveReplyText($text, $workspaceId, $contact);
        return match ($v->content_type) {
            'text'     => $text,
            'image'    => json_encode(['type' => 'image',    'url' => $url, 'caption'  => $text]),
            'video'    => json_encode(['type' => 'video',    'url' => $url, 'caption'  => $text]),
            'document' => json_encode(['type' => 'document', 'url' => $url, 'filename' => $v->original_name, 'mimetype' => $v->mime_type ?? 'application/octet-stream']),
            // Render the template to a SENDABLE shape the bot already handles
            // (text, or image/video/document with the body as caption) instead
            // of the bare {type:template} blob the Node renderer had no case
            // for (which sent nothing). Falls back to plain text on any miss.
            'template' => $this->renderTemplateReply($v->template_id, $workspaceId, $contact),
            default    => $text,
        };
    }

    /**
     * A 'template' keyword-reply content rendered for the bot. The Unofficial
     * API can't send Meta-"approved templates" — it sends a normal message —
     * so we flatten the template (header text + body + footer, placeholders
     * resolved) into text, and when the template has a media header we ship
     * it as the corresponding media blob with the body as the caption. Empty
     * string on any miss (lookup treats that as no-reply).
     */
    private function renderTemplateReply(?int $templateId, int $workspaceId, ?\App\Models\Contact $contact): string
    {
        if (!$templateId) return '';
        $t = \App\Models\WaTemplate::find($templateId);
        if (!$t) return '';
        // Scope guard — the template_id was workspace-validated at save time,
        // but double-check so a leaked id can't pull another tenant's template.
        if ($workspaceId > 0 && (int) ($t->workspace_id ?? 0) > 0 && (int) $t->workspace_id !== $workspaceId) {
            return '';
        }

        $type = strtolower((string) ($t->attachment_type ?? ''));
        $isMediaHeader = in_array($type, ['image', 'video', 'document'], true) && !empty($t->attachment_file);

        // Body = header text (only when NOT a media header) + body + footer.
        $parts = [];
        if (!empty($t->header) && !$isMediaHeader) $parts[] = (string) $t->header;
        if (!empty($t->template_body))             $parts[] = (string) $t->template_body;
        if (!empty($t->footer))                    $parts[] = (string) $t->footer;
        // Resolve placeholders exactly like campaigns/broadcasts do, so the
        // auto-reply ships REAL values, not raw {{1}} / {{name}}:
        //   • positional {{N}} → the template's variable_map slot → key
        //   • named      {{key}} → that key
        // …each resolved against workspace Attributes (AttributeResolver) AND
        // the contact's own fields + custom_attributes (mirrors
        // BroadcastsController::varsForRecipient). Empty map / no contact just
        // leaves the relevant placeholders untouched.
        $vmap = is_array($t->variable_map ?? null) ? $t->variable_map : [];
        $raw  = trim(implode("\n\n", $parts));
        $body = app(\App\Services\AttributeResolver::class)->resolve($raw, $vmap, $workspaceId);
        $body = $this->resolveTemplateContactVars($body, $vmap, $contact);

        if ($isMediaHeader) {
            $file = (string) $t->attachment_file;
            // Use media_url() (NOT asset()) to resolve the stored public-disk path.
            // asset('wa-templates/x.jpg') → APP_URL/wa-templates/x.jpg (missing the
            // /storage/ segment) → 404 when the bot fetches it. media_url() builds
            // APP_URL/storage/wa-templates/x.jpg, matching how the template blades
            // render the same file. Absolute URLs (already http/https) pass through.
            $url  = \Illuminate\Support\Str::startsWith($file, ['http://', 'https://']) ? $file : media_url($file);
            if ($type === 'document') {
                return json_encode(['type' => 'document', 'url' => $url, 'filename' => basename($file), 'mimetype' => 'application/octet-stream']);
            }
            return json_encode(['type' => $type, 'url' => $url, 'caption' => $body]);
        }

        return $body;
    }

    /**
     * Resolve a template body's placeholders against the CONTACT, the same way
     * campaigns/broadcasts do (BroadcastsController::varsForRecipient):
     *   • positional {{N}} → variable_map slot → key → contact value
     *   • named      {{key}} / {{name}} / {{first_name}} … → contact value
     * Values come from the contact's own columns first, then its
     * custom_attributes. Unmapped / unknown placeholders are left literal so
     * the operator notices. Workspace-level attributes are already resolved by
     * AttributeResolver before this runs.
     */
    private function resolveTemplateContactVars(string $body, array $vmap, ?\App\Models\Contact $contact): string
    {
        if ($body === '' || !str_contains($body, '{{') || !$contact) return $body;

        // Flatten the stored nested map (['body'=>[{num,key}]]) to {slot=>key};
        // a flat {slot=>key} map passes through unchanged.
        $flat = [];
        if (isset($vmap['header']) || isset($vmap['body'])) {
            foreach (['header', 'body'] as $sec) {
                foreach ((array) ($vmap[$sec] ?? []) as $e) {
                    if (is_array($e) && isset($e['num'], $e['key']) && $e['key'] !== '') {
                        $flat[(string) $e['num']] = (string) $e['key'];
                    }
                }
            }
        } else {
            foreach ($vmap as $k => $v) {
                if (is_string($v) && $v !== '') $flat[(string) $k] = $v;
            }
        }

        $custom = is_array($contact->custom_attributes ?? null) ? $contact->custom_attributes : [];
        $direct = [
            'name'       => (string) ($contact->name ?? ''),
            'first_name' => (string) ($contact->first_name ?? ''),
            'last_name'  => (string) ($contact->last_name ?? ''),
            'mobile'     => (string) ($contact->mobile ?? ''),
            'phone'      => (string) ($contact->mobile ?? ''),
            'email'      => (string) ($contact->email ?? ''),
        ];
        $pull = function (string $key) use ($contact, $custom, $direct): ?string {
            if (isset($direct[$key]) && $direct[$key] !== '') return $direct[$key];
            $v = $contact->{$key} ?? null;
            if (is_scalar($v) && (string) $v !== '') return (string) $v;
            if (isset($custom[$key]) && $custom[$key] !== '') return (string) $custom[$key];
            return null;
        };

        // Positional {{N}} → variable_map key → contact value.
        $body = preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/u', function ($m) use ($flat, $pull) {
            $key = $flat[$m[1]] ?? null;
            if (!$key) return $m[0];
            $v = $pull($key);
            return $v !== null ? $v : $m[0];
        }, $body);

        // Named {{key}} → contact value.
        $body = preg_replace_callback('/\{\{\s*([a-zA-Z_][\w.-]*)\s*\}\}/u', function ($m) use ($pull) {
            $v = $pull($m[1]);
            return $v !== null ? $v : $m[0];
        }, $body);

        return $body;
    }

    /**
     * Substitute workspace attributes + contact-level placeholders in
     * an auto-reply text. Matches what team-inbox does for operator
     * messages so the customer sees resolved values, never raw
     * `{{promo_key}}` / `{{name}}` placeholders.
     */
    private function resolveReplyText(string $text, int $workspaceId, ?\App\Models\Contact $contact): string
    {
        if ($text === '' || !str_contains($text, '{{')) return $text;

        if ($workspaceId > 0) {
            // No variable_map on auto-reply text (those live on Meta
            // templates, not keyword replies) — pass empty so the resolver
            // only honours named {{key}} placeholders.
            $text = app(\App\Services\AttributeResolver::class)->resolve($text, [], $workspaceId);
        }

        if ($contact) {
            $vars = [
                '{{name}}'        => (string) ($contact->name ?? ''),
                '{{first_name}}'  => (string) ($contact->first_name ?? ''),
                '{{last_name}}'   => (string) ($contact->last_name ?? ''),
                '{{mobile}}'      => (string) ($contact->mobile ?? ''),
                '{{phone}}'       => (string) ($contact->mobile ?? ''),
                '{{email}}'       => (string) ($contact->email ?? ''),
            ];
            $text = strtr($text, $vars);
        }

        return $text;
    }

    /**
     * Best-guess primary message_type for the parent row, based on the first
     * content variant in the request. Stored on keyword_replies so the index
     * page can render the correct icon/badge without joining contents.
     */
    private function guessPrimaryMessageType(Request $request): string
    {
        $first = $request->input('contents.0.content_type');
        return in_array($first, KeywordReply::MESSAGE_TYPES, true) ? $first : 'text';
    }

    /**
     * Build the Auto Responder / Welcome Message columns from the request.
     * Every value defaults to the classic keyword behaviour when unset, so a
     * plain keyword rule that posts none of these is stored identically to
     * before. The "Resend after" value/unit is the canonical `cooldown`.
     */
    private function responderColumns(array $data): array
    {
        // Preserve any of the recognised trigger types (keyword / welcome /
        // out_of_hours / away); anything unknown falls back to the classic keyword.
        $trigger   = in_array($data['trigger_type'] ?? 'keyword', KeywordReply::TRIGGER_TYPES, true)
            ? (string) ($data['trigger_type'] ?? 'keyword')
            : 'keyword';
        $resendSec = \App\Services\Inbox\AutoResponderEvaluator::toSeconds(
            $data['resend_value'] ?? null, $data['resend_unit'] ?? null
        );

        // Instagram-native trigger (dm_keyword / comment_to_dm / story_reply /
        // story_mention / mention). Only IG rules post it; WhatsApp rows leave
        // it null and keep using trigger_type. An unknown value → null.
        $igTrigger = in_array($data['ig_trigger'] ?? null, KeywordReply::IG_TRIGGERS, true)
            ? (string) $data['ig_trigger']
            : null;

        return [
            'trigger_type'        => $trigger,
            'ig_trigger'          => $igTrigger,
            // Lower number = higher priority when several auto-responder rules
            // could fire on the same inbound (competitor parity). Default 0.
            'priority'            => max(0, (int) ($data['priority'] ?? 0)),
            // Resend seconds → cooldown (canonical). Fall back to any explicit
            // cooldown, else null (welcome then defaults to 24h at eval time).
            'cooldown'            => $resendSec ?? ($data['cooldown'] ?? null),
            'resend_unit'         => $data['resend_unit'] ?? null,
            'stop_on_agent_reply' => (bool) ($data['stop_on_agent_reply'] ?? false),
            'resume_after'        => \App\Services\Inbox\AutoResponderEvaluator::toSeconds(
                $data['resume_value'] ?? null, $data['resume_unit'] ?? null
            ),
            'resume_unit'         => $data['resume_unit'] ?? null,
            'working_hours'       => $this->parseWorkingHours($data),
            'excluded_numbers'    => $this->parseExcludedNumbers($data['excluded_numbers'] ?? null),
            'excluded_group_ids'  => ! empty($data['excluded_group_ids'])
                ? array_values(array_unique(array_map('intval', $data['excluded_group_ids']))) : null,
        ];
    }

    /** Structured working-hours form fields → the working_hours JSON, or null when off. */
    private function parseWorkingHours(array $data): ?array
    {
        if (empty($data['wh_enabled'])) {
            return null;
        }
        $days = array_values(array_intersect(
            ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            array_map('strtolower', is_array($data['wh_days'] ?? null) ? $data['wh_days'] : [])
        ));
        return [
            'enabled'        => true,
            'days'           => $days,
            'from'           => substr((string) ($data['wh_from'] ?? '09:00'), 0, 5),
            'to'             => substr((string) ($data['wh_to'] ?? '18:00'), 0, 5),
            'timezone'       => (string) ($data['wh_timezone'] ?? '') ?: (string) config('app.timezone', 'UTC'),
            'outside_action' => (($data['wh_outside_action'] ?? 'none') === 'send') ? 'send' : 'none',
        ];
    }

    /** Excluded numbers — accepts a newline/comma textarea OR an array; returns digit strings. */
    private function parseExcludedNumbers($raw): ?array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach ($raw as $n) {
            $d = preg_replace('/\D+/', '', (string) $n);
            if ($d !== '') {
                $out[$d] = $d;
            }
        }
        return $out ? array_values($out) : null;
    }

    /**
     * Persist the outside-hours message variant(s) — same media pipeline as the
     * primary reply, tagged variant_role='outside_hours' + is_selected=false so
     * selectedContents() never returns them.
     */
    private function syncOutsideContents(Request $request, KeywordReply $row): void
    {
        $items = $request->input('outside_contents', []);
        if (! is_array($items) || empty($items)) {
            return;
        }
        foreach ($items as $i => $item) {
            $type = in_array($item['content_type'] ?? 'text', KeywordReply::MESSAGE_TYPES, true)
                ? $item['content_type'] : 'text';
            $filePath = $originalName = $mimeType = null;
            $fileSize = null;
            $key = "outside_contents.$i.media";
            if ($request->hasFile($key)) {
                $f = $request->file($key);
                if ($problem = \App\Support\SecureUpload::problem($f)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$key => [$problem]]);
                }
                $name         = \App\Support\SecureUpload::safeName($f);
                $originalName = $f->getClientOriginalName();
                $mimeType     = $f->getClientMimeType();
                $fileSize     = $f->getSize();
                $filePath     = $f->storeAs('auto-reply', $name, media_disk());
            }
            KeywordReplyContent::create([
                'keyword_reply_id' => $row->id,
                'content_type'     => $type,
                'variant_role'     => 'outside_hours',
                'content'          => $item['content'] ?? null,
                'file_path'        => $filePath,
                'original_name'    => $originalName,
                'file_size'        => $fileSize,
                'mime_type'        => $mimeType,
                'template_id'      => $item['template_id'] ?? null,
                'is_selected'      => false,   // never in the normal reply pool
                'sort_order'       => (int) $i,
            ]);
        }
    }
}
