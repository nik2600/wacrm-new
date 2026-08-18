<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced Meta Ads (CTWA) build — campaign-structural columns.
 *
 * The rich TARGETING data (regions / cities / zips / radius pins /
 * behaviors / life-events / exclusions / custom-audiences / locales /
 * advantage_audience toggle / detailed placements) is schemaless and
 * lives inside the existing encrypted `targeting` array column — no
 * migration needed for those (same pattern countries/interests already
 * use). Only the four CAMPAIGN-level structural knobs get real columns:
 *
 *   - budget_level          : 'adset' (legacy) | 'campaign' (CBO / Advantage campaign budget)
 *   - bid_strategy          : LOWEST_COST_WITHOUT_CAP | LOWEST_COST_WITH_BID_CAP | COST_CAP
 *   - bid_amount            : bid cap / cost cap in MINOR units (cents), nullable
 *   - special_ad_categories : ['HOUSING'|'CREDIT'|'EMPLOYMENT'|'ISSUES_ELECTIONS_POLITICS'|'NONE']
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('meta_campaigns', 'budget_level')) {
                $table->string('budget_level', 16)->default('adset')->after('lifetime_budget');
            }
            if (!Schema::hasColumn('meta_campaigns', 'bid_strategy')) {
                $table->string('bid_strategy', 40)->nullable()->after('budget_level');
            }
            if (!Schema::hasColumn('meta_campaigns', 'bid_amount')) {
                // Minor units (cents). Nullable — only used with cost/bid cap strategies.
                $table->unsignedBigInteger('bid_amount')->nullable()->after('bid_strategy');
            }
            if (!Schema::hasColumn('meta_campaigns', 'special_ad_categories')) {
                $table->json('special_ad_categories')->nullable()->after('bid_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meta_campaigns', function (Blueprint $table) {
            foreach (['budget_level', 'bid_strategy', 'bid_amount', 'special_ad_categories'] as $col) {
                if (Schema::hasColumn('meta_campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
