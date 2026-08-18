<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Media — upload a file once and get a hosted URL you can reuse as the
 * `media_url` on POST /messages (or anywhere a public media URL is needed).
 * Saves to the same workspace media disk the inbox uses.
 */
class MediaController extends V1Controller
{
    /**
     * POST /api/v1/media — multipart upload (field name: file).
     * Returns { url, path, type, mime, size }.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Max 16 MB — the WhatsApp document ceiling.
            'file' => ['required', 'file', 'max:16384'],
        ]);

        $file = $request->file('file');

        // Secure-upload guard: strict extension + real-MIME allowlist. Without
        // this the attacker-controlled extension (e.g. .php) is preserved and
        // written to the web-served media disk => arbitrary-file-upload / RCE.
        if ($problem = \App\Support\SecureUpload::problem($file)) {
            return $this->fail('unsupported_media_type', $problem, 422);
        }

        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $type = str_starts_with($mime, 'image/') ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video'
            : (str_starts_with($mime, 'audio/') ? 'audio' : 'document'));

        // Server-controlled, randomised filename with an allowlisted extension —
        // never derived from the client-supplied original name.
        $path = $file->storeAs('chat-media', \App\Support\SecureUpload::safeName($file), media_disk());

        return $this->created([
            'url'  => media_url($path),
            'path' => $path,
            'type' => $type,
            'mime' => $mime,
            'size' => $file->getSize(),
        ]);
    }
}
