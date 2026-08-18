<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Operator-only self-rating of AI agent replies. Score 1-10
            // + a one-line rationale. Never sent to the customer. Used
            // by the team-inbox AI Performance analytics page to score
            // each AI agent's response quality over time.
            $table->tinyInteger('quality_score')->unsigned()->nullable()->after('reaction');
            $table->string('quality_note', 191)->nullable()->after('quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['quality_score', 'quality_note']);
        });
    }
};
