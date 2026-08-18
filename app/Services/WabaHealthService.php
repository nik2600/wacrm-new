<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Support\Facades\Http;

/**
 * WABA Account Health — fetches EVERYTHING Meta exposes about a connected
 * WhatsApp Business number and rolls it up into a single diagnostics
 * payload the /devices/waba/{id}/health page renders.
 *
 * Everything is pulled live from the Graph API with the WABA's own
 * System-User token (same token used to send). We hit five nodes:
 *
 *   1. Phone Number node   GET /{phone_number_id}?fields=…,health_status
 *        verified_name, display_phone_number, quality_rating, status,
 *        code_verification_status, name_status, account_mode,
 *        platform_type, throughput, messaging_limit_tier,
 *        is_official_business_account, is_pin_enabled, last_onboarded_time
 *        + health_status (can_send_message + per-entity errors)
 *
 *   2. WABA node           GET /{waba_id}?fields=…
 *        name, currency, timezone_id, message_template_namespace,
 *        account_review_status, business_verification_status, country,
 *        ownership_type, primary_funding_id, owner_business_info,
 *        on_behalf_of_business_info, health_status
 *
 *   3. Token debug         GET /debug_token?input_token=…
 *        is_valid, scopes[], granular_scopes[], app_id, application,
 *        expires_at, data_access_expires_at, type
 *
 *   4. Webhook subscription GET /{waba_id}/subscribed_apps
 *        which apps (ours) are subscribed → can we receive inbound /
 *        status callbacks at all
 *
 *   5. Templates tally     GET /{waba_id}/message_templates?summary=true
 *        total + per-status counts (APPROVED / PENDING / REJECTED / PAUSED)
 *
 * Every call is independently try/caught: one failing node never blanks
 * the whole page — it lands in `errors[]` with a human message and the
 * rest still render. `issues[]` is the unified "what's wrong / blocked"
 * list the UI shows at the top (the customer's first question is always
 * "is my number working, and if not, why").
 */
class WabaHealthService
{
    private string $base;
    private string $version;

    public function __construct()
    {
        $this->version = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $this->base    = 'https://graph.facebook.com/' . ltrim($this->version, '/');
    }

