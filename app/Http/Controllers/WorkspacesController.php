<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticated-user workspace pages.
 *
 * The original /register/workspace flow lives in AuthController and
 * is part of the multi-step onboarding (Step 2 of 2). Once a user is
 * logged in and already has at least one workspace, they want a
 * proper page inside the app shell — not the onboarding screen — to
 * create another. That's what this controller is for.
 */
class WorkspacesController extends Controller
{
    public function create(): View
    {
        $user = Auth::user();
        // Plan gate — if the owner has hit their workspaces_per_owner_limit, the
        // form is useless (store() would just throw + bounce back). Tell the
        // blade to show the "limit reached, upgrade" message INSTEAD of the form,
        // even when the user reaches /workspaces/create directly by URL.
        $limitReached = $user ? !$user->canCreateWorkspace() : false;
        return view('user.workspaces.create', [
            'existing'       => $user ? $user->workspaces : collect(),
            'suggested_name' => '',
            'limitReached'   => $limitReached,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'industry'    => 'nullable|string|max:64',
            'size_range'  => 'nullable|string|max:32',
            'timezone'    => 'nullable|string|max:64',
            // Mandatory contact mobile — kept on the owner's account.
            'mobile'       => ['required', 'string', 'max:20', 'regex:/^[0-9()+\-\s]{6,20}$/'],
            'country_code' => ['required', 'string', 'max:8'],
        ], [
            'mobile.required' => 'A mobile number is required.',
            'mobile.regex'    => 'Enter a valid mobile number.',
        ]);

        $user->update([
            'mobile'       => preg_replace('/\s+/', '', $data['mobile']),
            'country_code' => $data['country_code'],
        ]);

        // Plan: workspaces-per-owner cap. Checked against the user's
        // CURRENT workspace's plan (the one they're inviting from).
        \App\Services\PlanLimitGuard::check(
            $user->currentWorkspace,
            'workspaces_per_owner_limit',
            Workspace::where('owner_user_id', $user->id)->count(),
        );

        $workspace = Workspace::create([
            'owner_user_id'  => $user->id,
            'name'           => $data['name'],
            'slug'           => Workspace::generateSlug($data['name']),
            'industry'       => $data['industry']   ?? null,
            'size_range'     => $data['size_range'] ?? null,
            'timezone'       => $data['timezone']   ?? 'Asia/Kolkata',
            'brand_color'    => null,   // follow the admin theme until someone picks one
            'plan'           => 'starter',
            'status'         => true,
            'last_active_at' => now(),
        ]);

        $workspace->members()->attach($user->id, [
            'role'      => 'owner',
            'joined_at' => now(),
        ]);

        $user->switchWorkspace($workspace->id);

        NotificationHelper::toUser(
            $user->id,
            'Workspace created',
            'Workspace "' . $workspace->name . '" is ready. Switch between workspaces from the top bar.',
            ['category' => 'system', 'severity' => 'success', 'action_url' => '/dashboard']
        );

        return redirect()->route('user.dashboard')
            ->with('status', 'Workspace "' . $workspace->name . '" is ready.');
    }
}
