<?php

namespace App\Http\Controllers;

use App\Enums\WaProvider;
use App\Models\WaOrder;
use App\Models\WaProduct;
use App\Models\WaProviderConfig;
use App\Models\WaStorefront;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * /store — workspace's commerce dashboard. Layout mirrors the Shopify
 * dashboard pattern (260px sidebar + 1fr main, tabs in the sidebar
 * driving content swap). One controller, multiple tab handlers.
 */
class StoreController extends Controller
{
    public function index(Request $request): Renderable|RedirectResponse
    {
        $tab = $request->string('tab')->toString() ?: 'overview';
        $u   = Auth::user();
        $wsId = $u?->current_workspace_id;

        // Multi-shop aware: ?shop=ID selects a specific shop, else
        // the workspace's most recently updated shop is shown. If
        // the workspace has no shops at all, route to the wizard.
        $shopId = (int) $request->integer('shop');
        $shopsQuery = $wsId ? WaStorefront::where('workspace_id', $wsId) : null;
        if ($shopId && $shopsQuery) {
            $sf = (clone $shopsQuery)->where('id', $shopId)->first();
            if (!$sf) {
                // Bad ?shop=ID — fall back to the workspace's default.
                $sf = (clone $shopsQuery)->orderByDesc('updated_at')->first();
            }
        } else {
            $sf = $shopsQuery ? (clone $shopsQuery)->orderByDesc('updated_at')->first() : null;
        }
        if ($wsId && !$sf) {
            return redirect('/connect?platform=wa-store');
        }

        // All shops in this workspace — feeds the shop switcher in
        // the sidebar so the operator can jump between shops without
        // bouncing back to /connect each time.
        $allShops = $wsId
            ? (clone $shopsQuery)->orderByDesc('updated_at')->get(['id', 'shop_name', 'slug'])
            : collect();

        $cfg = $wsId ? WaProviderConfig::query()->primaryForWorkspace($wsId)->first() : null;

        $stats = $this->stats($wsId);

        return view('user.store.index', [
            'tab'      => $tab,
            'cfg'      => $cfg,
            'sf'       => $sf,
            'allShops' => $allShops,
            'stats'    => $stats,
            'salesByDay' => $this->salesByDay($wsId, 30),
            'topProducts'=> $this->topProducts($wsId),
            'recentOrders'=> $this->recentOrders($wsId),
        ]);
    }

    private function stats(?int $wsId): array
    {
        if (!$wsId) {
            return ['revenue30' => 0, 'orders30' => 0, 'aov' => 0, 'products' => 0, 'storefrontViews30' => 0];
        }
        $since = now()->subDays(30);
        $o = WaOrder::forWorkspace($wsId)->where('created_at', '>=', $since);
        $rev = (int) (clone $o)->sum('total_minor');
        $cnt = (int) (clone $o)->count();

        // Real storefront pageviews (S9) — sum the daily counter across this
        // workspace's shops for the last 30 days.
        $views30 = (int) \Illuminate\Support\Facades\DB::table('wa_storefront_views')
            ->whereIn('storefront_id', \App\Models\WaStorefront::where('workspace_id', $wsId)->pluck('id'))
            ->where('day', '>=', $since->toDateString())
            ->sum('views');

        return [
            'revenue30' => $rev,
            'orders30'  => $cnt,
            'aov'       => $cnt > 0 ? (int) round($rev / $cnt) : 0,
            'products'  => WaProduct::forWorkspace($wsId)->count(),
            'storefrontViews30' => $views30,
        ];
    }

    private function salesByDay(?int $wsId, int $days): array
    {
        if (!$wsId) return ['labels' => [], 'revenue' => [], 'orders' => []];
        $rows = WaOrder::forWorkspace($wsId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get(['total_minor', 'created_at']);
        $byDay = $rows->groupBy(fn ($r) => $r->created_at->toDateString());
        $labels = [];
        $revenue = [];
        $orders = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key = now()->subDays($i)->toDateString();
            $rs = $byDay->get($key, collect());
            $labels[] = now()->subDays($i)->format('M d');
            $revenue[] = (int) ($rs->sum('total_minor') / 100);
            $orders[] = $rs->count();
        }
        return compact('labels', 'revenue', 'orders');
    }

    private function topProducts(?int $wsId): array
    {
        if (!$wsId) return [];
        $orders = WaOrder::forWorkspace($wsId)->where('created_at', '>=', now()->subDays(30))->get();
        $counts = [];
        foreach ($orders as $o) {
            foreach (($o->items_json ?? []) as $item) {
                $name = $item['name'] ?? 'Unknown';
                $counts[$name] = ($counts[$name] ?? 0) + (int) ($item['qty'] ?? 1);
            }
        }
        arsort($counts);
        return array_slice(array_map(fn ($name, $qty) => ['name' => $name, 'qty' => $qty], array_keys($counts), $counts), 0, 5);
    }

    private function recentOrders(?int $wsId)
    {
        if (!$wsId) return collect();
        return WaOrder::forWorkspace($wsId)->orderByDesc('created_at')->limit(8)->get();
    }
}