    /**
     * Pull the full health snapshot for one connected WABA row.
     * Returns a structured array (never throws) the view consumes.
     */
    public function fetch(WaProviderConfig $cfg): array
    {
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $pnid  = (string) ($meta['phone_number_id'] ?? '');
        $waba  = (string) ($meta['waba_id'] ?? '');
        $biz   = (string) ($meta['business_id'] ?? '');

        $out = [
            'ok'         => true,
            'version'    => $this->version,
            'fetched_at' => now()->toIso8601String(),
            'ids'        => ['phone_number_id' => $pnid, 'waba_id' => $waba, 'business_id' => $biz],
            'phone'      => [],
            'waba'       => [],
            'token'      => [],
            'webhook'    => ['subscribed' => null, 'apps' => []],
            'templates'  => ['total' => null, 'by_status' => []],
            'issues'     => [],
            'errors'     => [],
        ];

        if ($token === '') {
            $out['ok'] = false;
            $out['errors'][] = 'No access token stored for this number — reconnect it from the Add device → Meta (WABA) flow.';
            $this->pushIssue($out, 'critical', 'Token', 0, 'No stored access token',
                'This number has no Meta access token saved.', 'Disconnect and reconnect the WABA number.');
            return $out;
        }

        // ── 1. Phone number node (+ health_status) ───────────────────────
        if ($pnid !== '') {
            $r = $this->get($token, $pnid, [
                'fields' => 'verified_name,display_phone_number,quality_rating,messaging_limit_tier,'
                          . 'code_verification_status,name_status,status,account_mode,throughput,'
                          . 'platform_type,is_official_business_account,is_pin_enabled,'
                          . 'last_onboarded_time,health_status',
            ]);
            if ($r['ok']) {
                $out['phone'] = $r['data'];
                $this->absorbHealthStatus($out, $r['data']['health_status'] ?? null);
            } else {
                $out['errors'][] = 'Phone number: ' . $r['error'];
                $this->pushIssue($out, 'critical', 'Phone', $r['code'], 'Could not read phone number', $r['error'], $r['solution']);
            }
        }

        // ── 2. WABA node ─────────────────────────────────────────────────
        if ($waba !== '') {
            // Progressive field sets. The rich request includes billing/business
            // fields (primary_funding_id, owner_business_info, on_behalf_of_
            // business_info) that need elevated permissions — if the token can't
            // read even ONE of them Meta 400s the WHOLE request, blanking every
            // field on the Business-account panel (name/currency/timezone/country
            // that the token CAN read). Fall back to the safe subset so those
            // populate; only if the SAFE read also fails is the WABA genuinely
            // inaccessible (error #10 → the reconnect message below).
            $richFields = 'name,currency,timezone_id,message_template_namespace,'
                        . 'account_review_status,business_verification_status,country,'
                        . 'ownership_type,primary_funding_id,owner_business_info,'
                        . 'on_behalf_of_business_info,health_status';
            $safeFields = 'name,currency,timezone_id,message_template_namespace,'
                        . 'account_review_status,business_verification_status,country,health_status';

            $r = $this->get($token, $waba, ['fields' => $richFields]);
            if (!$r['ok']) {
                $r2 = $this->get($token, $waba, ['fields' => $safeFields]);
                if ($r2['ok']) $r = $r2;
            }
            if ($r['ok']) {
                $out['waba'] = $r['data'];
                $this->absorbHealthStatus($out, $r['data']['health_status'] ?? null);
                $this->absorbWabaStatuses($out, $r['data']);
            } else {
                // Meta error #10 = the app's Business isn't a registered WhatsApp
                // Tech/Solution Provider for this WABA — almost always because the
                // WABA was removed / un-shared from the connected Business. Show a
                // clear, actionable message instead of the raw "requires BSP" error.
                $isPerm = ((int) ($r['code'] ?? 0) === 10);
                $msg = $isPerm
                    ? 'This WhatsApp Business Account is no longer accessible — it was removed or un-shared from the connected Meta Business. Reconnect the number, or remove this stale connection.'
                    : $r['error'];
                $sol = $isPerm
                    ? 'Re-run Embedded Signup to reconnect, or click Remove on the device to delete the stale WABA link.'
                    : $r['solution'];
                $out['errors'][] = 'WABA: ' . $msg;
                $this->pushIssue($out, 'warning', 'WABA', $r['code'], 'Could not read WABA account', $msg, $sol);
            }
        }

        // ── 2b. Conversation analytics — this month's free vs paid usage ──
        // Meta gives every WABA a free monthly allowance of SERVICE (customer-
        // initiated) conversations; everything else is billed. We total this
        // calendar month's conversations broken down by type so the health page
        // can show "X free used · Y paid · Z left" — the "how many messages
        // free do we have" the operator asks for. Best-effort + tolerant: a
        // schema change or a brand-new number just yields no block (never an
        // error), so it can't break the rest of the health snapshot.
        if ($waba !== '') {
            $start = now()->startOfMonth()->timestamp;
            $end   = now()->timestamp;
            $ca = $this->get($token, $waba, [
                'fields' => "conversation_analytics.start({$start}).end({$end}).granularity(MONTHLY).dimensions([\"CONVERSATION_TYPE\"])",
            ]);
            if ($ca['ok']) {
                $points = $ca['data']['conversation_analytics']['data'][0]['data_points'] ?? [];
                $free = 0; $paid = 0; $cost = 0.0;
                foreach ((array) $points as $p) {
                    $n    = (int) ($p['conversation'] ?? 0);
                    $type = strtoupper((string) ($p['conversation_type'] ?? ''));
                    if (str_contains($type, 'FREE')) $free += $n; else $paid += $n;
                    $cost += (float) ($p['cost'] ?? 0);
                }
                $freeAllowance = 1000; // Meta's free service-conversation allowance / month
                $out['conversations'] = [
                    'free_used'  => $free,
                    'free_total' => $freeAllowance,
                    'free_left'  => max(0, $freeAllowance - $free),
                    'paid'       => $paid,
                    'total'      => $free + $paid,
                    'cost'       => round($cost, 2),
                ];
            }
        }

        // ── 3. Token debug — permissions + validity + expiry ─────────────
        // Meta's /debug_token requires the CALLER to be an APP access token
        // ({app_id}|{app_secret}) or an app owner/developer — NOT the token being
        // inspected. Passing the WABA token as the caller 400s with "(#100) …
        // must provide an app access token …", so the debug never ran and a
        // perfectly valid, WORKING token was mislabelled INVALID. Use the app
        // token: this device's own app creds if present, else the platform's
        // Embedded-Signup app (the app that ISSUED the token, so it CAN debug it).
        $appId     = trim((string) ($creds['app_id'] ?? '')) ?: trim((string) SystemSetting::get('waba_app_id', ''));
        $appSecret = trim((string) ($creds['app_secret'] ?? '')) ?: trim((string) SystemSetting::get('waba_app_secret', ''));
        $tokenDebugged = false;
        if ($appId !== '' && $appSecret !== '') {
            $t = $this->get($appId . '|' . $appSecret, 'debug_token', ['input_token' => $token]);
            if ($t['ok'] && isset($t['data']['data'])) {
                $tokenDebugged = true;
                $d = $t['data']['data'];
                $scopes  = array_values((array) ($d['scopes'] ?? []));
                $granular = array_values((array) ($d['granular_scopes'] ?? []));
                $isValid = (bool) ($d['is_valid'] ?? false);
                $expires = (int) ($d['expires_at'] ?? 0); // 0 = never (permanent token)
                $out['token'] = [
                    'is_valid'               => $isValid,
                    'app_id'                 => $d['app_id'] ?? null,
                    'application'            => $d['application'] ?? null,
                    'type'                   => $d['type'] ?? null,
                    'scopes'                 => $scopes,
                    'granular_scopes'        => $granular,
                    'expires_at'             => $expires,
                    'expires_never'          => $expires === 0,
                    'data_access_expires_at' => (int) ($d['data_access_expires_at'] ?? 0),
                ];
                // Required scopes for WABA messaging + management.
                $required = ['whatsapp_business_messaging', 'whatsapp_business_management'];
                $missing  = array_values(array_diff($required, $scopes));
                if (!$isValid) {
                    $this->pushIssue($out, 'critical', 'Token', 190, 'Access token is invalid',
                        'Meta reports this token is no longer valid.', 'Generate a new permanent System-User token and reconnect.');
                }
                if (!empty($missing)) {
                    // Don't over-report. whatsapp_business_messaging missing = can't SEND
                    // at all (critical). Only whatsapp_business_management missing =
                    // sending STILL works, but reading/syncing templates + WABA
                    // management is blocked (this is exactly why templates show 0 and
                    // template campaigns get rejected) — a warning, not a send-block.
                    if (in_array('whatsapp_business_messaging', $missing, true)) {
                        $this->pushIssue($out, 'critical', 'Token', 200, 'Missing permission: ' . implode(', ', $missing),
                            'The token lacks whatsapp_business_messaging — it cannot send messages on this number.',
                            'Regenerate the System-User token granting both whatsapp_business_messaging and whatsapp_business_management.');
                    } else {
                        $this->pushIssue($out, 'warning', 'Token', 200, 'Missing permission: whatsapp_business_management',
                            'Sending works, but reading/syncing message templates + WABA management is blocked — this is why templates show 0 and template campaigns are rejected.',
                            'Regenerate the System-User token WITH whatsapp_business_management added (the app also needs Advanced Access for it).');
                    }
                }
                if ($expires > 0 && $expires < now()->addDays(7)->timestamp) {
                    $this->pushIssue($out, 'warning', 'Token', 0, 'Token expires soon',
                        'This access token expires on ' . date('M j, Y H:i', $expires) . '.',
                        'Replace it with a permanent System-User token so sends do not stop.');
                }
            } else {
                $out['errors'][] = 'Token debug: ' . ($t['error'] ?? 'unavailable');
            }
        }
        // Fallback — no app creds, OR debug_token failed. Do NOT claim INVALID: if
        // ANY authenticated Graph read above succeeded with this token, Meta
        // accepted it → the token is PROVABLY valid; we just can't list its scopes/
        // expiry without the app secret. This is what makes the page show VALID
        // instead of the misleading INVALID for a token that is clearly working.
        if (!$tokenDebugged) {
            $readOk = !empty($out['phone']) || !empty($out['waba']);
            $out['token'] = [
                'is_valid'               => $readOk ? true : null,
                'app_id'                 => $appId ?: null,
                'application'            => null,
                'type'                   => null,
                'scopes'                 => [],
                'granular_scopes'        => [],
                'expires_at'             => 0,
                'expires_never'          => true,
                'data_access_expires_at' => 0,
                'debug_unavailable'      => true,
            ];
            if ($appId === '' || $appSecret === '') {
                $this->pushIssue($out, 'info', 'Token', 0, 'Token scopes/expiry not shown',
                    'The token is working (Meta accepted the account reads on this page), but the Meta App ID + App Secret are not configured, so the permission list and expiry can not be introspected.',
                    'Add the Meta App ID + App Secret in Admin → WhatsApp (WABA) settings to enable full token diagnostics.');
            } elseif (!$readOk) {
                $this->pushIssue($out, 'warning', 'Token', 0, 'Could not verify the token',
                    'Meta did not accept this token for the token debug or the account reads.',
                    'Reconnect the number with a fresh permanent System-User token.');
            }
        }

        // ── 4. Webhook subscription ──────────────────────────────────────
        if ($waba !== '') {
            $s = $this->get($token, "{$waba}/subscribed_apps", []);
            if ($s['ok']) {
                $apps = (array) ($s['data']['data'] ?? []);
                $names = [];
                foreach ($apps as $a) {
                    $names[] = (string) ($a['whatsapp_business_api_data']['name'] ?? ($a['name'] ?? 'App'));
                }
                $out['webhook'] = ['subscribed' => count($apps) > 0, 'apps' => $names];
                if (count($apps) === 0) {
                    $this->pushIssue($out, 'critical', 'Webhook', 0, 'No app subscribed to webhooks',
                        'This WABA is not subscribed to any app, so inbound messages and delivery statuses will not reach the system.',
                        'Reconnect the number, or re-run the subscribe step (POST /{waba_id}/subscribed_apps).');
                }
            } else {
                $out['errors'][] = 'Webhook: ' . $s['error'];
            }
        }

        // ── 5. Templates tally ───────────────────────────────────────────
        if ($waba !== '') {
            $tpl = $this->get($token, "{$waba}/message_templates", [
                'fields'  => 'name,status,category',
                'limit'   => 200,
                'summary' => 'true',
            ]);
            if ($tpl['ok']) {
                $rows = (array) ($tpl['data']['data'] ?? []);
                $byStatus = [];
                foreach ($rows as $row) {
                    $st = strtoupper((string) ($row['status'] ?? 'UNKNOWN'));
                    $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
                }
                $total = (int) ($tpl['data']['summary']['total_count'] ?? count($rows));
                $out['templates'] = ['total' => $total, 'by_status' => $byStatus];
            } else {
                // Meta error #3 = "(#3) Application does not have the capability to
                // make this API call" — the APP/token is missing the
                // whatsapp_business_management capability (Advanced Access); it is
                // NOT a removed-WABA / data problem. #10/#200 here = the token
                // can't see this WABA's templates (removed / un-shared / missing
                // scope). Give an actionable message + surface it as an issue.
                $tcode = (int) ($tpl['code'] ?? 0);
                if ($tcode === 3) {
                    $tmsg = 'The connected app cannot read templates — it is missing the "whatsapp_business_management" capability (Advanced Access).';
                    $tsol = 'Meta App Dashboard → App Review → Permissions and Features: request Advanced Access for whatsapp_business_management. Then regenerate the System User token with whatsapp_business_management + whatsapp_business_messaging.';
                } elseif (in_array($tcode, [10, 200], true)) {
                    $tmsg = 'The connected token cannot read this WhatsApp Business Account\'s templates — it was removed/un-shared, or the token is missing whatsapp_business_management.';
                    $tsol = 'Reconnect via Embedded Signup, or grant the System User access to this WABA with the right scopes.';
                } else {
                    $tmsg = $tpl['error'];
                    $tsol = $tpl['solution'] ?? 'Check the app permissions and the WABA connection, then sync again.';
                }
                $out['errors'][] = 'Templates: ' . $tmsg;
                $this->pushIssue($out, 'warning', 'Templates', $tcode, 'Could not read templates', $tmsg, $tsol);
            }
        }

        // Sort issues critical → warning → info for the top banner.
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($out['issues'], fn ($a, $b) => ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9));

