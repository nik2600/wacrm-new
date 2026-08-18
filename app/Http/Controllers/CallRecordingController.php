<?php

namespace App\Http\Controllers;

use App\Models\AiCallLog;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves call recordings to the /call-logs UI. Node writes raw
 * 48 kHz mono Int16 LE PCM to `public/uploads/call-recordings/{call}_{side}.pcm`
 * during the call (so the disk write is hot-path-cheap). On first
 * playback we lazily wrap a WAV header + cache the result, so the
 * operator's <audio> tag gets a playable file in one round-trip.
 *
 * Workspace-scoped: a row's caller is enforced via the parent
 * AiCallLog's workspace_id — operators in other workspaces 404.
 */
class CallRecordingController extends Controller
{
    private const SAMPLE_RATE = 48000;
    private const CHANNELS    = 1;
    private const BITS        = 16;

    /**
     * GET /call-logs/{id}/audio/{side}
     *  side: agent | user | mixed (mixed = both interleaved; falls back to agent if user missing)
     */
    public function audio(int $id, string $side)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $log = AiCallLog::where('workspace_id', $wsId)->findOrFail($id);

        if (!in_array($side, ['agent', 'user', 'mixed'], true)) {
            abort(404);
        }

        // Resolve the underlying call's meta_call_id — that's what
        // Node uses to name the .pcm files.
        $metaCallId = (string) ($log->twilio_call_sid ?: '');
        if ($metaCallId === '') {
            abort(404, 'no recording reference');
        }

