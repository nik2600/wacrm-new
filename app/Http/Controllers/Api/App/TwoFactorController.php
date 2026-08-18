<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtp;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Mobile-app e-mail-OTP verification + passcode setup. "Verified" maps to our
 * email_verified_at column (a verified e-mail = is_verified:1). Passcode is a
 * hashed quick-unlock PIN stored on users.passcode.
 */
class TwoFactorController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        try {
            $user = User::where('email', $request->email)->first();

            if ($user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already verified. OTP not required.',
                ], 400);
            }

            $otp = (string) random_int(100000, 999999);
            UserOtp::updateOrCreate(
                ['user_id' => $user->id],
                ['otp' => $otp, 'expires_at' => Carbon::now()->addMinutes(10)],
            );

            Mail::raw("Your OTP is: {$otp}", function ($m) use ($user) {
                $m->to($user->email)->subject('Your verification OTP');
            });

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully.',
                'expires_in' => '10 minutes',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to send OTP'], 500);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        try {
            $user = User::where('email', $request->email)->first();
            $userOtp = UserOtp::where('user_id', $user->id)->first();

            if (! $userOtp || $userOtp->otp !== (string) $request->otp) {
                return response()->json(['error' => 'Invalid OTP'], 400);
            }
            if ($userOtp->isExpired()) {
                return response()->json(['error' => 'OTP expired'], 400);
            }

            $user->email_verified_at = now();
            $user->save();
            $userOtp->delete();

            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => '2FA verified successfully',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_verified' => 1,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to verify OTP'], 500);
        }
    }

    public function setPasscode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'passcode' => 'required|digits:6',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (! $user->email_verified_at) {
                return response()->json(['error' => 'User not verified'], 400);
            }

            $user->passcode = Hash::make($request->passcode);
            $user->save();

            return response()->json(['success' => true, 'message' => 'Passcode set successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to set passcode'], 500);
        }
    }
}
