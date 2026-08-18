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

class BillingHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $window  = $request->query('window', '90d');
        $statusF = (string) $request->query('status', 'all');
        $gatewayF = $request->query('gateway');
        $q       = trim((string) $request->query('q', ''));
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        $query = Order::query()->whereBetween('created_at', [$from, $to]);
        if ($statusF !== 'all')   $query->where('status', $statusF);
        if ($gatewayF)            $query->where('gateway_slug', $gatewayF);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                  ->orWhere('customer_name', 'like', "%{$q}%")
                  ->orWhere('customer_email', 'like', "%{$q}%")
                  ->orWhereHas('workspace', function ($wq) use ($q) {
                      $wq->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%");
                  });
            });
        }
        $orders = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        return view('admin.billing-history.index', [
            'window'    => $window,
            'statusF'   => $statusF,
            'gatewayF'  => $gatewayF,
            'q'         => $q,
            'orders'    => $orders,
            'gateways'  => $this->gatewaySlugs(),
            'stats'     => $this->kpisIndex($from, $to, $prevFrom, $prevTo),
            'trend'     => $this->trendDaily($from, $to),
        ]);
    }

    public function analytics(Request $request): View
    {
        $window = $request->query('window', '90d');
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        return view('admin.billing-history.analytics', [
            'window'        => $window,
            'stats'         => $this->kpisAnalytics($from, $to, $prevFrom, $prevTo),
            'trend'         => $this->trendDailyExt($from, $to),
            'gatewayMix'    => $this->gatewayBreakdown($from, $to),
            'statusMix'     => $this->statusBreakdown($from, $to),
            'topWorkspaces' => $this->topPayers($from, $to),
        ]);
    }

    private function windowRange(string $window): array
    {
        $to   = now()->endOfDay();
        $days = match ($window) {
            '7d'   => 7,
            '30d'  => 30,
            '1y'   => 365,
            'all'  => 365 * 5,
            default => 90,
        };
        $from     = $to->copy()->subDays($days)->startOfDay();
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days)->startOfDay();
        return [$from, $to, $prevFrom, $prevTo];
    }

    private function gatewaySlugs(): array
    {
        return Order::query()
            ->whereNotNull('gateway_slug')
            ->distinct()->orderBy('gateway_slug')
            ->pluck('gateway_slug')->all();
    }

    /** Helper — money sum across an order set scoped by a status. */
    private function sumStatus(Carbon $from, Carbon $to, string $status, ?string $dateCol = null): float
    {
        $col = $dateCol ?: ($status === 'paid' ? 'paid_at' : 'updated_at');
        $q = Order::query()->where('status', $status);
        if ($col === 'paid_at') $q->whereBetween('paid_at', [$from, $to]);
        else                    $q->whereBetween('updated_at', [$from, $to]);
        return (float) $q->sum(DB::raw('COALESCE(total_amount, amount)'));
    }

    private function countStatus(Carbon $from, Carbon $to, string $status): int
    {
        $col = $status === 'paid' ? 'paid_at' : 'updated_at';
        $q = Order::query()->where('status', $status);
        if ($col === 'paid_at') $q->whereBetween('paid_at', [$from, $to]);
        else                    $q->whereBetween('updated_at', [$from, $to]);
        return $q->count();
    }

    /** 5-card KPI strip on the index page. */
    private function kpisIndex(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $gross      = $this->sumStatus($from, $to, 'paid');
        $prevGross  = $this->sumStatus($prevFrom, $prevTo, 'paid');

        $charges    = $this->countStatus($from, $to, 'paid');
        $failed     = $this->countStatus($from, $to, 'failed');
        $totalAuth  = $charges + $failed;
        $successPct = $totalAuth > 0 ? round($charges / $totalAuth * 100, 1) : 0.0;

        $refundsCount = $this->countStatus($from, $to, 'refunded');
        $refundsAmt   = $this->sumStatus($from, $to, 'refunded');

        return [
            'gross'        => \App\Support\FormatSettings::symbol() . number_format($gross, 0),
            'grossDelta'   => $this->delta($gross, $prevGross),
            'charges'      => number_format($charges),
            'failed'       => number_format($failed),
            'successPct'   => $successPct,
            'refunds'      => number_format($refundsCount),
            'refundsAmt'   => \App\Support\FormatSettings::symbol() . number_format($refundsAmt, 0),
            'chargebacks'  => 0,  // schema has no dispute state yet — keep label so UI doesn't shift.
            'chargebacksAmt' => \App\Support\FormatSettings::symbol() . '0',
        ];
    }

    private function delta(float $now, float $prev): array
    {
        if ($prev <= 0) return ['pct' => $now > 0 ? 100.0 : 0.0, 'positive' => true];
        $pct = round((($now - $prev) / $prev) * 100, 1);
        return ['pct' => $pct, 'positive' => $pct >= 0];
    }

    /** 6-card KPI strip on analytics. */
    private function kpisAnalytics(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $gross    = $this->sumStatus($from, $to, 'paid');
        $refunds  = $this->sumStatus($from, $to, 'refunded');
        $failedC  = $this->countStatus($from, $to, 'failed');
        $paidC    = $this->countStatus($from, $to, 'paid');
        $total    = $paidC + $failedC;
        $successPct = $total > 0 ? round($paidC / $total * 100, 1) : 0;

        return [
            'gross'        => \App\Support\FormatSettings::symbol() . number_format($gross, 0),
            'grossDelta'   => $this->delta($gross, $this->sumStatus($prevFrom, $prevTo, 'paid')),
            'net'          => \App\Support\FormatSettings::symbol() . number_format(max(0, $gross - $refunds), 0),
            'netPct'       => $gross > 0 ? round((1 - $refunds / $gross) * 100, 1) : 0,
            'successPct'   => $successPct,
            'totalCharges' => $paidC + $failedC,
            'paidCount'    => $paidC,
            'failedAmount' => \App\Support\FormatSettings::symbol() . number_format($this->sumStatus($from, $to, 'failed'), 0),
            'failedCount'  => $failedC,
            'refundsAmt'   => \App\Support\FormatSettings::symbol() . number_format($refunds, 0),
            'refundsCount' => $this->countStatus($from, $to, 'refunded'),
            'chargebacksAmt'   => \App\Support\FormatSettings::symbol() . '0',
            'chargebacksCount' => 0,
        ];
    }

    /**
     * Picks a sensible bucket size for the window:
     *   ≤ 31 days  → daily
     *   ≤ 100 days → weekly (Mon-anchored)
     *   ≤ 400 days → monthly
     *   else       → quarterly
     *
     * Returns the bucket list ({ start, end, label }) so the trend builders
     * can SUM-aggregate over arbitrary stride without crowding the X-axis.
     */
    private function buckets(Carbon $from, Carbon $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $buckets = [];
        if ($days <= 31) {
            for ($c = $from->copy(); $c->lte($to); $c->addDay()) {
                $buckets[] = ['start' => $c->copy(), 'end' => $c->copy()->endOfDay(), 'label' => $c->format('M j')];
            }
        } elseif ($days <= 100) {
            $cursor = $from->copy()->startOfWeek();
            while ($cursor->lte($to)) {
                $end = $cursor->copy()->endOfWeek();
                if ($end->greaterThan($to)) $end = $to->copy();
                $buckets[] = ['start' => $cursor->copy(), 'end' => $end, 'label' => 'wk ' . $cursor->format('M j')];
                $cursor->addWeek();
            }
        } elseif ($days <= 400) {
            $cursor = $from->copy()->startOfMonth();
            while ($cursor->lte($to)) {
                $end = $cursor->copy()->endOfMonth();
                if ($end->greaterThan($to)) $end = $to->copy();
                $buckets[] = ['start' => $cursor->copy(), 'end' => $end, 'label' => $cursor->format('M Y')];
                $cursor->addMonth();
            }
        } else {
            $cursor = $from->copy()->firstOfQuarter();
            while ($cursor->lte($to)) {
                $end = $cursor->copy()->endOfQuarter();
                if ($end->greaterThan($to)) $end = $to->copy();
                $buckets[] = ['start' => $cursor->copy(), 'end' => $end, 'label' => 'Q' . $cursor->quarter . ' ' . $cursor->format('Y')];
                $cursor->addQuarter();
            }
        }
        return $buckets;
    }

    /** Sum a numeric column from the orders table across a status + date column + window. */
    private function bucketSum(string $status, string $dateCol, Carbon $start, Carbon $end): float
    {
        return (float) Order::query()
            ->where('status', $status)
            ->whereBetween($dateCol, [$start, $end])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
    }

    /** Daily charges vs refunds for the trend chart — auto-bucketed by window length. */
    private function trendDaily(Carbon $from, Carbon $to): array
    {
        $labels = $charges = $refunds = [];
        foreach ($this->buckets($from, $to) as $b) {
            $labels[]  = $b['label'];
            $charges[] = round($this->bucketSum('paid',     'paid_at',    $b['start'], $b['end']), 2);
            $refunds[] = round($this->bucketSum('refunded', 'updated_at', $b['start'], $b['end']), 2);
        }
        return ['labels' => $labels, 'charges' => $charges, 'refunds' => $refunds];
    }

    /** Trend plus failed bucket for analytics. */
    private function trendDailyExt(Carbon $from, Carbon $to): array
    {
        $base = $this->trendDaily($from, $to);
        $failed = [];
        foreach ($this->buckets($from, $to) as $b) {
            $failed[] = round($this->bucketSum('failed', 'updated_at', $b['start'], $b['end']), 2);
        }
        $base['failed'] = $failed;
        return $base;
    }

    private function gatewayBreakdown(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select('gateway_slug', DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->where('status', 'paid')->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('gateway_slug')
            ->groupBy('gateway_slug')->orderByDesc('s')->get();
        return [
            'labels' => $rows->pluck('gateway_slug')->map(fn ($s) => ucfirst((string) $s))->all(),
            'series' => $rows->pluck('s')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    private function statusBreakdown(Carbon $from, Carbon $to): array
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

    private function topPayers(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select('workspace_id', DB::raw('SUM(COALESCE(total_amount, amount)) as s'), DB::raw('COUNT(*) as n'))
            ->where('status', 'paid')->whereBetween('paid_at', [$from, $to])
            ->groupBy('workspace_id')->orderByDesc('s')->limit(10)->get();
        $ws = Workspace::whereIn('id', $rows->pluck('workspace_id'))->get()->keyBy('id');
        return $rows->map(fn ($r) => [
            'name'    => $ws->get($r->workspace_id)?->name ?? 'Workspace #' . $r->workspace_id,
            'orders'  => (int) $r->n,
            'total'   => \App\Support\FormatSettings::symbol() . number_format((float) $r->s, 2),
            'href'    => $ws->get($r->workspace_id) ? route('admin.workspaces.detail', $r->workspace_id) : '#',
        ])->all();
    }
}
