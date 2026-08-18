<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Models\WpCampaign;
use App\Models\WpCampaignContact;
use App\Services\WalletService;
use App\Services\WhatsAppDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * WhatsApp Campaigns CRUD — ported from
 * D:\wadesk_2806\New folder\app\Http\Controllers\WhatsAppCampaignController.php
 *
 * Adapted for the new project:
 *   - dropped the multi-tenancy `$site_name` route segment
 *   - dropped Spatie permission checks and PackageLimites enforcement
 *   - dropped the external Node.js / Facebook Http::post calls (TODO: dispatch
 *     an internal SendWaCampaign job once the queue infra lands)
 *
 * Operator-facing methods only. Webhook-style endpoints (trackResponse,
 * trackClick, unsubscribe) and internal Node sync helpers are intentionally
 * not ported.
 */
class WaCampaignsController extends Controller
{
    public function __construct(
        private readonly WhatsAppDispatcher $dispatcher,
        private readonly WalletService $wallet,
    ) {}

    /**
     * Whether the active engine REQUIRES Meta-approved templates.
     * Baileys + Twilio are message-stream APIs that take any text body
     * (templates are just convenience snippets), so any template works.
     * WABA (Meta Cloud) only accepts the exact templates Meta has
     * approved on a phone-number basis.
     */
    private function requiresApprovedTemplates(): bool
    {
        $allowed = SystemSetting::get('allowed_send_methods', ['baileys']);
        $allowed = is_array($allowed) ? $allowed : [$allowed];
        $default = SystemSetting::get('default_send_method', 'baileys');
        $active  = in_array($default, $allowed, true) ? $default : ($allowed[0] ?? 'baileys');
        return $active === 'waba';
    }

    // -----------------------------------------------------------------
    // Listing + create form
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        // Filter params — sidebar status, sidebar type, top-bar
        // range, and the live-search query string. All five flow
        // through `?status=&type=&range=&q=` so the URL is the
        // single source of truth and the JS can re-fetch from
        // `?partial=1` whenever the user clicks a filter without
        // re-rendering the whole page.
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $typeFilter   = $request->string('type')->toString()   ?: 'all';
        $rangeFilter  = $request->string('range')->toString()  ?: 'all';
        $search       = $request->string('q')->toString();

        $allCampaigns = WpCampaign::query()->forCurrentWorkspace()->forCurrentEngine()->orderBy('id', 'desc')->get();

        $campaigns = $allCampaigns;
        if ($statusFilter === 'recently_created') {
            $campaigns = $campaigns->where('created_at', '>=', now()->subDays(7));
        } elseif ($statusFilter === 'recently_updated') {
            $campaigns = $campaigns->where('updated_at', '>=', now()->subDays(7));
        } elseif ($statusFilter !== 'all') {
            $campaigns = $campaigns->where('status', $statusFilter);
        }
        if ($typeFilter === 'text') {
            $campaigns = $campaigns->whereIn('campaign_type', ['text', 'custom', 'media', 'button']);
        } elseif ($typeFilter !== 'all') {
            $campaigns = $campaigns->where('campaign_type', $typeFilter);
        }
        if ($rangeFilter !== 'all') {
            $cutoff = match ($rangeFilter) {
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                default => null,
            };
            if ($cutoff) $campaigns = $campaigns->where('created_at', '>=', $cutoff);
        }
        if ($search !== '') {
            // `campaign_name` is encrypted-at-rest — LIKE on
            // ciphertext matches nothing, so we filter the
            // hydrated collection by the decrypted plaintext.
            $needle = mb_strtolower($search);
            $campaigns = $campaigns->filter(fn ($c) => str_contains(mb_strtolower((string) $c->campaign_name), $needle));
        }
        $campaigns = $this->paginateCollection($campaigns->values(), $request, 12);

        // Roll the per-recipient wp_campaign_contacts log up into the
        // delivered/read/responded/clicked counts for the KPI strip + the
        // per-campaign cards. Meta status webhooks patch the log rows, and the
        // aggregate columns can lag them, so compute the true counts here in
        // ONE grouped query and override the in-memory models (display only —
        // the webhook and the detail page persist the columns). Without this
        // the KPI cards and campaign cards showed 0 while the funnel was right.
        $campaignIds = $allCampaigns->pluck('id')->all();
        if (!empty($campaignIds)) {
            $aggById = \DB::table('wp_campaign_contacts')
                ->whereIn('campaign_id', $campaignIds)
                ->selectRaw('campaign_id, ' . WpCampaign::aggregateSelectSql())
                ->groupBy('campaign_id')
                ->get()
                ->keyBy('campaign_id');
            $applyAgg = function ($c) use ($aggById) {
                $a = $aggById->get($c->id);
                if (!$a) return;
                // sent MUST come from the same log as the rest. It used to be
                // left on the campaign's own aggregate column while delivered
                // was recomputed here, so a campaign could show delivered=2
                // against sent=1 and a 200% delivery rate.
                $c->sent_count      = (int) $a->sent;
                $c->delivered_count = (int) $a->delivered;
                $c->read_count      = (int) $a->read_c;
                $c->responded_count = (int) $a->responded;
                $c->clicked_count   = (int) $a->clicked;

                // A campaign whose recipients are all resolved (including ones
                // skipped as unsubscribed) is finished, even if the Node
                // finaliser never got to mark it. Without this it stays
                // "Waiting for send window" forever.
                if ($c instanceof WpCampaign) {
                    $c->reconcileStatus($a);
                }
            };
            $allCampaigns->each($applyAgg);
            collect($campaigns->items())->each($applyAgg);
        }

        // Sidebar "Message type" counts — always derived from the
        // unfiltered set so the counts represent "what's available
        // to filter to", not "what survived the current filter".
        $messageTypes = [
            'text'     => $allCampaigns->whereIn('campaign_type', ['text', 'custom', 'media', 'button'])->count(),
            'template' => $allCampaigns->where('campaign_type', 'template')->count(),
            'flow'     => $allCampaigns->where('campaign_type', 'flow')->count(),
        ];

        // Sidebar status counts — same reasoning. Derived from
        // the full collection so users see real totals regardless
        // of which filter is currently active.
        $statusCounts = [
            'all'                => $allCampaigns->count(),
            'recently_created'   => $allCampaigns->where('created_at', '>=', now()->subDays(7))->count(),
            'recently_updated'   => $allCampaigns->where('updated_at', '>=', now()->subDays(7))->count(),
            'scheduled'          => $allCampaigns->where('status', 'scheduled')->count(),
            'running'            => $allCampaigns->where('status', 'running')->count(),
            'completed'          => $allCampaigns->where('status', 'completed')->count(),
            'failed'             => $allCampaigns->where('status', 'failed')->count(),
        ];

        // Delivery health — compute the average delivery rate across campaigns
        // that actually attempted to send, and surface a "warning" tone when
        // any campaign has > 5 failures so the sidebar tip card can switch
        // copy without a hardcoded string. (Workspace-wide, not filtered.)
        $sentTotal      = (int) $allCampaigns->sum('sent_count');
        $deliveredTotal = (int) $allCampaigns->sum('delivered_count');
        $avgDeliveryRate = $sentTotal > 0 ? ($deliveredTotal / $sentTotal) * 100 : 0;
        $failingCampaigns = $allCampaigns->where('failed_count', '>', 5)->count();
        $deliveryHealth = [
            'avg_delivery_rate'  => round($avgDeliveryRate, 1),
            'failing_campaigns'  => $failingCampaigns,
            'status'             => $failingCampaigns > 0 ? 'warning' : 'healthy',
        ];

