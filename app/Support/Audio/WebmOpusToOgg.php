<?php

namespace App\Support\Audio;

/**
 * Pure-PHP WebM/Opus → Ogg/Opus REMUX. No ffmpeg, no external binary, no
 * re-encoding.
 *
 * Browser MediaRecorder (Chrome/Edge) records voice notes as WebM/Opus.
 * WhatsApp Cloud API only accepts audio as aac/amr/mp3/mp4/ogg-opus — it
 * rejects WebM during async delivery (the "sent but never received" bug).
 *
 * The key insight: WebM and Ogg both wrap the SAME Opus codec stream. The
 * only difference is the CONTAINER (WebM = Matroska/EBML, Ogg = Ogg pages).
 * So we don't transcode — we DEMUX the raw Opus packets out of the WebM
 * container and REMUX them into an Ogg container. Lossless, fast, dependency
 * free. The result is a real ogg/opus file WhatsApp accepts → voice-note
 * bubble.
 *
 * Returns the ogg bytes, or null if the input isn't parseable WebM/Opus
 * (caller then tries ffmpeg or fails loudly — never a silent drop).
 */
class WebmOpusToOgg
{
    /** Opus frame size per TOC config (0-31), in samples @48kHz. */
    private const FRAME_SIZES = [
        // SILK NB/MB/WB (0-11): 10/20/40/60 ms
        480, 960, 1920, 2880,
        480, 960, 1920, 2880,
        480, 960, 1920, 2880,
        // Hybrid SWB/FB (12-15): 10/20 ms
        480, 960, 480, 960,
        // CELT NB/WB/SWB/FB (16-31): 2.5/5/10/20 ms
        120, 240, 480, 960,
        120, 240, 480, 960,
        120, 240, 480, 960,
        120, 240, 480, 960,
    ];

    /** Matroska master-element IDs we descend into. */
    private const MASTERS = [
        0x18538067, // Segment
        0x1654AE6B, // Tracks
        0xAE,       // TrackEntry
        0x1F43B675, // Cluster
        0xA0,       // BlockGroup
    ];

    private int $opusTrack = -1;
    private ?string $opusHead = null;
    /** @var list<string> ordered Opus packets */
    private array $packets = [];

    public static function convert(string $webm): ?string
    {
        try {
            $self = new self();
            $self->parse($webm, 0, strlen($webm));
            if (empty($self->packets)) {
                return null;
            }
            return $self->buildOgg();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[WebmOpusToOgg] remux failed: ' . $e->getMessage());
            return null;
        }
    }

    // ── Matroska/EBML demux ────────────────────────────────────────────

    private function parse(string $b, int $start, int $end): void
    {
        $pos = $start;
        while ($pos < $end) {
            if ($pos + 1 > strlen($b)) break;
            [$id, $p1] = $this->readId($b, $pos);
            if ($p1 >= $end || $p1 > strlen($b)) break;
            [$size, $p2, $unknown] = $this->readSize($b, $p1);
            $dataStart = $p2;
            $dataEnd   = $unknown ? $end : min($dataStart + $size, $end);
            if ($dataEnd < $dataStart) break;

            if ($id === 0xAE) {                       // TrackEntry
                $this->handleTrackEntry($b, $dataStart, $dataEnd);
            } elseif ($id === 0xA3 || $id === 0xA1) { // SimpleBlock / Block
                $this->handleBlock($b, $dataStart, $dataEnd);
            } elseif (in_array($id, self::MASTERS, true)) {
                $this->parse($b, $dataStart, $dataEnd);
            }
            // else: a leaf we don't care about — skip.

            if ($dataEnd <= $pos) break; // strict-advance guard (no infinite loop)
            $pos = $dataEnd;
        }
    }

    private function handleTrackEntry(string $b, int $s, int $e): void
    {
        $num = -1; $type = -1; $codec = ''; $priv = null;
        $pos = $s;
        while ($pos < $e) {
            [$id, $p1] = $this->readId($b, $pos);
            if ($p1 >= $e) break;
            [$size, $p2, $unk] = $this->readSize($b, $p1);
            $ds = $p2;
            $de = $unk ? $e : min($ds + $size, $e);
            switch ($id) {
                case 0xD7:   $num   = $this->readUint($b, $ds, $de);  break; // TrackNumber
                case 0x83:   $type  = $this->readUint($b, $ds, $de);  break; // TrackType (2=audio)
                case 0x86:   $codec = substr($b, $ds, $de - $ds);     break; // CodecID
                case 0x63A2: $priv  = substr($b, $ds, $de - $ds);     break; // CodecPrivate (OpusHead)
            }
            if ($de <= $pos) break;
            $pos = $de;
        }
        if ($type === 2 && stripos($codec, 'OPUS') !== false) {
            $this->opusTrack = $num;
            $this->opusHead  = $priv;
        }
    }

