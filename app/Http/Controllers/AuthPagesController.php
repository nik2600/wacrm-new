<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlatformPermissions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Auth pages: login, register, logout, forgot/reset password.
 *
 * The companion AuthController handles the post-login multi-step
 * workspace setup (create / switch). Anything that's purely about
 * proving identity lives here.
 */
class AuthPagesController extends Controller
{
    /* ----------------------------- LOGIN ---------------------------- */

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Brute-force lockout (security.lockout_after_failures / window). 0 =
        // disabled. Keyed per email+IP and auto-clears after the window, so a
        // real user is never permanently locked out and a correct login resets
        // the counter immediately. This is the REAL web /login endpoint — the
        // matching guard in AuthController::login only covers the mobile API,
        // leaving this path unthrottled before now.
        $maxFail = \App\Support\SecurityPolicy::int('lockout_after_failures', 5);
        $winMin  = max(1, \App\Support\SecurityPolicy::int('lockout_window_minutes', 15));
        $rlKey   = 'login:' . sha1(mb_strtolower($credentials['email']) . '|' . $request->ip());
        if ($maxFail > 0 && \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rlKey, $maxFail)) {
            $mins = (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($rlKey) / 60);
            try { \App\Support\Audit::log('auth.lockout', ['layer' => 'platform', 'result' => 'warning', 'meta' => ['email' => $credentials['email']]]); } catch (\Throwable $e) {}
            return back()->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => __('Too many failed attempts. Try again in :m minute(s).', ['m' => max(1, $mins)])]);
        }

        // reCAPTCHA (admin-toggled). Passes through when disabled.
        if (!app(\App\Services\RecaptchaService::class)->verify(
            $request->input('g-recaptcha-response') ?: $request->input('recaptcha_token'),
            $request->ip(), 'login'
        )) {
            return back()->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => __('Please complete the captcha and try again.')]);
        }

        // Accept either the spec-canonical "remember" or the existing
        // login form's "remember_me" field so we don't break the UI.
        $remember = (bool) ($request->boolean('remember') || $request->boolean('remember_me'));

        if (Auth::attempt($credentials, $remember)) {
            // Correct credentials clear the failed-attempt counter immediately.
            \Illuminate\Support\Facades\RateLimiter::clear($rlKey);
            $request->session()->regenerate();

            // Persist the "Keep me signed in" choice so the idle SessionTimeout
            // middleware does NOT force-log-out (and wipe the long-lived remember
            // cookie) these users. Their session window is the 30-day remember
            // cookie, not the short idle timeout. Set after regenerate() so it
            // survives the session-id rotation above.
            $request->session()->put('auth.remember', $remember);

            $user = Auth::user();

            // Platform staff (Super Admin / Admin / Platform Support / Auditor)
            // log into the admin surface — they don't own a customer workspace
            // and we shouldn't push them through the "create workspace" gate.
            // Deterministic redirect to /admin/users via to() (not intended())
            // so a stale /dashboard intent in the session can't override it.
            if (PlatformPermissions::userHasPlatformAccess($user)) {
                $request->session()->forget('url.intended');
                return redirect()->to('/admin');
            }

            // If a freshly-logged-in user has zero workspaces, route
            // them through the existing workspace step before they
            // hit /dashboard (otherwise the dashboard renders empty).
            if (method_exists($user, 'workspaces') && $user->workspaces()->count() === 0) {
                return redirect()->route('register.workspace');
            }
            if (! $user->current_workspace_id) {
                $first = $user->workspaces()->first();
                if ($first) $user->switchWorkspace($first->id);
            }

            // Role-aware landing — Agent/Viewer don't have dashboard access,
            // so send them straight to /team-inbox (their real home).
            $wsRole = $user->workspaceRole();
            $landing = in_array($wsRole, ['agent', 'viewer'], true)
                ? url('/team-inbox')
                : route('user.dashboard');
            return redirect()->intended($landing);
        }

        // Failed attempt — count it against the email+IP window. The global
        // AuthFailed listener (AppServiceProvider) already writes the
        // auth.failed audit row, so we only advance the throttle here.
        if ($maxFail > 0) \Illuminate\Support\Facades\RateLimiter::hit($rlKey, $winMin * 60);

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => __('Those credentials don\'t match.')]);
    }

    /* ---------------------------- REGISTER -------------------------- */

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:191'],
            'email'        => ['required', 'email', 'max:191', 'unique:users,email'],
            'mobile'       => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'agree'        => ['accepted'],
            'ref'          => ['nullable', 'string', 'max:16'],
        ], [
            'agree.accepted' => 'You must agree to the Terms of Service.',
        ]);

        // reCAPTCHA (admin-toggled). Passes through when disabled.
        if (!app(\App\Services\RecaptchaService::class)->verify(
            $request->input('g-recaptcha-response') ?: $request->input('recaptcha_token'),
            $request->ip(), 'register'
        )) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => __('Please complete the captcha and try again.')]);
        }

        // Carry the referral code so it survives the OTP round-trip.
        $data['ref'] = $request->input('ref')
            ?: $request->cookie(\App\Http\Middleware\CaptureReferral::COOKIE_NAME);

        // Registration WhatsApp OTP. When the admin has it enabled + a sender
        // ready, verify the mobile BEFORE creating the account (no ghost users).
        $otp = app(\App\Services\Auth\RegistrationOtpService::class);
        if ($otp->isActive()) {
            if (empty($data['mobile']) || empty($data['country_code'])) {
                return back()->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['mobile' => __('A mobile number is required to verify your account.')]);
            }
            $code = $otp->generateCode();
            $res  = $otp->send($data['country_code'], $data['mobile'], $code);
            if (! ($res['ok'] ?? false)) {
                return back()->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['mobile' => $res['error'] ?? __('We could not send the verification code. Please try again.')]);
            }
            // Stash the pending signup + a HASH of the code (never the code).
            session()->put('pending_registration', [
                'data'         => $data,
                'otp_hash'     => Hash::make($code),
                'expires_at'   => now()->addMinutes($otp->ttlMinutes())->toIso8601String(),
                'attempts'     => 0,
                'last_sent_at' => now()->toIso8601String(),
            ]);
            return redirect()->route('register.verify');
        }

        return $this->finalizeRegistration($data, $request);
    }

    /**
     * Create the account, attribute the referral, fire Registered, log in, and
     * bounce to the workspace step. Shared by the direct path (OTP off) and the
     * OTP-verified path so the user is built identically either way.
     */
    private function finalizeRegistration(array $data, Request $request): RedirectResponse
    {
        // Admin can flip auto-verify ON at /admin/settings/general to skip
        // the verify-email screen entirely.
        $autoVerify = (bool) \App\Models\SystemSetting::get('auto_verify_email', true);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'mobile'            => $data['mobile']       ?? null,
            'country_code'      => $data['country_code'] ?? null,
            'password'          => Hash::make($data['password']),
            'role'              => 'user',
            'email_verified_at' => $autoVerify ? now() : null,
        ]);

        $refCode = $data['ref'] ?? null;
        if ($refCode) {
            $referralService = app(\App\Services\ReferralService::class);
            $referrer = $referralService->findReferrer($refCode, excludeUserId: $user->id);
            if ($referrer) {
                $referralService->attribute($referrer, $user, strtoupper((string) $refCode));
                cookie()->queue(cookie()->forget(\App\Http\Middleware\CaptureReferral::COOKIE_NAME));
            }
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        if (! $autoVerify) {
            \App\Http\Controllers\EmailVerificationController::send($user);
        }

        return redirect()->route('register.workspace');
    }

    /* ------------------- REGISTRATION OTP VERIFY -------------------- */

    public function showRegisterOtp(): View|RedirectResponse
    {
        $pending = session('pending_registration');
        if (! $pending) {
            return redirect()->route('register')->withErrors(['email' => __('Please start registration again.')]);
        }
        $otp = app(\App\Services\Auth\RegistrationOtpService::class);
        return view('auth.register.verify', [
            'masked'     => $this->maskNumber($pending['data']['country_code'] ?? '', $pending['data']['mobile'] ?? ''),
            'ttlMinutes' => $otp->ttlMinutes(),
            'cooldown'   => $otp->resendCooldownSec(),
            'sinceSent'  => (int) now()->diffInSeconds(\Illuminate\Support\Carbon::parse($pending['last_sent_at'])),
        ]);
    }

    public function verifyRegisterOtp(Request $request): RedirectResponse
    {
        $pending = session('pending_registration');
        if (! $pending) {
            return redirect()->route('register')->withErrors(['email' => __('Please start registration again.')]);
        }
        $request->validate(['code' => ['required', 'string', 'max:8']]);
        $otp = app(\App\Services\Auth\RegistrationOtpService::class);

        if (\Illuminate\Support\Carbon::parse($pending['expires_at'])->isPast()) {
            return back()->withErrors(['code' => __('That code has expired. Please request a new one.')]);
        }
        if (($pending['attempts'] ?? 0) >= $otp->maxAttempts()) {
            return back()->withErrors(['code' => __('Too many attempts. Please request a new code.')]);
        }
        if (! Hash::check(trim((string) $request->input('code')), $pending['otp_hash'])) {
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;
            session()->put('pending_registration', $pending);
            return back()->withErrors(['code' => __('Incorrect code. Please try again.')]);
        }

        $data = $pending['data'];
        session()->forget('pending_registration');
        return $this->finalizeRegistration($data, $request);
    }

    public function resendRegisterOtp(Request $request): RedirectResponse
    {
        $pending = session('pending_registration');
        if (! $pending) {
            return redirect()->route('register')->withErrors(['email' => __('Please start registration again.')]);
        }
        $otp   = app(\App\Services\Auth\RegistrationOtpService::class);
        $since = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($pending['last_sent_at']));
        if ($since < $otp->resendCooldownSec()) {
            return back()->withErrors(['code' => __('Please wait a moment before requesting another code.')]);
        }

        $code = $otp->generateCode();
        $res  = $otp->send($pending['data']['country_code'], $pending['data']['mobile'], $code);
        if (! ($res['ok'] ?? false)) {
            return back()->withErrors(['code' => $res['error'] ?? __('Could not resend the code. Please try again.')]);
        }
        $pending['otp_hash']     = Hash::make($code);
        $pending['expires_at']   = now()->addMinutes($otp->ttlMinutes())->toIso8601String();
        $pending['attempts']     = 0;
        $pending['last_sent_at'] = now()->toIso8601String();
        session()->put('pending_registration', $pending);

        return back()->with('status', __('A new code is on its way to your WhatsApp.'));
    }

    private function maskNumber(string $cc, string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile);
        return trim($cc) . ' •••• ••' . substr($digits, -2);
    }

    /* ---------------------------- LOGOUT ---------------------------- */

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    /* ----------------------- FORGOT PASSWORD ------------------------ */

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        // MAIL_MAILER=log in this dev install, so the reset link ends
        // up in storage/logs/laravel.log — log a friendly hint there
        // too so devs know where to grab it.
        if ($status === Password::RESET_LINK_SENT) {
            Log::info('Password reset link generated for ' . $request->email
                . ' — see the Mailable above for the URL.');
        } else {
            // Log the real broker status server-side only (INVALID_USER,
            // RESET_THROTTLED, …) so operators can debug without leaking it
            // to the client.
            Log::info('Password reset link status for ' . $request->email . ': ' . $status);
        }

        // Always return the SAME neutral message regardless of broker status
        // so the response can't be used to tell whether an email is registered
        // (account-enumeration oracle). Preserves normal UX: the user is told
        // to check their inbox either way.
        return back()->with('status', __('A password-reset link has been sent if that email exists.'));
    }

    /* ------------------------ RESET PASSWORD ------------------------ */

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Rotate the remember_token so any previously issued remember-me
                // cookie stops validating, and stamp the change time so
                // password-max-age logic stays accurate.
                $user->forceFill([
                    'password'            => Hash::make($password),
                    'remember_token'      => Str::random(60),
                    'password_changed_at' => now(),
                ])->save();

                // A reset is an account-recovery action: assume every prior
                // credential may be compromised. Revoke API (Sanctum) tokens and
                // purge ALL existing DB sessions for this user so a stolen
                // cookie / Bearer token cannot survive the reset. The user
                // re-authenticates fresh at /login right after.
                try { $user->tokens()->delete(); } catch (\Throwable $e) {}
                try {
                    \Illuminate\Support\Facades\DB::table('sessions')
                        ->where('user_id', $user->id)
                        ->delete();
                } catch (\Throwable $e) {}
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('Password reset. Please sign in.'));
        }
        return back()->withErrors(['email' => __($status)]);
    }
}
