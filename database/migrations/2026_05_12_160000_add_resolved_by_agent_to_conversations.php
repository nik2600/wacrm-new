<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Null = resolved by a human (resolved_by holds the user_id).
            // Non-null = the AI agent that closed the conversation.
            $table->unsignedBigInteger('resolved_by_agent_id')->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('resolved_by_agent_id');
        });
    }
};
