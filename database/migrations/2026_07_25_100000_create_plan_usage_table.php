<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan_usage — an increment-only usage ledger, one row per
 * (workspace, metric, billing-period). It is the delete-proof counter behind
 * PlanLimitGuard::checkQuota(): creating a resource bumps `count`, deleting it
 * does NOT decrement — so "create 5, delete 5, create 5 again" can no longer
 * bypass a plan cap. `period` rolls when the plan (package) or billing window
 * changes, which is what makes the quota re-open when a client buys a new plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_usage')) return;

        Schema::create('plan_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            // What is being metered, e.g. 'campaign'. Maps to a plan limit key.
            $table->string('metric', 64);
            // Billing-period key (PlanUsage::period()). Distinct value per plan
            // cycle so a renewal / new plan starts the counter fresh at 0.
            $table->string('period', 64);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            // One counter row per workspace + metric + period.
            $table->unique(['workspace_id', 'metric', 'period'], 'plan_usage_ws_metric_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usage');
    }
};
