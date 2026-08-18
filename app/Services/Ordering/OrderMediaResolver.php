<?php

namespace App\Services\Ordering;

use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\Workspace;
use App\Services\Voice\Drivers\WhisperAsrDriver;
use Illuminate\Support\Facades\Log;

/**
 * Jessica #3 — voice + picture ordering.
 *
 * The flow engine only ever hands the order endpoints the customer's TYPED
 * text ({{answer}}). When a customer instead sends a VOICE note ("I want two
 * chicken wings") or a PHOTO of the product they want, that text is empty or a
 * placeholder, so the AI extractor has nothing to work with.
 *
 * The inbound pipeline (WaInboundController) has ALREADY downloaded that media
 * and saved it on the matching conversation's latest inbound InboxMessage row
 * (media_path + media_type). This resolver finds that row for a given
 * (workspace, customer phone) and turns it into something the AI can use:
 *   - image  → {mime, b64}  attached to the vision model (it "sees" the photo)
 *   - audio  → transcript   (Whisper ASR, cached on the row so a retry/2nd
 *              read never re-bills) used as the order text (it "hears" it)
 *
 * No Node changes are needed — we read the same on-disk media the inbox and
 * the existing AI image-vision / voice-note pipelines already use.
 */
class OrderMediaResolver
{
    /** media younger than this (seconds) counts as "this turn's" attachment. */
    private const FRESH_WINDOW_SECONDS = 180;

    /** raw image base64 cap — matches AiAgentService (Anthropic ~5MB tightest). */
    private const MAX_IMAGE_BYTES = 4_000_000;

    /**
     * @return array{kind:?string, image:?array{mime:string,b64:string}, voice_text:?string, lang:?string, path:?string}
     *         kind = 'image' | 'voice' | null. `path` is the on-disk media path
     *         (relative to the media disk) so the order can keep the original
     *         voice note / photo for the merchant to replay later.
     */
    public function resolve(int $wsId, string $customerPhone, bool $waitForRace = false): array
    {
        $none = ['kind' => null, 'image' => null, 'voice_text' => null, 'lang' => null, 'path' => null];

        $convoIds = $this->conversationIdsFor($wsId, $customerPhone);
        if (empty($convoIds)) return $none;

        // The customer's MOST RECENT inbound media. A short retry covers the
        // race where the flow resumes a hair before the inbound forward has
        // finished persisting the media row (pure voice/photo with no caption).
        $msg = $this->latestInboundMedia($convoIds);
        if (!$msg && $waitForRace) {
            usleep(800_000); // 0.8s
            $msg = $this->latestInboundMedia($convoIds);
        }
        if (!$msg) return $none;

        $type = strtolower((string) $msg->media_type);

        if ($type === 'image') {
            $img = $this->loadImage((string) $msg->media_path);
            if ($img) {
                Log::info('[ORDER-FLOW] 2 · media resolved', ['kind' => 'image', 'msg_id' => $msg->id]);
                return ['kind' => 'image', 'image' => $img, 'voice_text' => null, 'lang' => null, 'path' => (string) $msg->media_path];
            }
            return $none;
        }

        if (in_array($type, ['audio', 'voice', 'ptt'], true)) {
            // Re-use a cached transcript when the voice-note AI pipeline (or a
            // prior order parse) already transcribed this exact message.
            $cached = trim((string) $msg->voice_transcript);
            if ($cached !== '') {
                return ['kind' => 'voice', 'image' => null, 'voice_text' => $cached, 'lang' => $msg->voice_transcript_lang, 'path' => (string) $msg->media_path];
            }
            $t = $this->transcribe($wsId, (string) $msg->media_path);
            if ($t && trim($t['text']) !== '') {
                // Cache on the row so the inbox shows it + we never re-bill ASR.
                try {
                    $msg->forceFill([
                        'voice_transcript'      => $t['text'],
                        'voice_transcript_lang' => $t['lang'],
                    ])->save();
                } catch (\Throwable $e) {
                    // caching is best-effort
                }
                Log::info('[ORDER-FLOW] 2 · media resolved', ['kind' => 'voice', 'msg_id' => $msg->id, 'chars' => mb_strlen($t['text'])]);
                return ['kind' => 'voice', 'image' => null, 'voice_text' => $t['text'], 'lang' => $t['lang'], 'path' => (string) $msg->media_path];
            }
            return $none;
        }

        return $none;
    }

