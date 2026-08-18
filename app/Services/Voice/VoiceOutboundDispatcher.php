<?php

namespace App\Services\Voice;

use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Services\InboxDispatcher;
use App\Services\Voice\Dto\TtsResult;
use Illuminate\Support\Facades\Log;

/**
 * Channel-agnostic outbound for AI voice-note replies.
 *
 * Why this is small: InboxDispatcher already knows how to send an
 * audio message via Baileys (when meta.ptt is true it tells the Node
 * bridge to use ptt + audio/ogg codecs) AND via WABA (auto-renders as
 * voice note for Opus). So this dispatcher just:
 *
 *   1. Mints an outbound InboxMessage row pointing at the TTS file.
 *   2. Sets meta.ptt = true so InboxDispatcher routes it as a voice note.
 *   3. Calls InboxDispatcher::send() which does the actual transport.
 *   4. Returns the row so the caller can correlate (ai_reply_id link).
 *
 * The reply text goes on `body` so the inbox UI can render a readable
 * transcript next to the play button — same shape that text replies use.
 */
class VoiceOutboundDispatcher
{
    public function __construct(private readonly InboxDispatcher $inbox) {}

    public function send(Conversation $convo, AiAgent $agent, string $replyText, TtsResult $tts, ?string $toHint = null): ?InboxMessage
    {
        // Media paths are stored RELATIVE to storage/app/public/ since
        // InboxDispatcher::buildNodeRequest resolves them via
        // storage_path('app/public/' . media_path). The TTS drivers now
        // write to that same disk so the prefix stripping just trims
        // the storage_path('app/public/') head off.
        $storagePrefix = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
        $relative = str_starts_with($tts->localPath, $storagePrefix)
            ? substr($tts->localPath, strlen($storagePrefix))
            : $tts->localPath;
        $relative = str_replace('\\', '/', $relative);

        // Resolve the recipient. This dispatcher creates the outbound row
        // DIRECTLY (not via TeamInboxController::reply), so it must populate
        // to_number itself — InboxDispatcher builds the Node/WABA payload from
        // $msg->to_number with NO fallback, so a missing value shipped the
        // voice note to a null recipient (the "AI voice reply never arrives /
        // behaves inconsistently" bug; text replies worked only because reply()
        // sets to_number). Digits come from the conversation's canonical JID,
        // falling back to the customer's last inbound number. Carry the raw JID
        // in meta.target_jid too so LID-routed Baileys chats send to the real
        // JID instead of a fabricated phone.
        $rawJid   = (string) ($convo->raw_jid ?? '');
        // Recipient precedence: an explicit hint from the caller (e.g. the
        // missed-call number the AiFallback passes) → the conversation's JID
        // → the customer's last inbound number. The hint is first because a
        // freshly-minted missed-call conversation may have no raw_jid and no
        // prior inbound message yet — which is exactly why the voicemail was
        // shipping to a null recipient ("The parameter to is required").
        $toNumber = ($toHint ? (preg_replace('/\D+/', '', $toHint) ?: null) : null)
            ?: (preg_replace('/\D+/', '', $rawJid) ?: null);
        if (!$toNumber) {
            $lastIn = InboxMessage::query()
                ->where('conversation_id', $convo->id)
                ->where('direction', 'in')
                ->orderByDesc('id')
                ->value('from_number');
            if ($lastIn) $toNumber = preg_replace('/\D+/', '', (string) $lastIn) ?: null;
        }
        // Carry a JID for LID-routed Baileys chats. Prefer the conversation's
        // real JID; fall back to the resolved number so a minted missed-call
        // conversation still ships to a valid target.
        $targetJid = $rawJid !== '' ? $rawJid : ($toNumber ? $toNumber . '@s.whatsapp.net' : null);

        $outbound = InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => null,
            'agent_id'        => $agent->id,
            'contact_id'      => $convo->contact_id,
            'direction'       => 'out',
            'to_number'       => $toNumber,
            'body'            => $replyText, // shown next to the play button
            'media_path'      => $relative,
            'media_type'      => 'audio',
            // 'pending' is the only pre-send status the InboxMessage
            // enum allows. 'queued' was wrong — InboxMessage::STATUSES
            // is [pending, sent, delivered, read, failed].
            'status'          => 'pending',
            'meta'            => array_filter([
                'ai_voice_reply' => true,
                'ptt'            => true,       // tells InboxDispatcher this is a voice note
                'target_jid'     => $targetJid,
                'agent_id'       => $agent->id,
                'voice_provider' => $agent->voice_provider,
                'voice_id'       => $agent->voice_id,
                'mimetype'       => $tts->mimetype,
                'char_count'     => $tts->charCount,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);

        try {
            $result = $this->inbox->send($outbound->fresh(), 'W');
            $patch = [
                'status' => $result['ok'] ? 'sent' : 'failed',
            ];
            if ($result['ok']) {
                $patch['sent_at'] = now();
                if (!empty($result['provider_id'])) $patch['meta'] = array_merge(
                    is_array($outbound->meta) ? $outbound->meta : [],
                    ['provider_id' => $result['provider_id']],
                );
            } else {
                $patch['failure_reason'] = (string) ($result['error'] ?? 'dispatcher failed');
            }
            $outbound->forceFill($patch)->save();
        } catch (\Throwable $e) {
            Log::warning('[VOICE-AI] outbound dispatch threw: ' . $e->getMessage(), [
                'msg_id'     => $outbound->id,
                'convo_id'   => $convo->id,
                'agent_id'   => $agent->id,
            ]);
            $outbound->forceFill([
                'status'         => 'failed',
                'failure_reason' => $e->getMessage(),
            ])->save();
        }

        return $outbound;
    }
}
