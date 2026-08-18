<?php

namespace App\Services\Hubspot;

use App\Models\HubspotIntegration;
use App\Models\HubspotIntegrationLog;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HubSpot OAuth + REST helpers. Pattern mirrors ShopifyService — admin
 * credentials live in system_settings, per-integration tokens on the
 * row. Refresh tokens are rotated on every access-token refresh.
 *
 * Default scope set covers the CRM objects we'd typically push from
 * WaDesk: contacts (sync customer records), deals (create from chat),
 * timeline events (log WhatsApp activity on the contact's HubSpot timeline).
 */
class HubspotService
{
    private const HTTP_TIMEOUT_SECONDS = 15;
    public const  DEFAULT_SCOPES = 'crm.objects.contacts.read crm.objects.contacts.write crm.objects.deals.read crm.objects.deals.write';

    public const  API_BASE       = 'https://api.hubapi.com';
    public const  AUTH_BASE      = 'https://app.hubspot.com';
    // v3 token endpoint — the legacy /oauth/v1/token is deprecated by HubSpot.
    // v3 keeps client_id/secret in the body only (never the query string).
    public const  TOKEN_ENDPOINT = '/oauth/v3/token';

    public function clientId(): string     { return (string) SystemSetting::get('hubspot_client_id', ''); }
    public function clientSecret(): string { return (string) SystemSetting::get('hubspot_client_secret', ''); }
    public function scopes(): string       { return (string) SystemSetting::get('hubspot_scopes', self::DEFAULT_SCOPES); }
    public function redirectUri(): string  { return (string) (SystemSetting::get('hubspot_redirect_uri') ?: url('/hubspot/oauth/callback')); }
    public function isEnabled(): bool      { return (bool) SystemSetting::get('hubspot_enabled', false); }

    /**
     * Generate a PKCE (RFC 7636) code_verifier + S256 code_challenge pair.
     * HubSpot apps configured to "require PKCE" reject the /oauth/authorize
     * call with "the code challenge parameter is missing" unless code_challenge
     * + code_challenge_method=S256 are sent, and the token exchange must then
     * carry the matching code_verifier. The verifier is 43 url-safe chars
     * (unreserved set), the challenge is base64url(sha256(verifier)) unpadded.
     */
    public static function generatePkce(): array
    {
        $verifier  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    public function authorizeUrl(string $state, ?string $codeChallenge = null): string
    {
        $params = [
            'client_id'    => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope'        => $this->scopes(),
            'state'        => $state,
        ];
        // PKCE — required by HubSpot apps with "require PKCE" enabled. Harmless
        // to include when the app doesn't require it, so always send when set.
        if ($codeChallenge) {
            $params['code_challenge']        = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        }
        return self::AUTH_BASE . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access + refresh token pair.
     * Returns hydrated portal info on success.
     */
    public function exchangeCode(string $code, ?string $codeVerifier = null): array
    {
        try {
            $body = [
                'grant_type'    => 'authorization_code',
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri'  => $this->redirectUri(),
                'code'          => $code,
            ];
            // PKCE: the verifier must match the challenge sent at /oauth/authorize.
            if ($codeVerifier) $body['code_verifier'] = $codeVerifier;
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . self::TOKEN_ENDPOINT, $body);
            if ($r->successful()) {
                return [
                    'success'       => true,
                    'access_token'  => $r->json('access_token'),
                    'refresh_token' => $r->json('refresh_token'),
                    'expires_in'    => (int) $r->json('expires_in', 0),
                ];
            }
            return ['success' => false, 'error' => $r->json('message') ?: ('HTTP ' . $r->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function refreshAccessToken(HubspotIntegration $integration): bool
    {
        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . self::TOKEN_ENDPOINT, [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'refresh_token' => $integration->refresh_token,
                ]);
            if (!$r->successful()) {
                $integration->update(['status' => 'error']);
                return false;
            }
            $integration->update([
                'access_token'            => $r->json('access_token'),
                'refresh_token'           => $r->json('refresh_token') ?: $integration->refresh_token,
                'access_token_expires_at' => now()->addSeconds((int) $r->json('expires_in', 1800)),
                'status'                  => 'active',
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[HUBSPOT] refresh failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch portal info (hub id + domain + admin email) — used during the
     * initial connect handshake to populate the integration row.
     *
     * Uses /oauth/v1/access-tokens/{token} per current HubSpot docs:
     * returns hub_id, hub_domain, user, scopes, app_id, expires_in.
     * (/integrations/v1/me is the legacy alternative.)
     */
    public function getPortalInfo(string $accessToken): array
    {
        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/oauth/v1/access-tokens/' . urlencode($accessToken));
            if ($r->successful()) {
                return [
                    'portal_id'    => (string) $r->json('hub_id', ''),
                    'portal_name'  => (string) $r->json('hub_domain', ''),
                    'portal_email' => (string) $r->json('user', ''),
                ];
            }
        } catch (\Throwable $e) {}
        return [];
    }

    /**
     * Push a contact + deal to HubSpot for a WaDesk conversation event.
     * Caller decides what to send — typically fired on wa_orders.created
     * or conversations.interested_sku change (#14).
     *
     * The contact step uses `batch/upsert` with `idProperty: email` so
     * the same customer messaging us repeatedly doesn't create N HubSpot
     * contacts. The deal step then associates against that contact's
     * portal ID using HUBSPOT_DEFINED type 3 (deal → contact).
     *
     * Reference: developers.hubspot.com/docs/api-reference/crm-contacts-v3
     *  + /crm/v3/objects/{type}/batch/upsert is the documented dedupe path
     */
    public function pushDeal(HubspotIntegration $integration, array $contactProperties, array $dealProperties): array
    {
        if ($this->shouldRefresh($integration)) $this->refreshAccessToken($integration);
        $token = $integration->access_token;

        try {
            $contactId = '';
            $email = (string) ($contactProperties['email'] ?? '');
            if ($email !== '') {
                // Upsert by email — HubSpot returns 200 (existing) or 201
                // (created) with the object back in `results[]`.
                $upsertResp = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->post(self::API_BASE . '/crm/v3/objects/contacts/batch/upsert', [
                        'inputs' => [[
                            'id'         => $email,
                            'idProperty' => 'email',
                            'properties' => $contactProperties,
                        ]],
                    ]);
                if ($upsertResp->successful()) {
                    $contactId = (string) ($upsertResp->json('results.0.id') ?? '');
                }
            } else {
                // No email — fall back to plain create. The customer
                // will get one HubSpot contact per chat thread; acceptable
                // since email is the only reliable dedupe key in HubSpot.
                $contactResp = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->post(self::API_BASE . '/crm/v3/objects/contacts', [
                        'properties' => $contactProperties,
                    ]);
                if ($contactResp->successful()) {
                    $contactId = (string) $contactResp->json('id', '');
                }
            }

            $dealResp = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/crm/v3/objects/deals', [
                    'properties' => $dealProperties,
                    'associations' => $contactId ? [[
                        'to'    => ['id' => $contactId],
                        'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => 3]],
                    ]] : [],
                ]);

            HubspotIntegrationLog::create([
                'integration_id' => $integration->id,
                'event_type'     => 'deal.created',
                'status'         => $dealResp->successful() ? 'sent' : 'failed',
                'object_id'      => (string) $dealResp->json('id', ''),
                'payload'        => ['contact' => $contactProperties, 'deal' => $dealProperties],
                'response'       => $dealResp->json(),
                'error'          => $dealResp->successful() ? null : ('HTTP ' . $dealResp->status()),
                'created_at'     => now(),
            ]);

            return [
                'ok'         => $dealResp->successful(),
                'contact_id' => $contactId,
                'deal_id'    => (string) $dealResp->json('id', ''),
            ];
        } catch (\Throwable $e) {
            HubspotIntegrationLog::create([
                'integration_id' => $integration->id,
                'event_type'     => 'deal.created',
                'status'         => 'failed',
                'error'          => mb_substr($e->getMessage(), 0, 250),
                'payload'        => ['contact' => $contactProperties, 'deal' => $dealProperties],
                'created_at'     => now(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Patch an existing deal — used to advance the deal stage when a
     * WaDesk order moves (new → paid → shipped → …). This is the piece
     * most competitors don't do: the deal keeps pace with the order.
     */
    public function updateDeal(HubspotIntegration $integration, string $dealId, array $dealProperties): array
    {
        if ($dealId === '') return ['ok' => false, 'error' => 'No deal id'];
        if ($this->shouldRefresh($integration)) $this->refreshAccessToken($integration);

        try {
            $resp = Http::withToken($integration->access_token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->patch(self::API_BASE . '/crm/v3/objects/deals/' . urlencode($dealId), [
                    'properties' => $dealProperties,
                ]);

            HubspotIntegrationLog::create([
                'integration_id' => $integration->id,
                'event_type'     => 'deal.updated',
                'status'         => $resp->successful() ? 'sent' : 'failed',
                'object_id'      => $dealId,
                'payload'        => ['deal' => $dealProperties],
                'response'       => $resp->json(),
                'error'          => $resp->successful() ? null : ('HTTP ' . $resp->status()),
                'created_at'     => now(),
            ]);

            return ['ok' => $resp->successful(), 'deal_id' => $dealId];
        } catch (\Throwable $e) {
            HubspotIntegrationLog::create([
                'integration_id' => $integration->id,
                'event_type'     => 'deal.updated',
                'status'         => 'failed',
                'object_id'      => $dealId,
                'error'          => mb_substr($e->getMessage(), 0, 250),
                'created_at'     => now(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function shouldRefresh(HubspotIntegration $integration): bool
    {
        if (!$integration->access_token_expires_at) return false;
        // Refresh 5min before expiry so we don't race an expiring token.
        return $integration->access_token_expires_at->lt(now()->addMinutes(5));
    }
}
