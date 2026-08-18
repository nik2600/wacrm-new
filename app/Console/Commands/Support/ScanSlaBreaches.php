<?php

namespace App\Console\Commands\Support;

use App\Support\SlaCalculator;
use Illuminate\Console\Command;

/**
 * Scans every unresolved support ticket and inserts sla_breaches rows
 * for any that crossed their first-response or resolution deadline.
 *
 * Idempotent — re-running won't double-insert (per-ticket+type uniqueness
 * is enforced in code, not DB, so the runtime exists() check is the
 * guard).
 *
 * Schedule: every 5 minutes (see routes/console.php).
 */
class ScanSlaBreaches extends Command
{
    protected $signature = 'support:sla-scan';
    protected $description = 'Detect tickets that crossed their SLA window and insert sla_breaches rows';

    public function handle(): int
    {
        $started = microtime(true);
        $count = SlaCalculator::scanAndPersist();
        $ms = (int) round((microtime(true) - $started) * 1000);
        $this->info("[support:sla-scan] inserted {$count} breach row(s) in {$ms}ms");
        return self::SUCCESS;
    }
}
