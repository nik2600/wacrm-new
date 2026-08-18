<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Models\WaOrder;
use App\Models\WaProduct;
use App\Models\WaProviderConfig;
use App\Services\StorefrontOrderParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound webhooks from Meta Cloud API and Twilio. We pattern-match
 * on the payload shape — no provider-specific routes — so a buyer
 * can configure either at the same URL.
 */
class WaWebhookController extends Controller
{
    /**
     * GET /webhooks/whatsapp/inbound  — Meta verification handshake.
     * Meta sends `?hub.mode=subscribe&hub.verify_token=<TOKEN>&hub.challenge=<RANDOM>`.
     * If the token matches our stored value, echo back the challenge.
     */
    public function verify(Request $request): \Illuminate\Http\Response
    {
        $expected = (string) SystemSetting::get('waba_webhook_verify_token', '');
        $mode     = $request->query('hub_mode', $request->query('hub.mode'));
        $token    = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge= $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            return response($challenge, 200);
        }
        return response('forbidden', 403);
    }

    /**
     * POST /webhooks/whatsapp/inbound  — both Meta + Twilio inbound.
     */
    public function receive(Request $request): JsonResponse
    {
        // Twilio posts as form-encoded; Meta posts JSON. Detect by content-type.
        if (str_contains($request->header('Content-Type', ''), 'application/json')) {
            return $this->receiveMeta($request);
        }
        return $this->receiveTwilio($request);
    }

    private function receiveMeta(Request $request): JsonResponse
    {
        // Meta signs every webhook with X-Hub-Signature-256. We need the
        // Meta App Secret to verify it. Admins paste it in the admin panel
        // (stored as `waba_app_secret`, encrypted at rest) — that MUST win,
        // because env META_APP_SECRET is almost always unset on these
        // installs, which is exactly the "META_APP_SECRET not set — refusing
        // webhook" log the operator sees even after saving it in admin. Fall
        // back to the env var for older single-tenant deploys that used it.
        $content = (string) $request->getContent();

        // DIAGNOSTIC — fires for EVERY Meta POST, before any signature/config
        // check, so we can tell "Meta never called us" from "we dropped it".
        // If this line is ABSENT from laravel.log when you message the number,
        // Meta is not delivering the webhook (subscription/verify/publish), and
        // the fix is in the Meta App dashboard, not the code. has_messages=false
        // with has_statuses=true means only delivery receipts are subscribed —
        // subscribe the `messages` field in App Dashboard → WhatsApp → Webhooks.
        try {
            $peek = json_decode($content, true) ?: [];
            // Surface the STATUS VALUE (+ any failure code/reason) on status
            // webhooks — a message showing "sent" then "failed" here is Meta
            // rejecting it AFTER accepting the API call (e.g. an unsupported
            // audio container). Without this you can't tell a delivered
            // message from a silently-failed one just from the log.
            $st0 = data_get($peek, 'entry.0.changes.0.value.statuses.0', []);
            \Log::info('[WA-webhook] Meta POST received', [
                'len'             => strlen($content),
                'field'           => data_get($peek, 'entry.0.changes.0.field', '(none)'),
                'has_messages'    => !empty(data_get($peek, 'entry.0.changes.0.value.messages')),
                'has_statuses'    => !empty(data_get($peek, 'entry.0.changes.0.value.statuses')),
                'status'          => data_get($st0, 'status', '(none)'),
                'status_wamid'    => data_get($st0, 'id', '(none)'),
                'error_code'      => data_get($st0, 'errors.0.code', '(none)'),
                'error_title'     => data_get($st0, 'errors.0.title', '(none)'),
                'error_detail'    => data_get($st0, 'errors.0.error_data.details', data_get($st0, 'errors.0.message', '(none)')),
                'from'            => data_get($peek, 'entry.0.changes.0.value.messages.0.from', '(none)'),
                'text'            => data_get($peek, 'entry.0.changes.0.value.messages.0.text.body', '(none)'),
                'phone_number_id' => data_get($peek, 'entry.0.changes.0.value.metadata.phone_number_id', '(none)'),
                'sig_present'     => $request->header('X-Hub-Signature-256') ? true : false,
            ]);
        } catch (\Throwable $e) { /* never block on diagnostics */ }

        $given   = (string) $request->header('X-Hub-Signature-256', '');
        $verify  = fn (string $s): bool => $s !== '' && hash_equals('sha256=' . hash_hmac('sha256', $content, $s), $given);

        // Primary: the admin/platform app secret (the common single-app case).
        $adminSecret = (string) (\App\Models\SystemSetting::get('waba_app_secret', '') ?: env('META_APP_SECRET', ''));
        $passed      = $verify($adminSecret);

        // Fallback: a WABA connected with override_callback_uri is signed by the
        // TOKEN-OWNER's app, NOT ours — so match against that config's stored
        // app_secret. Resolve the config by the phone_number_id in the (still
        // unverified) payload, then verify BEFORE we trust anything in it.
        $sigSource = $passed ? 'admin' : null;

        if (!$passed) {
            $peekBody = json_decode($content, true) ?: [];
            $pid      = (string) data_get($peekBody, 'entry.0.changes.0.value.metadata.phone_number_id', '');
            $waba     = (string) data_get($peekBody, 'entry.0.id', '');

            $wabaCfgs = WaProviderConfig::query()->where('provider', 'waba')->get();

            // Match on waba_id OR phone_number_id. This used to key on
            // phone_number_id ALONE, while the diagnostics below resolve the
            // same config on either id — so a config carrying only waba_id in
            // meta_json (the manual-connect and Embedded-Signup shapes both do
            // this) never had its app_secret tried at all. The log then read
            // "known_waba_config: true, config_has_secret: false" and every
            // inbound message from that number was rejected even though the
            // right secret was sitting in the row.
            $cfg = ($pid !== '' || $waba !== '')
                ? $wabaCfgs->first(function ($c) use ($pid, $waba) {
                    $m = (array) ($c->meta_json ?? []);
                    return ($waba !== '' && (string) ($m['waba_id'] ?? '') === $waba)
                        || ($pid !== '' && (string) ($m['phone_number_id'] ?? '') === $pid);
                })
                : null;

            if ($cfg) {
                $passed = $verify((string) ($cfg->creds()['app_secret'] ?? ''));
                if ($passed) $sigSource = 'config:' . $cfg->id;
            }

            // Last resort: try every stored WABA app_secret. On a multi-tenant
            // install each customer may bring their OWN Meta App, and a payload
            // can carry ids that match no config (a number re-added under a new
            // id, meta_json not yet backfilled). This is NOT a weakening of the
            // check — the HMAC must still verify, and a signature that validates
            // against a secret we hold proves the payload came from that app.
            if (!$passed) {
                foreach ($wabaCfgs as $c) {
                    $s = (string) ($c->creds()['app_secret'] ?? '');
                    if ($s === '' || $s === $adminSecret) continue;
                    if ($verify($s)) {
                        $passed    = true;
                        $sigSource = 'config-scan:' . $c->id;
                        break;
                    }
                }
            }

            if ($passed) {
                \Log::info('[WA-webhook] signature verified via fallback secret', [
                    'source'          => $sigSource,
                    'phone_number_id' => $pid ?: '(none)',
                    'waba_id'         => $waba ?: '(none)',
                ]);
            }
        }

        // Resolve the payload's WABA id + phone-number id (still unverified) so we
        // can (a) log useful diagnostics on a mismatch and (b) decide whether the
        // webhook is for a number we actually have connected.
        $decoded = json_decode($content, true) ?: [];
        $pidTop  = (string) data_get($decoded, 'entry.0.changes.0.value.metadata.phone_number_id', '');
        $wabaTop = (string) data_get($decoded, 'entry.0.id', '');
        $knownCfg = ($wabaTop !== '' || $pidTop !== '')
            ? WaProviderConfig::query()->where('provider', 'waba')->get()->first(function ($c) use ($wabaTop, $pidTop) {
                $m = (array) ($c->meta_json ?? []);
                return ($wabaTop !== '' && (string) ($m['waba_id'] ?? '') === $wabaTop)
                    || ($pidTop !== '' && (string) ($m['phone_number_id'] ?? '') === $pidTop);
            })
            : null;

        if (!$passed) {
            // DEEP diagnostics — NEVER log secret values, only presence/length and
            // signature PREFIXES, so we can pinpoint WHY it mismatched (wrong
            // secret? encrypted-not-decrypted? unknown app? no signature header?).
            \Log::warning('[WA-webhook] Meta signature mismatch — diagnostics', [
                'admin_secret_set'   => $adminSecret !== '',
                'admin_secret_len'   => strlen($adminSecret),
                'given_sig'          => $given !== '' ? substr($given, 0, 22) . '…' : '(no X-Hub-Signature-256 header)',
                'computed_admin_sig' => $adminSecret !== '' ? substr('sha256=' . hash_hmac('sha256', $content, $adminSecret), 0, 22) . '…' : '(no secret)',
                'phone_number_id'    => $pidTop ?: '(none in payload)',
                'waba_id'            => $wabaTop ?: '(none in payload)',
                'known_waba_config'  => (bool) $knownCfg,
                'config_has_secret'  => $knownCfg ? ((string) ($knownCfg->creds()['app_secret'] ?? '') !== '') : false,
                // creds() swallows a decrypt failure and returns [], so an
                // app_secret that IS stored but cannot be read (row written
                // under a different APP_KEY, or truncated by a too-small
                // column) looked identical to "never entered". These two
                // separate that: credentials present but creds() empty means
                // the value is there and undecryptable — reset it, don't retype.
                'creds_blob_present' => $knownCfg ? !empty($knownCfg->credentials_json) : false,
                'creds_decoded_ok'   => $knownCfg ? ($knownCfg->creds() !== []) : false,
                // How many OTHER configs held a secret we could have tried.
                'secrets_available'  => WaProviderConfig::query()->where('provider', 'waba')->get()
                    ->filter(fn ($c) => (string) ($c->creds()['app_secret'] ?? '') !== '')->count(),
                'workspace_id'       => $knownCfg->workspace_id ?? null,
                'body_len'           => strlen($content),
            ]);

            // OWNERSHIP FALLBACK — for WABAs connected with override_callback_uri.
            //
            // Those numbers stay subscribed to the CUSTOMER's Meta App, so Meta
            // signs their webhooks with the customer's App Secret. We do not hold
            // it (and Meta exposes no API that returns one), so the HMAC above can
            // never pass for them — every inbound message is rejected while
            // outbound keeps working, because outbound uses the access token.
            //
            // Instead of trusting the payload's self-declared ids, we ask Meta:
            // using the access token WE already hold for that config, fetch the
            // phone_number_id the payload claims. Meta only answers if that token
            // really governs that number, so a 200 proves the number is ours.
            //
            // This is WEAKER than the signature and deliberately so — it proves
            // "this number belongs to us", not "Meta sent this exact POST". An
            // attacker who knows the webhook URL AND a live phone_number_id could
            // still inject a message. It is enabled by default because inbound is
            // otherwise dead for these numbers; set waba_verify_by_ownership=false
            // to enforce signature-only.
            if (!$passed
                && $knownCfg
                && (bool) \App\Models\SystemSetting::get('waba_verify_by_ownership', true)) {

                if ($this->confirmsOwnership($knownCfg, $pidTop)) {
                    $passed = true;
                    \Log::warning('[WA-webhook] ACCEPTED via ownership check — signature could NOT be verified', [
                        'workspace_id'    => $knownCfg->workspace_id,
                        'waba_id'         => $wabaTop ?: '(none)',
                        'phone_number_id' => $pidTop ?: '(none)',
                        'why'             => 'no app_secret stored for this WABA; token-confirmed number ownership instead',
                    ]);
                }
            }
        }

        if (!$passed) {
            // FAIL CLOSED. The X-Hub-Signature-256 HMAC is the ONLY authentication
            // on this POST route: it is CSRF-exempt, unauthenticated, and the
            // verify-token is checked ONLY on the GET handshake — NOT here. The
            // waba_id / phone_number_id in the body are semi-public Meta numeric
            // identifiers, not secrets, so a "known WABA" match proves nothing
            // about the caller. Previously we processed unverified payloads for a
            // known WABA, which let an unauthenticated attacker forge inbound
            // messages + fake orders and trigger the victim workspace's outbound
            // sends. We now reject whenever the signature does not verify against
            // a configured app secret — for known AND unknown WABAs alike.
            //
            // If verification is failing for a LEGITIMATE number, the fix is to
            // store the correct App Secret (admin panel → waba_app_secret, or the
            // override_callback_uri config's app_secret) — the diagnostics above
            // pinpoint which secret is missing/wrong. We never trust unsigned data.
            \Log::warning('[WA-webhook] signature unverified — REJECTING (store the correct App Secret to restore inbound)', [
                'known_waba_config' => (bool) $knownCfg,
                'workspace_id'      => $knownCfg->workspace_id ?? null,
                'waba_id'           => $wabaTop ?: '-',
                'phone_number_id'   => $pidTop ?: '-',
            ]);
            return response()->json(['ok' => false, 'error' => 'bad signature'], 401);
        }

        $payload = $request->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            $wabaId = $entry['id'] ?? null;
            $config = WaProviderConfig::query()
                ->where('provider', 'waba')
                ->whereJsonContains('meta_json->waba_id', (string) $wabaId)
                ->first();
            // Tolerant fallback: whereJsonContains is type-strict (int vs string)
            // and can MISS a config that genuinely exists — which would silently
            // drop the customer's message. Re-match in PHP by waba_id OR by the
            // phone_number_id in the payload before giving up.
            if (!$config) {
                $entryPid = (string) data_get($entry, 'changes.0.value.metadata.phone_number_id', '');
                $config = WaProviderConfig::query()->where('provider', 'waba')->get()
                    ->first(function ($c) use ($wabaId, $entryPid) {
                        $m = (array) ($c->meta_json ?? []);
                        return ((string) ($m['waba_id'] ?? '') === (string) $wabaId)
                            || ($entryPid !== '' && (string) ($m['phone_number_id'] ?? '') === $entryPid);
                    });
            }
            if (!$config) {
                // No config for this WABA. INBOUND messages genuinely cannot be
                // handled — there is no workspace to file them under. DELIVERY
                // STATUSES are different: campaign and broadcast recipient rows
                // are matched by wamid ALONE (whatsapp_message_id / wa_message_id,
                // both globally unique), so they never needed the config.
                //
                // Dropping the whole entry meant a send from a number whose
                // config no longer resolves — renamed WABA, rotated
                // phone_number_id, a config edited after the send — sat at "sent"
                // forever with DELIVERED 0, even though the recipient had the
                // message in hand. The send itself works (it uses the phone_id
                // directly); only the receipt was being thrown away.
                $statusOnly = [];
                foreach ($entry['changes'] ?? [] as $change) {
                    foreach (data_get($change, 'value.statuses', []) ?? [] as $st) {
                        if (!empty($st['id'])) $statusOnly[] = $st;
                    }
                }

                foreach ($statusOnly as $st) {
                    // applyStatus's messages-table branch IS workspace-scoped, so
                    // recover the owner from the wamid. 0 means "not ours" — the
                    // wamid-only branches still run and simply match nothing.
                    $this->applyStatus($this->workspaceIdForWamid((string) $st['id']), $st);
                }

                \Log::warning('[WA-webhook] inbound dropped — no WaProviderConfig matches this WABA', [
                    'waba_id'         => (string) $wabaId,
                    'phone_number_id' => (string) data_get($entry, 'changes.0.value.metadata.phone_number_id', ''),
                    'statuses_kept'   => count($statusOnly),
                ]);
                continue;
            }

            $workspaceId = $config->workspace_id;

            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $field = (string) ($change['field'] ?? '');

                // Template lifecycle webhooks — handled first because
                // they don't share the messages[] / statuses[] shape.
                // Meta sends four template-related fields; we mirror
                // every state change back to wa_templates.meta_status
                // + quality_score so the detail page reflects truth
                // without depending on the AJAX poll.
                if (in_array($field, ['message_template_status_update', 'message_template_quality_update', 'template_category_update', 'message_template_components_update'], true)) {
                    $this->applyTemplateUpdate($field, $value, $config);
                    continue;
                }

                // WhatsApp Business username lifecycle — Meta flips the status
                // (reserved → approved once usernames roll out in the region) or
                // signals a change/delete made from the WhatsApp Business app.
                // Mirror it onto the config so the /devices health page badge is
                // always live without a manual re-check.
                if ($field === 'business_username_update') {
                    try { $this->applyUsernameUpdate($value, $config); }
                    catch (\Throwable $e) { \Log::warning('[WABA-USERNAME] webhook apply failed: ' . $e->getMessage()); }
                    continue;
                }

                // WhatsApp Business Calling — Meta delivers `calls` events (incl.
                // permission_update when the customer Accepts/Declines a call
                // permission request, and connect/terminate) to this SAME callback
                // URL as messages. Forward to the calling pipeline so permission is
                // RECORDED — otherwise every Call click keeps re-sending a permission
                // request and hits Meta's "limit reached" (138009).
                if ($field === 'calls') {
                    \Log::info('[WA-CALLING][trace] calls webhook arrived at main receiver → forwarding', ['entry_id' => $entry['id'] ?? null]);
                    try { app(\App\Http\Controllers\WaCallingWebhookController::class)->ingestCallsValue($value, (string) ($entry['id'] ?? '')); }
                    catch (\Throwable $e) { \Log::warning('[WABA-INBOUND] calls webhook forward failed: ' . $e->getMessage()); }
                    continue;
                }

                // ── WhatsApp Coexistence webhooks ───────────────────────
                // A coexistence number runs on BOTH the WhatsApp Business app
                // and the Cloud API at once. Meta mirrors the app side to us
                // via three fields that do NOT use the messages[]/statuses[]
                // shape. Without handling them, app-typed replies never reach
                // the team inbox, the business's app contacts never sync, and
                // pre-onboarding chat history never imports — i.e. it stops
                // being true coexistence and becomes "a number on the API".
                //   smb_message_echoes → messages the business sent FROM the app
                //   smb_app_state_sync → the business's app contacts (add/update)
                //   history            → past messages, pushed after onboarding
                if ($field === 'smb_message_echoes') {
                    foreach ($value['message_echoes'] ?? [] as $echo) {
                        try { $this->captureOutboundEcho($workspaceId, $echo, $value, 'waba'); }
                        catch (\Throwable $e) { \Log::warning('[WA-COEX] echo ingest failed: ' . $e->getMessage()); }
                    }
                    continue;
                }
                if ($field === 'smb_app_state_sync') {
                    try { $this->applyAppStateSync($workspaceId, $value); }
                    catch (\Throwable $e) { \Log::warning('[WA-COEX] app_state_sync failed: ' . $e->getMessage()); }
                    continue;
                }
                if ($field === 'history') {
                    try { $this->applyHistorySync($workspaceId, $value); }
                    catch (\Throwable $e) { \Log::warning('[WA-COEX] history sync failed: ' . $e->getMessage()); }
                    continue;
                }

                // Inbound messages — including `type:order` and
                // interactive form submissions (nfm_reply).
                foreach ($value['messages'] ?? [] as $msg) {
                    // Dedup on wamid. Meta retries webhooks aggressively
                    // (up to several times on 5xx + occasionally on 200).
                    // We key by JSON path `meta.wamid` since the messages
                    // table doesn't have a dedicated column. Idempotent.
                    $wamid = (string) ($msg['id'] ?? '');
                    if ($wamid !== '') {
                        // Dedup against the ACTUAL inbox table + key. Team-inbox
                        // bubbles live in `inbox_messages` keyed by
                        // meta->wa_message_id — NOT the legacy `messages` table /
                        // `meta->wamid`, which are never written for a WABA inbound.
                        // The old check therefore never matched, so Meta's retries
                        // (now amplified by app-level + override webhook delivery)
                        // produced duplicate inbound bubbles. Idempotent per wamid.
                        $already = \App\Models\InboxMessage::query()
                            ->whereJsonContains('meta->wa_message_id', $wamid)
                            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
                            ->exists();
                        if ($already) {
                            \Log::info('[WA-webhook] dedup — wa_message_id already ingested', ['wamid' => $wamid]);
                            continue;
                        }
                    }

                    $type = $msg['type'] ?? null;
                    if ($type === 'order') {
                        $this->captureOrderFromWaba($workspaceId, $msg, $value);
                    } elseif ($type === 'interactive'
                        && (($msg['interactive']['type'] ?? '') === 'nfm_reply')) {
                        // Form submission — route to the form service
                        // which writes wa_form_submissions + resumes
                        // the paused flow on Node. Wrapped so a
                        // resolver bug never makes Meta retry.
                        try {
                            app(\App\Services\Forms\WaFormSubmissionService::class)
                                ->ingest($workspaceId, $msg, $value);
                        } catch (\Throwable $e) {
                            \Log::warning('[WA-webhook] form submission ingest crashed: ' . $e->getMessage());
                        }
                    } else {
                        $this->captureInboundMessage($workspaceId, $msg, $value, 'waba');
                    }
                }

                // Status updates (sent / delivered / read / failed)
                foreach ($value['statuses'] ?? [] as $st) {
                    $this->applyStatus($workspaceId, $st);
                }

                // ── WhatsApp Pay — in-chat payment result (WP-2) ────────────
                // Meta delivers the payment outcome in statuses[] with type:payment
                // (verified vs official doc). Hand the whole value to the service,
                // which CONFIRMS via Payment Lookup (never trusts the webhook alone —
                // Meta's rule) then settles the order. Wrapped so a bug never makes
                // Meta retry. (value.payment kept as a defensive fallback.)
                if (collect($value['statuses'] ?? [])->contains(fn ($s) => ($s['type'] ?? '') === 'payment')
                    || !empty($value['payment'])) {
                    try {
                        app(\App\Services\Waba\WhatsAppPayService::class)->applyPaymentWebhook($workspaceId, $value);
                    } catch (\Throwable $e) {
                        \Log::warning('[WAPAY] webhook handler crashed: ' . $e->getMessage());
                    }
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function captureOrderFromWaba(int $workspaceId, array $msg, array $value): void
    {
        $order = $msg['order'] ?? null;
        if (!$order) return;

        // ── Resolve retailer_ids → real products in one query ──
        // Meta's item_price arrives as DECIMAL MAJOR units (e.g. 10.99)
        // — we convert to integer minor on the fly so the wa_order_items
        // rows stay consistent with the rest of the schema.
        $lineRows = $order['product_items'] ?? [];
        $retailerIds = array_filter(array_map(fn ($l) => $l['product_retailer_id'] ?? null, $lineRows));
        $productsByRid = [];
        if (!empty($retailerIds)) {
            $hits = \App\Models\WaProduct::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($retailerIds) {
                    $q->whereIn('meta_retailer_id', $retailerIds)
                      ->orWhereIn('sku', $retailerIds);
                })->get();
            foreach ($hits as $p) {
                $key = $p->meta_retailer_id ?: $p->sku;
                if ($key) $productsByRid[$key] = $p;
            }
        }

        $items = [];           // legacy items_json shape (kept for old renderers)
        $orderItems = [];      // new wa_order_items rows
        $totalMinor = 0;
        $currency = 'INR';
        foreach ($lineRows as $line) {
            $rid = $line['product_retailer_id'] ?? '';
            $price = (int) round(((float) ($line['item_price'] ?? 0)) * 100);
            $qty   = (int) ($line['quantity'] ?? 1);
            $totalMinor += $price * $qty;
            $currency = $line['currency'] ?? $currency;
            $product = $productsByRid[$rid] ?? null;

            $items[] = [
                'retailer_id' => $rid,
                'product_id'  => $product?->id,
                'name'        => $product?->name ?? $rid ?? 'Item',
                'image'       => $product?->image_url,
                'qty'         => $qty,
                'price_minor' => $price,
                'price'       => $price, // alias the existing UI uses
                'currency'    => $currency,
            ];
            $orderItems[] = [
                'retailer_id'   => $rid ?: ('wsn-unknown-' . count($orderItems)),
                'product_id'    => $product?->id,
                'name'          => $product?->name ?? ($rid ?? 'Item'),
                'image_url'     => $product?->image_url,
                'quantity'      => $qty,
                'price_minor'   => $price,
                'currency_code' => $currency,
                'meta_json'     => ['raw' => $line],
            ];
        }

        $contact = $value['contacts'][0] ?? [];
        $createdId = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($workspaceId, $msg, $contact, $items, $orderItems, $totalMinor, $currency, $order, &$createdId) {
            $created = \App\Models\WaOrder::create([
                'workspace_id'   => $workspaceId,
                'source'         => 'waba',
                'customer_phone' => $msg['from'] ?? '',
                'customer_name'  => $contact['profile']['name'] ?? null,
                'items_json'     => $items,
                'total_minor'    => $totalMinor,
                'currency_code'  => $currency,
                'status'         => 'new',
                'wa_message_id'  => $msg['id'] ?? null,
                'notes'          => $order['text'] ?? null,
                'meta_json'      => ['catalog_id' => $order['catalog_id'] ?? null, 'product_text' => $order['text'] ?? null],
            ]);
            $createdId = $created->id;
            foreach ($orderItems as $row) {
                $created->lineItems()->create($row);
            }
        });

        // Commerce-flow loop closer for WABA — if a flow's commerce node
        // is paused waiting on this customer, advance through `purchased`.
        // Phone-based lookup because Meta does NOT echo the
        // biz_opaque_callback_data on inbound order messages. Wrapped so
        // a resolver bug never causes Meta to retry the webhook.
        try {
            \App\Services\Commerce\FlowSessionResolver::resumeFromWabaCatalogOrderByPhone(
                $workspaceId,
                (string) ($msg['from'] ?? ''),
                [
                    'id'       => $createdId,
                    'total'    => $totalMinor / 100,
                    'currency' => $currency,
                    'provider' => 'whatsapp_shop',
                ],
            );
        } catch (\Throwable $e) {
            \Log::warning('[WA-webhook] WABA flow-resume crashed (swallowed): ' . $e->getMessage());
        }

        // Close the cart loop — acknowledge the order to the buyer (summary +
        // total + optional pay link) so they're not left in silence. Wrapped
        // so a send failure never makes Meta retry (and duplicate) the order.
        if ($createdId) {
            $row = \App\Models\WaOrder::find($createdId);
            // WhatsApp Pay — opt-in: send the native in-chat order_details charge
            // instead of a plain ack when auto_charge is enabled. Falls back to
            // the normal acknowledge (summary + optional pay link) otherwise.
            $charged = false;
            if ($row) {
                try { $charged = app(\App\Services\Waba\WhatsAppPayService::class)->maybeAutoCharge($row); }
                catch (\Throwable $e) { \Log::warning('[WAPAY] catalog auto-charge failed (swallowed): ' . $e->getMessage()); }
            }
            if ($row && !$charged) {
                try { app(\App\Services\WhatsAppCatalog\CatalogOrderService::class)->acknowledge($row); }
                catch (\Throwable $e) { \Log::warning('[WA-webhook] catalog order ack failed (swallowed): ' . $e->getMessage()); }
            }
        }
    }

    /**
     * Apply a `message_template_*` webhook to the local `wa_templates`
     * row. Meta sends FOUR field variants:
     *
     *   message_template_status_update     event: APPROVED, REJECTED, PENDING, DISABLED,
     *                                             PAUSED, IN_APPEAL, LIMIT_EXCEEDED, FLAGGED,
     *                                             PENDING_DELETION, DELETED
     *   message_template_quality_update    previous_quality_score, new_quality_score (GREEN/YELLOW/RED)
     *   template_category_update           previous_category, new_category (MARKETING/UTILITY/AUTHENTICATION)
     *   message_template_components_update components (the new shape if Meta auto-edited)
     *
     * Identifier resolution prefers `message_template_id` (Meta's stable
     * id), falls back to `(name, language)` for older webhooks that omit
     * the id. We scope to this WABA's `provider_config_id` so a
     * spoofed wabaId can't poison another workspace's templates.
     */
    private function applyTemplateUpdate(string $field, array $value, \App\Models\WaProviderConfig $cfg): void
    {
        $metaId = (string) ($value['message_template_id'] ?? '');
        $name   = (string) ($value['message_template_name'] ?? '');
        $lang   = (string) ($value['message_template_language'] ?? '');

        $tpl = null;
        if ($metaId !== '') {
            $tpl = \App\Models\WaTemplate::where('provider_config_id', $cfg->id)
                ->where('meta_template_id', $metaId)->first();
        }
        if (!$tpl && $name !== '') {
            // Encrypted column — load all rows for this WABA and match
            // in PHP. Cheap because per-WABA template count is small.
            $candidates = \App\Models\WaTemplate::where('provider_config_id', $cfg->id)->get();
            $needle   = mb_strtolower($name);
            $langNorm = strtolower($lang);
            $langBase = explode('_', $langNorm)[0];   // en_US -> en
            foreach ($candidates as $c) {
                if (mb_strtolower((string) $c->template_name) !== $needle) continue;
                // Tolerate region-code drift (our `en` vs Meta `en_US`) so a row
                // stuck without a meta_template_id still resolves + updates.
                $cLang = strtolower((string) $c->language);
                $langOk = $lang === '' || $cLang === $langNorm
                    || ($langBase !== '' && explode('_', $cLang)[0] === $langBase);
                if ($langOk) { $tpl = $c; break; }
            }
        }
        if (!$tpl) {
            \Log::info('[WA-template-webhook] no matching template', compact('field', 'metaId', 'name', 'lang'));
            return;
        }

        $patch = ['last_synced_at' => now()];
        // Backfill the Meta id onto a row that was stuck without one (matched by
        // name above) so every FUTURE webhook + the sweeper/refresh poll match it
        // directly instead of relying on the fragile name lookup each time.
        if ($metaId !== '' && !$tpl->meta_template_id) {
            $patch['meta_template_id'] = $metaId;
        }

        switch ($field) {
            case 'message_template_status_update':
                $event = strtoupper((string) ($value['event'] ?? ''));
                $reason = strtoupper((string) ($value['reason'] ?? ''));
                $patch['meta_status']           = $event;
                $patch['rejection_reason_code'] = $event === 'REJECTED' ? ($reason ?: 'NONE') : null;
                if ($event === 'PAUSED') {
                    // `other_info.title` sometimes carries a pause duration;
                    // safer to set a conservative 24h auto-unpause window.
                    $patch['paused_until'] = now()->addDay();
                }
                // Mirror to the local `status` column so existing list
                // filters keep working unchanged.
                $patch['status'] = match ($event) {
                    'APPROVED'              => 'approved',
                    'REJECTED'              => 'rejected',
                    'PENDING', 'IN_APPEAL'  => 'pending',
                    default                 => $tpl->status,
                };
                if ($event === 'APPROVED' && !$tpl->approved_at) {
                    $patch['approved_at'] = now();
                }
                break;

            case 'message_template_quality_update':
                // Meta sends either a string or a nested object — the
                // sweeper has the canonical normaliser, reuse it so
                // both code paths parse identically.
                $score = \App\Services\Waba\TemplateSyncSweeper::normalizeQualityScore(
                    $value['new_quality_score'] ?? $value['quality_score'] ?? null,
                    $tpl->quality_score
                );
                if ($score !== 'UNKNOWN') $patch['quality_score'] = $score;
                // RED quality auto-pauses non-AUTH templates for 24h —
                // ban-prevention rail. Re-checked on next sweep.
                if ($score === 'RED' && $tpl->template_type !== 'auth') {
                    $patch['paused_until'] = now()->addDay();
                }
                break;

            case 'template_category_update':
                $newCat = strtoupper((string) ($value['new_category'] ?? ''));
                if ($newCat !== '') $patch['meta_category'] = $newCat;
                break;

            case 'message_template_components_update':
                // Meta edited the components — usually footer normalization
                // or removing a duplicate variable. We don't overwrite the
                // local copy (the user authored it) but we DO log so the
                // admin can see Meta touched it.
                \Log::info('[WA-template-webhook] components edited by Meta', [
                    'tpl' => $tpl->id, 'components' => $value['components'] ?? null,
                ]);
                break;
        }

        $tpl->update($patch);
        \Log::info('[WA-template-webhook] applied', ['field' => $field, 'tpl' => $tpl->id, 'patch' => array_keys($patch)]);
    }

    /**
     * business_username_update webhook → mirror the live username status onto the
     * WABA config's meta_json so the /devices health page badge is always current.
     * Meta pushes this when a reserved handle becomes approved (usernames went live
     * in-region), or when it was changed / deleted from the WhatsApp Business app.
     * Tolerant of Meta's field naming (username | requested_username, status |
     * username_status) so a minor payload change can't silently drop the update.
     */
    private function applyUsernameUpdate(array $value, \App\Models\WaProviderConfig $config): void
    {
        $username = trim((string) ($value['username'] ?? ($value['requested_username'] ?? '')));
        $status   = strtolower((string) ($value['status'] ?? ($value['username_status'] ?? '')));
        $meta     = is_array($config->meta_json) ? $config->meta_json : [];

        if (in_array($status, ['deleted', 'removed', 'released'], true)) {
            unset($meta['wa_username'], $meta['wa_username_status'], $meta['wa_username_at']);
        } else {
            if ($username !== '') $meta['wa_username'] = $username;
            $meta['wa_username_status'] = in_array($status, ['reserved', 'approved'], true)
                ? $status
                : (string) ($meta['wa_username_status'] ?? 'reserved');
            $meta['wa_username_at'] = now()->toIso8601String();
        }
        $config->forceFill(['meta_json' => $meta])->save();

        \Log::info('[WABA-USERNAME] webhook applied', [
            'config_id' => $config->id,
            'username'  => $username,
            'status'    => $status ?: '(none)',
        ]);
    }

    private function captureInboundMessage(int $workspaceId, array $msg, array $value, string $provider = 'waba'): void
    {
        $contact = $value['contacts'][0] ?? [];
        $fromPhone = (string) ($msg['from'] ?? '');
        if ($fromPhone === '') return;
        $senderName = (string) ($contact['profile']['name'] ?? '');
        // WhatsApp username (Meta's 2026 rollout): once enabled, the customer's
        // @username arrives on the contact object in every inbound webhook. Capture
        // it now so the inbox can show "@handle" beside the number. No-op until Meta
        // starts sending it — safe to ship ahead of GA. (Business-scoped user id
        // rides on wa_id.)
        $waUsername = trim((string) ($contact['username'] ?? ''));
        // Fall back to the customer's SAVED contact name when Meta sends no profile
        // name, so the inbox shows "John" instead of a masked number — and so name
        // search works. Feeds both the create and backfill title paths below.
        if ($senderName === '') {
            $senderName = (string) (\App\Models\Contact::nameForPhone(
                (int) $workspaceId,
                preg_replace('/\D+/', '', $fromPhone)
            ) ?? '');
        }

        // DIAGNOSTIC — confirms the inbound was parsed and reached capture (i.e.
        // it passed signature + config matching). Pair it with the
        // '[WA-webhook] Meta POST received' line and '[WABA-INBOUND] flow bridge'
        // below to see exactly how far an inbound message gets.
        \Log::info('[WABA-INBOUND] capture', [
            'workspace_id' => $workspaceId,
            'provider'     => $provider,
            'from'         => $fromPhone,
            'type'         => (string) ($msg['type'] ?? ''),
            'text'         => (string) ($msg['text']['body'] ?? ''),
            'to_number'    => (string) data_get($value, 'metadata.display_phone_number', ''),
        ]);

        // fromMe ECHO DETECTION — if the inbound's `from` matches any
        // of this workspace's own connected provider phone numbers,
        // it's the operator replying from their personal phone (WABA
        // Business app on Meta, or the operator's mobile sharing a
        // Twilio number). Route it as OUTBOUND so the conversation in
        // /team-inbox shows the operator's mobile-typed reply. Same
        // pattern as Baileys `key.fromMe=true` sync via Node.
        //
        // Detection: normalize both sides to digits-only and compare.
        // Twilio strips `whatsapp:` prefix upstream; WABA always sends
        // raw digits. So a single digits-only equality check is safe.
        $fromDigits = preg_replace('/\D+/', '', $fromPhone);
        $isOutboundEcho = false;
        try {
            $ownPhones = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $workspaceId)
                ->pluck('phone_number')
                ->map(fn ($p) => preg_replace('/\D+/', '', (string) $p))
                ->filter()
                ->all();
            // Coexistence gap: WaProviderConfig.phone_number is often empty for a
            // WABA/coexistence config (the human number lives in meta_json, not this
            // column). The webhook itself ALWAYS carries the receiving (own) number
            // under metadata.display_phone_number — add it so a message the business
            // typed from the WA Business app (which can arrive in messages[] with
            // from == our own number) is recorded as OUTBOUND, not mis-stored as an
            // inbound bubble keyed by our own number (which would invert the thread).
            $ownNum = preg_replace('/\D+/', '', (string) data_get($value, 'metadata.display_phone_number', ''));
            if ($ownNum !== '') $ownPhones[] = $ownNum;
            if (in_array($fromDigits, $ownPhones, true)) {
                $isOutboundEcho = true;
            }
        } catch (\Throwable $e) { /* lookup failure — treat as normal inbound */ }

        if ($isOutboundEcho) {
            $this->captureOutboundEcho($workspaceId, $msg, $value, $provider);
            return;
        }

        // Resolve body + media type from Meta's payload shape. Each
        // type has its own field; we collapse to a single body string
        // + media_type so the team-inbox renderer treats WABA inbound
        // identical to Baileys inbound.
        [$body, $mediaType, $mediaMeta] = $this->extractMetaInboundContent($msg);

        // Reaction-only payload — mutates an existing message, not a
        // new bubble. Find the target by wa_message_id and stamp its
        // reaction column (or clear it if emoji is empty). Mirrors the
        // Baileys handler in WaInboundController.
        if ($mediaType === 'reaction' && !empty($mediaMeta['reaction_to'])) {
            $targetWaId = (string) $mediaMeta['reaction_to'];
            $emoji      = (string) $body;
            $target = \App\Models\InboxMessage::query()
                ->whereJsonContains('meta->wa_message_id', $targetWaId)
                ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->orderByDesc('id')
                ->first();
            if ($target) {
                $target->update(['reaction' => $emoji !== '' ? $emoji : null]);
                \Log::info('[WABA-INBOUND] reaction applied', [
                    'workspace_id' => $workspaceId, 'message_id' => $target->id, 'emoji' => $emoji,
                ]);
            }
            return;
        }

        // Cart parser — only applies to plain-text inbound matching the
        // wa.me storefront link shape.
        if ($body !== '' && $mediaType === null) {
            $parsed = app(StorefrontOrderParser::class)->parse($workspaceId, $body, $fromPhone, $senderName ?: null);
            if ($parsed) {
                $parsed->update(['wa_message_id' => $msg['id'] ?? null]);
            }
        }

        // "Asked about a product" — Meta stamps context.referred_product
        // when the buyer taps a product card before typing.
        $referred = $msg['context']['referred_product'] ?? null;
        if ($referred && !empty($referred['product_retailer_id'])) {
            $this->tagConversationWithReferredProduct(
                $workspaceId, $fromPhone, $referred['product_retailer_id']
            );
        }

        // Resolve the device row that owns the inbound number — the BUSINESS
        // number that RECEIVED this message — so the conversation binds to the
        // right sender and replies leave on the SAME number the customer wrote
        // to. On a multi-WABA workspace, falling back to the workspace's first
        // device (the old behaviour) made every reply go out from the primary
        // number — which the customer never messaged, and whose token may even
        // be the wrong/expired one.
        $device = $this->resolveDeviceForReceivingNumber($workspaceId, $value)
            ?? $this->resolveDeviceForWorkspace($workspaceId);

        // Upsert the conversation — mirrors WaInboundController::baileys
        // resolution but keyed on workspace + raw_jid form of the phone.
        $rawJid = preg_replace('/\D+/', '', $fromPhone);
        // Title carries BOTH the name and the number — parity with the Baileys
        // inbound path (which builds "Name · +number"). WABA previously stored
        // the WhatsApp profile name ONLY when Meta provided one, so a named
        // contact showed no number in the inbox even though the number IS
        // received (msg.from) and stored (raw_jid). Format: "Name · +number"
        // when a profile name is known, else just "+number". (Display masking
        // is applied downstream exactly as it is for Baileys.)
        $title = $senderName !== ''
            ? ($senderName . ' · +' . $rawJid)
            : ('+' . $rawJid);

        // Multi-engine: scope the match to the engine this message belongs to
        // ($provider). Without it, a customer who reaches a workspace on BOTH
        // its WABA number AND its Twilio (or Unofficial API) number would
        // collapse into ONE thread, and a reply would go back over whichever
        // channel last touched it — not the one the operator is looking at.
        // Each channel keeps its own conversation, mirroring how the Baileys
        // inbound path already separates by device_id. Single-engine (all rows
        // provider='waba') is unaffected — the filter is a no-op there.
        // Match by workspace + ENGINE (provider) + the customer's number. We do
        // NOT filter on `platform` here: inbound rows stamp platform='W' but a
        // Quick Send (/chat) thread for the same number stamps the engine's
        // legacy code ('WB'/'T'), so a platform filter would MISS the Quick Send
        // thread and open a duplicate. provider already scopes the engine, so a
        // reply now lands in the ONE existing conversation for that number —
        // whether it was started from Quick Send or a previous inbound.
        // ONE THREAD PER NUMBER — see ConversationResolver. Matches the
        // customer's digits across every stored JID shape and partitions on
        // nothing but the workspace, so a thread opened by the Unofficial API,
        // Quick Send, the catalog or the mobile app is reused here instead of
        // being duplicated.
        $convo = \App\Services\Inbox\ConversationResolver::find($workspaceId, $rawJid);

        // The engine-migration adoption fallbacks that used to live here are
        // gone. They existed to rescue a thread when the provider-scoped
        // lookup above missed it — a number moved from the Unofficial API to
        // WABA, a legacy row backfilled to provider='baileys'. The resolver
        // does not scope by provider at all, so those threads are simply found
        // on the first try and there is nothing left to rescue. Removing them
        // also removes their blind spot: both depended on the old Unofficial
        // `devices` row still existing, so deleting the number defeated them.

        // Backfill device_id so an EXISTING WABA thread (created before the
        // resolveDeviceForReceivingNumber fix, with device_id=null) is keyed on
        // cfg->id — the same id the outbound mirror uses — otherwise AI / flow /
        // auto-reply replies keep landing on a separate, invisible conversation.
        if ($convo && $device && !empty($device->id) && (int) ($convo->device_id ?? 0) !== (int) $device->id && empty($convo->device_id)) {
            $convo->forceFill(['device_id' => (int) $device->id])->save();
        }

        $isNewConversation = !$convo;
        if (!$convo) {
            $convo = \App\Models\Conversation::create([
                'user_id'          => $device?->user_id,
                'workspace_id'     => $workspaceId,
                'device_id'        => $device?->id,
                'contact_group_id' => null,
                'title'            => $title,
                'raw_jid'          => $rawJid,
                'preview'          => mb_substr(
                $body !== ''
                    ? $body
                    : ($mediaType === 'location'
                        ? '📍 ' . ($meta['location_name'] ?? 'Location')
                        : '📎 ' . ($mediaType ?: 'message')),
                0, 191
            ),
                'status'           => 'pending',
                'platform'         => 'W',
                // Multi-engine: stamp the engine this message ARRIVED on, not a
                // hard-coded 'waba'. captureInboundMessage is shared by the WABA
                // and Twilio webhooks ($provider is 'waba' or 'twilio'); the old
                // hard-code mis-stamped every Twilio inbound as 'waba', so the
                // reply routed back over WABA instead of Twilio.
                'provider'         => $provider,
                'origin'           => 'inbox',
                'recipients_count' => 1,
                'last_message_at'  => now(),
            ]);
        } else {
            // Backfill the title so it always carries the phone number. Heals
            // every existing WABA conversation created before the "title
            // includes number" change, the first time a new message arrives:
            //   - title missing the number (name-only legacy)  → "Name · +number"
            //   - title is bare digits + we now know the name  → "Name · +number"
            $updates = [];
            $currentTitle     = (string) $convo->title;
            $currentHasNumber = $rawJid !== '' && str_contains($currentTitle, $rawJid);
            $titleIsBareDigits = preg_match('/^\+?\d{6,}$/', trim($currentTitle)) === 1;
            if (!$currentHasNumber) {
                // Name-only (or any title lacking the number) → rebuild with number.
                $updates['title'] = $title;
            } elseif ($senderName !== '' && $titleIsBareDigits) {
                // Bare "+number" and we just learned the name → add the name.
                $updates['title'] = $title;
            }
            if (!empty($updates)) $convo->update($updates);
        }

        // Auto-capture the sender as a Contact. A brand-new number that messages
        // us should show up in Contacts (and grow the dashboard "Active contacts"
        // count) — previously a LIVE inbound only created a Conversation, never a
        // Contact, so the count sat flat while new people kept messaging.
        // rememberPhone dedups by phone hash, so an existing contact is reused
        // (no duplicates) and it's safe to call on every inbound. Also stamp the
        // resolved contact_id onto the thread when missing — that lets
        // campaign-reply attribution + other contact-keyed features resolve
        // without a phone re-scan. Best-effort: never break inbound processing.
        try {
            $capturedContact = \App\Models\Contact::rememberPhone(
                (int) $workspaceId,
                $device?->user_id,
                $rawJid,
                $senderName !== '' ? $senderName : null,
            );
            if ($capturedContact && empty($convo->contact_id) && $this->conversationsHaveContactId()) {
                $convo->forceFill(['contact_id' => $capturedContact->id])->save();
            }
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] contact capture failed: ' . $e->getMessage());
        }

        // ── DIAGNOSTIC TRACE ──────────────────────────────────────────────
        // The AI/flow/auto-reply OUTBOUND mirror (WaInboundController) logs
        // its own thread as `[AI-INBOX-TRACE] ... conversation_id:X device_id:Y`
        // and `[INBOUND] stored conv_id:X device_id:Y`. This line logs the
        // INBOUND side so the two can be lined up: if conversation_id here
        // differs from the mirror's, the thread is SPLIT (operator sees this
        // convo, bot replies land on the mirror's) — that is the "AI/flow
        // replies not showing in team inbox" symptom. If they MATCH but the
        // reply still isn't visible, the cause is downstream (render/filter),
        // not conversation resolution. `device_source` shows where device_id
        // came from: a real Baileys `devices` row, the WABA/Twilio
        // wa_provider_configs fallback, or the workspace default.
        try {
            $receivingNum = preg_replace('/\D+/', '', (string) data_get($value, 'metadata.display_phone_number', ''));
            \Log::info('[WABA-INBOUND-TRACE] inbound bound to conversation', [
                'workspace_id'     => $workspaceId,
                'provider'         => $provider,
                'customer'         => $rawJid,
                'receiving_number' => $receivingNum,        // our WABA number = mirror's device_phone
                'phone_number_id'  => (string) data_get($value, 'metadata.phone_number_id', ''),
                'device_id'        => $device->id ?? null,
                'device_source'    => $device instanceof \App\Models\Device
                    ? 'devices_row'
                    : (is_object($device) ? 'wa_provider_configs/workspace_fallback' : 'none'),
                'conversation_id'  => $convo->id,
                'conv_device_id'   => $convo->device_id,    // must equal the mirror's device_id to share a thread
                'is_new'           => $isNewConversation,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND-TRACE] log failed: ' . $e->getMessage());
        }

        // Persist the InboxMessage. Media payload is recorded as meta
        // (Meta's CDN URL needs an authenticated fetch; the media-pull
        // worker resolves the binary later — for now the operator at
        // least sees the message landed with the right type label).
        $meta = [];
        if ($msg['id'] ?? null) $meta['wa_message_id'] = $msg['id'];
        if (!empty($mediaMeta)) $meta = array_merge($meta, $mediaMeta);

        // Skip content-less placeholder events. Meta delivers 'unsupported'
        // (131051 — content stripped) and 'system' messages, plus the call
        // notifications, with NO body / media / location. Persisting them only
        // paints a useless "Message unavailable" bubble that clutters the
        // thread — the exact thing the client flagged. Real voice calls now get
        // a proper "Voice call · N min" entry via the calls-webhook mirror
        // (WaCallingWebhookController::mirrorCallToInbox).
        $inboundType   = (string) ($msg['type'] ?? '');
        $hasRenderable = ((string) $body !== '')
            || ((string) $mediaType !== '')
            || isset($meta['location_latitude']);
        if (!$hasRenderable && in_array($inboundType, ['unsupported', 'system', 'call'], true)) {
            \Log::info('[WABA-INBOUND] skipped content-less placeholder (no "Message unavailable" bubble)', [
                'type' => $inboundType, 'workspace_id' => $workspaceId, 'conversation_id' => $convo->id,
            ]);
            return;
        }

        $inboundMsg = \App\Models\InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $device?->user_id,
            'direction'       => 'in',
            'from_number'     => $fromPhone,
            'to_number'       => null,
            'body'            => $body,
            'media_type'      => $mediaType,
            'latitude'        => isset($meta['location_latitude'])  ? (float) $meta['location_latitude']  : null,
            'longitude'       => isset($meta['location_longitude']) ? (float) $meta['location_longitude'] : null,
            'meta'            => $meta ?: null,
            'status'          => 'received',
            'sent_at'         => isset($msg['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $msg['timestamp']) : now(),
            'delivered_at'    => now(),
        ]);

        // WABA Cloud media is referenced by a Meta media ID (not a URL), so
        // unlike Baileys nothing lands on disk by default. Pull inbound MEDIA
        // down now — before the AI auto-reply trigger below — so the team-inbox
        // can render/play it (voice notes, images, video, documents) AND the AI
        // agent can SEE images (vision) / HEAR voice. Previously scoped to
        // images only, which is why inbound VOICE notes showed "Voice message
        // unavailable / Media could not be downloaded" in the inbox. Best-effort:
        // any failure leaves media_path null and the operator/agent falls back to
        // text. (Twilio inbound media arrives as a direct URL on its own path.)
        if ($provider === 'waba' && in_array($mediaType, ['image', 'audio', 'video', 'document'], true) && !empty($meta['waba_media_id'])) {
            $localPath = $this->downloadWabaMediaToDisk(
                $workspaceId,
                (string) $meta['waba_media_id'],
                (string) ($meta['waba_mime_type'] ?? ''),
            );
            if ($localPath) {
                $inboundMsg->forceFill(['media_path' => $localPath])->save();
            }
        }

        // Campaign "Replied" tracking — Meta's status webhooks update
        // delivered/read but NEVER a customer's REPLY, so the Replied analytic
        // stayed 0 on WABA campaigns. Link this inbound to the recipient's most
        // recent un-answered campaign send: prefer the precise context reply-to
        // wamid (customer tapped "reply" on the campaign message); else fall
        // back to the same contact within the last 7 days. Then recompute the
        // campaign counters so the funnel + KPI cards reflect it.
        try {
            $ctxReplyId = (string) ($msg['context']['id'] ?? '');

            // A tapped QUICK-REPLY / interactive button on the campaign template
            // is a genuine BUTTON TAP. WABA delivers it as an inbound (type=button
            // for template quick-replies, or interactive.button_reply/list_reply)
            // — NOT a URL click through /r/, so it never reaches
            // LinkRedirectController. Count it here so the campaign's "Button taps"
            // (clicked_count) actually moves on WABA. NOTE: a static URL button
            // still can't be tracked at all — Meta serves its approved URL to the
            // customer directly and never tells us it was tapped.
            $msgType  = (string) ($msg['type'] ?? '');
            $iType    = (string) ($msg['interactive']['type'] ?? '');
            $isBtnTap = $msgType === 'button'
                || ($msgType === 'interactive' && in_array($iType, ['button_reply', 'list_reply'], true));

            $campQ = \DB::table('wp_campaign_contacts as wc')
                ->join('wpcampaigns as c', 'c.id', '=', 'wc.campaign_id')
                ->where('c.workspace_id', $workspaceId);
            // Only require "not yet replied" for a plain reply — a button tap
            // must still be credited even if the recipient already replied.
            if (! $isBtnTap) {
                $campQ->whereNull('wc.responded_at');
            }
            if ($ctxReplyId !== '') {
                $campQ->where('wc.whatsapp_message_id', $ctxReplyId);
            } elseif (!empty($convo->contact_id)) {
                $campQ->where('wc.contact_id', $convo->contact_id)
                      ->where('wc.sent_at', '>=', now()->subDays(7));
            } else {
                $campQ->whereRaw('1=0');
            }
            $campHit = $campQ->orderByDesc('wc.sent_at')->first(['wc.id', 'wc.campaign_id', 'wc.clicked', 'wc.responded_at']);
            if ($campHit) {
                $update = ['updated_at' => now()];
                // Credit the reply once (don't clobber an earlier reply).
                if (empty($campHit->responded_at)) {
                    $update['responded_at'] = now();
                    $update['response']     = mb_substr((string) $body, 0, 500);
                    $update['status']       = 'responded';
                }
                // Credit the button tap once.
                if ($isBtnTap && empty($campHit->clicked)) {
                    $update['clicked']    = 1;
                    $update['clicked_at'] = now();
                }
                if (count($update) > 1) {   // anything beyond updated_at
                    \DB::table('wp_campaign_contacts')->where('id', $campHit->id)->update($update);
                    \App\Models\WpCampaign::find($campHit->campaign_id)?->recomputeAggregates();
                    \Log::info('[WA-webhook] campaign reply/tap linked', [
                        'row' => $campHit->id, 'campaign' => $campHit->campaign_id, 'tap' => $isBtnTap,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[WA-webhook] campaign reply link failed: ' . $e->getMessage());
        }

        // Meta Business Agent coexistence — queue placement is the one honest
        // runtime difference between the two meta modes (Meta gives no
        // escalation webhook): meta_agent_only → 'closed' (logged, Meta owns
        // it); meta_agent_then_handoff → 'open' (surface for a human takeover);
        // everything else → 'pending' (unchanged default).
        $wsMode = $workspaceId ? \App\Models\Workspace::find($workspaceId) : null;
        $inboundStatus = $wsMode ? $wsMode->inboundInboxStatus() : 'pending';
        $convo->update([
            'preview'         => mb_substr(
                $body !== ''
                    ? $body
                    : ($mediaType === 'location'
                        ? '📍 ' . ($meta['location_name'] ?? 'Location')
                        : '📎 ' . ($mediaType ?: 'message')),
                0, 191
            ),
            'last_message_at' => now(),
            'last_inbound_at' => now(),
            'inbox_status'    => $inboundStatus,
        ]);
        $convo->increment('unread_count');

        // Persist the customer's WhatsApp @username (Meta 2026 rollout) onto the
        // conversation so the inbox can show it. Only writes when it actually
        // changed → no extra saves on the common (no-username) path.
        if ($waUsername !== '') {
            $rm = is_array($convo->routing_meta) ? $convo->routing_meta : [];
            if (($rm['wa_username'] ?? null) !== $waUsername) {
                $rm['wa_username'] = $waUsername;
                $convo->forceFill(['routing_meta' => $rm])->save();
                \Log::info('[WABA-INBOUND] captured wa username', ['convo' => $convo->id, 'username' => $waUsername]);
            }
        }

        // Mirror Baileys-side broadcast so operators see new inbound
        // live when Echo client listeners exist. Fires lockless via the
        // queue connection; non-broadcasting setups silently no-op.
        try {
            broadcast(new \App\Events\Inbox\MessageReceived(
                $inboundMsg->id, $convo->id, $workspaceId, 'in', null
            ))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] broadcast failed: ' . $e->getMessage());
        }

        // Webhook: message_received — fires for inbound on WABA Cloud AND
        // Twilio (both route through this method; the Baileys/Unofficial
        // path emits from WaInboundController::baileys).
        \App\Services\WebhookService::emit('message_received', [
            'workspace_id'    => $workspaceId,
            'user_id'         => $device?->user_id,
            'message_id'      => $inboundMsg->id,
            'conversation_id' => $convo->id,
            'from_number'     => $fromPhone,
            'body'            => $body,
            'media_type'      => $mediaType,
            'wamid'           => $msg['id'] ?? null,
            'provider'        => $provider,
            'timestamp'       => now()->timestamp,
        ], $device?->user_id);

        // Legacy messages table — keeps existing chat history / analytics
        // surfaces working. SAME shape as the old captureInboundMessage
        // pre-refactor so nothing downstream breaks.
        Message::create([
            'user_id'      => null,
            'workspace_id' => $workspaceId,
            'direction'    => 'in',
            'from_number'  => $fromPhone,
            'body'         => $body,
            'status'       => 'sent',
            'sent_at'      => now(),
        ]);

        // Outbound webhook subscribers — CRM integrations get notified.
        // `provider` lets downstream subscribers (Shopify / Hubspot / Woo
        // re-emitters, custom Zapier triggers, etc.) route by the actual
        // source: waba / baileys / twilio. Previously every inbound was
        // tagged `channel: whatsapp` with no provider, so a CRM that
        // expects a Meta-shaped wamid would misroute Twilio inbounds.
        try {
            $whd = app(\App\Services\Inbox\OutboundWebhookDispatcher::class);
            if ($isNewConversation) {
                $whd->fire('conversation.created', $convo->fresh(), [
                    'channel'      => 'whatsapp',
                    'provider'     => $provider,
                    'sender_phone' => $rawJid,
                    'sender_name'  => $senderName,
                ]);
            }
            $whd->fire('conversation.received', $convo->fresh(), [
                'body'         => $body,
                'media_type'   => $mediaType,
                'provider'     => $provider,
                'sender_phone' => $rawJid,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] webhook dispatch failed: ' . $e->getMessage());
        }

        // Routing rules — same engine the Baileys path uses, identical
        // contract (new-conv → full action set; follow-up → per-message).
        try {
            app(\App\Services\Inbox\RoutingEngine::class)->applyToInbound(
                $convo->fresh(),
                ['message_text' => $body, 'contact_phone' => $rawJid],
                isFollowUp: !$isNewConversation,
            );
            $convo = $convo->fresh();
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] routing engine failed: ' . $e->getMessage());
        }

        // Flow engine for WABA / Twilio. Baileys runs flows from its socket
        // handler; webhook-delivered providers reach the flow engine HERE. We
        // ask Node (synchronously) whether this inbound starts a flow (keyword
        // trigger) or resumes one parked on this customer's reply. When it does
        // ("consumed"), we SKIP the keyword auto-reply + AI agent below so the
        // customer never gets a double reply — the same way the Baileys handler
        // `continue`s after a flow fires. Node sends every flow node via the
        // WABA/Twilio engine (sock=null → engine resolved from settings).
        // STOP / START opt-out. Runs BEFORE the flow bridge, the keyword
        // matcher and the AI agent: a customer who just asked to be left alone
        // must not be answered with a chatbot menu or an AI reply — that is
        // exactly the behaviour that gets a number reported and blocked. When
        // this returns true the message was an opt-out instruction, is fully
        // handled (flag written + confirmation sent), and nothing else should
        // act on it.
        try {
            if (app(\App\Services\Inbox\OptOutService::class)->handle($convo, $body, $rawJid)) {
                return;
            }
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] opt-out check failed: ' . $e->getMessage());
        }

        $flowConsumed = false;
        // Meta Business Agent coexistence — this Node flow-bridge bypasses the
        // PHP RoutingEngine gate, so gate it here too: when the business declared
        // that Meta's agent answers this workspace, our keyword-triggered /
        // parked flows must stay silent (else the customer gets our flow reply
        // ON THE SAME WABA NUMBER alongside Meta's agent — a double reply).
        $wsGate = $workspaceId ? \App\Models\Workspace::find($workspaceId) : null;
        if ($wsGate && $wsGate->suppressesOurAutoReply()) {
            \Log::info('[WABA-INBOUND] flow bridge skipped — Meta Business Agent mode', ['workspace' => $workspaceId]);
        } else {
        try {
            // The flow must run on — and reply FROM — the number that RECEIVED
            // this message: our WABA/Twilio number. For WABA that is
            // metadata.display_phone_number. We must NOT use $device here: on a
            // workspace that also has a Baileys number, $device can resolve to
            // the Baileys device, which makes the flow reply go out via Baileys
            // from the wrong number (the "why is it using my Baileys number" bug).
            $receivingNumber = preg_replace('/\D+/', '', (string) data_get($value, 'metadata.display_phone_number', ''));
            $deviceNumber = $receivingNumber !== ''
                ? $receivingNumber
                : ($device ? preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) : '');
            $nodeBase = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
            if ($deviceNumber !== '' && $nodeBase !== '') {
                // Interactive tap id (button / list reply) so the flow can route
                // by port; empty for a plain typed reply.
                $replyId = (string) (
                    data_get($msg, 'interactive.button_reply.id')
                    ?? data_get($msg, 'interactive.list_reply.id')
                    ?? data_get($msg, 'button.payload')
                    ?? ''
                );
                // NON-CONTENT GUARD — only a real customer action (text, an
                // interactive tap, or media) may drive a flow. Meta ALSO delivers
                // content-less webhooks: type='unsupported'/'system', reactions,
                // and read/delivery-state changes — all with empty body + no tap.
                // Feeding those to a flow PARKED at the AI node (conversation mode)
                // resumes it with EMPTY input, so the AI re-replies ("Hi! How can I
                // help you today?") with NO real activity — the exact "AI keeps
                // auto-replying on its own with no message" bug. Skip them.
                $inboundType = (string) ($msg['type'] ?? '');
                $hasContent  = ((string) $body !== '') || ($replyId !== '')
                    || !in_array((string) $mediaType, ['', 'reaction'], true);
                if (!$hasContent || in_array($inboundType, ['unsupported', 'system'], true)) {
                    \Log::info('[WABA-INBOUND] flow bridge SKIPPED — non-content event (no text/tap/media)', [
                        'type' => $inboundType, 'workspace' => $workspaceId, 'customer' => $rawJid,
                    ]);
                } else {
                    // A shared location pin carries NO text body ($body is '' for a
                    // dropped pin — the coordinates live in $meta). Forwarding that
                    // empty string handed the flow's "Ask question" node nothing, so
                    // it dropped the pin and captured the customer's NEXT typed
                    // message as the answer instead (client: "location not detected;
                    // it saved the following text"). Forward a real Google-Maps link
                    // built from the pin's lat/lng (plus any place name it carried) so
                    // the node stores the location as free text the flow can validate
                    // — e.g. matching the maps link in a Run Code (JS) node.
                    $flowText = (string) $body;
                    if ((string) $mediaType === 'location'
                        && isset($meta['location_latitude'], $meta['location_longitude'])) {
                        $mapLink = 'https://www.google.com/maps?q='
                            . $meta['location_latitude'] . ',' . $meta['location_longitude'];
                        $flowText = $flowText !== '' ? ($flowText . ' — ' . $mapLink) : $mapLink;
                    }
                    $res = \Illuminate\Support\Facades\Http::withHeaders(['X-Node-Token' => node_token()])
                        ->timeout(12)->acceptJson()
                        ->post(rtrim($nodeBase, '/') . '/api/flow/provider-inbound', [
                            'deviceNumber'  => $deviceNumber,
                            'customerPhone' => $rawJid,
                            'workspaceId'   => $workspaceId,
                            'provider'      => $provider,
                            'text'          => $flowText,
                            'replyId'       => $replyId,
                            'pushName'      => (string) $senderName,
                        ]);
                    $flowConsumed = (bool) $res->json('consumed');
                    \Log::info('[WABA-INBOUND] flow bridge', ['device' => $deviceNumber, 'customer' => $rawJid, 'consumed' => $flowConsumed]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] provider-inbound flow bridge failed: ' . $e->getMessage());
        }
        }

        // /auto-reply (keyword_replies) matcher. Was Baileys-only via
        // Node before this hook — WABA + Twilio workspaces had the UI
        // but no firing path. KeywordReplyDispatcher handles match +
        // cooldown + anti-loop guard + dispatch via InboxDispatcher
        // (which picks the correct provider's send method). Skipped when a
        // flow already consumed this message.
        if (!$flowConsumed) {
        try {
            // Pass the workspace's own connected number explicitly so the
            // dispatcher can detect self-echo (operator typing on the same
            // WABA/Twilio number → re-trigger storm). `conversations`
            // has no device_phone column; for WABA the workspace number
            // lives on the resolved Device row.
            $selfNumber = (string) ($device?->phone_number ?? '');
            app(\App\Services\Inbox\KeywordReplyDispatcher::class)
                ->maybeDispatch($convo->fresh(), $body, $rawJid, $selfNumber ?: null);
            $convo = $convo->fresh();
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] keyword reply dispatch failed: ' . $e->getMessage());
        }
        } // end if (!$flowConsumed)

        // AI agent auto-respond — only fires if the workspace has an
        // active AI agent assigned and plan grants the feature.
        // Skip when this was an audio-only inbound with no transcript,
        // otherwise the LLM hallucinates against empty body. Also skipped
        // when a flow consumed the message (flow drives the conversation).
        if (!$flowConsumed && !($mediaType === 'audio' && $body === '')) {
            try {
                app(\App\Services\AiAgentService::class)->respondIfAssigned($convo->fresh());
            } catch (\Throwable $e) {
                \Log::warning('[WABA-INBOUND] ai agent failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Persist an outbound echo — operator replied from their personal
     * phone (WhatsApp Business app for WABA Cloud, or the shared mobile
     * sharing a Twilio number) and Meta/Twilio mirrored it back as a
     * webhook event. We record it as `direction='out'` on the
     * conversation so /team-inbox shows the mobile-typed reply inline
     * with API-sent replies. Mirrors `WaInboundController::baileys`
     * fromMe handling so all 3 providers behave identically.
     *
     * Key differences from regular inbound:
     *   - direction='out' (operator-side)
     *   - NO unread_count bump (operator already saw it on their phone)
     *   - NO inbox_status='pending' flip (they already responded)
     *   - NO routing rules (no point — operator already engaged)
     *   - NO AI auto-reply (we don't reply to our own reply)
     *   - NO `conversation.received` CRM webhook (those are for inbound)
     *   - DOES update last_message_at + last_outbound_at
     *   - DOES fire MessageReceived broadcast so OTHER operators see
     *     the mobile-typed reply live in their open inbox
     *   - DOES fire a `conversation.outbound_synced` CRM hook so
     *     downstream CRMs can record the activity
     */
    private function captureOutboundEcho(int $workspaceId, array $msg, array $value, string $provider): void
    {
        $contact   = $value['contacts'][0] ?? [];
        $ownPhone  = (string) ($msg['from'] ?? '');
        // The recipient of the operator's mobile-typed reply is the
        // customer — Meta puts that under `to` (Twilio under the
        // mapped 'to' we built in receiveTwilio).
        $toPhone   = (string) ($msg['to'] ?? '');
        if ($toPhone === '') {
            // Meta echoes occasionally omit `to`. Coexistence echoes can carry
            // the customer under `recipient_id` (the same key statuses use);
            // fall back to that, then to `context.from` (the original thread
            // the echo is replying to). Last resort: skip — can't route a
            // mobile-typed reply without a customer phone.
            $toPhone = (string) ($msg['recipient_id'] ?? $msg['context']['from'] ?? '');
        }
        if ($toPhone === '') {
            \Log::warning('[OUTBOUND-ECHO] missing `to` — cannot route', ['msg_id' => $msg['id'] ?? null, 'provider' => $provider]);
            return;
        }

        [$body, $mediaType, $mediaMeta] = $this->extractMetaInboundContent($msg);
        $rawJid = preg_replace('/\D+/', '', $toPhone);

        // Find the existing conversation with the customer. If it
        // doesn't exist yet (operator messaged the customer first from
        // their phone, never via the system), create one — the
        // conversation history should still surface this thread.
        // Multi-engine: scope the match to the engine this message belongs to
        // ($provider). Without it, a customer who reaches a workspace on BOTH
        // its WABA number AND its Twilio (or Unofficial API) number would
        // collapse into ONE thread, and a reply would go back over whichever
        // channel last touched it — not the one the operator is looking at.
        // Each channel keeps its own conversation, mirroring how the Baileys
        // inbound path already separates by device_id. Single-engine (all rows
        // provider='waba') is unaffected — the filter is a no-op there.
        // Match by workspace + ENGINE (provider) + the customer's number. We do
        // NOT filter on `platform` here: inbound rows stamp platform='W' but a
        // Quick Send (/chat) thread for the same number stamps the engine's
        // legacy code ('WB'/'T'), so a platform filter would MISS the Quick Send
        // thread and open a duplicate. provider already scopes the engine, so a
        // reply now lands in the ONE existing conversation for that number —
        // whether it was started from Quick Send or a previous inbound.
        // ONE THREAD PER NUMBER — see ConversationResolver. Matches the
        // customer's digits across every stored JID shape and partitions on
        // nothing but the workspace, so a thread opened by the Unofficial API,
        // Quick Send, the catalog or the mobile app is reused here instead of
        // being duplicated.
        $convo = \App\Services\Inbox\ConversationResolver::find($workspaceId, $rawJid);

        $device = $this->resolveDeviceForWorkspace($workspaceId);
        $isNewConversation = !$convo;
        if (!$convo) {
            $convo = \App\Models\Conversation::create([
                'user_id'          => $device?->user_id,
                'workspace_id'     => $workspaceId,
                'device_id'        => $device?->id,
                'title'            => $rawJid,
                'raw_jid'          => $rawJid,
                'preview'          => mb_substr($body !== '' ? $body : ('📎 ' . ($mediaType ?: 'message')), 0, 191),
                'status'           => 'sent',
                'platform'         => 'W',
                'provider'         => $provider,
                'origin'           => 'inbox',
                'recipients_count' => 1,
                'last_message_at'  => now(),
                'last_outbound_at' => now(),
            ]);
        }

        // Dedupe — if this wa_message_id is already on a row, skip the
        // insert. Meta echoes can arrive twice on retry; we don't want
        // duplicate bubbles in the operator's view.
        $waMessageId = (string) ($msg['id'] ?? '');
        if ($waMessageId !== '') {
            $existing = \App\Models\InboxMessage::query()
                ->whereJsonContains('meta->wa_message_id', $waMessageId)
                ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->exists();
            if ($existing) {
                \Log::info('[OUTBOUND-ECHO] dedupe — wa_message_id already persisted', [
                    'wa_message_id' => $waMessageId, 'provider' => $provider,
                ]);
                return;
            }
        }

        $meta = ['phone_sync' => true];
        if ($waMessageId !== '') $meta['wa_message_id'] = $waMessageId;
        if (!empty($mediaMeta)) $meta = array_merge($meta, $mediaMeta);

        $outboundMsg = \App\Models\InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $device?->user_id,
            'direction'       => 'out',
            'from_number'     => $ownPhone,
            'to_number'       => $toPhone,
            'body'            => $body,
            'media_type'      => $mediaType,
            'latitude'        => isset($meta['location_latitude'])  ? (float) $meta['location_latitude']  : null,
            'longitude'       => isset($meta['location_longitude']) ? (float) $meta['location_longitude'] : null,
            'meta'            => $meta,
            'status'          => 'sent',
            'sent_at'         => isset($msg['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $msg['timestamp']) : now(),
        ]);

        // Pull the bytes down, same as captureInboundMessage does for inbound.
        // Meta only ever hands us a media_id, never the binary — so without this
        // an image the operator sent from their own phone landed with
        // media_path=null and the team inbox drew "Photo unavailable / Media
        // could not be downloaded". The inbound path had this; the echo path
        // never did, which is why only phone-sent media was affected.
        if ($provider === 'waba'
            && in_array($mediaType, ['image', 'audio', 'video', 'document'], true)
            && !empty($meta['waba_media_id'])) {
            $localPath = $this->downloadWabaMediaToDisk(
                $workspaceId,
                (string) $meta['waba_media_id'],
                (string) ($meta['waba_mime_type'] ?? ''),
            );
            if ($localPath) {
                $outboundMsg->forceFill(['media_path' => $localPath])->save();
            }
        }

        // Update conversation pointers — but explicitly NOT unread_count
        // or inbox_status (operator just replied; nothing to action).
        $convo->update([
            'preview'          => mb_substr($body !== '' ? $body : ('📎 ' . ($mediaType ?: 'message')), 0, 191),
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
        ]);

        // Auto-capture the RECIPIENT as a Contact. A NEW number the operator
        // messaged FIRST from their own phone (this coexistence echo) must also
        // land in Contacts — not only when the customer messages back. The
        // inbound path (captureInboundMessage) already does this for the sender;
        // the echo path never did, so a number we reached from mobile stayed
        // missing from the contact list until it replied ("new no from mobile
        // reflects but no contact created"). rememberPhone dedups by phone hash
        // (existing contact reused, no duplicates); best-effort — never break the
        // echo. Stamp contact_id on the thread when missing, same as inbound.
        try {
            $echoName = trim((string) ($contact['profile']['name'] ?? ''));
            $capturedContact = \App\Models\Contact::rememberPhone(
                (int) $workspaceId,
                $device?->user_id,
                $rawJid,
                $echoName !== '' ? $echoName : null,
            );
            if ($capturedContact && empty($convo->contact_id) && $this->conversationsHaveContactId()) {
                $convo->forceFill(['contact_id' => $capturedContact->id])->save();
            }
        } catch (\Throwable $e) {
            \Log::warning('[OUTBOUND-ECHO] contact capture failed: ' . $e->getMessage());
        }

        // Broadcast so OTHER operators viewing this conversation see
        // the mobile-typed reply appear instantly (the operator who
        // typed it on their phone already saw it there).
        try {
            broadcast(new \App\Events\Inbox\MessageReceived(
                $outboundMsg->id, $convo->id, $workspaceId, 'out', $device?->user_id
            ))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('[OUTBOUND-ECHO] broadcast failed: ' . $e->getMessage());
        }

        // CRM webhook — typed as `conversation.outbound_synced` so
        // downstream subscribers can distinguish "system-sent" from
        // "mobile-typed" outbound. Provider is stamped so Zapier/
        // Shopify/Hubspot integrations can route correctly.
        try {
            $whd = app(\App\Services\Inbox\OutboundWebhookDispatcher::class);
            $whd->fire('conversation.outbound_synced', $convo->fresh(), [
                'body'         => $body,
                'media_type'   => $mediaType,
                'provider'     => $provider,
                'sender_phone' => $rawJid,
                'source'       => 'mobile',  // distinguishes phone-typed from system-typed
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[OUTBOUND-ECHO] webhook dispatch failed: ' . $e->getMessage());
        }

        \Log::info('[OUTBOUND-ECHO] mobile-typed reply mirrored to inbox', [
            'workspace_id' => $workspaceId,
            'conv_id'      => $convo->id,
            'msg_id'       => $outboundMsg->id,
            'provider'     => $provider,
        ]);
    }

    /**
     * Coexistence `smb_app_state_sync` — the business's WhatsApp Business app
     * contacts (and any new ones it adds) mirrored into our contact book, so
     * the workspace's app address-book and the platform stay in step. We only
     * ADD/UPDATE; a 'remove' is logged, never deleted (avoids surprise data
     * loss from an app-side edit). Phone-digit matched against the workspace's
     * existing contacts so we never duplicate someone already on the platform.
     */
    private function applyAppStateSync(int $workspaceId, array $value): void
    {
        $items = $value['state_sync'] ?? [];
        if (!is_array($items) || empty($items)) return;

        $device = $this->resolveDeviceForWorkspace($workspaceId);
        $ownerId = $device?->user_id;

        // Load existing contacts once for digit matching (mobile is encrypted,
        // so a SQL LIKE can't match). Index by `digits(mobile)` — the SAME key
        // the team inbox uses to match a contact to a conversation's full
        // number (TeamInboxController createDeal/conversationDeals compare
        // digits(mobile) === conv->raw_jid). The platform stores the country
        // code INSIDE `mobile`, so digits(mobile) is already the full number;
        // synced contacts store the full E.164 in `mobile` too, so the two
        // dedupe against each other. (Using country_code . mobile would double
        // the prefix for UI-added contacts and miss the match.)
        $existing = \App\Models\Contact::where('workspace_id', $workspaceId)->get();
        $indexByDigits = [];
        foreach ($existing as $c) {
            $d = preg_replace('/\D+/', '', (string) ($c->mobile ?: ''));
            if ($d !== '') $indexByDigits[$d] = $c;
        }

        $added = 0; $updated = 0;

        // CRITICAL: suppress model events for the whole sync. Contact::created
        // otherwise fires FlowEnrollmentService::onContactCreated (which can
        // POST to Node and send a real WhatsApp message) + the LogsNotifications
        // trait — so mirroring the business's entire app address book would
        // blast a flow message + notification to every synced contact. The
        // sync is a bulk data import, not live engagement: events stay off.
        \App\Models\Contact::withoutEvents(function () use ($items, $indexByDigits, $ownerId, $workspaceId, &$added, &$updated) {
            foreach ($items as $item) {
                if (($item['type'] ?? 'contact') !== 'contact') continue;
                $action  = (string) ($item['action'] ?? 'add');
                $contact = $item['contact'] ?? [];
                $phone   = (string) ($contact['phone_number'] ?? '');
                $digits  = preg_replace('/\D+/', '', $phone);
                if ($digits === '') continue;

                // full_name can be a string or {name:...}; first/last may be split.
                $full = $contact['full_name'] ?? null;
                if (is_array($full)) $full = $full['name'] ?? null;
                $name = trim((string) ($full
                    ?: trim(((string) ($contact['first_name'] ?? '')) . ' ' . ((string) ($contact['last_name'] ?? '')))));

                if ($action === 'remove') {
                    \Log::info('[WA-COEX] app_state_sync remove (kept on platform)', ['ws' => $workspaceId, 'phone' => mask_phone($phone)]);
                    continue;
                }

                if (isset($indexByDigits[$digits])) {
                    // Existing contact → fill a blank name only (never overwrite a
                    // name the operator curated). A `true` here means we already
                    // created this number earlier in THIS batch — just skip it
                    // (don't treat the bool as a model).
                    $row = $indexByDigits[$digits];
                    if ($row instanceof \App\Models\Contact && $name !== '' && trim((string) $row->name) === '') {
                        $row->name = $name;
                        $row->save();
                        $updated++;
                    }
                    continue;
                }

                // Store the full international number in `mobile` (no separate
                // country_code split — Meta hands us one E.164 string). Readers
                // concatenate country_code . mobile, so a NULL prefix + full
                // number resolves to the same digit key we indexed on above.
                \App\Models\Contact::create([
                    'user_id'      => $ownerId,
                    'workspace_id' => $workspaceId,
                    'name'         => $name ?: $phone,
                    'mobile'       => $phone,
                    'msg'          => 'Synced from WhatsApp Business app (Coexistence).',
                ]);
                $indexByDigits[$digits] = true; // guard against dupes within the same batch
                $added++;
            }
        });

        \Log::info('[WA-COEX] app_state_sync applied', ['ws' => $workspaceId, 'added' => $added, 'updated' => $updated]);
    }

    /**
     * Coexistence `history` — past messages the business sent/received on the
     * WhatsApp Business app BEFORE onboarding, pushed by Meta in the minutes
     * after a successful coexistence onboard. We backfill them into the team
     * inbox QUIETLY: deduped by wamid, with NO routing / AI / auto-reply / CRM
     * webhooks (those are for live traffic — firing flows on months-old chats
     * would be a disaster). Capped per delivery so a huge import can't stall
     * the webhook response.
     */
    private function applyHistorySync(int $workspaceId, array $value): void
    {
        $history = $value['history'] ?? [];
        if (!is_array($history) || empty($history)) return;

        // Resolve the RECEIVING WABA number first (its device_id = the
        // wa_provider_configs id), exactly like the live inbound path. The old
        // code jumped straight to resolveDeviceForWorkspace() — the workspace's
        // first Baileys device — so synced WABA chats were stamped a Baileys
        // devices.id while provider='waba'. That id belongs to a different
        // table than the WABA device-filter value, so filtering the inbox by
        // the WABA number matched ZERO synced chats (and their per-row device
        // label mis-resolved). Falls back to the old behaviour when the
        // receiving number can't be matched, so nothing regresses.
        $device = $this->resolveDeviceForReceivingNumber($workspaceId, $value)
            ?? $this->resolveDeviceForWorkspace($workspaceId);
        $ownPhones = [];
        try {
            $ownPhones = \App\Models\WaProviderConfig::where('workspace_id', $workspaceId)
                ->pluck('phone_number')->map(fn ($p) => preg_replace('/\D+/', '', (string) $p))->filter()->all();
        } catch (\Throwable $e) { /* treat all as inbound on failure */ }

        $cap = 2000; $done = 0;
        foreach ($history as $chunk) {
            foreach ($chunk['threads'] ?? [] as $thread) {
                $threadId = (string) ($thread['id'] ?? '');
                foreach ($thread['messages'] ?? [] as $msg) {
                    if ($done >= $cap) {
                        \Log::warning('[WA-COEX] history cap hit — remaining messages skipped this delivery', ['ws' => $workspaceId, 'cap' => $cap]);
                        return;
                    }
                    try {
                        if ($this->insertHistoricalMessage($workspaceId, $msg, $threadId, $device, $ownPhones)) $done++;
                    } catch (\Throwable $e) {
                        \Log::warning('[WA-COEX] history message insert failed: ' . $e->getMessage());
                    }
                }
            }
        }

        \Log::info('[WA-COEX] history backfilled', ['ws' => $workspaceId, 'messages' => $done]);
    }

    /**
     * Insert ONE historical message into the inbox without side effects.
     * Returns true if a row was written, false if deduped/skipped.
     */
    private function insertHistoricalMessage(int $workspaceId, array $msg, string $threadId, $device, array $ownPhones): bool
    {
        $wamid = (string) ($msg['id'] ?? '');
        if ($wamid !== '') {
            $dupe = \App\Models\InboxMessage::query()
                ->whereJsonContains('meta->wa_message_id', $wamid)
                ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
                ->exists();
            if ($dupe) return false;
        }

        $from = preg_replace('/\D+/', '', (string) ($msg['from'] ?? ''));
        $isOut = $from !== '' && in_array($from, $ownPhones, true);
        // The customer side of the thread = thread id, or the non-own party.
        $customer = preg_replace('/\D+/', '', $threadId ?: (string) ($msg['to'] ?? $msg['from'] ?? ''));
        if ($customer === '' || in_array($customer, $ownPhones, true)) {
            $customer = $isOut ? preg_replace('/\D+/', '', (string) ($msg['to'] ?? '')) : $from;
        }
        if ($customer === '') return false;

        [$body, $mediaType] = $this->extractMetaInboundContent($msg);

        // ONE THREAD PER NUMBER. The old `platform = 'W'` filter here silently
        // excluded Quick Send threads (stamped 'WB'/'T' by
        // WaProvider::legacyCode), so importing history opened a duplicate for
        // any customer who had been quick-sent to.
        $convo = \App\Services\Inbox\ConversationResolver::find($workspaceId, $customer);
        $existed = (bool) $convo;

        if (!$convo) {
            $convo = \App\Models\Conversation::create([
                'user_id'          => $device?->user_id,
                'workspace_id'     => $workspaceId,
                'device_id'        => $device?->id,
                'title'            => $customer,
                'raw_jid'          => $customer,
                'preview'          => mb_substr($body !== '' ? $body : ('📎 ' . ($mediaType ?: 'message')), 0, 191),
                'status'           => 'sent',
                'platform'         => 'W',
                'provider'         => 'waba',
                'origin'           => 'inbox',
                'recipients_count' => 1,
                'last_message_at'  => isset($msg['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $msg['timestamp']) : now(),
            ]);
        }

        // Coexistence history sync must SURFACE the thread. If this customer's
        // chat had been ARCHIVED, it's hidden from the queue list yet still
        // counts toward the inbox badge — the "322 shown / 0 in the list" bug.
        // Bringing their imported past chats in should un-archive it (the same
        // way a live inbound re-opens a thread). Freshly created threads above
        // already default to un-archived, so this only touches pre-existing ones.
        if ($existed && $convo->archived) {
            $convo->forceFill(['archived' => false])->save();
        }

        $sentAt = isset($msg['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $msg['timestamp']) : now();
        $meta = ['history_sync' => true];
        if ($wamid !== '') $meta['wa_message_id'] = $wamid;

        $row = \App\Models\InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $isOut ? $device?->user_id : null,
            'direction'       => $isOut ? 'out' : 'in',
            'from_number'     => (string) ($msg['from'] ?? ''),
            'to_number'       => (string) ($msg['to'] ?? ''),
            'body'            => $body,
            'media_type'      => $mediaType,
            'meta'            => $meta,
            'status'          => 'sent',
            'sent_at'         => $sentAt,
        ]);
        // Eloquent stamps created_at = now() on insert, which would sort old
        // history below live messages. Backdate it (bypassing timestamps) so
        // the thread renders in true chronological order.
        \App\Models\InboxMessage::withoutTimestamps(fn () => $row->forceFill(['created_at' => $sentAt])->save());

        // Keep the conversation's last_message_at at the newest message time,
        // but never push it forward past a live message already recorded.
        if (!$convo->last_message_at || $sentAt->gt($convo->last_message_at)) {
            $convo->forceFill(['last_message_at' => $sentAt])->save();
        }

        return true;
    }

    /**
     * Pull an inbound WABA Cloud media object down to local disk so the
     * team-inbox can render it AND the AI agent can "see" it (vision).
     *
     * Meta media is a 2-step authenticated fetch:
     *   1) GET /<media_id>            → { url, mime_type, file_size }
     *   2) GET <url>                  → the binary (same bearer token)
     * Both calls use the workspace's WABA access token (same resolution
     * as InboxDispatcher's send path). Stores under the SAME chat-media/
     * convention the Baileys inbound path uses so AiAgentService's
     * resolveInboundImage() finds it identically. Returns the relative
     * path or null on any failure (caller leaves media_path null →
     * graceful text-only reply).
     */
    private function downloadWabaMediaToDisk(int $workspaceId, string $mediaId, string $mimeHint = ''): ?string
    {
        // Logic lives in the shared WabaMediaFetcher so the operator "retry
        // download" endpoint (TeamInboxController::retryMedia) fetches media the
        // exact same way — including the MIME→extension map that lets voice
        // notes actually play.
        try {
            return app(\App\Services\Waba\WabaMediaFetcher::class)
                ->downloadToDisk($workspaceId, $mediaId, $mimeHint);
        } catch (\Throwable $e) {
            \Log::warning('[WABA-INBOUND] media download failed: ' . $e->getMessage(), ['media_id' => $mediaId]);
            return null;
        }
    }

    /**
     * Extract [body, mediaType, mediaMeta] from a Meta inbound message
     * payload. Returns ['', null, []] for unknown shapes — caller still
     * persists the row so the operator at least sees something landed.
     */
    private function extractMetaInboundContent(array $msg): array
    {
        $type = $msg['type'] ?? null;
        $meta = [];
        $mediaType = null;
        $body = '';

        switch ($type) {
            case 'text':
                $body = (string) ($msg['text']['body'] ?? '');
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $mediaType = $type === 'sticker' ? 'image' : $type;
                $payload = $msg[$type] ?? [];
                $body = (string) ($payload['caption'] ?? '');
                if (!empty($payload['id']))       $meta['waba_media_id']   = $payload['id'];
                if (!empty($payload['mime_type'])) $meta['waba_mime_type'] = $payload['mime_type'];
                if (!empty($payload['filename'])) $meta['waba_filename']  = $payload['filename'];
                if (!empty($payload['voice']))    $meta['voice']          = true;
                break;
            case 'location':
                $loc = $msg['location'] ?? [];
                $mediaType = 'location';
                $body = trim(($loc['name'] ?? '') . ($loc['address'] ?? '' ? "\n" . $loc['address'] : ''));
                if (isset($loc['latitude']))  $meta['location_latitude']  = (float) $loc['latitude'];
                if (isset($loc['longitude'])) $meta['location_longitude'] = (float) $loc['longitude'];
                if (!empty($loc['name']))     $meta['location_name']     = (string) $loc['name'];
                if (!empty($loc['address']))  $meta['location_address']  = (string) $loc['address'];
                break;
            case 'contacts':
                $mediaType = 'contact';
                $contacts = $msg['contacts'] ?? [];
                $body = (string) ($contacts[0]['name']['formatted_name'] ?? '');
                $meta['contacts'] = $contacts;
                break;
            case 'reaction':
                $body = (string) ($msg['reaction']['emoji'] ?? '');
                $meta['reaction_to'] = $msg['reaction']['message_id'] ?? null;
                $mediaType = 'reaction';
                break;
            case 'button':
                // Quick reply button on a template — Meta sends the
                // button title as body.
                $body = (string) ($msg['button']['text'] ?? '');
                $meta['button_payload'] = $msg['button']['payload'] ?? null;
                break;
            case 'interactive':
                $i = $msg['interactive'] ?? [];
                $itype = $i['type'] ?? '';
                if ($itype === 'button_reply') {
                    // Title is what the customer tapped; if Meta omits it, fall
                    // back to the button id so the bubble is never blank.
                    $body = (string) ($i['button_reply']['title'] ?? '');
                    $meta['button_id'] = $i['button_reply']['id'] ?? null;
                    if ($body === '') $body = (string) ($i['button_reply']['id'] ?? '');
                } elseif ($itype === 'list_reply') {
                    $body = (string) ($i['list_reply']['title'] ?? '');
                    $meta['list_row_id'] = $i['list_reply']['id'] ?? null;
                    if (!empty($i['list_reply']['description'])) {
                        $meta['list_row_description'] = $i['list_reply']['description'];
                    }
                    if ($body === '') {
                        $body = (string) ($i['list_reply']['description'] ?? ($i['list_reply']['id'] ?? ''));
                    }
                } elseif ($itype === 'nfm_reply') {
                    // WhatsApp Flow (form) submission — carries the filled fields
                    // as response_json. Render a readable label + keep the raw
                    // answers so the flow / inbox can use them.
                    $body = (string) ($i['nfm_reply']['body'] ?? 'Form submitted');
                    if (!empty($i['nfm_reply']['response_json'])) $meta['flow_response'] = $i['nfm_reply']['response_json'];
                    if (!empty($i['nfm_reply']['name']))          $meta['flow_name']     = $i['nfm_reply']['name'];
                } else {
                    // OUTGOING interactive message the BUSINESS sent (type
                    // button / list / cta_url / product) — e.g. a coexistence
                    // auto-greeting with an image header + quick-reply buttons.
                    // The old code left body empty here, so it showed as a blank
                    // "Message unavailable" bubble. Pull the readable content so
                    // it renders like WhatsApp (header, text, footer, buttons).
                    $body = (string) ($i['body']['text'] ?? '');
                    if (!empty($i['footer']['text'])) $meta['footer'] = (string) $i['footer']['text'];
                    $h     = $i['header'] ?? [];
                    $htype = $h['type'] ?? '';
                    if (in_array($htype, ['image', 'video', 'document'], true) && !empty($h[$htype]['id'])) {
                        $mediaType = $htype;
                        $meta['waba_media_id'] = $h[$htype]['id'];
                        if (!empty($h[$htype]['mime_type'])) $meta['waba_mime_type'] = $h[$htype]['mime_type'];
                    } elseif ($htype === 'text' && !empty($h['text'])) {
                        $meta['header'] = (string) $h['text'];
                    }
                    $btns = [];
                    foreach (($i['action']['buttons'] ?? []) as $b) {
                        $bt = (string) ($b['reply']['title'] ?? ($b['title'] ?? ''));
                        if ($bt !== '') $btns[] = ['type' => 'quick_reply', 'text' => $bt];
                    }
                    foreach (($i['action']['sections'] ?? []) as $sec) {
                        foreach (($sec['rows'] ?? []) as $row) {
                            $bt = (string) ($row['title'] ?? '');
                            if ($bt !== '') $btns[] = ['type' => 'quick_reply', 'text' => $bt];
                        }
                    }
                    // CTA-URL button (name='cta_url'): the visible label + the link
                    // both live under action.parameters — pull them so the bubble
                    // shows "Visit site → https://…" instead of a blank box.
                    $aname = (string) ($i['action']['name'] ?? '');
                    $ap    = (array) ($i['action']['parameters'] ?? []);
                    if ($aname === 'cta_url') {
                        $lbl = (string) ($ap['display_text'] ?? 'Open link');
                        $url = (string) ($ap['url'] ?? '');
                        if ($url !== '') { $btns[] = ['type' => 'url', 'text' => $lbl, 'url' => $url]; if ($body === '') $body = $lbl; }
                    } elseif ($aname === 'flow') {
                        // WhatsApp Flow launcher — action.parameters.flow_cta is the
                        // button label the customer sees.
                        $lbl = (string) ($ap['flow_cta'] ?? 'Open form');
                        $btns[] = ['type' => 'quick_reply', 'text' => $lbl];
                    }
                    if ($btns) $meta['buttons'] = $btns;
                    // Final fallback: never a bare "[Interactive message]" — name the
                    // interactive subtype so the operator at least knows what it was,
                    // and log the raw payload so we can add first-class extraction.
                    if ($body === '' && $mediaType === null && empty($btns)) {
                        $sub  = $aname !== '' ? $aname : ($itype !== '' ? $itype : 'interactive');
                        $body = '[Interactive · ' . $sub . ']';
                        \Log::info('[WABA-INBOUND] interactive with no extractable content', [
                            'interactive_type' => $itype,
                            'action_name'      => $aname,
                            'raw'              => mb_substr(json_encode($i), 0, 1500),
                        ]);
                    }
                }
                break;
            case 'template':
                // A template message the business sent, echoed back to us (common
                // with coexistence auto-replies). Pull body/header/footer/buttons
                // from components so it renders instead of a blank bubble.
                $tpl   = $msg['template'] ?? [];
                $comps = (array) ($tpl['components'] ?? []);
                foreach ($comps as $c) {
                    $ctype = strtolower((string) ($c['type'] ?? ''));
                    if ($ctype === 'body' && !empty($c['text'])) {
                        $body = (string) $c['text'];
                    } elseif ($ctype === 'header') {
                        if (!empty($c['text'])) $meta['header'] = (string) $c['text'];
                    } elseif ($ctype === 'footer' && !empty($c['text'])) {
                        $meta['footer'] = (string) $c['text'];
                    } elseif (in_array($ctype, ['buttons', 'button'], true)) {
                        foreach ((array) ($c['buttons'] ?? []) as $b) {
                            $bt = (string) ($b['text'] ?? '');
                            if ($bt !== '') $meta['buttons'][] = ['type' => 'quick_reply', 'text' => $bt];
                        }
                    }
                }
                if ($body === '' && empty($meta['header']) && empty($meta['buttons'])) {
                    $body = trim((string) ($tpl['name'] ?? '')) !== ''
                        ? '[Template · ' . $tpl['name'] . ']'
                        : '[Template message]';
                }
                break;
            case 'order':
                // WhatsApp catalog order — the customer sent products from the
                // catalog. Carries a real product list + optional note text, so
                // render it instead of dropping it as "unsupported".
                $order = $msg['order'] ?? [];
                $items = (array) ($order['product_items'] ?? []);
                $mediaType = 'order';
                $body = trim((string) ($order['text'] ?? '')) ?: ('Order · ' . count($items) . ' item' . (count($items) === 1 ? '' : 's'));
                if (!empty($order['catalog_id'])) $meta['order_catalog_id'] = $order['catalog_id'];
                if ($items)                       $meta['order_product_items'] = $items;
                break;
            case 'system':
                // System notification (e.g. the customer changed their phone
                // number). system.body is a human-readable sentence.
                $sys  = $msg['system'] ?? [];
                $body = (string) ($sys['body'] ?? 'System update');
                if (!empty($sys['type']))  $meta['system_type']  = $sys['type'];
                if (!empty($sys['wa_id'])) $meta['system_wa_id'] = $sys['wa_id'];
                break;

            default:
                // A message type we don't explicitly parse. The common one is
                // Meta's `unsupported` — the customer sent something the Cloud
                // API cannot deliver (poll, view-once, payment, or a newer type),
                // so there is genuinely no content to render. Others: order /
                // system / request_welcome. Give the bubble a readable label
                // (instead of a blank / "Message unavailable") AND log the raw
                // type so we can add first-class handling if it turns out common.
                $errDesc = (string) ($msg['errors'][0]['title'] ?? ($msg['errors'][0]['message'] ?? ''));
                if ($type === 'unsupported') {
                    // Meta error 131051: the customer sent a message type the
                    // WhatsApp Cloud API cannot render (poll / view-once / payment
                    // / a newer type). Meta STRIPS the content before it reaches the
                    // webhook — there is genuinely nothing to fetch or display; the
                    // raw "Message type unknown" title is not useful to an operator.
                    $body = '[Unsupported message — the customer sent something the WhatsApp Business API can\'t display]';
                } elseif (!empty($type)) {
                    $body = '[' . ucfirst((string) $type) . ' message]';
                } else {
                    $body = '[Message]';
                }
                \Log::info('[WABA-INBOUND] unhandled message type', [
                    'type' => (string) ($type ?? ''),
                    'keys' => array_keys($msg),
                    'error' => $errDesc ?: null,
                    // Raw snippet so we can see EXACTLY what Meta delivered for this
                    // message and add first-class extraction (for `unsupported` the
                    // content is stripped by Meta and this will be near-empty — that
                    // one genuinely can't be rendered on the Cloud API).
                    'raw' => mb_substr(json_encode($msg), 0, 1500),
                ]);
                break;
        }

        return [$body, $mediaType, $meta];
    }

    /**
     * Find the device row owning the workspace's WABA number. Falls
     * back to the workspace's first active device if no exact match.
     */
    private function resolveDeviceForWorkspace(int $workspaceId): ?\App\Models\Device
    {
        return \App\Models\Device::query()
            ->where('workspace_id', $workspaceId)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Find the device whose number RECEIVED this inbound (our business number),
     * so a multi-WABA workspace binds the conversation to the correct sender and
     * replies go back out on the SAME number — not the workspace's first device.
     * Matches the webhook's display_phone_number (fallback: the matched config's
     * phone_number) against each device's number. phone_number is encrypted, so
     * we compare in PHP via the model accessor. Returns null → caller falls back.
     */
    private function resolveDeviceForReceivingNumber(int $workspaceId, array $value): ?object
    {
        // The business number that received this inbound is in the webhook
        // metadata (display_phone_number). Match it to a device so the reply
        // goes back out on the SAME number.
        $recv = preg_replace('/\D+/', '', (string) data_get($value, 'metadata.display_phone_number', ''));
        if ($recv === '') return null;

        foreach (\App\Models\Device::query()->where('workspace_id', $workspaceId)->get() as $d) {
            $dn = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
            if ($dn === '') continue;
            // Exact, or one is a country-code-prefixed form of the other.
            if ($dn === $recv || str_ends_with($dn, $recv) || str_ends_with($recv, $dn)) {
                return $d;
            }
        }

        // WABA / Twilio numbers are NOT in the Baileys `devices` table. Resolve a
        // stand-in from wa_provider_configs carrying id = cfg->id — the SAME id
        // the team-inbox outbound MIRROR (WaInboundController) keys its
        // conversation on. Without this the inbound thread got device_id=null
        // while every AI / flow / auto-reply reply was mirrored onto a separate
        // conversation (device_id=cfg->id) → the automated replies were invisible
        // in the thread the operator is looking at.
        $cfg = \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('provider', ['waba', 'twilio'])
            ->get()
            ->first(function ($c) use ($recv) {
                $cn = preg_replace('/\D+/', '', (string) $c->phone_number);
                return $cn !== '' && ($cn === $recv || str_ends_with($cn, $recv) || str_ends_with($recv, $cn));
            });
        if ($cfg) {
            $ownerId = (int) ($cfg->user_id ?: (\App\Models\Workspace::whereKey($cfg->workspace_id)->value('owner_user_id') ?: 0));
            return (object) [
                'id'           => (int) $cfg->id,
                'user_id'      => $ownerId ?: null,
                'workspace_id' => $cfg->workspace_id,
                'country_code' => '',
                'phone_number' => $cfg->phone_number,
            ];
        }
        return null;
    }

    /**
     * Apply a Meta status webhook payload to ALL local records that
     * reference the same wamid:
     *
     *   1. `messages` rows where `meta->wa_message_id == wamid`
     *      (TeamInbox + chat path — the canonical storage).
     *   2. `messages` rows where `from_number == wamid`
     *      (legacy ChatController storage; kept for backwards-compat).
     *   3. `scheduled_message_contacts` rows where
     *      `wa_message_id == wamid` (broadcast recipients).
     *
     * For each row we touch, emit a `message_*` outbound webhook so
     * the customer's external systems get the event. For broadcast
     * rows, ALSO recompute the parent broadcast's aggregate counts
     * and fire `broadcast_message_status_updated`.
     *
     * Status enum mapping mirrors Meta exactly:
     *   sent      → POSTed accepted by Meta; recipient phone not yet contacted
     *   delivered → handed to recipient device
     *   read      → user opened the chat (ONLY if recipient has read receipts ON)
     *   failed    → delivery failed; `st.errors[0]` carries code + reason
     */
    private function applyStatus(int $workspaceId, array $st): void
    {
        $wamid  = (string) ($st['id'] ?? '');
        $status = (string) ($st['status'] ?? '');
        if ($wamid === '' || $status === '') return;
        if (!in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) return;

        $errCode = (string) ($st['errors'][0]['code']    ?? '');
        $errMsg  = (string) ($st['errors'][0]['message'] ?? '');
        $pricing = $st['pricing']      ?? null;
        $convo   = $st['conversation'] ?? null;
        $now     = now();

        // Diagnostic — log EVERY Meta delivery-status update with its full error,
        // so "sent but never delivered" is explainable. A MARKETING template to a
        // low-engagement / opted-out / experiment number comes back status=failed
        // with codes like 131049 ("not delivered to maintain healthy ecosystem
        // engagement"), 131050 ("user has marketing messages turned off"), or
        // 130472 ("user is in an experiment group"). Grep laravel.log for
        // [WABA-status] after a test send to see the exact reason Meta gave.
        Log::info('[WABA-status] update', [
            'workspace_id'  => $workspaceId,
            'wamid'         => $wamid,
            'status'        => $status,
            'recipient'     => $st['recipient_id'] ?? null,
            'error_code'    => $errCode,
            'error_title'   => $st['errors'][0]['title'] ?? null,
            'error_message' => $errMsg,
            'error_details' => $st['errors'][0]['error_data']['details'] ?? null,
            'origin_type'   => $st['conversation']['origin']['type'] ?? null,
            'raw'           => $st,
        ]);

        // --- 1) messages rows ----------------------------------------
        // Filter by provider='waba' so a wamid arriving for a WABA send
        // can't accidentally match a Baileys-sent row that happens to
        // share the same id-shape. Older rows with NULL provider still
        // match (back-compat).
        $messages = Message::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($q) {
                $q->where('provider', 'waba')->orWhereNull('provider');
            })
            ->where(function ($q) use ($wamid) {
                $q->whereJsonContains('meta->wa_message_id', $wamid)
                  ->orWhereJsonContains('meta->wamid',       $wamid)
                  ->orWhere('from_number', $wamid); // legacy ChatController
            })
            ->get();

        foreach ($messages as $msg) {
            // Don't downgrade: read > delivered > sent > failed.
            if (!$this->shouldAdvance($msg->status, $status)) continue;
            $msg->status = $status;
            if ($status === 'delivered' && !$msg->delivered_at) $msg->delivered_at = $now;
            if ($status === 'read'      && !$msg->read_at)      $msg->read_at      = $now;
            if ($status === 'failed' && $errMsg !== '')         $msg->failure_reason = $errMsg;
            // Persist Meta's typed error code + pricing into meta JSON
            // so the inbox detail panel can show "why" without a re-query.
            $metaCol = is_array($msg->meta) ? $msg->meta : [];
            if ($errCode !== '') $metaCol['error_code'] = $errCode;
            if ($pricing)        $metaCol['pricing']    = $pricing;
            if ($convo)          $metaCol['conversation'] = $convo;
            $msg->meta = $metaCol;
            $msg->save();

            // Fire outbound webhook to the customer's URL — one per
            // status transition. WebhookService de-dupes by webhook
            // subscription's `events` filter.
            $this->fireMessageWebhook($workspaceId, $msg, $status, $st);
        }

        // --- 2) broadcast recipient rows (NEW path: scheduled_message_contacts) ----
        $smcRows = \DB::table('scheduled_message_contacts')
            ->where('wa_message_id', $wamid)
            ->get();

        foreach ($smcRows as $row) {
            if (!$this->shouldAdvance($row->status, $status)) continue;
            $patch = ['status' => $status, 'updated_at' => $now];
            if ($status === 'delivered' && !$row->delivered_at) $patch['delivered_at'] = $now;
            if ($status === 'read'      && !$row->read_at)      $patch['read_at']      = $now;
            if ($status === 'failed') {
                $patch['failed_at']     = $row->failed_at ?? $now;
                $patch['error_message'] = mb_substr($errMsg ?: ('error_code:' . $errCode), 0, 512);
            }
            \DB::table('scheduled_message_contacts')->where('id', $row->id)
                ->update($this->patchForTable('scheduled_message_contacts', $patch));

            // Recompute parent broadcast aggregates + fire its webhook.
            // The scheduled_message_id column maps to broadcasts.id
            // on the SnapNest schema we inherited.
            $this->recomputeBroadcastAggregates((int) $row->scheduled_message_id);
            $this->fireBroadcastWebhook((int) $row->scheduled_message_id, $row, $status, $st);
        }

        // --- 3a) campaign recipient rows (wp_campaign_contacts) -----
        // Campaigns store wamid in wp_campaign_contacts.whatsapp_message_id
        // via /api/campaigns/update-contact-status. Without this branch
        // Meta delivered/read webhooks for campaign recipients never
        // reach the campaign aggregate counters.
        try {
            $campRows = \DB::table('wp_campaign_contacts')
                ->where('whatsapp_message_id', $wamid)
                ->get();
            // Diagnostic: prove the status webhook is (or isn't) matching a
            // campaign recipient row. matched=0 → the wamid was never stamped
            // on wp_campaign_contacts (real bug); matched>=1 → the row is found
            // and its status advances. Lets us tell "Meta never sent delivered"
            // apart from "we didn't record delivered".
            \Log::info('[WA-webhook] campaign status match', [
                'wamid'   => $wamid,
                'status'  => $status,
                'matched' => $campRows->count(),
            ]);
            foreach ($campRows as $row) {
                if (!$this->shouldAdvance($row->status, $status)) continue;
                $patch = ['status' => $status, 'updated_at' => $now];
                $retryAt = null;   // set when a Meta-block failure is scheduled for a patient retry
                if ($status === 'delivered') $patch['delivered_at'] = $now;
                if ($status === 'read')      $patch['read_at']      = $now;
                if ($status === 'failed') {
                    $patch['error_message'] = mb_substr($errMsg ?: ('error_code:' . $errCode), 0, 512);
                    $patch['failed_at']     = $row->failed_at ?? $now;

                    // PATIENT AUTO-RETRY for Meta's per-recipient marketing
                    // throttle (131049 "not delivered to maintain healthy
                    // ecosystem engagement"). The send SUCCEEDED — Meta accepted
                    // it and returned a wamid — and only THIS webhook, seconds
                    // later, reports the block. So the send-time retry loop never
                    // fired and the row would sit 'failed' forever. Meta lifts the
                    // block over time, so bump the attempt counter, schedule a
                    // LONG (hours) backoff, and re-arm the campaign below: the
                    // sweeper + runCampaignNowPaced then resend this exact row
                    // later. The counter guarantees it gives up after
                    // campaign_retry_attempts tries. Gated on the retry columns
                    // existing so legacy client DBs degrade to today's behaviour.
                    if ($this->isRetryableMetaBlock($errCode, $errMsg) && $this->campaignRetryColumnsExist()) {
                        $maxAttempts = max(1, (int) \App\Models\SystemSetting::get('campaign_retry_attempts', 3));
                        $attempts    = (int) ($row->send_attempts ?? 0) + 1;
                        $patch['send_attempts'] = $attempts;
                        if ($attempts < $maxAttempts) {
                            // Exponential hours: 2h, 4h, 8h … capped at 24h. A
                            // Meta engagement block is per-number + time-based and
                            // typically clears within a day.
                            $baseHours = max(1, (int) \App\Models\SystemSetting::get('waba_meta_block_retry_backoff_hours', 2));
                            $delayH    = min(24, $baseHours * (2 ** ($attempts - 1)));
                            $retryAt   = $now->copy()->addHours($delayH);
                            $patch['next_attempt_at'] = $retryAt;
                        } else {
                            $patch['next_attempt_at'] = null;   // retries exhausted → terminal
                        }
                    }
                }
                \DB::table('wp_campaign_contacts')->where('id', $row->id)
                    ->update($this->patchForTable('wp_campaign_contacts', $patch));

                // Webhook: campaign_contact_status_updated (Meta delivered/
                // read receipts for campaign recipients — the third status
                // source besides the two Node callbacks).
                $camp = \App\Models\WpCampaign::find($row->campaign_id);
                if ($camp) {
                    // Roll the per-recipient status up into the campaign's
                    // aggregate counters (delivered/read/responded/clicked) so
                    // the analytics KPI cards — which read those columns — stay
                    // in sync with the funnel (which reads the log). Without
                    // this the cards were stuck at 0.
                    $camp->recomputeAggregates();

                    // Re-arm the campaign so the sweeper resends the throttled
                    // row at its backoff time. Fire at the EARLIEST pending retry
                    // so one sweep pass covers every due row. Never resurrect a
                    // cancelled / paused / draft campaign — only a run that had
                    // actually finished or is in flight.
                    if ($retryAt && in_array($camp->status, ['completed', 'running', 'scheduled', 'failed'], true)) {
                        $earliest = \DB::table('wp_campaign_contacts')
                            ->where('campaign_id', $camp->id)
                            ->where('status', 'failed')
                            ->whereNotNull('next_attempt_at')
                            ->min('next_attempt_at');
                        try {
                            $fireAt = $earliest ? \Illuminate\Support\Carbon::parse($earliest) : $retryAt;
                        } catch (\Throwable $e) { $fireAt = $retryAt; }
                        $camp->update([
                            'status'        => 'scheduled',
                            'schedule_type' => 'scheduled',
                            'send_date'     => $fireAt->toDateString(),
                            'send_time'     => $fireAt->format('H:i:s'),
                        ]);
                        \Log::info('[WABA-status] campaign re-armed for Meta-block retry', [
                            'campaign_id' => $camp->id,
                            'row_id'      => $row->id,
                            'attempts'    => $patch['send_attempts'] ?? null,
                            'fire_at'     => $fireAt->toDateTimeString(),
                            'error_code'  => $errCode,
                        ]);
                    }

                    \App\Services\WebhookService::emit('campaign_contact_status_updated', [
                        'workspace_id'  => $camp->workspace_id,
                        'user_id'       => $camp->created_by,
                        'campaign_id'   => $camp->id,
                        'campaign_name' => $camp->campaign_name,
                        'contact_id'    => (int) $row->contact_id,
                        'status'        => $status,
                        'wamid'         => $wamid,
                        'error_message' => $status === 'failed' ? mb_substr($errMsg ?: ('error_code:' . $errCode), 0, 512) : null,
                        'timestamp'     => now()->timestamp,
                    ], $camp->created_by);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[WA-webhook] wp_campaign_contacts patch failed', ['error' => $e->getMessage()]);
        }

        // --- 3b) storefront order messages (wa_orders) --------------
        // Storefront sends OUTBOUND messages tracked on wa_orders.
        // Status webhook should flip these so the order dashboard
        // reflects delivery state.
        try {
            \DB::table('wa_orders')
                ->where('wa_message_id', $wamid)
                ->update(['updated_at' => $now]);
        } catch (\Throwable $e) {
            // wa_orders may not have a status column on every install — best-effort.
        }

        // --- 3c) inbox_messages (backup inbox table) -----------------
        try {
            $inboxRows = \DB::table('inbox_messages')
                ->whereJsonContains('meta->wa_message_id', $wamid)
                ->get();
            foreach ($inboxRows as $row) {
                $patch = ['updated_at' => $now];
                if ($status === 'delivered') $patch['delivered_at'] = $now;
                if ($status === 'read')      $patch['read_at']      = $now;
                if ($status === 'failed')    $patch['failure_reason'] = mb_substr($errMsg, 0, 191);
                \DB::table('inbox_messages')->where('id', $row->id)
                    ->update($this->patchForTable('inbox_messages', $patch));
            }
        } catch (\Throwable $e) {
            // inbox_messages columns vary across installs — best-effort.
        }

        // --- 4) broadcast recipient rows (LEGACY path: broadcast_contacts) ----
        // The current WaDesk broadcast flow goes PHP → Node → Meta, and
        // Node stores the wamid via /api/update-message-status which
        // writes to `broadcast_contacts.whatsapp_message_id`. Without
        // this branch, every status webhook from Meta for a broadcast
        // recipient was MISSED (the pre-2026-05-24 bug).
        //
        // Don't run this branch if the SMC branch above already touched
        // the same broadcast — defends against double-recompute racing
        // on broadcasts.success_count in the unlikely case both pivots
        // hold the same wamid.
        $smcBroadcastIds = $smcRows->pluck('scheduled_message_id')->unique()->all();
        $bcRows = \DB::table('broadcast_contacts')
            ->where('whatsapp_message_id', $wamid)
            ->when(!empty($smcBroadcastIds), fn ($q) => $q->whereNotIn('broadcast_id', $smcBroadcastIds))
            ->get();

        foreach ($bcRows as $row) {
            if (!$this->shouldAdvance($row->status, $status)) continue;
            $patch = ['status' => $status, 'updated_at' => $now];
            if ($status === 'delivered' && !$row->delivered_at) $patch['delivered_at'] = $now;
            if ($status === 'read'      && !$row->read_at)      $patch['read_at']      = $now;
            if ($status === 'failed') {
                $patch['error_message'] = mb_substr($errMsg ?: ('error_code:' . $errCode), 0, 512);
            }
            \DB::table('broadcast_contacts')->where('broadcast_id', $row->broadcast_id)
                ->where('contact_id', $row->contact_id)
                ->update($this->patchForTable('broadcast_contacts', $patch));

            $this->recomputeBroadcastAggregatesLegacy((int) $row->broadcast_id);

            // Build an SMC-shape stub for the broadcast webhook payload.
            $stub = (object) [
                'contact_id'    => $row->contact_id,
                'phone'         => null,           // not stored on broadcast_contacts
                'wa_message_id' => $row->whatsapp_message_id,
            ];
            $this->fireBroadcastWebhook((int) $row->broadcast_id, $stub, $status, $st);
        }
    }

    /**
     * Recompute broadcast aggregates from broadcast_contacts (legacy
     * table populated by the Node → /api/update-message-status flow).
     * Mirrors `recomputeBroadcastAggregates` but for the legacy table.
     */
    private function recomputeBroadcastAggregatesLegacy(int $broadcastId): void
    {
        // Delegates to the model so WABA (here) and Unofficial/Twilio
        // (BroadcastsController::nodeMessageStatus) share one definition
        // of what each counter means — they used to be two copies and
        // only one of them wrote delivered_count/read_count.
        \App\Models\Broadcast::find($broadcastId)?->recomputeAggregates();
    }

    /**
     * Status state machine: once a message is `read`, we never demote
     * it back to `delivered` if Meta retries an older webhook out of
     * order. `failed` is terminal and never overridden by anything
     * other than another `failed` (in case Meta updates the error).
     */
    /**
     * Drop patch keys whose column doesn't exist on $table before an update.
     * Client DBs were inherited from several schema lineages (SnapNest's
     * scheduled_message_contacts, the legacy wp_* campaign tables, …), so
     * optional status columns — failed_at / delivered_at / read_at /
     * error_message — are NOT guaranteed to exist everywhere. Writing a
     * missing column throws SQLSTATE 42S22 (1054 Unknown column) and fails the
     * WHOLE update, which silently loses the status transition + error reason
     * (exactly the `failed_at` on wp_campaign_contacts crash seen in prod).
     * Filtering the patch to real columns keeps status/error saving and only
     * drops the timestamps that particular install can't store. Column lists
     * are introspected once per request and cached.
     */
    private function patchForTable(string $table, array $patch): array
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            try {
                $cache[$table] = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $cache[$table] = null; // couldn't introspect — don't block the write
            }
        }
        $cols = $cache[$table];
        if (empty($cols)) return $patch;
        return array_intersect_key($patch, array_flip($cols));
    }

    /**
     * Does the conversations table carry a contact_id column? Cached per request
     * so the inbound contact-capture doesn't run Schema::hasColumn on every
     * message. Legacy schemas without it just skip the contact_id stamp (the
     * Contact is still created, so the dashboard count still grows).
     */
    private function conversationsHaveContactId(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = \Illuminate\Support\Facades\Schema::hasColumn('conversations', 'contact_id');
            } catch (\Throwable $e) {
                $has = false;
            }
        }
        return $has;
    }

    /**
     * Is this Meta delivery-failure code/message a TEMPORARY, per-recipient
     * throttle that clears over time — i.e. worth a patient resend later?
     *
     * Deliberately a TIGHT allowlist. 131049 ("not delivered to maintain healthy
     * ecosystem engagement") is Meta's per-number marketing-frequency / quality
     * protection: the send was accepted (ok + wamid) and Meta declined to deliver,
     * but the same number succeeds once the window passes. Everything else stays
     * terminal — opt-outs (131050 "marketing messages turned off"), undeliverable
     * (131026), and experiment-group exclusions (130472) will NOT clear on a
     * resend and would only burn attempts + credits.
     */
    private function isRetryableMetaBlock(string $code, string $msg): bool
    {
        if (in_array($code, ['131049'], true)) return true;
        return stripos($msg, 'healthy ecosystem') !== false;
    }

    /**
     * Does wp_campaign_contacts carry the retry-tracking columns this install
     * needs (send_attempts + next_attempt_at)? Some inherited client schemas
     * lack them; without both, the patient Meta-block retry can't be scheduled,
     * so we skip it and fall back to today's plain "record the failure" path.
     * Introspected once per request.
     */
    private function campaignRetryColumnsExist(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing('wp_campaign_contacts');
                $ok = in_array('send_attempts', $cols, true) && in_array('next_attempt_at', $cols, true);
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * Ask Meta whether this phone_number_id really belongs to the token we hold.
     *
     * Used only when the X-Hub-Signature-256 cannot be verified because the WABA
     * is subscribed to the customer's own Meta App (override_callback_uri) and we
     * have no App Secret for it. Meta answers GET /{phone_number_id} only for a
     * token that actually governs that number, so a 200 with a matching id is
     * meaningful evidence the number is ours.
     *
     * Cached for an hour and keyed on the id, because this runs on a webhook hit:
     * an uncached call would add a Graph round-trip to EVERY inbound message and
     * burn rate limit during a burst.
     */
    private function confirmsOwnership(WaProviderConfig $cfg, string $phoneNumberId): bool
    {
        if ($phoneNumberId === '') return false;

        $token = (string) ($cfg->creds()['access_token'] ?? '');
        if ($token === '') return false;

        return (bool) \Cache::remember(
            'waba:own:' . $cfg->id . ':' . $phoneNumberId,
            now()->addHour(),
            function () use ($token, $phoneNumberId, $cfg) {
                try {
                    $ver  = (string) (\App\Models\SystemSetting::get('waba_graph_version', 'v23.0') ?: 'v23.0');
                    $resp = \Illuminate\Support\Facades\Http::withToken($token)
                        ->acceptJson()->timeout(8)
                        ->get("https://graph.facebook.com/{$ver}/{$phoneNumberId}", ['fields' => 'id']);

                    $ok = $resp->successful()
                        && (string) $resp->json('id') === $phoneNumberId;

                    if (!$ok) {
                        \Log::warning('[WA-webhook] ownership check FAILED', [
                            'config_id'       => $cfg->id,
                            'phone_number_id' => $phoneNumberId,
                            'http'            => $resp->status(),
                            'error'           => $resp->json('error.message'),
                        ]);
                    }
                    return $ok;
                } catch (\Throwable $e) {
                    // A network blip must not silently start accepting forged
                    // payloads — fail closed and let the reject path run.
                    \Log::warning('[WA-webhook] ownership check threw', ['error' => $e->getMessage()]);
                    return false;
                }
            }
        );
    }

    /**
     * Recover the owning workspace from a wamid alone.
     *
     * Used when a status webhook arrives for a WABA that no longer resolves to a
     * WaProviderConfig. The recipient log rows carry the wamid and point back at
     * their parent campaign/broadcast, so the workspace is recoverable even
     * though the provider config is not. Returns 0 when the wamid is not ours.
     */
    private function workspaceIdForWamid(string $wamid): int
    {
        if ($wamid === '') return 0;

        try {
            $campaignId = \DB::table('wp_campaign_contacts')
                ->where('whatsapp_message_id', $wamid)
                ->value('campaign_id');
            if ($campaignId) {
                $ws = \DB::table('wpcampaigns')->where('id', $campaignId)->value('workspace_id');
                if ($ws) return (int) $ws;
            }

            $broadcastId = \DB::table('scheduled_message_contacts')
                ->where('wa_message_id', $wamid)
                ->value('scheduled_message_id');
            if ($broadcastId) {
                $ws = \DB::table('broadcasts')->where('id', $broadcastId)->value('workspace_id');
                if ($ws) return (int) $ws;
            }
        } catch (\Throwable $e) {
            // Schema drift between installs must not cost us the receipt — the
            // wamid-only branches still run with workspace 0.
            \Log::warning('[WA-webhook] workspaceIdForWamid failed', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    private function shouldAdvance(?string $current, string $incoming): bool
    {
        $rank = ['' => 0, 'pending' => 1, 'queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'failed' => 5];
        $curRank = $rank[$current ?? ''] ?? 0;
        $incRank = $rank[$incoming]      ?? 0;

        // Don't undo a terminal failed state with a stale "sent".
        if ($current === 'failed' && $incoming !== 'failed') return false;
        // Read is terminal-ish; ignore later delivered/sent.
        if ($current === 'read' && in_array($incoming, ['delivered', 'sent'], true)) return false;
        return $incRank >= $curRank;
    }

    /**
     * Per-broadcast aggregate recompute. Cheap because the SMC table
     * is indexed on (scheduled_message_id, status). Called after each
     * webhook hit that flips a recipient row.
     */
    private function recomputeBroadcastAggregates(int $broadcastId): void
    {
        $rows = \DB::table('scheduled_message_contacts')
            ->select('status', \DB::raw('COUNT(*) as c'))
            ->where('scheduled_message_id', $broadcastId)
            ->groupBy('status')
            ->pluck('c', 'status');

        $sent      = (int) ($rows['sent']      ?? 0);
        $delivered = (int) ($rows['delivered'] ?? 0);
        $read      = (int) ($rows['read']      ?? 0);
        $failed    = (int) ($rows['failed']    ?? 0);

        // success_count = sent + delivered + read (anything that got
        // through Meta's edge). fail_count = failed.
        // delivered_count = delivered + read (read implies delivered).
        // read_count      = read.
        \DB::table('broadcasts')->where('id', $broadcastId)->update([
            'success_count'   => $sent + $delivered + $read,
            'delivered_count' => $delivered + $read,
            'read_count'      => $read,
            'fail_count'      => $failed,
            'updated_at'      => now(),
        ]);
    }

    private function fireMessageWebhook(int $workspaceId, Message $msg, string $status, array $st): void
    {
        $eventName = match ($status) {
            'sent'      => 'message_sent',
            'delivered' => 'message_delivered',
            'read'      => 'message_read',
            'failed'    => 'message_failed',
            default     => null,
        };
        if (!$eventName) return;

        \App\Services\WebhookService::dispatch($eventName, [
            'workspace_id' => $workspaceId,
            'user_id'      => $msg->user_id,
            'message_id'   => $msg->id,
            'wamid'        => $st['id']            ?? null,
            'recipient'    => $msg->to_number      ?? null,
            'status'       => $status,
            'timestamp'    => (int) ($st['timestamp'] ?? now()->timestamp),
            'error_code'   => $st['errors'][0]['code']    ?? null,
            'error_reason' => $st['errors'][0]['message'] ?? null,
            'pricing'      => $st['pricing']      ?? null,
            'conversation' => $st['conversation'] ?? null,
        ], $msg->user_id);
    }

    private function fireBroadcastWebhook(int $broadcastId, $smcRow, string $status, array $st): void
    {
        $bc = \DB::table('broadcasts')->where('id', $broadcastId)->first();
        if (!$bc) return;

        \App\Services\WebhookService::dispatch('broadcast_message_status_updated', [
            'workspace_id'     => $bc->workspace_id,
            'user_id'          => $bc->user_id,
            'broadcast_id'     => $broadcastId,
            'broadcast_name'   => $bc->name,
            'template_id'      => $bc->template_id,
            'contact_id'       => $smcRow->contact_id,
            'phone'            => $smcRow->phone,
            'wamid'            => $smcRow->wa_message_id,
            'status'           => $status,
            'timestamp'        => (int) ($st['timestamp'] ?? now()->timestamp),
            'error_code'       => $st['errors'][0]['code']    ?? null,
            'error_reason'     => $st['errors'][0]['message'] ?? null,
            'aggregate'        => [
                'sent'      => $bc->success_count,
                'delivered' => $bc->delivered_count,
                'read'      => $bc->read_count,
                'failed'    => $bc->fail_count,
                'clicked'   => $bc->clicked_count,
                'total'     => $bc->total_recipients,
            ],
        ], $bc->user_id);
    }

    private function receiveTwilio(Request $request): JsonResponse
    {
        // Twilio signs every webhook with X-Twilio-Signature. Require
        // a configured TWILIO_AUTH_TOKEN so forged form-encoded posts
        // can't impersonate a Twilio number's inbound traffic.
        $authToken = (string) env('TWILIO_AUTH_TOKEN', '');
        if ($authToken === '') {
            \Log::error('[WA-webhook] TWILIO_AUTH_TOKEN not set — refusing webhook');
            return response()->json(['ok' => false, 'error' => 'server misconfigured'], 500);
        }
        // Per Twilio: sort POST params by key, concat key+value pairs,
        // prepend the full URL, HMAC-SHA1 with the auth token, base64.
        $given = (string) $request->header('X-Twilio-Signature', '');
        $url   = $request->fullUrl();
        $params = $request->post();
        ksort($params);
        $payloadStr = $url;
        foreach ($params as $k => $v) $payloadStr .= $k . $v;
        $expected = base64_encode(hash_hmac('sha1', $payloadStr, $authToken, true));
        if (!hash_equals($expected, $given)) {
            \Log::warning('[WA-webhook] Twilio signature mismatch');
            return response()->json(['ok' => false, 'error' => 'bad signature'], 401);
        }

        $from = preg_replace('/^whatsapp:/', '', (string) $request->input('From', ''));
        $body = (string) $request->input('Body', '');
        $name = (string) $request->input('ProfileName', '');
        $buttonText    = (string) $request->input('ButtonText', '');
        $buttonPayload = (string) $request->input('ButtonPayload', '');
        $listId        = (string) $request->input('ListId', '');
        $numMedia      = (int) $request->input('NumMedia', 0);
        $lat           = $request->input('Latitude');
        $lng           = $request->input('Longitude');
        $messageSid    = (string) $request->input('MessageSid', '');

        // Resolve workspace by Twilio "To" (which is our From number).
        $to = preg_replace('/^whatsapp:/', '', (string) $request->input('To', ''));
        $config = WaProviderConfig::query()
            ->where('provider', 'twilio')
            ->where('phone_number', $to)
            ->first();
        $workspaceId = $config?->workspace_id;
        if (!$workspaceId) return response()->json(['ok' => true]);

        // Translate Twilio's form-encoded shape into the Meta-shape
        // $msg array that captureInboundMessage understands. This
        // single bridge wires Twilio into the full inbound pipeline:
        // conversation creation, MessageReceived broadcast, routing
        // rules, AI auto-reply, CRM webhooks, AND flow-resume for
        // interactive replies (list/button taps from appointment
        // booking, surveys, polls, etc.). Previously Twilio inbound
        // just persisted a Message row and was invisible to every
        // automation surface — booking flows, auto-replies, AI
        // assistants all silently no-op'd for Twilio workspaces.
        //
        // `to` is stamped too so captureInboundMessage's fromMe-echo
        // detector can compare `from === workspace's own phone`. In
        // BYON setups (operator's personal phone shares the Twilio
        // number), this lets us mirror mobile-typed replies into
        // /team-inbox the same way Baileys + WABA do.
        $msg = [
            'id'        => $messageSid !== '' ? $messageSid : ('twi.' . bin2hex(random_bytes(8))),
            'from'      => $from,
            'to'        => $to,
            'timestamp' => (string) time(),
        ];

        if ($buttonText !== '' || $buttonPayload !== '') {
            // Quick-reply button tap — fold into Meta's `button` shape so
            // the existing extractor (line 616) picks it up unchanged.
            $msg['type']   = 'button';
            $msg['button'] = [
                'text'    => $buttonText !== '' ? $buttonText : $buttonPayload,
                'payload' => $buttonPayload,
            ];
        } elseif ($listId !== '') {
            // List selection — map to Meta's `interactive.list_reply`
            // (line 628) so the appointment booking handler, flow
            // resume, etc. all see a standard list_reply payload.
            $msg['type']        = 'interactive';
            $msg['interactive'] = [
                'type'       => 'list_reply',
                'list_reply' => [
                    'id'    => $listId,
                    'title' => $body ?: $listId,
                ],
            ];
        } elseif ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
            $msg['type']     = 'location';
            $msg['location'] = [
                'latitude'  => (float) $lat,
                'longitude' => (float) $lng,
                'name'      => $body,
            ];
        } elseif ($numMedia > 0) {
            // Twilio media — map to image / video / audio / document by
            // first MediaContentType0. Meta's extractor reads `image.id`
            // etc.; we stash the Twilio MediaUrl0 in `link` so downstream
            // can fetch directly (Twilio media is auth'd, not public —
            // a separate fetcher hits the URL with basic-auth creds).
            $mime  = (string) $request->input('MediaContentType0', '');
            $url   = (string) $request->input('MediaUrl0', '');
            $mtype = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                default                          => 'document',
            };
            $msg['type']    = $mtype;
            $msg[$mtype]    = ['mime_type' => $mime, 'link' => $url];
            if ($body !== '') $msg[$mtype]['caption'] = $body;
        } elseif ($body !== '') {
            $msg['type'] = 'text';
            $msg['text'] = ['body' => $body];
        } else {
            // Empty payload (Twilio occasionally sends an "ack" with no
            // body) — nothing actionable, ack the webhook and exit.
            return response()->json(['ok' => true]);
        }

        // Same envelope shape Meta's webhook uses, so the contact
        // profile-name resolution in captureInboundMessage works
        // unchanged.
        $value = [
            'contacts' => [['profile' => ['name' => $name]]],
            'messages' => [$msg],
        ];

        try {
            $this->captureInboundMessage($workspaceId, $msg, $value, 'twilio');
        } catch (\Throwable $e) {
            \Log::warning('[TWILIO-INBOUND] captureInboundMessage failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Tag a workspace conversation with a "interested_sku" attribute
     * when the buyer replies to a product card. Lets bots + agents
     * see at a glance what the buyer was looking at.
     *
     * Best-effort — silently skipped if we can't resolve the
     * conversation or the SKU isn't known to us.
     */
    private function tagConversationWithReferredProduct(int $workspaceId, string $fromPhone, string $retailerId): void
    {
        try {
            $conv = \App\Models\Conversation::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($fromPhone) {
                    $q->where('raw_jid', $fromPhone)
                      ->orWhere('alt_jid', $fromPhone);
                })->orderByDesc('id')->first();
            if (!$conv) return;

            $product = \App\Models\WaProduct::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($retailerId) {
                    $q->where('meta_retailer_id', $retailerId)
                      ->orWhere('sku', $retailerId);
                })->first();

            $conv->forceFill([
                'interested_sku' => $retailerId,
                'interested_product_id' => $product?->id,
                'interested_seen_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            \Log::warning('referred_product tagging failed', ['e' => $e->getMessage()]);
        }
    }
}
