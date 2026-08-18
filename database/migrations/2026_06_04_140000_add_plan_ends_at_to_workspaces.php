<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid-plan expiry. Until now a workspace only had `trial_ends_at` (free-trial
 * window) — a PURCHASED plan never expired, so a lapsed monthly/annual
 * subscription kept every feature forever. `plan_ends_at` is set on checkout
 * (now + the package billing cycle); when it passes, Workspace::package()
 * downgrades to the free plan so paid features lock until renewal.
 * null = no expiry (free plans, enterprise/custom contracts, legacy rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'plan_ends_at')) {
                $table->timestamp('plan_ends_at')->nullable()->after('trial_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (Schema::hasColumn('workspaces', 'plan_ends_at')) {
                $table->dropColumn('plan_ends_at');
            }
        });
    }
};
