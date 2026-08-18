<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Flow;
use App\Models\WaTemplate;
use App\Models\WpCampaign;
use App\Models\WpCampaignContact;
use App\Services\WorkspaceEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile-app Campaigns API (WaDesk).
 *
 * Response shapes are kept byte-compatible with the existing app — the
 * old data model used a per-user `WpCampaign` with `template`/`templateA`/
 * `templateB`/`flow`/`device`/`creator` Eloquent relations and a
 * `wpcampaigncontacts` table. OUR model is workspace-scoped and only
 * defines `contacts` + `creator`, so device / template / flow names are
 * resolved here with small lookups instead of eager loads, and queries
 * are scoped to the Sanctum user's current workspace via
 * WpCampaign::scopeForCurrentWorkspace(). Every response key the app
 * reads is preserved.
 *
 * Send / dispatch handoff to the Node bridge is STUBBED — see notes in
 * store() and stop(). This controller persists the campaign + recipients
 * and returns the contract shape, but does NOT itself fire the Node call;
 * the workspace's real dispatch path (WaCampaignsController /
 * NodeScheduler) owns that. Marked inline where it matters.
 */
class CampaignController extends Controller
{
    /**
     * GET /campaigns — list the workspace's campaigns.
     * Shape: { success, message, data: [ transformCampaign(), ... ] }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = WpCampaign::query()->forCurrentWorkspace();

            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($type = $request->get('campaign_type')) {
                $query->where('campaign_type', $type);
            }
            if ($search = $request->get('search')) {
                // campaign_name is encrypted at rest, so a SQL LIKE can't run
                // against it. Pull the workspace's rows and filter in PHP.
                $query->latest();
                $campaigns = $query->get()->filter(function ($c) use ($search) {
                    return mb_stripos((string) $c->campaign_name, $search) !== false;
                })->values();
            } else {
                $campaigns = $query->latest()->get();
            }

            $data = $campaigns->map(fn ($c) => $this->transformCampaign($c))->values();

            return response()->json([
                'success' => true,
                'message' => 'Campaigns retrieved successfully',
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\CampaignController@index failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /campaigns/{id} — one campaign with contacts, segments & metrics.
     * Mirrors the old show() payload (campaign, contacts, segments, metrics,
     * counts, ab_stats, templates, flow, date_filter).
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

            [$startDate, $endDate] = $this->resolveRange($request, $campaign);

            $contactsQuery = $campaign->contacts();
            if ($startDate) {
                $contactsQuery->where('updated_at', '>=', $startDate);
            }
            if ($endDate) {
                $contactsQuery->where('updated_at', '<=', $endDate);
            }
            $contacts = $contactsQuery->orderBy('updated_at', 'desc')->get();

            $contactDetails = $contacts->map(fn ($cc) => $this->transformContact($cc))->values();

            // Metrics from the FILTERED contact set.
            $sentCount = $contacts->filter(fn ($c) => !in_array($c->status, ['pending', 'failed']))->count();
            $deliveredCount = $contacts->filter(fn ($c) => in_array($c->status, ['delivered', 'read']))->count();
            $readCount = $contacts->where('status', 'read')->count();
            $clickedCount = $contacts->where('clicked', true)->count();
            $respondedCount = $contacts->whereNotNull('response')->count();
            $failedCount = $contacts->where('status', 'failed')->count();

            $totalBase = $sentCount;
            $currentMetrics = [
                'delivery_rate' => $totalBase > 0 ? ($deliveredCount / $totalBase) * 100 : 0,
                'read_rate' => $deliveredCount > 0 ? ($readCount / $deliveredCount) * 100 : 0,
                'click_rate' => $totalBase > 0 ? ($clickedCount / $totalBase) * 100 : 0,
                'response_rate' => $totalBase > 0 ? ($respondedCount / $totalBase) * 100 : 0,
            ];

            // Trend metrics (green default; no previous-period comparison wired
            // here because the analytics table query is workspace-internal —
            // the app reads value/percentage/count/color/is_up, all present).
            $metricsWithTrends = [
                'delivery' => $this->trendBlock(round($currentMetrics['delivery_rate'], 1), $deliveredCount),
                'read' => $this->trendBlock(round($currentMetrics['read_rate'], 1), $readCount),
                'click' => $this->trendBlock(round($currentMetrics['click_rate'], 1), $clickedCount),
                'response' => $this->trendBlock(round($currentMetrics['response_rate'], 1), $respondedCount),
            ];

            $abStats = $campaign->ab_testing ? $this->buildAbStats($campaign, $contacts, $sentCount, $deliveredCount, $currentMetrics) : null;

            $segments = [
                'sent' => $contactDetails->filter(fn ($c) => $c['sent'])->values(),
                'failed' => $contactDetails->filter(fn ($c) => $c['failed'])->values(),
                'delivered' => $contactDetails->filter(fn ($c) => $c['delivered'])->values(),
                'read' => $contactDetails->filter(fn ($c) => $c['read'])->values(),
                'clicked' => $contactDetails->filter(fn ($c) => $c['clicked'])->values(),
                'responded' => $contactDetails->filter(fn ($c) => $c['responded'])->values(),
            ];

            $templateMain = $campaign->template_id ? $this->findTemplate($campaign->template_id) : null;
            $templateA = $campaign->template_id_a ? $this->findTemplate($campaign->template_id_a) : null;
            $templateB = $campaign->template_id_b ? $this->findTemplate($campaign->template_id_b) : null;
            $flow = $campaign->flow_id ? $this->findFlow($campaign->flow_id) : null;
            $flowB = $campaign->flow_id_b ? $this->findFlow($campaign->flow_id_b) : null;

            return response()->json([
                'success' => true,
                'message' => 'Campaign details retrieved successfully',
                'data' => [
                    'campaign' => $this->transformCampaign($campaign),
                    'contacts' => $contactDetails,
                    'segments' => $segments,
                    'metrics' => $metricsWithTrends,
                    'raw_metrics' => $currentMetrics,
                    'counts' => [
                        'total' => $contacts->count(),
                        'sent' => $sentCount,
                        'delivered' => $deliveredCount,
                        'read' => $readCount,
                        'failed' => $failedCount,
                        'clicked' => $clickedCount,
                        'responded' => $respondedCount,
                    ],
                    'ab_stats' => $abStats,
                    'ab_winner' => $abStats ? ($abStats['winner']['winner'] ?? null) : null,
                    'template' => $templateMain ? $this->transformTemplate($templateMain) : null,
                    'template_a' => $templateA ? $this->transformTemplate($templateA) : null,
                    'template_b' => $templateB ? $this->transformTemplate($templateB) : null,
                    'flow' => $flow ? [
                        'id' => $flow->id,
                        'flow_name' => $flow->flow_name,
                        'description' => $flow->description ?? null,
                    ] : null,
                    'flow_b' => $flowB ? [
                        'id' => $flowB->id,
                        'flow_name' => $flowB->flow_name,
                        'description' => $flowB->description ?? null,
                    ] : null,
                    'date_filter' => [
                        'current' => [
                            'start' => $startDate?->toIso8601String(),
                            'end' => $endDate?->toIso8601String(),
                        ],
                        'previous' => [
                            'start' => null,
                            'end' => null,
                        ],
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('App\CampaignController@show failed', ['campaign_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * POST /campaigns — create a campaign, recipient rows, and SEND it.
     *
     * Recipients arrive as phone numbers and the device as a phone number;
     * both are bridged to the web model (device → row id via findDevice,
     * phones → find-or-create Contact ids) so the campaign is shaped exactly
     * like a /wa-campaigns one. Dispatch is then handed to the SAME paced web
     * dispatcher (WaCampaignsController::fireScheduledCampaign), which runs
     * after the HTTP response and applies msg_gap/batch pacing:
     *   - now       → fired immediately (single dispatch, no double-send)
     *   - scheduled → left for the heartbeat campaign sweeper (send_date set)
     *   - recurring → status 'active', handled by the recurring path
     */
    public function store(Request $request): JsonResponse
    {
        // Schedule_type alias — the web form uses 'scheduled', earlier API
        // builds used 'later'. Accept both inbound, persist 'scheduled' so the
        // CampaignScheduleSweeper (which keys on 'scheduled') picks it up.
        if (strtolower((string) $request->input('schedule_type')) === 'later') {
            $request->merge(['schedule_type' => 'scheduled']);
        }

        $validator = Validator::make($request->all(), [
            'campaign_name' => 'required|string|max:255',
            // Sender — either the row id (device_id) OR the composite
            // 'engine:id' key the web's multi-engine picker emits. Either is
            // optional; the dispatcher falls back to the workspace default.
            'device_id' => 'nullable',
            'sender' => 'nullable|string|max:64',
            // Match the web set so the app can send any campaign type the
            // dispatcher understands (text/button/media are the legacy
            // labels that still flow through the custom path).
            'campaign_type' => 'required|in:text,template,button,flow,media,custom',
            'schedule_type' => 'required|in:now,scheduled,recurring',
            'template_id' => 'nullable|integer',
            'template_id_a' => 'nullable|integer',
            'template_id_b' => 'nullable|integer',
            'flow_id' => 'nullable|integer',
            'flow_id_b' => 'nullable|integer',
            // custom_message is required for every NON-template-and-non-flow
            // type. text/button/media all carry a free-form body just like custom.
            'custom_message' => 'required_if:campaign_type,text,custom,button,media',
            // A/B variant B body (custom-type AB). Match the web column.
            'custom_message_b' => 'nullable|string',
            'send_date' => 'required_if:schedule_type,scheduled|nullable|date',
            'send_time' => 'required_if:schedule_type,scheduled|nullable',
            'timezone' => 'nullable|string',
            'ab_testing' => 'nullable',
            'ab_split' => 'nullable|integer|min:0|max:100',
            'contacts' => 'nullable',
            'groups' => 'nullable',
            // Recurring cadence — only meaningful when schedule_type=recurring.
            'repeat_interval' => 'nullable|in:daily,weekly,monthly',
            'repeat_until' => 'nullable|date',
            // Custom-message rich fields (header/footer/buttons + one media).
            'custom_header' => 'nullable|string|max:255',
            'custom_footer' => 'nullable|string|max:255',
            'custom_buttons' => 'nullable',                                     // array OR JSON string
            'custom_quick_replies' => 'nullable',                               // array OR JSON string
            'custom_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',      // <=2MB
            'custom_video' => 'nullable|file|mimes:mp4|max:16384',              // <=16MB
            'custom_document' => 'nullable|file|mimes:pdf,doc,docx|max:16384',  // <=16MB
        ]);

        $validator->after(function ($v) use ($request) {
            if (empty($request->contacts) && empty($request->groups) && empty($request->group_id) && empty($request->queue_ids)) {
                $v->errors()->add('recipients', 'Please provide at least one recipient source: contacts or groups.');
            }
            $abOn = in_array($request->ab_testing, ['1', 1, true, 'true', 'on'], true);
            if ($request->campaign_type === 'template' && !$abOn && empty($request->template_id)) {
                $v->errors()->add('template_id', 'Template is required for template campaigns.');
            }
            if ($request->campaign_type === 'template' && $abOn && (empty($request->template_id_a) || empty($request->template_id_b))) {
                $v->errors()->add('template_id_a', 'Both Template A and Template B are required for A/B testing.');
            }
            if ($request->campaign_type === 'flow' && empty($request->flow_id)) {
                $v->errors()->add('flow_id', 'Flow is required for flow campaigns.');
            }
            if ($request->campaign_type === 'flow' && $abOn && empty($request->flow_id_b)) {
                $v->errors()->add('flow_id_b', 'Variant B flow is required for A/B flow campaigns.');
            }
            // Custom-text AB needs both variant bodies — without B, recipients
            // assigned 'B' would ship the same body as A (no test actually run).
            if (in_array($request->campaign_type, ['custom', 'text', 'button', 'media'], true)
                && $abOn && empty($request->custom_message_b)) {
                $v->errors()->add('custom_message_b', 'Variant B message is required for A/B testing on custom campaigns.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Delete-proof campaign cap — enforce the SAME increment-only usage
            // ledger the web store() uses so the mobile app can't bypass the
            // plan limit by delete+recreate either. Feature gate first, then the
            // numeric quota (live count seeds the meter once on first use).
            try {
                \App\Services\PlanLimitGuard::feature($user->currentWorkspace, 'campaign');
                \App\Services\PlanLimitGuard::checkQuota(
                    $user->currentWorkspace,
                    'total_campaigns_limit',
                    WpCampaign::PLAN_QUOTA_METRIC,
                    WpCampaign::where('workspace_id', $user->current_workspace_id)->count(),
                );
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors'  => ['plan' => [$e->getMessage()]],
                ], 422);
            }

            $isAbTesting = in_array($request->ab_testing, ['1', 1, true, 'true', 'on'], true);
            $isRecurring = $request->schedule_type === 'recurring';
            $campaignType = (string) $request->campaign_type;
            $isTemplate = $campaignType === 'template';
            $isFlow     = $campaignType === 'flow';

            // Collect recipient phone numbers from `contacts` (groups/queues are
            // resolved by the web/store path; here we accept the direct list the
            // app sends).
            $recipients = [];
            if (!empty($request->contacts)) {
                $raw = $request->contacts;
                if (!is_array($raw)) {
                    $raw = explode(',', (string) $raw);
                }
                $recipients = array_merge($recipients, $raw);
            }
            $phoneNumbers = array_values(array_unique(array_filter(array_map(
                fn ($p) => preg_replace('/[^0-9]/', '', (string) $p),
                $recipients
            ))));
            $totalContacts = count($phoneNumbers);

            if ($totalContacts === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid recipients found from provided sources.',
                ], 422);
            }

            DB::beginTransaction();

            // Sender resolution — accept either device_id (row id or phone
            // digits, tolerated by findDevice) or the composite `engine:id` key
            // the web's multi-engine picker emits. Store the row id + provider
            // so the dispatcher routes through the operator-chosen engine.
            $wsId = (int) ($user->current_workspace_id ?? 0);
            $pickedDeviceId = null;
            $pickedProvider = null;
            if ($request->filled('device_id')) {
                $deviceRow = $this->findDevice($request->device_id);
                if ($deviceRow) {
                    $pickedDeviceId = (int) $deviceRow->id;
                    // CRITICAL parity fix — also stamp the engine the device
                    // actually runs on. Without this, the campaign row had
                    // provider=NULL and the dispatcher fell back to the
                    // workspace default engine (Twilio on multi-engine
                    // workspaces), so a Baileys-picked send shipped through
                    // Twilio's sandbox number and the recipient never saw it
                    // on the WhatsApp account they expected. devices table
                    // is Baileys-only by convention, so a hit here means
                    // baileys; WABA/Twilio senders come through the `sender`
                    // (engine:id) path below which sets provider explicitly.
                    $pickedProvider = WorkspaceEngine::ENGINE_BAILEYS;
                } else {
                    // No match — DON'T silently store the un-resolved value
                    // as the campaign's device_id (the old behaviour). That
                    // looked like "campaign sent" but actually routed via
                    // the workspace default engine because provider stayed
                    // NULL. Reject loudly so the app dev knows the id was
                    // wrong before the row exists.
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Sender device not found.',
                        'errors'  => [
                            'device_id' => [
                                "No device matches '" . $request->device_id . "' in your workspace. "
                                . "Pass either the Baileys devices.id from GET /api/app/devices, "
                                . "or the composite picker key via `sender` (e.g. `baileys:53`).",
                            ],
                        ],
                    ], 422);
                }
            }
            if ($request->filled('sender') && class_exists(\App\Services\WorkspaceEngine::class)) {
                try {
                    $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $request->input('sender'));
                    if ($picked) {
                        $pickedDeviceId = (int) $picked['id'];
                        $pickedProvider = (string) $picked['engine'];
                    }
                } catch (\Throwable $e) { /* malformed key — fall back to device_id */ }
            }

            // Custom-campaign media — store the uploaded file so the dispatcher
            // can ride it on the send (it reads $campaign->custom_image/video/
            // document directly). One media per campaign, image → video →
            // document precedence, exactly like the web /wa-campaigns form.
            $customImage = $customVideo = $customDocument = null;
            if ($request->hasFile('custom_image')) {
                $customImage = $request->file('custom_image')->store('campaign-media', media_disk());
            } elseif ($request->hasFile('custom_video')) {
                $customVideo = $request->file('custom_video')->store('campaign-media', media_disk());
            } elseif ($request->hasFile('custom_document')) {
                $customDocument = $request->file('custom_document')->store('campaign-media', media_disk());
            }

            // Status mapping — match the web EXACTLY so the sweeper finds the
            // row. CampaignScheduleSweeper at app/Services/CampaignScheduleSweeper.php:43
            // keys on status='scheduled'; the web sets recurring → 'scheduled'
            // too (WaCampaignsController.php:578). A previous 'active' value
            // here meant recurring campaigns were never picked up by the
            // heartbeat. fireScheduledCampaign then transitions to 'running'.
            $resolvedStatus = $request->schedule_type === 'now' ? 'running' : 'scheduled';

            $campaign = WpCampaign::create([
                'workspace_id' => $user->current_workspace_id,
                'campaign_name' => $request->campaign_name,
                'device_id' => $pickedDeviceId,
                'provider' => $pickedProvider,
                'campaign_type' => $campaignType,
                'status' => $resolvedStatus,
                'ab_testing' => $isAbTesting,
                // ab_split is a NOT NULL column. Never write null (even when A/B
                // is off) — default to 50; it's only actually used when ab_testing
                // is true (see split logic below). Matches the web controller.
                'ab_split' => $isAbTesting ? (int) ($request->ab_split ?? 50) : 50,
                'custom_message' => $request->custom_message,
                // Variant B body — only stored when A/B is on for a non-
                // template/flow campaign type (the dispatcher reads this
                // column in dispatchCampaignNow's A/B branch).
                'custom_message_b' => ($isAbTesting && !$isTemplate && !$isFlow) ? $request->custom_message_b : null,
                'custom_header' => $request->custom_header,
                'custom_footer' => $request->custom_footer,
                'custom_buttons' => is_string($request->custom_buttons) ? json_decode($request->custom_buttons, true) : $request->custom_buttons,
                'custom_quick_replies' => is_string($request->custom_quick_replies) ? json_decode($request->custom_quick_replies, true) : $request->custom_quick_replies,
                // Uploaded media paths (public disk) the dispatcher rides on send.
                'custom_image' => $customImage,
                'custom_video' => $customVideo,
                'custom_document' => $customDocument,
                'template_id'   => $isTemplate && !$isAbTesting ? $request->template_id : null,
                'template_id_a' => $isTemplate && $isAbTesting  ? $request->template_id_a : null,
                'template_id_b' => $isTemplate && $isAbTesting  ? $request->template_id_b : null,
                'flow_id'   => $isFlow ? $request->flow_id : null,
                'flow_id_b' => ($isFlow && $isAbTesting) ? $request->flow_id_b : null,
                'use_attributes' => (bool) ($request->use_attributes ?? false),
                'tracking_enabled' => (bool) ($request->tracking_enabled ?? true),
                'schedule_type' => $request->schedule_type,
                'send_date' => $request->send_date,
                'send_time' => $request->send_time,
                // Never store a bare UTC for a local workspace — the active-hours
                // window + scheduling are interpreted in THIS timezone, so fall
                // back to the workspace's own tz when none was sent.
                'timezone' => $request->timezone
                    ?: (optional($user->currentWorkspace)->timezone ?: config('app.timezone', 'UTC')),
                // Recurring cadence — only meaningful when schedule_type=recurring.
                'repeat_interval' => $isRecurring ? ($request->input('repeat_interval') ?: 'weekly') : null,
                'repeat_until'    => $isRecurring ? $request->input('repeat_until') : null,
                'total_recipients' => $totalContacts,
                'sent_count' => 0,
                'failed_count' => 0,
                'delivered_count' => 0,
                'read_count' => 0,
                'responded_count' => 0,
                'clicked_count' => 0,
                'created_by' => $user->id,
            ]);

            // Per-recipient variant assignment for A/B. Match the web exactly:
            // SHUFFLE first, then split by ab_split %. Sequential assignment
            // (the old behaviour) biases B toward whatever ordering the source
            // produced, which defeats the point of an A/B test.
            $abOn    = (bool) $campaign->ab_testing;
            $abSplit = max(0, min(100, (int) ($campaign->ab_split ?? 50)));
            $variantMap = [];
            if ($abOn) {
                $shuffled = $phoneNumbers;
                shuffle($shuffled);
                $countA = (int) round(count($shuffled) * $abSplit / 100);
                foreach ($shuffled as $i => $phone) {
                    $variantMap[$phone] = $i < $countA ? 'A' : 'B';
                }
            }

            // Resolve each recipient phone to a saved Contact id (find-or-create
            // in this workspace). The shared dispatcher loads recipients by
            // contact_id (Contact::whereIn('id', …)) and reads their mobile, so
            // phone-only rows would send to nobody. Mirrors the app queue path,
            // which already auto-creates contacts.
            $contactMap = $this->resolvePhonesToContactIds($phoneNumbers, (int) $user->current_workspace_id, (int) $user->id);

            // CRITICAL parity fix — the web dispatcher reads recipients via
            // WpCampaignContact->contact_id (Contact::whereIn('id', $ids)).
            // If ANY phone didn't resolve to a Contact, that row would land
            // with contact_id=NULL and the dispatcher would silently skip it
            // (or, if every row was NULL, run with zero recipients and the
            // app dev would see "Campaign is being sent" but nobody got a
            // message). Surface which phones missed so the caller can fix.
            $unresolvedPhones = [];
            foreach ($phoneNumbers as $phone) {
                if (empty($contactMap[$phone])) {
                    $unresolvedPhones[] = $phone;
                }
            }
            if (! empty($unresolvedPhones)) {
                DB::rollBack();
                Log::error('[App\Campaign] could not resolve every phone to a Contact', [
                    'campaign_name'      => $request->campaign_name,
                    'workspace_id'       => $user->current_workspace_id,
                    'total_phones'       => count($phoneNumbers),
                    'unresolved_count'   => count($unresolvedPhones),
                    'unresolved_sample'  => array_slice($unresolvedPhones, 0, 5),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Could not register every recipient as a workspace contact — campaign not created.',
                    'errors'  => [
                        'contacts' => [count($unresolvedPhones) . ' phone(s) could not be saved as a contact. The web /wa-campaigns form pre-creates contacts; the API tried to auto-create them and failed.'],
                    ],
                    'unresolved_phones' => array_slice($unresolvedPhones, 0, 10),
                ], 422);
            }

            foreach ($phoneNumbers as $phone) {
                $variant = $abOn ? ($variantMap[$phone] ?? 'A') : null;
                WpCampaignContact::create([
                    'campaign_id' => $campaign->id,
                    // Real contact id so the dispatcher can load + message the
                    // recipient and update this row's status by contact_id.
                    'contact_id' => $contactMap[$phone],
                    'phone_number' => $phone,
                    'variant' => $variant,
                    // 'queued' (not 'pending') to match the web /wa-campaigns
                    // rows — the shared dispatcher's status logic keys on it.
                    'status' => 'queued',
                    'tracking_id' => Str::random(32),
                ]);
            }

            DB::commit();

            // Pre-flight sanity check — surface common failure modes back to
            // the caller instead of silently swallowing them. The web /wa-
            // campaigns page lets the user see + fix these inline, but the
            // app dev had no signal that "Campaign is being sent" was
            // actually the queue refusing to start.
            $diag = $this->preflightDispatch($campaign->fresh());

            // Actually send. Hand off to the SAME paced web dispatcher the
            // /wa-campaigns page uses (flow → dispatchFlowCampaign, text/template
            // → runCampaignNowPaced), which runs after the HTTP response,
            // applies the msg_gap/batch pacing, and posts per-recipient status
            // back. 'now' fires immediately (deferred via afterResponse inside
            // dispatchCampaignNow); 'scheduled'/'recurring' are left for the
            // heartbeat campaign sweeper.
            $handoffError = null;
            if (!$isRecurring && $request->schedule_type === 'now' && $diag['ok']) {
                try {
                    Log::info('[App\Campaign] dispatch hand-off', [
                        'campaign'      => $campaign->id,
                        'type'          => $campaign->campaign_type,
                        'recipients'    => $totalContacts,
                        'device_id'     => $campaign->device_id,
                        'ab'            => (bool) $campaign->ab_testing,
                        'workspace_id'  => $campaign->workspace_id,
                    ]);
                    app(\App\Http\Controllers\WaCampaignsController::class)
                        ->fireScheduledCampaign($campaign->fresh());
                } catch (\Throwable $e) {
                    Log::error('App\CampaignController dispatch hand-off failed', [
                        'campaign' => $campaign->id,
                        'error'    => $e->getMessage(),
                    ]);
                    $handoffError = $e->getMessage();
                }
            }

            $sendingMessage = $isRecurring
                ? 'Recurring campaign started successfully'
                : ($request->schedule_type === 'now' ? 'Campaign is being sent' : 'Campaign scheduled successfully');

            // If the pre-flight failed, we still keep the row but tell the
            // caller WHY nothing went out so they can fix the device / plan
            // / recipients and retry instead of guessing.
            if (! $diag['ok']) {
                $sendingMessage = 'Campaign saved as draft — could not start sending: ' . $diag['error'];
            } elseif ($handoffError) {
                $sendingMessage = 'Campaign created but dispatch failed: ' . mb_substr($handoffError, 0, 191);
            }

            // Verify the per-recipient log rows landed AND every row has a
            // non-null contact_id — this is what the dispatcher iterates.
            // Exposing this in the response lets the app dev tell the
            // difference between "campaign saved but dispatcher saw 0
            // recipients" (no actual send) and "all good".
            $logRowCount   = WpCampaignContact::where('campaign_id', $campaign->id)->count();
            $linkedRowCount= WpCampaignContact::where('campaign_id', $campaign->id)->whereNotNull('contact_id')->count();

            return response()->json([
                'success'    => true,
                'message'    => $sendingMessage,
                'data'       => $this->transformCampaign($campaign->fresh()),
                // Diagnostic block — populated for every create, not just
                // failures, so the app dev can verify the right device /
                // recipients / engine were chosen before debugging Node.
                'diagnostics'=> [
                    'preflight'             => $diag,
                    'handoff_error'         => $handoffError,
                    'will_dispatch'         => !$isRecurring && $request->schedule_type === 'now' && $diag['ok'],
                    'will_schedule'         => $request->schedule_type !== 'now' && $diag['ok'],
                    // Recipient-row visibility — the dispatcher iterates
                    // wp_campaign_contacts and skips NULL contact_id rows.
                    // If `dispatcher_recipients` is 0 the campaign won't
                    // actually send no matter what `message` says.
                    'recipient_log_rows'    => $logRowCount,
                    'dispatcher_recipients' => $linkedRowCount,
                    // Engine the dispatcher will actually use. Catches the
                    // single most common silent failure: app dev picks a
                    // Baileys device but the campaign row's provider was
                    // NULL, so the dispatcher fell back to the workspace
                    // default engine (e.g. Twilio) and messages shipped
                    // through a different transport than the user expects.
                    'resolved_device_id'    => $campaign->device_id,
                    'resolved_engine'       => $campaign->provider ?: WorkspaceEngine::for($wsId),
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('App\CampaignController@store failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /campaigns/{id}/stop — stop a running/scheduled campaign.
     *
     * STUBBED DISPATCH: marks the campaign 'stopped' + stamps completed_at.
     * The old controller also DELETE'd the Node schedule; that Node-cancel
     * handoff is left to the workspace's dispatcher and is NOT called here
     * (see report). The model's `status` change still fires the
     * campaign_status_updated webhook via WpCampaign::booted().
     */
    public function stop(Request $request, $id): JsonResponse
    {
        try {
            $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

            if (!in_array($campaign->status, ['processing', 'scheduled', 'active'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only processing, scheduled, or active campaigns can be stopped.',
                ], 400);
            }

            $campaign->status = 'stopped';
            $campaign->completed_at = now();
            $campaign->save();

            return response()->json([
                'success' => true,
                'message' => 'Campaign stopped successfully.',
                'data' => $this->transformCampaign($campaign),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
        } catch (\Throwable $e) {
            Log::error('App\CampaignController@stop failed', ['campaign_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to stop campaign',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /campaigns/{id} — delete a single campaign.
     * Mirrors the old WhatsAppCampaignApiController@destroy: only
     * scheduled/failed/completed campaigns may be deleted; the campaign's
     * contact rows are removed first. Workspace-scoped.
     * Shape: { success, message }
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $campaign = WpCampaign::query()->forCurrentWorkspace()->find($id);

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found',
                ], 404);
            }

            if (!in_array($campaign->status, ['scheduled', 'failed', 'completed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only scheduled, failed, or completed campaigns can be deleted',
                ], 400);
            }

            // NOTE: Node-schedule cancel is owned by the workspace dispatcher.
            $campaign->contacts()->delete();
            $campaign->delete();

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\CampaignController@destroy failed', ['campaign_id' => $id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /campaigns/bulk — delete many campaigns by id.
     * Shape: { success, message, deleted_count, skipped }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $campaigns = WpCampaign::query()->forCurrentWorkspace()
                ->whereIn('id', $request->ids)
                ->get();

            if ($campaigns->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campaigns found for the provided IDs.',
                ], 404);
            }

            $deleted = 0;
            $skipped = [];

            foreach ($campaigns as $campaign) {
                // NOTE (stubbed dispatch): Node-schedule cancel skipped — owned
                // by the workspace dispatcher.
                $campaign->contacts()->delete();
                $campaign->delete();
                $deleted++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk delete processed.',
                'deleted_count' => $deleted,
                'skipped' => $skipped,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\CampaignController@bulkDestroy failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaigns',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /campaigns/statistics — workspace-wide rollup.
     * Shape: { success, message, data: { campaigns, messages, rates } }
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $campaigns = WpCampaign::query()->forCurrentWorkspace()->get();

            $total = $campaigns->count();
            $active = $campaigns->where('status', 'processing')->count();
            $completed = $campaigns->where('status', 'completed')->count();
            $scheduled = $campaigns->where('status', 'scheduled')->count();
            $failed = $campaigns->where('status', 'failed')->count();

            $totalRecipients = (int) $campaigns->sum('total_recipients');
            $totalSent = (int) $campaigns->sum('sent_count');
            $totalDelivered = (int) $campaigns->sum('delivered_count');
            $totalRead = (int) $campaigns->sum('read_count');
            $totalFailed = (int) $campaigns->sum('failed_count');
            $totalClicked = (int) $campaigns->sum('clicked_count');
            $totalResponded = (int) $campaigns->sum('responded_count');

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'campaigns' => [
                        'total' => $total,
                        'active' => $active,
                        'completed' => $completed,
                        'scheduled' => $scheduled,
                        'failed' => $failed,
                    ],
                    'messages' => [
                        'total_recipients' => $totalRecipients,
                        'sent' => $totalSent,
                        'delivered' => $totalDelivered,
                        'read' => $totalRead,
                        'failed' => $totalFailed,
                        'clicked' => $totalClicked,
                        'responded' => $totalResponded,
                    ],
                    'rates' => [
                        'delivery_rate' => $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 2) : 0,
                        'read_rate' => $totalDelivered > 0 ? round(($totalRead / $totalDelivered) * 100, 2) : 0,
                        'click_rate' => $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 2) : 0,
                        'response_rate' => $totalSent > 0 ? round(($totalResponded / $totalSent) * 100, 2) : 0,
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('App\CampaignController@statistics failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /**
     * Pre-flight check before handing a "now" campaign off to the web
     * dispatcher. Returns ['ok' => bool, 'error' => string|null].
     * Catches the failure modes the dispatcher would otherwise swallow:
     *   - no device on the campaign (resolveProvider would crash)
     *   - device exists but isn't connected (silent 404 inside Node)
     *   - template campaign with no template_id resolved
     *   - flow campaign with no flow_id_b when A/B is on
     *   - zero recipients reached the WpCampaignContact table
     * The app sees a clean message like "Campaign saved as draft — could
     * not start sending: device not connected" instead of guessing why
     * "Campaign is being sent" produced no actual WhatsApp messages.
     */
    private function preflightDispatch(WpCampaign $campaign): array
    {
        // 1. Recipients actually loaded?
        $recipients = $campaign->contacts()->count();
        if ($recipients === 0) {
            return ['ok' => false, 'error' => 'No recipients on the campaign — provide contacts[] or groups[] when creating.'];
        }

        // 2. Device resolved?
        if (! $campaign->device_id) {
            return ['ok' => false, 'error' => 'No sender device on the campaign. Pass `device_id` (Baileys row id) or `sender` (composite engine:id) on create.'];
        }
        // Baileys-only device-row check (WABA/Twilio configs are in a
        // different table — provider !== baileys means we can't verify
        // here, so we skip the row check and let the dispatcher report).
        if (($campaign->provider ?? 'baileys') === 'baileys') {
            $device = Device::query()->find($campaign->device_id);
            if (! $device) {
                return ['ok' => false, 'error' => 'Sender device row not found (device_id ' . $campaign->device_id . ').'];
            }
            if ($device->status !== 'connected' && ! $device->active) {
                return ['ok' => false, 'error' => 'Sender device is not connected — pair it at /devices/' . $device->id . '/qr first.'];
            }
        }

        // 3. Per-type sanity.
        $type   = (string) $campaign->campaign_type;
        $abOn   = (bool) $campaign->ab_testing;
        if ($type === 'template') {
            if ($abOn) {
                if (! $campaign->template_id_a || ! $campaign->template_id_b) {
                    return ['ok' => false, 'error' => 'A/B template campaigns need BOTH template_id_a and template_id_b.'];
                }
            } else {
                if (! $campaign->template_id) {
                    return ['ok' => false, 'error' => 'Template campaign needs template_id.'];
                }
            }
        } elseif ($type === 'flow') {
            if (! $campaign->flow_id) {
                return ['ok' => false, 'error' => 'Flow campaign needs flow_id.'];
            }
            if ($abOn && ! $campaign->flow_id_b) {
                return ['ok' => false, 'error' => 'A/B flow campaigns need BOTH flow_id and flow_id_b.'];
            }
        } else { // custom / text / button / media
            if ($abOn && empty($campaign->custom_message_b)) {
                return ['ok' => false, 'error' => 'A/B custom campaigns need both custom_message and custom_message_b.'];
            }
            if (empty($campaign->custom_message)) {
                return ['ok' => false, 'error' => $type . ' campaign needs custom_message.'];
            }
        }

        return ['ok' => true, 'error' => null];
    }

    private function transformCampaign(WpCampaign $campaign): array
    {
        // Multi-engine: the badge must reflect the engine THIS campaign was
        // saved to send on (its stamped provider), not the workspace default.
        $channel = WorkspaceEngine::descriptor($campaign->provider ?: WorkspaceEngine::for((int) $campaign->workspace_id));
        $device = $this->findDevice($campaign->device_id);
        $template = $campaign->template_id ? $this->findTemplate($campaign->template_id) : null;
        $templateA = $campaign->template_id_a ? $this->findTemplate($campaign->template_id_a) : null;
        $templateB = $campaign->template_id_b ? $this->findTemplate($campaign->template_id_b) : null;
        $flow = $campaign->flow_id ? $this->findFlow($campaign->flow_id) : null;

        $scheduledFor = null;
        if ($campaign->send_date) {
            try {
                $scheduledFor = $campaign->dueAtUtc()?->toIso8601String();
            } catch (\Throwable $e) {
                $scheduledFor = null;
            }
        }

        $sent = (int) $campaign->sent_count;
        $delivered = (int) $campaign->delivered_count;

        return [
            'id' => $campaign->id,
            'campaign_name' => $campaign->campaign_name,
            'campaign_type' => $campaign->campaign_type,
            'status' => $campaign->status,
            'device_id' => $campaign->device_id,
            'device_name' => $device?->device_name,
            'device_phone' => $device?->phone_number,

            'ab_testing' => (bool) ($campaign->ab_testing ?? false),
            'ab_split' => $campaign->ab_split,

            'template_id' => $campaign->template_id,
            'template_name' => $template?->template_name,
            'template_id_a' => $campaign->template_id_a,
            'template_name_a' => $templateA?->template_name,
            'template_id_b' => $campaign->template_id_b,
            'template_name_b' => $templateB?->template_name,
            'flow_id' => $campaign->flow_id,
            'flow_name' => $flow?->flow_name,
            'flow_id_b' => $campaign->flow_id_b ?? null,
            'flow_name_b' => $campaign->flow_id_b ? $this->findFlow($campaign->flow_id_b)?->flow_name : null,

            'custom_message' => $campaign->custom_message,
            'custom_message_b' => $campaign->custom_message_b ?? null,
            'custom_header' => $campaign->custom_header,
            'custom_footer' => $campaign->custom_footer,
            'custom_buttons' => $campaign->custom_buttons,
            'custom_quick_replies' => $campaign->custom_quick_replies,
            'custom_image' => $campaign->custom_image ? media_url($campaign->custom_image) : null,
            'custom_video' => $campaign->custom_video ? media_url($campaign->custom_video) : null,
            'custom_document' => $campaign->custom_document ? media_url($campaign->custom_document) : null,

            'schedule_type' => $campaign->schedule_type,
            'send_date' => $campaign->send_date instanceof \Carbon\CarbonInterface ? $campaign->send_date->toDateString() : $campaign->send_date,
            'send_time' => $campaign->send_time,
            'timezone' => $campaign->timezone,
            'scheduled_for' => $scheduledFor,

            'stats' => [
                'total_recipients' => (int) $campaign->total_recipients,
                'sent' => $sent,
                'delivered' => $delivered,
                'read' => (int) $campaign->read_count,
                'failed' => (int) $campaign->failed_count,
                'clicked' => (int) $campaign->clicked_count,
                'responded' => (int) $campaign->responded_count,
            ],

            'metrics' => [
                'delivery_rate' => $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0,
                'read_rate' => $delivered > 0 ? round(((int) $campaign->read_count / $delivered) * 100, 2) : 0,
                'click_rate' => $sent > 0 ? round(((int) $campaign->clicked_count / $sent) * 100, 2) : 0,
                'response_rate' => $sent > 0 ? round(((int) $campaign->responded_count / $sent) * 100, 2) : 0,
            ],

            'use_attributes' => (bool) ($campaign->use_attributes ?? false),
            'tracking_enabled' => (bool) ($campaign->tracking_enabled ?? true),

            'created_at' => optional($campaign->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($campaign->updated_at)->format('Y-m-d H:i:s'),
            'completed_at' => $campaign->completed_at ? $campaign->completed_at->format('Y-m-d H:i:s') : null,

            'created_by' => $campaign->created_by,
            'creator_name' => $campaign->creator?->name,

            // Channel sign (Meta / Unofficial API / Twilio) so the app can
            // badge the campaign. A workspace sends through exactly one engine,
            // so the campaign's channel is its workspace engine.
            'channel'       => $channel['channel'],   // meta | unofficial | twilio
            'channel_label' => $channel['label'],     // Meta | Unofficial API | Twilio
            'channel_code'  => $channel['code'],      // W | U | T
        ];
    }

    private function transformContact(WpCampaignContact $cc): array
    {
        return [
            'id' => $cc->contact_id,
            'name' => $cc->recipient_name ?? 'Unknown',
            'first_name' => null,
            'last_name' => null,
            'phone' => $cc->phone_number ?? $cc->contact_id,
            'email' => null,
            'variant' => $cc->variant,
            'status' => $cc->status,
            'sent' => !in_array($cc->status, ['pending', 'failed']),
            'sent_at' => $cc->sent_at,
            'delivered' => in_array($cc->status, ['delivered', 'read']),
            'delivered_at' => $cc->delivered_at,
            'read' => $cc->status === 'read',
            'read_at' => $cc->read_at,
            'clicked' => (bool) ($cc->clicked ?? false),
            'clicked_at' => $cc->clicked_at,
            'click_count' => (int) ($cc->click_count ?? 0),
            'responded' => !is_null($cc->response),
            'responded_at' => $cc->responded_at,
            'response' => $cc->response,
            'failed' => $cc->status === 'failed',
            'error_message' => $cc->error_message,
            'whatsapp_message_id' => $cc->whatsapp_message_id,
            'updated_at' => $cc->updated_at,
        ];
    }

    private function transformTemplate(WaTemplate $template): array
    {
        return [
            'id' => $template->id,
            'template_name' => $template->template_name,
            'header' => $template->header ?? null,
            'template_body' => $template->template_body,
            'footer' => $template->footer ?? null,
            'attachment_type' => $template->attachment_type ?? null,
            'attachment_file' => !empty($template->attachment_file) ? media_url($template->attachment_file) : null,
            'buttons' => is_string($template->buttons ?? null) ? json_decode($template->buttons, true) : ($template->buttons ?? null),
            'carousel_data' => is_string($template->carousel_data ?? null) ? json_decode($template->carousel_data, true) : ($template->carousel_data ?? null),
            'created_at' => $template->created_at,
        ];
    }

    private function trendBlock(float $value, int $count): array
    {
        return [
            'value' => $value,
            'percentage' => $value . '%',
            'count' => $count,
            'trend' => 'neutral',
            'diff' => 0,
            'color' => '#10b981',
            'is_up' => false,
        ];
    }

    private function buildAbStats(WpCampaign $campaign, $contacts, int $sentCount, int $deliveredCount, array $currentMetrics): array
    {
        $variantACount = $contacts->filter(fn ($c) => $c->variant === 'A' && in_array($c->status, ['delivered', 'read']))->count();
        $variantBCount = $contacts->filter(fn ($c) => $c->variant === 'B' && in_array($c->status, ['delivered', 'read']))->count();
        $failedCountAB = $contacts->filter(fn ($c) => $c->status === 'failed')->count();
        $baseForPie = max(1, $variantACount + $variantBCount + $failedCountAB);

        return [
            'is_running' => in_array($campaign->status, ['processing', 'active', 'scheduled']),
            'distribution_set' => [
                'A' => $campaign->ab_split ?? 50,
                'B' => 100 - ($campaign->ab_split ?? 50),
            ],
            'performance' => [
                'delivered_total' => $deliveredCount,
                'delivered_percentage' => round($currentMetrics['delivery_rate'], 1),
                'breakdown' => [
                    'A' => ['count' => $variantACount, 'percentage' => round(($variantACount / $baseForPie) * 100, 1), 'color' => '#A855F7'],
                    'B' => ['count' => $variantBCount, 'percentage' => round(($variantBCount / $baseForPie) * 100, 1), 'color' => '#EAB308'],
                    'Failed' => ['count' => $failedCountAB, 'percentage' => round(($failedCountAB / $baseForPie) * 100, 1), 'color' => '#EF4444'],
                ],
            ],
            'winner' => $this->determineAbWinner($contacts),
        ];
    }

    private function determineAbWinner($contacts): ?array
    {
        $variantA = $contacts->where('variant', 'A');
        $variantB = $contacts->where('variant', 'B');

        $sentA = $variantA->whereNotIn('status', ['pending', 'failed'])->count();
        $sentB = $variantB->whereNotIn('status', ['pending', 'failed'])->count();
        if ($sentA === 0 || $sentB === 0) {
            return null;
        }

        $deliveredA = $variantA->whereIn('status', ['delivered', 'read'])->count();
        $deliveredB = $variantB->whereIn('status', ['delivered', 'read'])->count();
        $readA = $variantA->where('status', 'read')->count();
        $readB = $variantB->where('status', 'read')->count();
        $clickedA = $variantA->where('clicked', true)->count();
        $clickedB = $variantB->where('clicked', true)->count();
        $respondedA = $variantA->whereNotNull('response')->count();
        $respondedB = $variantB->whereNotNull('response')->count();

        $scoreA = ($deliveredA / $sentA) * 0.2 + ($readA / $sentA) * 0.3 + ($clickedA / $sentA) * 0.3 + ($respondedA / $sentA) * 0.2;
        $scoreB = ($deliveredB / $sentB) * 0.2 + ($readB / $sentB) * 0.3 + ($clickedB / $sentB) * 0.3 + ($respondedB / $sentB) * 0.2;

        $winner = 'tie';
        $improvement = 0;
        if ($scoreA > $scoreB) {
            $winner = 'A';
            $improvement = $scoreB > 0 ? round((($scoreA - $scoreB) / $scoreB) * 100, 2) : 0;
        } elseif ($scoreB > $scoreA) {
            $winner = 'B';
            $improvement = $scoreA > 0 ? round((($scoreB - $scoreA) / $scoreA) * 100, 2) : 0;
        }

        return [
            'winner' => $winner,
            'score_a' => round($scoreA * 100, 2),
            'score_b' => round($scoreB * 100, 2),
            'improvement' => $improvement,
            'stats_a' => $this->variantStats($sentA, $deliveredA, $readA, $clickedA, $respondedA),
            'stats_b' => $this->variantStats($sentB, $deliveredB, $readB, $clickedB, $respondedB),
        ];
    }

    private function variantStats(int $sent, int $delivered, int $read, int $clicked, int $responded): array
    {
        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'read' => $read,
            'clicked' => $clicked,
            'responded' => $responded,
            'delivery_rate' => round(($delivered / $sent) * 100, 2),
            'read_rate' => round(($read / $sent) * 100, 2),
            'click_rate' => round(($clicked / $sent) * 100, 2),
            'response_rate' => round(($responded / $sent) * 100, 2),
        ];
    }

    /**
     * Resolve a [start, end] window from ?range / ?from_date / ?to_date,
     * mirroring the old show() defaults (recurring with no filter → today).
     */
    private function resolveRange(Request $request, WpCampaign $campaign): array
    {
        $start = null;
        $end = null;

        if ($request->has('range')) {
            switch ($request->range) {
                case 'today': $start = Carbon::today(); $end = Carbon::now(); break;
                case 'yesterday': $start = Carbon::yesterday(); $end = Carbon::yesterday()->endOfDay(); break;
                case 'last_7_days': $start = Carbon::now()->subDays(7); $end = Carbon::now(); break;
                case 'last_30_days': $start = Carbon::now()->subDays(30); $end = Carbon::now(); break;
                case 'this_month': $start = Carbon::now()->startOfMonth(); $end = Carbon::now(); break;
                case 'last_month': $start = Carbon::now()->subMonth()->startOfMonth(); $end = Carbon::now()->subMonth()->endOfMonth(); break;
            }
        } elseif ($request->has('from_date') || $request->has('to_date')) {
            if ($request->has('from_date')) {
                $start = Carbon::parse($request->from_date);
            }
            if ($request->has('to_date')) {
                $end = Carbon::parse($request->to_date)->endOfDay();
            }
        }

        if ($campaign->schedule_type === 'recurring' && !$start && !$end && !$request->has('range') && !$request->has('from_date')) {
            $start = Carbon::today();
            $end = Carbon::today()->endOfDay();
        }

        return [$start, $end];
    }

    /**
     * Map each recipient phone to a saved Contact id, find-or-creating a
     * lightweight contact for numbers that aren't saved yet. Returns
     * [phoneAsPassed => contactId]. The shared dispatcher loads recipients by
     * contact_id and reads their mobile, so phone-only rows would send to
     * nobody — this mirrors the app queue path's resolveRecipientContactIds()
     * (events suppressed so a bulk campaign never fires contact_created flows).
     */
    private function resolvePhonesToContactIds(array $phones, int $wsId, ?int $ownerId): array
    {
        $canon = fn ($v) => preg_replace('/\D+/', '', (string) $v);

        // Index existing workspace contacts by canonical phone (full + bare).
        // mobile is encrypted at rest, so compare in PHP.
        $existing = [];
        Contact::where('workspace_id', $wsId)->get(['id', 'country_code', 'mobile'])
            ->each(function (Contact $c) use (&$existing, $canon) {
                $full = $canon(($c->country_code ?? '') . $c->mobile);
                $bare = $canon($c->mobile);
                if ($full !== '' && !isset($existing[$full])) $existing[$full] = $c->id;
                if ($bare !== '' && !isset($existing[$bare])) $existing[$bare] = $c->id;
            });

        $map = [];
        foreach ($phones as $phone) {
            $key = $canon($phone);
            if ($key === '') continue;
            if (isset($existing[$key])) { $map[$phone] = $existing[$key]; continue; }

            $newId = null;
            Contact::withoutEvents(function () use ($key, $wsId, $ownerId, &$newId) {
                $c = Contact::create([
                    'user_id'      => $ownerId,
                    'workspace_id' => $wsId,
                    'name'         => $key,
                    'mobile'       => $key,
                    'msg'          => 'Added from app campaign.',
                ]);
                $newId = $c->id;
            });
            // Cache so any later duplicate of the same number reuses this row.
            $existing[$key] = $newId;
            $map[$phone] = $newId;
        }

        return $map;
    }

    private function findDevice($deviceId): ?Device
    {
        if (empty($deviceId)) {
            return null;
        }
        try {
            // Accept any of:
            //   - the row id (small integer like 53) — the canonical form
            //   - the bare phone_number ("9783969401") — what's stored on the row
            //   - the full international phone ("919783969401", "+91 9783 969401")
            //     because app devs often pass what's in their UI directly.
            // phone_number + country_code are encrypted at rest, so the
            // comparison runs in PHP across the workspace's device rows.
            $wsId = (int) (auth()->user()->current_workspace_id ?? 0);
            $needle    = (string) $deviceId;
            $needleDig = preg_replace('/\D+/', '', $needle);   // digits-only form for phone match
            $devices   = Device::where('workspace_id', $wsId)->get();
            return $devices->first(function ($d) use ($needle, $needleDig) {
                if ((string) $d->id === $needle) return true;          // row id
                $bare = preg_replace('/\D+/', '', (string) $d->phone_number);
                $full = preg_replace('/\D+/', '', (string) $d->country_code . (string) $d->phone_number);
                if ($needleDig === '') return false;
                return $bare === $needleDig || $full === $needleDig;
            });
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function findTemplate($id): ?WaTemplate
    {
        try {
            return WaTemplate::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function findFlow($id): ?Flow
    {
        try {
            return Flow::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
