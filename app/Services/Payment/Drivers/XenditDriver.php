<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xendit payment gateway driver (Southeast Asia).
 *
 * Creates an Invoice and redirects to Xendit-hosted checkout.
 *
 * @see https://docs.xendit.co/api-reference
 */
class XenditDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.xendit.co';

    public static function credentialFields(): array
    {
        return [
            'secret_key'    => ['label' => 'Secret Key',    'type' => 'password', 'required' => true],
            'public_key'    => ['label' => 'Public Key',    'type' => 'text',     'required' => true],
            'webhook_token' => ['label' => 'Webhook Token', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('xendit_secret_key_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('xendit_customer_email_missing');

        $extId = $order->order_number . '_' . time();
        $body = [
            'external_id'          => $extId,
            'amount'               => (float) $order->amount,
            'currency'             => strtoupper($order->currency ?? 'IDR'),
            'description'          => "Order #{$order->order_number}",
            'payer_email'          => $email,
            // Carry our external_id back on the success return so handleCallback
            // can verify the REAL invoice status via the API. The redirect's
            // ?status=success alone returned 'pending', which the checkout
            // treats as FAILED — the reported "Xendit paid but site says failed".
            'success_redirect_url' => $callbackUrl . '?status=success&xendit_ext=' . urlencode($extId),
            'failure_redirect_url' => $callbackUrl . '?status=failed',
        ];

        try {
            $r = Http::withBasicAuth($secretKey, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/v2/invoices', $body);
            $json = $r->json() ?: [];
            Log::info('[XENDIT-INIT] invoice create', [
                'ext' => $extId, 'http' => $r->status(),
                'id' => $json['id'] ?? null, 'has_url' => isset($json['invoice_url']),
                'msg' => $json['message'] ?? null,
            ]);
            if (isset($json['invoice_url'])) {
                return PaymentResult::redirect($json['invoice_url'], $json['id'] ?? null, $json);
            }
            return PaymentResult::failed('xendit: ' . ($json['message'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            Log::error('[XENDIT-INIT] exception: ' . $e->getMessage());
            return PaymentResult::failed('xendit_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        $extId  = (string) ($payload['xendit_ext'] ?? '');
        Log::info('[XENDIT-CALLBACK] return', ['status' => $status, 'ext' => $extId, 'payload' => $payload]);

        if ($status === 'failed') return PaymentResult::failed('xendit_failed');

        // The success redirect (`?status=success`) is NOT trusted on its own,
        // and returning 'pending' makes the checkout mark the order failed
        // (it only accepts 'paid' on return). So look up the REAL invoice
        // status via the API and confirm PAID/SETTLED → paid.
        if ($extId !== '') {
            $inv = $this->lookupInvoiceByExternalId($extId);
            $s   = strtoupper((string) ($inv['status'] ?? ''));
            Log::info('[XENDIT-CALLBACK] api lookup', ['ext' => $extId, 'invoice_status' => $s, 'id' => $inv['id'] ?? null]);
            if ($s === 'PAID' || $s === 'SETTLED') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($inv['id'] ?? ''),
                    gatewayOrderId:   $extId,
                    payload:          $inv,
                );
            }
        } else {
            Log::warning('[XENDIT-CALLBACK] no xendit_ext on return — cannot verify; left pending (webhook will confirm)');
        }

        return new PaymentResult(status: 'pending', payload: $payload);
    }

    /** Look up an invoice by our external_id (Xendit returns an array). */
    private function lookupInvoiceByExternalId(string $extId): array
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '' || $extId === '') return [];
        try {
            $r = Http::withBasicAuth($secretKey, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/v2/invoices', ['external_id' => $extId]);
            $arr = $r->json();
            if (is_array($arr) && isset($arr[0]) && is_array($arr[0])) return $arr[0];
            return is_array($arr) ? $arr : [];
        } catch (\Throwable $e) {
            Log::warning('[XENDIT-CALLBACK] lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $token = (string) $this->cred('webhook_token');
        // Skip only when no callback token is configured; reject when a token
        // is set but the x-callback-token header is absent.
        if ($token === '') { Log::info('[XENDIT-WEBHOOK] no token configured — verification skipped'); return true; }
        if ($signatureHeader === null) { Log::warning('[XENDIT-WEBHOOK] x-callback-token header MISSING — rejected (set the same token in Xendit dashboard + our gateway settings)'); return false; }
        $ok = hash_equals($token, $signatureHeader);
        if (!$ok) Log::warning('[XENDIT-WEBHOOK] token MISMATCH — rejected');
        return $ok;
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status     = $payload['status'] ?? '';
        $invoiceId  = $payload['id'] ?? '';
        $externalId = $payload['external_id'] ?? '';
        Log::info('[XENDIT-WEBHOOK] event', ['status' => $status, 'id' => $invoiceId, 'external_id' => $externalId]);
        if ($status === 'PAID' || $status === 'SETTLED') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) $invoiceId,
                gatewayOrderId:   (string) $externalId,
                payload:          $payload,
            );
        }
        return PaymentResult::failed("xendit_webhook_status: {$status}", $payload);
    }
}
