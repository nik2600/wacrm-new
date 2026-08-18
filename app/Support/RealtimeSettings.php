<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Platform-level real-time (Pusher) configuration.
 *
 * ONE Pusher app powers the whole platform: the admin pastes the keys on
 * /admin/settings/realtime, they're stored in system_settings (secret at rest
 * via Crypt), and applyToConfig() flips Laravel's broadcaster from `log` to
 * `pusher` at runtime — no .env edit, works with a cached config. The frontend
 * gets only the PUBLIC key + cluster (never the secret) via publicConfig().
 */
class RealtimeSettings
{
    /** Real-time on AND a key+cluster present — otherwise we stay on `log`. */
    public static function enabled(): bool
    {
        try {
            if (! (bool) SystemSetting::get('realtime_enabled', false)) return false;
            $c = self::raw();
            return $c['key'] !== '' && $c['cluster'] !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** All four values, secret decrypted. Empty strings when unset. */
    public static function raw(): array
    {
        $secret = (string) SystemSetting::get('pusher_app_secret', '');
        if ($secret !== '') {
            try { $secret = Crypt::decryptString($secret); } catch (\Throwable $e) { /* legacy plaintext */ }
        }
        return [
            'app_id'  => (string) SystemSetting::get('pusher_app_id', ''),
            'key'     => (string) SystemSetting::get('pusher_app_key', ''),
            'secret'  => $secret,
            'cluster' => (string) SystemSetting::get('pusher_cluster', 'mt1'),
        ];
    }

    /** Only what's safe to hand the browser (Echo needs key + cluster). */
    public static function publicConfig(): array
    {
        if (! self::enabled()) return ['enabled' => false];
        $c = self::raw();
        return [
            'enabled' => true,
            'key'     => $c['key'],
            'cluster' => $c['cluster'],
        ];
    }

    /** Runtime override — called from AppServiceProvider::boot(). */
    public static function applyToConfig(): void
    {
        if (! self::enabled()) return;
        $c = self::raw();
        config([
            'broadcasting.default'                              => 'pusher',
            'broadcasting.connections.pusher.app_id'            => $c['app_id'],
            'broadcasting.connections.pusher.key'               => $c['key'],
            'broadcasting.connections.pusher.secret'            => $c['secret'],
            'broadcasting.connections.pusher.options.cluster'   => $c['cluster'],
            'broadcasting.connections.pusher.options.host'      => 'api-' . $c['cluster'] . '.pusher.com',
            'broadcasting.connections.pusher.options.useTLS'    => true,
            'broadcasting.connections.pusher.options.encrypted' => true,
        ]);
    }

    /** Persist from the admin form. Secret only overwritten when provided. */
    public static function save(array $data): void
    {
        SystemSetting::set('realtime_enabled', (bool) ($data['realtime_enabled'] ?? false) ? 1 : 0, 'int');
        SystemSetting::set('pusher_app_id', (string) ($data['pusher_app_id'] ?? ''), 'string');
        SystemSetting::set('pusher_app_key', (string) ($data['pusher_app_key'] ?? ''), 'string');
        SystemSetting::set('pusher_cluster', (string) ($data['pusher_cluster'] ?? 'mt1'), 'string');
        if (!empty($data['pusher_app_secret'])) {
            SystemSetting::set('pusher_app_secret', Crypt::encryptString((string) $data['pusher_app_secret']), 'string');
        }
    }
}
