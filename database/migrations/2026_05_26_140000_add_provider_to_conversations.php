<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp every conversation with the engine that owns it (waba /
 * baileys / twilio) so the team-inbox queue and dashboard live-inbox
 * can hide conversations from other engines when the workspace
 * switches.
 *
 * Why a column, not a runtime JOIN: `Conversation.device_id` points
 * at the legacy `devices` table for BOTH Baileys and WABA-origin
 * conversations (the WABA webhook reuses an existing Device row as
 * the workspace's "sender slot"), so the column alone can't tell the
 * engines apart. The Conversation.platform column is a generic
 * "W"/"WB"/"T" mix that wasn't consistently set. Adding an explicit
 * `provider` field is the cheapest reliable signal — same pattern we
 * already use on send-recording tables (migration 2026_05_24_120000).
 *
 * Backfill: every existing conversation predates this column, so
 * default them to 'baileys' (the engine all historical inbox data
 * was created under). The webhook handlers stamp the right value on
 * NEW conversations going forward.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $t) {
            $t->string('provider', 16)->default('baileys')->after('platform');
            $t->index(['workspace_id', 'provider'], 'conversations_ws_provider_idx');
        });

        DB::table('conversations')->whereNull('provider')->update(['provider' => 'baileys']);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $t) {
            $t->dropIndex('conversations_ws_provider_idx');
            $t->dropColumn('provider');
        });
    }
};
