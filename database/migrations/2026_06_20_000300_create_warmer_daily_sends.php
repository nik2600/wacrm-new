<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-date warm-up send ledger. The single source of truth for how many
 * sends a number has used (or RESERVED) on any given calendar day — so the
 * warmer can govern not just today's immediate sends but also future-scheduled
 * broadcasts + scheduled messages (which reserve against the date they will
 * actually go out, since the number's ramped budget grows day by day).
 *
 * Supersedes the single-day devices.warm_day / warm_day_count columns (which
 * stay on the table but are no longer the authority). One row per (number, day).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warmer_daily_sends')) return;
        Schema::create('warmer_daily_sends', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('device_id');
            $t->date('day');
            $t->unsignedInteger('count')->default(0);
            $t->timestamps();
            $t->unique(['device_id', 'day']);
            $t->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warmer_daily_sends');
    }
};
