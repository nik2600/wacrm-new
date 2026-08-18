<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom (non-template) campaigns let the operator drop positional {{1}}
 * placeholders via the `/`-attribute picker, recording a {"1":"first_name"}
 * slot→attribute map. For an IMMEDIATE ('now') send this map was threaded
 * through the request, but SCHEDULED / RECURRING campaigns fire later from
 * the persisted row — with nowhere to read the map back from, so {{1}} would
 * ship literal. Persist it here (TEXT — JSON, encrypted via the model cast)
 * so the sweeper can resolve placeholders on every path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wpcampaigns') && !Schema::hasColumn('wpcampaigns', 'custom_variable_map')) {
            Schema::table('wpcampaigns', function (Blueprint $table) {
                $table->text('custom_variable_map')->nullable()->after('custom_quick_replies');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wpcampaigns') && Schema::hasColumn('wpcampaigns', 'custom_variable_map')) {
            Schema::table('wpcampaigns', function (Blueprint $table) {
                $table->dropColumn('custom_variable_map');
            });
        }
    }
};
