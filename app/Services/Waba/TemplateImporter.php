<?php

namespace App\Services\Waba;

use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pulls the templates that already live in a workspace's WhatsApp
 * Business Account (created directly in Meta Business Manager, or
 * approved before the WABA was connected here) INTO our local
 * `wa_templates` table.
 *
 * This is the missing half of the template sync: TemplateSyncSweeper
 * only REFRESHES the status of rows we ourselves submitted (rows that
 * already carry a `meta_template_id`). It never discovers templates
 * that exist on Meta's side but not in our DB — which is exactly why a
 * freshly-connected WABA with 8 approved templates showed an empty
 * Template Library.
 *
 * Importer = inverse of TemplatePayloadBuilder: it walks Meta's
 * `components[]` array (HEADER / BODY / FOOTER / BUTTONS / CAROUSEL)
 * and reconstructs our local header / template_body / footer / buttons
 * / carousel_data / attachment_type columns, then upserts keyed on
 * `meta_template_id` so re-running is idempotent (no duplicates).
 *
 * Read-only against Meta (GET only) — safe to run on page load.
 */
class TemplateImporter
{
    /**
     * Import every template from the workspace's primary WABA.
     *
     * @return array{imported:int, updated:int, total:int}
     * @throws RuntimeException when the workspace has no WABA connected.
     */
    public function forWorkspace(int $workspaceId): array
    {
        // Sync EVERY connected WABA account (a workspace can have several), so a
        // 3-number workspace pulls all three accounts' templates — each stamped
        // with its own provider_config_id by upsert(). Was: primary account only,
        // which left the other accounts' templates missing/stuck.
        $configs = $this->wabaConfigs($workspaceId);
        if ($configs->isEmpty()) {
            throw new RuntimeException('No WhatsApp Business (Cloud API) account is connected for this workspace. Connect a WABA first, then sync.');
        }

        $imported = 0;
        $updated  = 0;
        $total    = 0;
        $ok       = 0;        // accounts that synced without throwing
        $failures = [];       // per-account "<label>: <reason>" for the aggregate error

        foreach ($configs as $cfg) {
            // One broken WABA row must NOT abort the whole sync. A workspace can
            // hold several WABA configs (e.g. a coexistence connect left one with
            // NO access_token, or one whose token lacks whatsapp_business_management);
            // `new TemplateClient($cfg)` / list() throws for that one. Previously
            // the first bad config killed the entire loop, so the templates page
            // showed one account's "missing access_token" even when ANOTHER account
            // was fine. Isolate each account: skip a failure, keep the good ones,
            // and remember the reason so the user learns WHICH account failed WHY.
            try {
                $r = $this->importOne($cfg, $workspaceId);
                $imported += $r['imported'];
                $updated  += $r['updated'];
                $total    += $r['total'];
                $ok++;
            } catch (\Throwable $e) {
                $label = $cfg->phone_number ?: ($cfg->display_label ?: ('WABA #' . $cfg->id));
                $failures[] = $label . ': ' . $e->getMessage();
                Log::warning('[WABA-template-import] account skipped', [
                    'workspace' => $workspaceId,
                    'config'    => $cfg->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // If NOT ONE account synced, the sync genuinely failed — surface the
        // SPECIFIC reason(s) (naming each account) instead of the old misleading
        // single "missing access_token". One good account → we return its totals
        // and the bad ones are logged (best-effort; the good sync still lands).
        if ($ok === 0 && !empty($failures)) {
            throw new RuntimeException(
                count($failures) === 1
                    ? $failures[0]
                    : "Couldn't sync any connected WhatsApp Business account:\n• " . implode("\n• ", $failures)
            );
        }

        return ['imported' => $imported, 'updated' => $updated, 'total' => $total];
    }

    /** Import every template from ONE WABA account (paged). */
    private function importOne(WaProviderConfig $cfg, int $workspaceId): array
    {
        // Guard: the SAME WhatsApp Business Account can be connected in two
        // workspaces (e.g. a client re-connects it under a second login). But
        // `wa_templates.meta_template_id` is GLOBALLY unique, so importing the
        // same Meta template into a second workspace would blow up with a raw
        // `1062 Duplicate entry` SQLSTATE. Detect that up front and surface a
        // clear, human message instead — the first workspace that connected the
        // account owns its templates; the later one must disconnect it there
        // first (or manage templates from that workspace).
        $ownerWs = $this->conflictingOwnerWorkspace($cfg, $workspaceId);
        if ($ownerWs !== null) {
            $label = $cfg->phone_number
                ?: ('WABA ' . (string) ($cfg->creds()['waba_id'] ?? ''));
            Log::warning('[WABA-template-import] blocked — account already connected elsewhere', [
                'workspace'       => $workspaceId,
                'config'          => $cfg->id,
                'owner_workspace' => $ownerWs,
            ]);
            throw new RuntimeException(sprintf(
                'This WhatsApp Business account (%s) is already connected in another workspace, so its templates are managed there. A WhatsApp number can only sync templates in one workspace at a time — disconnect it from the other workspace first, then sync here.',
                $label
            ));
        }

        $client = new TemplateClient($cfg);

        $imported = 0;
        $updated  = 0;
        $total    = 0;
        $after    = null;
        $guard    = 0; // hard page cap so a pathological cursor can't loop forever

        do {
            $page = $client->list($after, 200);
            $rows = is_array($page['data'] ?? null) ? $page['data'] : [];

            foreach ($rows as $tpl) {
                if (!is_array($tpl) || empty($tpl['id'])) {
                    continue;
                }
                $total++;
                [$isNew] = $this->upsert($cfg, $workspaceId, $tpl);
                $isNew ? $imported++ : $updated++;
            }

            $after = $page['paging']['cursors']['after'] ?? null;
            $hasNext = !empty($page['paging']['next']) && $after;
            $guard++;
        } while ($hasNext && $guard < 50);

        Log::info('[WABA-template-import] done', [
            'workspace' => $workspaceId,
            'config'    => $cfg->id,
            'imported'  => $imported,
            'updated'   => $updated,
            'total'     => $total,
        ]);

        return ['imported' => $imported, 'updated' => $updated, 'total' => $total];
    }

    /** ALL WhatsApp Cloud (waba) provider configs for the workspace. */
    private function wabaConfigs(int $workspaceId)
    {
        return WaProviderConfig::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'waba')
            ->get();
    }

    /**
     * If this WABA account (same `waba_id`) is already connected in a
     * DIFFERENT workspace by an EARLIER-created config, return that
     * workspace's id — it owns the account's templates. `waba_id` lives in
     * the encrypted credentials blob, so we decrypt-compare in PHP rather
     * than filtering in SQL. First-connected (lowest config id) wins, so the
     * original workspace keeps syncing and only the later duplicate is
     * blocked. Returns null when there is no conflict.
     */
    private function conflictingOwnerWorkspace(WaProviderConfig $cfg, int $workspaceId): ?int
    {
        $wabaId = (string) ($cfg->creds()['waba_id'] ?? '');
        if ($wabaId === '') {
            return null; // no account id to compare — nothing we can assert
        }

        $earlierElsewhere = WaProviderConfig::query()
            ->where('provider', 'waba')
            ->where('workspace_id', '!=', $workspaceId)
            ->where('id', '<', $cfg->id)
            ->get();

        foreach ($earlierElsewhere as $other) {
            if ((string) ($other->creds()['waba_id'] ?? '') === $wabaId) {
                return (int) $other->workspace_id;
            }
        }

        return null;
    }

    /** The workspace's WhatsApp Cloud provider config (must be `waba`). */
    private function wabaConfig(int $workspaceId): ?WaProviderConfig
    {
        $primary = WaProviderConfig::primaryForWorkspace($workspaceId)->first();
        if ($primary && $primary->provider === 'waba') {
            return $primary;
        }
        // Primary might be another engine in a multi-engine workspace — fall
        // back to ANY waba config for the workspace.
        return WaProviderConfig::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'waba')
            ->first();
    }

    /**
     * Create or update the local row for one Meta template.
     *
     * @return array{0:bool}  [true] when a new row was created.
     */
    private function upsert(WaProviderConfig $cfg, int $workspaceId, array $tpl): array
    {
        $metaId = (string) $tpl['id'];

        $existing = WaTemplate::query()
            ->where('meta_template_id', $metaId)
            ->where('workspace_id', $workspaceId)
            ->first();

        // Fallback reconcile: an OLDER local row may have been submitted before
        // we tracked meta_template_id, so it's stuck "pending" while Meta shows
        // it approved. Match an un-linked WABA row by name + language (compared
        // in PHP so it works even though template_name is encrypted) and LINK it
        // — instead of creating a duplicate. This is the "approved-shows-pending"
        // sync fix.
        if (!$existing) {
            $wantName = strtolower(trim((string) ($tpl['name'] ?? '')));
            $wantLang = strtolower((string) ($tpl['language'] ?? 'en_US'));
            $wantBase = explode('_', $wantLang)[0];   // en_US -> en
            if ($wantName !== '') {
                $existing = WaTemplate::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('channel', 'waba')
                    ->whereNull('meta_template_id')
                    ->get()
                    ->first(function ($row) use ($wantName, $wantLang, $wantBase) {
                        if (strtolower(trim((string) $row->template_name)) !== $wantName) return false;
                        // Tolerate region-code drift: our row may store `en`
                        // while Meta returns `en_US` (or vice-versa). Exact match
                        // first, then compare the BASE language so the "stuck in
                        // review" row still links + inherits the approved status.
                        $rowLang = strtolower((string) $row->language);
                        if ($rowLang === $wantLang) return true;
                        $rowBase = explode('_', $rowLang)[0];
                        return $rowBase !== '' && $rowBase === $wantBase;
                    });
            }
        }

        // Final reconcile: a row with THIS Meta template id may already exist
        // under a workspace/config that has since been removed (the client moved
        // the WABA account to another workspace, or reconnected it under a new
        // config). Because meta_template_id is GLOBALLY unique, a fresh insert
        // would blow up with 1062 "Duplicate entry … meta_template_id_unique".
        // An account that is STILL active elsewhere was already blocked up front
        // by conflictingOwnerWorkspace(), so any row we find here is orphaned —
        // adopt it into this workspace (the $data below re-homes workspace_id +
        // provider_config_id) instead of failing the whole sync.
        if (!$existing) {
            $existing = WaTemplate::query()->where('meta_template_id', $metaId)->first();
        }

        $parsed = $this->parseComponents((array) ($tpl['components'] ?? []));

        $metaStatus = strtoupper((string) ($tpl['status'] ?? 'APPROVED'));
        $localStatus = match ($metaStatus) {
            'APPROVED'             => 'approved',
            'REJECTED'             => 'rejected',
            'PENDING', 'IN_APPEAL' => 'pending',
            default                => 'pending',
        };

        // Meta category is UPPER (UTILITY/MARKETING/AUTHENTICATION); our
        // meta_category column stores it lower-cased to match the create form.
        $metaCategory = strtolower((string) ($tpl['category'] ?? '')) ?: null;

        $data = [
            'workspace_id'       => $workspaceId,
            'provider_config_id' => $cfg->id,
            'channel'            => 'waba',
            'meta_template_id'   => $metaId,
            'meta_status'        => $metaStatus,
            'meta_category'      => $metaCategory,
            'quality_score'      => TemplateSyncSweeper::normalizeQualityScore($tpl['quality_score'] ?? null, 'UNKNOWN'),
            'template_name'      => (string) ($tpl['name'] ?? 'imported_template'),
            'language'           => (string) ($tpl['language'] ?? 'en_US'),
            'parameter_format'   => strtoupper((string) ($tpl['parameter_format'] ?? 'POSITIONAL')) ?: 'POSITIONAL',
            'template_type'      => $parsed['template_type'],
            'header'             => $parsed['header'],
            'template_body'      => $parsed['template_body'],
            'footer'             => $parsed['footer'],
            'buttons'            => $parsed['buttons'] ?: null,
            'carousel_data'      => $parsed['carousel_data'],
            'attachment_type'    => $parsed['attachment_type'],
            // Meta's approved sample image for a media header (example.header_handle[0]).
            // parseComponents captured it, but $data never carried it across — so the
            // sync reported "updated" while silently dropping the URL, header_sample_url
            // stayed NULL, and every IMAGE-header send failed "no image could be
            // resolved" no matter how many times the operator re-synced.
            // Only overwrite when Meta actually sent one, so a re-sync that omits the
            // sample can't wipe a URL (or an uploaded image) we already hold.
            ...($parsed['header_sample_url'] ? ['header_sample_url' => $parsed['header_sample_url']] : []),
            // Tag each {{N}} placeholder so the broadcast/campaign composer
            // renders a mapping field for it (Meta only gives us the text, not
            // which contact attribute fills each slot — the operator maps that
            // when they send). Positional keys by default; remappable in the UI.
            'variable_map'       => $this->buildVariableMap($parsed['header'], $parsed['template_body']),
            'status'             => $localStatus,
            'last_synced_at'     => now(),
        ];

        if ($metaStatus === 'REJECTED') {
            $data['rejection_reason_code'] = (string) ($tpl['rejection_reason'] ?? '') ?: null;
        }

        if ($existing) {
            $existing->fill($data);
            if ($metaStatus === 'APPROVED' && !$existing->approved_at) {
                $existing->approved_at = now();
            }
            $existing->save();
            return [false];
        }

        // A category is required on our side (industry bucket) but Meta
        // doesn't carry one — default to 'utility' which is the safe bucket.
        $data['category']     = 'utility';
        $data['user_id']      = Auth::id();
        $data['submitted_at'] = now();
        if ($metaStatus === 'APPROVED') {
            $data['approved_at'] = now();
        }

        try {
            WaTemplate::create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            // Safety net for a race: a row with this globally-unique
            // meta_template_id may have slipped in between our lookup and this
            // insert. Rather than 1062-ing the whole sync, adopt that row into
            // this workspace ($data re-homes workspace_id + provider_config_id).
            // The genuinely-active-elsewhere case is already blocked up front by
            // conflictingOwnerWorkspace(), so anything reaching here is the same
            // template and safe to re-home.
            if ((string) ($e->errorInfo[1] ?? '') === '1062'
                || str_contains($e->getMessage(), 'meta_template_id')) {
                $orphan = WaTemplate::query()->where('meta_template_id', $metaId)->first();
                if ($orphan) {
                    $orphan->fill($data);
                    if ($metaStatus === 'APPROVED' && !$orphan->approved_at) {
                        $orphan->approved_at = now();
                    }
                    $orphan->save();
                    return [false];
                }
            }
            throw $e;
        }
        return [true];
    }

    /**
     * Walk Meta's `components[]` and rebuild our local field shape.
     * Inverse of TemplatePayloadBuilder::buildComponents().
     *
     * @return array{header:?string, template_body:?string, footer:?string,
     *               buttons:array, attachment_type:string,
     *               template_type:string, carousel_data:?array}
     */
    private function parseComponents(array $components): array
    {
        $out = [
            'header'          => null,
            'template_body'   => null,
            'footer'          => null,
            'buttons'         => [],
            'attachment_type' => 'none',
            'template_type'   => 'standard',
            'carousel_data'   => null,
            'header_sample_url' => null,
        ];

        $isAuth = false;

        foreach ($components as $c) {
            if (!is_array($c)) continue;
            $type = strtoupper((string) ($c['type'] ?? ''));

            switch ($type) {
                case 'HEADER':
                    $fmt = strtoupper((string) ($c['format'] ?? 'TEXT'));
                    if ($fmt === 'TEXT') {
                        $out['header'] = (string) ($c['text'] ?? '') ?: null;
                    } elseif (in_array($fmt, ['IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'], true)) {
                        $out['attachment_type'] = strtolower($fmt);
                        // Capture Meta's OWN approved sample media URL. On a GET,
                        // a media header carries it under example.header_handle[0]
                        // — a downloadable URL to the exact image the template was
                        // approved with. Without it, an imported IMAGE-header
                        // template has no image to send (Meta gives the format,
                        // never the bytes), so every send fails 132012. The sender
                        // fetches this + uploads it to Meta for a media id.
                        $handle = $c['example']['header_handle'][0] ?? null;
                        if (is_string($handle) && $handle !== '') {
                            $out['header_sample_url'] = $handle;
                        }
                        // TEMP DIAGNOSTIC — proves whether Meta actually returned the
                        // sample on THIS sync. `build` also proves the running code is
                        // this version (stale opcache would never emit the line).
                        // has_example=false → Meta withheld it; nothing to capture and
                        // no amount of re-syncing will help (upload a header image).
                        \Log::info('tplImport:header', [
                            'build'        => 'header-sample-v1',
                            'format'       => $fmt,
                            'has_example'  => isset($c['example']),
                            'has_handle'   => isset($c['example']['header_handle']),
                            'handle'       => is_string($handle) ? substr($handle, 0, 120) : null,
                            'example_keys' => isset($c['example']) && is_array($c['example']) ? array_keys($c['example']) : [],
                        ]);
                    }
                    break;

                case 'BODY':
                    $out['template_body'] = (string) ($c['text'] ?? '') ?: null;
                    // Auth templates carry no body text but DO set this flag.
                    if (!empty($c['add_security_recommendation'])) {
                        $isAuth = true;
                    }
                    break;

                case 'FOOTER':
                    $out['footer'] = (string) ($c['text'] ?? '') ?: null;
                    if (isset($c['code_expiration_minutes'])) {
                        $isAuth = true;
                        // Keep the real validity Meta approved, not our default 10.
                        $out['code_expiration_minutes'] = (int) $c['code_expiration_minutes'];
                    }
                    break;

                case 'BUTTONS':
                    $out['buttons'] = $this->parseButtons((array) ($c['buttons'] ?? []), $isAuth);
                    break;

                case 'CAROUSEL':
                    $out['template_type'] = 'carousel';
                    $out['carousel_data'] = $this->parseCarousel((array) ($c['cards'] ?? []));
                    break;
            }
        }

        // Detect auth either from the body/footer flags above or an OTP button.
        $hasOtpButton = collect($out['buttons'])
            ->contains(fn ($b) => in_array($b['type'] ?? '', ['otp_one_tap', 'otp_copy'], true));
        if (($isAuth || $hasOtpButton) && $out['template_type'] !== 'carousel') {
            $out['template_type'] = 'auth';
        }

        return $out;
    }

    /**
     * Build the positional `variable_map` from the {{N}} placeholders in the
     * header + body, so the broadcast/campaign composer knows how many merge
     * fields to render. Meta only hands us the placeholder text, not which
     * contact attribute fills each slot, so the key defaults to the slot number
     * (the operator maps it to a real attribute in the composer).
     *
     * Output: ['header' => [['num'=>1,'key'=>'1']], 'body' => [['num'=>1,'key'=>'1'],...]]
     */
    private function buildVariableMap(?string $header, ?string $body): ?array
    {
        $extract = function (?string $text): array {
            if (!$text) return [];
            $out = [];
            $seen = [];
            if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $m)) {
                $i = 1;
                foreach ($m[1] as $name) {
                    if (isset($seen[$name])) continue;
                    $seen[$name] = true;
                    $out[] = ['num' => $i++, 'key' => (string) $name];
                }
            }
            return $out;
        };
        $map = [];
        if ($h = $extract($header)) $map['header'] = $h;
        if ($b = $extract($body))   $map['body']   = $b;
        return $map ?: null;
    }

