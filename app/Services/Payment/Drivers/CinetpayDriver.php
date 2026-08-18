<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CinetPay payment gateway driver (Francophone West Africa).
 *
 * Creates a CinetPay payment and redirects to the hosted page. Server
 * confirmation is fetched via the payment/check endpoint.
 *
 * @see https://docs.cinetpay.com/
 */
class CinetpayDriver extends AbstractGatewayDriver
{
    private const API_HOST = 'api-checkout.cinetpay.com';
    private const API_BASE = 'https://api-checkout.cinetpay.com/v2';

    /**
     * Currencies CinetPay only accepts as WHOLE multiples of 5
     * (the West/Central-African francs). A non-multiple amount is
     * rejected before the payment page even opens.
     */
    private const MULTIPLE_OF_5_CURRENCIES = ['XOF', 'XAF', 'CDF', 'GNF'];

    /**
     * Set by resolveViaDoh() to the last DNS `Status` an upstream resolver
     * reported. 3 == NXDOMAIN, meaning the CinetPay API hostname genuinely
     * has NO record in public DNS (a gateway-SIDE outage) — which we surface
     * as a clear message rather than a raw "could not resolve host".
     */
    private ?int $lastDohStatus = null;

    /**
     * Shared HTTP client for every CinetPay call.
     *
     * The reported failure was `cURL error 28: Resolving timed out after
     * 10001ms` — Laravel's DEFAULT 10s connect-timeout being hit during DNS
     * resolution, because many shared hosts hang on the IPv6 (AAAA) lookup
     * for api-checkout.cinetpay.com and never fall through to IPv4. So we:
     *   - force IPv4 (CURLOPT_IPRESOLVE) so the AAAA hang is skipped entirely,
     *   - raise the connect-timeout to 30s for slow resolvers,
     *   - keep the 45s overall timeout,
     *   - retry twice on transient DNS/timeout blips.
     *
     * When $pinnedIp is given we ALSO hard-pin the DNS answer with
     * CURLOPT_RESOLVE — this is the escape hatch for hosts whose own DNS
     * resolver can't resolve the CinetPay host at all (cURL error 6). SNI +
     * Host header still carry the real hostname, so TLS verification holds.
     */
    private function http(?string $pinnedIp = null)
    {
        $curl = [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4];
        if ($pinnedIp) {
            $curl[CURLOPT_RESOLVE] = [self::API_HOST . ':443:' . $pinnedIp];
        }

        return Http::asJson()->acceptJson()
            ->connectTimeout(30)
            ->timeout(self::HTTP_TIMEOUT_SECONDS)
            ->retry(2, 500)
            ->withOptions(['curl' => $curl]);
    }

    /**
     * POST to a CinetPay endpoint, transparently working around a broken
     * host DNS resolver. First try the normal client; if it fails to
     * RESOLVE the hostname (cURL 6 "Could not resolve host" or cURL 28
     * "Resolving timed out"), resolve the IP ourselves via public
     * DNS-over-HTTPS and retry once with that IP pinned. Any non-DNS error
     * (real timeout, TLS, HTTP error) is left to bubble up unchanged.
     */
    private function postJson(string $path, array $body)
    {
        $url = self::API_BASE . $path;
        try {
            $r = $this->http()->post($url, $body);
            Log::info('[cinetpay] POST ok', ['path' => $path, 'http' => $r->status()]);
            return $r;
        } catch (ConnectionException $e) {
            Log::warning('[cinetpay] POST connection failed', ['path' => $path, 'error' => $e->getMessage()]);

            if (!$this->isDnsFailure($e->getMessage())) {
                throw $e; // real timeout / TLS / network — not something the DoH pin can fix
            }

            $ip = $this->resolveViaDoh(self::API_HOST);
            if (!$ip) {
                // NXDOMAIN (DNS Status 3) means the hostname does not exist in
                // public DNS at all — CinetPay isn't publishing a record for
                // its own API host. That's a gateway-side outage, not the
                // client's server; say so plainly instead of "could not resolve".
                if ($this->lastDohStatus === 3) {
                    Log::error('[cinetpay] gateway host is NXDOMAIN — CinetPay API DNS record is missing (their side)', ['host' => self::API_HOST, 'path' => $path]);
                    throw new \RuntimeException('CinetPay\'s payment API (' . self::API_HOST . ') is currently unreachable — its DNS record is missing (NXDOMAIN). This is an outage on CinetPay\'s side, not your server. Please retry shortly, and if it persists contact CinetPay support to confirm your account\'s API endpoint.');
                }
                Log::error('[cinetpay] DNS fallback failed — DoH resolvers did not return an IP (see DoH warnings above for HTTP status/body)', ['path' => $path]);
                throw $e;
            }

            Log::warning('[cinetpay] host DNS could not resolve ' . self::API_HOST . ' — retrying with DoH-pinned IP', ['ip' => $ip, 'path' => $path]);
            $r = $this->http($ip)->post($url, $body);
            Log::info('[cinetpay] POST ok via DoH-pinned IP', ['path' => $path, 'http' => $r->status(), 'ip' => $ip]);
            return $r;
        }
    }

