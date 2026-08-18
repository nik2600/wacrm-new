<?php

namespace App\Console\Commands;

use App\Events\Inbox\ConversationUpdated;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use Illuminate\Console\Command;

/**
 * Wakes conversations whose snooze timer has expired and flips them
 * back to inbox_status='open'. The TeamInboxController::queue() also
 * runs this sweep inline on every poll so hosts without
 * `* * * * * php artisan schedule:run` still see snoozed convos
 * surface within ~30s of their wake-up time. This command exists for
 * deployments that DO have the cron set up — it sweeps every minute
 * and is the more responsive path.
 *
 * Idempotent: only matches rows that are still status=snoozed AND have
 * a non-null snoozed_until ≤ now.
 */
class InboxWakeSnoozedCommand extends Command
{
    protected $signature = 'inbox:wake-snoozed {--limit=500}';
    protected $description = 'Flip snoozed conversations back to "open" when their snoozed_until ≤ now.';

    public function handle(): int
    {
        $waking = Conversation::query()
            ->where('inbox_status', 'snoozed')
            ->whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->limit((int) $this->option('limit'))
            ->get();

        $this->info("Waking {$waking->count()} snoozed conversation(s)…");

        foreach ($waking as $c) {
            try {
                $c->forceFill([
                    'inbox_status'  => 'open',
                    'snoozed_until' => null,
                ])->save();

                ConversationUpdated::dispatch(
                    $c->id, $c->workspace_id, 'unsnoozed', ['inbox_status' => 'open']
                );
                ConversationEvent::record(
                    $c->id, (int) $c->workspace_id, null,
                    'auto_unsnoozed', ['by' => 'system'], 'system'
                );
                // Remind the assignee/team so the follow-up isn't missed.
                try { app(\App\Services\Inbox\NotificationDispatcher::class)->notifySnoozeWake($c); }
                catch (\Throwable $e) { /* notification is best-effort */ }
                $this->line("  ✓ conv {$c->id} woken");
            } catch (\Throwable $e) {
                $this->error("  ✗ conv {$c->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
