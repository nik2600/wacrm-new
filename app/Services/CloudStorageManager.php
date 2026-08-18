<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cloud media storage. When an admin configures + enables a remote provider in
 * /admin/storage, all client media uploads go to that bucket instead of the
 * local disk, and are served back from it. When disabled (default), everything
 * falls back to the local `public` disk — so this breaks nothing out of the box.
 *
 * Config lives in a single SystemSetting JSON key `cloud_storage`:
 *   {
 *     enabled: bool, provider: 's3'|'wasabi'|'bunny'|'spaces'|'r2'|'minio',
 *     visibility: 'public'|'private', base_path: 'wadesk',
 *     providers: { <provider>: { key, secret(enc), region, bucket, endpoint, url, ... } }
 *   }
 * Secret fields are encrypted at rest via Crypt.
 *
 * Mirrors the SnapNest CloudStorageManager pattern (S3-compatible family only —
 * AWS S3, Wasabi, Bunny.net, DigitalOcean Spaces, Cloudflare R2, MinIO — all of
 * which use Laravel's `s3` driver). That driver is NOT bundled with the
 * framework: it needs `league/flysystem-aws-s3-v3` (+ `aws/aws-sdk-php`), which
 * are declared in composer.json. If a "Connection failed: Class …
 * PortableVisibilityConverter not found" appears, run `composer install` — the
 * package is missing from vendor/ (e.g. after an in-app update, which skips vendor).
 */
class CloudStorageManager
{
    /** Dynamically-registered disk name used for all client media. */
    public const DISK = 'client_media';

    /** Fallback when no cloud provider is configured/enabled. */
    public const LOCAL_DISK = 'public';

    /** Secret fields encrypted at rest (per provider config array). */
    private const SECRET_FIELDS = ['secret', 'access_key'];

    private static bool $registered = false;

    /** Provider → human label (for the admin UI + logs). */
    public const PROVIDERS = [
        's3'     => 'Amazon S3',
        'wasabi' => 'Wasabi',
        'bunny'  => 'Bunny.net Storage',
        'spaces' => 'DigitalOcean Spaces',
        'r2'     => 'Cloudflare R2',
        'minio'  => 'MinIO / S3-compatible',
    ];

    /** Read the raw config blob (decoded), tolerant of bad/missing data. */
    public function config(): array
    {
        $raw = SystemSetting::get('cloud_storage');
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function provider(): string
    {
        return (string) ($this->config()['provider'] ?? '');
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config()['enabled'] ?? false) && $this->hasProviderConfig();
    }

    /** The disk name media should use right now: cloud when ready, else local. */
    public function diskName(): string
    {
        if ($this->isEnabled()) {
            // Callers use this name with storeAs(..., $name) / Storage::disk($name),
            // so the dynamic disk MUST be registered before we advertise it —
            // otherwise Laravel throws "Disk [client_media] does not have a
            // configured driver". Only return the cloud disk if registration
            // actually produced a config; otherwise fall back to local so uploads
            // never hard-fail on a half-configured provider.
            $this->registerDisks();
            if (config('filesystems.disks.' . self::DISK)) {
                return self::DISK;
            }
        }
        return self::LOCAL_DISK;
    }

    /** The Storage disk instance media should use right now. */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        if ($this->isEnabled()) {
            $this->registerDisks();
            return Storage::disk(self::DISK);
        }
        return Storage::disk(self::LOCAL_DISK);
    }

    /** Register the dynamic `client_media` disk from saved config (idempotent). */
    public function registerDisks(): void
    {
        if (self::$registered || !$this->hasProviderConfig()) {
            return;
        }
        $diskConfig = $this->buildDiskConfig();
        if (!$diskConfig) {
            return;
        }
        config(['filesystems.disks.' . self::DISK => $diskConfig]);
        self::$registered = true;
    }

    /** Are all required credentials present for the chosen provider? */
    public function hasProviderConfig(): bool
    {
        $cfg = $this->providerConfig();
        // Bunny uses storage_zone + access_key; the rest use key/secret/bucket.
        if ($this->provider() === 'bunny') {
            return $this->hasAll($cfg, ['storage_zone', 'access_key']);
        }
        return $this->hasAll($cfg, ['key', 'secret', 'bucket']);
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider()] ?? 'Unknown';
    }

    /** Optional path prefix prepended to every stored object. */
    public function pathPrefix(): string
    {
        return trim((string) ($this->config()['base_path'] ?? ''), '/');
    }

    public function applyPrefix(string $path): string
    {
        $prefix = $this->pathPrefix();
        $path = ltrim($path, '/');
        if ($prefix === '' || str_starts_with($path, $prefix . '/')) {
            return $path;
        }
        return $prefix . '/' . $path;
    }

    /** Write a tiny object + delete it; returns ['ok'=>bool,'message'=>string]. */
    public function testConnection(): array
    {
        if (!$this->hasProviderConfig()) {
            return ['ok' => false, 'message' => 'Storage configuration is incomplete — fill in every required field first.'];
        }

        // Force a fresh registration so a just-saved config is tested.
        self::$registered = false;
        config(['filesystems.disks.' . self::DISK => $this->buildDiskConfig()]);

        $testPath = $this->applyPrefix('connection-tests/' . Str::random(12) . '.txt');
        try {
            $disk = Storage::disk(self::DISK);
            $disk->put($testPath, 'WaDesk cloud storage connection test.');
            $disk->delete($testPath);
        } catch (\Throwable $e) {
            Log::error('[CloudStorage] test FAILED', [
                'provider' => $this->provider(),
                'error'    => $e->getMessage(),
                'at'       => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['ok' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Connection successful — the bucket is reachable and writable.'];
    }

    /**
     * Persist config. Pass the decoded array; secret fields are encrypted here.
     * Existing encrypted secrets are kept when the posted field is blank (so the
     * admin doesn't have to re-type secrets on every save).
     */
    public function save(array $incoming): void
    {
        $current = $this->config();
        $provider = (string) ($incoming['provider'] ?? $current['provider'] ?? 's3');

        $providers = $current['providers'] ?? [];
        $existing  = $providers[$provider] ?? [];
        $posted    = $incoming['providers'][$provider] ?? [];

        $merged = array_merge($existing, array_filter($posted, fn ($v) => $v !== null));
        foreach (self::SECRET_FIELDS as $field) {
            $val = $posted[$field] ?? null;
            if ($val === null || $val === '') {
                // keep the previously-stored (already encrypted) secret
                $merged[$field] = $existing[$field] ?? '';
            } else {
                $merged[$field] = Crypt::encryptString((string) $val);
            }
        }
        $providers[$provider] = $merged;

        $blob = [
            'enabled'    => (bool) ($incoming['enabled'] ?? false),
            'provider'   => $provider,
            'visibility' => ($incoming['visibility'] ?? 'private') === 'public' ? 'public' : 'private',
            'base_path'  => trim((string) ($incoming['base_path'] ?? ''), '/'),
            'providers'  => $providers,
        ];

        SystemSetting::set('cloud_storage', json_encode($blob), 'json', 'Cloud media storage configuration');
        self::$registered = false; // force re-register on next use
    }

    /** Provider config for the UI, with secrets masked (never sent to browser). */
    public function configForForm(): array
    {
        $cfg = $this->config();
        $providers = $cfg['providers'] ?? [];
        foreach ($providers as $p => &$pc) {
            foreach (self::SECRET_FIELDS as $field) {
                if (!empty($pc[$field])) {
                    $pc[$field] = ''; // never echo secrets back; blank = keep existing
                    $pc['__has_' . $field] = true;
                }
            }
        }
        unset($pc);
        $cfg['providers'] = $providers;
        return $cfg;
    }

    // ---- internals -------------------------------------------------------

    /** Decrypted config array for the active provider. */
    private function providerConfig(): array
    {
        $provider = $this->provider();
        $cfg = (array) (($this->config()['providers'] ?? [])[$provider] ?? []);
        foreach (self::SECRET_FIELDS as $field) {
            if (!empty($cfg[$field])) {
                try {
                    $cfg[$field] = Crypt::decryptString($cfg[$field]);
                } catch (\Throwable) {
                    // tolerate plaintext (pre-encryption) values
                }
            }
        }
        return $cfg;
    }

    private function buildDiskConfig(): ?array
    {
        $provider = $this->provider();
        $c = $this->providerConfig();
        $visibility = ($this->config()['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

        $base = ['driver' => 's3', 'visibility' => $visibility, 'throw' => true];

        if ($provider === 'bunny') {
            // Bunny S3 API: AccessKeyId = storage zone, SecretAccessKey = zone password.
            $zone = (string) ($c['storage_zone'] ?? '');
            $region = strtolower(trim((string) ($c['region'] ?? ''))) ?: 'de';
            $host = $region === 'de' ? 'storage.bunnycdn.com' : $region . '.storage.bunnycdn.com';
            return array_merge($base, [
                'key'                     => $zone,
                'secret'                  => (string) ($c['access_key'] ?? ''),
                'region'                  => 'auto',
                'bucket'                  => $zone,
                'endpoint'                => 'https://' . $host,
                'url'                     => $c['cdn_url'] ?? $c['url'] ?? null,
                'use_path_style_endpoint' => true,
            ]);
        }

        // Wasabi default endpoint when only a region is given.
        $endpoint = $c['endpoint'] ?? null;
        if ($provider === 'wasabi' && empty($endpoint) && !empty($c['region'])) {
            $endpoint = 'https://s3.' . $c['region'] . '.wasabisys.com';
        }

        if (empty($c['key']) || empty($c['secret']) || empty($c['bucket'])) {
            return null;
        }

        return array_merge($base, [
            'key'                     => (string) $c['key'],
            'secret'                  => (string) $c['secret'],
            'region'                  => (string) ($c['region'] ?? 'us-east-1'),
            'bucket'                  => (string) $c['bucket'],
            'endpoint'                => $endpoint ?: null,
            'url'                     => $c['cdn_url'] ?? $c['url'] ?? null,
            'use_path_style_endpoint' => filter_var($c['use_path_style_endpoint'] ?? ($provider !== 's3'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    private function hasAll(array $cfg, array $keys): bool
    {
        foreach ($keys as $k) {
            if (empty($cfg[$k])) {
                return false;
            }
        }
        return true;
    }
}
