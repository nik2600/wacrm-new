<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bring the Anthropic admin AI key onto the current flagship.
 *
 * Provider rows in admin_ai_keys are seeded once and never overwritten, so an
 * already-installed system keeps whatever default_model it was first seeded
 * with — here, the now-previous-gen `claude-opus-4-7`. Claude Opus 4.8
 * superseded it at the same price point, so move any row STILL on the old
 * seeded default up to 4.8. A row an admin deliberately changed to something
 * else (e.g. sonnet/haiku/fable) is left untouched. default_model is not a
 * secret (only api_key is encrypted), so a plain update is correct here.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('admin_ai_keys')
            ->where('provider', 'anthropic')
            ->where('default_model', 'claude-opus-4-7')
            ->update(['default_model' => 'claude-opus-4-8']);
    }

    public function down(): void
    {
        DB::table('admin_ai_keys')
            ->where('provider', 'anthropic')
            ->where('default_model', 'claude-opus-4-8')
            ->update(['default_model' => 'claude-opus-4-7']);
    }
};
