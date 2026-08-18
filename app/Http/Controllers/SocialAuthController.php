<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocialAuthService;
use App\Support\PlatformPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Google / Facebook social sign-in (SDK-free, via SocialAuthService).
 * Routes: GET /auth/{provider}/redirect, GET /auth/{provider}/callback.
 */
class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialAuthService $social) {}

    /** Kick off the OAuth consent redirect. */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        if (!$this->social->isProvider($provider) || !$this->social->enabled($provider)) {
            return redirect()->route('login')->withErrors(['email' => __('That sign-in method is not available.')]);
        }
        $state = Str::random(40);
        $request->session()->put('social_oauth_state', $state);
        $request->session()->put('social_oauth_provider', $provider);
        return redirect()->away($this->social->authorizeUrl($provider, $state));
    }

    /** Handle the provider callback: verify, fetch profile, find/create, log in. */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (!$this->social->isProvider($provider) || !$this->social->enabled($provider)) {
            return redirect()->route('login')->withErrors(['email' => __('That sign-in method is not available.')]);
        }
        if ($request->has('error')) {
            return redirect()->route('login')->withErrors(['email' => __('Sign-in was cancelled.')]);
        }

        // CSRF: the state we generated must come back unchanged.
        $state = (string) $request->query('state', '');
        if (!$state || $state !== $request->session()->pull('social_oauth_state')) {
            return redirect()->route('login')->withErrors(['email' => __('Sign-in expired — please try again.')]);
        }

        $profile = $this->social->fetchUser($provider, (string) $request->query('code', ''));
        if (!$profile) {
            return redirect()->route('login')->withErrors(['email' => __('Could not complete :p sign-in. Please try again.', ['p' => ucfirst($provider)])]);
        }

        $user = $this->resolveUser($profile);
        if (!$user) {
            // Facebook can withhold email; we can't create an account without one.
            return redirect()->route('login')->withErrors(['email' => __('Your :p account did not share an email address, so we could not sign you in. Use email & password instead.', ['p' => ucfirst($provider)])]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Same landing rules as the password login.
        if (PlatformPermissions::userHasPlatformAccess($user)) {
            return redirect()->to('/admin');
        }
        if (method_exists($user, 'workspaces') && $user->workspaces()->count() === 0) {
            return redirect()->route('register.workspace');
        }
        if (!$user->current_workspace_id && ($first = $user->workspaces()->first())) {
            $user->switchWorkspace($first->id);
        }
        $wsRole = $user->workspaceRole();
        return redirect()->to(in_array($wsRole, ['agent', 'viewer'], true) ? '/team-inbox' : route('user.dashboard'));
    }

    /**
     * Match an existing account (by provider id, then by email) or create a
     * new one. Returns null only when there's no email to key on.
     */
    private function resolveUser(array $profile): ?User
    {
        // 1) Returning social user — matched on provider + stable id.
        $byProvider = User::where('social_provider', $profile['provider'])
            ->where('social_provider_id', $profile['id'])->first();
        if ($byProvider) {
            $this->backfillAvatar($byProvider, $profile);
            return $byProvider;
        }

        $email = strtolower(trim((string) $profile['email']));
        if ($email === '') return null;

        // 2) Existing email account — link the social identity to it.
        $byEmail = User::where('email', $email)->first();
        if ($byEmail) {
            $byEmail->forceFill([
                'social_provider'    => $profile['provider'],
                'social_provider_id' => $profile['id'],
                'email_verified_at'  => $byEmail->email_verified_at ?: now(),
            ])->save();
            $this->backfillAvatar($byEmail, $profile);
            return $byEmail;
        }

        // 3) Brand-new account. OAuth email is provider-verified, so mark
        //    verified. A random password keeps the column populated; the
        //    user can set a real one later via "forgot password".
        return User::create([
            'name'               => $profile['name'] ?: Str::before($email, '@'),
            'email'              => $email,
            'password'           => Hash::make(Str::random(48)),
            'role'               => 'user',
            'email_verified_at'  => now(),
            'social_provider'    => $profile['provider'],
            'social_provider_id' => $profile['id'],
            'avatar_path'        => $profile['avatar'] ?: null,
        ]);
    }

    private function backfillAvatar(User $user, array $profile): void
    {
        if (empty($user->avatar_path) && !empty($profile['avatar'])) {
            $user->forceFill(['avatar_path' => $profile['avatar']])->save();
        }
    }
}
