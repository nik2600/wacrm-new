<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Automatic retry for BROADCASTS — the mirror of CampaignScheduleSweeper /
 * ScheduledMessageSweeper. Broadcasts previously had only a MANUAL "Retry
 * failed" button, so a device-offline blast left failed recipients stuck.
 *
 * Fired by the Node heartbeat: finds recent broadcasts that still have
 * failed/undelivered recipients, and re-dispatches ONLY those recipients
 * (via BroadcastsController::redispatchFailed) — paced by the bridge's own
 * per-message gap, with exponential backoff between rounds, up to the
 * per-feature 'broadcast' max attempts. Already-sent recipients are never
 * touched (the dispatch is scoped to the failed contact IDs).
 */
class BroadcastSweeper
{
    public function sweep(): int
    {
        $lock = Cache::lock('broadcast-sweeper', 25);
        if (!$lock->get()) return 0;

        $retried = 0;
        try {
            // Broadcasts with retryable failures, due for another round.
            $ids = DB::table('broadcasts as b')
                ->join('broadcast_contacts as bc', 'bc.broadcast_id', '=', 'b.id')
                ->whereIn('bc.status', ['failed', 'undelivered'])
                ->whereNotIn('b.status', ['draft', 'cancelled', 'scheduled'])
                ->where('b.created_at', '>=', now()->subDays(7))   // don't resurrect ancient blasts
                ->where(function ($q) {
                    $q->whereNull('b.next_attempt_at')->orWhere('b.next_attempt_at', '<=', now());
                })
                ->distinct()
                ->limit(20)
                ->pluck('b.id');

            if ($ids->isEmpty()) return 0;

            $controller = app(\App\Http\Controllers\BroadcastsController::class);

            foreach ($ids as $id) {
                $b = Broadcast::find($id);
                if (!$b) continue;
                $ws   = $b->workspace_id ? Workspace::find($b->workspace_id) : null;
                $max  = RetryPolicy::attempts($ws, 'broadcast');
                $done = (int) ($b->send_attempts ?? 0);
                if ($done >= $max) continue;   // exhausted — leave failures for manual review

                $next = $done + 1;
                // Record the attempt + backoff BEFORE dispatch so a crash can't
                // spin, and the next round waits the proper window.
                $b->forceFill([
                    'send_attempts'   => $next,
                    'next_attempt_at' => now()->addSeconds(RetryPolicy::delayForAttempt($ws, 'broadcast', $next)),
                ])->save();

                try {
                    $retried += $controller->redispatchFailed($b);
                    Log::info("[BCAST-SWEEP] broadcast #{$b->id} retry {$next}/{$max}");
                } catch (\Throwable $e) {
                    Log::warning("[BCAST-SWEEP] redispatch failed #{$b->id}: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error('[BCAST-SWEEP] sweep failed: ' . $e->getMessage());
        } finally {
            optional($lock)->release();
        }

        return $retried;
    }
}
