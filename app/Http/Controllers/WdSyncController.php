<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Developer file-sync receiver — a private "push code to the test server"
 * endpoint. My local machine POSTs a batch of changed files here; this writes
 * them to disk, runs pending migrations and clears the caches, so a code change
 * lands live without a manual cPanel upload.
 *
 * THIS IS A REMOTE FILE-WRITE ENDPOINT. It is deliberately dangerous, so it is:
 *   - OFF unless WD_SYNC_KEY is a non-empty string in .env (no key ⇒ 404, invisible).
 *   - authenticated by that key via hash_equals (constant-time) in the
 *     X-Sync-Key header — never a query string, so it stays out of access logs.
 *   - jailed to base_path(): every target path is normalised and must resolve
 *     INSIDE the app root, and a blocklist protects the credential/secret files.
 *   - logged on every call (ip + file count + result).
 *
 * If the key leaks, an attacker can overwrite the site — so treat it as a
 * secret, rotate it by changing the .env value, and remove the line to kill
 * the endpoint entirely.
 */
class WdSyncController extends Controller
{
    /** Paths that may NEVER be written, however the request spells them. */
    private const BLOCKED = [
        '.env',
        '.env.',            // .env.production, .env.backup, …
        '.git/',
        'storage/framework/down',
    ];

    public function push(Request $request): JsonResponse
    {
        $key = (string) config('wdsync.key', env('WD_SYNC_KEY', ''));

        // No key configured ⇒ the feature does not exist. 404, not 403, so a
        // scanner can't even tell the route is there.
        if ($key === '') {
            abort(404);
        }

        $given = (string) $request->header('X-Sync-Key', '');
        if (! hash_equals($key, $given)) {
            Log::warning('[WD-SYNC] rejected — bad key', ['ip' => $request->ip()]);
            abort(404);
        }

        $files = $request->input('files', []);
        if (! is_array($files) || $files === []) {
            return response()->json(['ok' => false, 'error' => 'no files in payload'], 422);
        }

        $written = [];
        $skipped = [];
        $base    = rtrim(str_replace('\\', '/', base_path()), '/');

        // The root .env is protected by default so a normal code push can never
        // clobber it. Allow it ONLY when the caller explicitly opts in with
        // allow_env=true — that's how we sync DB creds / keys to the server on
        // purpose. (.env.production / .env.backup and .git/ stay blocked always.)
        $allowEnv = $request->boolean('allow_env', false);

        foreach ($files as $f) {
            $rel = isset($f['path']) ? (string) $f['path'] : '';
            $b64 = isset($f['contents_b64']) ? (string) $f['contents_b64'] : '';

            $reason = $this->rejectReason($rel, $allowEnv);
            if ($reason !== null) {
                $skipped[] = ['path' => $rel, 'reason' => $reason];
                continue;
            }

            // Normalise and confirm the final absolute path stays inside the app
            // root. We can't realpath() a not-yet-existing file, so we normalise
            // the string ourselves and prefix-check against base_path().
            $abs = $this->safeAbsolute($base, $rel);
            if ($abs === null) {
                $skipped[] = ['path' => $rel, 'reason' => 'escapes app root'];
                continue;
            }

            $data = base64_decode($b64, true);
            if ($data === false) {
                $skipped[] = ['path' => $rel, 'reason' => 'bad base64'];
                continue;
            }

            $dir = dirname($abs);
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                $skipped[] = ['path' => $rel, 'reason' => 'mkdir failed'];
                continue;
            }

            // Write to a temp file in the SAME directory, then rename — an
            // atomic swap, so a half-written PHP file is never executed.
            $tmp = $abs . '.wdsync.' . substr(md5($rel . strlen($data)), 0, 8) . '.tmp';
            if (@file_put_contents($tmp, $data) === false || ! @rename($tmp, $abs)) {
                @unlink($tmp);
                $skipped[] = ['path' => $rel, 'reason' => 'write failed'];
                continue;
            }

            $written[] = $rel;
        }

        // Run migrations + clear caches only when files actually landed and the
        // caller asked for it (defaults on). Captured, never fatal.
        $migrateOut = null;
        $cacheOut   = null;

