<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each AI agent opt in to using the workspace's saved replies.
 * When on, AiAgentService::generateReply() prepends a "canned responses
 * you can use" block to the system prompt — the LLM then picks the
 * matching reply (or paraphrases it) instead of going off-script.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->boolean('use_saved_replies')->default(false)->after('handoff_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn('use_saved_replies');
        });
    }
};
