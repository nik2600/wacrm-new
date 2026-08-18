<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs the user out if they've been idle for longer than
 * security.session_timeout_minutes.
 *
 * Idle = time since session last_activity (tracked by Laravel's session
 * driver automatically). On bounce, an audit row is written with
 * `auth.session_timeout` so admin can see the count of timeouts in
 * the security KPI strip.
 */
class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip (Auth::check resolves the user from `users`).
        if (! is_file(storage_path('installed'))) return $next($request);

        if (! Auth::check()) return $next($request);

        // "Keep me signed in for 30 days" opt-in bypasses the idle timeout — the
        // whole point of the checkbox is to stay signed in across days, and
        // Auth::logout() below would also destroy the long-lived remember cookie.
        // Re-arm the flag when a fresh session was just re-authenticated from the
        // remember cookie (Auth::viaRemember()), so it keeps bypassing after the
        // original login session eventually expires.
        if ($request->session()->get('auth.remember') || Auth::viaRemember()) {
            if (Auth::viaRemember()) {
                $request->session()->put('auth.remember', true);
            }
            return $next($request);
        }

        $limit = (int) SystemSetting::get('security.session_timeout_minutes', 60);
        if ($limit <= 0) return $next($request);

        $lastActivity = $request->session()->get('_last_activity_at');
        $now = time();

        if ($lastActivity && ($now - (int) $lastActivity) > ($limit * 60)) {
            $userId = Auth::id();
            Audit::log('auth.session_timeout', [
                'subject_type' => 'user',
                'subject_id'   => $userId,
                'result'       => 'warning',
                'meta'         => ['idle_seconds' => $now - (int) $lastActivity, 'limit_minutes' => $limit],
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('warning', __('Your session timed out after :min minutes of inactivity. Please sign in again.', ['min' => $limit]));
        }

        $request->session()->put('_last_activity_at', $now);
        return $next($request);
    }
}
