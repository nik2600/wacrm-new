<?php

namespace App\Http\Controllers;

use App\Models\WaProduct;
use App\Models\WaProviderConfig;
use App\Models\WaStorefront;
use App\Models\WaProductReview;
use App\Services\Storefront\StorefrontCheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public storefront — resolves a workspace from either `<slug>.<host>`
 * subdomain or a custom domain. Renders one of 8 themes. The theme
 * blade receives a uniform context so they're interchangeable.
 */
class StorefrontPublicController extends Controller
{
    public function index(Request $request, ?string $slug = null): View
    {
        $sf = $this->resolveStorefront($request, $slug);
        abort_if(!$sf || !$sf->enabled, 404);

        // Catalog pagination. 48 cards is enough to fill a desktop
        // viewport ~3 times — beyond that we let the client lazy-load
        // more via a "Show more" button. WATI choked at 34-56 items
        // (reviews); we want any size to feel snappy.
        $perPage = 48;
        $page = max(1, (int) $request->integer('page'));
        $base = WaProduct::forWorkspace($sf->workspace_id)
            ->available()
            ->ordered();
        $total = (clone $base)->count();
        $products = (clone $base)
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1) // +1 to know if there's more
            ->get();
        $hasMore = $products->count() > $perPage;
        if ($hasMore) $products = $products->take($perPage);

        $cfg = WaProviderConfig::query()->primaryForWorkspace($sf->workspace_id)->first();

        // Category facet — only show categories that actually have at
        // least one available product so the sidebar doesn't show stale
        // tags that map to zero hits.
        $categories = $products
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Price range for the filter sidebar (in major currency units
        // so the JS doesn't have to divide by 100 on every render).
        $priceMin = $products->min('price_minor') ?? 0;
        $priceMax = $products->max('price_minor') ?? 0;

        // AJAX append for "Show more" — return only the rendered card
        // HTML for the next page so the storefront can lazy-load.
        if ($request->boolean('partial') && $page > 1) {
            return response()->view('storefront._partials._cards-only', $this->context($sf, $cfg, [
                'products' => $products,
                'hasMore'  => $hasMore,
                'page'     => $page,
            ]));
        }

        $this->trackView($sf->id);

        return view($this->themeView($sf, 'index'), $this->context($sf, $cfg, [
            'products'   => $products,
            'categories' => $categories,
            'priceMin'   => (int) round($priceMin / 100),
            'priceMax'   => (int) round($priceMax / 100),
            'hasMore'    => $hasMore,
            'total'      => $total,
            'page'       => $page,
        ]));
    }

    public function product(Request $request, string $slug, ?string $productSlug = null): View
    {
        // Two URL shapes are supported:
        //   /p/{productSlug}            — when subdomain has resolved storefront
        //   /{storefrontSlug}/p/{...}   — when host-based resolution failed
        if ($productSlug === null) {
            $productSlug = $slug;
            $sf = $this->resolveStorefront($request);
        } else {
            $sf = WaStorefront::where('slug', $slug)->where('enabled', true)->firstOrFail();
        }
        abort_if(!$sf || !$sf->enabled, 404);

        $product = WaProduct::forWorkspace($sf->workspace_id)
            ->where('slug', $productSlug)
            ->available()
            ->firstOrFail();

        $cfg = WaProviderConfig::query()->primaryForWorkspace($sf->workspace_id)->first();

        // Related products — same category first, fall back to "anything
        // else from this shop" so the rail is never empty.
        $relatedQuery = WaProduct::forWorkspace($sf->workspace_id)
            ->available()
            ->where('id', '!=', $product->id);
        if ($product->category) {
            $sameCategory = (clone $relatedQuery)
                ->where('category', $product->category)
                ->ordered()
                ->limit(8)
                ->get();
            $needed = 8 - $sameCategory->count();
            if ($needed > 0) {
                $filler = (clone $relatedQuery)
                    ->whereNotIn('id', $sameCategory->pluck('id')->all())
                    ->ordered()
                    ->limit($needed)
                    ->get();
                $related = $sameCategory->merge($filler);
            } else {
                $related = $sameCategory;
            }
        } else {
            $related = $relatedQuery->ordered()->limit(8)->get();
        }

        $reviews = WaProductReview::where('product_id', $product->id)
            ->approved()->latest()->limit(20)->get();

        $this->trackView($sf->id);

        return view($this->themeView($sf, 'product'), $this->context($sf, $cfg, [
            'product'     => $product,
            'related'     => $related,
            'reviews'     => $reviews,
            'ratingAvg'   => $reviews->count() ? round($reviews->avg('rating'), 1) : null,
            'ratingCount' => $reviews->count(),
        ]));
    }

