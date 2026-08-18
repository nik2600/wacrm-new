<?php

namespace App\Services;

use App\Http\Controllers\WaCampaignsController;
use App\Models\SystemSetting;
use App\Models\WpCampaign;
use App\Models\WpCampaignContact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fires due scheduled / recurring campaigns. WaDesk runs NO Laravel scheduler
 * (project constraint), so this is driven by the Node bridge's 30-second
 * heartbeat (WaConnectController::nodeHeartbeat) — the bridge is always running
 * because it IS the WhatsApp engine, so it's a reliable 24/7 tick.
 *
 * A scheduled campaign sits at status='scheduled' until its send_date/send_time
 * (in its own timezone) passes; we then fire it through the exact same
 * dispatch path as the "Send now" button. Recurring campaigns re-arm
 * themselves afterwards (see WpCampaign::advanceRecurring).
 */
class CampaignScheduleSweeper
{
    /**
     * @return int how many campaigns were fired this pass
     */
    public function sweep(int $max = 25): int
    {
        // One sweep at a time: many heartbeats can land in the same window
        // (multiple devices/workspaces), and we must never double-fire.
        $lock = Cache::lock('campaign-schedule-sweep', 25);
        if (! $lock->get()) {
            return 0;
        }

        $fired = 0;
        try {
            // Rescue campaigns stranded mid-send. A fired campaign is set to
            // 'running' and does its paced send in an afterResponse() job; if
            // that job dies before it re-arms — FPM request-timeout kill, OOM,
            // or a server/Node restart mid-send ("I ran it on the 25th and it
            // never resumed") — the campaign is stuck at 'running' with
            // recipients still queued, and the loop below only fires 'scheduled'
            // campaigns, so it never continues. Flip stalled ones back so the
            // sweep picks them up THIS pass.
            $this->resumeStalledCampaigns();

            // Kick any campaigns whose recipients are stuck on a Meta ecosystem
            // block (131049) that FAILED before the webhook auto-retry shipped —
            // those got their one webhook in the past and would otherwise sit
            // 'failed' forever. Cache-gated so it scans occasionally, not every
            // 30s tick. Runs inside the sweep lock, and re-arms due campaigns
            // that the candidates query below then fires this same pass.
            $this->reconcileStuckMetaBlocks();

            // Coarse DB filter (status + type + date) then exact per-row due
            // check in PHP, because each campaign's due time is in its own
            // timezone and can't be compared in a single SQL clause.
            $upper = Carbon::now('UTC')->addDay()->toDateString();
            $candidates = WpCampaign::query()
                ->where('status', 'scheduled')
                ->whereIn('schedule_type', ['scheduled', 'recurring'])
                ->whereNotNull('send_date')
                ->whereDate('send_date', '<=', $upper)
                ->orderBy('send_date')->orderBy('send_time')
                ->limit($max)
                ->get();

            $controller = app(WaCampaignsController::class);

            // Diagnostic (Log::warning so it survives production log level):
            // proves the heartbeat actually reached the sweeper, and how many
            // scheduled campaigns it's considering this tick. If you NEVER see
            // this line, the Node heartbeat isn't getting through (token/403).
            Log::warning('[CAMPAIGN SWEEP] tick', [
                'candidates' => $candidates->count(),
                'now_utc'    => Carbon::now('UTC')->toDateTimeString(),
            ]);

            foreach ($candidates as $campaign) {
                if (! $campaign->isDue()) {
                    // Show WHY a candidate is held back — almost always its due
                    // time (in its own timezone) simply hasn't passed yet.
                    Log::warning('[CAMPAIGN SWEEP] not due yet', [
                        'id'        => $campaign->id,
                        'send_date' => (string) $campaign->send_date,
                        'send_time' => (string) $campaign->send_time,
                        'tz'        => $campaign->timezone ?: 'UTC',
                        'due_utc'   => optional($campaign->dueAtUtc())->toDateTimeString(),
                        'now_utc'   => Carbon::now('UTC')->toDateTimeString(),
                    ]);
                    continue;
                }
                try {
                    $controller->fireScheduledCampaign($campaign);
                    $fired++;
                    Log::info('[CAMPAIGN SWEEP] fired due campaign', [
                        'id'   => $campaign->id,
                        'type' => $campaign->schedule_type,
                        'ws'   => $campaign->workspace_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[CAMPAIGN SWEEP] fire failed', ['id' => $campaign->id, 'err' => $e->getMessage()]);
                }
            }
        } finally {
            optional($lock)->release();
        }

        return $fired;
    }

    /**
     * Rescue campaigns stranded at status='running'. fireScheduledCampaign flips
     * a campaign to 'running' and hands the paced send to an afterResponse() job.
     * If that job never finishes — PHP-FPM request-terminate kill, an OOM, or a
     * server / Node restart mid-send — the campaign stays 'running' with
     * recipients still queued FOREVER, because the main sweep only fires
     * 'scheduled' campaigns. A healthy paced run re-arms (→ 'scheduled') within
     * its ~20s budget, so a campaign that's been 'running' for MINUTES with unsent
     * recipients is definitively dead. Flip it back to 'scheduled' + due-now so
     * the sweep resumes it. Idempotent: a genuinely live send is < 20s old and a
     * finished one has no queued rows, so neither is touched.
     */
    private function resumeStalledCampaigns(): void
    {
        try {
            // A healthy paced chunk re-arms (→ 'scheduled') within its ~20s
            // budget, so a campaign still 'running' 45s later is definitively dead
            // (FPM killed the afterResponse worker before it re-armed). 45s — down
            // from 2 minutes — recovers a stranded blast far faster while staying
            // clear of a legitimately in-flight chunk.
            $staleBefore = Carbon::now('UTC')->subSeconds(45);
            $maxAttempts = max(1, (int) SystemSetting::get('campaign_retry_attempts', 3));
            $stuck = WpCampaign::query()
                ->where('status', 'running')
                ->where(function ($q) use ($staleBefore) {
                    $q->whereNull('last_run_at')->orWhere('last_run_at', '<', $staleBefore);
                })
                ->orderBy('last_run_at')
                ->limit(100)
                ->get();

            foreach ($stuck as $c) {
                // Only resume if there is genuinely something left to send. A run
                // killed mid-send may have left rows either untouched ('queued'/
                // 'pending') OR attempted-and-failed with retries still remaining —
                // BOTH mean unfinished work. The old check looked ONLY at queued/
                // pending, so a campaign whose leftovers had all failed once froze
                // at 'running' forever (the "33 sent then dead for hours" symptom).
                $pending = WpCampaignContact::query()
                    ->where('campaign_id', $c->id)
                    ->where(function ($q) use ($maxAttempts) {
                        $q->whereIn('status', ['queued', 'pending'])
                          ->orWhere(function ($q2) use ($maxAttempts) {
                              $q2->where('status', 'failed')
                                 ->where('send_attempts', '<', $maxAttempts);
                          });
                    })
                    ->exists();
                if (! $pending) continue;

                $tz = $c->timezone ?: config('app.timezone', 'UTC');
                try { $now = Carbon::now($tz); } catch (\Throwable $e) { $now = Carbon::now('UTC'); }
                $c->update([
                    'status'        => 'scheduled',
                    'schedule_type' => $c->schedule_type === 'recurring' ? 'recurring' : 'scheduled',
                    'send_date'     => $now->toDateString(),
                    'send_time'     => $now->format('H:i:s'),
                ]);
                Log::warning('[CAMPAIGN SWEEP] resumed STALLED running campaign', [
                    'campaign_id' => $c->id,
                    'ws'          => $c->workspace_id,
                    'last_run_at' => (string) $c->last_run_at,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[CAMPAIGN SWEEP] resume-stalled failed: ' . $e->getMessage());
        }
    }

    /**
     * One-shot rescue for campaign recipients STUCK on Meta's per-recipient
     * marketing throttle (131049 "healthy ecosystem engagement") that failed
     * BEFORE the WaWebhookController auto-retry shipped. Those rows got their
     * single status webhook in the past — no new webhook will ever arrive — so
     * they'd sit 'failed' forever. This moves them (once) into the normal retry
     * loop: re-arm the campaign so the sweep above resends the due failed rows.
     *
     * SELF-TARGETING + SAFE, keyed on ONE precise filter:
     *   status='failed' AND send_attempts < cap AND next_attempt_at IS NULL
     * A send-time retry ALWAYS sets next_attempt_at (or caps attempts), and a
     * permanent failure caps attempts — so this filter can only match an OLD
     * webhook-reported failure, i.e. exactly the stuck ones. Within those:
     *   • 131049 / "healthy ecosystem" → left resendable (re-arm the campaign).
     *   • anything else (131050 opt-out, 131026 undeliverable, …) → stamped
     *     TERMINAL (attempts=cap) so the resend NEVER re-messages an opt-out or
     *     dead number.
     * Idempotent: once a row is re-sent it either advances (sent/delivered) or
     * the webhook fix stamps next_attempt_at — either way it drops out of the
     * filter, so no row is ever kicked twice and there is no tight loop.
     */
    private function reconcileStuckMetaBlocks(): void
    {
        // Scan at most once every 10 minutes (heartbeat fires every ~30s).
        if (Cache::has('campaign-meta-block-reconcile')) return;
        Cache::put('campaign-meta-block-reconcile', 1, now()->addMinutes(10));

        // Needs the retry-tracking columns; legacy client schemas without them
        // just skip this (same gate the webhook auto-retry uses).
        try {
            if (! Schema::hasColumn('wp_campaign_contacts', 'send_attempts')
                || ! Schema::hasColumn('wp_campaign_contacts', 'next_attempt_at')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $maxAttempts = max(1, (int) SystemSetting::get('campaign_retry_attempts', 3));

        // Campaigns that hold at least one stuck (old-webhook) failure. Bounded
        // so a big backlog is cleared over several 10-minute passes, not in one
        // thundering burst.
        $campaignIds = WpCampaignContact::query()
            ->where('status', 'failed')
            ->where('send_attempts', '<', $maxAttempts)
            ->whereNull('next_attempt_at')
            ->distinct()
            ->limit(25)
            ->pluck('campaign_id');

        foreach ($campaignIds as $cid) {
            $campaign = WpCampaign::find($cid);
            if (! $campaign) continue;
            // Don't resurrect a campaign the operator ended, and don't touch one
            // already in flight / re-armed (the sweep owns those).
            if (in_array($campaign->status, ['cancelled', 'paused', 'draft', 'scheduled', 'running'], true)) {
                continue;
            }

            $rows = WpCampaignContact::query()
                ->where('campaign_id', $cid)
                ->where('status', 'failed')
                ->where('send_attempts', '<', $maxAttempts)
                ->whereNull('next_attempt_at')
                ->get(['id', 'error_message', 'send_attempts']);

            $hasRetryable = false;
            foreach ($rows as $row) {
                $err = strtolower((string) $row->error_message);   // SafeEncrypted → readable
                $isRetryable = str_contains($err, 'healthy ecosystem') || str_contains($err, '131049');
                if ($isRetryable) {
                    // Leave it resendable: status='failed', attempts<cap,
                    // next_attempt_at=null → due on the next fire.
                    $hasRetryable = true;
                } else {
                    // Old webhook-reported PERMANENT failure (opt-out / dead
                    // number) → cap it so the resend can never re-message it.
                    $row->update(['send_attempts' => $maxAttempts, 'next_attempt_at' => null]);
                }
            }

            if (! $hasRetryable) continue;

            // Re-arm so the candidates loop below fires it this same pass and
            // runCampaignNowPaced resends the (now only retryable) due rows.
            $tz = $campaign->timezone ?: config('app.timezone', 'UTC');
            try { $now = Carbon::now($tz); } catch (\Throwable $e) { $now = Carbon::now('UTC'); }
            $campaign->update([
                'status'        => 'scheduled',
                'schedule_type' => 'scheduled',
                'send_date'     => $now->toDateString(),
                'send_time'     => $now->format('H:i:s'),
            ]);
            Log::warning('[CAMPAIGN SWEEP] re-armed stuck Meta-block (131049) failures', [
                'campaign_id' => $cid,
                'ws'          => $campaign->workspace_id,
            ]);
        }
    }
}
