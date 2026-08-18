<?php

namespace App\Http\Controllers;

use App\Models\IncomingWebhook;
use App\Models\IncomingWebhookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Incoming (inbound) webhooks. The workspace generates a URL here, hands
 * it to any external service, and we:
 *   1. capture every request that hits /hooks/in/{token} into
 *      incoming_webhook_events (so the operator can inspect the payload),
 *   2. optionally relay it on to a forward_url ("send to their location").
 *
 * The public `receive()` action is session-less + CSRF-exempt (the random
 * token is the access control). Everything else is workspace-scoped under
 * the authenticated /webhooks group.
 */
class IncomingWebhookController extends Controller
{
    /** Keep at most this many captured events per hook (newest wins). */
    private const MAX_EVENTS = 100;

    // -----------------------------------------------------------------
    // Public receiver — external services POST here.
    // -----------------------------------------------------------------
    public function receive(Request $request, string $token): JsonResponse
    {
        $hook = IncomingWebhook::query()->where('token', $token)->first();
        if (!$hook || !$hook->is_active) {
            return response()->json(['ok' => false, 'error' => 'unknown or inactive webhook'], 404);
        }

        // Capture a safe subset of headers (drop cookies / auth so we never
        // persist the caller's credentials in plaintext).
        $headers = [];
        foreach ($request->headers->all() as $k => $v) {
            $kl = strtolower($k);
            if (in_array($kl, ['cookie', 'authorization', 'x-csrf-token', 'x-xsrf-token'], true)) continue;
            $headers[$k] = is_array($v) ? implode(', ', $v) : (string) $v;
        }
        $raw = (string) $request->getContent();
        // Cap stored body at 64 KB so a giant POST can't bloat the table.
        $payload = mb_strlen($raw) > 65536 ? mb_substr($raw, 0, 65536) . "\n…(truncated)" : $raw;

        $event = IncomingWebhookEvent::create([
            'incoming_webhook_id' => $hook->id,
            'method'              => substr($request->method(), 0, 8),
            'source_ip'           => $request->ip(),
            'content_type'        => substr((string) $request->header('Content-Type', ''), 0, 191) ?: null,
            'headers'             => $headers,
            'payload'             => $payload,
            'received_at'         => now(),
        ]);

        $hook->forceFill([
            'received_count'   => $hook->received_count + 1,
            'last_received_at' => now(),
        ])->save();

        // Relay onward to the operator's destination, best-effort. Failures
        // are recorded on the event, never block the 200 to the caller.
        if ($hook->forward_enabled && !empty($hook->forward_url)) {
            // SSRF guard: forward_url is operator-supplied. Re-validate at relay
            // time (not just save time — DNS can change) and refuse private/
            // loopback/link-local/reserved targets so this public token endpoint
            // can't be used to probe or POST into internal services (IMDS, redis,
            // localhost admin, …). Redirects are disabled so a public host can't
            // 302 us onto an internal one.
            $ssrfErr = $this->guardSsrf((string) $hook->forward_url);
            if ($ssrfErr) {
                $event->forceFill([
                    'forwarded'     => true,
                    'forward_error' => mb_substr('forward blocked: ' . $ssrfErr, 0, 500),
                ])->save();
            } else {
                try {
                    $resp = Http::timeout(10)
                        ->withOptions(['allow_redirects' => false])
                        ->withHeaders(['Content-Type' => $request->header('Content-Type', 'application/json')])
                        ->withBody($raw, $request->header('Content-Type', 'application/json'))
                        ->post($hook->forward_url);
                    $event->forceFill([
                        'forwarded'      => true,
                        'forward_status' => $resp->status(),
                    ])->save();
                } catch (\Throwable $e) {
                    $event->forceFill([
                        'forwarded'     => true,
                        'forward_error' => mb_substr($e->getMessage(), 0, 500),
                    ])->save();
                }
            }
        }

        // Prune old events beyond the cap.
        $keepIds = IncomingWebhookEvent::where('incoming_webhook_id', $hook->id)
            ->orderByDesc('id')->limit(self::MAX_EVENTS)->pluck('id');
        IncomingWebhookEvent::where('incoming_webhook_id', $hook->id)
            ->whereNotIn('id', $keepIds)->delete();

        return response()->json(['ok' => true, 'received' => true, 'event_id' => $event->id]);
    }

    // -----------------------------------------------------------------
    // Authenticated UI (workspace-scoped, /webhooks/incoming).
    // -----------------------------------------------------------------
    public function index(Request $request): View
    {
        $hooks = IncomingWebhook::query()
            ->forCurrentWorkspace()
            ->with(['events' => fn ($q) => $q->limit(20)])
            ->orderByDesc('id')
            ->get();

        return view('user.webhooks.incoming', ['hooks' => $hooks]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:128',
        ]);

