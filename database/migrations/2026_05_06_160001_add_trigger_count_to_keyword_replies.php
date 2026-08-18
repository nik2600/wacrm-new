<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-rule trigger counter so the index page's "top performers" + total
 * stats are real, not faked. Incremented from AutoReplyController::lookup
 * every time a keyword matches; reset is manual via SQL.
 *
 * `last_triggered_at` lets us show "fired Xh ago" on each row without a
 * separate trigger log table — minimum-viable analytics until we wire
 * a real per-event log.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('keyword_replies', function (Blueprint $table) {
            $table->unsignedBigInteger('trigger_count')->default(0)->index()->after('status');
            $table->timestamp('last_triggered_at')->nullable()->after('trigger_count');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_replies', function (Blueprint $table) {
            $table->dropColumn(['trigger_count', 'last_triggered_at']);
        });
    }
};
