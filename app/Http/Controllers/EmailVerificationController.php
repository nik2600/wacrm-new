<?php

namespace App\Http\Controllers;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    /** Hit by the link in the email. Marks email_verified_at if the signed URL is valid. */
    public function verify(Request $request, int $userId, string $hash): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect('/login')->withErrors(['email' => 'Verification link expired or invalid. Please request a new one.']);
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect('/login')->withErrors(['email' => 'User not found.']);
        }
        if (!hash_equals(sha1($user->email), $hash)) {
            return redirect('/login')->withErrors(['email' => 'Verification link no longer matches this account (email may have changed).']);
        }

        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        if (!Auth::check()) {
            Auth::login($user);
        }
        return redirect('/dashboard')->with('success', 'Email verified — welcome!');
    }

    /** Authenticated user clicks "Resend verification email". */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) return redirect('/login');

        if ($user->email_verified_at) {
            return back()->with('success', 'Your email is already verified.');
        }

        $reason = self::send($user);
        return $reason === null
            ? back()->with('success', 'Verification email sent to ' . $user->email)
            : back()->withErrors(['email' => 'Could not send verification email — ' . $reason]);
    }

    /**
     * Send a verification email to a user. Returns NULL on success, a short
     * reason string on failure. Static + public so admin can reuse it.
     */
    public static function send(User $user): ?string
    {
        // Reuse the mail-config check from UsersController by hand here.
        $mailer = config('mail.default');
        $from   = config('mail.from.address');
        if (!$mailer) return 'no mailer configured.';
        if (empty($from) || $from === 'hello@example.com') return 'sender address not set.';
        if ($mailer === 'smtp') {
            $cfg = config('mail.mailers.smtp', []);
            if (empty($cfg['host']) || empty($cfg['port'])) return 'SMTP host/port not set.';
        }

        try {
            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $user->id, 'hash' => sha1($user->email)],
            );
            Mail::to($user->email)->send(new VerifyEmailMail(user: $user, verifyUrl: $url));
            return null;
        } catch (\Throwable $e) {
            Log::warning('Verification email failed for user ' . $user->id . ': ' . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'authentication') !== false) return 'SMTP authentication failed.';
            if (stripos($msg, 'connection')     !== false) return 'SMTP connection refused.';
            if (stripos($msg, 'timed out')      !== false) return 'SMTP server timed out.';
            return 'mail transport error (see logs).';
        }
    }
}
