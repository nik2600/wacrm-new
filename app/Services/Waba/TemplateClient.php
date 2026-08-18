<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Meta Cloud `message_templates` HTTP wrapper.
 *
 * Single-purpose: every Graph call our template flow needs lives
 * here so that the controller / job / dispatcher never builds a
 * URL or sets a header themselves.
 *
 * Errors are raised as RuntimeException with a hint string the
 * caller can show inline; the raw Meta response is also attached
 * via getCode() (HTTP status) and an `$lastError` array on the
 * client for debugging.
 *
 *   $client = new TemplateClient($wabaCfg);
 *   $resp   = $client->submit($payload);   // ['id' => '...', 'status' => 'PENDING']
 *   $state  = $client->fetch($resp['id']); // poll
 */
class TemplateClient
{
    public array $lastError = [];

    private string $base;
    private string $token;
    private string $wabaId;
    private string $appId;

    public function __construct(public WaProviderConfig $cfg)
    {
        $creds = $cfg->creds();
        $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];

        $version = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $this->base   = 'https://graph.facebook.com/' . ltrim($version, '/');
        $this->token  = (string) ($creds['access_token']      ?? '');
        $this->wabaId = (string) ($meta['waba_id']            ?? $creds['waba_id']        ?? '');
        $this->appId  = (string) ($creds['app_id']            ?? '');

        // Recover a missing waba_id. Older/partial connects store the token +
        // phone_number_id (so SENDING works) but no waba_id, which breaks SYNC
        // (this client) with "missing … waba_id". Derive it from the business's
        // WABAs (matched by phone) and backfill — one-time, then it's stored.
        if ($this->wabaId === '' && $this->token !== '') {
            $this->wabaId = (string) (WabaIdBackfiller::resolve($cfg) ?? '');
        }