    /**
     * Increment the storefront's daily pageview counter (S9). One row per
     * shop per day; never throws — analytics must not break the page.
     */
    private function trackView(int $storefrontId): void
    {
        try {
            $day = now()->toDateString();
            try {
                DB::table('wa_storefront_views')->insert([
                    'storefront_id' => $storefrontId, 'day' => $day, 'views' => 1,
                ]);
            } catch (\Throwable) {
                DB::table('wa_storefront_views')
                    ->where('storefront_id', $storefrontId)->where('day', $day)
                    ->increment('views');
            }
        } catch (\Throwable) {
            // ignore — tracking is best-effort
        }
    }

    /**
     * Server-side checkout (S1). Captures the order in the database BEFORE
     * the WhatsApp hand-off, re-pricing every line server-side. Returns
     * JSON: { ok, order_no, wa_url } — the storefront JS clears the cart
     * and redirects the buyer to WhatsApp with a short confirmation.
     *
     * Public + CSRF-exempt (cross-domain storefronts have no Laravel
     * session) — safe because every price/qty is re-derived from the DB
     * and the route is rate-limited.
     */
    public function checkout(Request $request, ?string $slug = null): JsonResponse
    {
        $sf = $this->resolveStorefront($request, $slug);
        if (!$sf || !$sf->enabled) {
            return response()->json(['ok' => false, 'message' => 'This store is not available.'], 404);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'phone'       => ['required', 'string', 'max:32'],
            'email'       => ['nullable', 'email', 'max:160'],
            'address'     => ['nullable', 'string', 'max:1000'],
            'note'        => ['nullable', 'string', 'max:1000'],
            'coupon'         => ['nullable', 'string', 'max:64'],
            'payment_method' => ['nullable', 'in:prepaid,cod'],
            'items'       => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id'  => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        // A real phone is the whole point of server checkout — reject junk.
        if (strlen(preg_replace('/\D/', '', $data['phone'])) < 7) {
            return response()->json(['ok' => false, 'message' => 'Please enter a valid phone number.'], 422);
        }

        $order = app(StorefrontCheckoutService::class)->placeOrder($sf, $data);
        if (!$order) {
            return response()->json([
                'ok' => false,
                'message' => 'Those items are no longer available. Please refresh and try again.',
            ], 422);
        }

        // Build the WhatsApp hand-off so the buyer lands in chat with the
        // merchant, order already on record. If the shop has no number we
        // still confirm the order was captured.
        $cfg      = WaProviderConfig::query()->primaryForWorkspace($sf->workspace_id)->first();
        $waNumber = $this->resolveWaNumber($sf, $cfg);
        $trackUrl = url('/s/' . $sf->slug . '/order/' . $order->recovery_token);
        $waUrl    = null;
        if ($waNumber) {
            $shopName = $sf->shop_name ?: ($sf->workspace?->name ?: 'the store');
            $text = "Hi {$shopName}! I just placed order #{$order->id}"
                . ' (' . $order->total_display . '). My name is ' . ($order->customer_name ?: '—') . '.'
                . "\nTrack: " . $trackUrl;
            $waUrl = 'https://wa.me/' . preg_replace('/\D+/', '', $waNumber)
                . '?text=' . rawurlencode($text);
        }

        return response()->json([
            'ok'        => true,
            'order_no'  => $order->id,
            'wa_url'    => $waUrl,
            'track_url' => '/s/' . $sf->slug . '/order/' . $order->recovery_token,
            'message'   => 'Order placed! ' . ($waUrl ? 'Opening WhatsApp…' : "We'll be in touch shortly."),
        ]);
    }

    /**
     * Public order-tracking page (S8). The buyer gets a tokenised link
     * (/s/{slug}/order/{token}) after checkout — a self-service status
     * page so they stop messaging "where's my order?". Read-only; the
     * unguessable 40-char token is the access control.
     */
    public function orderStatus(Request $request, string $slug, string $token): View
    {
        $sf = WaStorefront::where('slug', $slug)->where('enabled', true)->firstOrFail();
        $order = \App\Models\WaOrder::where('storefront_id', $sf->id)
            ->where('recovery_token', $token)
            ->firstOrFail();

        $cfg = WaProviderConfig::query()->primaryForWorkspace($sf->workspace_id)->first();

        return view('storefront.order-status', $this->context($sf, $cfg, [
            'order' => $order,
        ]));
    }

    /**
     * Coupon quote (S5) — validate a code against the live cart and return
     * the discount breakdown so the storefront can show it before checkout.
     */
    public function applyCoupon(Request $request, ?string $slug = null): JsonResponse
    {
        $sf = $this->resolveStorefront($request, $slug);
        if (!$sf || !$sf->enabled) return response()->json(['ok' => false], 404);

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:64'],
            'items'       => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id'  => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $q = app(StorefrontCheckoutService::class)->quote($sf, $data['items'], $data['code']);
        if (!$q['coupon_valid']) {
            return response()->json(['ok' => false, 'message' => 'That code is not valid for this cart.'], 422);
        }

        return response()->json([
            'ok'             => true,
            'discount_minor' => $q['discount'],
            'shipping_minor' => $q['shipping'],
            'total_minor'    => $q['total'],
            'free_shipping'  => $q['free_shipping'],
            'code'           => $q['coupon'],
        ]);
    }

    /**
     * Abandoned-cart beacon (S3). Fired when a buyer enters their phone at
     * checkout but hasn't placed the order. Schedules a WhatsApp recovery
     * nudge (cancelled automatically if they then order). Public + CSRF-exempt
     * + throttled; no-ops unless the merchant enabled recovery.
     */
    public function abandon(Request $request, ?string $slug = null): JsonResponse
    {
        $sf = $this->resolveStorefront($request, $slug);
        if (!$sf || !$sf->enabled) return response()->json(['ok' => false], 404);

        $data = $request->validate([
            'name'        => ['nullable', 'string', 'max:120'],
            'phone'       => ['required', 'string', 'max:32'],
            'items'       => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id'  => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        if (strlen(preg_replace('/\D/', '', $data['phone'])) < 7) {
            return response()->json(['ok' => false], 422);
        }

        // Re-price server-side so the stored subtotal is trustworthy.
        $quote = app(StorefrontCheckoutService::class)->quote($sf, $data['items']);
        $scheduled = app(\App\Services\Storefront\StorefrontCartService::class)
            ->scheduleRecovery($sf, $data['phone'], $data['name'] ?? null, $data['items'], (int) $quote['subtotal']);

        return response()->json(['ok' => true, 'scheduled' => $scheduled]);
    }

    /**
     * Submit a product review (S6). Lands as `pending` for merchant
     * moderation. Public + CSRF-exempt + throttled.
     */
    public function submitReview(Request $request, ?string $slug = null): JsonResponse
    {
        $sf = $this->resolveStorefront($request, $slug);
        if (!$sf || !$sf->enabled) return response()->json(['ok' => false], 404);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'name'       => ['required', 'string', 'max:120'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'body'       => ['nullable', 'string', 'max:1000'],
        ]);

        $product = WaProduct::forWorkspace($sf->workspace_id)->where('id', $data['product_id'])->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'Unknown product.'], 422);

