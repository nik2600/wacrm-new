<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Registers a WABA phone number on the WhatsApp Cloud API.
 *
 * Meta rejects EVERY send with `(#133010) Account not registered` until a
 * number has completed Cloud API registration:
 *   POST /{phone_number_id}/register  { messaging_product, pin }
 *
 * Our embedded-signup-via-devices and manual-paste connect paths mark a row
 * `connected` but never call /register, so a freshly added number can look
 * healthy yet fail on the first send. This centralises the registration call
 * so both the connect paths (auto, best-effort) and the operator's "Register"
 * button use one implementation.
 *
 * SAFETY — a COEXISTENCE number is shared with the WhatsApp Business app;
 * registering it migrates the number FULLY onto the Cloud API and kicks it off
 * the app. So auto-callers pass $allowCoexistence=false (coexistence rows are
 * skipped, never silently migrated); only an explicit, operator-confirmed
 * action passes true.
 */
class WabaNumberRegistrar
{
    /**
     * @return array{ok:bool, error:?string, skipped:bool}
     *   ok      = the number is registered (now, or already was)
     *   skipped = we intentionally did nothing (coexistence, or no creds)
     */
    public function register(WaProviderConfig $cfg, ?string $pin = null, bool $allowCoexistence = false): array
    {
        $creds   = $cfg->creds();
        $meta    = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) ($meta['phone_number_id'] ?? $creds['phone_number_id'] ?? '');
        $isCoex  = (bool) ($meta['coexistence'] ?? false);

        if ($token === '' || $phoneId === '') {
            return ['ok' => false, 'error' => 'This number has no stored phone-number id / token — reconnect it once to restore its credentials.', 'skipped' => true];
        }

        if ($isCoex && ! $allowCoexistence) {
            // Never migrate a coexistence number off the Business app silently.
            return ['ok' => false, 'error' => 'Coexistence number — skipped registration to keep it live on the WhatsApp Business app.', 'skipped' => true];
        }

        $gv  = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        // Reuse the pin we minted at connect if we have one; a number with
        // two-step verification already set MUST supply its existing pin, so
        // preferring the stored value avoids a needless 133005 mismatch.
        $pin = $pin
            ?: (string) ($creds['register_pin'] ?? '')
            ?: str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $res = Http::withToken($token)->acceptJson()->timeout(15)
                ->post("https://graph.facebook.com/{$gv}/{$phoneId}/register", [
                    'messaging_product' => 'whatsapp',
                    'pin'               => $pin,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[WABA-REGISTER] threw', ['phone_id' => $phoneId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not reach Meta: ' . $e->getMessage(), 'skipped' => false];
        }

        if ($res->successful()) {
            // Persist the working pin so a later re-register / 2FA op reuses it.
            $cfg->setCreds(array_merge($creds, ['register_pin' => $pin]))->save();
            Log::info('[WABA-REGISTER] ok', ['phone_id' => $phoneId, 'config_id' => $cfg->id]);
            return ['ok' => true, 'error' => null, 'skipped' => false];
        }

        $code = (int) $res->json('error.code');
        $msg  = (string) ($res->json('error.message') ?? 'unknown');

        // A number that is ALREADY registered is a success for our purposes —
        // stop nagging the operator. Meta phrases this as "already registered".
        if (str_contains(strtolower($msg), 'already')) {
            return ['ok' => true, 'error' => null, 'skipped' => false];
        }

        // Friendlier hints for the common register failure codes.
        $hint = match ($code) {
            133005 => ' — the number already has a two-step-verification PIN. Enter that exact PIN.',
            133006 => ' — the number needs re-verification in Meta Business Suite first.',
            133008, 133009 => ' — too many attempts; wait a few minutes and retry.',
            // SMB / coexistence number — Cloud-API /register isn't available for
            // it (it stays on the WhatsApp Business app). Not our bug, and it
            // doesn't need registering; finish setup in Meta Business Suite.
            100 => ' — this number is managed by the WhatsApp Business app (coexistence) and doesn\'t use Cloud-API registration. Finish its setup in Meta Business Suite; if sends still fail, use "Fix inbound" or reconnect.',
            default => '',
        };

        Log::warning('[WABA-REGISTER] failed', ['phone_id' => $phoneId, 'code' => $code, 'msg' => $msg]);
        return ['ok' => false, 'error' => "(#{$code}) {$msg}{$hint}", 'skipped' => false];
    }
}
