<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Multi-WABA support
 *
 * Adds `is_primary` to wa_provider_configs so a workspace can have
 * multiple rows (one per WABA phone number) and we still know which
 * is the default sender. Backfills `true` on the lowest-id row per
 * workspace so existing single-row workspaces keep behaving the same.
 *
 * The table schema already permits multiple rows per workspace (no
 * unique constraint blocks it); the singular-ness was just a
 * ->first() convention in 18 call sites. Phase 2 updates those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_provider_configs', function (Blueprint $t) {
            if (! Schema::hasColumn('wa_provider_configs', 'is_primary')) {
                $t->boolean('is_primary')->default(false)->after('display_label');
                $t->index(['workspace_id', 'is_primary']);
            }
        });

        // Backfill: for every workspace that already has rows, mark
        // the lowest-id one (oldest) as primary. Idempotent — skips
        // workspaces that already have a primary set.
        $needsBackfill = DB::table('wa_provider_configs')
            ->select('workspace_id')
            ->groupBy('workspace_id')
            ->havingRaw('SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) = 0')
            ->pluck('workspace_id');

        foreach ($needsBackfill as $wsId) {
            $firstId = DB::table('wa_provider_configs')
                ->where('workspace_id', $wsId)
                ->orderBy('id')
                ->value('id');
            if ($firstId) {
                DB::table('wa_provider_configs')->where('id', $firstId)->update(['is_primary' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('wa_provider_configs', function (Blueprint $t) {
            if (Schema::hasColumn('wa_provider_configs', 'is_primary')) {
                $t->dropIndex(['workspace_id', 'is_primary']);
                $t->dropColumn('is_primary');
            }
        });
    }
};
