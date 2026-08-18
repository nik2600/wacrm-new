<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add `provider` column to every table that records a send.
 *
 * Workspaces support three engines: waba, baileys, twilio. Until now,
 * the send-recording tables (messages, inbox_messages, broadcasts,
 * scheduled_messages) didn't track WHICH engine sent each row. That
 * made analytics break-down by engine impossible — KPI cards had to
 * use brittle proxies (template_id presence) to guess.
 *
 * This migration:
 *   1. Adds a nullable `provider` varchar(16) to each table (indexed)
 *   2. Backfills existing rows from the workspace's primary
 *      WaProviderConfig (best-effort — old rows where the workspace's
 *      engine has since changed will be stamped with the CURRENT
 *      engine, which is acceptable since we can't reconstruct the
 *      historical engine without an event log)
 *
 * After this, the dispatcher must stamp `provider` on every new send
 * (see P6) and KPI cards can filter directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['messages', 'inbox_messages', 'broadcasts', 'scheduled_messages'] as $table) {
            if (!Schema::hasTable($table)) continue;
            if (Schema::hasColumn($table, 'provider')) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->string('provider', 16)->nullable()->index();
            });
        }

        // Backfill provider lookups
        $primaryByWs = DB::table('wa_provider_configs')
            ->where('is_primary', true)
            ->pluck('provider', 'workspace_id')->all();

        $fallbackByWs = DB::table('wa_provider_configs')
            ->orderByDesc('connected_at')
            ->get()
            ->keyBy('workspace_id')
            ->map(fn ($r) => $r->provider)
            ->all();

        $resolveProvider = function (?int $wsId) use ($primaryByWs, $fallbackByWs): ?string {
            if (!$wsId) return null;
            return $primaryByWs[$wsId] ?? $fallbackByWs[$wsId] ?? null;
        };

        // Tables WITH workspace_id — direct backfill
        foreach (['messages', 'broadcasts', 'scheduled_messages'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'workspace_id')) continue;
            $workspaceIds = DB::table($table)
                ->whereNull('provider')
                ->whereNotNull('workspace_id')
                ->distinct()
                ->pluck('workspace_id');
            foreach ($workspaceIds as $wsId) {
                $provider = $resolveProvider((int) $wsId);
                if (!$provider) continue;
                DB::table($table)
                    ->where('workspace_id', $wsId)
                    ->whereNull('provider')
                    ->update(['provider' => $provider]);
            }
        }

        // inbox_messages — no direct workspace_id, backfill via Conversation
        if (Schema::hasTable('inbox_messages') && Schema::hasColumn('inbox_messages', 'provider')) {
            $convWorkspaceMap = DB::table('conversations')
                ->whereNotNull('workspace_id')
                ->pluck('workspace_id', 'id');
            foreach ($convWorkspaceMap as $convId => $wsId) {
                $provider = $resolveProvider((int) $wsId);
                if (!$provider) continue;
                DB::table('inbox_messages')
                    ->where('conversation_id', $convId)
                    ->whereNull('provider')
                    ->update(['provider' => $provider]);
            }
        }
    }

    public function down(): void
    {
        foreach (['messages', 'inbox_messages', 'broadcasts', 'scheduled_messages'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'provider')) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex([$t->getTable() . '_provider_index']);
            });
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('provider');
            });
        }
    }
};
