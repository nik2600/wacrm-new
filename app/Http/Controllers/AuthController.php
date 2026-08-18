<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PlatformPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Auth + multi-step register.
 *
 * Adapted from D:\wadesk_2806\New folder\app\Http\Controllers\AuthController.php
 * with one big addition: registration is now a 2-step flow that scaffolds
 * a Workspace alongside the user.
 *
 *   Step 1 — account     (name, email, password)
 *   Step 2 — workspace   (workspace name, slug, timezone, brand color)
 *
 * Each step lives on its own URL so the form fits in a single screen
 * with no scroll. Step 1 creates the user (logged-in immediately) and
 * redirects to step 2; step 2 creates the workspace, attaches the user
 * as owner, sets it as current_workspace_id, then redirects to /dashboard.
 *
 * Workspaces are the new top-level scoping unit — every user can have
 * many, switch between them, and each one has independent data.
 */
class AuthController extends Controller
{
    /* --------------------------- LOGIN ------------------------------ */

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Brute-force lockout (security.lockout_after_failures / window). 0 =
        // disabled. Keyed per email+IP and auto-clears after the window, so a
        // real user is never permanently locked out and a correct login resets
        // the counter immediately.
        $maxFail = \App\Support\SecurityPolicy::int('lockout_after_failures', 5);
        $winMin  = max(1, \App\Support\SecurityPolicy::int('lockout_window_minutes', 15));
        $rlKey   = 'login:' . sha1(mb_strtolower($data['email']) . '|' . $request->ip());
        if ($maxFail > 0 && \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rlKey, $maxFail)) {
            $mins = (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($rlKey) / 60);
            try { \App\Support\Audit::log('auth.lockout', ['layer' => 'platform', 'result' => 'warning', 'meta' => ['email' => $data['email']]]); } catch (\Throwable $e) {}
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Too many failed attempts. Try again in ' . max(1, $mins) . ' minute(s).']);
        }

        // "Remember me" honours the admin toggle (default on).
        $remember = $request->boolean('remember_me') && \App\Support\SecurityPolicy::bool('remember_me_enabled', true);

        if (!Auth::attempt($data, $remember)) {
            if ($maxFail > 0) \Illuminate\Support\Facades\RateLimiter::hit($rlKey, $winMin * 60);
            try { \App\Support\Audit::log('auth.failed', ['layer' => 'platform', 'result' => 'failure', 'meta' => ['email' => $data['email']]]); } catch (\Throwable $e) {}
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Incorrect email or password.']);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($rlKey);
        $user = Auth::user();

        // Accept any PENDING team invitation. A member invited by email is
        // attached to the workspace with joined_at = NULL ("pending"); the
        // invite email links here, so signing in IS the accept step. Flip them
        // to active now — otherwise the inviter sees "pending" forever.
        // DIAGNOSTIC LOGGING (temporary) — writes to storage/logs/laravel.log
        // so we can see on the live server exactly what happens at login.
        try {
            $pendingBefore = \Illuminate\Support\Facades\DB::table('workspace_user')
                ->where('user_id', $user->id)
                ->whereNull('joined_at')
                ->get(['id', 'workspace_id', 'role', 'invited_at', 'joined_at']);

            $rowsUpdated = \Illuminate\Support\Facades\DB::table('workspace_user')
                ->where('user_id', $user->id)
                ->whereNull('joined_at')
                ->update(['joined_at' => now()]);

            \Illuminate\Support\Facades\Log::info('[invite-accept] login membership activation', [
                'user_id'         => $user->id,
                'email'           => $user->email,
                'pending_before'  => $pendingBefore->count(),
                'pending_rows'    => $pendingBefore->toArray(),
                'rows_updated'    => $rowsUpdated,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[invite-accept] activation FAILED', [
                'user_id' => $user->id ?? null,
                'email'   => $user->email ?? null,
                'error'   => $e->getMessage(),
            ]);
        }

        // Password max-age (security.password_max_age_days, 0 = never). Soft
        // notice: flag the account + surface a message so the user changes it
        // on the security screen. Never blocks the login itself.
        if (\App\Support\PasswordPolicy::isStale($user->password_changed_at ?? null)) {
            try { $user->forceFill(['force_password_change' => true])->save(); } catch (\Throwable $e) {}
            $request->session()->flash('warning', 'Your password has expired under the security policy — please set a new one.');
        }

        // Platform staff (Super Admin / Admin / Platform Support / Auditor)
        // log into the admin surface, not the customer app. They don't need
        // a workspace — workspaces are a customer concept — so we skip the
        // workspace-creation gate entirely for them. Lands on /admin/users
        // (the operator's most-used screen) rather than /admin/dashboard.
        //
        // Using redirect()->to() not intended() — intended() falls back to
        // whatever URL was visited before login (often `/`, which is the
        // user dashboard). For admins we want a deterministic landing
        // regardless of how they arrived at /login.
        if (PlatformPermissions::userHasPlatformAccess($user)) {
            $request->session()->forget('url.intended');
            // Honour a PWA deep link (?next=, only submitted by the INSTALLED app)
            // so a platform admin opening the Team-Inbox app ALSO lands on the
            // inbox. Normal web admin logins (no ?next=) still go to /admin/users.
            $next = (string) $request->input('next', '');
            if ($next !== '' && \Illuminate\Support\Str::startsWith($next, '/') && !\Illuminate\Support\Str::startsWith($next, '//')) {
                return redirect()->to($next)->with('status', 'Welcome back, ' . $user->name . '.');
            }
            return redirect()->to('/admin/users')
                ->with('status', 'Welcome back, ' . $user->name . '.');
        }

        // Make sure they have at least one workspace; if not, send them
        // through step 2 so they can create one.
        if ($user->workspaces()->count() === 0) {
            return redirect()->route('register.workspace');
        }

        // Set current workspace if not already.
        if (!$user->current_workspace_id) {
            $first = $user->workspaces()->first();
            if ($first) $user->switchWorkspace($first->id);
        }

        // Honour an explicit ?next= deep link (e.g. the shared Team-Inbox app) —
        // carried as a query param so it survives the session/cookie quirks that
        // made intended() unreliable. Local relative paths ONLY (no open-redirect).
        $next = (string) $request->input('next', '');
        if ($next !== '' && \Illuminate\Support\Str::startsWith($next, '/') && !\Illuminate\Support\Str::startsWith($next, '//')) {
            return redirect()->to($next)->with('status', 'Welcome back, ' . $user->name . '.');
        }

        return redirect()->intended('/dashboard')->with('status', 'Welcome back, ' . $user->name . '.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /* ----------------------- REGISTER STEP 1 ------------------------ */

    public function showRegister(): View
    {
        return view('auth.register.step1');
    }

    public function registerAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:191',
            'email'                 => 'required|email|max:191|unique:users,email',
            // Mobile is MANDATORY — we contact users for account verification,
            // support and security notices. National number + dial code.
            'mobile'                => ['required', 'string', 'max:20', 'regex:/^[0-9()+\-\s]{6,20}$/'],
            'country_code'          => ['required', 'string', 'max:8'],
            'password'              => ['required', 'confirmed', \App\Support\PasswordPolicy::rule()],
            'agree'                 => 'accepted',
        ], [
            'agree.accepted'  => 'You must agree to the Terms of Service.',
            'mobile.required' => 'A mobile number is required.',
            'mobile.regex'    => 'Enter a valid mobile number.',
        ]);

        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'mobile'              => preg_replace('/\s+/', '', $data['mobile']),
            'country_code'        => $data['country_code'],
            'password'            => $data['password'],   // hashed via cast
            'password_changed_at' => now(),
            'site_name'           => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'role'                => 'U',
        ]);

        // Referral attribution. Code can come from either ?ref= on the
        // form post, the captured cookie, or a hidden form field —
        // CaptureReferral middleware drops it into the cookie on any
        // earlier visit, so the cookie path is the common one.
        $refCode = $request->input('ref')
            ?: $request->cookie(\App\Http\Middleware\CaptureReferral::COOKIE_NAME);
        if ($refCode) {
            $referralService = app(\App\Services\ReferralService::class);
            $referrer = $referralService->findReferrer($refCode, excludeUserId: $user->id);
            if ($referrer) {
                $referralService->attribute($referrer, $user, strtoupper((string) $refCode));
                cookie()->queue(cookie()->forget(\App\Http\Middleware\CaptureReferral::COOKIE_NAME));
            }
        }

        Auth::login($user);

        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        NotificationHelper::toUser(
            $user->id,
            'Welcome to ' . $appName,
            'Your account is ready. Set up your first workspace to start sending.',
            ['category' => 'system', 'severity' => 'success']
        );

        return redirect()->route('register.workspace');
    }

    /* ----------------------- REGISTER STEP 2 ------------------------ */

    public function showWorkspaceStep(): View
    {
        $user = Auth::user();
        // If the user already has at least one workspace, treat this
        // as "create another" and don't force-skip — but show the
        // existing list so they know what they have.
        $existing = $user ? $user->workspaces : collect();
        return view('auth.register.step2', [
            'existing' => $existing,
            'suggested_name' => $user ? Str::title(Str::before($user->email, '@')) . "'s workspace" : '',
        ]);
    }

    public function storeWorkspace(Request $request): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Mobile + country code are now collected (and, with OTP, verified) at
        // registration — and the step-2 form no longer carries those fields. So
        // require them here ONLY if the account somehow still has none; otherwise
        // they're optional and we never wipe the existing values.
        $needMobile = empty($user->mobile);
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'industry'    => 'nullable|string|max:64',
            'size_range'  => 'nullable|string|max:32',
            'timezone'    => 'nullable|string|max:64',
            'brand_color' => 'nullable|string|max:16',
            'mobile'       => [$needMobile ? 'required' : 'nullable', 'string', 'max:20', 'regex:/^[0-9()+\-\s]{6,20}$/'],
            'country_code' => [$needMobile ? 'required' : 'nullable', 'string', 'max:8'],
        ], [
            'mobile.required' => 'A mobile number is required.',
            'mobile.regex'    => 'Enter a valid mobile number.',
        ]);

        // Only touch the owner's mobile when this form actually supplied one.
        if (!empty($data['mobile'])) {
            $user->update([
                'mobile'       => preg_replace('/\s+/', '', $data['mobile']),
                'country_code' => $data['country_code'] ?? $user->country_code,
            ]);
        }
        $slug = Workspace::generateSlug($data['name']);

        // Resolve the plan a new workspace lands on. Admin picks it at
        // /admin/settings/general; fall back to a package flagged
        // is_default, then the first active free plan, then the legacy
        // 'starter' slug so signup never breaks if plans are misconfigured.
        $defaultPlanId = trim((string) \App\Models\SystemSetting::get('registration_default_plan_id', ''));
        $package = $defaultPlanId !== ''
            ? \App\Models\Package::where('plan_id', $defaultPlanId)->where('status', 1)->first()
            : null;
        $package ??= \App\Models\Package::where('status', 1)->where('is_default', true)->first();
        $package ??= \App\Models\Package::where('status', 1)
            ->where(fn ($q) => $q->where('free', true)
                ->orWhere(fn ($q2) => $q2->where('plan_amount', 0)->where('is_custom_quote', false)))
            ->orderBy('sort_order')->first();
        $planSlug = $package?->plan_id ?? 'starter';

        $attrs = [
            'owner_user_id' => $user->id,
            'name'          => $data['name'],
            'slug'          => $slug,
            'industry'      => $data['industry'] ?? null,
            'size_range'    => $data['size_range'] ?? null,
            'timezone'      => $data['timezone'] ?? 'Asia/Kolkata',
            'plan'          => $planSlug,
            'status'        => true,
            'last_active_at'=> now(),
        ];

        // brand_color is only SET when the operator actually picked one.
        //
        // We want it null so workspace_brand_color() follows the active theme
        // instead of being pinned to the shipped green forever. But
        // `workspaces.brand_color` shipped as NOT NULL DEFAULT '#075E54', so
        // passing an explicit null threw
        //   SQLSTATE[23000] 1048 Column 'brand_color' cannot be null
        // and broke registration outright on every install that had not yet run
        // the nullable migration. Omitting the key instead lets the column
        // default apply, so signup works on old and new schemas alike.
        if (! empty($data['brand_color'])) {
            $attrs['brand_color'] = $data['brand_color'];
        }

        // A FREE default plan starts a trial countdown (the user-side
        // trial bar reads trial_ends_at). trial_days = 0 means the free
        // plan has no expiry, so leave it null and show no bar. Paid
        // plans never get a trial window.
        if ($package && $package->isFreePlan()) {
            $trialDays = (int) \App\Models\SystemSetting::get('registration_trial_days', 14);
            if ($trialDays > 0) {
                $attrs['trial_ends_at'] = now()->addDays($trialDays);
                $attrs['billing_cycle'] = 'trial';
            }
        }

        $workspace = Workspace::create($attrs);

        $workspace->members()->attach($user->id, [
            'role'      => 'owner',
            'joined_at' => now(),
        ]);

        $user->switchWorkspace($workspace->id);

        NotificationHelper::toUser(
            $user->id,
            'Workspace created',
            'Workspace "' . $workspace->name . '" is ready. You can switch between workspaces from the top bar.',
            ['category' => 'system', 'severity' => 'success', 'action_url' => '/dashboard']
        );

        return redirect()->route('register.plan')->with('status', 'Workspace "' . $workspace->name . '" is ready.');
    }

    /* ----------------------- REGISTER STEP 3 (PLAN) ----------------- */

    public function showPlanStep(): View
    {
        // PRIMARY choice on this step = subscription PLANS (App\Models\Package),
        // matching exactly how /account/plans lists them (active, by sort order
        // then price). Credit packs are the SECONDARY overflow top-up option.
        $packages = \App\Models\Package::active()
            ->orderBy('sort_order')
            ->orderBy('plan_amount')
            ->get();

        // Currency for price formatting — workspace's, falling back to platform.
        $currency = optional(auth()->user()?->currentWorkspace)->currency
            ?? strtoupper((string) \App\Models\SystemSetting::get('default_currency', 'USD'));

        // Secondary: wallet top-up packs (for overflow / after the plan ends).
        $creditPackages = \App\Models\CreditPackage::query()->active()->ordered()->get();

        return view('auth.register.step3', compact('packages', 'currency', 'creditPackages'));
    }

    public function skipPlanStep(): RedirectResponse
    {
        // User chose to skip the credit-pack step. Land them on the
        // dashboard. They can buy a pack any time from the wallet.
        return redirect('/dashboard')->with('status', 'Welcome — top up credits any time from your wallet.');
    }

    /* ----------------------- WORKSPACE SWITCH ----------------------- */

    public function switchWorkspace(Request $request, int $id): RedirectResponse
    {
        if (!Auth::check()) return redirect()->route('login');
        $user = Auth::user();
        if (!$user->switchWorkspace($id)) {
            return back()->with('error', 'You are not a member of that workspace.');
        }
        $name = Workspace::find($id)?->name ?? 'workspace';
        return back()->with('status', 'Switched to ' . $name . '.');
    }
}