        if ($written !== [] && $request->boolean('migrate', true)) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                $migrateOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                $migrateOut = 'ERROR: ' . $e->getMessage();
            }
        }

        if ($written !== [] && $request->boolean('clear', true)) {
            try {
                // Clears compiled views, config, routes, events + app cache in
                // one shot — so a changed blade/route/config is picked up.
                Artisan::call('optimize:clear');
                $cacheOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                $cacheOut = 'ERROR: ' . $e->getMessage();
            }
        }

        // Optional: run a named database seeder. Same key gate as the rest of the
        // endpoint. The class name is constrained to the Database\Seeders
        // namespace + word chars only (no path, no args), so it can only invoke a
        // seeder that already exists on disk — never arbitrary code. Runs
        // independently of $written so a seeder can be triggered on its own
        // (push the seeder file first, then a second call with just `seed`).
        $seedOut = null;
        $seedClass = trim((string) $request->input('seed', ''));
        if ($seedClass !== '' && preg_match('/^(Database\\\\Seeders\\\\)?[A-Za-z0-9_]+$/', $seedClass)) {
            $fq = str_starts_with($seedClass, 'Database\\Seeders\\')
                ? $seedClass
                : 'Database\\Seeders\\' . $seedClass;
            // Optional env passthrough for the seeder (e.g. FLOW_SEED_EMAIL).
            // Keys are constrained to UPPER_SNAKE so only intended config knobs
            // can be set — never arbitrary server env.
            $seedEnv = $request->input('seed_env', []);
            if (is_array($seedEnv)) {
                foreach ($seedEnv as $k => $v) {
                    if (is_string($k) && preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) {
                        $val = is_scalar($v) ? (string) $v : '';
                        putenv("$k=$val");
                        $_ENV[$k] = $val;
                        $_SERVER[$k] = $val;
                    }
                }
            }
            try {
                // Explicitly load the file so a brand-new seeder class pushed in a
                // prior request resolves even if the autoloader/opcache hasn't
                // caught up yet.
                $seederFile = base_path('database/seeders/' . class_basename($fq) . '.php');
                if (is_file($seederFile)) require_once $seederFile;
                Artisan::call('db:seed', ['--class' => $fq, '--force' => true]);
                $seedOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                $seedOut = 'ERROR: ' . $e->getMessage();
            }
        }

        Log::info('[WD-SYNC] push applied', [
            'ip'      => $request->ip(),
            'written' => count($written),
            'skipped' => count($skipped),
        ]);

        return response()->json([
            'ok'      => true,
            'written' => $written,
            'skipped' => $skipped,
            'migrate' => $migrateOut,
            'cache'   => $cacheOut,
            'seed'    => $seedOut,
            'count'   => count($written),
        ]);
    }

    /** Null = allowed; otherwise a human reason the path is refused. */
    private function rejectReason(string $rel, bool $allowEnv = false): ?string
    {
        if ($rel === '')                       return 'empty path';
        if (str_contains($rel, "\0"))          return 'null byte';
        // Reject absolute paths (unix / and windows C:\) and any parent-dir hop.
        if (str_starts_with($rel, '/'))        return 'absolute path';
        if (preg_match('#^[A-Za-z]:#', $rel))  return 'absolute path';
        if (str_contains($rel, '..'))          return 'parent traversal';

        $norm = ltrim(str_replace('\\', '/', $rel), '/');

        // Explicit opt-in: allow the ROOT .env only (never .env.production /
        // .env.backup / anything under a subdir). Lets us sync DB creds + keys
        // to the server on purpose, without opening the door to every dotfile.
        if ($allowEnv && $norm === '.env') {
            return null;
        }

        foreach (self::BLOCKED as $b) {
            if ($norm === rtrim($b, '/') || str_starts_with($norm, $b)) {
                return 'protected path';
            }
        }
        return null;
    }

    /** Resolve $rel under $base, or null if it would escape the app root. */
    private function safeAbsolute(string $base, string $rel): ?string
    {
        $norm = ltrim(str_replace('\\', '/', $rel), '/');
        $abs  = $base . '/' . $norm;

        // Collapse any './' and confirm no surviving '../' — belt and braces on
        // top of rejectReason(), since a write here is unforgiving.
        $parts = [];
        foreach (explode('/', $abs) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') return null;
            $parts[] = $seg;
        }
        $rebuilt = '/' . implode('/', $parts);

        // On Windows base_path() has a drive letter; normalise the leading slash
        // away for the prefix comparison.
        $baseCmp = ltrim($base, '/');
        $absCmp  = ltrim($rebuilt, '/');
        if (! str_starts_with($absCmp . '/', $baseCmp . '/')) {
            return null;
        }
        return $rebuilt;
    }
}
