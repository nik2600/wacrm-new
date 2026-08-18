<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Live-demo guard (DEMO_MODE=true).
 *
 * The demo stays explorable — visitors can browse everything (GET) AND use most
 * features (create/edit their own workspace content). We only block the things
 * that are DESTRUCTIVE or GLOBAL or sensitive, so a client can't break the demo
 * or touch the platform:
 *
 *   1. Every DELETE — nothing can be deleted, anywhere.
 *   2. Admin platform config + branding/logo + the frontend editor — GLOBAL,
 *      affects everyone (admin/settings, payment-gateways, currencies, languages,
 *      packages/pricing, security, api-keys, wallet, site-settings, frontend).
 *   3. User & workspace management — suspend / block / role / reset (affects
 *      other accounts).
 *   4. The password / passcode / 2FA system.
 *
 * Everything else (a demo user creating a campaign, editing a contact, etc.)
 * keeps working. Guests are never blocked (login / register / contact / reset).
 * Off by default; flip DEMO_MODE=true only on the demo server.
 */
class DemoMode
{
    /** Essentials that always stay writable, even in demo. */
    private array $allowPaths = [
        'logout', 'locale*', 'lang*', 'set-locale*', 'language*', 'theme*',
    ];

    /**
     * GLOBAL / sensitive paths — blocked for POST/PUT/PATCH (DELETE is blocked
     * everywhere regardless). These mutate platform-wide config, branding, other
     * users, or the security system.
     */
    private array $blockPaths = [
        // Admin platform config (global, affects everyone)
        'admin/settings*', 'admin/site-settings*', 'admin/security*',
        'admin/payment-gateways*', 'admin/currencies*', 'admin/languages*',
        'admin/translation-providers*', 'admin/api-keys*', 'admin/wallet-rules*',
        // Branding / logo / public frontend content
        'admin/frontend*',
        // Plans / pricing / coupons (global billing)
        'admin/packages*', 'admin/credit-packages*', 'admin/coupons*',
        // User + workspace management (touches other accounts)
        'admin/users*', 'admin/workspaces*',
        // Password / passcode / 2FA system
        '*password*', '*passcode*', '*two-factor*', '*2fa*', 'account/security*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        // GET / HEAD / OPTIONS always browse.
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // Guests keep full access (login / register / password reset / contact).
        if (! $request->user()) {
            return $next($request);
        }

        // Essentials (logout, locale/theme switch) always work.
        if ($request->is(...$this->allowPaths)) {
            return $next($request);
        }

        // Block: every delete, plus the global/sensitive blocklist. Everything
        // else (own-workspace create/edit) is allowed so the demo is usable.
        $isDelete = $request->method() === 'DELETE';
        if ($isDelete || $request->is(...$this->blockPaths)) {
            return $this->deny($request, $isDelete);
        }

        return $next($request);
    }

    private function enabled(): bool
    {
        return (bool) config('app.demo_mode', false);
    }

    private function deny(Request $request, bool $isDelete): Response
    {
        $message = $isDelete
            ? __('Deleting is disabled in this live demo.')
            : __('This is a live demo — changing platform settings, branding, billing, users and passwords is disabled.');

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            // Front-end handlers variously read `error`, `message`, or `status`
            // — send the friendly demo text under all of them so a block shows
            // "disabled in the demo", not a generic "… failed".
            return response()->json([
                'ok'      => false,
                'demo'    => true,
                'status'  => 'error',
                'message' => $message,
                'error'   => $message,
            ], 422);
        }

        return back()->withInput()->with('error', $message)->with('demo', true);
    }
}