    /**
     * Map Meta's button shape back to our local `buttons[]` convention
     * (the same one the create form + TemplatePayloadBuilder use).
     */
    private function parseButtons(array $metaButtons, bool $authContext = false): array
    {
        $out = [];
        foreach ($metaButtons as $b) {
            if (!is_array($b)) continue;
            $btype = strtoupper((string) ($b['type'] ?? ''));
            $text  = (string) ($b['text'] ?? '');

            switch ($btype) {
                case 'URL':
                    $out[] = ['type' => 'visit_website', 'text' => $text, 'value' => (string) ($b['url'] ?? '')];
                    break;
                case 'PHONE_NUMBER':
                    $out[] = ['type' => 'call_phone', 'text' => $text, 'value' => (string) ($b['phone_number'] ?? '')];
                    break;
                case 'COPY_CODE':
                    $example = $b['example'] ?? [];
                    $code = is_array($example) ? (string) ($example[0] ?? '') : (string) $example;
                    $out[] = ['type' => 'copy_code', 'text' => $text ?: 'Copy code', 'value' => $code];
                    break;
                case 'OTP':
                    $otpType = strtoupper((string) ($b['otp_type'] ?? 'COPY_CODE'));
                    $out[] = [
                        'type' => $otpType === 'ONE_TAP' ? 'otp_one_tap' : 'otp_copy',
                        'text' => $text ?: 'Copy code',
                    ];
                    break;
                case 'QUICK_REPLY':
                    $out[] = ['type' => 'quick_reply', 'text' => $text];
                    break;
                default:
                    // FLOW / CATALOG / MPM / VOICE_CALL etc. — keep a generic
                    // quick-reply-ish row so the button still renders in the UI
                    // rather than vanishing on import.
                    $out[] = ['type' => 'quick_reply', 'text' => $text ?: $btype];
            }
        }
        return $out;
    }

    /**
     * Rebuild carousel cards. The card image is a Meta media handle we
     * can't download here, so `image` stays null (the body/buttons still
     * import so the operator sees the structure and can re-attach media).
     */
    private function parseCarousel(array $cards): array
    {
        $out = [];
        foreach ($cards as $card) {
            if (!is_array($card)) continue;
            $comps = (array) ($card['components'] ?? []);
            $row = ['title' => '', 'body' => '', 'image' => null, 'buttons' => []];
            foreach ($comps as $c) {
                if (!is_array($c)) continue;
                $type = strtoupper((string) ($c['type'] ?? ''));
                if ($type === 'BODY') {
                    $row['body'] = (string) ($c['text'] ?? '');
                } elseif ($type === 'BUTTONS') {
                    $row['buttons'] = $this->parseButtons((array) ($c['buttons'] ?? []));
                }
            }
            $out[] = $row;
        }
        return $out;
    }
}
