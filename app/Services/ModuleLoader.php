<?php

namespace App\Services;

/**
 * In-place module loader — runs add-ons straight from `addon/<slug>/` WITHOUT
 * copying their files into the core tree (unlike ExtensionService, which unpacks
 * an uploaded ZIP into base_path). Drop a module folder in `addon/`, run
 * `php artisan migrate`, and it boots: its `App\` classes autoload from the
 * module dir, its routes/migrations/views load from there, and its manifest
 * (nav + plan features/limits) is merged by ExtensionRegistry.
 *
 * A module folder is any `addon/<name>/` that contains an `extension.json`.
 */
class ModuleLoader
{
    private static ?array $dirs = null;
    private static ?array $manifests = null;
    private static ?string $root = null;

    /**
     * Project root. registerAutoloading() runs at the very top of
     * bootstrap/app.php — BEFORE the app container is configured — so the
     * base_path() helper isn't available yet and the root must be passed in.
     * Later callers fall back to base_path().
     */
    private static function root(): string
    {
        return self::$root ?? base_path();
    }

    /**
     * Absolute paths of ENABLED addon/* folders (carry an extension.json AND no
     * `.disabled` marker). This is the single source every loader path derives
     * from — autoload, routes, migrations, views, manifests — so a disabled
     * module drops out of ALL of them and simply stops running. The module's
     * code is left completely untouched; only a hidden marker file is added, so
     * re-enabling it is instant and nothing needs re-copying.
     */
    public static function dirs(): array
    {
        if (self::$dirs !== null) {
            return self::$dirs;
        }
        return self::$dirs = array_values(array_filter(
            self::allDirs(),
            fn ($dir) => ! is_file($dir . '/.disabled'),
        ));
    }

    /** EVERY addon/* folder with an extension.json — including disabled ones.
     *  Used only by the admin add-ons page so a disabled module still shows
     *  (with a re-enable action). NOT used by any runtime loader path. */
    public static function allDirs(): array
    {
        $out  = [];
        $base = self::root() . '/addon';
        if (is_dir($base)) {
            foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (is_file($dir . '/extension.json')) {
                    $out[] = $dir;
                }
            }
        }
        return $out;
    }

    /** [slug => manifest] for EVERY module, each tagged `_disabled`. Admin UI. */
    public static function allManifests(): array
    {
        $out = [];
        foreach (self::allDirs() as $dir) {
            $m = json_decode((string) @file_get_contents($dir . '/extension.json'), true);
            if (is_array($m) && ! empty($m['slug'])) {
                $m['_dir']      = $dir;
                $m['_disabled'] = is_file($dir . '/.disabled');
                $out[(string) $m['slug']] = $m;
            }
        }
        return $out;
    }

    /** Directory of an in-place module by slug (from ALL modules), or null. */
    public static function dirForSlug(string $slug): ?string
    {
        foreach (self::allDirs() as $dir) {
            if (basename($dir) === $slug) {
                return $dir;
            }
        }
        return null;
    }

    public static function isDisabled(string $slug): bool
    {
        $dir = self::dirForSlug($slug);
        return $dir !== null && is_file($dir . '/.disabled');
    }

    /** Deactivate an in-place module WITHOUT deleting its files (drop a marker). */
    public static function disable(string $slug): bool
    {
        $dir = self::dirForSlug($slug);
        if ($dir === null) {
            return false;
        }
        @file_put_contents($dir . '/.disabled', "disabled " . date('c') . "\n");
        self::$dirs = self::$manifests = null;  // bust the per-request memo
        return is_file($dir . '/.disabled');
    }

    /** Re-activate a previously disabled in-place module (remove the marker). */
    public static function enable(string $slug): bool
    {
        $dir = self::dirForSlug($slug);
        if ($dir === null) {
            return false;
        }
        @unlink($dir . '/.disabled');
        self::$dirs = self::$manifests = null;
        return ! is_file($dir . '/.disabled');
    }

    /** [slug => manifest] read from each module's extension.json. */
    public static function manifests(): array
    {
        if (self::$manifests !== null) {
            return self::$manifests;
        }
        $out = [];
        foreach (self::dirs() as $dir) {
            $m = json_decode((string) @file_get_contents($dir . '/extension.json'), true);
            if (is_array($m) && ! empty($m['slug'])) {
                $m['_dir'] = $dir;                 // remember where it lives
                $out[(string) $m['slug']] = $m;
            }
        }
        return self::$manifests = $out;
    }

    /**
     * Append each module's `app/` dir to the Composer PSR-4 map for `App\`, so
     * `App\…\Foo` resolves from `addon/<mod>/app/…/Foo.php` when it isn't in the
     * core `app/`. Called from bootstrap/app.php BEFORE routes are required.
     */
    public static function registerAutoloading(?string $basePath = null): void
    {
        if ($basePath !== null) {
            self::$root = rtrim(str_replace('\\', '/', $basePath), '/');
        }
        $autoload = self::root() . '/vendor/autoload.php';
        if (! is_file($autoload)) {
            return;
        }
        $loader = require $autoload; // Composer's cached ClassLoader instance
        if (! is_object($loader) || ! method_exists($loader, 'addPsr4')) {
            return;
        }
        foreach (self::dirs() as $dir) {
            if (is_dir($dir . '/app')) {
                // Append (4th arg false) so the core app/ is still searched first.
                $loader->addPsr4('App\\', $dir . '/app/', false);
            }
        }
    }

    /** Absolute route-file paths declared by module manifests. */
    public static function routeFiles(): array
    {
        $out = [];
        foreach (self::manifests() as $m) {
            $dir = (string) ($m['_dir'] ?? '');
            foreach ((array) ($m['routes'] ?? []) as $rel) {
                $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
                if ($rel === '' || str_contains($rel, '..')) {
                    continue;
                }
                $path = $dir . '/' . $rel;
                if (is_file($path)) {
                    $out[] = $path;
                }
            }
        }
        return $out;
    }

    /** Each module's migrations dir (for loadMigrationsFrom). */
    public static function migrationDirs(): array
    {
        return array_values(array_filter(
            array_map(fn ($d) => $d . '/database/migrations', self::dirs()),
            'is_dir',
        ));
    }

    /** Each module's views dir (added to the view finder). */
    public static function viewDirs(): array
    {
        return array_values(array_filter(
            array_map(fn ($d) => $d . '/resources/views', self::dirs()),
            'is_dir',
        ));
    }
}
