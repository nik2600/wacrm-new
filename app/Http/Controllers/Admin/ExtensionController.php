<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\SystemSetting;
use App\Services\ExtensionService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Admin → Settings → Extensions.
 *
 * Verify the WaDesk purchase code, upload an extension ZIP, merge it in.
 * Mirrors the Updater's shape (verify → upload → apply) because operators
 * already know that flow, and a second, different-looking installer would be
 * one more thing to learn for no benefit.
 */
class ExtensionController extends Controller
{
    public function __construct(private ExtensionService $extensions) {}

    public function index()
    {
        return view('admin.extensions.index', [
            'extensions'  => Extension::query()->orderBy('name')->get(),
            // In-place add-on modules — code that lives in addon/<slug>/ and runs
            // WITHOUT a ZIP upload / DB row (see App\Services\ModuleLoader). Surfaced
            // here so the operator sees they're active, even though there's nothing
            // to install/uninstall through this page.
            // allManifests() (not manifests()) so a DEACTIVATED in-place module
            // still appears here with a "Re-activate" action — manifests() only
            // returns the live ones, which drives nav/plan elsewhere.
            'modules'     => \App\Services\ModuleLoader::allManifests(),
            'coreVersion' => (string) config('version.version', '0.0.0'),
            // Instaflow runs as its OWN deployment — the operator connects to it
            // by URL + shared secret (no code upload). Surface the saved config +
            // last handshake result so the card shows connected / not-connected.
            'instaflowUrl'        => (string) SystemSetting::get('instaflow_url', ''),
            'instaflowConnected'  => (bool) SystemSetting::get('instaflow_connected', false),
            'instaflowLastCheck'  => (string) SystemSetting::get('instaflow_last_check', ''),
            'instaflowHasSecret'  => trim((string) SystemSetting::get('instaflow_secret', '')) !== '',
            // Display name shown on every "Sync from …", "Manage on …", "… URL"
            // label across the app — ig_brand_name() reads this. Default IgDesk.
            'instaflowBrand'      => (string) (SystemSetting::get('instaflow_brand', '') ?: 'IgDesk'),
        ]);
    }

