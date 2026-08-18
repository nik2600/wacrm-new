<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\Message;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OverviewController extends Controller
{
    public function index(Request $request): View
    {
        $window = $request->query('window', '7d');
        [$from, $to, $prevFrom, $prevTo] = $this->windowRange($window);

        return view('admin.dashboard.index', [
            'window'      => $window,
            'kpis'        => $this->kpis($from, $to, $prevFrom, $prevTo),
            'revenue'     => $this->revenueSeries($from, $to),
            'countries'   => $this->sessionsByCountry($from, $to),
            'region'      => $this->salesByRegion($from, $to),
            'platform'    => $this->salesByPlatform($from, $to),
            'plans'       => $this->planUserBreakdown(),
            'workspaces'  => $this->workspaceActivity(),
            'alerts'      => $this->alerts(),
        ]);
    }

    /** Returns [from, to, prevFrom, prevTo] Carbon instances for the requested window. */
    private function windowRange(string $window): array
    {
        $to   = now()->endOfDay();
        $days = match ($window) {
            '24h'  => 1,
            '30d'  => 30,
            '90d'  => 90,
            '1y'   => 365,
            default => 7,
        };
        $from     = $to->copy()->subDays($days)->startOfDay();
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days)->startOfDay();
        return [$from, $to, $prevFrom, $prevTo];
    }

    /** 4 headline KPI cards: total income, profit, total views (messages sent), conversion rate. */
    private function kpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $income      = $this->paidRevenue($from, $to);
        $prevIncome  = $this->paidRevenue($prevFrom, $prevTo);

        // Rough profit estimate — until a real cost table exists, treat profit as 32% of income.
        // Replace the multiplier once Cost/Refund tables land.
        $profit     = round($income     * 0.32, 2);
        $prevProfit = round($prevIncome * 0.32, 2);

        $views      = $this->messagesSent($from, $to);
        $prevViews  = $this->messagesSent($prevFrom, $prevTo);

        $signups       = User::query()->whereBetween('created_at', [$from, $to])->count();
        $paidWorkspaces = Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->distinct('workspace_id')->count('workspace_id');
        $conversion = $signups > 0 ? round($paidWorkspaces / $signups * 100, 2) : 0.0;

        $prevSignups       = User::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count();
        $prevPaidWorkspaces = Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$prevFrom, $prevTo])
            ->distinct('workspace_id')->count('workspace_id');
        $prevConversion = $prevSignups > 0 ? round($prevPaidWorkspaces / $prevSignups * 100, 2) : 0.0;

        $sym = $this->sym();
        return [
            'income'     => $this->card($income, $prevIncome, $sym, 2),
            'profit'     => $this->card($profit, $prevProfit, $sym, 2),
            'views'      => $this->card($views,  $prevViews,  '',  0),
            'conversion' => $this->card($conversion, $prevConversion, '', 2, '%'),
        ];
    }

    /** Build a single KPI card payload (value + formatted display + delta percent + delta polarity). */
    private function card(float|int $now, float|int $prev, string $prefix = '', int $decimals = 0, string $suffix = ''): array
    {
        $delta = 0.0;
        if ($prev > 0) {
            $delta = round((($now - $prev) / $prev) * 100, 2);
        } elseif ($now > 0) {
            $delta = 100.0;
        }
        return [
            'value'    => $now,
            'display'  => $prefix . number_format((float) $now, $decimals) . $suffix,
            'delta'    => $delta,
            'positive' => $delta >= 0,
        ];
    }

    /** Sum of paid orders' total_amount (or amount if total is null) in the window. */
    private function paidRevenue(Carbon $from, Carbon $to): float
    {
        return (float) Order::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum(DB::raw('COALESCE(total_amount, amount)'));
    }

    /** Total outbound messages (Message + InboxMessage) in window. */
    private function messagesSent(Carbon $from, Carbon $to): int
    {
        $m = Message::query()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $i = InboxMessage::query()
            ->where('direction', 'out')
            ->whereBetween('created_at', [$from, $to])
            ->count();
        return $m + $i;
    }

    /** 12-bucket revenue series — buckets adapt to window length. */
    private function revenueSeries(Carbon $from, Carbon $to): array
    {
        $diff = (int) $from->diffInDays($to);
        // Choose bucket size: tiny window → daily; longer → weekly/monthly.
        $buckets = 12;
        $stepDays = max(1, intdiv($diff, $buckets));
        $labels = $series = $target = [];

        $cursor = $from->copy();
        for ($i = 0; $i < $buckets; $i++) {
            $start = $cursor->copy();
            $end   = $cursor->copy()->addDays($stepDays)->subSecond();
            if ($end->greaterThan($to)) $end = $to;
            $rev = (float) Order::query()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])
                ->sum(DB::raw('COALESCE(total_amount, amount)'));
            $labels[] = $stepDays >= 28 ? $start->format('M Y') : $start->format('M j');
            $series[] = round($rev, 2);
            // Simple target — 10% growth on prior bucket; floor at 100.
            $target[] = max(100, round(($series[$i - 1] ?? $rev * 0.9) * 1.1, 2));
            $cursor->addDays($stepDays);
            if ($cursor->greaterThan($to)) break;
        }
        return [
            'labels'      => $labels,
            'series'      => $series,
            'target'      => $target,
            'total'       => $this->sym() . number_format(array_sum($series), 2),
            'totalTarget' => $this->sym() . number_format(array_sum($target), 2),
        ];
    }

    /** Active platform currency symbol (admin default_currency); '$' fallback. */
    private function sym(): string
    {
        return \App\Support\FormatSettings::symbol();
    }

    /** Sessions by country derived from User.country_code counts. Top 4. */
    private function sessionsByCountry(Carbon $from, Carbon $to): array
    {
        if (!Schema::hasColumn('users', 'country_code')) return [];
        $rows = User::query()
            ->select('country_code', DB::raw('COUNT(*) as c'))
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('country_code')->where('country_code', '!=', '')
            ->groupBy('country_code')
            ->orderByDesc('c')
            ->limit(4)->get();
        $total = max(1, $rows->sum('c'));
        return $rows->map(fn ($r) => [
            'code'    => strtoupper((string) $r->country_code),
            'name'    => $this->countryName((string) $r->country_code),
            'count'   => (int) $r->c,
            'percent' => round($r->c / $total * 100, 1),
        ])->all();
    }

    /** Minimal ISO-2 → display name map; falls back to the code. */
    private function countryName(string $code): string
    {
        $map = [
            'AU' => 'Australia', 'ID' => 'Indonesia', 'TH' => 'Thailand', 'DE' => 'Germany',
            'IN' => 'India',     'US' => 'United States', 'GB' => 'United Kingdom',
            'BR' => 'Brazil',    'PH' => 'Philippines',   'JP' => 'Japan',
        ];
        return $map[strtoupper($code)] ?? strtoupper($code);
    }

    /** Sales by region — radar chart axes. Heuristic: bucket country into a region. */
    private function salesByRegion(Carbon $from, Carbon $to): array
    {
        $regions = [
            'Europe'      => ['GB','DE','FR','IT','ES','NL','SE','NO','PL','RU','CH'],
            'Americas'    => ['US','CA','MX','BR','AR','CL','CO'],
            'Africa'      => ['ZA','NG','KE','EG','MA','GH'],
            'Middle East' => ['AE','SA','IL','TR','IR','QA','KW'],
            'Pacific'     => ['AU','NZ','FJ'],
            'Asia'        => ['IN','CN','JP','KR','ID','TH','VN','MY','PH','SG','BD','PK','LK'],
        ];
        $totals = array_fill_keys(array_keys($regions), 0);
        if (Schema::hasColumn('users', 'country_code')) {
            $rows = User::query()
                ->select('country_code', DB::raw('COUNT(*) as c'))
                ->whereBetween('created_at', [$from, $to])
                ->whereNotNull('country_code')->where('country_code', '!=', '')
                ->groupBy('country_code')->get();
            foreach ($rows as $r) {
                $code = strtoupper((string) $r->country_code);
                foreach ($regions as $reg => $codes) {
                    if (in_array($code, $codes, true)) { $totals[$reg] += (int) $r->c; break; }
                }
            }
        }
        return [
            'labels' => array_keys($totals),
            'series' => array_values($totals),
        ];
    }

    /** Sales-by-platform donut — top 3 gateway slugs from paid orders. */
    private function salesByPlatform(Carbon $from, Carbon $to): array
    {
        $rows = Order::query()
            ->select('gateway_slug', DB::raw('SUM(COALESCE(total_amount, amount)) as s'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('gateway_slug')
            ->groupBy('gateway_slug')
            ->orderByDesc('s')->limit(3)->get();
        if ($rows->isEmpty()) {
            return ['labels' => ['Stripe', 'Razorpay', 'PayPal'], 'series' => [0, 0, 0]];
        }
        return [
            'labels' => $rows->pluck('gateway_slug')->map(fn ($s) => ucfirst((string) $s))->all(),
            'series' => $rows->pluck('s')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    /** Registered users donut — split by plan tier (premium vs basic vs free). */
    private function planUserBreakdown(): array
    {
        $total = User::query()->count();
        $paidPlanIds = Package::query()->where('free', false)->pluck('id')->all();
        $premium = $paidPlanIds
            ? Workspace::query()->whereIn('plan', $paidPlanIds)->distinct('owner_user_id')->count('owner_user_id')
            : 0;
        $basic = max(0, $total - $premium);
        return [
            'total'        => $total,
            'totalDisplay' => number_format($total),
            'premium'      => $premium,
            'basic'        => $basic,
            'percent'      => $total > 0 ? round($premium / $total * 100) : 0,
        ];
    }

    /** Top 5 workspaces by MRR + their owner + health. */
    private function workspaceActivity(): array
    {
        $rows = Workspace::query()
            ->with(['owner'])
            ->limit(5)
            ->orderByDesc('last_active_at')
            ->get();

        // Bulk-load packages to avoid N+1.
        $planIds = $rows->pluck('plan')->filter()->unique()->all();
        $packages = $planIds ? Package::whereIn('id', $planIds)->get()->keyBy('id') : collect();

        return $rows->map(function (Workspace $ws) use ($packages) {
            $package = $ws->plan ? $packages->get($ws->plan) : null;
            $mrr = $package?->chargeableAmount() ?? 0;

            // Last-7d outbound messages — health proxy.
            $msg7d = Message::query()
                ->where('workspace_id', $ws->id)
                ->where('direction', 'out')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            // Same realistic scale as WorkspacesController::health() — the old
            // >50k/>5k thresholds branded every normal workspace "Risk".
            $health = match (true) {
                $msg7d >= 500 => ['label' => 'Good',   'tone' => 'wa-deep'],
                $msg7d >= 50  => ['label' => 'Active', 'tone' => 'wa-deep'],
                $msg7d > 0    => ['label' => 'Low',    'tone' => 'accent-amber'],
                default       => ['label' => 'Idle',   'tone' => 'ink-500'],
            };

            return [
                'id'        => $ws->id,
                'name'      => $ws->name,
                'industry'  => $ws->industry ?: '—',
                'owner'     => $ws->owner?->name ?? '—',
                'plan'      => $package?->pname ?? 'Free',
                'planTone'  => $this->planTone($package?->pname),
                'messages'  => $this->humanize($msg7d),
                'health'    => $health,
                'mrr'       => $this->sym() . number_format((float) $mrr, 0),
                'detailUrl' => route('admin.workspaces.detail', $ws->id),
            ];
        })->all();
    }

    private function planTone(?string $name): array
    {
        $n = strtolower($name ?? '');
        if (str_contains($n, 'enterprise')) return ['bg' => '#D9E5F2', 'text' => '#13478A'];
        if (str_contains($n, 'pro'))        return ['bg' => '#F3E9FF', 'text' => '#5B3D8A'];
        if (str_contains($n, 'growth') || str_contains($n, 'standard')) return ['bg' => '#D7F7E6', 'text' => '#075E54'];
        if (str_contains($n, 'starter') || str_contains($n, 'basic'))   return ['bg' => '#FFF4E0', 'text' => '#7B5A14'];
        return ['bg' => '#EFEBE0', 'text' => '#6B807C'];
    }

    private function humanize(int $n): string
    {
        if ($n >= 1_000_000) return rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'm';
        if ($n >= 1_000)     return rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.') . 'k';
        return (string) $n;
    }

    /** Operational alerts panel — payment failures + spikes + KYC review queue. */
    private function alerts(): array
    {
        $alerts = [];

        // 1. Recent payment failures — last 24h.
        $failed = Order::query()
            ->with('workspace')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(2)->get();
        foreach ($failed as $o) {
            $alerts[] = [
                'severity' => 'high',
                'title'    => ($o->workspace?->name ?? 'Workspace #' . $o->workspace_id) . ' payment failed',
                'detail'   => ($o->failure_reason ?: 'Gateway returned failed status') . '.',
            ];
        }

        // 2. Message spike — any workspace with last 1h count >= 3x its 7d hourly average.
        if (Schema::hasTable('messages')) {
            $spikes = DB::table('messages')
                ->select('workspace_id', DB::raw('COUNT(*) as c'))
                ->where('direction', 'out')
                ->where('created_at', '>=', now()->subHour())
                ->groupBy('workspace_id')
                ->orderByDesc('c')
                ->limit(1)->get();
            foreach ($spikes as $s) {
                $ws = Workspace::find($s->workspace_id);
                if (!$ws) continue;
                $avg = (int) Message::query()
                    ->where('workspace_id', $ws->id)
                    ->where('direction', 'out')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count() / (7 * 24);
                if ($avg > 0 && $s->c >= $avg * 3) {
                    $alerts[] = [
                        'severity' => 'medium',
                        'title'    => $ws->name . ' message spike',
                        'detail'   => round($s->c / max(1, $avg), 1) . '× normal hourly volume.',
                    ];
                }
            }
        }

        // 3. Pending KYC count — count of pending workspaces (status = 0).
        $pending = Workspace::query()->where('status', false)->count();
        if ($pending > 0) {
            $alerts[] = [
                'severity' => 'low',
                'title'    => $pending . ' workspace ' . ($pending === 1 ? 'review' : 'reviews') . ' pending',
                'detail'   => 'Newest signup awaiting admin approval.',
            ];
        }

        return [
            'open'  => count($alerts),
            'items' => array_slice($alerts, 0, 3),
        ];
    }
}
