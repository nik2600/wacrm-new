<?php

namespace App\Http\Controllers;

use App\Support\LocaleSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Language switcher endpoint. Both user-header + admin-header dropdowns
 * POST here with `code=es`. Persists to:
 *   - session `app_locale` (always)
 *   - users.locale (when authenticated) so the choice survives logout
 *
 * Validates `code` against the active-languages registry — unknown codes
 * are rejected. JSON 200 on success so JS can refresh in place; HTML
 * fallback redirects back.
 */
class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $code = trim((string) $request->input('code', ''));

        if ($code === '' || ! LocaleSettings::isAvailable($code)) {
            $msg = 'Language not available.';
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->with('error', $msg);
        }

        $request->session()->put('app_locale', $code);

        $user = Auth::user();
        if ($user) {
            try {
                $user->forceFill(['locale' => $code])->save();
            } catch (\Throwable $e) {
                // Column may be missing on stale schemas — session save still applies.
            }
        }

        app()->setLocale($code);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'        => true,
                'locale'    => $code,
                'direction' => LocaleSettings::directionFor($code),
            ]);
        }
        return back()->with('success', 'Language updated.');
    }
}
