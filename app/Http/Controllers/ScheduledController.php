<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Device;
use App\Models\ScheduledMessage;
use App\Models\WaTemplate;
use App\Services\NodeSchedulerClient;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * /scheduled — clean rewrite of the legacy ScheduledController.
 *
 * Responsibilities:
 *   - GET  /scheduled              — list page with stats + tabs (index)
 *   - GET  /scheduled/create       — composer form
 *   - POST /scheduled              — store a new schedule
 *   - GET  /scheduled/{id}         — detail page
 *   - PATCH /scheduled/{id}        — update an existing schedule
 *   - POST /scheduled/{id}/pause   — pause a recurring schedule
 *   - POST /scheduled/{id}/resume  — resume a paused recurring schedule
 *   - POST /scheduled/{id}/cancel  — cancel a future schedule
 *   - POST /scheduled/{id}/run-now — fire it immediately, ignoring schedule_time
 *   - DELETE /scheduled/{id}       — soft-delete
 *
 * Differences from the legacy version:
 *   - Workspace-scoped, not user-scoped
 *   - PII (name, body, target numbers) encrypted at rest
 *   - All cron + sending lives in /node — this controller only owns DB
 *     and HTTP handoff to the bot via NodeSchedulerClient
 *   - All lifecycle endpoints return JSON; the page is a thin SPA shell
 *   - Search/filters happen client-side (encrypted columns can't LIKE)
 */
class ScheduledController extends Controller
{
    public function __construct(private NodeSchedulerClient $node)
    {
    }

    /* ============================== Pages ============================== */

    public function index(Request $request): View|JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;

        // Encrypted name/body means search filtering happens client-side after
        // hydration (same pattern as the conversation list). Pull all rows
        // for now; if a workspace ever has thousands of schedules we add a
        // counts-only path with a cursor.
        $all = ScheduledMessage::forWorkspace($wsId)
            ->forCurrentEngine()
            ->orderByDesc('created_at')
            ->with('template:id,template_name', 'device:id,phone_number,device_name')
            ->limit(500)
            ->get();

        $totals = [
            'total'     => $all->count(),
            'active'    => $all->whereIn('status', ['scheduled', 'running'])->count(),
            'paused'    => $all->where('status', 'paused')->count(),
            'completed' => $all->where('status', 'completed')->count(),
            'failed'    => $all->where('status', 'failed')->count(),
            'cancelled' => $all->where('status', 'cancelled')->count(),
            'total_sent'      => $all->sum('total_sent'),
            'total_delivered' => $all->sum('total_delivered'),
            'avg_delivery'    => round($all->where('status', 'completed')->avg('delivery_rate') ?? 0, 1),
        ];

        $upcoming = $all
            ->where('status', 'scheduled')
            ->whereNotNull('next_run_at')
            ->sortBy('next_run_at')
            ->take(8);

        // Silent AJAX path (`?partial=1`) — used by the index JS to
        // refresh the table after pause / resume / cancel / run-now /
        // destroy without forcing a full page reload. Same pattern
        // /broadcasts uses (see resources/js/charts/user-broadcasts-index.js).
        if ($request->boolean('partial') || $request->wantsJson()) {
            return response()->json([
                'rows'   => view('user.scheduled._rows', ['rows' => $all])->render(),
                'totals' => $totals,
                'shown'  => $all->count(),
                'total'  => $all->count(),
            ]);
        }

        return view('user.scheduled.index', [
            'rows'     => $all,
            'totals'   => $totals,
            'upcoming' => $upcoming,
        ]);
    }

    public function create(Request $request): View
    {
        $wsId = (int) $request->user()->current_workspace_id;

        // All four pickers below are workspace-shared — any teammate's
        // device/template/group/broadcast in this workspace should be
        // pickable. Real column names from each table:
        //   devices:        device_name (not 'label')
        //   wa_templates:   template_name + template_body (not 'name'/'body')
        //   contact_groups: user_group + color (not 'name')
        // Device picker — engine-aware so a WABA workspace doesn't see
        // stale Baileys phones in the dropdown. Picks that don't match
        // the engine would fail silently at cron-fire time otherwise.
        $wsId   = $request->user()?->current_workspace_id;
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            // Only CONNECTED senders are pickable — you can't schedule a
            // send from a disconnected phone (the dispatcher would fail
            // silently at fire-time). The /devices page still lists all.
            $devices = Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')
                ->get(['id', 'phone_number', 'device_name']);
        } else {
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get()
                ->map(fn ($cfg) => (object) [
                    'id'           => $cfg->id,
                    'phone_number' => $cfg->phone_number,
                    'device_name'  => $cfg->display_label ?: ('WABA #' . $cfg->id),
                ]);
        }
        $templates = WaTemplate::query()
            ->forCurrentWorkspace()
            ->approved()
            ->orderByDesc('id')
            ->get();
        $groups    = ContactGroup::query()->forCurrentWorkspace()->get(['id', 'user_group', 'color']);

        // "Audience queue" — past broadcast sends. Operator can pick one or
        // more and we'll re-target their original recipients on this new
        // schedule. Only completed sends, capped at 50 most recent so the
        // hydrate pass for the encrypted `name` column stays cheap.
        $queues = Broadcast::query()
            ->forCurrentWorkspace()
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->latest('completed_at')
            ->limit(50)
            ->get(['id', 'name', 'status', 'total_recipients', 'success_count', 'completed_at']);

        // Multi-engine unified picker: every connected sender across ALL the
        // workspace's enabled engines (Unofficial API / Meta / Twilio), each as
        // a composite `engine:id` key. The legacy single-engine $devices list is
        // kept for the empty-state copy + back-compat, but the picker itself now
        // drives the form so an operator can schedule from any enabled engine.
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        // Tag audience for the recipients step, with live member counts.
        $wsId      = (int) (auth()->user()->current_workspace_id ?? 0);
        $tags      = \App\Models\Tag::query()->where('workspace_id', $wsId)->orderBy('name')->get();
        $tagCounts = \Illuminate\Support\Facades\DB::table('contact_tag')
            ->join('contacts', 'contacts.id', '=', 'contact_tag.contact_id')
            ->where('contacts.workspace_id', $wsId)
            ->whereIn('contact_tag.tag_id', $tags->pluck('id'))
            ->selectRaw('contact_tag.tag_id, COUNT(DISTINCT contact_tag.contact_id) AS c')
            ->groupBy('contact_tag.tag_id')
            ->pluck('c', 'contact_tag.tag_id')
            ->all();

        return view('user.scheduled.create', compact('devices', 'templates', 'groups', 'queues', 'senders', 'tags', 'tagCounts'));
    }

    public function detail(Request $request, int $id): View
    {
        $wsId = (int) $request->user()->current_workspace_id;
        $row  = ScheduledMessage::forWorkspace($wsId)
            ->with('template:id,template_name,template_body,header,footer,buttons,language,category,template_type')
            ->with('device:id,device_name,country_code,phone_number,status')
            ->findOrFail($id);

        $recipientSummary = match ($row->recipient_type) {
            'group'  => is_array($row->target_groups)  && count($row->target_groups)  > 0 ? count($row->target_groups) . ' group(s)' : '0 groups',
            'queue'  => is_array($row->target_queues)  && count($row->target_queues)  > 0 ? count($row->target_queues) . ' broadcast queue(s)' : '0 queues',
            'number' => is_array($row->target_numbers) && count($row->target_numbers) > 0 ? count($row->target_numbers) . ' number(s)' : '0 numbers',
            default  => '—',
        };

        $statusBadge = [
            'scheduled' => ['cls' => 'bg-wa-mint text-wa-deep',          'dot' => 'bg-wa-green',    'label' => 'Scheduled'],
            'running'   => ['cls' => 'bg-wa-deep/10 text-wa-deep',       'dot' => 'bg-wa-deep',     'label' => 'Running'],
            'paused'    => ['cls' => 'bg-accent-amber/20 text-[#7B5A14]','dot' => 'bg-accent-amber','label' => 'Paused'],
            'completed' => ['cls' => 'bg-paper-100 text-ink-700',        'dot' => 'bg-ink-700/40',  'label' => 'Completed'],
            'failed'    => ['cls' => 'bg-accent-coral/15 text-accent-coral','dot' => 'bg-accent-coral','label' => 'Failed'],
            'cancelled' => ['cls' => 'bg-paper-100 text-ink-500',        'dot' => 'bg-ink-500/40',  'label' => $row->status],
        ][$row->status] ?? ['cls' => 'bg-paper-100 text-ink-500', 'dot' => 'bg-ink-500/40', 'label' => $row->status];

        $previewBody = null;
        if ($row->template) {
            $previewBody = app(\App\Services\NodeSchedulerClient::class)->resolveTemplateData($row)['template_body'] ?? $row->template->template_body;
        } elseif ($row->message_content) {
            $previewBody = $row->message_content;
        }

        // Per-recipient pivot rows for the tabbed recipient panel. Eager
        // load the matching Contact so we can show the operator a name
        // (when the phone matched a saved contact at store time).
        $recipients = \App\Models\ScheduledMessageContact::query()
            ->where('scheduled_message_id', $row->id)
            ->with('contact:id,name,first_name,last_name,mobile,email,country_code')
            ->orderBy('id')
            ->get();

        // Counts per status — fast totals for the tab badges. Pull from
        // pivot, not from row counters, so the numbers always agree with
        // the recipient table.
        $tabCounts = [
            'all'       => $recipients->count(),
            'sent'      => $recipients->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'delivered' => $recipients->whereIn('status', ['delivered', 'read'])->count(),
            'read'      => $recipients->where('status', 'read')->count(),
            'failed'    => $recipients->where('status', 'failed')->count(),
            'pending'   => $recipients->where('status', 'pending')->count(),
        ];

        return view('user.scheduled.detail', [
            'row'              => $row,
            'recipientSummary' => $recipientSummary,
            'statusBadge'      => $statusBadge,
            'previewBody'      => $previewBody,
            'recipients'       => $recipients,
            'tabCounts'        => $tabCounts,
        ]);
    }

    /* ============================ JSON API ============================ */

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;

        // Plan: feature flag + numeric cap.
        \App\Services\PlanLimitGuard::feature($user->currentWorkspace, 'schedulemessage');
        // Count is scoped to the current workspace so the plan limit
        // matches what's actually visible in /scheduled (which itself
        // uses `forWorkspace($wsId)`). Previously this counted across
        // all of the user's workspaces, blocking creation in one
        // workspace if another was full.
        \App\Services\PlanLimitGuard::check(
            $user->currentWorkspace, 'scheduled_campaign_limit',
            \App\Models\ScheduledMessage::where('workspace_id', $wsId)->count(),
        );

        // Normalize days_of_week before validating — the form might send
        // weekday names ('mon') or numeric strings ('1'). Convert all to
        // integer 0–6 (Sunday=0).
        $request->merge(['days_of_week' => $this->normalizeDaysOfWeek($request->input('days_of_week', []))]);

        $validator = Validator::make($request->all(), [
            'schedule_name'    => 'required|string|max:255',
            'schedule_type'    => 'required|in:once,recurring',
            'send_date'        => 'required|date|after_or_equal:today',
            'send_time'        => 'required|string|max:16',
            // Timezone must be in PHP's known IANA list — a typo would crash
            // Carbon at runtime when we compute scheduled_time below.
            'timezone'         => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            // Multi-engine unified picker: `sender[]` posts an array of composite
            // `engine:id` keys (one per ticked sender). Each resolves to a
            // concrete sender so we stamp the engine the operator actually CHOSE.
            // For BACK-COMPAT the legacy single `device_id` / `device_ids[]`
            // (bare Baileys device ids) is still accepted when `sender` is absent,
            // so single-engine forms + any un-migrated caller stay byte-identical.
            // With 2+ ticked senders we fan out into one ScheduledMessage row per
            // sender (same pattern as /broadcasts and /auto-reply).
            'sender'           => 'required_without_all:device_id,device_ids|array|min:1',
            'sender.*'         => 'string|max:64',
            'device_id'        => 'required_without_all:device_ids,sender|integer',
            'device_ids'       => 'required_without_all:device_id,sender|array|min:1',
            'device_ids.*'     => 'integer',

            // Step 5 "Send a test to me first". The checkbox used to have no
            // `name` at all, so it never reached the server and the toggle
            // did nothing however the operator set it.
            'send_test'        => 'nullable|boolean',

            'message_type'     => 'required|in:plain,template,media,location',
            'template_id'      => 'required_if:message_type,template|integer|nullable',
            // Send-time template overrides from the shared mapping panel.
            'template_overrides' => 'nullable|string|max:65000',
            'message_content'  => 'nullable|string|max:4096',

            'recipient_type'   => 'required|in:group,queue,number,tag',
            'target_tags'      => 'array|nullable',
            'target_tags.*'    => 'integer',
            'target_groups'    => 'array|nullable',
            'target_queues'    => 'array|nullable',          // broadcast_id list
            'contact_numbers'  => 'array|nullable',
            'contact_numbers.*' => 'string|regex:/^[0-9+\-\s]{8,20}$/',

            'repeat_interval'  => 'nullable|in:daily,weekly,monthly',
            'repeat_every'     => 'nullable|integer|min:1|max:365',
            'days_of_week'     => 'required_if:repeat_interval,weekly|array',
            'days_of_week.*'   => 'integer|between:0,6',
            'end_date'         => 'nullable|date|after:send_date',

            'media'            => 'nullable|file|max:51200',
            'latitude'         => 'nullable|numeric|between:-90,90',
            'longitude'        => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        // Reject schedules less than 2 minutes from now — give the worker
        // breathing room and prevent timezone-edge gotchas.
        $tz   = $request->input('timezone');
        $time = $this->parseTimeTo24Hour($request->input('send_time'));
        $when = Carbon::createFromFormat('Y-m-d H:i', "{$request->send_date} {$time}", $tz)->setTimezone(config('app.timezone', 'UTC'));

        if ($when->lt(now()->addMinutes(2))) {
            return response()->json([
                'ok' => false,
                'errors' => ['send_time' => ['Schedule must be at least 2 minutes from now.']],
            ], 422);
        }

        // Resolve recipients — the recipient_type chooses which target_* gets
        // populated; the others stay null. total_recipients is a snapshot at
        // schedule time, not authoritative — the dispatcher recomputes when it
        // actually fires.
        [$totalRecipients, $resolved] = $this->resolveRecipients($wsId, $request);
        if ($totalRecipients === 0) {
            return response()->json([
                'ok' => false,
                'errors' => ['recipient_type' => ['Selected recipients are empty.']],
            ], 422);
        }

        // Auto-save manually-typed numbers to the Contacts table (dedup by
        // phone hash) so a scheduled one-off number is never lost. Group/queue
        // recipients already come from saved contacts, so only capture 'number'.
        if ($request->input('recipient_type') === 'number') {
            $uid = auth()->id();
            foreach ((array) $request->input('contact_numbers', []) as $num) {
                \App\Models\Contact::rememberPhone((int) $wsId, $uid, (string) $num);
            }
        }

        // Media upload — store under public/uploads/scheduled like the rest of
        // the project's user-uploaded media.
        $mediaFile = null;
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            // Secure-upload guard: reject script/active-content uploads and
            // force a server-controlled, randomised, allowlisted extension so
            // a client-named payload.php can never land in the web root.
            if ($problem = \App\Support\SecureUpload::problem($file)) {
                return response()->json(['ok' => false, 'errors' => ['media' => [$problem]]], 422);
            }
            $dir  = public_path('uploads/scheduled');
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = \App\Support\SecureUpload::safeName($file);
            $file->move($dir, $name);
            $mediaFile = $name;
        }

        $messageType = $request->input('message_type');
        // `filled()` not magic-property — `0` is a valid lat/lng (equator)
        // but falsy in PHP. The validator already enforces numeric range.
        $hasCoords = $request->filled('latitude') && $request->filled('longitude');
        $templateType = match (true) {
            $messageType === 'template'  => 'template',
            $hasCoords                   => 'location',
            $mediaFile !== null          => 'media',
            default                      => 'text',
        };

        // Resolve picked senders into a normalized list of targets, one per
        // ScheduledMessage row to create. Each target carries:
        //   id      — devices.id (Baileys) or wa_provider_configs.id (Meta/Twilio)
        //   engine  — the CHOSEN engine, stamped on the row's `provider`
        //   label   — for the multi-row name suffix
        //   from    — full-digit from_number for the dispatcher's client lookup
        //
        // Preferred path: the unified picker's `sender[]` composite keys, each
        // validated via senderForKey() so a forged/stale key is rejected.
        //
        // Fallback path (byte-identical to before): when `sender` is absent we
        // resolve the legacy `device_id` / `device_ids[]` bare Baileys device ids
        // against workspace membership and stamp the workspace default engine.
        $senderTargets = [];

        if ($request->filled('sender')) {
            $seenKeys = [];
            foreach ((array) $request->input('sender') as $key) {
                $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, (string) $key);
                if (!$picked) continue;                       // forged / stale key → drop
                if (isset($seenKeys[$picked['key']])) continue; // de-dupe
                $seenKeys[$picked['key']] = true;
                $senderTargets[] = [
                    'id'     => (int) $picked['id'],
                    'engine' => (string) $picked['engine'],
                    'label'  => (string) $picked['label'],
                    'from'   => preg_replace('/\D+/', '', (string) $picked['phone']),
                ];
            }
            if (empty($senderTargets)) {
                return response()->json(['ok' => false, 'errors' => ['sender' => ['No valid senders in this workspace.']]], 422);
            }
        } else {
            // Legacy device path — intersected with workspace membership so a
            // forged id can't reach the dispatcher. Either input shape
            // (single device_id or device_ids[]) is accepted.
            $requestedIds = $request->filled('device_ids')
                ? array_values(array_unique(array_map('intval', (array) $request->input('device_ids'))))
                : [(int) $request->input('device_id')];
            $devices = Device::query()
                ->forCurrentWorkspace()
                ->whereIn('id', $requestedIds)
                ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $requestedIds)) . ')')
                ->get();
            if ($devices->isEmpty()) {
                return response()->json(['ok' => false, 'errors' => ['device_id' => ['No valid devices in this workspace.']]], 422);
            }
            $legacyEngine = \App\Services\WorkspaceEngine::for($wsId);
            foreach ($devices as $device) {
                $senderTargets[] = [
                    'id'     => (int) $device->id,
                    'engine' => $legacyEngine,
                    'label'  => trim((string) $device->device_name) ?: ('Device #' . $device->id),
                    'from'   => preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)),
                ];
            }
        }

        // Fan-out split. With multi-sender + group/queue we MUST
        // pre-resolve to flat phone numbers at store time, otherwise
        // Node's resolveRecipients would independently expand the same
        // group for every sender row and each contact would receive N
        // copies of the message (once per sender). Same pattern
        // /broadcasts uses — see BroadcastsController@store.
        //
        // Single-sender installs keep the group/queue reference intact
        // so the dispatcher can pick up newly-added members between
        // store-time and fire-time.
        $deviceCount = count($senderTargets);
        $isMulti     = $deviceCount > 1;
        $rcpType     = $request->input('recipient_type');
        $resolvedNumbers = $resolved['numbers'] ?? [];

        if ($isMulti && in_array($rcpType, ['group', 'queue'], true)) {
            $resolvedNumbers = $this->resolveGroupOrQueueToNumbers($wsId, $rcpType, $resolved);
        }

        $numbersBuckets = array_fill(0, $deviceCount, []);
        if ($isMulti && !empty($resolvedNumbers)) {
            foreach (array_values($resolvedNumbers) as $i => $n) {
                $numbersBuckets[$i % $deviceCount][] = $n;
            }
        } else {
            $numbersBuckets[0] = $resolvedNumbers;
        }

        // For single-device + group/queue we kept the reference on the
        // row so the dispatcher can refresh at fire time, BUT the pivot
        // still needs a recipient list at store time so the detail page
        // can show who will receive. Resolve once here for that case.
        $singleDeviceResolvedForPivot = [];
        if (!$isMulti && in_array($rcpType, ['group', 'queue'], true)) {
            $singleDeviceResolvedForPivot = $this->resolveGroupOrQueueToNumbers($wsId, $rcpType, $resolved);
        }

        // Phone → contact_id map for the workspace. Encrypted mobile
        // column on contacts forces a hydrate; one round-trip is fine.
        $phoneToContact = Contact::query()
            ->where('workspace_id', $wsId)
            ->get()
            ->mapWithKeys(fn ($c) => [preg_replace('/\D+/', '', (string) $c->mobile) => $c->id])
            ->all();

        // WhatsApp Warmer — reserve a warming Unofficial number's one-off
        // scheduled send against the SEND DATE's ramped budget (parity with
        // campaigns + broadcasts). Blocks scheduling a batch that won't fit that
        // day's budget. Recurring schedules are skipped — they fire on many days
        // and can't be reserved against a single date at store time.
        if ($request->input('schedule_type') !== 'recurring') {
            $warmer      = app(\App\Services\WarmerService::class);
            $warmDate    = $request->input('send_date') ?: now()->toDateString();
            $warmHolds   = [];
            $warmReserve = [];
            foreach ($senderTargets as $idx => $target) {
                // Engine-aware: WABA/Twilio warm their wa_provider_configs row,
                // Unofficial its Device — the warmer paces volume on every engine.
                $eng  = (string) ($target['engine'] ?? 'baileys');
                $wdev = in_array($eng, ['waba', 'twilio'], true)
                    ? \App\Models\WaProviderConfig::find($target['id'])
                    : Device::find($target['id']);
                if (!$wdev || !$warmer->enabled($wdev)) continue;
                $n = $isMulti ? count($numbersBuckets[$idx] ?? []) : $totalRecipients;
                if ($n <= 0) continue;
                $label = $target['label'] ?: ('Device #' . $target['id']);
                $g = $warmer->gateBatchFor($wdev, $n, $warmDate);
                if (!$g['ok']) {
                    $warmHolds[] = $g['reason'] === 'daily_budget_reached'
                        ? "$label is warming — its budget for $warmDate is already reserved."
                        : "$label is warming — only {$g['remaining']} of its {$g['budget']}-message budget for $warmDate remains, but this send has $n recipients. Reduce the audience or pick a later date.";
                    continue;
                }
                $warmReserve[] = ['device' => $wdev, 'n' => $n, 'date' => $warmDate];
            }
            if (!empty($warmHolds)) {
                return response()->json(['ok' => false, 'errors' => ['schedule' => [
                    'Held by WhatsApp Warmer: ' . implode(' ', $warmHolds) . ' You can adjust these limits at /warmer.',
                ]]], 422);
            }
            foreach ($warmReserve as $r) { $warmer->recordSendsFor($r['device'], $r['n'], $r['date']); }
        }

        $createdRows = [];
        foreach ($senderTargets as $idx => $target) {
            $sliceNumbers = $numbersBuckets[$idx] ?? $resolvedNumbers;
            $suffix       = $isMulti
                ? ' · ' . $target['label']
                : '';

            // For multi-device we always pre-resolved to numbers above,
            // so the row stores ONLY target_numbers and recipient_type
            // becomes 'number'. Single-device rows keep the original
            // group/queue/number shape so the dispatcher can refresh
            // membership at fire time.
            $rowRecipientType = $isMulti ? 'number' : $rcpType;
            $rowQueues  = $isMulti ? null : $resolved['queues'];
            $rowGroups  = $isMulti ? null : $resolved['groups'];
            $sliceTotal = $isMulti ? count($sliceNumbers) : $totalRecipients;

            $row = ScheduledMessage::create([
                'user_id'         => $user->id,
                'workspace_id'    => $wsId,
                // Stamp the engine the operator actually CHOSE for THIS sender
                // (not just the workspace default), so each fanned-out row routes
                // through the right provider. The legacy fallback path resolves
                // $target['engine'] to WorkspaceEngine::for($wsId), keeping
                // single-engine behaviour identical.
                'provider'        => $target['engine'],
                'device_id'       => $target['id'],
                'schedule_name'   => $request->input('schedule_name') . $suffix,
                'message_content' => $request->input('message_content'),
                'template_id'     => $request->input('template_id'),
                'template_overrides' => \App\Services\TemplateOverrideResolver::sanitize(
                    $request->input('template_overrides')
                ),
                'template_type'   => $templateType,
                'schedule_type'   => $request->input('schedule_type'),
                'send_date'       => $request->input('send_date'),
                'send_time'       => $time,
                'scheduled_time'  => $when,
                'timezone'        => $tz,
                'repeat_interval' => $request->input('repeat_interval'),
                'repeat_every'    => $request->input('repeat_every') ?: 1,
                'days_of_week'    => $request->input('days_of_week') ?: null,
                'end_date'        => $request->input('end_date'),
                'media_file'      => $mediaFile,
                'latitude'        => $request->input('latitude'),
                'longitude'       => $request->input('longitude'),
                'recipient_type'  => $rowRecipientType,
                'target_queues'   => $rowQueues,
                'target_groups'   => $rowGroups,
                'target_numbers'  => $sliceNumbers ?: null,
                'total_recipients' => $sliceTotal,
                // Full digit string (country_code + phone_number) so
                // Node's app.locals.clients[phoneNumber] lookup hits
                // the right session. Storing just the local part
                // (`9145808988`) would 404 every dispatch since the
                // Baileys client is keyed by `919145808988`. The sender
                // resolver already normalised this to a full-digit string.
                'from_number'     => $target['from'],
                'status'          => 'scheduled',
                'next_run_at'     => $when,
            ]);

            // Seed per-recipient pivot rows so the /scheduled/{id} detail
            // page can show who will receive (and later, who succeeded /
            // failed). Skip duplicate numbers within one row's bucket.
            // Multi-device rows already pre-resolved group/queue; single-
            // device rows use the resolved-for-pivot list computed above.
            $rowPhones = $isMulti
                ? $sliceNumbers
                : ($rcpType === 'number' ? $resolvedNumbers : $singleDeviceResolvedForPivot);

            if (!empty($rowPhones)) {
                $now = now();
                $pivotRows = [];
                $seen = [];
                foreach ($rowPhones as $p) {
                    $digits = preg_replace('/\D+/', '', (string) $p);
                    if ($digits === '' || isset($seen[$digits])) continue;
                    $seen[$digits] = true;
                    $pivotRows[] = [
                        'scheduled_message_id' => $row->id,
                        'contact_id'           => $phoneToContact[$digits] ?? null,
                        'phone'                => $digits,
                        'status'               => 'pending',
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];
                }
                if (!empty($pivotRows)) {
                    DB::table('scheduled_message_contacts')->insert($pivotRows);
                }
            }

            // Hand each row off to the Node bot. Failures log but
            // don't abort the rest of the fan-out — operator can
            // run-now any rows that didn't register on Node yet.
            $mediaUrl = $mediaFile ? url('uploads/scheduled/' . $mediaFile) : null;
            $nodeId = $row->is_recurring
                ? $this->node->registerRecurring($row, $mediaUrl)
                : $this->node->registerOneOff($row, $mediaUrl);
            if ($nodeId) {
                $row->forceFill([
                    'node_schedule_id' => $nodeId,
                    'status'           => $row->is_recurring ? 'running' : 'scheduled',
                ])->save();
            }
            $createdRows[] = $row;
        }

        $first = $createdRows[0];
        // Surface Node-registration failures instead of reporting a silent
        // success. registerOneOff/registerRecurring return null (leaving
        // node_schedule_id empty) when the bridge is unreachable, the sender
        // device isn't connected, the Node URL is misconfigured, or no
        // recipients matched — in which case the schedule will NOT fire until
        // the bridge re-syncs. Previously this path always said "Schedule
        // created." even when nothing registered, so a broken bridge looked
        // like a working schedule that silently never sent.
        $failedReg = collect($createdRows)->filter(fn ($r) => empty($r->node_schedule_id))->count();
        $okCount   = count($createdRows) - $failedReg;
        if ($failedReg > 0) {
            $message = $failedReg === count($createdRows)
                ? "Saved, but the sending service didn't confirm this schedule — it won't send until the WhatsApp bridge is reachable and the sender device is connected. Reconnect the device, then edit & save to retry."
                : "{$okCount} schedule(s) registered; {$failedReg} couldn't reach the sending service and won't send until the bridge reconnects.";
        } else {
            $message = $isMulti
                ? count($createdRows) . ' schedules created across ' . $deviceCount . ' senders.'
                : 'Schedule created.';
        }

        // "Send a test to me first" — one preview to the operator's own
        // number, using the first created row's sender + engine. Runs AFTER
        // the schedule is saved, so a preview that can't go out never costs
        // the operator their schedule; the reason is appended to the success
        // message instead. On a multi-sender fan-out we preview once rather
        // than once per sender — the operator asked for a look at the
        // message, not a burst of copies.
        $testResult = null;
        if ($request->boolean('send_test')) {
            $testResult = app(\App\Services\Scheduled\SchedulePreviewSender::class)->send($first, $user);
            $message .= $testResult['ok']
                ? ' Test message sent to your number.'
                : " Test message wasn't sent — " . ($testResult['reason'] ?? 'unknown reason') . '.';
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => [
                'id'              => $first->id,
                'created_ids'     => array_map(fn ($r) => $r->id, $createdRows),
                'devices_count'   => $deviceCount,
                'node_schedule_id'=> $first->node_schedule_id,
                // null when the toggle was off; otherwise {ok, reason?, to?}
                // so the UI can flag a failed preview distinctly from a
                // failed schedule.
                'test_send'       => $testResult,
                'redirect_url'    => $isMulti
                    ? route('user.scheduled.index')
                    : route('user.scheduled.detail', $first->id),
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        $row  = ScheduledMessage::forWorkspace($wsId)->findOrFail($id);

        if (in_array($row->status, ['completed', 'cancelled', 'failed'], true)) {
            return response()->json(['ok' => false, 'message' => 'Cannot edit a finished schedule.'], 422);
        }

        $data = $request->validate([
            'schedule_name'   => 'sometimes|string|max:255',
            'message_content' => 'sometimes|nullable|string|max:4096',
            'send_date'       => 'sometimes|date',
            'send_time'       => 'sometimes|string|max:16',
            'timezone'        => 'sometimes|string|max:64',
        ]);

        if (isset($data['send_date']) || isset($data['send_time']) || isset($data['timezone'])) {
            $tz = $data['timezone'] ?? $row->timezone;
            $time = $this->parseTimeTo24Hour($data['send_time'] ?? $row->send_time);
            $when = Carbon::createFromFormat('Y-m-d H:i', ($data['send_date'] ?? $row->send_date->toDateString()) . ' ' . $time, $tz)->setTimezone(config('app.timezone', 'UTC'));
            $data['send_time']      = $time;
            $data['scheduled_time'] = $when;
            $data['next_run_at']    = $when;
        }

        $row->update($data);

        return response()->json(['ok' => true, 'data' => $row->fresh()]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $row = $this->ownedRow($request, $id);
        if (!in_array($row->status, ['scheduled', 'running'], true)) {
            return response()->json(['ok' => false, 'message' => 'Only active schedules can be paused.'], 422);
        }
        // Tell Node.js to stop firing. We still flip the local status even on
        // Node failure so the UI doesn't lie — if Node was wrong, the next
        // dispatcher tick will reconcile.
        $this->node->pause($row);
        $row->forceFill(['status' => 'paused'])->save();
        return response()->json(['ok' => true]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $row = $this->ownedRow($request, $id);
        if ($row->status !== 'paused') {
            return response()->json(['ok' => false, 'message' => 'Only paused schedules can be resumed.'], 422);
        }
        $this->node->resume($row);
        $row->forceFill(['status' => $row->is_recurring ? 'running' : 'scheduled'])->save();
        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $row = $this->ownedRow($request, $id);
        if (in_array($row->status, ['completed', 'cancelled', 'failed'], true)) {
            return response()->json(['ok' => false, 'message' => 'Already finished.'], 422);
        }
        $this->node->cancel($row);
        $row->forceFill(['status' => 'cancelled'])->save();
        return response()->json(['ok' => true]);
    }

    public function runNow(Request $request, int $id): JsonResponse
    {
        $row = $this->ownedRow($request, $id);
        if (in_array($row->status, ['completed', 'cancelled'], true)) {
            return response()->json(['ok' => false, 'message' => 'Schedule is already finished.'], 422);
        }

        // "Send now" must fire IMMEDIATELY. Node holds an in-memory timer keyed
        // to the original scheduled_time, so nudging next_run_at alone never
        // re-fires it (the previous behaviour just waited for a poll tick that
        // may never re-arm an already-registered schedule). Reset the fire time
        // to now, DROP the stale Node timer, then re-register so the bot sends
        // right away. For recurring rows this re-anchors the cadence to now,
        // which is the expected result of an explicit run-now.
        $now = now();
        $row->forceFill([
            'scheduled_time' => $now,
            'next_run_at'    => $now,
            'status'         => $row->is_recurring ? 'running' : 'scheduled',
        ])->save();

        // Remove the old Node timer first so we never double-fire (old time + now).
        if ($row->node_schedule_id) {
            $this->node->cancel($row);
        }

        $mediaUrl = $row->media_file ? url('uploads/scheduled/' . $row->media_file) : null;
        $nodeId = $row->is_recurring
            ? $this->node->registerRecurring($row, $mediaUrl)
            : $this->node->registerOneOff($row, $mediaUrl);

        if (!$nodeId) {
            return response()->json([
                'ok'      => false,
                'message' => "Couldn't reach the sending service to fire now — check the WhatsApp bridge is running and the sender device is connected.",
            ], 502);
        }

        $row->forceFill(['node_schedule_id' => $nodeId])->save();

        return response()->json(['ok' => true, 'message' => 'Sending now…']);
    }

    /**
     * Retry a failed schedule at a new send date / time. Operator opens
     * the retry modal on a failed row, picks fresh date+time+tz, and
     * the row gets reset to "scheduled" + re-registered on Node.
     *
     *   POST /scheduled/{id}/retry
     *     { send_date: "YYYY-MM-DD", send_time: "HH:mm", timezone: "Asia/Kolkata" }
     *
     * Pivot recipients flip back to "pending" so the per-recipient
     * panel starts clean for the new run. failure_reason / failed_at
     * cleared so the detail page reflects fresh state.
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $row = $this->ownedRow($request, $id);
        if ($row->status !== 'failed') {
            return response()->json([
                'ok' => false,
                'message' => 'Only failed schedules can be retried. Use Run-now for other states.',
            ], 422);
        }

        $data = $request->validate([
            'send_date' => 'required|date|after_or_equal:today',
            'send_time' => 'required|string|max:16',
            'timezone'  => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
        ]);

        $tz   = $data['timezone'];
        $time = $this->parseTimeTo24Hour($data['send_time']);
        $when = Carbon::createFromFormat('Y-m-d H:i', $data['send_date'] . ' ' . $time, $tz)->setTimezone(config('app.timezone', 'UTC'));
        if ($when->lt(now()->addMinutes(2))) {
            return response()->json([
                'ok' => false,
                'errors' => ['send_time' => ['Schedule must be at least 2 minutes from now.']],
            ], 422);
        }

        // Cancel any lingering Node cron from the failed run so we
        // don't end up with two crons firing the same recipients.
        if ($row->node_schedule_id) {
            $this->node->cancel($row);
        }

        // Reset the parent row: new time, status back to scheduled,
        // clear the failure trail, reset counters so the new run starts
        // with fresh aggregates.
        $row->forceFill([
            'send_date'        => $data['send_date'],
            'send_time'        => $time,
            'scheduled_time'   => $when,
            'timezone'         => $tz,
            'next_run_at'      => $when,
            'status'           => 'scheduled',
            'failed_at'        => null,
            'failure_reason'   => null,
            'completed_at'     => null,
            'last_run_at'      => null,
            'total_sent'       => 0,
            'total_delivered'  => 0,
            'total_failed'     => 0,
            'charged_sent'     => 0,
            'node_schedule_id' => null,
        ])->save();

        // Reset the per-recipient pivot so the new run starts clean.
        // We DON'T delete + reseed because contact_id matches and any
        // existing rows are still valid — just flip back to pending and
        // null out the timestamps.
        \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
            ->update([
                'status'        => 'pending',
                'error_message' => null,
                'wa_message_id' => null,
                'sent_at'       => null,
                'delivered_at'  => null,
                'read_at'       => null,
                'failed_at'     => null,
                'updated_at'    => now(),
            ]);

        // Re-register on Node with the fresh ISO time.
        $mediaUrl = $row->media_file ? url('uploads/scheduled/' . $row->media_file) : null;
        $nodeId = $row->is_recurring
            ? $this->node->registerRecurring($row, $mediaUrl)
            : $this->node->registerOneOff($row, $mediaUrl);
        if ($nodeId) {
            $row->forceFill([
                'node_schedule_id' => $nodeId,
                'status'           => $row->is_recurring ? 'running' : 'scheduled',
            ])->save();
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Retry scheduled.',
            'data'    => [
                'id'              => $row->id,
                'scheduled_time'  => optional($row->scheduled_time)->toIso8601String(),
                'timezone'        => $row->timezone,
                'node_schedule_id'=> $nodeId,
            ],
        ]);
    }

    /**
     * Webhook the Node.js scheduler hits whenever a job progresses.
     *
     *   POST /api/update-schedule-status
     *   { scheduleId:    <local scheduled_messages.id>,
     *     status:        sent|failed|running|completed|scheduled,
     *     totalSent?:    int,
     *     totalDelivered?: int,
     *     totalFailed?:  int,
     *     phoneNumber?:  string,
     *     timestamp?:    iso8601,
     *     lastRunAt?:    iso8601,
     *     reason?:       string }
     *
     * The Node service in D:\app\whatsapp-bot ships with this URL hard-coded
     * (helpers.js → updateBulkScheduleStatus, scheduleService.js → recurring
     * branch), so we accept its exact payload shape. Extra/optional fields
     * we tolerate but don't require.
     *
     * No CSRF — Node has no session. Authenticated via X-Node-Token header
     * vs NODE_WEBHOOK_TOKEN in .env. If the env var is empty (dev install),
     * any caller is accepted.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        // Refuse when token isn't configured — an empty env was
        // previously treated as "allow everyone", turning a misconfig
        // into a wide-open API. Production MUST set NODE_WEBHOOK_TOKEN.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            abort(403);
        }

        $data = $request->validate([
            'scheduleId'      => 'required',
            'status'          => 'required|in:sent,failed,running,completed,scheduled',
            'totalSent'       => 'nullable|integer|min:0',
            'totalDelivered'  => 'nullable|integer|min:0',
            'totalFailed'     => 'nullable|integer|min:0',
            'phoneNumber'     => 'nullable|string|max:64',
            'timestamp'       => 'nullable|string|max:64',
            'lastRunAt'       => 'nullable|string|max:64',
            'reason'          => 'nullable|string|max:1000',
        ]);

        // The bot stores Laravel's local id as `scheduleId` and its own as
        // `id` — match either to be defensive about future code paths.
        $row = ScheduledMessage::query()
            ->when(is_numeric($data['scheduleId']), fn ($q) => $q->where('id', (int) $data['scheduleId']))
            ->orWhere('node_schedule_id', $data['scheduleId'])
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Schedule not found'], 404);
        }

        // 'sent' is the bot's name for "this job's run is done" — map to
        // local 'completed'. The remaining values pass through 1:1.
        $newStatus = $data['status'] === 'sent' ? 'completed' : $data['status'];

        // Idempotency — if the bot retries this callback (network blip on
        // its side) we mustn't double-count totalSent. Skip the write when
        // the new state would equal or regress the existing one. Since the
        // bot reports cumulative counters per-run, "no progress" means a
        // duplicate.
        $newSent      = $data['totalSent']      ?? null;
        $newDelivered = $data['totalDelivered'] ?? null;
        $newFailed    = $data['totalFailed']    ?? null;

        $isDuplicate = $row->status === $newStatus
            && ($newSent      === null || $newSent      <= (int) $row->total_sent)
            && ($newDelivered === null || $newDelivered <= (int) $row->total_delivered)
            && ($newFailed    === null || $newFailed    <= (int) $row->total_failed);

        if ($isDuplicate) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $patch = ['status' => $newStatus];

        if ($newSent !== null)      { $patch['total_sent']      = $newSent; $patch['last_run_at'] = now(); }
        if ($newDelivered !== null) { $patch['total_delivered'] = $newDelivered; }
        if ($newFailed !== null)    { $patch['total_failed']    = $newFailed; }

        if ($newStatus === 'completed') $patch['completed_at'] = now();
        if ($newStatus === 'failed') {
            $patch['failed_at']      = now();
            $patch['failure_reason'] = $data['reason'] ?? null;
        }

        // Plan-first billing — bill the schedule owner for the *delta* in
        // sent counts since the last callback. The Node bot delivers
        // scheduled messages itself (no Laravel dispatcher meter sees them),
        // so we meter here with OverflowBilling: each message is FREE while
        // the workspace is under its monthly_messages_limit and only spends
        // ONE wallet credit on overflow. We work off totalSent (cumulative)
        // minus what we'd already metered, stored on the row as charged_sent
        // so duplicate / out-of-order callbacks don't double-bill. NO wallet
        // pre-gate — an active plan is never blocked by a zero wallet.
        if ($newSent !== null && $row->user_id) {
            $alreadyCharged = (int) ($row->charged_sent ?? 0);
            $delta = max(0, (int) $newSent - $alreadyCharged);
            if ($delta > 0) {
                $wsObj = $row->workspace_id ? \App\Models\Workspace::find($row->workspace_id) : null;
                if ($wsObj) {
                    // Base usage this calendar month across both outbound
                    // tables (same meter the dispatcher uses); grows per
                    // metered message so overflow kicks in mid-batch.
                    $monthStartSched = now()->startOfMonth();
                    $billUserIds = \DB::table('workspace_user')->where('workspace_id', $wsObj->id)->pluck('user_id');
                    $used = \App\Models\InboxMessage::query()
                        ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $wsObj->id))
                        ->where('direction', 'out')
                        ->where('created_at', '>=', $monthStartSched)
                        ->count();
                    if ($billUserIds->isNotEmpty()) {
                        $used += \DB::table('messages')
                            ->whereIn('user_id', $billUserIds)
                            ->where('direction', 'out')
                            ->where('created_at', '>=', $monthStartSched)
                            ->count();
                    }

                    $metered = 0;
                    for ($i = 0; $i < $delta; $i++) {
                        try {
                            \App\Services\OverflowBilling::consumeOne($wsObj, $used + $metered);
                            $metered++;
                        } catch (\App\Exceptions\PlanLimitReachedException $e) {
                            // Over cap + wallet empty — stop metering. Only
                            // mark what we actually billed so the rest can be
                            // retried on a later callback after a top-up.
                            \Log::warning('scheduled-fire plan cap reached: ' . $e->getMessage());
                            break;
                        } catch (\Throwable $e) {
                            \Log::warning('scheduled-fire billing failed: ' . $e->getMessage());
                            break;
                        }
                    }
                    $patch['charged_sent'] = $alreadyCharged + $metered;
                } else {
                    // No workspace on the row = unmetered (legacy). Advance
                    // the counter so we don't re-evaluate the same delta.
                    $patch['charged_sent'] = $alreadyCharged + $delta;
                }
            }
        }

        $row->forceFill($patch)->save();

        // Backstop: backfill the per-recipient pivot from the aggregate
        // counters. The per-recipient webhook (postRecipientStatus in
        // scheduleService.js) is the primary path, but if Node is
        // running pre-fix code OR a recipient call dropped on the
        // network, the pivot stays at "pending" while the row's
        // aggregate counters land. This call brings the two views into
        // sync so the /scheduled/{id} tabs reflect reality even if
        // per-recipient updates went missing.
        $this->backfillPivotFromAggregates($row);

        // For recurring schedules where the bot just ran a tick, advance
        // next_run_at locally so our /scheduled UI stays in sync. The bot
        // tracks its own cron internally — this is just for our display.
        if ($row->is_recurring && $patch['status'] === 'completed') {
            if ($row->advanceRecurring()) {
                $row->forceFill(['status' => 'running'])->save();
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Bring the per-recipient pivot in line with the parent row's
     * aggregate counters. The Node bot reports cumulative totals
     * (total_sent / total_delivered / total_failed) in its bulk
     * status webhook; if the per-recipient webhook never fired (old
     * Node, network glitch) we still want the detail page tabs to
     * show the right counts.
     *
     * Strategy:
     *   - failed slots come out of the pending pool first (we have no
     *     way to know which specific phones failed without per-recipient
     *     data; leave error_message null in this branch).
     *   - then sent slots flip remaining pending → sent.
     *   - then delivered slots upgrade sent → delivered.
     */
    private function backfillPivotFromAggregates(ScheduledMessage $row): void
    {
        $needSent      = max(0, (int) $row->total_sent);
        $needDelivered = max(0, (int) $row->total_delivered);
        $needFailed    = max(0, (int) $row->total_failed);

        // Cap each bucket against the pivot total so a buggy bot
        // reporting more than we have can't over-update.
        $pivotTotal = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)->count();
        if ($pivotTotal === 0) return;

        $now = now();

        // 1. failed: take from pending first.
        if ($needFailed > 0) {
            $alreadyFailed = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
                ->where('status', 'failed')->count();
            $remaining = $needFailed - $alreadyFailed;
            if ($remaining > 0) {
                $ids = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->pluck('id');
                if ($ids->isNotEmpty()) {
                    \App\Models\ScheduledMessageContact::whereIn('id', $ids)
                        ->update(['status' => 'failed', 'failed_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        // 2. sent: flip remaining pending → sent.
        if ($needSent > 0) {
            $alreadySent = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
                ->whereIn('status', ['sent', 'delivered', 'read'])->count();
            $remaining = $needSent - $alreadySent;
            if ($remaining > 0) {
                $ids = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->pluck('id');
                if ($ids->isNotEmpty()) {
                    \App\Models\ScheduledMessageContact::whereIn('id', $ids)
                        ->update(['status' => 'sent', 'sent_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        // 3. delivered: upgrade sent → delivered.
        if ($needDelivered > 0) {
            $alreadyDelivered = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
                ->whereIn('status', ['delivered', 'read'])->count();
            $remaining = $needDelivered - $alreadyDelivered;
            if ($remaining > 0) {
                $ids = \App\Models\ScheduledMessageContact::where('scheduled_message_id', $row->id)
                    ->where('status', 'sent')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->pluck('id');
                if ($ids->isNotEmpty()) {
                    \App\Models\ScheduledMessageContact::whereIn('id', $ids)
                        ->update(['status' => 'delivered', 'delivered_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    /**
     * Per-recipient outcome webhook. The Node bot calls this after each
     * Baileys send result so the /scheduled/{id} detail page can show
     * who succeeded / failed.
     *
     *   POST /api/update-scheduled-contact-status
     *   { scheduleId:  <local scheduled_messages.id>,
     *     phone:       "919876543210",
     *     status:      sent|delivered|read|failed,
     *     error?:      string,
     *     messageId?:  string (Baileys returned key.id),
     *     timestamp?:  iso8601 }
     *
     * Token-gated like updateStatus(). We aggregate up to the parent
     * row's total_sent / total_delivered / total_failed inside the same
     * transaction so the index page's counts stay coherent.
     */
    public function updateContactStatus(Request $request): JsonResponse
    {
        // Refuse when token isn't configured — an empty env was
        // previously treated as "allow everyone", turning a misconfig
        // into a wide-open API. Production MUST set NODE_WEBHOOK_TOKEN.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            abort(403);
        }

        $data = $request->validate([
            'scheduleId' => 'required|integer',
            'phone'      => 'required|string|max:32',
            'status'     => 'required|in:sent,delivered,read,failed',
            'error'      => 'nullable|string|max:512',
            'messageId'  => 'nullable|string|max:128',
            'timestamp'  => 'nullable|string|max:64',
        ]);

        $row = ScheduledMessage::find($data['scheduleId']);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Schedule not found'], 404);
        }

        $digits = preg_replace('/\D+/', '', (string) $data['phone']);

        // Find the matching pivot row. We pre-seeded these at store()
        // time so most sends will hit; if the bot adds a recipient on
        // the fly (e.g. group grew between store and fire), create one.
        $pivot = \App\Models\ScheduledMessageContact::query()
            ->where('scheduled_message_id', $row->id)
            ->where('phone', $digits)
            ->first();

        if (!$pivot) {
            // Try to match the phone to a Contact row in this workspace
            // so the detail page can display a name. Filter by the
            // scheduled-message's workspace_id directly — much tighter
            // than pivoting through workspace_user user_ids (which can
            // miss members whose current workspace is elsewhere).
            $contactId = \App\Models\Contact::query()
                ->where('workspace_id', $row->workspace_id)
                ->get()
                ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->mobile) === $digits)?->id;
            $pivot = \App\Models\ScheduledMessageContact::create([
                'scheduled_message_id' => $row->id,
                'contact_id'           => $contactId,
                'phone'                => $digits,
                'status'               => 'pending',
            ]);
        }

        // Idempotency / hierarchy — a `read` callback shouldn't regress
        // to `sent`, and we shouldn't double-count if Node retries.
        $rank = ['pending' => 0, 'failed' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];
        $newStatus = $data['status'];
        $newRank   = $rank[$newStatus]      ?? 0;
        $curRank   = $rank[$pivot->status]  ?? 0;

        // Failure overrides "still pending" but not a successful send —
        // mirror what /broadcasts does. Other transitions only go up.
        if ($newStatus === 'failed') {
            if ($pivot->status !== 'pending') {
                return response()->json(['ok' => true, 'noop' => true]);
            }
        } elseif ($newRank <= $curRank) {
            return response()->json(['ok' => true, 'noop' => true]);
        }

        $patch = ['status' => $newStatus];
        $ts = $this->parseIsoToDateTime($data['timestamp'] ?? null) ?: now();

        if ($newStatus === 'sent'      && !$pivot->sent_at)      $patch['sent_at']      = $ts;
        if ($newStatus === 'delivered' && !$pivot->delivered_at) $patch['delivered_at'] = $ts;
        if ($newStatus === 'read'      && !$pivot->read_at)      $patch['read_at']      = $ts;
        if ($newStatus === 'failed') {
            $patch['failed_at']     = $ts;
            $patch['error_message'] = $data['error'] ?? null;
        }
        if (!empty($data['messageId']) && !$pivot->wa_message_id) {
            $patch['wa_message_id'] = $data['messageId'];
        }

        $pivot->update($patch);

        // Recompute the parent row's aggregate counters from the pivot —
        // single source of truth, no double-counting. Cheap query
        // (indexed by scheduled_message_id + status).
        $counts = \App\Models\ScheduledMessageContact::query()
            ->where('scheduled_message_id', $row->id)
            ->selectRaw('
                SUM(CASE WHEN status IN ("sent","delivered","read") THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status IN ("delivered","read")        THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN status = "failed"                     THEN 1 ELSE 0 END) AS failed_count
            ')->first();
        $row->forceFill([
            'total_sent'      => (int) ($counts->sent_count ?? 0),
            'total_delivered' => (int) ($counts->delivered_count ?? 0),
            'total_failed'    => (int) ($counts->failed_count ?? 0),
            'last_run_at'     => now(),
        ])->save();

        return response()->json(['ok' => true, 'status' => $newStatus]);
    }

    private function parseIsoToDateTime(?string $iso): ?\Carbon\Carbon
    {
        if (!$iso) return null;
        try { return \Carbon\Carbon::parse($iso); } catch (\Throwable) { return null; }
    }

    /**
     * Bot startup hook. After a `pm2 restart`, the Node service has lost
     * its in-memory `app.locals.scheduledMessages` / `scheduledJobs` state
     * but our DB still holds the rows. Bot calls this to fetch every active
     * row and re-register its cron jobs.
     *
     * Token-gated like updateStatus(); same NODE_WEBHOOK_TOKEN.
     *
     *   GET /api/scheduled/active
     *     [optional] ?phone_number=+91...   only schedules from this device
     *
     *   → { ok: true, schedules: [
     *         { id, schedule_type, scheduled_time, timezone, message,
     *           messageType, mediaUrl, latitude, longitude,
     *           targetPhoneNumbers, recipientAttributes, isTemplate, templateData,
     *           repeatInterval, repeatEvery, daysOfWeek, endDate,
     *           from_number, node_schedule_id }, ... ] }
     *
     * The bot iterates and calls its own scheduleBulkMessage / scheduleRecurring
     * register routines with this payload. After re-registering, it should
     * POST back the new node_schedule_id via /api/update-schedule-status.
     */
    public function activeForBot(Request $request): JsonResponse
    {
        // Refuse when token isn't configured — an empty env was
        // previously treated as "allow everyone", turning a misconfig
        // into a wide-open API. Production MUST set NODE_WEBHOOK_TOKEN.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            abort(403);
        }

        $rows = ScheduledMessage::query()
            ->whereIn('status', ['scheduled', 'running'])
            ->when($request->query('phone_number'), fn ($q, $p) => $q->where('from_number', $p))
            ->orderBy('next_run_at')
            ->limit(1000)
            ->get();

        // Plan-first eligibility gate. We don't want the bot to start
        // blasting a 50k broadcast for an account that genuinely can't send
        // — but an active plan must NEVER be dropped just because its wallet
        // sits at 0. Mirror OverflowBilling's decision read-only (we can't
        // charge here; the actual per-message charge lands on the status
        // callback): let a schedule through when its workspace is still under
        // the monthly_messages_limit OR the workspace owner has at least one
        // wallet credit for overflow. Drop only over-cap + empty-wallet.
        $monthStartElig = now()->startOfMonth();
        $rows = $rows->filter(function (ScheduledMessage $r) use ($monthStartElig) {
            if (!$r->user_id) return true; // legacy rows — let through
            $ws = $r->workspace_id ? \App\Models\Workspace::find($r->workspace_id) : null;
            if (!$ws) return true; // no workspace = unmetered, don't drop

            $limit = $ws->effectiveLimit('monthly_messages_limit', null);
            if ($limit === null) return true; // unlimited plan

            // Count outbound this month across both tables, same as the
            // dispatcher's meter, to decide if we're still under cap.
            $billUserIds = \DB::table('workspace_user')->where('workspace_id', $ws->id)->pluck('user_id');
            $used = \App\Models\InboxMessage::query()
                ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $ws->id))
                ->where('direction', 'out')
                ->where('created_at', '>=', $monthStartElig)
                ->count();
            if ($billUserIds->isNotEmpty()) {
                $used += \DB::table('messages')
                    ->whereIn('user_id', $billUserIds)
                    ->where('direction', 'out')
                    ->where('created_at', '>=', $monthStartElig)
                    ->count();
            }
            if ($used < (int) $limit) return true; // still inside free quota

            // Over cap — only let through if there's a wallet credit to spend.
            $ownerId = (int) $ws->owner_user_id;
            if (!$ownerId) return false;
            return (int) (\App\Models\User::whereKey($ownerId)->value('wallet_credits') ?? 0) >= 1;
        })->values();

        $node = $this->node;
        $schedules = $rows->map(function (ScheduledMessage $r) use ($node) {
            // Resolve the recipients fresh — group membership may have
            // changed since the schedule was created. Same logic the
            // initial register call used, kept on the Node side via
            // `targetPhoneNumbers` so the bot doesn't have to re-resolve.
            [$recipients, $attrs] = $node->resolveRecipients($r);

            $mediaUrl     = $r->media_file ? url('uploads/scheduled/' . $r->media_file) : null;
            $templateData = $r->template_id ? $node->resolveTemplateData($r) : null;

            return [
                'id'                  => $r->id,
                'schedule_type'       => $r->schedule_type,
                'scheduleId'          => $r->id,
                'node_schedule_id'    => $r->node_schedule_id,
                'from_number'         => $r->from_number,
                // Authoritative engine for this row — Node routes off
                // this instead of guessing from workspace settings.
                'provider'            => $r->provider ?: null,
                'message'             => $node->resolveFreeformMessage($r),
                'messageType'         => $r->template_type,
                'isTemplate'          => $r->template_type === 'template',
                'templateData'        => $templateData,
                'mediaUrl'            => $mediaUrl,
                'latitude'            => $r->latitude,
                'longitude'           => $r->longitude,
                // ISO with offset so Node's moment.tz() converts UTC →
                // user-local correctly. See NodeSchedulerClient::registerOneOff.
                'scheduleDateTime'    => optional($r->scheduled_time)->toIso8601String(),
                'nextRunAt'           => optional($r->next_run_at)->toIso8601String(),
                'timezone'            => $r->timezone,
                'targetPhoneNumbers'  => $recipients,
                'recipientAttributes' => $attrs,
                'repeatInterval'      => $r->repeat_interval,
                'repeatEvery'         => $r->repeat_every,
                'daysOfWeek'          => $r->days_of_week ?? [],
                'endDate'             => optional($r->end_date)->toDateString(),
            ];
        });

        return response()->json(['ok' => true, 'schedules' => $schedules]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $row = $this->ownedRow($request, $id);

        // Tell Node.js to drop the registered job before we delete locally —
        // otherwise it would fire a message for a schedule the user already
        // wanted gone.
        if ($row->node_schedule_id) {
            $this->node->cancel($row);
        }

        // Drop the uploaded media file along with the row so we don't leak
        // public URLs to deleted schedules.
        if ($row->media_file) {
            $path = public_path('uploads/scheduled/' . $row->media_file);
            if (is_file($path)) @unlink($path);
        }

        $row->delete();
        return response()->json(['ok' => true]);
    }

    /* ========================== Internal ========================== */

    private function ownedRow(Request $request, int $id): ScheduledMessage
    {
        $wsId = (int) $request->user()->current_workspace_id;
        return ScheduledMessage::forWorkspace($wsId)->findOrFail($id);
    }

    private function parseTimeTo24Hour(string $timeStr): string
    {
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $timeStr)) {
            return $timeStr;
        }
        $dt = \DateTime::createFromFormat('h:i A', $timeStr) ?: \DateTime::createFromFormat('h:iA', $timeStr);
        return $dt ? $dt->format('H:i') : $timeStr;
    }

    private function normalizeDaysOfWeek(mixed $raw): array
    {
        if (!is_array($raw)) $raw = [$raw];
        $map = [
            'sun' => 0, 'sunday' => 0, 'mon' => 1, 'monday' => 1,
            'tue' => 2, 'tuesday' => 2, 'wed' => 3, 'wednesday' => 3,
            'thu' => 4, 'thursday' => 4, 'fri' => 5, 'friday' => 5,
            'sat' => 6, 'saturday' => 6,
        ];
        $out = [];
        foreach ($raw as $d) {
            $key = trim(strtolower((string) $d));
            if (isset($map[$key])) $out[] = $map[$key];
            elseif (is_numeric($d) && $d >= 0 && $d <= 6) $out[] = (int) $d;
        }
        return array_values(array_unique($out));
    }

    /**
     * Compute the recipients snapshot at schedule time. Returns a tuple
     * of [totalRecipients, ['queues' => [...], 'groups' => [...], 'numbers' => [...]]].
     * Only one of queues/groups/numbers is populated based on recipient_type.
     */
    private function resolveRecipients(int $wsId, Request $request): array
    {
        $type = $request->input('recipient_type');

        // "queue" maps to past broadcast sends in the new schema. Pick a list
        // of broadcast ids → snapshot the union of their recipients via the
        // broadcast_contacts pivot. Phone numbers come encrypted on the
        // contacts row, so we hydrate (no SQL filter possible there anyway).
        if ($type === 'queue') {
            $queueIds = collect((array) $request->input('target_queues', []))
                ->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
            $count = 0;
            if ($queueIds) {
                // Verify the broadcasts belong to this workspace before counting.
                $ownedQueues = DB::table('broadcasts')
                    ->whereIn('id', $queueIds)
                    ->where('workspace_id', $wsId)
                    ->pluck('id');
                if ($ownedQueues->isNotEmpty()) {
                    $count = DB::table('broadcast_contacts')
                        ->whereIn('broadcast_id', $ownedQueues)
                        ->distinct('contact_id')
                        ->count('contact_id');
                }
            }
            return [$count, ['queues' => $queueIds, 'groups' => null, 'numbers' => null]];
        }

        // Tag audience — everyone carrying the chosen tags. Tag ids are
        // restricted to THIS workspace before the contact query so a forged
        // id can't reach another tenant's contacts. Stored in target_groups
        // (prefixed) so no schema change is needed; the dispatcher re-resolves
        // at fire time, which is what makes a tag audience LIVE — contacts
        // tagged after scheduling are included when it actually sends.
        if ($type === 'tag') {
            $tagIds = collect((array) $request->input('target_tags', []))
                ->filter()->map(fn ($v) => (int) $v)->unique()->values();
            $safe = \App\Models\Tag::query()
                ->where('workspace_id', $wsId)
                ->whereIn('id', $tagIds->all())
                ->pluck('id');
            $count = 0;
            if ($safe->isNotEmpty()) {
                $count = \App\Models\Contact::query()
                    ->where('workspace_id', $wsId)
                    ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $safe->all()))
                    ->count();
            }
            return [$count, [
                'queues'  => null,
                'groups'  => $safe->map(fn ($id) => 'tag:' . $id)->values()->all(),
                'numbers' => null,
            ]];
        }

        if ($type === 'group') {
            $groups = collect((array) $request->input('target_groups', []))
                ->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();

            // contact_group is encrypted JSON in the Contact model — we can't
            // SQL-filter on it. Hydrate contacts owned by workspace members,
            // count those whose group set intersects the requested groups.
            $count = 0;
            if ($groups) {
                $count = Contact::query()->where('workspace_id', $wsId)->get()
                    ->filter(function ($c) use ($groups) {
                        $own = collect($c->contact_group ?? [])->map(fn ($v) => (string) $v);
                        return $own->intersect($groups)->isNotEmpty();
                    })->count();
            }
            return [$count, ['queues' => null, 'groups' => $groups, 'numbers' => null]];
        }

        // recipient_type === 'number'
        $numbers = collect((array) $request->input('contact_numbers', []))
            ->map(fn ($n) => preg_replace('/[^0-9+]/', '', (string) $n))
            ->filter(fn ($n) => preg_match('/^\+?[0-9]{8,15}$/', $n))
            ->unique()->values()->all();

        return [count($numbers), ['queues' => null, 'groups' => null, 'numbers' => $numbers ?: null]];
    }

    /**
     * Multi-device pre-resolution. When the operator picks a group or
     * queue with N>1 devices we expand the reference to a flat list of
     * phone numbers at store-time so the audience can be split round-
     * robin across the device rows. Otherwise each ScheduledMessage row
     * would point at the same group and Node would fan out the full
     * group per device → every contact gets N copies.
     */
    private function resolveGroupOrQueueToNumbers(int $wsId, string $type, array $resolved): array
    {
        if ($type === 'group') {
            $groups = collect($resolved['groups'] ?? [])->map(fn ($v) => (string) $v)->all();
            if (empty($groups)) return [];
            // subscribed() — never message someone who replied STOP. Campaigns
            // and broadcasts have always filtered here; /scheduled did not, so
            // an opted-out contact still received every scheduled send.
            return Contact::query()->where('workspace_id', $wsId)->subscribed()->get()
                ->filter(function ($c) use ($groups) {
                    $own = collect($c->contact_group ?? [])->map(fn ($v) => (string) $v)->all();
                    return !empty(array_intersect($own, $groups));
                })
                ->pluck('mobile')
                ->filter()
                ->map(fn ($m) => (string) $m)
                ->unique()
                ->values()
                ->all();
        }

        if ($type === 'queue') {
            $queueIds = collect($resolved['queues'] ?? [])->filter()->map(fn ($v) => (int) $v)->all();
            if (empty($queueIds)) return [];
            $ownedQueues = DB::table('broadcasts')
                ->whereIn('id', $queueIds)
                ->where('workspace_id', $wsId)
                ->pluck('id');
            if ($ownedQueues->isEmpty()) return [];
            $contactIds = DB::table('broadcast_contacts')
                ->whereIn('broadcast_id', $ownedQueues)
                ->distinct('contact_id')
                ->pluck('contact_id');
            if ($contactIds->isEmpty()) return [];
            return Contact::whereIn('id', $contactIds)->subscribed()->get()
                ->pluck('mobile')
                ->filter()
                ->map(fn ($m) => (string) $m)
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }
}
