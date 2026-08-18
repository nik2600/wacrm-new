<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

/**
 * Prune audit_log rows older than the configured retention window.
 *
 * Retention days come from the SystemSetting `security.audit_log_retention_days`
 * (default 365). Set to 0 to disable pruning entirely — useful for
 * compliance setups that require permanent retention.
 *
 *   php artisan audit:prune              → uses configured retention
 *   php artisan audit:prune --days=180   → override for one-off cleanup
 *   php artisan audit:prune --dry-run    → report what would be deleted
 *
 * Wire into the scheduler (app/Console/Kernel.php or routes/console.php):
 *   Schedule::command('audit:prune')->dailyAt('03:30');
 */
class AuditLogPruneCommand extends Command
{
    protected $signature = 'audit:prune {--days= : Override retention days} {--dry-run : Report only, no delete}';
    protected $description = 'Prune audit_log rows past the retention window.';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) SystemSetting::get('security.audit_log_retention_days', 365);

        if ($days <= 0) {
            $this->info('Audit log retention is disabled (days <= 0). Nothing pruned.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $query  = AuditLog::query()->where('created_at', '<', $cutoff);
        $count  = (clone $query)->count();

        if ($count === 0) {
            $this->info("No audit rows older than {$cutoff->toDateString()} ({$days} days).");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN — would delete {$count} audit row(s) older than {$cutoff->toDateString()}.");
            return self::SUCCESS;
        }

        // Chunked delete so a giant table doesn't OOM the worker.
        $deleted = 0;
        while (true) {
            $batch = $query->limit(2000)->delete();
            if ($batch === 0) break;
            $deleted += $batch;
            $this->line("  pruned {$deleted}/{$count}");
        }

        $this->info("Pruned {$deleted} audit row(s) older than {$days} days.");
        return self::SUCCESS;
    }
}
