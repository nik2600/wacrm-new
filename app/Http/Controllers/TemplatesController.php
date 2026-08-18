<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use App\Services\Waba\TemplateClient;
use App\Services\Waba\TemplateImporter;
use App\Services\Waba\TemplateLinter;
use App\Services\Waba\TemplatePayloadBuilder;
use App\Services\Waba\TemplateSyncSweeper;
use App\Services\WorkspaceEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Template library — ports the old project's
 * D:\wadesk_2806\New folder\app\Http\Controllers\TemplatesWaDeskController.php
 * onto the new Eloquent + encrypted-at-rest pattern.
 *
 * The page UI uses AJAX for category tabs + status filter + live
 * search, so index() returns either the full view or a JSON
 * `{cards, counts, ...}` partial when the request asks for JSON
 * or `?partial=1`.
 */
class TemplatesController extends Controller
{
    // -----------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        $userId = Auth::id();
        $category = $request->string('category')->toString() ?: 'all';
        $status   = $request->string('status')->toString()   ?: 'all';
        $search   = $request->string('q')->toString();
        $sort     = $request->string('sort')->toString()     ?: 'newest';
        // Channel/platform filter: 'whatsapp' (baileys/waba/twilio) vs 'instagram'
        // (templates synced from Instaflow). 'all' = both.
        $channel  = $request->string('channel')->toString()  ?: 'all';
        $myOnly   = $request->boolean('my');

        // Operators see public/approved templates plus their own
        // (any status). Admin-style filtering can layer on top via
        // the explicit status filter. `?my=1` flips to "only mine".
        //
        // Engine awareness: on WABA the "approved/public" library
        // pool must come from templates actually submitted to Meta
        // (meta_template_id IS NOT NULL) — synthetic Baileys
        // approvals would otherwise show up here, then fail at send
        // time because Meta doesn't know about them.
        $wsId    = (int) ($request->user()?->current_workspace_id ?? 0);
        $isWaba  = $wsId && WorkspaceEngine::isWaba($wsId);

        $all = WaTemplate::query()
            // `myOnly` now means "templates from THIS workspace" (not
            // a user's personal templates) — matches the workspace-
            // shared semantics the rest of the app uses.
            ->when($myOnly,  fn ($q) => $q->forCurrentWorkspace())
            ->when(!$myOnly && $userId,
                fn ($q) => $q->where(function ($w) use ($wsId, $isWaba) {
                    // This workspace's own templates …
                    $w->where('workspace_id', $wsId);
                    // … plus ONLY the admin-seeded GLOBAL starter library
                    // (workspace_id IS NULL AND user_id IS NULL). Scoping the
                    // public pool to admin globals stops approved templates from
                    // OTHER tenants leaking into a fresh account's list.
                    $w->orWhere(function ($pool) use ($isWaba) {
                        $pool->whereNull('workspace_id')->whereNull('user_id');
                        if ($isWaba) {
                            $pool->whereNotNull('meta_template_id')
                                 ->where('meta_status', 'APPROVED');
                        } else {
                            $pool->whereIn('status', ['approved', 'public']);
                        }
                    });
                }))
            // Hide templates whose WABA number was disconnected or removed —
            // they can't be sent, so they must vanish from the Templates section
            // too (not just the send pickers). Non-WABA templates are kept.
            ->providerLive()
            ->with('provider')   // eager-load the WABA account so the card can label which account each template belongs to
            ->orderByDesc('id')
            ->get();

        // Instagram templates only exist while an Instagram account is connected.
        // Disconnect the last one and they drop out of the list, counts and tabs
        // entirely (they come back on reconnect — the rows aren't deleted).
        if (!\App\Models\WorkspaceIgAccount::hasConnected($wsId)) {
            $all = $all->filter(fn ($t) => $t->engineKey() !== 'instagram')->values();
        }

        // Status tabs in the sidebar use generic names (approved /
        // pending / rejected). On WABA those map to Meta's enum; on
        // Baileys/Twilio they map to the local synthetic state.
        $templates = $all
            ->when($category !== 'all', fn ($c) => $c->where('meta_category', $category))
            ->when($status !== 'all', function ($c) use ($status, $isWaba) {
                if ($isWaba) {
                    // Match the CARD badge, which shows the EFFECTIVE status
                    // (Meta's verdict when present, else the local status).
                    // Filtering on meta_status alone hid templates whose Meta
                    // status is empty/unrecognised — they showed "In review" on
                    // the card but the "In review" filter + count returned 0.
                    return $c->filter(fn ($t) => $this->wabaEffectiveStatus($t) === $status)->values();
                }
                return $c->where('status', $status);
            })
            ->when($channel === 'instagram', fn ($c) => $c->filter(fn ($t) => $t->engineKey() === 'instagram')->values())
            ->when($channel === 'whatsapp', fn ($c) => $c->filter(fn ($t) => $t->engineKey() !== 'instagram')->values());
        $templates = WaTemplate::filterByName($templates, $search);
        $templates = $this->applySort($templates, $sort);
        $templates = $this->paginateCollection($templates, $request, 8);

        // Channel counts for the WhatsApp/Instagram tab strip. Instagram = rows
        // whose engineKey() resolves to 'instagram' (synced from Instaflow).
        $channelCounts = [
            'all'       => $all->count(),
            'whatsapp'  => $all->filter(fn ($t) => $t->engineKey() !== 'instagram')->count(),
            'instagram' => $all->filter(fn ($t) => $t->engineKey() === 'instagram')->count(),
        ];

        $payload = [
            'templates'        => $templates,
            'categoryCounts'   => $this->categoryCounts($all),
            'statusCounts'     => $this->statusCounts($all),
            'channelCounts'    => $channelCounts,
            'totalCount'       => $all->count(),
            'currentCategory'  => $category,
            'currentStatus'    => $status,
            'currentChannel'   => $channel,
            'currentSearch'    => $search,
            'currentSort'      => $sort,
            // Show the "Sync from Meta" button whenever this workspace has a
            // Meta Cloud-API (WABA) account connected to pull from. (Not gated
            // on $isWaba — a multi-engine workspace whose default engine isn't
            // WABA should still be able to import its Meta templates.)
            'canImportMeta'    => WaProviderConfig::query()
                ->where('workspace_id', $wsId)->where('provider', 'waba')->exists(),
        ];

        if ($request->wantsJson() || $request->boolean('partial')) {
            return response()->json([
                'ok'             => true,
                'cards'          => view('user.templates._cards', ['templates' => $templates])->render(),
                'categoryCounts' => $payload['categoryCounts'],
                'statusCounts'   => $payload['statusCounts'],
                'channelCounts'  => $payload['channelCounts'],
                'totalCount'     => $payload['totalCount'],
                'pagination'     => view('user.partials.pagination', ['paginator' => $templates, 'dataAttr' => 'data-tpl-page', 'label' => 'templates'])->render(),
                'shown'          => $templates->count(),
                'total'          => $templates->total(),
                'page'           => $templates->currentPage(),
            ]);
        }

