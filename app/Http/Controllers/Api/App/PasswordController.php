<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;

/**
 * Mobile-app forgot-password (e-mail OTP) + authenticated change-password.
 * OTPs are stored in Laravel's existing password_reset_tokens table; the
 * response shapes match the app ({success, message} / {success, error}).
 */
class PasswordController extends Controller
{
    /** OTP lifetime + brute-force guardrails. */
    private const OTP_TTL_MINUTES = 15;
    private const OTP_MAX_VERIFY_ATTEMPTS = 5;
    private const OTP_SEND_MAX = 5;            // OTP requests per email/IP window
    private const OTP_WINDOW_SECONDS = 900;    // 15 min

    public function sendOtp(Request $request): JsonResponse
    {
        // No `exists:users,email` — the response is IDENTICAL whether or not
        // the account exists, so this is no longer an enumeration oracle (#23).
        $request->validate(['email' => 'required|email']);

        $email = strtolower(trim((string) $request->email));

        // Throttle OTP issuance per e-mail and per IP to stop OTP flooding and
        // to cap how fast fresh codes can be minted for a brute-force sweep.
        $sendKey = 'pw-otp-send:' . sha1($email) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($sendKey, self::OTP_SEND_MAX)) {
            return $this->genericSent();
        }
        RateLimiter::hit($sendKey, self::OTP_WINDOW_SECONDS);

        // Only actually generate + send when the account exists; the caller
        // cannot tell the difference from the response.
        $user = User::where('email', $email)->first();
        if ($user) {
            $otp = (string) random_int(100000, 999999);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($otp), 'created_at' => now()],   // stored hashed (#18)
            );
            // Reset the per-code verify counter for the freshly issued OTP.
            RateLimiter::clear('pw-otp-verify:' . sha1($email));

            try {
                Mail::raw("Your password reset OTP is: {$otp}\n\nIt expires in " . self::OTP_TTL_MINUTES . " minutes.", function ($m) use ($email) {
                    $m->to($email)->subject('Password reset OTP');
                });
            } catch (\Throwable $e) { /* delivery failure shouldn't 500 the request */ }
        }

        return $this->genericSent();
    }

    private function genericSent(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email address, an OTP has been sent.',
        ], 200);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
        ]);

        $email = strtolower(trim((string) $request->email));

        $limit = $this->otpAttemptGuard($email, $request);
        if ($limit) {
            return $limit;
        }

        if (! $this->matchesOtp($email, (string) $request->otp)) {
            RateLimiter::hit('pw-otp-verify:' . sha1($email), self::OTP_WINDOW_SECONDS);

            return response()->json(['success' => false, 'error' => 'Invalid or expired OTP. Please try again.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully. You can now reset your password.',
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
        ]);

        $email = strtolower(trim((string) $request->email));

        $limit = $this->otpAttemptGuard($email, $request);
        if ($limit) {
            return $limit;
        }

        // Enforce match + 15-min expiry (previously missing here — #18).
        if (! $this->matchesOtp($email, (string) $request->otp)) {
            RateLimiter::hit('pw-otp-verify:' . sha1($email), self::OTP_WINDOW_SECONDS);

            return response()->json(['success' => false, 'error' => 'Invalid OTP or session expired.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Invalid OTP or session expired.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Invalidate every existing API token after a password reset so a
        // stolen/old token cannot outlive the reset (#35). Reset is
        // unauthenticated (OTP-based) so there is no current session to keep.
        try { $user->tokens()->delete(); } catch (\Throwable $e) { /* no tokens table on some installs */ }

        // Single-use: consume the OTP + reset the attempt counter.
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        RateLimiter::clear('pw-otp-verify:' . sha1($email));

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful. You can now login with your new password.',
        ], 200);
    }

    /**
     * Look up the stored OTP for this e-mail and confirm the supplied code
     * matches (constant-time hash check) AND is still within the TTL window.
     * Returns false for a missing, expired, or non-matching OTP.
     */
    private function matchesOtp(string $email, string $otp): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $row) {
            return false;
        }
        if (\Carbon\Carbon::parse($row->created_at)->addMinutes(self::OTP_TTL_MINUTES)->isPast()) {
            return false;
        }

        return Hash::check($otp, (string) $row->token);
    }

    /**
     * Per-account guard: after OTP_MAX_VERIFY_ATTEMPTS wrong guesses the OTP is
     * invalidated and further attempts are blocked until a new code is issued.
     * Returns a 429 JsonResponse when locked, or null to proceed.
     */
    private function otpAttemptGuard(string $email, Request $request): ?JsonResponse
    {
        $key = 'pw-otp-verify:' . sha1($email);
        if (RateLimiter::tooManyAttempts($key, self::OTP_MAX_VERIFY_ATTEMPTS)) {
            // Burn the OTP so it cannot be guessed further even after the window.
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'error' => 'Too many attempts. Please request a new OTP in ' . max(1, $seconds) . ' seconds.',
            ], 429);
        }

        return null;
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'error' => 'Your current password is incorrect.'], 400);
        }
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json(['success' => false, 'error' => 'New password cannot be the same as your current password.'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // Revoke all OTHER API tokens after a password change (keep the current
        // session so the caller stays logged in) so a leaked token elsewhere is
        // cut off (#35).
        try {
            $current = $user->currentAccessToken();
            $currentId = $current ? ($current->id ?? null) : null;
            $q = $user->tokens();
            if ($currentId) { $q->where('id', '!=', $currentId); }
            $q->delete();
        } catch (\Throwable $e) { /* no tokens table on some installs */ }

        return response()->json(['success' => true, 'message' => 'Password changed successfully.'], 200);
    }
}
