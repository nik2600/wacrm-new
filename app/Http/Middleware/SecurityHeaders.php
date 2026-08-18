<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response security headers (clickjacking + transport hardening) for the `web`
 * middleware group. Runs on authenticated AND public HTML responses.
 *
 * Framing defaults to SAME-ORIGIN (X-Frame-Options + CSP frame-ancestors 'self')
 * so a malicious site can no longer frame an authenticated page (/admin, billing,
 * workspace management) and clickjack a logged-in operator. The two legitimate
 * SAME-origin iframes the app already uses — the frontend live-editor and the
 * /devices?embed=1 connect sheet — keep working because they share our origin.
 *
 * Two legitimate CROSS-origin embeds are deliberately exempted from the framing
 * lock so this hardening does not break them:
 *   1. The chatbot widget page (/widget/{token}/chat) is embedded by customers on
 *      their own websites — its access control is the embed token + widget.cors.
 *   2. The Shopify embedded-app context renders our pages inside the Shopify admin
 *      iframe (admin.shopify.com / *.myshopify.com).
 *
 * The remaining headers (nosniff, Referrer-Policy, HSTS) are safe on every
 * response; HSTS is emitted only over HTTPS so plain-HTTP dev is unaffected.
 */
class SecurityHeaders
{
    /** Origins allowed to frame us inside the Shopify admin iframe. */
    private const SHOPIFY_FRAME_ANCESTORS = "frame-ancestors 'self' https://*.myshopify.com https://admin.shopify.com";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        // Content-sniffing + referrer-leak protection — safe on every response.
        if (! $headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // HSTS only over HTTPS so we never advertise it on plain-HTTP dev.
        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $path = ltrim($request->getPathInfo(), '/');

        // Never let mobile browsers CACHE the auth pages (or restore them from the
        // back-forward cache). A cached login / logout / password form carries a
        // STALE CSRF _token, so submitting it after the session rotated throws a
        // 419 "Page Expired" that only a manual reload clears — exactly what the
        // client saw. `no-store` forces a fresh page (fresh token) on every visit
        // and opts these pages out of the BFCache, so login + logout just work.
        $isAuthPage = in_array($path, ['login', 'register', 'logout', 'forgot-password', 'reset-password'], true)
            || str_starts_with($path, 'reset-password/')
            || str_starts_with($path, 'password/');
        if ($isAuthPage) {
            $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $headers->set('Pragma', 'no-cache');
        }

        // Chatbot widget page is embedded cross-origin on customer sites — leave
        // it framable. (It is token-authed + widget.cors gated, not session-authed.)
        if ($path === 'widget' || str_starts_with($path, 'widget/')) {
            return $response;
        }

        // Shopify embedded-app context: allow only the Shopify admin origins to
        // frame us; every other site is still blocked by the allowlist.
        if ($this->isShopifyEmbed($request, $path)) {
            if (! $headers->has('Content-Security-Policy')) {
                $headers->set('Content-Security-Policy', self::SHOPIFY_FRAME_ANCESTORS);
            }
            // X-Frame-Options cannot express an allowlist, so it is intentionally
            // omitted here; modern browsers honour the CSP frame-ancestors above.
            return $response;
        }

        // Default: same-origin framing only.
        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $response;
    }

    /**
     * Genuine Shopify embed context. Kept in lock-step with
     * EmbeddedShopifySession so both middleware agree on what "embed" means.
     */
    private function isShopifyEmbed(Request $request, string $path): bool
    {
        return $path === 'shopify'
            || str_starts_with($path, 'shopify/')
            || ($request->filled('host') && $request->filled('shop'))
            || $request->cookies->has(EmbeddedShopifySession::MARKER);
    }
}
