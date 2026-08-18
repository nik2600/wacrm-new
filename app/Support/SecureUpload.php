<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Centralised secure-upload guard for user-supplied media.
 *
 * Every user upload site (flow media, scheduled media, auto-reply media,
 * REST /api/v1/media) funnels through here so an attacker can never write an
 * executable script (.php/.phtml/…) or a same-origin active document
 * (.svg/.html/.js) into the web root and have it run / served back.
 *
 * Two guarantees:
 *   1. The stored extension is ALWAYS drawn from our server-side allowlist,
 *      never taken verbatim from the client filename — so a script extension
 *      can never reach disk even if MIME sniffing is fooled.
 *   2. The real, content-sniffed MIME must match an allowed media type
 *      (defence in depth behind the extension allowlist).
 *
 * The stored filename is randomised to prevent guessing / overwrite.
 */
class SecureUpload
{
    /** The ONLY stored extensions ever written to disk. Non-executable media
     *  + document types WhatsApp/Twilio legitimately deliver. Executables and
     *  active-content types are excluded here AND explicitly in BLOCKED below. */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff',          // images
        'mp4', 'webm', 'mov', '3gp', 'mkv',                          // video
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',          // documents
        'txt', 'csv', 'rtf', 'zip',                                  // documents (data/archive)
        'mp3', 'ogg', 'opus', 'm4a', 'aac', 'wav', 'amr',            // audio
    ];

    /** Never allowed — executable / active-content types (explicit blocklist). */
    public const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phtm',
        'phar', 'htaccess', 'htm', 'html', 'svg', 'xml', 'xhtml', 'js', 'mjs',
        'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'com', 'jsp', 'asp', 'aspx',
    ];

    /** Sniffed MIME types we accept (defence in depth behind the ext allowlist). */
    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp',
        'image/bmp', 'image/tiff',
        'video/mp4', 'video/webm', 'video/quicktime', 'video/3gpp', 'video/x-matroska',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/csv', 'application/rtf', 'text/rtf',
        'application/zip',            // docx/xlsx/pptx sniff as a zip container
        'audio/mpeg', 'audio/mp3', 'audio/ogg', 'application/ogg', 'video/ogg',
        'audio/opus', 'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/aacp',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/amr',
        'application/octet-stream',   // opus/m4a/office docs on some libmagic builds
    ];

    /**
     * Validate a user upload. Returns null when OK, or a short human-readable
     * error message describing why it was rejected.
     */
    public static function problem(?UploadedFile $file): ?string
    {
        if (!$file || !$file->isValid()) {
            return 'The uploaded file is invalid.';
        }

        $ext = strtolower(trim((string) $file->getClientOriginalExtension()));

        if ($ext === '' || in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            return 'This file type is not allowed.';
        }
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return 'This file type is not allowed.';
        }

        // Real, content-sniffed MIME (finfo on the actual bytes) — NOT the
        // client-declared Content-Type header, which is trivially spoofed.
        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        if ($mime !== '' && !in_array($mime, self::ALLOWED_MIMES, true)) {
            return 'This file content is not allowed.';
        }

        return null;
    }

    /** True when the upload passes every guard. */
    public static function passes(?UploadedFile $file): bool
    {
        return self::problem($file) === null;
    }

    /**
     * A randomised, collision-resistant, server-controlled filename. The
     * extension is drawn from our allowlist copy of the client extension —
     * never trusted verbatim — so a script extension can never be written.
     * Call ONLY on a file that already passed problem()/passes().
     */
    public static function safeName(UploadedFile $file): string
    {
        $ext = strtolower(trim((string) $file->getClientOriginalExtension()));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            // Unreachable after passes(); kept as a fail-safe. '.bin' is inert.
            $ext = 'bin';
        }

        return time() . '_' . Str::random(24) . '.' . $ext;
    }
}
