<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

/**
 * Web-Push (VAPID) sender for the Team-Inbox PWA. Rings an agent's subscribed
 * devices even when the app is closed.
 *
 * Depends on the `minishlink/web-push` composer package. Every entry point
 * degrades to a NO-OP when the package isn't installed or VAPID keys haven't
 * been generated yet, so the app never fatals before setup finishes
 * (`composer require minishlink/web-push` + generating the keys from the admin
 * PWA settings "Generate keys" button).
 */
class WebPushService
{
    /** Usable right now? — package present AND a VAPID keypair stored. */
    public function isConfigured(): bool
    {
        return $this->libAvailable() && $this->publicKey() !== '' && $this->privateKey() !== '';
    }

    public function libAvailable(): bool
    {
        return class_exists(\Minishlink\WebPush\WebPush::class);
    }

    public function publicKey(): string
    {
        return (string) SystemSetting::get('ti_vapid_public', '');
    }

    private function privateKey(): string
    {
        return (string) SystemSetting::get('ti_vapid_private', '');
    }

    /** VAPID subject — a contact URL/mailto Meta/Google require. App URL is valid. */
    private function subject(): string
    {
        $s = trim((string) SystemSetting::get('ti_vapid_subject', ''));
        if ($s !== '') return $s;
        $url = rtrim((string) config('app.url', ''), '/');
        return $url !== '' ? $url : rtrim(url('/'), '/');
    }

    /**
     * Generate + persist a VAPID keypair once. Returns the public key, or '' if
     * the package isn't installed yet / generation failed. Idempotent — keeps
     * existing keys.
     */
    public function generateKeys(): string
    {
        if (!$this->libAvailable()) return '';
        if ($this->publicKey() !== '' && $this->privateKey() !== '') return $this->publicKey();

        // Prefer the library's generator. On servers whose PHP can't find an
        // OpenSSL config (Windows / XAMPP: "configuration file routines: no such
        // file"), openssl_pkey_new(EC) fails and the library throws "Unable to
        // create the key" — so we fall back to building the P-256 keypair
        // ourselves with an EXPLICIT OpenSSL config, which works regardless.
        $keys = null;
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            Log::info('[WEBPUSH] library key-gen failed, using explicit-config fallback: ' . $e->getMessage());
        }
        if (!$keys || empty($keys['publicKey']) || empty($keys['privateKey'])) {
            $keys = $this->createVapidKeysWithConfig();
        }
        if (!$keys || empty($keys['publicKey']) || empty($keys['privateKey'])) {
            return '';
        }

        SystemSetting::set('ti_vapid_public', $keys['publicKey'], 'string');
        SystemSetting::set('ti_vapid_private', $keys['privateKey'], 'string'); // encrypted at rest (ENCRYPTED_KEYS)
        return $keys['publicKey'];
    }

    /**
     * Build a P-256 (prime256v1) VAPID keypair by hand with an EXPLICIT OpenSSL
     * config path — the rescue for servers where PHP's default OpenSSL config is
     * missing so `VAPID::createVapidKeys()` throws. Emits the SAME base64url-
     * unpadded shape the library does:
     *   publicKey  = base64url( 0x04 || X(32) || Y(32) )   (65 bytes)
     *   privateKey = base64url( d(32) )                    (32 bytes)
     *
     * @return array{publicKey:string,privateKey:string}|null
     */
    private function createVapidKeysWithConfig(): ?array
    {
        try {
            $args = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
            $cnf  = $this->opensslConfigPath();
            if ($cnf !== null) $args['config'] = $cnf;

            $res = @openssl_pkey_new($args);
            if (!$res) return null;

            $det = @openssl_pkey_get_details($res);
            if (!$det || !isset($det['ec']['d'], $det['ec']['x'], $det['ec']['y'])) return null;

            $b64u = fn (string $b): string => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
            $pad  = fn (string $b): string => str_pad($b, 32, "\0", STR_PAD_LEFT); // left-pad to 32 bytes

            $public  = "\x04" . $pad($det['ec']['x']) . $pad($det['ec']['y']);
            $private = $pad($det['ec']['d']);

            return ['publicKey' => $b64u($public), 'privateKey' => $b64u($private)];
        } catch (\Throwable $e) {
            Log::warning('[WEBPUSH] explicit-config key-gen failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a usable OpenSSL config file for key generation. Tries OPENSSL_CONF,
     * common XAMPP/Linux locations, else writes a minimal one we control so the
     * EC keygen can always initialise. Returns null only if even that fails.
     */
    private function opensslConfigPath(): ?string
    {
        foreach (array_filter([
            getenv('OPENSSL_CONF') ?: null,
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/conf/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
        ]) as $p) {
            if (is_file($p)) return $p;
        }
        try {
            $path = storage_path('app/openssl-vapid.cnf');
            if (!is_file($path)) {
                @file_put_contents($path, "[req]\ndistinguished_name = dn\n[dn]\n");
            }
            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Push to every device the given agent subscribed on the team-inbox channel.
     * Silent no-op when not configured. Expired subscriptions are pruned.
     *
     * $payload is what the service worker's `push` handler receives, e.g.
     *   ['title' => 'New message', 'body' => 'Hi…', 'url' => '/team-inbox?c=42', 'tag' => 'ti-42']
     */
    public function sendToUser(int $userId, array $payload): void
    {
        if ($userId <= 0 || !$this->isConfigured()) return;

        $subs = PushSubscription::query()
            ->where('user_id', $userId)
            ->where('channel', 'team-inbox')
            ->get();
        if ($subs->isEmpty()) return;

        $this->deliver($subs, $payload);
    }

    /** @param \Illuminate\Support\Collection<int,PushSubscription> $subs */
    private function deliver($subs, array $payload): void
    {
        try {
            $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => [
                'subject'    => $this->subject(),
                'publicKey'  => $this->publicKey(),
                'privateKey' => $this->privateKey(),
            ]]);

            $byEndpoint = [];
            foreach ($subs as $sub) {
                $byEndpoint[$sub->endpoint] = $sub;
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys'     => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                    ]),
                    json_encode($payload)
                );
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getEndpoint();
                $sub = $byEndpoint[$endpoint] ?? null;
                if ($report->isSuccess()) {
                    $sub?->forceFill(['last_notified_at' => now()])->saveQuietly();
                    continue;
                }
                // 404 / 410 → the browser dropped the subscription; prune it.
                if ($report->isSubscriptionExpired()) {
                    $sub?->delete();
                } else {
                    Log::warning('[WEBPUSH] delivery failed', [
                        'endpoint' => mb_substr((string) $endpoint, 0, 80),
                        'reason'   => $report->getReason(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[WEBPUSH] send exception: ' . $e->getMessage());
        }
    }
}
