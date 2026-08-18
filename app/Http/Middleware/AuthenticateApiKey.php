<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer REST API auth. Reads a `Authorization: Bearer wsk_...` (or
 * `X-Api-Key`) header, resolves the api_keys row, and "acts as" the key's
 * owner user with the key's workspace set as current — so every existing
 * controller/service (which read Auth::user()->current_workspace_id) works
 * unchanged. Returns a JSON error envelope on any failure.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->bearerToken() ?: $request->header('X-Api-Key');

        if (!$raw) {
            return $this->deny('missing_api_key', 'No API key provided. Send it as "Authorization: Bearer <key>".', 401);
        }

        $key = ApiKey::where('key_hash', hash('sha256', $raw))->first();
        if (!$key || !$key->isActive()) {
            return $this->deny('invalid_api_key', 'The API key is missing, revoked or expired.', 401);
        }

        $user = User::find($key->user_id);
        if (!$user) {
            return $this->deny('invalid_api_key', 'The API key owner no longer exists.', 401);
        }

        // Act as the owner with the key's workspace current. Not persisted —
        // forceFill keeps it in-memory for this request only.
        $user->forceFill(['current_workspace_id' => $key->workspace_id]);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Expose the key on the request so controllers can read scopes/limits.
        $request->attributes->set('api_key', $key);

        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function deny(string $code, string $message, int $status): Response
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
