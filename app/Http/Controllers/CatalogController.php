<?php

namespace App\Http\Controllers;

use App\Exceptions\WhatsAppCatalogException;
use App\Models\WaCatalog;
use App\Models\WaProduct;
use App\Models\WaProductSet;
use App\Models\WaStorefront;
use App\Services\WhatsAppCatalog\WhatsAppCatalogFactory;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * /store/catalog — Meta Commerce Catalog management. Everything in
 * here is SYNCHRONOUS by design: no queues, no jobs, no scheduled
 * commands. The admin clicks a button → AJAX request → Meta API
 * call → updated rows → JSON response → UI re-renders. Keeps the
 * deploy story simple (no worker process, no cron) and lets the
 * operator see results immediately.
 *
 * Trade-off: a "Sync 5000 products" request blocks for as long as
 * Meta takes to accept the batch. We cap at 1000 per click (one
 * Meta batch call) and the UI loops if there's more.
 */
class CatalogController extends Controller
{
    /**
     * Per Meta's items_batch hard cap. We push up to this many
     * products per AJAX call; the UI re-fires for the next chunk
     * until done.
     */
    private const CHUNK = 1000;

    public function index(): Renderable|RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return redirect('/dashboard');

        $catalog = WaCatalog::where('workspace_id', $wsId)->first();
        $shops   = WaStorefront::where('workspace_id', $wsId)->orderByDesc('id')->get();

        $statusBuckets = WaProduct::where('workspace_id', $wsId)
            ->selectRaw('COALESCE(meta_sync_status, "unsynced") as bucket, COUNT(*) as n')
            ->groupBy('bucket')
            ->pluck('n', 'bucket')
            ->toArray();

        $products = WaProduct::where('workspace_id', $wsId)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        // Catalog health: Meta rejects (or silently hides) products that
        // are missing an image, a price, or that errored on sync. A buyer
        // never sees those SKUs, so they're pure lost sales. We surface a
        // single score + the offending rows so the operator can fix them.
        $health = $this->catalogHealth($wsId);