        // Node writes the raw .pcm into the public web root, where it is
        // fetchable as a static file (bypassing this workspace-scoped
        // controller). On first authorized playback we migrate the recording
        // media into a NON-public storage dir and serve it only from there, so
        // the sensitive voice PII stops being reachable outside this method.
        $privateDir = storage_path('app/call-recordings');
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0755, true);
        }

        $userPcm  = $this->relocateRecording($metaCallId . '_user.pcm', $privateDir);
        $agentPcm = $this->relocateRecording($metaCallId . '_agent.pcm', $privateDir);

        if ($side === 'user' && !is_file($userPcm))   abort(404, 'user recording missing');
        if ($side === 'agent' && !is_file($agentPcm)) abort(404, 'agent recording missing');

        // For mixed we need both sides; fall back to whichever exists.
        if ($side === 'mixed' && !is_file($userPcm) && !is_file($agentPcm)) {
            abort(404, 'no recording on disk');
        }

        $wavPath = $privateDir . DIRECTORY_SEPARATOR . $metaCallId . '_' . $side . '.wav';
        // Purge any legacy WAV that a previous version built inside the web root.
        $legacyWav = public_path('uploads/call-recordings') . DIRECTORY_SEPARATOR . $metaCallId . '_' . $side . '.wav';
        if (is_file($legacyWav)) @unlink($legacyWav);
        if (!is_file($wavPath)) {
            try {
                if ($side === 'mixed') {
                    $this->writeMixedWav($userPcm, $agentPcm, $wavPath);
                } else {
                    $pcmPath = $side === 'user' ? $userPcm : $agentPcm;
                    $this->writeWav($pcmPath, $wavPath);
                }
            } catch (\Throwable $e) {
                Log::warning('[REC] wav build failed: ' . $e->getMessage());
                abort(500, 'recording build failed');
            }
        }

        return response()->file($wavPath, [
            'Content-Type'        => 'audio/wav',
            'Content-Disposition' => 'inline; filename="' . $metaCallId . '_' . $side . '.wav"',
            'Cache-Control'       => 'private, max-age=86400',
        ]);
    }

    /**
     * Return the private path for a recording file, migrating any copy Node
     * left in the public web root into the non-public storage dir first. This
     * keeps voice PII out of the docroot so it can only be reached through this
     * workspace-scoped controller. Idempotent and best-effort.
     */
    private function relocateRecording(string $filename, string $privateDir): string
    {
        $private = $privateDir . DIRECTORY_SEPARATOR . $filename;
        $public  = public_path('uploads/call-recordings') . DIRECTORY_SEPARATOR . $filename;

        if (is_file($public)) {
            // Prefer @rename (atomic on same volume); if a private copy already
            // exists just drop the exposed public one.
            if (is_file($private)) {
                @unlink($public);
            } elseif (!@rename($public, $private)) {
                // Cross-device or locked file — fall back to copy + unlink.
                if (@copy($public, $private)) {
                    @unlink($public);
                }
            }
        }

        return $private;
    }

    /** Wrap a raw PCM file in a WAV (RIFF) header and dump to disk. */
    private function writeWav(string $pcmPath, string $wavPath): void
    {
        $pcm = file_get_contents($pcmPath);
        if ($pcm === false) {
            throw new \RuntimeException('read pcm failed');
        }
        $wav = $this->wavHeader(strlen($pcm)) . $pcm;
        if (file_put_contents($wavPath, $wav, LOCK_EX) === false) {
            throw new \RuntimeException('write wav failed');
        }
    }

    /**
     * Sample-by-sample mix of user + agent PCM. Both sides are
     * 16-bit signed mono @ 48 kHz, so the average of each pair is
     * the mixed sample. Pads the shorter file with silence so the
     * timeline lines up with `started_at + duration_seconds`.
     */
    private function writeMixedWav(string $userPcm, string $agentPcm, string $wavPath): void
    {
        $uSize = is_file($userPcm)  ? (int) filesize($userPcm)  : 0;
        $aSize = is_file($agentPcm) ? (int) filesize($agentPcm) : 0;
        $len   = max($uSize, $aSize);
        if ($len === 0) throw new \RuntimeException('no pcm to mix');

        // Cap to ~30 min of 48 kHz mono 16-bit so a corrupt/oversized side
        // can't make the mix hang or OOM (that timeout is why the "Full call"
        // player showed 0:00). Even byte count for clean 16-bit samples.
        $maxBytes = 30 * 60 * self::SAMPLE_RATE * (self::BITS / 8);
        $len = (int) min($len, $maxBytes);
        if ($len % 2 === 1) $len--;

        $uf = $uSize ? fopen($userPcm, 'rb')  : null;
        $af = $aSize ? fopen($agentPcm, 'rb') : null;

        // STREAM the mix in 8 KB chunks → bounded memory (vs. loading whole
        // files + a per-sample substr/unpack loop, which timed out on long
        // recordings). Header written last once we know the data length.
        $tmp = $wavPath . '.tmp';
        $out = fopen($tmp, 'wb');
        fwrite($out, str_repeat("\x00", 44)); // header placeholder

        $CHUNK = 8192 * 2; // bytes (8192 samples)
        $done = 0;
        while ($done < $len) {
            $want = (int) min($CHUNK, $len - $done);
            $ub = $uf ? (string) fread($uf, $want) : '';
            $ab = $af ? (string) fread($af, $want) : '';
            $ub = str_pad(substr($ub, 0, $want), $want, "\x00");
            $ab = str_pad(substr($ab, 0, $want), $want, "\x00");
            $us = unpack('s*', $ub);
            $as = unpack('s*', $ab);
            $n  = min(count($us), count($as));
            $mix = [];
            for ($i = 1; $i <= $n; $i++) {
                $m = $us[$i] + $as[$i];
                $mix[] = $m < -32768 ? -32768 : ($m > 32767 ? 32767 : $m);
            }
            if ($mix) fwrite($out, pack('s*', ...$mix));
            $done += $want;
        }
        if ($uf) fclose($uf);
        if ($af) fclose($af);

        $dataLen = ftell($out) - 44;
        fseek($out, 0);
        fwrite($out, $this->wavHeader($dataLen)); // backfill real header
        fclose($out);
        @rename($tmp, $wavPath);
    }

    /** Minimal 44-byte WAV header for our PCM format. */
    private function wavHeader(int $dataLen): string
    {
        $byteRate   = self::SAMPLE_RATE * self::CHANNELS * (self::BITS / 8);
        $blockAlign = self::CHANNELS * (self::BITS / 8);
        return 'RIFF'
            . pack('V', 36 + $dataLen)
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)
            . pack('v', 1)                // PCM
            . pack('v', self::CHANNELS)
            . pack('V', self::SAMPLE_RATE)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', self::BITS)
            . 'data'
            . pack('V', $dataLen);
    }
}
