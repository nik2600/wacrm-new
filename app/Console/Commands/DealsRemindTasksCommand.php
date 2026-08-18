<?php

namespace App\Console\Commands;

use App\Services\Deals\DealReminderService;
use Illuminate\Console\Command;

/**
 * Nudges deal owners about tasks whose due_at has passed. The
 * TeamInboxController::queue() also runs this sweep inline on every poll
 * (cache-gated per workspace), so hosts without `php artisan schedule:run`
 * still get reminders within ~60s of an operator having the inbox open.
 * This command exists for deployments that DO run cron and want the nudge
 * even when nobody is looking at the inbox.
 *
 * Idempotent: only matches tasks with reminded_at IS NULL (stamped on notify).
 */
class DealsRemindTasksCommand extends Command
{
    protected $signature = 'deals:remind-tasks {--limit=500}';
    protected $description = 'Notify deal owners of tasks whose due date has passed (once per task).';

    public function handle(DealReminderService $svc): int
    {
        $n = $svc->sweep(null, (int) $this->option('limit'));
        $this->info("Reminded {$n} due deal task(s).");

        return self::SUCCESS;
    }
}