        IncomingWebhook::create([
            'workspace_id' => Auth::user()->current_workspace_id,
            'user_id'      => Auth::id(),
            'name'         => $data['name'] ?? 'Incoming webhook',
            'token'        => Str::random(40),
            'is_active'    => true,
        ]);

        return redirect()->route('user.webhooks.incoming')
            ->with('status', 'Incoming webhook generated — copy its URL below.');
    }

    public function forward(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'forward_url'     => 'nullable|url|max:1024',
            'forward_enabled' => 'nullable|boolean',
        ]);
        $hook = $this->resolve($id);
        $enabled = (bool) ($data['forward_enabled'] ?? false);
        if ($enabled && empty($data['forward_url'])) {
            return back()->withErrors(['forward_url' => 'Enter a destination URL to forward to.']);
        }
        // SSRF guard at save time — reject private/loopback/link-local/reserved
        // destinations up front (relay time re-checks too, in case DNS changes).
        if (!empty($data['forward_url'])) {
            $ssrfErr = $this->guardSsrf($data['forward_url']);
            if ($ssrfErr) {
                return back()->withErrors(['forward_url' => 'That destination is not allowed: ' . $ssrfErr]);
            }
        }
        $hook->update([
            'forward_url'     => $data['forward_url'] ?: null,
            'forward_enabled' => $enabled,
        ]);
        return back()->with('status', 'Forwarding settings saved.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $hook = $this->resolve($id);
        $hook->update(['is_active' => !$hook->is_active]);
        return back()->with('status', $hook->is_active ? 'Webhook activated.' : 'Webhook paused.');
    }

    public function clear(int $id): RedirectResponse
    {
        $hook = $this->resolve($id);
        IncomingWebhookEvent::where('incoming_webhook_id', $hook->id)->delete();
        return back()->with('status', 'Captured events cleared.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $hook = $this->resolve($id);
        IncomingWebhookEvent::where('incoming_webhook_id', $hook->id)->delete();
        $hook->delete();
        return back()->with('status', 'Incoming webhook deleted.');
    }

    /** Recent events for the live inspector (AJAX poll). */
    public function eventsJson(int $id): JsonResponse
    {
        $hook = $this->resolve($id);
        $events = IncomingWebhookEvent::where('incoming_webhook_id', $hook->id)
            ->orderByDesc('id')->limit(20)->get()
            ->map(fn ($e) => [
                'id'         => $e->id,
                'method'     => $e->method,
                'ip'         => $e->source_ip,
                'type'       => $e->content_type,
                'at'         => optional($e->received_at)->diffForHumans(),
                'forwarded'  => $e->forwarded,
                'fwd_status' => $e->forward_status,
                'preview'    => Str::limit((string) $e->payload, 280),
            ]);
        return response()->json([
            'ok'             => true,
            'received_count' => $hook->received_count,
            'events'         => $events,
        ]);
    }

    /** Workspace-scoped fetch (404 otherwise). */
    private function resolve(int $id): IncomingWebhook
    {
        return IncomingWebhook::query()->forCurrentWorkspace()->findOrFail($id);
    }

    /**
     * SSRF guard for the operator-supplied forward_url.
     *
     * Returns NULL when the URL is safe to relay to, or a human-readable
     * error string when it must be refused. Refuses non-http(s) schemes and
     * any hostname that resolves to a private/loopback/link-local/reserved IP
     * (RFC1918, 127.0.0.0/8, 169.254.0.0/16 incl. the cloud metadata IP, ::1,
     * fc00::/7, etc.). Mirrors AiTrainingController::guardSsrf so both
     * outbound-fetch paths fail closed the same way.
     */
    private function guardSsrf(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return 'invalid URL';
        }
        $scheme = strtolower($p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return "scheme {$scheme} not allowed (use http or https)";
        }
        $host = strtolower($p['host']);
        if (str_contains($host, 'metadata.') || str_ends_with($host, '.internal')) {
            return 'metadata host not allowed';
        }
        $ips = @gethostbynamel($host) ?: [];
        if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
        if (empty($ips)) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            foreach ((array) $aaaa as $rec) {
                if (!empty($rec['ipv6'])) $ips[] = $rec['ipv6'];
            }
        }
        if (empty($ips)) {
            return 'hostname did not resolve to a public IP';
        }
        foreach ($ips as $ip) {
            $public = filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) {
                return "host resolves to private/reserved IP ({$ip}) — refusing to forward";
            }
        }
        return null;
    }
}
