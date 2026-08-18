<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * /webhooks — adapted from D:\wadesk_2806\New folder\app\Http\Controllers\WebhookController.php
 * The old controller exposed index/create/store/edit/update/destroy. We
 * fold the create/edit forms into separate methods, keep all listing
 * logic AJAX-friendly (?partial=1) the same way the rest of this app
 * does it (templates, broadcasts, devices, etc.), and pull stats out
 * of the deliveries table so the KPI strip + event-mix + recent-
 * deliveries panels are real.
 */
class WebhooksController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->string('status')->toString() ?: 'all';
        $q      = $request->string('q')->toString();

        $hooks = Webhook::query()
            ->forCurrentWorkspace()
            ->orderByDesc('id')
            ->get()
            ->filter(function ($h) use ($status, $q) {
                if ($status === 'active'  && $h->state_label !== 'active')  return false;
                if ($status === 'paused'  && $h->state_label !== 'paused')  return false;
                if ($status === 'failing' && $h->state_label !== 'failing') return false;
                if ($q !== '') {
                    $hay = strtolower($h->name . ' ' . $h->webhook_url . ' ' . implode(' ', $h->events ?? []));
                    if (!str_contains($hay, strtolower($q))) return false;
                }
                return true;
            })
            ->values();
        $hooks = $this->paginateCollection($hooks, $request, 12);

        $stats        = $this->kpiStats($userId);
        $statusCounts = $this->statusCounts($userId);
        $eventMix     = $this->eventMix($userId);
        $recent       = $this->recentDeliveries($userId, 8);

        if ($request->boolean('partial')) {
            return response()->json([
                'ok'            => true,
                'rows'          => view('user.webhooks._rows', compact('hooks'))->render(),
                'stats'         => $stats,
                'statusCounts'  => $statusCounts,
                'eventMix'      => $eventMix,
                'recent'        => view('user.webhooks._recent', compact('recent'))->render(),
                'pagination'    => view('user.partials.pagination', ['paginator' => $hooks, 'dataAttr' => 'data-wh-page', 'label' => 'webhooks'])->render(),
                'shown'         => $hooks->count(),
                'total'         => $hooks->total(),
                'page'          => $hooks->currentPage(),
            ]);
        }

        return view('user.webhooks.index', [
            'hooks'         => $hooks,
            'stats'         => $stats,
            'statusCounts'  => $statusCounts,
            'eventMix'      => $eventMix,
            'recent'        => $recent,
            'currentStatus' => $status,
            'currentQuery'  => $q,
        ]);
    }

    public function create(): View
    {
        return view('user.webhooks.create', [
            'events' => WebhookService::availableEvents(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Plan: feature flag + numeric cap.
        $ws = $request->user()?->currentWorkspace;
        \App\Services\PlanLimitGuard::feature($ws, 'access_outbound_webhooks');
        // Plan limit per-workspace.
        \App\Services\PlanLimitGuard::check(
            $ws, 'webhooks_limit',
            \App\Models\OutboundWebhook::where('workspace_id', $request->user()->current_workspace_id)->count(),
        );

        $data = $request->validate([
            'name'        => 'nullable|string|max:191',
            'environment' => 'nullable|string|max:64',
            'http_method' => 'nullable|string|in:POST,PUT',
            'webhook_url' => 'required|url',
            'events'      => 'required|array|min:1',
            'events.*'    => 'string|max:64',
            'secret'      => 'nullable|string|max:191',
            'status'      => 'nullable|boolean',
        ]);

        // Security policy: when signing is required, every endpoint must carry
        // a secret so receivers can verify the X-WaDesk-Signature.
        if (\App\Support\SecurityPolicy::bool('webhook_signature_required', true) && empty($data['secret'])) {
            return back()->withInput()->withErrors(['secret' => 'A signing secret is required by the platform security policy.']);
        }

        Webhook::create([
            'user_id'      => Auth::id(),
            'workspace_id' => Auth::user()->current_workspace_id,
            'name'         => $data['name']        ?? null,
            'environment'  => $data['environment'] ?? 'Production',
            'http_method'  => $data['http_method'] ?? 'POST',
            'webhook_url'  => $data['webhook_url'],
            'events'       => $data['events'],
            'secret'       => $data['secret'] ?? null,
            'status'       => (bool) ($data['status'] ?? true),
        ]);

        return redirect()->route('user.webhooks.index')->with('status', 'Webhook saved.');
    }

    public function show(int $id): View
    {
        $hook = Webhook::query()->forCurrentWorkspace()->findOrFail($id);

        // Hydrate the last 7 days of deliveries for both the table and the
        // analytics aggregates. 7d not 30d because this is a per-firing
        // endpoint — the volumes can be high.
        $allDeliveries = WebhookDelivery::query()
            ->where('webhook_id', $hook->id)
            ->where('fired_at', '>=', now()->subDays(7))
            ->orderByDesc('fired_at')
            ->limit(2000)
            ->get();

        // Recent rows shown in the table — last 40 visually.
        $deliveries = $allDeliveries->take(40);

        $total       = $allDeliveries->count();
        $byCode      = $allDeliveries->groupBy(fn ($d) => $this->codeBucket($d->status_code));
        $count2xx    = ($byCode->get('2xx') ?? collect())->count();
        $count4xx    = ($byCode->get('4xx') ?? collect())->count();
        $count5xx    = ($byCode->get('5xx') ?? collect())->count();
        $countOther  = ($byCode->get('other') ?? collect())->count();

        $latencies = $allDeliveries->pluck('latency_ms')->filter()->values();
        $p95       = $latencies->isNotEmpty()
            ? (int) ($latencies->sort()->values()->get((int) floor($latencies->count() * 0.95)) ?? $latencies->max())
            : null;

        $retries   = $allDeliveries->where('is_retry', true)->count();
        $failed    = $allDeliveries->whereNotIn('status_code', range(200, 299))->count();

        // Daily series for the activity chart — 7 days, success vs failure.
        $byDay = $allDeliveries->groupBy(fn ($d) => optional($d->fired_at)->toDateString());
        $days  = collect();
        for ($i = 6; $i >= 0; $i--) {
            $key  = now()->subDays($i)->toDateString();
            $rows = $byDay->get($key, collect());
            $days->push([
                'date'    => $key,
                'success' => $rows->whereBetween('status_code', [200, 299])->count(),
                'failure' => $rows->where(fn ($r) => $r->status_code === null || $r->status_code >= 400)->count(),
            ]);
        }

        $analytics = [
            'total7d'      => $total,
            'success_pct'  => $total ? round($count2xx / $total * 100, 1) : null,
            'p95_ms'       => $p95,
            'retries'      => $retries,
            'failed'       => $failed,
            'codes'        => [
                ['label' => '200 OK', 'count' => $count2xx, 'cls' => 'bg-wa-deep'],
                ['label' => '4xx',    'count' => $count4xx, 'cls' => 'bg-accent-amber'],
                ['label' => '5xx',    'count' => $count5xx, 'cls' => 'bg-accent-coral'],
                ['label' => 'other',  'count' => $countOther, 'cls' => 'bg-paper-200'],
            ],
            'days'         => $days,
        ];

        return view('user.webhooks.detail', compact('hook', 'deliveries', 'analytics'));
    }

    /**
     * Bucket an HTTP status code into 2xx/4xx/5xx/other for the charts.
     */
    private function codeBucket(?int $code): string
    {
        if ($code === null)            return 'other';
        if ($code >= 200 && $code < 300) return '2xx';
        if ($code >= 400 && $code < 500) return '4xx';
        if ($code >= 500 && $code < 600) return '5xx';
        return 'other';
    }

    public function edit(int $id): View
    {
        $hook = Webhook::query()->forCurrentWorkspace()->findOrFail($id);
        return view('user.webhooks.create', [
            'events' => WebhookService::availableEvents(),
            'hook'   => $hook,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $hook = Webhook::query()->forCurrentWorkspace()->findOrFail($id);
        $data = $request->validate([
            'name'        => 'nullable|string|max:191',
            'environment' => 'nullable|string|max:64',
            'http_method' => 'nullable|string|in:POST,PUT',
            'webhook_url' => 'required|url',
            'events'      => 'required|array|min:1',
            'events.*'    => 'string|max:64',
            'secret'      => 'nullable|string|max:191',
            'status'      => 'nullable|boolean',
        ]);
        $hook->update($data);
        return redirect()->route('user.webhooks.index')->with('status', 'Webhook updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Webhook::query()->forCurrentWorkspace()->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function toggle(int $id): JsonResponse
    {
        $hook = Webhook::query()->forCurrentWorkspace()->findOrFail($id);
        $hook->update(['status' => !$hook->status]);
        return response()->json(['ok' => true, 'status' => $hook->status]);
    }

    public function testFire(int $id): JsonResponse
    {
        $hook = Webhook::query()->forCurrentWorkspace()->findOrFail($id);
        $delivery = WebhookService::testFire($hook);
        return response()->json([
            'ok'         => true,
            'statusCode' => $delivery->status_code,
            'latencyMs'  => $delivery->latency_ms,
            'isOk'       => $delivery->is_success,
        ]);
    }

    /**
     * POST /webhooks/test-fire-draft — fire a single test payload to an
     * UNSAVED endpoint. The /webhooks/create page hits this so an operator
     * can verify the URL + secret BEFORE saving (and polluting the list
     * with abandoned drafts). The request never touches the DB — same
     * payload shape as the saved-webhook test fire, but no Webhook /
     * WebhookDelivery row is written.
     *
     * Body:
     *   webhook_url   (string, required, url)
     *   secret        (string, optional) — signs the body with HMAC-SHA256
     *                                      under the brand's signature header
     *   http_method   (string, optional) POST | PUT (default POST)
     *   events[]      (string[], optional) — included in the test payload's
     *                                        `events` field for diagnostic
     */
    public function testFireDraft(\Illuminate\Http\Request $request): JsonResponse
    {
        $data = $request->validate([
            'webhook_url' => 'required|url|max:2048',
            'secret'      => 'nullable|string|max:191',
            'http_method' => 'nullable|in:POST,PUT',
            'events'      => 'nullable|array',
            'events.*'    => 'string|max:64',
        ]);

        // Build the same shape WebhookService::testFire emits so the
        // operator's endpoint sees a payload identical to what real
        // event firings will look like.
        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $payload = [
            'event'     => 'test_fire',
            'sent_at'   => now()->toIso8601String(),
            'app'       => (string) $appName,
            'data'      => [
                'message'        => 'Hello from ' . $appName . '. This is a test fire from /webhooks/create (draft, unsaved).',
                'user_id'        => $request->user()?->id,
                'workspace_id'   => (int) ($request->user()?->current_workspace_id ?? 0),
                'subscribed_to'  => $data['events'] ?? [],
            ],
        ];

        $headers = ['Content-Type' => 'application/json'];
        if (! empty($data['secret'])) {
            // Brand-prefixed signature header — same as production deliveries.
            $headers[\App\Support\Brand::webhookSignatureHeader()] = hash_hmac('sha256', json_encode($payload), $data['secret']);
        }

        $method = strtoupper($data['http_method'] ?? 'POST');
        $start  = microtime(true);
        $code   = null;
        $error  = null;
        $body   = null;

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders($headers);
            $r = $method === 'PUT'
                ? $resp->put($data['webhook_url'], $payload)
                : $resp->post($data['webhook_url'], $payload);
            $code = $r->status();
            $body = mb_substr((string) $r->body(), 0, 500);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        $isOk      = $code !== null && $code >= 200 && $code < 300;

        return response()->json([
            'ok'          => true,
            'isOk'        => $isOk,
            'statusCode'  => $code,
            'latencyMs'   => $latencyMs,
            'error'       => $error,
            'body_excerpt'=> $body,
            'sent_payload'=> $payload,
            'signed'      => ! empty($data['secret']),
        ]);
    }

    private function kpiStats(?int $userId): array
    {
        $endpoints = Webhook::query()->forCurrentWorkspace()->count();
        $active    = Webhook::query()->forCurrentWorkspace()->where('status', true)->where('is_failing', false)->count();
        $paused    = Webhook::query()->forCurrentWorkspace()->where('status', false)->count();
        $since     = now()->subDay();

        $deliveriesQuery = WebhookDelivery::query()
            ->whereHas('webhook', fn ($w) => $w->forCurrentWorkspace())
            ->where('fired_at', '>=', $since);

        $events24 = (clone $deliveriesQuery)->count();
        $ok24     = (clone $deliveriesQuery)->whereBetween('status_code', [200, 299])->count();
        $rate     = $events24 === 0 ? 100.0 : round(($ok24 / $events24) * 100, 1);
        $latP95   = (clone $deliveriesQuery)
            ->whereNotNull('latency_ms')
            ->orderByDesc('latency_ms')
            ->limit(max(1, (int) ceil($events24 * 0.05)))
            ->avg('latency_ms');
        $latP95   = $latP95 ? (int) round($latP95) : 0;

        return [
            'endpoints'   => $endpoints,
            'active'      => $active,
            'paused'      => $paused,
            'events24'    => $events24,
            'successRate' => $rate,
            'latencyP95'  => $latP95,
        ];
    }

    private function statusCounts(?int $userId): array
    {
        $all = Webhook::query()->forCurrentWorkspace()->get();
        return [
            'all'     => $all->count(),
            'active'  => $all->where('state_label', 'active')->count(),
            'paused'  => $all->where('state_label', 'paused')->count(),
            'failing' => $all->where('state_label', 'failing')->count(),
        ];
    }

    private function eventMix(?int $userId): array
    {
        $since = now()->subDay();
        $rows = WebhookDelivery::query()
            ->selectRaw('event_name, COUNT(*) as c')
            ->whereHas('webhook', fn ($w) => $w->forCurrentWorkspace())
            ->where('fired_at', '>=', $since)
            ->groupBy('event_name')
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        $max = $rows->max('c') ?: 1;
        $palette = ['bg-wa-deep', 'bg-wa-teal', 'bg-accent-amber', 'bg-[#5B3D8A]', 'bg-accent-coral'];
        return $rows->values()->map(fn ($r, $i) => [
            'name'  => $r->event_name,
            'count' => (int) $r->c,
            'pct'   => max(2, (int) round(($r->c / $max) * 100)),
            'color' => $palette[$i % count($palette)],
        ])->all();
    }

    private function recentDeliveries(?int $userId, int $limit = 8)
    {
        return WebhookDelivery::query()
            ->whereHas('webhook', fn ($w) => $w->forCurrentWorkspace())
            ->with('webhook')
            ->orderByDesc('fired_at')
            ->limit($limit)
            ->get();
    }
}
