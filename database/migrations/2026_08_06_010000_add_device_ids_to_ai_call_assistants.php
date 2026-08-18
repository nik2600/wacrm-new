<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device (per-number) routing for AI CALL assistants.
 *
 * `device_ids` lists the WABA numbers this assistant answers. Empty/NULL = the
 * workspace catch-all (answers any number not bound to a specific assistant) —
 * so existing single-assistant workspaces keep working unchanged. Mirrors
 * ai_agents.device_ids so the call + chat sides behave the same.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ai_call_assistants', 'device_ids')) {
            Schema::table('ai_call_assistants', function (Blueprint $table) {
                $table->json('device_ids')->nullable()->after('meta_json');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_call_assistants', 'device_ids')) {
            Schema::table('ai_call_assistants', function (Blueprint $table) {
                $table->dropColumn('device_ids');
            });
        }
    }
};
