<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI agents can talk forever in a support chat — eventually a human
 * has to take over. This migration adds three escape hatches:
 *
 *  1. `max_replies_per_conversation` — hard cap on AI replies in a
 *     single thread. After N back-and-forths, hand off.
 *  2. `handoff_keywords` — list of phrases the AI watches for in the
 *     customer's message ("real person", "human agent", "speak to
 *     someone", etc.). Match triggers handoff before generating a reply.
 *  3. `handoff_low_score_threshold` — if the AI's last N replies all
 *     self-rated below this score, it's stuck. Hand off.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_replies_per_conversation')->default(10)->after('temperature');
            $table->json('handoff_keywords')->nullable()->after('max_replies_per_conversation');
            $table->unsignedTinyInteger('handoff_low_score_threshold')->default(0)->after('handoff_keywords');
            $table->unsignedTinyInteger('handoff_low_score_window')->default(3)->after('handoff_low_score_threshold');
            $table->boolean('handoff_enabled')->default(true)->after('handoff_low_score_window');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn([
                'max_replies_per_conversation',
                'handoff_keywords',
                'handoff_low_score_threshold',
                'handoff_low_score_window',
                'handoff_enabled',
            ]);
        });
    }
};
