<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the Laravel session cookie usable when the app is loaded INSIDE the
 * Shopify admin iframe (our app is an embedded Shopify app).
 *
 * A cross-site iframe only sends our session cookie back on the login POST if it
 * was set with SameSite=None; Secure. The default SameSite=Lax is dropped in that
 * third-party context, so the session that held the CSRF token is gone by the
 * time the embedded login form is submitted — which surfaces as a 419
 * "This page has expired" (CSRF token mismatch) the instant the user clicks
 * Sign in inside Shopify.
 *
 * This runs PREPENDED to the `web` group (before StartSession) so the config
 * change actually reaches the session cookie when it is queued. It is scoped to
 * the Shopify embed ONLY: entry from Shopify carries ?host/&shop/&embedded, and
 * we drop a tiny plaintext marker cookie so the auth redirect to /login and the
 * login POST stay recognised as embed context. Every non-embedded request keeps
 * the stricter SameSite=Lax it has today.
 */
class EmbeddedShopifySession
{
    /** Plaintext (see EncryptCookies except) marker that carries the embed context forward. */
    public const MARKER = 'chatkar_embedded';

    public function handle(Request $request, Closure $next): Response
    {
        $embed = $this->isEmbed($request);

        // Only relax over HTTPS: SameSite=None REQUIRES Secure, and forcing a
        // secure cookie on plain HTTP (local dev) would drop the session entirely.
        if ($embed && $request->isSecure()) {
            config([
                'session.same_site' => 'none',
                'session.secure'    => true,
            ]);
        }

        $response = $next($request);

        // Persist the embed context across the auth redirect (/shopify -> /login)
        // and the login POST, so those requests are detected too.
        if ($embed && $request->isSecure() && ! $request->cookies->has(self::MARKER)) {
            $response->headers->setCookie(
                new Cookie(self::MARKER, '1', 0, '/', null, true, true, false, Cookie::SAMESITE_NONE)
            );
        }

        return $response;
    }

    /**
     * True ONLY for a genuine Shopify embed. Scoped tightly so a bare
     * attacker-supplied `?embedded=1` on an arbitrary path can NOT downgrade the
     * session cookie to SameSite=None across the whole app (which would strip the
     * Lax defence-in-depth for the victim's session everywhere).
     *
     * Recognised contexts:
     *   - the embedded-app surfaces themselves (/shopify, /shopify/*),
     *   - any navigation carrying Shopify's own host + shop params, and
     *   - the login handshake (/login GET -> /login POST -> back to /shopify)
     *     ONLY while the embed marker cookie is present. The marker is deliberately
     *     NOT honoured on ordinary app pages, so a stray marker cannot keep the
     *     downgrade alive for the rest of the browser session.
     */
    private function isEmbed(Request $request): bool
    {
        $path = ltrim($request->getPathInfo(), '/');

        if ($path === 'shopify' || str_starts_with($path, 'shopify/')) {
            return true;
        }

        // Shopify appends host + shop on every embedded navigation.
        if ($request->filled('host') && $request->filled('shop')) {
            return true;
        }

        // Carry the embed context across the login handshake only.
        if ($request->cookies->has(self::MARKER) && $this->isAuthPath($path)) {
            return true;
        }

        return false;
    }

    /** Login/auth handshake paths that the embed marker may bridge. */
    private function isAuthPath(string $path): bool
    {
        foreach (['login', 'logout', 'register', 'two-factor', 'forgot-password', 'reset-password', 'password'] as $p) {
            if ($path === $p || str_starts_with($path, $p.'/')) {
                return true;
            }
        }

        return false;
    }
}
