<?php

namespace App\Http\Middleware;

use App\Support\PlatformPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates `/admin/*`. Anyone hitting these routes must have one of the three
 * platform roles (Super Admin / Platform Support / Auditor). Returns 403
 * (not 404) so SaaS staff get a clear "not authorized" rather than a
 * confused "missing route" — this is an internal tool surface.
 */
class EnsurePlatformRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !PlatformPermissions::userHasPlatformAccess($user)) {
            abort(403, 'Platform access required.');
        }
        return $next($request);
    }
}
