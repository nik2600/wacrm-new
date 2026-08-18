<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;

/**
 * Thin Trello REST client (key+token auth) for the bits the integration
 * needs: validate the token, resolve a board, register/delete the board
 * webhook, and look up a member. All calls are best-effort and return
 * null / an ok-shaped array rather than throwing.
 */
class TrelloService
{
    private const BASE = 'https://api.trello.com/1';

    public function me(string $key, string $token): ?array
    {
        return $this->get('/members/me', $key, $token, ['fields' => 'id,username,fullName']);
    }

    /** $ref may be a 24-hex board id OR a board shortLink. */
    public function board(string $key, string $token, string $ref): ?array
    {
        return $this->get('/boards/' . rawurlencode($ref), $key, $token, ['fields' => 'id,name,url']);
    }

    public function member(string $key, string $token, string $memberId): ?array
    {
        return $this->get('/members/' . rawurlencode($memberId), $key, $token, ['fields' => 'id,username,fullName']);
    }

    public function registerWebhook(string $key, string $token, string $boardId, string $callbackUrl, string $desc = 'WaDesk'): array
    {
        try {
            $r = Http::asForm()->acceptJson()->timeout(20)->post(self::BASE . '/webhooks/', [
                'key'         => $key,
                'token'       => $token,
                'callbackURL' => $callbackUrl,
                'idModel'     => $boardId,
                'description' => $desc,
            ]);
            if ($r->successful()) {
                return ['ok' => true, 'id' => (string) $r->json('id')];
            }
            return ['ok' => false, 'error' => (string) ($r->json('message') ?? $r->body())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteWebhook(string $key, string $token, string $webhookId): void
    {
        try {
            Http::acceptJson()->timeout(15)->delete(self::BASE . '/webhooks/' . rawurlencode($webhookId) . '?key=' . urlencode($key) . '&token=' . urlencode($token));
        } catch (\Throwable $e) { /* best-effort */ }
    }

    private function get(string $path, string $key, string $token, array $params = []): ?array
    {
        try {
            $r = Http::acceptJson()->timeout(15)->get(self::BASE . $path, array_merge($params, ['key' => $key, 'token' => $token]));
            return $r->successful() ? $r->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