    /** True when a ConnectionException message is a DNS-resolution failure. */
    private function isDnsFailure(string $msg): bool
    {
        return str_contains($msg, 'Could not resolve host')
            || str_contains($msg, 'Resolving timed out')
            || str_contains($msg, 'Could not resolve');
    }

    /**
     * Resolve a hostname's A record through public DNS-over-HTTPS.
     *
     * We address each resolver by its REAL hostname (cloudflare-dns.com /
     * dns.google) but PIN that hostname to its well-known anycast IP with
     * CURLOPT_RESOLVE. That's the crucial detail: the server's own DNS is
     * dead, yet outbound 443 works — so pinning gives us a correct Host
     * header + SNI (which the JSON API requires; hitting the bare IP returns
     * an empty body, i.e. the "no A record" we first saw) with no system DNS
     * lookup at all. Returns the first IPv4 or null.
     */
    private function resolveViaDoh(string $host): ?string
    {
        // hostname => [anycast IPs to pin, JSON-API url]
        $resolvers = [
            ['name' => 'cloudflare-dns.com', 'ips' => ['1.1.1.1', '1.0.0.1'], 'url' => 'https://cloudflare-dns.com/dns-query'],
            ['name' => 'dns.google',         'ips' => ['8.8.8.8', '8.8.4.4'], 'url' => 'https://dns.google/resolve'],
        ];

        foreach ($resolvers as $r) {
            try {
                // "host:443:ip1,ip2" — comma list lets cURL fail over between
                // the resolver's anycast IPs (libcurl >= 7.59).
                $pin = $r['name'] . ':443:' . implode(',', $r['ips']);
                $res = Http::acceptJson()
                    ->withHeaders(['Accept' => 'application/dns-json'])
                    ->connectTimeout(10)->timeout(15)
                    ->withOptions(['curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_RESOLVE   => [$pin],
                    ]])
                    ->get($r['url'], ['name' => $host, 'type' => 'A']);

                // Remember the DNS Status (3 == NXDOMAIN) so postJson() can
                // tell "host doesn't exist" apart from "resolver unreachable".
                $dnsStatus = $res->json('Status');
                if (is_int($dnsStatus)) {
                    $this->lastDohStatus = $dnsStatus;
                }

                foreach ((array) $res->json('Answer') as $ans) {
                    $ip = $ans['data'] ?? null;
                    // type 1 == A record; guard against CNAME rows in the answer.
                    if ($ip && ($ans['type'] ?? 1) === 1
                        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        Log::info('[cinetpay] DoH resolved', ['resolver' => $r['name'], 'host' => $host, 'ip' => $ip]);
                        return $ip;
                    }
                }
                Log::warning('[cinetpay] DoH returned no A record', [
                    'resolver'   => $r['name'],
                    'host'       => $host,
                    'http'       => $res->status(),
                    'dns_status' => $dnsStatus,
                    'body'       => mb_substr((string) $res->body(), 0, 300),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[cinetpay] DoH resolver unreachable', ['resolver' => $r['name'], 'error' => $e->getMessage()]);
                // try the next resolver
            }
        }
        return null;
    }

    public static function credentialFields(): array
    {
        return [
            'api_key'    => ['label' => 'API Key',    'type' => 'text',     'required' => true],
            'site_id'    => ['label' => 'Site ID',    'type' => 'text',     'required' => true],
            'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => false, 'hint' => 'For webhook verification.'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        $siteId = (string) $this->cred('site_id');
        if ($apiKey === '' || $siteId === '') return PaymentResult::failed('cinetpay_credentials_missing');

        $email = $order->customer_email ?: optional($order->user)->email;
        if (!$email) return PaymentResult::failed('cinetpay_customer_email_missing');

        // transaction_id must not contain CinetPay's reserved characters
        // (# / $ _ & ,). order_number is safe, but strip anything odd + the
        // '_' separators would technically be reserved, so use '-'.
        $txId = 'CINET-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $order->order_number) . '-' . time();

        $currency = strtoupper($order->currency ?? 'XOF');
        $amount   = (int) round((float) $order->amount);
        // CinetPay rejects franc amounts that aren't a whole multiple of 5.
        if (in_array($currency, self::MULTIPLE_OF_5_CURRENCIES, true) && $amount % 5 !== 0) {
            $amount = (int) (round($amount / 5) * 5);
        }

        // channels=ALL enables card payment, which makes the full billing
        // block MANDATORY (CinetPay returns MINIMUM_REQUIRED_FIELDS / 609
        // otherwise). Fill from the order's billing fields, falling back to
        // safe non-empty placeholders so a card checkout is never blocked.
        $fullName = $order->customer_name ?: (optional($order->user)->name ?? 'Customer');
        $parts    = preg_split('/\s+/', trim($fullName)) ?: [];
        $first    = $parts[0] ?? 'Customer';
        $last     = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Customer';
        $phone    = (string) ($order->user->phone
            ?? $order->user->phone_number
            ?? $order->user->mobile
            ?? '');

        $body = [
            'apikey'                 => $apiKey,
            'site_id'                => $siteId,
            'transaction_id'         => $txId,
            'amount'                 => $amount,
            'currency'               => $currency,
            'description'            => "Order #{$order->order_number}",
            'return_url'             => $callbackUrl,
            'notify_url'             => route('payment.webhook', ['gateway' => 'cinetpay']),
            'channels'               => 'ALL',
            'lang'                   => 'en',
            'metadata'               => json_encode(['order_id' => $order->id, 'order_number' => $order->order_number]),
            // Customer + billing block — required once card is enabled.
            'customer_id'            => (string) $order->id,
            'customer_name'          => $first,
            'customer_surname'       => $last,
            'customer_email'         => $email,
            'customer_phone_number'  => $phone,
            'customer_address'       => $order->billing_address ?: 'N/A',
            'customer_city'          => $order->billing_city    ?: 'N/A',
            'customer_country'       => $order->billing_country ?: 'CI',
            'customer_state'         => $order->billing_country ?: 'CI',
            'customer_zip_code'      => $order->billing_postal   ?: '00000',
        ];

        // Never logs apikey/site_id — only the non-secret request shape.
        Log::info('[cinetpay] initiate', [
            'order'    => $order->order_number,
            'txId'     => $txId,
            'amount'   => $amount,
            'currency' => $currency,
            'host'     => self::API_HOST,
        ]);

        try {
            $r    = $this->postJson('/payment', $body);
            $json = $r->json() ?: [];
            // CinetPay returns code "201" (string) on success.
            if ((string) ($json['code'] ?? '') === '201' && isset($json['data']['payment_url'])) {
                Log::info('[cinetpay] payment link created', ['order' => $order->order_number, 'txId' => $txId]);
                return PaymentResult::redirect($json['data']['payment_url'], $txId, $json);
            }
            // Surface CinetPay's own reason (message + description) so the
            // operator sees "AMOUNT_NOT_MULTIPLE_OF_5" etc. instead of a blank.
            $reason = trim(($json['message'] ?? 'create_failed') . ' ' . ($json['description'] ?? ''));
            Log::warning('[cinetpay] create rejected', [
                'order'       => $order->order_number,
                'txId'        => $txId,
                'http'        => $r->status(),
                'code'        => $json['code'] ?? null,
                'message'     => $json['message'] ?? null,
                'description' => $json['description'] ?? null,
            ]);
            return PaymentResult::failed('cinetpay: ' . $reason);
        } catch (\Throwable $e) {
            Log::error('[cinetpay] initiate exception', [
                'order' => $order->order_number,
                'txId'  => $txId,
                'error' => $e->getMessage(),
            ]);
            return PaymentResult::failed('cinetpay_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $txId = $payload['transaction_id'] ?? $payload['cpm_trans_id'] ?? null;
        if (!$txId) return PaymentResult::failed('missing_cinetpay_tx');

        $apiKey = (string) $this->cred('api_key');
        $siteId = (string) $this->cred('site_id');

        try {
            $r = $this->postJson('/payment/check', [
                'apikey'         => $apiKey,
                'site_id'        => $siteId,
                'transaction_id' => $txId,
            ]);
            $json     = $r->json() ?: [];
            $respCode = $json['code'] ?? '';
            $status   = $json['data']['status'] ?? '';
            Log::info('[cinetpay] check result', [
                'txId'   => $txId,
                'http'   => $r->status(),
                'code'   => $respCode,
                'status' => $status,
            ]);
            if ($respCode !== '00') return PaymentResult::failed("cinetpay_check_code: {$respCode}", $json);
            if ($status === 'ACCEPTED') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $txId,
                    gatewayOrderId:   (string) $txId,
                    payload:          $json,
                );
            }
            return PaymentResult::failed("cinetpay_status: {$status}", $json);
        } catch (\Throwable $e) {
            Log::error('[cinetpay] callback exception', ['txId' => $txId, 'error' => $e->getMessage()]);
            return PaymentResult::failed('cinetpay_callback_exception: ' . $e->getMessage());
        }
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $txId = $payload['cpm_trans_id'] ?? null;
        if (!$txId) return PaymentResult::failed('missing_cinetpay_tx');
        return $this->handleCallback(['transaction_id' => $txId]);
    }
}
