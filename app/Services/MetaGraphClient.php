<?php

namespace App\Services;

use App\Models\MetaCampaign;
use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Meta Marketing Graph API client — full CTWA (Click-to-WhatsApp)
 * pipeline.
 *
 * Pre-2026-05-24 this class only POSTed to `/{account}/campaigns` and
 * stopped — Meta needs FIVE entities for a real CTWA ad to run:
 *
 *   1. POST /act_{id}/adimages    → image_hash
 *   2. POST /act_{id}/campaigns   → campaign_id (PAUSED)
 *   3. POST /act_{id}/adsets      → ad_set_id   (destination_type=WHATSAPP, promoted_object)
 *   4. POST /act_{id}/adcreatives → creative_id (object_story_spec.link_data with WHATSAPP_MESSAGE CTA)
 *   5. POST /act_{id}/ads         → ad_id       (links creative + adset)
 *
 * Each id is persisted on `meta_campaigns` so we can edit/toggle/
 * delete the whole tree later. Rollback on partial failure happens
 * in the controller — this client just reports per-step errors.
 *
 * Token + account resolution:
 *   - Per-workspace via WaProviderConfig.meta_json.fb_ad_account_id +
 *     credentials_json.ads_token (or access_token fallback).
 *   - Platform-default fallback to env() for single-tenant installs.
 *
 * Graph API version comes from system_settings.meta_ads_graph_api_version
 * (defaults to v23.0). Admin can bump via /admin/settings/wadesk-message
 * without redeploying.
 */
class MetaGraphClient
{
    public array $lastError = [];

    private string $version;
    private string $token;
    private string $account;     // 'act_{id}'
    private ?string $pageId      = null;
    private ?string $wabaId      = null;
    private ?string $phoneId     = null;   // Meta's internal phone_number_id (used by WABA Cloud sends)
    private ?string $phoneDigits = null;   // Actual E.164 digits (used by Marketing API promoted_object + wa.me link)
    private ?string $instagramUserId = null; // IG professional account id → object_story_spec.instagram_user_id
    private ?WaProviderConfig $cfg = null;

    public function __construct(?WaProviderConfig $cfg = null)
    {
        $this->cfg = $cfg;
        $this->version = (string) SystemSetting::get('meta_ads_graph_api_version', 'v23.0');

        if ($cfg) {
            $creds = $cfg->creds();
            $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];
            $this->token   = (string) ($creds['ads_token'] ?? $creds['access_token'] ?? '');
            $this->account = 'act_' . preg_replace('/^act_/', '', (string) ($meta['fb_ad_account_id'] ?? ''));
            $this->pageId  = (string) ($meta['fb_page_id'] ?? '') ?: null;
            $this->wabaId  = (string) ($meta['waba_id'] ?? '') ?: null;
            // Meta's INTERNAL phone_number_id — used by WABA Cloud message
            // sends (graph.facebook.com/<phone_number_id>/messages).
            $this->phoneId = (string) ($meta['phone_number_id'] ?? '') ?: null;
            // Actual E.164 phone digits — used by Marketing API
            // promoted_object.whatsapp_phone_number AND the wa.me link
            // in the ad creative. Meta rejects phone_number_id here with
            // "WhatsApp phone number is not linked to your account".
            // Pull from meta_json.display_phone_number first (canonical),
            // fall back to wa_provider_configs.phone_number column.
            $rawDigits = (string) ($meta['display_phone_number']
                ?? $cfg->phone_number
                ?? $creds['phone_number']
                ?? '');
            $rawDigits = preg_replace('/\D+/', '', $rawDigits);
            $this->phoneDigits = $rawDigits !== '' ? $rawDigits : null;
            // IG identity, if the messaging config happens to carry it.
            $this->instagramUserId = (string) ($meta['ig_user_id'] ?? $meta['instagram_user_id'] ?? '') ?: null;
        } else {
            $this->token   = '';
            $this->account = 'act_';
            $this->pageId  = null;
            $this->wabaId  = null;
            $this->phoneId = null;
            $this->phoneDigits = null;
            $this->instagramUserId = null;
        }

        // Workspace WABA fallback — runs BEFORE the admin fallback so the
        // workspace's own WhatsApp connection wins over global admin keys.
        $this->applyWorkspaceWabaFallback();

