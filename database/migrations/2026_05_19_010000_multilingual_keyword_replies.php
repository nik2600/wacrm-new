<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multilingual auto-reply (P7). No-AI approach:
 *
 *   1. At keyword-save time, the original keyword + reply text are
 *      translated to the workspace's configured language list via
 *      the free MyMemory API. Result is stored on the row as a JSON
 *      blob — translating once is far cheaper than translating
 *      every inbound message.
 *
 *   2. At inbound time, LanguageDetector::detect() classifies the
 *      message by Unicode-range (Hangul → ko, Devanagari → hi,
 *      Arabic → ar, CJK → zh, Cyrillic → ru, ...) and the matcher
 *      tries each translated variant of the keyword. Reply is sent
 *      in the customer's detected language.
 *
 * Shape of keyword_translations:
 *   {
 *     "en": "hello",
 *     "hi": "नमस्ते",
 *     "zh": "你好",
 *     "ko": "안녕하세요",
 *     "ar": "مرحبا"
 *   }
 *
 * Shape of content_translations (per content row):
 *   {
 *     "en": "Hi, how can I help?",
 *     "hi": "नमस्ते, मैं कैसे मदद करूँ?",
 *     ...
 *   }
 *
 * Workspace.auto_translate_languages: ["en","hi","zh","ko","ar","es"]
 *   — the fan-out targets when a new keyword is saved.
 * Workspace.default_language: "en"
 *   — fallback when an inbound message is in pure-Latin script we
 *   can't disambiguate by codepoints alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_replies', function (Blueprint $table) {
            $table->json('keyword_translations')->nullable()->after('keyword');
            $table->string('canonical_language', 8)->default('en')->after('keyword_translations');
        });

        Schema::table('keyword_reply_contents', function (Blueprint $table) {
            $table->json('content_translations')->nullable()->after('content');
        });

        Schema::table('keyword_reply_logs', function (Blueprint $table) {
            $table->string('detected_language', 8)->nullable()->after('matched_variant');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('auto_translate_languages')->nullable()->after('notification_prefs');
            $table->string('default_language', 8)->nullable()->after('auto_translate_languages');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['auto_translate_languages', 'default_language']);
        });
        Schema::table('keyword_reply_logs', function (Blueprint $table) {
            $table->dropColumn('detected_language');
        });
        Schema::table('keyword_reply_contents', function (Blueprint $table) {
            $table->dropColumn('content_translations');
        });
        Schema::table('keyword_replies', function (Blueprint $table) {
            $table->dropColumn(['keyword_translations', 'canonical_language']);
        });
    }
};
