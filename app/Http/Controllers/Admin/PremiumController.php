<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PremiumController extends Controller
{
    public function index(Request $request): View
    {
        $window = $request->query('window', '30d');
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        return view('admin.premium.index', [
            'window'        => $window,
            'kpis'          => $this->kpis($from, $to, $prevFrom, $prevTo),
            'planMix'       => $this->planMix(),
            'planRevenue'   => $this->planRevenue($from, $to),
            'upgradesDaily' => $this->upgradeFlow($from, $to),
            'planTable'     => $this->planTable($from, $to),
            'topPlans'      => $this->topPlans(),
        ]);
    }

    private function windowRange(string $window): array
    {
        $to   = now()->endOfDay();
        $days = match ($window) {
            '7d'   => 7,
            '90d'  => 90,
            '1y'   => 365,
            default => 30,
        };
        $from     = $to->copy()->subDays($days)->startOfDay();
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days)->startOfDay();
        return [$from, $to, $prevFrom, $prevTo];
    }

    private function kpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $paidWorkspaces = $this->paidWorkspaceCount();
        $freeWorkspaces = Workspace::query()->count() - $paidWorkspaces;

        // Trial conversion = paid orders in window / signups in window.
        $signups = User::query()->whereBetween('created_at', [$from, $to])->count();
        $paid    = Order::query()->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->distinct('workspace_id')->count('workspace_id');
        $conv    = $signups > 0 ? round($paid / $signups * 100, 2) : 0.0;

        $prevSignups = User::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $prevPaid    = Order::query()->where('status', 'paid')
            ->whereBetween('paid_at', [$prevFrom, $prevTo])
            ->distinct('workspace_id')->count('workspace_id');
        $prevConv    = $prevSignups > 0 ? round($prevPaid / $prevSignups * 100, 2) : 0.0;

        // ARPU = MRR / paid_workspaces.
        $mrr = (float) Workspace::query()
            ->join('packages', 'packages.id', '=', 'workspaces.plan')
            ->where('packages.free', false)
            ->where('packages.status', true)
            // Effective price = offer price when set, else plan_amount.
            ->sum(\DB::raw('CASE WHEN packages.offer_price IS NOT NULL AND packages.offer_price > 0 THEN packages.offer_price ELSE packages.plan_amount END'));
        $arpu = $paidWorkspaces > 0 ? $mrr / $paidWorkspaces : 0;

        return [
            'paid'   => ['display' => number_format($paidWorkspaces)],
            'free'   => ['display' => number_format($freeWorkspaces)],
            'arpu'   => ['display' => \App\Support\FormatSettings::symbol() . number_format($arpu, 2)],
            'conv'   => $this->card($conv, $prevConv, '', 2, '%'),
        ];
    }

    private function card(float $now, float $prev, string $prefix = '', int $decimals = 0, string $suffix = ''): array
    {
        $delta = $prev > 0 ? round((($now - $prev) / $prev) * 100, 2) : ($now > 0 ? 100.0 : 0.0);
        return [
            'value'    => $now,
            'display'  => $prefix . number_format($now, $decimals) . $suffix,
            'delta'    => $delta,
            'positive' => $delta >= 0,
        ];
    }

    private function paidWorkspaceCount(): int
    {
        $paidPlanIds = Package::query()->where('free', false)->pluck('id')->all();
        if (!$paidPlanIds) return 0;
        return Workspace::query()->whereIn('plan', $paidPlanIds)->count();
    }

    /** Plan distribution donut — workspaces per plan name. */
    private function planMix(): array
    {
        $rows = DB::table('workspaces')
            ->leftJoin('packages', 'packages.id', '=', 'workspaces.plan')
            ->select(DB::raw('COALESCE(packages.pname, "Free") as plan_name'), DB::raw('COUNT(*) as c'))
            ->groupBy('plan_name')->orderByDesc('c')->get();
        return [
            'labels' => $rows->pluck('plan_name')->all(),
            'series' => $rows->pluck('c')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** Revenue contribution per plan (bar chart). */
    private function planRevenue(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('orders')
            ->leftJoin('packages', 'packages.id', '=', 'orders.package_id')
            ->select(DB::raw('COALESCE(packages.pname, "Unknown") as plan_name'),
                     DB::raw('SUM(COALESCE(orders.total_amount, orders.amount)) as s'))
            ->where('orders.status', 'paid')
            ->whereBetween('orders.paid_at', [$from, $to])
            ->groupBy('plan_name')->orderByDesc('s')->get();
        return [
            'labels' => $rows->pluck('plan_name')->all(),
            'series' => $rows->pluck('s')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    /** Daily new-paid signups for the area-chart. */
    private function upgradeFlow(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('orders')
            ->select(DB::raw('DATE(paid_at) as d'), DB::raw('COUNT(DISTINCT workspace_id) as c'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $labels = $series = [];
        for ($c = $from->copy(); $c->lte($to); $c->addDay()) {
            $key = $c->format('Y-m-d');
            $labels[] = $c->format('M j');
            $series[] = (int) ($rows[$key]->c ?? 0);
        }
        return ['labels' => $labels, 'series' => $series];
    }

    /** Table — every paid package with workspace count + MRR. */
    private function planTable(Carbon $from, Carbon $to): array
    {
        $packages = Package::query()->plans()->orderByDesc('plan_amount')->get();
        $wsCounts = DB::table('workspaces')->select('plan', DB::raw('COUNT(*) as c'))
            ->groupBy('plan')->pluck('c', 'plan');

        $revWindow = DB::table('orders')->select('package_id', DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('package_id')->pluck('s', 'package_id');

        return $packages->map(function (Package $p) use ($wsCounts, $revWindow) {
            $count = (int) ($wsCounts[$p->id] ?? 0);
            $mrr   = $p->chargeableAmount() * $count;
            return [
                'name'     => $p->pname,
                'price'    => \App\Support\FormatSettings::symbol() . number_format($p->chargeableAmount(), 2),
                'duration' => $p->plan_duration . ' ' . $p->plan_unit,
                'free'     => (bool) ($p->free ?? false),
                'status'   => (bool) ($p->status ?? false),
                'count'    => $count,
                'mrr'      => \App\Support\FormatSettings::symbol() . number_format($mrr, 2),
                'revenue'  => \App\Support\FormatSettings::symbol() . number_format((float) ($revWindow[$p->id] ?? 0), 2),
                'edit'     => route('admin.packages.edit', $p->id),
            ];
        })->all();
    }

    /** Top 5 most-purchased plans in the last 90 days. */
    private function topPlans(): array
    {
        $cutoff = now()->subDays(90);
        return DB::table('orders')
            ->leftJoin('packages', 'packages.id', '=', 'orders.package_id')
            ->select(DB::raw('COALESCE(packages.pname, "Unknown") as plan_name'),
                     DB::raw('COUNT(*) as c'),
                     DB::raw('SUM(COALESCE(orders.total_amount, orders.amount)) as s'))
            ->where('orders.status', 'paid')
            ->where('orders.paid_at', '>=', $cutoff)
            ->groupBy('plan_name')->orderByDesc('c')->limit(5)
            ->get()->map(fn ($r) => [
                'name'    => $r->plan_name,
                'orders'  => (int) $r->c,
                'revenue' => \App\Support\FormatSettings::symbol() . number_format((float) $r->s, 2),
            ])->all();
    }
}
