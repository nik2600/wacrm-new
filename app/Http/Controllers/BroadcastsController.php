<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\ContactGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Broadcasts — ports the old project's
 * D:\wadesk_2806\New folder\app\Http\Controllers\BroadcastController.php
 * onto the new Eloquent + encrypted-at-rest pattern, with the
 * same external-bridge integration (Node's broadcast/send-immediate
 * and broadcast/schedule endpoints) gated on env('SERVER_URL').
 *
 * The page UI uses AJAX for filter pills + live search, so index()
 * returns either the full view or a JSON `{cards, stats, ...}`
 * partial when the request asks for JSON / `partial=1`.
 */
class BroadcastsController extends Controller
{
    // -----------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        $userId = Auth::id();
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $rangeFilter  = $request->string('range')->toString()  ?: 'all';
        $search       = $request->string('q')->toString();

        $allBroadcasts = Broadcast::query()
            ->forCurrentWorkspace()
            ->forCurrentEngine()
            ->orderByDesc('id')
            ->get();

        $broadcasts = $allBroadcasts;
        if ($statusFilter !== 'all') {
            $broadcasts = $broadcasts->where('status', $statusFilter);
        }
        if ($rangeFilter !== 'all') {
            $cutoff = match ($rangeFilter) {
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                default => null,
            };
            if ($cutoff) $broadcasts = $broadcasts->where('created_at', '>=', $cutoff);
        }
        $broadcasts = Broadcast::filterByName($broadcasts, $search)->values();
        $broadcasts = $this->paginateCollection($broadcasts, $request, 12);

        $payload = [
            'broadcasts'    => $broadcasts,
            'stats'         => $this->statsForUser($userId, $allBroadcasts),
            'statusCounts'  => $this->statusCounts($allBroadcasts),
            'currentStatus' => $statusFilter,
            'currentRange'  => $rangeFilter,
            'currentSearch' => $search,
        ];

        if ($request->wantsJson() || $request->boolean('partial')) {
            return response()->json([
                'ok'           => true,
                'rows'         => view('user.broadcasts._rows', ['broadcasts' => $broadcasts])->render(),
                'stats'        => $payload['stats'],
                'statusCounts' => $payload['statusCounts'],
                'pagination'   => view('user.partials.pagination', ['paginator' => $broadcasts, 'dataAttr' => 'data-bc-page', 'label' => 'broadcasts'])->render(),
                'shown'        => $broadcasts->count(),
                'total'        => $broadcasts->total(),
                'page'         => $broadcasts->currentPage(),
            ]);
        }