        if ($this->token === '' || $this->wabaId === '') {
            // Name the field that's actually missing so the operator knows whether
            // to fix the token or reconnect to capture the WABA id.
            $missing = $this->token === '' ? 'access_token' : 'waba_id';

            // TRACK WHY it's missing (grep [WABA-TEMPLATE-SYNC] in logs). This tells
            // a code bug apart from an install/timing issue WITHOUT DB access:
            //   raw_creds_len=0                     → token was NEVER stored (capture
            //                                          failed: coexistence async / re-used
            //                                          code / the empty-token guard gap)
            //   raw_creds_len>0 && !creds_decrypted → DECRYPT FAILED — credentials_json
            //                                          not migrated to TEXT (truncated) OR
            //                                          APP_KEY rotated on THIS install
            //   creds_decrypted && token_len=0      → creds stored but no access_token key
            //   token_expired=true                  → the ~60-day coexistence token lapsed
            $exp = (string) ($meta['token_expires_at'] ?? '');
            \Illuminate\Support\Facades\Log::warning('[WABA-TEMPLATE-SYNC] missing ' . $missing, [
                'config_id'        => $cfg->id,
                'workspace_id'     => $cfg->workspace_id,
                'missing'          => $missing,
                'raw_creds_len'    => strlen((string) $cfg->credentials_json),
                'creds_decrypted'  => !empty($creds),
                'creds_keys'       => array_keys($creds),
                'token_len'        => strlen($this->token),
                'waba_id_present'  => $this->wabaId !== '',
                'connected_via'    => $meta['connected_via'] ?? null,
                'coexistence'      => (bool) ($meta['coexistence'] ?? false),
                'token_expires_at' => $exp ?: null,
                'token_expired'    => $exp !== '' ? (($t = strtotime($exp)) !== false && $t < time()) : null,
            ]);

            throw new RuntimeException(
                "WABA config is missing {$missing}. Reconnect the WhatsApp Business number under Devices so it's recaptured."
            );
        }
    }

    /**
     * POST /{WABA_ID}/message_templates
     *
     * @param  array  $payload  from TemplatePayloadBuilder::build()
     * @return array            ['id' => 'meta_template_id', 'status' => 'PENDING', 'category' => 'UTILITY']
     */
    public function submit(array $payload): array
    {
        $resp = $this->http()->post("{$this->base}/{$this->wabaId}/message_templates", $payload);
        $this->stash($resp, 'submit', $payload);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp), $resp->status());
        }

        $body = $resp->json();
        return [
            'id'       => (string) ($body['id']       ?? ''),
            'status'   => (string) ($body['status']   ?? 'PENDING'),
            'category' => (string) ($body['category'] ?? ''),
        ];
    }

    /** GET /{TEMPLATE_ID}?fields=… — single template state refresh. */
    public function fetch(string $metaTemplateId): array
    {
        $resp = $this->http()->get("{$this->base}/{$metaTemplateId}", [
            'fields' => 'id,name,status,category,language,quality_score,rejection_reason,components',
        ]);
        $this->stash($resp, 'fetch', ['id' => $metaTemplateId]);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp), $resp->status());
        }
        return (array) $resp->json();
    }

    /** GET /{WABA_ID}/message_templates — paginated list. */
    public function list(?string $after = null, int $limit = 200): array
    {
        $params = [
            // `components` + `parameter_format` are needed so the importer can
            // reconstruct the local row (header/body/footer/buttons) from a
            // template that was created directly in Meta Business Manager.
            'fields' => 'id,name,status,category,language,quality_score,rejection_reason,parameter_format,components',
            'limit'  => $limit,
        ];
        if ($after) $params['after'] = $after;

        $resp = $this->http()->get("{$this->base}/{$this->wabaId}/message_templates", $params);
        $this->stash($resp, 'list', $params);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp), $resp->status());
        }
        return (array) $resp->json();
    }

    /** DELETE /{WABA_ID}/message_templates?name=… — Meta delete-by-name. */
    public function deleteByName(string $name): bool
    {
        $resp = $this->http()->delete("{$this->base}/{$this->wabaId}/message_templates", ['name' => $name]);
        $this->stash($resp, 'delete', ['name' => $name]);
        return $resp->successful();
    }

    /**
     * POST /{APP_ID}/uploads — start a resumable upload session.
     * Then POST /{UPLOAD_SESSION_ID} with the binary body. Returns
     * the `header_handle` (Meta's `h` field) used in template create
     * payloads as `example.header_handle[0]`.
     */
    public function uploadHeaderMedia(string $localPath, string $mime): string
    {
        if ($this->resolveAppId() === '') {
            throw new RuntimeException('Cannot upload media — no Meta App ID available. Set the Meta App ID in Admin → Settings → WhatsApp (the same App your Embedded Signup uses), then retry.');
        }
        if (!is_readable($localPath)) {
            throw new RuntimeException("File not readable: $localPath");
        }
        $bytes = filesize($localPath);

        // 1) Open session.
        $open = $this->http()->post("{$this->base}/{$this->appId}/uploads", [
            'file_length' => $bytes,
            'file_type'   => $mime,
            'file_name'   => basename($localPath),
        ]);
        $this->stash($open, 'upload_open', ['path' => $localPath, 'mime' => $mime]);
        if (!$open->successful()) {
            throw new RuntimeException($this->errorHint($open), $open->status());
        }
        $sessionId = (string) ($open->json('id') ?? '');
        if ($sessionId === '') {
            throw new RuntimeException('Meta did not return an upload session id.');
        }

        // 2) Upload bytes. Meta wants `OAuth {token}` (not `Bearer`) and
        // `file_offset: 0` on the binary POST.
        $upload = Http::withHeaders([
                'Authorization' => 'OAuth ' . $this->token,
                'file_offset'   => '0',
            ])
            ->withBody(file_get_contents($localPath), $mime)
            ->timeout(60)
            ->post("{$this->base}/{$sessionId}");
        $this->stash($upload, 'upload_body', ['session' => $sessionId]);

        if (!$upload->successful()) {
            throw new RuntimeException($this->errorHint($upload), $upload->status());
        }
        $handle = (string) ($upload->json('h') ?? '');
        if ($handle === '') {
            throw new RuntimeException('Meta upload finished but did not return a header_handle.');
        }
        return $handle;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function http()
    {
        return Http::withToken($this->token)->acceptJson()->timeout(30);
    }

    /**
     * The Meta App ID that owns this WABA — required for the resumable media
     * upload (POST /{APP_ID}/uploads). Manual connections and some embedded-
     * signup flows never captured it into creds, so when it's absent we derive
     * it from Meta's token-debug endpoint (a token can inspect itself and it
     * reports its owning `app_id` — the same source WabaHealthService uses) and
     * cache it back onto the config so the next upload reads it directly.
     * Returns '' only when Meta itself won't surface it; the caller then shows
     * an actionable "reconnect / add the App ID" message.
     */
    private function resolveAppId(): string
    {
        if ($this->appId !== '') return $this->appId;

        try {
            $resp = $this->http()->get("{$this->base}/debug_token", ['input_token' => $this->token]);
            if (! $resp->successful() || ($resp->json('data.app_id') ?? '') === '') {
                // Meta only fills data.app_id when the AUTHORIZING token is an
                // app/app-developer token. An embedded-signup token debugging
                // itself is normally refused, so this is an expected miss, not
                // an outage — logged because it decides the fallback below.
                \Log::info('[WABA-template] debug_token gave no app_id — falling back to platform App ID', [
                    'status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 200),
                ]);
            }
            if ($resp->successful()) {
                $appId = (string) ($resp->json('data.app_id') ?? '');
                if ($appId !== '') {
                    $this->appId = $appId;
                    // Cache back onto the WABA config so we never re-derive.
                    // Best-effort — a save failure must not block the upload.
                    try {
                        $creds = $this->cfg->creds();
                        $creds['app_id'] = $appId;
                        $this->cfg->setCreds($creds)->save();
                    } catch (\Throwable $e) {
                        // ignore — the in-memory appId still lets this upload proceed
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through — the platform App ID below is the real backstop
        }

        // Last resort: the PLATFORM's Meta App ID (admin → WhatsApp settings,
        // the one Embedded Signup runs on). Any WABA onboarded through our
        // signup — or connected manually against our App — is owned by that
        // app, so it is the correct /uploads target. debug_token only answers
        // for app/system-user tokens, which left every embedded-signup client
        // dead-ended on "no Meta App ID" with no way to proceed: the template
        // saved locally and never reached Meta at all.
        if ($this->appId === '') {
            $platform = trim((string) SystemSetting::get('waba_app_id', ''));
            if ($platform !== '') {
                $this->appId = $platform;
                \Log::info('[WABA-template] using platform App ID for media upload', ['app_id' => $platform]);
            }
        }

        return $this->appId;
    }

    private function stash(Response $resp, string $op, array $context): void
    {
        $this->lastError = [
            'op'       => $op,
            'status'   => $resp->status(),
            'body'     => $resp->json() ?? $resp->body(),
            'context'  => $context,
        ];

        // Log the FULL Meta error on any non-2xx. A carousel rejection often comes
        // back as a vague "Invalid parameter" in `message`, but Meta's error_data /
        // error_subcode / fbtrace_id name the ACTUAL offending card/field — and
        // those are discarded once errorHint() flattens to one sentence. Persist
        // the raw error so it's always diagnosable from the log. Grep [WABA-TEMPLATE].
        if (!$resp->successful()) {
            \Log::warning('[WABA-TEMPLATE] ' . $op . ' failed', [
                'status'  => $resp->status(),
                'waba_id' => $this->wabaId,
                'error'   => $resp->json('error') ?? mb_substr((string) $resp->body(), 0, 1200),
            ]);
        }
    }

    /**
     * Translate Meta's typed error envelopes into a single
     * user-actionable sentence. Mirrors the DevicesController helper
     * but specialized for the template endpoint's common failures.
     */
    private function errorHint(Response $resp): string
    {
        $err     = (array) $resp->json('error', []);
        $code    = (int) ($err['code']          ?? 0);
        $sub     = (int) ($err['error_subcode'] ?? 0);
        $msg     = (string) ($err['message']    ?? 'Unknown Meta error.');

        // Meta buries the ACTUAL reason in error_user_msg / error_data.details —
        // `message` is often just the generic "Invalid parameter", which tells the
        // operator (and us) nothing. This is exactly why a carousel rejection read
        // "Bad parameter: Invalid parameter" with no clue which card/field is at
        // fault. Pull the richest specific field Meta provides so the banner names
        // the real problem (e.g. "header_handle is not valid", "example missing").
        $detailRaw = $err['error_data']['details'] ?? '';
        if (is_array($detailRaw)) $detailRaw = implode('; ', array_map('strval', $detailRaw));
        $detail = trim((string) (
            (string) ($err['error_user_msg'] ?? '')
            ?: (string) $detailRaw
            ?: (string) ($err['error_user_title'] ?? '')
        ));

        // Token / permission issues
        if ($code === 190)                  return 'Meta token expired or invalid. Reconnect this WABA, then retry.';
        if ($code === 200 && $sub === 1349174) return 'Your Meta app is missing whatsapp_business_management permission. Regenerate the System User token with that scope.';

        // Does Meta's OWN text actually describe a limit? Code 192 is not
        // quota-specific — Meta reuses it for several template problems — so we
        // only surface the "delete old templates" advice when its message /
        // detail corroborates a limit. Otherwise we show Meta's real message so
        // we never send the operator deleting templates that aren't the cause.
        $looksLikeLimit = (bool) preg_match('/\b(limit|quota|maximum|reached|too many|exceed)\b/i', $msg . ' ' . $detail);

        // Template-specific known codes.
        $base = match (true) {
            $code === 100   => "Bad parameter: $msg",
            $code === 132000 => "Template parameter mismatch: $msg",  // common on bad placeholder examples
            $code === 132001 => 'Template language not supported: ' . $msg,
            $code === 132005 => "Template not found by name. It may have been deleted or never approved.",
            $code === 132007 => "Template name already exists for a different language. Pick a different name.",
            $code === 192 && $looksLikeLimit => "Template exceeds your WABA's template quota. Delete old templates first.",
            default          => "Meta error $code" . ($sub ? "/$sub" : '') . ": $msg",
        };

        // Append Meta's specific detail when it says more than the generic message.
        if ($detail !== '' && strcasecmp($detail, $msg) !== 0 && stripos($base, $detail) === false) {
            $base .= ' — ' . $detail;
        }

        // Always keep Meta's numeric code visible so support can diagnose a
        // mislabeled or unfamiliar failure straight from the banner.
        if ($code > 0 && stripos($base, "Meta error $code") === false && stripos($base, "#$code") === false) {
            $base .= " (Meta #$code" . ($sub ? "/$sub" : '') . ')';
        }
        // For a carousel this is nearly always a media header_handle minted under
        // the wrong Meta App (multi-WABA) or a per-card example/uniformity issue —
        // point the operator at the usual culprit when Meta stayed vague.
        if ($code === 100 && $detail === '' && stripos($msg, 'invalid parameter') !== false) {
            $base .= ' — Meta did not say which field. For a carousel this is almost always the card media: re-upload each card image and make sure you submit on the WhatsApp account that owns them, then retry.';
        }
        return $base;
    }
}
