<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WaProduct;
use App\Models\WaStorefront;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public API for the WaDesk Google Sheets add-on.
 *
 * Auth model: a per-user "Sheets API key" (token format
 * `wsn_live_<32 hex>`) which the user generates from /account and
 * pastes into the add-on sidebar. We store only a sha256 hash + the
 * last 8 chars as a UI label, so a DB compromise doesn't leak usable
 * tokens.
 *
 * Endpoints:
 *   GET  /api/v1/sheets-addon/health           — auth check
 *   GET  /api/v1/sheets-addon/shops            — list shops for this user's workspace
 *   POST /api/v1/sheets-addon/sync             — upsert one shop + bulk-upsert its products
 *
 * Errors return JSON {ok:false, error:"..."} with appropriate HTTP code.
 */
class SheetsAddonController extends Controller
{
    /**
     * Read the Bearer token from the Authorization header, hash it,
     * find the matching user. Returns the User or null.
     */
    private function authenticate(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (!$token || !str_starts_with($token, 'wsn_live_')) {
            return null;
        }
        $hash = hash('sha256', $token);
        $user = User::where('sheets_api_key_hash', $hash)->first();
        if ($user) {
            // Touch last_used_at so the user can see their token is
            // alive without us writing to it on every page load.
            $user->forceFill(['sheets_api_key_last_used_at' => now()])->save();
        }
        return $user;
    }

    public function health(Request $request): JsonResponse
    {
        $user = $this->authenticate($request);
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'Invalid or missing API key'], 401);
        }
        return response()->json([
            'ok'        => true,
            'user'      => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'workspace' => $user->current_workspace_id,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function shops(Request $request): JsonResponse
    {
        $user = $this->authenticate($request);
        if (!$user) return response()->json(['ok' => false, 'error' => 'Invalid API key'], 401);

        $wsId = $user->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'No active workspace'], 422);

        // Single SQL pull instead of per-row queries inside the map —
        // safer when the workspace owns many shops.
        $productCount = WaProduct::where('workspace_id', $wsId)->count();

        $shops = WaStorefront::where('workspace_id', $wsId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($s) => [
                'id'            => $s->id,
                'shop_name'     => $s->shop_name,
                'slug'          => $s->slug,
                'currency'      => $s->currency_code ?? 'INR',
                'enabled'       => (bool) $s->enabled,
                'theme_key'     => $s->theme_key,
                'custom_domain' => $s->custom_domain,
                'public_url'    => $s->public_url,
                'product_count' => $productCount, // workspace-wide; products aren't yet shop-scoped
                'updated_at'    => $s->updated_at?->toIso8601String(),
            ]);

        return response()->json([
            'ok'         => true,
            'shops'      => $shops,
            'workspace'  => $wsId,
            'product_count_total' => $productCount,
        ]);
    }

    /**
     * Upsert one shop + bulk-upsert its products. Idempotent on
     * `shop_id` (when present in the payload). Without shop_id we
     * either reuse an existing slug match or create a brand new shop.
     *
     * Expected body:
     *   {
     *     shop_id?:   int,
     *     shop_name:  string,
     *     description?: string,
     *     currency?:  3-letter,
     *     wa_number?: string,
     *     theme_key?: one of WaStorefront::THEMES,
     *     products: [
     *       { name, category?, description?, image_url?, price, sku?, stock?, active? },
     *       ...
     *     ]
     *   }
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $this->authenticate($request);
        if (!$user) return response()->json(['ok' => false, 'error' => 'Invalid API key'], 401);

        $wsId = $user->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'No active workspace'], 422);

        $data = $request->validate([
            'shop_id'      => ['nullable', 'integer'],
            'shop_name'    => ['required', 'string', 'max:191'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'theme_key'    => ['nullable', 'string', 'in:' . implode(',', array_keys(WaStorefront::THEMES))],
            'products'     => ['required', 'array', 'min:1', 'max:500'],
            'products.*.name'        => ['required', 'string', 'max:191'],
            'products.*.category'    => ['nullable', 'string', 'max:96'],
            'products.*.description' => ['nullable', 'string', 'max:2000'],
            'products.*.image_url'   => ['nullable', 'url', 'max:1024'],
            'products.*.price'       => ['required', 'numeric', 'min:0', 'max:1000000'],
            'products.*.sku'         => ['nullable', 'string', 'max:96'],
            'products.*.stock'       => ['nullable', 'integer', 'min:0'],
            'products.*.active'      => ['nullable', 'boolean'],
        ]);

        $result = DB::transaction(function () use ($user, $wsId, $data) {
            // ── Resolve shop ──
            $shop = null;
            if (!empty($data['shop_id'])) {
                $shop = WaStorefront::where('workspace_id', $wsId)->where('id', $data['shop_id'])->first();
            }
            if (!$shop) {
                // Slug derived from shop_name, unique globally.
                $base = Str::slug($data['shop_name']) ?: 'shop';
                $slug = $base; $i = 2;
                while (WaStorefront::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $shop = new WaStorefront([
                    'workspace_id' => $wsId,
                    'slug'         => $slug,
                    'theme_key'    => $data['theme_key'] ?? WaStorefront::DEFAULT_THEME,
                    'enabled'      => true,
                ]);
            }
            $shop->shop_name = $data['shop_name'];
            if (!empty($data['currency']))  $shop->currency_code = strtoupper($data['currency']);
            if (!empty($data['theme_key'])) $shop->theme_key = $data['theme_key'];
            if (!empty($data['description'])) {
                $shop->settings_json = array_merge($shop->settings_json ?? [], [
                    'hero_text' => $data['description'],
                ]);
            }
            $shop->save();

            // ── Upsert products ──
            // Match strategy: SKU first (when provided), then name+workspace.
            // Anything in our DB that's NOT in the incoming list keeps its
            // soft-delete state — the add-on is additive, not destructive.
            $created = 0;
            $updated = 0;
            foreach ($data['products'] as $row) {
                $existing = null;
                if (!empty($row['sku'])) {
                    $existing = WaProduct::where('workspace_id', $wsId)
                        ->where('sku', $row['sku'])->first();
                }
                if (!$existing) {
                    $existing = WaProduct::where('workspace_id', $wsId)
                        ->where('name', $row['name'])->first();
                }
                $isNew = !$existing;
                $p = $existing ?: new WaProduct(['workspace_id' => $wsId, 'user_id' => $user->id]);
                $p->name        = $row['name'];
                $p->sku         = $row['sku'] ?? $p->sku;
                $p->description = $row['description'] ?? $p->description;
                $p->image_url   = $row['image_url']   ?? $p->image_url;
                $p->category    = $row['category']    ?? $p->category;
                $p->price_minor = (int) round(((float) $row['price']) * 100);
                $p->currency_code = $shop->currency_code ?? 'INR';
                if (array_key_exists('stock', $row)) $p->stock_qty = $row['stock'] !== null ? (int) $row['stock'] : null;
                $p->in_stock    = (bool) ($row['active'] ?? true);
                $p->status      = $p->in_stock ? 'active' : 'draft';
                $p->save();
                $isNew ? $created++ : $updated++;
            }

            return [
                'shop'    => [
                    'id'        => $shop->id,
                    'shop_name' => $shop->shop_name,
                    'slug'      => $shop->slug,
                    'currency'  => $shop->currency_code ?? 'INR',
                    'public_url'=> $shop->public_url,
                ],
                'products' => ['created' => $created, 'updated' => $updated, 'total' => $created + $updated],
            ];
        });

        return response()->json(['ok' => true] + $result);
    }
}
