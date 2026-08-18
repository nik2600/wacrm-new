<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram-native auto-reply triggers.
 *
 * A WhatsApp auto-reply row is keyed by `trigger_type` (keyword/welcome/
 * away/out_of_hours). Instagram rules (provider='instagram') additionally
 * carry `ig_trigger` — WHICH Meta event fires them:
 *   dm_keyword     — a DM whose text matches the keyword (the existing path)
 *   comment_to_dm  — a comment on a post → public reply + private DM
 *   story_reply    — a DM that replies to one of the account's stories
 *   story_mention  — someone mentions the account in THEIR story (arrives as a DM)
 *   mention        — someone @mentions the account in a comment/caption
 * NULL on every WhatsApp row (they use trigger_type instead).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('keyword_replies', 'ig_trigger')) {
            Schema::table('keyword_replies', function (Blueprint $table) {
                $table->string('ig_trigger', 24)->nullable()->after('trigger_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('keyword_replies', 'ig_trigger')) {
            Schema::table('keyword_replies', function (Blueprint $table) {
                $table->dropColumn('ig_trigger');
            });
        }
    }
};
