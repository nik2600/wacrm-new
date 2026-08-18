<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast-level auto-retry. Broadcasts only had a MANUAL "Retry failed"
 * button — a device-offline blast left failed recipients stuck until someone
 * clicked it. These columns let BroadcastSweeper re-fire the failed/undelivered
 * recipients automatically (backoff, up to the per-feature max attempts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            if (!Schema::hasColumn('broadcasts', 'send_attempts')) {
                $table->unsignedTinyInteger('send_attempts')->default(0)->after('status');
            }
            if (!Schema::hasColumn('broadcasts', 'next_attempt_at')) {
                $table->timestamp('next_attempt_at')->nullable()->after('send_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            foreach (['send_attempts', 'next_attempt_at'] as $col) {
                if (Schema::hasColumn('broadcasts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