    /** Conversation ids in this workspace that belong to the customer's phone. */
    private function conversationIdsFor(int $wsId, string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return [];

        $candidates = [
            $digits . '@s.whatsapp.net',
            $digits . '@lid',
            $digits . '@c.us',
        ];

        return Conversation::query()
            ->where('workspace_id', $wsId)
            ->where(function ($q) use ($candidates, $digits) {
                $q->whereIn('raw_jid', $candidates)
                  ->orWhereIn('alt_jid', $candidates)
                  ->orWhere('title', 'like', '%' . $digits . '%');
            })
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('id')
            ->all();
    }

    private function latestInboundMedia(array $convoIds): ?InboxMessage
    {
        $msg = InboxMessage::query()
            ->whereIn('conversation_id', $convoIds)
            ->where('direction', 'in')
            ->whereIn('media_type', ['image', 'audio', 'voice', 'ptt'])
            ->whereNotNull('media_path')
            ->where('created_at', '>=', now()->subSeconds(self::FRESH_WINDOW_SECONDS))
            ->orderByDesc('id')
            ->first();
        if (!$msg) return null;

        // Only treat the media as THIS turn's order/address if it's the
        // customer's latest inbound — bail if a newer text/media has since
        // arrived (mirrors AiAgentService::resolveInboundImage). Stops a stale
        // photo from an earlier turn attaching itself to a later typed order.
        $hasNewer = InboxMessage::query()
            ->whereIn('conversation_id', $convoIds)
            ->where('direction', 'in')
            ->where('id', '>', $msg->id)
            ->exists();

        return $hasNewer ? null : $msg;
    }

    /** @return array{mime:string,b64:string}|null */
    private function loadImage(string $path): ?array
    {
        $path = ltrim($path, '/\\');
        if ($path === '') return null;
        $disk = media_storage();
        try {
            if (!$disk->exists($path)) return null;
            $size = $disk->size($path);
            if ($size === null || $size <= 0 || $size > self::MAX_IMAGE_BYTES) return null;
            $bytes = $disk->get($path);
        } catch (\Throwable $e) {
            return null;
        }
        if ($bytes === null || $bytes === '') return null;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };
        return ['mime' => $mime, 'b64' => base64_encode($bytes)];
    }

    /**
     * Whisper ASR on the inbound audio. Reads bytes through the active media
     * disk (works whether media is local or on cloud storage) into a scratch
     * file, since Whisper needs a real file handle.
     *
     * @return array{text:string,lang:?string}|null
     */
    private function transcribe(int $wsId, string $path): ?array
    {
        $path = ltrim($path, '/\\');
        if ($path === '') return null;

        $disk = media_storage();
        $tmp  = null;
        try {
            if (!$disk->exists($path)) return null;
            $bytes = $disk->get($path);
            if ($bytes === null || $bytes === '') return null;

            // Whisper picks the audio format from the filename EXTENSION and
            // only accepts a fixed set. WhatsApp voice notes are ogg/opus, but
            // our stored extension can be a mangled mime slug (e.g. "oggcod"
            // from "audio/ogg; codecs=opus") which Whisper 400s as "Invalid file
            // format". Force a supported extension — the bytes ARE ogg.
            $rawExt = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'ogg');
            $ext = in_array($rawExt, ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'], true) ? $rawExt : 'ogg';
            $tmp = tempnam(sys_get_temp_dir(), 'ord_asr_') . '.' . $ext;
            file_put_contents($tmp, $bytes);

            $workspace = $wsId > 0 ? Workspace::find($wsId) : null;
            $driver = new WhisperAsrDriver(workspace: $workspace);
            $res = $driver->transcribe($tmp, null); // null lang → Whisper auto-detects

            return ['text' => (string) $res->text, 'lang' => $res->language];
        } catch (\Throwable $e) {
            Log::warning('[ORDER-FLOW] 2 · ASR failed: ' . $e->getMessage());
            return null;
        } finally {
            if ($tmp && is_file($tmp)) @unlink($tmp);
        }
    }
}