    private function handleBlock(string $b, int $s, int $e): void
    {
        $pos = $s;
        [$track, $pos] = $this->readVint($b, $pos); // block track number (vint)
        $pos += 2;                                  // int16 relative timecode
        if ($pos >= $e) return;
        $flags = ord($b[$pos]); $pos++;
        if ($track !== $this->opusTrack) return;

        $lacing = ($flags >> 1) & 0x03; // 0 none, 1 Xiph, 2 fixed, 3 EBML
        if ($lacing === 0) {
            // The common MediaRecorder case — one Opus packet per block.
            $this->packets[] = substr($b, $pos, $e - $pos);
            return;
        }

        if ($pos >= $e) return;
        $frames = ord($b[$pos]) + 1; $pos++;
        $sizes  = [];
        if ($lacing === 2) {                    // fixed lacing
            $each = intdiv($e - $pos, max(1, $frames));
            for ($i = 0; $i < $frames; $i++) $sizes[] = $each;
        } elseif ($lacing === 1) {              // Xiph lacing
            for ($i = 0; $i < $frames - 1; $i++) {
                $sz = 0;
                do { $x = ord($b[$pos++]); $sz += $x; } while ($x === 255 && $pos < $e);
                $sizes[] = $sz;
            }
            $sizes[] = ($e - $pos) - array_sum($sizes);
        } else {                                // EBML lacing (rare)
            [$first, $pos] = $this->readVint($b, $pos);
            $sizes[] = $first;
            for ($i = 1; $i < $frames - 1; $i++) {
                [$delta, $pos] = $this->readVint($b, $pos);
                $bias = (1 << (7 * 1 - 1)) - 1; // best-effort; MediaRecorder never uses this
                $sizes[] = $sizes[$i - 1] + ($delta - $bias);
            }
            if ($frames > 1) $sizes[] = ($e - $pos) - array_sum($sizes);
        }
        foreach ($sizes as $sz) {
            if ($sz <= 0 || $pos + $sz > $e) break;
            $this->packets[] = substr($b, $pos, $sz);
            $pos += $sz;
        }
    }

    // ── Ogg remux ──────────────────────────────────────────────────────

    private function buildOgg(): string
    {
        $serial = 0x57614400;            // arbitrary stream serial ("Wa\0\0")
        $seq    = 0;
        $out    = '';

        // Page 0 — OpusHead (Beginning-Of-Stream). Use the WebM CodecPrivate
        // (which IS an OpusHead) when present; synthesise a sane default
        // otherwise.
        $head = ($this->opusHead !== null && str_starts_with((string) $this->opusHead, 'OpusHead'))
            ? $this->opusHead
            : $this->defaultOpusHead();
        $out .= $this->oggPage($serial, $seq++, 0x02, 0, [$head]);

        // Page 1 — OpusTags (comment header).
        $tags = 'OpusTags' . pack('V', 6) . 'WaDesk' . pack('V', 0);
        $out .= $this->oggPage($serial, $seq++, 0x00, 0, [$tags]);

        // Audio pages — up to 50 packets each; granulepos = cumulative
        // 48kHz sample count at the end of the page (Ogg-Opus convention).
        $granule = 0;
        $n = count($this->packets);
        for ($i = 0; $i < $n;) {
            $chunk = [];
            for ($j = 0; $j < 50 && $i < $n; $j++, $i++) {
                $chunk[]  = $this->packets[$i];
                $granule += $this->opusSamples($this->packets[$i]);
            }
            $last = ($i >= $n);
            $out .= $this->oggPage($serial, $seq++, $last ? 0x04 : 0x00, $granule, $chunk);
        }
        return $out;
    }

