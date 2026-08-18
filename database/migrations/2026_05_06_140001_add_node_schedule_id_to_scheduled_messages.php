<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Node.js scheduler returns its own ID for each registered job; we
 * persist it on the parent row so subsequent pause/resume/cancel calls
 * can target the right Node-side job. Same column name as the legacy
 * D:\wadesk_2806\New folder install for portability.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->string('node_schedule_id', 100)->nullable()->index()->after('from_number');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->dropColumn('node_schedule_id');
        });
    }
};
