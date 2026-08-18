<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Round 2 of engine-stamping. Round 1 (2026_05_24_120000) covered
 * send-recording tables; round 2 (this) handles user-facing CRUD
 * tables that ALSO need engine filtering so a workspace switching
 * from Baileys → WABA doesn't keep firing Baileys-only rules or
 * showing Baileys-only history.
 *
 * Affected:
 *   - keyword_replies  : auto-reply rules fire on inbound; wrong-engine
 *                        rules would trigger on the new engine's
 *                        inbound and break replies. Provider = which
 *                        engine the rule was authored for.
 *   - wpcampaigns      : WhatsApp campaigns (the older send-list path).
 *                        Engine pinned at create — operator can't move
 *                        a Baileys campaign to WABA mid-flight.
 *   - flows            : flow execution depends on engine-specific node
 *                        helpers (sendButtonsWABA vs sendButtons). A
 *                        flow tied to Baileys can't run on a WABA
 *                        workspace cleanly.
 *   - appointments     : booking confirmation send + reminders route
 *                        through the engine the slot was created under.
 *   - wa_calls         : calling is a WABA-only feature; Baileys
 *                        workspaces should see empty history.
 *
 * Backfill: every existing row → 'baileys' (all historical data was
 * created when these tables were single-engine).
 */
return new class extends Migration {
    private array $tables = [
        'keyword_replies',
        'wpcampaigns',
        'flows',
        'appointments',
        'wa_calls',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            if (Schema::hasColumn($table, 'provider')) continue;

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('provider', 16)->default('baileys')->after('workspace_id');
                $t->index(['workspace_id', 'provider'], "{$table}_ws_provider_idx");
            });
            DB::table($table)->whereNull('provider')->update(['provider' => 'baileys']);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            if (!Schema::hasColumn($table, 'provider')) continue;

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("{$table}_ws_provider_idx");
                $t->dropColumn('provider');
            });
        }
    }
};