        // Queue health — Template approvals: the REAL share of this workspace's
        // library that is actually sendable. (The old stub hardcoded 100% with a
        // "no Templates model yet" TODO — WaTemplate has existed for a long time,
        // so every workspace was told its templates were fine even when they were
        // all rejected or pending.) Device readiness pulls live counts from the
        // Device table when present. Retry backlog == sum of failed_count.
        // Devices-ready tile — engine-aware so a WABA workspace doesn't
        // see Baileys phone counts here. Baileys counts the devices
        // table; WABA / Twilio count wa_provider_configs rows of the
        // active engine.
        // Multi-engine: a workspace can run Baileys + WABA + Twilio at once,
        // so the devices-ready tile must SUM connected/total senders across
        // EVERY enabled engine — not just the single default. For a single-
        // engine workspace enginesFor() == [default], so this sum equals the
        // old single-engine branch (byte-identical). Baileys counts the
        // devices table; WABA / Twilio count wa_provider_configs rows of that
        // provider.
        $wsIdForRow = $request->user()?->current_workspace_id;
        $totalDevices     = 0;
        $connectedDevices = 0;
        foreach (\App\Services\WorkspaceEngine::enginesFor($wsIdForRow) as $engineForRow) {
            if ($engineForRow === \App\Services\WorkspaceEngine::ENGINE_BAILEYS && class_exists(\App\Models\Device::class)) {
                $totalDevices     += \App\Models\Device::query()->forCurrentWorkspace()->count();
                $connectedDevices += \App\Models\Device::query()->forCurrentWorkspace()->where('status', 'connected')->count();
            } else {
                $waba = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsIdForRow)
                    ->where('provider', $engineForRow)
                    ->get(['status']);
                $totalDevices     += $waba->count();
                $connectedDevices += $waba->where('status', 'connected')->count();
            }
        }
        $deviceReady = $totalDevices > 0 ? "{$connectedDevices}/{$totalDevices}" : 'N/A';
        // `approved()` is ENGINE-AWARE: a WABA workspace only counts Meta's real
        // verdict (meta_template_id + meta_status=APPROVED, not paused), while
        // Baileys/Twilio use the operator-controlled status — so each workspace
        // gets a truthful number. Same forCurrentWorkspace() universe as the
        // Templates library the operator actually sees (incl. admin globals).
        // NULL when the library is empty: "—" is honest, 100% or 0% is not.
        $tplTotal    = \App\Models\WaTemplate::query()->forCurrentWorkspace()->count();
        $tplApproved = $tplTotal > 0
            ? \App\Models\WaTemplate::query()->forCurrentWorkspace()->approved()->count()
            : 0;

        $queueHealth = [
            'template_approval_rate' => $tplTotal > 0 ? (int) round($tplApproved / $tplTotal * 100) : null,
            'template_approved'      => $tplApproved,
            'template_total'         => $tplTotal,
            'devices_ready'          => $deviceReady,
            'retry_backlog'          => (int) $allCampaigns->sum('failed_count'),
        ];

        $stats = [
            'total'   => $allCampaigns->count(),
            'queued'  => $statusCounts['scheduled'],
            'running' => $statusCounts['running'],
            'sent'    => $statusCounts['completed'],
            'failed'  => $statusCounts['failed'],
            // KPI tiles roll-ups — full workspace, not filtered.
            'sent_total'      => $sentTotal,
            'delivered_total' => $deliveredTotal,
            'read_total'      => (int) $allCampaigns->sum('read_count'),
            'failed_total'    => (int) $allCampaigns->sum('failed_count'),
            'processing'      => $statusCounts['running'],
            // Sidebar/aside derived metrics.
            'messageTypes'    => $messageTypes,
            'statusCounts'    => $statusCounts,
            'deliveryHealth'  => $deliveryHealth,
            'queueHealth'     => $queueHealth,
        ];

        $payload = [
            'campaigns'       => $campaigns,
            'stats'           => $stats,
            'currentStatus'   => $statusFilter,
            'currentType'     => $typeFilter,
            'currentRange'    => $rangeFilter,
            'currentSearch'   => $search,
        ];

        if ($request->wantsJson() || $request->boolean('partial')) {
            return response()->json([
                'ok'           => true,
                'cards'        => view('user.wa-campaigns._cards', ['campaigns' => $campaigns])->render(),
                'stats'        => $stats,
                'statusCounts' => $statusCounts,
                'messageTypes' => $messageTypes,
                'pagination'   => view('user.partials.pagination', ['paginator' => $campaigns, 'dataAttr' => 'data-wac-page', 'label' => 'campaigns'])->render(),
                'shown'        => $campaigns->count(),
                'total'        => $campaigns->total(),
                'page'         => $campaigns->currentPage(),
            ]);
        }

        return view('user.wa-campaigns.index', $payload);
    }

    /**
     * #4 — Downloadable sample CSV for the campaign bulk-recipient upload.
     * Columns match what the CSV importer reads: name + country_code + phone.
     */
    public function sampleCsv(): \Symfony\Component\HttpFoundation\Response
    {
        $csv = implode("\n", [
            'name,country_code,phone',
            'Aarav Sharma,91,9812345678',
            'Priya Patel,91,9898765432',
        ]) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="campaign-recipients-sample.csv"',
        ]);
    }

    public function create(Request $request): View
    {
        // Workspace-shared pickers — every asset created in the current
        // workspace shows up, regardless of which teammate added it.
        // Device picker is engine-aware: Baileys workspaces see paired
        // phones from `devices`; WABA / Twilio surface wa_provider_configs
        // rows as pseudo-devices so the operator can never pick a wrong-
        // engine sender that would silently fail at dispatch.
        $wsId   = $request->user()?->current_workspace_id;
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS && class_exists(\App\Models\Device::class)) {
            // Connected senders only — a disconnected phone can't run a
            // campaign, so keep it out of the picker. The /devices page
            // and the index KPIs still count every device.
            $devices = \App\Models\Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')->orderByDesc('id')->get();
        } else {
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get()
                ->map(function ($cfg) {
                    return (object) [
                        'id'           => $cfg->id,
                        'device_name'  => $cfg->display_label ?: ('WABA #' . $cfg->id),
                        'country_code' => '',
                        'phone_number' => $cfg->phone_number,
                        'status'       => $cfg->status,
                        'active'       => $cfg->status === \App\Models\WaProviderConfig::STATUS_CONNECTED,
                    ];
                });
        }
        // Multi-engine: every connected sender across ALL enabled engines,
        // for the unified <x-sender-picker> (composite engine:id keys). The
        // single-engine $devices list above is kept for back-compat / empty-
        // state copy.
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        $contacts  = Contact::query()->forCurrentWorkspace()->orderByDesc('id')->get();
        $groups    = ContactGroup::query()->forCurrentWorkspace()->orderByDesc('id')->get();
        // Load all of the user's templates — show their status next
        // to the name so the operator picks an approved one. The store
        // step rejects non-approved templates.
        $templates = class_exists(\App\Models\WaTemplate::class)
            ? \App\Models\WaTemplate::query()->forCurrentWorkspace()->providerLive()->with('provider')->orderByDesc('id')->get()
            : collect();
        $flows = class_exists(\App\Models\Flow::class)
            ? \App\Models\Flow::query()->forCurrentWorkspace()->orderByDesc('id')->get()
            : collect();

        // Pre-compute per-group member counts. The contacts.contact_group
        // column is encrypted JSON, so we hydrate once and tally in PHP
        // (cheaper than a per-group Eloquent query). Workspace-scoped
        // so foreign contacts can't inflate another tenant's counts.
        $allContacts = Contact::query()->forCurrentWorkspace()->get(['id', 'contact_group']);
        $groupCounts = [];
        foreach ($groups as $g) {
            $gid = (string) $g->id;
            $groupCounts[$g->id] = $allContacts->filter(function ($c) use ($gid) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array($gid, array_map('strval', $list), true);
            })->count();
        }

        $requiresApprovedTemplates = $this->requiresApprovedTemplates();

        // Null on the create path — the edit view reuses the same picker
        // payload but pre-fills from an existing campaign when present.
        $campaign = null;

        // Tag audience for the recipients step — with a live member count so
        // the operator can see "VIP · 34" before committing to a send.
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

        return view('user.wa-campaigns.create', compact(
            'devices', 'senders', 'contacts', 'groups', 'groupCounts',
            'templates', 'flows', 'requiresApprovedTemplates', 'campaign',
            'tags', 'tagCounts',
        ));
    }

    /**
     * GET /wa-campaigns/{id}/edit — pre-filled editor for a campaign that is
     * still mutable. Mirrors the picker payload built in create() so the
     * device / contact / group / template selects render identically, then
     * hands the loaded campaign to the focused edit view. Only draft,
     * scheduled or paused campaigns can be edited; anything in-flight or
     * finished is redirected back to its detail page. The server-side guard
     * in update() backs this up so a forged POST can't bypass it.
     */
    public function edit(int $id, Request $request): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        if (!in_array($campaign->status, ['draft', 'paused', 'scheduled'], true)) {
            return redirect()
                ->route('user.wa-campaigns.detail', $campaign->id)
                ->with('status', 'Only draft, scheduled or paused campaigns can be edited.');
        }

        $wsId   = $request->user()?->current_workspace_id;
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS && class_exists(\App\Models\Device::class)) {
            $devices = \App\Models\Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')->orderByDesc('id')->get();
        } else {
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get()
                ->map(function ($cfg) {
                    return (object) [
                        'id'           => $cfg->id,
                        'device_name'  => $cfg->display_label ?: ('WABA #' . $cfg->id),
                        'country_code' => '',
                        'phone_number' => $cfg->phone_number,
                        'status'       => $cfg->status,
                        'active'       => $cfg->status === \App\Models\WaProviderConfig::STATUS_CONNECTED,
                    ];
                });
        }
        $templates = class_exists(\App\Models\WaTemplate::class)
            ? \App\Models\WaTemplate::query()->forCurrentWorkspace()->providerLive()->with('provider')->orderByDesc('id')->get()
            : collect();
        $requiresApprovedTemplates = $this->requiresApprovedTemplates();

        // Recipient ids already attached to this campaign — used to mark
        // the matching checkboxes on the edit form. Stored on the per-contact
        // log table, so we pull the distinct contact ids back.
        $recipientIds = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('contact_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $contacts = Contact::query()->forCurrentWorkspace()->orderByDesc('id')->get();

        // Multi-engine sender set (all enabled engines) for <x-sender-picker>.
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        return view('user.wa-campaigns.edit', compact(
            'campaign', 'devices', 'senders', 'contacts', 'templates',
            'requiresApprovedTemplates', 'recipientIds',
        ));
    }

    // -----------------------------------------------------------------
    // Store
    // -----------------------------------------------------------------

    /**
     * Reject a campaign submit so the user actually SEES why. The create form
     * posts via fetch (Accept: application/json), and a back()->withErrors()
     * 302 is silently followed by fetch → res.ok=true, no error shown → the
     * "Launch" button just hangs. Return a JSON 422 the JS surfaces as a toast,
     * fall back to a redirect for non-AJAX callers.
     */
    private function rejectForm(Request $request, string $field, string $message)
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422);
        }
        return back()->withErrors([$field => $message])->withInput();
    }

    public function store(Request $request)
    {
        // Plan: feature flag + numeric cap.
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'campaign');
        // Delete-proof numeric cap: enforce against the INCREMENT-ONLY usage
        // ledger (PlanUsage via checkQuota), not a live row count — so "create
        // N, delete N, create N again" can no longer bypass the cap. The live
        // row count seeds the counter ONCE (first time this workspace is
        // metered) so an existing install starts at its true usage; buying a
        // new plan re-opens the allowance automatically (new billing period).
        // The count itself is auto-incremented on WpCampaign::create() by the
        // TracksPlanQuota trait — nothing per-controller to remember.
        \App\Services\PlanLimitGuard::checkQuota(
            $request->user()->currentWorkspace,
            'total_campaigns_limit',
            \App\Models\WpCampaign::PLAN_QUOTA_METRIC,
            \App\Models\WpCampaign::where('workspace_id', $request->user()->current_workspace_id)->count(),
        );

        $validated = $request->validate([
            'campaign_name'           => 'required|string|max:191',
            'device_id'               => 'nullable|integer',
            // Multi-engine: unified picker posts a composite `engine:id` key.
            // device_id stays accepted for back-compat (legacy single-engine form).
            'sender'                  => 'nullable|string|max:64',
            'campaign_type'           => 'required|in:text,template,button,flow,media,custom',
            'status'                  => 'nullable|string|max:32',
            'ab_testing'              => 'nullable|boolean',
            'ab_split'                => 'nullable|integer|min:0|max:100',
            'custom_message_b'        => 'nullable|string',
            'custom_message'          => 'required_if:campaign_type,text,custom,button,media|nullable|string',
            'custom_header'           => 'nullable|string|max:255',
            'custom_footer'           => 'nullable|string|max:255',
            'custom_buttons'          => 'nullable|array',
            'custom_quick_replies'    => 'nullable|array',
            // Positional-placeholder map for the CUSTOM message body. The
            // composer's `/`-attribute picker inserts {{1}} {{2}} tokens and
            // records {"1":"order_id"} here (compose-textarea emits it as
            // `custom_message_variable_map`). resolveCampaignBody feeds it to
            // AttributeResolver so the slots resolve to real workspace
            // attribute values at send time instead of shipping literal {{1}}.
            'custom_message_variable_map' => 'nullable|string',
            'template_id'             => 'nullable|integer',
            'template_id_a'           => 'nullable|integer',
            'template_id_b'           => 'nullable|integer',
            // Send-time template overrides (JSON from the mapping panel).
            // Shape is validated by TemplateOverrideResolver::sanitize(),
            // which drops anything it can't walk — the column is read back
            // on every send, so it must never hold an unknown shape.
            'template_overrides'      => 'nullable|string|max:65000',
            // Variant B's own mapping. Only meaningful when ab_testing is on
            // AND template_id_b differs from A; ignored otherwise.
            'template_overrides_b'    => 'nullable|string|max:65000',
            'flow_id'                 => 'nullable|integer',
            'flow_id_b'               => 'nullable|integer',
            'use_attributes'          => 'nullable|boolean',
            'tracking_enabled'        => 'nullable|boolean',
            'schedule_type'           => 'required|in:now,scheduled,recurring',
            'send_date'               => 'nullable|date',
            'send_time'               => 'nullable',
            'expires_at'              => 'nullable|date',
            'timezone'                => ['nullable', 'string', \Illuminate\Validation\Rule::in(\DateTimeZone::listIdentifiers())],
            'repeat_interval'         => 'nullable|in:daily,weekly,monthly',
            'repeat_until'            => 'nullable|date',
            // Smart Delivery (anti-ban) — all optional; blank = global default.
            'throttle_min_sec'        => 'nullable|integer|min:0|max:3600',
            'throttle_max_sec'        => 'nullable|integer|min:0|max:3600|gte:throttle_min_sec',
            'batch_size'              => 'nullable|integer|min:1|max:10000',
            'batch_pause_min'         => 'nullable|integer|min:0|max:1440',
            'daily_limit'             => 'nullable|integer|min:1|max:100000',
            'window_start'            => 'nullable|date_format:H:i',
            'window_end'              => 'nullable|date_format:H:i',
            'recipients'              => 'nullable|array',
            'recipients.*'            => 'integer',
            'groups'                  => 'nullable|array',
            // Tag audience — send to everyone carrying these tags.
            'tags'                    => 'nullable|array',
            'tags.*'                  => 'integer',
            'groups.*'                => 'integer',
            'manual_numbers'          => 'nullable|string',
            'csv_file'                => 'nullable|file|mimes:csv,txt|max:5120',
            // Rich CUSTOM-campaign media — an image / video / document that
            // rides WITH the caption (custom_message) + buttons as a product
            // card. Only ONE per campaign (first non-empty wins below).
            'custom_image'            => 'nullable|file|mimes:jpg,jpeg,png|max:2048',     // ≤2MB
            'custom_video'            => 'nullable|file|mimes:mp4|max:16384',             // ≤16MB
            'custom_document'         => 'nullable|file|mimes:pdf,doc,docx|max:16384',    // ≤16MB
        ]);

        // Named → positional normalization for the CUSTOM body. The
        // composer now inserts named tokens ({{order_id}}) for readability;
        // mirror the template path and store POSITIONAL {{1}} + a slot→key
        // map so storage stays canonical. Idempotent: a body that is
        // already positional ({{1}}) is left untouched and keeps whatever
        // custom_message_variable_map the form carried. AttributeResolver
        // resolves both shapes at send time, so this is purely about keeping
        // what we persist consistent with templates.
        [$normMsg, $normMap] = $this->normalizeCustomMessage(
            (string) $request->input('custom_message', ''),
            (string) $request->input('custom_message_variable_map', '')
        );
        $request->merge([
            'custom_message'              => $normMsg,
            'custom_message_variable_map' => $normMap,
        ]);

        // WhatsApp guardrails — screen the campaign's free-text body ONCE
        // (it's the same body for every recipient). No-op unless the admin
        // set /admin/security guardrails to monitor/enforce; monitor only
        // logs. Fail-open inside SendGate. Template campaigns carry no free
        // body here, so this only bites custom/text campaigns.
        try {
            \App\Support\SendGate::screenBody((string) $request->input('custom_message'), [
                'source'       => 'campaign',
                'workspace_id' => (int) (optional($request->user())->current_workspace_id ?? 0),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }


        // Compute total recipients from the union of explicit contact ids and
        // contacts in the selected groups.
        //
        // SECURITY: the explicit recipients[] ids are validated only as
        // integers (no exists/ownership rule), so a forged request could name
        // another tenant's sequential contact ids. Filter them through the
        // caller's workspace exactly like the group-expansion path below does —
        // foreign ids are dropped, same-workspace ids pass through unchanged.
        $rawRecipientIds = collect($request->input('recipients', []))->map(fn ($v) => (int) $v);
        $contactIds = $rawRecipientIds->isEmpty()
            ? collect()
            : Contact::query()->forCurrentWorkspace()
                ->whereIn('id', $rawRecipientIds->all())
                ->pluck('id')->map(fn ($v) => (int) $v);
        $groupIds   = collect($request->input('groups', []))->map(fn ($v) => (string) $v);

        if ($groupIds->isNotEmpty()) {
            // Workspace-scoped — never hydrate another tenant's
            // contacts when expanding the chosen groups.
            $groupMembers = Contact::query()
                ->forCurrentWorkspace()
                ->get(['id', 'contact_group'])
                ->filter(function ($c) use ($groupIds) {
                    $list = is_array($c->contact_group) ? $c->contact_group : [];
                    foreach ($list as $gid) {
                        if ($groupIds->contains((string) $gid)) return true;
                    }
                    return false;
                })
                ->pluck('id');
            $contactIds = $contactIds->merge($groupMembers)->unique()->values();
        }

        // Tag audience — "send to everyone tagged VIP". The audience tile has
        // always ADVERTISED tags ("Use saved segments and tags") but only ever
        // expanded contact_group, so picking a tag silently sent to nobody.
        // Resolved through the contact_tag pivot, workspace-scoped on BOTH
        // sides so a tag id from another tenant can't pull their contacts.
        $tagIds = collect($request->input('tags', []))
            ->map(fn ($v) => (int) $v)->filter()->unique();
        if ($tagIds->isNotEmpty()) {
            $wsId = (int) ($request->user()->current_workspace_id ?? 0);
            $safeTagIds = \App\Models\Tag::query()
                ->where('workspace_id', $wsId)
                ->whereIn('id', $tagIds->all())
                ->pluck('id');
            if ($safeTagIds->isNotEmpty()) {
                $tagged = Contact::query()
                    ->forCurrentWorkspace()
                    ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $safeTagIds->all()))
                    ->pluck('id');
                $contactIds = $contactIds->merge($tagged)->unique()->values();
            }
        }

        // Manual numbers (textarea, one per line / comma-separated) and
        // CSV upload — both pass arbitrary phone numbers that aren't
        // tied to an existing Contact row. We materialise an on-the-fly
        // Contact for each one (so the dispatch pipeline + recipient log
        // stays homogeneous) and merge the new ids into $contactIds.
        $extraNumbers = $this->parseManualNumbers((string) $request->input('manual_numbers', ''));
        if ($request->hasFile('csv_file')) {
            $extraNumbers = $extraNumbers->merge($this->parseCsvNumbers($request->file('csv_file')));
        }
        $extraNumbers = $extraNumbers->map(fn ($n) => preg_replace('/\D+/', '', (string) $n))
            ->filter(fn ($n) => strlen((string) $n) >= 8)
            ->unique()
            ->values();

        if ($extraNumbers->isNotEmpty()) {
            $wsId = (int) (Auth::user()->current_workspace_id ?? 0);
            $uid  = Auth::id();
            foreach ($extraNumbers as $phone) {
                // Auto-save (or reuse) a Contact for every manual/CSV number so
                // it lands in the Contacts table — O(1) dedup by phone hash.
                $c = Contact::rememberPhone($wsId, $uid, (string) $phone, 'Recipient · ' . substr((string) $phone, -4));
                if ($c) {
                    $contactIds->push($c->id);
                }
            }
            $contactIds = $contactIds->unique()->values();
        }

        $totalRecipients = $contactIds->count();

        $scheduleType = $request->input('schedule_type');
        $resolvedStatus = $scheduleType === 'now' ? 'running' : 'scheduled';

        // Provider-aware template gate. WABA needs Meta-approved
        // templates; Baileys/Twilio accept any.
        if ($request->input('campaign_type') === 'template' && $request->input('template_id')) {
            $tpl = \App\Models\WaTemplate::query()
                ->forCurrentWorkspace()
                ->find($request->input('template_id'));
            if (!$tpl) {
                Log::warning('[CAMPAIGN] REJECTED at template gate: template not found', ['template_id' => $request->input('template_id')]);
                return $this->rejectForm($request, 'template_id', 'Template not found.');
            }
            if ($this->requiresApprovedTemplates() && !in_array($tpl->status, ['approved', 'public'], true)) {
                Log::warning('[CAMPAIGN] REJECTED at template gate: WABA needs an approved template', [
                    'template_id' => $tpl->id, 'name' => $tpl->template_name, 'status' => $tpl->status,
                ]);
                return $this->rejectForm($request, 'template_id', 'WABA engine requires a Meta-approved template. This template is "' . $tpl->status . '".');
            }

            // Auth/OTP templates can't be broadcast/campaigned. Each
            // recipient needs a unique verifiable code that only the
            // merchant's own backend can mint. Same safety rail as
            // /broadcasts. Force 1:1 sends via the transactional API.
            if ($tpl->template_type === 'auth') {
                Log::warning('[CAMPAIGN] REJECTED at template gate: auth/OTP template cannot be campaigned', ['template_id' => $tpl->id]);
                return $this->rejectForm($request, 'template_id', 'Authentication (OTP) templates cannot be sent via campaign — each recipient needs a unique verifiable code. Send them 1:1 from your backend using the transactional template send endpoint instead.');
            }

            // WABA-v2 templates also get the full quality/paused/media gate.
            if ($tpl->meta_template_id
                && \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {
                $reasons = [];
                if (strtoupper((string) $tpl->meta_status) !== 'APPROVED') {
                    $reasons[] = "Template is not approved by Meta yet (status: {$tpl->meta_status}).";
                }
                if ($tpl->paused_until && $tpl->paused_until->isFuture()) {
                    $reasons[] = 'Template is paused until ' . $tpl->paused_until->format('Y-m-d H:i') . '.';
                }
                $floor = strtoupper((string) \App\Models\SystemSetting::get('waba_template_quality_floor', 'YELLOW'));
                $rank  = ['UNKNOWN' => 1, 'RED' => 0, 'YELLOW' => 2, 'GREEN' => 3];
                $score = strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN'));
                // UNKNOWN = Meta hasn't rated the template yet — true of EVERY
                // brand-new approved template (it only earns a rating AFTER it
                // starts sending). Blocking it was a chicken-and-egg that stopped
                // any new template from ever being campaigned. Only enforce the
                // quality floor for ACTUAL ratings (RED / YELLOW / GREEN).
                if ($score !== 'UNKNOWN' && ($rank[$score] ?? 1) < ($rank[$floor] ?? 2)) {
                    $reasons[] = "Template quality is {$score} (floor: {$floor}).";
                }
                if (!empty($tpl->attachment_type) && !in_array(strtoupper($tpl->attachment_type), ['NONE','TEXT','LOCATION'], true)
                    && !empty($tpl->attachment_file)) {
                    $url = media_url($tpl->attachment_file);
                    $urlErr = $this->mediaUrlReachableForMeta($url);
                    if ($urlErr) $reasons[] = $urlErr;
                }
                if (!empty($reasons)) {
                    Log::warning('[CAMPAIGN] REJECTED at template gate: WABA-v2 quality/paused/media', [
                        'template_id' => $tpl->id, 'name' => $tpl->template_name, 'reasons' => $reasons,
                    ]);
                    return $this->rejectForm($request, 'template_id', 'Cannot use this template: ' . implode(' ', $reasons));
                }
            }
        }

        // A/B variant B — OWNERSHIP only. The variant-B template/flow ids were
        // persisted + sent WITHOUT a workspace scope, so a forged id could point
        // at another tenant's template/flow. Reject a foreign id here. This is
        // ownership-only (not the full approval gate) so the normal web A/B flow
        // — which only ever offers this workspace's own items — is unaffected;
        // it fires solely on a cross-tenant id.
        if ($request->filled('template_id_b')
            && !\App\Models\WaTemplate::forCurrentWorkspace()->whereKey((int) $request->input('template_id_b'))->exists()) {
            return $this->rejectForm($request, 'template_id_b', 'Variant B template not found in this workspace.');
        }
        if ($request->filled('flow_id_b')
            && !\App\Models\Flow::forCurrentWorkspace()->whereKey((int) $request->input('flow_id_b'))->exists()) {
            return $this->rejectForm($request, 'flow_id_b', 'Variant B flow not found in this workspace.');
        }

        // Rich CUSTOM-campaign media. Store the uploaded file on the `public`
        // disk and remember the path on the matching column. Only ONE media
        // per campaign — first non-empty of image / video / document wins.
        // The column that holds the path also encodes the media TYPE (image
        // → custom_image, etc.), which dispatchCampaignNow reads back below.
        $customImage = $customVideo = $customDocument = null;
        if ($request->hasFile('custom_image')) {
            $customImage = $request->file('custom_image')->store('campaign-media', media_disk());
            Log::warning('[CAMPAIGN] custom media stored', ['type' => 'image', 'path' => $customImage]);
        } elseif ($request->hasFile('custom_video')) {
            $customVideo = $request->file('custom_video')->store('campaign-media', media_disk());
            Log::warning('[CAMPAIGN] custom media stored', ['type' => 'video', 'path' => $customVideo]);
        } elseif ($request->hasFile('custom_document')) {
            $customDocument = $request->file('custom_document')->store('campaign-media', media_disk());
            Log::warning('[CAMPAIGN] custom media stored', ['type' => 'document', 'path' => $customDocument]);
        }

        // Multi-engine: the unified picker posts a composite `engine:id` sender
        // key. Resolve it to the concrete sender id + engine so we persist the
        // engine the operator actually CHOSE (not just the workspace default).
        // When validated, set `provider` explicitly so the model's creating()
        // auto-stamp (which defaults to WorkspaceEngine::for()) is skipped. With
        // no sender key (legacy form) we leave device_id/provider on the old
        // path and the model auto-stamps the default engine as before.
        $wsId = $request->user()->current_workspace_id;
        $pickedDeviceId = $request->filled('device_id') ? (int) $request->input('device_id') : null;
        $pickedProvider = null;
        if ($request->filled('sender')) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $request->input('sender'));
            if ($picked) {
                $pickedDeviceId = (int) $picked['id'];
                $pickedProvider = (string) $picked['engine'];
            }
        }
        // Bare device_id with no composite sender key (REST API / legacy form):
        // a `devices` row is ALWAYS the Unofficial (Baileys) channel, so pin the
        // provider to Baileys. Without this the model's creating() auto-stamps
        // the workspace DEFAULT engine (e.g. Twilio) and the campaign sends on
        // the wrong channel even though a Baileys device was chosen.
        if ($pickedProvider === null && $pickedDeviceId) {
            $pickedProvider = \App\Services\WorkspaceEngine::ENGINE_BAILEYS;
        }

        // Twilio + WABA can't carry inline buttons on a CUSTOM (non-template)
        // send — Twilio requires a Content template, and WABA free-form
        // interactive only works inside the 24h window. Drop them so they're
        // never stored or shipped on those engines (the composer hides the
        // Buttons section client-side too, so this is the server backstop).
        if (in_array($pickedProvider, ['twilio', 'waba'], true)) {
            $request->merge(['custom_buttons' => [], 'custom_quick_replies' => []]);
        }

        $campaign = WpCampaign::create([
            'workspace_id'         => $request->user()->current_workspace_id,
            'campaign_name'        => $request->input('campaign_name'),
            'device_id'            => $pickedDeviceId,
            'provider'             => $pickedProvider,
            'campaign_type'        => $request->input('campaign_type'),
            'status'               => $request->input('status') ?: $resolvedStatus,
            'ab_testing'           => (bool) $request->boolean('ab_testing'),
            'ab_split'             => (int) ($request->input('ab_split') ?? 50),
            'custom_message'       => $request->input('custom_message'),
            'custom_message_b'     => $request->input('custom_message_b'),
            'custom_header'        => $request->input('custom_header'),
            'custom_footer'        => $request->input('custom_footer'),
            'custom_buttons'       => $request->input('custom_buttons'),
            'custom_quick_replies' => $request->input('custom_quick_replies'),
            // Persist the {{1}}→attribute slot map so SCHEDULED/RECURRING sends
            // (which fire later from the row) resolve positional vars too.
            'custom_variable_map'  => $request->input('custom_message_variable_map'),
            'custom_image'         => $customImage,
            'custom_video'         => $customVideo,
            'custom_document'      => $customDocument,
            'template_id'          => $request->input('template_id'),
            'template_id_a'        => $request->input('template_id_a'),
            'template_id_b'        => $request->input('template_id_b'),
            'template_overrides_b' => \App\Services\TemplateOverrideResolver::sanitize(
                $request->input('template_overrides_b')
            ),
            'template_overrides'   => \App\Services\TemplateOverrideResolver::sanitize(
                $request->input('template_overrides')
            ),
            'flow_id'              => $request->input('flow_id'),
            'flow_id_b'            => $request->input('flow_id_b'),
            'use_attributes'       => (bool) $request->boolean('use_attributes'),
            'tracking_enabled'     => $request->has('tracking_enabled') ? (bool) $request->boolean('tracking_enabled') : true,
            'schedule_type'        => $scheduleType,
            'send_date'            => $request->input('send_date'),
            'send_time'            => $request->input('send_time'),
            // Never store a bare UTC for a local workspace — the active-hours
            // window + scheduling are interpreted in THIS timezone, so fall back
            // to the workspace's own tz (then the app default) when none is sent.
            'timezone'             => $request->input('timezone')
                ?: (optional($request->user()?->currentWorkspace)->timezone ?: config('app.timezone', 'UTC')),
            // Recurring cadence — only meaningful when schedule_type=recurring.
            'repeat_interval'      => $scheduleType === 'recurring' ? ($request->input('repeat_interval') ?: 'weekly') : null,
            'repeat_until'         => $scheduleType === 'recurring' ? $request->input('repeat_until') : null,
            // Optional per-campaign END DATE — hard stop for sending. Interpreted
            // in the campaign's timezone, stored UTC. Blank => admin default applies.
            'expires_at'           => $this->resolveCampaignExpiry($request),
            // Smart Delivery (anti-ban) — null when left blank => global default.
            'throttle_min_sec'     => $request->filled('throttle_min_sec') ? (int) $request->input('throttle_min_sec') : null,
            'throttle_max_sec'     => $request->filled('throttle_max_sec') ? (int) $request->input('throttle_max_sec') : null,
            'batch_size'           => $request->filled('batch_size') ? (int) $request->input('batch_size') : null,
            'batch_pause_min'      => $request->filled('batch_pause_min') ? (int) $request->input('batch_pause_min') : null,
            'daily_limit'          => $request->filled('daily_limit') ? (int) $request->input('daily_limit') : null,
            'window_start'         => $request->filled('window_start') ? substr((string) $request->input('window_start'), 0, 5) : null,
            'window_end'           => $request->filled('window_end') ? substr((string) $request->input('window_end'), 0, 5) : null,
            'total_recipients'     => $totalRecipients,
            'created_by'           => optional($request->user())->id,
        ]);

        // Pre-create per-recipient log rows so the detail page has something
        // to render. Each row starts in 'queued' status. When A/B testing is
        // on, assign each recipient a variant ('A'/'B') by ab_split (% to A)
        // — shuffled so the split is random but honours the exact ratio, and
        // PERSISTED so resume passes keep the same assignment.
        $abOn    = (bool) $campaign->ab_testing;
        $abSplit = max(0, min(100, (int) ($campaign->ab_split ?? 50)));
        $allIds  = collect($contactIds)->all();
        $variantMap = [];
        if ($abOn) {
            $shuffled = $allIds;
            shuffle($shuffled);
            $countA = (int) round(count($shuffled) * $abSplit / 100);
            foreach ($shuffled as $i => $cid) {
                $variantMap[$cid] = $i < $countA ? 'A' : 'B';
            }
        }
        foreach ($allIds as $cid) {
            WpCampaignContact::create([
                'campaign_id' => $campaign->id,
                'contact_id'  => $cid,
                'status'      => 'queued',
                'variant'     => $abOn ? ($variantMap[$cid] ?? 'A') : null,
            ]);
        }

        // Hand the campaign off to the dispatcher. For schedule_type='now'
        // we fire immediately; scheduled / recurring stay queued until a
        // worker picks them up (cron worker not yet wired — they'll show
        // as 'scheduled' in the UI).
        // CHECKPOINT: if you see THIS line but never "dispatchCampaignNow queued",
        // the `if ($scheduleType==='now')` branch isn't being taken. If you DON'T
        // see this line at all (only "recipients resolved"), the request aborts
        // between recipient-resolution and here — OR the new code isn't live
        // (deploy + RELOAD PHP-FPM: optimize:clear does NOT clear opcache).
        if ($scheduleType === 'now') {
            $this->dispatchCampaignNow($campaign, $contactIds, $request->input('campaign_type'), [
                'template_id'          => $request->input('template_id'),
                'custom_message'       => $request->input('custom_message'),
                'custom_header'        => $request->input('custom_header'),
                'custom_footer'        => $request->input('custom_footer'),
                'custom_buttons'       => $request->input('custom_buttons'),
                'custom_quick_replies' => $request->input('custom_quick_replies'),
                'custom_variable_map'  => $request->input('custom_message_variable_map'),
                // Send-time overrides must reach the Unofficial path too —
                // it does its own substitution and was ignoring them entirely.
                'template_overrides'   => $campaign->template_overrides,
            ]);
        }

        $message = match ($scheduleType) {
            'now'       => 'Campaign launched.',
            'recurring' => 'Recurring campaign saved.',
            default     => 'Campaign scheduled.',
        };

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => $message,
                'campaign' => $campaign,
                'redirect' => route('user.wa-campaigns.detail', $campaign->id),
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $message);
    }

    /**
     * Normalize a custom-campaign body's placeholders to POSITIONAL and
     * (re)build its flat {slot => key} variable map.
     *
     * The composer inserts NAMED tokens ({{order_id}}) for readability;
     * this converts them to {{1}}, {{2}}… in first-appearance order and
     * returns a JSON map { "1":"order_id" } so storage matches the
     * template path. AttributeResolver already resolves both shapes, so
     * this only affects what we persist, not what gets sent.
     *
     * Idempotency / back-compat:
     *   - If the body has NO named token (it's empty or already positional
     *     {{1}}), return it unchanged together with the ORIGINAL map JSON —
     *     never renumber an existing positional body or clobber its map.
     *   - Mixed bodies: named tokens get key = token name; a bare numeric
     *     token (a generic {{1}} chip) has no attribute identity and maps
     *     to the literal number at its first-appearance slot.
     *
     * @param  string $body     raw custom_message
     * @param  string $mapJson  raw custom_message_variable_map (flat JSON)
     * @return array{0:string,1:string}  [normalizedBody, normalizedMapJson]
     */
    private function normalizeCustomMessage(string $body, string $mapJson): array
    {
        if ($body === '' || preg_match('/\{\{\s*[a-zA-Z_][\w.-]*\s*\}\}/u', $body) !== 1) {
            // No named tokens → leave body + map exactly as submitted.
            return [$body, $mapJson];
        }

        // First-appearance order → {token => slot}. Numeric and named
        // tokens share one sequence (matches the template normalizer).
        $order   = [];   // token => slot(int)
        $map     = [];   // slot(string) => key
        $newBody = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_][\w.-]*)\s*\}\}/u',
            function ($m) use (&$order, &$map) {
                $token = $m[1];
                if (!isset($order[$token])) {
                    $slot = count($order) + 1;
                    $order[$token] = $slot;
                    // Named token → key is the name; bare numeric chip →
                    // literal number (unmapped slot).
                    $map[(string) $slot] = $token;
                }
                return '{{' . $order[$token] . '}}';
            },
            $body
        );

        return [(string) $newBody, json_encode((object) $map, JSON_UNESCAPED_SLASHES)];
    }

    /**
     * Mirrors BroadcastsController::mediaUrlReachableForMeta — Meta
     * cannot fetch http://, private IPs, or .local/.test hosts. Pre-
     * flight here so a campaign with media-header doesn't burn quota
     * + credits 1-by-1 with #131053 before the operator sees the
     * failure.
     */
    private function mediaUrlReachableForMeta(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return "Media URL '$url' is invalid.";
        $scheme = strtolower($parts['scheme'] ?? 'http');
        if ($scheme !== 'https') {
            return "Media URL must be HTTPS for Meta to fetch it (got: {$scheme}). Configure APP_URL with an https:// public domain.";
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPrivate = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPrivate) return "Media URL host '$host' is a private/reserved IP. Meta cannot reach it.";
        } else {
            foreach (['.local', '.test', '.internal', '.localhost'] as $bad) {
                if (str_ends_with($host, $bad)) return "Media URL host '$host' ends with $bad which Meta cannot resolve.";
            }
            if ($host === 'localhost') return "Media URL host is 'localhost'. Meta cannot reach it.";
        }
        return null;
    }

    private function parseManualNumbers(string $raw): \Illuminate\Support\Collection
    {
        if (trim($raw) === '') return collect();
        return collect(preg_split('/[\s,;]+/', $raw))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values();
    }

    private function parseCsvNumbers($file): \Illuminate\Support\Collection
    {
        $out = collect();
        if (!$file) return $out;
        $handle = @fopen($file->getRealPath(), 'r');
        if (!$handle) return $out;
        $headers = null;
        $row = 0;
        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1) {
                // Detect headers — if first row contains any letters
                // outside digits/+/- assume it's a header row.
                $headers = array_map(fn ($c) => strtolower(trim((string) $c)), $cols);
                $hasHeader = false;
                foreach ($cols as $c) {
                    if (preg_match('/^(name|phone|mobile|number|contact)$/i', trim((string) $c))) {
                        $hasHeader = true; break;
                    }
                }
                if ($hasHeader) continue;
                $headers = null;
            }
            // Find phone column — by header name if available, else first
            // column that looks digit-y.
            $phoneIdx = 0;
            if ($headers) {
                foreach (['phone', 'mobile', 'number'] as $key) {
                    $idx = array_search($key, $headers, true);
                    if ($idx !== false) { $phoneIdx = $idx; break; }
                }
            }
            $phone = trim((string) ($cols[$phoneIdx] ?? ''));
            if ($phone !== '') $out->push($phone);
        }
        fclose($handle);
        return $out;
    }

    /**
     * Resolve the message body for a campaign — picks template body
     * (with `{{name}}` per-contact + `{{promo_key}}` workspace
     * substitution) or the custom_message field. Returned body is
     * per-contact since {{name}} differs per row.
     */
    private function resolveCampaignBody(Contact $contact, string $type, array $payload, int $workspaceId = 0): string
    {
        // Body resolution rules:
        //   - template type → use the template's stored template_body verbatim.
        //     Templates already have their own structure baked in; the campaign's
        //     custom_header / custom_footer are NOT applied (they belong to
        //     text/custom/button types).
        //   - everything else → use custom_message. Bold header is prepended,
        //     but the footer is NOT appended here — the dispatcher passes
        //     `footer` as a separate Baileys field, which is the native slot
        //     under the buttons. Appending it here would render it twice
        //     on the recipient's screen.
        $tpl = null;
        if ($type === 'template' && !empty($payload['template_id'])) {
            $tpl  = \App\Models\WaTemplate::query()->find($payload['template_id']);
            $full = (string) ($tpl?->template_body ?? '');
        } else {
            // Header/footer travel as separate Baileys fields (title +
            // footer slots) — see WhatsAppDispatcher::mergeButtonsFooter.
            // We don't bold-prepend them to the body here; Baileys will
            // render them in the proper UI slots above and below.
            $full = (string) ($payload['custom_message'] ?? '');
        }

        // Workspace-attribute substitution first ({{promo_key}}, {{order_id}},
        // positional {{1}} via variable_map). This is what /team-inbox does
        // — and matches the AttributeResolver pass on dispatcher paths so
        // operators get consistent behaviour everywhere.
        if ($workspaceId > 0) {
            // Template campaigns carry their slot→attribute mapping on the
            // template row. CUSTOM campaigns carry it in the composer's
            // `custom_message_variable_map` (the `/`-picker's {{1}}→key map),
            // threaded through here as `custom_variable_map`. Pick whichever
            // applies so positional {{1}} slots resolve to real values on
            // BOTH paths — and custom sends never ship a literal {{1}}.
            $variableMap = $tpl ? $tpl->variable_map : ($payload['custom_variable_map'] ?? null);
            if (is_string($variableMap)) {
                $decoded = json_decode($variableMap, true);
                $variableMap = is_array($decoded) ? $decoded : [];
            }
            $variableMap = is_array($variableMap) ? $variableMap : [];
            $full = app(\App\Services\AttributeResolver::class)->resolve($full, $variableMap, $workspaceId);
        }

        // Per-contact substitution second: {{name}}, {{first_name}}, etc.
                //
        // Tolerant matcher — accepts ANY of:
        //   {{first_name}}  {{First Name}}  {{FIRST NAME}}  {{ first name }}
        // and falls back to the contact's own custom_attributes JSON when
        // the placeholder name isn't a built-in field. That way an operator
        // can write `Hey {{First Name}}, your code is {{Promo Code}}` in the
        // composer (the natural reading form) without having to map every
        // placeholder to a numbered {{1}}/{{2}} slot first. We previously
        // only matched exact lowercase snake_case keys (`{{first_name}}`),
        // so the screenshot's `{{First Name}}` / `{{Promo Code}}` / etc.
        // shipped as literal text.
        // Combined / display name — falls back to first+last when contact.name
        // is blank (some import paths populate parts but not the joined column).
        $combinedName = (string) ($contact->name
            ?? trim(((string) ($contact->first_name ?? '')) . ' ' . ((string) ($contact->last_name ?? '')))
        );

        $stdFields = [
            'name'         => $combinedName,
            'full_name'    => $combinedName,                              // alias — the natural form
            'fullname'     => $combinedName,                              // alias (no-space variant)
            'display_name' => $combinedName,                              // alias (some clients use this)
            'first_name'   => (string) ($contact->first_name ?? ''),
            'firstname'    => (string) ($contact->first_name ?? ''),     // alias
            'last_name'    => (string) ($contact->last_name ?? ''),
            'lastname'     => (string) ($contact->last_name ?? ''),      // alias
            'mobile'       => (string) ($contact->mobile ?? ''),
            'phone'        => (string) ($contact->mobile ?? ''),
            'email'        => (string) ($contact->email ?? ''),
            'address'      => (string) ($contact->address ?? ''),
            'language'     => (string) ($contact->language ?? ''),
            'title'        => (string) ($contact->title ?? ''),
            'country_code' => (string) ($contact->country_code ?? ''),
        ];
        // Per-contact custom_attributes (the JSON column populated when the
        // operator adds free-form key/value pairs on /contacts). Casefolded
        // + space-stripped so placeholder text matches regardless of casing.
        $customAttrs = $contact->custom_attributes;
        if (is_string($customAttrs)) {
            $d = json_decode($customAttrs, true);
            $customAttrs = is_array($d) ? $d : [];
        }
        $customAttrs = is_array($customAttrs) ? $customAttrs : [];

        // Build a lookup that normalises every key (spaces → underscores,
        // lowercased, surrounding whitespace trimmed) so the placeholder
        // form the operator wrote can be matched in one pass.
        $normalisedLookup = [];
        foreach ($stdFields as $k => $v) {
            $normalisedLookup[strtolower(str_replace(' ', '_', $k))] = (string) $v;
        }
        foreach ($customAttrs as $k => $v) {
            $key = strtolower(str_replace(' ', '_', (string) $k));
            if (! isset($normalisedLookup[$key])) {
                $normalisedLookup[$key] = is_scalar($v) ? (string) $v : '';
            }
        }

        // Single regex pass over every {{anything}} in the body. Skips pure
        // numeric tokens like {{1}} / {{2}} — those are positional slots
        // handled by the variable_map block below, not by-name substitution.
        $full = (string) preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/u',
            function ($m) use ($normalisedLookup) {
                $rawKey = trim($m[1]);
                if ($rawKey === '' || preg_match('/^\d+$/', $rawKey)) {
                    return $m[0]; // leave {{N}} for the positional pass below
                }
                $key = strtolower(str_replace(' ', '_', $rawKey));
                if (array_key_exists($key, $normalisedLookup)) {
                    return $normalisedLookup[$key];
                }
                return $m[0]; // unknown placeholder — preserve so operator sees it
            },
            $full
        );

        // Positional {{N}} → the attribute the operator mapped this slot to in
        // the template's variable_map → THIS contact's value. AttributeResolver
        // above already filled slots mapped to WORKSPACE attributes; this fills
        // slots mapped to CONTACT fields / per-contact custom attributes (which
        // the workspace resolver can't see). This is the campaign-path twin of
        // BroadcastsController::varsForRecipient — so {{1}}/{{2}} personalize
        // identically whether the operator assigns them a workspace OR contact
        // attribute. The literal {{N}} stays in template_body for Meta.
        // Source the slot map from the template (nested header/body shape)
        // OR, for a CUSTOM campaign, from the composer's flat
        // {"1":"first_name"} map threaded in via custom_variable_map.
        $vmSource = $tpl ? $tpl->variable_map : ($payload['custom_variable_map'] ?? null);
        if ($vmSource && str_contains($full, '{{')) {
            $vm = $vmSource;
            if (is_string($vm)) {
                $d = json_decode($vm, true);
                $vm = is_array($d) ? $d : [];
            }
            // Both shapes (template nested / composer flat) go through the ONE
            // shared flattener. This block used to hand-roll it, which is how
            // the three copies drifted apart in the first place.
            $flat = \App\Services\TemplateOverrideResolver::flattenMap($vm);
            if ($flat) {
                $custom = $contact->custom_attributes;
                if (is_string($custom)) {
                    $d = json_decode($custom, true);
                    $custom = is_array($d) ? $d : [];
                }
                $custom = is_array($custom) ? $custom : [];
                $std = [
                    'name'       => $contact->name,
                    'first_name' => $contact->first_name,
                    'last_name'  => $contact->last_name,
                    'mobile'     => $contact->mobile,
                    'phone'      => $contact->mobile,
                    'email'      => $contact->email,
                    'address'    => $contact->address ?? null,
                    'title'      => $contact->title ?? null,
                ];
                // Build the same contact array the Meta path uses, then defer to
                // TemplateOverrideResolver. The old hand-rolled lookup only knew
                // a hardcoded field list — it could not see workspace attributes
                // or system tokens, and it normalised nothing, so a slot mapped
                // to `company_name` never resolved on this engine.
                $contactArr = array_merge($std, [
                    'id'                => $contact->id,
                    'custom_attributes' => $custom,
                ]);
                $res = app(\App\Services\TemplateOverrideResolver::class);

                // Send-time overrides apply on THIS engine too. Without this the
                // whole "fill in this send" panel was silently Meta-only.
                $ovr = [];
                if ($tpl && is_array($payload['template_overrides'] ?? null)) {
                    $ovr = $payload['template_overrides'];
                }
                $ovrBody = is_array($ovr['body'] ?? null) ? array_values($ovr['body']) : [];

                foreach ($flat as $slot => $key) {
                    $typed = $ovrBody[((int) $slot) - 1] ?? '';
                    $val = (is_string($typed) && trim($typed) !== '')
                        ? $res->render($typed, $contactArr, $workspaceId)
                        : $res->lookup($key, $contactArr, $workspaceId);
                    if ($val !== '') {
                        $full = preg_replace('/\{\{\s*' . preg_quote((string) $slot, '/') . '\s*\}\}/', $val, $full);
                    }
                }
            }
        }

        // FINAL SWEEP — never let a raw placeholder reach a customer.
        //
        // Previously an unresolved slot was left as the literal text "{{1}}"
        // and WhatsApp delivered exactly that ("Hi {{1}}, we saved you an
        // offer at {{2}}."). A contact with no first_name, or a bare phone
        // number with no contact record at all, hit this every time.
        //
        // Order: the template's own example for that slot → a friendly
        // greeting fallback for name-ish slots → drop it. Then tidy the
        // punctuation the removal leaves behind ("Hi ," → "Hi,").
        if (str_contains($full, '{{')) {
            $examples = [];
            $slotKeys = [];
            if ($tpl && is_array($tpl->variable_map)) {
                foreach (['header', 'body'] as $sec) {
                    foreach ((array) ($tpl->variable_map[$sec] ?? []) as $e) {
                        if (is_array($e) && isset($e['num'])) {
                            $examples[(string) $e['num']] = (string) ($e['example'] ?? '');
                            $slotKeys[(string) $e['num']] = (string) ($e['key'] ?? '');
                        }
                    }
                }
            }
            $full = (string) preg_replace_callback(
                \App\Services\TemplateOverrideResolver::TOKEN_RE,
                function ($m) use ($examples, $slotKeys) {
                    $raw = trim((string) $m[1]);
                    if (($examples[$raw] ?? '') !== '') return $examples[$raw];
                    // A positional slot carries its meaning in variable_map, so
                    // translate {{1}} → first_name BEFORE deciding — otherwise
                    // every numeric slot looks unnameable and we'd emit "Hi,".
                    $named = ctype_digit($raw) ? ($slotKeys[$raw] ?? $raw) : $raw;
                    $k = \App\Services\TemplateOverrideResolver::normalizeKey($named);
                    // A name slot reading "Hi ," looks broken; "Hi there," doesn't.
                    if (in_array($k, ['name', 'first_name', 'full_name'], true)) return 'there';
                    return '';
                },
                $full
            );
            // Collapse the artefacts an empty substitution leaves.
            $full = preg_replace('/[ \t]{2,}/', ' ', $full);
            $full = preg_replace('/\s+([,.!?;:])/u', '$1', $full);
            $full = preg_replace('/([,;:])\s*([,.!?;:])/u', '$2', $full);
            $full = trim((string) $full);
        }

        return $full;
    }

    /**
     * Public entry for a "send now" campaign. Hands the actual recipient loop
     * off to run AFTER the HTTP response is flushed, so the request returns
     * instantly instead of the browser hanging while the paced loop sleeps
     * between recipients (msg_gap can be a minute or more). The anti-ban gap
     * can only work if we don't block the request — the pacing itself lives in
     * runCampaignNowPaced().
     */
    private function dispatchCampaignNow(WpCampaign $campaign, $contactIds, string $type, array $payload): void
    {
        // Diagnostic: this line proves store() reached the dispatch. The send
        // itself is deferred to AFTER the HTTP response (afterResponse) so the
        // page returns instantly. If you see THIS log but never "afterResponse
        // fired" below, the server isn't running terminating callbacks for this
        // request (PHP-FPM request_terminate_timeout / proxy buffering / Octane)
        // — in which case we fall back to a synchronous run.

        $run = function () use ($campaign, $contactIds, $type, $payload) {
            @set_time_limit(0);
            @ignore_user_abort(true);
            try {
                $this->runCampaignNowPaced($campaign, $contactIds, $type, $payload);
            } catch (\Throwable $e) {
                Log::error('[CAMPAIGN] runCampaignNowPaced threw', [
                    'campaign_id' => $campaign->id,
                    'err'         => $e->getMessage(),
                    'at'          => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        };

        // Prefer after-response (instant page). If the runtime can't defer
        // (no fastcgi_finish_request — e.g. some CLI/proxy setups), run inline
        // so the campaign ALWAYS sends instead of silently never dispatching.
        if (function_exists('fastcgi_finish_request')) {
            dispatch($run)->afterResponse();
        } else {
            Log::warning('[CAMPAIGN] no fastcgi_finish_request — running send inline', ['campaign_id' => $campaign->id]);
            $run();
        }
    }

    /**
     * Record a TRANSIENT send failure (network/provider/Node-down) with
     * bounded exponential backoff. While attempts remain, the row stays
     * non-terminal with a future next_attempt_at so a later sweeper pass
     * retries it; only once the cap is hit does it become a terminal
     * failure that counts toward failed_count. failed_count is therefore
     * incremented exactly once per recipient (on final give-up), never per
     * retry.
     */
    private function recordSendFailure(?WpCampaignContact $logRow, WpCampaign $campaign, string $errMsg, int $maxAttempts, int $retryBackoff): void
    {
        $err = mb_substr($errMsg, 0, 191);
        if (! $logRow) {
            $campaign->increment('failed_count');
            return;
        }

        // Meta's marketing-frequency throttle (131049 "healthy ecosystem") is NOT
        // a transient network error — Meta is deliberately holding marketing
        // messages to this recipient. Retrying it every few minutes is futile AND
        // drags down the number's quality rating, which makes Meta throttle EVEN
        // harder. So for this code: cap attempts low and space the retry HOURS out
        // (not minutes), then stamp it terminal at the GLOBAL cap so
        // reconcileStuckMetaBlocks can never resurrect and re-poke Meta with it.
        // (130429 throughput / 131056 pair-rate are transient rate limits and stay
        // on the normal short backoff below — they clear on their own.)
        $lc = strtolower($err);
        $isEcoThrottle = str_contains($lc, 'healthy ecosystem')
            || str_contains($lc, '131049');

        $attempts     = (int) ($logRow->send_attempts ?? 0) + 1;
        $effectiveCap = $isEcoThrottle ? min($maxAttempts, 2) : $maxAttempts;

        if ($attempts < $effectiveCap) {
            // Ecosystem throttle: 6h → 12h (cap 24h). Everything else: normal
            // exponential backoff base, base*2, … capped at 1 hour.
            $delay = $isEcoThrottle
                ? min(86400, 21600 * (2 ** ($attempts - 1)))
                : min(3600, $retryBackoff * (2 ** ($attempts - 1)));
            $logRow->update([
                'status'          => 'failed',
                'send_attempts'   => $attempts,
                'next_attempt_at' => now()->addSeconds($delay),
                'error_message'   => $err,
            ]);
            return;
        }
        // Retries exhausted — terminal failure. An ecosystem throttle is stamped
        // at the GLOBAL cap (not just its own low cap) so the reconcile sweep,
        // which resurrects rows with send_attempts < maxAttempts, never re-pokes
        // Meta with a message it has explicitly chosen to throttle.
        $logRow->update([
            'status'          => 'failed',
            'send_attempts'   => $isEcoThrottle ? $maxAttempts : $attempts,
            'next_attempt_at' => null,
            'error_message'   => $err,
        ]);
        $campaign->increment('failed_count');
    }

    /**
     * PARALLEL drain of a WABA approved-template campaign — the "as fast as
     * WATI/AiSensy" path. Mirrors the sequential per-recipient fast path exactly
     * (skip-sent, retry backoff, daily cap, active window, operator halt,
     * plan-first billing, inbox mirror, warmer, durable-retry failure recording)
     * but sends recipients in CONCURRENT batches via TemplateSender::sendMany()
     * (Http::pool). That converts a latency-bound ~5-10/sec sequential blast into
     * a throughput-bound one that saturates Meta's ~80/sec-per-number ceiling — a
     * 1000-recipient WABA campaign finishes in seconds.
     *
     * Caller gates this to non-A/B, official-engine, APPROVED-template campaigns
     * with the parallel flag on, and wraps the call in try/catch so ANY error
     * falls back to the proven sequential loop (already-sent rows are skipped, so
     * a fallback never double-sends). Budget/cap/window all honoured between
     * batches so it re-arms exactly like the sequential path.
     *
     * @return array{stopReason:?string,resumeInSec:int,sentThisRun:int,operatorHalted:bool,campaignExpired:bool}
     */
    private function drainWabaTemplateParallel($contacts, array $ctx): array
    {
        $campaign        = $ctx['campaign'];
        $tplCache        = $ctx['tplCache'];
        $wsId            = (int) $ctx['wsId'];
        $maxAttempts     = (int) $ctx['maxAttempts'];
        $retryBackoff    = (int) $ctx['retryBackoff'];
        $billWsObj       = $ctx['billWsObj'];
        $billCampaignIds = $ctx['billCampaignIds'];
        $warmer          = $ctx['warmer'];
        $warmEnabled     = (bool) $ctx['warmEnabled'];
        $warmDevice      = $ctx['warmDevice'];
        $deadlineAt      = $ctx['deadlineAt'];
        $dailyLimit      = (int) $ctx['dailyLimit'];
        $tz              = $ctx['tz'];
        $winStart        = $ctx['winStart'];
        $winEnd          = $ctx['winEnd'];
        $runStart        = (int) $ctx['runStart'];
        $maxRunSec       = (int) $ctx['maxRunSec'];
        $sendOverrides   = $ctx['sendOverrides'];

        $stopReason = null; $resumeInSec = 0; $sentThisRun = 0;
        $operatorHalted = false; $campaignExpired = false;

        $concurrency = max(1, min(30, (int) \App\Models\SystemSetting::get('campaign_parallel_concurrency', 15)));
        $sender      = new \App\Services\Waba\TemplateSender();
        $headerType  = strtoupper((string) ($tplCache->attachment_type ?: 'TEXT'));

        // SAME per-recipient vars pipeline the sequential fast path uses.
        $bcCtl = app(\App\Http\Controllers\BroadcastsController::class);
        $ref   = new \ReflectionClass($bcCtl);
        $varsM = $ref->getMethod('varsForRecipient');    $varsM->setAccessible(true);
        $wrapM = $ref->getMethod('wrapUrlsForRecipient'); $wrapM->setAccessible(true);

        // Plan-first billing counter — base once, +1 per bill (rows aren't flushed
        // to 'sent' until the batch completes, so a per-recipient re-query would be
        // stale within a batch; the local counter matches the sequential total).
        $usedBase = 0;
        if ($billWsObj) {
            $usedBase = WpCampaignContact::query()
                ->whereIn('campaign_id', $billCampaignIds)
                ->whereIn('status', ['sent', 'delivered', 'read', 'responded'])
                ->where('updated_at', '>=', now()->startOfMonth())->count();
        }
        $billed = 0;

        $batch = [];   // contactId => ['logRow'=>, 'to'=>, 'vars'=>]

        $flush = function () use (&$batch, $sender, $tplCache, $concurrency, $campaign, $wsId, $warmer, $warmEnabled, $warmDevice, $maxAttempts, $retryBackoff) {
            if (empty($batch)) return;
            $recipients = [];
            foreach ($batch as $cid => $b) {
                $recipients[] = ['id' => $cid, 'to' => $b['to'], 'vars' => $b['vars']];
            }
            $results = $sender->sendMany($tplCache, $recipients, null, $concurrency);
            foreach ($batch as $cid => $b) {
                $res = $results[$cid] ?? ['ok' => false, 'error' => 'no result from pool', 'code' => 'meta_error'];
                if (!empty($res['ok'])) {
                    $b['logRow']?->update([
                        'status'              => 'sent',
                        'sent_at'             => now(),
                        'whatsapp_message_id' => $res['wamid'] ?? null,
                    ]);
                    $campaign->increment('sent_count');
                    if ($warmEnabled) { $warmer->recordSend($warmDevice); }
                    try {
                        $td = \App\Services\Whatsapp\TemplateDataBuilder::build($tplCache, (int) $wsId);
                        app(\App\Services\Inbox\InboxMirror::class)->appendOutboundToOpenConversation(
                            (int) $wsId, (string) $b['to'],
                            \App\Services\Inbox\InboxMirror::readableTemplateBody($td),
                            $res['wamid'] ?? null, 'waba',
                            array_filter([
                                'type'          => 'template',
                                'template_name' => $tplCache->template_name,
                                'buttons'       => (is_array($tplCache->buttons) && $tplCache->buttons) ? $tplCache->buttons : null,
                                'campaign_id'   => $campaign->id,
                            ], fn ($v) => $v !== null),
                        );
                    } catch (\Throwable $e) { /* best-effort mirror */ }
                } else {
                    $this->recordSendFailure($b['logRow'], $campaign, (string) ($res['error'] ?? 'unknown'), $maxAttempts, $retryBackoff);
                }
            }
            $batch = [];
        };

        foreach ($contacts as $contact) {
            if (in_array(WpCampaign::where('id', $campaign->id)->value('status'), ['cancelled', 'paused'], true)) {
                $flush(); $operatorHalted = true; break;
            }
            if ($deadlineAt && $deadlineAt->isPast()) { $flush(); $campaignExpired = true; break; }

            $logRow = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)->where('contact_id', $contact->id)->first();

            if ($logRow && in_array($logRow->status, ['sent', 'delivered', 'read', 'responded'], true)) continue;
            if ($logRow && (int) ($logRow->send_attempts ?? 0) >= $maxAttempts) continue;
            if ($logRow && $logRow->next_attempt_at && now()->lt($logRow->next_attempt_at)) continue;

            if ($winStart && $winEnd && !$this->withinSendWindow($tz, $winStart, $winEnd)) { $flush(); $stopReason = 'window'; break; }
            if ($dailyLimit > 0 && $sentThisRun >= $dailyLimit) { $flush(); $stopReason = 'cap'; break; }
            if ($warmEnabled) { $wm = $warmer->canSend($warmDevice); if (!$wm['ok']) { $flush(); $stopReason = 'warmer'; break; } }

            // Wall-clock budget — flush the in-flight batch, then stop + resume ASAP.
            if ((time() - $runStart) >= $maxRunSec) { $flush(); $stopReason = 'time'; $resumeInSec = 0; break; }

            $to = $contact->mobile;
            if (!$to) { $this->recordPermanentFailure($logRow, $campaign, 'No mobile number on contact', $maxAttempts); continue; }

            $contactArr = [
                'id'                => $contact->id,
                'phone'             => $to,
                'first_name'        => $contact->first_name,
                'last_name'         => $contact->last_name,
                'name'              => $contact->name,
                'email'             => $contact->email,
                'title'             => (string) ($contact->title ?? ''),
                'middle_name'       => (string) ($contact->middle_name ?? ''),
                'address'           => (string) ($contact->address ?? ''),
                'language'          => (string) ($contact->language ?? ''),
                'contact_group'     => is_array($contact->contact_group)
                    ? implode(', ', array_filter($contact->contact_group, 'is_scalar'))
                    : (string) ($contact->contact_group ?? ''),
                'country_code'      => preg_replace('/\D+/', '', (string) ($contact->country_code ?? '')),
                'mobile'            => preg_replace('/\D+/', '', (string) ($contact->mobile ?? '')),
                'custom_attributes' => is_array($contact->custom_attributes) ? $contact->custom_attributes : [],
            ];
            $vars = $varsM->invoke($bcCtl, $tplCache, $contactArr, (int) $wsId, $sendOverrides);
            $vars = $wrapM->invoke($bcCtl, $vars, [
                'workspace_id' => (int) $wsId,
                'campaign_id'  => $campaign->id,
                'contact_id'   => $contact->id,
                'template_id'  => $tplCache->id,
                'phone'        => $to,
            ]);
            if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
                && empty($vars['header_media_url']) && empty($vars['header_media_id'])) {
                $campMedia = $campaign->custom_image ?: $campaign->custom_video ?: $campaign->custom_document;
                if (!empty($campMedia)) $vars['header_media_url'] = media_url($campMedia);
            }
            $vars['_tracking'] = ['campaign_id' => $campaign->id, 'contact_id' => $contact->id];

            // Plan-first billing (identical model to the sequential path).
            try {
                if ($billWsObj) {
                    \App\Services\OverflowBilling::consumeOne($billWsObj, $usedBase + $billed, $to, 'marketing');
                    $billed++;
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $this->recordPermanentFailure($logRow, $campaign, 'Plan cap reached — top up wallet to keep sending', $maxAttempts);
                continue;
            }

            $sentThisRun++;
            $batch[$contact->id] = ['logRow' => $logRow, 'to' => $to, 'vars' => $vars];
            if (count($batch) >= $concurrency) { $flush(); }
        }
        $flush();   // final partial batch

        return compact('stopReason', 'resumeInSec', 'sentThisRun', 'operatorHalted', 'campaignExpired');
    }

    /**
     * Record a PERMANENT failure that retrying can't fix (bad number, empty
     * body, plan cap reached). Stamp send_attempts at the cap so the resume
     * loop treats it as terminal — never re-attempted, never counted as
     * "retryable" (which would stop the campaign ever completing).
     */
    /**
     * Why a WABA / Twilio campaign could not take its provider's native send
     * path. Checks the SAME preconditions as the TemplateSender fast-path, in
     * order, and returns the FIRST unmet one as an operator-readable sentence.
     *
     * Exists because that fast-path is guarded by six separate conditions — when
     * any one failed, the run fell through to the Unofficial-API route and
     * finished "sent=0 failed=0" with nothing logged, indistinguishable from
     * "no recipients". Now the operator is told exactly what to fix.
     */
    private function providerPathUnavailableReason($campaign, bool $isTemplate, $tplCache, string $engine): string
    {
        $tplName = (string) ($tplCache->template_name ?? $tplCache->name ?? 'the selected template');

        if ($engine === 'twilio') {
            return $isTemplate
                ? 'Twilio send failed: template "' . $tplName . '" has no approved Content template (ContentSid) mapped.'
                : 'Twilio campaigns must use an approved Content template — this campaign sends a custom message.';
        }

        // WABA (WhatsApp Cloud API)
        if (! $isTemplate) {
            return 'WhatsApp Cloud (Official API) campaigns must use an approved template. This campaign sends a custom message, which Meta only permits inside an open 24-hour customer session.';
        }
        if (! $tplCache) {
            return 'The template selected for this campaign no longer exists. Re-select a template and save the campaign.';
        }
        if (empty($tplCache->meta_template_id)) {
            return 'Template "' . $tplName . '" has not been submitted to Meta yet (no meta_template_id). Open Templates, submit it, and wait for Meta approval.';
        }
        if (strtoupper((string) $tplCache->meta_status) !== 'APPROVED') {
            return 'Template "' . $tplName . '" is "' . ($tplCache->meta_status ?: 'not approved') . '" at Meta. Only APPROVED templates can be sent on the Official API.';
        }
        if (! \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {
            return 'WhatsApp Cloud template sending is disabled platform-wide. Enable "waba_templates_v2_enabled" in Admin → Settings → WhatsApp.';
        }
        return 'The WhatsApp Cloud send path is unavailable for this campaign (provider "' . $engine . '").';
    }

    private function recordPermanentFailure(?WpCampaignContact $logRow, WpCampaign $campaign, string $errMsg, int $maxAttempts): void
    {
        $campaign->increment('failed_count');
        $logRow?->update([
            'status'          => 'failed',
            'send_attempts'   => $maxAttempts,
            'next_attempt_at' => null,
            'error_message'   => mb_substr($errMsg, 0, 191),
        ]);
    }

    /**
     * End a campaign that has passed its send window (expiry deadline). Marks
     * every recipient that hasn't been sent yet as terminal so it is NEVER
     * messaged, then completes the campaign so the sweeper never re-arms it.
     *
     * The mass update goes through the query builder (bypassing model casts) —
     * that's SAFE here because error_message uses the SafeEncrypted cast, which
     * reads the plaintext we write back unchanged (same pattern the status
     * webhook already relies on). One statement instead of N model saves.
     */
    private function endExpiredCampaign(WpCampaign $campaign, int $maxAttempts, int $expiryHrs, ?\Illuminate\Support\Carbon $deadlineAt): void
    {
        $expired = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded', 'unsubscribed'])
            ->update([
                'status'          => 'failed',
                'send_attempts'   => $maxAttempts,
                'next_attempt_at' => null,
                'error_message'   => 'Not sent — campaign ended after its ' . $expiryHrs . 'h send window (auto-expired)',
                'updated_at'      => now(),
            ]);
        $campaign->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    }

    /**
     * Parse the optional per-campaign "end date" field into a UTC datetime (or
     * null). The datetime-local value is read in the campaign's own timezone
     * (the same tz used for send_date / active-hours) so "5 PM" means 5 PM to
     * the operator, not UTC. Blank => the admin default auto-end applies.
     */
    private function resolveCampaignExpiry(Request $request): ?string
    {
        if (!$request->filled('expires_at')) return null;
        $tz = $request->input('timezone')
            ?: (optional($request->user()?->currentWorkspace)->timezone ?: config('app.timezone', 'UTC'));
        try {
            return \Illuminate\Support\Carbon::parse((string) $request->input('expires_at'), $tz)
                ->utc()->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Iterate the campaign's recipients and fire each through the
     * dispatcher's `sendRaw` API, pacing between sends with the admin's
     * msg_gap / batch settings. NO rows written to `conversations`
     * or `messages` — campaign data lives entirely in `wp_campaigns`
     * + `wp_campaign_contacts`. The chat tables (`conversations` +
     * `messages`) stay clean and are only ever touched by /chat.
     *
     * Failures get logged into the WpCampaignContact row's
     * `status` / `error_message` columns.
     */
    private function runCampaignNowPaced(WpCampaign $campaign, $contactIds, string $type, array $payload): void
    {
        // Never message a contact who opted out (STOP keyword or the manual
        // unsubscribe toggle). Keep false + null (never-set); drop only
        // explicit unsubscribes. This is the binding compliance filter — it
        // runs for both immediate and scheduled campaigns (both land here).
        $contacts = Contact::query()->whereIn('id', $contactIds)
            ->where(function ($q) {
                $q->where('is_unsubscribed', false)->orWhereNull('is_unsubscribed');
            })
            ->get();

        // Recipients the compliance filter just dropped are UNSUBSCRIBED at the
        // contact level. Their pivot rows were created "queued" at store() time;
        // if left queued they (a) show a stuck "Queued" row that never sends,
        // (b) keep the campaign re-arming to "scheduled" forever (counted as
        // retryable), and (c) never appear on the Opt-outs tab (which reads the
        // pivot flag, not the contact flag) — so the operator sees "0 opted out"
        // next to a permanently-queued recipient. Stamp them terminally here:
        // status='unsubscribed' + is_unsubscribed=true. Now they read as
        // "Unsubscribed", count on Opt-outs, and drop out of the retryable set so
        // the run COMPLETES. (Marked directly on the pivot, phone OR contact id.)
        $keptIds    = $contacts->pluck('id')->all();
        $skippedIds = collect($contactIds)->map(fn ($i) => (int) $i)->filter()->diff($keptIds)->values();
        if ($skippedIds->isNotEmpty()) {
            WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('contact_id', $skippedIds->all())
                ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded', 'unsubscribed'])
                ->update(['status' => 'unsubscribed', 'is_unsubscribed' => true]);
        }

        // Resolve the owner from the CAMPAIGN row, NOT the auth session. This
        // method also runs from the Node-heartbeat sweep (fireScheduledCampaign)
        // where there is no logged-in user — Auth::id() would be null and the
        // device + workspace scoping below would then resolve to nothing
        // ("No connected device"). The campaign always carries its own owner.
        $userId   = $campaign->user_id ?: Auth::id();


        // Flow campaigns aren't a body-send — each recipient gets a new
        // flow session spun up by the Node bridge. Hand off to the
        // dedicated dispatcher so the body-build + dispatcher->sendRaw
        // pipeline below stays for text/template/button/media/custom
        // where the body is the message itself.
        if ($type === 'flow') {
            $this->dispatchFlowCampaign($campaign, $contacts, $userId);
            return;
        }

        // Sender phone — read once from the sender picked in step 1.
        //
        // campaigns.device_id is POLYMORPHIC: for Baileys it is a `devices` row,
        // but the unified picker stores the `wa_provider_configs` id for WABA /
        // Twilio. Resolving it ONLY from `devices` returned NULL on a WABA-only
        // workspace (whose `devices` table is legitimately empty), so sendRaw got
        // from_number = null and the campaign died silently.
        $devicePhone    = null;
        $campaignEngine = strtolower((string) ($campaign->provider ?? ''));
        if ($campaign->device_id && in_array($campaignEngine, ['waba', 'twilio'], true)) {
            $cfg = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $campaign->workspace_id)
                ->where('provider', $campaignEngine)
                ->find($campaign->device_id);
            // Same authoritative-id fallback as the Baileys branch below.
            if (! $cfg) $cfg = \App\Models\WaProviderConfig::query()->find($campaign->device_id);
            if ($cfg) {
                $devicePhone = preg_replace('/\D+/', '', (string) $cfg->phone_number) ?: null;
            } else {
                // Legacy / REST-API rows stored the PHONE NUMBER in device_id
                // instead of the wa_provider_configs id (the unified picker only
                // started emitting row ids in Phase 3, and parseSenderKey still
                // accepts a bare value). Verified against real production rows:
                // `provider=twilio device_id=919783969401`. Match it back to a
                // CONNECTED sender on this workspace+engine so those campaigns
                // resolve a real from_number instead of silently sending nothing.
                $digits = preg_replace('/\D+/', '', (string) $campaign->device_id);
                if ($digits !== '' && strlen($digits) >= 8) {
                    $byPhone = \App\Models\WaProviderConfig::query()
                        ->where('workspace_id', $campaign->workspace_id)
                        ->where('provider', $campaignEngine)
                        ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                        ->get(['id', 'phone_number'])
                        ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->phone_number) === $digits);
                    if ($byPhone) $devicePhone = $digits;
                }
            }
        } elseif ($campaign->device_id) {
            // Scope by the CAMPAIGN's workspace/owner (forWorkspace), not
            // forCurrentWorkspace() which reads the auth session the sweep
            // doesn't have. forWorkspace() also falls back to user ownership
            // for legacy rows whose workspace_id was never stamped.
            $device = \App\Models\Device::query()
                ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                ->find($campaign->device_id);
            // The campaign's device_id is an explicit, stored choice — it is
            // authoritative. If workspace-scoping can't see it (e.g. a device
            // paired before devices.workspace_id was populated → NULL, and the
            // campaign's user_id is also NULL so the ownership fallback misses)
            // do a direct lookup. Otherwise we'd pass a NULL sender and let the
            // dispatcher backfill the workspace's PRIMARY engine number — which
            // for a multi-engine workspace can be a totally different channel.
            if (! $device) {
                $device = \App\Models\Device::query()->find($campaign->device_id);
            }
            if ($device) {
                $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
            }
        }

        // Buttons + footer + header per Baileys interactive-message
        // schema. For template campaigns we read from the template's
        // structured columns so the copy_code / visit_website / etc.
        // types are preserved. For custom/button/text we use the
        // campaign-create form's basic single-button override.
        //
        // Workspace-attribute resolution ({{promo_key}} → "Media City",
        // {{order_id}} → "ORD-12", positional {{1}} via variable_map)
        // happens HERE once, before the per-contact loop — those values
        // are constant across recipients. Contact-level placeholders
        // ({{name}}, {{email}}, …) are substituted later inside
        // resolveCampaignBody per-row.
        $isTemplate = $type === 'template';

        // Rich CUSTOM-campaign media — resolve which (if any) column holds an
        // uploaded file. The column that's set encodes the type. This rides
        // the legacy/sendRaw (Unofficial API) path only; the WABA
        // TemplateSender fast-path below handles its own media headers, so
        // template campaigns skip this. Single media per campaign, image first.
        $mediaPath = $mediaType = null;
        if (!$isTemplate) {
            if (!empty($campaign->custom_image)) {
                $mediaPath = $campaign->custom_image; $mediaType = 'image';
            } elseif (!empty($campaign->custom_video)) {
                $mediaPath = $campaign->custom_video; $mediaType = 'video';
            } elseif (!empty($campaign->custom_document)) {
                $mediaPath = $campaign->custom_document; $mediaType = 'document';
            }
            if ($mediaPath) {
                Log::warning('[CAMPAIGN] custom media will ride sendRaw', [
                    'campaign_id' => $campaign->id, 'media_type' => $mediaType, 'media_path' => $mediaPath,
                ]);
            }
        }

        $tplCache = null;
        if ($isTemplate && !empty($payload['template_id'])) {
            $tplCache = \App\Models\WaTemplate::query()->find($payload['template_id']);
        }

        // PER-SEND header-media OVERRIDE — the operator uploaded/pasted an image
        // for THIS campaign in the "Fill in this send" panel. It must win over
        // the template's stored header image (the old code ignored it entirely
        // on the Unofficial path and always shipped the template's default). The
        // override is a full public URL; the dispatcher now accepts a URL
        // media_path directly (WhatsAppDispatcher media block).
        $ovrHeaderUrl = '';
        $ovrBlob = is_array($payload['template_overrides'] ?? null)
            ? $payload['template_overrides']
            : (is_array($campaign->template_overrides ?? null) ? $campaign->template_overrides : null);
        // Read the override URL from EVERY shape it can take, and don't demand
        // mode==='media' — a non-empty header media_url IS the override. This is
        // deliberately forgiving so a stored-shape variation can't silently drop
        // the operator's uploaded image and fall back to the template default.
        if (is_array($ovrBlob)) {
            if (isset($ovrBlob['header']) && is_array($ovrBlob['header'])) {
                $ovrHeaderUrl = trim((string) ($ovrBlob['header']['media_url'] ?? ''));
            }
            if ($ovrHeaderUrl === '' && !empty($ovrBlob['header_media_url'])) {
                $ovrHeaderUrl = trim((string) $ovrBlob['header_media_url']);
            }
        }
        // DIAGNOSTIC — dumps EXACTLY what the override resolver saw, so
        // "still sends the template image" can be pinned to data (override not
        // saved / wrong shape) vs the send path. Look for this line in the log
        // right before the media decision below.
        if ($isTemplate && $tplCache && !$mediaPath && $ovrHeaderUrl !== '') {
            $mediaPath = $ovrHeaderUrl;
            $mediaType = in_array($tplCache->attachment_type, ['image', 'video', 'document'], true)
                ? $tplCache->attachment_type : 'image';
        }

        // TEMPLATE campaign on the sendRaw (Unofficial API) fallback path:
        // WABA-approved templates use TemplateSender below (own media header),
        // but a NON-approved / Unofficial template still rides sendRaw — so
        // carry the template's HEADER media here or the image ships as text-only.
        // attachment_file is the public-disk relative path ('wa-templates/<file>')
        // which the dispatcher base64-inlines just like custom media. Skipped
        // when an override above already set $mediaPath.
        if ($isTemplate && $tplCache && !$mediaPath
            && !empty($tplCache->attachment_file)
            && in_array($tplCache->attachment_type, ['image', 'video', 'document'], true)) {
            $mediaPath = $tplCache->attachment_file;
            $mediaType = $tplCache->attachment_type;
            Log::warning('[CAMPAIGN] template header media will ride sendRaw fallback', [
                'campaign_id' => $campaign->id, 'media_type' => $mediaType, 'media_path' => $mediaPath,
            ]);
        }

        $wsId = (int) ($campaign->workspace_id ?? Auth::user()->current_workspace_id ?? 0);
        $resolver = app(\App\Services\AttributeResolver::class);
        $variableMap = $tplCache?->variable_map;
        if (is_string($variableMap)) {
            $decoded = json_decode($variableMap, true);
            $variableMap = is_array($decoded) ? $decoded : [];
        }
        $variableMap = is_array($variableMap) ? $variableMap : [];

        $headerRaw = $isTemplate ? ($tplCache?->header ?: null) : ($payload['custom_header'] ?? null);
        $footerRaw = $isTemplate ? ($tplCache?->footer ?: null) : ($payload['custom_footer'] ?? null);
        $headerResolved = $headerRaw ? $resolver->resolve((string) $headerRaw, $variableMap, $wsId) : null;
        $footerResolved = $footerRaw ? $resolver->resolve((string) $footerRaw, $variableMap, $wsId) : null;

        // Resolve button labels — operator can drop {{promo_key}} into a
        // button's `text` field too (used for "Use code XYZ" CTAs).
        // Send-time button overrides, keyed by button index. The operator
        // types a link / coupon code in the "Button values" panel; without
        // this the template's own value was sent regardless and the typed
        // value silently vanished.
        $btnOverrides = [];
        foreach ((array) (($payload['template_overrides']['buttons'] ?? null) ?: []) as $bo) {
            if (is_array($bo) && isset($bo['index']) && trim((string) ($bo['value'] ?? '')) !== '') {
                $btnOverrides[(int) $bo['index']] = (string) $bo['value'];
            }
        }

        $resolveButtons = function ($buttons, bool $applyOverrides = false) use ($resolver, $variableMap, $wsId, $btnOverrides) {
            if (!is_array($buttons)) return $buttons;
            return array_map(function ($b, $i) use ($resolver, $variableMap, $wsId, $btnOverrides, $applyOverrides) {
                if (!is_array($b)) return $b;
                // `value` carries the URL / phone / coupon code — it was
                // missing from this list, so a {{token}} in a button link
                // was never substituted either.
                foreach (['text', 'title', 'url', 'value'] as $f) {
                    if (isset($b[$f]) && is_string($b[$f])) {
                        $b[$f] = $resolver->resolve($b[$f], $variableMap, $wsId);
                    }
                }
                if ($applyOverrides && array_key_exists((int) $i, $btnOverrides)) {
                    $b['value'] = $resolver->resolve($btnOverrides[(int) $i], $variableMap, $wsId);
                    if (isset($b['url'])) $b['url'] = $b['value'];
                }
                return $b;
            }, $buttons, array_keys($buttons));
        };

        $extras = array_filter([
            'buttons' => $resolveButtons(
                $isTemplate
                    ? ($tplCache && is_array($tplCache->buttons) ? $tplCache->buttons : null)
                    : ($payload['custom_buttons'] ?? null),
                true    // Variant A carries this send's button overrides
            ),
            'quick_replies' => $resolveButtons($payload['custom_quick_replies'] ?? null),
            'footer' => $footerResolved,
            'header' => $headerResolved,
            // Carousel cards for the Unofficial-API legacy path — without these
            // a carousel-type template campaign on a non-WABA workspace ships
            // only the body and drops every card.
            'template_type' => ($isTemplate && $tplCache) ? ($tplCache->template_type ?: null) : null,
            'carousel_data' => ($isTemplate && $tplCache && $tplCache->template_type === 'carousel' && !empty($tplCache->carousel_data))
                ? $tplCache->carousel_data : null,
        ], fn ($v) => !empty($v));

        // ==== A/B testing — build the Variant B content bundle ONCE ==========
        // Snapshot the Variant A bundle (above) and, when A/B is on, build the
        // parallel Variant B bundle. The per-recipient loop just re-points the
        // working vars to A or B by the contact's assigned `variant` — the send
        // logic itself is untouched. Template campaigns swap template_id_a→_b
        // (different template + its own buttons/header/carousel); custom-text
        // campaigns swap the body to custom_message_b (media/buttons shared).
        $abOn       = (bool) $campaign->ab_testing;
        $tplCacheA  = $tplCache;   $extrasA = $extras;
        $mediaPathA = $mediaPath;  $mediaTypeA = $mediaType;  $payloadA = $payload;
        $tplCacheB  = $tplCache;   $extrasB = $extras;
        $mediaPathB = $mediaPath;  $mediaTypeB = $mediaType;  $payloadB = $payload;
        if ($abOn) {
            if ($isTemplate && !empty($campaign->template_id_b)) {
                $tplCacheB = \App\Models\WaTemplate::query()->find($campaign->template_id_b);
                $payloadB  = array_merge($payload, ['template_id' => $campaign->template_id_b]);
                // Variant B header media (sendRaw fallback path), mirroring A.
                $mediaPathB = $mediaTypeB = null;
                if ($tplCacheB && !empty($tplCacheB->attachment_file)
                    && in_array($tplCacheB->attachment_type, ['image', 'video', 'document'], true)) {
                    $mediaPathB = $tplCacheB->attachment_file;
                    $mediaTypeB = $tplCacheB->attachment_type;
                }
                // Variant B extras (buttons/footer/header/carousel) from tplCacheB.
                $vmapB = $tplCacheB?->variable_map;
                if (is_string($vmapB)) { $dB = json_decode($vmapB, true); $vmapB = is_array($dB) ? $dB : []; }
                $vmapB = is_array($vmapB) ? $vmapB : [];
                $hdrB  = $tplCacheB?->header ?: null;
                $ftrB  = $tplCacheB?->footer ?: null;
                $extrasB = array_filter([
                    'buttons'       => $resolveButtons($tplCacheB && is_array($tplCacheB->buttons) ? $tplCacheB->buttons : null),
                    'quick_replies' => $resolveButtons($payload['custom_quick_replies'] ?? null),
                    'footer'        => $ftrB ? $resolver->resolve((string) $ftrB, $vmapB, $wsId) : null,
                    'header'        => $hdrB ? $resolver->resolve((string) $hdrB, $vmapB, $wsId) : null,
                    'template_type' => $tplCacheB ? ($tplCacheB->template_type ?: null) : null,
                    'carousel_data' => ($tplCacheB && $tplCacheB->template_type === 'carousel' && !empty($tplCacheB->carousel_data))
                        ? $tplCacheB->carousel_data : null,
                ], fn ($v) => !empty($v));
            } elseif (!$isTemplate && trim((string) ($campaign->custom_message_b ?? '')) !== '') {
                $payloadB = array_merge($payload, ['custom_message' => $campaign->custom_message_b]);
            }
        }

        // Sender pacing — per-campaign "Smart Delivery" overrides win; otherwise
        // fall back to the platform-wide admin knobs (the SAME ones Node uses):
        // msg_gap (seconds between sends), enable_batches + batches_gap (batch
        // size), bw_msg_gap (minutes between batches). This loop runs after the
        // HTTP response (see dispatchCampaignNow), so sleeping here is what
        // actually spaces the messages out without hanging the request.
        $gapSec      = max(0, (int) \App\Models\SystemSetting::get('msg_gap', 3));
        $batchOn     = (bool) \App\Models\SystemSetting::get('enable_batches', false);
        $batchSize   = max(1, (int) \App\Models\SystemSetting::get('batches_gap', 50));
        $batchGapMin = max(0, (int) \App\Models\SystemSetting::get('bw_msg_gap', 5));

        // ── Engine-aware pacing ────────────────────────────────────────────
        // msg_gap / batch pauses are an ANTI-BAN measure for the Unofficial
        // (Baileys) engine — WhatsApp's spam radar fingerprints uniform, high-
        // volume sending on an unofficial socket. The OFFICIAL APIs (WABA Cloud /
        // Twilio) do NOT need it: Meta rate-limits itself at ~80 msg/sec per
        // number and returns 130429/131056 if exceeded, which the durable retry
        // below already backs off on. Applying a 3s/message sleep to a WABA blast
        // throttled it to ~20/min (≈6 msgs per 20s FPM budget) — the reported
        // "sends 10-15% then stalls" bottleneck. For official engines drop the
        // gap to 0 and let Meta/Twilio be the governor. An explicit per-campaign
        // throttle (throttle_min/max) and the WhatsApp Warmer floor still win for
        // BOTH engines, because those are deliberate operator choices.
        $__prov = strtolower((string) ($campaign->provider ?? ''));
        if ($__prov === 'waba' || $__prov === 'twilio') {
            $officialEngine = true;
        } elseif ($__prov === 'baileys') {
            $officialEngine = false;
        } else {
            // Empty/legacy provider = workspace default. Treat as official only
            // when the workspace actually has a CONNECTED WABA/Twilio config so a
            // single-engine WABA workspace (provider left blank) also runs at full
            // speed. Unsure → false (keep the safe anti-ban gap).
            try {
                $officialEngine = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->whereIn('provider', ['waba', 'twilio'])
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->exists();
            } catch (\Throwable $e) {
                $officialEngine = false;
            }
        }

        // Durable auto-retry — a FAILED recipient is retried up to
        // $maxAttempts times with exponential backoff (base * 2^(n-1)),
        // instead of staying terminally failed after one try. The campaign
        // re-arms (status=scheduled) until every recipient is either
        // delivered or has exhausted its attempts; CampaignScheduleSweeper
        // resumes it on the next heartbeat. Set attempts=1 to disable.
        $maxAttempts   = max(1, (int) \App\Models\SystemSetting::get('campaign_retry_attempts', 3));
        $retryBackoff  = max(5, (int) \App\Models\SystemSetting::get('campaign_retry_backoff_sec', 60));

        // ── Campaign END-DATE / expiry ─────────────────────────────────────
        // A campaign must not keep sending for DAYS (the client saw an event blast
        // from the 21st still going on the 29th). The deadline is resolved in
        // priority order:
        //   1. The operator's PER-CAMPAIGN end date (`expires_at`) — a hard stop
        //      that wins over everything, even a recurring/daily-cap campaign.
        //   2. Else, the admin's platform default: auto-end N hours after the FIRST
        //      real send (Settings → System message → "Campaign auto-end"). Only
        //      when that toggle is ON and the campaign isn't DELIBERATELY multi-day
        //      (recurring, a daily cap, or a restricted send-window) — those the
        //      operator chose, so the default must not cut them short.
        // Anchored on the first send (min sent_at) — NOT created_at — so a campaign
        // scheduled well in advance is never expired before it even starts. Past
        // the deadline: mark every un-sent recipient terminal + complete the
        // campaign, so nothing re-arms and no one is messaged after the window.
        $expiryHrs        = max(1, (int) \App\Models\SystemSetting::get('campaign_default_expiry_hours', 24));
        $autoExpiryOn     = (bool) \App\Models\SystemSetting::get('campaign_auto_expiry_enabled', true);
        $deadlineAt       = null;
        if (!empty($campaign->expires_at)) {
            // Operator set an explicit end date → absolute hard stop.
            $deadlineAt = \Illuminate\Support\Carbon::parse($campaign->expires_at);
        } elseif ($autoExpiryOn) {
            $intentionallyPaced = ($campaign->schedule_type === 'recurring')
                || ((int) ($campaign->daily_limit ?? 0) > 0)
                || (!empty($campaign->window_start) && !empty($campaign->window_end));
            if (!$intentionallyPaced) {
                $firstSentAt = WpCampaignContact::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereNotNull('sent_at')
                    ->min('sent_at');
                $deadlineAt = $firstSentAt
                    ? \Illuminate\Support\Carbon::parse($firstSentAt)->addHours($expiryHrs)
                    : null;
            }
        }
        $campaignExpired = false;
        if ($deadlineAt && $deadlineAt->isPast()) {
            // Already past the window on entry → end it without sending anything.
            $this->endExpiredCampaign($campaign, $maxAttempts, $expiryHrs, $deadlineAt);
            return;
        }

        // Per-campaign random delay window (throttle_min/max seconds). When both
        // are set and max>=min>0 we draw a FRESH random_int(min,max) per
        // recipient — a true per-user interval — instead of the global gap ±20%.
        $tMin = (int) ($campaign->throttle_min_sec ?? 0);
        $tMax = (int) ($campaign->throttle_max_sec ?? 0);
        $useRandomDelay = $tMin > 0 && $tMax >= $tMin;

        // Per-campaign batch overrides (size + pause). NULL => keep the global.
        if ((int) ($campaign->batch_size ?? 0) > 0) { $batchOn = true; $batchSize = max(1, (int) $campaign->batch_size); }
        if (($campaign->batch_pause_min ?? null) !== null) { $batchGapMin = max(0, (int) $campaign->batch_pause_min); }

        // Daily cap + active sending window (interpreted in the campaign's own
        // timezone). On hitting either, the run STOPS and re-arms via the
        // sweeper (see end of method) — no multi-hour FPM sleep.
        $dailyLimit  = (int) ($campaign->daily_limit ?? 0);
        $tz          = $campaign->timezone ?: config('app.timezone', 'UTC');
        $winStart    = $campaign->window_start ?: null;   // "HH:MM"
        $winEnd      = $campaign->window_end   ?: null;
        $sentThisRun = 0;
        $stopReason  = null;   // 'cap' | 'window' — drives re-arm vs complete

        $paceIdx = 0;

        // WhatsApp Warmer — per-NUMBER governor layered on the per-campaign
        // pacing above. When the sending number opted into warming, its ramped
        // daily budget + active hours + send-gap floor + spintax apply to EVERY
        // campaign from that number (the per-campaign knobs are per-blast).
        $warmer      = app(\App\Services\WarmerService::class);
        $warmProvider = strtolower((string) ($campaign->provider ?? ''));
        // Engine-aware governor. WABA / Twilio warm the SENDING wa_provider_configs
        // row (Meta still enforces tiers, but the ramp paces volume to protect the
        // quality rating); Unofficial warms its Device. Single-number campaigns
        // leave device_id NULL → fall back to the workspace's primary number for
        // that engine, so warming is never silently skipped.
        if (in_array($warmProvider, ['waba', 'twilio'], true)) {
            try {
                $warmDevice = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->where('provider', $warmProvider)
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->when($campaign->device_id, fn ($q) => $q->where('id', $campaign->device_id))
                    ->orderByDesc('is_primary')->orderByDesc('connected_at')->first();
            } catch (\Throwable $e) { $warmDevice = null; }
        } else {
            $warmDevice = $device ?? null;
            if (!$warmDevice) {
                try {
                    $warmDevice = \App\Models\Device::query()
                            ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                            ->where('status', 'connected')->orderByDesc('id')->first()
                        ?: \App\Models\Device::query()
                            ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                            ->orderByDesc('id')->first();
                } catch (\Throwable $e) { $warmDevice = null; }
            }
        }
        $warmEnabled = $warmDevice && $warmer->enabled($warmDevice);

        // Wall-clock budget. This loop runs in afterResponse() (or the sweep
        // tick); PHP-FPM's request_terminate_timeout HARD-kills the worker after
        // ~30-120s regardless of set_time_limit(0). With a per-message delay,
        // delay × recipients easily exceeds that, so the worker dies mid-loop
        // and ONLY the first recipient(s) get sent. Cap each run: stop cleanly
        // before the kill and re-arm — CampaignScheduleSweeper resumes the rest
        // (idempotent: already-sent recipients are skipped).
        $runStart    = time();
        $maxRunSec   = 20;   // safely under typical FPM timeouts and the 25s sweep lock
        $resumeInSec = 0;    // pending gap to honour when the next chunk resumes
        $operatorHalted = false; // set if the operator Cancels/Pauses mid-run

        // Billing prep — hoisted out of the per-recipient loop. The workspace row
        // and the workspace's campaign-id list don't change mid-run, so re-loading
        // them for EVERY recipient was pure N+1 overhead (Workspace::find + a
        // WpCampaign pluck per message) that shrank the paced chunk under DB load.
        // The monthly-usage COUNT itself still runs per recipient below so the
        // plan-vs-wallet decision stays byte-identical.
        $billWsObj       = \App\Models\Workspace::find($wsId);
        $billCampaignIds = WpCampaign::where('workspace_id', $wsId)->pluck('id')->all();

        // ── PARALLEL FAST PATH ───────────────────────────────────────────────
        // A WABA approved-template, non-A/B campaign can be drained CONCURRENTLY
        // (Http::pool via TemplateSender::sendMany) instead of one Meta round-trip
        // at a time — saturating Meta's ~80/sec-per-number ceiling so a 1000-blast
        // finishes in seconds (WATI/AiSensy parity). Everything else (Baileys /
        // Twilio / custom / non-approved / A/B) stays on the proven sequential
        // loop below. Flag-gated (`campaign_parallel_send`, default on) and wrapped
        // in try/catch: ANY error falls back to the sequential loop, whose
        // skip-sent guard means already-sent recipients are never re-sent.
        $ranParallel = false;
        if ((bool) \App\Models\SystemSetting::get('campaign_parallel_send', true)
            && $officialEngine && !$abOn && $isTemplate && $tplCacheA
            && $tplCacheA->meta_template_id
            && strtoupper((string) $tplCacheA->meta_status) === 'APPROVED'
            && in_array(strtolower((string) ($campaign->provider ?? '')), ['', 'waba'], true)
            && \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {
            try {
                $pr = $this->drainWabaTemplateParallel($contacts, [
                    'campaign' => $campaign, 'tplCache' => $tplCacheA, 'wsId' => $wsId,
                    'maxAttempts' => $maxAttempts, 'retryBackoff' => $retryBackoff,
                    'billWsObj' => $billWsObj, 'billCampaignIds' => $billCampaignIds,
                    'warmer' => $warmer, 'warmEnabled' => $warmEnabled, 'warmDevice' => $warmDevice,
                    'deadlineAt' => $deadlineAt, 'dailyLimit' => $dailyLimit, 'tz' => $tz,
                    'winStart' => $winStart, 'winEnd' => $winEnd,
                    'runStart' => $runStart, 'maxRunSec' => $maxRunSec,
                    'sendOverrides' => $campaign->template_overrides,
                ]);
                $ranParallel     = true;
                $stopReason      = $pr['stopReason'];
                $resumeInSec     = $pr['resumeInSec'];
                $sentThisRun     = $pr['sentThisRun'];
                $operatorHalted  = $pr['operatorHalted'];
                $campaignExpired = $campaignExpired || $pr['campaignExpired'];
            } catch (\Throwable $e) {
                Log::error('[CAMPAIGN] parallel drain threw — falling back to sequential', [
                    'campaign_id' => $campaign->id, 'err' => $e->getMessage(),
                    'at' => $e->getFile() . ':' . $e->getLine(),
                ]);
                $ranParallel = false;
            }
        }

        foreach (($ranParallel ? [] : $contacts) as $contact) {
            // Operator hit Cancel/Pause while this paced batch was mid-flight →
            // stop NOW; the guard after the loop then SKIPS the re-arm so a
            // stopped campaign never "auto-restarts". $campaign was loaded before
            // the batch started, so re-read the LIVE status. Cheap indexed lookup,
            // and pacing already spaces sends seconds apart.
            if (in_array(WpCampaign::where('id', $campaign->id)->value('status'), ['cancelled', 'paused'], true)) {
                $operatorHalted = true;
                break;
            }

            // Crossed the campaign end-date mid-batch → stop; the guard after the
            // loop ends the campaign. Uses the precomputed deadline (no query).
            if ($deadlineAt && $deadlineAt->isPast()) {
                $campaignExpired = true;
                break;
            }

            $logRow = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('contact_id', $contact->id)
                ->first();

            // Skip recipients already sent on a PRIOR run (idempotent resume +
            // crash-safety). A re-fired campaign — after a daily-cap/window
            // pause, or after the Node bridge restarted mid-run — never
            // double-sends to anyone already delivered.
            if ($logRow && in_array($logRow->status, ['sent', 'delivered', 'read', 'responded'], true)) {
                continue;
            }

            // Retry exhausted — this recipient hit the attempt cap (or was a
            // permanent/data failure stamped at the cap). Terminal: never
            // re-attempt, and it no longer counts toward "retryable" below.
            if ($logRow && (int) ($logRow->send_attempts ?? 0) >= $maxAttempts) {
                continue;
            }

            // Backoff not elapsed — a prior attempt failed and this row's
            // next_attempt_at is still in the future. Defer to a later run;
            // the campaign re-arms to the earliest due time at the end.
            if ($logRow && $logRow->next_attempt_at && now()->lt($logRow->next_attempt_at)) {
                continue;
            }

            // Active sending window — outside the allowed hours we stop and
            // re-arm to the next window open (covers "send only 9am–9pm").
            if ($winStart && $winEnd && !$this->withinSendWindow($tz, $winStart, $winEnd)) {
                $stopReason = 'window';
                break;
            }
            // Daily cap — once this run has sent its quota, stop and resume
            // tomorrow. This is what makes a 1000+ blast safe on the Unofficial
            // API (daily volume is the #1 ban driver).
            if ($dailyLimit > 0 && $sentThisRun >= $dailyLimit) {
                $stopReason = 'cap';
                break;
            }

            // WhatsApp Warmer — per-number governor. Over the number's ramped
            // daily budget or outside its active hours → stop + re-arm (the
            // sweeper resumes next window/day). Protects the NUMBER's reputation
            // across every campaign it sends.
            if ($warmEnabled) {
                $wm = $warmer->canSend($warmDevice);
                if (!$wm['ok']) { $stopReason = 'warmer'; break; }
            }

            // Space out sends: per-message gap before every recipient after the
            // first. Per-campaign throttle draws a fresh random delay in its
            // [min,max] window; otherwise the global gap ±20% jitter (so the
            // timing isn't fingerprint-uniform). Plus the longer batch gap every
            // $batchSize messages when batching is on.
            // Wall-clock budget — stop cleanly + re-arm BEFORE PHP-FPM's
            // request_terminate_timeout hard-kills this afterResponse worker.
            // UNCONDITIONAL (not gated on a >0 gap) because the official-engine
            // fast path below sends with NO delay, so the gap-based budget check
            // would never fire and a 1000-recipient WABA blast would run past the
            // FPM kill and strand the rest at 'running'. Resume ASAP (gap already
            // satisfied) so the next heartbeat continues the drain.
            if ($paceIdx > 0 && (time() - $runStart) >= $maxRunSec) {
                $stopReason  = 'time';
                $resumeInSec = 0;
                break;
            }

            if ($paceIdx > 0) {
                // Per-message gap. Official engines (WABA/Twilio) need none —
                // Meta/Twilio self-throttle. Unofficial keeps the admin msg_gap
                // ±20% jitter. An explicit per-campaign throttle wins for BOTH
                // engines (deliberate operator choice), as does the Warmer floor.
                if ($useRandomDelay) {
                    $thisGap = random_int($tMin, $tMax);
                } elseif ($officialEngine) {
                    $thisGap = 0;
                } else {
                    $thisGap = $gapSec > 0 ? max(1, (int) round($gapSec * (1 + random_int(-20, 20) / 100))) : 0;
                }
                // Batch pause is an anti-ban device too — skip it for official
                // engines, keep it for Unofficial.
                if (!$officialEngine && $batchOn && $batchGapMin > 0 && ($paceIdx % $batchSize) === 0) {
                    $thisGap += $batchGapMin * 60;
                }
                // Warmer per-number gap floor — keep sends at least this far apart
                // regardless of the campaign's own (possibly faster) pacing.
                if ($warmEnabled) {
                    $thisGap = max($thisGap, $warmer->gapSeconds($warmDevice));
                }
                // Never sleep past the run budget — stop cleanly + re-arm instead
                // of risking an FPM hard-kill that strands the rest unsent.
                if ($thisGap > 0 && (time() - $runStart) + $thisGap > $maxRunSec) {
                    $stopReason  = 'time';
                    $resumeInSec = $thisGap;
                    break;
                }
                if ($thisGap > 0) {
                    sleep($thisGap);
                }
            }
            $paceIdx++;

            // A/B variant select — re-point ALL content inputs to this
            // recipient's assigned variant. Reset from the A/B snapshots every
            // iteration so an A after a B never inherits B's content. The send
            // logic below uses $tplCache/$payload/$extras/$mediaPath/$mediaType
            // unchanged — only their source flips here.
            $useB      = $abOn && $logRow && $logRow->variant === 'B';
            $tplCache  = $useB ? $tplCacheB  : $tplCacheA;
            $extras    = $useB ? $extrasB    : $extrasA;
            $mediaPath = $useB ? $mediaPathB : $mediaPathA;
            $mediaType = $useB ? $mediaTypeB : $mediaTypeA;
            $payload   = $useB ? $payloadB   : $payloadA;

            // Send-time overrides must flip with the variant too. Everything
            // above already does; this did NOT — variant B was sent with
            // variant A's mapping applied to B's template. With two different
            // templates their variable names differ, so B's fields came out
            // blank or, worse, filled from the wrong slot.
            //
            // When B reuses A's template the variables are identical, so A's
            // mapping is the right default and the operator doesn't have to
            // fill the same form twice. Only a genuinely different template
            // needs (and gets) its own. If B has no mapping of its own we send
            // it with NONE rather than A's — blank beats wrong.
            $sameTemplate = $tplCacheB && $tplCacheA && (int) $tplCacheB->id === (int) $tplCacheA->id;
            $sendOverrides = $useB
                ? ($sameTemplate
                    ? $campaign->template_overrides
                    : ($campaign->template_overrides_b ?: null))
                : $campaign->template_overrides;

            $to = $contact->mobile;
            if (!$to) {
                Log::warning('[CAMPAIGN] skipping — no mobile', ['contact_id' => $contact->id]);
                $this->recordPermanentFailure($logRow, $campaign, 'No mobile number on contact', $maxAttempts);
                continue;
            }
            $body = $this->resolveCampaignBody($contact, $type, $payload, $wsId);
            // Warmer spintax — expand {a|b|c} for per-message variety so a blast
            // isn't byte-identical to every recipient (only when the number opted in).
            if ($warmEnabled) { $body = $warmer->applySpin($warmDevice, $body); }
            if (trim($body) === '') {
                $this->recordPermanentFailure($logRow, $campaign, 'Empty message body after template/variable resolution', $maxAttempts);
                continue;
            }

            // Click tracking for the NON-WABA engines. Until now LinkTracker
            // was only reached from the WABA template path (URL buttons), and
            // that path is itself behind waba_templates_v2_enabled — so an
            // Unofficial-API or Twilio campaign shipped raw URLs and
            // `clicked_count` sat at zero forever no matter how many people
            // tapped. Those engines send a plain text body, so the links live
            // in the text: rewrite them here, inside the per-recipient loop,
            // which is the only place campaign_id + contact_id are both known
            // and attribution is therefore per-recipient rather than per-blast.
            //
            // wrap() no-ops on tel:/mailto:, on our own /r/ shortlinks, and
            // when tracking is switched off — so this is a pass-through in
            // every case where it shouldn't fire.
            try {
                $body = \App\Services\Waba\LinkTracker::wrapInText($body, [
                    'workspace_id' => (int) $wsId,
                    'campaign_id'  => (int) $campaign->id,
                    'contact_id'   => (int) $contact->id,
                    'phone'        => $to,
                ]);
            } catch (\Throwable $e) {
                // Tracking is an analytics nicety — never let it stop a send.
                Log::warning('[CAMPAIGN] link tracking skipped', [
                    'campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'error' => $e->getMessage(),
                ]);
            }
            // This recipient is a real send attempt — count it toward the daily
            // cap (bad-data skips above don't burn the quota).
            $sentThisRun++;

            // Plan-first billing — identical model to every other surface
            // (OverflowBilling, used by WhatsAppDispatcher + InboxDispatcher):
            // each send is FREE while the workspace is under its plan's
            // monthly_messages_limit, and only spends ONE wallet credit once the
            // plan quota is exhausted. NO wallet pre-gate: an active plan must
            // not be blocked by an empty wallet. $used = this workspace's
            // campaign sends already marked this calendar month; it grows as the
            // loop marks rows 'sent', so it self-tracks per recipient.
            try {
                $wsObj = $billWsObj;
                if ($wsObj) {
                    $usedThisMonth = WpCampaignContact::query()
                        ->whereIn('campaign_id', $billCampaignIds)
                        ->whereIn('status', ['sent', 'delivered', 'read', 'responded'])
                        ->where('updated_at', '>=', now()->startOfMonth())
                        ->count();
                    // Campaigns are bulk outreach → bill at the recipient's
                    // country MARKETING rate (the safe/expensive tier; admin can
                    // tune per country). No-ops to flat when per-country is OFF.
                    \App\Services\OverflowBilling::consumeOne($wsObj, $usedThisMonth, $to, 'marketing');
                    Log::warning('[CAMPAIGN TRACE] billing ok (plan-first)', [
                        'campaign_id' => $campaign->id,
                        'contact_id'  => $contact->id,
                        'to'          => $to,
                        'used_month'  => $usedThisMonth,
                    ]);
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                Log::warning('[CAMPAIGN TRACE] billing BLOCKED — plan cap reached + wallet empty', [
                    'campaign_id' => $campaign->id,
                    'contact_id'  => $contact->id,
                    'to'          => $to,
                ]);
                $this->recordPermanentFailure($logRow, $campaign, 'Plan cap reached — top up wallet to keep sending', $maxAttempts);
                continue;
            }

            // FAST PATH — when this is a WABA template campaign AND
            // the template has been submitted to Meta + approved, send
            // through TemplateSender. It builds the FULL Meta payload
            // (buttons / carousel / media headers / auth OTP), wraps
            // URLs via LinkTracker, runs ban-prevention gates, fires
            // outbound webhooks. Bypasses the legacy sendRaw path
            // which only built header+body text params and dropped
            // every button / carousel / media header silently.
            //
            // IMPORTANT: when TemplateSender path is selected, we COMMIT
            // to it. An exception → mark failed + refund + continue,
            // NOT fall through to dispatcher->sendRaw which would
            // double-charge AND send a degraded text-only message.
            // Multi-engine: this fast-path sends via Meta Cloud (WABA). Only take
            // it when the campaign is actually on WABA — otherwise a campaign the
            // operator pinned to the Unofficial API / Twilio whose template ALSO
            // happens to be WABA-approved would be silently force-routed through
            // Meta Cloud, ignoring the chosen engine. Empty provider == legacy /
            // workspace-default (unchanged for single-engine WABA workspaces).
            $campaignProvider = strtolower((string) ($campaign->provider ?? ''));
            $usedTemplateSender = false;
            if ($isTemplate && $tplCache
                && $tplCache->meta_template_id
                && strtoupper((string) $tplCache->meta_status) === 'APPROVED'
                && ($campaignProvider === '' || $campaignProvider === 'waba')
                && \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {

                $usedTemplateSender = true;  // commit BEFORE try so exception still skips legacy

                $contactArr = [
                    'id'                => $contact->id,
                    'phone'             => $to,
                    'first_name'        => $contact->first_name,
                    'last_name'         => $contact->last_name,
                    'name'              => $contact->name,
                    'email'             => $contact->email,
                    // Kept in lockstep with the broadcast recipient row + the
                    // SendAttributes catalog — every token the send screen
                    // offers has to resolve here too.
                    'title'             => (string) ($contact->title ?? ''),
                    'middle_name'       => (string) ($contact->middle_name ?? ''),
                    'address'           => (string) ($contact->address ?? ''),
                    'language'          => (string) ($contact->language ?? ''),
                    // Cast is encrypted:ARRAY — a (string) cast throws here.
                    'contact_group'     => is_array($contact->contact_group)
                        ? implode(', ', array_filter($contact->contact_group, 'is_scalar'))
                        : (string) ($contact->contact_group ?? ''),
                    'country_code'      => preg_replace('/\D+/', '', (string) ($contact->country_code ?? '')),
                    'mobile'            => preg_replace('/\D+/', '', (string) ($contact->mobile ?? '')),
                    'custom_attributes' => is_array($contact->custom_attributes) ? $contact->custom_attributes : [],
                ];

                try {
                    $bcCtl = app(\App\Http\Controllers\BroadcastsController::class);
                    $ref   = new \ReflectionClass($bcCtl);
                    $varsM = $ref->getMethod('varsForRecipient'); $varsM->setAccessible(true);
                    $wrapM = $ref->getMethod('wrapUrlsForRecipient'); $wrapM->setAccessible(true);

                    // 4th arg = this campaign's send-time overrides (NULL for
                    // every pre-existing row → identical behaviour).
                    // $sendOverrides is the variant-correct blob resolved above,
                    // NOT $campaign->template_overrides — that one is variant A's.
                    $vars = $varsM->invoke($bcCtl, $tplCache, $contactArr, (int) $wsId, $sendOverrides);
                    $vars = $wrapM->invoke($bcCtl, $vars, [
                        'workspace_id' => (int) $wsId,
                        'campaign_id'  => $campaign->id,
                        'contact_id'   => $contact->id,
                        'template_id'  => $tplCache->id,
                        'phone'        => $to,
                    ]);

                    // Image/Video/Document-header templates need a media URL per
                    // send. varsForRecipient sources it from the template's stored
                    // sample (attachment_file), but a template IMPORTED from Meta
                    // has none — Meta's sync gives us the header FORMAT, never the
                    // sample bytes. Without an image the send fails with Meta's
                    // "header: Format mismatch, expected IMAGE, received UNKNOWN".
                    // Fall back to the media the user uploaded on THIS campaign
                    // (custom_image/video/document), same source the legacy path uses.
                    $headerType = strtoupper((string) ($tplCache->attachment_type ?: ''));

                    // WABA SAFETY: a per-send override may have set header_media_url
                    // to a link Meta CANNOT fetch (HTTP / private-IP — e.g. a LAN
                    // install). Shipping that to Meta 131053-fails the send. If it's
                    // unreachable, drop it so the fallback below restores the
                    // template's own (public) header image — WABA behaves exactly as
                    // before. Unofficial is unaffected: it base64-inlines and never
                    // hands Meta a URL.
                    if (!empty($vars['header_media_url'])
                        && $this->mediaUrlReachableForMeta((string) $vars['header_media_url']) !== null) {
                        unset($vars['header_media_url']);
                    }

                    if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
                        && empty($vars['header_media_url']) && empty($vars['header_media_id'])) {
                        $campMedia = $campaign->custom_image
                            ?: $campaign->custom_video
                            ?: $campaign->custom_document;
                        if (!empty($campMedia)) {
                            $vars['header_media_url'] = media_url($campMedia);
                        } else {
                            Log::warning('[CAMPAIGN][WABA-TPL] media-header template has NO image — send will fail Meta format check', [
                                'campaign' => $campaign->id, 'template' => $tplCache->template_name, 'header_type' => $headerType,
                            ]);
                        }
                    }


                    // Click attribution. TemplateSender wraps URL buttons through
                    // LinkTracker, but it only knows workspace/template/phone —
                    // the extra IDs have to be handed to it via `_tracking`.
                    // Without this the shortlink row is written with a NULL
                    // campaign_id, so a real customer click could never be
                    // attributed back to this campaign and `clicked_count`
                    // stayed 0 forever (which is why Clicks / CTR / CPC on the
                    // analytics page were structurally always zero).
                    $vars['_tracking'] = [
                        'campaign_id' => $campaign->id,
                        'contact_id'  => $contact->id,
                    ];

                    $sender = new \App\Services\Waba\TemplateSender();
                    $res    = $sender->send($tplCache, $to, $vars);


                    if ($res['ok']) {
                        $logRow?->update([
                            'status'              => 'sent',
                            'sent_at'             => now(),
                            'whatsapp_message_id' => $res['wamid'] ?? null,
                        ]);
                        $campaign->increment('sent_count');
                        // Warmer: count this number's send ONLY on confirmed success —
                        // no double-count on retry, no budget spent on a failed send.
                        if ($warmEnabled) { $warmer->recordSend($warmDevice); }

                        // Mirror into the team-inbox thread when this recipient
                        // already has an open conversation (cold contacts are
                        // skipped inside the mirror — no inbox flood). Campaign
                        // sends synchronously so we pass the wamid → the mirrored
                        // bubble also earns delivery/read ticks.
                        try {
                            $td = \App\Services\Whatsapp\TemplateDataBuilder::build($tplCache, (int) $wsId);
                            app(\App\Services\Inbox\InboxMirror::class)->appendOutboundToOpenConversation(
                                (int) $wsId,
                                (string) $to,
                                \App\Services\Inbox\InboxMirror::readableTemplateBody($td),
                                $res['wamid'] ?? null,
                                'waba',
                                array_filter([
                                    'type'          => 'template',
                                    'template_name' => $tplCache->template_name,
                                    // Buttons so the inbox bubble renders the same
                                    // CTA rows (Check Now / Reply STOP / etc.) the
                                    // customer saw — matching a team-inbox template
                                    // send. Without this the WABA campaign mirror
                                    // dropped every button and showed plain text.
                                    'buttons'       => (is_array($tplCache->buttons) && $tplCache->buttons) ? $tplCache->buttons : null,
                                    'campaign_id'   => $campaign->id,
                                ], fn ($v) => $v !== null),
                            );
                        } catch (\Throwable $e) { /* best-effort mirror — never break the send */ }
                    } else {
                        $this->recordSendFailure($logRow, $campaign, (string) ($res['error'] ?? 'unknown'), $maxAttempts, $retryBackoff);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[CAMPAIGN] TemplateSender threw — marking failed (NOT falling back to legacy)', [
                        'err' => $e->getMessage(),
                        'campaign' => $campaign->id,
                        'contact' => $contact->id,
                    ]);
                    $this->recordSendFailure($logRow, $campaign, 'TemplateSender exception: ' . $e->getMessage(), $maxAttempts, $retryBackoff);
                }
            }
            if ($usedTemplateSender) continue;

            // A WABA / Twilio campaign that reaches here did NOT take its
            // provider's native send path. The legacy sendRaw route below is
            // Unofficial-API only — its from_number is a Baileys device — so it
            // would deliver NOTHING while the run still finished "sent=0
            // failed=0" with no error recorded anywhere. Refuse it and record a
            // real per-recipient failure naming the exact unmet precondition.
            if (in_array($campaignEngine, ['waba', 'twilio'], true)) {
                $reason = $this->providerPathUnavailableReason($campaign, $isTemplate, $tplCache, $campaignEngine);
                Log::error('[CAMPAIGN] provider path unavailable — refusing legacy sendRaw', [
                    'campaign_id' => $campaign->id,
                    'contact_id'  => $contact->id,
                    'engine'      => $campaignEngine,
                    'reason'      => $reason,
                    'is_template' => $isTemplate,
                    'tpl_meta_id' => $tplCache->meta_template_id ?? null,
                    'tpl_status'  => $tplCache->meta_status ?? null,
                    'v2_enabled'  => (bool) \App\Models\SystemSetting::get('waba_templates_v2_enabled', false),
                ]);
                $this->recordPermanentFailure($logRow, $campaign, $reason, $maxAttempts);
                continue;
            }

            try {
                // Send WITHOUT creating Conversation/Message rows.
                // Dispatcher builds a transient Message in memory only.
                $result = $this->dispatcher->sendRaw([
                    'from_number' => $devicePhone,
                    'to_number'   => $to,
                    'body'        => $body,
                    // Rich product-card: when the campaign carries an uploaded
                    // image/video/document, hand the path to the dispatcher so
                    // it routes to /api/send-media-message (media + this body as
                    // caption + meta buttons), not the plain-text endpoint.
                    'media_path'  => $mediaPath,
                    'media_type'  => $mediaType,
                    'meta'        => $extras ?: null,
                    // Multi-engine route: stamp the engine the operator chose
                    // for THIS campaign (Phase 3 wpcampaigns.provider) so the
                    // dispatcher routes Baileys/WABA/Twilio per-record instead
                    // of by the workspace-wide default. Empty/legacy campaigns
                    // (provider == workspace default) route exactly as before.
                    'provider'    => $campaign->provider,
                    // Carry the campaign's workspace so the dispatcher's
                    // forWorkspace($msg->workspace_id, $msg->user_id) device
                    // lookup resolves in the auth-less sweep context too.
                    'workspace_id' => $campaign->workspace_id,
                ], $userId, 'W');

                // `local_only` means the dispatcher stored the row but NOTHING
                // left the server (provider disabled, emergency halt, …). It
                // still returns ok=true, so counting on ok alone marked those
                // recipients "sent" — a FALSE success the operator could never
                // detect. Treat it as a failure carrying the dispatcher's reason.
                $reallySent = (($result['ok'] ?? false) === true) && (($result['local_only'] ?? false) !== true);
                if ($reallySent) {
                    $logRow?->update([
                        'status'              => 'sent',
                        'sent_at'             => now(),
                        'whatsapp_message_id' => $result['provider_id'] ?? null,
                    ]);
                    $campaign->increment('sent_count');

                    // Mirror into the team inbox RIGHT HERE, the instant the
                    // message leaves. This loop is the Unofficial/Twilio send
                    // path — PHP paces it in-process — so the bubble appears
                    // immediately instead of waiting on Node's status callback.
                    //
                    // Mirroring off the callback made appearance depend on a
                    // round-trip that could be delayed or dropped, which is why
                    // sends showed up minutes late or not at all. The callback
                    // hook stays as a backstop; InboxMirror de-dupes so the two
                    // paths can never double-post.
                    try {
                        // $body is EXACTLY what went to the dispatcher a few
                        // lines above — already variable-resolved ("Hi there,"
                        // not "Hi {{1}},") and footer-appended. Use it verbatim
                        // so the inbox bubble matches the customer's screen.
                        //
                        // The previous fallback rebuilt the text from the
                        // template via readableTemplateBody(), which returns it
                        // AS WRITTEN — that is why the thread showed raw {{1}}
                        // while WhatsApp showed the resolved name.
                        $mBody = (string) $body;
                        // Prepend the template header when the send carried one,
                        // matching the bold title WhatsApp renders above the body.
                        $mHeader = trim((string) ($tplCache->header ?? ''));
                        if ($mHeader !== '' && $mBody !== '' && !str_starts_with($mBody, $mHeader)) {
                            $mBody = $mHeader . "

" . $mBody;
                        }
                        $mPhone = preg_replace('/\D+/', '', (string) ($contact->mobile ?? ''));
                        if ($mPhone !== '' && trim($mBody) !== '') {
                            app(\App\Services\Inbox\InboxMirror::class)->appendOutboundToOpenConversation(
                                (int) $wsId,
                                $mPhone,
                                $mBody,
                                $result['provider_id'] ?? null,
                                $campaign->provider ?: null,
                                array_filter([
                                    'type'          => !empty($tplCache) ? 'template' : 'text',
                                    'template_name' => $tplCache->template_name ?? null,
                                    'buttons'       => (!empty($tplCache) && is_array($tplCache->buttons) && $tplCache->buttons) ? $tplCache->buttons : null,
                                    'campaign_id'   => $campaign->id,
                                    'source'        => 'campaign',
                                    // Deterministic key so the later status
                                    // callback recognises THIS bubble even
                                    // before a wamid exists.
                                    'src_key'       => 'campaign:' . $campaign->id . ':contact:' . $contact->id,
                                ], fn ($v) => $v !== null),
                            );
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[CAMPAIGN-MIRROR] inline mirror failed: ' . $e->getMessage());
                    }

                    // Warmer: count this number's send ONLY on confirmed success —
                    // no double-count on retry, no budget spent on a failed send.
                    if ($warmEnabled) { $warmer->recordSend($warmDevice); }
                } else {
                    $err = (string) ($result['error'] ?? '');
                    if ($err === '' && ($result['local_only'] ?? false) === true) {
                        $err = 'Not delivered — the selected channel is disabled or halted, so the message was stored but never sent.';
                    }
                    $this->recordSendFailure($logRow, $campaign, $err ?: 'unknown', $maxAttempts, $retryBackoff);
                }
            } catch (\Throwable $e) {
                Log::warning('campaign send threw', ['err' => $e->getMessage(), 'campaign' => $campaign->id, 'contact' => $contact->id]);
                $this->recordSendFailure($logRow, $campaign, $e->getMessage(), $maxAttempts, $retryBackoff);
            }
        }

        // Re-arm vs complete. When we stopped early for the daily cap or the
        // sending window AND recipients are still unsent, hand the campaign back
        // to the sweeper for the next slot so a large list is spread safely
        // across days / business hours. Recurring cadence is owned separately by
        // fireScheduledCampaign, so only NON-recurring campaigns re-arm here.
        $remaining = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded', 'unsubscribed'])
            ->count();

        // Retryable subset — non-delivered rows that still have attempts left.
        // Terminal failures (permanent, or retries exhausted) are stamped at
        // the cap so they're excluded; this is what lets the campaign converge.
        $retryable = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded', 'unsubscribed'])
            ->where('send_attempts', '<', $maxAttempts)
            ->count();

        // Operator CANCELLED or PAUSED this campaign while the paced batch was
        // running — honour it: do NOT re-arm to 'scheduled' (the sweeper would
        // otherwise resume it, which IS the "paused campaign auto-restarts" bug).
        // Re-read the LIVE status because $campaign was loaded before the batch.
        $liveStatus = WpCampaign::where('id', $campaign->id)->value('status');
        if ($operatorHalted || in_array($liveStatus, ['cancelled', 'paused'], true)) {
            return;
        }

        // Past the campaign end-date → END it (do NOT re-arm): mark the remaining
        // recipients expired + complete. Backstop for a run that crossed the
        // deadline mid-batch (the top-of-method check ends it on later ticks).
        if ($campaignExpired || ($deadlineAt && $deadlineAt->isPast())) {
            $this->endExpiredCampaign($campaign, $maxAttempts, $expiryHrs, $deadlineAt);
            return;
        }

        if ($stopReason && $remaining > 0 && $campaign->schedule_type !== 'recurring') {
            if ($stopReason === 'time') {
                // Resume the rest after the pending gap so pacing is preserved
                // (re-arm to ~now + gap, in the campaign's timezone).
                try {
                    $rtz  = $campaign->timezone ?: config('app.timezone', 'UTC');
                    $next = \Illuminate\Support\Carbon::now($rtz)->addSeconds(max(1, (int) $resumeInSec));
                } catch (\Throwable $e) {
                    $next = \Illuminate\Support\Carbon::now('UTC')->addSeconds(max(1, (int) $resumeInSec));
                }
                $nextDate = $next->toDateString();
                $nextTime = $next->format('H:i:s');
            } else {
                [$nextDate, $nextTime] = $this->nextRunSlot($campaign, $stopReason);
            }
            $campaign->update([
                'status'        => 'scheduled',
                // The sweeper only fires schedule_type scheduled/recurring; a paced
                // "now" send keeps schedule_type='now', so flip it — otherwise the
                // remaining chunk would never be resumed.
                'schedule_type' => 'scheduled',
                'send_date'     => $nextDate,
                'send_time'     => $nextTime,
            ]);
        } elseif (!$stopReason && $retryable > 0 && $campaign->schedule_type !== 'recurring') {
            // AUTO-RETRY re-arm. The run finished (no cap/window/time stop) but
            // some recipients failed transiently and still have attempts left.
            // Re-arm to the EARLIEST per-row backoff time so the sweeper resumes
            // and the loop retries only the rows whose next_attempt_at is due.
            // Converges: each pass either delivers a row or burns one attempt
            // until every recipient is sent or terminal (then it completes).
            $nextRetryAt = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded', 'unsubscribed'])
                ->where('send_attempts', '<', $maxAttempts)
                ->whereNotNull('next_attempt_at')
                ->min('next_attempt_at');
            $rtz = $campaign->timezone ?: config('app.timezone', 'UTC');
            try {
                $base = $nextRetryAt
                    ? \Illuminate\Support\Carbon::parse($nextRetryAt)->timezone($rtz)
                    : \Illuminate\Support\Carbon::now($rtz)->addSeconds($retryBackoff);
            } catch (\Throwable $e) {
                $base = \Illuminate\Support\Carbon::now('UTC')->addSeconds($retryBackoff);
            }
            $campaign->update([
                'status'        => 'scheduled',
                'schedule_type' => 'scheduled',
                'send_date'     => $base->toDateString(),
                'send_time'     => $base->format('H:i:s'),
            ]);
        } else {
            // Don't clobber a recurring campaign that fireScheduledCampaign
            // already re-armed to 'scheduled' for its next occurrence: that
            // re-arm runs synchronously BEFORE this async (afterResponse) loop,
            // so an unconditional 'completed' here would kill recurrence after a
            // single fire. Only complete when it wasn't re-armed — i.e. a one-off
            // scheduled/now run, or a recurring run past its repeat_until.
            $freshStatus = WpCampaign::where('id', $campaign->id)->value('status');
            if ($freshStatus !== 'scheduled') {
                $campaign->update(['status' => 'completed']);
            }
        }

    }

    /**
     * Is "now" inside the campaign's active sending window, in its own
     * timezone? Handles overnight windows (start > end, e.g. 22:00–06:00).
     * Times are zero-padded "HH:MM" so string comparison is correct.
     */
    private function withinSendWindow(string $tz, string $start, string $end): bool
    {
        try {
            $now = \Illuminate\Support\Carbon::now($tz)->format('H:i');
        } catch (\Throwable $e) {
            return true;   // bad tz — fail open, don't block the send
        }
        $s = substr($start, 0, 5);
        $e = substr($end, 0, 5);
        if ($s === $e) return true;                 // degenerate = no restriction
        return ($s < $e) ? ($now >= $s && $now <= $e)   // same-day window
                         : ($now >= $s || $now <= $e);  // overnight window
    }

    /**
     * Next [send_date, send_time] for a campaign that paused for the daily cap
     * or the closed sending window. Cap → tomorrow at the window open (or the
     * original send time). Window → today if it hasn't opened yet, else
     * tomorrow, at the window open time. All in the campaign's timezone.
     */
    private function nextRunSlot(WpCampaign $campaign, string $reason): array
    {
        $tz       = $campaign->timezone ?: config('app.timezone', 'UTC');
        $openTime = $campaign->window_start
            ? substr($campaign->window_start, 0, 5) . ':00'
            : ((string) ($campaign->send_time ?: '09:00:00'));

        try {
            $now = \Illuminate\Support\Carbon::now($tz);
        } catch (\Throwable $e) {
            $now = \Illuminate\Support\Carbon::now('UTC');
        }

        if ($reason === 'window' && $campaign->window_start) {
            // If today's window open is still ahead, resume today; else tomorrow.
            $todayOpen = \Illuminate\Support\Carbon::parse($now->toDateString() . ' ' . $openTime, $tz);
            $target    = $now->lt($todayOpen) ? $todayOpen : $todayOpen->copy()->addDay();
        } else {
            // Daily cap (or window with no explicit open) → next day.
            $target = \Illuminate\Support\Carbon::parse($now->toDateString() . ' ' . $openTime, $tz)->addDay();
        }

        return [$target->toDateString(), $target->format('H:i:s')];
    }

    /**
     * Flow campaigns: per-recipient POST to Node's existing flow-start
     * endpoint, mirroring DripEnrollmentService::launchFlow. The Node
     * runtime then owns delays, branching, and downstream sends. We
     * record the dispatch result on each WpCampaignContact row — `sent`
     * once Node ACKs the start, `failed` with the upstream error
     * otherwise. Wallet is charged 1 credit per recipient (refunded
     * on failure) to match text/template/button campaign accounting.
     */
    private function dispatchFlowCampaign(WpCampaign $campaign, $contacts, ?int $userId): void
    {
        $flowId  = (int) ($campaign->flow_id ?? 0);
        $flowIdB = (int) ($campaign->flow_id_b ?? 0);
        $abOn    = (bool) $campaign->ab_testing && $flowIdB > 0;
        if ($flowId <= 0) {
            $campaign->update(['status' => 'failed']);
            Log::warning('[CAMPAIGN-FLOW] no flow_id on campaign ' . $campaign->id);
            return;
        }

        // Active flow(s) owned by THIS campaign's workspace. Otherwise a
        // deleted/disabled flow (or a flow from another tenant slipped
        // in by id collision) would silently 404 in Node for every
        // recipient — fail the campaign fast instead. A/B campaigns load
        // both variants and route per-recipient by their assigned variant.
        $flow = \App\Models\Flow::query()
            ->where('id', $flowId)
            ->where('is_active', true)
            ->where('workspace_id', $campaign->workspace_id)
            ->first();
        if (!$flow) {
            Log::warning('[CAMPAIGN-FLOW] aborted — flow inactive or missing', [
                'campaign_id' => $campaign->id, 'flow_id' => $flowId, 'ws' => $campaign->workspace_id,
            ]);
            $campaign->update(['status' => 'failed']);
            \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => 'Flow inactive or missing']);
            return;
        }
        $flowB = $abOn ? \App\Models\Flow::query()
            ->where('id', $flowIdB)
            ->where('is_active', true)
            ->where('workspace_id', $campaign->workspace_id)
            ->first() : null;
        if ($abOn && !$flowB) {
            // Variant B missing/inactive — fail the B half so the run doesn't
            // silently fall back to A for every B recipient.
            Log::warning('[CAMPAIGN-FLOW] variant B flow inactive or missing', [
                'campaign_id' => $campaign->id, 'flow_id_b' => $flowIdB, 'ws' => $campaign->workspace_id,
            ]);
        }

        // Sender phone — Node addresses flow sessions by the sender's phone.
        // ENGINE-AWARE: campaigns.device_id is POLYMORPHIC — a `devices` row for
        // Baileys, but a `wa_provider_configs` id for WABA / Twilio. The old code
        // resolved it ONLY from `devices`, so on a WABA/Twilio workspace (whose
        // `devices` table is empty) it returned NULL and EVERY flow campaign died
        // with "No paired device on campaign". Node runs the session with sock=null
        // and sends over the Cloud/Twilio API, so any consistent sender phone works
        // as the key. Mirrors the text/template resolver above.
        $devicePhone    = null;
        $campaignEngine = strtolower((string) ($campaign->provider ?? ''));
        // If the campaign never got a provider stamped (legacy / form gap) but the
        // workspace runs an official engine and has no Baileys devices, infer it so
        // the WABA/Twilio branch is taken instead of the empty-Baileys one.
        if ($campaignEngine === '' || $campaignEngine === 'baileys') {
            $wsDefault = \App\Services\WorkspaceEngine::defaultEngineFor($campaign->workspace_id);
            if (in_array($wsDefault, ['waba', 'twilio'], true)
                && !\App\Models\Device::query()->forWorkspace($campaign->workspace_id, $campaign->user_id)->exists()) {
                $campaignEngine = $wsDefault;
            }
        }
        if (in_array($campaignEngine, ['waba', 'twilio'], true)) {
            $cfg = $campaign->device_id
                ? \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->where('provider', $campaignEngine)
                    ->find($campaign->device_id)
                : null;
            if (! $cfg && $campaign->device_id) $cfg = \App\Models\WaProviderConfig::query()->find($campaign->device_id);
            if (! $cfg) {
                // Legacy rows stored the PHONE in device_id — match it back to a
                // connected sender on this workspace + engine.
                $digits = preg_replace('/\D+/', '', (string) $campaign->device_id);
                if ($digits !== '' && strlen($digits) >= 8) {
                    $cfg = \App\Models\WaProviderConfig::query()
                        ->where('workspace_id', $campaign->workspace_id)
                        ->where('provider', $campaignEngine)
                        ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                        ->get(['id', 'phone_number'])
                        ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->phone_number) === $digits);
                }
            }
            // Final fallback: any connected sender for this engine, so a flow
            // campaign whose device_id drifted still goes out from a real number.
            if (! $cfg) {
                $cfg = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->where('provider', $campaignEngine)
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->first();
            }
            $devicePhone = $cfg ? (preg_replace('/\D+/', '', (string) $cfg->phone_number) ?: null) : null;
        } else {
            $device = $campaign->device_id
                ? \App\Models\Device::query()
                    ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                    ->find($campaign->device_id)
                : null;
            if (! $device && $campaign->device_id) {
                $device = \App\Models\Device::query()->find($campaign->device_id);
            }
            $devicePhone = $device
                ? preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number))
                : null;
        }
        if (!$devicePhone) {
            Log::warning('[CAMPAIGN-FLOW] aborted — no paired device phone', [
                'campaign_id' => $campaign->id, 'device_id' => $campaign->device_id,
            ]);
            $campaign->update(['status' => 'failed']);
            \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => 'No paired device on campaign']);
            return;
        }

        $nodeUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($nodeUrl === '') {
            Log::warning('[CAMPAIGN-FLOW] aborted — NODE bridge URL not configured', [
                'campaign_id' => $campaign->id,
            ]);
            $campaign->update(['status' => 'failed']);
            \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => 'NODE bridge URL not configured']);
            return;
        }
        $nodeUrl = rtrim($nodeUrl, '/');
        $token   = node_token();


        foreach ($contacts as $contact) {
            $logRow = \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('contact_id', $contact->id)
                ->first();

            $to = preg_replace('/\D+/', '', (string) (($contact->country_code ?? '') . $contact->mobile));
            if ($to === '') {
                $logRow?->update(['status' => 'failed', 'error_message' => 'No mobile number on contact']);
                $campaign->increment('failed_count');
                continue;
            }
            // Plan-first billing (OverflowBilling) — free under the plan's
            // monthly_messages_limit, wallet credit only on overflow. Same as
            // the text/template path; no wallet pre-gate.
            try {
                $wsObj = \App\Models\Workspace::find($campaign->workspace_id);
                if ($wsObj) {
                    $usedThisMonth = WpCampaignContact::query()
                        ->whereIn('campaign_id', WpCampaign::where('workspace_id', $campaign->workspace_id)->pluck('id'))
                        ->whereIn('status', ['sent', 'delivered', 'read', 'responded'])
                        ->where('updated_at', '>=', now()->startOfMonth())
                        ->count();
                    // Flow-campaign send → recipient's country MARKETING rate
                    // (no-ops to flat when per-country pricing is OFF).
                    \App\Services\OverflowBilling::consumeOne($wsObj, $usedThisMonth, optional($contact)->mobile, 'marketing');
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $logRow?->update(['status' => 'failed', 'error_message' => 'Plan cap reached — top up wallet to keep sending']);
                $campaign->increment('failed_count');
                continue;
            }

            // A/B variant routing — recipients assigned 'B' run flow_id_b when
            // the campaign is in A/B mode. If B is missing/inactive at this
            // point, fail the recipient instead of silently sending the A flow.
            $isVariantB = $abOn && ($logRow?->variant === 'B');
            if ($isVariantB && !$flowB) {
                $logRow?->update(['status' => 'failed', 'error_message' => 'Variant B flow inactive or missing']);
                $campaign->increment('failed_count');
                continue;
            }
            $chosenFlow = $isVariantB ? $flowB : $flow;

            try {
                $r = \Illuminate\Support\Facades\Http::withHeaders([
                        'X-Node-Token' => $token,
                    ])
                    ->timeout(15)
                    ->acceptJson()
                    ->post($nodeUrl . '/api/flow/start/' . rawurlencode($devicePhone), [
                        'flowId'            => $chosenFlow->id,
                        'targetPhoneNumber' => $to,
                        // Diagnostic crumbs — Node logs them so ops can
                        // correlate flow sessions to campaigns.
                        'campaignId'        => $campaign->id,
                        'contactId'         => $contact->id,
                        'variant'           => $isVariantB ? 'B' : 'A',
                    ]);

                if ($r->successful()) {
                    $logRow?->update(['status' => 'sent', 'sent_at' => now()]);
                    $campaign->increment('sent_count');
                } else {
                    $err = 'Node ' . $r->status() . ': ' . mb_substr((string) $r->body(), 0, 150);
                    $logRow?->update(['status' => 'failed', 'error_message' => $err]);
                    $campaign->increment('failed_count');
                }
            } catch (\Throwable $e) {
                $err = 'Node unreachable: ' . mb_substr($e->getMessage(), 0, 150);
                $logRow?->update(['status' => 'failed', 'error_message' => $err]);
                $campaign->increment('failed_count');
            }
        }

        $campaign->update(['status' => 'completed']);
    }

    // -----------------------------------------------------------------
    // Show / update
    // -----------------------------------------------------------------

    public function show($id, Request $request = null)
    {
        $request = $request ?? request();
        $campaign = WpCampaign::query()
            ->forCurrentWorkspace()
            ->with('contacts')
            ->findOrFail($id);

        // Self-heal the aggregate counters from the per-recipient log before we
        // render anything — Meta delivery/read webhooks patch the log rows but
        // historically never these columns, so the KPI cards (which read the
        // columns) were stuck at 0 while the funnel (which reads the log) was
        // right. This makes both consistent, and backfills campaigns whose
        // webhooks fired before the counter-sync fix landed.
        $campaign->recomputeAggregates();

        // Live-refresh JSON branch — user-wa-campaigns-detail.js
        // polls every 15 s with `?partial=1` to repaint the KPI tiles
        // + status pill without a full page reload. Shape mirrors
        // BroadcastsController::statistics so the frontend keeps a
        // single update path.
        if ($request->wantsJson() || $request->boolean('partial')) {
            $totalRecipients = (int) ($campaign->total_recipients ?: $campaign->contacts->count());
            $pct = function (int $n, int $base): float {
                return $base > 0 ? round($n / $base * 100, 1) : 0.0;
            };
            return response()->json([
                'ok' => true,
                'status' => (string) ($campaign->status ?? 'draft'),
                'stats'  => [
                    'recipients'    => $totalRecipients,
                    'sent'          => (int) $campaign->sent_count,
                    'delivered'     => (int) $campaign->delivered_count,
                    'read'          => (int) $campaign->read_count,
                    'replies'       => (int) $campaign->responded_count,
                    'clicks'        => (int) $campaign->clicked_count,
                    'failed'        => (int) $campaign->failed_count,
                    'delivered_pct' => $pct((int) $campaign->delivered_count, $totalRecipients),
                    'read_pct'      => $pct((int) $campaign->read_count, (int) $campaign->delivered_count),
                    'replies_pct'   => $pct((int) $campaign->responded_count, $totalRecipients),
                    'clicks_pct'    => $pct((int) $campaign->clicked_count, $totalRecipients),
                    'failed_pct'    => $pct((int) $campaign->failed_count, $totalRecipients),
                ],
                // Live failure reasons. The KPI tiles alone only said HOW MANY
                // failed; the operator still had to reload to learn WHY. Ship
                // the newest failures + a grouped tally so the page can show
                // the actual blocker (bad template, disabled channel, …) as it
                // happens — this is what turned "sent=0 failed=0" into a
                // multi-hour debug for the operator.
                'failures' => WpCampaignContact::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->whereNotNull('error_message')
                    ->latest('id')->take(10)
                    ->get(['id', 'error_message', 'updated_at'])
                    ->map(fn ($r) => [
                        'id'    => (int) $r->id,
                        'error' => (string) $r->error_message,
                        'at'    => optional($r->updated_at)->diffForHumans(),
                    ])->values(),
                'failure_summary' => WpCampaignContact::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->whereNotNull('error_message')
                    ->selectRaw('error_message, COUNT(*) as c')
                    ->groupBy('error_message')
                    ->orderByDesc('c')->limit(5)
                    ->get()
                    ->map(fn ($r) => ['error' => (string) $r->error_message, 'count' => (int) $r->c])
                    ->values(),
            ]);
        }

        // ---------------------------------------------------------
        // Recipient log slices used by the Messages + Engagement tabs.
        // ---------------------------------------------------------
        $allContacts = $campaign->contacts; // already eager-loaded
        // Message-log search — when a query is present we pull a wider slice
        // and filter in PHP (phone/name are encrypted, so no SQL LIKE), so
        // the search works fully server-side without any JS/build upload.
        $msgSearch = trim((string) request('q', ''));
        // Laravel SERVER-SIDE pagination for the Messages-tab recipient log —
        // was take(20) with no pager, so only the first ~20 of a 300-person send
        // ever showed. The base list paginates in the DB (page size 50). A search
        // can't hit the DB (name/phone are encrypted → no SQL LIKE), so that
        // branch pulls a wide pool, filters in PHP after hydration, then hands
        // the matches to a manual paginator below. Either way the blade receives
        // a LengthAwarePaginator it pages through with ?mpage=.
        $msgPerPage = 50;
        if ($msgSearch !== '') {
            $messages = WpCampaignContact::where('campaign_id', $campaign->id)
                ->latest('id')->take(5000)->get();   // filtered + paginated below
        } else {
            $messages = WpCampaignContact::where('campaign_id', $campaign->id)
                ->latest('id')->paginate($msgPerPage, ['*'], 'mpage')->withQueryString();
        }
        $replies = WpCampaignContact::where('campaign_id', $campaign->id)
            ->whereNotNull('responded_at')
            ->latest('responded_at')
            ->take(10)
            ->get();
        // True recipient total — drives the table headers + a "showing first N
        // of T" notice when a very large campaign exceeds the 2000-row cap.
        $recipientTotal = (int) WpCampaignContact::where('campaign_id', $campaign->id)->count();

        // ---------------------------------------------------------
        // Header right-side metric tiles (ROI / Audience / Cost / CPC / Quality).
        // No real cost-tracking or revenue-attribution yet — we derive each
        // from recipient counters and the campaign's contact group makeup.
        // TODO: replace with real cost-tracking + revenue tables when available.
        // ---------------------------------------------------------
        $sent      = (int) $campaign->sent_count;
        $delivered = (int) $campaign->delivered_count;
        $responded = (int) $campaign->responded_count;
        $clicked   = (int) $campaign->clicked_count;

        // Audience: pick the largest contact group across this campaign's
        // recipients. WpCampaign has no `groups` column, so we walk the
        // recipient log -> Contact -> contact_group (encrypted JSON array)
        // and tally group ids. The biggest bucket wins.
        // Describe what the campaign ACTUALLY went to instead of always saying
        // "All contacts". Recipients pasted as manual numbers have no
        // contact_id; the rest come from the contact book and may carry groups.
        $manualCount = $allContacts->filter(fn ($r) => empty($r->contact_id))->count();
        $bookCount   = max(0, $allContacts->count() - $manualCount);

        $audienceParts = [];
        $contactIds = $allContacts->pluck('contact_id')->filter()->unique()->values();
        if ($contactIds->isNotEmpty()) {
            $contactRows = Contact::query()->forCurrentWorkspace()->whereIn('id', $contactIds)->get(['id', 'contact_group']);
            $groupTally = [];
            foreach ($contactRows as $c) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                foreach ($list as $gid) {
                    $key = (string) $gid;
                    $groupTally[$key] = ($groupTally[$key] ?? 0) + 1;
                }
            }
            if (!empty($groupTally)) {
                arsort($groupTally);
                // Name the biggest group(s); workspace-scope the lookup so a
                // cross-tenant id can't leak a group name into the label.
                $topIds = array_slice(array_keys($groupTally), 0, 2);
                $names  = ContactGroup::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->whereIn('id', array_map('intval', $topIds))
                    ->pluck('user_group', 'id');
                $labels = [];
                foreach ($topIds as $gid) {
                    $n = (string) ($names[(int) $gid] ?? '');
                    if ($n !== '') $labels[] = $n;
                }
                if (!empty($labels)) {
                    $extra = count($groupTally) - count($labels);
                    $audienceParts[] = implode(', ', $labels) . ($extra > 0 ? ' +' . $extra : '');
                }
            }
            // Contacts that belong to no group at all. Kept SHORT — this renders
            // in a narrow tile, and a long phrase just truncates to nonsense.
            if (empty($audienceParts) && $bookCount > 0) {
                $audienceParts[] = __('Ungrouped');
            }
        }
        if ($manualCount > 0) {
            $audienceParts[] = __(':count manual', ['count' => number_format($manualCount)]);
        }
        $audienceLabel = !empty($audienceParts) ? implode(' · ', $audienceParts) : __('All contacts');

        // Sender tile — show the device NAME / number, not the raw row id.
        // `device_id` is POLYMORPHIC: for baileys it points at `devices`, for
        // waba/twilio at `wa_provider_configs`. Look the row up WITHOUT a status
        // filter so a since-disconnected sender still resolves on an old campaign.
        $deviceLabel = '—';
        if ($campaign->device_id) {
            $prov = (string) ($campaign->provider ?: 'baileys');
            if ($prov === 'baileys') {
                $d = \App\Models\Device::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->find($campaign->device_id);
                if ($d) {
                    $ph = trim((string) ($d->country_code ?? '') . ' ' . (string) ($d->phone_number ?? ''));
                    $deviceLabel = $d->device_name ?: ($ph !== '' ? $ph : '#' . $d->id);
                }
            } else {
                $c = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->find($campaign->device_id);
                if ($c) {
                    $deviceLabel = $c->display_label ?: ((string) $c->phone_number ?: strtoupper($prov));
                }
            }
            if ($deviceLabel === '—') $deviceLabel = '#' . $campaign->device_id;
        }

        // Header tiles — every one of these is now a REAL measured rate.
        //
        // What was here before, and why it had to go:
        //   • "Cost"    = $sent × 0.04, a hardcoded 4-cents-per-message. There
        //                 is no message-pricing infrastructure, so this was an
        //                 invented figure shown as money — the one number a
        //                 client would actually budget against.
        //   • "CPC"     = that invented cost ÷ clicks. Fake numerator, and
        //                 clicks were structurally 0, so it was always 0.
        //   • "ROI"     = responded / sent × 10. That is a reply rate on a
        //                 0-10 scale. It contains no revenue and no cost, so
        //                 calling it return-on-investment was simply wrong.
        //   • "Quality" = delivered / sent × 10. That is a delivery rate.
        //                 Meta publishes a real per-number quality rating
        //                 (GREEN/YELLOW/RED); inventing a different number and
        //                 labelling it "quality" invited the wrong decision.
        //
        // The maths behind ROI/Quality was fine — the LABELS were false. They
        // are kept, renamed to what they actually measure, and expressed as
        // percentages rather than an arbitrary 0-10 score. Cost/CPC are
        // replaced by two rates we can actually prove.
        $read = (int) $campaign->read_count;

        $rate = fn (int $n) => $sent > 0 ? round($n / $sent * 100, 1) : 0.0;

        $header = [
            'audience'      => $audienceLabel,
            'device_label'  => $deviceLabel,
            'delivery_rate' => $rate($delivered),
            'read_rate'     => $rate($read),
            'reply_rate'    => $rate($responded),
            'click_rate'    => $rate($clicked),
        ];

        // ---------------------------------------------------------
        // Chart data — built from the recipient log when populated, or
        // sensible fallbacks derived from the campaign counters when the
        // log is empty (e.g. brand-new campaigns where the SendWaCampaign
        // job hasn't run yet).
        // ---------------------------------------------------------
        $chartData = $this->buildChartData($campaign, $allContacts);

        // Timeline placeholder — once the SendWaCampaign job lands it can
        // append real events into a campaign_events table. For now mirror the
        // old controller's "fetch + map" shape with mock data so the Blade
        // panel renders.
        // Render every timeline time in the campaign's OWN timezone (the zone
        // it was scheduled/sent in) so operators don't see raw UTC. Stored
        // timestamps are UTC; wa_local() converts, pinned to $campaign->timezone.
        $campTz    = $campaign->timezone ?: null;
        $createdAt = wa_local($campaign->created_at, $campTz);
        $updatedAt = wa_local($campaign->updated_at, $campTz);
        $timeline = [
            [
                'icon'   => '1',
                'title'  => 'Campaign queued',
                'detail' => $campaign->total_recipients . ' recipients loaded.',
                'time'   => $createdAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '2',
                'title'  => 'Status: ' . ucfirst((string) $campaign->status),
                'detail' => 'Schedule type ' . ($campaign->schedule_type ?: 'now') . '.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '3',
                'title'  => 'Delivery progress',
                'detail' => $campaign->delivered_count . ' of ' . $campaign->total_recipients . ' delivered.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '4',
                'title'  => 'Reads recorded',
                'detail' => $campaign->read_count . ' read receipts captured.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '5',
                'title'  => 'Failures observed',
                'detail' => $campaign->failed_count . ' messages failed.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
        ];

        // ---------------------------------------------------------
        // Conversion funnel: Recipients -> Delivered -> Read -> Clicked -> Replied.
        // All five values come from the WpCampaignContact log (real per-recipient
        // status), so the funnel reflects what actually happened — not the
        // synthesised demo numbers the static blade had.
        // ---------------------------------------------------------
        $logBase = WpCampaignContact::where('campaign_id', $campaign->id);
        $totalLog     = (clone $logBase)->count();
        $deliveredLog = (clone $logBase)->whereIn('status', ['delivered','read','sent'])->count();
        // A read recipient normally has BOTH `read_at` set AND status='read'.
        // Adding the two counts double-counted every one of them — a 1-recipient
        // campaign showed "Read 2 / 200%". OR them in a single query so each
        // recipient is counted at most once, while still catching rows where
        // only one of the two markers landed (a dropped callback can set the
        // timestamp without advancing status, or vice versa).
        $readLog      = (clone $logBase)
            ->where(fn ($q) => $q->whereNotNull('read_at')->orWhere('status', 'read'))
            ->count();
        $clickedLog   = (clone $logBase)->where('clicked', true)->count();
        $repliedLog   = (clone $logBase)->whereNotNull('responded_at')->count();
        // Use whichever is bigger between the campaign counters and the log
        // counts so newly-fired sends don't appear empty before the log
        // catches up (the dispatcher writes to log+counter).
        $totalRecipients = max($totalLog, (int) $campaign->total_recipients);
        $pct = fn ($n) => $totalRecipients > 0 ? round(($n / $totalRecipients) * 100, 1) : 0.0;
        // Clamp every stage to the recipient count. These take max(log, counter)
        // so a lagging log doesn't read as empty — but that also means a stale
        // or over-counted counter could exceed the audience and render >100%.
        // No funnel stage can legitimately exceed the people it was sent to.
        $cap = fn (int $n) => $totalRecipients > 0 ? min($n, $totalRecipients) : $n;
        $funnel = [
            'recipients'    => $totalRecipients,
            'delivered'     => $cap(max($deliveredLog, (int) $campaign->delivered_count)),
            'read'          => $cap(max($readLog,      (int) $campaign->read_count)),
            'clicked'       => $cap(max($clickedLog,   (int) $campaign->clicked_count)),
            'replied'       => $cap(max($repliedLog,   (int) $campaign->responded_count)),
        ];
        $funnel['delivered_pct'] = $pct($funnel['delivered']);
        $funnel['read_pct']      = $pct($funnel['read']);
        $funnel['clicked_pct']   = $pct($funnel['clicked']);
        $funnel['replied_pct']   = $pct($funnel['replied']);

        // ---------------------------------------------------------
        // Read heatmap — 7 days × 24 hours grid of read counts. Built
        // from `read_at` timestamps on the recipient log.
        // ---------------------------------------------------------
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        $readRows = (clone $logBase)->whereNotNull('read_at')->get(['read_at']);
        foreach ($readRows as $r) {
            if (!$r->read_at) continue;
            $dow = (int) $r->read_at->dayOfWeek; // 0=Sun..6=Sat
            $hr  = (int) $r->read_at->hour;
            $heatmap[$dow][$hr]++;
        }

        // ---------------------------------------------------------
        // Top performers — group recipient log by contact_group, then
        // rank by read-rate. Empty when the campaign has no group-based
        // segmentation.
        // ---------------------------------------------------------
        $segments = [];
        if (!empty($contactRows ?? null)) {
            $byGroup = []; // gid => ['recipients', 'replies', 'reads', 'opt_outs']
            foreach ($contactRows as $c) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                foreach ($list as $gid) {
                    $key = (string) $gid;
                    if (!isset($byGroup[$key])) $byGroup[$key] = ['recipients'=>0,'replies'=>0,'reads'=>0,'opt_outs'=>0];
                    $byGroup[$key]['recipients']++;
                    $row = $allContacts->firstWhere('contact_id', $c->id);
                    if ($row) {
                        if ($row->responded_at) $byGroup[$key]['replies']++;
                        if ($row->read_at)      $byGroup[$key]['reads']++;
                        if ($row->is_unsubscribed) $byGroup[$key]['opt_outs']++;
                    }
                }
            }
            foreach ($byGroup as $gid => $stats) {
                $g = ContactGroup::find((int) $gid);
                if (!$g) continue;
                $segments[] = [
                    'name'       => (string) ($g->user_group ?: 'Group #' . $gid),
                    'recipients' => $stats['recipients'],
                    'replies'    => $stats['replies'],
                    'opt_outs'   => $stats['opt_outs'],
                    'read_pct'   => $stats['recipients'] > 0 ? round($stats['reads'] / $stats['recipients'] * 100, 1) : 0.0,
                ];
            }
            usort($segments, fn ($a, $b) => $b['read_pct'] <=> $a['read_pct']);
            $segments = array_slice($segments, 0, 5);
        }

        // ---------------------------------------------------------
        // Per-tab data sources — every panel was previously hardcoded.
        // ---------------------------------------------------------

        // Messages tab — "Sent content" preview reads the real body /
        // buttons / footer / header used for this campaign. For template
        // sends we pull from the template; for custom sends from the
        // campaign's own columns.
        $tpl = $campaign->template_id ? \App\Models\WaTemplate::find($campaign->template_id) : null;
        $isTemplateCampaign = $campaign->campaign_type === 'template' && $tpl;
        $previewBody     = $isTemplateCampaign ? (string) $tpl->template_body : (string) $campaign->custom_message;
        $previewFooter   = $isTemplateCampaign ? (string) ($tpl->footer ?? '') : (string) ($campaign->custom_footer ?? '');
        $previewHeader   = $isTemplateCampaign ? (string) ($tpl->header ?? '') : (string) ($campaign->custom_header ?? '');
        $previewButtons  = $isTemplateCampaign
            ? (is_array($tpl->buttons) ? $tpl->buttons : [])
            : (is_array($campaign->custom_buttons) ? $campaign->custom_buttons : []);
        $previewTemplateName = $isTemplateCampaign ? (string) $tpl->template_name : ('Custom · ' . $campaign->campaign_name);
        $previewCategory = $isTemplateCampaign
            ? ucfirst((string) ($tpl->category ?? 'marketing'))
            : ucfirst((string) ($campaign->campaign_type ?: 'custom'));

        // Engagement tab — 4 top metric cards. Re-derive percentages
        // from the campaign counters so the displayed values agree with
        // the funnel card on the overview tab.
        $sentN        = (int) $campaign->sent_count;
        $readN        = (int) max($campaign->read_count, $readLog);
        $clickedN     = (int) max($campaign->clicked_count, $clickedLog);
        $repliedN     = (int) max($campaign->responded_count, $repliedLog);
        $optOutsN     = (int) $allContacts->where('is_unsubscribed', true)->count();
        $totalForPct  = max($sentN, 1);
        $engagement   = [
            'opened_pct'  => round($readN    / $totalForPct * 100, 1),
            'opened_n'    => $readN,
            'clicked_pct' => round($clickedN / $totalForPct * 100, 1),
            'clicked_n'   => $clickedN,
            'replied_pct' => round($repliedN / $totalForPct * 100, 1),
            'replied_n'   => $repliedN,
            'optout_pct'  => $totalRecipients > 0 ? round($optOutsN / $totalRecipients * 100, 1) : 0.0,
            'optout_n'    => $optOutsN,
        ];

        // Engagement tab — "Top buttons" card, from REAL per-button clicks.
        //
        // This used to divide the campaign's total clicks evenly across the
        // buttons and print that as per-button data — invented numbers that
        // looked authoritative. Every URL button is wrapped by LinkTracker at
        // send time, and each wrap writes a `wa_link_clicks` row carrying the
        // destination URL, so the true per-destination count is simply the sum
        // of `clicks` grouped by `original_url`.
        $btnSrc = $isTemplateCampaign
            ? (is_array($tpl->buttons) ? $tpl->buttons : [])
            : (is_array($campaign->custom_buttons) ? $campaign->custom_buttons : []);

        $clicksByUrl = \App\Models\WaLinkClick::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('original_url, SUM(clicks) AS total')
            ->groupBy('original_url')
            ->pluck('total', 'original_url');

        $btnRows = [];
        foreach ($btnSrc as $idx => $b) {
            if (!is_array($b)) continue;
            // Only URL buttons are trackable — a quick-reply has no link to
            // wrap, so it can never accrue a click. Label it as such instead
            // of showing a 0 that reads like "nobody tapped it".
            $isUrl = strtolower((string) ($b['type'] ?? $b['sub_type'] ?? '')) === 'url'
                  || filter_var((string) ($b['value'] ?? $b['url'] ?? ''), FILTER_VALIDATE_URL);
            $url   = (string) ($b['value'] ?? $b['url'] ?? '');
            $count = $isUrl ? (int) ($clicksByUrl[$url] ?? 0) : null;

            $btnRows[] = [
                'label'     => (string) ($b['text'] ?? ('Button ' . ($idx + 1))),
                'count'     => $count,                 // null = not trackable
                'trackable' => $isUrl,
                'pct'       => ($count && $clickedN > 0) ? round(($count / $clickedN) * 100) : 0,
            ];
        }

        // Opt-outs tab — WHO opted out, not just how many.
        //
        // The page only ever had `optout_n`, a bare number, so an operator
        // could see that 12 people left but never which 12 — no way to follow
        // up, clean a list, or prove an opt-out was honoured. These are the
        // campaign's own recipients whose contact is now unsubscribed, joined
        // back to the contact for a name and the timestamp of the opt-out.
        // `recipient_name` is denormalised onto the pivot at send time, so the
        // name comes for free — a Contact::find() per row would have been one
        // query per opt-out.
        // Fetch opt-out recipients as MODELS (not pre-mapped arrays) so they can
        // pass through the SAME contact hydration the message log uses below.
        // Without it, a pivot row the send path never stamped a name/phone onto
        // shows "Unknown / —" here even though the message log resolves it fine.
        // `contact_id` is required for the hydration join.
        $optOutContacts = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->where('is_unsubscribed', true)
            ->get(['id', 'contact_id', 'recipient_name', 'phone_number', 'status', 'sent_at', 'unsubscribed_at']);

        // Opt-INs — recipients still subscribed. The other half of the story:
        // an opt-out list on its own reads like the campaign burned the
        // audience, with no denominator next to it.
        $optInCount = max(0, $totalRecipients - $optOutContacts->count());

        // Recipients tab — segment totals card (left side). Top 3 by
        // recipient count from $segments we already computed. Empty
        // when no contact-group data exists.
        $segmentTotals = collect($segments)
            ->sortByDesc('recipients')
            ->take(3)
            ->map(fn ($s) => ['name' => $s['name'], 'recipients' => $s['recipients'], 'read_pct' => $s['read_pct']])
            ->values()
            ->all();

        // Recipients tab — recipient table (the per-row analytics).
        // Pull straight from the WpCampaignContact log; show all rows
        // up to 200, ordered by most-recently-sent.
        $recipientRows = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'rpage')
            ->withQueryString();

        // Backfill recipient_name + phone_number from the linked Contact for
        // any row the send path didn't stamp them on (older campaign_contact
        // rows have null phone → the delivery table showed "—" and
        // "Recipient #<id>"). Contact fields decrypt via Eloquent, so no
        // ciphertext ever reaches the view. One query covers all three sets.
        $this->hydrateRecipientRows([$messages, $replies, $recipientRows, $optOutContacts]);

        // Map opt-outs to view rows now that name + phone are resolved. Fall
        // back to "Recipient · <last4>" (the exact display the send path stamps
        // for an unsaved number) when only a phone is known, and "Unknown" only
        // when neither name nor phone exists.
        $optOutRows = $optOutContacts
            ->map(function ($r) {
                $phone = (string) $r->phone_number;
                $name  = trim((string) $r->recipient_name);
                if ($name === '') {
                    $name = $phone !== '' ? ('Recipient · ' . substr($phone, -4)) : __('Unknown');
                }
                return [
                    'name'     => $name,
                    'phone'    => $phone,
                    'sent_at'  => $r->sent_at,
                    'opted_at' => $r->unsubscribed_at,
                    'status'   => (string) $r->status,
                ];
            })
            ->sortByDesc(fn ($r) => $r['opted_at'])
            ->values()
            ->all();

        // Apply the message-log search now that name + phone are resolved, then
        // page the matches through a manual LengthAwarePaginator (encrypted
        // fields can't be DB-paginated). The base (no-search) list already came
        // back as a DB paginator above.
        if ($msgSearch !== '') {
            $needle  = mb_strtolower($msgSearch);
            $matches = $messages->filter(function ($m) use ($needle) {
                return str_contains(mb_strtolower((string) $m->recipient_name . ' ' . (string) $m->phone_number), $needle);
            })->values();
            $mpage    = (int) \Illuminate\Pagination\Paginator::resolveCurrentPage('mpage');
            $messages = new \Illuminate\Pagination\LengthAwarePaginator(
                $matches->forPage($mpage, $msgPerPage)->values(),
                $matches->count(),
                $msgPerPage,
                $mpage,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'pageName' => 'mpage', 'query' => request()->query()]
            );
        }

        // Recipients tab — Audience cleanup card. Compute the real
        // uploaded → final-send-list breakdown.
        $audienceStats = [
            'uploaded'     => (int) $campaign->total_recipients,
            'duplicates'   => 0, // would need pre-dedupe counter; not tracked
            'invalid'      => $allContacts->whereNull('phone_number')->count(),
            'opt_out_skip' => $optOutsN,
            'final_list'   => max(0, (int) $campaign->total_recipients - $optOutsN),
        ];

        // Failures tab — header count + recent error table.
        $failureRows = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        $failedTotal = $failureRows->count(); // capped at 50 for the visible table; the campaign counter has the real total
        $failedCount = (int) $campaign->failed_count;

        // Surface the EXACT WhatsApp/Meta reason for "sent but not delivered"
        // right on the page (not just the server log) so the operator sees WHY.
        // error_message is an encrypted column, so we can't filter it in SQL —
        // pull the first non-empty one out of the already-loaded failed rows.
        $deliveryIssueReason = $failureRows
            ->map(fn ($r) => trim((string) ($r->error_message ?? '')))
            ->first(fn ($m) => $m !== '');

        return view('user.wa-campaigns.detail', compact(
            'campaign', 'timeline', 'messages', 'replies', 'header', 'chartData',
            'funnel', 'heatmap', 'segments',
            'previewBody', 'previewFooter', 'previewHeader', 'previewButtons',
            'previewTemplateName', 'previewCategory',
            'engagement', 'btnRows',
            'segmentTotals', 'recipientRows', 'recipientTotal', 'audienceStats',
            'failureRows', 'failedCount', 'deliveryIssueReason',
            'optOutRows', 'optInCount',
        ));
    }

    /**
     * Fill in recipient_name + phone_number on campaign-contact rows from the
     * linked Contact whenever the row itself doesn't carry them. Both columns
     * are encrypted casts, so setting them in-memory round-trips cleanly and
     * the Blade / mask_phone() sees a real value instead of "—". Accepts any
     * number of collections and hydrates them from a single Contact query.
     */
    private function hydrateRecipientRows(array $collections): void
    {
        $ids = collect();
        foreach ($collections as $col) {
            if ($col) $ids = $ids->concat($col->pluck('contact_id'));
        }
        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) return;

        // SECURITY: scope to the current workspace so recipient rows carrying
        // a foreign contact id (legacy pre-scoping data) never decrypt another
        // tenant's name + phone into the campaign detail view.
        $contacts = Contact::query()->forCurrentWorkspace()->whereIn('id', $ids)
            ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
            ->keyBy('id');

        foreach ($collections as $col) {
            if (!$col) continue;
            $col->transform(function ($row) use ($contacts) {
                $c = $row->contact_id ? $contacts->get($row->contact_id) : null;
                if (!$c) return $row;
                if (empty($row->recipient_name)) {
                    $nm = trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
                    if ($nm !== '') $row->recipient_name = $nm;
                }
                if (empty($row->phone_number)) {
                    $cc  = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                    $mob = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                    if ($mob !== '') {
                        $row->phone_number = ($cc !== '' && strpos($mob, $cc) !== 0) ? ($cc . $mob) : $mob;
                    }
                }
                return $row;
            });
        }
    }

    /**
     * CSV export of every recipient delivery row for a campaign — full phone +
     * name (resolved from the Contact), status and the delivery/engagement
     * timeline. Streams so a large audience never buffers into memory.
     */
    public function exportRecipients($id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        $rows = WpCampaignContact::where('campaign_id', $campaign->id)
            ->orderByDesc('id')->get();
        // SECURITY: scope contact hydration to the current workspace so a
        // campaign that carries a foreign contact id (legacy rows created
        // before store() was scoped) can never decrypt/export another tenant's
        // name + phone. Same-workspace recipients hydrate unchanged; foreign
        // ids fall through to the stored recipient_name/phone_number or the
        // "Contact #id" placeholder.
        $contacts = Contact::query()->forCurrentWorkspace()
            ->whereIn('id', $rows->pluck('contact_id')->filter()->unique())
            ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
            ->keyBy('id');

        $tz  = $campaign->timezone ?: wa_tz();
        $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->setTimezone($tz)->format('Y-m-d H:i') : '';
        // Neutralise CSV formula injection (=, +, -, @ leading a cell).
        $safe = fn ($v) => (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) ? "'" . $v : (string) $v;

        $filename = 'campaign-' . $campaign->id . '-recipients-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $contacts, $fmt, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Recipient', 'Phone', 'Variant', 'Status', 'Clicked',
                'Queued', 'Sent', 'Delivered', 'Read', 'Responded', 'Last event', 'Error',
            ]);
            foreach ($rows as $r) {
                $c = $r->contact_id ? $contacts->get($r->contact_id) : null;
                $name = $r->recipient_name
                    ?: ($c ? (trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))) : '')
                    ?: ('Contact #' . $r->contact_id);
                $phone = (string) $r->phone_number;
                if ($phone === '' && $c) {
                    $cc  = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                    $mob = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                    $phone = $mob === '' ? '' : (($cc !== '' && strpos($mob, $cc) !== 0) ? $cc . $mob : $mob);
                }
                $lastEvent = $r->responded_at ? 'Reply'
                    : ($r->clicked_at ? 'Button tap'
                    : ($r->read_at ? 'Read'
                    : ($r->delivered_at ? 'Delivered'
                    : ($r->sent_at ? 'Sent' : 'Queued'))));
                fputcsv($out, [
                    $safe($name),
                    $safe($phone),
                    $safe((string) ($r->variant ?? '')),
                    ucfirst((string) ($r->status ?: 'queued')),
                    $r->clicked ? 'Yes' : 'No',
                    $fmt($r->created_at),
                    $fmt($r->sent_at),
                    $fmt($r->delivered_at),
                    $fmt($r->read_at),
                    $fmt($r->responded_at),
                    $lastEvent,
                    $safe((string) ($r->error_message ?? '')),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Build the JSON-serializable chart payload that the Blade injects into
     * `window.WA_CAMPAIGN_DATA`. Each key matches a `#chart-*` container in
     * the detail view. Where recipient-log data is sparse (e.g. brand new
     * campaigns) we fall back to deterministic shapes derived from the
     * counters on `wpcampaigns` so the charts always render something
     * meaningful instead of blank canvases.
     */
    protected function buildChartData(WpCampaign $campaign, $contacts): array
    {
        // All day/hour bucketing below must happen in the campaign's OWN
        // timezone, not raw UTC — otherwise India's "messages per hour" and
        // read-heatmap charts sit 5h30 off and rows land in the wrong day.
        // Stored timestamps are UTC; $loc() converts each to the campaign tz
        // before we read its day-of-week / hour / calendar day.
        $tz  = $campaign->timezone ?: wa_tz();
        $loc = fn ($t) => $t ? $t->copy()->setTimezone($tz) : null;

        // ----- chart-delivery: 7-day timeline of sent / delivered / read -----
        $now = Carbon::now($tz);
        $deliveryCategories = [];
        $sentSeries = $deliveredSeries = $readSeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $deliveryCategories[] = $day->format('M j');
            $sentSeries[]      = $contacts->whereNotNull('sent_at')
                ->filter(fn ($c) => $c->sent_at && $loc($c->sent_at)->isSameDay($day))->count();
            $deliveredSeries[] = $contacts->whereNotNull('delivered_at')
                ->filter(fn ($c) => $c->delivered_at && $loc($c->delivered_at)->isSameDay($day))->count();
            $readSeries[]      = $contacts->whereNotNull('read_at')
                ->filter(fn ($c) => $c->read_at && $loc($c->read_at)->isSameDay($day))->count();
        }
        $logHasTimeline = array_sum($sentSeries) > 0 || array_sum($deliveredSeries) > 0;
        if (!$logHasTimeline) {
            // Fallback: distribute the campaign's counters evenly across 7 buckets.
            $bucketSent = (int) floor(((int) $campaign->sent_count) / 7);
            $bucketDel  = (int) floor(((int) $campaign->delivered_count) / 7);
            $bucketRead = (int) floor(((int) $campaign->read_count) / 7);
            $sentSeries      = array_fill(0, 7, $bucketSent);
            $deliveredSeries = array_fill(0, 7, $bucketDel);
            $readSeries      = array_fill(0, 7, $bucketRead);
        }

        // ----- chart-status: pie of sent / delivered / read / failed / responded -----
        // Derive from the recipient log; fall back to campaign counters.
        $statusFromLog = [
            'sent'      => $contacts->where('status', 'sent')->count(),
            'delivered' => $contacts->where('status', 'delivered')->count(),
            'read'      => $contacts->where('status', 'read')->count(),
            'failed'    => $contacts->where('status', 'failed')->count(),
            'responded' => $contacts->whereNotNull('responded_at')->count(),
        ];
        if (array_sum($statusFromLog) === 0) {
            $statusFromLog = [
                'sent'      => (int) $campaign->sent_count,
                'delivered' => (int) $campaign->delivered_count,
                'read'      => (int) $campaign->read_count,
                'failed'    => (int) $campaign->failed_count,
                'responded' => (int) $campaign->responded_count,
            ];
        }

        // ----- chart-throughput: messages per hour (24 buckets) -----
        $throughputCats = [];
        $throughputData = [];
        for ($h = 0; $h < 24; $h++) {
            $throughputCats[] = sprintf('%02d:00', $h);
            $throughputData[] = $contacts->whereNotNull('sent_at')
                ->filter(fn ($c) => $c->sent_at && (int) $loc($c->sent_at)->format('G') === $h)
                ->count();
        }
        if (array_sum($throughputData) === 0) {
            $sentTotal = (int) $campaign->sent_count;
            $perBucket = (int) floor($sentTotal / 24);
            $throughputData = array_fill(0, 24, $perBucket);
        }

        // ----- chart-engagement: clicks + replies over the past 7 days -----
        $engagementCats = $deliveryCategories;
        $clicksSeries   = [];
        $repliesSeries  = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $clicksSeries[]  = $contacts->whereNotNull('clicked_at')
                ->filter(fn ($c) => $c->clicked_at && $loc($c->clicked_at)->isSameDay($day))->count();
            $repliesSeries[] = $contacts->whereNotNull('responded_at')
                ->filter(fn ($c) => $c->responded_at && $loc($c->responded_at)->isSameDay($day))->count();
        }
        if (array_sum($clicksSeries) === 0 && array_sum($repliesSeries) === 0) {
            $clicksSeries  = array_fill(0, 7, 0);
            $repliesSeries = array_fill(0, 7, 0);
        }

        // ----- chart-read-heatmap: 24-hour read distribution by weekday -----
        $heatmapDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $heatmapHours = ['00','03','06','09','12','15','18','21'];
        $heatmap = [];
        foreach ($heatmapDays as $idx => $dayLabel) {
            $row = ['name' => $dayLabel, 'data' => []];
            foreach ($heatmapHours as $hourLabel) {
                $hourInt = (int) $hourLabel;
                $value = $contacts->whereNotNull('read_at')
                    ->filter(function ($c) use ($idx, $hourInt, $loc) {
                        if (!$c->read_at) return false;
                        $r   = $loc($c->read_at);
                        $dow = (int) $r->format('N') - 1; // Mon=0
                        $h   = (int) $r->format('G');
                        return $dow === $idx && $h >= $hourInt && $h < ($hourInt + 3);
                    })->count();
                $row['data'][] = ['x' => $hourLabel, 'y' => $value];
            }
            $heatmap[] = $row;
        }

        // ----- chart-intents: TODO — no intent labels tracked yet. -----
        // Fallback: split the responded_count across three generic buckets.
        $intentTotal = (int) $campaign->responded_count;
        $intents = [
            'labels' => ['Order', 'Support', 'Other'],
            'series' => [
                (int) round($intentTotal * 0.4),
                (int) round($intentTotal * 0.3),
                (int) round($intentTotal * 0.3),
            ],
        ];

        // ----- chart-segments: top 5 group breakdown of recipients -----
        $contactIds = $contacts->pluck('contact_id')->filter()->unique()->values();
        $segmentLabels = [];
        $segmentValues = [];
        if ($contactIds->isNotEmpty()) {
            $contactRows = Contact::query()->forCurrentWorkspace()->whereIn('id', $contactIds)->get(['id', 'contact_group']);
            $tally = [];
            foreach ($contactRows as $c) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                foreach ($list as $gid) {
                    $key = (string) $gid;
                    $tally[$key] = ($tally[$key] ?? 0) + 1;
                }
            }
            arsort($tally);
            $top = array_slice($tally, 0, 5, true);
            if (!empty($top)) {
                $groupRows = ContactGroup::whereIn('id', array_keys($top))->get(['id', 'user_group']);
                foreach ($top as $gid => $count) {
                    $row = $groupRows->firstWhere('id', (int) $gid);
                    $segmentLabels[] = $row && $row->user_group ? (string) $row->user_group : ('Group #' . $gid);
                    $segmentValues[] = $count;
                }
            }
        }
        if (empty($segmentLabels)) {
            $segmentLabels = ['All contacts'];
            $segmentValues = [(int) $campaign->total_recipients];
        }

        // ----- chart-failures: top 5 failure reasons (from decrypted error_message). -----
        $failureLabels = [];
        $failureValues = [];
        $failedRows = $contacts->where('status', 'failed');
        if ($failedRows->count() > 0) {
            $reasons = [];
            foreach ($failedRows as $row) {
                $reason = trim((string) ($row->error_message ?: 'Unknown'));
                if ($reason === '') $reason = 'Unknown';
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
            arsort($reasons);
            $top = array_slice($reasons, 0, 5, true);
            foreach ($top as $label => $count) {
                $failureLabels[] = $label;
                $failureValues[] = $count;
            }
        }
        if (empty($failureLabels)) {
            // Fallback when no decrypted messages exist yet.
            $failed = (int) $campaign->failed_count;
            if ($failed > 0) {
                $failureLabels = ['Pending diagnosis'];
                $failureValues = [$failed];
            } else {
                $failureLabels = ['No failures'];
                $failureValues = [0];
            }
        }

        return [
            'delivery' => [
                'categories' => $deliveryCategories,
                'sent'       => array_map('intval', $sentSeries),
                'delivered'  => array_map('intval', $deliveredSeries),
                'read'       => array_map('intval', $readSeries),
            ],
            'status' => [
                'labels' => ['Sent', 'Delivered', 'Read', 'Failed', 'Responded'],
                'series' => [
                    $statusFromLog['sent'],
                    $statusFromLog['delivered'],
                    $statusFromLog['read'],
                    $statusFromLog['failed'],
                    $statusFromLog['responded'],
                ],
            ],
            'throughput' => [
                'categories' => $throughputCats,
                'series'     => array_map('intval', $throughputData),
            ],
            'engagement' => [
                'categories' => $engagementCats,
                'clicks'     => array_map('intval', $clicksSeries),
                'replies'    => array_map('intval', $repliesSeries),
            ],
            'readHeatmap' => $heatmap,
            'intents'     => $intents,
            'segments'    => [
                'labels' => $segmentLabels,
                'series' => array_map('intval', $segmentValues),
            ],
            'failures' => [
                'labels' => $failureLabels,
                'series' => array_map('intval', $failureValues),
            ],
        ];
    }

    public function update(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        if (!in_array($campaign->status, ['draft', 'paused', 'scheduled'], true)) {
            $msg = 'This campaign can no longer be edited.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
        }

        $request->validate([
            'campaign_name'           => 'required|string|max:191',
            'device_id'               => 'nullable|integer',
            // Multi-engine: unified picker posts a composite `engine:id` key.
            // device_id stays accepted for back-compat (legacy single-engine form).
            'sender'                  => 'nullable|string|max:64',
            'campaign_type'           => 'required|in:text,template,button,flow,media,custom',
            'status'                  => 'nullable|string|max:32',
            'custom_message'          => 'nullable|string',
            'custom_message_b'        => 'nullable|string',
            'ab_testing'              => 'nullable|boolean',
            'ab_split'                => 'nullable|integer|min:0|max:100',
            'custom_header'           => 'nullable|string|max:255',
            'custom_footer'           => 'nullable|string|max:255',
            'custom_buttons'          => 'nullable|array',
            'custom_quick_replies'    => 'nullable|array',
            // Same positional-placeholder map the create composer emits, so
            // editing a custom body keeps the {{1}}→attribute resolution.
            'custom_message_variable_map' => 'nullable|string',
            'template_id'             => 'nullable|integer',
            'template_id_a'           => 'nullable|integer',
            'template_id_b'           => 'nullable|integer',
            // Send-time template overrides (JSON from the mapping panel).
            // Shape is validated by TemplateOverrideResolver::sanitize(),
            // which drops anything it can't walk — the column is read back
            // on every send, so it must never hold an unknown shape.
            'template_overrides'      => 'nullable|string|max:65000',
            // Variant B's own mapping. Only meaningful when ab_testing is on
            // AND template_id_b differs from A; ignored otherwise.
            'template_overrides_b'    => 'nullable|string|max:65000',
            'flow_id'                 => 'nullable|integer',
            'flow_id_b'               => 'nullable|integer',
            'schedule_type'           => 'required|in:now,scheduled,recurring',
            'send_date'               => 'nullable|date',
            'send_time'               => 'nullable',
            'expires_at'              => 'nullable|date',
            'timezone'                => ['nullable', 'string', \Illuminate\Validation\Rule::in(\DateTimeZone::listIdentifiers())],
            'repeat_interval'         => 'nullable|in:daily,weekly,monthly',
            'repeat_until'            => 'nullable|date',
            // Smart Delivery (anti-ban) — all optional; blank = global default.
            'throttle_min_sec'        => 'nullable|integer|min:0|max:3600',
            'throttle_max_sec'        => 'nullable|integer|min:0|max:3600|gte:throttle_min_sec',
            'batch_size'              => 'nullable|integer|min:1|max:10000',
            'batch_pause_min'         => 'nullable|integer|min:0|max:1440',
            'daily_limit'             => 'nullable|integer|min:1|max:100000',
            'window_start'            => 'nullable|date_format:H:i',
            'window_end'              => 'nullable|date_format:H:i',
            'recipients'              => 'nullable|array',
            'recipients.*'            => 'integer',
            'groups'                  => 'nullable|array',
            // Tag audience — send to everyone carrying these tags.
            'tags'                    => 'nullable|array',
            'tags.*'                  => 'integer',
            'groups.*'                => 'integer',
            // Replacing the existing attachment is optional; an edit that
            // touches no media leaves the persisted path untouched.
            'custom_image'            => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'custom_video'            => 'nullable|file|mimes:mp4|max:16384',
            'custom_document'         => 'nullable|file|mimes:pdf,doc,docx|max:16384',
        ]);

        // Named → positional normalization for the CUSTOM body — identical to
        // store() so what we persist stays canonical. Idempotent for bodies
        // that are already positional or have no tokens.
        [$normMsg, $normMap] = $this->normalizeCustomMessage(
            (string) $request->input('custom_message', ''),
            (string) $request->input('custom_message_variable_map', '')
        );

        $campaign->fill($request->only([
            'campaign_name', 'device_id', 'campaign_type', 'status',
            'custom_header', 'custom_footer', 'custom_message_b',
            'custom_buttons', 'custom_quick_replies',
            'template_id', 'template_id_a', 'template_id_b', 'flow_id', 'flow_id_b',
            'schedule_type', 'send_date', 'send_time', 'timezone',
        ]));
        $campaign->custom_message      = $normMsg;
        $campaign->custom_variable_map = $normMap;

        // Send-time template overrides. Only touched when the field was
        // actually submitted — an edit form that doesn't render the panel
        // (e.g. a custom-message campaign) must not wipe a stored override.
        // An empty submitted value DOES clear it: that's the operator
        // deliberately reverting to the template's own mapping.
        if ($request->has('template_overrides_b')) {
            // Variant B's own mapping — see the migration note. Kept separate
            // so an A/B campaign with two different templates doesn't send B
            // with A's values.
            $campaign->template_overrides_b = \App\Services\TemplateOverrideResolver::sanitize(
                $request->input('template_overrides_b')
            );
        }
        if ($request->has('template_overrides')) {
            $campaign->template_overrides = \App\Services\TemplateOverrideResolver::sanitize(
                $request->input('template_overrides')
            );
        }

        // A/B testing flags — not in the fill() list, so set explicitly.
        $campaign->ab_testing = (bool) $request->boolean('ab_testing');
        $campaign->ab_split   = (int) ($request->input('ab_split') ?? $campaign->ab_split ?? 50);

        // Smart Delivery (anti-ban) — persist edits; null clears back to the
        // global default. (These aren't in the fill() list above so an edit
        // would otherwise silently drop them.)
        foreach (['throttle_min_sec', 'throttle_max_sec', 'batch_size', 'batch_pause_min', 'daily_limit'] as $f) {
            $campaign->{$f} = $request->filled($f) ? (int) $request->input($f) : null;
        }
        $campaign->window_start = $request->filled('window_start') ? substr((string) $request->input('window_start'), 0, 5) : null;
        $campaign->window_end   = $request->filled('window_end') ? substr((string) $request->input('window_end'), 0, 5) : null;
        // Per-campaign end date — a blank field clears it (falls back to the
        // admin's default auto-end). Not in fill() so it wouldn't save otherwise.
        $campaign->expires_at = $this->resolveCampaignExpiry($request);
        // Never persist an empty timezone — active-hours windows must resolve in
        // the workspace's local tz, not silently in UTC.
        if (empty($campaign->timezone)) {
            $campaign->timezone = optional($request->user()?->currentWorkspace)->timezone ?: config('app.timezone', 'UTC');
        }

        // Multi-engine: honor a sender changed via the unified picker (composite
        // engine:id key). Set device_id + provider together so an edit that
        // switches engines re-routes the campaign. No sender key → device_id
        // stays whatever the fill() above kept (legacy single-engine edit).
        if ($request->filled('sender')) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($campaign->workspace_id, $request->input('sender'));
            if ($picked) {
                $campaign->device_id = (int) $picked['id'];
                $campaign->provider  = (string) $picked['engine'];
            }
        }

        $scheduleType = (string) $request->input('schedule_type');
        // Recurring cadence — only meaningful when the campaign repeats.
        $campaign->repeat_interval = $scheduleType === 'recurring'
            ? ($request->input('repeat_interval') ?: 'weekly') : null;
        $campaign->repeat_until = $scheduleType === 'recurring'
            ? $request->input('repeat_until') : null;

        // Optional attachment swap — first non-empty of image/video/document
        // wins, mirroring store(). A missing file leaves the stored path as-is.
        if ($request->hasFile('custom_image')) {
            $campaign->custom_image    = $request->file('custom_image')->store('campaign-media', media_disk());
            $campaign->custom_video    = null;
            $campaign->custom_document = null;
        } elseif ($request->hasFile('custom_video')) {
            $campaign->custom_video    = $request->file('custom_video')->store('campaign-media', media_disk());
            $campaign->custom_image    = null;
            $campaign->custom_document = null;
        } elseif ($request->hasFile('custom_document')) {
            $campaign->custom_document = $request->file('custom_document')->store('campaign-media', media_disk());
            $campaign->custom_image    = null;
            $campaign->custom_video    = null;
        }

        $campaign->save();

        // Recipient sync — only when the form actually carried an audience
        // selection (the HTML edit form does; the legacy JSON path may not).
        // Rebuild the per-contact log from the union of picked contacts +
        // group members so the campaign always reflects the current choice.
        if ($request->has('recipients') || $request->has('groups')) {
            $contactIds = collect($request->input('recipients', []))->map(fn ($v) => (int) $v);
            $groupIds   = collect($request->input('groups', []))->map(fn ($v) => (string) $v);

            if ($groupIds->isNotEmpty()) {
                $groupMembers = Contact::query()
                    ->forCurrentWorkspace()
                    ->get(['id', 'contact_group'])
                    ->filter(function ($c) use ($groupIds) {
                        $list = is_array($c->contact_group) ? $c->contact_group : [];
                        foreach ($list as $gid) {
                            if ($groupIds->contains((string) $gid)) return true;
                        }
                        return false;
                    })
                    ->pluck('id');
                $contactIds = $contactIds->merge($groupMembers)->unique()->values();
            }

            $contactIds = $contactIds->unique()->values();
            if ($contactIds->isNotEmpty()) {
                WpCampaignContact::where('campaign_id', $campaign->id)->delete();
                foreach ($contactIds as $cid) {
                    WpCampaignContact::create([
                        'campaign_id' => $campaign->id,
                        'contact_id'  => $cid,
                        'status'      => 'queued',
                    ]);
                }
                $campaign->total_recipients = $contactIds->count();
                $campaign->save();
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => 'Campaign updated.',
                'campaign' => $campaign,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign updated.');
    }

    // -----------------------------------------------------------------
    // Lifecycle actions
    // -----------------------------------------------------------------

    public function destroy(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        // Cascade-delete recipient log rows (no FK constraint on the table).
        WpCampaignContact::where('campaign_id', $campaign->id)->delete();
        $campaign->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign deleted.',
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.index')->with('status', 'Campaign deleted.');
    }

    public function cancel(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $campaign->status = 'cancelled';
        $campaign->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign cancelled.',
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign cancelled.');
    }

    public function resume(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $campaign->status = 'running';
        $campaign->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign resumed.',
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign resumed.');
    }

    public function sendNow(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $campaign->status    = 'running';
        $campaign->send_date = Carbon::now()->toDateString();
        $campaign->send_time = Carbon::now()->format('H:i:s');
        $campaign->save();

        // Pull the contact ids from the queued log rows and fire each
        // through the dispatcher. Reuses the same helper as the "now"
        // path in store() so behaviour is identical.
        $contactIds = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('contact_id')
            ->all();
        $this->dispatchCampaignNow($campaign, $contactIds, $campaign->campaign_type, [
            'template_id'          => $campaign->template_id,
            'custom_message'       => $campaign->custom_message,
            'custom_header'        => $campaign->custom_header,
            'custom_footer'        => $campaign->custom_footer,
            'custom_buttons'       => $campaign->custom_buttons,
            'custom_quick_replies' => $campaign->custom_quick_replies,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign is sending now.',
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign is sending now.');
    }

    /**
     * POST /wa-campaigns/{id}/resend — re-run a campaign that already finished
     * (completed / failed / cancelled) WITHOUT cloning the row. Every recipient
     * log row is reset to 'queued', the aggregate counters are zeroed, and the
     * campaign is dispatched again exactly the way the create + sweeper paths
     * do — reusing fireScheduledCampaign's payload shape so all custom fields
     * (header/footer/buttons/variable_map/media) ride along.
     *
     * Billing is NOT bypassed: dispatchCampaignNow runs OverflowBilling per
     * send, identical to the first run. A running campaign can't be resent —
     * it's still in flight.
     */
    public function resend(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        // Guard: only re-run a finished campaign. A running one is in flight.
        if (!in_array($campaign->status, ['completed', 'failed', 'cancelled'], true)) {
            $msg = 'Only completed, failed or cancelled campaigns can be resent.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
        }

        // WHO to resend to. Previously this always reset EVERY recipient, so
        // the only possible action was blasting the whole list again — which
        // re-messages people who already received it and burns quota.
        //
        //   failed    (default) — only the ones that didn't get through
        //   all                 — everybody, the old behaviour
        //   selected            — an explicit recipient list
        //
        // Default is `failed`: it is the safe, almost-always-intended action,
        // and a resend that accidentally re-messages a delivered customer is
        // not undoable.
        $scope = (string) $request->input('scope', 'failed');
        if (!in_array($scope, ['failed', 'all', 'selected'], true)) {
            $scope = 'failed';
        }

        $targets = WpCampaignContact::query()->where('campaign_id', $campaign->id);

        if ($scope === 'failed') {
            $targets->where('status', 'failed');
        } elseif ($scope === 'selected') {
            $ids = collect((array) $request->input('contact_ids', []))
                ->map(fn ($v) => (int) $v)->filter()->unique()->values();
            if ($ids->isEmpty()) {
                $msg = 'Pick at least one recipient to resend to.';
                return $request->wantsJson()
                    ? response()->json(['ok' => false, 'message' => $msg], 422)
                    : redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
            }
            // Scoped to THIS campaign's rows, so a forged id can't reach
            // another campaign's recipients.
            $targets->whereIn('contact_id', $ids);
        }

        $targetContactIds = (clone $targets)->pluck('contact_id')->filter()->unique()->values()->all();

        if (empty($targetContactIds)) {
            $msg = $scope === 'failed'
                ? 'Nothing to resend — no failed recipients in this campaign.'
                : 'Nothing to resend — no matching recipients.';
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
        }

        // Reset ONLY the targeted rows back to queued and clear their prior
        // send artefacts. Untargeted rows keep their history, so the campaign's
        // record of who already received it stays intact.
        (clone $targets)->update([
            'status'              => 'queued',
            'sent_at'             => null,
            'whatsapp_message_id' => null,
            'error_message'       => null,
        ]);

        // RECOMPUTE the aggregates from the per-recipient rows rather than
        // zeroing them. Blanking every counter was only correct when a resend
        // meant "the whole list again"; on a partial resend it would erase the
        // history of everyone NOT being resent, so the campaign would report 0
        // sent while most recipients had already received it.
        $counts = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $campaign->sent_count      = (int) ($counts['sent'] ?? 0) + (int) ($counts['delivered'] ?? 0) + (int) ($counts['read'] ?? 0);
        $campaign->failed_count    = (int) ($counts['failed'] ?? 0);
        $campaign->delivered_count = (int) ($counts['delivered'] ?? 0) + (int) ($counts['read'] ?? 0);
        $campaign->read_count      = (int) ($counts['read'] ?? 0);
        $campaign->completed_at    = null;

        $scheduleType = (string) $campaign->schedule_type;

        if ($scheduleType === 'now') {
            // Immediate re-run — same path the "Send now" button + store()'s
            // now-branch use. dispatchCampaignNow flips status to running and
            // completes the campaign itself.
            $campaign->status      = 'running';
            $campaign->last_run_at = now();
            $campaign->save();

            // ONLY the targeted recipients — not the whole list.
            $contactIds = $targetContactIds;

            // Payload shape mirrors fireScheduledCampaign exactly so every
            // custom field (incl. the variable map) rides along — no second,
            // divergent dispatch.
            $this->dispatchCampaignNow($campaign, $contactIds, $campaign->campaign_type, [
                'template_id'          => $campaign->template_id,
                'custom_message'       => $campaign->custom_message,
                'custom_header'        => $campaign->custom_header,
                'custom_footer'        => $campaign->custom_footer,
                'custom_buttons'       => $campaign->custom_buttons,
                'custom_quick_replies' => $campaign->custom_quick_replies,
                'custom_variable_map'  => $campaign->custom_variable_map,
                'template_overrides'   => $campaign->template_overrides,
            ]);
        } else {
            // Scheduled / recurring — hand it back to the sweeper. Reset the
            // status so fireScheduledCampaign picks it up at its send window.
            $campaign->status = 'scheduled';
            $campaign->save();
        }

        $n   = count($targetContactIds);
        $msg = match ($scope) {
            'failed'   => "Resending to {$n} failed recipient" . ($n === 1 ? '' : 's') . '.',
            'selected' => "Resending to {$n} selected recipient" . ($n === 1 ? '' : 's') . '.',
            default    => "Resending to all {$n} recipient" . ($n === 1 ? '' : 's') . '.',
        };

        if ($request->wantsJson()) {
            return response()->json([
                'ok'         => true,
                'message'    => $msg,
                'status'     => $campaign->status,
                'id'         => (int) $id,
                'scope'      => $scope,
                'recipients' => $n,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
    }

    /**
     * Fire a DUE scheduled / recurring campaign from the sweeper. There is no
     * logged-in user in that context, so we pin the campaign's creator +
     * workspace in-memory (never saved) — this is what `forCurrentWorkspace()`
     * (sender device) and the wallet charge resolve against. Reuses the exact
     * same dispatchCampaignNow path as the "Send now" button, so behaviour is
     * identical to a manual send.
     */
    public function fireScheduledCampaign(WpCampaign $campaign): void
    {
        $actor    = $campaign->created_by ? \App\Models\User::find($campaign->created_by) : null;
        $previous = Auth::user();
        if ($actor) {
            $actor->current_workspace_id = $campaign->workspace_id;   // in-memory pin only
            Auth::setUser($actor);
        }

        try {
            Log::warning('[CAMPAIGN TRACE] fireScheduledCampaign START', [
                'campaign_id'   => $campaign->id,
                'name'          => $campaign->campaign_name,
                'type'          => $campaign->campaign_type,
                'schedule_type' => $campaign->schedule_type,
                'workspace_id'  => $campaign->workspace_id,
                'actor_id'      => $actor?->id,
                'device_id'     => $campaign->device_id,
                'send_date'     => (string) $campaign->send_date,
                'send_time'     => (string) $campaign->send_time,
            ]);

            $campaign->status      = 'running';
            $campaign->last_run_at = now();
            $campaign->save();

            // Drop NULL contact_ids defensively. The web /wa-campaigns store
            // path always pre-resolves contacts so every row has a non-null
            // contact_id, but the mobile API path used to allow null when
            // auto-create failed (now hard-fails in the API store) — this
            // filter also covers legacy rows that pre-date the parity fix.
            // Without it, `Contact::whereIn('id', [null, ...])` in
            // runCampaignNowPaced silently matches zero rows and the
            // campaign reports "Campaign is being sent" but never dispatches.
            $allLogRows = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->get(['id', 'contact_id', 'phone_number']);
            $contactIds = $allLogRows->pluck('contact_id')->filter()->values()->all();
            $nullRows   = $allLogRows->whereNull('contact_id')->count();

            Log::warning('[CAMPAIGN TRACE] loaded recipients', [
                'campaign_id'        => $campaign->id,
                'recipients'         => count($contactIds),
                'null_contact_rows'  => $nullRows,
                'total_log_rows'     => $allLogRows->count(),
            ]);
            if ($nullRows > 0) {
                Log::warning('[CAMPAIGN TRACE] dropped rows with NULL contact_id — pre-resolve recipients on create', [
                    'campaign_id'      => $campaign->id,
                    'null_phone_sample'=> $allLogRows->whereNull('contact_id')->take(3)->pluck('phone_number')->all(),
                ]);
            }

            $this->dispatchCampaignNow($campaign, $contactIds, $campaign->campaign_type, [
                'template_id'          => $campaign->template_id,
                'custom_message'       => $campaign->custom_message,
                'custom_header'        => $campaign->custom_header,
                'custom_footer'        => $campaign->custom_footer,
                'custom_buttons'       => $campaign->custom_buttons,
                'custom_quick_replies' => $campaign->custom_quick_replies,
                // Without this, scheduled/recurring custom sends ship a literal {{1}}.
                'custom_variable_map'  => $campaign->custom_variable_map,
                'template_overrides'   => $campaign->template_overrides,
            ]);

            $campaign->refresh();
            Log::warning('[CAMPAIGN TRACE] dispatch finished', [
                'campaign_id'  => $campaign->id,
                'status'       => $campaign->status,
                'sent_count'   => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
            ]);

            // Recurring → advance one cadence + re-queue so it fires again;
            // otherwise dispatchCampaignNow already completed it (one-shot).
            if ($campaign->schedule_type === 'recurring') {
                $advanced = $campaign->advanceRecurring();
                Log::warning('[CAMPAIGN TRACE] recurring re-arm', [
                    'campaign_id' => $campaign->id,
                    'advanced'    => $advanced,
                    'next_date'   => (string) $campaign->send_date,
                    'next_time'   => (string) $campaign->send_time,
                ]);
                if ($advanced) {
                    // Fresh cadence — reset the per-recipient send state INCLUDING
                    // the retry counter + backoff, otherwise a recipient that
                    // exhausted its retries last cadence would be skipped as
                    // "terminal" on this brand-new occurrence.
                    WpCampaignContact::where('campaign_id', $campaign->id)
                        ->update(['status' => 'queued', 'send_attempts' => 0, 'next_attempt_at' => null]);
                    $campaign->status = 'scheduled';
                    $campaign->save();
                }
            }
        } finally {
            if ($actor) {
                if ($previous) {
                    Auth::setUser($previous);   // restore the real user (real request path)
                } else {
                    // Node heartbeat has no session — clear the pin so this actor's
                    // identity can't leak into the next campaign in the same sweep.
                    try { Auth::forgetUser(); } catch (\Throwable $e) { /* guard lacks forgetUser */ }
                }
            }
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);
        $ids = $request->input('ids', []);

        // Constrain delete to the current workspace so a forged payload
        // can't reach into another tenant's campaigns.
        $ownedIds = WpCampaign::query()
            ->forCurrentWorkspace()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        WpCampaignContact::whereIn('campaign_id', $ownedIds)->delete();
        WpCampaign::whereIn('id', $ownedIds)->delete();
        $ids = $ownedIds;

        $count   = count($ids);
        $message = "Deleted {$count} campaign" . ($count === 1 ? '' : 's') . '.';

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'ids'     => array_map('intval', $ids),
            ]);
        }

        return redirect()->route('user.wa-campaigns.index')->with('status', $message);
    }

    // -----------------------------------------------------------------
    // Build-with-AI
    // -----------------------------------------------------------------

    /**
     * GET /wa-campaigns/api/ai-models — list admin-enabled text models.
     * Mirrors the picker on /templates and /meta-ads so the UX matches.
     */
    public function apiAiModels(): JsonResponse
    {
        $rows = \DB::table('admin_ai_keys')
            ->where('is_active', true)
            ->whereNotIn('provider', ['elevenlabs'])
            ->orderBy('sort_order')
            ->get(['provider', 'name', 'default_model', 'extra_config']);

        $providerLabel = [
            'openai'    => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini'    => 'Google',
            'mistral'   => 'Mistral',
        ];

        $models = [];
        foreach ($rows as $r) {
            $label = $providerLabel[$r->provider] ?? ucfirst($r->provider);
            $default = (string) ($r->default_model ?? '');
            if ($default === '') continue;
            $extra = json_decode((string) ($r->extra_config ?? '[]'), true) ?: [];
            $extraModels = is_array($extra['models'] ?? null) ? $extra['models'] : [];
            $list = array_values(array_unique(array_merge([$default], $extraModels)));
            foreach ($list as $m) {
                $models[] = [
                    'value'    => $m,
                    'label'    => $label . ' · ' . $m,
                    'provider' => $r->provider,
                ];
            }
        }

        // BYOK — the workspace's OWN active AI keys ALSO appear and get used, so
        // the picker offers admin-enabled providers OR the user's own key.
        $ws = auth()->user()?->current_workspace_id
            ? \App\Models\Workspace::find(auth()->user()->current_workspace_id)
            : null;
        if ($ws) {
            $byokDefaults = [
                'openai'    => ['gpt-4o-mini', 'gpt-4o'],
                'anthropic' => ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
                'gemini'    => ['gemini-2.0-flash', 'gemini-1.5-pro'],
                'mistral'   => ['mistral-large-latest', 'mistral-small-latest'],
            ];
            $own = \App\Models\AiProviderKey::query()
                ->where('workspace_id', $ws->id)->where('is_active', true)
                ->pluck('provider')->all();
            foreach ($own as $prov) {
                // Workspace has its OWN key for this provider → drop the admin's
                // models for it so ONLY the user's key shows (not both).
                $models = array_values(array_filter($models, fn ($mm) => $mm['provider'] !== $prov));
                $plabel = $providerLabel[$prov] ?? ucfirst($prov);
                foreach (($byokDefaults[$prov] ?? []) as $m) {
                    $models[] = ['value' => $m, 'label' => $plabel . ' (your key) · ' . $m, 'provider' => $prov];
                }
            }
        }

        return response()->json(['ok' => true, 'models' => $models]);
    }

    /**
     * POST /wa-campaigns/api/ai-generate — generate WhatsApp campaign
     * copy from a structured brief. Returns campaign_name, body
     * message, optional footer, primary CTA button + URL, and up to
     * three quick-reply labels. The front-end pastes the response
     * into the existing #campaignForm inputs.
     */
    public function apiAiGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model'              => 'required|string|max:120',
            'provider'           => 'required|string|in:openai,anthropic,gemini',
            'business_name'      => 'required|string|max:191',
            'product'            => 'nullable|string|max:255',
            'goal'               => 'nullable|string|max:120',
            'audience'           => 'nullable|string|max:500',
            'offer'              => 'nullable|string|max:500',
            'cta_label'          => 'nullable|string|max:60',
            'cta_url'            => 'nullable|string|max:1024',
            'tone'               => 'nullable|string|max:60',
            'custom_prompt'      => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $workspace = $user?->current_workspace_id
            ? \App\Models\Workspace::find($user->current_workspace_id)
            : null;

        $resolved = \App\Services\AiKeyResolver::resolve($workspace, $data['provider']);
        if (!$resolved['key']) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_key',
                'message' => 'Admin has not enabled this provider in /admin/api-keys.',
            ], 422);
        }

        $systemPrompt = <<<'SYS'
