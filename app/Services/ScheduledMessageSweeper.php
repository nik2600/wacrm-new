<?php

namespace App\Services;

use App\Models\ScheduledMessage;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Durable retry for one-off scheduled messages — the mirror of
 * CampaignScheduleSweeper. A scheduled send that failed (e.g. the device was
 * offline when its time came) is marked status='failed' by Node and would
 * otherwise sit there forever. This sweeper, fired by the Node heartbeat,
 * re-registers it with the bridge (so it fires again ~now) with exponential
 * backoff between attempts, up to RetryPolicy's per-workspace 'scheduled' max.
 *
 * Recurring schedules re-arm themselves (advanceRecurring), so we only sweep
 * one-off ('once') rows.
 */
class ScheduledMessageSweeper
{
    public function sweep(): int
    {
        // Cluster-safe: only one heartbeat does the sweep per ~25s window.
        $lock = Cache::lock('scheduled-message-sweeper', 25);
        if (!$lock->get()) return 0;

        $fired = 0;
        try {
            $rows = ScheduledMessage::query()
                ->where('schedule_type', 'once')
                ->where('status', 'failed')
                ->whereNotNull('from_number')
                ->where(function ($q) {
                    $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('id')
                ->limit(50)
                ->get();

            foreach ($rows as $row) {
                $ws  = $row->workspace_id ? Workspace::find($row->workspace_id) : null;
                $max = RetryPolicy::attempts($ws, 'scheduled');
                $done = (int) ($row->send_attempts ?? 0);
                if ($done >= $max) {
                    continue; // exhausted — leave it failed for the user to see
                }
                $next = $done + 1;

                // Arm to fire almost immediately + record the attempt up-front
                // so a crash mid-register can't double-count or spin.
                $row->forceFill([
                    'send_attempts'  => $next,
                    'scheduled_time' => now()->addSeconds(5),
                    'next_attempt_at'=> now()->addSeconds(RetryPolicy::delayForAttempt($ws, 'scheduled', $next)),
                ])->save();

                try {
                    $mediaUrl = $row->media_file ? url($row->media_file) : null;
                    $nodeId = app(NodeSchedulerClient::class)->registerOneOff($row, $mediaUrl);
                    if ($nodeId) {
                        $row->forceFill(['status' => 'scheduled', 'node_schedule_id' => $nodeId])->save();
                        $fired++;
                        Log::info("[SCHED-SWEEP] re-armed scheduled #{$row->id} attempt {$next}/{$max}");
                    } else {
                        // Bridge unreachable — stay failed; next sweep (after the
                        // backoff window) tries again until attempts hit max.
                        $row->forceFill(['status' => 'failed', 'last_error' => 'retry: bridge unreachable'])->save();
                    }
                } catch (\Throwable $e) {
                    $row->forceFill(['status' => 'failed', 'last_error' => 'retry: ' . $e->getMessage()])->save();
                    Log::warning("[SCHED-SWEEP] re-register failed #{$row->id}: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error('[SCHED-SWEEP] sweep failed: ' . $e->getMessage());
        } finally {
            optional($lock)->release();
        }

        return $fired;
    }
}
