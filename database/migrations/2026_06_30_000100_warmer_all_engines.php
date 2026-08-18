<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the WhatsApp Warmer to WABA + Twilio numbers.
 *
 *   wa_provider_configs.warmer_config / warm_day / warm_day_count
 *       — same per-number warm-up profile the `devices` table got, so an
 *         Official/Twilio number can carry its own ramp + gaps + active hours.
 *   warmer_daily_sends.engine
 *       — the ledger keyed by `device_id` only would COLLIDE: a Baileys
 *         devices.id and a wa_provider_configs.id can both be 5. Scope every
 *         counter by engine so each channel keeps its own daily tally.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_provider_configs')) {
            Schema::table('wa_provider_configs', function (Blueprint $t) {
                if (!Schema::hasColumn('wa_provider_configs', 'warmer_config'))  $t->json('warmer_config')->nullable();
                if (!Schema::hasColumn('wa_provider_configs', 'warm_day'))       $t->date('warm_day')->nullable();
                if (!Schema::hasColumn('wa_provider_configs', 'warm_day_count')) $t->unsignedInteger('warm_day_count')->default(0);
            });
        }

        if (Schema::hasTable('warmer_daily_sends')) {
            if (!Schema::hasColumn('warmer_daily_sends', 'engine')) {
                Schema::table('warmer_daily_sends', function (Blueprint $t) {
                    $t->string('engine', 16)->default('baileys')->after('device_id');
                });
                // Rebuild the unique key to include the engine so the same id on
                // two engines keeps two independent daily counters.
                Schema::table('warmer_daily_sends', function (Blueprint $t) {
                    try { $t->dropUnique(['device_id', 'day']); } catch (\Throwable $e) {}
                });
                Schema::table('warmer_daily_sends', function (Blueprint $t) {
                    $t->unique(['engine', 'device_id', 'day']);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warmer_daily_sends') && Schema::hasColumn('warmer_daily_sends', 'engine')) {
            Schema::table('warmer_daily_sends', function (Blueprint $t) {
                try { $t->dropUnique(['engine', 'device_id', 'day']); } catch (\Throwable $e) {}
                $t->dropColumn('engine');
            });
            Schema::table('warmer_daily_sends', function (Blueprint $t) {
                try { $t->unique(['device_id', 'day']); } catch (\Throwable $e) {}
            });
        }
        if (Schema::hasTable('wa_provider_configs')) {
            Schema::table('wa_provider_configs', function (Blueprint $t) {
                foreach (['warmer_config', 'warm_day', 'warm_day_count'] as $c) {
                    if (Schema::hasColumn('wa_provider_configs', $c)) $t->dropColumn($c);
                }
            });
        }
    }
};
