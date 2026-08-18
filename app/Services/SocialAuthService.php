<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SDK-free social sign-in (Google + Facebook). Mirrors the raw-OAuth
 * pattern already used by ShopifyService / HubspotService — admin keys
 * live in system_settings, no laravel/socialite dependency, so it works
 * on any CodeCanyon install without composer access.
 *
 * Endpoints verified 2026:
 *   Google   → accounts.google.com/o/oauth2/v2/auth, oauth2.googleapis.com/token,
 *              openidconnect.googleapis.com/v1/userinfo (scope: openid email profile)
 *   Facebook → facebook.com/v19.0/dialog/oauth, graph.facebook.com/v19.0/oauth/access_token,
 *              graph.facebook.com/v19.0/me?fields=id,name,email,picture
 */
class SocialAuthService
{
    private const TIMEOUT = 15;
    public const PROVIDERS = ['google', 'facebook'];
    private const FB_VERSION = 'v19.0';

    public function isProvider(string $p): bool { return in_array($p, self::PROVIDERS, true); }

    /** A provider is usable only when the admin enabled it AND set both keys. */
    public function enabled(string $provider): bool
    {
        return (bool) SystemSetting::get("social_{$provider}_enabled", false)
            && $this->clientId($provider) !== ''
            && $this->clientSecret($provider) !== '';
    }

    /** Any provider live? Drives whether the auth pages show the social block. */
    public function anyEnabled(): bool
    {
        foreach (self::PROVIDERS as $p) {
            if ($this->enabled($p)) return true;
        }
        return false;
    }

    public function clientId(string $p): string     { return trim((string) SystemSetting::get("social_{$p}_client_id", '')); }
    public function clientSecret(string $p): string { return trim((string) SystemSetting::get("social_{$p}_client_secret", '')); }
    public function redirectUri(string $p): string  { return url('/auth/' . $p . '/callback'); }

    /** The URL we send the browser to start the consent flow. */
    public function authorizeUrl(string $provider, string $state): string
    {
        if ($provider === 'google') {
            return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id'     => $this->clientId('google'),
                'redirect_uri'  => $this->redirectUri('google'),
                'response_type' => 'code',
                'scope'         => 'openid email profile',
                'state'         => $state,
                'access_type'   => 'online',
                'prompt'        => 'select_account',
            ]);
        }
        // facebook
        return 'https://www.facebook.com/' . self::FB_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id'     => $this->clientId('facebook'),
            'redirect_uri'  => $this->redirectUri('facebook'),
            'response_type' => 'code',
            'scope'         => 'email,public_profile',
            'state'         => $state,
        ]);
    }

    /**
     * Exchange the auth code and fetch the profile. Returns a normalized
     * ['provider','id','email','name','avatar'] or null on any failure.
     */
    public function fetchUser(string $provider, string $code): ?array
    {
        try {
            return $provider === 'google'
                ? $this->fetchGoogle($code)
                : $this->fetchFacebook($code);
        } catch (\Throwable $e) {
            Log::warning("[SOCIAL] {$provider} fetch failed: " . $e->getMessage());
            return null;
        }
    }

    private function fetchGoogle(string $code): ?array
    {
        $tok = Http::asForm()->timeout(self::TIMEOUT)->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->clientId('google'),
            'client_secret' => $this->clientSecret('google'),
            'redirect_uri'  => $this->redirectUri('google'),
            'grant_type'    => 'authorization_code',
        ]);
        if (!$tok->successful() || !$tok->json('access_token')) return null;

        $u = Http::withToken($tok->json('access_token'))->timeout(self::TIMEOUT)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        if (!$u->successful() || !$u->json('sub')) return null;

        return [
            'provider' => 'google',
            'id'       => (string) $u->json('sub'),
            'email'    => (string) ($u->json('email') ?? ''),
            'name'     => (string) ($u->json('name') ?: $u->json('given_name') ?: 'Google user'),
            'avatar'   => (string) ($u->json('picture') ?? ''),
        ];
    }

    private function fetchFacebook(string $code): ?array
    {
        $tok = Http::timeout(self::TIMEOUT)->get('https://graph.facebook.com/' . self::FB_VERSION . '/oauth/access_token', [
            'client_id'     => $this->clientId('facebook'),
            'client_secret' => $this->clientSecret('facebook'),
            'redirect_uri'  => $this->redirectUri('facebook'),
            'code'          => $code,
        ]);
        if (!$tok->successful() || !$tok->json('access_token')) return null;

        $u = Http::timeout(self::TIMEOUT)->get('https://graph.facebook.com/' . self::FB_VERSION . '/me', [
            'fields'       => 'id,name,email,picture.width(256)',
            'access_token' => $tok->json('access_token'),
        ]);
        if (!$u->successful() || !$u->json('id')) return null;

        return [
            'provider' => 'facebook',
            'id'       => (string) $u->json('id'),
            'email'    => (string) ($u->json('email') ?? ''),   // may be absent — caller handles
            'name'     => (string) ($u->json('name') ?: 'Facebook user'),
            'avatar'   => (string) ($u->json('picture.data.url') ?? ''),
        ];
    }
}
