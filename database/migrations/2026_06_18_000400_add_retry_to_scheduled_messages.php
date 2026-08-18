<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable retry for scheduled messages. A scheduled send that failed because
 * the device was offline was marked failed once and never retried — these
 * columns let ScheduledMessageSweeper re-fire it (with backoff, up to the
 * per-feature max) once the device is back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('scheduled_messages', 'send_attempts')) {
                $table->unsignedTinyInteger('send_attempts')->default(0)->after('status');
            }
            if (!Schema::hasColumn('scheduled_messages', 'next_attempt_at')) {
                $table->timestamp('next_attempt_at')->nullable()->after('send_attempts');
            }
            if (!Schema::hasColumn('scheduled_messages', 'last_error')) {
                $table->text('last_error')->nullable()->after('next_attempt_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            foreach (['send_attempts', 'next_attempt_at', 'last_error'] as $col) {
                if (Schema::hasColumn('scheduled_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