You write WhatsApp marketing-campaign messages. Output STRICT JSON only —
no prose, no markdown, no code fences. Schema:

{
  "campaign_name": "<short lowercase slug-ish label, max 60>",
  "message":       "<the body, max 1024, plain text with optional *bold* _italic_, use {{name}} for first-name token>",
  "footer":        "<short footer line, max 60, optional, no variables>",
  "button_text":   "<primary CTA label, max 25, optional>",
  "button_url":    "<https://... destination for the CTA, optional>",
  "quick_replies": ["<max 25 chars>", "<max 25 chars>", "<max 25 chars>"]
}

Rules:
1. Respect WhatsApp Business policy — no spam wording, no all-caps
   shouting, no emojis.
2. The message should hook in the first line, explain the offer, then
   suggest the next step.
3. Use {{name}} only when personalising; never invent other tokens.
4. quick_replies is 0-3 short labels the customer can tap to respond
   (e.g. "Yes please", "Not now").
5. button_text + button_url are the CTA that opens the destination
   when the customer taps. Skip both if no URL is provided.
6. Keep tone, language, and intent consistent with the brief.
7. Output ONLY the JSON object. No explanation. No code fences.
SYS;

        $lines = [];
        $lines[] = 'Business name: ' . $data['business_name'];
        if (!empty($data['product']))   $lines[] = 'Product / service: ' . $data['product'];
        if (!empty($data['goal']))      $lines[] = 'Campaign goal: ' . $data['goal'];
        if (!empty($data['audience']))  $lines[] = 'Target audience: ' . $data['audience'];
        if (!empty($data['offer']))     $lines[] = 'Offer / hook: ' . $data['offer'];
        if (!empty($data['cta_label'])) $lines[] = 'Preferred CTA label: ' . $data['cta_label'];
        if (!empty($data['cta_url']))   $lines[] = 'CTA destination URL: ' . $data['cta_url'];
        if (!empty($data['tone']))      $lines[] = 'Tone: ' . $data['tone'];
        if (!empty($data['custom_prompt'])) {
            $lines[] = '';
            $lines[] = 'Additional notes:';
            $lines[] = $data['custom_prompt'];
        }
        $userPrompt = implode("\n", $lines);

        $ai = app(\App\Services\AiAgentService::class);
        $raw = $ai->callProvider(
            provider:     $data['provider'],
            model:        $data['model'],
            workspaceId:  (int) ($workspace?->id ?? 0),
            systemPrompt: $systemPrompt,
            userPrompt:   $userPrompt,
            maxTokens:    1200,
            temperature:  0.7,
        );

        if (!$raw) {
            return response()->json([
                'ok'      => false,
                'error'   => 'provider_failed',
                'message' => 'AI provider returned no content — check API key + model id.',
            ], 502);
        }

        $clean = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $tpl = json_decode($clean, true);
        if (!is_array($tpl)) {
            Log::warning('[AI-WaCampaign] bad JSON from model: ' . substr($raw, 0, 400));
            return response()->json([
                'ok'      => false,
                'error'   => 'bad_json',
                'message' => 'Model output was not valid JSON. Try again or refine the brief.',
                'raw'     => mb_substr($raw, 0, 600),
            ], 422);
        }

        // Hard caps so the front-end never paints something the
        // controller will reject at submit time.
        $payload = [
            'campaign_name' => mb_substr((string) ($tpl['campaign_name'] ?? ''), 0, 60),
            'message'       => mb_substr((string) ($tpl['message'] ?? ''), 0, 1024),
            'footer'        => mb_substr((string) ($tpl['footer'] ?? ''), 0, 60),
            'button_text'   => mb_substr((string) ($tpl['button_text'] ?? ''), 0, 25),
            'button_url'    => mb_substr((string) ($tpl['button_url'] ?? ''), 0, 1024),
            'quick_replies' => [],
        ];
        $qr = is_array($tpl['quick_replies'] ?? null) ? $tpl['quick_replies'] : [];
        foreach (array_slice($qr, 0, 3) as $label) {
            $payload['quick_replies'][] = mb_substr((string) $label, 0, 25);
        }

        return response()->json([
            'ok'       => true,
            'campaign' => $payload,
            'model'    => $data['model'],
        ]);
    }

    // -----------------------------------------------------------------
    // Node→Laravel status callbacks
    //
    // Mirrors BroadcastsController::nodeMessageStatus /
    // nodeBroadcastStatus. All five methods share the same auth gate
    // (X-Node-Token + hash_equals) and update wp_campaign_contacts
    // and/or the parent wpcampaigns row so the campaign detail page
    // tracks sent / delivered / read / failed in real time.
    // -----------------------------------------------------------------

    private function nodeAuthOk(Request $request): bool
    {
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        return $expected !== '' && hash_equals($expected, $given);
    }

    /**
     * Node ships ISO-8601 like `2026-05-16T09:27:01.807Z` which MySQL
     * DATETIME columns reject. Parse through Carbon → canonical form.
     */
    private function parseNodeTs(?string $raw): ?string
    {
        if (!$raw) return null;
        try {
            return \Illuminate\Support\Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Recompute wpcampaigns aggregates from the pivot. CASE-WHEN keeps
     * the call idempotent under duplicate Node webhooks.
     */
    private function recountCampaign(WpCampaign $c): void
    {
        $row = WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->selectRaw("SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) AS sent_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw("SUM(CASE WHEN status IN ('delivered','read') THEN 1 ELSE 0 END) AS delivered_count")
            ->selectRaw("SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) AS read_count")
            ->selectRaw("SUM(CASE WHEN COALESCE(responded_at, response) IS NOT NULL AND response <> '' THEN 1 ELSE 0 END) AS responded_count")
            ->selectRaw("SUM(CASE WHEN clicked = 1 THEN 1 ELSE 0 END) AS clicked_count")
            ->first();
        $c->update([
            'sent_count'      => (int) ($row->sent_count      ?? 0),
            'failed_count'    => (int) ($row->failed_count    ?? 0),
            'delivered_count' => (int) ($row->delivered_count ?? 0),
            'read_count'      => (int) ($row->read_count      ?? 0),
            'responded_count' => (int) ($row->responded_count ?? 0),
            'clicked_count'   => (int) ($row->clicked_count   ?? 0),
        ]);
    }

    /**
     * POST /api/campaigns/update-status — parent-row aggregate
     * callback. Node ships final stats once at end-of-run (mirrors
     * broadcasts.node.broadcast-status). Schema:
     *   { campaign_id, status, total_recipients, sent_count,
     *     failed_count, delivered_count, read_count,
     *     responded_count, clicked_count }
     */
    public function nodeCampaignStatus(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id'      => 'required|integer',
            'status'           => 'nullable|string|max:32',
            'total_recipients' => 'nullable|integer',
            'sent_count'       => 'nullable|integer',
            'failed_count'     => 'nullable|integer',
            'delivered_count'  => 'nullable|integer',
            'read_count'       => 'nullable|integer',
            'responded_count'  => 'nullable|integer',
            'clicked_count'    => 'nullable|integer',
        ]);
        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        // Trust the pivot — recount from wp_campaign_contacts rather
        // than from Node's in-memory counts. Node's snapshot can drift
        // if a callback was dropped; the pivot is the source of truth.
        $this->recountCampaign($c);
        if (!empty($data['status'])) {
            $c->update(['status' => $data['status']]);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/campaigns/update-contact-status — per-recipient
     * callback fired by Node's campaignService.updateContactStatus().
     * Schema:
     *   { campaign_id, contact_id, status, error_message?,
     *     whatsapp_message_id?, variant?, sent_at|delivered_at|read_at }
     */
    public function nodeContactStatus(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id'         => 'required|integer',
            'contact_id'          => 'required|integer',
            'status'              => 'required|in:queued,pending,sent,delivered,read,failed,unsubscribed',
            'error_message'       => 'nullable|string|max:1024',
            'whatsapp_message_id' => 'nullable|string|max:191',
            'variant'             => 'nullable|string|max:8',
            'sent_at'             => 'nullable|date',
            'delivered_at'        => 'nullable|date',
            'read_at'             => 'nullable|date',
        ]);

        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        $updates = ['status' => $data['status'], 'updated_at' => now()];
        if (!empty($data['error_message']))       $updates['error_message']       = mb_substr($data['error_message'], 0, 1024);
        if (!empty($data['whatsapp_message_id'])) $updates['whatsapp_message_id'] = $data['whatsapp_message_id'];
        if (!empty($data['variant']))             $updates['variant']             = mb_substr($data['variant'], 0, 8);
        foreach (['sent_at', 'delivered_at', 'read_at'] as $col) {
            if ($ts = $this->parseNodeTs($data[$col] ?? null)) {
                $updates[$col] = $ts;
            }
        }

        WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->where('contact_id',  $data['contact_id'])
            ->update($updates);

        // Mirror the campaign send into the team-inbox thread. The WABA path
        // mirrors inline during its synchronous per-recipient loop; Unofficial
        // and Twilio dispatch via Node and only report back HERE, so this is
        // their equivalent hook. Without it a campaign template on those two
        // engines never appeared in the inbox at all.
        //
        // 'sent' only — delivered/read are later flips of an existing bubble.
        // InboxMirror de-dupes on wa_message_id, so the WABA path mirroring
        // inline AND a status callback arriving for the same wamid cannot
        // produce two bubbles.
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
                    $tpl  = $c->template_id ? \App\Models\WaTemplate::find($c->template_id) : null;
                    $body = $tpl
                        ? \App\Services\Inbox\InboxMirror::readableTemplateBody(
                            \App\Services\Whatsapp\TemplateDataBuilder::build($tpl, (int) $c->workspace_id)
                        )
                        : (string) ($c->custom_message ?? '');


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
                                app(BroadcastsController::class)->varsForRecipient($tpl, $row, (int) $c->workspace_id, null)
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
                    // campaign carried no text — posting it would put a
                    // silent, contentless bubble in the customer's thread.
                    if (trim($body) === '') {
                    } else {
                    app(\App\Services\Inbox\InboxMirror::class)->appendOutboundToOpenConversation(
                        (int) $c->workspace_id,
                        $phone,
                        $body,
                        $data['whatsapp_message_id'] ?? null,
                        $c->provider ?: null,
                        array_filter([
                            'type'          => $tpl ? 'template' : 'text',
                            // Buttons so the inbox bubble renders the same
                            // CTAs the customer saw on WhatsApp.
                            'buttons'       => ($tpl && is_array($tpl->buttons) && $tpl->buttons) ? $tpl->buttons : null,
                            'template_name' => $tpl?->template_name,
                            'campaign_id'   => $c->id,
                            'source'        => 'campaign',
                            // Must match the key the send loop used.
                            'src_key'       => 'campaign:' . $c->id . ':contact:' . $data['contact_id'],
                        ], fn ($v) => $v !== null),
                    );
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('[CAMPAIGN-MIRROR] failed: ' . $e->getMessage());
            }
        }

        // Webhook: campaign_contact_status_updated (this write is a mass
        // UPDATE, which bypasses model events — so emit explicitly).
        \App\Services\WebhookService::emit('campaign_contact_status_updated', [
            'workspace_id'  => $c->workspace_id,
            'user_id'       => $c->created_by,
            'campaign_id'   => $c->id,
            'campaign_name' => $c->campaign_name,
            'contact_id'    => (int) $data['contact_id'],
            'status'        => $data['status'],
            'wamid'         => $data['whatsapp_message_id'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'timestamp'     => now()->timestamp,
        ], $c->created_by);

        $this->recountCampaign($c);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/campaigns/update-status-by-id — fallback used by
     * Node's handleCampaignMessageUpdate() when the campaign isn't in
     * its in-memory map (after pm2 restart or for the chat-endpoint
     * dispatch path used by this controller). Schema:
     *   { message_id, status }
     *
     * We look up wp_campaign_contacts by whatsapp_message_id and patch
     * the status + delivered_at/read_at. Without this fallback Node
     * gives up and the delivered/read receipts never land.
     */
    public function nodeStatusByMessageId(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'message_id' => 'required|string|max:191',
            'status'     => 'required|in:queued,pending,sent,delivered,read,failed',
            'timestamp'  => 'nullable|date',
        ]);

        $row = WpCampaignContact::query()
            ->where('whatsapp_message_id', $data['message_id'])
            ->first();
        if (!$row) {
            // Not a campaign message — probably a broadcast or chat
            // reply that happened to flow through the same listener.
            return response()->json(['ok' => true, 'matched' => false]);
        }

        // Only ratchet forward — don't regress read → delivered if a
        // late callback arrives out of order.
        $rank = ['queued' => 0, 'pending' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'failed' => 9];
        $cur  = $rank[$row->status] ?? 0;
        $new  = $rank[$data['status']] ?? 0;
        if ($new < $cur) {
            return response()->json(['ok' => true, 'matched' => true, 'skipped' => 'older']);
        }

        $ts = $this->parseNodeTs($data['timestamp'] ?? null) ?? now()->toDateTimeString();
        $updates = ['status' => $data['status'], 'updated_at' => now()];
        if ($data['status'] === 'sent'      && !$row->sent_at)      $updates['sent_at']      = $ts;
        if ($data['status'] === 'delivered' && !$row->delivered_at) $updates['delivered_at'] = $ts;
        if ($data['status'] === 'read'      && !$row->read_at)      $updates['read_at']      = $ts;

        $row->update($updates);

        $campaign = WpCampaign::find($row->campaign_id);
        if ($campaign) {
            $this->recountCampaign($campaign);
            \App\Services\WebhookService::emit('campaign_contact_status_updated', [
                'workspace_id'  => $campaign->workspace_id,
                'user_id'       => $campaign->created_by,
                'campaign_id'   => $campaign->id,
                'campaign_name' => $campaign->campaign_name,
                'contact_id'    => (int) $row->contact_id,
                'status'        => $data['status'],
                'wamid'         => $data['message_id'],
                'timestamp'     => now()->timestamp,
            ], $campaign->created_by);
        }

        return response()->json(['ok' => true, 'matched' => true]);
    }

    /**
     * POST /api/campaigns/track-response — fired by Node when a
     * recipient replies to a campaign message. Updates the pivot's
     * response + responded_at so the operator sees reply rate.
     */
    public function nodeTrackResponse(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id'  => 'required|integer',
            'contact_id'   => 'required|integer',
            'response'     => 'nullable|string|max:4096',
            'responded_at' => 'nullable|date',
        ]);
        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->where('contact_id',  $data['contact_id'])
            ->update([
                'response'     => mb_substr((string) ($data['response'] ?? ''), 0, 4096),
                'responded_at' => $this->parseNodeTs($data['responded_at'] ?? null) ?? now()->toDateTimeString(),
                'updated_at'   => now(),
            ]);

        // Webhook: campaign_contact_replied (mass UPDATE bypasses model events).
        \App\Services\WebhookService::emit('campaign_contact_replied', [
            'workspace_id'  => $c->workspace_id,
            'user_id'       => $c->created_by,
            'campaign_id'   => $c->id,
            'campaign_name' => $c->campaign_name,
            'contact_id'    => (int) $data['contact_id'],
            'response'      => mb_substr((string) ($data['response'] ?? ''), 0, 4096),
            'timestamp'     => now()->timestamp,
        ], $c->created_by);

        $this->recountCampaign($c);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/campaigns/unsubscribe — Node detected a STOP/UNSUB
     * keyword in a reply. Mark the pivot row + flip contact-level
     * unsubscribe so future campaigns skip this number.
     */
    public function nodeUnsubscribe(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id' => 'required|integer',
            'phone'       => 'required|string|max:32',
        ]);
        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        $digits = preg_replace('/\D+/', '', (string) $data['phone']);

        // Mobile is encrypted-at-rest (non-deterministic) — it can't be queried
        // in SQL, so hydrate the campaign's pivot contacts and match the
        // decrypted number in PHP.
        $last10 = substr($digits, -10);

        // Resolve the contact first so we can match the pivot on contact_id.
        // Campaigns built from a contact list write contact_id but leave the
        // denormalised phone_number BLANK — matching on phone alone found
        // nothing for those, so the row stayed subscribed and the campaign's
        // Opt-outs tab showed "nobody opted out" even though the STOP was
        // honoured at contact level.
        $optContact = \App\Models\Contact::query()
            ->where('workspace_id', $c->workspace_id)
            ->get(['id', 'mobile', 'country_code'])
            ->first(function ($ct) use ($digits, $last10) {
                $d = preg_replace('/\D+/', '', (string) ($ct->country_code . $ct->mobile))
                    ?: preg_replace('/\D+/', '', (string) $ct->mobile);
                return $d !== '' && ($d === $digits || ($last10 !== '' && str_ends_with($d, $last10)));
            });

        $row = WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->get()
            ->first(function ($r) use ($digits, $last10, $optContact) {
                if ($optContact && (int) $r->contact_id === (int) $optContact->id) {
                    return true;
                }
                $d = preg_replace('/\D+/', '', (string) $r->phone_number);
                return $d !== '' && ($d === $digits || ($last10 !== '' && str_ends_with($d, $last10)));
            });

        if ($row) {
            $row->update([
                'status'           => 'unsubscribed',
                'is_unsubscribed'  => true,
                'unsubscribed'     => true,
                'unsubscribed_at'  => now(),
                'updated_at'       => now(),
            ]);
            // Flip the contact's workspace-level unsubscribe flag too
            // so other campaigns + broadcasts auto-skip the number.
            if ($row->contact_id) {
                $alreadyOut = (bool) optional(Contact::find($row->contact_id))->is_unsubscribed;
                Contact::query()
                    ->where('id', $row->contact_id)
                    ->update(['is_unsubscribed' => true]);

                // Webhook: contact_opt_in (covers opt-out too — STOP keyword).
                // Only fire on an actual transition, and emit explicitly since
                // this is a mass UPDATE that bypasses the Contact model events.
                if (!$alreadyOut) {
                    \App\Services\WebhookService::emit('contact_opt_in', [
                        'workspace_id' => $c->workspace_id,
                        'user_id'      => $c->created_by,
                        'contact_id'   => (int) $row->contact_id,
                        'phone_number' => preg_replace('/\D+/', '', (string) $row->phone_number) ?: null,
                        'opted_in'     => false,
                        'action'       => 'unsubscribed',
                        'source'       => 'stop_keyword',
                        'timestamp'    => now()->timestamp,
                    ], $c->created_by);
                }
            }
            $this->recountCampaign($c);
        }

        return response()->json(['ok' => true]);
    }
}
