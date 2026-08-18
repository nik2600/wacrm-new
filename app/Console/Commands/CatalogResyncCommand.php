<?php

namespace App\Console\Commands;

use App\Models\WaCatalog;
use App\Services\WhatsAppCatalog\CatalogSyncService;
use Illuminate\Console\Command;

/**
 * Sweep every connected Meta Commerce catalog: push any dirty (never-synced
 * or failed) products as a batch, then poll Meta to settle pending rows to
 * synced/failed. This is the scheduled twin of the per-product auto-sync —
 * a safety net that catches anything the live observer missed (a product
 * edited while the catalog was briefly unreachable, a failed earlier batch).
 *
 *   php artisan catalog:resync                  → all workspaces
 *   php artisan catalog:resync --workspace=12   → one workspace
 *   php artisan catalog:resync --poll-only      → just settle pending, no push
 *
 * Wire into a scheduler (optional — the live observer already keeps catalogs
 * current; this just backstops it):
 *   Schedule::command('catalog:resync')->hourly();
 */
class CatalogResyncCommand extends Command
{
    protected $signature = 'catalog:resync {--workspace= : Limit to one workspace id} {--poll-only : Skip the push, only settle pending batches}';
    protected $description = 'Push dirty products to connected Meta catalogs and settle pending batch statuses.';

    public function handle(CatalogSyncService $sync): int
    {
        $wsIds = WaCatalog::query()
            ->whereNotNull('catalog_id')
            ->when($this->option('workspace'), fn ($q, $id) => $q->where('workspace_id', (int) $id))
            ->pluck('workspace_id')
            ->unique()
            ->values();

        if ($wsIds->isEmpty()) {
            $this->info('No connected catalogs to sync.');
            return self::SUCCESS;
        }

        $pollOnly = (bool) $this->option('poll-only');
        $pushed = 0;
        $synced = 0;
        $failed = 0;

        foreach ($wsIds as $wsId) {
            if (!$pollOnly) {
                $res = $sync->flushWorkspace((int) $wsId);
                $pushed += $res['pushed'] ?? 0;
                if (!empty($res['error'])) {
                    $this->warn("Workspace {$wsId}: push error — {$res['error']}");
                }
            }
            $poll = $sync->pollWorkspace((int) $wsId);
            $synced += $poll['synced'];
            $failed += $poll['failed'];
        }

        $this->info("Catalogs: {$wsIds->count()} · pushed {$pushed} · settled synced {$synced} / failed {$failed}.");
        return self::SUCCESS;
    }
}
