<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring-campaign cadence. Until now `schedule_type='recurring'` had no way
 * to say HOW OFTEN it repeats — only a single send_date/send_time. These two
 * columns let a recurring campaign re-arm itself: after each fire the sweeper
 * advances send_date by `repeat_interval` (daily/weekly/monthly) and keeps
 * going until `repeat_until` (null = forever).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wpcampaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('wpcampaigns', 'repeat_interval')) {
                $table->string('repeat_interval', 16)->nullable()->after('timezone'); // daily|weekly|monthly
            }
            if (!Schema::hasColumn('wpcampaigns', 'repeat_until')) {
                $table->date('repeat_until')->nullable()->after('repeat_interval');
            }
            if (!Schema::hasColumn('wpcampaigns', 'last_run_at')) {
                $table->timestamp('last_run_at')->nullable()->after('repeat_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wpcampaigns', function (Blueprint $table) {
            foreach (['repeat_interval', 'repeat_until', 'last_run_at'] as $col) {
                if (Schema::hasColumn('wpcampaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