        $out['overall'] = $this->overall($out);
        return $out;
    }

    /** Roll the issue list into a single status word the banner uses. */
    private function overall(array $out): string
    {
        foreach ($out['issues'] as $i) {
            if ($i['severity'] === 'critical') return 'blocked';
        }
        foreach ($out['issues'] as $i) {
            if ($i['severity'] === 'warning') return 'attention';
        }
        return 'healthy';
    }

    /** Translate Meta's health_status.entities[*] into issues. */
    private function absorbHealthStatus(array &$out, $hs): void
    {
        if (!is_array($hs) || empty($hs['entities'])) return;
        foreach ((array) $hs['entities'] as $ent) {
            $can = strtoupper((string) ($ent['can_send_message'] ?? 'AVAILABLE'));
            $type = (string) ($ent['entity_type'] ?? 'ENTITY');
            if ($can === 'BLOCKED') {
                foreach ((array) ($ent['errors'] ?? []) as $err) {
                    $this->pushIssue($out, 'critical', $type,
                        (int) ($err['error_code'] ?? 0),
                        'Blocked: ' . ($err['error_description'] ?? 'messaging requirement not met'),
                        (string) ($err['error_description'] ?? ''),
                        (string) ($err['possible_solution'] ?? ''));
                }
                if (empty($ent['errors'])) {
                    $this->pushIssue($out, 'critical', $type, 0, "$type is blocked from sending",
                        'Meta reports this entity cannot send messages.', 'Open Meta Business Suite for details.');
                }
            } elseif ($can === 'LIMITED') {
                $info = implode('; ', (array) ($ent['additional_info'] ?? []));
                $this->pushIssue($out, 'warning', $type, 0, "$type has messaging limitations",
                    $info ?: 'Meta reports limited messaging capability.', 'Review limits in Meta Business Suite.');
            }
        }
    }

    /** WABA-level review + verification statuses → issues. */
    private function absorbWabaStatuses(array &$out, array $w): void
    {
        $review = strtoupper((string) ($w['account_review_status'] ?? ''));
        if ($review !== '' && $review !== 'APPROVED') {
            $sev = $review === 'PENDING' ? 'warning' : 'critical';
            $this->pushIssue($out, $sev, 'WABA', 0, 'Account review: ' . $review,
                'Meta has not approved this WhatsApp Business Account.',
                $review === 'PENDING' ? 'Wait for Meta review, or check Business Suite for required steps.'
                                      : 'Resolve the review status in Meta Business Suite before sending.');
        }
        $verif = strtolower((string) ($w['business_verification_status'] ?? ''));
        if ($verif !== '' && $verif !== 'verified') {
            $this->pushIssue($out, 'warning', 'Business', 0, 'Business verification: ' . ($w['business_verification_status'] ?? '—'),
                'The owning business is not fully verified, which caps messaging limits and feature access.',
                'Complete Business Verification in Meta Business Settings.');
        }
    }

    /** Single Graph GET with token; normalised {ok,data|error,code,solution}. */
    private function get(string $token, string $path, array $query): array
    {
        try {
            $resp = Http::withToken($token)->acceptJson()->timeout(12)
                ->get("{$this->base}/{$path}", $query);
            if ($resp->successful()) {
                return ['ok' => true, 'data' => (array) $resp->json()];
            }
            $err = (array) $resp->json('error', []);
            return [
                'ok'       => false,
                'error'    => $this->translate($err),
                'code'     => (int) ($err['code'] ?? $resp->status()),
                'solution' => $this->solution($err),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Could not reach Meta: ' . $e->getMessage(), 'code' => 0, 'solution' => 'Retry shortly; if it persists, check the server can reach graph.facebook.com.'];
        }
    }

    /** Human-readable copy for Meta's typed error envelopes. */
    private function translate(array $err): string
    {
        $code    = (int) ($err['code'] ?? 0);
        $subcode = (int) ($err['error_subcode'] ?? 0);
        $message = (string) ($err['message'] ?? 'Unknown Meta error.');

        if ($code === 190) {
            return match ($subcode) {
                463     => 'Access token expired — generate a new permanent System-User token.',
                467     => 'Meta session is invalid — re-authorize the app.',
                460     => 'Facebook password changed — re-authorize the WABA.',
                default => 'Meta rejected the token: ' . $message,
            };
        }
        if ($code === 200 && $subcode === 1349174) {
            return 'Token is missing the whatsapp_business_management permission.';
        }
        if ($code === 100) {
            return 'Bad parameter: ' . $message;
        }
        return 'Meta error ' . $code . ($subcode ? '/' . $subcode : '') . ': ' . $message;
    }

    private function solution(array $err): string
    {
        $code    = (int) ($err['code'] ?? 0);
        $subcode = (int) ($err['error_subcode'] ?? 0);
        if ($code === 190) return 'Reconnect this number with a fresh permanent System-User token.';
        if ($code === 200 && $subcode === 1349174) return 'Regenerate the token with whatsapp_business_management scope.';
        return 'Check the number in Meta Business Suite.';
    }

    private function pushIssue(array &$out, string $severity, string $area, int $code, string $title, string $detail, string $solution): void
    {
        $out['issues'][] = [
            'severity' => $severity,
            'area'     => $area,
            'code'     => $code,
            'title'    => $title,
            'detail'   => $detail,
            'solution' => $solution,
        ];
    }
}
