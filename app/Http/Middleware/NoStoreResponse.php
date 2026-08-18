<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force `Cache-Control: no-store` on the responses it wraps.
 *
 * Used on the guest auth pages (login / register / forgot / reset). Mobile
 * browsers and CDNs love to cache these HTML pages; a cached page carries a
 * `_token` bound to a session that gets swept from the DB, so the next POST
 * fails CSRF and 419s — over and over. Telling the browser/CDN never to store
 * the page means the login form is always rendered fresh with a live token.
 * Paired with resources/js/csrf-guard.js, which repairs the token client-side
 * if a stale copy is served anyway.
 */
class NoStoreResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
