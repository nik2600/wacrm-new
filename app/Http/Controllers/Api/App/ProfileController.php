<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Api\App\Concerns\FormatsUser;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile-app profile: current user + plan summary (GET /user), profile update
 * (POST /user-profile), and the country dropdown (GET /countries). Shapes
 * match the app: {success, data:{user, order}} / {success, message, data}.
 */
class ProfileController extends Controller
{
    use FormatsUser;

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $userData = $this->userPayload($user);
        // Our profile columns store names directly (free text), so expose them
        // under the *_name keys the app reads.
        $userData['country_name'] = $user->country ?: null;
        $userData['state_name'] = $user->state ?: null;
        $userData['city_name'] = $user->city ?: null;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $userData,
                'order' => $this->planSummary($user),
            ],
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'mobile' => 'nullable',
            'address' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            // Validated by MIME in the handler (the field may be an uploaded
            // file OR a base64 data-URI), so keep the rule permissive here —
            // the old mimes:jpeg,png,jpg,gif rejected webp/heic + extensionless
            // Flutter uploads outright.
            'image' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            // Accept any real image the phone sends: an uploaded file of any
            // image MIME (jpeg/png/gif/webp/heic/…), even with no extension, or
            // a base64 data-URI string. Reject only genuine non-images.
            $newAvatar = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
                    return response()->json(['error' => ['image' => ['The image must be a valid image file.']]], 422);
                }
                $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $file->move(public_path('images/users'), $filename);
                $newAvatar = 'images/users/' . $filename;
            } elseif (is_string($request->input('image')) && str_contains((string) $request->input('image'), 'base64,')) {
                if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', (string) $request->input('image'), $m)) {
                    $ext  = strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
                    $data = base64_decode($m[2], true);
                    if ($data !== false) {
                        $dir = public_path('images/users');
                        if (! File::exists($dir)) {
                            File::makeDirectory($dir, 0755, true);
                        }
                        $filename = time() . '_' . uniqid() . '.' . $ext;
                        File::put($dir . DIRECTORY_SEPARATOR . $filename, $data);
                        $newAvatar = 'images/users/' . $filename;
                    }
                }
            }

            if ($newAvatar !== null) {
                if ($user->avatar_path && File::exists(public_path($user->avatar_path))) {
                    File::delete(public_path($user->avatar_path));
                }
                $user->avatar_path = $newAvatar;
            }

            $user->fill($request->only(['name', 'email', 'mobile', 'address', 'timezone', 'country', 'state', 'city']));
            $user->save();

            $userData = $this->userPayload($user);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $userData,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }

    public function countries(): JsonResponse
    {
        $list = [];
        if (class_exists(\Symfony\Component\Intl\Countries::class)) {
            foreach (\Symfony\Component\Intl\Countries::getNames(app()->getLocale()) as $code => $name) {
                $list[] = ['id' => $code, 'name' => $name];
            }
        } else {
            foreach (self::FALLBACK_COUNTRIES as $code => $name) {
                $list[] = ['id' => $code, 'name' => $name];
            }
        }

        return response()->json(['success' => true, 'data' => $list], 200);
    }

    /**
     * Best-effort current-plan summary in the app's `order` shape. Built from
     * the user's current workspace plan + latest paid order; returns null on
     * any gap so the profile screen still renders.
     */
    private function planSummary($user): ?array
    {
        try {
            $workspace = $user->current_workspace_id ? Workspace::find($user->current_workspace_id) : null;
            if (! $workspace) {
                return null;
            }
            $package = method_exists($workspace, 'package') ? $workspace->package() : null;
            if (! $package) {
                return null;
            }

            $end = $workspace->trial_ends_at ?? ($workspace->plan_ends_at ?? null);

            $limit = 0;
            try { $limit = (int) $workspace->effectiveLimit('monthly_messages_limit'); } catch (\Throwable $e) {}
            $remainingPercent = $limit <= 0 ? 100 : 100; // refined when usage metering lands

            $order = Order::where('user_id', $user->id)->latest()->first();

            return [
                'plan_name' => $package->pname ?? null,
                'end_date' => $end ? $end->format('d M Y') : null,
                'progress_remaining' => $remainingPercent . '%',
                'amount' => $order->amount ?? null,
                'currency' => $order->currency ?? null,
                'symbol' => $order->currency_symbol ?? ($order->symbol ?? null),
                'id' => $order->id ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Minimal fallback if symfony/intl isn't present. */
    private const FALLBACK_COUNTRIES = [
        'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom', 'AE' => 'United Arab Emirates',
        'CA' => 'Canada', 'AU' => 'Australia', 'SG' => 'Singapore', 'DE' => 'Germany', 'FR' => 'France',
        'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'BR' => 'Brazil', 'MX' => 'Mexico',
        'ZA' => 'South Africa', 'NG' => 'Nigeria', 'KE' => 'Kenya', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
        'ID' => 'Indonesia', 'MY' => 'Malaysia', 'PH' => 'Philippines', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar',
        'JP' => 'Japan', 'CN' => 'China', 'RU' => 'Russia', 'TR' => 'Turkey', 'EG' => 'Egypt', 'NP' => 'Nepal',
    ];
}
