<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Renders the read-only prototype views for the platform admin console.
 */
class AdminPagesController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard.index');
    }

    // Users & access
    public function users(): View
    {
        return view('admin.users.index');
    }
    public function userCreate(): View
    {
        return view('admin.users.create');
    }
    public function userEdit(string $id): View
    {
        return view('admin.users.edit');
    }
    public function userImport(): View
    {
        return view('admin.users.import');
    }

    /** Stream the blank bulk-import CSV template (header row only). */
    public function userImportTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $columns = ['name', 'email', 'mobile', 'role', 'workspace', 'gender', 'address', 'status'];
        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fputcsv($out, ['Jane Doe', 'jane@example.com', '+15551234567', 'user', 'Acme', 'female', '12 Main St', 'active']);
            fclose($out);
        }, 'users-import-template.csv', ['Content-Type' => 'text/csv']);
    }
    public function userTrash(): View
    {
        return view('admin.users.trash');
    }

    // Roles + permissions are handled by RoleController + PermissionController.

    // Workspaces & devices
    public function workspaces(): View
    {
        return view('admin.workspaces.index');
    }
    public function workspaceCreate(): View
    {
        return view('admin.workspaces.create');
    }
    public function workspaceDetail(string $id): View
    {
        $ws = \App\Models\Workspace::findOrFail($id);
        $package = $ws->plan ? \App\Models\Package::find($ws->plan) : null;
        return view('admin.workspaces.detail', [
            'workspace'      => $ws,
            'package'        => $package,
            'limitColumns'   => self::planLimitColumns(),
        ]);
    }

    /**
     * Per-workspace plan limit overrides (#31-36). Stored as JSON on
     * workspaces.plan_overrides. Reading goes through Workspace::effectiveLimit().
     */
    public function workspaceSaveOverrides(\Illuminate\Http\Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $ws = \App\Models\Workspace::findOrFail($id);

        $rules = [];
        foreach (self::planLimitColumns() as $key) {
            $rules['overrides.'.$key] = 'nullable|integer|min:-1|max:10000000';   // -1 = unlimited convention
        }
        $data = $request->validate($rules);
        $overrides = collect($data['overrides'] ?? [])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->all();

        $ws->update(['plan_overrides' => $overrides ?: null]);
        return back()->with('success', 'Plan overrides saved.');
    }

    /**
     * The full set of integer "limit" columns on packages. Driving the
     * UI off this list keeps the override form in sync with the schema
     * — when a new limit column is added, just append here.
     */
    public const PLAN_LIMIT_COLUMNS = [
        'device_limit',
        'monthly_messages_limit',
        'contacts_limit',
        'broadcast_limit',
        'template_limit',
        'groups_limit',
        'campaign_messages_limit',
        'automation_messages_limit',
        'broadcast_size_limit',
        'total_campaigns_limit',
        'active_campaign_limit',
        'user_seat_limit',
        'tags_limit',
        'flow_limit',
        'flow_steps_limit',
        'autoreply_limit',
        'chatbot_limit',
        'scheduled_campaign_limit',
        'daily_media_size_allowance',
        // Added in 2026_05_17 extension — every user-facing feature gets a limit.
        'workspaces_per_owner_limit',
        'routing_rules_limit',
        'drip_campaigns_limit',
        'appointments_limit',
        'ai_agents_limit',
        'saved_replies_limit',
        'webhooks_limit',
        // AI token cap when the workspace falls back to admin keys.
        'ai_token_limit_monthly',
        // Sprint 9.5 — caps for WABA calling, AI voice/chat, training, widgets,
        // storefronts, SLA policies, translation.
        'waba_calling_minutes_monthly',
        'ai_voice_minutes_monthly',
        'ai_chat_messages_monthly',
        'ai_training_sources_limit',
        'chatbot_widgets_limit',
        'storefronts_limit',
        'sla_policies_limit',
        'translation_chars_monthly',
        // Customer REST API (/api/v1) requests-per-minute for this plan.
        // 0 = inherit global default (security.api_rate_limit_per_minute).
        'api_rate_limit_per_minute',
    ];

    /**
     * Boolean feature toggles a plan can enable / disable. Empty
     * value = enabled (so existing rows aren't surprise-disabled).
     * Used by Package::PLAN_FEATURE_TOGGLES on the create / edit form
     * and the PlanLimitGuard::featureEnabled() check.
     */
    public const PLAN_FEATURE_TOGGLES = [
        // Existing top-level features.
        'autoreply', 'bulkmessage', 'schedulemessage', 'ads', 'campaign',
        'autoflow', 'broadcast', 'chatgpt_suggestion', 'template',
        'access_carousel_templates', 'role_based_permissions',
        'access_drip_campaigns', 'access_ctwa', 'access_analytics', 'remove_branding',
        'access_instagram',
        // Integrations.
        'integration_shopify', 'integration_woocommerce', 'integration_hubspot',
        'integration_google_calendar', 'integration_google_sheets',
        'integration_slack', 'integration_trello',
        // Granular feature gates added in 2026_05_17.
        'access_kanban_view', 'access_appointment_booking', 'access_edit_messages',
        'access_internal_notes', 'access_message_reactions', 'access_routing_rules',
        'access_business_hours', 'access_team_performance', 'access_outbound_webhooks',
        'access_keyword_replies', 'access_ai_agents',
        // Plan-level "Bring your own AI keys" — when ON, the user's
        // own keys in /settings?tab=aikeys take precedence over the
        // admin's global keys. Off by default so cheap plans don't
        // accidentally bypass the admin's billing.
        'allow_byok_ai_keys',
        // Multi-device sending — when ON, chat queues can fan out
        // across two or more paired devices, splitting the recipient
        // list round-robin so heavy sends don't bottleneck on a
        // single number. Single-select stays the default.
        'multipledevice',
        // File-type restrictions on uploads (was in fillable but missing from gate list).
        'file_type_restrictions',
        // Sprint 9.5 — Cloud-API voice calling + AI voice/chat/training.
        'access_waba_calling',
        'access_call_recording',
        'access_ai_voice_agent',
        'access_ai_chat_assistant',
        'access_ai_training',
        'access_ai_generate',
        // Sprint 9.5 — Storefront / commerce flows / chatbot widgets.
        'access_wa_storefront',
        'access_whatsapp_pay',
        'access_flows_commerce',
        'access_chatbot_widgets',
        // Sprint 9.5 — SLA / translation / data residency.
        'access_sla_policies',
        'access_translation',
        'access_data_residency',
        // Were declared on the Package model (fillable + cast) but missing from
        // this save/gate list, so their toggles never rendered/persisted on the
        // package create/edit form and the plan gate never recognised them.
        'access_proxy_isolation',   // Per-number proxy / dedicated IP
        'access_sales_pipeline',    // Sales pipeline (Deal CRM)
    ];

    /**
     * Core feature flags PLUS any contributed by installed extensions.
     *
     * Everything downstream — validation rules, the boolean cast, the
     * "default to false when the form omits it" pass, and the checkbox list —
     * reads through here rather than the bare constant. That is what makes an
     * extension's flags actually settable: previously a key could exist in the
     * constant but have no checkbox, and the save path would then force it to
     * false on every submit.
     *
     * @return array<int, string>
     */
    public static function planFeatureToggles(): array
    {
        return array_values(array_unique(array_merge(
            self::PLAN_FEATURE_TOGGLES,
            array_keys(\App\Services\ExtensionRegistry::planFeatures())
        )));
    }

    /**
     * Core limit columns PLUS any contributed by installed extensions.
     *
     * @return array<int, string>
     */
    public static function planLimitColumns(): array
    {
        return array_values(array_unique(array_merge(
            self::PLAN_LIMIT_COLUMNS,
            array_keys(\App\Services\ExtensionRegistry::planLimits())
        )));
    }


    /**
     * Platform-wide device list. Spans EVERY workspace (this is the admin
     * console, not the operator's own /devices page), so it deliberately does
     * NOT use Device::forCurrentWorkspace().
     *
     * `device_name` / `country_code` / `phone_number` are ENCRYPTED casts, so
     * the search term cannot be matched in SQL — status filtering happens in
     * the query, the text search + pagination happen in PHP on the decrypted
     * collection (same approach ContactsController uses).
     */
    public function devices(\Illuminate\Http\Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $q      = trim((string) $request->query('q', ''));

        $base = \App\Models\Device::query()->with(['user', 'workspace']);

        // KPI counts — computed before the status filter so the cards always
        // describe the whole platform, not the current view.
        $counts = [
            'all'          => (clone $base)->count(),
            'connected'    => (clone $base)->where('status', 'connected')->count(),
            'disconnected' => (clone $base)->where('status', 'disconnected')->count(),
            'needs_pair'   => (clone $base)->where('status', 'needs_pair')->count(),
            'failed'       => (clone $base)->where('status', 'failed')->count(),
        ];
        $workspacesWithDevices = (clone $base)->distinct()->count('workspace_id');
        $sent24hTotal          = (int) (clone $base)->sum('sent_24h');
        $newThisWeek           = (clone $base)->where('created_at', '>=', now()->subDays(7))->count();

        if (in_array($status, \App\Models\Device::STATUSES, true)) {
            $base->where('status', $status);
        }

        $rows = $base->orderByDesc('id')->get();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function ($d) use ($needle) {
                $phone = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
                foreach ([$d->device_name, $phone, optional($d->user)->name, optional($d->user)->email, optional($d->workspace)->name] as $hay) {
                    if ($hay !== null && $hay !== '' && mb_stripos((string) $hay, $needle) !== false) return true;
                }
                return false;
            })->values();
        }

        $perPage = 20;
        $page    = max(1, (int) $request->query('page', 1));
        $devices = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.devices.index', compact(
            'devices', 'counts', 'status', 'q', 'workspacesWithDevices', 'sent24hTotal', 'newThisWeek'
        ));
    }

    public function deviceDetail(string $id, \Illuminate\Http\Request $request): View
    {
        $device = \App\Models\Device::with(['user', 'workspace'])->findOrFail($id);

        // Real event log — audit_logs rows recorded against this device
        // (admin disconnect/remove and anything else stamped subject_type=device).
        // There is no separate device-events table, so this is the only truthful
        // source; an empty list is shown rather than invented activity. Paginated
        // (was a flat limit(20)) so a busy device's full history is reachable.
        $events = \App\Models\AuditLog::query()
            ->where('subject_type', 'device')
            ->where('subject_id', $device->id)
            ->with('actor')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // The header time-range picker drives the volume chart + status donut.
        $range = in_array($request->query('range'), ['24h', '7d', '30d'], true)
            ? (string) $request->query('range') : '7d';

        // Real per-device analytics. Mirrors the user-side device detail
        // (DevicesController::deviceDetail): messages tie to a device through
        // conversations.device_id — the same plain, indexed link the user page
        // uses — NOT workspace_id or provider. from_number is encrypted, so this
        // relation is the only truthful per-device attribution.
        $analytics = $this->deviceAnalytics($device, $range);

        return view('admin.devices.detail', compact('device', 'events', 'analytics', 'range'));
    }

    /**
     * Build the three device-detail charts from real message rows:
     *  - volume: 7 days of daily sent vs failed
     *  - status: read / delivered / pending / failed breakdown
     *  - heatmap: 7 days x 24 hours of send activity
     * Returns zeros (an honest empty chart) when there are no messages.
     */
    private function deviceAnalytics(\App\Models\Device $device, string $range = '7d'): array
    {
        [$buckets, $unit] = match ($range) {
            '24h'   => [24, 'hour'],
            '30d'   => [30, 'day'],
            default => [7,  'day'],
        };

        $emptyHeatLabels = [];
        for ($i = 0; $i < 7; $i++) {
            $emptyHeatLabels[] = now()->copy()->startOfDay()->subDays(6 - $i)->format('D');
        }
        $empty = [
            'volume'      => ['labels' => [], 'sent' => [], 'failed' => []],
            'status'      => ['read' => 0, 'delivered' => 0, 'pending' => 0, 'failed' => 0],
            'heatmap'     => array_fill(0, 7, array_fill(0, 24, 0)),
            'heat_labels' => $emptyHeatLabels,
            'total'       => 0,
            'lifetime'    => ['read' => 0, 'failed' => 0, 'total' => 0],
        ];
        if (! \Illuminate\Support\Facades\Schema::hasTable('messages')
            || ! \Illuminate\Support\Facades\Schema::hasTable('conversations')) {
            return $empty;
        }

        // Every query below is scoped to THIS device via conversations.device_id
        // — the same link the user-side device page uses. A closure so the
        // queries can't drift apart.
        $scoped = fn () => \App\Models\Message::query()
            ->where('direction', 'out')
            ->whereHas('conversation', fn ($q) => $q->where('device_id', $device->id));

        // KPI cards read lifetime totals — independent of the range picker so
        // "Total sent · all time" stays all-time.
        $lifeByStatus = $scoped()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $lifetime = [
            'read'   => (int) ($lifeByStatus['read'] ?? 0),
            'failed' => (int) ($lifeByStatus['failed'] ?? 0),
            'total'  => (int) $lifeByStatus->sum(),
        ];

        // Window start for the selected range.
        $since = $unit === 'hour'
            ? now()->copy()->subHours($buckets - 1)->startOfHour()
            : now()->copy()->startOfDay()->subDays($buckets - 1);

        // Heatmap is always 7 days; the volume window may be shorter (24h) or
        // longer (30d). Pull from whichever start is earliest so one query feeds
        // both, then gate each bucket by its own window below.
        $heatSince = now()->copy()->startOfDay()->subDays(6);
        $rowsSince = $since->lt($heatSince) ? $since : $heatSince;
        $rows = $scoped()->where('created_at', '>=', $rowsSince)->get(['status', 'created_at']);

        // Bucket labels + a key→index map, portable across DB engines.
        $labels = [];
        $idx    = [];
        for ($i = 0; $i < $buckets; $i++) {
            if ($unit === 'hour') {
                $t = $since->copy()->addHours($i);
                $labels[] = $t->format('H:00');
                $idx[$t->format('Y-m-d H')] = $i;
            } else {
                $t = $since->copy()->addDays($i);
                $labels[] = $buckets > 7 ? $t->format('j M') : $t->format('D');
                $idx[$t->format('Y-m-d')] = $i;
            }
        }
        $sent   = array_fill(0, $buckets, 0);
        $failed = array_fill(0, $buckets, 0);
        // Heatmap is always a 7-day x 24-hour grid (its own label says so). Its
        // row labels are the 7 day names — NOT the volume window's labels, which
        // become hourly when range=24h and would spawn 24 phantom rows.
        $heatSince   = now()->copy()->startOfDay()->subDays(6);
        $heatIdx     = [];
        $heatLabels  = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $heatSince->copy()->addDays($i);
            $heatIdx[$d->format('Y-m-d')] = $i;
            $heatLabels[] = $d->format('D');
        }
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));

        $status = ['read' => 0, 'delivered' => 0, 'pending' => 0, 'failed' => 0];

        foreach ($rows as $m) {
            $ts  = $m->created_at;
            $key = $unit === 'hour' ? optional($ts)->format('Y-m-d H') : optional($ts)->format('Y-m-d');
            if ($key !== null && isset($idx[$key])) {
                $i = $idx[$key];
                $m->status === 'failed' ? $failed[$i]++ : $sent[$i]++;
            }
            // Windowed status breakdown for the donut.
            match ($m->status) {
                'read'                       => $status['read']++,
                'delivered'                  => $status['delivered']++,
                'failed'                     => $status['failed']++,
                'pending', 'scheduled', 'sent' => $status['pending']++,
                default                      => null,
            };
            // Heatmap (7d) — independent of the range window.
            $hKey = optional($ts)->format('Y-m-d');
            if ($hKey !== null && isset($heatIdx[$hKey]) && $m->status !== 'failed') {
                $heatmap[$heatIdx[$hKey]][(int) $ts->format('G')]++;
            }
        }

        return [
            'volume'      => ['labels' => $labels, 'sent' => $sent, 'failed' => $failed],
            'status'      => $status,
            'heatmap'     => $heatmap,
            'heat_labels' => $heatLabels,
            'total'       => array_sum($status),
            'lifetime'    => $lifetime,
        ];
    }

    /**
     * Force-disconnect a device from the admin console: terminate the live
     * Baileys socket on the Node bridge, then mark the row disconnected.
     * Mirrors DevicesController::killSession but is NOT workspace-scoped —
     * an admin acts across tenants.
     */
    public function deviceDisconnect(string $id): \Illuminate\Http\RedirectResponse
    {
        $d    = \App\Models\Device::findOrFail($id);
        $base = wd_node_url();

        $bridgeOk = false;
        $phone    = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
        if ($base !== '' && $phone !== '') {
            try {
                $res = \Illuminate\Support\Facades\Http::timeout(8)->acceptJson()
                    ->withHeaders(['X-Node-Token' => node_token()])
                    ->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
                $bridgeOk = $res->successful();
            } catch (\Throwable $e) {
                $bridgeOk = false;
            }
        }

        $d->active = false;
        $d->status = 'disconnected';
        $d->save();

        \App\Support\Audit::log('admin.device.disconnect', [
            'subject_type' => 'device', 'subject_id' => $d->id,
            'meta' => ['bridge' => $bridgeOk, 'workspace_id' => $d->workspace_id],
        ]);

        return back()->with('status', $bridgeOk
            ? __('Device disconnected and its session terminated.')
            : __('Device marked disconnected (the bridge did not confirm — it may already be offline).'));
    }

    /**
     * Pull a device's live connection state from the Node bridge and update the
     * row. Same endpoint DevicesController::connectionStatus polls, but admin
     * scope (any tenant). Shared by the per-row action and bulk re-sync.
     *
     * Returns true when the bridge answered, so bulk can report a real count.
     */
    private function syncDeviceFromBridge(\App\Models\Device $d): bool
    {
        $base  = wd_node_url();
        $phone = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
        if ($base === '' || $phone === '') {
            return false;
        }
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(8)->acceptJson()
                ->withHeaders(['X-Node-Token' => node_token()])
                ->get(rtrim($base, '/') . '/api/client-status/' . urlencode($phone));
            if (! $res->successful()) {
                return false;
            }
            $body   = $res->json() ?: [];
            $status = (string) ($body['status'] ?? '');
            // Only trust the statuses our own column uses; anything else leaves
            // the row untouched rather than writing a value the UI can't render.
            if (in_array($status, \App\Models\Device::STATUSES, true)) {
                $d->status = $status;
                $d->active = $status === 'connected' ? $d->active : false;
            }
            $d->last_seen_at = now();
            $d->save();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Re-sync one device's status from the bridge (admin, cross-tenant). */
    public function deviceResync(string $id): \Illuminate\Http\RedirectResponse
    {
        $d  = \App\Models\Device::findOrFail($id);
        $ok = $this->syncDeviceFromBridge($d);

        \App\Support\Audit::log('admin.device.resync', [
            'subject_type' => 'device', 'subject_id' => $d->id,
            'meta' => ['bridge' => $ok, 'status' => $d->status, 'workspace_id' => $d->workspace_id],
        ]);

        return back()->with('status', $ok
            ? __('Device re-synced — status is now :s.', ['s' => $d->status])
            : __('Could not reach the bridge; the device status is unchanged.'));
    }

    /** Re-sync every device on the platform from the bridge in one pass. */
    public function devicesBulkResync(): \Illuminate\Http\RedirectResponse
    {
        $devices = \App\Models\Device::query()->get();
        $ok = 0;
        foreach ($devices as $d) {
            if ($this->syncDeviceFromBridge($d)) {
                $ok++;
            }
        }

        \App\Support\Audit::log('admin.device.bulk_resync', [
            'meta' => ['total' => $devices->count(), 'synced' => $ok],
        ]);

        return back()->with('status', $ok > 0
            ? __(':n of :t devices re-synced from the bridge.', ['n' => $ok, 't' => $devices->count()])
            : __('No devices could be reached on the bridge.'));
    }

    /**
     * Export the device roster as CSV, honouring the same status + search
     * filters as the index so "what you see is what you export".
     */
    public function devicesExport(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = (string) $request->query('status', 'all');
        $q      = trim((string) $request->query('q', ''));

        $rows = \App\Models\Device::query()->with(['user', 'workspace'])
            ->when(in_array($status, \App\Models\Device::STATUSES, true), fn ($x) => $x->where('status', $status))
            ->orderByDesc('id')->get();

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = $rows->filter(function ($d) use ($needle) {
                $phone = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
                foreach ([$d->device_name, $phone, optional($d->user)->name, optional($d->user)->email, optional($d->workspace)->name] as $hay) {
                    if ($hay !== null && $hay !== '' && mb_stripos((string) $hay, $needle) !== false) return true;
                }
                return false;
            })->values();
        }

        $filename = 'devices-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Device', 'Number', 'Workspace', 'Owner', 'Owner email', 'Status', 'Sent 24h', 'Last sync']);
            foreach ($rows as $d) {
                // Prefix a leading quote onto values that Excel would treat as a
                // formula (=,+,-,@), the same CSV-injection guard the audit-log
                // export uses.
                $safe = function ($v) {
                    $v = (string) $v;
                    return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
                };
                fputcsv($out, [
                    $safe($d->device_name ?: ('#' . $d->id)),
                    $safe(trim($d->country_code . ' ' . $d->phone_number)),
                    $safe(optional($d->workspace)->name ?? '—'),
                    $safe(optional($d->user)->name ?? '—'),
                    $safe(optional($d->user)->email ?? '—'),
                    $safe($d->status),
                    (int) $d->sent_24h,
                    $d->last_seen_at ? $d->last_seen_at->toDateTimeString() : '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Permanently remove a device row from the admin console. */
    public function deviceDestroy(string $id): \Illuminate\Http\RedirectResponse
    {
        $d    = \App\Models\Device::findOrFail($id);
        $name = $d->device_name ?: ('#' . $d->id);
        $wsId = $d->workspace_id;

        // Best-effort: kill the live socket first so a deleted row can't leave
        // an orphan session running on the bridge.
        $base  = wd_node_url();
        $phone = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
        if ($base !== '' && $phone !== '') {
            try {
                \Illuminate\Support\Facades\Http::timeout(8)->acceptJson()
                    ->withHeaders(['X-Node-Token' => node_token()])
                    ->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
            } catch (\Throwable $e) { /* deleting anyway */ }
        }

        $d->delete();

        \App\Support\Audit::log('admin.device.destroy', [
            'subject_type' => 'device', 'subject_id' => (int) $id,
            'meta' => ['workspace_id' => $wsId],
        ]);

        return redirect()->route('admin.devices.index')
            ->with('status', __('Device ":name" removed.', ['name' => $name]));
    }

    // Billing
    public function packages(): View
    {
        // Plans only — add-ons (type='addon') live on their own /admin/addons
        // page, so they must NOT leak into the packages list.
        $packages = \App\Models\Package::query()->plans()->orderBy('sort_order')->orderBy('plan_amount')->get();

        // Workspace count per plan — used to show "N workspaces" on each card.
        $wsCounts = \Illuminate\Support\Facades\DB::table('workspaces')
            ->select('plan', \Illuminate\Support\Facades\DB::raw('COUNT(*) as c'))
            ->groupBy('plan')->pluck('c', 'plan');

        $activeCount   = $packages->where('status', true)->count();
        $trialCount    = $packages->where('free', true)->count();
        $archivedCount = $packages->where('status', false)->count();

        // Total subscribed workspaces (any plan).
        $subscribed = (int) \App\Models\Workspace::query()->whereIn('plan', $packages->pluck('id'))->count();

        // MRR = sum of the ACTUAL charged price (offer price when set) ×
        // workspace_count for paid+active plans. Using plan_amount overstated
        // MRR whenever a discounted offer price was in effect.
        $mrr = 0.0;
        foreach ($packages as $p) {
            if ($p->free || !$p->status) continue;
            $mrr += $p->chargeableAmount() * (int) ($wsCounts[$p->id] ?? 0);
        }

        return view('admin.packages.index', [
            'packages'      => $packages,
            'wsCounts'      => $wsCounts,
            'stats' => [
                'active'     => $activeCount,
                'trial'      => $trialCount,
                'archived'   => $archivedCount,
                'subscribed' => $subscribed,
                'mrr'        => $mrr,
            ],
        ]);
    }
    public function packageCreate(): View
    {
        return view('admin.packages.create', [
            'package'        => null,
            'limitColumns'   => self::planLimitColumns(),
            'featureToggles' => self::planFeatureToggles(),
            'currencies'     => \App\Models\Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol']),
        ]);
    }
    public function packageEdit(string $id): View
    {
        $package = \App\Models\Package::findOrFail($id);
        return view('admin.packages.create', [
            'package'        => $package,
            'limitColumns'   => self::planLimitColumns(),
            'featureToggles' => self::planFeatureToggles(),
            'currencies'     => \App\Models\Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol']),
        ]);
    }
    public function packageAnalytics(\Illuminate\Http\Request $request): View
    {
        $days = (int) $request->query('days', 90);
        if (! in_array($days, [30, 90, 365], true)) $days = 90;
        $packageFilter = (string) $request->query('package', '');
        $since = now()->subDays($days);

        // ── Package list (drives filter dropdown + leaderboard) — plans only ─
        $packages = \App\Models\Package::query()->plans()->orderBy('sort_order')->orderBy('id')->get();
        $packageById = $packages->keyBy('id');

        // ── Per-plan subscriber counts (workspaces.plan = slug) ──────
        $subsByPlan = collect();
        try {
            $subsByPlan = \DB::table('workspaces')
                ->selectRaw("COALESCE(NULLIF(plan, ''), 'free') AS plan, COUNT(*) AS n")
                ->whereNull('deleted_at')
                ->groupBy('plan')->pluck('n', 'plan');
        } catch (\Throwable $e) {}

        // ── Per-plan MRR (paid orders in window) ────────────────────
        $mrrByPlan = collect();
        try {
            $mrrByPlan = \DB::table('orders')
                ->where('status', 'paid')
                ->where('created_at', '>=', $since)
                ->selectRaw('package_id, SUM(total_amount) AS mrr')
                ->groupBy('package_id')->pluck('mrr', 'package_id');
        } catch (\Throwable $e) {}

        // ── KPI hero ────────────────────────────────────────────────
        $totalMrr   = (float) $mrrByPlan->sum();
        $subsTotal  = (int)   $subsByPlan->sum();
        $arpa       = $subsTotal > 0 ? $totalMrr / $subsTotal : 0;
        // LTV avg approximated as ARPA × 28 (typical SaaS lifetime months);
        // when subs=0 this collapses to 0.
        $ltvAvg     = $arpa * 28;
        $churnPct   = 0.0;
        $cancelled30d = 0;
        $newPaid30d   = 0;
        try {
            $cancelled30d = (int) \DB::table('orders')
                ->where('status', 'cancelled')->where('updated_at', '>=', $since)->count();
            $newPaid30d   = (int) \DB::table('orders')
                ->where('status', 'paid')->where('created_at', '>=', $since)->count();
            if ($subsTotal > 0) $churnPct = round($cancelled30d / max(1, $subsTotal) * 100, 1);
        } catch (\Throwable $e) {}
        $trialActive = (int) ($subsByPlan->get('free', $subsByPlan->get('trial', 0)));
        $trialToPaidPct = $trialActive > 0 ? round($newPaid30d / max(1, $newPaid30d + $trialActive) * 100) : 0;

        // ── 90-day weekly MRR series per top-3 plans (for chart) ───
        // Weekly buckets so the line chart stays smooth even at 365d.
        $bucketWeeks = max(4, (int) round($days / 7));
        $topPlans = $packages->take(3); // first three packages = top three slugs
        $mrrSeries = ['categories' => [], 'series' => []];
        try {
            $start = now()->subWeeks($bucketWeeks - 1)->startOfWeek();
            $weekCats = [];
            for ($i = 0; $i < $bucketWeeks; $i++) {
                $weekCats[] = $start->copy()->addWeeks($i)->format('M j');
            }
            $mrrSeries['categories'] = $weekCats;

            foreach ($topPlans as $pkg) {
                $row = ['name' => (string) $pkg->pname, 'data' => array_fill(0, $bucketWeeks, 0)];
                $weekly = \DB::table('orders')
                    ->where('status', 'paid')
                    ->where('package_id', $pkg->id)
                    ->where('created_at', '>=', $start)
                    ->selectRaw('YEARWEEK(created_at, 1) as yw, SUM(total_amount) as t')
                    ->groupBy('yw')->pluck('t', 'yw');
                foreach ($weekly as $yw => $t) {
                    $y = (int) substr((string) $yw, 0, 4);
                    $w = (int) substr((string) $yw, 4);
                    $bucket = $start->copy()->setISODate($y, $w);
                    $idx = $start->diffInWeeks($bucket);
                    if ($idx >= 0 && $idx < $bucketWeeks) $row['data'][$idx] = round((float) $t / 1000, 1);
                }
                $mrrSeries['series'][] = $row;
            }
        } catch (\Throwable $e) {}

        // ── Plan share (donut) ──────────────────────────────────────
        $share = [];
        foreach ($packages as $pkg) {
            $count = (int) ($subsByPlan->get(strtolower($pkg->pname), 0)
                ?: $subsByPlan->get($pkg->plan_id ?? '', 0));
            $mrr   = (float) ($mrrByPlan->get($pkg->id, 0));
            $share[] = ['label' => $pkg->pname, 'count' => $count, 'mrr' => $mrr];
        }

        // ── Leaderboard rows ────────────────────────────────────────
        $leaderboard = [];
        foreach ($packages as $pkg) {
            $key = strtolower($pkg->pname);
            $cnt = (int) ($subsByPlan->get($key, 0));
            $mrr = (float) ($mrrByPlan->get($pkg->id, 0));
            $netNew = 0;
            try {
                $netNew = (int) \DB::table('orders')
                    ->where('status', 'paid')->where('package_id', $pkg->id)
                    ->where('created_at', '>=', $since)->count();
            } catch (\Throwable $e) {}
            $cancellations = 0;
            try {
                $cancellations = (int) \DB::table('orders')
                    ->where('status', 'cancelled')->where('package_id', $pkg->id)
                    ->where('updated_at', '>=', $since)->count();
            } catch (\Throwable $e) {}
            $churn = $cnt > 0 ? round($cancellations / $cnt * 100, 1) : 0;
            $arpaP = $cnt > 0 ? $mrr / $cnt : 0;
            $leaderboard[] = [
                'package'   => $pkg,
                'count'     => $cnt,
                'net_new'   => $netNew,
                'mrr'       => $mrr,
                'arpa'      => $arpaP,
                'churn_pct' => $churn,
                'ltv'       => $arpaP * 28,
                'trend'     => $netNew > $cancellations ? 'up' : ($netNew === $cancellations ? 'flat' : 'down'),
            ];
        }

        // ── Upgrade paths ───────────────────────────────────────────
        // Per-workspace plan transitions in the window: pull all paid
        // orders, sort by created_at, then count package_id → package_id
        // transitions. Cheap pass for a 90-day window.
        $upgrades = [];
        $totalTransitions = 0;
        try {
            $workspaceOrders = \DB::table('orders')
                ->where('status', 'paid')
                ->where('created_at', '>=', $since)
                ->whereNotNull('workspace_id')
                ->orderBy('workspace_id')->orderBy('created_at')
                ->get(['workspace_id', 'package_id', 'created_at']);
            $transitions = [];
            $prev = [];
            foreach ($workspaceOrders as $o) {
                $wsId = $o->workspace_id;
                if (isset($prev[$wsId]) && $prev[$wsId] !== $o->package_id) {
                    $key = $prev[$wsId] . '→' . $o->package_id;
                    $transitions[$key] = ($transitions[$key] ?? 0) + 1;
                }
                $prev[$wsId] = $o->package_id;
            }
            // Add a synthetic "trial → first paid" bucket: any workspace
            // that placed its first paid order in this window.
            $trialToPaid = (int) \DB::table('orders')
                ->where('status', 'paid')->where('created_at', '>=', $since)
                ->select('workspace_id')->distinct()->count('workspace_id');
            // Add a cancellations bucket.
            $cancels = $cancelled30d;
            $totalTransitions = array_sum($transitions) + $trialToPaid + $cancels;
            $maxCount = max([$trialToPaid, $cancels, ...array_values($transitions), 1]);

            if ($trialToPaid > 0) {
                $upgrades[] = ['from' => 'Trial', 'to' => 'First paid', 'count' => $trialToPaid,
                               'kind' => 'upgrade', 'pct' => (int) round($trialToPaid / $maxCount * 100)];
            }
            foreach ($transitions as $key => $cnt) {
                [$fromId, $toId] = explode('→', $key);
                $from = $packageById->get((int) $fromId)?->pname ?? '?';
                $to   = $packageById->get((int) $toId)?->pname ?? '?';
                $kind = (int) $toId > (int) $fromId ? 'upgrade' : 'downgrade';
                $upgrades[] = ['from' => $from, 'to' => $to, 'count' => $cnt,
                               'kind' => $kind, 'pct' => (int) round($cnt / $maxCount * 100)];
            }
            if ($cancels > 0) {
                $upgrades[] = ['from' => 'Any plan', 'to' => 'Cancel', 'count' => $cancels,
                               'kind' => 'cancel', 'pct' => (int) round($cancels / $maxCount * 100)];
            }
        } catch (\Throwable $e) {}

        // ── Cancellation reasons ────────────────────────────────────
        // Look for a `cancellation_reason` column on workspaces (set
        // when a workspace cancels). Empty defaults are kept as the
        // prototype layout but with real counts.
        $reasons = collect();
        try {
            if (\Schema::hasColumn('workspaces', 'cancellation_reason')) {
                $reasons = \DB::table('workspaces')
                    ->whereNotNull('cancellation_reason')
                    ->where('updated_at', '>=', $since)
                    ->selectRaw('cancellation_reason as label, COUNT(*) as n')
                    ->groupBy('cancellation_reason')->orderByDesc('n')->get();
            }
        } catch (\Throwable $e) {}
        $maxReason = (int) ($reasons->max('n') ?: 1);

        // ── Feature adoption ────────────────────────────────────────
        // Each row is % of paying workspaces that have at least one row
        // in the matching table. Tables we don't have yet → 0%.
        $payingWs = max(1, $subsTotal);
        $featureAdoption = [];
        $countDistinctWs = function (string $table) {
            try {
                if (! \Schema::hasTable($table)) return 0;
                $col = \Schema::hasColumn($table, 'workspace_id') ? 'workspace_id' : null;
                if (! $col) return 0;
                return (int) \DB::table($table)->distinct($col)->count($col);
            } catch (\Throwable $e) { return 0; }
        };
        // Real table names only — the old list pointed several rows at
        // tables that don't exist (campaigns→wpcampaigns, templates→
        // wa_templates, meta_ads→meta_campaigns) so they always read 0%,
        // and at features with no table at all (REST API, SSO/SAML) which
        // are dropped rather than shown as a permanent 0%.
        $featureAdoption = [
            ['label' => 'Inbox & reply',       'count' => $countDistinctWs('messages')],
            ['label' => 'WA campaigns',        'count' => $countDistinctWs('wpcampaigns')],
            ['label' => 'Templates',           'count' => $countDistinctWs('wa_templates')],
            ['label' => 'Flow builder',        'count' => $countDistinctWs('flows')],
            ['label' => 'Meta Ads CTWA',       'count' => $countDistinctWs('meta_campaigns')],
            ['label' => 'Shopify',             'count' => $countDistinctWs('shopify_integrations')],
            ['label' => 'WooCommerce',         'count' => $countDistinctWs('woocommerce_integrations')],
            ['label' => 'HubSpot CRM',         'count' => $countDistinctWs('hubspot_integrations')],
        ];
        foreach ($featureAdoption as &$f) {
            $f['pct'] = $subsTotal > 0 ? (int) round($f['count'] / $payingWs * 100) : 0;
        }
        unset($f);

        $brand = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));

        return view('admin.packages.analytics', compact(
            'days', 'packageFilter', 'packages', 'mrrSeries', 'share', 'leaderboard',
            'totalMrr', 'subsTotal', 'arpa', 'ltvAvg', 'churnPct', 'cancelled30d',
            'newPaid30d', 'trialActive', 'trialToPaidPct', 'brand',
            'upgrades', 'totalTransitions', 'reasons', 'maxReason', 'featureAdoption'
        ));
    }

    /**
     * GET /admin/packages/analytics/export — stream the same numbers the
     * /analytics/overview page renders, as a single CSV. Honours the same
     * `days` + `package` query params so the export matches what the admin
     * is currently looking at on screen.
     *
     * The page-side "Export" button was previously a form submit that just
     * reloaded the same screen — i.e. no actual download. This handler is
     * what the button now points at.
     */
    public function packageAnalyticsExport(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $days = (int) $request->query('days', 90);
        if (! in_array($days, [30, 90, 365], true)) $days = 90;
        $packageFilter = (string) $request->query('package', '');
        $since = now()->subDays($days);

        $packages = \App\Models\Package::query()->plans()->orderBy('sort_order')->orderBy('id')->get();

        // Mirror the per-plan stats the page already computes — kept in lock-step
        // with packageAnalytics() so the CSV always matches the screen.
        $subsByPlan = collect();
        try {
            $subsByPlan = \DB::table('workspaces')
                ->selectRaw("COALESCE(NULLIF(plan, ''), 'free') AS plan, COUNT(*) AS n")
                ->whereNull('deleted_at')
                ->groupBy('plan')->pluck('n', 'plan');
        } catch (\Throwable $e) {}

        $mrrByPlan = collect();
        try {
            $mrrByPlan = \DB::table('orders')
                ->where('status', 'paid')
                ->where('created_at', '>=', $since)
                ->selectRaw('package_id, SUM(total_amount) AS mrr')
                ->groupBy('package_id')->pluck('mrr', 'package_id');
        } catch (\Throwable $e) {}

        // KPI totals.
        $totalMrr  = (float) $mrrByPlan->sum();
        $subsTotal = (int)   $subsByPlan->sum();
        $arpa      = $subsTotal > 0 ? $totalMrr / $subsTotal : 0;
        $cancelled = 0; $newPaid = 0;
        try {
            $cancelled = (int) \DB::table('orders')->where('status', 'cancelled')->where('updated_at', '>=', $since)->count();
            $newPaid   = (int) \DB::table('orders')->where('status', 'paid')     ->where('created_at', '>=', $since)->count();
        } catch (\Throwable $e) {}
        $churnPct = $subsTotal > 0 ? round($cancelled / max(1, $subsTotal) * 100, 1) : 0;

        $brand = \Illuminate\Support\Str::slug((string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk')));
        $filename = $brand . '-package-analytics-' . $days . 'd-' . now()->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use (
            $days, $packageFilter, $packages, $subsByPlan, $mrrByPlan,
            $totalMrr, $subsTotal, $arpa, $churnPct, $newPaid, $cancelled, $since
        ) {
            $out = fopen('php://output', 'w');

            // 1) Window + filters header — so the file is self-describing
            //    weeks later when someone re-opens it from email.
            fputcsv($out, ['# Package analytics export']);
            fputcsv($out, ['Window (days)', $days]);
            fputcsv($out, ['Package filter', $packageFilter ?: 'all']);
            fputcsv($out, ['Generated at', now()->toIso8601String()]);
            fputcsv($out, []);

            // 2) Top-line KPIs.
            fputcsv($out, ['# Summary']);
            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, ['Total MRR (window)',   number_format($totalMrr, 2, '.', '')]);
            fputcsv($out, ['Total subscribers',    $subsTotal]);
            fputcsv($out, ['ARPA',                 number_format($arpa, 2, '.', '')]);
            fputcsv($out, ['LTV (avg, ARPAx28)',   number_format($arpa * 28, 2, '.', '')]);
            fputcsv($out, ['Churn % (window)',     $churnPct]);
            fputcsv($out, ['New paid orders',      $newPaid]);
            fputcsv($out, ['Cancellations',        $cancelled]);
            fputcsv($out, []);

            // 3) Per-plan leaderboard — one row per package.
            fputcsv($out, ['# Per-plan leaderboard']);
            fputcsv($out, ['plan_id', 'plan_slug', 'name', 'subscribers', 'mrr', 'arpa', 'ltv', 'net_new', 'cancellations', 'churn_pct']);
            foreach ($packages as $pkg) {
                if ($packageFilter !== '' && $pkg->pname !== $packageFilter) continue;
                $cnt = (int) ($subsByPlan->get(strtolower($pkg->pname), 0)
                    ?: $subsByPlan->get($pkg->plan_id ?? '', 0));
                $mrr = (float) ($mrrByPlan->get($pkg->id, 0));
                $netNew = 0; $cancels = 0;
                try {
                    $netNew  = (int) \DB::table('orders')->where('status', 'paid')     ->where('package_id', $pkg->id)->where('created_at', '>=', $since)->count();
                    $cancels = (int) \DB::table('orders')->where('status', 'cancelled')->where('package_id', $pkg->id)->where('updated_at', '>=', $since)->count();
                } catch (\Throwable $e) {}
                $arpaP = $cnt > 0 ? $mrr / $cnt : 0;
                $churn = $cnt > 0 ? round($cancels / $cnt * 100, 1) : 0;
                fputcsv($out, [
                    $pkg->id, $pkg->plan_id, $pkg->pname,
                    $cnt,
                    number_format($mrr,      2, '.', ''),
                    number_format($arpaP,    2, '.', ''),
                    number_format($arpaP*28, 2, '.', ''),
                    $netNew, $cancels, $churn,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Validates + persists a Package row. Accepts every limit column
     * + every feature toggle. Empty limit = NULL = unlimited.
     */
    public function packageStore(\Illuminate\Http\Request $request)
    {
        return $this->packagePersist($request, null);
    }

    public function packageUpdate(\Illuminate\Http\Request $request, string $id)
    {
        $package = \App\Models\Package::findOrFail($id);
        return $this->packagePersist($request, $package);
    }

    public function packageDestroy(string $id)
    {
        $package = \App\Models\Package::findOrFail($id);
        // Block delete if any workspace currently uses this package.
        $inUse = \App\Models\Workspace::where('plan', $package->id)->count();
        if ($inUse > 0) {
            return back()->with('error', 'Cannot delete: ' . $inUse . ' workspace(s) currently use this package.');
        }
        $package->delete();
        return redirect()->route('admin.packages.index')->with('success', 'Package deleted.');
    }

    public function packageToggle(string $id)
    {
        $package = \App\Models\Package::findOrFail($id);
        $package->update(['status' => !$package->status]);
        return back()->with('success', $package->status ? 'Activated.' : 'Deactivated.');
    }

    private function packagePersist(\Illuminate\Http\Request $request, ?\App\Models\Package $package)
    {
        // packages.plan_id is VARCHAR(255) NOT NULL UNIQUE — a SLUG, not an
        // integer (confirmed via SHOW COLUMNS). The old code declared it as
        // integer + auto-numbered new rows, which both broke edits of any
        // string-slug row ("plan id field must be an integer") AND would have
        // collided with any seeded slug like "growth"/"pro". Resolve as a slug:
        //   • edit  → keep the row's existing slug (never rename a published plan)
        //   • new   → use the posted slug, else derive one from pname, with a
        //             unique-suffix bumper so duplicates never throw at SQL.
        $planIdRaw = trim((string) $request->input('plan_id', ''));
        if ($package) {
            $request->merge(['plan_id' => $package->plan_id]);
        } else {
            $slug = $planIdRaw !== ''
                ? \Illuminate\Support\Str::slug($planIdRaw, '_')
                : \Illuminate\Support\Str::slug((string) $request->input('pname', 'plan'), '_');
            $slug = $slug !== '' ? $slug : 'plan';
            // Bump _2, _3 … until unique. Cheap — there are O(10) packages.
            $base = $slug; $i = 2;
            while (\App\Models\Package::where('plan_id', $slug)->exists()) {
                $slug = $base . '_' . $i++;
            }
            $request->merge(['plan_id' => $slug]);
        }

        // Base validation rules.
        $rules = [
            'pname'           => ['required', 'string', 'max:120'],
            'subtitle'        => ['nullable', 'string', 'max:191'],
            'detail'          => ['nullable', 'string', 'max:5000'],
            'plan_id'         => ['nullable', 'string', 'max:191'],
            'plan_amount'     => ['required', 'numeric', 'min:0'],
            // The offer / discounted price must be LESS than the regular
            // plan_amount — otherwise it isn't a discount. Without lt: an admin
            // could set an offer ABOVE the price and it saved silently (the
            // Kothari Tech report). nullable still allows no offer at all.
            'offer_price'     => ['nullable', 'numeric', 'min:0', 'lt:plan_amount'],
            'currency'        => ['required', 'string', 'max:10'],
            'plan_duration'   => ['required', 'integer', 'min:1', 'max:120'],
            'plan_unit'       => ['required', 'in:days,weeks,months,years'],
            'cta_label'       => ['nullable', 'string', 'max:64'],
            'cta_url'         => ['nullable', 'string', 'max:255'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
            'free'            => ['nullable', 'boolean'],
            'lifetime'        => ['nullable', 'boolean'],
            'status'          => ['nullable', 'boolean'],
            'is_default'      => ['nullable', 'boolean'],
            'is_highlighted'  => ['nullable', 'boolean'],
            'is_custom_quote' => ['nullable', 'boolean'],
            // Add-on packages: 'addon' = an à-la-carte feature pack bought on
            // top of a plan (grants its toggles/limits via Workspace::
            // effectiveLimit merge). 'plan' = a normal subscription (default).
            'type'                => ['nullable', 'in:plan,addon'],
            // Days after plan expiry before the workspace's data is auto-wiped
            // (WipeExpiredWorkspaces command). 0 = never.
            'data_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
        foreach (self::planLimitColumns() as $col)   $rules[$col] = ['nullable', 'integer', 'min:0'];
        foreach (self::planFeatureToggles() as $col) $rules[$col] = ['nullable', 'boolean'];
        $data = $request->validate($rules, [
            'offer_price.lt' => __('The offer price must be lower than the plan price.'),
        ]);

        // Normalize: empty limit → 0 (unlimited). Schema marks every limit
        // column NOT NULL DEFAULT 0, so sending NULL crashed the INSERT
        // with "Column 'device_limit' cannot be null" when the admin left
        // the field blank. PlanLimitGuard already treats 0 as "no throw"
        // (same as NULL), so the form copy "leave blank = unlimited"
        // still holds. Checkbox unchecked = false; the form sends "1"
        // only for checked checkboxes.
        $payload = [];
        foreach ($data as $k => $v) {
            if (in_array($k, self::planLimitColumns(), true)) {
                $payload[$k] = ($v === null || $v === '') ? 0 : (int) $v;
            } elseif (in_array($k, self::planFeatureToggles(), true) || in_array($k, ['free', 'lifetime', 'status', 'is_default', 'is_highlighted', 'is_custom_quote'], true)) {
                $payload[$k] = (bool) $v;
            } else {
                $payload[$k] = $v;
            }
        }
        // Defense in depth: every limit column on the schema is NOT NULL —
        // stamp 0 for any column the form omits entirely (e.g. a newly-
        // added limit column before its <input> ships in the blade).
        foreach (self::planLimitColumns() as $col) {
            if (!array_key_exists($col, $payload)) $payload[$col] = 0;
        }
        // Ensure every feature toggle has an explicit value (unchecked
        // checkboxes don't submit at all). Default to false.
        foreach (self::planFeatureToggles() as $col) {
            if (!array_key_exists($col, $payload)) $payload[$col] = false;
        }
        foreach (['free', 'lifetime', 'status', 'is_default', 'is_highlighted', 'is_custom_quote'] as $col) {
            if (!array_key_exists($col, $payload)) $payload[$col] = false;
        }
        // Add-on type + retention — explicit defaults (form may omit them).
        $payload['type'] = in_array(($data['type'] ?? 'plan'), ['plan', 'addon'], true) ? $data['type'] : 'plan';
        $payload['data_retention_days'] = (($data['data_retention_days'] ?? '') === '' || ($data['data_retention_days'] ?? null) === null)
            ? 0 : (int) $data['data_retention_days'];

        // sort_order — schema is NOT NULL DEFAULT 0. The blade pre-fills 0
        // so normally this is set, but if the admin clears the field
        // before saving, validation leaves it null and the INSERT crashes.
        // Coerce here.
        if (($payload['sort_order'] ?? null) === null || $payload['sort_order'] === '') {
            $payload['sort_order'] = 0;
        } else {
            $payload['sort_order'] = (int) $payload['sort_order'];
        }
        // offer_price column allows NULL in schema, but plan_amount is
        // required by validation so doesn't need coercion. Same for currency,
        // pname, plan_unit, plan_duration — all required and validated.

        // Add-ons are managed from their own /admin/addons section — send the
        // operator back there after save; plans go to the packages list.
        $isAddon = ($payload['type'] ?? 'plan') === 'addon';
        $route   = $isAddon ? 'admin.addons.index' : 'admin.packages.index';
        $noun    = $isAddon ? 'Add-on' : 'Package';

        if ($package) {
            $package->update($payload);
            return redirect()->route($route)->with('success', "{$noun} updated.");
        }
        \App\Models\Package::create($payload);
        return redirect()->route($route)->with('success', "{$noun} created.");
    }

    public function billingHistory(): View
    {
        return view('admin.billing-history.index');
    }
    public function billingAnalytics(): View
    {
        return view('admin.billing-history.analytics');
    }
    public function orderHistory(): View
    {
        return view('admin.order-history.index');
    }
    public function orderAnalytics(): View
    {
        return view('admin.order-history.analytics');
    }
    public function invoices(): View
    {
        return view('admin.invoices.index');
    }
    public function invoiceView(string $id): View
    {
        return view('admin.invoices.view');
    }
    public function coupons(): View
    {
        return view('admin.coupons.index');
    }

    // Support
    public function support(): View
    {
        // Every ticket every workspace has opened across the platform.
        // The user-side flow at /support writes rows to `support_tickets`;
        // this view is where admins triage them.
        //
        // Eager-load `user` + `workspace` so the Workspace column +
        // requester sub-line render without N+1 fetches.
        $tickets = \App\Models\SupportTicket::query()
            ->with(['user', 'workspace'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $openCount       = \App\Models\SupportTicket::where('status', '!=', 'resolved')->count();
        $unassignedCount = $openCount; // No admin-assignee column yet; every open ticket is unassigned today.
        $urgentCount     = \App\Models\SupportTicket::where('status', '!=', 'resolved')
            ->where('created_at', '<=', now()->subHour())->count();
        $breachCount     = \App\Models\SupportTicket::where('status', '!=', 'resolved')
            ->where('created_at', '<=', now()->subHours(4))->count();
        $resolvedTodayCount = \App\Models\SupportTicket::whereDate('resolved_at', now()->toDateString())->count();

        $kpi = [
            'open'           => $openCount,
            'unassigned'     => $unassignedCount,
            'urgent'         => $urgentCount,
            'breach'         => $breachCount,
            'resolved_today' => $resolvedTodayCount,
            // Mine = tickets assigned to the current admin. No
            // assignee column shipped yet, so zero for now — wire
            // when we ship the assignment UI.
            'mine'           => 0,
        ];

        return view('admin.support.index', compact('tickets', 'kpi'));
    }
    public function supportSla(): View
    {
        return view('admin.support.sla');
    }
    public function supportCustomers(): View
    {
        return view('admin.support.customers');
    }
    public function supportPlaybooks(): View
    {
        return view('admin.support.playbooks');
    }
    public function supportReports(): View
    {
        return view('admin.support.reports');
    }
    public function teamInbox(): View
    {
        return view('admin.team-inbox.index');
    }

    // Marketing
    public function announcements(): View
    {
        return view('admin.announcements.index');
    }
    public function guidebook(): View
    {
        return view('admin.guidebook.index');
    }

    // Platform admin
    public function analytics(\Illuminate\Http\Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $since = now()->subDays($days);

        // ?export=csv → stream a daily-signup CSV for the window. Kept
        // small on purpose: the rest of the dashboard is interactive,
        // CSV is for "give me the chart series" use cases.
        if ($request->query('export') === 'csv') {
            $rows = \App\Models\User::where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
                ->groupBy('d')->orderBy('d')->get();
            $filename = 'analytics-signups-' . $days . 'd-' . now()->format('Ymd-Hi') . '.csv';
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['date', 'signups']);
                foreach ($rows as $r) fputcsv($out, [$r->d, (int) $r->n]);
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        // ── Devices ─────────────────────────────────────────────────
        $devices = \App\Models\Device::query()
            ->orderByDesc('active')->orderByDesc('id')->limit(8)->get();
        $deviceLabels = $devices->map(function ($d) {
            $tail = $d->phone_number ? ' · +' . ltrim($d->country_code ?: '', '+') . ' ' . substr($d->phone_number, 0, 4) . 'xx' : '';
            return ($d->device_name ?: 'Device #' . $d->id) . $tail;
        })->values()->all();
        $deviceData         = $devices->map(fn ($d) => (int) $d->sent_24h)->values()->all();
        $devicesOnlineCount = \App\Models\Device::where('status', 'connected')->where('active', true)->count();
        $devicesTotalCount  = \App\Models\Device::count();

        // ── Platform headline KPIs ──────────────────────────────────
        $kpi = [
            'users_total'       => \App\Models\User::count(),
            'users_new'         => \App\Models\User::where('created_at', '>=', $since)->count(),
            'workspaces_total'  => \App\Models\Workspace::count(),
            'workspaces_new'    => \App\Models\Workspace::where('created_at', '>=', $since)->count(),
            'devices_online'    => $devicesOnlineCount,
            'devices_total'     => $devicesTotalCount,
        ];

        // Best-effort message + revenue stats. Tables may not exist on
        // fresh installs (e.g. messages_outbound is sprint-N); guard.
        try {
            $kpi['messages_sent']   = \DB::table('messages_outbound')->where('created_at', '>=', $since)->count();
        } catch (\Throwable $e) { $kpi['messages_sent'] = null; }
        try {
            $kpi['revenue_window']  = (float) \DB::table('orders')
                ->where('status', 'paid')->where('created_at', '>=', $since)->sum('total_amount');
        } catch (\Throwable $e) { $kpi['revenue_window'] = null; }
        try {
            $kpi['tickets_open']    = \DB::table('support_tickets')->whereIn('status', ['open','in_progress','pending'])->count();
        } catch (\Throwable $e) { $kpi['tickets_open'] = 0; }

        // ── Daily signups (sparkline) ───────────────────────────────
        $signupSeries = \App\Models\User::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
            ->groupBy('d')->orderBy('d')->get();

        // ── Plan distribution ───────────────────────────────────────
        // workspaces.plan is a string slug (starter, pro, free, …) — no
        // FK join required. Single quotes inside the raw SELECT are
        // important: double quotes would be interpreted as identifiers
        // under ANSI_QUOTES sql_mode and silently break the query.
        $planDistribution = collect();
        try {
            $planDistribution = \DB::table('workspaces')
                ->selectRaw("COALESCE(NULLIF(plan, ''), 'free') as plan, COUNT(*) as n")
                ->groupBy('plan')
                ->orderByDesc('n')
                ->get()
                ->map(fn ($r) => (object) ['plan' => ucfirst((string) $r->plan), 'n' => (int) $r->n])
                ->values();
        } catch (\Throwable $e) {}

        // ── Top workspaces by 30-day spend ──────────────────────────
        $topWorkspaces = collect();
        try {
            $topWorkspaces = \DB::table('orders')
                ->join('workspaces', 'workspaces.id', '=', 'orders.workspace_id')
                ->where('orders.status', 'paid')
                ->where('orders.created_at', '>=', $since)
                ->selectRaw('workspaces.id, workspaces.name, SUM(orders.total_amount) as paid, COUNT(*) as n')
                ->groupBy('workspaces.id', 'workspaces.name')
                ->orderByDesc('paid')->limit(8)->get();
        } catch (\Throwable $e) {}

        return view('admin.analytics.index', compact(
            'days', 'kpi',
            'deviceLabels', 'deviceData', 'devicesOnlineCount', 'devicesTotalCount',
            'signupSeries', 'planDistribution', 'topWorkspaces',
        ));
    }
    public function auditLog(): View
    {
        return view('admin.audit-log.index');
    }
    public function security(): View
    {
        return view('admin.security.index');
    }

    // Settings
    public function settings(): View
    {
        // Surface affiliate / wallet tunables AND the 3-provider toggle
        // on the settings index so an admin can configure both from the
        // same place. The provider toggle is the master switch that
        // controls which methods workspaces can connect to at /connect.
        $allowed = \App\Models\SystemSetting::get('allowed_send_methods', ['waba', 'baileys', 'twilio']);
        $allowed = is_array($allowed) ? $allowed : ['waba', 'baileys', 'twilio'];

        $settings = [
            'referral_signup_credits'    => (int)   \App\Models\SystemSetting::get('referral_signup_credits', 100),
            'credits_per_message'        => (int)   \App\Models\SystemSetting::get('credits_per_message', 1),
            'credits_per_currency_minor' => (float) \App\Models\SystemSetting::get('credits_per_currency_minor', 0.1),
            'default_send_method'        => (string) \App\Models\SystemSetting::get('default_send_method', 'baileys'),
            'allowed_send_methods'       => $allowed,
            // Per-provider admin defaults — workspaces reuse these unless
            // they override in their own wa_provider_configs row.
            'waba_app_id'                => (string) \App\Models\SystemSetting::get('waba_app_id', ''),
            'waba_app_secret_set'        => \App\Models\SystemSetting::where('key', 'waba_app_secret')->exists(),
            'waba_config_id'             => (string) \App\Models\SystemSetting::get('waba_config_id', ''),
            'waba_coexistence'           => (bool) \App\Models\SystemSetting::get('waba_coexistence', false),
            'waba_webhook_verify_token'  => (string) \App\Models\SystemSetting::get('waba_webhook_verify_token', ''),
            'baileys_server_url'         => (string) \App\Models\SystemSetting::get('baileys_server_url', env('SERVER_URL', '')),
            'twilio_account_sid'         => (string) \App\Models\SystemSetting::get('twilio_account_sid', ''),
            'twilio_auth_token_set'      => \App\Models\SystemSetting::where('key', 'twilio_auth_token')->exists(),
            'twilio_whatsapp_number'     => (string) \App\Models\SystemSetting::get('twilio_whatsapp_number', ''),
            // Phase 5 — multi-WABA dispatcher feature flag + Graph API version.
            'waba_dispatch_v2_enabled'   => (bool)   \App\Models\SystemSetting::get('waba_dispatch_v2_enabled', false),
            'waba_graph_api_version'     => (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0'),
            'waba_connected_count'       => (int)    \DB::table('wa_provider_configs')->where('provider', 'waba')->where('status', 'connected')->count(),
            // Phase 6 — templates v2 (Meta /message_templates submit + status sync).
            'waba_templates_v2_enabled'  => (bool)   \App\Models\SystemSetting::get('waba_templates_v2_enabled', false),
            'waba_template_polling_min'  => (int)    \App\Models\SystemSetting::get('waba_template_polling_min', 30),
            'waba_template_lint_strict'  => (bool)   \App\Models\SystemSetting::get('waba_template_lint_strict', true),
            // Sender pacing — Node bridge reads these via /api/whatsapp-message-settings
            // and uses them as msg_gap (sec), batches_gap (per batch),
            // bw_msg_gap (min), enable_batches (0/1). Keys match
            // node/index.js exactly so the bridge picks them up without
            // any translation layer.
            'msg_gap'                    => (int)    \App\Models\SystemSetting::get('msg_gap', 3),
            'batches_gap'                => (int)    \App\Models\SystemSetting::get('batches_gap', 50),
            'bw_msg_gap'                 => (int)    \App\Models\SystemSetting::get('bw_msg_gap', 5),
            'enable_batches'             => (bool)   \App\Models\SystemSetting::get('enable_batches', false),
            // Campaign auto-end (expiry) — default ON, 24h from first send.
            'campaign_auto_expiry_enabled'  => (bool) \App\Models\SystemSetting::get('campaign_auto_expiry_enabled', true),
            'campaign_default_expiry_hours' => (int)  \App\Models\SystemSetting::get('campaign_default_expiry_hours', 24),
        ];
        // KPI strip on the index — replaces the hardcoded 12/9/3/8/2h
        // placeholders. Numbers come from the actual system_settings
        // table so a fresh install starts blank and fills up as the
        // admin configures things.
        $secretPatterns = ['_secret', '_password', '_token', '_apikey', '_api_key'];
        $allKv = \App\Models\SystemSetting::query()->select(['key', 'value', 'updated_at'])->get();
        $totalRows = $allKv->count();
        $healthyRows = $allKv->filter(fn ($r) => trim((string) $r->value) !== '')->count();
        $secretRows = $allKv->filter(function ($r) use ($secretPatterns) {
            $k = strtolower($r->key);
            foreach ($secretPatterns as $p) if (str_ends_with($k, $p)) return true;
            return false;
        })->filter(fn ($r) => trim((string) $r->value) !== '')->count();

        // "Needs test" = saved but never verified by an audit ping. Looks
        // for the matching audit-action keys; missing tables don't crash.
        $needsTest = [];
        try {
            $testedActions = \App\Models\AuditLog::query()->whereIn('action', [
                'settings.mail_test', 'settings.catalog_test', 'shopify.oauth_completed',
            ])->where('outcome', 'success')->pluck('action')->unique()->all();
            if (! in_array('settings.mail_test', $testedActions, true)
                && trim((string) \App\Models\SystemSetting::get('mail_host', '')) !== '') $needsTest[] = 'mail';
            if (\App\Models\SystemSetting::get('catalog_enabled')
                && ! in_array('settings.catalog_test', $testedActions, true)) $needsTest[] = 'catalog';
            if (\App\Models\SystemSetting::get('shopify_enabled')
                && ! in_array('shopify.oauth_completed', $testedActions, true)) $needsTest[] = 'shopify';
        } catch (\Throwable $e) {}

        $lastUpdate = $allKv->max('updated_at');
        $lastUpdateBy = null;
        try {
            $lastAudit = \App\Models\AuditLog::query()
                ->where('action', 'like', 'settings.%')
                ->orderByDesc('created_at')
                ->with('user:id,name')
                ->first();
            $lastUpdateBy = $lastAudit?->user?->name;
        } catch (\Throwable $e) {}

        $kpi = [
            'total_rows'    => $totalRows,
            'healthy'       => $healthyRows,
            'needs_test'    => $needsTest,
            'secret_count'  => $secretRows,
            'last_updated'  => $lastUpdate,
            'last_updated_by' => $lastUpdateBy,
        ];

        return view('admin.settings.index', compact('settings', 'kpi'));
    }

    public function settingsProvidersUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        // Track every providers/pacing save so a "toggle didn't save" report is
        // reproducible from the log: exactly what reached the controller +
        // the raw enable_batches value before validation/cast.
        \Illuminate\Support\Facades\Log::info('[WADESK-MSG] providers.update received', [
            'allowed_send_methods'=> $request->input('allowed_send_methods'),
            'default_engine'      => $request->input('default_engine'),
            'enable_batches_raw'  => $request->input('enable_batches'),     // null when unchecked
            'enable_batches_bool' => $request->boolean('enable_batches'),
            'msg_gap'             => $request->input('msg_gap'),
            'batches_gap'         => $request->input('batches_gap'),
            'bw_msg_gap'          => $request->input('bw_msg_gap'),
            'node_token_field_filled' => filled($request->input('node_webhook_token')),
            'admin_id'            => optional($request->user())->id,
        ]);

        $data = $request->validate([
            // Multi-engine: the admin enables ANY SUBSET of the three engines
            // platform-wide; each workspace then uses whatever it connects
            // within this set. `default_engine` is the platform fallback for
            // sends that don't pin a sender. (Was a single `active_engine`
            // radio — now checkboxes + a default picker.)
            'allowed_send_methods'       => 'required|array|min:1',
            'allowed_send_methods.*'     => 'in:twilio,wa-api,business-api',
            'default_engine'             => 'nullable|in:twilio,wa-api,business-api',
            'waba_app_id'                => 'nullable|string|max:64',
            'waba_app_secret'            => 'nullable|string|max:128',
            'waba_config_id'             => 'nullable|string|max:64',
            'waba_coexistence'           => 'nullable|boolean',
            'waba_webhook_verify_token'  => 'nullable|string|max:96',
            'baileys_server_url'         => 'nullable|url|max:191',
            'twilio_account_sid'         => 'nullable|string|max:64',
            'twilio_auth_token'          => 'nullable|string|max:128',
            'twilio_whatsapp_number'     => 'nullable|string|max:32',
            // Phase 5 feature flag — gate the multi-tenant WABA dispatcher.
            // Default OFF; admin flips ON once they've connected at least
            // one WABA account at /devices. Rollback by flipping OFF.
            'waba_dispatch_v2_enabled'   => 'sometimes|boolean',
            'waba_graph_api_version'     => 'nullable|regex:/^v\d{1,2}\.\d{1,2}$/',
            'waba_templates_v2_enabled'  => 'sometimes|boolean',
            'waba_template_polling_min'  => 'sometimes|integer|min:5|max:240',
            'waba_template_lint_strict'  => 'sometimes|boolean',
            // Sender pacing — Node-side key names, matched 1:1.
            'msg_gap'                    => 'sometimes|integer|min:1|max:600',
            'batches_gap'                => 'sometimes|integer|min:1|max:10000',
            'bw_msg_gap'                 => 'sometimes|integer|min:1|max:1440',
            'enable_batches'             => 'sometimes|boolean',
            // Campaign auto-end (expiry).
            'campaign_auto_expiry_enabled'  => 'sometimes|boolean',
            'campaign_default_expiry_hours' => 'sometimes|integer|min:1|max:8760',
            'node_webhook_token'         => 'nullable|string|max:191',
            // Call-flow "Search web" node provider + key (encrypted at rest).
            'web_search_provider'        => 'nullable|in:tavily,serpapi,brave',
            'web_search_key'             => 'nullable|string|max:200',
        ]);

        // Multi-engine: map each visual engine slug → internal provider key.
        // The admin may enable any subset (1, 2, or all 3). allowed_send_methods
        // is the platform-wide enabled SET; default_send_method is the fallback
        // engine for sends that don't pin a sender. 3 cards — "Business API"
        // covers both manual-token + Embedded Signup (same Meta Cloud API).
        $slugToProvider = [
            'twilio'       => 'twilio',
            'wa-api'       => 'baileys',
            'business-api' => 'waba',
        ];
        $providerToSlug = array_flip($slugToProvider);

        // Enabled set (unique, order-preserving) of internal provider keys.
        $enabledProviders = array_values(array_unique(array_map(
            fn ($slug) => $slugToProvider[$slug],
            $data['allowed_send_methods']
        )));

        // Default engine must be one of the enabled set. If the posted default
        // isn't enabled (or wasn't posted), fall back to the first enabled.
        $defaultProvider = isset($data['default_engine'])
            ? ($slugToProvider[$data['default_engine']] ?? null)
            : null;
        if (! $defaultProvider || ! in_array($defaultProvider, $enabledProviders, true)) {
            $defaultProvider = $enabledProviders[0];
        }
        $defaultSlug = $providerToSlug[$defaultProvider] ?? 'wa-api';

        \App\Models\SystemSetting::set('allowed_send_methods', $enabledProviders, 'json',   'Platform-wide enabled WhatsApp engines (any subset of baileys/waba/twilio).');
        \App\Models\SystemSetting::set('default_send_method',  $defaultProvider,  'string', 'Default engine for sends that do not pin a sender.');
        \App\Models\SystemSetting::set('active_engine_slug',   $defaultSlug,      'string', 'Default engine card (back-compat: the visual slug of default_send_method).');

        // Per-provider creds. Empty strings clear the value; secrets
        // are only updated when the form actually carried a value
        // (so re-saving the page doesn't blank out a stored secret).
        \App\Models\SystemSetting::set('waba_app_id',               $data['waba_app_id']               ?? '', 'string', 'Meta App ID for Embedded Signup.');
        \App\Models\SystemSetting::set('waba_config_id',            $data['waba_config_id']            ?? '', 'string', 'Meta Login Configuration ID.');
        \App\Models\SystemSetting::set('waba_coexistence',          $request->boolean('waba_coexistence'), 'bool', 'Launch Embedded Signup in WhatsApp Coexistence mode (onboard existing Business App numbers).');
        // Call-flow web search (Tavily/SerpAPI/Brave). Key only updated when
        // the form carried a value — re-saving won't blank a stored key.
        \App\Models\SystemSetting::set('web_search_provider', (string) ($data['web_search_provider'] ?? 'tavily'), 'string', 'Provider for the Call Flow "Search web" node.');
        if (!empty($data['web_search_key'])) {
            \App\Models\SystemSetting::set('web_search_key', $data['web_search_key'], 'string', 'API key for the Call Flow web search provider.');
        }
        \App\Models\SystemSetting::set('waba_webhook_verify_token', $data['waba_webhook_verify_token'] ?? '', 'string', 'Webhook verify token Meta will echo on subscription.');
        if (!empty($data['waba_app_secret'])) {
            \App\Models\SystemSetting::set('waba_app_secret', $data['waba_app_secret'], 'string', 'Meta App Secret (encrypt-at-rest recommended).');
        }
        \App\Models\SystemSetting::set('baileys_server_url', $data['baileys_server_url'] ?? '', 'string', 'Default URL of the Baileys Node bridge.');
        // Mirror the Server URL into .env SERVER_URL so DB and env never drift
        // (admin doesn't hand-edit .env). DB stays authoritative via wd_node_url().
        \App\Support\EnvWriter::set('SERVER_URL', (string) ($data['baileys_server_url'] ?? ''));

        \App\Models\SystemSetting::set('twilio_account_sid',     $data['twilio_account_sid']     ?? '', 'string', 'Twilio Account SID.');
        \App\Models\SystemSetting::set('twilio_whatsapp_number', $data['twilio_whatsapp_number'] ?? '', 'string', 'Twilio WhatsApp From number.');
        if (!empty($data['twilio_auth_token'])) {
            \App\Models\SystemSetting::set('twilio_auth_token', $data['twilio_auth_token'], 'string', 'Twilio Auth Token.');
        }

        // Phase 5 — multi-WABA dispatcher toggle + Graph API version.
        \App\Models\SystemSetting::set('waba_dispatch_v2_enabled', $request->boolean('waba_dispatch_v2_enabled'), 'bool', 'Route /chat sends through wa_provider_configs instead of .env (multi-tenant).');
        if (! empty($data['waba_graph_api_version'])) {
            \App\Models\SystemSetting::set('waba_graph_api_version', $data['waba_graph_api_version'], 'string', 'Meta Cloud API version for outbound sends.');
        }

        // Phase 6 — templates v2: Meta submission + status sync.
        \App\Models\SystemSetting::set('waba_templates_v2_enabled', $request->boolean('waba_templates_v2_enabled'), 'bool', 'POST templates to Meta /message_templates on save + sync status via webhook/poll.');
        if (array_key_exists('waba_template_polling_min', $data)) {
            \App\Models\SystemSetting::set('waba_template_polling_min', (int) $data['waba_template_polling_min'], 'int', 'Server-side sweep interval (minutes) for PENDING templates older than 1h.');
        }
        \App\Models\SystemSetting::set('waba_template_lint_strict', $request->boolean('waba_template_lint_strict'), 'bool', 'When ON, lint warnings (trigger phrases, long bodies) block submit instead of just warning.');

        // Sender pacing — exact Node-side key names so the bridge
        // picks them up without translation. Node reads these via
        // /api/whatsapp-message-settings (WaConnectController::nodeMessageSettings).
        if (array_key_exists('msg_gap',     $data)) {
            \App\Models\SystemSetting::set('msg_gap',     (int) $data['msg_gap'],     'int', 'Seconds between consecutive outbound sends (Node bridge).');
        }
        if (array_key_exists('batches_gap', $data)) {
            \App\Models\SystemSetting::set('batches_gap', (int) $data['batches_gap'], 'int', 'Recipients per send batch (Node bridge).');
        }
        if (array_key_exists('bw_msg_gap',  $data)) {
            \App\Models\SystemSetting::set('bw_msg_gap',  (int) $data['bw_msg_gap'],  'int', 'Minutes between batches (Node bridge).');
        }
        \App\Models\SystemSetting::set('enable_batches', $request->boolean('enable_batches'), 'bool', 'Whether the Node bridge breaks large sends into batches.');

        // Campaign auto-end (expiry). Read by WaCampaignsController::dispatchCampaignNow:
        // a one-off campaign with no per-campaign end date and no daily-cap/window
        // auto-ends this many hours after its FIRST send. Toggle off = unlimited.
        \App\Models\SystemSetting::set('campaign_auto_expiry_enabled', $request->boolean('campaign_auto_expiry_enabled'), 'bool', 'Auto-end one-off campaigns after the default window.');
        if (array_key_exists('campaign_default_expiry_hours', $data)) {
            \App\Models\SystemSetting::set('campaign_default_expiry_hours', (int) $data['campaign_default_expiry_hours'], 'int', 'Hours after first send a one-off campaign auto-ends (default expiry).');
        }

        // Shared Node-bridge secret (X-Node-Token). Only update when the admin
        // actually typed a value — the field is masked + blank on load, so an
        // empty submit must KEEP the existing token, not wipe it. Stored
        // encrypted at rest (node_webhook_token ∈ SystemSetting::ENCRYPTED_KEYS).
        if (filled($data['node_webhook_token'] ?? null)) {
            \App\Models\SystemSetting::set('node_webhook_token', trim((string) $data['node_webhook_token']), 'string', 'Shared secret authenticating Node <-> Laravel bridge calls (X-Node-Token).');
            // Mirror into .env so DB and Laravel's env stay identical — no
            // manual .env editing. (Node's own node/.env must still match.)
            \App\Support\EnvWriter::set('NODE_WEBHOOK_TOKEN', trim((string) $data['node_webhook_token']));
        }

        // Registration WhatsApp OTP verification. Sender device + auth template
        // are cleared to 0 when the admin picks "none". The RegistrationOtpService
        // gates activation on enabled + a valid device + (for WABA) an APPROVED
        // template, so a half-configured setup simply stays inactive.
        \App\Models\SystemSetting::set('registration_otp_enabled', $request->boolean('registration_otp_enabled'), 'bool', 'Verify the mobile number with a WhatsApp OTP during signup.');
        \App\Models\SystemSetting::set('registration_otp_sender', trim((string) $request->input('registration_otp_sender', '')), 'string', 'Selected OTP sender — device:ID (Unofficial) | waba:ID | twilio:ID.');
        // template_id: keep the existing one when the form sends 0 (a local
        // Unofficial/Twilio template isn't in the dropdown, so a plain save must
        // not wipe it). The "Create & submit" button is what sets it.
        $otpTplId = $request->integer('registration_otp_template_id');
        if ($otpTplId > 0) {
            \App\Models\SystemSetting::set('registration_otp_template_id', $otpTplId, 'int', 'OTP message template.');
        }
        // Numeric knobs clamped to sane ranges so an empty field never saves 0
        // (that was the "code validity keeps going to 0" bug).
        $otpTtl = (int) $request->integer('registration_otp_ttl_minutes', 5);
        \App\Models\SystemSetting::set('registration_otp_ttl_minutes', $otpTtl >= 1 ? min(90, $otpTtl) : 5, 'int', 'Registration OTP validity in minutes.');
        $otpLen = (int) $request->integer('registration_otp_length', 6);
        \App\Models\SystemSetting::set('registration_otp_length', $otpLen >= 4 ? min(8, $otpLen) : 6, 'int', 'Registration OTP code length.');
        $otpCd = (int) $request->integer('registration_otp_resend_cooldown_sec', 60);
        \App\Models\SystemSetting::set('registration_otp_resend_cooldown_sec', $otpCd >= 15 ? min(600, $otpCd) : 60, 'int', 'Seconds before a registration OTP can be resent.');
        $otpMa = (int) $request->integer('registration_otp_max_attempts', 5);
        \App\Models\SystemSetting::set('registration_otp_max_attempts', $otpMa >= 1 ? min(20, $otpMa) : 5, 'int', 'Max wrong attempts before a new registration OTP is required.');

        \Illuminate\Support\Facades\Log::info('[WADESK-MSG] providers.update SAVED', [
            'enable_batches' => (bool) \App\Models\SystemSetting::get('enable_batches', false),
            'msg_gap'        => (int)  \App\Models\SystemSetting::get('msg_gap', 3),
            'node_token_set' => node_token() !== '',
        ]);

        // Push the new pacing + provider settings to the Node bridge NOW
        // instead of waiting up to an hour for its auto-refresh timer.
        // refreshNodeSettings() reloads Node's global app.locals.messageSettings
        // (msg_gap/batches_gap/bw_msg_gap) + whatsappSettings; bustAll() flushes
        // the per-phone WABA settings cache since provider creds may have
        // changed. Both are best-effort and never block the save.
        \App\Services\NodeCacheBuster::refreshNodeSettings();
        \App\Services\NodeCacheBuster::bustAll();

        return redirect()->route('admin.settings.wadesk-message')->with('status', 'Provider settings saved.');
    }

    /**
     * Coexistence webhook self-check.
     *
     * The client-reported "phone-typed replies don't reach the inbox" bug is
     * almost always a MISSING webhook FIELD subscription on the Meta App, not a
     * code fault — our capture path (WaWebhookController::captureOutboundEcho)
     * only runs once Meta actually delivers `smb_message_echoes`. This button
     * asks Meta directly which fields the platform App is subscribed to, so an
     * admin can confirm the setup instead of guessing.
     *
     * Uses the App access token ({app_id}|{app_secret}) to read
     * GET /{app_id}/subscriptions — the App-level field list for the
     * whatsapp_business_account object. Read-only; changes nothing.
     */
    public function checkCoexistenceWebhooks()
    {
        return back()->with('coex_check', $this->coexSubscriptionStatus());
    }

    /**
     * Subscribe the three coexistence fields on the Meta App now.
     *
     * POST /{app_id}/subscriptions with the UNION of what's already subscribed
     * plus messages + smb_message_echoes + smb_app_state_sync + history — so we
     * never drop an existing field. Meta re-runs the verification handshake
     * against our callback the moment we subscribe, so we pass the stored
     * `waba_webhook_verify_token` (which WaWebhookController::verify checks);
     * if it's somehow blank we mint + persist one first so the handshake can't
     * fail. Read-then-write, then re-check so the UI shows the fresh state.
     */
    public function subscribeCoexistenceWebhooks()
    {
        $appId     = trim((string) \App\Models\SystemSetting::get('waba_app_id', ''));
        $appSecret = trim((string) \App\Models\SystemSetting::get('waba_app_secret', ''));
        $version   = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');

        if ($appId === '' || $appSecret === '') {
            return back()->with('coex_check', [
                'ok'    => false,
                'error' => 'Set the WhatsApp App ID and App Secret above, click Save, then subscribe.',
            ]);
        }

        // Read current state first — to keep existing fields and reuse the
        // callback Meta already has on file.
        $current = $this->coexSubscriptionStatus();
        if (!empty($current['error']) && empty($current['all_fields'])) {
            // Couldn't even read (bad creds / no webhook product) — surface it
            // rather than blindly POSTing.
            return back()->with('coex_check', $current);
        }

        // Verify token Meta will echo during the handshake. Must equal what
        // WaWebhookController::verify() compares. Auto-provision if missing.
        $verifyToken = trim((string) \App\Models\SystemSetting::get('waba_webhook_verify_token', ''));
        if ($verifyToken === '') {
            $verifyToken = \Illuminate\Support\Str::random(24);
            \App\Models\SystemSetting::set('waba_webhook_verify_token', $verifyToken, 'string', 'Meta webhook verification token (handshake).');
        }

        $callback = (string) ($current['callback_url'] ?? '');
        if ($callback === '') $callback = url('/webhooks/whatsapp/inbound');

        // Union: keep everything already subscribed + add the coexistence set.
        $desired = array_values(array_unique(array_merge(
            $current['all_fields'] ?? [],
            ['messages', 'smb_message_echoes', 'smb_app_state_sync', 'history'],
        )));

        try {
            $res = \Illuminate\Support\Facades\Http::asForm()->acceptJson()->timeout(20)
                ->post("https://graph.facebook.com/{$version}/{$appId}/subscriptions", [
                    'object'       => 'whatsapp_business_account',
                    'callback_url' => $callback,
                    'fields'       => implode(',', $desired),
                    'verify_token' => $verifyToken,
                    'access_token' => $appId . '|' . $appSecret,
                ]);

            if (!$res->ok() || !($res->json('success') ?? false)) {
                $metaMsg = (string) ($res->json('error.message') ?? $res->body());
                $status  = $this->coexSubscriptionStatus();
                $status['error'] = 'Meta rejected the subscribe: ' . mb_substr($metaMsg, 0, 300)
                    . ' (common causes: callback URL not reachable, or the verify token above does not match).';
                $status['ok'] = false;
                return back()->with('coex_check', $status);
            }
        } catch (\Throwable $e) {
            return back()->with('coex_check', [
                'ok'    => false,
                'error' => 'Could not reach Meta to subscribe: ' . mb_substr($e->getMessage(), 0, 200),
            ]);
        }

        // Re-read so the operator sees the now-green fields immediately.
        $after = $this->coexSubscriptionStatus();
        if ($after['ok'] ?? false) {
            $after['subscribed_now'] = true;
        }
        return back()->with('coex_check', $after);
    }

    /**
     * Query Meta for the App-level webhook field subscription and shape the
     * result the blade renders. Shared by check + subscribe. Read-only.
     * Returns: ok, error?, callback_url, active, present{field:bool}, missing[],
     * all_fields[].
     */
    private function coexSubscriptionStatus(): array
    {
        $appId     = trim((string) \App\Models\SystemSetting::get('waba_app_id', ''));
        $appSecret = trim((string) \App\Models\SystemSetting::get('waba_app_secret', '')); // auto-decrypted
        $version   = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');

        // 'messages' first (base), then the three coexistence fields.
        $required = ['messages', 'smb_message_echoes', 'smb_app_state_sync', 'history'];

        if ($appId === '' || $appSecret === '') {
            return ['ok' => false, 'error' => 'Set the WhatsApp App ID and App Secret above, click Save, then check again.'];
        }

        try {
            $res = \Illuminate\Support\Facades\Http::acceptJson()->timeout(15)
                ->get("https://graph.facebook.com/{$version}/{$appId}/subscriptions", [
                    'access_token' => $appId . '|' . $appSecret,
                ]);

            if (!$res->ok()) {
                $metaMsg = (string) ($res->json('error.message') ?? $res->body());
                return ['ok' => false, 'error' => 'Meta rejected the check: ' . mb_substr($metaMsg, 0, 300)
                    . ' (verify the App ID + App Secret are correct).'];
            }

            // Find the whatsapp_business_account object and read its subscribed
            // field names. Meta shapes fields as {name:'...'} objects or (older)
            // plain strings — handle both.
            $wabaSub = collect($res->json('data') ?? [])->firstWhere('object', 'whatsapp_business_account');
            if (!$wabaSub) {
                return ['ok' => false, 'error' => 'This Meta App has NO WhatsApp Business Account webhook configured at all. In Meta App → Webhooks, add the WhatsApp Business Account product and set the callback URL first.'];
            }

            $fieldNames = collect($wabaSub['fields'] ?? [])
                ->map(fn ($f) => is_array($f) ? ($f['name'] ?? '') : (string) $f)
                ->filter()->values()->all();

            $present = [];
            foreach ($required as $f) {
                $present[$f] = in_array($f, $fieldNames, true);
            }
            $missing = array_keys(array_filter($present, fn ($v) => !$v));

            return [
                'ok'           => count($missing) === 0,
                'callback_url' => (string) ($wabaSub['callback_url'] ?? ''),
                'active'       => (bool) ($wabaSub['active'] ?? false),
                'present'      => $present,
                'missing'      => $missing,
                'all_fields'   => $fieldNames,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Could not reach Meta: ' . mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * Quick "Update timing" save — persists ONLY the sender-pacing fields
     * (msg_gap / batches_gap / bw_msg_gap / enable_batches) and pushes them to
     * the Node bridge immediately, WITHOUT re-submitting the whole providers
     * form. Returns JSON so the admin page updates inline. Same SystemSetting
     * keys + Node refresh as settingsProvidersUpdate(), just scoped to pacing.
     */
    public function settingsPacingUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'msg_gap'        => 'required|integer|min:1|max:600',
            'batches_gap'    => 'required|integer|min:1|max:10000',
            'bw_msg_gap'     => 'required|integer|min:1|max:1440',
            'enable_batches' => 'sometimes|boolean',
        ]);

        \App\Models\SystemSetting::set('msg_gap',     (int) $data['msg_gap'],     'int', 'Seconds between consecutive outbound sends (Node bridge).');
        \App\Models\SystemSetting::set('batches_gap', (int) $data['batches_gap'], 'int', 'Recipients per send batch (Node bridge).');
        \App\Models\SystemSetting::set('bw_msg_gap',  (int) $data['bw_msg_gap'],  'int', 'Minutes between batches (Node bridge).');
        \App\Models\SystemSetting::set('enable_batches', $request->boolean('enable_batches'), 'bool', 'Whether the Node bridge breaks large sends into batches.');

        \Illuminate\Support\Facades\Log::info('[WADESK-MSG] pacing quick-save', [
            'msg_gap'        => (int) $data['msg_gap'],
            'batches_gap'    => (int) $data['batches_gap'],
            'bw_msg_gap'     => (int) $data['bw_msg_gap'],
            'enable_batches' => $request->boolean('enable_batches'),
            'admin_id'       => optional($request->user())->id,
        ]);

        // Push to the Node bridge NOW so the new gap takes effect on the next
        // send without waiting for the bridge's hourly auto-refresh. The call
        // returns what the bridge NOW actually holds (or null if unreachable),
        // so the admin can confirm the value landed instead of guessing.
        $bridge = \App\Services\NodeCacheBuster::refreshNodeSettings();

        return response()->json([
            'ok'      => true,
            'message' => 'Sending timing updated and pushed to the bridge.',
            'values'  => [
                'msg_gap'        => (int) $data['msg_gap'],
                'batches_gap'    => (int) $data['batches_gap'],
                'bw_msg_gap'     => (int) $data['bw_msg_gap'],
                'enable_batches' => $request->boolean('enable_batches'),
            ],
            // What the Node bridge reports holding after the push. null = the
            // bridge is unreachable (wrong baileys_server_url / token / offline).
            'bridge'  => $bridge ? [
                'msg_gap'        => $bridge['msg_gap']        ?? null,
                'batches_gap'    => $bridge['batches_gap']    ?? null,
                'bw_msg_gap'     => $bridge['bw_msg_gap']     ?? null,
                'enable_batches' => $bridge['enable_batches'] ?? null,
            ] : null,
        ]);
    }

    public function settingsAffiliateUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'referral_signup_credits'    => 'required|integer|min:0|max:1000000',
            'credits_per_message'        => 'required|integer|min:1|max:1000',
            'credits_per_currency_minor' => 'nullable|numeric|min:0|max:100000',
        ]);
        \App\Models\SystemSetting::set('referral_signup_credits',    $data['referral_signup_credits'],    'int',  'Credits awarded to the referrer when their referee signs up.');
        \App\Models\SystemSetting::set('credits_per_message',        $data['credits_per_message'],        'int',  'Credits charged per outbound message.');
        // Top-up conversion rate — only overwrite when a value is provided so a
        // blank submit never zeroes the rate.
        if ($request->filled('credits_per_currency_minor')) {
            \App\Models\SystemSetting::set('credits_per_currency_minor', (float) $data['credits_per_currency_minor'], 'float', 'Credits granted per major unit of currency at top-up.');
        }
        return redirect()->route('admin.settings.wallet-rules')->with('status', 'Wallet rules saved.');
    }

    public function settingWalletRules(): \Illuminate\Contracts\View\View
    {
        $settings = [
            'referral_signup_credits'    => (int)   \App\Models\SystemSetting::get('referral_signup_credits', 100),
            'credits_per_message'        => (int)   \App\Models\SystemSetting::get('credits_per_message', 1),
            'credits_per_currency_minor' => (float) \App\Models\SystemSetting::get('credits_per_currency_minor', 0.1),
        ];
        // Per-country × category credit rates (fair pricing).
        $perCountryEnabled = (string) \App\Models\SystemSetting::get('per_country_credits_enabled', '0') === '1';
        $messageRates = \App\Models\MessageRate::query()
            ->orderByRaw("country_code = '' desc")  // default rows first
            ->orderBy('country_code')->orderBy('category')->get();
        return view('admin.settings.wallet-rules', compact('settings', 'perCountryEnabled', 'messageRates'));
    }

    /**
     * Save per-country credit pricing: the master toggle, an upsert/delete of
     * the country × category rate grid, and an optional one-click re-seed.
     * Flushes the resolver cache. See MessageCreditRate / OverflowBilling.
     */
    public function messageRatesUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        \App\Models\SystemSetting::set('per_country_credits_enabled', $request->boolean('per_country_credits_enabled') ? '1' : '0');

        if ($request->boolean('seed')) {
            $starter = [
                ['', '', 1], ['', 'service', 0], ['', 'utility', 1], ['', 'authentication', 1],
                ['IN', 'marketing', 1], ['US', 'marketing', 12], ['GB', 'marketing', 12],
                ['BR', 'marketing', 6], ['AE', 'marketing', 5], ['MX', 'marketing', 3],
            ];
            foreach ($starter as [$cc, $cat, $cr]) {
                \App\Models\MessageRate::updateOrCreate(
                    ['country_code' => $cc, 'category' => $cat],
                    ['credits' => $cr, 'is_active' => true],
                );
            }
        }

        foreach ((array) $request->input('delete', []) as $delId) {
            \App\Models\MessageRate::where('id', (int) $delId)->delete();
        }

        // Upsert the grid (parallel arrays country_code[]/category[]/credits[]).
        $cc  = (array) $request->input('country_code', []);
        $cat = (array) $request->input('category', []);
        $cr  = (array) $request->input('credits', []);
        foreach ($cc as $i => $country) {
            $country  = strtoupper(trim((string) $country));
            $category = strtolower(trim((string) ($cat[$i] ?? '')));
            $credits  = $cr[$i] ?? null;
            if ($credits === null || $credits === '') continue;
            if ($category !== '' && !in_array($category, \App\Models\MessageRate::CATEGORIES, true)) continue;
            if ($country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) continue;
            \App\Models\MessageRate::updateOrCreate(
                ['country_code' => $country, 'category' => $category],
                ['credits' => max(0, (int) $credits), 'is_active' => true],
            );
        }

        \App\Services\MessageCreditRate::forget();
        return back()->with('status', __('Per-country credit rates saved.'));
    }

    /**
     * Themes that get their own logo upload slot. Keep this in sync with
     * resources/js/wadesk.js THEMES array — adding a theme there means
     * adding it here too. The id is what `[data-theme="<id>"]` uses on
     * the html element; falsy default ("paper") means no attribute set.
     */
    public const BRAND_THEMES = [
        ['id' => 'paper',  'label' => 'Paper (default)', 'note' => 'Light, off-white. The default look.'],
        ['id' => 'bright', 'label' => 'Bright white',    'note' => 'Higher-contrast white background.'],
        ['id' => 'dark',   'label' => 'Dark (beta)',     'note' => 'Use a light/inverted version of your logo.'],
        ['id' => 'doodle', 'label' => 'Doodle (fancy)',  'note' => 'Pastel mint background.'],
    ];

    public function settingGeneral(): View
    {
        $settings = [
            'app_name'        => (string) \App\Models\SystemSetting::get('app_name',        config('app.name', 'WaDesk')),
            'app_url'         => (string) \App\Models\SystemSetting::get('app_url',         config('app.url', '')),
            'support_email'   => (string) \App\Models\SystemSetting::get('support_email',   ''),
            'contact_number'  => (string) \App\Models\SystemSetting::get('contact_number',  ''),
            'from_email'      => (string) \App\Models\SystemSetting::get('from_email',      ''),
            'default_timezone'=> (string) \App\Models\SystemSetting::get('default_timezone',config('app.timezone', 'Asia/Kolkata')),
            'default_language'=> (string) \App\Models\SystemSetting::get('default_language',config('app.locale', 'en')),
            'default_currency'=> (string) \App\Models\SystemSetting::get('default_currency','USD'),
            // Platform-wide default country for every phone-input picker.
            // Read by app_default_country() → meta tags → intl-tel-input JS,
            // so flipping this once switches every blade + JS picker default.
            'default_country_code'=> (string) \App\Models\SystemSetting::get('default_country_code','+91'),
            'default_country_iso' => (string) \App\Models\SystemSetting::get('default_country_iso','in'),
            'font_family'     => (string) \App\Models\SystemSetting::get('font_family',     ''),
            'address'         => (string) \App\Models\SystemSetting::get('address',         ''),
            'map_iframe_url'  => (string) \App\Models\SystemSetting::get('map_iframe_url',  ''),
            'preloader'       => (bool)   \App\Models\SystemSetting::get('preloader',       true),
            'maintenance_mode'=> (bool)   \App\Models\SystemSetting::get('maintenance_mode',false),
            'public_registration' => (bool) \App\Models\SystemSetting::get('public_registration', true),
            // When ON, new accounts are stamped `email_verified_at = now()`
            // at signup and skip the verify-email screen entirely. Useful
            // for dev installs without SMTP and for invite-only deployments
            // where the admin has already vetted operators out of band.
            'auto_verify_email'   => (bool) \App\Models\SystemSetting::get('auto_verify_email', true),
            // Plan assigned to a workspace the moment its owner finishes
            // signup. Empty = fall back to the first active free plan.
            // When the chosen plan is FREE, the workspace gets a trial
            // window of `registration_trial_days` days.
            'registration_default_plan_id' => (string) \App\Models\SystemSetting::get('registration_default_plan_id', ''),
            'registration_trial_days'      => (int)    \App\Models\SystemSetting::get('registration_trial_days', 14),
            // How plans apply across an owner's workspaces:
            //   workspace = each workspace billed separately (default)
            //   account   = owner's paid plan unlocks all their workspaces
            'billing_plan_scope'           => (string) \App\Models\SystemSetting::get('billing_plan_scope', 'workspace'),
            // Brand assets — single favicon shared across themes, one logo
            // per theme so dark mode can have an inverted variant etc.
            'brand_favicon'   => (string) \App\Models\SystemSetting::get('brand.favicon', ''),
            // Platform-fixed message footer (applies to workspaces without
            // remove_branding). Workspaces with that plan flag can override
            // their own from /account → Branding.
            'platform_branding_footer' => (string) \App\Models\SystemSetting::get('platform_branding_footer', ''),
        ];
        foreach (self::BRAND_THEMES as $t) {
            $settings['brand_logo_' . $t['id']] = (string) \App\Models\SystemSetting::get('brand.logo.' . $t['id'], '');
        }
        $languages  = \App\Models\Language::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $currencies = \App\Models\Currency::where('is_active', true)->orderBy('code')->get();
        $brandThemes = self::BRAND_THEMES;
        // Active plans for the "default signup plan" picker. isFreePlan()
        // drives the (free → trial) hint shown next to each option.
        $signupPlans = \App\Models\Package::query()->plans()->where('status', 1)
            ->orderBy('sort_order')->orderBy('pname')->get();
        return view('admin.settings.general', compact('settings', 'languages', 'currencies', 'brandThemes', 'signupPlans'));
    }

    public function settingGeneralUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $rules = [
            'app_name'            => 'required|string|max:80',
            'app_url'             => 'nullable|url|max:191',
            'support_email'       => 'nullable|email|max:160',
            'contact_number'      => 'nullable|string|max:40',
            'from_email'          => 'nullable|email|max:160',
            'default_timezone'    => 'nullable|string|max:64',
            'default_language'    => 'nullable|string|max:12',
            'default_currency'    => 'nullable|string|max:8',
            // Default country for phone-input pickers across the platform.
            // code is the dial code ("+62"); iso is the 2-letter ISO ("id").
            'default_country_code'=> 'nullable|string|max:6',
            'default_country_iso' => 'nullable|string|size:2',
            // UI font family (key from app_font_catalog(); '' = theme default).
            'font_family'         => 'nullable|string|max:40',
            'address'             => 'nullable|string|max:500',
            'map_iframe_url'      => 'nullable|string|max:1000',
            'preloader'           => 'sometimes|boolean',
            'maintenance_mode'    => 'sometimes|boolean',
            'public_registration' => 'sometimes|boolean',
            'auto_verify_email'   => 'sometimes|boolean',
            // Default plan for new signups (plan_id slug) + free-trial length.
            'registration_default_plan_id' => 'nullable|string|max:64|exists:packages,plan_id',
            'registration_trial_days'      => 'nullable|integer|min:0|max:365',
            'billing_plan_scope'           => 'nullable|in:workspace,account',
            // Brand assets — favicon is shared. Per-theme logo files key on
            // the theme id (paper / bright / dark / doodle / future).
            'favicon'             => 'nullable|file|mimes:png,ico,jpg,jpeg,svg,webp|max:512',
            'logos'               => 'nullable|array',
            'logos.*'             => 'nullable|file|mimes:png,jpg,jpeg,svg,webp|max:1024',
            // Platform-fixed outbound message footer — string used when a
            // workspace's plan doesn't grant remove_branding.
            'platform_branding_footer' => 'nullable|string|max:60',
        ];
        $data = $request->validate($rules);

        // Persist scalar fields first (excluding files).
        $scalar = \Illuminate\Support\Arr::except($data, ['favicon', 'logos']);
        foreach ($scalar as $key => $value) {
            $type = match (true) {
                is_bool($value)  => 'bool',
                is_int($value)   => 'int',
                default          => 'string',
            };
            \App\Models\SystemSetting::set($key, $value, $type, 'General settings: ' . $key);
        }

        // Favicon upload — single shared file.
        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('brand', 'public');
            \App\Models\SystemSetting::set('brand.favicon', $path, 'string', 'Brand favicon (shared across themes)');
        }

        // Per-theme logo uploads. `logos[<theme-id>]` from the form.
        if ($request->hasFile('logos')) {
            foreach ($request->file('logos') as $themeId => $file) {
                $themeId = preg_replace('/[^a-z0-9_-]/i', '', (string) $themeId);
                if ($themeId === '' || !$file) continue;
                $path = $file->store('brand', 'public');
                \App\Models\SystemSetting::set(
                    'brand.logo.' . $themeId,
                    $path,
                    'string',
                    'Logo for theme: ' . $themeId
                );
            }
        }

        \App\Support\Audit::log('admin.settings.general_updated', [
            'layer' => 'platform',
            'meta'  => [
                'fields'     => array_keys($scalar),
                'favicon'    => $request->hasFile('favicon'),
                'logos'      => $request->hasFile('logos') ? array_keys($request->file('logos')) : [],
            ],
        ]);
        // Platform footer might have changed → flush Node's entire
        // settings cache so every workspace picks up the new default.
        if (array_key_exists('platform_branding_footer', $scalar)) {
            \App\Services\NodeCacheBuster::bustAll();
        }
        return redirect()->route('admin.settings.general')->with('status', 'General settings saved.');
    }
    public function settingWaDeskMessage(): View
    {
        // Provider toggles + per-provider creds live here. Admin picks
        // single or multiple methods workspaces are allowed to connect
        // with — workspaces then see only the enabled tabs in the
        // /devices add-device modal.
        $allowed = \App\Models\SystemSetting::get('allowed_send_methods', ['waba', 'baileys', 'twilio']);
        $allowed = is_array($allowed) ? $allowed : ['waba', 'baileys', 'twilio'];

        $settings = [
            'default_send_method'        => (string) \App\Models\SystemSetting::get('default_send_method', 'baileys'),
            'allowed_send_methods'       => $allowed,
            'waba_app_id'                => (string) \App\Models\SystemSetting::get('waba_app_id', ''),
            'waba_app_secret_set'        => \App\Models\SystemSetting::where('key', 'waba_app_secret')->exists(),
            'waba_config_id'             => (string) \App\Models\SystemSetting::get('waba_config_id', ''),
            'waba_coexistence'           => (bool) \App\Models\SystemSetting::get('waba_coexistence', false),
            'waba_webhook_verify_token'  => (string) \App\Models\SystemSetting::get('waba_webhook_verify_token', ''),
            'baileys_server_url'         => (string) \App\Models\SystemSetting::get('baileys_server_url', env('SERVER_URL', '')),
            'twilio_account_sid'         => (string) \App\Models\SystemSetting::get('twilio_account_sid', ''),
            'twilio_auth_token_set'      => \App\Models\SystemSetting::where('key', 'twilio_auth_token')->exists(),
            'twilio_whatsapp_number'     => (string) \App\Models\SystemSetting::get('twilio_whatsapp_number', ''),
            // Shared Node-bridge secret (X-Node-Token). Stored encrypted — only
            // expose whether it's set so the field shows a masked placeholder.
            'node_webhook_token_set'     => node_token() !== '',
            // WABA dispatcher + templates v2 flags the blade renders.
            'waba_dispatch_v2_enabled'   => (bool)   \App\Models\SystemSetting::get('waba_dispatch_v2_enabled', false),
            'waba_graph_api_version'     => (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0'),
            'waba_connected_count'       => (int)    \DB::table('wa_provider_configs')->where('provider', 'waba')->where('status', 'connected')->count(),
            'waba_templates_v2_enabled'  => (bool)   \App\Models\SystemSetting::get('waba_templates_v2_enabled', false),
            'waba_template_polling_min'  => (int)    \App\Models\SystemSetting::get('waba_template_polling_min', 30),
            'waba_template_lint_strict'  => (bool)   \App\Models\SystemSetting::get('waba_template_lint_strict', true),
            // Sender pacing — WITHOUT these the blade read undefined keys, so the
            // Enable-batches toggle always rendered OFF and the gap fields showed
            // defaults even after a successful save. This is the real cause of
            // "toggle won't save": the save worked, the read-back was missing.
            'msg_gap'                    => (int)    \App\Models\SystemSetting::get('msg_gap', 3),
            'batches_gap'                => (int)    \App\Models\SystemSetting::get('batches_gap', 50),
            'bw_msg_gap'                 => (int)    \App\Models\SystemSetting::get('bw_msg_gap', 5),
            'enable_batches'             => (bool)   \App\Models\SystemSetting::get('enable_batches', false),
            // Campaign auto-end (expiry) — default ON, 24h from first send.
            'campaign_auto_expiry_enabled'  => (bool) \App\Models\SystemSetting::get('campaign_auto_expiry_enabled', true),
            'campaign_default_expiry_hours' => (int)  \App\Models\SystemSetting::get('campaign_default_expiry_hours', 24),
            // Registration WhatsApp OTP verification.
            'registration_otp_enabled'       => (bool) \App\Models\SystemSetting::get('registration_otp_enabled', false),
            'registration_otp_device_id'     => (int)  \App\Models\SystemSetting::get('registration_otp_device_id', 0),
            'registration_otp_template_id'   => (int)  \App\Models\SystemSetting::get('registration_otp_template_id', 0),
            'registration_otp_ttl_minutes'   => ((int) \App\Models\SystemSetting::get('registration_otp_ttl_minutes', 5)) ?: 5,
            'registration_otp_length'        => (int)  \App\Models\SystemSetting::get('registration_otp_length', 6),
            'registration_otp_resend_cooldown_sec' => (int) \App\Models\SystemSetting::get('registration_otp_resend_cooldown_sec', 60),
            'registration_otp_max_attempts'  => (int)  \App\Models\SystemSetting::get('registration_otp_max_attempts', 5),
        ];

        // OTP sender dropdown — a composite of every CONNECTED channel. Device
        // rows ARE the Unofficial (Baileys) channels; WABA/Twilio are
        // wa_provider_configs rows. Values are "device:ID" / "waba:ID" /
        // "twilio:ID" — parsed by RegistrationOtpService. Encrypted phone fields
        // decrypt on attribute read, not in SQL.
        $otpSenders = collect();
        \App\Models\Device::query()->where('status', 'connected')->orderByDesc('id')->limit(200)->get()
            ->each(function ($d) use ($otpSenders) {
                $phone = trim((string) ($d->country_code ? '+' . ltrim((string) $d->country_code, '+') . ' ' : '')
                    . preg_replace('/\D+/', '', (string) $d->phone_number)) ?: ('Device #' . $d->id);
                $otpSenders->push(['value' => 'device:' . $d->id, 'label' => $phone . ' (Unofficial)']);
            });
        \App\Models\WaProviderConfig::query()->whereIn('provider', ['waba', 'twilio'])
            ->where('status', 'connected')->orderByDesc('id')->limit(200)->get()
            ->each(function ($c) use ($otpSenders) {
                $creds = $c->creds();
                $num = $c->provider === 'twilio'
                    ? (string) ($creds['from_number'] ?? '')
                    : (string) (($c->meta_json['phone_number'] ?? '') ?: ($creds['phone_number_id'] ?? ''));
                $eng = $c->provider === 'waba' ? 'WABA' : 'Twilio';
                $otpSenders->push([
                    'value' => $c->provider . ':' . $c->id,
                    'label' => ($num !== '' ? $num . ' ' : '') . '(' . $eng . ')',
                ]);
            });
        $otpSenders = $otpSenders->values();

        // Currently-selected sender (with legacy device_id fallback).
        $settings['registration_otp_sender'] = (string) \App\Models\SystemSetting::get('registration_otp_sender', '');
        if ($settings['registration_otp_sender'] === '' && $settings['registration_otp_device_id'] > 0) {
            $settings['registration_otp_sender'] = 'device:' . $settings['registration_otp_device_id'];
        }

        // Approved authentication-category templates for the OTP template picker.
        // meta_status is queryable; category may be encrypted so filter in PHP.
        $otpTemplates = \App\Models\WaTemplate::query()
            ->where('meta_status', 'APPROVED')->orderByDesc('id')->limit(300)->get()
            ->filter(fn ($t) => strtolower((string) $t->category) === 'authentication')
            ->map(fn ($t) => ['id' => $t->id, 'label' => (string) $t->template_name])
            ->values();

        // Always surface the CURRENTLY-selected OTP template even if it isn't an
        // approved auth template — e.g. the local Unofficial/Twilio "utility"
        // template the Create button makes. Without this it never showed up and a
        // plain Save would wipe it (dropdown value 0).
        $curTplId = (int) $settings['registration_otp_template_id'];
        if ($curTplId > 0 && !$otpTemplates->contains(fn ($t) => $t['id'] === $curTplId)) {
            $cur = \App\Models\WaTemplate::find($curTplId);
            if ($cur) {
                $st = strtoupper((string) ($cur->meta_status ?: 'PENDING'));
                $otpTemplates->prepend(['id' => $cur->id, 'label' => (string) $cur->template_name . ' (' . $st . ')']);
            }
        }
        $otpTemplates = $otpTemplates->values();

        return view('admin.settings.wadesk-message', compact('settings', 'otpSenders', 'otpTemplates'));
    }

    /**
     * One-click: build the registration-OTP authentication template in code and
     * submit it to Meta for approval against the selected WABA device — the same
     * submit pipeline the workspace template screen uses. The admin never has to
     * hand-craft it. Once Meta approves (polled by the existing template sync),
     * the RegistrationOtpService activates automatically.
     */
    public function createOtpTemplate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        // The sender rides along with the form ("device:ID" / "waba:ID" /
        // "twilio:ID"). WABA is submitted to Meta for approval; Unofficial and
        // Twilio need NO Meta approval, so their template is created directly
        // APPROVED and used as the OTP message body right away.
        $sender = trim((string) ($request->input('registration_otp_sender')
            ?: \App\Models\SystemSetting::get('registration_otp_sender', '')));
        [$kind, $idRaw] = array_pad(explode(':', $sender, 2), 2, '');
        $id = (int) $idRaw;
        if ($id <= 0 || !in_array($kind, ['device', 'waba', 'twilio'], true)) {
            return back()->with('error', 'Pick a sender channel first, then create the template.');
        }

        // The button posts the WHOLE form (formaction), so persist the OTP knobs
        // here too — one click saves them AND creates the template. Clamped so an
        // empty "Code validity" field never lands as 0.
        $ttl = (int) $request->integer('registration_otp_ttl_minutes', 5);
        $ttl = $ttl >= 1 ? min(90, $ttl) : 5;
        \App\Models\SystemSetting::set('registration_otp_enabled', $request->boolean('registration_otp_enabled'), 'bool', 'Verify the mobile number with a WhatsApp OTP during signup.');
        \App\Models\SystemSetting::set('registration_otp_ttl_minutes', $ttl, 'int', 'Registration OTP validity in minutes.');
        $otpLen = (int) $request->integer('registration_otp_length', 6);
        \App\Models\SystemSetting::set('registration_otp_length', $otpLen >= 4 ? min(8, $otpLen) : 6, 'int', 'Registration OTP code length.');
        $otpCd = (int) $request->integer('registration_otp_resend_cooldown_sec', 60);
        \App\Models\SystemSetting::set('registration_otp_resend_cooldown_sec', $otpCd >= 15 ? min(600, $otpCd) : 60, 'int', 'Seconds before a registration OTP can be resent.');
        $otpMa = (int) $request->integer('registration_otp_max_attempts', 5);
        \App\Models\SystemSetting::set('registration_otp_max_attempts', $otpMa >= 1 ? min(20, $otpMa) : 5, 'int', 'Max wrong attempts before a new registration OTP is required.');

        $name = 'wadesk_registration_otp';

        // --- Unofficial (Baileys) + Twilio: local, directly-approved template ---
        if ($kind !== 'waba') {
            $wsId = null;
            if ($kind === 'device') {
                $wsId = \App\Models\Device::find($id)?->workspace_id;
            } else {
                $wsId = \App\Models\WaProviderConfig::query()->where('id', $id)->where('provider', 'twilio')->value('workspace_id');
            }
            $fields = [
                'category'      => 'utility',
                'meta_category' => 'UTILITY',
                'template_type' => 'standard',
                'template_body' => '{{1}} is your verification code. It expires in ' . $ttl . ' minutes.',
                'language'      => 'en_US',
                'meta_status'   => 'APPROVED',   // no Meta review for these engines
                'status'        => 'approved',
            ];
            $tpl = \App\Models\WaTemplate::query()->where('workspace_id', $wsId)
                ->get()->first(fn ($t) => (string) $t->template_name === $name . '_local');
            $tpl ? $tpl->update($fields)
                 : $tpl = \App\Models\WaTemplate::create($fields + ['workspace_id' => $wsId, 'template_name' => $name . '_local']);

            \App\Models\SystemSetting::set('registration_otp_template_id', $tpl->id, 'int', 'OTP message template.');
            \App\Models\SystemSetting::set('registration_otp_sender', $kind . ':' . $id, 'string', 'Selected OTP sender — device:ID (Unofficial) | waba:ID | twilio:ID.');
            return back()->with('status', 'OTP message created and approved — no Meta review needed for this channel. It is ready to use now.');
        }

        // --- WABA: authentication template submitted to Meta for approval ---
        $cfg = \App\Models\WaProviderConfig::query()->where('id', $id)->where('provider', 'waba')->first();
        if (!$cfg) {
            return back()->with('error', 'That WABA sender was not found. Reconnect it and try again.');
        }
        $authFields = [
            'category'       => 'authentication',
            'meta_category'  => 'AUTHENTICATION',
            'template_type'  => 'auth',
            'template_body'  => '{{1}} is your verification code.',
            'buttons'        => [['type' => 'otp_copy', 'text' => 'Copy code']],
            'code_expiration_minutes' => $ttl,
            'language'       => 'en_US',
        ];
        $tpl = \App\Models\WaTemplate::query()->where('workspace_id', $cfg->workspace_id)
            ->get()->first(fn ($t) => (string) $t->template_name === $name);
        $tpl ? $tpl->update($authFields)
             : $tpl = \App\Models\WaTemplate::create($authFields + [
                 'workspace_id' => $cfg->workspace_id, 'template_name' => $name, 'status' => 'pending',
             ]);

        try {
            $client  = new \App\Services\Waba\TemplateClient($cfg);
            $builder = new \App\Services\Waba\TemplatePayloadBuilder();
            $result  = $client->submit($builder->build($tpl));
            if (($result['id'] ?? '') === '') {
                return back()->with('error', 'Meta accepted the request but created no template. Check the WABA connection on this device and try again.');
            }
            $tpl->update([
                'provider_config_id' => $cfg->id,
                'meta_template_id'   => $result['id'],
                'meta_status'        => $result['status'] ?: 'PENDING',
                'meta_category'      => $result['category'] ?: 'AUTHENTICATION',
                'submitted_at'       => now(),
                'last_synced_at'     => now(),
                'status'             => 'pending',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not submit the template to Meta: ' . $e->getMessage());
        }

        \App\Models\SystemSetting::set('registration_otp_template_id', $tpl->id, 'int', 'WABA auth template used for registration OTPs.');
        \App\Models\SystemSetting::set('registration_otp_sender', 'waba:' . $cfg->id, 'string', 'Selected OTP sender — device:ID (Unofficial) | waba:ID | twilio:ID.');

        return back()->with('status', 'OTP template created and submitted to Meta for approval. It activates automatically once approved (usually a few minutes).');
    }

    public function settingMessage(): View
    {
        $settings = [
            'message_twilio_enabled'   => (bool) \App\Models\SystemSetting::get('message_twilio_enabled', false),
            'message_whatsapp_enabled' => (bool) \App\Models\SystemSetting::get('message_whatsapp_enabled', false),
            'message_facebook_id'      => (string) \App\Models\SystemSetting::get('message_facebook_id', ''),
            'message_has_facebook_token' => \App\Models\SystemSetting::get('message_facebook_token', '') !== '',
            'message_whatsapp_api_version' => (string) \App\Models\SystemSetting::get('message_whatsapp_api_version', 'v23.0'),
            'message_whatsapp_business_id' => (string) \App\Models\SystemSetting::get('message_whatsapp_business_id', ''),
            'message_twilio_sid'       => (string) \App\Models\SystemSetting::get('message_twilio_sid', ''),
            'message_has_twilio_token' => \App\Models\SystemSetting::get('message_twilio_token', '') !== '',
            'message_twilio_sender'    => (string) \App\Models\SystemSetting::get('message_twilio_sender', ''),
            'message_twilio_webhook'   => (string) \App\Models\SystemSetting::get('message_twilio_webhook', url('/webhooks/twilio')),
        ];
        return view('admin.settings.message', compact('settings'));
    }

    public function settingMessageUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'message_twilio_enabled'       => 'nullable|boolean',
            'message_whatsapp_enabled'     => 'nullable|boolean',
            'message_facebook_id'          => 'nullable|string|max:191',
            'message_facebook_token'       => 'nullable|string|max:1024',
            'message_whatsapp_api_version' => 'nullable|string|max:12',
            'message_whatsapp_business_id' => 'nullable|string|max:191',
            'message_twilio_sid'           => 'nullable|string|max:191',
            'message_twilio_token'         => 'nullable|string|max:191',
            'message_twilio_sender'        => 'nullable|string|max:32',
            'message_twilio_webhook'       => 'nullable|url|max:300',
        ]);

        \App\Models\SystemSetting::set('message_twilio_enabled',   $request->boolean('message_twilio_enabled'), 'bool', 'Message provider: Twilio enabled');
        \App\Models\SystemSetting::set('message_whatsapp_enabled', $request->boolean('message_whatsapp_enabled'), 'bool', 'Message provider: WhatsApp Business API enabled');
        foreach (['message_facebook_id', 'message_whatsapp_api_version', 'message_whatsapp_business_id', 'message_twilio_sid', 'message_twilio_sender', 'message_twilio_webhook'] as $k) {
            \App\Models\SystemSetting::set($k, (string) ($data[$k] ?? ''), 'string', 'Message provider ' . str_replace('message_', '', $k));
        }
        // Secrets: only overwrite when a new value is actually typed (the form
        // shows a masked placeholder, not the stored value).
        foreach (['message_facebook_token', 'message_twilio_token'] as $secret) {
            if ($request->filled($secret)) {
                \App\Models\SystemSetting::set($secret, (string) $request->input($secret), 'string', 'Message provider ' . str_replace('message_', '', $secret));
            }
        }

        \App\Support\Audit::log('settings.message_update', ['meta' => ['fields' => array_keys($data)]]);
        return redirect()->route('admin.settings.message')->with('success', __('Message provider settings saved.'));
    }

    public function settingMail(): View
    {
        // Every field falls back to the legacy config()/.env value so a
        // fresh install (where /admin/settings/mail hasn't been saved
        // yet) still shows whatever the env was configured with.
        $mail = [
            'mailer'      => (string) \App\Models\SystemSetting::get('mail_mailer',      config('mail.default', 'smtp')),
            'host'        => (string) \App\Models\SystemSetting::get('mail_host',        config('mail.mailers.smtp.host', '')),
            'port'        => (string) \App\Models\SystemSetting::get('mail_port',        config('mail.mailers.smtp.port', '587')),
            'username'    => (string) \App\Models\SystemSetting::get('mail_username',    config('mail.mailers.smtp.username', '')),
            'encryption'  => (string) \App\Models\SystemSetting::get('mail_encryption',  config('mail.mailers.smtp.encryption', 'tls')),
            'from_name'   => (string) \App\Models\SystemSetting::get('mail_from_name',   config('mail.from.name', \App\Models\SystemSetting::get('app_name', 'WaDesk'))),
            'from_address'=> (string) \App\Models\SystemSetting::get('mail_from_address',config('mail.from.address', '')),
            // Password is encrypted at rest — show "(set)" placeholder
            // in the form, never the actual value.
            'password_set'=> (bool) \App\Models\SystemSetting::get('mail_password',      ''),
        ];
        return view('admin.settings.mail', compact('mail'));
    }

    public function settingMailUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer'       => 'nullable|in:smtp,sendmail,log,array,mailgun,ses,postmark,resend',
            'mail_host'         => 'nullable|string|max:200',
            'mail_port'         => 'nullable|integer|min:1|max:65535',
            'mail_username'     => 'nullable|string|max:200',
            'mail_password'     => 'nullable|string|max:500',
            'mail_encryption'   => 'nullable|in:tls,ssl,starttls,',
            'mail_from_name'    => 'nullable|string|max:120',
            'mail_from_address' => 'nullable|email|max:200',
        ]);

        foreach ($data as $key => $value) {
            // Skip password when blank — never overwrite a saved one
            // with empty just because the form didn't submit it.
            if ($key === 'mail_password') {
                if ($value === null || $value === '') continue;
                $value = \Illuminate\Support\Facades\Crypt::encryptString((string) $value);
            }
            \App\Models\SystemSetting::set($key, (string) ($value ?? ''), 'string', str_replace('_', ' ', $key));
        }
        \App\Support\Audit::log('settings.mail_update', ['meta' => ['fields' => array_keys($data)]]);

        // Re-apply config immediately so the redirect target reflects
        // the new values without a second request boot.
        \App\Support\MailConfig::apply();

        return redirect()->route('admin.settings.mail')->with('success', __('Mail settings saved.'));
    }

    /**
     * POST /admin/settings/mail/test — send a one-line test email to
     * the admin's address with the currently-saved SMTP creds. Useful
     * to verify host/port/credentials without leaving the page.
     */
    public function settingMailTest(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['to' => 'required|email|max:200']);
        \App\Support\MailConfig::apply();
        try {
            \Illuminate\Support\Facades\Mail::raw(
                __('This is a test email from :app to confirm your SMTP settings are working. Sent at :time.', [
                    'app'  => (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk')),
                    'time' => now()->toDateTimeString(),
                ]),
                function ($m) use ($data) {
                    $m->to($data['to'])->subject(__('Mail settings test'));
                }
            );
            \App\Support\Audit::log('settings.mail_test', ['meta' => ['to' => $data['to'], 'ok' => true]]);
            return back()->with('success', __('Test email sent to :to.', ['to' => $data['to']]));
        } catch (\Throwable $e) {
            \App\Support\Audit::log('settings.mail_test', ['meta' => ['to' => $data['to'], 'ok' => false, 'error' => $e->getMessage()]], 'failure');
            return back()->with('error', __('Test email failed: ') . $e->getMessage());
        }
    }
    public function settingIntegration(): View
    {
        // Reuses the SAME keys the dedicated Shopify/Woo pages own, so this
        // master-switch summary stays in sync with them (one source of truth).
        $settings = [
            'shopify_enabled'        => (bool) \App\Models\SystemSetting::get('shopify_enabled', false),
            'woocommerce_enabled'    => (bool) \App\Models\SystemSetting::get('woocommerce_enabled', false),
            'shopify_client_id'      => (string) \App\Models\SystemSetting::get('shopify_client_id', ''),
            'shopify_has_secret'     => \App\Models\SystemSetting::get('shopify_client_secret', '') !== '',
            'woocommerce_instructions' => (string) \App\Models\SystemSetting::get('woocommerce_instructions', ''),
        ];
        return view('admin.settings.integration', compact('settings'));
    }

    public function settingIntegrationUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'shopify_enabled'          => 'nullable|boolean',
            'woocommerce_enabled'      => 'nullable|boolean',
            'shopify_client_id'        => 'nullable|string|max:191',
            'shopify_client_secret'    => 'nullable|string|max:191',
            'woocommerce_instructions' => 'nullable|string|max:2000',
        ]);

        \App\Models\SystemSetting::set('shopify_enabled',     $request->boolean('shopify_enabled'), 'bool', 'Toggle Shopify OAuth integration');
        \App\Models\SystemSetting::set('woocommerce_enabled', $request->boolean('woocommerce_enabled'), 'bool', 'Toggle WooCommerce integration');
        \App\Models\SystemSetting::set('shopify_client_id',   (string) ($data['shopify_client_id'] ?? ''), 'string', 'Shopify app client ID');
        \App\Models\SystemSetting::set('woocommerce_instructions', (string) ($data['woocommerce_instructions'] ?? ''), 'string', 'WooCommerce connection instructions shown to workspaces');
        // Masked secret: only overwrite when a new value is typed.
        if ($request->filled('shopify_client_secret')) {
            \App\Models\SystemSetting::set('shopify_client_secret', (string) $request->input('shopify_client_secret'), 'string', 'Shopify app client secret');
        }

        \App\Support\Audit::log('settings.integration_update', ['meta' => ['fields' => array_keys($data)]]);
        return redirect()->route('admin.settings.integration')->with('success', __('Integration settings saved.'));
    }

    public function settingShopify(): View
    {
        return view('admin.settings.shopify', [
            'enabled'      => (bool) \App\Models\SystemSetting::get('shopify_enabled', false),
            'clientId'     => (string) \App\Models\SystemSetting::get('shopify_client_id', ''),
            // Never echo the secret back into the form HTML — only expose whether one is set.
            'hasSecret'    => \App\Models\SystemSetting::get('shopify_client_secret', '') !== '',
            'scopes'       => (string) \App\Models\SystemSetting::get('shopify_scopes', \App\Services\Shopify\ShopifyService::DEFAULT_SCOPES),
            'redirectUri'  => (string) (\App\Models\SystemSetting::get('shopify_redirect_uri') ?: url('/shopify/oauth/callback')),
            'integrationsCount' => \App\Models\ShopifyIntegration::count(),
            'eventsCount'       => \App\Models\ShopifyIntegrationEvent::count(),
            'logsCount'         => \App\Models\ShopifyIntegrationLog::count(),
        ]);
    }

    public function settingShopifyUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'shopify_enabled'       => 'nullable|in:0,1',
            'shopify_client_id'     => 'nullable|string|max:191',
            'shopify_client_secret' => 'nullable|string|max:191',
            'shopify_scopes'        => 'nullable|string|max:500',
            'shopify_redirect_uri'  => 'nullable|url|max:255',
        ]);

        \App\Models\SystemSetting::set('shopify_enabled',       $request->boolean('shopify_enabled'), 'bool', 'Toggle Shopify OAuth integration');
        \App\Models\SystemSetting::set('shopify_client_id',     (string) $request->input('shopify_client_id', ''), 'string', 'Shopify app client ID');
        // Leave the secret untouched when the field is submitted blank (it's masked in the form).
        if ($request->filled('shopify_client_secret')) {
            \App\Models\SystemSetting::set('shopify_client_secret', (string) $request->input('shopify_client_secret'), 'string', 'Shopify app client secret');
        }
        \App\Models\SystemSetting::set('shopify_scopes',        (string) $request->input('shopify_scopes', \App\Services\Shopify\ShopifyService::DEFAULT_SCOPES), 'string', 'Shopify OAuth scopes');
        \App\Models\SystemSetting::set('shopify_redirect_uri',  (string) $request->input('shopify_redirect_uri', ''), 'string', 'Shopify OAuth redirect URI');

        return back()->with('success', 'Shopify settings saved.');
    }
    public function settingHubspot(): View
    {
        return view('admin.settings.hubspot', [
            'enabled'      => (bool) \App\Models\SystemSetting::get('hubspot_enabled', false),
            'clientId'     => (string) \App\Models\SystemSetting::get('hubspot_client_id', ''),
            // Never echo the secret back into the form HTML — only expose whether one is set.
            'hasSecret'    => \App\Models\SystemSetting::get('hubspot_client_secret', '') !== '',
            'scopes'       => (string) \App\Models\SystemSetting::get('hubspot_scopes', \App\Services\Hubspot\HubspotService::DEFAULT_SCOPES),
            'redirectUri'  => (string) (\App\Models\SystemSetting::get('hubspot_redirect_uri') ?: url('/hubspot/oauth/callback')),
            'integrationsCount' => \App\Models\HubspotIntegration::count(),
            'activeCount'       => \App\Models\HubspotIntegration::where('status', 'active')->count(),
            'logsCount'         => \App\Models\HubspotIntegrationLog::count(),
        ]);
    }

    public function settingHubspotUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'hubspot_enabled'       => 'nullable|in:0,1',
            'hubspot_client_id'     => 'nullable|string|max:191',
            'hubspot_client_secret' => 'nullable|string|max:191',
            'hubspot_scopes'        => 'nullable|string|max:500',
            'hubspot_redirect_uri'  => 'nullable|url|max:255',
        ]);

        \App\Models\SystemSetting::set('hubspot_enabled',   $request->boolean('hubspot_enabled'), 'bool', 'Toggle HubSpot OAuth integration');
        \App\Models\SystemSetting::set('hubspot_client_id', (string) $request->input('hubspot_client_id', ''), 'string', 'HubSpot app client ID');
        // Leave the secret untouched when the field is submitted blank (it's masked in the form).
        if ($request->filled('hubspot_client_secret')) {
            \App\Models\SystemSetting::set('hubspot_client_secret', (string) $request->input('hubspot_client_secret'), 'string', 'HubSpot app client secret');
        }
        \App\Models\SystemSetting::set('hubspot_scopes',       (string) $request->input('hubspot_scopes', \App\Services\Hubspot\HubspotService::DEFAULT_SCOPES), 'string', 'HubSpot OAuth scopes');
        \App\Models\SystemSetting::set('hubspot_redirect_uri', (string) $request->input('hubspot_redirect_uri', ''), 'string', 'HubSpot OAuth redirect URI');

        \App\Support\Audit::log('settings.hubspot_update', ['meta' => [
            'enabled'    => $request->boolean('hubspot_enabled'),
            'has_id'     => $request->filled('hubspot_client_id'),
            'set_secret' => $request->filled('hubspot_client_secret'),
        ]]);

        return back()->with('success', 'HubSpot settings saved.');
    }

    // ---- Slack (per-workspace creds; admin holds the platform on/off + setup) ----
    public function settingSlack(): View
    {
        return view('admin.settings.slack', [
            'enabled'           => (bool) \App\Models\SystemSetting::get('slack_enabled', true),
            'integrationsCount' => \App\Models\SlackIntegration::count(),
            'activeCount'       => \App\Models\SlackIntegration::where('status', 'active')->count(),
            'logsCount'         => \App\Models\SlackIntegrationLog::count(),
            'commandUrl'        => url('/webhooks/slack/command'),
        ]);
    }

    public function settingSlackUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['slack_enabled' => 'nullable|in:0,1']);
        \App\Models\SystemSetting::set('slack_enabled', $request->boolean('slack_enabled'), 'bool', 'Toggle Slack integration platform-wide');
        \App\Support\Audit::log('settings.slack_update', ['meta' => ['enabled' => $request->boolean('slack_enabled')]]);
        return back()->with('success', 'Slack settings saved.');
    }

    // ---- Trello (per-workspace creds; admin holds the platform on/off + setup) ----
    public function settingTrello(): View
    {
        return view('admin.settings.trello', [
            'enabled'           => (bool) \App\Models\SystemSetting::get('trello_enabled', true),
            'integrationsCount' => \App\Models\TrelloIntegration::count(),
            'activeCount'       => \App\Models\TrelloIntegration::where('status', 'active')->count(),
            'logsCount'         => \App\Models\TrelloIntegrationLog::count(),
            'callbackUrl'       => url('/webhooks/trello'),
        ]);
    }

    public function settingTrelloUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['trello_enabled' => 'nullable|in:0,1']);
        \App\Models\SystemSetting::set('trello_enabled', $request->boolean('trello_enabled'), 'bool', 'Toggle Trello integration platform-wide');
        \App\Support\Audit::log('settings.trello_update', ['meta' => ['enabled' => $request->boolean('trello_enabled')]]);
        return back()->with('success', 'Trello settings saved.');
    }

    /**
     * Google integration platform config — the ONE place that writes the
     * google_calendar_* SystemSettings that GoogleCalendarService /
     * GoogleApiService read. Without these the whole Google suite
     * (Appointments, Meet, Sheets/Docs/Forms flow nodes) is dead because
     * clientId() resolves to '' and every OAuth start fails.
     */
    public function settingGoogleCalendar(): View
    {
        $gcal = app(\App\Services\GoogleCalendar\GoogleCalendarService::class);

        // KPI: how many workspaces have already completed the per-workspace
        // OAuth (token lives in appointment_settings.google_oauth.access_token).
        $connected = \App\Models\Workspace::query()
            ->whereNotNull('appointment_settings')
            ->get(['appointment_settings'])
            ->filter(fn ($w) => !empty(($w->appointment_settings['google_oauth']['access_token'] ?? null)))
            ->count();

        return view('admin.settings.google-calendar', [
            'enabled'        => (bool) \App\Models\SystemSetting::get('google_calendar_enabled', false),
            'clientId'       => (string) \App\Models\SystemSetting::get('google_calendar_client_id', ''),
            // Never echo the secret back into the form HTML — only expose whether one is set.
            'hasSecret'      => \App\Models\SystemSetting::get('google_calendar_client_secret', '') !== '',
            'scopes'         => $gcal->scopes(),
            'redirectUri'    => $gcal->redirectUri(),
            'defaultScopes'  => \App\Services\GoogleCalendar\GoogleCalendarService::DEFAULT_SCOPES,
            'connectedCount' => $connected,
        ]);
    }

    public function settingGoogleCalendarUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'google_calendar_enabled'       => 'nullable|in:0,1',
            'google_calendar_client_id'     => 'nullable|string|max:255',
            'google_calendar_client_secret' => 'nullable|string|max:255',
            'google_calendar_scopes'        => 'nullable|string|max:1000',
            'google_calendar_redirect_uri'  => 'nullable|url|max:255',
        ]);

        \App\Models\SystemSetting::set('google_calendar_enabled',   $request->boolean('google_calendar_enabled'), 'bool', 'Toggle Google integration (Calendar/Meet/Sheets/Docs/Forms)');
        \App\Models\SystemSetting::set('google_calendar_client_id', (string) $request->input('google_calendar_client_id', ''), 'string', 'Google OAuth client ID');
        // Leave the secret untouched when the field is submitted blank (it's masked in the form). Stored encrypted via ENCRYPTED_KEYS.
        if ($request->filled('google_calendar_client_secret')) {
            \App\Models\SystemSetting::set('google_calendar_client_secret', (string) $request->input('google_calendar_client_secret'), 'string', 'Google OAuth client secret');
        }
        \App\Models\SystemSetting::set('google_calendar_scopes',       (string) ($request->input('google_calendar_scopes') ?: \App\Services\GoogleCalendar\GoogleCalendarService::DEFAULT_SCOPES), 'string', 'Google OAuth scopes');
        \App\Models\SystemSetting::set('google_calendar_redirect_uri', (string) $request->input('google_calendar_redirect_uri', ''), 'string', 'Google OAuth redirect URI');

        \App\Support\Audit::log('settings.google_calendar_update', ['meta' => [
            'enabled'    => $request->boolean('google_calendar_enabled'),
            'has_id'     => $request->filled('google_calendar_client_id'),
            'set_secret' => $request->filled('google_calendar_client_secret'),
        ]]);

        return back()->with('success', 'Google integration settings saved.');
    }

    public function settingSocialLogin(): View
    {
        $g = fn ($k, $d = '') => \App\Models\SystemSetting::get($k, $d);
        return view('admin.settings.social-login', [
            'googleEnabled'   => (bool) $g('social_google_enabled', false),
            'googleClientId'  => (string) $g('social_google_client_id', ''),
            'googleHasSecret' => $g('social_google_client_secret', '') !== '',
            'fbEnabled'       => (bool) $g('social_facebook_enabled', false),
            'fbClientId'      => (string) $g('social_facebook_client_id', ''),
            'fbHasSecret'     => $g('social_facebook_client_secret', '') !== '',
            'reEnabled'       => (bool) $g('recaptcha_enabled', false),
            'reVersion'       => (string) ($g('recaptcha_version', 'v2') ?: 'v2'),
            'reSiteKey'       => (string) $g('recaptcha_site_key', ''),
            'reHasSecret'     => $g('recaptcha_secret', '') !== '',
            'reThreshold'     => (string) ($g('recaptcha_v3_threshold', '0.5') ?: '0.5'),
            'googleRedirect'  => url('/auth/google/callback'),
            'fbRedirect'      => url('/auth/facebook/callback'),
        ]);
    }

    public function settingSocialLoginUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'social_google_enabled'        => 'nullable|in:0,1',
            'social_google_client_id'      => 'nullable|string|max:255',
            'social_google_client_secret'  => 'nullable|string|max:255',
            'social_facebook_enabled'      => 'nullable|in:0,1',
            'social_facebook_client_id'    => 'nullable|string|max:255',
            'social_facebook_client_secret'=> 'nullable|string|max:255',
            'recaptcha_enabled'            => 'nullable|in:0,1',
            'recaptcha_version'            => 'nullable|in:v2,v3',
            'recaptcha_site_key'           => 'nullable|string|max:255',
            'recaptcha_secret'             => 'nullable|string|max:255',
            'recaptcha_v3_threshold'       => 'nullable|numeric|min:0|max:1',
        ]);

        $S = fn ($k, $v, $t = 'string', $d = '') => \App\Models\SystemSetting::set($k, $v, $t, $d);
        $S('social_google_enabled',   $request->boolean('social_google_enabled'), 'bool', 'Enable Google sign-in');
        $S('social_google_client_id', (string) $request->input('social_google_client_id', ''), 'string', 'Google OAuth client ID');
        if ($request->filled('social_google_client_secret')) $S('social_google_client_secret', (string) $request->input('social_google_client_secret'), 'string', 'Google OAuth client secret');

        $S('social_facebook_enabled',   $request->boolean('social_facebook_enabled'), 'bool', 'Enable Facebook sign-in');
        $S('social_facebook_client_id', (string) $request->input('social_facebook_client_id', ''), 'string', 'Facebook app ID');
        if ($request->filled('social_facebook_client_secret')) $S('social_facebook_client_secret', (string) $request->input('social_facebook_client_secret'), 'string', 'Facebook app secret');

        $S('recaptcha_enabled',      $request->boolean('recaptcha_enabled'), 'bool', 'Enable Google reCAPTCHA');
        $S('recaptcha_version',      $request->input('recaptcha_version', 'v2') === 'v3' ? 'v3' : 'v2', 'string', 'reCAPTCHA version');
        $S('recaptcha_site_key',     (string) $request->input('recaptcha_site_key', ''), 'string', 'reCAPTCHA site key');
        if ($request->filled('recaptcha_secret')) $S('recaptcha_secret', (string) $request->input('recaptcha_secret'), 'string', 'reCAPTCHA secret key');
        $S('recaptcha_v3_threshold', (string) ($request->input('recaptcha_v3_threshold') ?: '0.5'), 'string', 'reCAPTCHA v3 score threshold');

        \App\Support\Audit::log('settings.social_login_update', ['meta' => [
            'google'   => $request->boolean('social_google_enabled'),
            'facebook' => $request->boolean('social_facebook_enabled'),
            'recaptcha'=> $request->boolean('recaptcha_enabled'),
        ]]);

        return back()->with('success', 'Social login & captcha settings saved.');
    }
    public function settingWoocommerce(): View
    {
        return view('admin.settings.woocommerce', [
            'enabled'           => (bool) \App\Models\SystemSetting::get('woocommerce_enabled', false),
            'integrationsCount' => \App\Models\WoocommerceIntegration::count(),
            'eventsCount'       => \App\Models\WoocommerceIntegrationEvent::count(),
            'logsCount'         => \App\Models\WoocommerceIntegrationLog::count(),
        ]);
    }

    public function settingWoocommerceUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['woocommerce_enabled' => 'nullable|in:0,1']);
        \App\Models\SystemSetting::set('woocommerce_enabled', $request->boolean('woocommerce_enabled'), 'bool', 'Toggle WooCommerce integration');
        return back()->with('success', 'WooCommerce settings saved.');
    }
    /**
     * GET /admin/settings/export — stream every system_settings row as a
     * JSON file the admin can back up. Secrets are redacted to a fixed
     * placeholder so the export is safe to attach to a support ticket.
     * Secret keys: anything ending in _secret, _password, _token, _key.
     */
    public function settingsExport(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows = \App\Models\SystemSetting::query()->orderBy('key')->get(['key', 'value', 'type']);
        $brand = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $filename = \Illuminate\Support\Str::slug($brand) . '-settings-' . now()->format('Ymd-Hi') . '.json';

        $isSecret = function (string $key): bool {
            $key = strtolower($key);
            foreach (['_secret', '_password', '_token', '_apikey', '_api_key'] as $suffix) {
                if (str_ends_with($key, $suffix)) return true;
            }
            // Cover provider keys explicitly so we don't leak BYOK.
            foreach (['posthog_key', 'mixpanel_token', 'mail_password',
                      'shopify_client_secret', 'catalog_meta_app_secret',
                      'twilio_auth', 'razorpay_key_secret', 'stripe_secret_key'] as $exact) {
                if ($key === $exact) return true;
            }
            return false;
        };

        $payload = [
            'app'        => $brand,
            'exported_at'=> now()->toIso8601String(),
            'note'       => 'Secret values are redacted as "[REDACTED]" — this export is safe to share with support.',
            'settings'   => $rows->map(function ($r) use ($isSecret) {
                $val = $r->value;
                if ($isSecret((string) $r->key) && $val !== null && $val !== '') {
                    $val = '[REDACTED]';
                }
                return ['key' => $r->key, 'value' => $val, 'type' => $r->type];
            })->all(),
        ];

        \App\Support\Audit::log('settings.export', ['meta' => ['rows' => count($payload['settings'])]]);

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function settingCatalog(): View
    {
        // Platform-level catalog knobs only. Per-merchant Catalog ID +
        // WABA ID + Phone Number ID live on each workspace's /catalog
        // page — Meta requires the catalog be connected to the
        // merchant's own Commerce Account, so there's no single
        // platform-wide catalog to configure.
        $catalog = [
            'enabled'         => (bool)   \App\Models\SystemSetting::get('catalog_enabled', true),
            'graph_api_version' => (string) \App\Models\SystemSetting::get('catalog_graph_api_version', 'v23.0'),
            'meta_app_id'     => (string) \App\Models\SystemSetting::get('catalog_meta_app_id', ''),
            'meta_app_secret' => (string) \App\Models\SystemSetting::get('catalog_meta_app_secret', ''),
            'default_currency'=> (string) \App\Models\SystemSetting::get('catalog_default_currency', 'USD'),
            'commerce_enabled'=> (bool)   \App\Models\SystemSetting::get('catalog_commerce_enabled', false),
        ];
        $stats = [
            'connected' => 0,
            'products'  => 0,
            'sends'     => 0,
        ];
        try {
            $stats['connected'] = (int) \App\Models\WaCatalog::query()->whereNotNull('catalog_id')->count();
        } catch (\Throwable $e) {}
        try {
            $stats['products']  = (int) \DB::table('wa_catalog_products')->count();
        } catch (\Throwable $e) {}
        try {
            $stats['sends']     = (int) \DB::table('wa_catalog_send_logs')->count();
        } catch (\Throwable $e) {}
        return view('admin.settings.catalog', compact('catalog', 'stats'));
    }

    public function settingCatalogUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'catalog_enabled'           => 'sometimes|boolean',
            'catalog_commerce_enabled'  => 'sometimes|boolean',
            'catalog_graph_api_version' => 'nullable|regex:/^v\d{1,2}\.\d{1,2}$/',
            'catalog_meta_app_id'       => 'nullable|string|max:60',
            'catalog_meta_app_secret'   => 'nullable|string|max:120',
            'catalog_default_currency'  => 'nullable|string|size:3',
        ]);

        // Encrypt the app secret at rest; blank submission keeps the
        // saved value (mirrors the mail settings pattern).
        if (! empty($data['catalog_meta_app_secret'])) {
            $data['catalog_meta_app_secret'] = \Illuminate\Support\Facades\Crypt::encryptString($data['catalog_meta_app_secret']);
        } else {
            unset($data['catalog_meta_app_secret']);
        }

        $boolKeys = ['catalog_enabled', 'catalog_commerce_enabled'];
        $this->persistSettingsGroup($request, $data, $boolKeys, 'settings.catalog_update');

        return redirect()->route('admin.settings.catalog')->with('success', __('Catalog settings saved.'));
    }
    public function settingPwa(): View
    {
        $brand = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $pwa = [
            'enabled'             => (bool)   \App\Models\SystemSetting::get('pwa_enabled', false),
            'install_prompt'      => (bool)   \App\Models\SystemSetting::get('pwa_install_prompt', true),
            'offline_enabled'     => (bool)   \App\Models\SystemSetting::get('pwa_offline_enabled', true),
            'app_name'            => (string) \App\Models\SystemSetting::get('pwa_app_name', $brand),
            'short_name'          => (string) \App\Models\SystemSetting::get('pwa_short_name', $brand),
            'description'         => (string) \App\Models\SystemSetting::get('pwa_description', ''),
            'start_url'           => (string) \App\Models\SystemSetting::get('pwa_start_url', '/'),
            'scope'               => (string) \App\Models\SystemSetting::get('pwa_scope', '/'),
            'display'             => (string) \App\Models\SystemSetting::get('pwa_display', 'standalone'),
            'orientation'         => (string) \App\Models\SystemSetting::get('pwa_orientation', 'portrait'),
            'theme_color'         => (string) \App\Models\SystemSetting::get('pwa_theme_color', '#075E54'),
            'background_color'    => (string) \App\Models\SystemSetting::get('pwa_background_color', '#FBFAF6'),
            'icon_192'            => (string) \App\Models\SystemSetting::get('pwa_icon_192', ''),
            'icon_512'            => (string) \App\Models\SystemSetting::get('pwa_icon_512', ''),
            'splash'              => (string) \App\Models\SystemSetting::get('pwa_splash', ''),
            // Team-Inbox PWA — a SEPARATE installable app for /team-inbox with
            // push notifications (agents share the link, install, get pinged).
            'ti_enabled'          => (bool)   \App\Models\SystemSetting::get('ti_pwa_enabled', false),
            'ti_name'             => (string) \App\Models\SystemSetting::get('ti_pwa_name', $brand . ' Inbox'),
            'ti_short_name'       => (string) \App\Models\SystemSetting::get('ti_pwa_short_name', 'Inbox'),
            'ti_icon_192'         => (string) \App\Models\SystemSetting::get('ti_pwa_icon_192', ''),
            'ti_icon_512'         => (string) \App\Models\SystemSetting::get('ti_pwa_icon_512', ''),
        ];
        $tiPush      = app(\App\Services\Push\WebPushService::class);
        $tiPushReady = $tiPush->isConfigured();  // package installed + VAPID keys generated
        $tiPushLib   = $tiPush->libAvailable();  // composer package present
        return view('admin.settings.pwa', compact('pwa', 'brand', 'tiPushReady', 'tiPushLib'));
    }

    public function settingPwaUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'pwa_enabled'             => 'sometimes|boolean',
            'pwa_install_prompt'      => 'sometimes|boolean',
            'pwa_offline_enabled'     => 'sometimes|boolean',
            'pwa_app_name'            => 'nullable|string|max:60',
            'pwa_short_name'          => 'nullable|string|max:12',
            'pwa_description'         => 'nullable|string|max:300',
            'pwa_start_url'           => 'nullable|string|max:200',
            'pwa_scope'               => 'nullable|string|max:200',
            'pwa_display'             => 'nullable|in:standalone,fullscreen,minimal-ui,browser',
            'pwa_orientation'         => 'nullable|in:any,portrait,landscape,natural,portrait-primary,landscape-primary',
            'pwa_theme_color'         => 'nullable|regex:/^#[0-9A-Fa-f]{3,8}$/',
            'pwa_background_color'    => 'nullable|regex:/^#[0-9A-Fa-f]{3,8}$/',
            // Icons are uploaded files now (replacing the old URL fields). The
            // stored public URL is saved back into the pwa_icon_* settings.
            'pwa_icon_192_file'       => 'nullable|image|mimes:png,webp|max:1024',
            'pwa_icon_512_file'       => 'nullable|image|mimes:png,webp|max:2048',
            // Team-Inbox PWA
            'ti_pwa_enabled'          => 'sometimes|boolean',
            'ti_pwa_name'             => 'nullable|string|max:60',
            'ti_pwa_short_name'       => 'nullable|string|max:12',
            // Dedicated Team-Inbox app icons (fall back to the app-wide PWA icons
            // in the manifest when not set).
            'ti_pwa_icon_192_file'    => 'nullable|image|mimes:png,webp|max:1024',
            'ti_pwa_icon_512_file'    => 'nullable|image|mimes:png,webp|max:2048',
        ]);

        // Strip the upload fields — they are not settings themselves.
        unset($data['pwa_icon_192_file'], $data['pwa_icon_512_file'],
              $data['ti_pwa_icon_192_file'], $data['ti_pwa_icon_512_file']);

        // Store any newly uploaded icon and point its setting at the public
        // URL. If no new file was sent, the existing value is left untouched
        // (the key simply isn't included in $data).
        foreach ([
            'pwa_icon_192'    => 'pwa_icon_192_file',
            'pwa_icon_512'    => 'pwa_icon_512_file',
            'ti_pwa_icon_192' => 'ti_pwa_icon_192_file',
            'ti_pwa_icon_512' => 'ti_pwa_icon_512_file',
        ] as $settingKey => $fileField) {
            if ($request->hasFile($fileField)) {
                $path = $request->file($fileField)->store('pwa', 'public');
                $data[$settingKey] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
            }
        }

        // Normalise start_url / scope so a value typed as "host.tld/login" (which a
        // browser resolves RELATIVE to the manifest → dead path → app opens the
        // homepage) is stored as a clean absolute path "/login".
        if (array_key_exists('pwa_start_url', $data)) {
            $data['pwa_start_url'] = $this->pwaPath((string) $data['pwa_start_url'], '/');
        }
        if (array_key_exists('pwa_scope', $data)) {
            $data['pwa_scope'] = $this->pwaPath((string) $data['pwa_scope'], '/');
        }

        $boolKeys = ['pwa_enabled','pwa_install_prompt','pwa_offline_enabled','ti_pwa_enabled'];
        $this->persistSettingsGroup($request, $data, $boolKeys, 'settings.pwa_update');
        return redirect()->route('admin.settings.pwa')->with('success', __('PWA settings saved.'));
    }

    /**
     * POST /admin/settings/pwa/generate-keys — one-click VAPID key generation for
     * the Team-Inbox PWA push, from the settings "Generate keys" button (replaces
     * the old `teaminbox:push-setup` artisan command — no SSH needed). Idempotent:
     * keeps existing keys. No-op with a clear message when the composer package
     * isn't installed yet.
     */
    public function settingPwaGenerateKeys(): \Illuminate\Http\JsonResponse
    {
        $push = app(\App\Services\Push\WebPushService::class);

        if (!$push->libAvailable()) {
            return response()->json([
                'ok'      => false,
                'ready'   => false,
                'message' => __('The push library is not installed on the server yet. Run "composer require minishlink/web-push" once, then click Generate keys again.'),
            ], 422);
        }

        try {
            $public = $push->generateKeys();  // idempotent — returns existing key if already set
        } catch (\Throwable $e) {
            \Log::warning('[WEBPUSH] generate-keys endpoint failed: ' . $e->getMessage());
            $public = '';
        }

        return response()->json([
            'ok'      => $public !== '',
            'ready'   => $push->isConfigured(),
            'message' => $public !== ''
                ? __('Push notification keys are ready. Agents can now turn on notifications from the inbox.')
                : __('Could not generate the keys on this server. Please check the server logs, or contact support.'),
        ]);
    }

    public function settingPrivacy(): View
    {
        $privacy = [
            'cookie_banner_enabled' => (bool)   \App\Models\SystemSetting::get('privacy_cookie_banner_enabled', true),
            'cookie_banner_style'   => (string) \App\Models\SystemSetting::get('privacy_cookie_banner_style', 'bottom-bar'),
            'cookie_message'        => (string) \App\Models\SystemSetting::get('privacy_cookie_message',
                'We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.'),
            'privacy_policy_url'    => (string) \App\Models\SystemSetting::get('privacy_policy_url', ''),
            'terms_url'             => (string) \App\Models\SystemSetting::get('privacy_terms_url', ''),
            'cookies_policy_url'    => (string) \App\Models\SystemSetting::get('privacy_cookies_policy_url', ''),
            'gdpr_compliance'       => (bool)   \App\Models\SystemSetting::get('privacy_gdpr_compliance', true),
            'ccpa_compliance'       => (bool)   \App\Models\SystemSetting::get('privacy_ccpa_compliance', true),
            'ada_skip_link'         => (bool)   \App\Models\SystemSetting::get('privacy_ada_skip_link', true),
            'ada_high_contrast'     => (bool)   \App\Models\SystemSetting::get('privacy_ada_high_contrast', false),
            'ada_large_text'        => (bool)   \App\Models\SystemSetting::get('privacy_ada_large_text', false),
            'ada_reduced_motion'    => (bool)   \App\Models\SystemSetting::get('privacy_ada_reduced_motion', false),
            'dnt_respect'           => (bool)   \App\Models\SystemSetting::get('privacy_dnt_respect', true),
        ];
        return view('admin.settings.privacy', compact('privacy'));
    }

    public function settingPrivacyUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'privacy_cookie_banner_enabled' => 'sometimes|boolean',
            'privacy_cookie_banner_style'   => 'nullable|in:modal,bottom-bar,top-bar',
            'privacy_cookie_message'        => 'nullable|string|max:1000',
            'privacy_policy_url'            => 'nullable|url|max:300',
            'privacy_terms_url'             => 'nullable|url|max:300',
            'privacy_cookies_policy_url'    => 'nullable|url|max:300',
            'privacy_gdpr_compliance'       => 'sometimes|boolean',
            'privacy_ccpa_compliance'       => 'sometimes|boolean',
            'privacy_ada_skip_link'         => 'sometimes|boolean',
            'privacy_ada_high_contrast'     => 'sometimes|boolean',
            'privacy_ada_large_text'        => 'sometimes|boolean',
            'privacy_ada_reduced_motion'    => 'sometimes|boolean',
            'privacy_dnt_respect'           => 'sometimes|boolean',
        ]);
        $boolKeys = ['privacy_cookie_banner_enabled','privacy_gdpr_compliance','privacy_ccpa_compliance',
            'privacy_ada_skip_link','privacy_ada_high_contrast','privacy_ada_large_text',
            'privacy_ada_reduced_motion','privacy_dnt_respect'];
        $this->persistSettingsGroup($request, $data, $boolKeys, 'settings.privacy_update');
        return redirect()->route('admin.settings.privacy')->with('success', __('Privacy, GDPR & ADA settings saved.'));
    }

    /** Defaults for the auth-page editor (match the original blade copy). */
    private function authPageDefaults(): array
    {
        return [
            'login'    => ['eyebrow' => 'Operator console for WhatsApp', 'heading' => 'One place for every', 'heading_accent' => 'conversation', 'subheading' => 'Broadcasts, flows, AI assist, shared inbox — all in one workspace your team will actually use.'],
            'register' => ['eyebrow' => 'Get started in minutes',        'heading' => 'Start your',           'heading_accent' => 'free trial',   'subheading' => 'Create your workspace and connect your first WhatsApp number in minutes.'],
            'forgot'   => ['eyebrow' => 'Account recovery',               'heading' => 'Get back in,',         'heading_accent' => 'fast',         'subheading' => 'Enter your email and we will send you a secure link to reset your password.'],
        ];
    }

    public function settingAuthPages(\Illuminate\Http\Request $request): View
    {
        $page = in_array($request->query('page'), ['login', 'register', 'forgot'], true) ? $request->query('page') : 'login';
        $defaults = $this->authPageDefaults();
        $cfg = [];
        foreach (['login', 'register', 'forgot'] as $p) {
            $cfg[$p] = [
                'eyebrow'        => (string) \App\Models\SystemSetting::get("auth.$p.eyebrow", $defaults[$p]['eyebrow']),
                'heading'        => (string) \App\Models\SystemSetting::get("auth.$p.heading", $defaults[$p]['heading']),
                'heading_accent' => (string) \App\Models\SystemSetting::get("auth.$p.heading_accent", $defaults[$p]['heading_accent']),
                'subheading'     => (string) \App\Models\SystemSetting::get("auth.$p.subheading", $defaults[$p]['subheading']),
                'accent'         => (string) \App\Models\SystemSetting::get("auth.$p.accent", '#25D366'),
                'media_url'      => (string) \App\Models\SystemSetting::get("auth.$p.media_url", ''),
                'media_type'     => (string) \App\Models\SystemSetting::get("auth.$p.media_type", ''),
            ];
        }
        // Global design variant (1–5) — applies to login/register/forgot together.
        $variant = (int) \App\Models\SystemSetting::get('auth.variant', '1');
        if ($variant < 1 || $variant > 5) $variant = 1;

        return view('admin.settings.auth-pages', compact('page', 'cfg', 'variant'));
    }

    public function settingAuthPagesUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $page = $request->input('page');
        abort_unless(in_array($page, ['login', 'register', 'forgot'], true), 422);

        $data = $request->validate([
            'variant'        => 'nullable|integer|min:1|max:5',
            'eyebrow'        => 'nullable|string|max:120',
            'heading'        => 'nullable|string|max:120',
            'heading_accent' => 'nullable|string|max:120',
            'subheading'     => 'nullable|string|max:400',
            'accent'         => 'nullable|string|max:9',
            'media'          => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,mp4,webm|max:20480',
            'remove_media'   => 'nullable|boolean',
        ]);

        // Design variant is GLOBAL (one look for all auth pages).
        if (isset($data['variant'])) {
            \App\Models\SystemSetting::set('auth.variant', (string) $data['variant'], 'string', 'Auth pages design variant (1-5)');
        }

        foreach (['eyebrow', 'heading', 'heading_accent', 'subheading', 'accent'] as $k) {
            \App\Models\SystemSetting::set("auth.$page.$k", (string) ($data[$k] ?? ''), 'string', "Auth page ($page) $k");
        }

        if ($request->boolean('remove_media')) {
            \App\Models\SystemSetting::set("auth.$page.media_url", '', 'string', "Auth page ($page) media");
            \App\Models\SystemSetting::set("auth.$page.media_type", '', 'string', "Auth page ($page) media type");
        } elseif ($request->hasFile('media')) {
            $file = $request->file('media');
            $dir  = public_path('uploads/auth');
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $ext  = strtolower($file->getClientOriginalExtension());
            $name = $page . '_' . time() . '.' . $ext;
            $file->move($dir, $name);
            $type = in_array($ext, ['mp4', 'webm'], true) ? 'video' : ($ext === 'gif' ? 'gif' : 'image');
            \App\Models\SystemSetting::set("auth.$page.media_url", 'uploads/auth/' . $name, 'string', "Auth page ($page) media");
            \App\Models\SystemSetting::set("auth.$page.media_type", $type, 'string', "Auth page ($page) media type");
        }

        return redirect()->route('admin.settings.auth-pages', ['page' => $page])->with('success', __('Auth page saved.'));
    }

    /**
     * Render an auth page for the editor PREVIEW. The real /login etc. routes
     * use the `guest` middleware, which redirects a logged-in admin away to the
     * dashboard — so the preview iframe would show the dashboard, not the page.
     * This admin-gated route renders the same blade directly with no redirect.
     */
    public function settingAuthPagesPreview(string $page): View
    {
        abort_unless(in_array($page, ['login', 'register', 'forgot'], true), 404);
        $view = ['login' => 'auth.login', 'register' => 'auth.register', 'forgot' => 'auth.forgot-password'][$page];
        return view($view);
    }

    /** Inline text save from the on-page editor bridge. key = "{page}.{field}". */
    /** Save the global auth design variant (1–5) — applies to all auth pages. */
    public function settingAuthPagesVariant(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['variant' => 'required|integer|min:1|max:5']);
        \App\Models\SystemSetting::set('auth.variant', (string) $data['variant'], 'string', 'Auth pages design variant (1-5)');
        return response()->json(['ok' => true, 'variant' => (int) $data['variant']]);
    }

    public function settingAuthPagesInline(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'key'   => 'required|string|max:60',
            'value' => 'nullable|string|max:600',
        ]);
        [$page, $field] = array_pad(explode('.', $data['key'], 2), 2, '');
        $allowedFields = ['eyebrow', 'heading', 'heading_accent', 'subheading', 'accent', 'hero_title', 'hero_desc', 'footer'];
        // Showcase panel: stat pills, feature cards, "what's also inside" chips.
        $showcaseOk = (bool) preg_match('/^(stat[1-3]_(num|label)|feat[1-4]_(title|desc)|inside_heading|chip[1-6])$/', $field);
        if (!in_array($page, ['login', 'register', 'forgot'], true) || (!in_array($field, $allowedFields, true) && !$showcaseOk)) {
            return response()->json(['ok' => false, 'error' => 'Unknown field'], 422);
        }
        \App\Models\SystemSetting::set("auth.$page.$field", (string) ($data['value'] ?? ''), 'string', "Auth page ($page) $field");
        return response()->json(['ok' => true]);
    }

    /** Inline media (image/video/gif) upload for the side panel from the bridge. */
    public function settingAuthPagesInlineMedia(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'page'  => 'required|in:login,register,forgot',
            'media' => 'required|file|mimes:jpg,jpeg,png,gif,webp,mp4,webm|max:20480',
        ]);
        $file = $request->file('media');
        $dir  = public_path('uploads/auth');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ext  = strtolower($file->getClientOriginalExtension());
        $name = $data['page'] . '_' . time() . '.' . $ext;
        $file->move($dir, $name);
        $type = in_array($ext, ['mp4', 'webm'], true) ? 'video' : ($ext === 'gif' ? 'gif' : 'image');
        $url  = 'uploads/auth/' . $name;
        \App\Models\SystemSetting::set("auth.{$data['page']}.media_url", $url, 'string', "Auth page ({$data['page']}) media");
        \App\Models\SystemSetting::set("auth.{$data['page']}.media_type", $type, 'string', "Auth page ({$data['page']}) media type");
        return response()->json(['ok' => true, 'url' => asset($url), 'type' => $type]);
    }

    /** Clear the side-panel media (revert to the built-in showcase) from the bridge. */
    public function settingAuthPagesInlineMediaClear(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['page' => 'required|in:login,register,forgot']);
        \App\Models\SystemSetting::set("auth.{$data['page']}.media_url", '', 'string', "Auth page ({$data['page']}) media");
        \App\Models\SystemSetting::set("auth.{$data['page']}.media_type", '', 'string', "Auth page ({$data['page']}) media type");
        return response()->json(['ok' => true]);
    }

    /**
     * Canonical user-panel nav items. Each: label + default zone + a short
     * description for the editor. `zone` is where the item sits out-of-the-box:
     * 'bar' = the top navigation, 'more' = the /more page (promotable up).
     * `locked` items can't leave the bar (the More gateway).
     */
    private function userNavItems(): array
    {
        return [
            'dashboard'    => ['label' => __('Dashboard'),  'zone' => 'bar',  'desc' => __('Home / overview')],
            'wa-campaigns' => ['label' => __('Campaigns'),  'zone' => 'bar',  'desc' => __('Bulk campaign sends')],
            'flows'        => ['label' => __('Flows'),      'zone' => 'bar',  'desc' => __('Automation builder')],
            'templates'    => ['label' => __('Templates'),  'zone' => 'bar',  'desc' => __('Message templates')],
            'metaads'      => ['label' => __('Meta Ads'),   'zone' => 'bar',  'desc' => __('Click-to-WhatsApp ads')],
            'devices'      => ['label' => __('Devices'),    'zone' => 'bar',  'desc' => __('Connected numbers')],
            'broadcasts'      => ['label' => __('Broadcasts'),    'zone' => 'more', 'desc' => __('One-off broadcasts')],
            'contacts'        => ['label' => __('Contacts'),      'zone' => 'more', 'desc' => __('Audience / CRM')],
            'team-inbox'      => ['label' => __('Team Inbox'),    'zone' => 'more', 'desc' => __('Shared live inbox')],
            'deals'           => ['label' => __('Deals'),         'zone' => 'more', 'desc' => __('Sales pipeline')],
            'chat'            => ['label' => __('Live Chat'),     'zone' => 'more', 'desc' => __('1-to-1 chat console')],
            'analytics'       => ['label' => __('Analytics'),     'zone' => 'more', 'desc' => __('Reports & insights')],
            'auto-reply'      => ['label' => __('Auto-reply'),    'zone' => 'more', 'desc' => __('Keyword auto-replies')],
            'scheduled'       => ['label' => __('Scheduled'),     'zone' => 'more', 'desc' => __('Scheduled messages')],
            'message-history' => ['label' => __('History'),       'zone' => 'more', 'desc' => __('Message history')],
            'ai-assistants'   => ['label' => __('AI Assistants'), 'zone' => 'more', 'desc' => __('AI reply agents')],
            'ai-training'     => ['label' => __('AI Training'),   'zone' => 'more', 'desc' => __('Knowledge base')],
            'wa-links'        => ['label' => __('WA Links'),      'zone' => 'more', 'desc' => __('Click-to-chat links')],
            'chatbot-widgets' => ['label' => __('Chat Widget'),   'zone' => 'more', 'desc' => __('Website chat widget')],
            'webhooks'        => ['label' => __('Webhooks'),      'zone' => 'more', 'desc' => __('Outbound webhooks')],
            'integrations'    => ['label' => __('Integrations'),  'zone' => 'more', 'desc' => __('Connected apps')],
            'warmer'          => ['label' => __('Warmer'),        'zone' => 'more', 'desc' => __('Number warm-up')],
            'call-logs'       => ['label' => __('Call Logs'),     'zone' => 'more', 'desc' => __('WhatsApp calls')],
            'support'         => ['label' => __('Support'),       'zone' => 'more', 'desc' => __('Help tickets')],
            'more'            => ['label' => __('More'),          'zone' => 'bar',  'desc' => __('Gateway to every page'), 'locked' => true],
        ];
    }

    /** Resolve the saved bar order (NEW {bar:[]} or legacy flat array or default). */
    private function resolvedNavBar(array $items): array
    {
        $cfg = json_decode((string) \App\Models\SystemSetting::get('user_nav_order', '[]'), true);
        $bar = [];
        if (is_array($cfg) && isset($cfg['bar']) && is_array($cfg['bar'])) {
            $bar = array_values(array_filter($cfg['bar'], fn ($k) => isset($items[$k])));
        } elseif (is_array($cfg) && $cfg && array_is_list($cfg)) {
            // legacy flat order = the bar (only the default-bar keys it listed)
            $bar = array_values(array_filter($cfg, fn ($k) => isset($items[$k]) && ($items[$k]['zone'] ?? 'more') === 'bar'));
        }
        if (empty($bar)) {
            $bar = array_keys(array_filter($items, fn ($i) => ($i['zone'] ?? 'more') === 'bar'));
        }
        if (!in_array('more', $bar, true)) $bar[] = 'more';   // gateway always last-resort present
        return $bar;
    }

    public function settingMenuOrder(): View
    {
        $items = $this->userNavItems();
        $bar   = $this->resolvedNavBar($items);
        // "More" zone = every item not in the bar, in canonical order.
        $more  = array_values(array_filter(array_keys($items), fn ($k) => !in_array($k, $bar, true)));
        return view('admin.settings.menu-order', compact('items', 'bar', 'more'));
    }

    public function settingMenuOrderUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $items = $this->userNavItems();
        $valid = array_keys($items);
        $bar = array_values(array_unique(array_filter((array) $request->input('bar', []), fn ($k) => in_array($k, $valid, true))));
        if (!in_array('more', $bar, true)) $bar[] = 'more';   // never let the gateway be removed
        \App\Models\SystemSetting::set('user_nav_order', json_encode(['bar' => $bar]), 'json', 'User panel top-bar items + order');
        return redirect()->route('admin.settings.menu-order')->with('success', __('Menu layout saved. Users see it on their next page load.'));
    }

    public function settingAnalytics(): View
    {
        $analytics = [
            'google_analytics_id'   => (string) \App\Models\SystemSetting::get('analytics_google_ga4', ''),
            'google_tag_manager_id' => (string) \App\Models\SystemSetting::get('analytics_google_gtm', ''),
            'meta_pixel_id'         => (string) \App\Models\SystemSetting::get('analytics_meta_pixel', ''),
            'microsoft_clarity_id'  => (string) \App\Models\SystemSetting::get('analytics_microsoft_clarity', ''),
            'plausible_domain'      => (string) \App\Models\SystemSetting::get('analytics_plausible_domain', ''),
            'posthog_api_key'       => (string) \App\Models\SystemSetting::get('analytics_posthog_key', ''),
            'posthog_host'          => (string) \App\Models\SystemSetting::get('analytics_posthog_host', 'https://app.posthog.com'),
            'hotjar_site_id'        => (string) \App\Models\SystemSetting::get('analytics_hotjar_site_id', ''),
            'tiktok_pixel_id'       => (string) \App\Models\SystemSetting::get('analytics_tiktok_pixel', ''),
            'linkedin_partner_id'   => (string) \App\Models\SystemSetting::get('analytics_linkedin_partner', ''),
            'twitter_pixel_id'      => (string) \App\Models\SystemSetting::get('analytics_twitter_pixel', ''),
            'mixpanel_token'        => (string) \App\Models\SystemSetting::get('analytics_mixpanel_token', ''),
        ];
        return view('admin.settings.analytics', compact('analytics'));
    }

    public function settingAnalyticsUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'analytics_google_ga4'        => 'nullable|string|max:50',
            'analytics_google_gtm'        => 'nullable|string|max:50',
            'analytics_meta_pixel'        => 'nullable|string|max:50',
            'analytics_microsoft_clarity' => 'nullable|string|max:50',
            'analytics_plausible_domain'  => 'nullable|string|max:120',
            'analytics_posthog_key'       => 'nullable|string|max:120',
            'analytics_posthog_host'      => 'nullable|url|max:200',
            'analytics_hotjar_site_id'    => 'nullable|string|max:30',
            'analytics_tiktok_pixel'      => 'nullable|string|max:50',
            'analytics_linkedin_partner'  => 'nullable|string|max:50',
            'analytics_twitter_pixel'     => 'nullable|string|max:50',
            'analytics_mixpanel_token'    => 'nullable|string|max:120',
        ]);
        $this->persistSettingsGroup($request, $data, [], 'settings.analytics_update');
        return redirect()->route('admin.settings.analytics')->with('success', __('Analytics integrations saved.'));
    }

    /**
     * Shared persistence helper for the three settings update methods.
     * Bool keys absent from $data (toggle off + browser drops the field)
     * are explicitly written as false so the persisted state mirrors the
     * form state exactly.
     */
    private function persistSettingsGroup(\Illuminate\Http\Request $request, array $data, array $boolKeys, string $auditAction): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $boolKeys, true)) {
                \App\Models\SystemSetting::set($key, (bool) $request->boolean($key), 'bool', str_replace('_', ' ', $key));
            } else {
                \App\Models\SystemSetting::set($key, (string) ($value ?? ''), 'string', str_replace('_', ' ', $key));
            }
        }
        foreach ($boolKeys as $b) {
            if (! array_key_exists($b, $data)) {
                \App\Models\SystemSetting::set($b, false, 'bool', str_replace('_', ' ', $b));
            }
        }
        \App\Support\Audit::log($auditAction, ['meta' => ['fields' => array_keys($data)]]);
    }

    /**
     * GET /manifest.json — built from system_settings so a fresh PWA
     * install picks up whatever the admin most recently saved.
     */
    public function pwaManifest(): \Illuminate\Http\JsonResponse
    {
        $brand = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $icons = [];
        $i192 = (string) \App\Models\SystemSetting::get('pwa_icon_192', '');
        $i512 = (string) \App\Models\SystemSetting::get('pwa_icon_512', '');
        if ($i192) $icons[] = ['src' => $i192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'];
        if ($i512) $icons[] = ['src' => $i512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];

        return response()->json([
            'name'             => (string) \App\Models\SystemSetting::get('pwa_app_name', $brand),
            'short_name'       => (string) \App\Models\SystemSetting::get('pwa_short_name', $brand),
            'description'      => (string) \App\Models\SystemSetting::get('pwa_description', ''),
            // Normalise so a value the admin typed as "chatkar.in/login" (no
            // leading slash / scheme) can't resolve RELATIVE to the manifest
            // ("/manifest.json" base → "/chatkar.in/login"), which made the
            // installed PWA fall back to the homepage. Output an absolute path.
            'start_url'        => $this->pwaPath((string) \App\Models\SystemSetting::get('pwa_start_url', '/'), '/'),
            'scope'            => $this->pwaPath((string) \App\Models\SystemSetting::get('pwa_scope', '/'), '/'),
            'display'          => (string) \App\Models\SystemSetting::get('pwa_display', 'standalone'),
            'orientation'      => (string) \App\Models\SystemSetting::get('pwa_orientation', 'portrait'),
            'theme_color'      => (string) \App\Models\SystemSetting::get('pwa_theme_color', '#075E54'),
            'background_color' => (string) \App\Models\SystemSetting::get('pwa_background_color', '#FBFAF6'),
            'icons'            => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * Turn whatever the admin typed for start_url / scope into a valid PWA URL.
     * A PWA start_url MUST be an absolute path ("/login") or a full URL — a bare
     * "chatkar.in/login" is treated as RELATIVE to the manifest and resolves to a
     * dead path, so the installed app opens the homepage instead. Rules:
     *   - full "http(s)://…" URL → kept as-is
     *   - "host.tld/path" typed without a scheme → strip the host → "/path"
     *   - "path" or "login" → prefixed with "/"
     */
    private function pwaPath(string $raw, string $fallback = '/'): string
    {
        $raw = trim($raw);
        if ($raw === '') return $fallback;
        if (preg_match('#^https?://#i', $raw)) return $raw;
        if (str_contains($raw, '/')) {
            $first = substr($raw, 0, strpos($raw, '/'));
            if ($first !== '' && str_contains($first, '.')) {   // looks like a host segment
                $raw = substr($raw, strpos($raw, '/'));
            }
        }
        if ($raw === '') return $fallback;
        return $raw[0] === '/' ? $raw : '/' . $raw;
    }

    public function settingSeo(): View
    {
        // Every field pulls its current value from system_settings via
        // the Seo helper. A fresh install shows sensible defaults that
        // still describe what each field does, so the preview card
        // doesn't render as an empty rectangle on first load.
        $seo = [
            'meta_title'          => (string) \App\Support\Seo::get('meta_title', ''),
            'meta_description'    => (string) \App\Support\Seo::get('meta_description', ''),
            'meta_keywords'       => (string) \App\Support\Seo::get('meta_keywords', ''),
            'robots'              => (string) \App\Support\Seo::get('robots', 'index, follow'),
            'canonical'           => (string) \App\Support\Seo::get('canonical', ''),
            'author'              => (string) \App\Support\Seo::get('author', ''),
            'og_title'            => (string) \App\Support\Seo::get('og_title', ''),
            'og_description'      => (string) \App\Support\Seo::get('og_description', ''),
            'og_image'            => (string) \App\Support\Seo::get('og_image', ''),
            'og_type'             => (string) \App\Support\Seo::get('og_type', 'website'),
            'og_url'              => (string) \App\Support\Seo::get('og_url', ''),
            'twitter_card'        => (string) \App\Support\Seo::get('twitter_card', 'summary_large_image'),
            'twitter_site'        => (string) \App\Support\Seo::get('twitter_site', ''),
            'twitter_creator'     => (string) \App\Support\Seo::get('twitter_creator', ''),
            'google_verification' => (string) \App\Support\Seo::get('google_verification', ''),
            'bing_verification'   => (string) \App\Support\Seo::get('bing_verification', ''),
        ];
        $brandName = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        return view('admin.settings.seo', compact('seo', 'brandName'));
    }

    public function settingSeoUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'meta_title'          => 'nullable|string|max:160',
            'meta_description'    => 'nullable|string|max:320',
            'meta_keywords'       => 'nullable|string|max:500',
            'robots'              => 'nullable|string|max:120',
            'canonical'           => 'nullable|url|max:300',
            'author'              => 'nullable|string|max:120',
            'og_title'            => 'nullable|string|max:160',
            'og_description'      => 'nullable|string|max:320',
            'og_image'            => 'nullable|url|max:500',
            'og_type'             => 'nullable|in:website,article,product,profile',
            'og_url'              => 'nullable|url|max:300',
            'twitter_card'        => 'nullable|in:summary,summary_large_image,app,player',
            'twitter_site'        => 'nullable|string|max:60',
            'twitter_creator'     => 'nullable|string|max:60',
            'google_verification' => 'nullable|string|max:200',
            'bing_verification'   => 'nullable|string|max:200',
        ]);

        foreach ($data as $key => $value) {
            \App\Models\SystemSetting::set('seo_' . $key, (string) ($value ?? ''), 'string', 'SEO ' . str_replace('_', ' ', $key));
        }
        \App\Support\Audit::log('settings.seo_update', ['meta' => ['fields' => array_keys($data)]]);
        return redirect()->route('admin.settings.seo')->with('success', __('SEO settings saved.'));
    }
    public function settingFooter(): View
    {
        $settings = [
            'footer_title'       => (string) \App\Models\SystemSetting::get('footer_title', ''),
            'footer_copyright'   => (string) \App\Models\SystemSetting::get('footer_copyright', ''),
            'footer_description' => (string) \App\Models\SystemSetting::get('footer_description', ''),
            // Social URLs share the site.* keys site_info()/the public footer read.
            'social_facebook'    => (string) site_info('social_facebook', ''),
            'social_linkedin'    => (string) site_info('social_linkedin', ''),
            'social_instagram'   => (string) site_info('social_instagram', ''),
            'social_x'           => (string) site_info('social_x', ''),
        ];
        return view('admin.settings.footer', compact('settings'));
    }

    public function settingFooterUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'footer_title'       => 'nullable|string|max:120',
            'footer_copyright'   => 'nullable|string|max:300',
            'footer_description' => 'nullable|string|max:500',
            'social_facebook'    => 'nullable|url|max:300',
            'social_linkedin'    => 'nullable|url|max:300',
            'social_instagram'   => 'nullable|url|max:300',
            'social_x'           => 'nullable|url|max:300',
        ]);

        // Footer text → dedicated SystemSetting keys (read by the public footer).
        foreach (['footer_title', 'footer_copyright', 'footer_description'] as $k) {
            \App\Models\SystemSetting::set($k, (string) ($data[$k] ?? ''), 'string', 'Footer ' . str_replace('footer_', '', $k));
        }
        // Social URLs → site.* keys: the SAME keys site_info() + the public footer
        // + the Site Settings page already read, so one edit updates everywhere.
        foreach (['facebook', 'linkedin', 'instagram', 'x'] as $net) {
            \App\Models\SystemSetting::set('site.social_' . $net, (string) ($data['social_' . $net] ?? ''), 'string', 'Footer social ' . $net);
        }

        \App\Support\Audit::log('settings.footer_update', ['meta' => ['fields' => array_keys($data)]]);
        return redirect()->route('admin.settings.footer')->with('success', __('Footer settings saved.'));
    }

    public function settingCustom(): View
    {
        $settings = [
            'custom_css'          => (string) \App\Models\SystemSetting::get('custom_css', ''),
            'custom_js'           => (string) \App\Models\SystemSetting::get('custom_js', ''),
            'custom_code_enabled' => (bool) \App\Models\SystemSetting::get('custom_code_enabled', false),
        ];
        return view('admin.settings.custom', compact('settings'));
    }

    public function settingCustomUpdate(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'custom_css'          => 'nullable|string|max:50000',
            'custom_js'           => 'nullable|string|max:50000',
            'custom_code_enabled' => 'nullable|boolean',
        ]);
        \App\Models\SystemSetting::set('custom_css', (string) ($data['custom_css'] ?? ''), 'string', 'Custom CSS injected into public pages');
        \App\Models\SystemSetting::set('custom_js', (string) ($data['custom_js'] ?? ''), 'string', 'Custom JS injected into public pages');
        \App\Models\SystemSetting::set('custom_code_enabled', $request->boolean('custom_code_enabled'), 'bool', 'Inject custom CSS/JS into public pages');
        \App\Support\Audit::log('settings.custom_update', ['meta' => ['css_len' => strlen((string) ($data['custom_css'] ?? '')), 'js_len' => strlen((string) ($data['custom_js'] ?? ''))]]);
        return redirect()->route('admin.settings.custom')->with('success', __('Custom code saved.'));
    }
}
