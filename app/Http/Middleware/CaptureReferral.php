<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the request URL has `?ref=ABCD`, drop the code into a long-lived
 * cookie so it survives clicks around the marketing site before the
 * user gets to /register.
 *
 * 30 days mirrors typical SaaS attribution windows. We don't bother
 * with last-touch vs first-touch because v1 only awards on signup —
 * not on conversion / payment — so the only question is "who gets
 * the credit at register time".
 */
class CaptureReferral
{
    public const COOKIE_NAME = 'wadesk_ref';
    public const TTL_MINUTES = 60 * 24 * 30; // 30 days

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('ref');
        if (!$code || !is_string($code)) {
            return $next($request);
        }

        // Sanitise — only the alphabet our generator uses, capped at
        // 16 chars. Anything else means a typo or a tampering attempt
        // and we silently drop it.
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
        if ($code === '' || strlen($code) > 16) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        // Use queue() so the cookie rides along with whatever response
        // the upstream pipeline produced, including Inertia / JSON.
        cookie()->queue(
            self::COOKIE_NAME,
            $code,
            self::TTL_MINUTES,
            null, null,
            $request->isSecure(),
            true,        // httpOnly
            false,
            'lax'
        );

        return $response;
    }
}
