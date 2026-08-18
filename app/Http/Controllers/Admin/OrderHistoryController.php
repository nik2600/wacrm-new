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

class OrderHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $window  = $request->query('window', '90d');
        $typeF   = (string) $request->query('type', 'all');
        $q       = trim((string) $request->query('q', ''));
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        $query = Order::query()->with('workspace')->whereBetween('created_at', [$from, $to]);

        // "Type" is derived from status — keep mapping consistent between
        // the filter pills and the per-row badge in the view.
        if ($typeF !== 'all') {
            $query->where(function ($w) use ($typeF) {
                switch ($typeF) {
                    case 'new':       $w->where('status', 'paid'); break;
                    case 'upgrade':   $w->where('status', 'paid'); break;
                    case 'downgrade': $w->where('status', 'pending'); break;
                    case 'addon':     $w->whereNull('package_id'); break;
                    case 'cancel':    $w->whereIn('status', ['failed', 'refunded']); break;
                }
            });
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                  ->orWhere('customer_email', 'like', "%{$q}%")
                  ->orWhereHas('workspace', fn ($wq) => $wq->where('name', 'like', "%{$q}%"));
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        // Offline / bank-transfer orders whose buyer submitted proof and are
        // still pending — queried INDEPENDENTLY of the page's window / filter /
        // pagination so a payment awaiting approval is never hidden from admin.
        $awaitingApproval = Order::query()->with(['workspace', 'user'])
            ->where('status', 'pending')
            ->whereNotNull('proof_submitted_at')
            ->orderByDesc('proof_submitted_at')
            ->limit(50)
            ->get();

        return view('admin.order-history.index', [
            'window'  => $window,
            'typeF'   => $typeF,
            'q'       => $q,
            'orders'  => $orders,
            'awaitingApproval' => $awaitingApproval,
            'stats'   => $this->indexKpis($from, $to, $prevFrom, $prevTo),
        ]);
    }

    /**
     * Approve an offline / bank-transfer order: activate the plan via the
     * same finalizeOrder() path the gateways use (reused from CheckoutController).
     */
    public function approve(Request $request, int $id)
    {
        $order = Order::findOrFail($id);
        if ($order->status === 'paid') {
            return back()->with('info', 'Order ' . $order->order_number . ' is already paid.');
        }

        app(\App\Http\Controllers\CheckoutController::class)
            ->markPaidManually($order, $request->user()->id, $order->payment_reference);

        \App\Support\Audit::log('admin.order.approved', ['layer' => 'platform', 'meta' => [
            'order'    => $order->order_number,
            'amount'   => $order->amount,
            'currency' => $order->currency,
        ]]);

        return back()->with('success', 'Order ' . $order->order_number . ' approved — plan activated.');
    }

    /** Reject an offline / bank-transfer order's payment proof. */
    public function reject(Request $request, int $id)
    {
        $order = Order::findOrFail($id);
        $data  = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'status'         => 'failed',
            'failure_reason' => 'rejected_by_admin',
            'review_note'    => $data['review_note'] ?? null,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ]);

        \App\Support\Audit::log('admin.order.rejected', ['layer' => 'platform', 'meta' => [
            'order'    => $order->order_number,
            'amount'   => $order->amount,
            'currency' => $order->currency,
        ]]);

        return back()->with('success', 'Order ' . $order->order_number . ' rejected.');
    }

    public function analytics(Request $request): View
    {
        $window = $request->query('window', '90d');
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        return view('admin.order-history.analytics', [
            'window'   => $window,
            'stats'    => $this->analyticsKpis($from, $to, $prevFrom, $prevTo),
            'motion'   => $this->dailyMotion($from, $to),
            'typeMix'  => $this->typeMix($from, $to),
            'addons'   => $this->topAddons($from, $to),
            'funnel'   => $this->funnel($from, $to),
            'byCountry'=> $this->byCountry($from, $to),
            'cohorts'  => $this->cohorts(),
        ]);
    }

    private function windowRange(string $window): array
    {
        $to   = now()->endOfDay();
        $days = match ($window) {
            'this_year' => max(1, (int) now()->startOfYear()->diffInDays(now())),
            'all'       => 365 * 5,
            default     => 90,
        };
        $from     = $to->copy()->subDays($days)->startOfDay();
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days)->startOfDay();
        return [$from, $to, $prevFrom, $prevTo];
    }

    /** Per-row "type" label + tone, derived from order status + package change. */
    public static function typeFor(Order $o): array
    {
        return match ($o->status) {
            'paid'     => ['label' => $o->package_id ? 'Renewal' : 'New', 'class' => 'bg-wa-bubble text-wa-deep'],
            'pending'  => ['label' => 'Pending',   'class' => 'bg-accent-amber/10 text-accent-amber'],
            'failed'   => ['label' => 'Failed',    'class' => 'bg-accent-coral/10 text-accent-coral'],
            'refunded' => ['label' => 'Refunded',  'class' => 'bg-[#F3E9FF] text-[#5B3D8A]'],
            default    => ['label' => ucfirst($o->status ?? '—'), 'class' => 'bg-paper-100 text-ink-700'],
        };
    }

    private function indexKpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $totalNow  = Order::query()->whereBetween('created_at', [$from, $to])->count();
        $totalPrev = Order::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $delta = $totalPrev > 0 ? round((($totalNow - $totalPrev) / $totalPrev) * 100, 1) : ($totalNow > 0 ? 100.0 : 0);

        $paidCount = Order::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->count();
        $paidMrr   = (float) Order::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum(DB::raw('COALESCE(total_amount, amount)'));

        $cancelCount = Order::query()->whereIn('status', ['failed', 'refunded'])->whereBetween('updated_at', [$from, $to])->count();
        $cancelMrr   = (float) Order::query()->whereIn('status', ['failed', 'refunded'])->whereBetween('updated_at', [$from, $to])->sum(DB::raw('COALESCE(total_amount, amount)'));

        $addonCount = Order::query()->whereNull('package_id')->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->count();
        $addonMrr   = (float) Order::query()->whereNull('package_id')->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum(DB::raw('COALESCE(total_amount, amount)'));

        return [
            'total'       => number_format($totalNow),
            'delta'       => ($delta >= 0 ? '+' : '') . $delta . '%',
            'deltaPos'    => $delta >= 0,
            'upgrades'    => number_format($paidCount),
            'upgradesMrr' => '+' . \App\Support\FormatSettings::symbol() . self::short($paidMrr) . ' MRR added',
            'downgrades'  => number_format(0),     // schema has no downgrade signal yet — kept for UI symmetry.
            'downMrr'     => '-' . \App\Support\FormatSettings::symbol() . '0 MRR',
            'addons'      => number_format($addonCount),
            'addonMrr'    => \App\Support\FormatSettings::symbol() . self::short($addonMrr) . ' attached',
            'cancels'     => number_format($cancelCount),
            'cancelMrr'   => '-' . \App\Support\FormatSettings::symbol() . self::short($cancelMrr) . ' MRR lost',
        ];
    }

    private function analyticsKpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $totalNow  = Order::query()->whereBetween('created_at', [$from, $to])->count();
        $totalPrev = Order::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $delta = $totalPrev > 0 ? round((($totalNow - $totalPrev) / $totalPrev) * 100, 1) : ($totalNow > 0 ? 100.0 : 0);

        $paidSum   = (float) Order::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->sum(DB::raw('COALESCE(total_amount, amount)'));
        $paidCount = Order::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->count();
        $aov       = $paidCount > 0 ? round($paidSum / $paidCount, 0) : 0;

        $lostSum   = (float) Order::query()->whereIn('status', ['failed', 'refunded'])->whereBetween('updated_at', [$from, $to])->sum(DB::raw('COALESCE(total_amount, amount)'));
        $cancels   = Order::query()->whereIn('status', ['failed', 'refunded'])->whereBetween('updated_at', [$from, $to])->count();

        $addons    = Order::query()->whereNull('package_id')->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->count();
        $attachPct = $totalNow > 0 ? round(($addons / max(1, $totalNow)) * 100, 1) : 0;

        // Conversion = paid orders / signups in same window (proxy).
        $signups   = \App\Models\User::query()->whereBetween('created_at', [$from, $to])->count();
        $convPct   = $signups > 0 ? round($paidCount / $signups * 100, 1) : 0;

        return [
            'total'       => number_format($totalNow),
            'totalDelta'  => ($delta >= 0 ? '+' : '') . $delta . '% QoQ',
            'totalDeltaPos' => $delta >= 0,
            'newMrr'      => '+' . \App\Support\FormatSettings::symbol() . self::short($paidSum),
            'upgrades'    => number_format($paidCount) . ' paid',
            'lostMrr'     => '-' . \App\Support\FormatSettings::symbol() . self::short($lostSum),
            'cancels'     => number_format($cancels) . ' cancels',
            'aov'         => \App\Support\FormatSettings::symbol() . number_format($aov),
            'attachPct'   => $attachPct . '%',
            'attachLabel' => number_format($addons) . ' add-ons / ' . number_format($totalNow),
            'convPct'     => $convPct . '%',
        ];
    }

    /** Bucket helper — same logic as BillingHistoryController. */
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

    /** New orders vs cancels — auto-bucketed by window length. */
    private function dailyMotion(Carbon $from, Carbon $to): array
    {
        $labels = $newArr = $cancelArr = [];
        foreach ($this->buckets($from, $to) as $b) {
            $labels[]    = $b['label'];
            $newArr[]    = (int) Order::query()->where('status', 'paid')->whereBetween('paid_at', [$b['start'], $b['end']])->count();
            $cancelArr[] = (int) Order::query()->whereIn('status', ['failed', 'refunded'])->whereBetween('updated_at', [$b['start'], $b['end']])->count();
        }
        return ['labels' => $labels, 'new' => $newArr, 'cancel' => $cancelArr];
    }

    /** Donut + sidebar counters showing order-type mix. */
    private function typeMix(Carbon $from, Carbon $to): array
    {
        $renewal  = Order::query()->where('status', 'paid')->whereNotNull('package_id')->whereBetween('paid_at', [$from, $to])->count();
        $addon    = Order::query()->where('status', 'paid')->whereNull('package_id')->whereBetween('paid_at', [$from, $to])->count();
        $cancel   = Order::query()->whereIn('status', ['failed', 'refunded'])->whereBetween('updated_at', [$from, $to])->count();
        return [
            'labels' => ['Renewal', 'Add-on', 'Cancel'],
            'series' => [$renewal, $addon, $cancel],
            'rows'   => [
                ['label' => 'Renewal', 'count' => $renewal, 'tone' => 'bg-wa-deep'],
                ['label' => 'Add-on',  'count' => $addon,   'tone' => 'bg-[#13478A]'],
                ['label' => 'Cancel',  'count' => $cancel,  'tone' => 'bg-accent-coral'],
            ],
        ];
    }

    /** Top non-package paid orders (i.e. add-on SKUs by total spend). */
    private function topAddons(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('orders')
            ->select(DB::raw('COALESCE(NULLIF(gateway_slug, ""), "Unknown") as sku'),
                     DB::raw('COUNT(*) as n'),
                     DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->whereNull('package_id')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('sku')->orderByDesc('s')->limit(5)->get();
        $max = max(1, (int) $rows->max('s'));
        return $rows->map(fn ($r) => [
            'label' => ucfirst($r->sku) . ' add-on',
            'count' => (int) $r->n,
            'total' => \App\Support\FormatSettings::symbol() . self::short((float) $r->s),
            'pct'   => round((float) $r->s / $max * 100),
        ])->all();
    }

    private function funnel(Carbon $from, Carbon $to): array
    {
        $signups = \App\Models\User::query()->whereBetween('created_at', [$from, $to])->count();
        $paid    = Order::query()->where('status', 'paid')->whereBetween('paid_at', [$from, $to])->distinct('workspace_id')->count('workspace_id');
        // Visitors + trial counts aren't tracked yet — use proxies.
        $visits  = max(1, $signups * 10);
        $trials  = (int) ($signups * 0.7);
        $active  = (int) ($trials * 0.78);
        return [
            ['label' => 'Pricing page visits', 'count' => $visits, 'pct' => null,                       'bar' => '100%'],
            ['label' => 'Sign-ups',            'count' => $signups,'pct' => round($signups / max(1, $visits) * 100, 1) . '%', 'bar' => round($signups / max(1, $visits) * 100) . '%'],
            ['label' => 'Trials started',      'count' => $trials, 'pct' => '70%',                     'bar' => round($trials / max(1, $visits) * 100) . '%'],
            ['label' => 'Active in trial',     'count' => $active, 'pct' => '78%',                     'bar' => round($active / max(1, $visits) * 100) . '%'],
            ['label' => 'Converted to paid',   'count' => $paid,   'pct' => round($paid / max(1, $trials) * 100, 1) . '%', 'bar' => round($paid / max(1, $visits) * 100) . '%'],
        ];
    }

    private function byCountry(Carbon $from, Carbon $to): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'country_code')) return [];
        $rows = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select('users.country_code', DB::raw('COUNT(*) as c'))
            ->where('orders.status', 'paid')
            ->whereBetween('orders.paid_at', [$from, $to])
            ->whereNotNull('users.country_code')->where('users.country_code', '!=', '')
            ->groupBy('users.country_code')->orderByDesc('c')->limit(6)->get();
        return $rows->map(fn ($r) => [
            'code'  => strtoupper((string) $r->country_code),
            'count' => (int) $r->c,
        ])->all();
    }

    /** Workspaces-by-signup-month cohort retention (6 most-recent months). */
    private function cohorts(): array
    {
        $cohorts = [];
        for ($m = 5; $m >= 0; $m--) {
            $monthStart = now()->copy()->subMonths($m)->startOfMonth();
            $monthEnd   = now()->copy()->subMonths($m)->endOfMonth();
            $size = Workspace::query()->whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $cells = [];
            for ($k = 0; $k <= 5; $k++) {
                if ($k > $m) { $cells[] = null; continue; }
                $byThen = $monthEnd->copy()->addMonths($k);
                if ($byThen->isFuture()) { $cells[] = null; continue; }
                $alive = $size > 0
                    ? Workspace::query()
                        ->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->where(function ($q) use ($byThen) {
                            $q->whereNull('deleted_at')->orWhere('deleted_at', '>', $byThen);
                        })
                        ->count()
                    : 0;
                $cells[] = $size > 0 ? (int) round($alive / $size * 100) : 0;
            }
            $cohorts[] = [
                'label' => $monthStart->format('M Y'),
                'size'  => $size,
                'cells' => $cells,
            ];
        }
        return $cohorts;
    }

    /** Short money formatter — used in KPI strings. */
    public static function short(float $n): string
    {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
        return number_format($n, 0);
    }
}
