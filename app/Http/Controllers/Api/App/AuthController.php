<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Api\App\Concerns\FormatsUser;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\SocialAuthService;
use App\Services\WorkspaceProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile-app authentication. Response shapes are kept byte-compatible with
 * the existing app (login: {status, token_type, access_token, user, message};
 * register: {success, access_token, token_type, user{...credits}}), but the
 * implementation runs against our current models — Sanctum tokens, plan/trial
 * provisioning via WorkspaceProvisioner, avatar_path, email_verified_at, etc.
 */
class AuthController extends Controller
{
    use FormatsUser;

    /** Max failed credential attempts before a per-account+IP lockout kicks in. */
    private const LOGIN_MAX_ATTEMPTS = 6;
    private const LOGIN_DECAY_SECONDS = 900; // 15 min

    public function login(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $email = strtolower(trim((string) $request->email));
        $throttleKey = 'mobile-login:' . $request->ip() . '|' . sha1($email);

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            return $this->throttled($throttleKey);
        }

        $user = User::where('email', $email)->first();

        // Unified generic failure for BOTH unknown-email and wrong-password so
        // the response is not a user-enumeration oracle (#38) and the path is
        // rate-limited to defeat brute force (#19).
        if (! $user || ! Hash::check((string) $request->password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            return response()->json([
                'status' => 'error', 'error_type' => 'invalid_credentials',
                'message' => 'The email or password you entered is incorrect.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $abilities = strtolower((string) $user->role) === 'admin' ? ['admin'] : ['*'];
        $token = $user->createToken('mobile-app', $abilities)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => $this->userPayload($user),
            'message' => 'Login successful',
        ], 200);
    }

    /** Uniform 429 for every throttled auth path (no timing/oracle leak). */
    private function throttled(string $key): JsonResponse
    {
        $seconds = RateLimiter::availableIn($key);

        return response()->json([
            'status' => 'error', 'error_type' => 'too_many_attempts',
            'message' => 'Too many attempts. Please try again in ' . max(1, $seconds) . ' seconds.',
        ], 429);
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'mobile' => 'nullable|string|max:32',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'refer_code' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $autoVerify = (bool) SystemSetting::get('auto_verify_email', true);

        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->mobile = $request->mobile;
        $user->password = Hash::make($request->password);
        $user->role = 'user';
        $user->has_seen_intro = false;
        $user->email_verified_at = $autoVerify ? now() : null;

        if ($request->hasFile('image')) {
            $name = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/users'), $name);
            $user->avatar_path = 'images/users/' . $name;
        }
        $user->save();

        try { $user->assignRole('User'); } catch (\Throwable $e) { /* role optional */ }

        // Referral attribution (same service the web register uses).
        if ($refCode = $request->input('refer_code')) {
            try {
                $svc = app(ReferralService::class);
                $referrer = $svc->findReferrer($refCode, excludeUserId: $user->id);
                if ($referrer) {
                    $svc->attribute($referrer, $user, strtoupper((string) $refCode));
                }
            } catch (\Throwable $e) { /* referral never blocks signup */ }
        }

        $workspace = app(WorkspaceProvisioner::class)->provision($user);
        $token = $user->createToken('mobile-app')->plainTextToken;

        $limit = 0;
        try { $limit = (int) $workspace->effectiveLimit('monthly_messages_limit'); } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'image' => $this->avatarUrl($user),
                'credits' => ['monthly_messages_limit' => $limit],
            ],
        ], 201);
    }

    /**
     * Native social sign-in for the mobile app.
     *
     * SECURITY (#5 account takeover): the identity (provider id + e-mail) is
     * NEVER trusted from the request body. The app must send the provider's
     * OAuth token (Google id_token or access_token / Facebook access_token),
     * which we verify server-side with the provider; we then trust ONLY the
     * e-mail the provider itself returns. A client-asserted e-mail alone can
     * no longer mint a token for an arbitrary victim account.
     */
    public function socialCallback(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'provider'     => 'required|string|in:google,facebook',
                // Accept any of the common field names the native SDKs expose.
                'access_token' => 'required_without_all:id_token,token|nullable|string',
                'id_token'     => 'nullable|string',
                'token'        => 'nullable|string',
                'name'         => 'nullable|string',
                'photo'        => 'nullable|string',
            ]);

            // Throttle: an unauthenticated endpoint that mints tokens must be
            // rate limited even though each call now requires a valid provider
            // token (defence in depth against verification-endpoint abuse).
            $throttleKey = 'mobile-social:' . $request->ip();
            if (RateLimiter::tooManyAttempts($throttleKey, 20)) {
                return $this->throttled($throttleKey);
            }
            RateLimiter::hit($throttleKey, 600);

            $provider = (string) $request->provider;
            $providerToken = (string) ($request->input('id_token')
                ?: $request->input('access_token')
                ?: $request->input('token'));

            $verified = $this->verifySocialToken($provider, $providerToken, (bool) $request->input('id_token'));

            // Fail closed: no verified identity → no token. We never fall back
            // to a client-asserted e-mail.
            if (! $verified || empty($verified['id']) || empty($verified['email'])) {
                return response()->json([
                    'status' => 'error', 'error_type' => 'social_verification_failed',
                    'message' => 'We could not verify your ' . ucfirst($provider) . ' sign-in. Please try again.',
                ], 401);
            }

            $email = strtolower(trim((string) $verified['email']));
            $uid   = (string) $verified['id'];
            $name  = (string) ($verified['name'] ?: $request->input('name') ?: 'User');
            $photo = (string) ($verified['avatar'] ?: $request->input('photo') ?: '');

            // Match on the verified provider id first, then on the verified
            // e-mail (provider-owned, so linking an existing local account to
            // this social identity is safe).
            $user = User::where(fn ($q) => $q
                ->where('social_provider', $provider)->where('social_provider_id', $uid))
                ->orWhere('email', $email)
                ->first();

            $isNew = false;
            if ($user) {
                if (! $user->social_provider_id) {
                    $user->social_provider = $provider;
                    $user->social_provider_id = $uid;
                    $user->save();
                }
            } else {
                $isNew = true;
                $user = User::create([
                    'role' => 'user',
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(24)),
                    'social_provider' => $provider,
                    'social_provider_id' => $uid,
                    'avatar_path' => $photo ?: null,
                    'has_seen_intro' => false,
                ]);
                try { $user->assignRole('User'); } catch (\Throwable $e) {}
                app(WorkspaceProvisioner::class)->provision($user);
            }

            RateLimiter::clear($throttleKey);
            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token_type' => 'Bearer',
                'access_token' => $token,
                'message' => $isNew ? 'Registration successful' : 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'image' => $this->avatarUrl($user),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Authentication failed'], 500);
        }
    }

    /**
     * Verify a provider-issued token server-side and return the PROVIDER's
     * ['id','email','name','avatar'] — or null on any failure. Mirrors the
     * raw-OAuth verification the web SocialAuthService already performs.
     */
    private function verifySocialToken(string $provider, string $token, bool $preferIdToken = false): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        try {
            return $provider === 'google'
                ? $this->verifyGoogleToken($token, $preferIdToken)
                : $this->verifyFacebookToken($token);
        } catch (\Throwable $e) {
            Log::warning("[MOBILE-SOCIAL] {$provider} verification failed: " . $e->getMessage());

            return null;
        }
    }

    private function verifyGoogleToken(string $token, bool $preferIdToken): ?array
    {
        $allowedAud = array_filter([
            app(SocialAuthService::class)->clientId('google'),
            (string) SystemSetting::get('social_google_ios_client_id', ''),
            (string) SystemSetting::get('social_google_android_client_id', ''),
        ]);

        // A JWT (three dot-separated segments) is a Google id_token → validate
        // via tokeninfo, which also lets us enforce the audience (aud).
        $looksJwt = substr_count($token, '.') === 2;
        if ($preferIdToken || $looksJwt) {
            $r = Http::timeout(15)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $token]);
            if ($r->successful() && $r->json('sub')) {
                $aud = (string) $r->json('aud');
                // If we know our client id(s), the token MUST be minted for us.
                if (! empty($allowedAud) && ! in_array($aud, $allowedAud, true)) {
                    Log::warning('[MOBILE-SOCIAL] google id_token aud mismatch: ' . $aud);

                    return null;
                }
                if (($r->json('email_verified') ?? 'true') === 'false') {
                    return null;
                }

                return [
                    'id'     => (string) $r->json('sub'),
                    'email'  => (string) ($r->json('email') ?? ''),
                    'name'   => (string) ($r->json('name') ?: 'Google user'),
                    'avatar' => (string) ($r->json('picture') ?? ''),
                ];
            }
            // Fall through to access-token path if id_token lookup failed.
        }

        // Treat as an access_token: userinfo proves the token is a genuine,
        // live Google-issued credential and returns the provider-owned email.
        $u = Http::withToken($token)->timeout(15)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        if (! $u->successful() || ! $u->json('sub')) {
            return null;
        }

        return [
            'id'     => (string) $u->json('sub'),
            'email'  => (string) ($u->json('email') ?? ''),
            'name'   => (string) ($u->json('name') ?: $u->json('given_name') ?: 'Google user'),
            'avatar' => (string) ($u->json('picture') ?? ''),
        ];
    }

    private function verifyFacebookToken(string $token): ?array
    {
        $svc = app(SocialAuthService::class);

        // When we hold the app credentials, confirm the token was issued for
        // OUR Facebook app before trusting it (debug_token app_id check).
        $appId = $svc->clientId('facebook');
        $appSecret = $svc->clientSecret('facebook');
        if ($appId !== '' && $appSecret !== '') {
            $dbg = Http::timeout(15)->get('https://graph.facebook.com/debug_token', [
                'input_token'  => $token,
                'access_token' => $appId . '|' . $appSecret,
            ]);
            $data = $dbg->successful() ? (array) $dbg->json('data') : [];
            if (empty($data['is_valid']) || (string) ($data['app_id'] ?? '') !== $appId) {
                Log::warning('[MOBILE-SOCIAL] facebook token failed debug_token app_id check');

                return null;
            }
        }

        $u = Http::timeout(15)->get('https://graph.facebook.com/v19.0/me', [
            'fields'       => 'id,name,email,picture.width(256)',
            'access_token' => $token,
        ]);
        if (! $u->successful() || ! $u->json('id')) {
            return null;
        }

        return [
            'id'     => (string) $u->json('id'),
            'email'  => (string) ($u->json('email') ?? ''),
            'name'   => (string) ($u->json('name') ?: 'Facebook user'),
            'avatar' => (string) ($u->json('picture.data.url') ?? ''),
        ];
    }

    public function verifyPasscode(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'passcode' => 'required|string|min:4|max:20',
        ]);

        // A short numeric passcode is only ~10^4 combinations, so this path
        // MUST be throttled or it is trivially brute-forced into a token (#19).
        $throttleKey = 'mobile-passcode:' . $request->ip() . '|' . (int) $request->user_id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            return $this->throttled($throttleKey);
        }

        $user = User::findOrFail($request->user_id);

        if (! $user->passcode || ! Hash::check($request->passcode, $user->passcode)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            return response()->json([
                'status' => 'error',
                'message' => 'The passcode you entered is incorrect.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => $this->userPayload($user),
            'message' => 'Login successful',
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logged out.'], 200);
    }
}
