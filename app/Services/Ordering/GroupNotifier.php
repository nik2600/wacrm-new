<?php

namespace App\Services\Ordering;

use App\Models\WaGroup;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts a message into a WhatsApp GROUP via the Node/Baileys bridge, optionally
 * @-mentioning participants. Unofficial API only — WABA/Cloud can't post to
 * arbitrary groups. Sends through the SAME bot number that's a member of the
 * group (WaGroup.device_phone) so Baileys can deliver it.
 */
class GroupNotifier
{
    private function nodeUrl(): string
    {
        return (string) (SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));
    }

    /**
     * @param  array<int,string> $mentionPhones  digits-only phones to @mention.
     *         The matching `@<digits>` tokens must already be present in $text.
     * @return array{ok: bool, error?: string}
     */
    public function sendToGroup(WaGroup $group, string $text, array $mentionPhones = []): array
    {
        $base = $this->nodeUrl();
        if ($base === '') {
            return ['ok' => false, 'error' => 'SERVER_URL not configured'];
        }
        $from = preg_replace('/\D+/', '', (string) $group->device_phone);
        if ($from === '') {
            return ['ok' => false, 'error' => 'group has no bot device phone — re-sync groups'];
        }

        $mentions = array_values(array_filter(array_map(
            fn ($p) => preg_replace('/\D+/', '', (string) $p),
            $mentionPhones
        )));

        try {
            $res = Http::timeout(20)->acceptJson()->asJson()->post(
                rtrim($base, '/') . '/api/send-group-message/' . urlencode($from),
                [
                    'group_jid' => $group->group_jid,
                    'text'      => $text,
                    'mentions'  => $mentions,
                ]
            );
            if ($res->successful()) {
                return ['ok' => true];
            }
            $err = $res->json('error') ?? $res->json('details') ?? ('HTTP ' . $res->status());
            Log::warning('[GROUP-NOTIFY] send failed', ['group' => $group->group_jid, 'err' => $err]);
            return ['ok' => false, 'error' => is_string($err) ? $err : json_encode($err)];
        } catch (\Throwable $e) {
            Log::warning('[GROUP-NOTIFY] send threw: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convenience: build "@customer" mention text + send. The customer's phone
     * is mentioned at the top, the body follows.
     */
    public function notifyCustomerInGroup(WaGroup $group, string $customerPhone, string $body, ?string $lang = null): array
    {
        // Jessica #1 — post the group @mention in the customer's own language
        // (best-effort; falls back to the original text on any translate failure).
        if ($lang !== null && $lang !== '') {
            try { $body = app(\App\Services\Ordering\OrderingService::class)->localizeTo($body, $lang); }
            catch (\Throwable $e) { /* keep original */ }
        }
        $digits = preg_replace('/\D+/', '', $customerPhone);
        $text = ($digits !== '' ? '@' . $digits . "\n" : '') . $body;
        return $this->sendToGroup($group, $text, $digits !== '' ? [$digits] : []);
    }
}