    /** Build one Ogg page. $packets are complete Opus packets. */
    private function oggPage(int $serial, int $seq, int $headerType, int $granule, array $packets): string
    {
        $segTable = '';
        $body     = '';
        foreach ($packets as $pkt) {
            $len = strlen($pkt);
            while ($len >= 255) { $segTable .= chr(255); $len -= 255; }
            $segTable .= chr($len);
            $body .= $pkt;
        }
        $numSegs   = strlen($segTable);
        $granuleLo = $granule & 0xFFFFFFFF;
        $granuleHi = ($granule >> 32) & 0xFFFFFFFF;

        $header = 'OggS'
            . chr(0)                                    // stream structure version
            . chr($headerType)                          // header type flag
            . pack('V', $granuleLo) . pack('V', $granuleHi) // granulepos (64-bit LE)
            . pack('V', $serial)
            . pack('V', $seq)
            . pack('V', 0)                              // CRC placeholder
            . chr($numSegs)
            . $segTable;

        $page = $header . $body;
        $crc  = $this->oggCrc($page);
        // Splice the CRC into its slot (offset 22).
        return substr($page, 0, 22) . pack('V', $crc) . substr($page, 26);
    }

    private function defaultOpusHead(): string
    {
        // OpusHead: version=1, channels=1, pre-skip=3840, rate=48000,
        // output-gain=0, channel-mapping-family=0.
        return 'OpusHead' . chr(1) . chr(1) . pack('v', 3840) . pack('V', 48000) . pack('v', 0) . chr(0);
    }

    /** Samples in one Opus packet (48kHz), from its TOC byte. */
    private function opusSamples(string $pkt): int
    {
        if ($pkt === '') return 960;
        $toc    = ord($pkt[0]);
        $config = $toc >> 3;
        $code   = $toc & 0x03;
        $fs     = self::FRAME_SIZES[$config] ?? 960;
        $frames = match ($code) {
            0       => 1,
            1, 2    => 2,
            default => (strlen($pkt) >= 2 ? (ord($pkt[1]) & 0x3F) : 1),
        };
        return $fs * max(1, $frames);
    }

    // ── EBML primitives ────────────────────────────────────────────────

    private function readId(string $b, int $pos): array
    {
        $first = ord($b[$pos]);
        $len   = $this->vintLen($first);
        $id    = 0;
        for ($i = 0; $i < $len; $i++) $id = ($id << 8) | ord($b[$pos + $i]);
        return [$id, $pos + $len];
    }

    /** @return array{0:int,1:int,2:bool} [value, newPos, isUnknownSize] */
    private function readSize(string $b, int $pos): array
    {
        $first = ord($b[$pos]);
        $len   = $this->vintLen($first);
        $val   = $first & ((1 << (8 - $len)) - 1);
        $ones  = (1 << (8 - $len)) - 1;
        for ($i = 1; $i < $len; $i++) {
            $val  = ($val << 8) | ord($b[$pos + $i]);
            $ones = ($ones << 8) | 0xFF;
        }
        return [$val, $pos + $len, $val === $ones];
    }

    /** @return array{0:int,1:int} [value, newPos] */
    private function readVint(string $b, int $pos): array
    {
        [$v, $np] = $this->readSize($b, $pos);
        return [$v, $np];
    }

    private function readUint(string $b, int $s, int $e): int
    {
        $v = 0;
        for ($i = $s; $i < $e; $i++) $v = ($v << 8) | ord($b[$i]);
        return $v;
    }

    private function vintLen(int $first): int
    {
        if ($first & 0x80) return 1;
        if ($first & 0x40) return 2;
        if ($first & 0x20) return 3;
        if ($first & 0x10) return 4;
        if ($first & 0x08) return 5;
        if ($first & 0x04) return 6;
        if ($first & 0x02) return 7;
        return 8;
    }

    /** Ogg CRC32 — polynomial 0x04c11db7, no reflection, init 0. */
    private function oggCrc(string $data): int
    {
        static $table = null;
        if ($table === null) {
            $table = [];
            for ($i = 0; $i < 256; $i++) {
                $r = $i << 24;
                for ($j = 0; $j < 8; $j++) {
                    $r = ($r & 0x80000000) ? ((($r << 1) ^ 0x04c11db7) & 0xFFFFFFFF) : (($r << 1) & 0xFFFFFFFF);
                }
                $table[$i] = $r & 0xFFFFFFFF;
            }
        }
        $crc = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc = ((($crc << 8) & 0xFFFFFFFF) ^ $table[(($crc >> 24) & 0xFF) ^ ord($data[$i])]) & 0xFFFFFFFF;
        }
        return $crc & 0xFFFFFFFF;
    }
}
