<?php

namespace App\Http\Middleware;

use App\Models\ChatbotWidget;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS handler for /widget/{token}/api/* public endpoints.
 *
 * The chatbot widget runs inside the operator's third-party site via an
 * iframe. Every fetch() from that iframe is a cross-origin request to
 * our app, so the browser enforces CORS preflight + headers. Without
 * this middleware the embed silently failed on every site that wasn't
 * served from the same host as WaDesk — operators saw "CORS error" in
 * the browser console and no message ever reached their inbox.
 *
 * Strategy:
 *   1. Resolve the widget by {token} from the route binding.
 *   2. Read the request's Origin header.
 *   3. If the widget's `allowed_domains` list permits this origin,
 *      echo it back in `Access-Control-Allow-Origin` so the browser
 *      releases the response. Otherwise the embed gets a 403 with
 *      `cors_origin_denied` so the operator's console shows the real
 *      reason (not the generic browser CORS message).
 *   4. Handle OPTIONS preflight returning 204 with the same headers.
 *
 * Credentials (`Access-Control-Allow-Credentials: true`) is enabled so
 * the visitor session cookie flows back and forth — the widget keeps
 * conversation state across page loads. That forces us to echo the
 * exact origin (no `*`), which is what `originAllowed()` resolves.
 */
class ChatbotWidgetCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $token  = (string) $request->route('token');
        $origin = $request->headers->get('Origin');

        // No Origin header → same-origin or non-browser caller. Skip
        // CORS handling and let the controller handle auth normally.
        // (curl, the visitor's chat iframe loaded directly, etc.)
        if (!$origin) {
            return $next($request);
        }

        $widget = $token ? ChatbotWidget::where('embed_token', $token)->first() : null;
        if (!$widget) {
            // Don't leak token existence via different error codes —
            // 403 with no CORS headers, browser will block. Controller
            // will also 404 the request body.
            return response()->json(['ok' => false, 'error' => 'widget_not_found'], 404);
        }

        $allowed = $widget->originAllowed($origin);

        // Preflight: respond immediately so the browser unlocks the
        // actual request. Mirrors the headers we set on the real
        // response below.
        if ($request->isMethod('OPTIONS')) {
            $resp = response('', 204);
            return $this->applyCorsHeaders($resp, $origin, $allowed, $request);
        }

        if (!$allowed) {
            // Block the request entirely + tell the operator's console
            // exactly what to add to the widget's allow-list.
            $resp = response()->json([
                'ok'    => false,
                'error' => 'cors_origin_denied',
                'message' => "This widget isn't allowed to load from {$origin}. Add the domain to the widget's allow-list.",
            ], 403);
            // Don't set Access-Control-Allow-Origin — the browser will
            // surface the CORS error so the operator sees both signals.
            return $resp;
        }

        // Allowed origin — process the request and stamp CORS headers
        // on the response so the browser lets the JS read the body.
        $response = $next($request);
        return $this->applyCorsHeaders($response, $origin, true, $request);
    }

    private function applyCorsHeaders(Response $response, string $origin, bool $allowed, Request $request): Response
    {
        if (!$allowed) return $response;

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        // Echo any requested headers in the preflight to keep
        // operator-defined custom headers (e.g. X-Widget-Session)
        // working without us maintaining a hardcoded allow-list.
        $requested = $request->headers->get('Access-Control-Request-Headers', 'Content-Type, X-Requested-With, X-Widget-Session');
        $response->headers->set('Access-Control-Allow-Headers', $requested);
        // Cache the preflight for 1 hour so the browser doesn't repeat
        // it for every message during an active chat. Don't cache too
        // long — operators rotate the embed token to revoke access.
        $response->headers->set('Access-Control-Max-Age', '3600');
        // Vary on Origin so a CDN doesn't serve site A's CORS headers
        // to a request that originated from site B.
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
