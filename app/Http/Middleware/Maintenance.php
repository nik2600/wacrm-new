<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\PlatformPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the admin "Maintenance mode" toggle (SystemSetting `maintenance_mode`,
 * set in Admin → Settings → General). When ON, the whole customer-facing app
 * returns the branded 503 page — EXCEPT:
 *
 *   - Platform staff (Super Admin / Admin / Support / Auditor) always pass, so
 *     they can keep working and turn maintenance back off.
 *   - The auth surface (login / logout / social callback / csrf token) stays
 *     open, so an admin who isn't logged in can sign in from the 503 screen's
 *     "Admin sign in" button and then get through.
 *   - Static assets + the health check stay reachable so the 503 page renders.
 *
 * The toggle previously did nothing because no middleware read it — this is the
 * missing enforcement.
 */
class Maintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before install there's no DB / settings table to read.
        if (! is_file(storage_path('installed'))) {
            return $next($request);
        }

        if (! (bool) SystemSetting::get('maintenance_mode', false)) {
            return $next($request);
        }

        // Platform staff bypass entirely (also covers an admin impersonating a
        // customer — they still need to be able to work).
        if (Auth::check() && PlatformPermissions::userHasPlatformAccess(Auth::user())) {
            return $next($request);
        }

        // Keep the login path + assets reachable so an admin CAN sign in from the
        // maintenance screen, and so that screen renders with its styling.
        if ($this->allowedWhileDown($request)) {
            return $next($request);
        }

        // JSON/API callers get a clean 503 they can handle.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('The service is temporarily down for maintenance. Please try again shortly.'),
            ], 503)->header('Retry-After', '3600');
        }

        return response()->view('errors.503', ['maintenance' => true], 503)
            ->header('Retry-After', '3600');
    }

    /**
     * Paths that must stay open while maintenance is on — the auth surface (so an
     * admin can log in), static assets, the health check, and every
     * MACHINE-TO-MACHINE callback.
     *
     * The callbacks are not a convenience: they are data we can never get back.
     * Meta, Twilio, Stripe/Razorpay, Shopify and Woo all POST once and retry only
     * a few times before giving up permanently. A 503 here silently drops
     * delivery/read receipts, inbound customer messages and paid-order
     * notifications — and because platform staff bypass this middleware, the
     * operator sees a perfectly healthy app while it happens.
     *
     * That was the bug: campaigns showed "sent" but never advanced to delivered,
     * and inbound stopped arriving, on installs where maintenance mode had been
     * left on. Senders are authenticated by their own signature (Meta's
     * X-Hub-Signature-256, Shopify/Woo HMAC, the node token), so exempting them
     * costs nothing — maintenance mode is about keeping HUMANS out of the UI.
     */
    private function allowedWhileDown(Request $request): bool
    {
        return $request->is(
            'login',
            'logout',
            'auth/*',           // social sign-in redirect + callback
            'csrf-token',
            'up',               // health check
            'build/*',          // Vite assets
            'css/*', 'js/*', 'images/*', 'fonts/*', 'storage/*',
            'favicon.ico', 'robots.txt',

            // ── Machine-to-machine: never 503 these ──────────────────────────
            'webhooks/*',           // Meta inbound + statuses, wa-calling, slack, trello, storefront-pay
            'hooks/in/*',           // operator-defined incoming webhooks
            'api/*',                // Node bridge callbacks (token-authed)
            'shopify/webhook/*',
            'woocommerce/webhook/*',
            'payment/webhook/*',
            'payment/callback/*',   // gateway returns the buyer here after paying
            'wd-sync',
        );
    }
}
