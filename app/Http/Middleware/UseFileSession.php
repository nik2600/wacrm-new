<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force file-based sessions for the install wizard.
 *
 * During installation the DB doesn't exist yet, so the default
 * (driver=database) session would 500. We pin a fixed cookie name
 * — NOT derived from APP_NAME — because the wizard rewrites APP_NAME
 * mid-flow, which would change the session cookie name and orphan the
 * step-state mid-AJAX-call.
 */
class UseFileSession
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.driver'  => 'file',
            'session.encrypt' => false,
            'session.cookie'  => 'wadesk_install_session',
        ]);

        return $next($request);
    }
}
