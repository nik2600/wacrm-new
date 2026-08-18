<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Package;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialController extends Controller
{
    public function index(Request $request): View
    {
        $window = $request->query('window', '30d');
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        return view('admin.financial.index', [
            'window'        => $window,
            'kpis'          => $this->kpis($from, $to, $prevFrom, $prevTo),
            'revenueDaily'  => $this->dailyRevenue($from, $to),
            'gateways'      => $this->gatewayBreakdown($from, $to),
            'topWorkspaces' => $this->topPayingWorkspaces($from, $to),
            'statusMix'     => $this->statusMix($from, $to),
            'recentOrders'  => $this->recentOrders(),
            'refundsDaily'  => $this->refundsDaily($from, $to),
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

    /** MRR + ARR + revenue + refunds + outstanding (pending) totals. */
    private function kpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $revenue = $this->paidRevenue($from, $to);
        $prevRev = $this->paidRevenue($prevFrom, $prevTo);

        // MRR — sum of the effective price (offer price when set) across
        // currently-active paid plans this month.
        $mrr = (float) Workspace::query()
            ->join('packages', 'packages.id', '=', 'workspaces.plan')
            ->where('packages.free', false)
            ->where('packages.status', true)
            ->sum(\DB::raw('CASE WHEN packages.offer_price IS NOT NULL AND packages.offer_price > 0 THEN packages.offer_price ELSE packages.plan_amount END'));

        $refunds = (float) Order::query()
            ->where('status', 'refunded')
            ->whereBetween('updated_at', [$from, $to])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));

        $outstanding = (float) Order::query()
            ->where('status', 'pending')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));

        return [
            'revenue'     => $this->card($revenue, $prevRev, \App\Support\FormatSettings::symbol(), 2),
            'mrr'         => ['value' => $mrr, 'display' => \App\Support\FormatSettings::symbol() . number_format($mrr, 2)],
            'arr'         => ['value' => $mrr * 12, 'display' => \App\Support\FormatSettings::symbol() . number_format($mrr * 12, 0)],
            'refunds'     => ['value' => $refunds, 'display' => \App\Support\FormatSettings::symbol() . number_format($refunds, 2)],
            'outstanding' => ['value' => $outstanding, 'display' => \App\Support\FormatSettings::symbol() . number_format($outstanding, 2)],
        ];
    }

    private function card(float $now, float $prev, string $prefix = '', int $decimals = 0): array
    {
        $delta = $prev > 0 ? round((($now - $prev) / $prev) * 100, 2) : ($now > 0 ? 100.0 : 0.0);
        return [
            'value'    => $now,
            'display'  => $prefix . number_format($now, $decimals),
            'delta'    => $delta,
            'positive' => $delta >= 0,
        ];
    }

    private function paidRevenue(Carbon $from, Carbon $to): float
    {
        return (float) Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
    }

    /** Daily revenue + daily refunds for the area-chart. */
    private function dailyRevenue(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select(DB::raw('DATE(paid_at) as d'), DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $labels = $series = [];
        for ($c = $from->copy(); $c->lte($to); $c->addDay()) {
            $key = $c->format('Y-m-d');
            $labels[] = $c->format('M j');
            $series[] = round((float) ($rows[$key]->s ?? 0), 2);
        }
        return [
            'labels' => $labels,
            'series' => $series,
            'total'  => \App\Support\FormatSettings::symbol() . number_format(array_sum($series), 2),
        ];
    }

    private function refundsDaily(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select(DB::raw('DATE(updated_at) as d'), DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->where('status', 'refunded')
            ->whereBetween('updated_at', [$from, $to])
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $labels = $series = [];
        for ($c = $from->copy(); $c->lte($to); $c->addDay()) {
            $key = $c->format('Y-m-d');
            $labels[] = $c->format('M j');
            $series[] = round((float) ($rows[$key]->s ?? 0), 2);
        }
        return ['labels' => $labels, 'series' => $series];
    }

    /** Gateway slug → revenue share. */
    private function gatewayBreakdown(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select('gateway_slug', DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('gateway_slug')
            ->groupBy('gateway_slug')->orderByDesc('s')->get();
        return [
            'labels' => $rows->pluck('gateway_slug')->map(fn ($s) => ucfirst((string) $s))->all(),
            'series' => $rows->pluck('s')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    /** Top 10 paying workspaces in window. */
    private function topPayingWorkspaces(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select('workspace_id', DB::raw('SUM(COALESCE(total_amount, amount)) as s'), DB::raw('COUNT(*) as n'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('workspace_id')
            ->orderByDesc('s')->limit(10)->get();

        $workspaces = Workspace::whereIn('id', $rows->pluck('workspace_id'))->get()->keyBy('id');
        return $rows->map(function ($r) use ($workspaces) {
            $ws = $workspaces->get($r->workspace_id);
            return [
                'name'   => $ws?->name ?? 'Workspace #' . $r->workspace_id,
                'orders' => (int) $r->n,
                'total'  => \App\Support\FormatSettings::symbol() . number_format((float) $r->s, 2),
                'href'   => $ws ? route('admin.workspaces.detail', $ws->id) : '#',
            ];
        })->all();
    }

    /** Order status distribution donut. */
    private function statusMix(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('status')->get();
        return [
            'labels' => $rows->pluck('status')->map(fn ($s) => ucfirst((string) $s))->all(),
            'series' => $rows->pluck('c')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** Last 8 orders for the activity panel. */
    private function recentOrders(): array
    {
        return Order::query()
            ->latest('created_at')
            ->limit(8)->get()
            ->map(function (Order $o) {
                $ws = $o->workspace_id ? Workspace::find($o->workspace_id) : null;
                $pkg = $o->package_id ? Package::find($o->package_id) : null;
                return [
                    'number'   => $o->order_number,
                    'workspace' => $ws?->name ?? 'Workspace #' . $o->workspace_id,
                    'plan'     => $pkg?->pname ?? '—',
                    // Use the admin's CONFIGURED currency symbol (same as the KPIs +
                    // top-workspaces on this page), not a hardcoded '$'. This cell was
                    // the only place that hardcoded the dollar sign, so Recent orders
                    // read "$1,178.82" while everything else read "₹1,178.82".
                    'amount'   => \App\Support\FormatSettings::symbol() . number_format((float) ($o->total_amount ?? $o->amount), 2),
                    'status'   => $o->status,
                    'date'     => $o->created_at->diffForHumans(),
                ];
            })->all();
    }
}