        // Detect whether the operator has a working send path BEFORE
        // we render. The Setup tab tailors itself per state:
        //   • Meta catalog connected     → full sync UI
        //   • A connected sender          → "you're ready" card + optional Meta connect
        //   • Nothing                     → "connect a device first" prompt
        //
        // A working sender is engine-specific: Unofficial-API workspaces
        // pair a phone (devices table), while WABA / Twilio workspaces
        // have no `devices` row at all — their live number lives in
        // wa_provider_configs. Checking only `devices` wrongly showed
        // "connect a device first" to workspaces whose WABA number is
        // already live. Mirror sendPage()'s engine-aware sender lookup.
        $engine = \App\Services\WorkspaceEngine::for($wsId);

        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $devices = \App\Models\Device::query()
                ->forCurrentWorkspace()
                ->where('status', 'connected')
                ->orderByDesc('active')
                ->get();
        } else {
            // Normalise provider configs into the same shape the Setup
            // view renders (device_name / country_code / phone_number /
            // status) so the "connected devices" card works unchanged.
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get()
                ->map(fn ($c) => (object) [
                    'id'           => $c->id,
                    'device_name'  => $c->display_label ?: strtoupper((string) $c->provider),
                    'country_code' => '',
                    'phone_number' => $c->phone_number,
                    'status'       => 'connected',
                ]);
        }

        $hasBaileysDevice = $devices->isNotEmpty();

        return view('user.catalog.index', [
            'tab'              => 'setup',
            'catalog'          => $catalog,
            'shops'            => $shops,
            'products'         => $products,
            'statusBuckets'    => $statusBuckets,
            'health'           => $health,
            'sets'             => WaProductSet::where('workspace_id', $wsId)->orderByDesc('id')->get(),
            'totalProducts'    => WaProduct::where('workspace_id', $wsId)->count(),
            'recentSends'      => $this->recentSends($wsId, 5),
            'devices'          => $devices,
            'hasBaileysDevice' => $hasBaileysDevice,
            'engine'           => $engine,
        ]);
    }

    /**
     * Catalog health for a workspace. A product is "unsellable" to Meta
     * if it has no image, no price, or its last sync errored — buyers
     * never see those SKUs. Returns a 0-100 score (share of healthy
     * rows), per-issue counts, and a short sample of offending rows so
     * the Setup tab can show "fix these N products".
     *
     * @return array{score:?int,total:int,no_image:int,no_price:int,errored:int,flagged:int,samples:\Illuminate\Support\Collection}
     */
    private function catalogHealth(int $wsId): array
    {
        // A row is flagged when it can't sell as-is.
        $flaggedScope = fn ($q) => $q->where(function ($w) {
            $w->whereNull('image_url')->orWhere('image_url', '')
              ->orWhereNull('price_minor')->orWhere('price_minor', 0)
              ->orWhere('meta_sync_status', 'error');
        });

        $counts = WaProduct::where('workspace_id', $wsId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN image_url IS NULL OR image_url = '' THEN 1 ELSE 0 END) as no_image")
            ->selectRaw('SUM(CASE WHEN price_minor IS NULL OR price_minor = 0 THEN 1 ELSE 0 END) as no_price')
            ->selectRaw("SUM(CASE WHEN meta_sync_status = 'error' THEN 1 ELSE 0 END) as errored")
            ->first();

        $total   = (int) ($counts->total ?? 0);
        $flagged = $total > 0
            ? WaProduct::where('workspace_id', $wsId)->where($flaggedScope)->count()
            : 0;

        $samples = $flagged > 0
            ? WaProduct::where('workspace_id', $wsId)->where($flaggedScope)
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get(['id', 'name', 'sku', 'image_url', 'price_minor', 'meta_sync_status'])
            : collect();

        return [
            'score'    => $total > 0 ? (int) round(($total - $flagged) / $total * 100) : null,
            'total'    => $total,
            'no_image' => (int) ($counts->no_image ?? 0),
            'no_price' => (int) ($counts->no_price ?? 0),
            'errored'  => (int) ($counts->errored ?? 0),
            'flagged'  => $flagged,
            'samples'  => $samples,
        ];
    }

    /**
     * Send tab — the form to send a catalog to ANY phone number.
     * The reason this feature exists as its own surface: in-chat
     * sending requires a conversation thread; this lets the operator
     * cold-blast a customer who hasn't messaged them yet (within the
     * 24-h rules — see runtime guards in sendToNumber()).
     */
    public function sendPage(): Renderable|RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return redirect('/dashboard');

        $catalog = WaCatalog::where('workspace_id', $wsId)->first();
        // Engine-aware sender picker — Baileys workspaces send catalogs
        // through a paired phone (devices table); WABA / Twilio
        // workspaces send through their wa_provider_configs row.
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $devices = \App\Models\Device::query()
                ->forCurrentWorkspace()
                ->where('status', 'connected')
                ->orderByDesc('active')
                ->get();
        } else {
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get();
        }

        // Recipient picker data — same shape as wa-campaigns/create so
        // the form patterns + JS stay consistent.
        $contacts = \App\Models\Contact::orderByDesc('id')->get();
        $groups   = \App\Models\ContactGroup::orderByDesc('id')->get();

        $allContacts = \App\Models\Contact::all(['id', 'contact_group']);
        $groupCounts = [];
        foreach ($groups as $g) {
            $gid = (string) $g->id;
            $groupCounts[$g->id] = $allContacts->filter(function ($c) use ($gid) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array($gid, array_map('strval', $list), true);
            })->count();
        }

        // Shop selector — operator picks WHICH storefront to send
        // from. Products are now shop-scoped via wa_products.storefront_id
        // (migration 2026_05_13_070000) so picking a shop actually
        // filters the product picker.
        $shops = WaStorefront::where('workspace_id', $wsId)
            ->orderByDesc('id')
            ->get();

        // Load products grouped by (storefront_id × category). The
        // view picks the right group when the shop dropdown changes.
        $allProducts = WaProduct::where('workspace_id', $wsId)
            ->where('status', 'active')
            ->where('in_stock', true)
            ->ordered()
            ->get(['id', 'storefront_id', 'name', 'sku', 'image_url', 'price_minor', 'currency_code', 'category', 'meta_retailer_id', 'meta_sync_status']);

        // Default initial filter — the first shop's products.
        $selectedShopId = $shops->first()?->id;
        $shopProducts = $allProducts->where('storefront_id', $selectedShopId);
        $productsByCategory = $shopProducts->groupBy(fn ($p) => $p->category ?: 'Other');

        // Build a JS-side map { shopId => [products...] } so the
        // dropdown can swap categories without a round-trip.
        $productMapForJs = $allProducts->groupBy('storefront_id')->map(function ($byShop) {
            return $byShop->groupBy(fn ($p) => $p->category ?: 'Other')->map(function ($cat) {
                return $cat->map(fn ($p) => [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'sku'          => $p->sku,
                    'image_url'    => $p->image_url,
                    'price_display'=> $p->price_display,
                    'retailer_id'  => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                ])->values();
            });
        });

        // Multi-engine: all connected senders across every enabled engine,
        // for the unified <x-sender-picker> (composite engine:id keys).
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        return view('user.catalog.index', [
            'tab'                => 'send',
            'catalog'            => $catalog,
            'devices'            => $devices,
            'senders'            => $senders,
            'totalProducts'      => $allProducts->count(),
            'recentSends'        => $this->recentSends($wsId, 10),
            'contacts'           => $contacts,
            'groups'             => $groups,
            'groupCounts'        => $groupCounts,
            'shops'              => $shops,
            'productsByCategory' => $productsByCategory,
            'selectedShopId'     => $selectedShopId,
            'productMapForJs'    => $productMapForJs,
        ]);
    }

    public function activityPage(): Renderable|RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return redirect('/dashboard');

        $catalog = WaCatalog::where('workspace_id', $wsId)->first();
        return view('user.catalog.index', [
            'tab'         => 'activity',
            'catalog'     => $catalog,
            'recentSends' => $this->recentSends($wsId, 50),
        ]);
    }

    /**
     * Pull the workspace's recent catalog sends out of inbox_messages
     * where meta->kind='catalog'. inbox_messages has no workspace_id
     * column — we filter via conversation.workspace_id with a join.
     */
    private function recentSends(int $wsId, int $limit = 10)
    {
        return \App\Models\InboxMessage::query()
            ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->whereJsonContains('inbox_messages.meta->kind', 'catalog')
            ->orderByDesc('inbox_messages.id')
            ->limit($limit)
            ->select('inbox_messages.*')
            ->get();
    }

    /**
     * AJAX endpoint — the heart of the new Send tab.
     *
     * Sends a catalog (SPM / MPM / link) to a phone number that
     * doesn't need an existing conversation. The operator types the
     * number, picks products, hits Send.
     *
     * Flow:
     *  1. Validate phone + mode + product selection
     *  2. Pick a device (operator chose one, or default to first
     *     connected one)
     *  3. Look up / lazy-create a Conversation so the send shows up
     *     in /team-inbox afterwards
     *  4. Route to WhatsAppCatalogFactory (WABA) or BaileysCatalogService
     *  5. Log an InboxMessage for the audit trail
     */
    public function sendToNumber(Request $request): JsonResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);

        $data = $request->validate([
            // Recipient sources — at least one must yield ≥1 number.
            'manual_numbers'       => ['nullable', 'string', 'max:65535'],
            'group_ids'            => ['nullable', 'array'],
            'group_ids.*'          => ['integer'],
            'contact_ids'          => ['nullable', 'array'],
            'contact_ids.*'        => ['integer'],
            // Send config
            'device_id'            => ['nullable', 'integer'],
            // Multi-engine: unified picker posts a composite `engine:id` key.
            'sender'               => ['nullable', 'string', 'max:64'],
            'shop_id'              => ['nullable', 'integer'],
            'mode'                 => ['required', 'in:spm,mpm,link'],
            'body'                 => ['nullable', 'string', 'max:1024'],
            'header'               => ['nullable', 'string', 'max:60'],
            'footer'               => ['nullable', 'string', 'max:60'],
            'product_ids'          => ['nullable', 'array', 'max:30'],
            'product_ids.*'        => ['integer'],
        ]);

        // Multi-engine: resolve the composite `engine:id` sender key. The
        // picked engine stamps the conversation provider (so the thread routes
        // over the channel the operator chose); a picked Baileys device also
        // narrows the device lookup below. Falls back to the workspace default.
        $pickedEngine = null;
        if ($request->filled('sender')) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $request->input('sender'));
            if ($picked) {
                $pickedEngine = (string) $picked['engine'];
                if ($pickedEngine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
                    $data['device_id'] = (int) $picked['id'];
                }
            }
        }

        // ── Resolve the recipient list ──
        $recipients = $this->resolveRecipients(
            $wsId,
            $data['manual_numbers']  ?? '',
            $data['group_ids']       ?? [],
            $data['contact_ids']     ?? [],
        );
        if (empty($recipients)) {
            return response()->json(['ok' => false, 'error' => 'no_recipients',
                'message' => 'Pick at least one contact, group, or paste a number.'], 422);
        }
        if (count($recipients) > 500) {
            return response()->json(['ok' => false, 'error' => 'too_many',
                'message' => 'Maximum 500 recipients per send. Split into smaller batches.'], 422);
        }

        // Resolve products once — shared across all recipients.
        $products = collect();
        if (!empty($data['product_ids'])) {
            $products = WaProduct::where('workspace_id', $wsId)
                ->whereIn('id', $data['product_ids'])
                ->where('status', 'active')->get();
        }
        if ($data['mode'] !== 'link' && $products->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'no_products',
                'message' => 'Pick at least one product first.'], 422);
        }

        // Pick device — once for the whole batch.
        $device = null;
        if (!empty($data['device_id'])) {
            $device = \App\Models\Device::query()->forCurrentWorkspace()
                ->where('id', $data['device_id'])->where('status', 'connected')->first();
        }
        if (!$device) {
            $device = \App\Models\Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')
                ->orderByDesc('active')->first();
        }
        if (!$device) {
            return response()->json(['ok' => false, 'error' => 'no_device',
                'message' => 'No connected device. Pair one at /devices first.'], 422);
        }

        $productPayload = [
            'mode'   => $data['mode'],
            'body'   => $data['body']   ?? null,
            'header' => $data['header'] ?? null,
            'footer' => $data['footer'] ?? null,
            'product_ids' => $data['product_ids'] ?? [],
            'product_retailer_ids' => $products->map(fn ($p) =>
                $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id))->values()->all(),
        ];
        if ($data['mode'] === 'spm' && $products->isNotEmpty()) {
            $productPayload['product_id'] = $products->first()->id;
        }

        $sent = 0; $failed = 0; $errors = [];

        foreach ($recipients as $toWaId) {
            // Find-or-create a Conversation per recipient so each
            // send shows up in /team-inbox under that thread.
            // The conversations table identifies the customer by
            // raw_jid (WhatsApp JID = phone digits); there is no
            // customer_phone column — we hit that bug live the first
            // time this method was exercised.
            // ONE THREAD PER NUMBER - see ConversationResolver.
            //
            // This was a firstOrCreate keyed on the EXACT (workspace, device_id,
            // raw_jid) triple, so it matched only a byte-identical JID on the
            // same device. A customer already threaded by inbound (stored
            // '919...@s.whatsapp.net') or reached from a different device got a
            // second thread on every catalog send. The resolver matches on the
            // digits and ignores device_id, so it reuses the existing thread.
            $conv = \App\Services\Inbox\ConversationResolver::findOrCreate($wsId, $toWaId, [
                'device_id'       => $device->id,
                'status'          => 'open',
                'inbox_status'    => 'open',
                'priority'        => 'normal',
                'origin'          => 'inbox',
                'provider'        => $pickedEngine ?: \App\Services\WorkspaceEngine::for($wsId),
                'last_message_at' => now(),
            ]);
            if (!$conv) { $failed++; $errors[] = 'invalid recipient'; continue; }

            try {
                $fake = Request::create('', 'POST', $productPayload);
                $fake->setUserResolver(fn () => Auth::user());
                $resp = app(\App\Http\Controllers\TeamInboxController::class)
                    ->catalogContent($fake, $conv->id);
                $payload = $resp->getData(true);
                if (($payload['ok'] ?? false) === true) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = "$toWaId: " . ($payload['message'] ?? 'unknown');
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "$toWaId: " . $e->getMessage();
            }
        }

        return response()->json([
            'ok'           => $sent > 0,
            'sent'         => $sent,
            'failed'       => $failed,
            'total'        => count($recipients),
            'errors'       => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Merge three recipient sources into a deduped digits-only list.
     *
     *  • manual_numbers  — pasted textarea (newline/comma/semicolon)
     *  • group_ids[]     — every contact tagged with the group
     *  • contact_ids[]   — explicit contact pick
     *
     * @return array<int,string>
     */
    private function resolveRecipients(int $wsId, string $manual, array $groupIds, array $contactIds): array
    {
        $phones = [];

        if (!empty($manual)) {
            $parts = preg_split('/[\s,;]+/', $manual, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $digits = preg_replace('/\D+/', '', $part);
                if (strlen($digits) >= 8) $phones[$digits] = true;
            }
        }

        // Direct contact picks — workspace-scoped so a forged
        // contact_ids[] payload can't target another tenant's contacts.
        if (!empty($contactIds)) {
            $rows = \App\Models\Contact::query()
                ->where('workspace_id', $wsId)
                ->whereIn('id', $contactIds)
                ->get(['mobile']);
            foreach ($rows as $c) {
                $digits = preg_replace('/\D+/', '', (string) $c->mobile);
                if (strlen($digits) >= 8) $phones[$digits] = true;
            }
        }

        // Group expansion — contact.contact_group is encrypted JSON
        // so we filter in PHP rather than in SQL. Workspace-scoped to
        // avoid pulling every workspace's contacts into memory.
        if (!empty($groupIds)) {
            $stringIds = array_map('strval', $groupIds);
            $rows = \App\Models\Contact::query()
                ->where('workspace_id', $wsId)
                ->get(['id', 'mobile', 'contact_group']);
            foreach ($rows as $c) {
                $cg = is_array($c->contact_group) ? $c->contact_group : [];
                $cg = array_map('strval', $cg);
                if (array_intersect($cg, $stringIds)) {
                    $digits = preg_replace('/\D+/', '', (string) $c->mobile);
                    if (strlen($digits) >= 8) $phones[$digits] = true;
                }
            }
        }

        return array_keys($phones);
    }

    public function connect(Request $request): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);

        $data = $request->validate([
            'provider'        => ['required', 'in:meta_cloud,dialog_360'],
            'catalog_id'      => ['required', 'string', 'max:64'],
            'catalog_name'    => ['nullable', 'string', 'max:191'],
            'waba_id'         => ['nullable', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'access_token'    => ['required', 'string', 'min:20', 'max:4096'],
        ]);

        $catalog = WaCatalog::updateOrCreate(
            ['workspace_id' => $wsId, 'provider' => $data['provider']],
            [
                'catalog_id'        => $data['catalog_id'],
                'catalog_name'      => $data['catalog_name'] ?? null,
                'waba_id'           => $data['waba_id'] ?? null,
                'phone_number_id'   => $data['phone_number_id'] ?? null,
                'access_token_enc'  => $data['access_token'],
                'is_cart_enabled'   => true,
                'is_catalog_visible'=> true,
            ],
        );

        // Verify credentials by pulling catalog metadata. Bad creds
        // = remove the row immediately so the admin can retry.
        try {
            WhatsAppCatalogFactory::forCatalog($catalog)->verifyCatalog();
        } catch (WhatsAppCatalogException $e) {
            $catalog->delete();
            // Surface what META actually returned — not just the short message.
            // The exception carries Meta's full error envelope (code, subcode,
            // fbtrace) in context(); show it plus an actionable hint so the
            // client can fix it instead of staring at "Unsupported get request".
            $err  = (array) ($e->context()['error'] ?? []);
            $code = (int) ($err['code'] ?? $e->getCode());
            $sub  = (int) ($err['error_subcode'] ?? 0);
            $raw  = trim((string) ($err['message'] ?? $e->getMessage()));
            // "(#100) Unsupported get request" on GET /{catalog_id} almost always
            // means the access token can't READ the catalog object: a WhatsApp
            // Cloud-API token carries whatsapp_business_messaging/management but
            // NOT catalog_management, so Meta refuses the read (or the Catalog ID
            // is wrong / not owned by this Business).
            $hint = ($code === 100 || str_contains(strtolower($raw), 'unsupported get request'))
                ? __(' — This usually means the access token is missing the "catalog_management" permission (a WhatsApp messaging token can SEND catalog messages but not READ the catalog), or the Catalog ID is wrong. Use a System-User token from Meta Business Manager that has catalog_management + business_management, and copy the Catalog ID from Commerce Manager.')
                : '';
            return back()->withErrors([
                'access_token' => __('Meta rejected verification:') . ' ' . ($raw ?: __('unknown error'))
                    . ($code ? ' (' . __('code') . ' ' . $code . ($sub ? '/' . $sub : '') . ')' : '')
                    . $hint,
            ])->withInput();
        } catch (\Throwable $e) {
            // Safety net — a network/unexpected failure must never leak a raw
            // 500 to the connect form. Roll back the half-created row and show a
            // clean message instead.
            $catalog->delete();
            \Log::warning('[wa-catalog] connect verify threw', ['error' => $e->getMessage()]);
            return back()->withErrors([
                'access_token' => 'Could not verify the catalog right now — check the Catalog ID and access token, then try again.',
            ])->withInput();
        }

        return redirect('/catalog')->with('status', 'Catalog connected.');
    }

    /**
     * One-click link — no keys to paste. When the workspace already has
     * a connected WABA number, we hold its access token + waba_id in
     * wa_provider_configs. The WABA connect flow even links (or creates)
     * a Meta Commerce catalog and stashes its id in meta_json.catalog_id.
     * So we can wire the Commerce catalog straight from those stored
     * credentials instead of asking the operator to re-enter Catalog ID,
     * WABA ID and an access token by hand.
     *
     * Resolution order for the catalog id:
     *   1. meta_json.catalog_id / creds.catalog_id  (set at WABA connect)
     *   2. GET /{waba_id}/product_catalogs           (already linked on Meta)
     *   3. POST /{business_id}/owned_product_catalogs + link  (create one)
     */
    public function autodetect(): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);

        $cfg = \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $wsId)
            ->where('provider', 'waba')
            ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
            ->orderByDesc('is_primary')
            ->orderByDesc('connected_at')
            ->first();

        if (!$cfg) {
            return back()->withErrors([
                'autodetect' => __('No connected WhatsApp Business (WABA) number found. Connect a WABA number first, or link the catalog manually below.'),
            ]);
        }

        $creds = $cfg->creds();
        $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];

        $token       = (string) ($creds['access_token'] ?? '');
        $wabaId      = (string) ($meta['waba_id'] ?? $creds['waba_id'] ?? '');
        $phoneId     = (string) ($meta['phone_number_id'] ?? $creds['phone_number_id'] ?? '');
        $businessId  = (string) ($meta['business_id'] ?? $creds['business_id'] ?? '');
        $catalogId   = (string) ($meta['catalog_id'] ?? $creds['catalog_id'] ?? '');

        if ($token === '' || $wabaId === '') {
            return back()->withErrors([
                'autodetect' => __('Your WABA number is connected but its Meta access token could not be read. Reconnect the number, or link the catalog manually below.'),
            ]);
        }

        $gv = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');

        // Fill in the catalog id from Meta when the connect step didn't stash one.
        if ($catalogId === '') {
            try {
                $list = \Illuminate\Support\Facades\Http::withToken($token)->timeout(12)
                    ->get("https://graph.facebook.com/{$gv}/{$wabaId}/product_catalogs");
                if ($list->successful() && !empty($list->json('data.0.id'))) {
                    $catalogId = (string) $list->json('data.0.id');
                } elseif ($businessId !== '') {
                    // Nothing linked yet — create one and attach it to the WABA.
                    $create = \Illuminate\Support\Facades\Http::withToken($token)->timeout(12)
                        ->post("https://graph.facebook.com/{$gv}/{$businessId}/owned_product_catalogs", [
                            'name'     => \App\Support\Brand::name() . ' · ' . now()->format('Y-m-d'),
                            'vertical' => 'commerce',
                        ]);
                    if ($create->successful() && !empty($create->json('id'))) {
                        $catalogId = (string) $create->json('id');
                        \Illuminate\Support\Facades\Http::withToken($token)->timeout(12)
                            ->post("https://graph.facebook.com/{$gv}/{$wabaId}/product_catalogs", [
                                'catalog_id' => $catalogId,
                            ]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('[wa-catalog] autodetect list/create failed', ['ws' => $wsId, 'error' => $e->getMessage()]);
            }
        }

        if ($catalogId === '') {
            return back()->withErrors([
                'autodetect' => __('No product catalog is linked to your WhatsApp account yet. Create one in Meta Commerce Manager (or add a Business ID to your WABA connection), then try again — or link it manually below.'),
            ]);
        }

        // Best-effort friendly name straight from Meta.
        $catalogName = null;
        try {
            $info = \Illuminate\Support\Facades\Http::withToken($token)->timeout(10)
                ->get("https://graph.facebook.com/{$gv}/{$catalogId}", ['fields' => 'name']);
            if ($info->successful()) $catalogName = $info->json('name');
        } catch (\Throwable $e) { /* label is optional */ }

        // Persist the id back onto the WABA config so a later reconnect keeps it.
        if (($meta['catalog_id'] ?? null) !== $catalogId) {
            $cfg->meta_json = array_merge($meta, ['catalog_id' => $catalogId]);
            $cfg->save();
        }

        $catalog = WaCatalog::updateOrCreate(
            ['workspace_id' => $wsId, 'provider' => WaCatalog::PROVIDER_META_CLOUD],
            [
                'catalog_id'         => $catalogId,
                'catalog_name'       => $catalogName ?: 'WhatsApp Catalog',
                'waba_id'            => $wabaId,
                'phone_number_id'    => $phoneId ?: null,
                'access_token_enc'   => $token,
                'is_cart_enabled'    => true,
                'is_catalog_visible' => true,
            ],
        );

        // Same verification gate as the manual connect — bad/expired token
        // rolls the row back so we never show a broken "connected" state.
        try {
            WhatsAppCatalogFactory::forCatalog($catalog)->verifyCatalog();
        } catch (WhatsAppCatalogException $e) {
            $catalog->delete();
            return back()->withErrors([
                'autodetect' => __('Found catalog :id but Meta rejected the stored token: :msg', ['id' => $catalogId, 'msg' => $e->getMessage()]),
            ]);
        } catch (\Throwable $e) {
            $catalog->delete();
            \Log::warning('[wa-catalog] autodetect verify threw', ['ws' => $wsId, 'error' => $e->getMessage()]);
            return back()->withErrors([
                'autodetect' => __('Could not verify the catalog right now — please try again in a moment.'),
            ]);
        }

        return redirect('/catalog')->with('status', __('Catalog linked from your WhatsApp account.'));
    }

    public function disconnect(): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        WaCatalog::where('workspace_id', $wsId)->delete();
        WaProduct::where('workspace_id', $wsId)->update([
            'meta_sync_status'  => null,
            'meta_batch_handle' => null,
            'meta_synced_at'    => null,
            'meta_last_error'   => null,
        ]);
        return redirect('/catalog')->with('status', 'Catalog disconnected.');
    }

    /**
     * PULL products FROM the connected Meta catalog into wa_products. For a
     * catalog built directly in Meta Commerce Manager (WaDesk stores products
     * locally and only pushes UP, so a Meta-side catalog shows 0 here and
     * "Sync to Meta" has nothing to push). Upserts by meta_retailer_id and
     * stamps them already-synced. Prices are best-effort — Meta is the source
     * of truth for the customer-facing catalog.
     */
    public function importFromMeta(Request $request): JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        abort_unless($wsId, 403);
        $catalog = WaCatalog::where('workspace_id', $wsId)->first();
        if (!$catalog) {
            return response()->json(['ok' => false, 'error' => 'No catalog connected.'], 422);
        }

        try {
            $rows = WhatsAppCatalogFactory::forCatalog($catalog)->listProducts();
        } catch (\Throwable $e) {
            \Log::warning('[wa-catalog] importFromMeta failed', ['ws' => $wsId, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $imported = 0; $updated = 0; $skipped = 0;
        foreach ($rows as $r) {
            $retailer = trim((string) ($r['retailer_id'] ?? ''));
            $name     = trim((string) ($r['name'] ?? ''));
            if ($retailer === '' || $name === '') { $skipped++; continue; }

            $priceMinor = $this->parseMetaPriceMinor($r['price'] ?? null);
            $avail      = strtolower((string) ($r['availability'] ?? ''));

            $attrs = [
                'name'             => $name,
                'description'      => $r['description'] ?? null,
                'image_url'        => $r['image_url'] ?: null,
                'product_url'      => $r['url'] ?: null,
                'currency_code'    => $r['currency'] ?: null,
                'availability'     => $avail ?: null,
                'in_stock'         => !str_contains($avail, 'out'),
                'status'           => 'active',
                'meta_retailer_id' => $retailer,
                'meta_sync_status' => 'synced',   // already lives on Meta
                'meta_synced_at'   => now(),
                'meta_last_error'  => null,
            ];
            if ($priceMinor !== null) $attrs['price_minor'] = $priceMinor;

            $existing = WaProduct::where('workspace_id', $wsId)->where('meta_retailer_id', $retailer)->first();
            if ($existing) {
                // Don't clobber a currency the operator already set with null.
                if (empty($attrs['currency_code'])) unset($attrs['currency_code']);
                $existing->update($attrs);
                $updated++;
            } else {
                WaProduct::create(array_merge([
                    'workspace_id' => $wsId,
                    'user_id'      => $request->user()->id,
                    'sku'          => $retailer,
                    'price_minor'  => $priceMinor ?? 0,
                ], $attrs));
                $imported++;
            }
        }

        \Log::info('[wa-catalog] importFromMeta done', [
            'ws' => $wsId, 'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped, 'total' => count($rows),
        ]);

        return response()->json([
            'ok'       => true,
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'total'    => count($rows),
        ]);
    }

    /** Best-effort parse of Meta's formatted price → integer minor units. */
    private function parseMetaPriceMinor($raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        if (is_int($raw)) return $raw;                 // already minor units
        // Meta formats with 2 decimal places (e.g. "$5.00", "Rp32.689.000,00");
        // stripping every non-digit yields the minor-unit integer.
        $digits = preg_replace('/\D+/', '', (string) $raw);
        return ($digits === '' || $digits === null) ? null : (int) $digits;
    }

    /**
     * AJAX endpoint — push ONE chunk of products to Meta and return.
     * Body: { product_ids?: [int,int,...]  // explicit list, else "all active" }
     * Response: { ok, pushed, has_more, total_pending, total_synced }
     */
    public function syncChunk(Request $request): JsonResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $catalog = WaCatalog::where('workspace_id', $wsId)->first();
        if (!$catalog) return response()->json(['ok' => false, 'error' => 'No catalog connected.'], 422);

        // Log EVERY sync trigger — even when there's nothing to push — so a
        // "Sync to Meta" click always leaves a trace in laravel.log.
        \Log::info('[wa-catalog] syncChunk triggered', [
            'workspace_id'    => $wsId,
            'catalog_id'      => $catalog->catalog_id,
            'active_products' => WaProduct::where('workspace_id', $wsId)->where('status', 'active')->count(),
        ]);

        $ids = $request->input('product_ids');
        $query = WaProduct::where('workspace_id', $wsId)->where('status', 'active');
        if (is_array($ids) && !empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            // Default mode: products that haven't been synced yet,
            // OR previously failed. Skip ones already 'synced'.
            $query->where(function ($q) {
                $q->whereNull('meta_sync_status')
                  ->orWhereIn('meta_sync_status', ['failed', 'pending']);
            });
        }

        $chunk = $query->orderBy('id')->limit(self::CHUNK)->get();
        if ($chunk->isEmpty()) {
            \Log::info('[wa-catalog] syncChunk nothing to push', [
                'workspace_id' => $wsId,
                'catalog_id'   => $catalog->catalog_id,
                'reason'       => (is_array($ids) && !empty($ids))
                    ? 'none of the requested product ids are active in this workspace'
                    : 'no active products are unsynced/failed — the catalog likely has 0 products (import/create products first), or all are already synced',
            ]);
            return response()->json([
                'ok' => true, 'pushed' => 0, 'has_more' => false,
                'total_pending' => 0,
                'total_synced'  => (int) WaProduct::where('workspace_id', $wsId)->where('meta_sync_status', 'synced')->count(),
            ]);
        }

        \Log::info('[wa-catalog] syncChunk push', [
            'workspace_id' => $wsId,
            'catalog_id'   => $catalog->catalog_id,
            'chunk'        => $chunk->count(),
            'mode'         => (is_array($ids) && !empty($ids)) ? 'explicit_ids' : 'unsynced+failed',
        ]);

        // Resolve a shop URL to use as the `link` field on each
        // product (Meta requires a public URL).
        $shop = WaStorefront::where('workspace_id', $wsId)->orderByDesc('id')->first();
        $shopUrl = $shop?->public_url ?? '';

        try {
            $result = WhatsAppCatalogFactory::forCatalog($catalog)
                ->upsertProductsBatch($chunk, $shopUrl);

            // Meta returns one handle per request entry, in order.
            $handles = $result['handles'] ?? [];
            DB::transaction(function () use ($chunk, $handles) {
                foreach ($chunk as $i => $p) {
                    $p->forceFill([
                        'meta_sync_status'  => 'pending',
                        'meta_batch_handle' => $handles[$i] ?? null,
                        'meta_retailer_id'  => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                        'meta_last_error'   => null,
                    ])->save();
                }
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($chunk, $e) {
                foreach ($chunk as $p) {
                    $p->forceFill([
                        'meta_sync_status' => 'failed',
                        'meta_last_error'  => mb_substr($e->getMessage(), 0, 500),
                    ])->save();
                }
            });
            \Log::warning('[wa-catalog] syncChunk push FAILED — chunk marked failed', [
                'workspace_id' => $wsId,
                'catalog_id'   => $catalog->catalog_id,
                'chunk'        => $chunk->count(),
                'error'        => $e->getMessage(),
            ]);
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
                'pushed_failed' => $chunk->count(),
            ], 502);
        }

        // Tell the client whether to call us again for the next chunk.
        $remaining = WaProduct::where('workspace_id', $wsId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('meta_sync_status')->orWhere('meta_sync_status', 'failed');
            })->count();

        return response()->json([
            'ok'            => true,
            'pushed'        => $chunk->count(),
            'has_more'      => $remaining > 0,
            'total_pending' => (int) WaProduct::where('workspace_id', $wsId)->where('meta_sync_status', 'pending')->count(),
            'total_synced'  => (int) WaProduct::where('workspace_id', $wsId)->where('meta_sync_status', 'synced')->count(),
            'total_failed'  => (int) WaProduct::where('workspace_id', $wsId)->where('meta_sync_status', 'failed')->count(),
        ]);
    }

    /**
     * AJAX endpoint — poll Meta for batch status updates and flip
     * any pending rows to synced/failed.
     */
    public function pollBatches(): JsonResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $catalog = WaCatalog::where('workspace_id', $wsId)->first();
        if (!$catalog) return response()->json(['ok' => false, 'error' => 'No catalog'], 422);

        $pending = WaProduct::where('workspace_id', $wsId)
            ->whereNotNull('meta_batch_handle')
            ->where('meta_sync_status', 'pending')
            ->get();
        if ($pending->isEmpty()) {
            return response()->json(['ok' => true, 'pending' => 0]);
        }

        $handles = $pending->pluck('meta_batch_handle')->unique()->values()->all();
        try {
            $res = WhatsAppCatalogFactory::forCatalog($catalog)->checkBatchStatus($handles);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        $byHandle = collect($res['data'] ?? [])->keyBy('handle');
        $flipped = ['synced' => 0, 'failed' => 0, 'pending' => 0];
        DB::transaction(function () use ($pending, $byHandle, &$flipped) {
            foreach ($pending as $p) {
                $row = $byHandle->get($p->meta_batch_handle);
                if (!$row) { $flipped['pending']++; continue; }
                $status = strtolower($row['status'] ?? '');
                if (in_array($status, ['finished', 'success', 'completed'], true)) {
                    $p->forceFill([
                        'meta_sync_status'  => 'synced',
                        'meta_synced_at'    => now(),
                        'meta_batch_handle' => null,
                        'meta_last_error'   => null,
                    ])->save();
                    $flipped['synced']++;
                } elseif (in_array($status, ['error', 'failed'], true)) {
                    $p->forceFill([
                        'meta_sync_status' => 'failed',
                        'meta_last_error'  => mb_substr($row['errors'][0]['message'] ?? 'unknown', 0, 500),
                    ])->save();
                    $flipped['failed']++;
                } else {
                    $flipped['pending']++;
                }
            }
        });

        return response()->json(['ok' => true] + $flipped);
    }

    public function commerceSettings(Request $request): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $catalog = WaCatalog::where('workspace_id', $wsId)->firstOrFail();

        $data = $request->validate([
            'is_catalog_visible' => ['nullable', 'boolean'],
            'is_cart_enabled'    => ['nullable', 'boolean'],
        ]);
        try {
            WhatsAppCatalogFactory::forCatalog($catalog)->setCommerceSettings(
                (bool) ($data['is_catalog_visible'] ?? false),
                (bool) ($data['is_cart_enabled']    ?? false),
            );
        } catch (WhatsAppCatalogException $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }
        return back()->with('status', 'Commerce settings updated.');
    }

    /**
     * Save catalog automation config (stored on WaCatalog.meta_json — no
     * Meta call). Drives the cart-order acknowledgement (C1) and the
     * inbound concierge (C5). Both read these keys at runtime.
     */
    public function saveAutomation(Request $request): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $catalog = WaCatalog::where('workspace_id', $wsId)->firstOrFail();

        $data = $request->validate([
            'order_ack_enabled'        => ['nullable', 'boolean'],
            'order_ack_pay_url'        => ['nullable', 'url', 'max:500'],
            'concierge_enabled'        => ['nullable', 'boolean'],
            'concierge_header'         => ['nullable', 'string', 'max:60'],
            'concierge_max'            => ['nullable', 'integer', 'min:1', 'max:30'],
            'concierge_reply_on_empty' => ['nullable', 'boolean'],
            'concierge_empty_text'     => ['nullable', 'string', 'max:1024'],
        ]);

        $meta = is_array($catalog->meta_json) ? $catalog->meta_json : [];
        $meta['order_ack_enabled']        = (bool) ($data['order_ack_enabled'] ?? false);
        $meta['order_ack_pay_url']        = $data['order_ack_pay_url'] ?? null;
        $meta['concierge_enabled']        = (bool) ($data['concierge_enabled'] ?? false);
        $meta['concierge_header']         = $data['concierge_header'] ?? null;
        $meta['concierge_max']            = (int) ($data['concierge_max'] ?? 10);
        $meta['concierge_reply_on_empty'] = (bool) ($data['concierge_reply_on_empty'] ?? false);
        $meta['concierge_empty_text']     = $data['concierge_empty_text'] ?? null;

        $catalog->forceFill(['meta_json' => $meta])->save();

        return back()->with('status', 'Automation settings saved.');
    }

    // ─── Product sets / collections ──────────────────────────────────
    // Reusable named groups of products. Saved once, fired as an MPM (or
    // reused in broadcasts/flows) without re-picking products each time.

    /** Validate + normalise the product_ids list for the current workspace. */
    private function validateSet(Request $request, int $wsId): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:500'],
            'product_ids'   => ['required', 'array', 'min:1', 'max:30'],
            'product_ids.*' => ['integer'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        // Keep only ids that actually belong to this workspace, preserving
        // the submitted order (tenant isolation + drops stale ids).
        $owned = WaProduct::where('workspace_id', $wsId)
            ->whereIn('id', $data['product_ids'])
            ->pluck('id')
            ->all();
        $ordered = array_values(array_intersect(
            array_map('intval', $data['product_ids']),
            $owned,
        ));
        abort_if(empty($ordered), 422, 'None of the selected products belong to this workspace.');
        $data['product_ids'] = $ordered;

        return $data;
    }

    public function storeSet(Request $request): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $data = $this->validateSet($request, $wsId);

        WaProductSet::create([
            'workspace_id' => $wsId,
            'user_id'      => Auth::id(),
            'name'         => $data['name'],
            'slug'         => WaProductSet::uniqueSlug($wsId, $data['name']),
            'description'  => $data['description'] ?? null,
            'product_ids'  => $data['product_ids'],
            'is_active'    => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('status', 'Collection created.');
    }

    public function updateSet(Request $request, int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $set = WaProductSet::where('workspace_id', $wsId)->findOrFail($id);
        $data = $this->validateSet($request, $wsId);

        $set->forceFill([
            'name'        => $data['name'],
            'slug'        => $set->name === $data['name'] ? $set->slug : WaProductSet::uniqueSlug($wsId, $data['name'], $set->id),
            'description' => $data['description'] ?? null,
            'product_ids' => $data['product_ids'],
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ])->save();

        return back()->with('status', 'Collection updated.');
    }

    public function deleteSet(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        WaProductSet::where('workspace_id', $wsId)->where('id', $id)->delete();

        return back()->with('status', 'Collection deleted.');
    }

    /** Collections tab — manage reusable product sets + fire them as an MPM. */
    public function collectionsPage(): Renderable|RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return redirect('/dashboard');

        return view('user.catalog.index', [
            'tab'          => 'collections',
            'catalog'      => WaCatalog::where('workspace_id', $wsId)->first(),
            'sets'         => WaProductSet::where('workspace_id', $wsId)->orderByDesc('id')->get(),
            // Active products for the picker (cap keeps the page light; the
            // Send tab's live search covers very large catalogs).
            'pickProducts' => WaProduct::where('workspace_id', $wsId)
                ->where('status', 'active')
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'sku', 'image_url', 'price_minor', 'currency_code']),
            'devices'      => \App\Models\Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')->orderByDesc('active')->get(),
            // Multi-engine: all senders across enabled engines for <x-sender-picker>.
            'senders'      => \App\Services\WorkspaceEngine::senders($wsId),
            'totalProducts' => WaProduct::where('workspace_id', $wsId)->count(),
        ]);
    }
}
