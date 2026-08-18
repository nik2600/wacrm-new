<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-schedule denormalised counter of how many messages we've
 * already charged the wallet for. The bot reports `totalSent` as a
 * running cumulative count, so the delta we still owe credits on is
 * (totalSent - charged_sent). Storing it on the row makes the
 * callback handler safe against duplicate / out-of-order POSTs from
 * the bot — same idempotency play `total_sent` itself uses.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->unsignedInteger('charged_sent')->default(0)->after('total_failed');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->dropColumn('charged_sent');
        });
    }
};
