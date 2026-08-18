<?php

use App\Services\WorkspaceEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-engine foundation (Phase 0). A workspace may run ANY SUBSET of the
 * platform-allowed engines (baileys | waba | twilio) at the same time.
 *
 *  - enabled_engines (json, null): the subset of the platform's
 *    allowed_send_methods this workspace has switched on. NULL = "use every
 *    platform-allowed engine that's connected" (the day-one behaviour).
 *    Resolved by WorkspaceEngine (Phase 1).
 *  - default_engine (string, null): the engine used for sends that DON'T pin a
 *    sender (automated / commerce / AI fallback). NULL = resolved at runtime by
 *    WorkspaceEngine::defaultEngineFor() (== today's WorkspaceEngine::for()).
 *
 * Behaviour-preserving: nothing reads these columns yet (the app still uses
 * WorkspaceEngine::for()). We backfill default_engine from the currently
 * resolved single engine so when later phases switch to it, day-one behaviour
 * is byte-identical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('enabled_engines')->nullable()->after('plan');
            $table->string('default_engine', 16)->nullable()->after('enabled_engines');
        });

        // Backfill default_engine from the currently-resolved single engine so
        // each workspace's default matches today's behaviour exactly. Use the
        // raw query builder (no model events / soft-delete scope) and resolve
        // via WorkspaceEngine so the answer is identical to runtime. Best-
        // effort: if resolution throws, leave NULL (runtime falls back).
        try {
            DB::table('workspaces')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $ws) {
                    try {
                        $engine = WorkspaceEngine::for((int) $ws->id);
                        DB::table('workspaces')->where('id', $ws->id)->update(['default_engine' => $engine]);
                    } catch (\Throwable $e) {
                        // leave NULL — defaultEngineFor() resolves it at runtime
                    }
                }
            });
        } catch (\Throwable $e) {
            // empty table / service unavailable during migrate — non-fatal
        }
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['enabled_engines', 'default_engine']);
        });
    }
};
