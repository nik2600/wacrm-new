<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHEN an operator started recording a call.
 *
 * Calls are no longer recorded automatically — the operator presses Record
 * on the call popup and capture begins from that moment. Storing the
 * timestamp gives two things the old always-on behaviour could not: the UI
 * can show "recording since 14:32" instead of guessing, and there is an
 * auditable record of when consent was actually given, which matters if a
 * recording is ever disputed.
 *
 * Nullable: a call with no value was simply never recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wa_calls')) return;
        if (Schema::hasColumn('wa_calls', 'recording_started_at')) return;

        Schema::table('wa_calls', function (Blueprint $table) {
            $table->timestamp('recording_started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('wa_calls')) return;
        if (!Schema::hasColumn('wa_calls', 'recording_started_at')) return;

        Schema::table('wa_calls', function (Blueprint $table) {
            $table->dropColumn('recording_started_at');
        });
    }
};
