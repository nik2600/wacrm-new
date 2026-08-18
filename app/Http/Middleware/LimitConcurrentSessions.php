<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits the number of concurrent live sessions per user.
 * Runs ONCE per session per request after auth is established —
 * if user is over the cap, terminates the OLDEST extra sessions
 * (keeping the current one + the most-recent N-1).
 *
 * Cap source: security.max_concurrent_sessions (default 5).
 * Requires SESSION_DRIVER=database (verified — sessions table exists).
 */
class LimitConcurrentSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip (Auth::check resolves the user from `users`).
        if (! is_file(storage_path('installed'))) return $next($request);

        if (! Auth::check()) return $next($request);

        $cap = (int) SystemSetting::get('security.max_concurrent_sessions', 5);
        if ($cap <= 0) return $next($request);

        $currentSessionId = $request->session()->getId();
        $userId = Auth::id();

        // Cheap: only check once per session, marker stored in session.
        if ($request->session()->get('_concurrent_checked_for_cap') === $cap) {
            return $next($request);
        }
        $request->session()->put('_concurrent_checked_for_cap', $cap);

        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get();

        if ($sessions->count() <= $cap) {
            return $next($request);
        }

        // Keep the cap-1 most recent OTHER sessions plus the current one,
        // delete the rest.
        $keep = collect([$currentSessionId]);
        foreach ($sessions as $row) {
            if ($keep->count() >= $cap) break;
            if ($row->id !== $currentSessionId) $keep->push($row->id);
        }
        $deleted = DB::table('sessions')
            ->where('user_id', $userId)
            ->whereNotIn('id', $keep->all())
            ->delete();

        if ($deleted > 0) {
            Audit::log('auth.concurrent_sessions_evicted', [
                'subject_type' => 'user',
                'subject_id'   => $userId,
                'result'       => 'warning',
                'meta'         => ['cap' => $cap, 'evicted' => $deleted],
            ]);
        }

        return $next($request);
    }
}