        return view('user.templates.index', $payload);
    }

    public function create(): View
    {
        // Twilio Content SID is only relevant when the workspace's active
        // engine is Twilio — hide the field otherwise.
        $wsId = (int) (auth()->user()?->current_workspace_id ?? 0);
        // Multi-engine: show the Twilio ContentSid field whenever Twilio is among
        // the workspace's enabled engines, not only when it's the single default.
        $isTwilio = $wsId && WorkspaceEngine::isEngineEnabled($wsId, 'twilio');
        // Channels this workspace can author a template for (any subset of the
        // three engines). The form shows a picker when there's more than one.
        $channels = $wsId ? WorkspaceEngine::availableFor($wsId) : ['baileys'];
        // Instagram templates are LOCAL reusable messages (no Meta approval,
        // like the Unofficial-API path) — offer the Instagram channel when the
        // workspace has an IG account linked from Instaflow.
        if ($wsId && !in_array('instagram', $channels, true)
            && \App\Models\WorkspaceIgAccount::hasConnected($wsId)) {
            $channels[] = 'instagram';
        }
        $defaultChannel = $wsId ? WorkspaceEngine::for($wsId) : 'baileys';
        // The "New template" modal's channel step passes ?channel= — honor it so
        // "Instagram" opens the form already scoped to an Instagram (local) template.
        $reqChannel = (string) request()->query('channel', '');
        if ($reqChannel !== '' && in_array($reqChannel, $channels, true)) {
            $defaultChannel = $reqChannel;
        }
        // Connected WABA accounts. When a workspace has MORE THAN ONE, the create
        // form shows a picker so the operator chooses WHICH WABA the template is
        // built on — the media header_handle upload AND the Meta submit must both
        // hit the SAME WABA, else Meta rejects with "Bad parameter: Invalid
        // parameter" (a handle uploaded to WABA A is not valid on WABA B). With
        // 0/1 connected there's nothing to pick and it uses the primary as before.
        $wabaConfigs = $wsId
            ? WaProviderConfig::query()->forWorkspace($wsId)->connected()
                ->where('provider', 'waba')->get(['id', 'display_label', 'phone_number'])
            : collect();
        $primaryWabaId = (int) (optional(WaProviderConfig::primaryForWorkspace($wsId)->first())->id ?? 0);
        return view('user.templates.create', compact('isTwilio', 'channels', 'defaultChannel', 'wabaConfigs', 'primaryWabaId'));
    }

    /**
     * Pull the workspace's Instagram templates from Instaflow into WaDesk's own
     * template library (local Instagram templates — no Meta). Upserts by
     * (workspace, channel=instagram, name) so re-syncing updates rather than
     * duplicates. Instaflow types map to WaDesk buttons: quick_replies →
     * QUICK_REPLY buttons, buttons → URL buttons, text → no buttons.
     */
    public function syncFromInstaflow(Request $request)
    {
        $user = $request->user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (! $wsId || ! class_exists(\App\Models\WorkspaceIgAccount::class)
            || ! \App\Models\WorkspaceIgAccount::hasConnected($wsId)) {
            return redirect('/templates')->with('error', __('Connect an Instagram account first.'));
        }

        $client = \App\Services\Instaflow\InstaflowClient::fromSettings();
        if (! $client->isConfigured()) {
            return redirect('/templates')->with('error', __('Instagram is not connected.'));
        }

        // Every Instaflow account linked to this workspace, deduped by name.
        $accounts = \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)
            ->pluck('instaflow_account_id')->filter()->map(fn ($v) => (string) $v)->unique();

        $imported = 0; $seenNames = [];
        foreach ($accounts as $acct) {
            foreach ($client->templates($acct) as $t) {
                $name = trim((string) ($t['name'] ?? ''));
                if ($name === '' || isset($seenNames[mb_strtolower($name)])) continue;
                $seenNames[mb_strtolower($name)] = true;

                $type  = (string) ($t['type'] ?? 'text');
                $items = is_array($t['items'] ?? null) ? $t['items'] : [];
                $buttons = [];
                if ($type === 'quick_replies') {
                    foreach ($items as $it) {
                        $label = trim((string) (is_array($it) ? ($it['title'] ?? $it['text'] ?? '') : $it));
                        if ($label !== '') $buttons[] = ['type' => 'QUICK_REPLY', 'text' => mb_substr($label, 0, 25)];
                    }
                } elseif ($type === 'buttons') {
                    foreach ($items as $it) {
                        if (! is_array($it)) continue;
                        $label = trim((string) ($it['title'] ?? $it['text'] ?? ''));
                        $url   = trim((string) ($it['url'] ?? $it['value'] ?? ''));
                        if ($label === '') continue;
                        $buttons[] = $url !== ''
                            ? ['type' => 'URL', 'text' => mb_substr($label, 0, 25), 'url' => $url]
                            : ['type' => 'QUICK_REPLY', 'text' => mb_substr($label, 0, 25)];
                    }
                }

                try {
                    WaTemplate::updateOrCreate(
                        ['workspace_id' => $wsId, 'channel' => 'instagram', 'template_name' => $name],
                        [
                            'user_id'       => $user->id,
                            'template_type' => 'standard',
                            'template_body' => (string) ($t['body'] ?? ''),
                            'buttons'       => $buttons,
                            'category'      => 'utility',
                            'meta_category' => 'utility',
                            'language'      => 'en',
                            'status'        => 'approved', // local IG template — ready to use
                        ],
                    );
                    $imported++;
                } catch (\Throwable $e) {
                    \Log::warning('[IG-TPL-SYNC] upsert failed: ' . $e->getMessage(), ['name' => $name]);
                }
            }
        }

        return redirect('/templates')->with(
            'success',
            $imported > 0
                ? trans_choice(':count Instagram template synced from :brand.|:count Instagram templates synced from :brand.', $imported, ['count' => $imported, 'brand' => ig_brand_name()])
                : __('No Instagram templates found on :brand.', ['brand' => ig_brand_name()]),
        );
    }

    public function edit(int $id): View
    {
        $template = WaTemplate::query()->forCurrentWorkspace()->find($id)
            ?: WaTemplate::query()->approved()->findOrFail($id);   // public/approved fallback
        $wsId = (int) (auth()->user()?->current_workspace_id ?? 0);
        // Multi-engine: show the Twilio ContentSid field whenever Twilio is among
        // the workspace's enabled engines, not only when it's the single default.
        $isTwilio = $wsId && WorkspaceEngine::isEngineEnabled($wsId, 'twilio');
        return view('user.templates.edit', compact('template', 'isTwilio'));
    }

    // -----------------------------------------------------------------
    // Mutations
    // -----------------------------------------------------------------

    /**
     * Persist a new template. Mirrors the old TemplatesWaDesk
     * `store` — supports both `standard` and `carousel` types
     * with full carousel-card processing (per-card image upload,
     * per-card buttons array, header / body / footer split). For
     * standard templates we also process buttons (CTA + quick
     * replies), the attachment file, and build the WABA
     * positional variable_map from `{var}` tokens in the body.
     */
    public function store(Request $request): RedirectResponse
    {
        // Plan: feature flag + numeric cap.
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'template');
        // Count user-created templates in the current workspace only.
        // Admin-seeded global templates (user_id IS NULL) don't count
        // against the workspace's limit.
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'template_limit',
            \App\Models\WaTemplate::where('workspace_id', $request->user()->current_workspace_id)->count(),
        );

        // Resolve the per-template channel up front so validation can relax the
        // body requirement for Twilio (its content lives in Twilio's builder).
        $wsId = (int) (Auth::user()->current_workspace_id ?? 0);
        $channel = (string) $request->input('channel', '');
        $available = WorkspaceEngine::availableFor($wsId);
        // Instagram is a valid LOCAL-template channel when an IG account is linked
        // (never submitted to Meta — like Baileys, it's a saved reusable message).
        if (!in_array('instagram', $available, true)
            && \App\Models\WorkspaceIgAccount::hasConnected($wsId)) {
            $available[] = 'instagram';
        }
        if (!in_array($channel, ['baileys', 'waba', 'twilio', 'instagram'], true) || !in_array($channel, $available, true)) {
            $channel = $this->wabaSubmitEnabled() ? 'waba' : 'baileys';
        }
        $submitWaba = ($channel === 'waba'); // Instagram never submits to Meta

        $data = $this->validateTemplate($request, false, $channel);

        // LOCATION header — tied to the Attachment dropdown's "Location" option.
        // (lat/lng inputs stay in the DOM even when hidden, so only honour them
        // when Location is actually the selected attachment type.)
        if (($request->input('attachment_type') ?: '') === 'location') {
            if ($err = $this->validateCoordinates($request)) {
                return back()->withInput()->withErrors(['latitude' => $err]);
            }
            $data['header_location'] = $this->collectTemplateLocation($request);
            $data['attachment_file'] = null; // location mode carries no file
        } else {
            $data['header_location'] = null;
        }

        // Capture the RAW (possibly NAMED, e.g. `{{first_name}}`) header +
        // body BEFORE we normalize them to positional. The editor now lets
        // operators author named tokens for readability; we read the names
        // off this snapshot to build the variable_map below, since
        // normalization rewrites them to bare {{1}} and loses the names.
        $rawNamed = [
            'header'        => $data['header']        ?? null,
            'template_body' => $data['template_body'] ?? null,
        ];

        // Normalize any named placeholders (`{{first_name}}`) to
        // positional (`{{1}}`) BEFORE we save or send to Meta. Meta
        // defaults templates to parameter_format=POSITIONAL and would
        // reject the create call otherwise. Idempotent for inputs that
        // are already positional. See normalizePlaceholders() docs.
        foreach (['header', 'template_body', 'footer'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = self::normalizePlaceholders((string) $data[$field]);
                $request->merge([$field => $data[$field]]);
            }
        }

        // Reject bodies with non-contiguous positional placeholders.
        // After normalization this should never fail unless something
        // truly malformed was submitted, but the guard stays as a
        // belt-and-braces check before the Meta call.
        foreach (['header', 'template_body', 'footer'] as $field) {
            $err = AttributesController::validatePlaceholders($request->input($field));
            if ($err) return back()->withInput()->withErrors([$field => $err]);
        }

        // Channel was resolved above (before validation, so Twilio could skip the
        // required body). A Twilio Content SID only belongs to a Twilio template —
        // drop a stray one if the operator typed it then switched channels. For
        // Twilio the body isn't authored here (it lives in Twilio's Content
        // Builder), so store a short reference label instead.
        if ($channel !== 'twilio') {
            $data['twilio_content_sid'] = null;
        } elseif (empty($data['template_body'])) {
            $data['template_body'] = 'Twilio template (content managed in Twilio Content Builder).';
        }

        $data['user_id'] = Auth::id();
        $data['channel'] = $channel;
        // Scope the template to the creator's workspace. Without this it saved
        // NULL and — being auto-"approved" on Baileys — leaked into every other
        // tenant's library pool on the templates index.
        $data['workspace_id'] = $wsId;
        // Multi-WABA: remember the operator's chosen WABA account (the create form
        // shows a picker when >1 is connected) so the media header upload + Meta
        // submit both target THAT WABA — not a random primary. Validated to a
        // CONNECTED WABA in this workspace; anything else is ignored and submit
        // falls back to the primary, exactly as before.
        $chosenCfgId = (int) $request->input('provider_config_id', 0);
        if ($chosenCfgId > 0
            && WaProviderConfig::query()->forWorkspace($wsId)->connected()
                ->where('provider', 'waba')->whereKey($chosenCfgId)->exists()) {
            $data['provider_config_id'] = $chosenCfgId;
        }
        $data['status']  = $data['status'] ?? ($submitWaba ? 'pending' : 'approved');
        if (!$submitWaba) {
            $data['meta_status'] = 'APPROVED';
            $data['approved_at'] = now();
        }

        if (($data['template_type'] ?? 'standard') === 'carousel') {
            $cards = $this->processCarouselData($request);
            if (!empty($cards)) {
                $data['carousel_data'] = $cards['cards'] ?? [];
                $data['header']        = $cards['header']        ?? ($data['header']        ?? null);
                $data['template_body'] = $cards['template_body'] ?? ($data['template_body'] ?? null);
                $data['footer']        = $cards['footer']        ?? ($data['footer']        ?? null);
            }
        } else {
            // Standard: button arrays, attachment file upload.
            if ($request->has('button_type') || $request->has('quick_reply')) {
                $data['buttons'] = $this->processButtons($request);
            }
            if ($request->hasFile('attachment_file')) {
                $file = $request->file('attachment_file');
                $data['attachment_type'] = $this->resolveAttachmentType($file);
                if ($err = $this->assertMediaSize($file, $data['attachment_type'])) {
                    return back()->withInput()->withErrors(['attachment_file' => $err]);
                }
                $data['attachment_file'] = $file->store('wa-templates', media_disk());
            }
        }

        // Variable map for WABA positional placeholders. When the editor
        // submits an explicit slot→attribute mapping (the "Variable
        // mapping" panel writes JSON into `variable_map_json`), build the
        // map from that so each `{{N}}` resolves to the attribute the user
        // assigned. Falls back to buildVariableMap() (name-derived) when
        // the JSON is absent — preserves back-compat with older forms.
        $variableMap = $this->buildVariableMapForSave(
            $request,
            $rawNamed['header'],
            $rawNamed['template_body'],
            $data['header']        ?? null,
            $data['template_body'] ?? null
        );
        if ($variableMap) $data['variable_map'] = $variableMap;

        $template = WaTemplate::create($data);

        // Instagram — the template is local to WaDesk, but ALSO push it up to
        // Instaflow so the same template is usable there (author-in-WaDesk →
        // use-in-Instaflow, the same round-trip flows have). Best-effort: a
        // push failure never blocks the local save.
        if ($channel === 'instagram') {
            $this->pushInstagramTemplateToInstaflow($template, $wsId);
        }

        // WABA — submit to Meta when the operator chose the WABA channel and a
        // primary WABA exists for this workspace. The local row already exists
        // so any Meta-side failure is recoverable by clicking "Submit to Meta"
        // on the detail page.
        if ($submitWaba) {
            [$ok, $err] = $this->submitToMeta($template, $request);
            if (!$ok) {
                return redirect()
                    ->route('user.templates.show', $template->id)
                    ->withErrors(['meta' => $err]);
            }
            return redirect()->route('user.templates.show', $template->id)
                ->with('status', 'Template "' . $template->template_name . '" submitted for review.');
        }

        return redirect()->route('user.templates.index')
            ->with('status', 'Template "' . $template->template_name . '" saved successfully.');
    }

    /**
     * Push a locally-saved Instagram template up to Instaflow so it appears in
     * Instaflow's own template list for every IG account linked to this
     * workspace. Converts WaDesk's WABA-shaped button array into Instaflow's
     * native {type, items} shape:
     *   - no buttons                → type=text
     *   - all buttons QUICK_REPLY   → type=quick_replies, items=[{title,payload}]
     *   - any URL button            → type=buttons, items=[{type,title,value}]
     * Best-effort: every failure is logged, never thrown — the local save stands.
     */
    private function pushInstagramTemplateToInstaflow(WaTemplate $template, int $wsId): void
    {
        try {
            if (! $wsId || ! class_exists(\App\Models\WorkspaceIgAccount::class)) return;

            $client = \App\Services\Instaflow\InstaflowClient::fromSettings();
            if (! $client->isConfigured()) return;

            $buttons = is_array($template->buttons) ? $template->buttons : [];
            $hasUrl  = false;
            foreach ($buttons as $b) {
                $t = strtoupper((string) ($b['type'] ?? ''));
                if ($t !== '' && $t !== 'QUICK_REPLY') { $hasUrl = true; break; }
            }

            $type = 'text'; $items = [];
            if (! empty($buttons)) {
                if ($hasUrl) {
                    $type = 'buttons';
                    foreach ($buttons as $b) {
                        $label = trim((string) ($b['text'] ?? ''));
                        if ($label === '') continue;
                        $url = trim((string) ($b['url'] ?? $b['value'] ?? ''));
                        $items[] = $url !== ''
                            ? ['type' => 'web_url', 'title' => $label, 'value' => $url]
                            : ['type' => 'postback', 'title' => $label, 'value' => $label];
                    }
                } else {
                    $type = 'quick_replies';
                    foreach ($buttons as $b) {
                        $label = trim((string) ($b['text'] ?? ''));
                        if ($label !== '') $items[] = ['title' => $label, 'payload' => $label];
                    }
                }
            }

            $accounts = \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)
                ->pluck('instaflow_account_id')->filter()->map(fn ($v) => (string) $v)->unique();

            foreach ($accounts as $acct) {
                $res = $client->pushTemplate(
                    $acct,
                    (string) $template->template_name,
                    $type,
                    (string) ($template->template_body ?? ''),
                    $items,
                );
                if (empty($res['ok'])) {
                    \Log::warning('[IG-TPL-PUSH] Instaflow rejected template', [
                        'account' => $acct, 'name' => $template->template_name, 'error' => $res['error'] ?? 'unknown',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[IG-TPL-PUSH] push failed: ' . $e->getMessage(), ['template_id' => $template->id]);
        }
    }

    /**
     * Detail page — shown after create and from the "View" CTA on the
     * card grid. Polls every 30s while meta_status=PENDING.
     */
    public function show(int $id): View
    {
        $template = WaTemplate::query()->forCurrentWorkspace()->find($id)
            ?: WaTemplate::query()->approved()->findOrFail($id);

        $provider = $template->provider_config_id
            ? WaProviderConfig::find($template->provider_config_id)
            : WaProviderConfig::primaryForWorkspace($template->workspace_id)->first();

        // Connected WABA accounts — the detail page's "Submit to Meta" form shows
        // a picker when >1 is connected, so a RE-submit can target a specific WABA
        // (the media header/carousel upload + the Meta submit both use it). Pre-
        // selects the template's remembered WABA, else the resolved provider, else
        // the primary. With 0/1 WABA there's nothing to pick and it's hidden.
        $wsId = (int) $template->workspace_id;
        $wabaConfigs = WaProviderConfig::query()->forWorkspace($wsId)->connected()
            ->where('provider', 'waba')->get(['id', 'display_label', 'phone_number']);
        $selectedWabaId = (int) ($template->provider_config_id
            ?: (($provider && $provider->provider === 'waba') ? $provider->id : 0));

        return view('user.templates.show', [
            'template'        => $template,
            'provider'        => $provider,
            'wabaSubmittable' => $this->wabaSubmitEnabled() && $provider,
            'wabaConfigs'     => $wabaConfigs,
            'selectedWabaId'  => $selectedWabaId,
        ]);
    }

    /**
     * AJAX poll endpoint — called by the detail page every 30s while
     * the template is PENDING. Cache-locked at 15s so a button-mashed
     * "Refresh now" can't exhaust Meta's GET quota.
     *
     * Delegates the actual GET + state-mirror to TemplateSyncSweeper
     * so the same logic runs identically on detail page, index page,
     * and webhook fallback paths.
     */
    public function refresh(int $id, TemplateSyncSweeper $sweeper): JsonResponse
    {
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);

        // Stuck row: submitted (or created) but never linked to a Meta template
        // id, so it shows "in review" forever and the webhook/sweeper can't match
        // it. Reconcile on demand — pull Meta's list and LINK this row by name +
        // language (TemplateImporter now matches base-language, so `en`↔`en_US`
        // links too). 15s lock stops refresh-spam from hammering Meta's quota.
        if (!$t->meta_template_id && (string) $t->channel === 'waba'
            && Cache::add("waba_tpl_relink:{$t->id}", 1, now()->addSeconds(15))) {
            try { app(\App\Services\Waba\TemplateImporter::class)->forWorkspace((int) $t->workspace_id); }
            catch (\Throwable $e) { \Log::warning('[TPL-REFRESH] relink import failed: ' . $e->getMessage()); }
            $t->refresh();
        }

        if (!$t->meta_template_id || !$t->provider_config_id) {
            return response()->json([
                'ok'          => false,
                'reason'      => 'not_submitted',
                'meta_status' => $t->meta_status,
            ]);
        }

        $lockKey = "waba_tpl_refresh:{$t->id}";
        if (!Cache::add($lockKey, 1, now()->addSeconds(15))) {
            return response()->json([
                'ok'          => true,
                'rate_limited'=> true,
                'meta_status' => $t->meta_status,
                'last_synced' => optional($t->last_synced_at)->toIso8601String(),
            ]);
        }

        $sweeper->one($t);
        $t->refresh();

        return response()->json([
            'ok'             => true,
            'meta_status'    => $t->meta_status,
            'quality_score'  => $t->quality_score,
            'last_synced'    => optional($t->last_synced_at)->toIso8601String(),
            'rejection_code' => $t->rejection_reason_code,
        ]);
    }

    /**
     * Fire-and-forget sweep for the templates index page. Front-end
     * fires this on page load (and on tab-foreground) — the sweeper
     * is internally debounced to once-per-10-min per workspace so
     * tab-storms can't hammer Meta. Returns the count of rows whose
     * status changed; the index page reloads its cards only if > 0.
     */
    public function syncStale(Request $request, TemplateSyncSweeper $sweeper): JsonResponse
    {
        if (!$this->wabaSubmitEnabled()) {
            return response()->json(['ok' => true, 'enabled' => false, 'changed' => 0]);
        }
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        if (!$wsId) {
            return response()->json(['ok' => true, 'changed' => 0]);
        }
        $changed = $sweeper->forWorkspace($wsId);

        // Opportunistic IMPORT — pull any templates that exist on Meta's side
        // but not in our DB (created in Business Manager, or approved before
        // this WABA was connected here). Cache-locked to once/hour per
        // workspace so the page-load AJAX can't hammer Meta's GET quota. This
        // is what makes a freshly-connected WABA's templates appear without
        // the operator having to click "Sync from Meta".
        $importLock = "waba_tpl_import:ws:{$wsId}";
        if (Cache::add($importLock, 1, now()->addHour())) {
            try {
                $res = app(TemplateImporter::class)->forWorkspace($wsId);
                $changed += (int) ($res['imported'] ?? 0);
            } catch (\Throwable $e) {
                Log::info('[WABA-template-import] page-load import skipped', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /**
     * Manual "Sync from Meta" — pulls every template from the workspace's
     * connected WABA into the local library. Idempotent (keyed on
     * meta_template_id), so the operator can click it any time to reconcile.
     */
    public function importFromMeta(Request $request, TemplateImporter $importer): RedirectResponse
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        if (!$wsId) {
            return back()->withErrors(['meta' => 'No active workspace.']);
        }

        try {
            $res = $importer->forWorkspace($wsId);
        } catch (\Throwable $e) {
            return back()->withErrors(['meta' => $e->getMessage()]);
        }

        // Let the next page-load import run immediately rather than waiting for
        // the hourly lock to expire (operator just asked for a fresh pull).
        Cache::forget("waba_tpl_import:ws:{$wsId}");

        $msg = $res['total'] === 0
            ? 'No templates found on this WhatsApp Business account yet.'
            : sprintf('Synced %d template(s) from Meta — %d new, %d updated.', $res['total'], $res['imported'], $res['updated']);

        return redirect()->route('user.templates.index')->with('status', $msg);
    }

    /**
     * POST /templates/{id}/submit — (re)submit a LOCAL template to Meta.
     *
     * This is the recovery path store() references: when the initial submit
     * fails, the row is left `pending` with no meta_template_id (never accepted
     * by Meta). The operator clicks "Submit to Meta" on the detail page to try
     * again — same submitToMeta() as create, so lint errors / Meta rejections
     * surface with the real reason. No-op once a meta_template_id exists.
     */
    public function submitToMetaAction(int $id, Request $request): RedirectResponse
    {
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);

        if (!$this->wabaSubmitEnabled()) {
            return back()->withErrors(['meta' => 'WABA template submission is turned off. Ask your admin to enable it in settings.']);
        }
        if ($t->meta_template_id) {
            return back()->with('status', 'This template is already on Meta (ID ' . $t->meta_template_id . '). Use Refresh to re-check its status.');
        }

        [$ok, $err] = $this->submitToMeta($t, $request);
        if (!$ok) {
            return back()->withErrors(['meta' => $err]);
        }
        return back()->with('status', 'Template "' . $t->template_name . '" submitted to Meta for review.');
    }

    // -----------------------------------------------------------------
    // WABA Meta-submit helpers
    // -----------------------------------------------------------------

    /** Feature flag — admin can toggle the new Meta-sync flow from settings. */
    private function wabaSubmitEnabled(): bool
    {
        return (bool) SystemSetting::get('waba_templates_v2_enabled', false);
    }

    /**
     * Which WABA account this template is created/submitted on. Critical for
     * multi-WABA workspaces: the same config MUST be used for the header/carousel
     * media upload AND the template create, or Meta rejects with "Invalid
     * parameter" (a header_handle minted on WABA A is not valid on WABA B).
     *
     * Priority: (1) an explicit `provider_config_id` on THIS request — the create
     * form / detail-page submit picker; (2) the WABA already remembered on the
     * template row (set at create or a prior submit); (3) the workspace's primary
     * WABA (legacy single-WABA behaviour). Every candidate is constrained to a
     * CONNECTED provider='waba' row in the template's own workspace, so a stale or
     * cross-tenant id can never be honoured.
     */
    private function resolveTemplateConfig(WaTemplate $t, Request $request): ?WaProviderConfig
    {
        $wsId = (int) $t->workspace_id;

        $chosen = (int) $request->input('provider_config_id', 0);
        if ($chosen > 0) {
            $cfg = WaProviderConfig::query()->forWorkspace($wsId)->connected()
                ->where('provider', 'waba')->whereKey($chosen)->first();
            if ($cfg) return $cfg;
        }

        if ($t->provider_config_id) {
            $cfg = WaProviderConfig::query()->forWorkspace($wsId)->connected()
                ->where('provider', 'waba')->whereKey($t->provider_config_id)->first();
            if ($cfg) return $cfg;
        }

        // Fallback (single-WABA / no explicit choice): the primary send config
        // when it IS a WABA, else the first connected WABA in the workspace — a
        // Baileys/Twilio primary must not block a template that has a real WABA
        // to publish to (primaryForWorkspace isn't provider-filtered).
        $primary = WaProviderConfig::primaryForWorkspace($wsId)->first();
        if ($primary && $primary->provider === 'waba') return $primary;

        return WaProviderConfig::query()->forWorkspace($wsId)->connected()
            ->where('provider', 'waba')->orderByDesc('is_primary')->orderByDesc('id')->first();
    }

    /**
     * Run the linter, upload header media if needed, POST to Meta,
     * update the row with meta_template_id + meta_status. Returns
     * `[bool $ok, ?string $error]`.
     */
    private function submitToMeta(WaTemplate $t, Request $request): array
    {
        // Resolve WHICH WABA this template belongs to — the operator's chosen
        // account, NOT a blind "primary". The media header_handle uploaded below
        // and the template create must both hit this same WABA (a handle from
        // WABA A is invalid on WABA B → Meta "Invalid parameter"). Priority:
        // this request's picker → the template's remembered WABA → primary.
        $cfg = $this->resolveTemplateConfig($t, $request);
        if (!$cfg || $cfg->provider !== 'waba') {
            return [false, 'No WABA connected for this workspace. Connect a WABA account (or pick one) first.'];
        }

        // Linter — block on errors, allow on warnings (warnings shown in flash).
        $lint = (new TemplateLinter())->check($t);
        if (!empty($lint['errors'])) {
            return [false, "Template would be rejected by Meta:\n• " . implode("\n• ", $lint['errors'])];
        }
        if (!empty($lint['warnings'])) {
            session()->flash('lint_warnings', $lint['warnings']);
        }

        // Media upload — only for non-text headers + carousel cards.
        $mediaHandles = [];
        $tmpMedia     = [];   // temp files streamed from cloud storage → cleaned up in finally
        try {
            $client  = new TemplateClient($cfg);
            $builder = new TemplatePayloadBuilder();

            if ($t->attachment_type && $t->attachment_type !== 'none' && $t->attachment_file) {
                [$localPath, $isTmp] = $this->materializeTemplateMedia($t->attachment_file);
                if ($localPath === null) {
                    return [false, 'Header media file is missing from storage. Re-upload it, then submit again.'];
                }
                if ($isTmp) $tmpMedia[] = $localPath;
                $mime = $this->guessMime($t->attachment_type, $t->attachment_file);
                $mediaHandles['header'] = $client->uploadHeaderMedia($localPath, $mime);
            }

            if ($t->template_type === 'carousel' && is_array($t->carousel_data)) {
                foreach ($t->carousel_data as $idx => $card) {
                    if (empty($card['image'])) continue;
                    [$localPath, $isTmp] = $this->materializeTemplateMedia($card['image']);
                    if ($localPath === null) continue;
                    if ($isTmp) $tmpMedia[] = $localPath;
                    $mime = $this->guessMime('image', $card['image']);
                    $mediaHandles['card_' . $idx] = $client->uploadHeaderMedia($localPath, $mime);
                }
            }

            $payload = $builder->build($t, $mediaHandles);
            $result  = $client->submit($payload);
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        } finally {
            foreach ($tmpMedia as $f) { if (is_file($f)) @unlink($f); }
        }

        // No template id = Meta did NOT create the template. submit() only
        // throws on a non-2xx, so a 2xx whose body carries no `id` fell through
        // to the success return below: we wrote meta_template_id = null, marked
        // the row `pending`, and flashed "submitted for review" — while the
        // detail page correctly showed "Not submitted to Meta yet". The banner
        // was right and the flash was lying. Fail loudly instead, and leave the
        // row unsubmitted so "Submit to Meta" stays a real retry.
        if (($result['id'] ?? '') === '') {
            $raw = json_encode($client->lastError['body'] ?? null);
            return [false, 'Meta accepted the request but returned no template ID, so nothing was created on Meta. Raw response: ' . mb_substr((string) $raw, 0, 300)];
        }

        $t->update([
            'provider_config_id' => $cfg->id,
            'meta_template_id'   => $result['id'],
            'meta_status'        => $result['status']   ?: 'PENDING',
            'meta_category'      => $result['category'] ?: $t->meta_category,
            'submitted_at'       => now(),
            'last_synced_at'     => now(),
            'status'             => 'pending',
        ]);

        return [true, null];
    }

    /**
     * Resolve a template's stored media to a readable LOCAL file path — Meta's
     * resumable upload needs a real file handle. Media is saved via media_disk(),
     * which may be LOCAL or a cloud disk (S3/Wasabi). Local disks return the real
     * on-disk path with no copy; cloud files are streamed to a temp file the
     * caller deletes. Returns [path|null, isTemp]; a null path = file missing.
     */
    private function materializeTemplateMedia(string $file): array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(media_disk());

        // Local disk — use the real on-disk path directly (no extra copy).
        try {
            $path = $disk->path($file);
            if (is_string($path) && is_readable($path)) return [$path, false];
        } catch (\Throwable $e) {
            // cloud adapters may not expose a local path — fall through to stream
        }

        // Cloud / remote — stream the bytes into a temp file to upload from.
        try {
            if (!$disk->exists($file)) return [null, false];
            $bytes = $disk->get($file);
        } catch (\Throwable $e) {
            return [null, false];
        }
        if ($bytes === null || $bytes === '') return [null, false];

        $ext = pathinfo($file, PATHINFO_EXTENSION) ?: 'bin';
        $tmp = tempnam(sys_get_temp_dir(), 'wa_tpl_') . '.' . $ext;
        file_put_contents($tmp, $bytes);
        return [$tmp, true];
    }

    private function guessMime(string $type, string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'mp4'         => 'video/mp4',
            'mov'         => 'video/quicktime',
            'pdf'         => 'application/pdf',
            default       => match ($type) {
                'image'    => 'image/jpeg',
                'video'    => 'video/mp4',
                'document' => 'application/pdf',
                default    => 'application/octet-stream',
            },
        };
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);
        $data = $this->validateTemplate($request, updating: true);

        // LOCATION header — tied to the Attachment dropdown. The edit form
        // always submits attachment_type, so honour it: Location → store the
        // pin (and clear any file); anything else → clear the pin.
        if ($request->has('attachment_type')) {
            if (($request->input('attachment_type') ?: '') === 'location') {
                if ($err = $this->validateCoordinates($request)) {
                    return back()->withInput()->withErrors(['latitude' => $err]);
                }
                $data['header_location'] = $this->collectTemplateLocation($request);
                $data['attachment_file'] = null;
            } else {
                $data['header_location'] = null;
            }
        }

        // Snapshot the raw (possibly named) header + body before
        // normalization so we can rebuild variable_map from the names.
        $rawNamed = [
            'header'        => $data['header']        ?? null,
            'template_body' => $data['template_body'] ?? null,
        ];

        // Normalize named placeholders → positional. Same reasoning as
        // store(): Meta only accepts {{1}}, {{2}} with POSITIONAL mode.
        foreach (['header', 'template_body', 'footer'] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = self::normalizePlaceholders((string) $data[$field]);
                $request->merge([$field => $data[$field]]);
            }
        }

        foreach (['header', 'template_body', 'footer'] as $field) {
            if ($request->has($field)) {
                $err = AttributesController::validatePlaceholders($request->input($field));
                if ($err) return back()->withInput()->withErrors([$field => $err]);
            }
        }

        if (($data['template_type'] ?? $t->template_type) === 'carousel') {
            $cards = $this->processCarouselData($request);
            if (!empty($cards)) {
                $data['carousel_data'] = $cards['cards'] ?? $t->carousel_data;
                $data['header']        = $cards['header']        ?? ($data['header']        ?? $t->header);
                $data['template_body'] = $cards['template_body'] ?? ($data['template_body'] ?? $t->template_body);
                $data['footer']        = $cards['footer']        ?? ($data['footer']        ?? $t->footer);
            }
        } else {
            if ($request->has('button_type') || $request->has('quick_reply')) {
                $data['buttons'] = $this->processButtons($request);
            }
            if ($request->hasFile('attachment_file')) {
                $file = $request->file('attachment_file');
                $data['attachment_type'] = $this->resolveAttachmentType($file);
                if ($err = $this->assertMediaSize($file, $data['attachment_type'])) {
                    return back()->withInput()->withErrors(['attachment_file' => $err]);
                }
                $data['attachment_file'] = $file->store('wa-templates', media_disk());
            }
        }

        $variableMap = $this->buildVariableMapForSave(
            $request,
            $rawNamed['header'],
            $rawNamed['template_body'],
            $data['header']        ?? $t->header,
            $data['template_body'] ?? $t->template_body
        );
        if ($variableMap) $data['variable_map'] = $variableMap;

        $t->fill($data)->save();
        return redirect()->route('user.templates.index')
            ->with('status', 'Template "' . $t->template_name . '" updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);

        // Delete from Meta FIRST for templates that were pushed there
        // (meta_template_id set). Without this, deleting only the local row
        // leaves the template live on Meta AND the "Sync from Meta" sweeper
        // re-imports it on the next run — so the operator deletes it and it
        // keeps coming back. Meta deletes templates BY NAME. Best-effort:
        // a Meta failure (already-deleted, token blip) must not block the
        // local delete — the row goes regardless so the UI never gets stuck.
        $metaDeleted = null;
        if ($t->meta_template_id && $t->template_name) {
            try {
                $cfg = $t->provider_config_id
                    ? WaProviderConfig::find($t->provider_config_id)
                    : WaProviderConfig::primaryForWorkspace($t->workspace_id)->first();
                if ($cfg && $cfg->provider === 'waba') {
                    $metaDeleted = (new \App\Services\Waba\TemplateClient($cfg))
                        ->deleteByName((string) $t->template_name);
                }
            } catch (\Throwable $e) {
                \Log::warning('[TEMPLATE] Meta delete failed (local delete continues): ' . $e->getMessage(), [
                    'template_id' => $t->id, 'name' => $t->template_name,
                ]);
                $metaDeleted = false;
            }
        }

        $t->delete();

        return response()->json([
            'ok'   => true,
            'data' => ['id' => $id, 'meta_deleted' => $metaDeleted],
            'meta' => $this->statusCounts(WaTemplate::query()->get()),
        ]);
    }

    /**
     * Admin-style approve / reject. Mirrors the old controller's
     * approveTemplate / rejectTemplate. In the new project there's
     * no Spatie role yet, so any authenticated operator can flip —
     * tighten to a role middleware once roles land.
     */
    public function approve(int $id): JsonResponse
    {
        // Workspace-scope the lookup so an operator can't flip status
        // on a template that belongs to another tenant by guessing IDs.
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);
        $t->update(['status' => 'approved', 'approved_at' => now(), 'rejection_reason' => null]);
        return response()->json(['ok' => true, 'data' => ['id' => $t->id, 'status' => $t->status]]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);
        $t->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->input('rejection_reason'),
        ]);
        return response()->json(['ok' => true, 'data' => ['id' => $t->id, 'status' => $t->status]]);
    }

    /**
     * `approved()` / `pending()` / `rejected()` / `myTemplates()` —
     * convenience views matching the old TemplatesWaDeskController
     * tabs. Each one sets the status filter and reuses the main
     * `index` blade so the layout stays consistent.
     */
    public function approved(Request $request): View
    {
        return $this->scopedIndex($request, ['status' => 'approved']);
    }

    public function pending(Request $request): View
    {
        return $this->scopedIndex($request, ['status' => 'pending']);
    }

    public function rejected(Request $request): View
    {
        return $this->scopedIndex($request, ['status' => 'rejected']);
    }

    public function myTemplates(Request $request): View
    {
        // `forUser` scope is in the model — the index() pipeline
        // honours `?my=1` by tightening the base query.
        $request->merge(['my' => '1']);
        return $this->index($request);
    }

    /**
     * JSON list endpoint — same shape the old controller's
     * getTemplatesApi() returned. Used by the carousel-builder
     * "import existing template" picker. Honours an optional
     * `type` filter (the old code only allowed `standard`).
     */
    public function apiList(Request $request): JsonResponse
    {
        // Scope to the CURRENT workspace — without this the flow-builder /
        // picker dropdown leaked every workspace's approved templates (the UI
        // claims "from the workspace" but the query didn't enforce it).
        $query = WaTemplate::query()->approved()->forCurrentWorkspace();
        if ($request->string('type')->toString() === 'standard') {
            $query->where('template_type', 'standard');
        }
        $templates = $query->orderByDesc('id')->get();
        return response()->json([
            'ok'        => true,
            'templates' => $templates->map(fn ($t) => [
                'id'              => $t->id,
                'template_name'   => $t->template_name,
                'template_type'   => $t->template_type,
                'category'        => $t->category,
                'meta_category'   => $t->meta_category,
                'header'          => $t->header,
                'template_body'   => $t->template_body,
                'footer'          => $t->footer,
                'buttons'         => $t->buttons,
                'carousel_data'   => $t->carousel_data,
                'attachment_type' => $t->attachment_type,
                'attachment_file' => $t->attachment_file ? media_url($t->attachment_file) : null,
                'language'        => $t->language,
            ]),
        ]);
    }

    /**
     * Single-template JSON — handy for previews + send-time
     * variable substitution. Mirrors the old getTemplatesById.
     */
    public function apiShow(int $id): JsonResponse
    {
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);
        return response()->json(['ok' => true, 'template' => $t]);
    }

    /**
     * Render a template body with merge-tag substitution. The
     * dispatcher / broadcast worker call this when fanning out to
     * recipients — `{{name}}` becomes the contact's first name etc.
     * Returns the assembled body so the caller can drop it
     * straight into the WhatsApp send payload.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $t = WaTemplate::query()->forCurrentWorkspace()->findOrFail($id);
        $vars = $request->input('vars', []);
        $body = $this->mergeBody($t->template_body, is_array($vars) ? $vars : []);
        return response()->json([
            'ok'      => true,
            'header'  => $this->mergeBody($t->header, is_array($vars) ? $vars : []),
            'body'    => $body,
            'footer'  => $t->footer,
            'buttons' => $t->buttons,
        ]);
    }

    private function mergeBody(?string $template, array $vars): string
    {
        if (!$template) return '';
        // Substitute both `{{name}}` (mustache) and `{name}`
        // (single-brace, the format the old controller stored).
        $template = preg_replace_callback('/\{\{\s*([\w_]+)\s*\}\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, $template);
        return preg_replace_callback('/\{([\w_]+)\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, $template);
    }

    /**
     * Build the positional WABA variable map from `{var}` tokens
     * in the header + body. Mirrors the old controller's
     * `buildVariableMap`. Returns null when no vars are found so
     * the caller can leave the column empty for dumb templates.
     *
     * Output shape: ['header' => [{num:1,key:'name'}], 'body' => […]]
     */
    private function buildVariableMap(?string $header, ?string $body): ?array
    {
        // Pre-2026-05-24: matched single-brace `{var}` only. But every
        // template create form and the AI-generation flow use Meta's
        // double-brace convention `{{var}}`. The regex missed those →
        // `variable_map` stayed null → at broadcast send the body had
        // placeholders but no params, causing Meta error #132000 on
        // every recipient. Patched to match BOTH forms (positional
        // `{{1}}` numeric and named `{{first_name}}`).
        $extract = function (?string $text): array {
            if (!$text) return [];
            $out  = [];
            $i    = 1;
            $seen = [];
            // Match `{var}` OR `{{var}}` OR `{{ var }}` (any whitespace).
            if (preg_match_all('/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/', $text, $m)) {
                foreach ($m[1] as $varName) {
                    // Skip duplicates — Meta needs ONE example per
                    // unique placeholder name.
                    if (isset($seen[$varName])) continue;
                    $seen[$varName] = true;
                    $out[] = ['num' => $i++, 'key' => $varName];
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
     * Resolve the positional `variable_map` for a template from the
     * request. Prefers the explicit slot→attribute mapping the editor's
     * "Variable mapping" panel writes into `variable_map_json`
     * (`{ "1":"promo_key", "2":"order_id" }`) so each `{{N}}` placeholder
     * resolves to the attribute the operator picked. Falls back to the
     * name-derived buildVariableMap() when no JSON is present (back-compat
     * with older create/edit forms + the AI-generation flow).
     *
     * Output keeps the canonical stored shape the dispatcher + resolver
     * already understand:
     *   ['header' => [['num'=>1,'key'=>'first_name']],
     *    'body'   => [['num'=>1,'key'=>'promo_key'], ['num'=>2,'key'=>'order_id']]]
     *
     * Header vs body is split by which placeholders actually appear in
     * each field's text. Any positional slot present in a field but NOT
     * covered by the submitted JSON falls back to the literal-number key
     * (buildVariableMap's behaviour) so a slot is never silently dropped.
     */
    private function buildVariableMapFromRequest(Request $request, ?string $header, ?string $body): ?array
    {
        $raw = $request->input('variable_map_json');
        // No explicit mapping submitted (older form / carousel / auth) —
        // keep the name-derived behaviour untouched.
        if (!is_string($raw) || trim($raw) === '') {
            return $this->buildVariableMap($header, $body);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // Malformed JSON — don't lose the map entirely, fall back.
            return $this->buildVariableMap($header, $body);
        }

        // Normalize the submitted map to slot(string) => attribute_key.
        $slotKey = [];
        foreach ($decoded as $slot => $key) {
            $slot = (string) $slot;
            $key  = is_string($key) ? trim($key) : '';
            if ($slot === '' || !ctype_digit($slot) || $key === '') continue;
            $slotKey[$slot] = $key;
        }

        // For a given field's text, list its positional slots in order of
        // first appearance and map each to the assigned attribute key —
        // or, when unmapped, the literal number (so nothing is dropped).
        $entriesFor = function (?string $text) use ($slotKey): array {
            if (!$text) return [];
            if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/u', $text, $m)) return [];
            $out  = [];
            $seen = [];
            foreach ($m[1] as $slot) {
                if (isset($seen[$slot])) continue;
                $seen[$slot] = true;
                $out[] = [
                    'num' => (int) $slot,
                    'key' => $slotKey[$slot] ?? $slot,
                ];
            }
            return $out;
        };

        $map = [];
        if ($h = $entriesFor($header)) $map['header'] = $h;
        if ($b = $entriesFor($body))   $map['body']   = $b;

        // If the text had no positional placeholders at all but the form
        // still carried named `{{key}}` tokens, defer to the name-derived
        // builder so those aren't lost either.
        if (!$map) {
            return $this->buildVariableMap($header, $body);
        }
        return $map;
    }

    /**
     * Build the stored positional `variable_map` for a save, preferring
     * the NAMED tokens the operator authored.
     *
     * The editor now inserts named tokens ({{order_id}}) for readability;
     * normalizePlaceholders() rewrites them to {{1}} for storage + Meta.
     * This method reads the slot→key relationship off the RAW (pre-
     * normalization) header/body so the names aren't lost: it assigns each
     * DISTINCT token a 1-based slot in first-appearance order — exactly the
     * order normalizePlaceholders() renumbers into — so slot N here maps to
     * {{N}} in the stored body, byte-for-byte.
     *
     * Idempotency / back-compat rule:
     *   - If a field's raw text contains ANY named token, we derive the map
     *     from the raw text (named tokens → their key; bare numeric tokens
     *     in a mixed body fall back to the slot→key from variable_map_json,
     *     else the literal number).
     *   - If a field's raw text is PURELY positional ({{1}}, {{2}}) — an
     *     existing template or a re-edit with no named tokens — we DON'T
     *     touch it here and defer to buildVariableMapFromRequest(), which
     *     reads variable_map_json (preserving the existing stored map and
     *     never renumbering). This keeps `Hi {{1}}` + {1:name} unchanged.
     *
     * @param  string|null $rawHeader   pre-normalization header text
     * @param  string|null $rawBody     pre-normalization body text
     * @param  string|null $normHeader  normalized (positional) header
     * @param  string|null $normBody    normalized (positional) body
     */
    private function buildVariableMapForSave(
        Request $request,
        ?string $rawHeader,
        ?string $rawBody,
        ?string $normHeader,
        ?string $normBody
    ): ?array {
        $hasNamed = fn (?string $t): bool =>
            $t !== null && preg_match('/\{\{\s*[a-zA-Z_][\w.-]*\s*\}\}/u', $t) === 1;

        // No named tokens anywhere → legacy positional path is untouched.
        if (!$hasNamed($rawHeader) && !$hasNamed($rawBody)) {
            return $this->buildVariableMapFromRequest($request, $normHeader, $normBody);
        }

        // Per field: walk DISTINCT tokens in first-appearance order (the
        // same order normalizePlaceholders uses) and emit [{num,key}].
        // A named token's key is the token name. A bare numeric token (a
        // generic {{1}} insert-chip riding alongside named ones) has no
        // attribute identity, so it maps to the literal number — it stays
        // an unmapped slot the operator can wire up later.
        $entriesFor = function (?string $text): array {
            if ($text === null || $text === '') return [];
            if (!preg_match_all('/\{\{\s*([a-zA-Z0-9_][\w.-]*)\s*\}\}/u', $text, $m)) return [];
            $out  = [];
            $seen = [];
            $i    = 0;
            foreach ($m[1] as $token) {
                if (isset($seen[$token])) continue;
                $seen[$token] = true;
                $i++;
                $out[] = ['num' => $i, 'key' => (string) $token];
            }
            return $out;
        };

        $map = [];
        if ($h = $entriesFor($rawHeader)) $map['header'] = $h;
        if ($b = $entriesFor($rawBody))   $map['body']   = $b;

        // Fall back to the request-based builder if somehow nothing came
        // out (shouldn't happen given $hasNamed above), so a slot is never
        // silently dropped.
        return $map ?: $this->buildVariableMapFromRequest($request, $normHeader, $normBody);
    }

    /**
     * Walk the per-card carousel payload submitted by the form
     * and assemble the saved structure. Each card gets a title,
     * body, optional image (uploaded via `carousel_images.{idx}`),
     * and a `buttons[]` array. Returns the parent header/body/
     * footer alongside so the controller can write them onto the
     * row in one shot.
     */
    private function processCarouselData(Request $request): array
    {
        $titles = $request->input('carousel_titles', []);
        $bodies = $request->input('carousel_bodies', []);
        $images = $request->file('carousel_images') ?? [];
        $btnTypes  = $request->input('carousel_button_types',        []);
        $btnTexts  = $request->input('carousel_button_texts',        []);
        $btnValues = $request->input('carousel_button_values',       []);
        $btnCard   = $request->input('carousel_button_card_indexes', []);

        if (empty($titles) && empty($bodies) && empty($images)) return [];

        $cards = [];
        foreach ($titles as $idx => $title) {
            $card = [
                'title'   => (string) ($title ?? ''),
                'body'    => (string) ($bodies[$idx] ?? ''),
                'image'   => null,
                'buttons' => [],
            ];
            if (isset($images[$idx]) && $images[$idx]?->isValid()) {
                $card['image'] = $images[$idx]->store('wa-templates/carousel', media_disk());
            }
            foreach ($btnCard as $bIdx => $cardIdx) {
                if ((int) $cardIdx === (int) $idx && isset($btnTypes[$bIdx])) {
                    $card['buttons'][] = [
                        'type'  => (string) $btnTypes[$bIdx],
                        'text'  => (string) ($btnTexts[$bIdx]  ?? ''),
                        'value' => (string) ($btnValues[$bIdx] ?? ''),
                    ];
                }
            }
            $cards[] = $card;
        }
        return [
            'cards'         => $cards,
            'header'        => $request->input('header'),
            'template_body' => $request->input('carousel_body') ?? $request->input('template_body'),
            'footer'        => $request->input('footer'),
        ];
    }

    /**
     * Rewrite ANY `{{placeholder}}` form to positional `{{1}}`, `{{2}}`
     * in order of first appearance. Meta defaults templates to
     * `parameter_format=POSITIONAL` and rejects named placeholders
     * outright in that mode — even one stray `{{first_name}}` fails
     * the whole template creation.
     *
     * Operators may still type `{{first_name}}` in the form (or paste
     * AI output that uses named vars); this normalizer guarantees what
     * we save and what we ship to Meta is always positional + 1-based
     * + contiguous. The original name is preserved separately in
     * `variable_map` so the dispatcher still knows which value goes
     * into each slot at send time.
     *
     * Idempotent — a body that already uses `{{1}}`, `{{2}}` comes
     * back unchanged. Treats `{{1}}` as the canonical token even if
     * the input mixes the two styles.
     *
     * @return string  normalized text
     */
    public static function normalizePlaceholders(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') return $text;

        // Walk left-to-right; assign a 1-based index to each unique
        // placeholder name. Numeric tokens map to themselves only if
        // the writer started at 1 and didn't skip — otherwise they
        // still get renumbered into order so the result is contiguous.
        $order = [];
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u',
            function ($m) use (&$order) {
                $key = $m[1];
                if (!isset($order[$key])) {
                    $order[$key] = count($order) + 1;
                }
                return '{{' . $order[$key] . '}}';
            },
            $text
        );
    }

    /**
     * Standard-template button processing — CTA buttons from
     * parallel arrays + quick-reply texts from `quick_reply[]`.
     * Returns the canonical buttons[] array we stash on the
     * model (encrypted JSON via the model cast).
     */
    private function processButtons(Request $request): array
    {
        $types  = $request->input('button_type',   []);
        $texts  = $request->input('button_text',   []);
        $values = $request->input('button_value',  []);
        $ccs    = $request->input('country_code',  []);
        $urlT   = $request->input('url_type',      []);

        $buttons = [];
        foreach ($types as $i => $type) {
            if (empty($texts[$i])) continue;
            $b = [
                'type'  => (string) $type,
                'text'  => (string) $texts[$i],
                'value' => (string) ($values[$i] ?? ''),
            ];
            if ($type === 'call_phone'    && isset($ccs[$i]))  $b['country_code'] = (string) $ccs[$i];
            if ($type === 'visit_website' && isset($urlT[$i])) $b['url_type']     = (string) $urlT[$i];
            $buttons[] = $b;
        }
        foreach ((array) $request->input('quick_reply', []) as $reply) {
            $reply = trim((string) $reply);
            if ($reply !== '') {
                $buttons[] = ['type' => 'quick_reply', 'text' => $reply];
            }
        }
        // WhatsApp reliably renders only 3 interactive buttons — enforce a
        // hard cap of 3 server-side so a crafted/over-filled form can't exceed
        // it (matches the editor BTN_MAX and the Node formatter chokepoint).
        return array_slice($buttons, 0, 3);
    }

    /**
     * Collect the optional LOCATION header from the web editor's fields:
     * latitude / longitude / location_name / location_address. Returns null
     * when no valid coordinates were entered.
     */
    /**
     * Validate latitude/longitude are real WhatsApp coordinates. Returns an
     * error string when invalid (out of range or non-numeric), else null.
     * WhatsApp silently renders a 0,0 pin for out-of-range values, so we block
     * them at save time instead.
     */
    private function validateCoordinates(Request $request): ?string
    {
        $lat = trim((string) $request->input('latitude', ''));
        $lng = trim((string) $request->input('longitude', ''));
        if ($lat === '' || $lng === '' || ! is_numeric($lat) || ! is_numeric($lng)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lng < -180 || (float) $lng > 180) {
            return __('Enter a real latitude (-90 to 90) and longitude (-180 to 180). Example: 19.0760, 72.8777');
        }
        return null;
    }

    private function collectTemplateLocation(Request $request): ?array
    {
        $lat = trim((string) $request->input('latitude', ''));
        $lng = trim((string) $request->input('longitude', ''));
        if ($lat === '' || $lng === '' || ! is_numeric($lat) || ! is_numeric($lng)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lng < -180 || (float) $lng > 180) {
            return null;
        }
        return array_filter([
            'latitude'  => (string) $lat,
            'longitude' => (string) $lng,
            'name'      => trim((string) $request->input('location_name', '')) ?: null,
            'address'   => trim((string) $request->input('location_address', '')) ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Map the uploaded MIME type to the `attachment_type` enum
     * used by the dispatcher (image / video / audio / document).
     */
    private function resolveAttachmentType($file): string
    {
        $mime = (string) $file->getMimeType();
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        return 'document';
    }

    /**
     * Internal helper used by the status-scoped routes
     * (`approved` / `pending` / `rejected`). Re-merges the request
     * with the forced filter and delegates to index() so the page
     * layout, AJAX partial path, and counts stay shared.
     */
    private function scopedIndex(Request $request, array $forced): View|JsonResponse
    {
        $request->merge($forced);
        $resp = $this->index($request);
        // index() returns a View (full page) or JsonResponse (partial)
        return $resp;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * WhatsApp Cloud API media size ceilings (MB) per resolved header type.
     * Source: developers.facebook.com/docs/whatsapp/cloud-api/reference/media
     */
    public const MEDIA_MAX_MB = ['image' => 5, 'video' => 16, 'document' => 100, 'audio' => 16];

    /** Human-readable hint of the per-type caps, for the upload UI. */
    public static function mediaSizeHint(): string
    {
        return 'Image ≤ ' . self::MEDIA_MAX_MB['image'] . 'MB · Video ≤ ' . self::MEDIA_MAX_MB['video']
             . 'MB · PDF ≤ ' . self::MEDIA_MAX_MB['document'] . 'MB';
    }

    /** Return a human error if the file exceeds its type's WhatsApp cap, else null. */
    private function assertMediaSize(\Illuminate\Http\UploadedFile $file, string $type): ?string
    {
        $maxMb = self::MEDIA_MAX_MB[$type] ?? 16;
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            $haveMb = round($file->getSize() / 1048576, 1);
            return "This {$type} is {$haveMb}MB — WhatsApp only accepts up to {$maxMb}MB for a {$type}. Please upload a smaller file.";
        }
        return null;
    }

    private function validateTemplate(Request $request, bool $updating = false, ?string $channel = null): array
    {
        $rule = $updating ? 'sometimes' : 'required';
        // Twilio templates are authored in Twilio's Content Builder (sent by
        // Content SID), so the body isn't entered in this form — don't require it.
        $bodyRule = $channel === 'twilio' ? 'sometimes|nullable|string|max:4096' : "{$rule}|string|max:4096";

        $data = $request->validate([
            'template_name'   => "{$rule}|string|max:191",
            'category'        => "sometimes|in:travel,healthcare,education,ecommerce,festival,finance,utility",
            'meta_category'   => 'sometimes|nullable|in:marketing,authentication,utility',
            'template_type'   => 'sometimes|in:standard,carousel,media,auth',
            'header'          => 'sometimes|nullable|string|max:255',
            'template_body'   => $bodyRule,
            'footer'          => 'sometimes|nullable|string|max:255',
            // Authentication (OTP) code validity — Meta allows 1–90 min; NULL → 10.
            'code_expiration_minutes' => 'sometimes|nullable|integer|min:1|max:90',
            'buttons'         => 'sometimes|nullable|array',
            'carousel_data'   => 'sometimes|nullable|array',
            'variable_map'    => 'sometimes|nullable|array',
            'attachment_type'    => 'sometimes|nullable|in:image,video,document,location,none',
            // Hard upload ceiling = WhatsApp's largest media type (document = 100MB).
            // The precise PER-TYPE cap (image 5MB / video 16MB / doc 100MB) is
            // enforced in assertMediaSize() once we know the resolved type, with a
            // human message — was previously UNLIMITED (a 40MB file passed despite
            // the UI saying "16MB").
            'attachment_file'    => 'sometimes|nullable|file|max:102400',
            'language'           => 'sometimes|string|max:16',
            'status'             => 'sometimes|in:pending,approved,rejected,public',
            // Twilio Content Builder SID (`HX...`) — optional per template,
            // required for compliant Twilio MARKETING/UTILITY/AUTH sends.
            // Format is `HX` + 32 hex digits (Twilio error 21655 if non-hex).
            'twilio_content_sid' => ['sometimes', 'nullable', 'string', 'max:34', 'regex:/^HX[0-9a-fA-F]{32}$/'],
        ]);
        return array_filter($data, fn ($v) => $v !== null);
    }

    private function applySort($items, string $sort)
    {
        return match ($sort) {
            'oldest'    => $items->sortBy('created_at')->values(),
            'name-asc'  => $items->sortBy(fn ($t)     => mb_strtolower((string) $t->template_name))->values(),
            'name-desc' => $items->sortByDesc(fn ($t) => mb_strtolower((string) $t->template_name))->values(),
            default     => $items->sortByDesc('created_at')->values(),
        };
    }

    private function categoryCounts($all): array
    {
        // Tabs reflect the WhatsApp/Meta category (Marketing · Utility ·
        // Authentication) — the category Meta reviews + bills against and the
        // one shown on each card. The local `category` field is the business
        // vertical used only for AI generation, never the library filter, so
        // it was wrongly collapsing every tab to "Utility".
        $counts = ['all' => $all->count()];
        foreach (['marketing', 'utility', 'authentication'] as $cat) {
            $counts[$cat] = $all->where('meta_category', $cat)->count();
        }
        return $counts;
    }

    /**
     * Sidebar status counts. WABA counts come from Meta's enum so
     * synthetic Baileys "approved" rows (no meta_template_id) don't
     * inflate the WABA Approved tab.
     */
    /**
     * The status the UI treats a WABA template as — generic 'approved' /
     * 'pending' / 'rejected'. Meta's verdict (meta_status) is authoritative
     * WHEN PRESENT; when it's empty or an enum value we don't map (a template
     * created/submitted but never confirmed by a Meta webhook), we fall back to
     * the local `status` column — which is exactly what the card badge shows.
     * This keeps the sidebar counts + filter in lockstep with the cards
     * ("In review 0" while cards clearly read In review was this drifting).
     */
    private function wabaEffectiveStatus($t): string
    {
        $ms = strtoupper((string) $t->meta_status);
        if ($ms === 'APPROVED' && $t->meta_template_id) return 'approved';
        if (in_array($ms, ['PENDING', 'IN_APPEAL'], true)) return 'pending';
        if (in_array($ms, ['REJECTED', 'DISABLED', 'PAUSED', 'LIMIT_EXCEEDED', 'FLAGGED'], true)) return 'rejected';

        // No / unrecognised Meta status → mirror the local status the card uses.
        return match ((string) $t->status) {
            'approved', 'public' => 'approved',
            'rejected'           => 'rejected',
            default              => 'pending',   // pending / anything else = In review
        };
    }

    private function statusCounts($all): array
    {
        $wsId   = (int) (auth()->user()?->current_workspace_id ?? 0);
        $isWaba = $wsId && WorkspaceEngine::isWaba($wsId);

        if ($isWaba) {
            // Count by the SAME effective status the card badge shows, so the
            // sidebar totals always match the visible cards (a template that
            // reads "In review" on its card is counted under In review).
            return [
                'all'      => $all->count(),
                'approved' => $all->filter(fn ($t) => $this->wabaEffectiveStatus($t) === 'approved')->count(),
                'pending'  => $all->filter(fn ($t) => $this->wabaEffectiveStatus($t) === 'pending')->count(),
                'rejected' => $all->filter(fn ($t) => $this->wabaEffectiveStatus($t) === 'rejected')->count(),
            ];
        }

        return [
            'all'      => $all->count(),
            'approved' => $all->whereIn('status', ['approved', 'public'])->count(),
            'pending'  => $all->where('status', 'pending')->count(),
            'rejected' => $all->where('status', 'rejected')->count(),
        ];
    }

    // -----------------------------------------------------------------
    // Build-with-AI
    // -----------------------------------------------------------------

    /**
     * GET /templates/api/ai-models — list AI text-generation models
     * the admin has switched on in /admin/api-keys. Drives the model
     * picker inside the Build-with-AI modal so a user never picks a
     * provider the server isn't actually configured for.
     */
    public function apiAiModels(): JsonResponse
    {
        $rows = \DB::table('admin_ai_keys')
            ->where('is_active', true)
            ->whereNotIn('provider', ['elevenlabs'])
            ->orderBy('sort_order')
            ->get(['provider', 'name', 'default_model', 'extra_config']);

        $providerLabel = [
            'openai'    => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini'    => 'Google',
            'mistral'   => 'Mistral',
        ];

        $models = [];
        foreach ($rows as $r) {
            $label = $providerLabel[$r->provider] ?? ucfirst($r->provider);
            $default = (string) ($r->default_model ?? '');
            if ($default === '') continue;
            $extra = json_decode((string) ($r->extra_config ?? '[]'), true) ?: [];
            $extraModels = is_array($extra['models'] ?? null) ? $extra['models'] : [];
            $list = array_values(array_unique(array_merge([$default], $extraModels)));
            foreach ($list as $m) {
                $models[] = [
                    'value'    => $m,
                    'label'    => $label . ' · ' . $m,
                    'provider' => $r->provider,
                ];
            }
        }

        // BYOK — the workspace's OWN active AI keys ALSO appear and get used, so
        // the picker offers admin-enabled providers OR the user's own key.
        $ws = auth()->user()?->current_workspace_id
            ? \App\Models\Workspace::find(auth()->user()->current_workspace_id)
            : null;
        if ($ws) {
            $byokDefaults = [
                'openai'    => ['gpt-4o-mini', 'gpt-4o'],
                'anthropic' => ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
                'gemini'    => ['gemini-2.0-flash', 'gemini-1.5-pro'],
                'mistral'   => ['mistral-large-latest', 'mistral-small-latest'],
            ];
            $own = \App\Models\AiProviderKey::query()
                ->where('workspace_id', $ws->id)->where('is_active', true)
                ->pluck('provider')->all();
            foreach ($own as $prov) {
                // Workspace has its OWN key for this provider → drop the admin's
                // models for it so ONLY the user's key shows (not both).
                $models = array_values(array_filter($models, fn ($mm) => $mm['provider'] !== $prov));
                $plabel = $providerLabel[$prov] ?? ucfirst($prov);
                foreach (($byokDefaults[$prov] ?? []) as $m) {
                    $models[] = ['value' => $m, 'label' => $plabel . ' (your key) · ' . $m, 'provider' => $prov];
                }
            }
        }

        return response()->json(['ok' => true, 'models' => $models]);
    }

    /**
     * POST /templates/api/ai-generate — generate a ready-to-fill
     * WhatsApp template from a structured brief + optional free-form
     * prompt. Returns header / body / footer / buttons that the
     * front-end pastes into the create form's existing inputs.
     *
     * AiAgentService::callProvider only implements openai/anthropic/
     * gemini today, so the validator caps the provider list to those.
     */
    public function apiAiGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model'         => 'required|string|max:120',
            'provider'      => 'required|string|in:openai,anthropic,gemini',
            'type'          => 'nullable|string|in:standard,carousel',
            'category'      => 'nullable|string|in:marketing,utility,authentication',
            'language'      => 'nullable|string|max:16',
            'business_name' => 'required|string|max:191',
            'industry'      => 'nullable|string|max:120',
            'occasion'      => 'nullable|string|max:120',
            'purpose'       => 'nullable|string|max:120',
            'action'        => 'nullable|string|max:60',
            'tone'          => 'nullable|string|max:60',
            'custom_prompt' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $workspace = $user?->current_workspace_id
            ? \App\Models\Workspace::find($user->current_workspace_id)
            : null;

        $resolved = \App\Services\AiKeyResolver::resolve($workspace, $data['provider']);
        if (!$resolved['key']) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_key',
                'message' => 'Admin has not enabled this provider in /admin/api-keys.',
            ], 422);
        }

        $type     = $data['type'] ?? 'standard';
        $category = $data['category'] ?? 'utility';
        $language = $data['language'] ?? 'en_US';

        // System prompt — STRICT JSON only so the front-end can parse
        // and paint the form without follow-up regex juggling.
        $systemPrompt = $type === 'carousel'
            ? $this->aiSystemPromptCarousel()
            : $this->aiSystemPromptStandard();

        // User prompt assembles the structured brief into a single
        // natural-language request, then appends the operator's custom
        // notes if they typed any. The brief alone is usually enough.
        $userPrompt = $this->buildAiUserPrompt($data, $category, $language);

        $ai = app(\App\Services\AiAgentService::class);
        $raw = $ai->callProvider(
            provider:     $data['provider'],
            model:        $data['model'],
            workspaceId:  (int) ($workspace?->id ?? 0),
            systemPrompt: $systemPrompt,
            userPrompt:   $userPrompt,
            maxTokens:    1600,
            temperature:  0.6,
        );

        if (!$raw) {
            return response()->json([
                'ok'      => false,
                'error'   => 'provider_failed',
                'message' => 'AI provider returned no content — check API key + model id.',
            ], 502);
        }

        // The model sometimes wraps JSON in code fences despite the
        // system prompt's instruction — strip them before decoding.
        $clean = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $m)) {
            $clean = trim($m[1]);
        }

        $tpl = json_decode($clean, true);
        if (!is_array($tpl)) {
            Log::warning('[AI-Template] bad JSON from model: ' . substr($raw, 0, 400));
            return response()->json([
                'ok'      => false,
                'error'   => 'bad_json',
                'message' => 'Model output was not valid JSON. Try again or refine the brief.',
                'raw'     => mb_substr($raw, 0, 600),
            ], 422);
        }

        // Hard caps — Meta's template surface enforces these and the
        // operator shouldn't be able to paste 10 KB into the body
        // field by accident.
        $payload = [
            'template_name' => mb_substr((string) ($tpl['template_name'] ?? ''), 0, 60),
            'header'        => mb_substr((string) ($tpl['header'] ?? ''), 0, 60),
            'body'          => mb_substr((string) ($tpl['body'] ?? ''), 0, 1024),
            'footer'        => mb_substr((string) ($tpl['footer'] ?? ''), 0, 60),
            'buttons'       => [],
        ];
        $buttons = is_array($tpl['buttons'] ?? null) ? $tpl['buttons'] : [];
        foreach (array_slice($buttons, 0, 3) as $b) {
            if (!is_array($b)) continue;
            $btype = (string) ($b['type'] ?? 'visit_website');
            if (!in_array($btype, ['visit_website', 'call_phone', 'copy_code'], true)) {
                $btype = 'visit_website';
            }
            $payload['buttons'][] = [
                'type'  => $btype,
                'text'  => mb_substr((string) ($b['text'] ?? ''), 0, 25),
                'value' => mb_substr((string) ($b['value'] ?? ''), 0, 2000),
            ];
        }
        if ($type === 'carousel') {
            $cards = is_array($tpl['cards'] ?? null) ? $tpl['cards'] : [];
            $payload['cards'] = [];
            foreach (array_slice($cards, 0, 10) as $c) {
                if (!is_array($c)) continue;
                $payload['cards'][] = [
                    'title'       => mb_substr((string) ($c['title'] ?? ''), 0, 40),
                    'body'        => mb_substr((string) ($c['body'] ?? ''), 0, 160),
                    'button_text' => mb_substr((string) ($c['button_text'] ?? ''), 0, 25),
                    'button_url'  => mb_substr((string) ($c['button_url'] ?? ''), 0, 2000),
                ];
            }
        }

        return response()->json([
            'ok'       => true,
            'template' => $payload,
            'model'    => $data['model'],
        ]);
    }

    private function aiSystemPromptStandard(): string
    {
        return <<<'SYS'
You write WhatsApp Business message templates. Output STRICT JSON only —
no prose, no markdown, no code fences. Schema:

{
  "template_name": "<lowercase a-z 0-9 underscore, max 60>",
  "header": "<plain text, max 60, optional, no variables>",
  "body":   "<the main message, max 1024, use *bold* _italic_ markers, use {{1}} {{2}} for placeholders>",
  "footer": "<short legal/closing line, max 60, optional, no variables>",
  "buttons": [
    { "type": "visit_website|call_phone|copy_code", "text": "<max 25>", "value": "<url|phone|code>" }
  ]
}

Rules:
1. Match Meta's WhatsApp Business template policy: clear, no spammy
   wording, no emojis, no all-caps shouting.
2. Use {{1}}, {{2}} for personalisation tokens — first_name typically
   maps to {{1}}.
3. Buttons are optional. If you include them, keep to 1-3.
4. Keep tone, language, and intent consistent with the brief.
5. Output ONLY the JSON object. No explanation. No code fences.
SYS;
    }

    private function aiSystemPromptCarousel(): string
    {
        return <<<'SYS'
You write WhatsApp Business CAROUSEL templates. Output STRICT JSON only —
no prose, no markdown, no code fences. Schema:

{
  "template_name": "<lowercase a-z 0-9 underscore, max 60>",
  "header": "<intro header, max 60, optional, no variables>",
  "body":   "<intro body shown above the cards, max 1024>",
  "footer": "<short closing line, max 60, optional>",
  "buttons": [],
  "cards": [
    { "title": "<max 40>", "body": "<max 160>", "button_text": "<max 25>", "button_url": "<https://...>" }
  ]
}

Rules:
1. Generate 3-5 swipeable cards unless the brief asks for more.
2. Cards share a common theme (e.g. a product line, a destination set).
3. No emojis. Plain text only. Use {{1}}, {{2}} for placeholders if needed.
4. Keep tone, language, and intent consistent with the brief.
5. Output ONLY the JSON object. No explanation. No code fences.
SYS;
    }

    private function buildAiUserPrompt(array $d, string $category, string $language): string
    {
        $lines = [];
        $lines[] = 'Business name: ' . $d['business_name'];
        $lines[] = 'Template category (Meta review path): ' . $category;
        $lines[] = 'Language: ' . $language;
        if (!empty($d['industry']))  $lines[] = 'Industry: ' . $d['industry'];
        if (!empty($d['occasion']))  $lines[] = 'Occasion: ' . $d['occasion'];
        if (!empty($d['purpose']))   $lines[] = 'Message purpose: ' . $d['purpose'];
        if (!empty($d['action']))    $lines[] = 'Primary call to action: ' . $d['action'];
        if (!empty($d['tone']))      $lines[] = 'Tone: ' . $d['tone'];
        if (!empty($d['custom_prompt'])) {
            $lines[] = '';
            $lines[] = 'Additional operator notes:';
            $lines[] = $d['custom_prompt'];
        }
        return implode("\n", $lines);
    }
}
