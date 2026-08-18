<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Single entry-point for sending an approved WABA template.
 *
 * Caller passes a `WaTemplate` + recipient number + per-recipient
 * variable map. We enforce four ban-prevention rails before the
 * request ever leaves our server:
 *
 *   1. Meta status MUST be APPROVED (refuse PENDING/REJECTED/PAUSED/DISABLED).
 *   2. Quality floor — refuse to send when `quality_score = RED`
 *      unless the admin override flag is set. Sending into a RED
 *      quality template is the fast track to losing the WABA.
 *   3. Per-template per-24h send cap. Default = workspace's daily
 *      messaging tier ÷ 4 (Meta's tier 1000 → 250/day/template max).
 *      Configurable via `waba_template_daily_cap` (system_setting).
 *   4. Auto-pause check — if `paused_until` is in the future, refuse.
 *
 * On success, returns the wamid. On failure, returns an error code
 * + hint string so the caller can decide whether to retry, fall
 * back to a different template, or surface to the operator.
 */
class TemplateSender
{
    public const RC_OK              = 'ok';
    public const RC_NOT_APPROVED    = 'not_approved';
    public const RC_QUALITY_FLOOR   = 'quality_floor';
    public const RC_PAUSED          = 'paused';
    public const RC_RATE_LIMITED    = 'rate_limited';
    public const RC_NO_PROVIDER     = 'no_provider';
    public const RC_META_ERROR      = 'meta_error';
    public const RC_MISSING_MEDIA   = 'missing_header_media';

    /**
     * @param  array{header?:string,header_media_id?:string,header_media_url?:string,body?:array<int,string>,buttons?:array<int,array>,cards?:array<int,array>}  $vars
     * @return array{ok:bool,code:string,wamid?:?string,error?:?string,template_id:int}
     */
    public function send(WaTemplate $tpl, string $toNumber, array $vars = [], ?WaProviderConfig $cfg = null): array
    {
        // 1. Approval gate ------------------------------------------------
        if ($tpl->meta_status !== 'APPROVED') {
            return $this->fail(self::RC_NOT_APPROVED, $tpl,
                "Template is not approved by Meta (current: {$tpl->meta_status}). Wait for approval before sending.");
        }

        // 2. Paused gate --------------------------------------------------
        if ($tpl->paused_until && $tpl->paused_until->isFuture()) {
            return $this->fail(self::RC_PAUSED, $tpl,
                'Template is paused until ' . $tpl->paused_until->toIso8601String() . '. Quality must recover before sending again.');
        }

        // 3. Quality floor — ONLY a confirmed RED rating blocks a send.
        // UNKNOWN (Meta hasn't rated a brand-new approved template yet — true of
        // EVERY freshly-approved template), YELLOW and GREEN are all allowed.
        // Blocking UNKNOWN/YELLOW stopped legitimate templates from ever going
        // out; RED is the only "fast track to losing the WABA" signal, so that
        // is all we refuse.
        $score = strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN'));
        if ($score === 'RED') {
            return $this->fail(self::RC_QUALITY_FLOOR, $tpl,
                'Template quality score is RED — sending would accelerate the quality drop and risk losing the WABA. Refusing.');
        }

        // 4. Per-template rate limit -------------------------------------
        $cap = (int) SystemSetting::get('waba_template_daily_cap', 0);
        if ($cap > 0) {
            $count = (int) Cache::get($this->capKey($tpl), 0);
            if ($count >= $cap) {
                return $this->fail(self::RC_RATE_LIMITED, $tpl,
                    "Daily send cap of $cap reached for this template. Resets at midnight UTC.");
            }
        }

        // 5. Resolve provider --------------------------------------------
        $cfg = $cfg ?? ($tpl->provider_config_id
            ? WaProviderConfig::find($tpl->provider_config_id)
            : WaProviderConfig::primaryForWorkspace($tpl->workspace_id)->first());
        if (!$cfg || $cfg->provider !== 'waba') {
            return $this->fail(self::RC_NO_PROVIDER, $tpl,
                'No WABA provider configured for this workspace.');
        }
        $creds   = $cfg->creds();
        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) (($cfg->meta_json['phone_number_id'] ?? null) ?: ($creds['phone_number_id'] ?? ''));
        if ($token === '' || $phoneId === '') {
            return $this->fail(self::RC_NO_PROVIDER, $tpl,
                'WABA provider is missing access_token or phone_number_id.');
        }

        // 6. Click-tracking — wrap any URL-button parameter that looks
        //    like a full URL via LinkTracker. Templates whose button URL
        //    pattern already lives on our shortlink domain just stay as
        //    the token. For partial-value buttons (placeholder inside a
        //    URL), tracking happens at TEMPLATE CREATE time via the
        //    builder's button rewrite — see TemplatePayloadBuilder.
        $vars = $this->wrapTrackableButtonValues($tpl, $toNumber, $vars);

        // 6b. LOCATION header — feed the template's stored coordinates into
        //     the vars so TemplatePayloadBuilder::buildSendHeader fires its
        //     location branch (it reads $vars['header_location']).
        if (empty($vars['header_location']) && is_array($tpl->header_location) && !empty($tpl->header_location)) {
            $vars['header_location'] = $tpl->header_location;
        }

        // 6c. Media header auto-resolution + sanity.
        //
        // An IMAGE / VIDEO / DOCUMENT header template MUST carry a media
        // reference on EVERY send. A template SYNCED from Meta arrives with the
        // header FORMAT but no sample bytes — Meta never returns the approved
        // sample image in a way we can send — which is why its preview shows a
        // grey placeholder and every send failed
        // "132012 header: Format mismatch, expected IMAGE, received UNKNOWN".
        //
        // When the caller supplied no media, fetch Meta's OWN approved sample
        // (captured at import as header_sample_url), upload it to Meta's /media
        // endpoint, and send with the resulting MEDIA ID. Using an id — not a
        // link — means the image lives on Meta's servers, so Meta never has to
        // reach back to ours (which is the other way this error happens when our
        // public URL isn't fetchable). The id is cached per (template, phone) so
        // we upload once, not per recipient.
        $version    = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $headerType = strtoupper((string) ($tpl->attachment_type ?: 'TEXT'));
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && empty($vars['header_media_id'])
            && empty($vars['header_media_url'])) {
            $mediaId = $this->resolveHeaderMediaId($tpl, $phoneId, $token, $version);
            if ($mediaId !== null) {
                $vars['header_media_id'] = $mediaId;
            }
        }

        // Send-time media OVERRIDE — the operator swapped the header image for
        // this send. Previously we passed the URL through as `link`, leaving
        // Meta to fetch from OUR server on every recipient: slower, and it
        // fails outright whenever our host isn't publicly reachable (the exact
        // failure mode the block above exists to avoid). Upload it once, cache
        // the id per (url, phone), and send the id instead. Falls back to the
        // link if the upload fails, so a swap never turns into a dead send.
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && empty($vars['header_media_id'])
            && ! empty($vars['header_media_url'])) {
            $overrideUrl = (string) $vars['header_media_url'];
            // Same download → validate → upload → cache pipeline the template's
            // own sample uses; passing the URL explicitly rather than copying
            // that code is the whole point.
            $uploaded = $this->resolveHeaderMediaId($tpl, $phoneId, $token, $version, $overrideUrl);
            if ($uploaded !== null) {
                $vars['header_media_id'] = $uploaded;
                unset($vars['header_media_url']);
            } else {
                Log::warning('[WABA-template-send] override media upload failed — falling back to link', [
                    'tpl' => $tpl->id, 'url' => mb_substr($overrideUrl, 0, 200),
                ]);
            }
        }

        // Still nothing after auto-resolution (no sample on Meta, no upload, no
        // stored attachment) → refuse with an actionable message rather than let
        // Meta reject cryptically and drag the WABA quality rating down.
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && empty($vars['header_media_id'])
            && empty($vars['header_media_url'])
            && empty($tpl->attachment_file)) {
            $noun = strtolower($headerType);
            Log::warning('[WABA-template-send] refused — media-header template has no ' . $noun, [
                'tpl' => $tpl->id, 'template' => $tpl->template_name, 'header_type' => $headerType,
            ]);
            return $this->fail(self::RC_MISSING_MEDIA, $tpl,
                "This template has a {$headerType} header but no {$noun} could be resolved. Re-sync the template from Meta (so we capture its sample image), or upload a header {$noun} on the template — then send again.");
        }

        // 7. Build + POST ------------------------------------------------
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        $template = (new TemplatePayloadBuilder())->buildSend($tpl, $vars);
        $payload  = [
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/\D+/', '', $toNumber),
            'type'              => 'template',
            'template'          => $template,
        ];

        // Full visibility into what we send Meta (the exact Graph payload).
        Log::info('[WABA-template-send] POST', [
            'tpl'              => $tpl->id,
            'template'         => $tpl->template_name,
            'meta_template_id' => $tpl->meta_template_id,
            'phone_id'         => $phoneId,
            'to'               => $toNumber,
            'payload'          => $payload,
        ]);

        try {
            $resp = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("{$base}/{$phoneId}/messages", $payload);
        } catch (\Throwable $e) {
            return $this->fail(self::RC_META_ERROR, $tpl, 'HTTP exception: ' . $e->getMessage());
        }

        // 132001 = "template name (...) does not exist in <lang>". The #1 cause
        // is a REGION-SUFFIX mismatch: our record says en_US but Meta approved
        // the template in the base code `en` (the most common WABA gotcha).
        // Retry ONCE with the base language (en_US → en, pt_BR → pt); on success
        // persist the corrected language so every future send skips this retry.
        if (!$resp->successful() && (int) ($resp->json('error.code') ?? 0) === 132001) {
            $sentLang = (string) ($payload['template']['language']['code'] ?? '');
            if (str_contains($sentLang, '_')) {
                $baseLang = explode('_', $sentLang)[0];
                $payload['template']['language']['code'] = $baseLang;
                Log::info('[WABA-template-send] 132001 → retry with base language', [
                    'tpl' => $tpl->id, 'from' => $sentLang, 'to' => $baseLang,
                ]);
                try {
                    $resp = Http::withToken($token)->acceptJson()->timeout(20)
                        ->post("{$base}/{$phoneId}/messages", $payload);
                } catch (\Throwable $e) {
                    return $this->fail(self::RC_META_ERROR, $tpl, 'HTTP exception: ' . $e->getMessage());
                }
                if ($resp->successful()) {
                    // Self-heal: store the language Meta actually accepts.
                    try { $tpl->forceFill(['language' => $baseLang])->save(); } catch (\Throwable $e) {}
                }
            }
        }

        if (!$resp->successful()) {
            $err     = (array) ($resp->json('error') ?? []);
            $errCode = (int) ($err['code'] ?? 0);
            // Surface META'S REAL error (error_user_msg / error_data.details +
            // code + trace) rather than our paraphrase, so the operator sees the
            // exact reason and can act / quote it to Meta support.
            $real    = MetaError::describe($err) ?: ('HTTP ' . $resp->status());
            Log::warning('[WABA-template-send] failed', [
                'tpl' => $tpl->id, 'to' => $toNumber, 'code' => $errCode, 'msg' => $real,
                'body' => $resp->body(),   // Meta's full error response
            ]);
            return $this->fail(self::RC_META_ERROR, $tpl, $real);
        }

        $wamid = (string) ($resp->json('messages.0.id') ?? '');

        // Bump the cap counter (24h sliding window).
        if ($cap > 0) {
            $key = $this->capKey($tpl);
            Cache::add($key, 0, now()->addDay());
            Cache::increment($key);
        }

        Log::info('[WABA-template-send] ok', [
            'tpl'              => $tpl->id,
            'meta_template_id' => $tpl->meta_template_id,
            'quality_at_send'  => $score,
            'to'               => $toNumber,
            'wamid'            => $wamid,
        ]);

        // Fire `message_sent` outbound webhook — gives the customer's
        // external systems a real-time "we accepted the send" event
        // BEFORE Meta's status webhook lands. Status update webhooks
        // (delivered/read/failed) fire later from WaWebhookController.
        WebhookService::dispatch('message_sent', [
            'workspace_id' => $tpl->workspace_id,
            'template_id'  => $tpl->id,
            'template_name'=> $tpl->template_name,
            'recipient'    => $toNumber,
            'wamid'        => $wamid,
            'status'       => 'sent',
            'timestamp'    => now()->timestamp,
            'quality_at_send' => $score,
        ], $tpl->user_id);

        return [
            'ok'          => true,
            'code'        => self::RC_OK,
            'wamid'       => $wamid,
            'error'       => null,
            'template_id' => $tpl->id,
        ];
    }

    /**
     * PARALLEL batch send of the SAME approved template to many recipients.
     *
     * The single-send send() above pays one full Meta round-trip PER recipient
     * (sequential), which caps a campaign at ~5-10 msgs/sec no matter how fast the
     * network is. Meta actually allows ~80 msgs/sec per number, so this method
     * fires a whole CHUNK of recipients CONCURRENTLY via Http::pool() and only then
     * waits — turning latency-bound sequential sends into throughput-bound parallel
     * ones (a 1000-recipient WABA blast finishes in seconds, matching WATI/AiSensy).
     *
     * RATE SAFETY: the chunk size IS the concurrency, capped at 30. A pool of ~15
     * completes in ~1 round-trip (~300ms) ⇒ ~50/sec, comfortably under Meta's 80/s
     * throughput limit (error 130429). Template-level gates (approval / paused /
     * quality / provider) run ONCE for the whole batch; per-recipient payload build
     * + media resolution reuse the exact same private helpers as send(), so a
     * pooled send is byte-identical to a sequential one — only the waiting is
     * shared.
     *
     * @param  array<int,array{id:int|string,to:string,vars:array}> $recipients
     * @param  int $concurrency  max in-flight requests per pool round (1..30)
     * @return array<int|string,array{ok:bool,code:string,wamid:?string,error:?string,template_id:int}>  keyed by recipient id
     */
    public function sendMany(WaTemplate $tpl, array $recipients, ?WaProviderConfig $cfg = null, int $concurrency = 15): array
    {
        $results = [];
        if (empty($recipients)) return $results;

        // --- Template-level gates, run ONCE for the whole batch ---
        $gateFail = null;
        if ($tpl->meta_status !== 'APPROVED') {
            $gateFail = $this->fail(self::RC_NOT_APPROVED, $tpl, "Template is not approved by Meta (current: {$tpl->meta_status}).");
        } elseif ($tpl->paused_until && $tpl->paused_until->isFuture()) {
            $gateFail = $this->fail(self::RC_PAUSED, $tpl, 'Template is paused until ' . $tpl->paused_until->toIso8601String() . '.');
        } elseif (strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN')) === 'RED') {
            $gateFail = $this->fail(self::RC_QUALITY_FLOOR, $tpl, 'Template quality score is RED — refusing.');
        }
        if ($gateFail) {
            foreach ($recipients as $r) $results[$r['id']] = $gateFail;
            return $results;
        }

        // Provider resolution — ONCE.
        $cfg = $cfg ?? ($tpl->provider_config_id
            ? WaProviderConfig::find($tpl->provider_config_id)
            : WaProviderConfig::primaryForWorkspace($tpl->workspace_id)->first());
        if (!$cfg || $cfg->provider !== 'waba') {
            $f = $this->fail(self::RC_NO_PROVIDER, $tpl, 'No WABA provider configured for this workspace.');
            foreach ($recipients as $r) $results[$r['id']] = $f;
            return $results;
        }
        $creds   = $cfg->creds();
        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) (($cfg->meta_json['phone_number_id'] ?? null) ?: ($creds['phone_number_id'] ?? ''));
        if ($token === '' || $phoneId === '') {
            $f = $this->fail(self::RC_NO_PROVIDER, $tpl, 'WABA provider missing access_token or phone_number_id.');
            foreach ($recipients as $r) $results[$r['id']] = $f;
            return $results;
        }

        $version    = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base       = 'https://graph.facebook.com/' . ltrim($version, '/');
        $cap        = (int) SystemSetting::get('waba_template_daily_cap', 0);
        $headerType = strtoupper((string) ($tpl->attachment_type ?: 'TEXT'));
        $concurrency = max(1, min(30, $concurrency));

        foreach (array_chunk($recipients, $concurrency, true) as $chunk) {
            // Build each recipient's Meta payload up front (reuses the SAME helpers
            // as send()). A recipient whose media can't resolve is recorded as a
            // failure and simply left out of the pool.
            $ready = [];   // key => ['to'=>, 'payload'=>]
            foreach ($chunk as $r) {
                $key   = $r['id'];
                $to    = (string) ($r['to'] ?? '');
                $vars  = is_array($r['vars'] ?? null) ? $r['vars'] : [];
                try {
                    $built = $this->buildRecipientPayload($tpl, $to, $vars, $phoneId, $token, $version, $headerType);
                } catch (\Throwable $e) {
                    // A single recipient's payload failing to build must fail ONLY
                    // that recipient — never throw out of the batch and strand the
                    // rest unsent.
                    $results[$key] = $this->fail(self::RC_META_ERROR, $tpl, 'payload build failed: ' . $e->getMessage());
                    continue;
                }
                if (!$built['ok']) { $results[$key] = $built; continue; }
                $ready[$key] = ['to' => $to, 'payload' => $built['payload']];
            }
            if (empty($ready)) continue;

            // Fire the whole chunk CONCURRENTLY, then reap.
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($ready, $token, $base, $phoneId) {
                $calls = [];
                foreach ($ready as $key => $r) {
                    $calls[$key] = $pool->as((string) $key)->withToken($token)->acceptJson()->timeout(20)
                        ->post("{$base}/{$phoneId}/messages", $r['payload']);
                }
                return $calls;
            });

            foreach ($ready as $key => $r) {
                $resp = $responses[(string) $key] ?? null;
                $res  = $this->interpretResponse($tpl, $r['to'], $r['payload'], $resp, $token, $base, $phoneId);
                $results[$key] = $res;
                if ($res['ok']) {
                    if ($cap > 0) { $ck = $this->capKey($tpl); Cache::add($ck, 0, now()->addDay()); Cache::increment($ck); }
                    try {
                        WebhookService::dispatch('message_sent', [
                            'workspace_id'  => $tpl->workspace_id,
                            'template_id'   => $tpl->id,
                            'template_name' => $tpl->template_name,
                            'recipient'     => $r['to'],
                            'wamid'         => $res['wamid'],
                            'status'        => 'sent',
                            'timestamp'     => now()->timestamp,
                        ], $tpl->user_id);
                    } catch (\Throwable $e) { /* webhook is best-effort */ }
                }
            }
        }

        return $results;
    }

    /**
     * Build the exact Graph `/messages` payload for ONE recipient — the same
     * wrap-tracking → resolve-media → refuse-if-missing → buildSend pipeline
     * send() runs inline, factored so the parallel path is byte-identical.
     * Returns ['ok'=>true,'payload'=>array] or a fail() array on missing media.
     */
    private function buildRecipientPayload(WaTemplate $tpl, string $toNumber, array $vars, string $phoneId, string $token, string $version, string $headerType): array
    {
        $vars = $this->wrapTrackableButtonValues($tpl, $toNumber, $vars);

        if (empty($vars['header_location']) && is_array($tpl->header_location) && !empty($tpl->header_location)) {
            $vars['header_location'] = $tpl->header_location;
        }

        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && empty($vars['header_media_id']) && empty($vars['header_media_url'])) {
            $mediaId = $this->resolveHeaderMediaId($tpl, $phoneId, $token, $version);
            if ($mediaId !== null) $vars['header_media_id'] = $mediaId;
        }
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && empty($vars['header_media_id']) && ! empty($vars['header_media_url'])) {
            $uploaded = $this->resolveHeaderMediaId($tpl, $phoneId, $token, $version, (string) $vars['header_media_url']);
            if ($uploaded !== null) { $vars['header_media_id'] = $uploaded; unset($vars['header_media_url']); }
        }
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && empty($vars['header_media_id']) && empty($vars['header_media_url']) && empty($tpl->attachment_file)) {
            $noun = strtolower($headerType);
            return $this->fail(self::RC_MISSING_MEDIA, $tpl,
                "This template has a {$headerType} header but no {$noun} could be resolved.");
        }

        $template = (new TemplatePayloadBuilder())->buildSend($tpl, $vars);
        return [
            'ok'      => true,
            'payload' => [
                'messaging_product' => 'whatsapp',
                'to'                => preg_replace('/\D+/', '', $toNumber),
                'type'              => 'template',
                'template'          => $template,
            ],
        ];
    }

    /**
     * Turn a pooled Response (or a thrown ConnectionException) into the standard
     * result shape, including the same one-shot 132001 base-language retry send()
     * does (en_US → en) — run sequentially for the rare failed item.
     *
     * @param  \Illuminate\Http\Client\Response|\Throwable|null $resp
     */
    private function interpretResponse(WaTemplate $tpl, string $toNumber, array $payload, $resp, string $token, string $base, string $phoneId): array
    {
        if ($resp instanceof \Throwable) {
            return $this->fail(self::RC_META_ERROR, $tpl, 'HTTP exception: ' . $resp->getMessage());
        }
        if ($resp === null) {
            return $this->fail(self::RC_META_ERROR, $tpl, 'No response from Meta (pool miss).');
        }

        // 132001 region-suffix mismatch → retry ONCE with the base language.
        if (!$resp->successful() && (int) ($resp->json('error.code') ?? 0) === 132001) {
            $sentLang = (string) ($payload['template']['language']['code'] ?? '');
            if (str_contains($sentLang, '_')) {
                $baseLang = explode('_', $sentLang)[0];
                $payload['template']['language']['code'] = $baseLang;
                try {
                    $resp = Http::withToken($token)->acceptJson()->timeout(20)
                        ->post("{$base}/{$phoneId}/messages", $payload);
                    if ($resp->successful()) {
                        try { $tpl->forceFill(['language' => $baseLang])->save(); } catch (\Throwable $e) {}
                    }
                } catch (\Throwable $e) {
                    return $this->fail(self::RC_META_ERROR, $tpl, 'HTTP exception: ' . $e->getMessage());
                }
            }
        }

        if (!$resp->successful()) {
            $err  = (array) ($resp->json('error') ?? []);
            $real = MetaError::describe($err) ?: ('HTTP ' . $resp->status());
            return $this->fail(self::RC_META_ERROR, $tpl, $real);
        }

        return [
            'ok'          => true,
            'code'        => self::RC_OK,
            'wamid'       => (string) ($resp->json('messages.0.id') ?? ''),
            'error'       => null,
            'template_id' => $tpl->id,
        ];
    }

    /**
     * Wrap any URL-shaped button parameter via LinkTracker, attaching
     * per-recipient context so the broadcasts page can answer
     * "which contact clicked".
     *
     * Operates only on button parameters whose `value` is a full
     * `http(s)://` URL (templates where the variable IS the URL).
     * For URL-pattern templates (placeholder inside a fixed URL), the
     * link-tracking rewrite happens at template create time so Meta
     * approves the wadesk shortlink domain.
     */
    private function wrapTrackableButtonValues(WaTemplate $tpl, string $toNumber, array $vars): array
    {
        // Pulled out FIRST so it is stripped on every path — including the
        // disabled-tracking early return below. `_tracking` is send-time
        // bookkeeping; handing it to TemplatePayloadBuilder would put an
        // unknown key in a LIVE Meta payload.
        $tracking = (isset($vars['_tracking']) && is_array($vars['_tracking'])) ? $vars['_tracking'] : [];
        unset($vars['_tracking']);

        if (!LinkTracker::enabled()) return $vars;

        $context = [
            'workspace_id' => $tpl->workspace_id,
            'template_id'  => $tpl->id,
            'phone'        => preg_replace('/\D+/', '', $toNumber),
            // broadcast_id / contact_id / message_id are merged in by
            // the caller (e.g. BroadcastsController) via the vars
            // `_tracking` key — kept optional so unit tests stay clean.
        ];
        if ($tracking) {
            $context = array_merge($context, $tracking);
        }

        foreach (($vars['buttons'] ?? []) as $i => $btn) {
            $sub = strtolower((string) ($btn['sub_type'] ?? ''));
            $val = (string) ($btn['value'] ?? '');
            if ($sub === 'url' && filter_var($val, FILTER_VALIDATE_URL)) {
                $vars['buttons'][$i]['value'] = LinkTracker::wrap($val, $context);
            }
        }

        foreach (($vars['cards'] ?? []) as $cardIdx => $card) {
            foreach (($card['buttons'] ?? []) as $i => $btn) {
                $sub = strtolower((string) ($btn['sub_type'] ?? ''));
                $val = (string) ($btn['value'] ?? '');
                if ($sub === 'url' && filter_var($val, FILTER_VALIDATE_URL)) {
                    $vars['cards'][$cardIdx]['buttons'][$i]['value'] = LinkTracker::wrap($val, $context);
                }
            }
        }

        return $vars;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function qualityMeetsFloor(string $score, string $floor): bool
    {
        $rank = ['UNKNOWN' => 1, 'RED' => 0, 'YELLOW' => 2, 'GREEN' => 3];
        return ($rank[$score] ?? 1) >= ($rank[$floor] ?? 2);
    }

    private function capKey(WaTemplate $tpl): string
    {
        return 'waba_tpl_cap:' . $tpl->id . ':' . now()->format('Y-m-d');
    }

    /**
     * Resolve a Meta MEDIA ID for a media-header template, uploading the
     * template's sample image to Meta's /media endpoint if needed.
     *
     * Source order: the operator's own uploaded attachment_file (our disk) →
     * Meta's approved sample captured at import (header_sample_url). We download
     * the bytes and POST them to /{phone}/media, then cache the id per
     * (template, phone) for 20 days — Meta media ids live ~30. Using an id
     * (Meta-hosted) rather than a link means Meta never has to fetch OUR server,
     * which sidesteps the other cause of "132012 received UNKNOWN" (an
     * unreachable public URL). Returns null on any failure.
     */
    /**
     * @param ?string $overrideUrl Send-time media swap. When given it is used
     *                             INSTEAD of the template's own attachment /
     *                             Meta sample, and keys its own cache entry so
     *                             one send's swap can't poison another's.
     */
    private function resolveHeaderMediaId(WaTemplate $tpl, string $phoneId, string $token, string $version, ?string $overrideUrl = null): ?string
    {
        $sourceUrl = '';
        if ($overrideUrl !== null && trim($overrideUrl) !== '') {
            $sourceUrl = trim($overrideUrl);
        } elseif (!empty($tpl->attachment_file)) {
            $sourceUrl = (string) media_url($tpl->attachment_file);
        } elseif (!empty($tpl->header_sample_url)) {
            $sourceUrl = (string) $tpl->header_sample_url;
        }
        // TEMP DIAGNOSTIC — which of the two sources (if either) the template
        // actually has. Both empty → nothing was ever captured/uploaded, so the
        // send guard fires. `build` proves this version is the one running.
        \Log::info('tplSend:resolveHeader', [
            'build'           => 'header-sample-v1',
            'tpl'             => $tpl->id,
            'name'            => $tpl->name ?? null,
            'attachment_file' => $tpl->attachment_file ?: null,
            'sample_url'      => $tpl->header_sample_url ? substr((string) $tpl->header_sample_url, 0, 120) : null,
            'source'          => $sourceUrl !== '' ? substr($sourceUrl, 0, 120) : null,
        ]);
        if ($sourceUrl === '') return null;

        // Key is VERSIONED: the media id is cached 20 days, so the first upload of
        // a bad rendition poisoned every later send — the cache returned that id
        // and short-circuited the download/re-encode entirely (same id in the
        // payload run after run, no re-encode log). Bumping the version retires
        // every previously cached id without a manual cache flush; bump it again
        // if the upload pipeline below ever changes what bytes we send.
        $cacheKey = 'waba_tpl_hdr_media:v3:' . ($overrideUrl !== null && trim($overrideUrl) !== '' ? 'ovr' : $tpl->id) . ':' . $phoneId . ':' . md5($sourceUrl);
        $cached   = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        try {
            // Browser UA: Meta's CDN (scontent.whatsapp.net) answers header-less
            // server requests with an error page instead of the bytes, which then
            // surfaced downstream as a useless "Media upload error".
            $dl = Http::timeout(30)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                    'Accept'     => 'image/avif,image/webp,image/png,image/jpeg,*/*;q=0.8',
                ])
                ->get($sourceUrl);
            if (!$dl->successful() || $dl->body() === '') {
                Log::warning('[WABA-template-send] header sample download failed', [
                    'tpl' => $tpl->id, 'url' => mb_substr($sourceUrl, 0, 200), 'status' => $dl->status(),
                ]);
                return null;
            }
            // Guard: a CDN error page is HTML, not bytes we can upload. Without
            // this it sailed into /media and failed there with an opaque error.
            $dlType = strtolower(trim(explode(';', (string) ($dl->header('Content-Type') ?: ''))[0]));
            if ($dlType !== '' && str_starts_with($dlType, 'text/')) {
                Log::warning('[WABA-template-send] header sample is not media', [
                    'tpl' => $tpl->id, 'ctype' => $dlType, 'bytes' => strlen((string) $dl->body()),
                    'snip' => mb_substr((string) $dl->body(), 0, 200),
                ]);
                return null;
            }
            $bytes = $dl->body();

            // TEMP DIAGNOSTIC — the "PNG" we get back is a Guzzle multipart body
            // (magic 2d2d = "--", then Content-Disposition), which no CDN serves.
            // Log the FULL url actually requested, the final status and every
            // response header: that shows where the request really landed and
            // what really answered it. `url_len` catches a truncated stored URL
            // (the send log clips to 120 chars, so a cut value looks identical).
            \Log::warning('tplSend:download', [
                'build'      => 'dl-facts-v1',
                'tpl'        => $tpl->id,
                'url'        => $sourceUrl,
                'url_len'    => strlen($sourceUrl),
                'status'     => $dl->status(),
                'bytes'      => strlen($bytes),
                'headers'    => $dl->headers(),
                'body_head'  => preg_replace('/[^\x20-\x7e]/', '.', substr($bytes, 0, 180)),
            ]);

            // Some template authoring tools POST a /media-style multipart body
            // to Meta's RESUMABLE upload endpoint, which wants the raw binary.
            // Meta never validates it: it stores the whole envelope AS the
            // sample, approves the template, and serves it back as image/png.
            // Sending those bytes then fails async with 131053 "Image is
            // invalid" — Meta rejecting what Meta stored. The real file is a
            // part inside the envelope, so unwrap it and use the largest part
            // (the binary; the others are short scalars like type=image/png).
            if (str_starts_with($bytes, '--') && preg_match('/^--([^\r\n]{1,70})\r?\n/', $bytes, $m)) {
                $sep   = '--' . $m[1];
                $parts = explode($sep, $bytes);
                $best  = '';
                foreach ($parts as $part) {
                    $split = preg_split('/\r?\n\r?\n/', $part, 2);
                    if (count($split) !== 2) continue;
                    // Strip exactly the ONE CRLF that delimits the next
                    // boundary — a charlist rtrim would eat real trailing
                    // bytes of a binary whose file ends in 0x0d/0x0a/'-'.
                    $body = preg_replace('/\r?\n\z/', '', $split[1], 1);
                    if (strlen($body) > strlen($best)) $best = $body;
                }
                if ($best !== '' && @getimagesizefromstring($best) !== false) {
                    Log::warning('[WABA-template-send] header sample was a multipart envelope — unwrapped', [
                        'tpl' => $tpl->id, 'envelope_bytes' => strlen($bytes), 'file_bytes' => strlen($best),
                    ]);
                    $bytes = $best;
                }
            }

            // Content-type: trust the download header, else infer from the
            // template's declared header type.
            $mime = trim(explode(';', (string) ($dl->header('Content-Type') ?: ''))[0]);
            if ($mime === '' || str_contains($mime, 'octet-stream')) {
                $mime = match (strtoupper((string) $tpl->attachment_type)) {
                    'IMAGE'    => 'image/jpeg',
                    'VIDEO'    => 'video/mp4',
                    'DOCUMENT' => 'application/pdf',
                    default    => 'application/octet-stream',
                };
            }
            // Re-encode images to a baseline 8-bit RGB JPEG before upload.
            // /media accepts almost any bytes, but the SEND-time validator then
            // fails async with 131053 "Image is invalid ... PNG RGB/RGBA up to
            // 8 bit/channel" — Meta's own approved sample comes back in
            // renditions (16-bit/palette/CMYK) its own sender rejects. Flatten
            // onto white so alpha doesn't turn black, and hand Meta a format it
            // cannot argue with. Best-effort: if GD is absent or can't decode,
            // fall through and upload the original bytes unchanged.
            // Only a decoded (or re-encoded) image is worth caching — see the
            // Cache::put below.
            $validated = ! str_starts_with($mime, 'image/');
            if (str_starts_with($mime, 'image/') && function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($bytes);
                if ($src !== false) {
                    $validated = true;
                    $w = imagesx($src); $h = imagesy($src);
                    $flat = imagecreatetruecolor($w, $h);
                    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                    imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
                    ob_start();
                    $encoded = imagejpeg($flat, null, 90);
                    $jpeg = (string) ob_get_clean();
                    imagedestroy($src); imagedestroy($flat);
                    if ($encoded && $jpeg !== '') {
                        Log::info('[WABA-template-send] header re-encoded → jpeg', [
                            'tpl' => $tpl->id, 'from' => $mime, 'w' => $w, 'h' => $h,
                            'bytes_in' => strlen($bytes), 'bytes_out' => strlen($jpeg),
                        ]);
                        $bytes = $jpeg;
                        $mime  = 'image/jpeg';
                    }
                } else {
                    // TEMP DIAGNOSTIC — GD refused bytes the CDN labelled image/png.
                    // Either they are a real PNG this GD build can't read, or they
                    // aren't an image at all. `magic` settles it: 89504e47 = a true
                    // PNG (=> GD lacks PNG support), anything else => bad download.
                    $info = function_exists('gd_info') ? gd_info() : [];
                    Log::warning('[WABA-template-send] header sample not decodable as image', [
                        'build'       => 'hdr-magic-v1',
                        'tpl'         => $tpl->id,
                        'mime'        => $mime,
                        'bytes'       => strlen($bytes),
                        'magic'       => bin2hex(substr($bytes, 0, 8)),
                        'ascii'       => preg_replace('/[^\x20-\x7e]/', '.', substr($bytes, 0, 64)),
                        'getimagesize'=> @getimagesizefromstring($bytes) ?: null,
                        'gd_png'      => $info['PNG Support'] ?? null,
                        'gd_jpeg'     => $info['JPEG Support'] ?? null,
                        'gd_webp'     => $info['WebP Support'] ?? null,
                        'gd_version'  => $info['GD Version'] ?? null,
                    ]);
                }
            }

            $ext = match (true) {
                str_contains($mime, 'png')  => 'png',
                str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'mp4')  => 'mp4',
                str_contains($mime, 'pdf')  => 'pdf',
                default                     => 'bin',
            };

            $up = Http::withToken($token)
                ->attach('file', $bytes, 'header_' . $tpl->id . '.' . $ext, ['Content-Type' => $mime])
                ->timeout(60)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/media", [
                    'messaging_product' => 'whatsapp',
                ]);

            $id = (string) ($up->json('id') ?? '');
            if ($up->successful() && $id !== '') {
                // Cache ONLY a validated image. /media hands back an id for bytes
                // it never checks, so caching unconditionally pinned a doomed id
                // for 20 days: every later send replayed it, skipped the download
                // entirely and failed 131053 with no way to recover but a version
                // bump. An unvalidated id is still returned (this send may as well
                // try) but never stored, so the next attempt re-downloads.
                if ($validated) {
                    Cache::put($cacheKey, $id, now()->addDays(20));
                }
                Log::info('[WABA-template-send] header media uploaded → id', [
                    'tpl' => $tpl->id, 'phone' => $phoneId, 'media_id' => $id,
                    'validated' => $validated, 'cached' => $validated,
                ]);
                return $id;
            }
            Log::warning('[WABA-template-send] header media upload failed', [
                'tpl' => $tpl->id, 'status' => $up->status(), 'body' => mb_substr($up->body(), 0, 300),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WABA-template-send] header media resolve threw: ' . $e->getMessage(), ['tpl' => $tpl->id]);
        }
        return null;
    }

    private function errorHint(int $code, string $msg): string
    {
        return match ($code) {
            132000          => "Template parameter mismatch — your `vars` shape doesn't match what was submitted. Re-check placeholders.",
            132001          => "Template language not supported on this WABA: $msg",
            132005          => 'Template not found on Meta — it may have been deleted or never approved.',
            132007          => "Translation missing for this language: $msg",
            131026          => 'Recipient is not on WhatsApp.',
            131047          => '24-hour customer service window expired AND recipient has no active conversation. Templates are required to re-open.',
            131056          => 'Pair throttled — too many messages to this number recently. Back off and retry.',
            190             => 'Meta access token expired. Reconnect the WABA at /devices.',
            default         => "Meta error $code: $msg",
        };
    }

    private function fail(string $code, WaTemplate $tpl, string $error): array
    {
        return [
            'ok'          => false,
            'code'        => $code,
            'wamid'       => null,
            'error'       => $error,
            'template_id' => $tpl->id,
        ];
    }
}
