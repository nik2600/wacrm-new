<?php

namespace App\Services\Instaflow;

use App\Models\Conversation;
use App\Models\WorkspaceIgAccount;
use Illuminate\Support\Facades\Log;

/**
 * WaDesk PULLS new Instagram DMs from Instaflow into the unified inbox.
 *
 * Why pull (not push): Instaflow's reverse push (WadeskPushService) can only
 * reach a PUBLICLY-addressable WaDesk. When WaDesk runs on a private LAN (dev,
 * or an internal deployment) the hosted Instaflow can't POST to it, so IG DMs
 * would land in Instaflow but never in WaDesk. WaDesk → Instaflow always works
 * (Instaflow is the public party), so we pull instead: list the workspace's IG
 * conversations, fetch messages for the ones with new activity, and ingest the
 * inbound ones via the shared InstaflowIngestService (deduped, so it composes
 * safely with the push path when both are available).
 */
class InstaflowInboundPuller
{
    /**
     * Pull + ingest new inbound IG messages for one workspace.
     *
     * @return int number of NEW inbound messages ingested
     */
    public static function pull(int $wsId, bool $force = false): int
    {
        if ($wsId <= 0) return 0;

        // The IG accounts THIS workspace linked (WaDesk-driven scoping — we ask
        // Instaflow only for these accounts' threads, rather than relying on
        // Instaflow's own wadesk_workspace_id stamp which the link-existing path
        // may not have set). No account → nothing to pull.
        $accountIds = WorkspaceIgAccount::where('workspace_id', $wsId)
            ->pluck('instaflow_account_id')->filter()->map(fn ($v) => (string) $v)->all();
        if (empty($accountIds)) {
            return 0;
        }

        $client = InstaflowClient::fromSettings();
        if (! $client->isConfigured()) {
            return 0;
        }

        // Gather every thread across the workspace's linked IG accounts.
        $convos = [];
        foreach ($accountIds as $acctId) {
            foreach ($client->conversations($acctId) as $co) {   // ?account=<instaflow_account_id>
                $convos[] = $co;
            }
        }
        if (empty($convos)) {
            Log::info('[INSTAFLOW-PULL] no conversations', ['ws' => $wsId, 'accounts' => $accountIds]);
            return 0;
        }

        // Map existing IG threads by raw_jid so we skip fetching messages for
        // conversations that have no new activity since we last stored them.
        $existing = Conversation::where('workspace_id', $wsId)
            ->where('channel', 'instagram')
            ->get(['raw_jid', 'last_message_at'])
            ->keyBy('raw_jid');

        $ingested = 0;
        $scanned  = 0;

        foreach ($convos as $co) {
            $convId = (string) ($co['id'] ?? '');
            if ($convId === '') continue;

            $rawJid   = 'ig:' . $convId;
            $coLastAt = strtotime((string) ($co['last_at'] ?? '')) ?: 0;
            $row      = $existing->get($rawJid);
            $localAt  = $row && $row->last_message_at ? strtotime((string) $row->last_message_at) : 0;

            // Known thread with no newer activity → nothing to do (unless forced,
            // e.g. a one-time backfill of the outbound side we didn't fetch before).
            if (! $force && $row && $coLastAt && $localAt && $coLastAt <= $localAt) {
                continue;
            }

            $scanned++;
            $msgs = $client->messages($convId);
            if (empty($msgs)) continue;

            foreach ($msgs as $m) {
                // Ingest BOTH directions so the WaDesk thread shows the full
                // back-and-forth (the customer's DMs AND the account's own sends:
                // auto-replies, flow sends, Instaflow-side manual replies). Dedup
                // in the ingest service (meta.instagram.message_id OR
                // meta.wa_message_id) means WaDesk-originated replies never double.
                $fromMe = ! empty($m['from_me']);

                $data = [
                    'event'        => 'message',
                    'workspace_id' => $wsId,
                    'account'      => [
                        'id'       => (string) ($co['account'] ?? ''),
                        'username' => (string) ($co['handle'] ?? ''),
                        'name'     => (string) ($co['name'] ?? ''),
                        'avatar'   => (string) ($co['avatar'] ?? ''),
                    ],
                    'conversation' => [
                        'id'     => $convId,
                        'name'   => (string) ($co['name'] ?? ''),
                        'handle' => (string) ($co['handle'] ?? ''),
                        'avatar' => (string) ($co['avatar'] ?? ''),
                    ],
                    'message'      => [
                        'id'        => (string) ($m['id'] ?? ''),
                        'from_me'   => $fromMe,
                        'type'      => (string) ($m['type'] ?? 'text'),
                        'text'      => (string) ($m['text'] ?? ''),
                        'media_url' => (string) ($m['media_url'] ?? ''),
                        // Carry the template's interactive buttons through so the
                        // ingest service stores meta.buttons (new rows) OR backfills
                        // it onto historic plain-text rows (dedup path).
                        'buttons'   => (isset($m['buttons']) && is_array($m['buttons'])) ? $m['buttons'] : null,
                        'at'        => $m['at'] ?? null,
                    ],
                ];

                try {
                    $before = \App\Models\InboxMessage::max('id');
                    $stored = InstaflowIngestService::ingest($wsId, $data);
                    // Count only genuinely-new rows (ingest returns the existing
                    // row on dedup — its id is <= the pre-insert max).
                    if ($stored && $stored->id > (int) $before) $ingested++;
                } catch (\Throwable $e) {
                    Log::warning('[INSTAFLOW-PULL] ingest failed: ' . $e->getMessage(), ['ws' => $wsId, 'conv' => $convId]);
                }
            }
        }

        if ($ingested > 0 || $scanned > 0) {
            Log::info('[INSTAFLOW-PULL] done', ['ws' => $wsId, 'convos' => count($convos), 'scanned' => $scanned, 'ingested' => $ingested]);
        }

        return $ingested;
    }
}
