<?php

namespace App\Http\Middleware;

use App\Models\AgentStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Touches `agent_statuses.last_seen_at` on every authenticated request, and
 * rolls today_* counters over at midnight. Cheap (one indexed UPDATE per
 * request) and lets the inbox UI render fresh "last seen 2 min ago" pills
 * without polling a separate /heartbeat endpoint.
 *
 * Throttled to once-per-30-seconds via the cache so a chatty SPA polling
 * the queue every 10s doesn't write four UPDATEs per minute per user.
 */
class RecordAgentActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip activity recording entirely.
        if (! is_file(storage_path('installed'))) return $next($request);

        $response = $next($request);

        $user = $request->user();
        if (!$user || !$user->current_workspace_id) return $response;

        $cacheKey = "agent_activity:{$user->id}:{$user->current_workspace_id}";
        if (cache()->has($cacheKey)) return $response;
        cache()->put($cacheKey, 1, now()->addSeconds(30));

        try {
            $row = AgentStatus::firstOrCreate(
                ['user_id' => $user->id, 'workspace_id' => $user->current_workspace_id],
                ['status' => 'online', 'counters_date' => now()->toDateString()],
            );
            $row->rolloverIfStale();
            $row->forceFill(['last_seen_at' => now()])->save();
        } catch (\Throwable $e) {
            // If agent_statuses isn't migrated yet (early bootstrap or fresh
            // testing DB), silently no-op — don't break every request just
            // because activity tracking can't write.
        }

        return $response;
    }
}
