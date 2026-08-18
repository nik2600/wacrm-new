<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recovers a WABA config's MISSING `waba_id`.
 *
 * Why this exists: template SENDING (TemplateSender) needs only access_token +
 * phone_number_id, but template SYNC/import (TemplateClient) hits
 * /{waba_id}/message_templates and so needs the waba_id. A number connected by
 * an older / partial flow can carry the token + phone_number_id (sends fine) yet
 * lack the waba_id, which makes "Sync from Meta" fail with
 * "WABA config is missing access_token or waba_id" even though outbound works.
 *
 * Meta exposes no phone_number_id → WABA field. But the connect flows also store
 * the owning `business_id`, and from that we CAN list the business's WABAs
 * (owned + client) and match the one whose phone_numbers include our
 * phone_number_id — then backfill meta_json.waba_id so every future WABA op has
 * it. Entirely best-effort: any failure returns null and the caller falls back
 * to its normal "reconnect the number" error.
 */
class WabaIdBackfiller
{
    /**
     * Return the config's waba_id — stored if present, else derived from Meta
     * and persisted onto meta_json. null when it cannot be resolved.
     */
    public static function resolve(WaProviderConfig $cfg): ?string
    {
        $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $creds = $cfg->creds();

        $existing = (string) ($meta['waba_id'] ?? $creds['waba_id'] ?? '');
        if ($existing !== '') return $existing;

        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) ($meta['phone_number_id'] ?? $creds['phone_number_id'] ?? '');
        $bizId   = (string) ($meta['business_id'] ?? $creds['business_id'] ?? '');
        // Need the token + the number to match against, and the business to list
        // its WABAs. Without any of them there's nothing to derive from.
        if ($token === '' || $phoneId === '' || $bizId === '') return null;

        $gv   = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base = 'https://graph.facebook.com/' . ltrim($gv, '/');

        try {
            // 1) Every WABA the business owns or is a client of.
            $wabaIds = [];
            foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                $res = Http::withToken($token)->timeout(12)
                    ->get("{$base}/{$bizId}/{$edge}", ['fields' => 'id', 'limit' => 100]);
                if ($res->successful()) {
                    foreach ((array) $res->json('data', []) as $row) {
                        if (!empty($row['id'])) $wabaIds[] = (string) $row['id'];
                    }
                }
            }
            $wabaIds = array_values(array_unique($wabaIds));
            if (empty($wabaIds)) return null;

            // 2) The WABA whose phone_numbers include OUR phone_number_id. Always
            //    verify by phone even when there's a single WABA — a business can
            //    own several, and picking the wrong one would sync the wrong
            //    template set.
            $match = null;
            foreach ($wabaIds as $wid) {
                $pn = Http::withToken($token)->timeout(12)
                    ->get("{$base}/{$wid}/phone_numbers", ['fields' => 'id', 'limit' => 100]);
                if (! $pn->successful()) continue;
                foreach ((array) $pn->json('data', []) as $row) {
                    if ((string) ($row['id'] ?? '') === $phoneId) { $match = $wid; break 2; }
                }
            }
            if (! $match) return null;

            // 3) Persist so every future WABA op (sync, delete, submit) has it.
            $cfg->meta_json = array_merge($meta, ['waba_id' => $match]);
            $cfg->save();
            Log::info('[WABA-BACKFILL] recovered waba_id from business', [
                'config_id' => $cfg->id, 'waba_id' => $match, 'phone_number_id' => $phoneId,
            ]);
            return $match;
        } catch (\Throwable $e) {
            Log::warning('[WABA-BACKFILL] failed: ' . $e->getMessage(), ['config_id' => $cfg->id]);
            return null;
        }
    }
}