        // Admin fallback. The workspace's OWN Meta Ads keys always win;
        // anything the workspace left blank falls back to the platform
        // admin's global Meta Ads credentials (set at
        // /admin/meta-ads/keys). This is per-field so a workspace that
        // configured only an ad account can still borrow the admin
        // token, etc. Done last so it never overrides a real workspace
        // value.
        $this->applyAdminFallback();
    }

    /**
     * Fill the CTWA identifiers from the workspace's own WhatsApp (WABA)
     * connection when the Meta Ads keys row doesn't carry them.
     *
     * waba_id + phone_number_id normally live on the provider=waba row (set at
     * /devices, inside its ENCRYPTED credentials_json) — not on the Meta Ads
     * keys row. But metaConfig() resolves a single row and prefers
     * provider=meta_ads, so a workspace with WhatsApp fully connected still
     * reported "CTWA prerequisites missing" — while the warning told the
     * operator to add them at /devices, which was never read. Gap-fill only:
     * anything explicitly set on the Meta Ads row always wins.
     */
    private function applyWorkspaceWabaFallback(): void
    {
        if ($this->cfg === null) return;
        if ($this->wabaId !== null && $this->phoneId !== null && $this->phoneDigits !== null) return;

        $waba = \App\Models\WaProviderConfig::query()
            ->where('workspace_id', (int) $this->cfg->workspace_id)
            ->where('provider', 'waba')
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->first();
        if (!$waba) return;

        $creds = $waba->creds();
        $meta  = is_array($waba->meta_json) ? $waba->meta_json : [];

        if ($this->wabaId === null) {
            $this->wabaId = (string) ($creds['waba_id'] ?? $meta['waba_id'] ?? '') ?: null;
        }
        if ($this->phoneId === null) {
            $this->phoneId = (string) ($creds['phone_number_id'] ?? $meta['phone_number_id'] ?? '') ?: null;
        }
        if ($this->phoneDigits === null) {
            $d = preg_replace('/\D+/', '', (string) ($meta['display_phone_number'] ?? $waba->phone_number ?? ''));
            $this->phoneDigits = $d !== '' ? $d : null;
        }
    }

    /**
     * Fill any credential the workspace config left blank from the
     * admin-configured global Meta Ads keys. Workspace values are never
     * overwritten — this only ever fills gaps.
     */
    private function applyAdminFallback(): void
    {
        $fb = self::adminFallbackKeys();
        if (empty($fb['token']) && empty($fb['ad_account_id'])) return; // nothing configured

        if ($this->token === '' && !empty($fb['token'])) {
            $this->token = (string) $fb['token'];
        }
        if ($this->account === 'act_' && !empty($fb['ad_account_id'])) {
            $this->account = 'act_' . preg_replace('/^act_/', '', (string) $fb['ad_account_id']);
        }
        if ($this->pageId === null && !empty($fb['page_id'])) {
            $this->pageId = (string) $fb['page_id'];
        }
        if ($this->wabaId === null && !empty($fb['waba_id'])) {
            $this->wabaId = (string) $fb['waba_id'];
        }
        if ($this->phoneId === null && !empty($fb['phone_number_id'])) {
            $this->phoneId = (string) $fb['phone_number_id'];
        }
        if ($this->phoneDigits === null && !empty($fb['phone'])) {
            $d = preg_replace('/\D+/', '', (string) $fb['phone']);
            $this->phoneDigits = $d !== '' ? $d : null;
        }
        if ($this->instagramUserId === null && !empty($fb['instagram_user_id'])) {
            $this->instagramUserId = (string) $fb['instagram_user_id'];
        }
    }

    /**
     * Admin global Meta Ads fallback credentials, read from
     * system_settings (set on /admin/meta-ads/keys). Token is stored
     * encrypted; SystemSetting::get transparently decrypts.
     *
     * @return array{token:string,ad_account_id:string,page_id:string,phone:string,waba_id:string,phone_number_id:string}
     */
    public static function adminFallbackKeys(): array
    {
        return [
            'token'           => (string) SystemSetting::get('meta_ads.token', ''),
            'ad_account_id'   => (string) SystemSetting::get('meta_ads.ad_account_id', ''),
            'page_id'         => (string) SystemSetting::get('meta_ads.page_id', ''),
            'phone'           => (string) SystemSetting::get('meta_ads.phone', ''),
            'waba_id'         => (string) SystemSetting::get('meta_ads.waba_id', ''),
            'phone_number_id' => (string) SystemSetting::get('meta_ads.phone_number_id', ''),
            'instagram_user_id' => (string) SystemSetting::get('meta_ads.instagram_user_id', ''),
        ];
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->account !== 'act_';
    }

    /** True when we have a usable token but no ad account yet — the exact
     *  state right after WhatsApp embedded signup / coexistence (the token is
     *  reused from the messaging connection, but signup never carries an ad
     *  account or page). discoverAssets() can then fill those automatically. */
    public function hasTokenButNoAdAccount(): bool
    {
        return $this->token !== '' && $this->account === 'act_';
    }

    /**
     * Discover the ad accounts + Facebook Pages the connected token can see —
     * so the Meta Ads connect flow AUTO-FILLS the one thing WhatsApp embedded
     * signup / coexistence never provides (the ad account + page) instead of
     * making the operator paste raw IDs. Reuses the token already stored on the
     * workspace's WhatsApp/Meta connection (the constructor pulls access_token).
     *
     * NOTE: a plain WhatsApp embedded-signup token often lacks ads_management —
     * in that case the lists come back empty with an error, and the UI falls
     * back to manual entry / a "grant ads access" prompt. When the token DOES
     * carry business/ads scope (common with coexistence + business login), the
     * operator just picks from a dropdown, or we adopt a lone account outright.
     *
     * @return array{ok:bool,ad_accounts:array<int,array{id:string,name:string}>,pages:array<int,array{id:string,name:string}>,error:?string}
     */
    public function discoverAssets(): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'ad_accounts' => [], 'pages' => [], 'error' => 'no_token'];
        }

        $base  = 'https://graph.facebook.com/' . $this->version;
        $bizId = (string) (is_array($this->cfg?->meta_json) ? ($this->cfg->meta_json['business_id'] ?? '') : '');
        $error = null;

        // De-dupes by id across the /me and business-owned endpoints.
        $pull = function (string $url, array $query, string $idKey) use (&$error): array {
            $out = [];
            try {
                $r = Http::withToken($this->token)->acceptJson()->timeout(20)->get($url, $query);
                if (!$r->successful()) {
                    $error = $error ?: (string) ($r->json('error.message') ?: ('HTTP ' . $r->status()));
                    return $out;
                }
                foreach (($r->json('data') ?: []) as $row) {
                    $id = (string) ($row[$idKey] ?? $row['id'] ?? '');
                    if ($id === '') continue;
                    $out[$id] = ['id' => $id, 'name' => (string) ($row['name'] ?? $id)];
                }
            } catch (\Throwable $e) {
                $error = $error ?: $e->getMessage();
            }
            return $out;
        };

        // Ad accounts: the token user's own, then the business's owned + client.
        $accts = $pull($base . '/me/adaccounts', ['fields' => 'account_id,name', 'limit' => 200], 'account_id');
        if ($bizId !== '') {
            $accts += $pull($base . '/' . $bizId . '/owned_ad_accounts',  ['fields' => 'account_id,name', 'limit' => 200], 'account_id');
            $accts += $pull($base . '/' . $bizId . '/client_ad_accounts', ['fields' => 'account_id,name', 'limit' => 200], 'account_id');
        }

        // Pages: the token user's, then the business's owned + client.
        $pages = $pull($base . '/me/accounts', ['fields' => 'id,name', 'limit' => 200], 'id');
        if ($bizId !== '') {
            $pages += $pull($base . '/' . $bizId . '/owned_pages',  ['fields' => 'id,name', 'limit' => 200], 'id');
            $pages += $pull($base . '/' . $bizId . '/client_pages', ['fields' => 'id,name', 'limit' => 200], 'id');
        }

        $accts = array_values($accts);
        $pages = array_values($pages);
        $ok = $accts !== [] || $pages !== [];
        return ['ok' => $ok, 'ad_accounts' => $accts, 'pages' => $pages, 'error' => $ok ? null : ($error ?: 'no_assets')];
    }

    /**
     * True if we have everything CTWA-specific needs (page + WABA +
     * BOTH phone identifiers). Marketing API needs raw digits;
     * WABA Cloud sends need the phone_number_id. Without both, the ad
     * either creates with the wrong route or can't be created at all.
     */
    public function isCtwaReady(): bool
    {
        return $this->isConfigured()
            && $this->pageId !== null
            && $this->wabaId !== null
            && $this->phoneId !== null
            && $this->phoneDigits !== null;
    }

    /**
     * TEMP DIAGNOSTIC — the exact resolved state behind isCtwaReady(), so the
     * log shows WHICH prerequisite is null and which provider row it came from.
     * `build` proves the running bytecode is this version (stale opcache would
     * simply never emit it).
     */
    public function ctwaDebug(): array
    {
        return [
            'build'        => 'ctwa-waba-fallback-v2',
            'cfg_id'       => $this->cfg?->id,
            'cfg_provider' => $this->cfg?->provider,
            'workspace_id' => $this->cfg?->workspace_id,
            'configured'   => $this->isConfigured(),
            'has_token'    => $this->token !== '',
            'account'      => $this->account,
            'page_id'      => $this->pageId,
            'waba_id'      => $this->wabaId,
            'phone_id'     => $this->phoneId,
            'phone_digits' => $this->phoneDigits,
            'ready'        => $this->isCtwaReady(),
        ];
    }

    public function adAccountId(): string
    {
        return $this->account;
    }

    public function pageId(): ?string
    {
        return $this->pageId;
    }

    /**
     * Inject the Instagram professional-account id used as the ad's
     * Instagram identity (object_story_spec.instagram_user_id). The
     * controller resolves it from the workspace's instagram_accounts row
     * (or a Page-Backed Instagram Account) and sets it before sync.
     */
    public function withInstagramUserId(?string $igUserId): self
    {
        $id = trim((string) $igUserId);
        $this->instagramUserId = $id !== '' ? $id : $this->instagramUserId;
        return $this;
    }

    public function instagramUserId(): ?string
    {
        return $this->instagramUserId;
    }

    /**
     * Everything an Instagram ad needs: a configured ad account + a Page
     * (page_id is mandatory in object_story_spec even for IG-only/mixed
     * placements) + an Instagram identity (a real IG professional account
     * or a Page-Backed Instagram Account). Meta does NOT silently fall
     * back to the bare Page identity on Instagram.
     */
    public function isInstagramReady(): bool
    {
        return $this->isConfigured()
            && $this->pageId !== null
            && $this->instagramUserId !== null;
    }

    /**
     * Page-Backed Instagram Account — a "shadow" IG account derived from
     * the Facebook Page so a workspace WITHOUT a real connected IG account
     * can still run Instagram ads (the Page is the displayed identity).
     * Returns the PBIA id, creating it if needed. Also caches it onto this
     * client as the instagram_user_id. Best-effort: returns null on failure.
     */
    public function ensurePbia(): ?string
    {
        if (!$this->isConfigured() || $this->pageId === null) return null;
        try {
            // Existing PBIA?
            $get = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$this->pageId}/page_backed_instagram_accounts"));
            $existing = (string) ($get->json('data.0.id') ?? '');
            if ($existing !== '') {
                $this->instagramUserId = $existing;
                return $existing;
            }
            // Create one.
            $post = Http::withToken($this->token)->acceptJson()->timeout(15)
                ->post($this->endpoint("{$this->pageId}/page_backed_instagram_accounts"));
            $this->stash($post, 'ensurePbia', ['page_id' => $this->pageId]);
            $id = (string) ($post->json('id') ?? '');
            if ($id !== '') {
                $this->instagramUserId = $id;
                return $id;
            }
        } catch (\Throwable $e) {
            Log::warning('Meta ensurePbia threw', ['error' => $e->getMessage()]);
        }
        return null;
    }

    // =================================================================
    // STEP 1 — Image upload (POST /act_{id}/adimages)
    // =================================================================

    /**
     * Upload an image to Meta's ad image library, return the hash.
     * Hash is then referenced as `image_hash` in the ad creative.
     *
     * @param  string  $localPath  absolute path to a JPG/PNG/GIF
     * @return string  image_hash from Meta
     * @throws RuntimeException on failure
     */
    public function uploadImage(string $localPath): string
    {
        $this->requireConfigured();
        if (!is_readable($localPath)) {
            throw new RuntimeException("Image not readable: {$localPath}");
        }

        $resp = Http::withToken($this->token)
            ->timeout(30)
            ->attach('source', file_get_contents($localPath), basename($localPath))
            ->post($this->endpoint("{$this->account}/adimages"));

        $this->stash($resp, 'uploadImage', ['path' => $localPath]);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp));
        }

        // Meta returns `{ images: { <filename>: { hash, url } } }`.
        $images = (array) $resp->json('images', []);
        $first  = $images ? array_values($images)[0] : null;
        $hash   = (string) ($first['hash'] ?? '');
        if ($hash === '') {
            throw new RuntimeException('Meta uploaded image but returned no hash.');
        }
        return $hash;
    }

    // =================================================================
    // STEP 2 — Campaign (POST /act_{id}/campaigns)
    // =================================================================

    public function createCampaign(MetaCampaign $c): string
    {
        $this->requireConfigured();

        $campaignPayload = [
            'name'                  => (string) $c->name,
            'objective'             => $this->normalizeObjective((string) $c->objective),
            // special_ad_categories MUST be an array since v18+.
            // [] for normal ads; ["HOUSING"|"CREDIT"|"EMPLOYMENT"|"ISSUES_ELECTIONS_POLITICS"]
            // for restricted categories (advanced: user-selectable now).
            'special_ad_categories' => $this->specialAdCategories($c),
            'status'                => 'PAUSED',
            'buying_type'           => 'AUCTION',
        ];

        // Campaign Budget Optimization (Advantage campaign budget). When the
        // operator picks budget_level=campaign, the budget + bid strategy live
        // on the CAMPAIGN and Meta distributes spend across ad sets. The ad set
        // then MUST NOT carry its own budget (createAdSet honours this).
        if ($this->isCampaignBudget($c)) {
            $campaignPayload['daily_budget'] = max(100, (int) round(((float) ($c->daily_budget ?? 5)) * $this->budgetMultiplier()));
            $campaignPayload['bid_strategy'] = $this->bidStrategy($c);
            // bid_amount (cost/bid cap) rides at campaign level under CBO.
            if ($this->bidAmountCents($c) !== null && $this->bidStrategy($c) !== 'LOWEST_COST_WITHOUT_CAP') {
                $campaignPayload['bid_cap'] = $this->bidAmountCents($c);
            }
        }

        $resp = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->endpoint("{$this->account}/campaigns"), $campaignPayload);

        $this->stash($resp, 'createCampaign', ['name' => $c->name]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // STEP 3 — Ad Set (POST /act_{id}/adsets)
    // =================================================================

    /**
     * Create an ad set with CTWA destination + promoted_object linking
     * the WABA phone, and basic targeting derived from the local
     * campaign row.
     */
    public function createAdSet(MetaCampaign $c, string $campaignId): string
    {
        $this->requireConfigured();

        $dailyBudgetCents = (int) round(((float) ($c->daily_budget ?? 5)) * $this->budgetMultiplier());
        $startTime  = optional($c->start_date)->copy()?->setTime(0, 1)->toIso8601String() ?: now()->addMinutes(5)->toIso8601String();
        $endTime    = optional($c->end_date)?->copy()?->setTime(23, 59)->toIso8601String();

        // Honour the form's `adset_name` override (stored in
        // targeting['_adset_name'] since there's no dedicated column).
        // Falls back to the campaign name as the display label, not the
        // older "— Ad Set" suffix which polluted Meta's Ads Manager UI.
        $t              = is_array($c->targeting) ? $c->targeting : [];
        $adsetNameOverride = trim((string) ($t['_adset_name'] ?? ''));
        $adsetName      = $adsetNameOverride !== '' ? $adsetNameOverride : (string) $c->name;

        $adType = $c->adType();

        $payload = [
            'name'              => $adsetName,
            'campaign_id'       => $campaignId,
            'billing_event'     => 'IMPRESSIONS',
            'optimization_goal' => $this->adsetOptimizationGoal($c),
            'status'            => 'PAUSED',
            'start_time'        => $startTime,
            'targeting'         => $this->buildTargeting($c),
        ];

        // Budget + bidding live on the AD SET only when NOT using Campaign
        // Budget Optimization. Under CBO the campaign owns both and Meta
        // rejects an ad-set budget ("Cannot set budget on ad set when campaign
        // has a budget").
        if (!$this->isCampaignBudget($c)) {
            $payload['daily_budget'] = max(100, $dailyBudgetCents); // Meta minimum
            $payload['bid_strategy'] = $this->bidStrategy($c);
            if ($this->bidAmountCents($c) !== null && $this->bidStrategy($c) !== 'LOWEST_COST_WITHOUT_CAP') {
                $payload['bid_amount'] = $this->bidAmountCents($c);
            }
        }
        if ($endTime) $payload['end_time'] = $endTime;

        // Destination + promoted_object by ad type.
        if ($adType === MetaCampaign::AD_TYPE_CTWA) {
            // Click-to-WhatsApp. destination_type=WHATSAPP needs a
            // promoted_object identifying WHICH WhatsApp number routes.
            //
            // CRITICAL: `whatsapp_phone_number` takes the ACTUAL E.164
            // digits (e.g. "919876543210"), NOT Meta's internal
            // phone_number_id — that causes "This WhatsApp phone number
            // is not linked to your account". Priority: form's ctwa_phone
            // override → workspace primary WABA digits.
            $payload['destination_type'] = 'WHATSAPP';
            $overrideDigits = preg_replace('/\D+/', '', (string) ($c->ctwa_phone ?? ''));
            $resolvedDigits = $overrideDigits !== '' ? $overrideDigits : $this->phoneDigits;
            if ($this->pageId)   $payload['promoted_object']['page_id'] = $this->pageId;
            if ($resolvedDigits) $payload['promoted_object']['whatsapp_phone_number'] = $resolvedDigits;
        } elseif ($adType === MetaCampaign::AD_TYPE_IG_DIRECT) {
            // Click-to-Instagram-Direct — the tap opens an IG DM thread.
            $payload['destination_type'] = 'INSTAGRAM_DIRECT';
            if ($this->pageId) $payload['promoted_object']['page_id'] = $this->pageId;
        }
        // else: plain link ad (traffic/awareness) — no messaging
        // destination and no promoted_object (LINK_CLICKS/REACH don't
        // require one and Meta rejects an unexpected promoted_object).

        $resp = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->endpoint("{$this->account}/adsets"), $payload);

        $this->stash($resp, 'createAdSet', ['campaign_id' => $campaignId]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // STEP 4 — Ad Creative (POST /act_{id}/adcreatives)
    // =================================================================

    /**
     * Build the CTWA ad creative with the exact `object_story_spec`
     * shape Meta documents:
     *
     *   object_story_spec.page_id
     *   object_story_spec.link_data.{name,message,description,image_hash,link}
     *   object_story_spec.link_data.page_welcome_message
     *   object_story_spec.link_data.call_to_action.type = WHATSAPP_MESSAGE
     *   object_story_spec.link_data.call_to_action.value.app_destination = WHATSAPP
     */
    public function createAdCreative(MetaCampaign $c, string $imageHash): string
    {
        $this->requireConfigured();
        if (!$this->pageId) {
            throw new RuntimeException('Cannot build ad creative — workspace has no fb_page_id configured.');
        }

        // Read from the columns the form actually populates:
        //   form creative_title → MetaCampaign.creative_title
        //   form creative_body  → MetaCampaign.creative_body
        $adType   = $c->adType();
        $headline = (string) ($c->creative_title ?: $c->name);
        $body     = (string) ($c->creative_body  ?: '');

        if ($adType === MetaCampaign::AD_TYPE_CTWA) {
            // Click-to-WhatsApp. link_data.link MUST be the wa.me/<digits>
            // deep-link (phone_number_id renders unreachable on tap).
            $waLink = $this->phoneDigits ? 'https://wa.me/' . $this->phoneDigits : 'https://wa.me/';
            $cta    = (string) ($c->ctwa_cta ?: 'WHATSAPP_MESSAGE');
            $linkData = [
                'name'                 => $headline,
                'message'              => $body,
                'image_hash'           => $imageHash,
                'link'                 => $waLink,
                'page_welcome_message' => (string) ($c->ctwa_message ?: 'Hi, I saw your ad and I\'m interested. Can you tell me more?'),
                'call_to_action'       => [
                    'type'  => $cta,
                    'value' => ['app_destination' => 'WHATSAPP'],
                ],
            ];
        } elseif ($adType === MetaCampaign::AD_TYPE_IG_DIRECT) {
            // Click-to-Instagram-Direct. The ad set's destination_type=
            // INSTAGRAM_DIRECT routes the tap into an IG DM thread; the
            // creative carries the welcome / ice-breaker text. NOTE: the
            // exact CTA enum for IG-Direct is verify-on-ship (Meta docs
            // are JS-rendered); MESSAGE_PAGE is the documented messaging
            // CTA — a rejection surfaces as a clear FAILED error, not a crash.
            $linkData = [
                'name'                 => $headline,
                'message'              => $body,
                'image_hash'           => $imageHash,
                'link'                 => (string) ($c->creative_link_url ?: 'https://www.instagram.com/'),
                'page_welcome_message' => (string) ($c->ctwa_message ?: 'Hi! Thanks for your interest — how can we help?'),
                'call_to_action'       => ['type' => 'MESSAGE_PAGE'],
            ];
        } else {
            // Plain link ad (traffic / awareness) to a website / landing page.
            $cta  = (string) ($c->ctwa_cta && $c->ctwa_cta !== 'WHATSAPP_MESSAGE' ? $c->ctwa_cta : 'LEARN_MORE');
            $link = (string) ($c->creative_link_url ?: 'https://');
            $linkData = [
                'name'           => $headline,
                'message'        => $body,
                'image_hash'     => $imageHash,
                'link'           => $link,
                'call_to_action' => ['type' => $cta],
            ];
        }

        // object_story_spec: page_id is mandatory even for IG-only/mixed.
        // instagram_user_id gives the ad its Instagram identity — only
        // added when resolved (a pure CTWA ad with no IG placement stays
        // byte-identical to the legacy payload).
        $objectStorySpec = ['page_id' => $this->pageId, 'link_data' => $linkData];
        // Only attach the Instagram identity when the ad actually wants
        // Instagram (IG-Direct, or an instagram-placement ad). A pure CTWA
        // ad must NOT carry instagram_user_id even if the workspace/admin
        // config happens to have one — keeps the CTWA creative byte-identical.
        if ($this->instagramUserId && $c->wantsInstagram()) {
            $objectStorySpec['instagram_user_id'] = $this->instagramUserId;
        }

        $resp = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->endpoint("{$this->account}/adcreatives"), [
                'name'              => (string) $c->name . ' — Creative',
                'object_story_spec' => $objectStorySpec,
            ]);

        $this->stash($resp, 'createAdCreative', ['name' => $c->name]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // STEP 5 — Ad (POST /act_{id}/ads)
    // =================================================================

    public function createAd(MetaCampaign $c, string $adSetId, string $creativeId): string
    {
        $this->requireConfigured();

        $resp = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->post($this->endpoint("{$this->account}/ads"), [
                'name'        => (string) $c->name . ' — Ad',
                'adset_id'    => $adSetId,
                'creative'    => ['creative_id' => $creativeId],
                'status'      => 'PAUSED',
            ]);

        $this->stash($resp, 'createAd', ['adset' => $adSetId]);
        if (!$resp->successful()) throw new RuntimeException($this->errorHint($resp));
        return (string) $resp->json('id');
    }

    // =================================================================
    // Boost — promote an EXISTING Instagram post (no new creative upload)
    // =================================================================

    /**
     * Boost an existing IG media as an engagement ad. Builds the full tree
     * (campaign → adset → creative-from-media → ad), all PAUSED so the user
     * reviews + activates in Ads Manager (no accidental spend). The creative
     * references the live post via `source_instagram_media_id` (the documented
     * way to promote an existing IG post — no image re-upload).
     *
     * @return array{ok:bool,error?:string,campaign_id?:string,adset_id?:string,creative_id?:string,ad_id?:string}
     */
    public function boostInstagramMedia(string $igMediaId, float $dailyBudget, int $days, array $opts = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Meta Ads is not configured for this workspace.'];
        }
        $tag = substr($igMediaId, -6);
        try {
            // 1) Campaign — engagement objective.
            $camp = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/campaigns"), [
                    'name'                  => 'IG Boost ' . $tag,
                    'objective'             => $this->normalizeObjective('OUTCOME_ENGAGEMENT'),
                    'special_ad_categories' => [],
                    'status'                => 'PAUSED',
                    'buying_type'           => 'AUCTION',
                ]);
            if (!$camp->successful()) return ['ok' => false, 'error' => $this->errorHint($camp)];
            $campaignId = (string) $camp->json('id');

            // 2) Ad set — IG placements, daily budget, run window.
            $cents = max(100, (int) round($dailyBudget * $this->budgetMultiplier()));
            $aset = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/adsets"), [
                    'name'              => 'IG Boost ' . $tag,
                    'campaign_id'       => $campaignId,
                    'daily_budget'      => $cents,
                    'billing_event'     => 'IMPRESSIONS',
                    'optimization_goal' => 'POST_ENGAGEMENT',
                    'bid_strategy'      => 'LOWEST_COST_WITHOUT_CAP',
                    'status'            => 'PAUSED',
                    'start_time'        => now()->addMinutes(5)->toIso8601String(),
                    'end_time'          => now()->addDays(max(1, $days))->toIso8601String(),
                    'targeting'         => [
                        'geo_locations'        => ['countries' => [(string) ($opts['country'] ?? 'US')]],
                        'publisher_platforms'  => ['instagram'],
                        'instagram_positions'  => ['stream', 'explore', 'reels'],
                    ],
                ]);
            if (!$aset->successful()) return ['ok' => false, 'error' => $this->errorHint($aset), 'campaign_id' => $campaignId];
            $adsetId = (string) $aset->json('id');

            // 3) Creative referencing the existing IG post.
            $creativePayload = ['name' => 'IG Boost creative ' . $tag, 'source_instagram_media_id' => $igMediaId];
            if ($this->instagramUserId) $creativePayload['instagram_user_id'] = $this->instagramUserId;
            $cr = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/adcreatives"), $creativePayload);
            if (!$cr->successful()) return ['ok' => false, 'error' => $this->errorHint($cr), 'campaign_id' => $campaignId, 'adset_id' => $adsetId];
            $creativeId = (string) $cr->json('id');

            // 4) Ad.
            $ad = Http::withToken($this->token)->acceptJson()->timeout(20)
                ->post($this->endpoint("{$this->account}/ads"), [
                    'name'     => 'IG Boost ' . $tag,
                    'adset_id' => $adsetId,
                    'creative' => ['creative_id' => $creativeId],
                    'status'   => 'PAUSED',
                ]);
            if (!$ad->successful()) return ['ok' => false, 'error' => $this->errorHint($ad), 'campaign_id' => $campaignId, 'adset_id' => $adsetId, 'creative_id' => $creativeId];

            return ['ok' => true, 'campaign_id' => $campaignId, 'adset_id' => $adsetId, 'creative_id' => $creativeId, 'ad_id' => (string) $ad->json('id')];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // =================================================================
    // Lifecycle — status toggle / delete / insights
    // =================================================================

    /**
     * Toggle status on a Meta entity (campaign, adset, or ad).
     * Same endpoint shape for all three — different id types.
     */
    public function setStatus(string $entityId, string $status): bool
    {
        if (!$this->isConfigured() || $entityId === '') return false;

        try {
            $resp = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(10)
                ->post($this->endpoint($entityId), ['status' => $status]);
            $this->stash($resp, 'setStatus', ['id' => $entityId, 'status' => $status]);
            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('Meta setStatus threw', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Set status on the campaign + propagate to adset + ad so the
     * whole tree pauses/activates together. Returns true if ALL legs
     * succeeded.
     */
    public function setStatusCascade(MetaCampaign $c, string $status): bool
    {
        $ok = true;
        if ($c->facebook_id)      $ok = $this->setStatus($c->facebook_id, $status) && $ok;
        if ($c->meta_adset_id)    $ok = $this->setStatus($c->meta_adset_id, $status) && $ok;
        if ($c->meta_ad_id)       $ok = $this->setStatus($c->meta_ad_id, $status) && $ok;
        return $ok;
    }

    public function fetchInsights(string $campaignId): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];

        try {
            $resp = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(10)
                ->get($this->endpoint("{$campaignId}/insights"), [
                    // account_currency so analytics shows the ad account's real
                    // currency (e.g. IDR) instead of a hardcoded $ — spend/cpc
                    // are already returned IN that currency by Meta.
                    'fields'      => 'impressions,clicks,spend,reach,cpc,cpm,ctr,frequency,actions,account_currency',
                    'date_preset' => 'last_7d',
                ]);
            $this->stash($resp, 'fetchInsights', ['id' => $campaignId]);
            if (!$resp->successful()) return [];

            $row = $resp->json('data.0') ?? [];
            return [
                'spend'            => (float) ($row['spend']       ?? 0),
                'impressions'      => (int)   ($row['impressions'] ?? 0),
                'clicks'           => (int)   ($row['clicks']      ?? 0),
                'reach'            => (int)   ($row['reach']       ?? 0),
                'conversions'      => $this->extractConversations($row['actions'] ?? []),
                'ctr'              => (float) ($row['ctr']         ?? 0),
                'cpc'              => (float) ($row['cpc']         ?? 0),
                'cpm'              => (float) ($row['cpm']         ?? 0),
                'frequency'        => (float) ($row['frequency']   ?? 0),
                'revenue'          => 0.0,
                'account_currency' => strtoupper((string) ($row['account_currency'] ?? '')),
                // Click-outcome mix for the analytics donut — derived from the
                // SAME actions array, so no extra Graph call.
                'outcomes'         => $this->extractOutcomeMix($row['actions'] ?? [], (int) ($row['clicks'] ?? 0)),
            ];
        } catch (\Throwable $e) {
            Log::warning('Meta fetchInsights threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Daily time-series for the analytics trend + revenue charts. One row per
     * day (time_increment=1) over the last N days. Powers the per-campaign
     * "Spend, clicks, leads" line (previously a hardcoded demo array shared by
     * every campaign). Best-effort: [] on failure so the view falls back to a
     * totals-derived series.
     *
     * @return array<int,array{date:string,spend:float,clicks:int,leads:int,impressions:int}>
     */
    public function fetchDailyInsights(string $campaignId, string $datePreset = 'last_14d'): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$campaignId}/insights"), [
                    'fields'         => 'impressions,clicks,spend,actions',
                    'date_preset'    => $datePreset,
                    'time_increment' => 1,
                    'limit'          => 90,
                ]);
            $this->stash($resp, 'fetchDailyInsights', ['id' => $campaignId]);
            if (!$resp->successful()) return [];
            $out = [];
            foreach (($resp->json('data') ?? []) as $row) {
                $out[] = [
                    'date'        => (string) ($row['date_start'] ?? ''),
                    'spend'       => round((float) ($row['spend'] ?? 0), 2),
                    'clicks'      => (int) ($row['clicks'] ?? 0),
                    'leads'       => $this->extractConversations($row['actions'] ?? []),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('Meta fetchDailyInsights threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Split total clicks into the four analytics-donut buckets from ONE
     * insights `actions` array (no extra Graph call): WhatsApp chats vs
     * website visits vs lead-form submits vs drop-offs (clicks that did none).
     * Values are capped so the parts never exceed the click total.
     *
     * @return array{whatsapp:int,website:int,leads:int,dropoff:int}
     */
    public function extractOutcomeMix(array $actions, int $clicks): array
    {
        $pick = function (array $needles) use ($actions): int {
            foreach ($needles as $n) {
                foreach ($actions as $a) {
                    if (($a['action_type'] ?? '') === $n) return (int) ($a['value'] ?? 0);
                }
            }
            foreach ($needles as $n) {
                foreach ($actions as $a) {
                    if (str_contains((string) ($a['action_type'] ?? ''), $n)) return (int) ($a['value'] ?? 0);
                }
            }
            return 0;
        };

        $whatsapp = $this->extractConversations($actions);
        $leads    = $pick(['onsite_conversion.lead_grouped', 'lead']);
        $website  = $pick(['landing_page_view', 'link_click']);
        // WhatsApp + leads are the "committed" outcomes; website visits are a
        // subset of link clicks — cap it so the four parts stay within clicks.
        $website  = max(0, min($website, $clicks - ($whatsapp + $leads)));
        $dropoff  = max(0, $clicks - ($whatsapp + $website + $leads));

        return ['whatsapp' => $whatsapp, 'website' => $website, 'leads' => $leads, 'dropoff' => $dropoff];
    }

    /**
     * Generic insights breakdown (placement / demographics) — returns Meta's
     * raw rows so callers can shape them for the placement + audience charts.
     * Best-effort: [] on failure.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchBreakdown(string $campaignId, string $breakdowns, string $fields = 'spend,impressions', string $datePreset = 'last_14d'): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$campaignId}/insights"), [
                    'fields'      => $fields,
                    'breakdowns'  => $breakdowns,
                    'date_preset' => $datePreset,
                    'limit'       => 100,
                ]);
            $this->stash($resp, 'fetchBreakdown', ['id' => $campaignId, 'breakdowns' => $breakdowns]);
            if (!$resp->successful()) return [];
            return $resp->json('data') ?? [];
        } catch (\Throwable $e) {
            Log::warning('Meta fetchBreakdown threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Per-ad-set breakdown (last 7d insights + the set's targeting). Powers
     * the analytics "Ads & sets" table and the "Audience" tab. Best-effort:
     * a failure returns [] and the campaign-level numbers still render.
     */
    public function fetchAdSets(string $campaignId): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            // Progressive field sets. The rich request (targeting + a nested
            // insights expansion) 400s on some tokens/accounts — a restricted
            // `targeting` field, or the nested `insights{…}` sub-query — and
            // that would drop the WHOLE ad-set list (silent "—" entity tree).
            // Fall back to simpler shapes so we still recover the ad-set ids +
            // names (+ targeting when allowed), and only give up if even the
            // bare id list is refused.
            $fieldSets = [
                'id,name,status,targeting,insights.date_preset(last_7d){impressions,clicks,spend,reach,cpc,ctr,actions}',
                'id,name,status,targeting',
                'id,name,status',
            ];
            $resp = null;
            foreach ($fieldSets as $i => $fields) {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                    ->get($this->endpoint("{$campaignId}/adsets"), ['fields' => $fields, 'limit' => 50]);
                // Keep the error from the FIRST (richest) failure — it explains
                // best what Meta actually rejected.
                if ($i === 0 || !$resp->successful()) {
                    $this->stash($resp, 'fetchAdSets#' . $i, ['id' => $campaignId, 'fields' => $fields]);
                }
                if ($resp->successful()) break;
            }
            if (!$resp || !$resp->successful()) return [];
            $out = [];
            foreach (($resp->json('data') ?? []) as $row) {
                $ins = $row['insights']['data'][0] ?? [];
                $out[] = [
                    'id'          => (string) ($row['id']     ?? ''),
                    'name'        => (string) ($row['name']   ?? ''),
                    'status'      => (string) ($row['status'] ?? ''),
                    'targeting'   => is_array($row['targeting'] ?? null) ? $row['targeting'] : [],
                    'spend'       => (float) ($ins['spend']       ?? 0),
                    'impressions' => (int)   ($ins['impressions'] ?? 0),
                    'clicks'      => (int)   ($ins['clicks']      ?? 0),
                    'reach'       => (int)   ($ins['reach']       ?? 0),
                    'ctr'         => (float) ($ins['ctr']         ?? 0),
                    'cpc'         => (float) ($ins['cpc']         ?? 0),
                    'conversions' => $this->extractConversations($ins['actions'] ?? []),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('Meta fetchAdSets threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Per-ad breakdown with insights + the creative's image url, so the
     * detail page can render a real preview instead of "No image".
     */
    public function fetchAds(string $campaignId): array
    {
        if (!$this->isConfigured() || $campaignId === '') return [];
        try {
            // Same progressive fallback as fetchAdSets — the nested
            // insights expansion is the field most likely to 400 the whole
            // request, so drop it first and keep `creative{…}` (that's what
            // carries the image the detail page needs), then bare ids.
            $fieldSets = [
                'id,name,status,creative{id,image_url,thumbnail_url,object_story_spec},insights.date_preset(last_7d){impressions,clicks,spend,reach,cpc,ctr,actions}',
                'id,name,status,creative{id,image_url,thumbnail_url,object_story_spec}',
                'id,name,status',
            ];
            $resp = null;
            foreach ($fieldSets as $i => $fields) {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                    ->get($this->endpoint("{$campaignId}/ads"), ['fields' => $fields, 'limit' => 50]);
                if ($i === 0 || !$resp->successful()) {
                    $this->stash($resp, 'fetchAds#' . $i, ['id' => $campaignId, 'fields' => $fields]);
                }
                if ($resp->successful()) break;
            }
            if (!$resp || !$resp->successful()) return [];
            $out = [];
            foreach (($resp->json('data') ?? []) as $row) {
                $ins = $row['insights']['data'][0] ?? [];
                $cr  = is_array($row['creative'] ?? null) ? $row['creative'] : [];
                // Use `?:` not `??` — Meta returns an EMPTY STRING image_url for
                // carousel/dynamic creatives (the real preview is in thumbnail_url),
                // and `??` only falls back on null, so `??` left $img = "" and the
                // detail page's Creative preview showed "No image".
                $img = ($cr['image_url'] ?? '')
                    ?: ($cr['thumbnail_url'] ?? '')
                    ?: ($cr['object_story_spec']['link_data']['picture'] ?? '');
                $out[] = [
                    'id'          => (string) ($row['id']     ?? ''),
                    'name'        => (string) ($row['name']   ?? ''),
                    'status'      => (string) ($row['status'] ?? ''),
                    'creative_id' => (string) ($cr['id'] ?? ''),
                    'image_url'   => (string) $img,
                    'spend'       => (float) ($ins['spend']       ?? 0),
                    'impressions' => (int)   ($ins['impressions'] ?? 0),
                    'clicks'      => (int)   ($ins['clicks']      ?? 0),
                    'reach'       => (int)   ($ins['reach']       ?? 0),
                    'ctr'         => (float) ($ins['ctr']         ?? 0),
                    'cpc'         => (float) ($ins['cpc']         ?? 0),
                    'conversions' => $this->extractConversations($ins['actions'] ?? []),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('Meta fetchAds threw', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Meta ad-set targeting object → the local {countries,age_min,age_max,
     * gender,interests} shape the analytics Audience tab already reads.
     */
    public function normalizeTargeting(array $t): array
    {
        if (!$t) return [];
        $countries = $t['geo_locations']['countries'] ?? [];
        $genders   = $t['genders'] ?? [];
        $gender    = 'all';
        if ($genders === [1]) $gender = 'male';
        elseif ($genders === [2]) $gender = 'female';

        $interests = [];
        foreach (($t['flexible_spec'] ?? []) as $spec) {
            foreach (($spec['interests'] ?? []) as $i) {
                if (!empty($i['name'])) $interests[] = $i['name'];
            }
        }
        foreach (($t['interests'] ?? []) as $i) {
            if (is_array($i) && !empty($i['name'])) $interests[] = $i['name'];
            elseif (is_string($i) && $i !== '')    $interests[] = $i;
        }

        // Named geo — cities / regions come back as objects with a `name`.
        $named = function (array $rows): array {
            $out = [];
            foreach ($rows as $r) {
                if (is_array($r) && !empty($r['name'])) $out[] = (string) $r['name'];
                elseif (is_string($r) && $r !== '')    $out[] = $r;
            }
            return array_values(array_unique($out));
        };
        $cities  = $named($t['geo_locations']['cities']  ?? []);
        $regions = $named($t['geo_locations']['regions'] ?? []);
        $zips    = $named($t['geo_locations']['zips']    ?? []);

        // Custom + excluded audiences (id + name).
        $audNames = function (array $rows): array {
            $out = [];
            foreach ($rows as $r) {
                $out[] = is_array($r) ? (string) ($r['name'] ?? $r['id'] ?? '') : (string) $r;
            }
            return array_values(array_filter(array_unique($out)));
        };
        $customAud   = $audNames($t['custom_audiences'] ?? []);
        $excludedAud = $audNames(
            $t['excluded_custom_audiences']
            ?? ($t['exclusions']['custom_audiences'] ?? [])
        );

        // Languages — Meta returns numeric locale ids; map the common ones and
        // fall back to the raw id so nothing is silently dropped.
        $localeNames = [
            6 => 'English (US)', 24 => 'English (UK)', 5 => 'German', 7 => 'Spanish',
            8 => 'French', 9 => 'Hindi', 12 => 'Indonesian (Bahasa)', 16 => 'Italian',
            17 => 'Japanese', 23 => 'Portuguese (BR)', 46 => 'Malay', 26 => 'Arabic',
            48 => 'Thai', 64 => 'Vietnamese', 32 => 'Korean', 31 => 'Chinese (Simplified)',
        ];
        $languages = [];
        foreach (($t['locales'] ?? []) as $loc) {
            $languages[] = $localeNames[(int) $loc] ?? ('Locale ' . $loc);
        }

        // Placements. Explicit publisher_platforms → list them; otherwise Meta
        // is running automatic (Advantage+) placements.
        $placements = [];
        foreach (($t['publisher_platforms'] ?? []) as $p) {
            $placements[] = ucfirst((string) $p);
        }
        $autoPlacement = empty($placements)
            || (int) ($t['targeting_automation']['advantage_audience'] ?? 0) === 1;

        return array_filter([
            'countries'        => array_values((array) $countries),
            'cities'           => $cities,
            'regions'          => $regions,
            'zips'             => $zips,
            'age_min'          => $t['age_min'] ?? null,
            'age_max'          => $t['age_max'] ?? null,
            'gender'           => $gender,
            'interests'        => array_values(array_unique($interests)),
            'custom_audiences' => $customAud,
            'excluded_audiences' => $excludedAud,
            'languages'        => array_values(array_unique($languages)),
            'placements'       => $placements,
            'auto_placement'   => $autoPlacement,
        ], fn ($v) => $v !== null && $v !== [] && $v !== false);
    }

    /**
     * Delete the full CTWA tree in reverse order (Ad → Creative →
     * Ad Set → Campaign). Each leg is best-effort — losing a remote
     * entity is worse than partial cleanup.
     */
    public function deleteCascade(MetaCampaign $c): bool
    {
        $ok = true;
        foreach ([$c->meta_ad_id, $c->meta_creative_id, $c->meta_adset_id, $c->facebook_id] as $id) {
            if (!$id) continue;
            try {
                $resp = Http::withToken($this->token)->timeout(10)->delete($this->endpoint($id));
                $ok = $resp->successful() && $ok;
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** @deprecated — kept for back-compat with old callers; use deleteCascade. */
    public function deleteCampaign(string $facebookId): bool
    {
        if (!$this->isConfigured() || $facebookId === '') return false;
        try {
            return Http::withToken($this->token)->timeout(10)->delete($this->endpoint($facebookId))->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function endpoint(string $path): string
    {
        return "https://graph.facebook.com/{$this->version}/{$path}";
    }

    private function requireConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Meta Ads not configured. Connect a Meta Business account at /devices first.');
        }
    }

    /**
     * Multiplier for money amounts SENT to Meta (budgets/bids). Meta wants them
     * in the account currency's MINOR unit — cents (×100) for normal 2-decimal
     * currencies, but ZERO-decimal currencies (IDR, JPY, KRW, VND, HUF…) must be
     * sent WHOLE (×1) or Meta gets a budget 100x too high; 3-decimal → ×1000.
     * The account currency is fetched once and cached (1h) per ad account.
     */
    private function budgetMultiplier(): int
    {
        $cur = strtoupper((string) \Illuminate\Support\Facades\Cache::remember(
            'meta_acct_currency_' . $this->account, 3600, function () {
                try {
                    $r = Http::withToken($this->token)->acceptJson()->timeout(8)
                        ->get($this->endpoint((string) $this->account), ['fields' => 'currency']);
                    return $r->successful() ? (string) $r->json('currency') : '';
                } catch (\Throwable $e) { return ''; }
            }
        ));
        if (in_array($cur, ['BIF','CLP','DJF','GNF','IDR','ISK','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF','HUF','TWD'], true)) return 1;
        if (in_array($cur, ['BHD','IQD','JOD','KWD','LYD','OMR','TND'], true)) return 1000;
        return 100;
    }

    private function stash(Response $resp, string $op, array $context): void
    {
        $this->lastError = [
            'op'      => $op,
            'status'  => $resp->status(),
            'body'    => $resp->json() ?? $resp->body(),
            'context' => $context,
        ];
    }

    /**
     * Convert OUTCOME_xxx objectives stored locally to whatever the
     * current Marketing API version accepts. v23 still accepts the
     * legacy 2024 names (LINK_CLICKS, MESSAGES, etc.) but v25 enforces
     * the OUTCOME_ prefix. We normalise both ways defensively.
     */
    private function normalizeObjective(string $objective): string
    {
        $map = [
            // Legacy → OUTCOME_*
            'LINK_CLICKS'       => 'OUTCOME_TRAFFIC',
            'MESSAGES'          => 'OUTCOME_ENGAGEMENT',
            'CONVERSIONS'       => 'OUTCOME_SALES',
            'LEAD_GENERATION'   => 'OUTCOME_LEADS',
            'BRAND_AWARENESS'   => 'OUTCOME_AWARENESS',
            'REACH'             => 'OUTCOME_AWARENESS',
            'VIDEO_VIEWS'       => 'OUTCOME_ENGAGEMENT',
        ];
        return $map[strtoupper($objective)] ?? strtoupper($objective);
    }

    /**
     * Build a Meta `targeting` object from the local row. Hands the
     * absolute minimum so Meta accepts the ad set; the controller can
     * supply richer targeting once the UI gives users a way to pick
     * detailed interests.
     */
    private function buildTargeting(MetaCampaign $c): array
    {
        // Targeting fields live INSIDE the model's encrypted-array
        // `targeting` column (the form mapper packs countries / age /
        // gender / interests in there). Old code referenced $c->countries
        // / $c->age_min / $c->gender directly — those aren't columns,
        // so every user-typed targeting was silently dropped and Meta
        // got the hardcoded ['IN','US'] / 18 / 65 / all-genders default.
        $t = is_array($c->targeting) ? $c->targeting : [];

        // --- GEO (advanced) -------------------------------------------
        // Country + region + city (+ radius) + zip + custom pin location.
        // Meta rejects a country AND a city inside it (overlap), so when
        // any granular geo (region/city/zip/pin) is supplied we DROP the
        // country list and target the granular geo only. Country-only
        // campaigns behave exactly as before.
        $geo = [];
        $regions = $this->geoKeyList($t['regions'] ?? []);
        $cities  = $this->geoCityList($t['cities'] ?? []);
        $zips    = $this->geoKeyList($t['zips'] ?? []);
        $pins    = $this->geoCustomLocations($t['custom_locations'] ?? []);
        $hasGranular = $regions || $cities || $zips || $pins;

        if ($regions) $geo['regions'] = $regions;
        if ($cities)  $geo['cities']  = $cities;
        if ($zips)    $geo['zips']    = $zips;
        if ($pins)    $geo['custom_locations'] = $pins;

        if (!$hasGranular) {
            $countries = array_values(array_map('strtoupper', (array) ($t['countries'] ?? [])));
            if (empty($countries)) $countries = ['IN', 'US'];
            $geo['countries'] = $countries;
        }
        // "home" | "recent" | "travel_in" — who counts as "in" the location.
        //
        // The UI offers ONE checkbox, "Include people recently in these
        // locations", which posts just `recent`. Sending ["recent"] alone tells
        // Meta to target ONLY recent visitors and to EXCLUDE residents — the
        // opposite of what that label promises, and an expensive silent
        // mistake on a local-business campaign. "Include" means residents PLUS
        // visitors, so always keep `home` alongside `recent`.
        $locTypes = array_values(array_intersect(
            array_map('strtolower', (array) ($t['location_types'] ?? [])),
            ['home', 'recent', 'travel_in']
        ));
        if ($locTypes && !in_array('home', $locTypes, true)) {
            array_unshift($locTypes, 'home');
        }
        // Unticked → send nothing: Meta already defaults to ["home","recent"].
        if ($locTypes) $geo['location_types'] = $locTypes;

        $payload = [
            'geo_locations' => $geo ?: ['countries' => ['IN', 'US']],
            'age_min'       => max(13, (int) ($t['age_min'] ?: 18)),
            'age_max'       => min(65, (int) ($t['age_max'] ?: 65)),
            'genders'       => $this->genders($t['gender'] ?? null),
        ];

        // --- ADVANTAGE+ AUDIENCE (toggleable now) ---------------------
        // 1 = let Meta's AI expand beyond the defined audience (default,
        // "simple" mode). 0 = honour the exact targeting the operator set.
        $advantage = array_key_exists('advantage_audience', $t) ? (int) $t['advantage_audience'] : 1;
        $payload['targeting_automation'] = ['advantage_audience' => $advantage ? 1 : 0];

        // --- DETAILED TARGETING ---------------------------------------
        // interests: merge curated NAMES (resolved to {id,name} via search)
        // with any already-resolved {id,name} pairs from the live picker.
        $interests = $this->mergeTargetingPairs(
            $this->resolveInterests($this->stringList($t['interests'] ?? [])),
            $this->pairList($t['interests_resolved'] ?? [])
        );
        if ($interests) $payload['interests'] = $interests;

        // behaviors + life_events arrive pre-resolved ({id,name}) from the
        // adTargetingCategory live search — pass straight through.
        $behaviors  = $this->pairList($t['behaviors'] ?? []);
        $lifeEvents = $this->pairList($t['life_events'] ?? []);
        if ($behaviors)  $payload['behaviors']   = $behaviors;
        if ($lifeEvents) $payload['life_events'] = $lifeEvents;

        // locales (language targeting) — Meta locale IDs, e.g. 6 = en_US.
        $locales = array_values(array_filter(array_map('intval', (array) ($t['locales'] ?? []))));
        if ($locales) $payload['locales'] = $locales;

        // --- CUSTOM / LOOKALIKE AUDIENCES -----------------------------
        $customAud   = $this->idList($t['custom_audiences'] ?? []);
        $excludedAud = $this->idList($t['excluded_custom_audiences'] ?? []);
        if ($customAud)   $payload['custom_audiences']          = $customAud;
        if ($excludedAud) $payload['excluded_custom_audiences'] = $excludedAud;

        // --- EXCLUSIONS (exclude interests/behaviors) -----------------
        $exclInterests = $this->pairList($t['exclude_interests'] ?? []);
        $exclBehaviors = $this->pairList($t['exclude_behaviors'] ?? []);
        $exclusions = array_filter([
            'interests' => $exclInterests ?: null,
            'behaviors' => $exclBehaviors ?: null,
        ]);
        if ($exclusions) $payload['exclusions'] = $exclusions;

        // --- PLACEMENTS (manual) --------------------------------------
        // publisher_platforms drives which networks; per-network position
        // arrays refine each. Leaving publisher_platforms empty keeps
        // Meta's Advantage+ automatic placements.
        $platforms = array_values(array_intersect(
            array_map('strtolower', (array) ($c->publisher_platforms ?? [])),
            ['facebook', 'instagram', 'audience_network', 'messenger']
        ));
        if (!empty($platforms)) {
            $payload['publisher_platforms'] = $platforms;
            if (in_array('facebook', $platforms, true)) {
                $fbPos = array_values(array_intersect(
                    array_map('strtolower', (array) ($t['facebook_positions'] ?? [])),
                    ['feed', 'right_hand_column', 'marketplace', 'video_feeds', 'story', 'search', 'instream_video', 'facebook_reels']
                ));
                if ($fbPos) $payload['facebook_positions'] = $fbPos;
            }
            if (in_array('instagram', $platforms, true)) {
                $igPos = array_values(array_intersect(
                    array_map('strtolower', (array) ($c->instagram_positions ?? [])),
                    ['stream', 'story', 'reels', 'profile_feed', 'explore', 'ig_search']
                ));
                if ($igPos) $payload['instagram_positions'] = $igPos;
            }
            if (in_array('messenger', $platforms, true)) {
                $mePos = array_values(array_intersect(
                    array_map('strtolower', (array) ($t['messenger_positions'] ?? [])),
                    ['messenger_home', 'story']
                ));
                if ($mePos) $payload['messenger_positions'] = $mePos;
            }
            if (in_array('audience_network', $platforms, true)) {
                $anPos = array_values(array_intersect(
                    array_map('strtolower', (array) ($t['audience_network_positions'] ?? [])),
                    ['classic', 'rewarded_video']
                ));
                if ($anPos) $payload['audience_network_positions'] = $anPos;
            }
        }
        // device_platforms: ['mobile'] / ['desktop'] / both.
        $devices = array_values(array_intersect(
            array_map('strtolower', (array) ($t['device_platforms'] ?? [])),
            ['mobile', 'desktop']
        ));
        if ($devices) $payload['device_platforms'] = $devices;

        // Drop any underscore-prefixed keys from the targeting array —
        // those are local metadata stashed alongside the real targeting
        // fields (e.g. `_adset_name`). Sending unknown keys to Meta
        // results in a 100/Bad parameter rejection.
        foreach (array_keys($payload) as $k) {
            if (is_string($k) && str_starts_with($k, '_')) unset($payload[$k]);
        }

        return $payload;
    }

    /**
     * The ad-set optimization_goal per ad type. Messaging ads (CTWA +
     * Instagram-Direct) optimize for CONVERSATIONS (started chats);
     * plain link ads optimize for LINK_CLICKS, or REACH when the user
     * picked a reach/awareness goal. Both link goals are pixel-free and
     * reliable — richer goals (offsite conversions, leads) need a pixel
     * and are intentionally out of scope here.
     */
    private function adsetOptimizationGoal(MetaCampaign $c): string
    {
        if ($c->isMessagingAd()) {
            return 'CONVERSATIONS';
        }
        // REACH + BRAND_AWARENESS both run under OUTCOME_AWARENESS, whose
        // valid goal is REACH (LINK_CLICKS belongs to OUTCOME_TRAFFIC and
        // would be rejected). Everything else → LINK_CLICKS (traffic).
        return in_array(strtoupper((string) $c->optimization_goal), ['REACH', 'BRAND_AWARENESS'], true)
            ? 'REACH'
            : 'LINK_CLICKS';
    }

    private function genders($g): array
    {
        return match (strtolower((string) $g)) {
            'male'   => [1],
            'female' => [2],
            default  => [1, 2],
        };
    }

    /**
     * Resolve a list of interest NAMES (from the curated catalog) to
     * Meta `{id, name}` objects via the Targeting Search endpoint.
     *
     * Each name is cached for 7 days under the running Graph API
     * version so a Meta-side ID rename doesn't trap us on stale data
     * forever. Names that don't return a match are skipped — better to
     * ship a slightly broader audience than to fail the whole ad.
     */
    /**
     * List existing campaigns in the connected ad account — powers the
     * "Fetch from Meta" import so ads created directly in Ads Manager show
     * up in WaDesk with their stats. Insights are nested (lifetime) so the
     * import gets analytics in a single round-trip.
     */
    /** Last error from listCampaigns() (Meta permission/API message) — surfaced inline to the user. */
    public ?string $lastListError = null;

    public function listCampaigns(int $limit = 100): array
    {
        $this->lastListError = null;
        if (!$this->isConfigured()) {
            $this->lastListError = 'Meta ad account not connected — add your Ad Account ID + access token in Keys.';
            Log::warning('[META-IMPORT] not configured', ['account' => $this->account, 'has_token' => $this->token !== '']);
            return [];
        }
        try {
            // Basic fields ONLY — a nested insights expansion can error out the
            // WHOLE request (returning zero campaigns); insights are pulled
            // per-campaign by the caller instead. effective_status widens the
            // result to include paused/archived/issue campaigns too.
            $resp = Http::withToken($this->token)->acceptJson()->timeout(25)
                ->get($this->endpoint("{$this->account}/campaigns"), [
                    'fields'           => 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,created_time',
                    'effective_status' => json_encode([
                        'ACTIVE', 'PAUSED', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED', 'ARCHIVED',
                        'IN_PROCESS', 'WITH_ISSUES', 'PENDING_REVIEW', 'DISAPPROVED',
                    ]),
                    'limit'            => max(1, min(500, $limit)),
                ]);
            $data = (array) $resp->json('data', []);
            Log::info('[META-IMPORT] list campaigns', [
                'account' => $this->account,
                'http'    => $resp->status(),
                'ok'      => $resp->successful(),
                'count'   => count($data),
                'error'   => $resp->json('error.message'),
                'code'    => $resp->json('error.code'),
            ]);
            if (!$resp->successful()) {
                $this->lastListError = (string) ($resp->json('error.message') ?? 'Meta API returned an error.');
            }
            return $resp->successful() ? $data : [];
        } catch (\Throwable $e) {
            $this->lastListError = $e->getMessage();
            Log::warning('[META-IMPORT] list exception: ' . $e->getMessage());
            return [];
        }
    }

    // =================================================================
    // ADVANCED TARGETING — normalizers (targeting array → Meta shape)
    // =================================================================

    /** Flat array of trimmed non-empty strings. */
    private function stringList($v): array
    {
        return array_values(array_filter(array_map('trim', array_map('strval', (array) $v)), fn ($s) => $s !== ''));
    }

    /** [{id,name}] from a picker array of {id,name} (or bare ids). */
    private function pairList($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            if (is_array($row) && !empty($row['id'])) {
                $out[] = ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? '')];
            } elseif (is_scalar($row) && (string) $row !== '') {
                $out[] = ['id' => (string) $row];
            }
        }
        return $out;
    }

    /** [{id}] audience references. */
    private function idList($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : $row;
            if ($id !== null && (string) $id !== '') $out[] = ['id' => (string) $id];
        }
        return $out;
    }

    /** Merge two {id,name} lists, de-duped by id. */
    private function mergeTargetingPairs(array $a, array $b): array
    {
        $byId = [];
        foreach (array_merge($a, $b) as $row) {
            if (!empty($row['id'])) $byId[(string) $row['id']] = $row;
        }
        return array_values($byId);
    }

    /** [{key}] region/zip list (accepts bare keys or {key} objects). */
    private function geoKeyList($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : $row;
            if ($key !== null && (string) $key !== '') $out[] = ['key' => (string) $key];
        }
        return $out;
    }

    /** [{key,radius,distance_unit}] city list with optional radius. */
    private function geoCityList($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : $row;
            if ($key === null || (string) $key === '') continue;
            $city = ['key' => (string) $key];
            $radius = is_array($row) ? (float) ($row['radius'] ?? 0) : 0;
            if ($radius > 0) {
                $unit = is_array($row) ? strtolower((string) ($row['distance_unit'] ?? 'kilometer')) : 'kilometer';
                $unit = in_array($unit, ['mile', 'kilometer'], true) ? $unit : 'kilometer';
                // Meta radius bounds: 1–50 mi / 1–80 km.
                $max  = $unit === 'mile' ? 50 : 80;
                $city['radius']        = max(1, min($max, (int) round($radius)));
                $city['distance_unit'] = $unit;
            }
            $out[] = $city;
        }
        return $out;
    }

    /** [{latitude,longitude,radius,distance_unit}] dropped-pin locations. */
    private function geoCustomLocations($v): array
    {
        $out = [];
        foreach ((array) $v as $row) {
            if (!is_array($row) || !isset($row['latitude'], $row['longitude'])) continue;
            $unit = strtolower((string) ($row['distance_unit'] ?? 'kilometer'));
            $unit = in_array($unit, ['mile', 'kilometer'], true) ? $unit : 'kilometer';
            $max  = $unit === 'mile' ? 50 : 80;
            $out[] = [
                'latitude'      => (float) $row['latitude'],
                'longitude'     => (float) $row['longitude'],
                'radius'        => max(1, min($max, (int) round((float) ($row['radius'] ?? 5)))),
                'distance_unit' => $unit,
            ];
        }
        return $out;
    }

    /** special_ad_categories column → Meta array ([] when none/NONE). */
    private function specialAdCategories(MetaCampaign $c): array
    {
        $cats = array_values(array_filter(array_map(
            fn ($x) => strtoupper(trim((string) $x)),
            (array) ($c->special_ad_categories ?? [])
        )));
        $cats = array_values(array_intersect($cats, ['HOUSING', 'CREDIT', 'EMPLOYMENT', 'ISSUES_ELECTIONS_POLITICS']));
        return $cats; // [] = no special category
    }

    private function isCampaignBudget(MetaCampaign $c): bool
    {
        return strtolower((string) ($c->budget_level ?? 'adset')) === 'campaign';
    }

    private function bidStrategy(MetaCampaign $c): string
    {
        $s = strtoupper((string) ($c->bid_strategy ?? ''));
        return in_array($s, ['LOWEST_COST_WITHOUT_CAP', 'LOWEST_COST_WITH_BID_CAP', 'COST_CAP'], true)
            ? $s : 'LOWEST_COST_WITHOUT_CAP';
    }

    private function bidAmountCents(MetaCampaign $c): ?int
    {
        $amt = (int) ($c->bid_amount ?? 0);
        return $amt > 0 ? $amt : null;
    }

    // =================================================================
    // ADVANCED TARGETING — live search (Targeting Search API)
    // Powers the autocomplete pickers on the campaign form. All results
    // cached briefly so typing doesn't hammer Graph.
    // =================================================================

    /** Geolocation autocomplete → [{key,name,type,label}]. */
    public function searchGeo(string $q, array $locationTypes = ['city']): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $types = array_values(array_intersect($locationTypes, ['country', 'region', 'city', 'zip', 'geo_market', 'country_group']));
        if (empty($types)) $types = ['city'];
        $ck = 'meta_geo:' . md5($this->version . '|' . implode(',', $types) . '|' . mb_strtolower($q));
        return Cache::remember($ck, now()->addHours(6), function () use ($q, $types) {
            try {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(8)
                    ->get($this->endpoint('search'), [
                        'type'           => 'adgeolocation',
                        'location_types' => json_encode($types),
                        'q'              => $q,
                        'limit'          => 25,
                    ]);
                if (!$resp->successful()) return [];
                return array_values(array_map(function ($r) {
                    $bits = array_filter([$r['name'] ?? '', $r['region'] ?? '', $r['country_name'] ?? ($r['country_code'] ?? '')]);
                    return [
                        'key'   => (string) ($r['key'] ?? ''),
                        'name'  => (string) ($r['name'] ?? ''),
                        'type'  => (string) ($r['type'] ?? ''),
                        'label' => implode(', ', $bits),
                    ];
                }, (array) $resp->json('data', [])));
            } catch (\Throwable $e) {
                Log::warning('Meta geo search failed', ['q' => $q, 'error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /** Interest autocomplete → [{id,name,label,size}]. */
    public function searchInterests(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $ck = 'meta_int_search:' . md5($this->version . '|' . mb_strtolower($q) . '|' . $limit);
        return Cache::remember($ck, now()->addHours(6), function () use ($q, $limit) {
            try {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(8)
                    ->get($this->endpoint('search'), ['type' => 'adinterest', 'q' => $q, 'limit' => $limit]);
                if (!$resp->successful()) return [];
                return array_values(array_map(fn ($r) => [
                    'id'    => (string) ($r['id'] ?? ''),
                    'name'  => (string) ($r['name'] ?? ''),
                    'label' => (string) ($r['name'] ?? '') . (isset($r['path']) && is_array($r['path']) ? ' · ' . implode(' › ', $r['path']) : ''),
                    'size'  => (int) ($r['audience_size_upper_bound'] ?? $r['audience_size'] ?? 0),
                ], (array) $resp->json('data', [])));
            } catch (\Throwable $e) {
                Log::warning('Meta interest search failed', ['q' => $q, 'error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /** Language/locale autocomplete → [{id,name,label}] (Meta locale IDs). */
    public function searchLocales(string $q, int $limit = 30): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $ck = 'meta_locale:' . md5($this->version . '|' . mb_strtolower($q));
        return Cache::remember($ck, now()->addDays(7), function () use ($q, $limit) {
            try {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(8)
                    ->get($this->endpoint('search'), ['type' => 'adlocale', 'q' => $q, 'limit' => $limit]);
                if (!$resp->successful()) return [];
                return array_values(array_map(fn ($r) => [
                    'id'    => (string) ($r['key'] ?? $r['id'] ?? ''),
                    'name'  => (string) ($r['name'] ?? ''),
                    'label' => (string) ($r['name'] ?? ''),
                ], (array) $resp->json('data', [])));
            } catch (\Throwable $e) {
                Log::warning('Meta locale search failed', ['q' => $q, 'error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /** Behaviors / demographics / life_events browse → [{id,name,label,size}]. */
    public function searchTargetingCategory(string $class, string $q = '', int $limit = 60): array
    {
        $class = in_array($class, ['behaviors', 'demographics', 'life_events', 'industries', 'income', 'family_statuses'], true) ? $class : 'behaviors';
        $ck = 'meta_cat:' . md5($this->version . '|' . $class . '|' . mb_strtolower($q));
        $rows = Cache::remember($ck, now()->addHours(12), function () use ($class, $limit) {
            try {
                $resp = Http::withToken($this->token)->acceptJson()->timeout(10)
                    ->get($this->endpoint('search'), ['type' => 'adTargetingCategory', 'class' => $class, 'limit' => $limit]);
                if (!$resp->successful()) return [];
                return array_values(array_map(fn ($r) => [
                    'id'    => (string) ($r['id'] ?? ''),
                    'name'  => (string) ($r['name'] ?? ''),
                    'label' => (string) ($r['name'] ?? '') . (isset($r['path']) && is_array($r['path']) ? ' · ' . implode(' › ', $r['path']) : ''),
                    'size'  => (int) ($r['audience_size_upper_bound'] ?? $r['audience_size'] ?? 0),
                ], (array) $resp->json('data', [])));
            } catch (\Throwable $e) {
                Log::warning('Meta category search failed', ['class' => $class, 'error' => $e->getMessage()]);
                return [];
            }
        });
        // Client-side filter by q (the browse endpoint isn't a text search).
        $q = trim(mb_strtolower($q));
        if ($q === '') return $rows;
        return array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower($r['name']), $q)));
    }

    /** The ad account's saved Custom + Lookalike audiences → [{id,name,label}]. */
    public function listCustomAudiences(int $limit = 200): array
    {
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$this->account}/customaudiences"), [
                    'fields' => 'id,name,subtype,approximate_count_lower_bound,delivery_status',
                    'limit'  => max(1, min(500, $limit)),
                ]);
            if (!$resp->successful()) return [];
            return array_values(array_map(function ($r) {
                $sub = strtoupper((string) ($r['subtype'] ?? ''));
                $tag = $sub === 'LOOKALIKE' ? 'Lookalike' : 'Custom';
                return [
                    'id'    => (string) ($r['id'] ?? ''),
                    'name'  => (string) ($r['name'] ?? ''),
                    'label' => (string) ($r['name'] ?? '') . ' · ' . $tag,
                ];
            }, (array) $resp->json('data', [])));
        } catch (\Throwable $e) {
            Log::warning('Meta custom audiences list failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /** Delivery/reach estimate for a campaign's current targeting. */
    public function deliveryEstimate(MetaCampaign $c): array
    {
        try {
            $resp = Http::withToken($this->token)->acceptJson()->timeout(12)
                ->get($this->endpoint("{$this->account}/delivery_estimate"), [
                    'optimization_goal' => $this->adsetOptimizationGoal($c),
                    'targeting_spec'    => json_encode($this->buildTargeting($c)),
                ]);
            if (!$resp->successful()) return ['ok' => false, 'error' => (string) $resp->json('error.message', 'estimate failed')];
            $row = (array) $resp->json('data.0', []);
            return [
                'ok'    => true,
                'lower' => (int) ($row['estimate_mau_lower_bound'] ?? $row['estimate_dau_lower_bound'] ?? 0),
                'upper' => (int) ($row['estimate_mau_upper_bound'] ?? $row['estimate_dau_upper_bound'] ?? 0),
                'ready' => (bool) ($row['estimate_ready'] ?? false),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function resolveInterests(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $key = 'meta_interest:' . md5($this->version . '|' . mb_strtolower($name));
            $hit = Cache::remember($key, now()->addDays(7), function () use ($name) {
                try {
                    $resp = Http::withToken($this->token)
                        ->acceptJson()
                        ->timeout(8)
                        ->get($this->endpoint('search'), [
                            'type'  => 'adinterest',
                            'q'     => $name,
                            'limit' => 1,
                        ]);
                    if (!$resp->successful()) return null;
                    $row = $resp->json('data.0');
                    if (!is_array($row) || empty($row['id'])) return null;
                    return ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? $name)];
                } catch (\Throwable $e) {
                    Log::warning('Meta interest resolve failed', ['name' => $name, 'error' => $e->getMessage()]);
                    return null;
                }
            });
            if (is_array($hit) && !empty($hit['id'])) $out[] = $hit;
        }
        return $out;
    }

    /**
     * Pluck the CTWA "conversation started" count from Meta's actions
     * array. For CTWA campaigns this is the meaningful conversion
     * metric — clicks that turned into a real WhatsApp conversation.
     *
     * Meta surfaces this under several action_type names since the
     * Oct-2024 Insights API consolidation. Resolution order:
     *
     *   1. `onsite_conversion.messaging_conversation_started_7d`
     *      (the canonical CTWA metric Meta kept post-cleanup)
     *   2. `onsite_conversion.total_messaging_connection`
     *      (Meta's "new messaging connections" replacement metric for
     *      accounts that have it enabled)
     *   3. `onsite_conversion.messaging_first_reply`
     *      (older surface, still emitted for some accounts)
     *
     * Returns the FIRST tier that has rows, summed (1d/7d/28d windows
     * all appear as separate rows of the same action_type — picking
     * one is wrong, summing across windows would double-count, so we
     * filter to action_type WITHOUT a window suffix and take its value).
     */
    private function extractConversations(array $actions): int
    {
        $priority = [
            'onsite_conversion.messaging_conversation_started_7d',
            'onsite_conversion.total_messaging_connection',
            'onsite_conversion.messaging_first_reply',
        ];
        // Substring matchers — Meta sometimes emits unprefixed variants
        // (e.g. just "messaging_conversation_started_7d"); fall through
        // to a `str_contains` pass if exact match misses everything.
        foreach ($priority as $target) {
            foreach ($actions as $a) {
                if (($a['action_type'] ?? '') === $target) {
                    return (int) ($a['value'] ?? 0);
                }
            }
        }
        foreach (['messaging_conversation_started', 'total_messaging_connection', 'messaging_first_reply'] as $needle) {
            foreach ($actions as $a) {
                if (str_contains((string) ($a['action_type'] ?? ''), $needle)) {
                    return (int) ($a['value'] ?? 0);
                }
            }
        }
        return 0;
    }

    /**
     * Translate Meta's typed error envelope into actionable copy.
     * Mirrors WaConnectController's helper but for Marketing API
     * codes (per Meta docs Q1 2026).
     */
    private function errorHint(Response $resp): string
    {
        $err     = (array) $resp->json('error', []);
        $code    = (int) ($err['code']          ?? 0);
        $sub     = (int) ($err['error_subcode'] ?? 0);
        $msg     = (string) ($err['message']    ?? 'Unknown Meta error.');
        $userMsg = (string) ($err['error_user_msg'] ?? '');

        $hint = match (true) {
            $code === 190                  => 'Meta access token expired or revoked. Reconnect your Meta Business account.',
            $code === 200 && $sub === 1359047 => 'Your Meta app is missing the ads_management permission. Re-authorize via App Review.',
            $code === 200                  => 'Permission denied: ' . $msg . '. Check that your access token has ads_management + pages_manage_ads scopes.',
            $code === 100                  => 'Bad parameter: ' . $msg . '. Often a missing field, wrong enum value, or stale Graph API version.',
            $code === 17                   => 'Meta API rate limit reached. Wait a few minutes and retry.',
            $code === 4                    => 'Meta application request limit reached. Wait an hour.',
            $code === 32                   => 'Page-level rate limit reached for the connected Facebook Page.',
            $code === 803                  => 'Some of the requested fields are invalid for this Graph API version. Bump meta_ads_graph_api_version.',
            $code === 1487616              => 'Ad image is too small. Use at least 1080×1080 px.',
            $code === 1815269              => 'Ad creative violates Meta\'s commerce/marketing policy. Review headline + body for restricted content.',
            default                        => "Meta error {$code}" . ($sub ? "/{$sub}" : '') . ': ' . $msg,
        };
        return $userMsg ? ($hint . ' [' . $userMsg . ']') : $hint;
    }
}