        return view('user.broadcasts.index', $payload);
    }

    public function create(): View
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $wsId = (int) $user->current_workspace_id;

        // Workspace-shared visibility — every asset in this workspace
        // (regardless of which teammate created it) shows up in the
        // pickers below. Includes admin-seeded global templates via
        // WaTemplate::forCurrentWorkspace.
        $contacts = Contact::query()
            ->forCurrentWorkspace()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $groups = ContactGroup::query()
            ->forCurrentWorkspace()
            ->orderBy('user_group')
            ->get();

        // Real approved Meta templates — same library /wa-campaigns
        // and /chat pull from. Workspace-scoped so a tenant can't see
        // another tenant's templates by opening the picker.
        $templates = \App\Models\WaTemplate::query()
            ->forCurrentWorkspace()
            ->approved()
            ->with('provider')   // so the picker can label which WABA account each template belongs to
            ->orderByDesc('id')
            ->get();

        // Multi-engine: every connected sender across ALL enabled engines
        // (Unofficial API + WABA + Twilio), surfaced through the single
        // source of truth so a workspace running more than one engine at
        // once gets every phone in the bespoke share-weight picker — not
        // just the default engine's. Each item carries the composite
        // `key` (engine:id) the blade/JS/store now key the share map on,
        // plus `engine` + `is_default` so the blade can group by engine
        // and the store can stamp the CHOSEN engine per fanned-out row.
        //
        // Single-engine workspaces (the entire current customer base) get
        // exactly the same flat list as before — one engine ⇒ no group
        // headers, identical weight UI. The descriptor `label` ("Unofficial
        // API" for baileys) is carried for the per-engine group header.
        $devices = \App\Services\WorkspaceEngine::senders($user?->current_workspace_id)
            ->map(fn ($s) => [
                'key'         => $s['key'],
                'id'          => $s['id'],
                'label'       => $s['label'],
                'phone'       => $s['phone'],
                'engine'      => $s['engine'],
                'engineLabel' => $s['descriptor']['label'] ?? $s['engine'],
                'is_default'  => (bool) ($s['is_default'] ?? false),
                'status'      => 'connected',
            ])
            ->values();

        // Browser-visible IANA timezone list. Pre-select the
        // workspace's timezone so the operator doesn't have to
        // hunt for it every send.
        $timezones = \DateTimeZone::listIdentifiers();
        $defaultTz = optional($user->currentWorkspace)->timezone
            ?? $user->timezone
            ?? config('app.timezone', 'UTC');

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

        return view('user.broadcasts.create', compact(
            'contacts', 'groups', 'templates', 'devices', 'timezones', 'defaultTz',
            'tags', 'tagCounts'
        ));
    }

    public function show(int $id): View
    {
        $broadcast = Broadcast::query()->forCurrentWorkspace()
            ->with(['contacts', 'recipients'])
            ->findOrFail($id);

        // Per-recipient rows. Two source tables — legacy
        // `broadcast_contacts` (old broadcasts pre-2026) and the new
        // `scheduled_message_contacts` (new path). Query both, normalize
        // to a single shape, merge by contact_id preferring SMC since
        // that's the live-updated row. Encrypted Contact fields still
        // round-trip via Eloquent so the page never leaks ciphertext.
        $smcRows = DB::table('scheduled_message_contacts')
            ->where('scheduled_message_id', $broadcast->id)
            ->get([
                'contact_id', 'status', 'error_message', 'wa_message_id as whatsapp_message_id',
                'sent_at', 'delivered_at', 'read_at', 'failed_at', 'created_at as queued_at',
            ]);

        $legacyRows = DB::table('broadcast_contacts')
            ->where('broadcast_id', $broadcast->id)
            ->whereNotIn('contact_id', $smcRows->pluck('contact_id'))
            ->get([
                'contact_id', 'status', 'error_message', 'whatsapp_message_id',
                'sent_at', 'delivered_at', 'read_at', \DB::raw('NULL as failed_at'), 'created_at as queued_at',
            ]);

        $pivot = $smcRows->concat($legacyRows)->sortByDesc('queued_at')->values();

        // Click counts per contact for this broadcast. Single grouped
        // query so the merge below is O(1) per row.
        $clicksByContact = DB::table('wa_link_clicks')
            ->where('broadcast_id', $broadcast->id)
            ->where('clicks', '>', 0)
            ->selectRaw('contact_id, SUM(clicks) as click_count, MAX(last_click_at) as last_click_at')
            ->groupBy('contact_id')
            ->get()
            ->keyBy('contact_id');

        // Bulk-hydrate the involved contacts in one query so the
        // encrypted-at-rest fields decrypt. Keyed by id for fast
        // lookup inside the map below.
        // SECURITY: scope hydration to the current workspace so a broadcast
        // that somehow carries a foreign contact id (legacy rows created
        // before the store() scoping) never decrypts/renders another tenant's
        // name + phone. Same-workspace recipients hydrate unchanged; foreign
        // ids simply fall through to the "Contact #id" placeholder below.
        $contactMap = \App\Models\Contact::query()->forCurrentWorkspace()
            ->whereIn('id', $pivot->pluck('contact_id')->unique())
            ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
            ->keyBy('id');

        $recipients = $pivot->map(function ($r) use ($contactMap, $clicksByContact) {
            $c = $contactMap->get($r->contact_id);
            $name = $c
                ? (trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: ('Contact #' . $r->contact_id))
                : ('Contact #' . $r->contact_id);
            $cleanCc     = $c ? preg_replace('/\D+/', '', (string) ($c->country_code ?? '')) : '';
            $cleanMobile = $c ? (string) ($c->mobile ?? '') : '';
            $phone = $cleanMobile === ''
                ? ''
                : ($cleanCc !== '' && strpos(preg_replace('/\D+/', '', $cleanMobile), $cleanCc) !== 0
                    ? '+' . $cleanCc . ' ' . $cleanMobile
                    : $cleanMobile);
            $click = $clicksByContact->get($r->contact_id);
            return [
                'contact_id'    => $r->contact_id,
                'name'          => $name,
                'phone'         => $phone,
                'status'        => $r->status ?: 'pending',
                'error'         => $r->error_message,
                'wa_message_id' => $r->whatsapp_message_id,
                'queued_at'     => $r->queued_at,
                'sent_at'       => $r->sent_at,
                'delivered_at'  => $r->delivered_at,
                'read_at'       => $r->read_at,
                'failed_at'     => $r->failed_at ?? null,
                'click_count'   => $click ? (int) $click->click_count : 0,
                'last_click_at' => $click->last_click_at ?? null,
            ];
        });

        // Roll up the recipient rows into a single header/funnel
        // structure. Hierarchical so the higher tiers subsume the
        // lower ones (read → delivered → sent), matching the global
        // /broadcasts stat strip.
        $statusCounts = [
            'pending'   => 0,
            'sent'      => 0,
            'delivered' => 0,
            'read'      => 0,
            'failed'    => 0,
        ];
        foreach ($recipients as $row) {
            $key = $row['status'];
            if (isset($statusCounts[$key])) $statusCounts[$key]++;
        }
        $total      = $recipients->count();
        $sent       = $statusCounts['sent'] + $statusCounts['delivered'] + $statusCounts['read'];
        $delivered  = $statusCounts['delivered'] + $statusCounts['read'];
        $read       = $statusCounts['read'];
        $failed     = $statusCounts['failed'];
        $queued     = $statusCounts['pending'];
        // Clicked = unique contacts who tapped any tracked URL. Sourced
        // from the per-contact group above (already deduped at SQL).
        $clicked    = $clicksByContact->count();

        $pct = fn ($n, $base) => $base > 0 ? round(($n / $base) * 100, 1) : 0;
        $header = [
            'total'     => $total,
            'sent'      => $sent,
            'delivered' => $delivered,
            'read'      => $read,
            'failed'    => $failed,
            'queued'    => $queued,
            'clicked'   => $clicked,
            'sent_pct'      => $pct($sent,      $total),
            'delivered_pct' => $pct($delivered, $total),
            'read_pct'      => $pct($read,      max($delivered, 1)),
            'failed_pct'    => $pct($failed,    $total),
            'clicked_pct'   => $pct($clicked,   max($delivered, 1)),
        ];

        // Template body + media-path preview. The /wa-campaigns
        // detail shows the rendered message; broadcast operators
        // want the same so they can sanity-check "is this the body
        // that actually went out?".
        $tpl = $broadcast->template_id ? \App\Models\WaTemplate::find($broadcast->template_id) : null;
        $templatePreview = $tpl ? [
            'id'      => $tpl->id,
            'name'    => (string) $tpl->template_name,
            'header'  => (string) ($tpl->header ?? ''),
            'body'    => (string) $tpl->template_body,
            'footer'  => (string) ($tpl->footer ?? ''),
            'buttons' => is_array($tpl->buttons) ? $tpl->buttons : [],
            'media'   => (string) ($tpl->attachment_type ?? ''),
            'category'=> (string) ($tpl->meta_category ?? $tpl->category ?? 'utility'),
        ] : null;

        // Device summary so the page identifies which paired number
        // actually carried the send.
        $device = $broadcast->device_id ? \App\Models\Device::find($broadcast->device_id) : null;
        $deviceLabel = $device
            ? (trim((string) $device->device_name) ?: ('Device #' . $device->id))
            : null;
        $devicePhone = $device
            ? trim('+' . ltrim((string) $device->country_code, '+') . ' ' . $device->phone_number)
            : null;

        // Chart data series. Two ApexCharts on the overview tab:
        //
        //   1. Delivery curve (area chart) — buckets of recipients
        //      that crossed each milestone (sent / delivered / read)
        //      by hour-since-broadcast-creation. Buckets cap at 24h
        //      after the first message; broadcasts older than that
        //      cap at the broadcast's end timestamp so the line
        //      doesn't show empty hours forever.
        //
        //   2. Status donut — the same 5 buckets the funnel uses,
        //      good for at-a-glance success / failure split.
        //
        // Failure reason histogram (also exposed under the Failures
        // tab) groups the error_message strings so the operator can
        // spot recurring "Not on WhatsApp" / quota errors / etc.
        // Bucket the delivery curve in the broadcast's OWN timezone so the
        // hour labels on the x-axis read local time, not UTC (India was 5h30
        // off). Every timestamp is converted to $bcastTz before startOfHour().
        $bcastTz     = $broadcast->timezone ?: wa_tz();
        $createdTs   = $broadcast->created_at ? $broadcast->created_at->copy()->setTimezone($bcastTz)->startOfHour() : null;
        $deliverySeries = ['categories' => [], 'sent' => [], 'delivered' => [], 'read' => []];
        if ($createdTs) {
            $latest = $recipients
                ->flatMap(fn ($r) => [$r['sent_at'], $r['delivered_at'], $r['read_at']])
                ->filter()
                ->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->setTimezone($bcastTz)->startOfHour())
                ->max();
            $end = $latest ?: $createdTs->copy()->addHour();
            $end = $end->lt($createdTs) ? $createdTs : $end;
            $hours = min(24, max(1, $createdTs->diffInHours($end) + 1));

            $buckets = [];
            $cursor = $createdTs->copy();
            for ($i = 0; $i < $hours; $i++) {
                $key = $cursor->format('Y-m-d H:00');
                $buckets[$key] = ['sent' => 0, 'delivered' => 0, 'read' => 0];
                $deliverySeries['categories'][] = $cursor->format('H:00');
                $cursor->addHour();
            }
            foreach ($recipients as $r) {
                foreach (['sent', 'delivered', 'read'] as $col) {
                    $ts = $r[$col . '_at'] ?? null;
                    if (!$ts) continue;
                    $key = \Illuminate\Support\Carbon::parse($ts)->setTimezone($bcastTz)->startOfHour()->format('Y-m-d H:00');
                    if (isset($buckets[$key])) $buckets[$key][$col]++;
                }
            }
            foreach ($buckets as $b) {
                $deliverySeries['sent'][]      = $b['sent'];
                $deliverySeries['delivered'][] = $b['delivered'];
                $deliverySeries['read'][]      = $b['read'];
            }
        }

        $statusSeries = [
            'labels' => ['Sent', 'Delivered', 'Read', 'Queued', 'Failed'],
            'series' => [
                $header['sent'] - $header['delivered'], // only "ever sent but not yet delivered"
                $header['delivered'] - $header['read'],
                $header['read'],
                $header['queued'],
                $header['failed'],
            ],
        ];

        // Failure reasons grouped — top 8, rest under "Other".
        $failureRows = $recipients->where('status', 'failed')->values();
        $failureCounts = [];
        foreach ($failureRows as $row) {
            $key = $row['error'] ? trim((string) $row['error']) : 'Unknown';
            $failureCounts[$key] = ($failureCounts[$key] ?? 0) + 1;
        }
        arsort($failureCounts);
        $topFailures = array_slice($failureCounts, 0, 8, true);
        $otherTotal  = array_sum(array_slice($failureCounts, 8, null, true));
        if ($otherTotal > 0) $topFailures['Other'] = $otherTotal;
        $failuresChart = [
            'labels' => array_keys($topFailures),
            'series' => array_values($topFailures),
        ];

        $events = [
            ['label' => 'Created',  'at' => $broadcast->created_at?->toIso8601String()],
            ['label' => 'Scheduled at', 'at' => $broadcast->scheduled_at?->toIso8601String()],
            ['label' => 'Status',    'value' => ucfirst(str_replace('_', ' ', (string) $broadcast->status))],
            ['label' => 'Completed', 'at' => $broadcast->completed_at?->toIso8601String()],
        ];

        $chartData = [
            'delivery' => $deliverySeries,
            'status'   => $statusSeries,
            'failures' => $failuresChart,
        ];

        return view('user.broadcasts.show', compact(
            'broadcast', 'recipients', 'header', 'templatePreview',
            'deviceLabel', 'devicePhone', 'chartData', 'failureRows', 'events'
        ));
    }

    // -----------------------------------------------------------------
    // Mutations
    // -----------------------------------------------------------------

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Plan: feature flag + numeric cap.
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'broadcast');
        // Plan limit counts broadcasts in the CURRENT workspace only,
        // not the user's aggregate across all their workspaces. The
        // 2026_05_19 migration added `workspace_id` to broadcasts and
        // backfilled from the owner's current_workspace_id.
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'broadcast_limit',
            \App\Models\Broadcast::where('workspace_id', $request->user()->current_workspace_id)->count(),
        );

        $data = $request->validate([
            'broadcast_name' => 'required|string|max:255',
            'template_id'    => 'nullable|integer|exists:wa_templates,id',
            // Send-time template overrides (JSON from the mapping panel).
            // Shape is enforced by TemplateOverrideResolver::sanitize().
            'template_overrides' => 'nullable|string|max:65000',
            'contacts'       => 'nullable|array',
            'contacts.*'     => 'integer|exists:contacts,id',
            'groups'         => 'nullable|array',
            // Tag audience — send to everyone carrying these tags.
            'tags'           => 'nullable|array',
            'tags.*'         => 'integer',
            'groups.*'       => 'integer|exists:contact_groups,id',
            // Multi-engine sender picker: `device_ids[]` now carries the
            // composite `engine:id` key (string) for each ticked sender so
            // a workspace running 2+ engines at once can fan a single
            // broadcast across, say, an Unofficial API phone AND a WABA
            // number. A bare integer id is still accepted (legacy single-
            // engine forms / un-migrated callers) — senderForKey() infers
            // its engine from the workspace default. The legacy single
            // `device_id` (bare int) remains valid as a fallback.
            'device_id'      => 'nullable',
            'device_ids'     => 'nullable|array',
            'device_ids.*'   => 'string|max:64',
            // Per-sender weights for the audience split. Keys are now the
            // same composite `engine:id` keys (or legacy bare ids), values
            // are positive numbers. Missing keys or non-positive values
            // default to 1 (equal weight).
            'device_share'   => 'nullable|array',
            'device_share.*' => 'nullable|numeric|min:0|max:1000',
            'schedule_type'  => 'required|in:now,later',
            'send_date'      => 'required_if:schedule_type,later|nullable|date_format:Y-m-d',
            'send_time'      => 'required_if:schedule_type,later|nullable',
            'timezone'       => 'nullable|string|max:64',
        ]);

        // Resolve picked senders. `device_ids[]` (or the legacy single
        // `device_id`) now carry the unified picker's composite `engine:id`
        // keys; each is validated via senderForKey() against the senders
        // this workspace can actually use right now, so a forged/stale key
        // — or one naming a sender on an engine the workspace can't send
        // through — is rejected. A bare integer is still accepted and its
        // engine inferred from the workspace default (legacy single-engine
        // forms + any un-migrated caller stay byte-identical).
        //
        // Each resolved sender is normalised to a plain descriptor
        //   ['key','id','engine','label']
        // so the weight map (keyed by composite key), the per-sender
        // fan-out, and the per-row provider stamp can all read from one
        // shape regardless of engine (Device vs WaProviderConfig).
        $wsId = (int) $request->user()->current_workspace_id;
        $requestedKeys = !empty($data['device_ids'])
            ? array_values(array_unique(array_map('strval', $data['device_ids'])))
            : (!empty($data['device_id']) ? [(string) $data['device_id']] : []);
        $devices = collect($requestedKeys)
            ->map(function ($key) use ($wsId) {
                $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $key);
                if (!$picked) return null;
                return [
                    'key'    => (string) $picked['key'],
                    'id'     => (int) $picked['id'],
                    'engine' => (string) $picked['engine'],
                    'label'  => (string) $picked['label'],
                ];
            })
            ->filter()
            ->unique('key')
            ->values();

        // Resolve recipients — direct contact ids + group members
        // (encrypted JSON column on contacts, so we hydrate then
        // filter just like ChatController@createConversation does).
        // SECURITY: resolve BOTH the direct contact ids and the group
        // expansion through the caller's workspace only. Validation upstream
        // (contacts.* => exists:contacts,id) enforces GLOBAL existence, not
        // ownership — so a forged request naming another tenant's sequential
        // contact ids would otherwise be attached to this broadcast and their
        // decrypted PII rendered/sent. forCurrentWorkspace() drops any foreign
        // id; same-workspace ids pass through byte-identically.
        $rawContactIds = collect($request->input('contacts', []))->map(fn ($v) => (int) $v);
        $contactIds = $rawContactIds->isEmpty()
            ? collect()
            : Contact::query()->forCurrentWorkspace()
                ->whereIn('id', $rawContactIds->all())
                ->pluck('id')->map(fn ($v) => (int) $v);
        $groupIds   = collect($request->input('groups',   []))->map(fn ($v) => (string) $v);
        if ($groupIds->isNotEmpty()) {
            $extra = Contact::query()->forCurrentWorkspace()->get(['id', 'contact_group'])
                ->filter(function ($c) use ($groupIds) {
                    $list = is_array($c->contact_group) ? $c->contact_group : [];
                    foreach ($list as $gid) {
                        if ($groupIds->contains((string) $gid)) return true;
                    }
                    return false;
                })
                ->pluck('id');
            $contactIds = $contactIds->merge($extra)->unique()->values();
        }

        // Tag audience — same contract as campaigns. Tag ids are filtered to
        // THIS workspace before the contact query, so a forged id can't pull
        // another tenant's contacts.
        $tagIds = collect($request->input('tags', []))
            ->map(fn ($v) => (int) $v)->filter()->unique();
        if ($tagIds->isNotEmpty()) {
            $safeTagIds = \App\Models\Tag::query()
                ->where('workspace_id', (int) ($request->user()->current_workspace_id ?? 0))
                ->whereIn('id', $tagIds->all())
                ->pluck('id');
            if ($safeTagIds->isNotEmpty()) {
                $tagged = Contact::query()->forCurrentWorkspace()
                    ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $safeTagIds->all()))
                    ->pluck('id');
                $contactIds = $contactIds->merge($tagged)->unique()->values();
            }
        }

        // Drop opted-out contacts (STOP keyword / manual unsubscribe) before
        // they ever enter the broadcast — never message someone who stopped.
        if ($contactIds->isNotEmpty()) {
            $optedOut = Contact::query()->whereIn('id', $contactIds)
                ->where('is_unsubscribed', true)->pluck('id');
            if ($optedOut->isNotEmpty()) {
                $contactIds = $contactIds->reject(fn ($id) => $optedOut->contains((int) $id))->values();
            }
        }

        if ($contactIds->isEmpty()) {
            return back()->withInput()->with('error', 'Select at least one contact or group (opted-out contacts are skipped automatically).');
        }

        // WABA template ban-prevention gates — refuse the broadcast
        // BEFORE we burn quota or risk a quality penalty. These mirror
        // TemplateSender::send() so the same rules apply whether the
        // template is sent 1:1 or in a broadcast.
        //
        // Only enforced when the workspace is on WABA + templates v2
        // is on + the picked template was submitted to Meta (has a
        // meta_template_id). Legacy approved-locally templates skip
        // the gate so existing customer flows don't break.
        if (!empty($data['template_id'])) {
            // SECURITY: scope the template lookup to the caller's workspace.
            // Validation only enforces global existence (exists:wa_templates,id),
            // so an unscoped find() would let a tenant broadcast another
            // workspace's confidential template body. A foreign id resolves to
            // null and is rejected below like a missing template.
            $tpl = \App\Models\WaTemplate::query()->forCurrentWorkspace()
                ->whereKey((int) $data['template_id'])->first();
            if (!$tpl) {
                return back()->withInput()->with('error', 'Selected template was not found in your workspace.');
            }

            // Auth/OTP templates can't be broadcast. Each recipient
            // needs a unique code that the merchant's own backend has
            // to know about to verify on submission. Broadcasting
            // mints random codes per recipient that nobody can ever
            // verify against → useless to recipients + bad UX. Force
            // 1:1 sends via the transactional API for auth templates.
            if ($tpl && $tpl->template_type === 'auth') {
                return back()->withInput()->with('error',
                    'Authentication (OTP) templates cannot be broadcast — each recipient needs a unique verifiable code. Send them 1:1 from your backend using the transactional template send endpoint instead.');
            }

            // HTTPS + public-host media reachability check runs for ALL
            // engines whenever the template has a media header. Meta's
            // fetcher rejects #131053 on http://, private IPs (10/8,
            // 172.16/12, 192.168/16, 127/8), .local, .test domains —
            // Baileys + Twilio media downloads have the same hygiene
            // requirement (recipient WhatsApp clients won't expand
            // previews from non-public URLs). This check used to live
            // inside the WABA v2 gate below, so Baileys broadcasts
            // could silently 404 every recipient on a localhost APP_URL.
            if ($tpl) {
                $needsMedia = !empty($tpl->attachment_type)
                    && !in_array(strtoupper((string) $tpl->attachment_type), ['NONE', 'TEXT', 'LOCATION'], true);
                if ($needsMedia && !empty($tpl->attachment_file)) {
                    $mediaCheckUrl = media_url($tpl->attachment_file);
                    $mediaCheckErr = $this->mediaUrlReachableForMeta($mediaCheckUrl);
                    if ($mediaCheckErr) {
                        Log::warning('[BCAST] refused — media URL not reachable', [
                            'template_id' => $tpl->id,
                            'url'         => $mediaCheckUrl,
                            'reason'      => $mediaCheckErr,
                        ]);
                        return back()->withInput()->with('error', 'Cannot broadcast this template: ' . $mediaCheckErr);
                    }
                }
            }

            if ($tpl && $tpl->meta_template_id
                && \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {

                $reasons = [];
                if (strtoupper((string) $tpl->meta_status) !== 'APPROVED') {
                    $reasons[] = "Template is not approved by Meta yet (status: {$tpl->meta_status}). Wait for approval.";
                }
                if ($tpl->paused_until && $tpl->paused_until->isFuture()) {
                    $reasons[] = 'Template is paused until ' . $tpl->paused_until->format('Y-m-d H:i') . ' due to negative customer feedback. Sending now would worsen the quality score.';
                }
                // Quality gate — match TemplateSender (the campaign path): ONLY
                // refuse on a RED score. UNKNOWN means Meta hasn't rated a
                // brand-new / rarely-sent approved template yet (e.g. hello_world)
                // — that is NOT poor quality, and blocking UNKNOWN/YELLOW stopped
                // legitimate templates from ever going out. Campaigns already send
                // these fine, so broadcasts must behave the same.
                $score = strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN'));
                if ($score === 'RED') {
                    $reasons[] = 'Template quality score is RED — sending now would accelerate the quality drop and risk a Meta ban.';
                }

                if (!empty($reasons)) {
                    Log::warning('[BCAST] refused — template guardrails failed', [
                        'template_id' => $tpl->id,
                        'reasons'     => $reasons,
                    ]);
                    return back()->withInput()->with('error', 'Cannot broadcast this template: ' . implode(' ', $reasons));
                }
            }
        }

        $isNow = $data['schedule_type'] === 'now';
        $scheduledAt = $isNow
            ? now()
            : Carbon::parse($data['send_date'] . ' ' . $data['send_time']);

        // Fan out into one Broadcast per picked device. With zero
        // devices selected we still create a single device-less
        // broadcast (legacy "pick first connected" path). With 2+
        // devices we create N broadcasts AND split the contact list
        // by the operator-supplied share weights — equal weights =
        // even split, asymmetric weights (e.g. 7/3) bias accordingly.
        $sliceDevices = $devices->isEmpty() ? collect([null]) : $devices;
        $deviceCount  = $sliceDevices->count();

        // Normalise the per-sender weights. The share map is now keyed by
        // the composite `engine:id` key (matching the blade's
        // name="device_share[{key}]"); a legacy bare-int key still resolves
        // because device_ids[] carrying a bare int resolves to the same id
        // on the default engine. Missing keys / non-positive values default
        // to 1 so an operator who ticks senders without filling in weights
        // gets a clean even split.
        $shareInput = is_array($data['device_share'] ?? null) ? $data['device_share'] : [];
        $weights = [];
        foreach ($sliceDevices->values() as $idx => $dev) {
            // $dev is the normalised descriptor array (or null for the
            // device-less legacy slice). Look the weight up by composite
            // key, falling back to the bare id for an un-migrated submission.
            $w = 1.0;
            if (is_array($dev)) {
                $raw = $shareInput[$dev['key']] ?? $shareInput[$dev['id']] ?? 1;
                $w   = (float) $raw;
            }
            $weights[$idx] = $w > 0 ? $w : 1.0;
        }
        $sumWeight = array_sum($weights);
        if ($sumWeight <= 0) {
            // All zero somehow — fall back to equal weights so the
            // audience still goes out instead of vanishing.
            $weights   = array_fill(0, $deviceCount, 1.0);
            $sumWeight = (float) $deviceCount;
        }

        // Compute integer bucket sizes whose sum equals the total
        // contact count. Floor each share, then distribute the
        // remainder (caused by rounding) to the buckets with the
        // highest fractional parts — keeps the split within 1 of
        // the exact weighted ideal.
        $total      = $contactIds->count();
        $sizes      = array_fill(0, $deviceCount, 0);
        $fractions  = [];
        $assigned   = 0;
        foreach ($weights as $i => $w) {
            $exact = ($w / $sumWeight) * $total;
            $sizes[$i]     = (int) floor($exact);
            $fractions[$i] = $exact - $sizes[$i];
            $assigned     += $sizes[$i];
        }
        $remainder = $total - $assigned;
        if ($remainder > 0) {
            arsort($fractions); // largest fractional parts first
            foreach (array_keys($fractions) as $i) {
                if ($remainder === 0) break;
                $sizes[$i]++;
                $remainder--;
            }
        }

        // Build the contact buckets — sequential slices so each
        // customer lands in exactly one bucket and no duplicate is
        // ever attached. (Order doesn't matter for broadcasts; the
        // operator only sees totals per device.)
        $buckets    = array_fill(0, $deviceCount, []);
        $contactArr = array_values($contactIds->all());
        $cursor     = 0;
        foreach ($sizes as $i => $size) {
            if ($size > 0) {
                $buckets[$i] = array_slice($contactArr, $cursor, $size);
                $cursor += $size;
            }
        }

        Log::info('[BCAST] store() audience split computed', [
            'workspace_id'    => $request->user()->current_workspace_id,
            'contacts_total'  => $total,
            'devices_count'   => $deviceCount,
            'weights'         => $weights,
            'bucket_sizes'    => $sizes,
            'is_now'          => $isNow,
            'scheduled_at'    => $scheduledAt?->toIso8601String(),
            'template_id'     => $data['template_id'] ?? null,
        ]);

        // WhatsApp Warmer — govern warming Unofficial numbers on broadcasts too
        // (parity with campaigns; closes the bulk-send bypass), for BOTH immediate
        // and future-scheduled sends. A number outside its active hours (immediate
        // only), out of budget, or whose audience won't fit in the SEND DATE's
        // ramped budget is HELD with a clear message — we can't split a single Node
        // batch, so a too-big blast is blocked rather than silently exceeding the
        // warm-up. Passing slices RESERVE their batch against the send date's
        // ledger so the per-number budget stays accurate across every surface and
        // every future day.
        $warmer      = app(\App\Services\WarmerService::class);
        $warmHolds   = [];
        $warmReserve = [];
        $warmDate    = $isNow ? null : ($scheduledAt ? $scheduledAt->toDateString() : null);
        $whenLabel   = $isNow ? 'today' : ($warmDate ?: 'that day');
        foreach ($sliceDevices->values() as $idx => $dev) {
            if (!is_array($dev)) continue;
            $n = count($buckets[$idx] ?? []);
            if ($n <= 0) continue;
            // Engine-aware: WABA/Twilio warm their wa_provider_configs row,
            // Unofficial its Device. The warmer paces volume per number on every
            // engine (Meta still enforces tiers; this protects the quality rating).
            $eng  = (string) ($dev['engine'] ?? 'baileys');
            $wdev = in_array($eng, ['waba', 'twilio'], true)
                ? \App\Models\WaProviderConfig::find($dev['id'])
                : \App\Models\Device::find($dev['id']);
            if (!$wdev || !$warmer->enabled($wdev)) continue;
            $label = trim((string) ($dev['label'] ?? '')) ?: ('Device #' . $dev['id']);
            // Immediate sends must also be inside the number's active hours.
            if ($isNow && !$warmer->withinActiveHours($wdev)) {
                $warmHolds[] = "$label is warming and outside its active hours right now.";
                continue;
            }
            $remaining = $warmer->remainingFor($wdev, $warmDate);
            if ($remaining <= 0) {
                $warmHolds[] = "$label is warming — its $whenLabel warm-up budget is already used up.";
                continue;
            }
            if ($n > $remaining) {
                $warmHolds[] = "$label is warming — only $remaining of its "
                    . $warmer->dailyBudgetFor($wdev, $warmDate) . "-message budget for $whenLabel remains, but this send has $n recipients. "
                    . ($isNow ? 'Reduce the audience or try again tomorrow as the budget ramps up.' : 'Reduce the audience or pick a later date.');
                continue;
            }
            $warmReserve[] = ['device' => $wdev, 'n' => $n, 'date' => $warmDate];
        }
        if (!empty($warmHolds)) {
            return back()->withInput()->with('error', 'Held by WhatsApp Warmer: ' . implode(' ', $warmHolds)
                . ' You can adjust these limits at /warmer.');
        }
        foreach ($warmReserve as $r) { $warmer->recordSendsFor($r['device'], $r['n'], $r['date']); }

        $createdRows = DB::transaction(function () use ($data, $isNow, $scheduledAt, $sliceDevices, $buckets, $deviceCount) {
            $created = [];
            foreach ($sliceDevices->values() as $idx => $dev) {
                $sliceContacts = $buckets[$idx] ?? [];
                // Skip empty buckets — possible when contacts < senders
                // (e.g. 1 contact + 2 senders = bucket[1] is empty).
                if (empty($sliceContacts) && $dev !== null && $deviceCount > 1) continue;

                $suffix = ($deviceCount > 1 && $dev)
                    ? ' · ' . (trim((string) ($dev['label'] ?? '')) ?: ('Device #' . $dev['id']))
                    : '';
                $b = Broadcast::create([
                    'user_id'          => Auth::id(),
                    'workspace_id'     => Auth::user()->current_workspace_id,
                    // Stamp the CHOSEN engine for this slice (not the
                    // workspace default) so a fan-out across mixed engines
                    // routes each slice through the right provider. The
                    // device-less legacy slice falls back to the workspace
                    // default engine (model also auto-stamps when empty).
                    'provider'         => is_array($dev)
                        ? $dev['engine']
                        : \App\Services\WorkspaceEngine::for(Auth::user()->current_workspace_id),
                    'device_id'        => is_array($dev) ? $dev['id'] : null,
                    'template_id'      => $data['template_id']    ?? null,
                    // Same overrides on every slice of a fan-out — the
                    // operator filled them in once for the whole broadcast.
                    'template_overrides' => \App\Services\TemplateOverrideResolver::sanitize(
                        $data['template_overrides'] ?? null
                    ),
                    'name'             => $data['broadcast_name'] . $suffix,
                    'timezone'         => $data['timezone']       ?? 'UTC',
                    'status'           => $isNow ? 'processing' : 'scheduled',
                    'scheduled_at'     => $scheduledAt,
                    'total_recipients' => count($sliceContacts),
                ]);
                foreach ($sliceContacts as $cid) {
                    $b->contacts()->attach($cid, [
                        'status'     => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $created[] = $b;
            }
            return $created;
        });

        Log::info('[BCAST] store() rows created', [
            'count'         => count($createdRows),
            'broadcast_ids' => array_map(fn ($b) => $b->id, $createdRows),
            'per_device'    => array_map(fn ($b) => ['id' => $b->id, 'device_id' => $b->device_id, 'total' => $b->total_recipients], $createdRows),
        ]);

        // Hand every created broadcast off to the Node bridge —
        // earlier this only dispatched the first row, leaving N-1
        // broadcasts stuck at status=processing with no Node side
        // record. Looping fixes that and lets each device's slice
        // dispatch in parallel.
        foreach ($createdRows as $b) {
            $this->dispatchToBridge($b, $isNow);
        }
        Log::info('[BCAST] store() dispatch loop finished', [
            'count' => count($createdRows),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'             => true,
                'broadcast_id'   => $createdRows[0]?->id,
                'broadcast_ids'  => array_map(fn ($b) => $b->id, $createdRows),
                'devices_count'  => $deviceCount,
            ]);
        }
        $msg = count($createdRows) > 1
            ? count($createdRows) . ' broadcasts ' . ($isNow ? 'sent' : 'scheduled') . ' across ' . $deviceCount . ' devices.'
            : 'Broadcast "' . ($createdRows[0]?->name ?? $data['broadcast_name']) . '" ' . ($isNow ? 'sent' : 'scheduled') . '.';
        return redirect()->route('user.broadcasts.index')->with('status', $msg);
    }

    /**
     * Node → Laravel webhook for per-recipient delivery status.
     * Hit from `broadcastService.updateMessageStatus()` once for every
     * contact transition (sent / failed / delivered / read). Updates
     * the `broadcast_contacts` pivot row so the operator's index page
     * counters reflect Node's real progress instead of staying at zero.
     *
     * Auth via the same X-Node-Token header the other Node→Laravel
     * webhooks use (defined in env NODE_WEBHOOK_TOKEN). No session.
     */
    public function nodeMessageStatus(Request $request): JsonResponse
    {
        // Refuse when token isn't configured — an empty token was
        // previously treated as "allow everyone".
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'broadcast_id'        => 'required|integer',
            'contact_id'          => 'required|integer',
            'status'              => 'required|in:pending,sent,delivered,read,failed',
            'error_message'       => 'nullable|string|max:1024',
            'whatsapp_message_id' => 'nullable|string|max:191',
            'sent_at'             => 'nullable|date',
            'delivered_at'        => 'nullable|date',
            'read_at'             => 'nullable|date',
        ]);

        $b = Broadcast::find($data['broadcast_id']);
        if (!$b) {
            return response()->json(['ok' => false, 'message' => 'broadcast not found'], 404);
        }

        // Update the pivot row for this contact. Keep the existing
        // pivot fields and only overwrite what the callback gives us.
        $updates = ['status' => $data['status']];
        if (!empty($data['error_message']))       $updates['error_message']       = $data['error_message'];
        if (!empty($data['whatsapp_message_id'])) $updates['whatsapp_message_id'] = $data['whatsapp_message_id'];
        // Node ships ISO 8601 like `2026-05-16T09:27:01.807Z` —
        // MySQL DATETIME columns reject that format (SQLSTATE 22007
        // "Incorrect datetime value"). Parse through Carbon so we
        // store the canonical `Y-m-d H:i:s` form Laravel expects.
        foreach (['sent_at', 'delivered_at', 'read_at'] as $col) {
            if (!empty($data[$col])) {
                try {
                    $updates[$col] = \Illuminate\Support\Carbon::parse($data[$col])->toDateTimeString();
                } catch (\Throwable $e) {
                    // Bad timestamp from Node — skip the column rather
                    // than fail the whole row update. The status flip
                    // is the important bit; sent_at is metadata.
                }
            }
        }
        $updates['updated_at'] = now();

        $b->contacts()->newPivotStatement()
            ->where('broadcast_id', $b->id)
            ->where('contact_id',   $data['contact_id'])
            ->update($updates);

        // Roll the parent broadcast's counters so the /broadcasts index
        // doesn't have to re-aggregate the pivot table on every poll.
        // Idempotent against duplicate webhooks (Node sometimes re-fires
        // on transient errors) because it recounts rather than increments.
        //
        // This used to set success_count/fail_count ONLY, which meant an
        // Unofficial or Twilio broadcast reported Delivered 0 / Read 0
        // permanently: Broadcast::getStatusCounts takes its fast path as
        // soon as success_count > 0, so the live-count fallback that would
        // have shown the real numbers never ran. Both engines report
        // delivered/read here — the counters just weren't being written.
        $b->recomputeAggregates();

        // Mirror the send into the team-inbox thread. This is the ONLY point
        // where Unofficial (Baileys) and Twilio report a per-recipient send —
        // they dispatch through Node, so the controller never loops recipients
        // the way the synchronous WABA path does. Without this hook a template
        // sent on those engines was invisible in the inbox while the identical
        // WABA send showed up fine.
        //
        // Runs on 'sent' only: delivered/read are later status flips for a
        // bubble that already exists, and mirroring those would stack copies.
        // InboxMirror additionally de-dupes on wa_message_id, so a Node retry
        // or an overlapping Message-observer write cannot double-post.
        // Mirror on ANY delivery-positive status, not just 'sent'.
        //
        // Relying on the single 'sent' callback made the mirror fragile: if
        // that one POST was missed, raced, or hit a stale worker, the message
        // was lost from the inbox forever with no way to recover — every later
        // callback for the same message was ignored. Now 'delivered' and 'read'
        // can also create the bubble, so a dropped 'sent' self-heals within
        // seconds. The wa_message_id de-dupe in InboxMirror guarantees the
        // three statuses still produce exactly ONE bubble.
        if (in_array($data['status'], ['sent', 'delivered', 'read'], true)) {
            try {
                $contact = \App\Models\Contact::find($data['contact_id']);
                $phone   = preg_replace('/\D+/', '', (string) ($contact->mobile ?? ''));
                if ($phone !== '') {
                    $tpl  = $b->template_id ? \App\Models\WaTemplate::find($b->template_id) : null;
                    $body = $tpl
                        ? \App\Services\Inbox\InboxMirror::readableTemplateBody(
                            \App\Services\Whatsapp\TemplateDataBuilder::build($tpl, (int) $b->workspace_id)
                        )
                        : (string) ($b->temp_caption ?? '');



                    // Resolve {{1}}/{{2}} against THIS contact before mirroring.
                    // TemplateDataBuilder returns the template as written, so
                    // without this the inbox bubble shows raw placeholders while
                    // the customer received the substituted text — the exact
                    // mismatch this mirror exists to prevent.
                    if ($tpl && $body !== '' && str_contains($body, '{{')) {
                        try {
                            $row = $contact->toArray();
                            $row['phone'] = (string) ($contact->mobile ?? $phone);
                            $vars = \App\Services\TemplateOverrideResolver::positional(
                                app(BroadcastsController::class)->varsForRecipient($tpl, $row, (int) $b->workspace_id, null)
                            );
                            $body = preg_replace_callback(
                                \App\Services\TemplateOverrideResolver::TOKEN_RE,
                                function ($m) use ($vars, $tpl) {
                                    $k = trim($m[1]);
                                    $v = (string) ($vars[$k] ?? '');
                                    if ($v !== '') return $v;
                                    // Same fallback the send path applies: a blank
                                    // NAME slot becomes "there" so the bubble reads
                                    // "Hi there," exactly as the customer received
                                    // it — not "Hi ,".
                                    $key = \App\Services\TemplateOverrideResolver::normalizeKey(
                                        \App\Services\TemplateOverrideResolver::flattenMap(
                                            is_array($tpl->variable_map) ? $tpl->variable_map : []
                                        )[$k] ?? $k
                                    );
                                    return in_array($key, ['name','first_name','full_name'], true) ? 'there' : '';
                                },
                                $body
                            );
                            $body = trim(preg_replace('/[ 	]{2,}/', ' ', $body));
                        } catch (\Throwable $e) { /* keep unresolved rather than drop */ }
                    }
                    // An empty body means the template row is blank or the
                    // broadcast carried no text — posting it would put a
                    // silent, contentless bubble in the customer's thread.
                    // Skip instead; the send itself is still recorded on the
                    // broadcast/campaign row.
                    if (trim($body) === '') {
                        \Log::info('[MIRROR] skipped empty body', ['to' => $phone]);
                    } else {
                    app(\App\Services\Inbox\InboxMirror::class)->appendOutboundToOpenConversation(
                        (int) $b->workspace_id,
                        $phone,
                        $body,
                        $data['whatsapp_message_id'] ?? null,
                        $b->provider ?: null,
                        array_filter([
                            'type'          => $tpl ? 'template' : 'text',
                            // Buttons so the inbox bubble renders the same
                            // CTAs the customer saw on WhatsApp.
                            'buttons'       => ($tpl && is_array($tpl->buttons) && $tpl->buttons) ? $tpl->buttons : null,
                            'template_name' => $tpl?->template_name,
                            'broadcast_id'  => $b->id,
                            'source'        => 'broadcast',
                            // Must match the key the send loop used.
                            'src_key'       => 'broadcast:' . $b->id . ':contact:' . $data['contact_id'],
                        ], fn ($v) => $v !== null),
                    );
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort: the send already succeeded — a mirror failure
                // must not turn a delivered message into a 500 for Node.
                \Log::warning('[BCAST-MIRROR] failed: ' . $e->getMessage());
            }
        }

        // Fire outbound `message_sent` / `message_failed` webhook so the
        // customer's external systems get the event in real-time. Meta's
        // delivered/read webhooks fire LATER (from /webhooks/whatsapp/
        // inbound → applyStatus); this one fires the moment Node hands
        // the message off to Meta.
        $event = match ($data['status']) {
            'sent'      => 'message_sent',
            'delivered' => 'message_delivered',
            'read'      => 'message_read',
            'failed'    => 'message_failed',
            default     => null,
        };
        if ($event) {
            \App\Services\WebhookService::dispatch($event, [
                'workspace_id'  => $b->workspace_id,
                'broadcast_id'  => $b->id,
                'broadcast_name'=> $b->name,
                'template_id'   => $b->template_id,
                'contact_id'    => $data['contact_id'],
                'wamid'         => $data['whatsapp_message_id'] ?? null,
                'status'        => $data['status'],
                'error_reason'  => $data['error_message'] ?? null,
                'timestamp'     => now()->timestamp,
                'aggregate'     => [
                    'sent'      => (int) $b->success_count,
                    'delivered' => (int) $b->delivered_count,
                    'read'      => (int) $b->read_count,
                    'failed'    => (int) $b->fail_count,
                    'clicked'   => (int) $b->clicked_count,
                    'total'     => (int) $b->total_recipients,
                ],
            ], $b->user_id);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Node → Laravel webhook for the parent-row broadcast lifecycle.
     * Fires once at the end of the run with the final aggregate
     * status + counts. We accept Node's view of truth here because
     * per-message webhooks can drop in flight (no retry on the Node
     * side); this final write-once landmark is the safety net.
     */
    public function nodeBroadcastStatus(Request $request): JsonResponse
    {
        // Refuse when token isn't configured — an empty token was
        // previously treated as "allow everyone".
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'broadcast_id'  => 'required|integer',
            'status'        => 'required|in:scheduled,processing,completed,completed_with_errors,failed',
            'success_count' => 'nullable|integer|min:0',
            'fail_count'    => 'nullable|integer|min:0',
        ]);

        $b = Broadcast::find($data['broadcast_id']);
        if (!$b) {
            return response()->json(['ok' => false, 'message' => 'broadcast not found'], 404);
        }

        $b->update([
            'status'        => $data['status'],
            'success_count' => $data['success_count'] ?? $b->success_count,
            'fail_count'    => $data['fail_count']    ?? $b->fail_count,
            'completed_at'  => in_array($data['status'], ['completed', 'completed_with_errors', 'failed'], true)
                ? ($b->completed_at ?? now())
                : $b->completed_at,
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $b = Broadcast::query()->forCurrentWorkspace()->findOrFail($id);

        // Same guard the old controller had — only scheduled or
        // failed broadcasts are deletable, otherwise the operator
        // would be cancelling an in-flight send.
        if (!in_array($b->status, ['scheduled', 'failed'], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Only scheduled or failed broadcasts can be deleted.',
            ], 422);
        }

        // Best-effort cancel on the Node bridge before nuking
        // locally — same order as the old controller.
        if ($b->node_schedule_id) {
            $base = wd_node_url();
            if ($base !== '') {
                try {
                    Http::timeout(5)->delete(rtrim($base, '/') . '/api/broadcast/cancel/' . $b->node_schedule_id);
                } catch (\Throwable $e) {
                    Log::warning('broadcast bridge cancel failed', ['id' => $b->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $b->contacts()->detach();
        $b->delete();

        return response()->json([
            'ok'    => true,
            'data'  => ['id' => $id],
            'meta'  => $this->statusCounts(Broadcast::query()->forCurrentWorkspace()->get()),
        ]);
    }

    /**
     * Live-stats endpoint for the detail page poll. Returns the per-
     * status counts + the broadcast's own status so the JS can refresh
     * the header KPI tiles while a broadcast is in flight without
     * reloading the page. Cheap — single GROUP BY on broadcast_contacts.
     */
    public function liveStats(int $id): JsonResponse
    {
        $b = Broadcast::query()->forCurrentWorkspace()->findOrFail($id);
        $rows = DB::table('broadcast_contacts')
            ->where('broadcast_id', $b->id)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $sent      = (int) ($rows['sent']      ?? 0);
        $delivered = (int) ($rows['delivered'] ?? 0);
        $read      = (int) ($rows['read']      ?? 0);
        $failed    = (int) ($rows['failed']    ?? 0);
        // Cascade: read implies delivered implies sent.
        $delivered += $read;
        $sent      += $delivered;
        $total = (int) ($b->total_recipients ?: 0);
        $pct = fn (int $n) => $total > 0 ? round($n / $total * 100, 1) : 0.0;
        return response()->json([
            'status'    => $b->status,
            'total'     => $total,
            'sent'      => $sent,
            'delivered' => $delivered,
            'read'      => $read,
            'failed'    => $failed,
            'queued'    => max(0, $total - $sent - $failed),
            'pct'       => [
                'sent'      => $pct($sent),
                'delivered' => $pct($delivered),
                'read'      => $pct($read),
                'failed'    => $pct($failed),
            ],
            'in_flight' => in_array($b->status, ['processing', 'sending', 'scheduled', 'pending', 'queued'], true),
            'fetched_at'=> now()->toIso8601String(),
        ]);
    }

    /**
     * Retry every failed recipient on a broadcast. Resets each failed
     * row to status='pending', clears error_message, and re-dispatches
     * the broadcast loop on Node. Common after fixing a stale media URL
     * or recovering from a Baileys disconnect mid-run.
     */
    public function retryFailed(Request $request, int $id): JsonResponse
    {
        $b = Broadcast::query()->forCurrentWorkspace()->findOrFail($id);

        $failedRows = DB::table('broadcast_contacts')
            ->where('broadcast_id', $b->id)
            ->whereIn('status', ['failed', 'undelivered'])
            ->get();
        if ($failedRows->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'no_failed', 'message' => 'No failed recipients to retry.'], 400);
        }

        // Reset failed rows so the dispatcher picks them up as pending.
        DB::table('broadcast_contacts')
            ->where('broadcast_id', $b->id)
            ->whereIn('status', ['failed', 'undelivered'])
            ->update([
                'status'              => 'pending',
                'error_message'       => null,
                'whatsapp_message_id' => null,
                'updated_at'          => now(),
            ]);

        // Build the ID list of previously-failed recipients so the
        // bridge call below scopes its contacts query to JUST those rows.
        $contactIds = $failedRows->pluck('contact_id')->filter()->unique()->values()->all();

        // Bump broadcast status back to processing so the index page poll
        // shows it as in-flight while the retry loop runs.
        $b->status = 'processing';
        $b->save();

        try {
            $this->dispatchToBridge($b, true, $contactIds);
        } catch (\Throwable $e) {
            Log::warning('[BCAST] retry-failed dispatch threw', [
                'broadcast_id' => $b->id,
                'error'        => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'error'   => 'dispatch_failed',
                'message' => 'Retry submitted but Node bridge call threw — check logs.',
            ], 500);
        }

        return response()->json([
            'ok'    => true,
            'retried' => count($failedRows),
            'status' => $b->status,
            'message' => 'Retrying ' . count($failedRows) . ' failed recipients.',
        ]);
    }

    /**
     * AUTO-RETRY core (no auth, workspace-scoped via $b) — resets this
     * broadcast's failed/undelivered recipients to pending and re-dispatches
     * ONLY them to the bridge. Same machinery as the manual retryFailed(),
     * but callable from BroadcastSweeper on the heartbeat. Returns the number
     * of recipients re-queued (0 if none failed).
     */
    public function redispatchFailed(Broadcast $b): int
    {
        $failedRows = DB::table('broadcast_contacts')
            ->where('broadcast_id', $b->id)
            ->whereIn('status', ['failed', 'undelivered'])
            ->get();
        if ($failedRows->isEmpty()) return 0;

        DB::table('broadcast_contacts')
            ->where('broadcast_id', $b->id)
            ->whereIn('status', ['failed', 'undelivered'])
            ->update([
                'status'              => 'pending',
                'error_message'       => null,
                'whatsapp_message_id' => null,
                'updated_at'          => now(),
            ]);

        $contactIds = $failedRows->pluck('contact_id')->filter()->unique()->values()->all();
        $b->forceFill(['status' => 'processing'])->save();
        $this->dispatchToBridge($b, true, $contactIds);

        return $failedRows->count();
    }

    /**
     * Recipient-level statistics endpoint — same JSON shape as
     * the old getStatistics($id). Useful for the future detail
     * page sidebar.
     */
    public function statistics(int $id): JsonResponse
    {
        $b = Broadcast::query()->forCurrentWorkspace()->findOrFail($id);
        $rows = $b->recipients()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        // Cascade so each higher tier subsumes the lower:
        //   sent = sent + delivered + read
        //   delivered = delivered + read
        //   read = read
        // Matches what statsForUser does so the per-broadcast modal
        // and the global stat strip agree on counts.
        $sent       = (int) ($rows['sent']       ?? 0)
                    + (int) ($rows['delivered']  ?? 0)
                    + (int) ($rows['read']       ?? 0);
        $delivered  = (int) ($rows['delivered']  ?? 0)
                    + (int) ($rows['read']       ?? 0);
        return response()->json([
            'success' => true,
            'stats'   => [
                'sent'       => $sent,
                'delivered'  => $delivered,
                'read'       => (int) ($rows['read']       ?? 0),
                'failed'     => (int) ($rows['failed']     ?? 0),
                'processing' => (int) ($rows['processing'] ?? 0),
                'queued'     => (int) ($rows['pending']    ?? 0),
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Roll up workspace-wide stats for the KPI strip.
     *
     * Pre-fix: read from `broadcast_contacts` (legacy table used by
     * the old controller). New broadcasts populate the
     * `scheduled_message_contacts` table AND the cached aggregate
     * columns on `broadcasts` (success_count / delivered_count /
     * read_count / fail_count / clicked_count) — so the legacy
     * pivot showed stale zeros while the per-row counts on the same
     * page showed real values. Two sources of truth.
     *
     * Fix: sum the cached broadcasts.* columns (the same numbers the
     * per-row table renders), and pull `processing`/`queued` from
     * `scheduled_message_contacts` since those transient states
     * aren't mirrored to the cached columns. Workspace-scoped so a
     * teammate sees the whole workspace, not just their own rows.
     */
    private function statsForUser(?int $userId, $allBroadcasts): array
    {
        $wsId = (int) (\Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0);

        // Primary tiles — sum the cached aggregate columns. Matches
        // per-row Sent/Delivered/Read/Failed/Clicked exactly because
        // those columns are recomputed on every Meta status webhook.
        $sums = DB::table('broadcasts')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($userId && !$wsId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('
                COALESCE(SUM(success_count),   0) as sent,
                COALESCE(SUM(delivered_count), 0) as delivered,
                COALESCE(SUM(read_count),      0) as read_total,
                COALESCE(SUM(fail_count),      0) as failed,
                COALESCE(SUM(clicked_count),   0) as clicked,
                COALESCE(SUM(total_recipients),0) as total_recipients
            ')
            ->first();

        // Transient state (processing / queued) — not stored on
        // broadcasts directly. Join SMC → broadcasts and count by
        // status. Cheap with the (scheduled_message_id, status)
        // index on the SMC table.
        $transient = DB::table('scheduled_message_contacts as smc')
            ->join('broadcasts as b', 'b.id', '=', 'smc.scheduled_message_id')
            ->when($wsId, fn ($q) => $q->where('b.workspace_id', $wsId))
            ->when($userId && !$wsId, fn ($q) => $q->where('b.user_id', $userId))
            ->selectRaw("
                SUM(CASE WHEN smc.status = 'pending' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN smc.status = 'sent' AND smc.delivered_at IS NULL AND smc.failed_at IS NULL THEN 1 ELSE 0 END) as in_flight
            ")
            ->first();

        return [
            'total'      => $allBroadcasts->count(),
            'sent'       => (int) ($sums->sent       ?? 0),
            'delivered'  => (int) ($sums->delivered  ?? 0),
            'read'       => (int) ($sums->read_total ?? 0),
            'failed'     => (int) ($sums->failed     ?? 0),
            'clicked'    => (int) ($sums->clicked    ?? 0),
            'processing' => (int) ($transient->in_flight ?? 0),
            'queued'     => (int) ($transient->queued    ?? 0),
            'recipients' => (int) ($sums->total_recipients ?? 0),
        ];
    }

    private function statusCounts($allBroadcasts): array
    {
        return [
            'all'                   => $allBroadcasts->count(),
            'scheduled'             => $allBroadcasts->where('status', 'scheduled')->count(),
            'processing'            => $allBroadcasts->where('status', 'processing')->count(),
            'completed'             => $allBroadcasts->where('status', 'completed')->count(),
            'completed_with_errors' => $allBroadcasts->where('status', 'completed_with_errors')->count(),
            'failed'                => $allBroadcasts->where('status', 'failed')->count(),
        ];
    }

    /**
     * Pre-flight check that a media URL we'd send to Meta as `link`
     * can actually be fetched by Meta. Returns null if OK, or a
     * human-readable error reason if Meta would reject it.
     *
     * Meta's media downloader needs:
     *   - HTTPS (no plain http)
     *   - Public DNS-resolvable host (no private IPs, .local, .test)
     *   - A working endpoint serving the file with correct Content-Type
     *
     * We only validate the first two here (cheap, deterministic). The
     * third is checked by Meta itself when it fetches.
     */
    private function mediaUrlReachableForMeta(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return "Media URL '$url' is invalid.";
        }
        $scheme = strtolower($parts['scheme'] ?? 'http');
        if ($scheme !== 'https') {
            return "Media URL must be HTTPS for Meta to fetch it (got: {$scheme}). Configure APP_URL with an https:// public domain.";
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Reject IP literals (always private in this context).
            $isPrivate = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPrivate) {
                return "Media URL host '$host' is a private/reserved IP. Meta cannot reach it. Use a public domain.";
            }
        } else {
            // Reject obvious dev TLDs that won't resolve from Meta's edge.
            foreach (['.local', '.test', '.internal', '.localhost'] as $bad) {
                if (str_ends_with($host, $bad)) {
                    return "Media URL host '$host' ends with $bad which Meta cannot resolve. Use a public domain.";
                }
            }
            if ($host === 'localhost') {
                return "Media URL host is 'localhost'. Meta cannot reach it. Use a public domain.";
            }
        }
        return null;
    }

    /**
     * Resolve per-recipient variable values for the Meta payload.
     *
     * Walks the template's variable_map (which positional slot maps to
     * which contact attribute / custom_attributes key) and pulls the
     * contact-specific value. The shape matches what
     * TemplatePayloadBuilder::buildSend expects:
     *
     *   [
     *     'header'  => 'A12345',                   // text param for {{1}} in header
     *     'body'    => ['Sudhir', '1 × Hoodie'],   // positional body params
     *     'buttons' => [                           // per-button params (URL/quick-reply/copy-code)
     *       ['index' => 0, 'sub_type' => 'url', 'value' => 'https://shop/INV-001'],
     *     ],
     *     'cards'   => [...],                      // carousel cards if template_type=carousel
     *   ]
     *
     * Unknown placeholders fall back to a literal empty string so Meta
     * still gets a valid POST (better than dropping the whole send) —
     * the linter at template-create time should have caught missing
     * example values long before any broadcast hits this code path.
     */
    /**
     * Public because the team-inbox composer sends templates too and must
     * resolve slots identically. Every duplicated copy of this logic in the
     * past disagreed with the others and shipped raw `{{1}}` to customers —
     * there is one implementation on purpose.
     */
    public function varsForRecipient(\App\Models\WaTemplate $tpl, array $contact, int $workspaceId, ?array $overrides = null): array
    {
        // Send-time overrides shadow the template's variable_map for THIS
        // send only, per slot. Applied at the end of this method so the
        // template-driven result is always computed first and an override
        // that leaves a slot blank falls back to it untouched.
        $overrideResolver = ($overrides && is_array($overrides))
            ? app(\App\Services\TemplateOverrideResolver::class)
            : null;

        // Authentication templates carry a server-generated OTP code,
        // NOT a customer-supplied placeholder map. Meta requires the
        // same code in both the body[0] parameter and the URL button[0]
        // parameter. We mint a fresh per-recipient 6-digit code so it
        // is impossible to reuse one OTP across two contacts. The
        // caller (broadcast loop) doesn't need to track it — Meta
        // doesn't echo OTPs in webhooks, so we don't either.
        if ($tpl->template_type === 'auth') {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // Deliberately NOT override-able: a hand-typed OTP would be the
            // same for every recipient, which defeats the whole point and is
            // a security regression, not a feature. The UI hides the panel
            // for auth templates.
            return ['otp' => $code, 'body' => [$code]];
        }

        $vmap = $tpl->variable_map ?? [];
        if (is_string($vmap)) {
            $vmap = json_decode($vmap, true) ?: [];
        }
        $vmap = is_array($vmap) ? $vmap : [];

        // Coerce any value to a safe scalar string. A contact's custom-attribute
        // value CAN be an array (nested JSON) — casting that straight to string
        // throws "Array to string conversion", which surfaced as
        // "TemplateSender exception: Array to string conversion" and failed the
        // whole campaign/broadcast send. Flatten arrays to a comma-joined string.
        $scalarize = function ($v): string {
            if (is_array($v)) {
                return implode(', ', array_map('strval', array_filter($v, 'is_scalar')));
            }
            return is_scalar($v) ? (string) $v : '';
        };
        // Contact field → custom attribute → (fallback) workspace attribute /
        // system token. The fallback matters: a template mapping a slot to
        // `company_name` or a workspace Attribute used to resolve to an EMPTY
        // string here, because this closure only ever looked at the contact.
        // TemplateOverrideResolver::lookup() knows the full precedence and
        // normalises names ("Phone Number" → phone), so defer to it rather
        // than maintaining a second, weaker copy of the same rules.
        $fallback = app(\App\Services\TemplateOverrideResolver::class);
        $pull = function (string $key) use ($contact, $scalarize, $fallback, $workspaceId): string {
            if (isset($contact[$key]) && $contact[$key] !== null && $contact[$key] !== '') return $scalarize($contact[$key]);
            $custom = $contact['custom_attributes'] ?? [];
            if (is_array($custom) && isset($custom[$key]) && $custom[$key] !== '') return $scalarize($custom[$key]);
            return $fallback->lookup($key, $contact, $workspaceId);
        };

        $vars = [];

        // Header text variables.
        $headerMap = $vmap['header'] ?? [];
        if (is_array($headerMap) && !empty($headerMap)) {
            // header is a SINGLE text token, take the first slot's value
            $first = is_array($headerMap[0] ?? null) ? $headerMap[0] : ['key' => $headerMap[0] ?? null];
            $vars['header'] = $pull((string) ($first['key'] ?? ''));
        }

        // Body positional variables.
        $bodyMap = $vmap['body'] ?? [];
        if (is_array($bodyMap) && !empty($bodyMap)) {
            $vars['body'] = array_values(array_map(function ($entry) use ($pull) {
                $key = is_array($entry) ? ($entry['key'] ?? '') : (string) $entry;
                return $pull((string) $key);
            }, $bodyMap));
        }

        // Buttons — for URL buttons with placeholders the per-contact
        // substituted URL goes here. quick_reply/copy_code buttons
        // pass their literal value through.
        $btns = is_array($tpl->buttons) ? $tpl->buttons : [];
        foreach ($btns as $idx => $b) {
            if (!is_array($b)) continue;
            $type = (string) ($b['type'] ?? '');
            $val  = (string) ($b['value'] ?? '');
            // Aliases matter: rows carry `call`/`copy_text` as well as
            // `call_phone`/`copy_code` depending on how they were created.
            // Matching only the canonical names sent a quick_reply `payload`
            // for PHONE_NUMBER and COPY_CODE buttons → Meta #132000.
            // WaTemplate::buttonSubType is the single source of truth.
            $subType = \App\Models\WaTemplate::buttonSubType($type);   // NULL = no send-time param
            if ($subType === null) continue;
            // Substitute placeholders in the URL/code value with contact data.
            // Accept both named ({{name}}) and positional ({{1}}) tokens — the
            // previous regex `[\w_]+` matched digits too, but the resolver `$pull`
            // wouldn't know how to map a positional index to a contact attribute,
            // so we route positional URL/code placeholders through the same
            // variable_map → contact lookup that the body uses.
            $resolved = preg_replace_callback(\App\Services\TemplateOverrideResolver::TOKEN_RE, function ($m) use ($pull, $tpl) {
                $key = trim((string) $m[1]);
                // Positional index — translate via variable_map first.
                //
                // variable_map['body'] is a LIST of ['num'=>N,'key'=>'...']
                // objects, not a flat {slot => key} map. Indexing it directly
                // by the slot number returned the wrong entry (off by one)
                // AND returned an array, which threw "Array to string
                // conversion" — so parameterised button URLs never resolved.
                if (ctype_digit($key)) {
                    $named = \App\Services\TemplateOverrideResolver::flattenMap(
                        $tpl->variable_map, ['body']
                    )[$key] ?? '';
                    if ($named !== '') return $pull($named);
                }
                return $pull($key);
            }, $val);

            // Skip emitting send-time parameters for QUICK_REPLY buttons
            // that have no payload — Meta rejects `{type:'payload', payload:''}`
            // with error 132000. When omitted entirely, Meta uses the
            // button's text as the default payload, which is what the
            // merchant actually wants in the inbound webhook anyway.
            if ($subType === 'quick_reply' && trim($resolved) === '') continue;

            $vars['buttons'][] = [
                'index'    => $idx,
                'sub_type' => $subType,
                'value'    => $resolved,
            ];
        }

        // Header media reference (image/video/document) — passes the
        // public URL through as `header_media_url`. Meta accepts `link`
        // OR `id`; we use `link` since we don't pre-upload broadcast
        // media to Meta. Customers with high volume should pre-upload
        // for faster delivery, but link works correctly.
        //
        // Storage path note: TemplatesController stores via
        // `$file->store('wa-templates', 'public')`, so the relative
        // path looks like `wa-templates/abc.jpg` and the public URL
        // is `app/storage/wa-templates/abc.jpg` (NOT the legacy
        // `uploads/templates/attachments/` path that NodeSchedulerClient
        // still uses — that's a pre-existing bug we don't inherit here).
        if (!empty($tpl->attachment_file) && !empty($tpl->attachment_type)
            && $tpl->attachment_type !== 'none' && strtoupper($tpl->attachment_type) !== 'TEXT') {
            $vars['header_media_url'] = media_url($tpl->attachment_file);
        }

        // Carousel cards — build a per-card vars structure.
        if ($tpl->template_type === 'carousel' && is_array($tpl->carousel_data)) {
            foreach ($tpl->carousel_data as $cardIdx => $card) {
                $cardBodyMap = $vmap['cards'][$cardIdx]['body'] ?? [];
                $cardVars = [];
                if (!empty($card['image'])) {
                    $cardVars['header_media_url'] = str_starts_with($card['image'], 'http')
                        ? $card['image']
                        : media_url($card['image']);
                    $cardVars['header_format'] = pathinfo($card['image'], PATHINFO_EXTENSION) === 'mp4' ? 'video' : 'image';
                }
                if (is_array($cardBodyMap) && !empty($cardBodyMap)) {
                    $cardVars['body'] = array_values(array_map(function ($entry) use ($pull) {
                        $key = is_array($entry) ? ($entry['key'] ?? '') : (string) $entry;
                        return $pull((string) $key);
                    }, $cardBodyMap));
                }
                $cardBtns = is_array($card['buttons'] ?? null) ? $card['buttons'] : [];
                foreach ($cardBtns as $bIdx => $cb) {
                    if (!is_array($cb)) continue;
                    $cbType = (string) ($cb['type'] ?? '');
                    $cbVal  = (string) ($cb['value'] ?? '');
                    // Same alias normalisation as the top-level button loop.
                    // Carousel cards can't carry PHONE_NUMBER buttons, but a
                    // NULL here still means "emit no parameter" — skip rather
                    // than fall back to quick_reply and trip #132000.
                    $cbSub = \App\Models\WaTemplate::buttonSubType($cbType);
                    if ($cbSub === null) continue;
                    // Mirror the top-level button regex fix — accept BOTH
                    // named ({{name}}) and positional ({{1}}) placeholders,
                    // routing positional indices through the card's body
                    // variable_map so {{1}} resolves to the right attribute.
                    $resolvedCb = preg_replace_callback('/\{\{\s*([^\s{}]+?)\s*\}\}/', function ($m) use ($pull, $cardBodyMap) {
                        $key = (string) $m[1];
                        if (ctype_digit($key) && is_array($cardBodyMap)) {
                            // cardBodyMap is the per-card positional → named map.
                            $entry = $cardBodyMap[$key] ?? null;
                            $named = is_array($entry) ? ($entry['key'] ?? '') : (string) ($entry ?? '');
                            if ($named !== '') return $pull($named);
                        }
                        return $pull($key);
                    }, $cbVal);
                    // Same Meta-rejects-empty-payload rule as the top-level
                    // button loop above — omit the QUICK_REPLY param entirely
                    // when there's no value so Meta defaults to the button text.
                    if ($cbSub === 'quick_reply' && trim($resolvedCb) === '') continue;
                    $cardVars['buttons'][] = [
                        'index'    => $bIdx,
                        'sub_type' => $cbSub,
                        'value'    => $resolvedCb,
                    ];
                }
                $vars['cards'][$cardIdx] = $cardVars;
            }
        }

        // Overlay the operator's send-time edits last, so they win over the
        // template's map but only for the slots they actually filled in.
        if ($overrideResolver) {
            $vars = $overrideResolver->apply($vars, $overrides, $contact, $workspaceId);
        }

        return $vars;
    }

    /**
     * Pass every URL-shaped button value (and carousel card URL button
     * value) through LinkTracker so the click attributes to this
     * recipient. Mirrors TemplateSender's logic — same context shape.
     */
    private function wrapUrlsForRecipient(array $vars, array $context): array
    {
        if (!\App\Services\Waba\LinkTracker::enabled()) return $vars;

        foreach (($vars['buttons'] ?? []) as $i => $btn) {
            if (($btn['sub_type'] ?? '') !== 'url') continue;
            $val = (string) ($btn['value'] ?? '');
            if (filter_var($val, FILTER_VALIDATE_URL)) {
                $vars['buttons'][$i]['value'] = \App\Services\Waba\LinkTracker::wrap($val, $context);
            }
        }
        foreach (($vars['cards'] ?? []) as $cardIdx => $card) {
            foreach (($card['buttons'] ?? []) as $i => $btn) {
                if (($btn['sub_type'] ?? '') !== 'url') continue;
                $val = (string) ($btn['value'] ?? '');
                if (filter_var($val, FILTER_VALIDATE_URL)) {
                    $vars['cards'][$cardIdx]['buttons'][$i]['value'] = \App\Services\Waba\LinkTracker::wrap($val, $context);
                }
            }
        }
        return $vars;
    }

    /**
     * Single-source-of-truth for the templateData blob Node consumes
     * across scheduled / broadcasts. Workspace-level attribute
     * substitution happens here (server-side, once) — contact-level
     * placeholders ({{name}}, {{phone}}, {{email}}) stay literal so
     * Node can do per-recipient substitution at send time. Mirrors
     * NodeSchedulerClient::resolveTemplateData — keep them in sync.
     */
    private function buildTemplateData(\App\Models\WaTemplate $tpl, int $workspaceId): array
    {
        // Single source of truth, shared with the mobile-app queue + anywhere
        // a template is sent, so every type (standard/media/carousel/auth)
        // renders identically. See App\Services\Whatsapp\TemplateDataBuilder.
        return \App\Services\Whatsapp\TemplateDataBuilder::build($tpl, $workspaceId);
    }

    /**
     * Hand the broadcast over to the Node bridge for actual send.
     * Env-gated; if SERVER_URL isn't set we leave the row in
     * `processing` / `scheduled` and the local seeder counters
     * stand in until a bridge exists.
     */
    private function dispatchToBridge(Broadcast $b, bool $immediate, ?array $retryContactIds = null): void
    {
        $base = wd_node_url();
        Log::info('[BCAST] dispatchToBridge ENTER', [
            'broadcast_id' => $b->id,
            'device_id'    => $b->device_id,
            'template_id'  => $b->template_id,
            'recipients'   => $b->total_recipients,
            'immediate'    => $immediate,
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'server_url'   => $base ?: 'NOT SET',
        ]);
        if ($base === '') {
            Log::warning('[BCAST] aborted — env SERVER_URL is empty; Node bridge unreachable, broadcast stays in local state only', [
                'broadcast_id' => $b->id,
            ]);
            return;
        }

        // Resolve the sender phone for the Node URL path. Order:
        //   1. Broadcast.device_id (set by the new multi-device fan-out)
        //   2. Workspace's first connected device (legacy fallback)
        // Node routes are `/api/broadcast/{send-immediate|schedule}/:phoneNumber`
        // and key into its `app.locals.clients[<phone>]` map by digits
        // — without the segment Node 404s and the broadcast goes
        // straight to status=failed (the silent bug we just fixed).
        $sender = null;
        $phoneDigits = '';

        // Multi-engine: a WABA / Twilio broadcast slice stores a
        // wa_provider_configs.id in device_id (NOT a devices.id). Resolve the
        // sender PHONE from that config so the Node URL segment carries the
        // right number — Node routes the slice by payload.provider and uses
        // this phone only to look up the workspace's WABA/Twilio creds. Using
        // Device::find() here (the Baileys path) would hit a wrong/missing row
        // (id-namespace collision) and silently fail or misroute every
        // non-Baileys broadcast.
        if (in_array($b->provider, ['waba', 'twilio'], true)) {
            $cfg = $b->device_id ? \App\Models\WaProviderConfig::find($b->device_id) : null;
            // device_id didn't resolve (legacy/cleared) → fall back to the
            // workspace's primary connected sender for that exact engine.
            if (!$cfg && $b->workspace_id) {
                $cfg = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $b->workspace_id)
                    ->where('provider', $b->provider)
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->orderByDesc('is_primary')
                    ->orderByDesc('id')
                    ->first();
            }
            $sender = $cfg; // for the diagnostic log below (id + label)
            $phoneDigits = $cfg ? preg_replace('/\D+/', '', (string) $cfg->phone_number) : '';
            if ($phoneDigits === '') {
                Log::warning('[BCAST] aborted — no connected ' . $b->provider . ' sender for slice', [
                    'broadcast_id' => $b->id,
                    'device_id_on_broadcast' => $b->device_id,
                    'hint' => 'Connect a ' . $b->provider . ' sender at /devices first.',
                ]);
                $b->update(['status' => 'failed']);
                return;
            }
        } else {
            // Baileys (and legacy untyped) — resolve from the devices table.
            if ($b->device_id) {
                $sender = \App\Models\Device::find($b->device_id);
            }
            // Worker context — no auth user. Resolve a device from the
            // broadcast's own workspace_id (workspace-shared visibility),
            // falling back to the legacy user_id path only for pre-migration
            // broadcasts whose workspace_id wasn't backfilled.
            if (!$sender && $b->workspace_id) {
                $sender = \App\Models\Device::query()
                    ->where('workspace_id', $b->workspace_id)
                    ->where('status', 'connected')
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$sender && $b->user_id) {
                $sender = \App\Models\Device::query()
                    ->where('user_id', $b->user_id)
                    ->whereNull('workspace_id')
                    ->where('status', 'connected')
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$sender) {
                Log::warning('[BCAST] aborted — no sender device resolvable', [
                    'broadcast_id' => $b->id,
                    'device_id_on_broadcast' => $b->device_id,
                    'user_id' => $b->user_id,
                    'hint' => 'No active connected device for this workspace. Pair one at /devices first.',
                ]);
                $b->update(['status' => 'failed']);
                return;
            }
            $phoneDigits = preg_replace('/\D+/', '', (string) ($sender->country_code . $sender->phone_number));
            if ($phoneDigits === '') {
                Log::warning('[BCAST] aborted — sender phone empty after digit-strip', [
                    'broadcast_id' => $b->id,
                    'sender_device_id' => $sender->id,
                    'cc_raw' => $sender->country_code,
                    'pn_raw' => $sender->phone_number,
                ]);
                $b->update(['status' => 'failed']);
                return;
            }
        }

        $endpoint = rtrim($base, '/')
            . ($immediate ? '/api/broadcast/send-immediate/' : '/api/broadcast/schedule/')
            . rawurlencode($phoneDigits);
        Log::info('[BCAST] sender + endpoint resolved', [
            'broadcast_id' => $b->id,
            'sender_device_id' => $sender->id,
            'sender_name' => $sender->device_name,
            'sender_digits' => $phoneDigits,
            'endpoint' => $endpoint,
        ]);

        // Hydrate full Contact rows so the encrypted `mobile` cast
        // decrypts, then format each as { id, phone, name, ...attrs } —
        // the shape Node's broadcastService expects. We pull more
        // columns now because the per-recipient Meta payload builder
        // below needs them to substitute {{first_name}} etc. server-side.
        $contactsQuery = $b->contacts()
            ->select(['contacts.id', 'contacts.country_code', 'contacts.mobile', 'contacts.name',
                      'contacts.first_name', 'contacts.last_name', 'contacts.email',
                      'contacts.custom_attributes']);
        // When `retryContactIds` is set we're inside the retry-failed
        // flow — only re-dispatch the previously-failed subset. Without
        // this guard a retry would re-send to the whole audience and
        // duplicate every already-delivered message.
        if (is_array($retryContactIds) && !empty($retryContactIds)) {
            $contactsQuery->whereIn('contacts.id', $retryContactIds);
        }
        $allContacts = $contactsQuery->get();

        $contactRows = $allContacts
            ->map(function ($c) {
                $cc    = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                $local = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                $phone = $cc && $local && strpos($local, $cc) !== 0
                    ? $cc . $local
                    : $local;
                return [
                    'id'                => $c->id,
                    'phone'             => $phone,
                    'name'              => (string) ($c->name ?? ''),
                    'first_name'        => (string) ($c->first_name ?? ''),
                    'last_name'         => (string) ($c->last_name ?? ''),
                    'email'             => (string) ($c->email ?? ''),
                    // Widened for send-time overrides: SendAttributes offers
                    // these as insertable tokens, so they must actually be
                    // present here or {{address}} would resolve to ''.
                    'title'             => (string) ($c->title ?? ''),
                    'middle_name'       => (string) ($c->middle_name ?? ''),
                    'address'           => (string) ($c->address ?? ''),
                    'language'          => (string) ($c->language ?? ''),
                    // Cast is encrypted:ARRAY — a (string) cast throws here.
                    'contact_group'     => is_array($c->contact_group)
                        ? implode(', ', array_filter($c->contact_group, 'is_scalar'))
                        : (string) ($c->contact_group ?? ''),
                    'country_code'      => $cc,
                    'mobile'            => $local,
                    'custom_attributes' => is_array($c->custom_attributes) ? $c->custom_attributes : [],
                ];
            })
            ->filter(fn ($c) => $c['phone'] !== '')
            ->values()
            ->all();

        // Diagnose any contacts that got dropped during phone
        // assembly — operator may have selected contacts with empty
        // `mobile` columns, and we want them to know which.
        $attachedCount = $b->contacts()->count();
        Log::info('[BCAST] contacts resolved', [
            'broadcast_id'   => $b->id,
            'attached_pivot' => $attachedCount,
            'resolved_phone' => count($contactRows),
            'dropped'        => max(0, $attachedCount - count($contactRows)),
        ]);

        if (empty($contactRows)) {
            Log::warning('[BCAST] aborted — every contact had an empty phone after digit-strip', [
                'broadcast_id' => $b->id,
                'attached_pivot' => $attachedCount,
                'hint' => 'Contacts in this broadcast have no usable mobile column. Check /contacts.',
            ]);
            $b->update(['status' => 'failed']);
            return;
        }

        // Hydrate the template so Node has the actual body to send,
        // not just an id. The previous payload sent `templateData: {id: N}`
        // and Node looked for `templateData.template_body` — empty
        // string → every recipient got a blank message.
        $tpl = $b->template_id ? \App\Models\WaTemplate::find($b->template_id) : null;
        $templateData = $tpl ? $this->buildTemplateData($tpl, (int) $b->workspace_id) : null;

        // If this is a Meta-approved WABA template, build the FULL
        // Meta `type:template` payload per recipient in PHP and ship
        // it to Node. Node's legacy buildWabaPayload only handles
        // header+body text params — buttons / carousel / media headers
        // get silently dropped. By pre-building here we get:
        //
        //   - URL / quick-reply / copy-code button parameters
        //   - Carousel cards with card_index + per-card params
        //   - Media header parameters (image/video/document by id|link)
        //   - LinkTracker wrapping per recipient (so clicks attribute
        //     to the right contact_id on the broadcasts page)
        //
        // Node short-circuits when it sees a `meta_payload` for the
        // contact and passes the components through to Meta verbatim.
        $metaPayloads = [];

        // Node's fallback builder can only express TEXT header/body params
        // (broadcastService.js buildWabaPayload). A template whose header is
        // IMAGE/VIDEO/DOCUMENT therefore reaches Meta with no media parameter
        // at all and is rejected with 132012 "header: Format mismatch, expected
        // IMAGE, received UNKNOWN" — the campaign path never hit this because it
        // sends through TemplatePayloadBuilder in PHP.
        //
        // Pre-building was gated behind waba_templates_v2_enabled, so on an
        // install with that flag off every media-header broadcast failed while
        // the identical campaign succeeded. A feature flag may gate a NEW
        // capability; it must not gate correctness for a shape Node cannot
        // render, so those templates are pre-built regardless of the flag.
        // Buttons and carousels are in the same boat — Node drops both.
        $needsPrebuild = false;
        if ($tpl) {
            $headerKind = strtoupper((string) ($tpl->attachment_type ?? ''));
            $hasMediaHeader = in_array($headerKind, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
                || !empty($tpl->attachment_file);

            $btns = $tpl->buttons;
            if (is_string($btns)) $btns = json_decode($btns, true);
            $hasButtons = is_array($btns) && $btns !== [];

            $hasCarousel = !empty($tpl->carousel_data)
                || strtolower((string) ($tpl->template_type ?? '')) === 'carousel';

            $needsPrebuild = $hasMediaHeader || $hasButtons || $hasCarousel;
        }

        if ($tpl && $tpl->meta_template_id
            && strtoupper((string) $tpl->meta_status) === 'APPROVED'
            && (\App\Models\SystemSetting::get('waba_templates_v2_enabled', false) || $needsPrebuild)) {

            $builder = new \App\Services\Waba\TemplatePayloadBuilder();
            foreach ($contactRows as $cr) {
                $vars = $this->varsForRecipient($tpl, $cr, (int) $b->workspace_id, $b->template_overrides);
                // Per-recipient LinkTracker wrap — each contact gets
                // a unique short-URL so click attribution works on the
                // broadcasts page.
                $vars = $this->wrapUrlsForRecipient($vars, [
                    'workspace_id' => (int) $b->workspace_id,
                    'broadcast_id' => $b->id,
                    'contact_id'   => $cr['id'],
                    'template_id'  => $tpl->id,
                    'phone'        => $cr['phone'],
                ]);
                $metaPayloads[$cr['id']] = $builder->buildSend($tpl, $vars);
            }
            $templateData['meta_payloads'] = $metaPayloads;
            Log::info('[BCAST] meta_payloads pre-built', [
                'broadcast_id'   => $b->id,
                'recipients'     => count($metaPayloads),
                'template_id'    => $tpl->id,
                // Which trigger fired. A media/button/carousel template that
                // reaches Meta WITHOUT a prebuilt payload is the 132012 case,
                // so this line is the first thing to check on a failed send.
                'needs_prebuild' => $needsPrebuild,
                'header_kind'    => strtoupper((string) ($tpl->attachment_type ?? '')) ?: 'none',
            ]);
        }

        // Click tracking for the engines Node sends on (Unofficial API and
        // Twilio). The WABA branch above already wraps URL buttons per
        // recipient, but it's gated on an approved Meta template AND
        // waba_templates_v2_enabled — so on the other two engines every
        // recipient got the raw URL and `broadcasts.clicked_count` could
        // never move off zero.
        //
        // Those engines send the body as TEXT, so the links live in the
        // body. Node builds that body per contact, which is why we ship a
        // contactId → pre-wrapped body map rather than rewriting the single
        // shared template_body: a shortlink has to be unique per recipient
        // or a click can't be attributed to anyone.
        //
        // {{name}}-style placeholders are left untouched — wrapping only
        // rewrites http(s) URLs, and Node still runs its own attribute
        // substitution over whichever body it ends up using. Node falls back
        // to the shared body when the map is absent, so an un-deployed Node
        // behaves exactly as it does today.
        $trackedBodies = [];
        $shared = (string) ($templateData['template_body'] ?? '');
        if (trim($shared) !== '' && str_contains($shared, 'http')
            && \App\Services\Waba\LinkTracker::enabled()) {
            foreach ($contactRows as $cr) {
                try {
                    $trackedBodies[$cr['id']] = \App\Services\Waba\LinkTracker::wrapInText($shared, [
                        'workspace_id' => (int) $b->workspace_id,
                        'broadcast_id' => $b->id,
                        'contact_id'   => $cr['id'],
                        'template_id'  => $tpl?->id,
                        'phone'        => $cr['phone'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    // Analytics must never cost a send — this recipient just
                    // gets the untracked body Node would have used anyway.
                    Log::warning('[BCAST] link tracking skipped for recipient', [
                        'broadcast_id' => $b->id, 'contact_id' => $cr['id'] ?? null, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('[BCAST] template hydrated', [
            'broadcast_id' => $b->id,
            'template_id'  => $b->template_id,
            'template_name'=> $tpl?->template_name,
            'body_len'     => $tpl ? mb_strlen((string) $tpl->template_body) : 0,
            'has_template' => (bool) $tpl,
            'has_meta_payloads' => !empty($metaPayloads),
            'tracked_bodies'    => count($trackedBodies),
        ]);
        $payload = [
            'broadcastId'    => $b->id,
            'targetContacts' => $contactRows,
            'isTemplate'     => (bool) $tpl,
            'templateData'   => $templateData,
            // Fallback message when no template is picked — keeps
            // broadcasts useful for plain-text blasts on Baileys
            // workspaces (Meta WABA still requires a template). Resolve
            // workspace attrs in the freeform body too so {{promo_key}}
            // doesn't leak literally to customers.
            'message'        => $templateData['template_body'] ?? '',
            // Multi-engine route: carry the engine this broadcast slice was
            // stamped with (Phase 3 broadcasts.provider — 'baileys'|'waba'|
            // 'twilio') so Node's broadcastService routes per-record rather
            // than by the workspace-wide settings heuristic. A broadcast
            // whose provider equals the workspace default (or an empty
            // legacy value) routes exactly as before.
            'provider'       => (string) $b->provider,
        ];
        // contactId → body with every URL replaced by that recipient's own
        // /r/{token} shortlink. Node prefers this over the shared body when
        // present; omitted entirely when there's nothing to track.
        if ($trackedBodies) {
            $payload['tracked_bodies'] = $trackedBodies;
        }
        // WhatsApp Warmer — when the sending number is warming, carry its
        // per-number send-gap range so Node paces THIS broadcast at the warmer's
        // gap (gap_min..gap_max sec, fresh pick per message) instead of the global
        // msg_gap. Engine-aware: Baileys warms its Device, WABA/Twilio their
        // wa_provider_configs row — both are accepted by WarmerService.
        if ($sender instanceof \App\Models\Device || $sender instanceof \App\Models\WaProviderConfig) {
            try {
                $w = app(\App\Services\WarmerService::class);
                if ($w->enabled($sender)) {
                    $cfg = $w->config($sender);
                    $payload['warmerGap'] = ['min' => (int) $cfg['gap_min'], 'max' => (int) $cfg['gap_max']];
                }
            } catch (\Throwable $e) { /* fall back to the global msg_gap */ }
        }
        if (!$immediate) {
            // ISO-8601 with offset so Node's moment.tz() converts UTC →
            // user-local correctly. A naive datetime string is treated
            // by moment AS IF it's already user-local, shifting every
            // send by the UTC offset (e.g. 17:15 IST gets fired at 11:45 IST).
            $payload['scheduleDateTime'] = $b->scheduled_at?->toIso8601String();
            $payload['timezone']         = $b->timezone ?: 'UTC';
        }

        Log::info('[BCAST] POST → Node', [
            'broadcast_id' => $b->id,
            'endpoint'     => $endpoint,
            'recipients'   => count($contactRows),
            'isTemplate'   => $payload['isTemplate'],
            'first_phone'  => $contactRows[0]['phone'] ?? null,
        ]);

        try {
            $startedAt = microtime(true);
            $res = Http::timeout(15)->post($endpoint, $payload);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($res->successful()) {
                $scheduleId = $res->json('scheduleId');
                $b->update([
                    'node_schedule_id' => $scheduleId,
                    'status'           => $immediate ? 'processing' : 'scheduled',
                ]);
                Log::info('[BCAST] Node accepted ✓', [
                    'broadcast_id'    => $b->id,
                    'http_status'     => $res->status(),
                    'node_schedule_id'=> $scheduleId,
                    'latency_ms'      => $latencyMs,
                    'response_summary'=> mb_substr((string) $res->body(), 0, 240),
                ]);

                // Mirror this send into the team-inbox thread for any recipient
                // who ALREADY has an open conversation, so the broadcast/template
                // shows in their chat history (agents were seeing the customer's
                // reply but not the message they replied to). Recipients with no
                // existing thread are skipped inside the mirror — no inbox flood.
                // Immediate sends only; scheduled ones haven't actually gone out.
                if ($immediate) {
                    $this->mirrorBroadcastToOpenInbox($b, $tpl, $templateData, $contactRows);
                }
            } else {
                Log::warning('[BCAST] Node REJECTED — non-2xx', [
                    'broadcast_id' => $b->id,
                    'endpoint'     => $endpoint,
                    'http_status'  => $res->status(),
                    'body'         => mb_substr((string) $res->body(), 0, 1024),
                    'latency_ms'   => $latencyMs,
                    'hint'         => $res->status() === 404
                        ? 'Node route not found — check Node server is running on env SERVER_URL'
                        : ($res->status() === 503
                            ? 'Node returned 503 — CLIENT NOT READY (device not paired on Node side or pairing in progress)'
                            : 'Node bridge returned an error — check its terminal for details'),
                ]);
                $b->update(['status' => 'failed']);
            }
        } catch (\Throwable $e) {
            Log::error('[BCAST] dispatch THREW — network error or Node bridge down', [
                'broadcast_id' => $b->id,
                'endpoint'     => $endpoint,
                'error'        => $e->getMessage(),
                'class'        => get_class($e),
                'hint'         => 'Common causes: SERVER_URL points at an offline Node, firewall blocks the port, or HTTP timeout exceeded 15s',
            ]);
            $b->update(['status' => 'failed']);
        }
    }

    /**
     * Mirror an immediate broadcast into the team-inbox threads of recipients
     * who ALREADY have an open conversation, so the message shows in their chat
     * history. Skips cold recipients (no thread) — the mirror service returns
     * null for those, so no new conversations are spawned. Best-effort: never
     * throws into the send path.
     *
     * @param  array  $contactRows  [['id'=>int,'phone'=>string,'name'=>?string], …]
     */
    private function mirrorBroadcastToOpenInbox(Broadcast $b, ?\App\Models\WaTemplate $tpl, ?array $templateData, array $contactRows): void
    {
        // Body the agent should see in the thread: the readable template
        // (header + body + footer) for template broadcasts, else the plain
        // freeform message. No body → nothing meaningful to mirror.
        $body = $tpl
            ? \App\Services\Inbox\InboxMirror::readableTemplateBody($templateData)
            : trim((string) (is_array($templateData) ? ($templateData['template_body'] ?? '') : ''));
        if ($body === '') return;

        // Guard huge blasts: mirroring does one conversation lookup per
        // recipient. Above this size we skip (and log) rather than run tens of
        // thousands of queries in the request. Normal broadcasts are far below.
        $cap = 2000;
        if (count($contactRows) > $cap) {
            Log::info('[BCAST] inbox mirror skipped — recipient count over cap', [
                'broadcast_id' => $b->id,
                'recipients'   => count($contactRows),
                'cap'          => $cap,
            ]);
            return;
        }

        $mirror   = app(\App\Services\Inbox\InboxMirror::class);
        $provider = (string) $b->provider ?: null;
        $mirrored = 0;
        foreach ($contactRows as $cr) {
            $phone = (string) ($cr['phone'] ?? '');
            if ($phone === '') continue;
            $id = $mirror->appendOutboundToOpenConversation(
                (int) $b->workspace_id,
                $phone,
                $body,
                null,
                $provider,
                array_filter([
                    'type'          => $tpl ? 'template' : 'text',
                    'template_name' => $tpl?->template_name,
                    // Buttons so the inbox bubble renders the template's CTA rows
                    // (Check Now / Reply STOP / …) exactly like a team-inbox send
                    // — this mirror path dropped them and showed plain text.
                    'buttons'       => ($tpl && is_array($tpl->buttons) && $tpl->buttons) ? $tpl->buttons : null,
                    'broadcast_id'  => $b->id,
                ], fn ($v) => $v !== null),
            );
            if ($id) $mirrored++;
        }
        if ($mirrored > 0) {
            Log::info('[BCAST] mirrored to open inbox threads', [
                'broadcast_id' => $b->id,
                'mirrored'     => $mirrored,
                'recipients'   => count($contactRows),
            ]);
        }
    }
}
