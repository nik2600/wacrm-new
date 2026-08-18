<?php

namespace App\Services\Storefront;

use App\Models\WaOrder;
use App\Models\WaStorefront;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Storefront payment links (S2). Mints a REAL, shareable payment link with
 * the MERCHANT's own gateway keys (stored per-storefront), and verifies the
 * gateway webhook to auto-flip the order to paid. Reuses the exact request +
 * HMAC-signature format from App\Services\Payment\Drivers\RazorpayDriver, so
 * it behaves identically to the platform billing gateway — just pointed at
 * the merchant's account so the money lands with them, not the platform.
 *
 * Razorpay is implemented (INR/UPI-first, the primary market). Other
 * providers fall back to the existing static-link / COD path.
 *
 * Keys live in WaStorefront.payment_config_json with the secrets encrypted
 * at rest (Crypt). payment_provider must be 'razorpay_api'.
 */
class StorefrontPaymentService
{
    private const RZP_API = 'https://api.razorpay.com/v1';

    /** Does this storefront have API-based link minting configured? */
    public function supportsLinks(WaStorefront $sf): bool
    {
        if ($sf->payment_provider !== 'razorpay_api') return false;
        $c = $this->config($sf);
        return $c['key_id'] !== '' && $c['key_secret'] !== '';
    }

    /**
     * Mint a payment link for an order. Returns ['url'=>..., 'id'=>...] or
     * null when unconfigured / on error (caller falls back to manual link).
     */
    public function mintLink(WaStorefront $sf, WaOrder $order, string $callbackUrl): ?array
    {
        if (!$this->supportsLinks($sf)) return null;
        $c = $this->config($sf);

        $amountMinor = (int) $order->total_minor;
        if ($amountMinor < 100) return null; // Razorpay minimum

        try {
            $r = Http::withBasicAuth($c['key_id'], $c['key_secret'])
                ->timeout(20)
                ->post(self::RZP_API . '/payment_links', [
                    'amount'                 => $amountMinor,
                    'currency'               => strtoupper($order->currency_code ?: 'INR'),
                    'accept_partial'         => false,
                    'reference_id'           => 'WAORD-' . $order->id,
                    'description'            => 'Order #' . $order->id . ' · ' . ($sf->shop_name ?: 'Store'),
                    'customer'               => array_filter([
                        'name'    => $order->customer_name ?: null,
                        'contact' => $order->customer_phone ?: null,
                        'email'   => $order->customer_email ?: null,
                    ]),
                    'notify'                 => ['sms' => false, 'email' => false], // we deliver via WhatsApp ourselves
                    'reminder_enable'        => false,
                    'notes'                  => [
                        'wa_order_id'   => (string) $order->id,
                        'storefront_id' => (string) $sf->id,
                        'workspace_id'  => (string) $sf->workspace_id,
                    ],
                    'callback_url'           => $callbackUrl,
                    'callback_method'        => 'get',
                ]);

            if (!$r->successful()) {
                Log::warning('[STOREFRONT-PAY] mint failed: ' . ($r->json('error.description') ?: ('HTTP ' . $r->status())));
                return null;
            }
            $j = $r->json();
            return ['url' => $j['short_url'] ?? null, 'id' => $j['id'] ?? null];
        } catch (Throwable $e) {
            Log::warning('[STOREFRONT-PAY] mint exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify a Razorpay webhook against the merchant's webhook secret.
     * Same HMAC-SHA256(rawBody, secret) === X-Razorpay-Signature check the
     * platform RazorpayDriver uses. If the merchant set no secret we can't
     * verify, so we reject (fail-closed) to avoid spoofed paid flips.
     */
    public function verifyWebhook(WaStorefront $sf, string $rawBody, ?string $signature): bool
    {
        $secret = $this->config($sf)['webhook_secret'];
        if ($secret === '' || !$signature) return false; // fail-closed
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Apply a verified `payment_link.paid` / `payment.captured` event to the
     * order: flip status to paid (idempotent). Returns the order or null.
     */
    public function applyPaidEvent(array $payload): ?WaOrder
    {
        $entity = $payload['payload']['payment_link']['entity']
            ?? $payload['payload']['payment']['entity']
            ?? [];
        $notes  = is_array($entity['notes'] ?? null) ? $entity['notes'] : [];
        $orderId = (int) ($notes['wa_order_id'] ?? 0);
        if ($orderId < 1 && !empty($entity['reference_id'])) {
            $orderId = (int) preg_replace('/\D+/', '', (string) $entity['reference_id']);
        }
        if ($orderId < 1) return null;

        $order = WaOrder::find($orderId);
        if (!$order) return null;
        if ($order->status === 'paid') return $order; // idempotent

        $order->forceFill([
            'status'      => 'paid',
            'payment_link' => $entity['short_url'] ?? $order->payment_link,
            'meta_json'   => array_merge(is_array($order->meta_json) ? $order->meta_json : [], [
                'paid_via'           => 'razorpay_link',
                'razorpay_payment'   => $payload['payload']['payment']['entity']['id'] ?? null,
                'razorpay_plink'     => $entity['id'] ?? null,
            ]),
        ])->save();

        return $order;
    }

    /** Decrypted merchant gateway config (key_id plain, secrets decrypted). */
    public function config(WaStorefront $sf): array
    {
        $raw = is_array($sf->payment_config_json) ? $sf->payment_config_json : [];
        return [
            'key_id'         => (string) ($raw['key_id'] ?? ''),
            'key_secret'     => $this->dec($raw['key_secret'] ?? ''),
            'webhook_secret' => $this->dec($raw['webhook_secret'] ?? ''),
        ];
    }

    /** Encrypt a secret for storage (used by the settings controller). */
    public static function encryptSecret(?string $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : Crypt::encryptString($v);
    }

    private function dec($v): string
    {
        $v = (string) $v;
        if ($v === '') return '';
        try {
            return Crypt::decryptString($v);
        } catch (Throwable) {
            return $v; // tolerate a plaintext value (pre-encryption)
        }
    }
}
