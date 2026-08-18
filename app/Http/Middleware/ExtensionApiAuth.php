<?php

namespace App\Http\Middleware;

use App\Models\ExtensionApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates WaDesk browser-extension requests via the
 * `Authorization: Bearer <token>` header against extension_api_tokens.
 * On success the resolved user is bound so auth()->user()/id() work for
 * the rest of the request (the dispatcher reads auth()->id()).
 */
class ExtensionApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();
        $user = $bearer !== '' ? ExtensionApiToken::resolveUser($bearer) : null;

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        // Bind for auth() helpers + set the resolver so $request->user() works.
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
