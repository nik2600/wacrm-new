<?php

namespace App\Http\Controllers;

use App\Models\WaProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WaProductController extends Controller
{
    public function index(Request $request): View
    {
        $wsId = Auth::user()->current_workspace_id;
        $q = trim((string) $request->string('q')->toString());
        $rows = WaProduct::forWorkspace($wsId)
            ->when($q !== '', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"))
            ->ordered()->paginate(25)->withQueryString();
        return view('user.store.products.index', compact('rows', 'q'));
    }

    public function create(): View
    {
        return view('user.store.products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $data = $this->validateProduct($request);
        $product = new WaProduct(array_merge($data, ['workspace_id' => $wsId, 'user_id' => Auth::id()]));
        $product->save();
        $this->handleImage($request, $product);
        return redirect()->route('user.store.products.index')->with('status', 'Product created.');
    }

    public function edit(int $id): View
    {
        $wsId = Auth::user()->current_workspace_id;
        $product = WaProduct::forWorkspace($wsId)->findOrFail($id);
        return view('user.store.products.edit', compact('product'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $product = WaProduct::forWorkspace($wsId)->findOrFail($id);
        $data = $this->validateProduct($request, $id);
        $product->fill($data);
        $product->save();
        $this->handleImage($request, $product);
        return redirect()->route('user.store.products.index')->with('status', 'Product updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        WaProduct::forWorkspace($wsId)->findOrFail($id)->delete();
        return redirect()->route('user.store.products.index')->with('status', 'Product removed.');
    }

    /**
     * Lightweight JSON listing used by the team-inbox + chat catalog
     * picker modal. Optional `q=` filters by name/SKU. Only returns
     * active, in-stock products — disabled ones can't be sent.
     */
    public function apiList(Request $request): JsonResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'products' => []]);

        $q = trim((string) $request->string('q')->toString());
        $rows = WaProduct::forWorkspace($wsId)
            ->where('status', 'active')
            ->where('in_stock', true)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($q2) use ($q) {
                    $q2->where('name', 'like', '%' . $q . '%')
                       ->orWhere('sku', 'like', '%' . $q . '%');
                });
            })
            ->ordered()
            ->limit(120)
            ->get(['id', 'sku', 'name', 'image_url', 'price_minor', 'currency_code', 'category', 'meta_retailer_id', 'meta_sync_status']);

        return response()->json([
            'ok'       => true,
            'products' => $rows->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'sku'          => $p->sku,
                'image_url'    => $p->image_url,
                'category'     => $p->category,
                'price_minor'  => $p->price_minor,
                'price_display'=> $p->price_display,
                'currency'     => $p->currency_code,
                'retailer_id'  => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                'synced'       => $p->meta_sync_status === 'synced',
            ])->values(),
        ]);
    }

    public function shareLinks(int $id): JsonResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $product = WaProduct::forWorkspace($wsId)->findOrFail($id);
        $sf = \App\Models\WaStorefront::where('workspace_id', $wsId)->first();
        $cfg = \App\Models\WaProviderConfig::query()->primaryForWorkspace($wsId)->first();
        $publicUrl = $sf ? rtrim($sf->public_url, '/') . '/p/' . $product->slug : null;
        $waNumber = $cfg?->phone_number ?: '';
        $waText   = "Hi! I want to order:\n\n*{$product->name}* — {$product->price_display}\n" . ($product->image_url ? $product->image_url . "\n" : '') . "\nQty: 1";
        $waLink = $waNumber ? 'https://wa.me/' . preg_replace('/\D+/', '', $waNumber) . '?text=' . rawurlencode($waText) : null;
        return response()->json([
            'ok' => true,
            'public_url' => $publicUrl,
            'wa_link'    => $waLink,
            'product'    => ['id' => $product->id, 'name' => $product->name, 'image' => $product->image_url],
        ]);
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name'                 => 'required|string|max:191',
            'sku'                  => 'nullable|string|max:96',
            'description'          => 'nullable|string|max:2000',
            'body_html'            => 'nullable|string|max:65535',
            'price_major'          => 'required|numeric|min:0|max:1000000',
            'compare_price_major'  => 'nullable|numeric|min:0|max:1000000',
            'currency_code'        => 'required|string|size:3|in:INR,USD,EUR,GBP,AED,KES,NGN,ZAR,BRL,MXN,CRC,PHP,IDR,SGD,MYR,THB,VND,EGP,PKR,BDT,LKR',
            'in_stock'             => 'sometimes|boolean',
            'stock_qty'            => 'nullable|integer|min:0|max:1000000',
            'sort_order'           => 'nullable|integer|min:0|max:9999',
            'status'               => 'nullable|string|in:active,draft,archived',
            'weight_grams'         => 'nullable|integer|min:0|max:1000000',
            'category'             => 'nullable|string|max:96',
            'tags'                 => 'nullable|string|max:1024',
            'image'                => 'nullable|image|max:4096',
            'image_url'            => 'nullable|url|max:1024',
            'gallery_urls'         => 'nullable|array|max:12',
            'gallery_urls.*'       => 'nullable|url|max:1024',
        ];
        $data = $request->validate($rules);
        $data['price_minor'] = (int) round(((float) $data['price_major']) * 100);
        $data['compare_price_minor'] = !empty($data['compare_price_major'])
            ? (int) round(((float) $data['compare_price_major']) * 100)
            : null;
        $data['in_stock'] = (bool) ($data['in_stock'] ?? true);
        $data['status']   = $data['status'] ?? 'active';

        // Tags arrive as a CSV string in the form; normalise to a
        // unique, trimmed array stored in tags_json.
        if (!empty($data['tags'])) {
            $tags = array_filter(array_map('trim', explode(',', $data['tags'])));
            $data['tags_json'] = array_values(array_unique($tags));
        } else {
            $data['tags_json'] = null;
        }

        // Normalise gallery_urls[] (drops empty rows, dedupes, caps at 12)
        // into the gallery_json field. NULL when there are no URLs so
        // the form's "empty" state is preserved.
        if (!empty($data['gallery_urls'])) {
            $gallery = array_values(array_unique(array_filter(array_map('trim', $data['gallery_urls']))));
            $data['gallery_json'] = array_slice($gallery, 0, 12) ?: null;
        } else {
            $data['gallery_json'] = null;
        }

        unset($data['price_major'], $data['compare_price_major'], $data['image'], $data['tags'], $data['gallery_urls']);
        return $data;
    }

    private function handleImage(Request $request, WaProduct $product): void
    {
        if (!$request->hasFile('image')) return;
        $path = $request->file('image')->store('store-products', media_disk());
        $product->update(['image_url' => media_url($path)]);
    }
}
