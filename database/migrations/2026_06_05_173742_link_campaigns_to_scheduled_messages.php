<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way link between a WA campaign and the ScheduledMessage it is mirrored
 * into when scheduled / recurring. This lets the Node status callbacks
 * (per-recipient sent/delivered/read/failed) be reflected onto the campaign's
 * own counters so its detail page tracks live.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_messages') && !Schema::hasColumn('scheduled_messages', 'campaign_id')) {
            Schema::table('scheduled_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('campaign_id')->nullable()->after('id')->index();
            });
        }
        if (Schema::hasTable('wpcampaigns') && !Schema::hasColumn('wpcampaigns', 'scheduled_message_id')) {
            Schema::table('wpcampaigns', function (Blueprint $table) {
                $table->unsignedBigInteger('scheduled_message_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('scheduled_messages', 'campaign_id')) {
            Schema::table('scheduled_messages', fn (Blueprint $table) => $table->dropColumn('campaign_id'));
        }
        if (Schema::hasColumn('wpcampaigns', 'scheduled_message_id')) {
            Schema::table('wpcampaigns', fn (Blueprint $table) => $table->dropColumn('scheduled_message_id'));
        }
    }
};
