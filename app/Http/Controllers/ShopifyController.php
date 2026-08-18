<?php

namespace App\Http\Controllers;

use App\Models\ShopifyIntegration;
use App\Models\ShopifyIntegrationEvent;
use App\Models\ShopifyIntegrationLog;
use App\Models\SystemSetting;
use App\Models\WaTemplate;
use App\Services\Shopify\ShopifyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyController extends Controller
{
    public function __construct(private readonly ShopifyService $shopify) {}

    /**
     * GET /shopify — single page with tabs. If not connected, shows the
     * install/connect form. If connected, shows the dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        $integration = $wsId
            ? ShopifyIntegration::where('workspace_id', $wsId)->latest('id')->first()
            : null;

        $activeTab = $request->string('tab')->toString() ?: 'overview';
        $appEnabled = $this->shopify->isEnabled() && $this->shopify->clientId() !== '';

        $viewData = [
            'integration'   => $integration,
            'activeTab'     => $activeTab,
            'appEnabled'    => $appEnabled,
            'eventTopics'   => ShopifyService::WEBHOOK_TOPICS,
        ];

        if ($integration && $integration->isConnected()) {
            $viewData = array_merge($viewData, $this->dashboardData($integration));
        }

        return view('user.shopify.dashboard', $viewData);
    }

    /**
     * POST /shopify/connect — Validate the shop domain, store CSRF state
     * in session, redirect to Shopify's authorize endpoint.
     */
    public function startOAuth(Request $request)
    {
        $request->validate([
            'shop' => ['required', 'string', 'max:191'],
        ]);

        $shop = $this->shopify->normalizeShop($request->string('shop')->toString());
        if (!$this->shopify->isValidShop($shop)) {
            return back()->with('error', 'Enter a valid Shopify domain like my-store.myshopify.com.');
        }

        if (!$this->shopify->isEnabled() || $this->shopify->clientId() === '') {
            return back()->with('error', 'Shopify integration is not configured. Ask your admin to enable it.');
        }

        $state = Str::random(40);
        session(['shopify_oauth_state' => $state, 'shopify_oauth_shop' => $shop]);

        return redirect()->away($this->shopify->authorizeUrl($shop, $state));
    }

    /**
     * GET /shopify/oauth/callback — Verify HMAC + state, exchange code,
     * persist integration, register webhooks.
     */
    public function oauthCallback(Request $request)
    {
        // Plan: integration must be enabled on the workspace's plan.
        \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_shopify');

        $query = $request->query();

        if (!$this->shopify->verifyOAuthHmac($query)) {
            return redirect('/shopify')->with('error', 'Invalid Shopify signature. Try again.');
        }

        $sessionState = session('shopify_oauth_state');
        $sessionShop  = session('shopify_oauth_shop');
        $state = (string) $request->query('state', '');
        $shop  = (string) $request->query('shop', '');
        $code  = (string) $request->query('code', '');

        if (!$state || !$sessionState || !hash_equals((string) $sessionState, $state)) {
            return redirect('/shopify')->with('error', 'Session expired. Please reconnect.');
        }
        if (!$shop || $shop !== $sessionShop) {
            return redirect('/shopify')->with('error', 'Shop mismatch during callback.');
        }

        $exchange = $this->shopify->exchangeCode($shop, $code);
        if (!$exchange['success']) {
            return redirect('/shopify')->with('error', 'OAuth failed: ' . ($exchange['error'] ?? 'unknown'));
        }

        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) {
            return redirect('/shopify')->with('error', 'No workspace selected for this account.');
        }

        $shopData = $this->shopify->getShop($shop, $exchange['access_token'])['shop'] ?? [];

        // If a row already exists for (workspace, shop), tear down the OLD
        // Shopify-side webhook subscriptions first. The new webhook_secret
        // (issued below in updateOrCreate) will route through a different
        // URL — otherwise the old subscriptions deliver to a 404.
        $existing = ShopifyIntegration::where('workspace_id', $wsId)
            ->where('store_url', $shop)
            ->first();
        if ($existing) {
            try { $this->shopify->deleteWebhooks($existing); } catch (\Throwable $e) {}
        }

        $integration = ShopifyIntegration::updateOrCreate(
            ['workspace_id' => $wsId, 'store_url' => $shop],
            [
                'user_id'          => $user->id,
                'store_name'       => $shopData['name'] ?? $shop,
                'shop_id'          => isset($shopData['id']) ? (string) $shopData['id'] : null,
                'shop_email'       => $shopData['email'] ?? null,
                'shop_owner'       => $shopData['shop_owner'] ?? null,
                'shop_plan'        => $shopData['plan_name'] ?? null,
                'shop_currency'    => $shopData['currency'] ?? null,
                'shop_country'     => $shopData['country_name'] ?? null,
                'access_token'     => $exchange['access_token'],
                'scopes'           => $exchange['scope'] ?? '',
                'status'           => 'active',
                'webhook_secret'   => Str::random(40),
                'last_verified_at' => now(),
                'connected_at'     => now(),
            ],
        );

        try {
            $this->shopify->registerWebhooks($integration);
        } catch (\Throwable $e) {
            Log::warning('[SHOPIFY] register webhooks failed', ['error' => $e->getMessage()]);
        }

        // Initial import of products / orders / customers into our tables so
        // the dashboard, catalog send, broadcasts and automations all run on
        // real local data. Best-effort — never block the connect on it.
        try {
            app(\App\Services\Shopify\ShopifyImporter::class)->importAll($integration);
        } catch (\Throwable $e) {
            Log::warning('[SHOPIFY] initial import failed', ['error' => $e->getMessage()]);
        }

        session()->forget(['shopify_oauth_state', 'shopify_oauth_shop']);

        return redirect('/shopify?tab=overview')->with('success', 'Shopify store connected.');
    }

    /**
     * POST /shopify/{id}/sync — synchronous AJAX endpoint that re-fetches
     * counts and shop info, updating the integration row.
     */
    public function sync(int $id): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false, 'message' => 'Not found.'], 404);

        $shopResult = $this->shopify->getShop($integration->store_url, $integration->access_token);
        if (!($shopResult['success'] ?? false)) {
            $integration->update(['status' => 'error']);
            return response()->json(['ok' => false, 'message' => $shopResult['error'] ?? 'Verify failed']);
        }

        $shopData = $shopResult['shop'] ?? [];
        $integration->update([
            'store_name'       => $shopData['name'] ?? $integration->store_name,
            'shop_email'       => $shopData['email'] ?? $integration->shop_email,
            'shop_owner'       => $shopData['shop_owner'] ?? $integration->shop_owner,
            'shop_plan'        => $shopData['plan_name'] ?? $integration->shop_plan,
            'shop_currency'    => $shopData['currency'] ?? $integration->shop_currency,
            'shop_country'     => $shopData['country_name'] ?? $integration->shop_country,
            'status'           => 'active',
            'last_verified_at' => now(),
        ]);

        // Pull products / orders / customers into our tables. Best-effort —
        // a partial failure still returns the shop refresh result.
        $imported = ['products' => 0, 'orders' => 0, 'customers' => 0];
        try {
            $imported = app(\App\Services\Shopify\ShopifyImporter::class)->importAll($integration);
        } catch (\Throwable $e) {
            Log::warning('[SHOPIFY] sync import failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok'       => true,
            'imported' => $imported,
            'counts'   => $this->shopify->getStoreCounts($integration),
            'shop'     => [
                'name'    => $integration->store_name,
                'plan'    => $integration->shop_plan,
                'country' => $integration->shop_country,
            ],
        ]);
    }

    /**
     * POST /shopify/{id}/disconnect — delete webhooks then remove the row.
     * No soft-deletes on this model, so this is a hard delete; reconnecting
     * issues a fresh integration row + webhook secret.
     */
    public function disconnect(int $id)
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) abort(404);

        try { $this->shopify->deleteWebhooks($integration); } catch (\Throwable $e) {}
        $integration->delete();

        return redirect('/shopify')->with('success', 'Shopify disconnected.');
    }

    /**
     * POST /shopify/{id}/events — bulk save event → template mappings.
     */
    public function saveEvents(int $id, Request $request): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false], 404);

        $data = $request->validate([
            'events'                  => 'required|array',
            'events.*.is_active'      => 'required|boolean',
            'events.*.template_id'    => 'nullable|integer',
            'events.*.var_map'        => 'nullable|array',
            'events.*.var_map.*'      => 'nullable|string|max:40',
            'events.*.send_to'        => 'nullable|in:customer,admin,both',
            'events.*.admin_number'   => 'nullable|string|max:32',
            'events.*.delay_seconds'  => 'nullable|integer|min:0|max:86400',
        ]);

        DB::transaction(function () use ($integration, $data) {
            $allowedTypes = array_merge(ShopifyService::WEBHOOK_TOPICS, ['cod/confirm', 'cod/prepaid', 'stock/back', 'order/delivered', 'cart/step2', 'cart/step3']);
            foreach ($data['events'] as $type => $row) {
                if (!in_array($type, $allowedTypes, true)) continue;
                $varMap = array_values(array_filter((array) ($row['var_map'] ?? []), fn ($v) => $v !== null && $v !== ''));
                ShopifyIntegrationEvent::updateOrCreate(
                    ['integration_id' => $integration->id, 'event_type' => $type],
                    [
                        'is_active'     => (bool) ($row['is_active'] ?? false),
                        'template_id'   => $row['template_id'] ?: null,
                        'var_map'       => $varMap ?: null,
                        'send_to'       => $row['send_to'] ?? 'customer',
                        'admin_number'  => $row['admin_number'] ?? null,
                        'delay_seconds' => (int) ($row['delay_seconds'] ?? 0),
                    ],
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * POST /shopify/{id}/offer — send an approved template (a product offer
     * / promo) to every contact in a chosen segment, engine-aware. Reuses
     * CommerceEventNotifier; injects product + coupon into the variable
     * context. Returns sent/failed counts. Logged as an `offer/broadcast`.
     */
    public function sendOffer(int $id, Request $request): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false, 'message' => 'Not found.'], 404);

        // Plan gate — same feature flag the connect flow enforces.
        try {
            \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_shopify');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Your plan does not include the Shopify integration.'], 403);
        }

        $data = $request->validate([
            'template_id'   => 'required|integer',
            'group_id'      => 'required|string|max:64',
            'product_ids'   => 'nullable|array',
            'product_ids.*' => 'integer',
            'coupon_code'   => 'nullable|string|max:64',
        ]);

        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($data['template_id']);
        if (!$tpl) return response()->json(['ok' => false, 'message' => 'Template not found.']);

        $gid = (string) $data['group_id'];
        $contacts = \App\Models\Contact::where('workspace_id', $integration->workspace_id)
            ->where('is_unsubscribed', false)
            ->get()
            ->filter(function ($c) use ($gid) {
                $groups = is_array($c->contact_group) ? array_map('strval', $c->contact_group) : [];
                return in_array($gid, $groups, true);
            });

        if ($contacts->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'That segment has no contactable (opted-in) members.']);
        }

        $product = !empty($data['product_ids'])
            ? \App\Models\WaProduct::where('workspace_id', $integration->workspace_id)->find($data['product_ids'][0])
            : null;

        $notifier = app(\App\Services\Commerce\CommerceEventNotifier::class);
        $sent = 0; $fail = 0;
        foreach ($contacts as $c) {
            $phone = preg_replace('/\D+/', '', (string) $c->mobile);
            if ($phone === '') { $fail++; continue; }
            $ctx = $this->offerContext($integration, $product, $data['coupon_code'] ?? null, $c);
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx);
            ($r['ok'] ?? false) ? $sent++ : $fail++;
        }

        ShopifyIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => 'offer/broadcast',
            'status'         => $sent > 0 ? 'sent' : 'failed',
            'recipient'      => $contacts->count() . ' contacts',
            'payload'        => ['product' => $product?->name, 'coupon' => $data['coupon_code'] ?? null, 'template' => $tpl->template_name],
            'response'       => ['sent' => $sent, 'failed' => $fail],
            'created_at'     => now(),
        ]);

        return response()->json(['ok' => true, 'sent' => $sent, 'failed' => $fail, 'total' => $contacts->count()]);
    }

    /**
     * POST /shopify/{id}/winback — re-engage lapsed customers: everyone
     * whose most-recent order is older than N days. One-click (no cron):
     * the merchant runs it on demand. Engine-aware, coupon-aware, skips
     * opted-out contacts.
     */
    public function sendWinback(int $id, Request $request): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false, 'message' => 'Not found.'], 404);
        try {
            \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_shopify');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Your plan does not include the Shopify integration.'], 403);
        }

        $data = $request->validate([
            'template_id' => 'required|integer',
            'days'        => 'nullable|integer|min:0|max:365',   // 0 = any time (pure segment, no recency)
            'min_orders'  => 'nullable|integer|min:0|max:1000',
            'min_spent'   => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:64',
        ]);
        $days      = (int) ($data['days'] ?? 60);
        $minOrders = (int) ($data['min_orders'] ?? 0);
        $minSpent  = (float) ($data['min_spent'] ?? 0);
        $tpl  = WaTemplate::where('workspace_id', $integration->workspace_id)->find($data['template_id']);
        if (!$tpl) return response()->json(['ok' => false, 'message' => 'Template not found.']);

        // Smart segment from order history: recency (lapsed) + min orders + min spend.
        $q = \App\Models\WaOrder::where('workspace_id', $integration->workspace_id)
            ->whereNotNull('customer_phone')->where('customer_phone', '!=', '')
            ->groupBy('customer_phone')
            ->selectRaw('customer_phone, COUNT(*) as o, SUM(total_minor) as s, MAX(created_at) as last_order');
        if ($days > 0)      $q->havingRaw('MAX(created_at) < ?', [now()->subDays($days)]);
        if ($minOrders > 0) $q->havingRaw('COUNT(*) >= ?', [$minOrders]);
        if ($minSpent > 0)  $q->havingRaw('SUM(total_minor) >= ?', [(int) round($minSpent * 100)]);
        $phones = $q->pluck('customer_phone');

        if ($phones->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'No customers match this segment.']);
        }

        // Drop opted-out numbers.
        $optedOut = \App\Models\Contact::where('workspace_id', $integration->workspace_id)
            ->where('is_unsubscribed', true)->get(['mobile'])
            ->map(fn ($c) => preg_replace('/\D+/', '', (string) $c->mobile))->filter()->all();

        $notifier = app(\App\Services\Commerce\CommerceEventNotifier::class);
        $sent = 0; $fail = 0;
        foreach ($phones->unique() as $phone) {
            $digits = preg_replace('/\D+/', '', (string) $phone);
            if ($digits === '' || in_array($digits, $optedOut, true)) { continue; }
            $ctx = [
                'name' => 'there', 'coupon_code' => (string) ($data['coupon_code'] ?? ''),
                'store_name' => (string) ($integration->store_name ?: $integration->store_url),
                '_positional' => ['there', $integration->store_name ?: $integration->store_url, $data['coupon_code'] ?? ''],
            ];
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $digits, $tpl, $ctx);
            ($r['ok'] ?? false) ? $sent++ : $fail++;
        }

        ShopifyIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => 'winback/broadcast',
            'status'         => $sent > 0 ? 'sent' : 'failed',
            'recipient'      => $phones->count() . ' lapsed (>' . $days . 'd)',
            'payload'        => ['days' => $days, 'coupon' => $data['coupon_code'] ?? null, 'template' => $tpl->template_name],
            'response'       => ['sent' => $sent, 'failed' => $fail],
            'created_at'     => now(),
        ]);

        return response()->json(['ok' => true, 'sent' => $sent, 'failed' => $fail, 'total' => $phones->count()]);
    }

    /**
     * Variable context for an offer broadcast — product + coupon + contact.
     * Positional default: [customer name, product name, coupon/price].
     */
    private function offerContext(ShopifyIntegration $integration, $product, ?string $coupon, $contact): array
    {
        $name     = trim((string) ($contact->first_name ?: $contact->name ?: 'there'));
        $currency = $integration->shop_currency ?: '';
        $priceFmt = $product ? trim(number_format($product->price_minor / 100, 2) . ' ' . $currency) : '';

        return [
            'name'         => $name,
            'first_name'   => $name,
            'product_name' => $product?->name ?? '',
            'price'        => $priceFmt,
            'product_url'  => $product?->product_url ?? '',
            'coupon_code'  => (string) ($coupon ?? ''),
            'store_name'   => (string) ($integration->store_name ?: $integration->store_url),
            'currency'     => $currency,
            '_positional'  => [$name, $product?->name ?? '', $coupon ?: $priceFmt],
        ];
    }

    /**
     * POST /shopify/webhook/{secret} — Shopify webhook receiver.
     *
     * We verify the X-Shopify-Hmac-SHA256 header before parsing.
     * No queues per project rule — we log and return 200 fast; the
     * actual messaging dispatch happens inline. If a template isn't
     * configured for the event we still log it as 'skipped'.
     */
    public function webhook(string $secret, Request $request): Response
    {
        $integration = ShopifyIntegration::where('webhook_secret', $secret)->first();
        if (!$integration) return response('not found', 404);

        $payload = $request->getContent();
        $hmac    = (string) $request->header('X-Shopify-Hmac-SHA256', '');
        if (!$this->shopify->verifyWebhookSignature($payload, $hmac)) {
            return response('bad hmac', 401);
        }

        $topic = (string) $request->header('X-Shopify-Topic', '');
        $data  = json_decode($payload, true) ?: [];

        $event = ShopifyIntegrationEvent::where('integration_id', $integration->id)
            ->where('event_type', $topic)
            ->first();

        $shouldSend = $event && $event->is_active && $event->template_id;

        $log = ShopifyIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => $topic,
            'status'         => $shouldSend ? 'processed' : 'skipped',
            'recipient'      => $this->resolveRecipient($data),
            'payload'        => $data,
            'created_at'     => now(),
        ]);

        // Fire the configured WhatsApp template — engine-aware (Unofficial
        // API / WABA / Twilio). Wrapped so a send failure NEVER makes
        // Shopify retry the webhook (which would duplicate the log + send).
        if ($shouldSend) {
            try {
                $this->dispatchEventMessage($integration, $event, $data, $log);
            } catch (\Throwable $e) {
                Log::warning('[Shopify-webhook] dispatch crashed (swallowed): ' . $e->getMessage());
                $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            }
        }

        // Back-in-stock — detect an out→in transition BEFORE the mirror
        // upsert overwrites the previous stock state, and message anyone
        // on the waitlist for this product.
        if ($topic === 'products/update' && !empty($data['id'])) {
            try {
                app(\App\Services\Shopify\ShopifyStockService::class)->handleProductUpdate($integration, $data);
            } catch (\Throwable $e) {
                \Log::warning('[Shopify-webhook] back-in-stock failed (swallowed): ' . $e->getMessage());
            }
        }

        // Keep our local mirror in sync as Shopify changes. Best-effort,
        // wrapped so a mapping bug never makes Shopify retry the webhook.
        try {
            $importer = app(\App\Services\Shopify\ShopifyImporter::class);
            if ($topic === 'products/update' && !empty($data['id'])) {
                $importer->upsertProduct($integration, $data);
            } elseif (in_array($topic, ['orders/create', 'orders/updated', 'orders/paid', 'orders/fulfilled', 'orders/cancelled'], true) && !empty($data['id'])) {
                $importer->upsertOrder($integration, $data);
            }
        } catch (\Throwable $e) {
            \Log::warning('[Shopify-webhook] local mirror failed (swallowed): ' . $e->getMessage());
        }

        // COD double-confirmation — on a new cash-on-delivery order, if the
        // merchant has the COD automation active, message the customer to
        // confirm (Yes/No) and open a pending tracking row.
        if ($topic === 'orders/create' && \App\Services\Shopify\ShopifyCodService::isCodOrder($data)) {
            try {
                $codEvent = ShopifyIntegrationEvent::where('integration_id', $integration->id)
                    ->where('event_type', 'cod/confirm')->where('is_active', true)->first();
                if ($codEvent && $codEvent->template_id) {
                    app(\App\Services\Shopify\ShopifyCodService::class)->sendConfirmation($integration, $codEvent, $data);
                }
                // COD → Prepaid nudge: offer to pay online now (template uses
                // {{order_url}} + a discount the merchant bakes in).
                $prepaid = ShopifyIntegrationEvent::where('integration_id', $integration->id)
                    ->where('event_type', 'cod/prepaid')->where('is_active', true)->first();
                if ($prepaid && $prepaid->template_id) {
                    $this->sendPseudoEvent($integration, $prepaid, $this->resolveRecipient($data), $this->orderContext($integration, $data), 'cod/prepaid');
                }
            } catch (\Throwable $e) {
                \Log::warning('[Shopify-webhook] COD confirm/prepaid failed (swallowed): ' . $e->getMessage());
            }
        }

        // Delivered — fulfillments/update carries shipment_status. When the
        // courier marks it delivered, fire the Delivered automation (resolve
        // the customer from our mirrored order).
        if ($topic === 'fulfillments/update' && strtolower((string) ($data['shipment_status'] ?? '')) === 'delivered') {
            try {
                $dEvent = ShopifyIntegrationEvent::where('integration_id', $integration->id)
                    ->where('event_type', 'order/delivered')->where('is_active', true)->first();
                if ($dEvent && $dEvent->template_id) {
                    $waOrder = \App\Models\WaOrder::where('workspace_id', $integration->workspace_id)
                        ->where('shopify_order_id', (string) ($data['order_id'] ?? ''))->first();
                    $phone = $waOrder?->customer_phone ?: ($data['destination']['phone'] ?? null);
                    $name  = $waOrder?->customer_name ?: 'there';
                    $oName = $waOrder?->meta_json['name'] ?? ('#' . ($data['order_id'] ?? ''));
                    $ctx = [
                        'name' => $name, 'first_name' => $name, 'order_name' => $oName,
                        'store_name' => (string) ($integration->store_name ?: $integration->store_url),
                        '_positional' => [$name, $oName, ''],
                    ];
                    $this->sendPseudoEvent($integration, $dEvent, $phone, $ctx, 'order/delivered');
                }
            } catch (\Throwable $e) {
                \Log::warning('[Shopify-webhook] delivered failed (swallowed): ' . $e->getMessage());
            }
        }

        // Abandoned-cart recovery — schedule the delayed follow-up steps on
        // a new checkout; cancel them the moment the order is placed/paid.
        try {
            $cart = app(\App\Services\Shopify\ShopifyCartService::class);
            if ($topic === 'checkouts/create') {
                $cart->scheduleSequence($integration, $data);
            } elseif (in_array($topic, ['orders/create', 'orders/paid'], true)) {
                $cart->cancelOnOrder($integration, $data);
            }
        } catch (\Throwable $e) {
            \Log::warning('[Shopify-webhook] cart recovery failed (swallowed): ' . $e->getMessage());
        }

        // Commerce-flow loop closer — orders created via the flow
        // builder's commerce node carry the flow session in the cart
        // `note` (we set it on cartCreate / draft_order). Resolve it
        // and ping Node to advance through the `purchased` port.
        // Wrapped — a resolver bug must NEVER trigger Shopify to retry
        // the webhook (which would duplicate the log we just wrote).
        if (in_array($topic, ['orders/create', 'orders/paid'], true)) {
            try {
                \App\Services\Commerce\FlowSessionResolver::resumeFromShopifyOrder($data);
            } catch (\Throwable $e) {
                \Log::warning('[Shopify-webhook] flow-resume crashed (swallowed): ' . $e->getMessage());
            }
        }

        // Merchant uninstalled the app → revoke + clear our copy of
        // the access token. The integration row stays so its logs +
        // event map remain visible in /shopify, but isConnected() now
        // returns false (no access_token) so we stop making API calls.
        if ($topic === 'app/uninstalled') {
            try {
                $integration->update([
                    'access_token' => null,
                    'connected_at' => null,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('[Shopify-webhook] uninstall cleanup failed: ' . $e->getMessage());
            }
        }

        return response('ok', 200);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function ownedIntegration(int $id): ?ShopifyIntegration
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) return null;
        return ShopifyIntegration::where('workspace_id', $wsId)->find($id);
    }

    private function resolveRecipient(array $data): ?string
    {
        return $data['customer']['phone']
            ?? $data['phone']
            ?? $data['shipping_address']['phone']
            ?? $data['billing_address']['phone']
            ?? null;
    }

    /**
     * Send the configured template for a fired event to the customer
     * and/or the merchant's admin number, then record the outcome on the
     * log row. delay_seconds is not honoured inline (a webhook must return
     * fast) — the send fires immediately; scheduled delays are a future
     * enhancement via the Node scheduler.
     */
    private function dispatchEventMessage(
        ShopifyIntegration $integration,
        ShopifyIntegrationEvent $event,
        array $data,
        ShopifyIntegrationLog $log
    ): void {
        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)
            ->find($event->template_id);
        if (!$tpl) {
            $log->update(['status' => 'failed', 'error' => 'Configured template no longer exists.']);
            return;
        }

        $ctx       = $this->orderContext($integration, $data);

        // Per-event variable mapping wins over the positional default:
        // var_map is an ordered list of order fields → {{1}}/{{2}}/… params.
        if (is_array($event->var_map) && $event->var_map) {
            $ctx['_positional'] = array_map(fn ($field) => (string) ($ctx[$field] ?? ''), $event->var_map);
        }

        $sendTo     = $event->send_to ?: 'customer';
        $notifier  = app(\App\Services\Commerce\CommerceEventNotifier::class);

        $targets = [];
        if (in_array($sendTo, ['customer', 'both'], true)) {
            $customer = $this->resolveRecipient($data);
            if ($customer) $targets['customer'] = $customer;
        }
        if (in_array($sendTo, ['admin', 'both'], true) && $event->admin_number) {
            $targets['admin'] = $event->admin_number;
        }

        if (empty($targets)) {
            $log->update(['status' => 'failed', 'error' => 'No recipient — order has no customer phone' . ($sendTo === 'admin' ? '' : ' and no admin number set') . '.']);
            return;
        }

        $results  = [];
        $anyOk     = false;
        foreach ($targets as $who => $number) {
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $number, $tpl, $ctx);
            $results[$who] = $r;
            $anyOk = $anyOk || ($r['ok'] ?? false);
        }

        $errors = collect($results)
            ->filter(fn ($r) => !($r['ok'] ?? false))
            ->map(fn ($r, $who) => $who . ': ' . ($r['error'] ?? 'failed'))
            ->values()->all();

        $log->update([
            'status'    => $anyOk ? 'sent' : 'failed',
            'recipient' => implode(', ', array_values($targets)),
            'response'  => $results,
            'error'     => $errors ? implode(' | ', $errors) : null,
        ]);
    }

    /**
     * Flatten a Shopify order/customer webhook payload into the named +
     * positional variable map CommerceEventNotifier substitutes into the
     * template. Positional default order is [customer name, order number,
     * total] — the common "Hi {{1}}, order {{2}} for {{3}} confirmed" shape.
     */

    /**
     * Fire a configured automation that isn't a 1:1 webhook topic
     * (cod/prepaid, order/delivered) — send the template to one recipient
     * with a prebuilt context, honour the event's var_map, and log it.
     */
    private function sendPseudoEvent(ShopifyIntegration $integration, ShopifyIntegrationEvent $event, ?string $phone, array $ctx, string $logType): void
    {
        $phone = $phone ? preg_replace('/\D+/', '', (string) $phone) : '';
        if ($phone === '') return;
        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return;
        if (is_array($event->var_map) && $event->var_map) {
            $ctx['_positional'] = array_map(fn ($f) => (string) ($ctx[$f] ?? ''), $event->var_map);
        }
        $r = app(\App\Services\Commerce\CommerceEventNotifier::class)
            ->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx);
        ShopifyIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => $logType,
            'status'         => ($r['ok'] ?? false) ? 'sent' : 'failed',
            'recipient'      => $phone,
            'payload'        => ['order' => $ctx['order_name'] ?? null],
            'response'       => $r,
            'error'          => ($r['ok'] ?? false) ? null : ($r['error'] ?? null),
            'created_at'     => now(),
        ]);
    }

    private function orderContext(ShopifyIntegration $integration, array $data): array
    {
        $cust      = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $first     = trim((string) ($cust['first_name'] ?? ($data['first_name'] ?? '')));
        $last      = trim((string) ($cust['last_name']  ?? ($data['last_name']  ?? '')));
        $name      = trim($first . ' ' . $last) ?: ($data['name'] ?? 'there');
        $orderName = (string) ($data['name'] ?? ('#' . ($data['order_number'] ?? $data['id'] ?? '')));
        $currency  = (string) ($data['currency'] ?? $integration->shop_currency ?? '');
        $total     = (string) ($data['total_price'] ?? '');
        $totalFmt  = $total !== '' ? trim($total . ' ' . $currency) : '';

        // Fulfilment / tracking — for the Shipped + Delivered automations.
        $ful      = is_array($data['fulfillments'][0] ?? null) ? $data['fulfillments'][0] : [];
        $orderUrl = (string) ($data['order_status_url'] ?? '');

        return [
            'name'         => $name,
            'first_name'   => $first ?: $name,
            'last_name'    => $last,
            'order_number' => (string) ($data['order_number'] ?? $data['id'] ?? ''),
            'order_name'   => $orderName,
            'total'        => $totalFmt,
            'total_price'  => $total,
            'currency'     => $currency,
            'email'        => (string) ($cust['email'] ?? $data['email'] ?? ''),
            'store_name'   => (string) ($integration->store_name ?: $integration->store_url),
            'financial_status'    => (string) ($data['financial_status'] ?? ''),
            'fulfillment_status'  => (string) ($data['fulfillment_status'] ?? ''),
            // Tracking + order URL — usable as {{tracking_url}}, {{tracking_number}},
            // {{tracking_company}}, {{order_url}} in Shipped/Delivered/Prepaid templates.
            'tracking_url'     => (string) ($ful['tracking_url'] ?? ($ful['tracking_urls'][0] ?? $orderUrl)),
            'tracking_number'  => (string) ($ful['tracking_number'] ?? ''),
            'tracking_company' => (string) ($ful['tracking_company'] ?? ''),
            'order_url'        => $orderUrl,
            'checkout_url'     => (string) ($data['abandoned_checkout_url'] ?? $orderUrl),
            // Positional fallback for numeric {{1}}/{{2}}/{{3}} templates.
            '_positional'  => [$name, $orderName, $totalFmt],
        ];
    }

    private function dashboardData(ShopifyIntegration $integration): array
    {
        // DB-driven — everything is served from our own mirrored tables
        // (populated by ShopifyImporter on connect / sync / webhook), so the
        // page is fast and works offline of the live API. Shopify API shapes
        // are reproduced so the existing tab markup keeps working unchanged.
        $wsId = $integration->workspace_id;

        $productModels = \App\Models\WaProduct::where('workspace_id', $wsId)
            ->orderByDesc('id')->limit(120)->get();

        $mapProduct = function ($p) {
            $price   = $p->price_minor / 100;
            $compare = $p->compare_price_minor ? $p->compare_price_minor / 100 : null;
            $off     = ($compare && $compare > $price) ? (int) round((1 - $price / $compare) * 100) : 0;
            return [
                'id'            => $p->id,
                'title'         => $p->name,
                'handle'        => $p->slug,
                'vendor'        => $p->brand,
                'product_type'  => $p->category,
                'status'        => $p->status,
                'images'        => $p->image_url ? [['src' => $p->image_url]] : [],
                'image_url'     => $p->image_url,
                'product_url'   => $p->product_url,
                'price'         => $price,
                'compare_price' => $compare,
                'discount_pct'  => $off,
                'in_stock'      => (bool) $p->in_stock,
                'variants'      => [['price' => number_format($price, 2, '.', ''), 'compare_at_price' => $compare ? number_format($compare, 2, '.', '') : null]],
            ];
        };

        $products    = $productModels->map($mapProduct)->values()->all();
        $offers      = $productModels->filter(fn ($p) => $p->compare_price_minor > $p->price_minor)->map($mapProduct)->values()->take(8)->all();
        $newArrivals = $productModels->take(8)->map($mapProduct)->values()->all();
        $popular     = $productModels->sortByDesc('price_minor')->map($mapProduct)->values()->take(8)->all();

        $orderModels = \App\Models\WaOrder::where('workspace_id', $wsId)
            ->orderByDesc('id')->limit(20)->get();
        $orders = $orderModels->map(fn ($o) => [
            'name'               => $o->meta_json['name'] ?? ('#' . $o->id),
            'order_number'       => $o->meta_json['order_number'] ?? $o->id,
            'customer'           => ['first_name' => $o->customer_name, 'last_name' => ''],
            'email'              => $o->customer_email,
            'phone'              => $o->customer_phone,
            'total_price'        => number_format($o->total_minor / 100, 2, '.', ''),
            'currency'           => $o->currency_code,
            'financial_status'   => $o->meta_json['financial_status'] ?? $o->status,
            'fulfillment_status' => $o->meta_json['fulfillment_status'] ?? null,
            'created_at'         => $o->created_at,
        ])->all();

        $customerModels = \App\Models\Contact::where('workspace_id', $wsId)
            ->orderByDesc('id')->limit(50)->get();
        $customers = $customerModels->map(fn ($c) => [
            'first_name'   => $c->first_name ?: $c->name,
            'last_name'    => $c->last_name,
            'email'        => $c->email,
            'phone'        => $c->mobile,
            'orders_count' => (int) (is_array($c->custom_attributes) ? ($c->custom_attributes['orders_count'] ?? 0) : 0),
            'total_spent'  => (float) (is_array($c->custom_attributes) ? ($c->custom_attributes['total_spent'] ?? 0) : 0),
        ])->all();

        $counts = [
            'products'  => \App\Models\WaProduct::where('workspace_id', $wsId)->count(),
            'orders'    => \App\Models\WaOrder::where('workspace_id', $wsId)->count(),
            'customers' => $customerModels->count(),
        ];

        $logs = ShopifyIntegrationLog::where('integration_id', $integration->id);
        $logTotal     = (clone $logs)->count();
        $logsByStatus = (clone $logs)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->toArray();
        $logsByEvent  = (clone $logs)
            ->selectRaw('event_type, COUNT(*) as n')
            ->groupBy('event_type')
            ->pluck('n', 'event_type')
            ->toArray();
        $recentLogs = (clone $logs)
            ->latest('created_at')
            ->limit(15)
            ->get();

        $eventsByType = ShopifyIntegrationEvent::where('integration_id', $integration->id)
            ->get()
            ->keyBy('event_type');

        $activeEvents = $eventsByType->where('is_active', true)->count();

        $templates = WaTemplate::query()
            ->where('workspace_id', $integration->workspace_id)
            ->approved()
            ->with('provider')
            ->orderBy('template_name')
            ->get(['id', 'template_name', 'category', 'language', 'template_body', 'channel', 'provider_config_id', 'meta_template_id', 'twilio_content_sid']);

        // For the per-event variable-mapping UI: how many positional
        // {{1}}/{{2}}/… params each template's body declares. 0 = the
        // template uses only named tokens (no mapping needed).
        $templateParamCounts = $templates->mapWithKeys(function ($t) {
            $max = 0;
            if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) $t->template_body, $m)) {
                foreach ($m[1] as $n) $max = max($max, (int) $n);
            }
            return [$t->id => $max];
        })->toArray();

        // ---- Analytics (all DB-driven) ----
        $revenueTotal = (int) \App\Models\WaOrder::where('workspace_id', $wsId)->sum('total_minor');
        $ordersTotal  = $counts['orders'];
        $messagesSent = ShopifyIntegrationLog::where('integration_id', $integration->id)->where('status', 'sent')->count();
        $offersSent   = ShopifyIntegrationLog::where('integration_id', $integration->id)->where('event_type', 'offer/broadcast')->count();

        // 14-day revenue trend from wa_orders.
        $since = now()->subDays(13)->startOfDay();
        $byDay = \App\Models\WaOrder::where('workspace_id', $wsId)
            ->where('created_at', '>=', $since)
            ->get(['total_minor', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->format('Y-m-d'))
            ->map(fn ($g) => $g->sum('total_minor') / 100);
        $trend = [];
        for ($d = 0; $d < 14; $d++) {
            $key = now()->subDays(13 - $d)->format('Y-m-d');
            $trend[] = ['label' => now()->subDays(13 - $d)->format('M j'), 'value' => (float) ($byDay[$key] ?? 0)];
        }

        // Impact / ROI — real, attributable numbers (no fabricated revenue).
        $aov          = $ordersTotal ? ($revenueTotal / 100 / $ordersTotal) : 0;
        $codConfirmed = \App\Models\ShopifyCodConfirmation::where('workspace_id', $wsId)->where('status', 'confirmed')->count();
        $codCancelled = \App\Models\ShopifyCodConfirmation::where('workspace_id', $wsId)->where('status', 'cancelled')->count();
        $recoverySends = ShopifyIntegrationLog::where('integration_id', $integration->id)
            ->whereIn('event_type', ['offer/broadcast', 'winback/broadcast', 'checkouts/create', 'cod/confirm', 'cod/prepaid', 'stock/back'])
            ->where('status', 'sent')->count();

        $analytics = [
            'revenue_total' => $revenueTotal / 100,
            'orders_total'  => $ordersTotal,
            'aov'           => $aov,
            'messages_sent' => $messagesSent,
            'offers_sent'   => $offersSent,
            'trend'         => $trend,
            'trend_max'     => max(1, collect($trend)->max('value')),
            // Impact: COD confirmations protect revenue; cancellations are RTO avoided.
            'cod_confirmed' => $codConfirmed,
            'cod_cancelled' => $codCancelled,
            'cod_protected' => $codConfirmed * $aov,
            'rto_avoided'   => $codCancelled * $aov,
            'recovery_sends'=> $recoverySends,
        ];

        // Offer-composer pickers: contact groups (segments) + active coupons.
        $contactGroups = \App\Models\ContactGroup::where('workspace_id', $wsId)->get(['id', 'user_group', 'color']);
        $coupons = \App\Models\Coupon::where('is_active', true)->orderBy('code')->limit(100)->get(['id', 'code', 'type', 'amount']);

        $revenue30d = collect($orders)->sum(fn ($o) => (float) ($o['total_price'] ?? 0));
        $currency   = $integration->shop_currency ?: ($orders[0]['currency'] ?? 'USD');

        return [
            'analytics'     => $analytics,
            'contactGroups' => $contactGroups,
            'coupons'       => $coupons,
            'counts'        => $counts,
            'orders'        => $orders,
            'products'      => $products,
            'customers'     => $customers,
            'logTotal'      => $logTotal,
            'logsByStatus'  => $logsByStatus,
            'logsByEvent'   => $logsByEvent,
            'recentLogs'    => $recentLogs,
            'eventsByType'  => $eventsByType,
            'activeEvents'  => $activeEvents,
            'offers'        => $offers,
            'newArrivals'   => $newArrivals,
            'popular'       => $popular,
            'templates'           => $templates,
            'templateParamCounts' => $templateParamCounts,
            'revenue30d'    => $revenue30d,
            'currency'      => $currency,
        ];
    }

}
