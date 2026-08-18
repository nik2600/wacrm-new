<?php

namespace App\Services;

use App\Models\Package;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Workspace;

/**
 * Provisions a fresh workspace for a brand-new account — the same plan +
 * trial resolution the web onboarding step (AuthController::storeWorkspace)
 * uses, extracted so the mobile-app register/social endpoints produce an
 * immediately-usable account in a single call.
 */
class WorkspaceProvisioner
{
    public function provision(User $user, ?string $name = null): Workspace
    {
        $name = $name ?: trim((string) $user->name);
        $name = $name !== '' ? $name . "'s Workspace" : 'My Workspace';

        // Resolve the plan a new workspace lands on: admin-picked default →
        // is_default package → first active free plan → legacy 'starter'.
        $defaultPlanId = trim((string) SystemSetting::get('registration_default_plan_id', ''));
        $package = $defaultPlanId !== ''
            ? Package::where('plan_id', $defaultPlanId)->where('status', 1)->first()
            : null;
        $package ??= Package::where('status', 1)->where('is_default', true)->first();
        $package ??= Package::where('status', 1)
            ->where(fn ($q) => $q->where('free', true)
                ->orWhere(fn ($q2) => $q2->where('plan_amount', 0)->where('is_custom_quote', false)))
            ->orderBy('sort_order')->first();
        $planSlug = $package?->plan_id ?? 'starter';

        $attrs = [
            'owner_user_id'  => $user->id,
            'name'           => $name,
            'slug'           => Workspace::generateSlug($name),
            'timezone'       => 'Asia/Kolkata',
            'brand_color'    => '#075E54',
            'plan'           => $planSlug,
            'status'         => true,
            'last_active_at' => now(),
        ];

        // A FREE default plan starts the trial countdown; paid plans never do.
        if ($package && $package->isFreePlan()) {
            $trialDays = (int) SystemSetting::get('registration_trial_days', 14);
            if ($trialDays > 0) {
                $attrs['trial_ends_at'] = now()->addDays($trialDays);
                $attrs['billing_cycle'] = 'trial';
            }
        }

        $workspace = Workspace::create($attrs);
        $workspace->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->switchWorkspace($workspace->id);

        return $workspace;
    }
}
