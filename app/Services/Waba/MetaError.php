<?php

namespace App\Services\Waba;

/**
 * Turns a Meta Graph API `error` object into the most useful human string we
 * can show the operator — Meta's OWN words, not our paraphrase.
 *
 * Graph errors carry several message fields; the generic `message` is often
 * vague ("Unsupported post request"), while `error_user_msg` and
 * `error_data.details` hold the SPECIFIC reason ("Template name does not exist
 * in en_US", "(#131047) Re-engagement message…"). We prefer the specific ones
 * and always append the code/subcode + fbtrace_id so it can be matched to
 * Meta's error-code reference and quoted to Meta support.
 *
 * Shape (per Meta docs):
 *   { "error": { "message", "code", "error_subcode", "type",
 *                "error_user_title", "error_user_msg", "fbtrace_id",
 *                "error_data": { "messaging_product", "details" } } }
 */
class MetaError
{
    /** @param array $error  The `error` sub-array from a Graph JSON response. */
    public static function describe(array $error): string
    {
        if (empty($error)) return '';

        $code    = $error['code']             ?? null;
        $subcode = $error['error_subcode']    ?? null;
        $title   = trim((string) ($error['error_user_title'] ?? ''));
        $userMsg = trim((string) ($error['error_user_msg']   ?? ''));
        $details = trim((string) ($error['error_data']['details'] ?? ''));
        $message = trim((string) ($error['message']          ?? ''));
        $trace   = trim((string) ($error['fbtrace_id']       ?? ''));

        // Most specific human message Meta gave us, in priority order.
        $primary = $userMsg ?: $details ?: $message ?: 'Unknown Meta error';

        // Lead with Meta's own title when it adds context the body doesn't.
        if ($title !== '' && stripos($primary, $title) === false) {
            $primary = $title . ' — ' . $primary;
        }

        $suffix = [];
        if ($code !== null && $code !== '') {
            $suffix[] = 'code ' . $code . ($subcode ? '/' . $subcode : '');
        }
        if ($trace !== '') {
            $suffix[] = 'trace ' . $trace;
        }

        return $primary . ($suffix ? '  (' . implode(', ', $suffix) . ')' : '');
    }
}
