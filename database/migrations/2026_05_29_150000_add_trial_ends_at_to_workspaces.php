<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-trial support for workspaces.
 *
 * New signups can be placed on an admin-chosen default plan
 * (SystemSetting `registration_default_plan_id`). When that plan is a
 * FREE plan, the workspace is given a trial window — `trial_ends_at`
 * is stamped now()+`registration_trial_days`. The user-side trial bar
 * (resources/views/components/trial-bar.blade.php) reads this column to
 * count down, and ONLY shows for workspaces on a free plan. Paid plans
 * leave it null and never see the bar.
 *
 * Backfill: existing workspaces already on a free package get a fresh
 * trial window from deploy time so they aren't shown as "expired" the
 * moment this ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workspaces', 'trial_ends_at')) {
            Schema::table('workspaces', function (Blueprint $t) {
                $t->timestamp('trial_ends_at')->nullable()->after('billing_cycle');
            });
        }

        // Backfill: give every workspace currently on a FREE plan a fair
        // trial window starting now, so the bar shows a countdown rather
        // than an instant "trial ended" for accounts that predate this.
        $trialDays = (int) \App\Models\SystemSetting::get('registration_trial_days', 14);
        if ($trialDays <= 0) {
            $trialDays = 14;
        }

        $freePlanIds = \App\Models\Package::query()
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('free', true)
                  ->orWhere(function ($q2) {
                      $q2->where('plan_amount', 0)->where('is_custom_quote', false);
                  });
            })
            ->pluck('plan_id')
            ->all();

        if (! empty($freePlanIds)) {
            \App\Models\Workspace::query()
                ->whereIn('plan', $freePlanIds)
                ->whereNull('trial_ends_at')
                ->update([
                    'trial_ends_at' => now()->addDays($trialDays),
                    'billing_cycle' => 'trial',
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workspaces', 'trial_ends_at')) {
            Schema::table('workspaces', function (Blueprint $t) {
                $t->dropColumn('trial_ends_at');
            });
        }
    }
};
