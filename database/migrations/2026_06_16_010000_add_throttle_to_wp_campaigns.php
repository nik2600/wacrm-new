<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign "Smart Delivery" anti-ban throttling. All nullable —
 * a NULL column means "use the platform-wide admin default" so every
 * existing campaign keeps its exact current pacing (byte-identical).
 *
 *   throttle_min_sec / throttle_max_sec — random delay BETWEEN messages
 *       (per recipient). When both set + max>=min, the paced loop sleeps
 *       a fresh random_int(min,max) per message instead of the global
 *       msg_gap ±20%. This is the per-user interval + jitter in one.
 *   batch_size / batch_pause_min — per-campaign batching overrides
 *       (size = messages per batch, pause = minutes between batches).
 *   daily_limit — max sends per day for THIS campaign. On reaching it the
 *       run stops and re-arms for the next day via the sweeper, so a
 *       1000+ blast is spread safely under WhatsApp's daily ban radar.
 *   window_start / window_end — "HH:MM" active sending window in the
 *       campaign's own timezone. Outside it the run re-arms to the next
 *       window open instead of sending at an unnatural hour.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wpcampaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('throttle_min_sec')->nullable()->after('repeat_until');
            $table->unsignedSmallInteger('throttle_max_sec')->nullable()->after('throttle_min_sec');
            $table->unsignedSmallInteger('batch_size')->nullable()->after('throttle_max_sec');
            $table->unsignedSmallInteger('batch_pause_min')->nullable()->after('batch_size');
            $table->unsignedInteger('daily_limit')->nullable()->after('batch_pause_min');
            $table->string('window_start', 5)->nullable()->after('daily_limit');
            $table->string('window_end', 5)->nullable()->after('window_start');
        });
    }

    public function down(): void
    {
        Schema::table('wpcampaigns', function (Blueprint $table) {
            $table->dropColumn([
                'throttle_min_sec', 'throttle_max_sec', 'batch_size',
                'batch_pause_min', 'daily_limit', 'window_start', 'window_end',
            ]);
        });
    }
};
