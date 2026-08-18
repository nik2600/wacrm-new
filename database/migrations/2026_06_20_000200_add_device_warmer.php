<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Warmer (Unofficial-API only) — per-number warm-up settings the
 * user configures themselves. Risk-REDUCTION, not ban-proof.
 *
 *   devices.warmer_config   — JSON: the whole per-number warm-up profile
 *                             (enabled, ramping daily budget, send gaps,
 *                             active hours, spintax, started_at).
 *   devices.warm_day        — calendar date of the current daily counter
 *   devices.warm_day_count  — sends counted against today's ramped budget
 *
 * The daily counter is calendar-day (resets at midnight in the workspace tz),
 * unlike the existing rolling `sent_24h`. Health score is computed on the fly
 * from sent_24h/failed_24h/connection/age — no column needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            if (!Schema::hasColumn('devices', 'warmer_config')) {
                $t->json('warmer_config')->nullable()->after('failed_24h');
            }
            if (!Schema::hasColumn('devices', 'warm_day')) {
                $t->date('warm_day')->nullable()->after('warmer_config');
            }
            if (!Schema::hasColumn('devices', 'warm_day_count')) {
                $t->unsignedInteger('warm_day_count')->default(0)->after('warm_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn(['warmer_config', 'warm_day', 'warm_day_count']);
        });
    }
};
