<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-template channel for multi-engine: which engine a template targets —
 * 'baileys' (Unofficial API), 'waba' (Meta Cloud), or 'twilio'. Before this
 * the engine was decided by a single global flag with no per-template choice
 * and no way to label which template belonged to which engine.
 *
 * Backfilled from the fields that already encode the engine:
 *   - twilio_content_sid present            → twilio
 *   - meta_template_id / provider_config_id → waba
 *   - otherwise                             → baileys
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_templates', 'channel')) {
                $table->string('channel', 16)->nullable()->after('meta_status')->index();
            }
        });

        // These three columns are stored in plaintext (not in the encrypted
        // casts list), so a raw backfill is safe.
        DB::table('wa_templates')
            ->whereNotNull('twilio_content_sid')->where('twilio_content_sid', '!=', '')
            ->update(['channel' => 'twilio']);

        DB::table('wa_templates')
            ->whereNull('channel')
            ->where(function ($q) {
                $q->whereNotNull('meta_template_id')->orWhereNotNull('provider_config_id');
            })
            ->update(['channel' => 'waba']);

        DB::table('wa_templates')->whereNull('channel')->update(['channel' => 'baileys']);
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (Schema::hasColumn('wa_templates', 'channel')) {
                $table->dropColumn('channel');
            }
        });
    }
};