        \App\Models\WaProductReview::create([
            'workspace_id'  => $sf->workspace_id,
            'storefront_id' => $sf->id,
            'product_id'    => $product->id,
            'customer_name' => $data['name'],
            'rating'        => $data['rating'],
            'body'          => $data['body'] ?? null,
            'status'        => 'pending',
        ]);

        return response()->json(['ok' => true, 'message' => 'Thanks! Your review will appear once approved.']);
    }

    /**
     * Resolve the storefront's outbound WhatsApp number: workspace
     * WaProviderConfig phone first, else the bound device. Shared by the
     * theme context + checkout hand-off.
     */
    private function resolveWaNumber(WaStorefront $sf, ?WaProviderConfig $cfg): ?string
    {
        $waNumber = $cfg?->phone_number ?: null;
        if (!$waNumber && $sf->device_id && ($device = $sf->device)) {
            $waNumber = trim(($device->country_code ?? '') . $device->phone_number);
        }
        return $waNumber ?: null;
    }

    /**
     * Common variables every theme view (and _theme-base.blade.php)
     * relies on. Centralised here so themes like mercato / studio /
     * meridian that reference `$hero` directly inside their own
     * @section('content') don't crash — Blade section scopes don't
     * inherit variables defined inside the base layout's @php block.
     */
    private function context(WaStorefront $sf, ?WaProviderConfig $cfg, array $extra = []): array
    {
        $settings = $sf->settings_json ?? [];
        $workspace = $sf->workspace;
        $shopName = $sf->shop_name ?: ($workspace?->name ?: 'Store');

        // Resolve a WhatsApp number for the "Order on WhatsApp" link
        // (workspace WaProviderConfig phone, else the bound device).
        $waNumber = $this->resolveWaNumber($sf, $cfg);

        return array_merge([
            'sf'        => $sf,
            'workspace' => $workspace,
            'settings'  => $settings,
            'waNumber'  => $waNumber,
            'shopName'  => $shopName,
            'hero'      => $settings['hero_text'] ?? '',
            'footer'    => $settings['footer_text'] ?? ('© ' . date('Y') . ' ' . $shopName),
            'logo'      => $settings['logo_url'] ?? null,
            'brand'     => $settings['brand_color'] ?? '#075E54',
        ], $extra);
    }

    /**
     * Public: does this request's HOST belong to a live storefront — a verified
     * custom domain (shop.acme.com) or a storefront subdomain (acme.parent.tld)?
     * The homepage route calls this so that pointing a custom domain at us serves
     * the SHOP at "/", not the platform's marketing homepage. Null on the main
     * app host (normal homepage). Best-effort — never throws.
     */
    public function storefrontForHost(Request $request): ?WaStorefront
    {
        try {
            return $this->resolveStorefront($request, null);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveStorefront(Request $request, ?string $slug = null): ?WaStorefront
    {
        if ($slug) {
            return WaStorefront::where('slug', $slug)->where('enabled', true)->first();
        }

        $host = strtolower($request->getHost());
        $rootHost = strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?: '');

        // Custom domain (verified) match
        $sf = WaStorefront::where('custom_domain', $host)->where('custom_domain_verified', true)->where('enabled', true)->first();
        if ($sf) return $sf;

        // Subdomain match: foo.parent.tld
        if ($rootHost && str_ends_with($host, '.' . $rootHost)) {
            $candidate = substr($host, 0, -strlen('.' . $rootHost));
            return WaStorefront::where('slug', $candidate)->where('enabled', true)->first();
        }
        return null;
    }

    private function themeView(WaStorefront $sf, string $page): string
    {
        $theme = $sf->theme_key ?: WaStorefront::DEFAULT_THEME;
        $candidate = "storefront.themes.$theme.$page";
        if (view()->exists($candidate)) return $candidate;
        // Fallback to aurora if theme file missing.
        return "storefront.themes.aurora.$page";
    }
}
