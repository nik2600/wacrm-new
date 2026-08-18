<?php

namespace App\Services;

use App\Models\Extension;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * What the installed extensions contribute to core.
 *
 * Core asks this class three questions — "what nav do you add", "what plan
 * features do you add", "what plan limits do you add" — and never has to know
 * that the answer happens to be Instagram. Add a second extension tomorrow and
 * the sidebar and package form pick it up with no core change.
 *
 * Every answer is derived from the manifest of extensions whose status is
 * ACTIVE, so disabling an extension hides its nav and its plan fields without
 * deleting a single file.
 *
 * Cached for the same reason Extension::enabled() is: the sidebar renders on
 * every admin request, and this must never become a per-request query.
 */
class ExtensionRegistry
{
    private const TTL = 300;

    /** Manifests of every ACTIVE extension, keyed by slug. */
    /**
     * All active contributions: ZIP-imported extensions (DB rows) PLUS in-place
     * add-on modules living in `addon/<slug>/` (see App\Services\ModuleLoader).
     * A disk module needs no import step — its extension.json is the manifest —
     * so nav + plan features/limits surface the moment the folder is present.
     */
    public static function manifests(): array
    {
        return array_merge(self::dbManifests(), \App\Services\ModuleLoader::manifests());
    }

    private static function dbManifests(): array
    {
        // The try/catch MUST wrap Cache::remember itself, not just the closure.
        // With the database cache store (CACHE_STORE=database), the cache read
        // hits the `cache` table BEFORE the closure runs — on a FRESH INSTALL
        // (no DB configured yet) that throws SQLSTATE[HY000] 1045 and 500s the
        // installer, since this class is consulted on every request (route +
        // nav registration). Any DB/cache failure → "no contributions" so core
        // still boots and the install wizard can run.
        try {
            return Cache::remember('extensions.manifests', self::TTL, function () {
                if (!Schema::hasTable('extensions')) {
                    return [];
                }
                // The manifest column arrived after the table did — an install
                // mid-upgrade must degrade to "no contributions", not a 500.
                if (!Schema::hasColumn('extensions', 'manifest')) {
                    return [];
                }

                return Extension::query()
                    ->where('status', Extension::STATUS_ACTIVE)
                    ->get(['slug', 'name', 'manifest'])
                    ->mapWithKeys(fn ($e) => [$e->slug => (array) ($e->manifest ?? [])])
                    ->all();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Absolute paths of the route files enabled extensions declare.
     *
     * Only files that actually exist are returned — an extension whose row
     * survived but whose files were removed by hand must not fatal the whole
     * app at route-registration time.
     *
     * @return array<int, string>
     */
    public static function routeFiles(): array
    {
        $out = [];

        foreach (self::manifests() as $manifest) {
            foreach ((array) ($manifest['routes'] ?? []) as $rel) {
                $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');

                // Must resolve inside the install. A manifest is operator-
                // supplied data, and `require` on an escaped path would be
                // arbitrary file inclusion.
                if ($rel === '' || str_contains($rel, '..')) {
                    continue;
                }

                $path = base_path($rel);
                if (is_file($path)) {
                    $out[] = $path;
                }
            }
        }

        return $out;
    }

    /**
     * Nav entries for a given surface ('admin' or 'user'), already shaped like
     * the arrays those sidebars iterate.
     *
     * @return array<int, array{key:string,href:string,label:string,icon:string,sw:float,extension:string}>
     */
    public static function nav(string $surface = 'admin'): array
    {
        $out = [];

        foreach (self::manifests() as $slug => $manifest) {
            foreach ((array) ($manifest['nav'][$surface] ?? []) as $item) {
                if (empty($item['key']) || empty($item['href'])) {
                    continue;
                }
                $out[] = [
                    'key'   => (string) $item['key'],
                    // Manifest hrefs are app-relative ('/admin/instagram'); an
                    // absolute URL in a manifest would let a package point the
                    // operator's own nav off-site.
                    'href'  => url('/' . ltrim((string) $item['href'], '/')),
                    'label' => __((string) ($item['label'] ?? $item['key'])),
                    'icon'  => (string) ($item['icon'] ?? ''),
                    'sw'    => (float) ($item['sw'] ?? 1.5),
                    'after' => (string) ($item['after'] ?? ''),
                    'extension' => $slug,
                ];
            }
        }

        return $out;
    }

    /**
     * Plan feature flags contributed by extensions — the checkbox list on the
     * package form, and the set EnforcePlanFeature will accept.
     *
     * @return array<string, array{label:string,group:string,extension:string}>
     */
    public static function planFeatures(): array
    {
        $out = [];

        foreach (self::manifests() as $slug => $manifest) {
            foreach ((array) ($manifest['plan_features'] ?? []) as $key => $meta) {
                // Tolerate both ["access_x", ...] and {"access_x": {...}}.
                if (is_int($key)) {
                    $key  = (string) $meta;
                    $meta = [];
                }
                $key = (string) $key;
                if ($key === '') {
                    continue;
                }
                $out[$key] = [
                    'label'     => __((string) ($meta['label'] ?? $key)),
                    'group'     => (string) ($meta['group'] ?? ($manifest['name'] ?? $slug)),
                    'extension' => $slug,
                ];
            }
        }

        return $out;
    }

    /**
     * Numeric plan limits contributed by extensions.
     *
     * @return array<string, array{label:string,group:string,default:?int,extension:string}>
     */
    public static function planLimits(): array
    {
        $out = [];

        foreach (self::manifests() as $slug => $manifest) {
            foreach ((array) ($manifest['plan_limits'] ?? []) as $key => $meta) {
                if (is_int($key)) {
                    $key  = (string) $meta;
                    $meta = [];
                }
                $key = (string) $key;
                if ($key === '') {
                    continue;
                }
                $out[$key] = [
                    'label'     => __((string) ($meta['label'] ?? $key)),
                    'group'     => (string) ($meta['group'] ?? ($manifest['name'] ?? $slug)),
                    // null means unlimited, which is a different fact from 0.
                    'default'   => array_key_exists('default', $meta) && $meta['default'] !== null
                        ? (int) $meta['default']
                        : null,
                    'hint'      => (string) ($meta['hint'] ?? ''),
                    'extension' => $slug,
                ];
            }
        }

        return $out;
    }

    /**
     * Does this flag belong to an extension that is NOT currently active?
     *
     * Fails closed. A plan keeps its `access_instagram_*` columns after the
     * extension is disabled or removed, and a stale `true` in the database
     * must not keep granting access to code that is no longer installed.
     *
     * Deliberately generic — no extension slug appears here. A flag is dormant
     * when it is neither a core flag nor contributed by an active extension.
     */
    public static function isDormantFeature(string $flag): bool
    {
        if (isset(self::planFeatures()[$flag])) {
            return false;   // an active extension owns it
        }

        $core = \App\Http\Controllers\AdminPagesController::PLAN_FEATURE_TOGGLES;

        return !in_array($flag, $core, true);
    }

    public static function forget(): void
    {
        // Runs during install / migrations too, where the database cache store
        // may not be reachable yet — never let a cache flush 500 the request.
        try {
            Cache::forget('extensions.manifests');
        } catch (\Throwable $e) {
            // fresh install / no cache store — nothing to forget.
        }
    }
}
