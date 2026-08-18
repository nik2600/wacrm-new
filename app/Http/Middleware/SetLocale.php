<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\LocaleSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set the request locale at the start of every web request.
 *
 * Resolution order:
 *   1. users.locale (per-user, set by the language dropdown)
 *   2. session app_locale (set when guests/operators pick from the dropdown)
 *   3. workspace.default_language (per-workspace, /account → Preferences)
 *   4. system_settings.default_language (platform-wide, /admin/languages)
 *   5. config app.locale → 'en'
 *
 * Falls back silently when the languages table doesn't exist yet
 * (fresh install pre-migration).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip (locale reads users/workspace + settings).
        if (! is_file(storage_path('installed'))) return $next($request);

        $fallback = LocaleSettings::defaultLocale();
        $locale = $fallback;

        try {
            $user = Auth::user();

            // 1. Per-user choice.
            if ($user && ! empty($user->locale) && LocaleSettings::isAvailable($user->locale)) {
                $locale = (string) $user->locale;
            }
            // 2. Session pick (guests + operators using header dropdown
            //    before save).
            elseif ($request->hasSession()) {
                $sessLocale = (string) $request->session()->get('app_locale', '');
                if ($sessLocale !== '' && LocaleSettings::isAvailable($sessLocale)) {
                    $locale = $sessLocale;
                }
                // 3. Workspace default.
                elseif ($user && method_exists($user, 'currentWorkspace')) {
                    $ws = $user->currentWorkspace;
                    $wsLocale = (string) ($ws->default_language ?? '');
                    if ($wsLocale !== '' && LocaleSettings::isAvailable($wsLocale)) {
                        $locale = $wsLocale;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Schema missing or any other startup-time failure — keep fallback.
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