    /**
     * Connect this WaDesk to a standalone Instaflow deployment: save the base
     * URL + shared secret, then run the handshake. Instaflow must expose
     *   GET  {url}/api/wadesk/handshake   (header: X-Instaflow-Secret: <secret>)
     *   → 200 {"ok":true,"service":"instaflow", ...}
     * so both sides prove they share the secret before any data flows. Secret is
     * stored encrypted (SystemSetting::ENCRYPTED_KEYS). No package is uploaded.
     */
    public function connectInstaflow(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instaflow_url'    => 'required|url|max:255',
            'instaflow_secret' => 'nullable|string|max:255',
            'instaflow_brand'  => 'nullable|string|max:40',
        ]);

        // ONE INSTAGRAM ENGINE AT A TIME (other direction). If the NATIVE
        // Instagram add-on is installed (addon/instagram/ present), refuse a
        // remote InstaMagic connection — the operator must remove the add-on
        // first. Prevents the two engines fighting over the same webhook + UI.
        // A DEACTIVATED native add-on (kept on disk but not loading) no longer
        // owns the Instagram engine, so it must not block a remote connection.
        if (is_dir(base_path('addon/instagram')) && ! \App\Services\ModuleLoader::isDisabled('instagram')) {
            return back()->withErrors([
                'instaflow' => __('Instagram runs one engine at a time. The native Instagram add-on is active — remove (deactivate) it first, then connect a remote InstaMagic.'),
            ]);
        }

        $url = rtrim(trim($data['instaflow_url']), '/');
        SystemSetting::set('instaflow_url', $url, 'string', 'Instaflow deployment base URL');

        // Display name of the connected IG product (drives ig_brand_name()).
        $brand = trim((string) ($data['instaflow_brand'] ?? ''));
        SystemSetting::set('instaflow_brand', $brand !== '' ? $brand : 'IgDesk', 'string', 'Connected Instagram product display name');

        // A blank secret field means "keep the existing secret" — so the operator
        // can re-test the connection without re-typing it. A non-blank value replaces it.
        $secret = trim((string) ($data['instaflow_secret'] ?? ''));
        if ($secret !== '') {
            SystemSetting::set('instaflow_secret', $secret, 'string', 'Instaflow handshake shared secret');
        } else {
            $secret = (string) SystemSetting::get('instaflow_secret', '');
        }

        // Handshake.
        // Display name comes from the admin's IG-brand setting (ig_brand_name),
        // so a re-branded install ("InstaMagic", etc.) never shows the literal
        // "Instaflow" in any user-facing message. The handshake header + service
        // key stay literal (functional identifiers, never re-branded).
        $brand     = ig_brand_name();
        $connected = false;
        $reason    = '';
        try {
            $resp = Http::withHeaders(['X-Instaflow-Secret' => $secret])
                ->timeout(12)
                ->acceptJson()
                ->get($url . '/api/wadesk/handshake');
            if ($resp->status() === 401 || $resp->status() === 403) {
                $reason = 'Secret rejected by ' . $brand . ' (HTTP ' . $resp->status() . ').';
            } elseif (! $resp->successful()) {
                $reason = $brand . ' returned HTTP ' . $resp->status() . '.';
            } elseif (($resp->json('ok') === true) || ($resp->json('service') === 'instaflow')) {
                $connected = true;
            } else {
                $reason = 'Reached the URL but it did not respond as an ' . $brand . ' deployment.';
            }
        } catch (\Throwable $e) {
            $reason = 'Could not reach ' . $brand . ' at that URL (' . $e->getMessage() . ').';
        }

        SystemSetting::set('instaflow_connected', $connected ? '1' : '0', 'bool', 'Instaflow handshake result');
        SystemSetting::set('instaflow_last_check', now()->toDateTimeString(), 'string', 'Instaflow last handshake time');

        // Audit::log() takes (string $action, array $opts) — everything else
        // (subject, result, meta) goes INSIDE $opts. Passing them as extra
        // positional args throws a TypeError before the redirect ever runs.
        // `admin.` prefix puts the row on the platform layer, like other
        // operator actions.
        Audit::log('admin.instaflow.connect', [
            'subject_type' => 'instaflow',
            'result'       => $connected ? 'success' : 'failure',
            'meta'         => [
                'url'    => $url,
                'reason' => $connected ? null : $reason,
            ],
        ]);

        return $connected
            ? back()->with('status', $brand . ' connected.')
            : back()->with('error', $brand . ' not connected — ' . ($reason ?: 'handshake failed.'));
    }

    /**
     * Disconnect the standalone Instaflow/InstaMagic deployment — wipes the
     * stored URL + shared secret and flips the connection flag off, so the
     * app stops treating Instagram as connected (the card shows "Connect"
     * again). The display-name brand is left as-is (harmless).
     */
    public function disconnectInstaflow(Request $request): RedirectResponse
    {
        $brand = (string) (SystemSetting::get('instaflow_brand', '') ?: 'IgDesk');

        SystemSetting::set('instaflow_url', '', 'string', 'Instaflow deployment base URL');
        SystemSetting::set('instaflow_secret', '', 'string', 'Instaflow handshake shared secret');
        SystemSetting::set('instaflow_connected', '0', 'bool', 'Instaflow handshake result');
        SystemSetting::set('instaflow_last_check', now()->toDateTimeString(), 'string', 'Instaflow last handshake time');

        Audit::log('admin.instaflow.disconnect', [
            'subject_type' => 'instaflow',
            'result'       => 'success',
        ]);

        return back()->with('status', $brand . ' disconnected.');
    }

    /** Step 1 — licence check. Same code that unlocks the Updater. */
    public function verify(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('purchase_code', ''));
        $res  = $this->extensions->verifyPurchase($code);

        Audit::log('admin.extension.verify', [
            'subject_type' => 'extension',
            'result'       => $res['ok'] ? 'success' : 'failure',
            // Never log the licence key itself — only whether it worked.
            'meta'         => ['outcome' => $res['ok'] ? 'valid' : 'invalid'],
        ]);

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    /**
     * Step 2 — upload + install, in one call.
     *
     * Inspect BEFORE writing anything: a malformed or too-new package is
     * rejected while the install is still untouched. A half-merged extension is
     * much harder to recover from than a refused upload.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'extension'         => 'required|file|mimes:zip|max:102400',   // 100 MB
            'purchase_code' => 'nullable|string|max:191',
        ]);

        $code = trim((string) $request->input('purchase_code', ''));

        // Re-verify server-side. The front-end already checked, but that check
        // is advisory — without this an admin could POST straight to /upload
        // and skip the licence entirely.
        $lic = $this->extensions->verifyPurchase($code);
        if (!($lic['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $lic['message'] ?? 'Verify your purchase code before installing an extension.',
            ], 422);
        }

        try {
            $zipPath = $this->extensions->saveUploadedZip($request->file('extension'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not save the upload: ' . $e->getMessage()], 500);
        }

        $info = $this->extensions->inspect($zipPath);
        if (!$info['ok']) {
            return response()->json($info, 422);
        }

        // ONE INSTAGRAM ENGINE AT A TIME. If a REMOTE InstaMagic/Instaflow is
        // connected (instaflow_url points at an EXTERNAL host + handshake OK),
        // refuse the native Instagram add-on — the operator must disconnect
        // InstaMagic first. A native self-wire (URL = WaDesk itself) does NOT
        // block, because that self-wire IS the add-on being (re)installed.
        if (($info['manifest']['slug'] ?? '') === 'instagram') {
            $igUrl    = (string) SystemSetting::get('instaflow_url', '');
            $igHost   = (string) parse_url($igUrl, PHP_URL_HOST);
            $selfHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
            $remoteConnected = (bool) SystemSetting::get('instaflow_connected', false)
                && $igHost !== ''
                && $igHost !== $selfHost
                && ! in_array($igHost, ['127.0.0.1', 'localhost'], true);
            if ($remoteConnected) {
                $brand = (string) (SystemSetting::get('instaflow_brand', '') ?: 'InstaMagic');
                return response()->json([
                    'ok' => false,
                    'message' => "Instagram runs one engine at a time. Disconnect {$brand} first, then import the Instagram add-on.",
                ], 422);
            }
        }

        try {
            $res = $this->extensions->install(
                $zipPath,
                $info['manifest'],
                $info['root'],
                $code ?: null,
                (int) $request->user()->id,
            );
        } catch (\Throwable $e) {
            \Log::error('[EXTENSION] install threw', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Install failed: ' . $e->getMessage()], 500);
        }

        if (!($res['ok'] ?? false)) {
            return response()->json($res, 422);
        }

        Audit::log('admin.extension.installed', [
            'subject_type' => 'extension',
            'meta'         => [
                'slug'    => $info['manifest']['slug'],
                'version' => $info['manifest']['version'],
                'files'   => $res['files'] ?? 0,
            ],
        ]);

        $m = $info['manifest'];
        $skipped = (int) ($res['skipped'] ?? 0);

        // Say plainly if the tables did not get created. Reporting a bare
        // "installed" while migrations failed would send the operator off to
        // debug 500s on a feature that never had its schema.
        $migrationNote = '';
        if (!empty($res['migration_error'])) {
            $migrationNote = ' WARNING: database migration failed — ' . $res['migration_error'];
        } elseif (!empty($res['migrated'])) {
            $migrationNote = ' Database updated.';
        }

        return response()->json([
            'ok' => true,
            'message' => "{$m['name']} {$m['version']} installed — {$res['files']} file(s) merged."
                . ($skipped > 0 ? " {$skipped} protected path(s) skipped." : '')
                . $migrationNote,
            'extension' => $m,
        ]);
    }

    /** Switch an extension off without deleting its files. */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $extension = Extension::findOrFail($id);
        $extension->status = $extension->isActive() ? Extension::STATUS_DISABLED : Extension::STATUS_ACTIVE;
        $extension->save();
        Extension::forget($extension->slug);

        Audit::log('admin.extension.' . ($extension->isActive() ? 'enabled' : 'disabled'), [
            'subject_type' => 'extension',
            'subject_id'   => $extension->id,
            'meta'         => ['slug' => $extension->slug],
        ]);

        return response()->json(['ok' => true, 'status' => $extension->status]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $extension = Extension::findOrFail($id);
        $slug  = $extension->slug;

        try {
            $res = $this->extensions->uninstall($extension);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Uninstall failed: ' . $e->getMessage()], 500);
        }

        Audit::log('admin.extension.uninstalled', [
            'subject_type' => 'extension',
            'meta'         => ['slug' => $slug, 'removed' => $res['removed'] ?? 0],
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Removed {$slug} — " . ($res['removed'] ?? 0) . ' file(s) deleted.',
        ]);
    }

    /**
     * Deactivate / re-activate an IN-PLACE module (code in addon/<slug>/) WITHOUT
     * touching its files. "Remove" here writes a hidden `.disabled` marker so the
     * ModuleLoader stops loading the module — its routes, migrations, views and
     * classes all drop out — but every file stays on disk, so re-enabling it is
     * instant and nothing needs re-uploading. This is the safe, reversible
     * counterpart to deleting the folder.
     */
    public function moduleToggle(Request $request, string $slug): JsonResponse
    {
        // Only a real module folder may be toggled — never an arbitrary path.
        if (! preg_match('/^[a-z0-9_-]+$/i', $slug) || \App\Services\ModuleLoader::dirForSlug($slug) === null) {
            return response()->json(['ok' => false, 'message' => 'Unknown module.'], 404);
        }

        $wasDisabled = \App\Services\ModuleLoader::isDisabled($slug);
        $ok = $wasDisabled
            ? \App\Services\ModuleLoader::enable($slug)
            : \App\Services\ModuleLoader::disable($slug);

        if (! $ok) {
            return response()->json(['ok' => false, 'message' => 'Could not update the module. Check the addon/ folder is writable.'], 500);
        }

        // The module's routes/views were compiled into the caches — clear them so
        // the change takes effect on the next request, and reset opcache so the
        // route file (no longer loaded) is forgotten.
        try {
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            if (function_exists('opcache_reset')) { @opcache_reset(); }
        } catch (\Throwable $e) { /* best-effort */ }

        $nowDisabled = ! $wasDisabled;
        Audit::log('admin.module.' . ($nowDisabled ? 'disabled' : 'enabled'), [
            'subject_type' => 'module',
            'meta'         => ['slug' => $slug],
        ]);

        return response()->json([
            'ok'       => true,
            'disabled' => $nowDisabled,
            'message'  => $nowDisabled
                ? "{$slug} deactivated — its files are kept, so you can re-activate it any time."
                : "{$slug} re-activated.",
        ]);
    }
}
