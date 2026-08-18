<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate every request behind the install wizard until installation
 * completes. Detection is the presence of `storage/installed`.
 *
 * Behaviour:
 *   - Not installed + non-install URL → redirect to /install
 *   - Installed     + install URL    → redirect to /
 *   - Otherwise pass through
 *
 * While not installed, the same fixed file-session config that the
 * install routes use is applied here too so guests landing on /
 * before installation get a consistent session cookie name across
 * the redirect.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed     = file_exists(storage_path('installed'));
        $path          = trim($request->getPathInfo(), '/');
        $isInstallRoute = $path === 'install' || str_starts_with($path, 'install/');

        if (! $installed) {
            // Bootstrap a usable runtime on a fresh checkout that
            // shipped with only .env.example. The wizard's controller
            // rewrites .env later — these guarantees just let Laravel
            // boot far enough to render the wizard.
            $this->bootstrapEnvIfMissing();

            // Force file session with the same fixed cookie name the
            // install routes use — APP_NAME may change mid-installation
            // (writeEnvFile) and a name-derived cookie would otherwise
            // invalidate the step-state session.
            config([
                'session.driver'  => 'file',
                'session.encrypt' => false,
                'session.cookie'  => 'wadesk_install_session',
            ]);
        }

        if (! $installed && ! $isInstallRoute) {
            return redirect('/install');
        }

        if ($installed && $isInstallRoute) {
            return redirect('/');
        }

        return $next($request);
    }

    /**
     * Make sure .env exists and has a usable APP_KEY before the wizard
     * renders. Otherwise Laravel can't sign CSRF tokens consistently
     * and every form POST would 419.
     *
     * Strategy:
     *   1. If .env is missing but .env.example exists → copy it.
     *   2. If APP_KEY is missing/empty in .env → mint one and write back.
     *
     * Both writes are skipped silently when the file system isn't
     * writable — the wizard's Requirements step will surface that to
     * the operator.
     */
    private function bootstrapEnvIfMissing(): void
    {
        $envPath     = base_path('.env');
        $envExample  = base_path('.env.example');

        try {
            if (! file_exists($envPath) && file_exists($envExample) && is_writable(base_path())) {
                @copy($envExample, $envPath);
            }
            if (file_exists($envPath) && is_writable($envPath)) {
                $content = (string) @file_get_contents($envPath);
                if (! preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', $content)) {
                    $key = 'base64:' . base64_encode(random_bytes(32));
                    if (preg_match('/^APP_KEY=.*/m', $content)) {
                        $content = preg_replace('/^APP_KEY=.*/m', "APP_KEY={$key}", $content);
                    } else {
                        $content .= "\nAPP_KEY={$key}\n";
                    }
                    @file_put_contents($envPath, $content);
                    // Apply at runtime too so the current request can
                    // already use the new key for CSRF + encryption.
                    config(['app.key' => $key]);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal — wizard's Requirements check will flag the
            // missing-writable .env condition for the operator.
        }
    }
}
