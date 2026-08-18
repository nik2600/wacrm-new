<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('assignee_agent_id')->nullable()->after('assignee_team_id')->index();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('assignee_agent_id');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('agent_id');
        });
    }
};
