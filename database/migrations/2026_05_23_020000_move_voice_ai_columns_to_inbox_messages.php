<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correction migration. The earlier migration put the voice-AI columns
 * on `messages` (the legacy chat/campaigns/broadcasts table) when they
 * belong on `inbox_messages` (what team-inbox + WaInboundController
 * actually write to). This rectifies that without dropping data —
 * voice columns aren't used by anything yet so the move is safe.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Add the voice columns to the RIGHT table.
        Schema::table('inbox_messages', function (Blueprint $t) {
            $t->text('voice_transcript')->nullable()->after('media_type');
            $t->string('voice_transcript_lang', 8)->nullable()->after('voice_transcript');
            $t->timestamp('ai_processed_at')->nullable()->after('voice_transcript_lang');
            $t->unsignedBigInteger('ai_reply_id')->nullable()->after('ai_processed_at');
            $t->index(['ai_processed_at']);
        });

        // 2. Drop them from the WRONG table. Wrapped in try/catch so a
        //    fresh install that runs both migrations in order doesn't
        //    fail if column was never created for whatever reason.
        try {
            Schema::table('messages', function (Blueprint $t) {
                $t->dropIndex(['ai_processed_at']);
            });
        } catch (\Throwable $e) {}
        try {
            Schema::table('messages', function (Blueprint $t) {
                $t->dropColumn(['voice_transcript', 'voice_transcript_lang', 'ai_processed_at', 'ai_reply_id']);
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Reverse — put columns back on `messages`, drop from `inbox_messages`.
        Schema::table('inbox_messages', function (Blueprint $t) {
            $t->dropIndex(['ai_processed_at']);
            $t->dropColumn(['voice_transcript', 'voice_transcript_lang', 'ai_processed_at', 'ai_reply_id']);
        });
        Schema::table('messages', function (Blueprint $t) {
            $t->text('voice_transcript')->nullable();
            $t->string('voice_transcript_lang', 8)->nullable();
            $t->timestamp('ai_processed_at')->nullable();
            $t->unsignedBigInteger('ai_reply_id')->nullable();
            $t->index(['ai_processed_at']);
        });
    }
};
