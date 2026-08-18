<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-wipe the data of workspaces whose plan has been EXPIRED for longer than
 * the plan's `data_retention_days`. Configured per-plan in the admin package
 * editor (0 = never wipe). Destructive — DRY-RUN by default; pass --force to
 * actually delete. Each wiped workspace is stamped `data_wiped_at` so it runs
 * exactly once.
 *
 * Eligibility: plan.data_retention_days > 0  AND  plan_ends_at is set and is
 * more than data_retention_days in the past  AND  not already wiped.
 */
class WipeExpiredWorkspaces extends Command
{
    protected $signature = 'workspaces:wipe-expired {--force : Actually delete (default is a dry-run report)}';
    protected $description = 'Wipe data of workspaces whose plan expired beyond the plan data-retention window';

    /** Tables scoped directly by workspace_id (guarded — only those present). */
    private array $directTables = [
        'contacts', 'contact_groups', 'conversations', 'wpcampaigns', 'broadcasts',
        'flows', 'flow_subscribers', 'scheduled_messages', 'wa_templates', 'keyword_replies',
        'deals', 'deal_activities', 'wa_orders', 'ai_agents', 'ai_training_sources',
        'ai_chat_assistants', 'ai_call_assistants', 'appointments', 'saved_replies',
        'attributes', 'chatbot_widgets', 'incoming_webhooks', 'devices', 'wa_provider_configs',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $now = now();

        // Plans that opt into auto-wipe.
        $retentionByPlan = Package::query()
            ->where('data_retention_days', '>', 0)
            ->get(['id', 'plan_id', 'pname', 'data_retention_days']);
        if ($retentionByPlan->isEmpty()) {
            $this->info('No plans have data_retention_days > 0 — nothing to wipe.');
            return self::SUCCESS;
        }

        $totalWiped = 0;
        foreach ($retentionByPlan as $plan) {
            $cutoff = $now->copy()->subDays((int) $plan->data_retention_days);
            // Workspaces on this plan (matched by slug OR numeric id, like
            // Workspace::package() resolves) whose plan expired before cutoff
            // and which haven't been wiped yet.
            $workspaces = Workspace::query()
                ->where(fn ($q) => $q->where('plan', $plan->plan_id)->orWhere('plan', (string) $plan->id))
                ->whereNull('data_wiped_at')
                ->whereNotNull('plan_ends_at')
                ->where('plan_ends_at', '<', $cutoff)
                ->get();

            foreach ($workspaces as $ws) {
                $counts = $this->wipe($ws, $force);
                $totalWiped += array_sum($counts);
                $line = sprintf(
                    '%s workspace #%d "%s" (plan=%s, expired %s, retention=%dd) — %d rows%s',
                    $force ? 'WIPED' : 'would wipe',
                    $ws->id, $ws->name ?? '?', $plan->plan_id,
                    optional($ws->plan_ends_at)->toDateString(), $plan->data_retention_days,
                    array_sum($counts),
                    $force ? '' : ' [dry-run]'
                );
                $this->warn($line);
                Log::warning('[WIPE-EXPIRED] ' . $line, ['workspace_id' => $ws->id, 'counts' => $counts, 'force' => $force]);
            }
        }

        $this->info(($force ? 'Done. ' : 'Dry-run complete. ') . $totalWiped . ' row(s) ' . ($force ? 'deleted.' : 'would be deleted. Re-run with --force to apply.'));
        return self::SUCCESS;
    }

    /**
     * Delete a workspace's data. Children-then-parents for the big relational
     * sets, then every direct workspace_id table. Each delete is guarded +
     * isolated so one missing table never aborts the rest. Returns per-table
     * counts. On --force, also stamps data_wiped_at + clears the plan.
     */
    private function wipe(Workspace $ws, bool $force): array
    {
        $wsId = (int) $ws->id;
        $counts = [];

        $del = function (string $table, \Closure $scope) use ($force, &$counts) {
            if (!Schema::hasTable($table)) return;
            try {
                $q = DB::table($table);
                $scope($q);
                $counts[$table] = $force ? $q->delete() : $q->count();
            } catch (\Throwable $e) {
                Log::warning("[WIPE-EXPIRED] {$table} failed: " . $e->getMessage());
            }
        };

        // Children first (no FK cascade is assumed).
        if (Schema::hasTable('conversations')) {
            $convoIds = DB::table('conversations')->where('workspace_id', $wsId)->pluck('id');
            if ($convoIds->isNotEmpty()) {
                $del('inbox_messages', fn ($q) => $q->whereIn('conversation_id', $convoIds));
                $del('conversation_participants', fn ($q) => $q->whereIn('conversation_id', $convoIds));
            }
        }
        if (Schema::hasTable('wpcampaigns')) {
            $campIds = DB::table('wpcampaigns')->where('workspace_id', $wsId)->pluck('id');
            if ($campIds->isNotEmpty()) $del('wp_campaign_contacts', fn ($q) => $q->whereIn('campaign_id', $campIds));
        }
        if (Schema::hasTable('broadcasts')) {
            $bcIds = DB::table('broadcasts')->where('workspace_id', $wsId)->pluck('id');
            if ($bcIds->isNotEmpty()) $del('broadcast_contacts', fn ($q) => $q->whereIn('broadcast_id', $bcIds));
        }
        // Legacy messages table is user-scoped, not workspace — scope via members.
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'workspace_id')) {
            $del('messages', fn ($q) => $q->where('workspace_id', $wsId));
        }

        // Direct workspace_id tables.
        foreach ($this->directTables as $t) {
            if (Schema::hasColumn($t, 'workspace_id')) {
                $del($t, fn ($q) => $q->where('workspace_id', $wsId));
            }
        }

        if ($force) {
            try {
                // Keep the workspace shell + owner account; just flag it wiped
                // and drop the (already-expired) plan so it can be re-subscribed.
                $ws->forceFill(['data_wiped_at' => now(), 'plan' => null])->save();
            } catch (\Throwable $e) {
                Log::warning('[WIPE-EXPIRED] stamp failed ws#' . $wsId . ': ' . $e->getMessage());
            }
        }

        return $counts;
    }
}
