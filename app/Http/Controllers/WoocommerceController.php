<?php

namespace App\Http\Controllers;

use App\Models\WaTemplate;
use App\Models\WoocommerceIntegration;
use App\Models\WoocommerceIntegrationEvent;
use App\Models\WoocommerceIntegrationLog;
use App\Services\Woocommerce\WoocommerceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WoocommerceController extends Controller
{
    public function __construct(private readonly WoocommerceService $woo) {}

    /**
     * GET /woocommerce — connect screen when no integration exists,
     * dashboard otherwise. Tabs mirror the Shopify controller.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        $integration = $wsId
            ? WoocommerceIntegration::where('workspace_id', $wsId)->latest('id')->first()
            : null;

        $activeTab = $request->string('tab')->toString() ?: 'overview';
        $appEnabled = $this->woo->isEnabled();

        $viewData = [
            'integration' => $integration,
            'activeTab'   => $activeTab,
            'appEnabled'  => $appEnabled,
            'eventTopics' => WoocommerceService::WEBHOOK_TOPICS,
        ];

        if ($integration && $integration->isConnected()) {
            $viewData = array_merge($viewData, $this->dashboardData($integration));
        }

        return view('user.woocommerce.dashboard', $viewData);
    }

    /**
     * POST /woocommerce/connect — test credentials, persist on success,
     * register webhooks. Inline AJAX (no queue) per project policy.
     */
    public function connect(Request $request): JsonResponse
    {
        // Plan: integration must be enabled on the workspace's plan.
        \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_woocommerce');

        $data = $request->validate([
            'store_url'       => 'required|string|max:255',
            'consumer_key'    => 'required|string|max:191',
            'consumer_secret' => 'required|string|max:191',
        ]);

        if (!$this->woo->isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'WooCommerce is disabled. Ask an admin to enable it.'], 422);
        }

        $url = $this->woo->normalizeUrl($data['store_url']);
        if (!$this->woo->isValidUrl($url)) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid store URL (e.g. https://your-store.com).'], 422);
        }

        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) {
            return response()->json(['ok' => false, 'message' => 'No workspace selected.'], 422);
        }

        // 1. Verify the credentials against the live store.
        $probe = $this->woo->testConnection($url, $data['consumer_key'], $data['consumer_secret']);
        if (!$probe['ok']) {
            return response()->json(['ok' => false, 'message' => 'Could not reach WooCommerce: ' . ($probe['error'] ?? 'unknown error')], 422);
        }

        // 2. If we're reconnecting an existing row, tear down the OLD
        //    webhooks on the WC side first — the new webhook_secret will
        //    invalidate their HMAC signatures otherwise, leaving zombie
        //    subscriptions that fail forever on the merchant's side.
        $existing = WoocommerceIntegration::where('workspace_id', $wsId)
            ->where('store_url', $url)
            ->first();
        if ($existing) {
            // Use the existing creds for delete — the new ones haven't
            // been persisted yet, but we still have authorization to
            // call DELETE /webhooks/{id} on the merchant's store.
            try { $this->woo->deleteWebhooks($existing); } catch (\Throwable $e) {}
        }

        // 3. Persist. Reusing the existing row when the same workspace
        //    reconnects the same store — preserves event mappings + logs.
        $integration = WoocommerceIntegration::updateOrCreate(
            ['workspace_id' => $wsId, 'store_url' => $url],
            [
                'user_id'          => $user->id,
                'store_name'       => $probe['store']['name'] ?? parse_url($url, PHP_URL_HOST),
                'store_currency'   => $probe['store']['currency'] ?? null,
                'store_country'    => $probe['store']['country'] ?? null,
                'store_version'    => $probe['store']['wc_version'] ?? null,
                'consumer_key'     => $data['consumer_key'],
                'consumer_secret'  => $data['consumer_secret'],
                'status'           => 'active',
                'webhook_secret'   => Str::random(40),
                'last_verified_at' => now(),
                'connected_at'     => now(),
            ],
        );

        // 4. Register webhooks. Failures get logged but don't roll back
        //    the connection itself — the user can re-register later.
        try {
            $this->woo->registerWebhooks($integration);
        } catch (\Throwable $e) {
            Log::warning('[WC] register webhooks failed', ['error' => $e->getMessage()]);
        }

        // 5. Import the store into our local mirror (products/orders/customers)
        //    so the CRM surfaces — offers, win-back, analytics — have real data
        //    immediately. Best-effort; the merchant can re-sync any time.
        try {
            app(\App\Services\Woocommerce\WoocommerceImporter::class)->importAll($integration);
        } catch (\Throwable $e) {
            Log::warning('[WC] initial import failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'redirect' => url('/woocommerce?tab=overview'),
        ]);
    }

    /** POST /woocommerce/test — non-persisting credential check used by the form. */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_url'       => 'required|string|max:255',
            'consumer_key'    => 'required|string|max:191',
            'consumer_secret' => 'required|string|max:191',
        ]);
        $url = $this->woo->normalizeUrl($data['store_url']);
        if (!$this->woo->isValidUrl($url)) {
            return response()->json(['ok' => false, 'message' => 'Invalid URL.']);
        }
        $r = $this->woo->testConnection($url, $data['consumer_key'], $data['consumer_secret']);
        return response()->json($r['ok']
            ? ['ok' => true, 'message' => 'Connection verified. Store: ' . ($r['store']['name'] ?? $url)]
            : ['ok' => false, 'message' => $r['error'] ?? 'Connection failed.']);
    }

    public function sync(int $id): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false], 404);

        $r = $this->woo->testConnection($integration->store_url, $integration->consumer_key, $integration->consumer_secret);
        if (!$r['ok']) {
            $integration->update(['status' => 'error']);
            return response()->json(['ok' => false, 'message' => $r['error'] ?? 'Verify failed.']);
        }
        $integration->update([
            'store_name'       => $r['store']['name'] ?? $integration->store_name,
            'store_currency'   => $r['store']['currency'] ?? $integration->store_currency,
            'store_country'    => $r['store']['country'] ?? $integration->store_country,
            'store_version'    => $r['store']['wc_version'] ?? $integration->store_version,
            'status'           => 'active',
            'last_verified_at' => now(),
        ]);

        // Re-import into the local mirror on every manual sync.
        try {
            app(\App\Services\Woocommerce\WoocommerceImporter::class)->importAll($integration);
        } catch (\Throwable $e) {
            Log::warning('[WC] sync import failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'counts' => $this->woo->getStoreCounts($integration),
        ]);
    }

    public function disconnect(int $id)
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) abort(404);
        try { $this->woo->deleteWebhooks($integration); } catch (\Throwable $e) {}
        $integration->delete();
        return redirect('/woocommerce')->with('success', 'WooCommerce disconnected.');
    }

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
            'sender_key'              => 'nullable|string|max:64',
        ]);

        // Persist the chosen "Send from" sender (engine:id) on the integration —
        // blank = Auto (dispatcher picks). Stored in metadata (no schema change).
        $meta = (array) ($integration->metadata ?? []);
        $meta['sender_key'] = (string) ($data['sender_key'] ?? '');
        $integration->update(['metadata' => $meta]);

        DB::transaction(function () use ($integration, $data) {
            $allowed = self::automationTypes();
            foreach ($data['events'] as $type => $row) {
                if (!in_array($type, $allowed, true)) continue;
                $varMap = array_values(array_filter((array) ($row['var_map'] ?? []), fn ($v) => $v !== null && $v !== ''));
                WoocommerceIntegrationEvent::updateOrCreate(
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
     * POST /woocommerce/{id}/offer — send an approved template (product offer /
     * promo) to every opted-in contact in a chosen segment, engine-aware.
     * Reuses CommerceEventNotifier; injects product + coupon. Logs offer/broadcast.
     */
    public function sendOffer(int $id, Request $request): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false, 'message' => 'Not found.'], 404);

        try {
            \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_woocommerce');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Your plan does not include the WooCommerce integration.'], 403);
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
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx, (string) ($integration->metadata["sender_key"] ?? "") ?: null);
            ($r['ok'] ?? false) ? $sent++ : $fail++;
        }

        WoocommerceIntegrationLog::create([
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
     * POST /woocommerce/{id}/winback — smart-segment broadcast over the
     * mirrored WooCommerce order history: recency (lapsed) + min orders +
     * min spend. One-click, engine-aware, coupon-aware, skips opt-outs.
     */
    public function sendWinback(int $id, Request $request): JsonResponse
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) return response()->json(['ok' => false, 'message' => 'Not found.'], 404);
        try {
            \App\Services\PlanLimitGuard::feature($request->user()?->currentWorkspace, 'integration_woocommerce');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Your plan does not include the WooCommerce integration.'], 403);
        }

        $data = $request->validate([
            'template_id' => 'required|integer',
            'days'        => 'nullable|integer|min:0|max:365',
            'min_orders'  => 'nullable|integer|min:0|max:1000',
            'min_spent'   => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:64',
        ]);
        $days      = (int) ($data['days'] ?? 60);
        $minOrders = (int) ($data['min_orders'] ?? 0);
        $minSpent  = (float) ($data['min_spent'] ?? 0);
        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($data['template_id']);
        if (!$tpl) return response()->json(['ok' => false, 'message' => 'Template not found.']);

        $q = \App\Models\WaOrder::where('workspace_id', $integration->workspace_id)
            ->where('source', 'woocommerce')
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

        $optedOut = \App\Models\Contact::where('workspace_id', $integration->workspace_id)
            ->where('is_unsubscribed', true)->get(['mobile'])
            ->map(fn ($c) => preg_replace('/\D+/', '', (string) $c->mobile))->filter()->all();

        $notifier = app(\App\Services\Commerce\CommerceEventNotifier::class);
        $sent = 0; $fail = 0;
        foreach ($phones->unique() as $phone) {
            $digits = preg_replace('/\D+/', '', (string) $phone);
            if ($digits === '' || in_array($digits, $optedOut, true)) continue;
            $ctx = [
                'name' => 'there', 'coupon_code' => (string) ($data['coupon_code'] ?? ''),
                'store_name' => (string) ($integration->store_name ?: $integration->store_url),
                '_positional' => ['there', $integration->store_name ?: $integration->store_url, $data['coupon_code'] ?? ''],
            ];
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $digits, $tpl, $ctx, (string) ($integration->metadata["sender_key"] ?? "") ?: null);
            ($r['ok'] ?? false) ? $sent++ : $fail++;
        }

        WoocommerceIntegrationLog::create([
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
     * GET /woocommerce/{id}/plugin — download the WaDesk WooCommerce companion
     * plugin as an installable zip, with this store's webhook URL + secret
     * pre-baked. Unlocks abandoned-cart (phone capture), subscription dunning
     * and other events the REST API can't emit.
     */
    public function downloadPlugin(int $id)
    {
        $integration = $this->ownedIntegration($id);
        if (!$integration) abort(404);

        // Brand-driven names: __BRAND__ is the display name (Plugin Name, menu,
        // headings); $slug is a filesystem-safe brand for the zip + folder, so a
        // re-branded install downloads e.g. "instamagic-woocommerce.zip" — never
        // a hardcoded "wadesk-" file name.
        $slug = \Illuminate\Support\Str::slug((string) brand_name()) ?: 'whatsapp';
        $stub = file_get_contents(resource_path('stubs/wadesk-woocommerce.php.stub'));
        $php  = strtr($stub, [
            '__WEBHOOK_URL__' => url('/woocommerce/webhook/' . $integration->webhook_secret),
            '__SECRET__'      => $integration->webhook_secret,
            '__DELAY__'       => '30',
            '__BRAND__'       => (string) brand_name(),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'wcplugin') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString("{$slug}-woocommerce/{$slug}-woocommerce.php", $php);
        $zip->close();

        return response()->download($tmp, "{$slug}-woocommerce.zip", [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /** Variable context for an offer broadcast — product + coupon + contact. */
    private function offerContext(WoocommerceIntegration $integration, $product, ?string $coupon, $contact): array
    {
        $name     = trim((string) ($contact->first_name ?: $contact->name ?: 'there'));
        $currency = $integration->store_currency ?: '';
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
     * POST /woocommerce/webhook/{secret} — public, HMAC-verified.
     */
    public function webhook(string $secret, Request $request): Response
    {
        $integration = WoocommerceIntegration::where('webhook_secret', $secret)->first();
        if (!$integration) return response('not found', 404);

        $payload = $request->getContent();
        $sig     = (string) $request->header('X-WC-Webhook-Signature', '');
        if (!$this->woo->verifyWebhookSignature($payload, $sig, $integration->webhook_secret)) {
            return response('bad signature', 401);
        }

        $topic = (string) $request->header('X-WC-Webhook-Topic', '');
        $data  = json_decode($payload, true) ?: [];

        $event = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
            ->where('event_type', $topic)
            ->first();

        $shouldSend = $event && $event->is_active && $event->template_id;

        $log = WoocommerceIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => $topic,
            'status'         => $shouldSend ? 'processed' : 'skipped',
            'recipient'      => $this->resolveRecipient($data),
            'payload'        => $data,
            'created_at'     => now(),
        ]);

        // 1. Fire the configured WhatsApp template for THIS topic — engine
        //    aware (Unofficial API / WABA / Twilio). Wrapped so a send
        //    failure never makes WooCommerce retry (which would duplicate).
        if ($shouldSend) {
            try {
                $this->dispatchEventMessage($integration, $event, $data, $log);
            } catch (\Throwable $e) {
                \Log::warning('[WC-webhook] dispatch crashed (swallowed): ' . $e->getMessage());
                $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            }
        }

        // 2. Order-lifecycle automations. WooCommerce sends order.updated on
        //    every status transition; map the new status onto a friendly
        //    `order.<status>` automation (processing=confirmed, completed=
        //    delivered, cancelled, refunded, …) so merchants get a clean
        //    per-status notification instead of one noisy order.updated.
        if ($topic === 'order.updated') {
            try {
                $this->fireOrderStatusAutomation($integration, $data);
            } catch (\Throwable $e) {
                \Log::warning('[WC-webhook] status automation failed (swallowed): ' . $e->getMessage());
            }
        }

        // 2b. Back-in-stock — detect an out→in transition BEFORE the mirror
        //     upsert overwrites the previous stock state, and message anyone
        //     waiting for this product.
        if (in_array($topic, ['product.created', 'product.updated'], true) && !empty($data['id'])) {
            try {
                app(\App\Services\Woocommerce\WoocommerceStockService::class)->handleProductUpdate($integration, $data);
            } catch (\Throwable $e) {
                \Log::warning('[WC-webhook] back-in-stock failed (swallowed): ' . $e->getMessage());
            }
        }

        // 3. Keep our local mirror in sync (powers dashboard, offers, win-back,
        //    analytics). Best-effort — a mapping bug must not trigger a retry.
        try {
            $importer = app(\App\Services\Woocommerce\WoocommerceImporter::class);
            if (in_array($topic, ['product.created', 'product.updated'], true) && !empty($data['id'])) {
                $importer->upsertProduct($integration, $data);
            } elseif (in_array($topic, ['order.created', 'order.updated'], true) && !empty($data['id'])) {
                $importer->upsertOrder($integration, $data);
            }
        } catch (\Throwable $e) {
            \Log::warning('[WC-webhook] local mirror failed (swallowed): ' . $e->getMessage());
        }

        // 4. COD double-confirmation — on a new cash-on-delivery order, if the
        //    merchant armed the COD automation, message the customer to confirm
        //    (Yes/No) and open a pending tracking row. The reply flips the order.
        if ($topic === 'order.created' && \App\Services\Woocommerce\WoocommerceCodService::isCodOrder($data)) {
            try {
                // RTO / fraud risk score — attach to the log so the merchant can
                // see why an order is risky, and branch high-risk to prepaid.
                $risk = \App\Services\Woocommerce\WoocommerceRiskService::score($data, (int) $integration->workspace_id);

                $codEvent = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
                    ->where('event_type', 'cod/confirm')->where('is_active', true)->first();
                if ($codEvent && $codEvent->template_id) {
                    app(\App\Services\Woocommerce\WoocommerceCodService::class)->sendConfirmation($integration, $codEvent, $data);
                }

                // COD → Prepaid nudge — offer a pay-online link (template uses
                // {{order_pay_url}}). Fire it when configured; especially useful
                // for medium/high-risk orders.
                $prepaid = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
                    ->where('event_type', 'cod/prepaid')->where('is_active', true)->first();
                if ($prepaid && $prepaid->template_id) {
                    $ctx = $this->orderContext($integration, $data);
                    $ctx['risk_level'] = $risk['level'];
                    $this->sendPseudoEvent($integration, $prepaid, $this->resolveRecipient($data), $ctx, 'cod/prepaid');
                }

                WoocommerceIntegrationLog::create([
                    'integration_id' => $integration->id,
                    'event_type'     => 'cod/risk',
                    'status'         => 'processed',
                    'recipient'      => $this->resolveRecipient($data),
                    'payload'        => ['order' => '#' . ($data['number'] ?? ''), 'risk' => $risk],
                    'created_at'     => now(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('[WC-webhook] COD confirm/prepaid/risk failed (swallowed): ' . $e->getMessage());
            }
        }

        // 4b. Abandoned-cart recovery — schedule the delayed follow-up steps on
        //     a new checkout (checkout.created comes from the WaDesk companion
        //     plugin, which captures the phone), and cancel them the moment the
        //     order is placed.
        try {
            $cart = app(\App\Services\Woocommerce\WoocommerceCartService::class);
            if ($topic === 'checkout.created') {
                $cart->scheduleSequence($integration, $data);
            } elseif (in_array($topic, ['order.created', 'order.updated'], true)) {
                $cart->cancelOnOrder($integration, $data);
            }
        } catch (\Throwable $e) {
            \Log::warning('[WC-webhook] cart recovery failed (swallowed): ' . $e->getMessage());
        }

        // 5. Commerce-flow loop closer — if THIS order originated from a
        //    flow's commerce node we tagged it with _wa_flow_session when
        //    minting the draft order. Pop the session id and ping Node so
        //    it can advance the paused session through the `purchased` port.
        if (in_array($topic, ['order.created', 'order.updated'], true)) {
            try {
                \App\Services\Commerce\FlowSessionResolver::resumeFromWoocommerceOrder($data);
            } catch (\Throwable $e) {
                \Log::warning('[WC-webhook] flow-resume crashed (swallowed): ' . $e->getMessage());
            }
        }

        return response('ok', 200);
    }

    /** The full set of automation keys saveEvents + the dashboard accept. */
    public static function automationTypes(): array
    {
        return array_merge(WoocommerceService::WEBHOOK_TOPICS, [
            'order.processing', 'order.completed', 'order.on-hold',
            'order.cancelled', 'order.refunded', 'order.failed',
            'cod/confirm', 'cod/prepaid', 'stock/back',
            'checkout.created', 'cart/step2', 'cart/step3',
            'subscription.payment_failed', 'review/reward',
        ]);
    }

    /**
     * On an order.updated, fire the `order.<status>` automation matching the
     * order's current WooCommerce status (if the merchant configured + armed
     * one). Lets a single order move through confirmed → delivered etc.,
     * each with its own template.
     */
    private function fireOrderStatusAutomation(WoocommerceIntegration $integration, array $data): void
    {
        $status = strtolower((string) ($data['status'] ?? ''));
        if ($status === '') return;

        $event = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
            ->where('event_type', 'order.' . $status)
            ->where('is_active', true)
            ->first();
        if (!$event || !$event->template_id) return;

        $this->sendPseudoEvent($integration, $event, $this->resolveRecipient($data), $this->orderContext($integration, $data), 'order.' . $status);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function ownedIntegration(int $id): ?WoocommerceIntegration
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) return null;
        return WoocommerceIntegration::where('workspace_id', $wsId)->find($id);
    }

    private function resolveRecipient(array $data): ?string
    {
        // WC order/customer payloads carry the phone in billing.phone — very
        // often in LOCAL format (e.g. "08012345678"). Normalise to E.164 using
        // the order's billing country so WhatsApp can actually reach it; a raw
        // local number silently never delivers. WooPhone::fromOrder pulls the
        // phone + ISO-2 country and prepends the dialing code.
        return \App\Support\Woo\WooPhone::fromOrder($data);
    }

    /**
     * Send the configured template for a fired event to the customer and/or
     * the merchant's admin number, then record the outcome on the log row.
     * Engine-aware via CommerceEventNotifier. A webhook must return fast so
     * delay_seconds is not honoured inline (scheduled steps ride the Node
     * scheduler — see Phase 3 cart recovery).
     */
    private function dispatchEventMessage(
        WoocommerceIntegration $integration,
        WoocommerceIntegrationEvent $event,
        array $data,
        WoocommerceIntegrationLog $log
    ): void {
        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) {
            $log->update(['status' => 'failed', 'error' => 'Configured template no longer exists.']);
            return;
        }

        $ctx = $this->orderContext($integration, $data);
        if (is_array($event->var_map) && $event->var_map) {
            $ctx['_positional'] = array_map(fn ($field) => (string) ($ctx[$field] ?? ''), $event->var_map);
        }

        $sendTo   = $event->send_to ?: 'customer';
        $notifier = app(\App\Services\Commerce\CommerceEventNotifier::class);

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

        $results = [];
        $anyOk   = false;
        foreach ($targets as $who => $number) {
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $number, $tpl, $ctx, (string) ($integration->metadata["sender_key"] ?? "") ?: null);
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
     * Fire an automation that isn't a 1:1 webhook topic (order.<status>,
     * cod/prepaid, …) to one recipient with a prebuilt context. Honours the
     * event's var_map and writes its own log row.
     */
    private function sendPseudoEvent(WoocommerceIntegration $integration, WoocommerceIntegrationEvent $event, ?string $phone, array $ctx, string $logType): void
    {
        $phone = $phone ? preg_replace('/\D+/', '', (string) $phone) : '';
        if ($phone === '') return;
        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return;
        if (is_array($event->var_map) && $event->var_map) {
            $ctx['_positional'] = array_map(fn ($f) => (string) ($ctx[$f] ?? ''), $event->var_map);
        }
        $r = app(\App\Services\Commerce\CommerceEventNotifier::class)
            ->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx, (string) ($integration->metadata["sender_key"] ?? "") ?: null);
        WoocommerceIntegrationLog::create([
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

    /**
     * Flatten a WooCommerce order webhook payload into the named + positional
     * variable map CommerceEventNotifier substitutes into the template.
     * Positional default order is [name, order #, total] — the common
     * "Hi {{1}}, order {{2}} for {{3}}" shape.
     */
    private function orderContext(WoocommerceIntegration $integration, array $data): array
    {
        $billing  = is_array($data['billing'] ?? null) ? $data['billing'] : [];
        $first    = trim((string) ($billing['first_name'] ?? ''));
        $last     = trim((string) ($billing['last_name'] ?? ''));
        $name     = trim($first . ' ' . $last) ?: 'there';
        $number   = (string) ($data['number'] ?? $data['id'] ?? '');
        $orderName = '#' . $number;
        $currency = (string) ($data['currency'] ?? $integration->store_currency ?? '');
        $total    = (string) ($data['total'] ?? '');
        $totalFmt = $total !== '' ? trim($total . ' ' . $currency) : '';

        // Customer-facing "view order" page (standard My Account endpoint).
        $base     = $integration->store_url ? rtrim($integration->store_url, '/') : '';
        $orderUrl = $base ? $base . '/my-account/view-order/' . ($data['id'] ?? '') . '/' : '';
        // Pay-now link (COD → prepaid). WooCommerce's order-pay endpoint needs
        // the order id + order_key; the webhook payload carries order_key.
        $payUrl = ($base && !empty($data['order_key']))
            ? $base . '/checkout/order-pay/' . ($data['id'] ?? '') . '/?pay_for_order=true&key=' . $data['order_key']
            : $orderUrl;
        $tracking = $this->trackingFromMeta($data);
        $invoiceUrl = $this->invoiceUrlFromOrder($data, $base);

        return [
            'name'         => $name,
            'first_name'   => $first ?: $name,
            'last_name'    => $last,
            'order_number' => $number,
            'order_name'   => $orderName,
            'total'        => $totalFmt,
            'total_price'  => $total,
            'currency'     => $currency,
            'email'        => (string) ($billing['email'] ?? ''),
            'store_name'   => (string) ($integration->store_name ?: $integration->store_url),
            'status'       => (string) ($data['status'] ?? ''),
            'payment_method'       => (string) ($data['payment_method'] ?? ''),
            'payment_method_title' => (string) ($data['payment_method_title'] ?? ''),
            'order_url'        => $orderUrl,
            'order_pay_url'    => $payUrl,
            // Always populated: a real PDF from the store's invoice plugin when
            // present, else the customer's order page (where the invoice download
            // lives). So {{invoice_url}} works in ANY store — Events + flows.
            'invoice_url'      => $invoiceUrl ?: $orderUrl,
            'invoice_is_pdf'   => $invoiceUrl !== '' ? '1' : '0',
            'tracking_number'  => $tracking['number'],
            'tracking_url'     => $tracking['url'] ?: $orderUrl,
            'tracking_company' => $tracking['company'],
            '_positional'  => [$name, $orderName, $totalFmt],
        ];
    }

    /**
     * Resolve the invoice PDF URL for a Woo order. WooCommerce core has no
     * invoice, so this comes from a PDF-invoice plugin on the store:
     *   1. explicit `invoice_url` the WaDesk companion plugin forwards (best —
     *      a public/tokenized link it built from the PDF plugin), else
     *   2. the common PDF plugins' stored document link in order meta_data
     *      (WooCommerce PDF Invoices & Packing Slips / Print Invoices / YITH),
     *      else empty — the flow/event should then skip the invoice send.
     * Only https links are returned (WhatsApp document sends require public https).
     */
    private function invoiceUrlFromOrder(array $data, string $base): string
    {
        $pick = function ($v): string {
            $v = is_string($v) ? trim($v) : '';
            return \Illuminate\Support\Str::startsWith($v, 'https://') ? $v : '';
        };

        // 1) Explicit field forwarded by the companion plugin.
        foreach (['invoice_url', 'invoice_pdf_url', 'pdf_invoice_url'] as $k) {
            if ($u = $pick($data[$k] ?? '')) return $u;
        }

        // 2) Known PDF-plugin meta keys on the order.
        $wanted = [
            '_wcpdf_invoice_url', '_wcpdf_document_link', '_wpo_wcpdf_invoice_url',
            '_invoice_url', '_pdf_invoice_url', 'invoice_url', 'ywpi_invoice_url',
        ];
        foreach ((is_array($data['meta_data'] ?? null) ? $data['meta_data'] : []) as $m) {
            if (in_array((string) ($m['key'] ?? ''), $wanted, true)) {
                if ($u = $pick($m['value'] ?? '')) return $u;
            }
        }

        return '';
    }

    /**
     * Best-effort tracking extraction from a Woo order's meta_data — covers
     * the common Shipment Tracking plugins (which store _wc_shipment_tracking_items
     * or _tracking_number). Returns empty strings when absent.
     */
    private function trackingFromMeta(array $data): array
    {
        $out = ['number' => '', 'url' => '', 'company' => ''];
        $meta = is_array($data['meta_data'] ?? null) ? $data['meta_data'] : [];
        foreach ($meta as $m) {
            $key = $m['key'] ?? '';
            $val = $m['value'] ?? null;
            if ($key === '_wc_shipment_tracking_items' && is_array($val) && isset($val[0])) {
                $first = $val[0];
                $out['number']  = (string) ($first['tracking_number'] ?? '');
                $out['company'] = (string) ($first['tracking_provider'] ?? ($first['custom_tracking_provider'] ?? ''));
                $out['url']     = (string) ($first['custom_tracking_link'] ?? '');
            } elseif ($key === '_tracking_number' && is_string($val)) {
                $out['number'] = $val;
            }
        }
        return $out;
    }

    private function dashboardData(WoocommerceIntegration $integration): array
    {
        $counts      = $this->woo->getStoreCounts($integration);
        $orders      = $this->woo->getOrders($integration, 10);
        $products    = $this->woo->getProducts($integration, 10);
        $customers   = $this->woo->getCustomers($integration, 10);
        $salesReport = $this->woo->getSalesReport($integration, 30);

        $logsQ = WoocommerceIntegrationLog::where('integration_id', $integration->id);
        $logTotal     = (clone $logsQ)->count();
        $logsByStatus = (clone $logsQ)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->toArray();
        $logsByEvent  = (clone $logsQ)->selectRaw('event_type, COUNT(*) as n')->groupBy('event_type')->pluck('n', 'event_type')->toArray();
        $recentLogs   = (clone $logsQ)->latest('created_at')->limit(15)->get();

        $eventsByType = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
            ->get()
            ->keyBy('event_type');
        $activeEvents = $eventsByType->where('is_active', true)->count();

        // Load every workspace template with the fields engineKey() needs, then
        // keep the SENDABLE ones: WABA templates only when Meta-approved; Unofficial
        // (Baileys) / Twilio always. Each carries its engine + WABA account binding
        // so the blade can tag the <option>s and the JS can filter the picker to the
        // chosen "Send from" sender (WABA account → its approved templates; Unofficial
        // → any non-WABA template).
        $templates = WaTemplate::query()
            ->where('workspace_id', $integration->workspace_id)
            ->orderBy('template_name')
            ->get(['id', 'template_name', 'category', 'language', 'channel', 'twilio_content_sid', 'meta_template_id', 'meta_status', 'provider_config_id'])
            ->filter(function ($t) {
                if ($t->engineKey() === 'waba') {
                    return $t->meta_template_id && strtoupper((string) $t->meta_status) === 'APPROVED';
                }
                return true;
            })->values();

        // Connected senders (Unofficial devices + WABA/Twilio accounts) for the
        // "Send from" picker, and the currently-chosen sender key (metadata).
        $senders   = \App\Services\WorkspaceEngine::senders($integration->workspace_id);
        $senderKey = (string) ($integration->metadata['sender_key'] ?? '');

        $currency    = $integration->store_currency ?: ($orders[0]['currency'] ?? 'USD');
        $revenue30d  = (float) ($salesReport['total_sales'] ?? 0);
        $orders30d   = (int) ($salesReport['total_orders'] ?? 0);
        $customers30d= (int) ($salesReport['total_customers'] ?? 0);
        $itemsSold30d= (int) ($salesReport['total_items'] ?? 0);

        // ----- CRM data (DB-driven, scoped to WooCommerce) -----
        $wsId = $integration->workspace_id;
        $mirrorOrders = \App\Models\WaOrder::where('workspace_id', $wsId)->where('source', 'woocommerce');
        $revenueTotal = (int) (clone $mirrorOrders)->sum('total_minor');
        $ordersTotal  = (clone $mirrorOrders)->count();
        $messagesSent = WoocommerceIntegrationLog::where('integration_id', $integration->id)->where('status', 'sent')->count();
        $offersSent   = WoocommerceIntegrationLog::where('integration_id', $integration->id)->where('event_type', 'offer/broadcast')->count();

        // 14-day revenue trend from the mirror.
        $since = now()->subDays(13)->startOfDay();
        $byDay = (clone $mirrorOrders)->where('created_at', '>=', $since)
            ->get(['total_minor', 'created_at'])
            ->groupBy(fn ($o) => $o->created_at->format('Y-m-d'))
            ->map(fn ($g) => $g->sum('total_minor') / 100);
        $trend = [];
        for ($d = 0; $d < 14; $d++) {
            $key = now()->subDays(13 - $d)->format('Y-m-d');
            $trend[] = ['label' => now()->subDays(13 - $d)->format('M j'), 'value' => (float) ($byDay[$key] ?? 0)];
        }

        $aov          = $ordersTotal ? ($revenueTotal / 100 / $ordersTotal) : 0;
        $codConfirmed = \App\Models\WoocommerceCodConfirmation::where('workspace_id', $wsId)->where('status', 'confirmed')->count();
        $codCancelled = \App\Models\WoocommerceCodConfirmation::where('workspace_id', $wsId)->where('status', 'cancelled')->count();
        $recoverySends = WoocommerceIntegrationLog::where('integration_id', $integration->id)
            ->whereIn('event_type', ['offer/broadcast', 'winback/broadcast', 'checkout.created', 'cod/confirm', 'cod/prepaid', 'stock/back'])
            ->where('status', 'sent')->count();

        $analytics = [
            'revenue_total' => $revenueTotal / 100,
            'orders_total'  => $ordersTotal,
            'aov'           => $aov,
            'messages_sent' => $messagesSent,
            'offers_sent'   => $offersSent,
            'trend'         => $trend,
            'trend_max'     => max(1, collect($trend)->max('value')),
            'cod_confirmed' => $codConfirmed,
            'cod_cancelled' => $codCancelled,
            'cod_protected' => $codConfirmed * $aov,
            'rto_avoided'   => $codCancelled * $aov,
            'recovery_sends'=> $recoverySends,
        ];

        // Offer-composer pickers.
        $contactGroups = \App\Models\ContactGroup::where('workspace_id', $wsId)->get(['id', 'user_group', 'color']);
        $coupons       = \App\Models\Coupon::where('is_active', true)->orderBy('code')->limit(100)->get(['id', 'code', 'type', 'amount']);
        $offerProducts = \App\Models\WaProduct::where('workspace_id', $wsId)
            ->whereNotNull('woo_product_id')->orderBy('name')->limit(200)->get(['id', 'name', 'price_minor']);

        return [
            'analytics'     => $analytics,
            'contactGroups' => $contactGroups,
            'coupons'       => $coupons,
            'offerProducts' => $offerProducts,
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
            'templates'     => $templates,
            'senders'       => $senders,
            'senderKey'     => $senderKey,
            'engineIsWaba'  => false,
            'currency'      => $currency,
            'revenue30d'    => $revenue30d,
            'orders30d'     => $orders30d,
            'customers30d'  => $customers30d,
            'itemsSold30d'  => $itemsSold30d,
        ];
    }
}
