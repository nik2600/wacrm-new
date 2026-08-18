{{--
 /admin/packages/analytics/overview — mirrors prototype
 D:\wadesk_2806\whatsnap\tutot (3)\admin-package-analytics.html
 exactly. Every prototype "static" number is replaced with the
 matching value computed in AdminPagesController::packageAnalytics().
--}}
<x-layouts.admin :title="__('Admin · Package analytics')" admin-key="packages" page="packages-analytics">

    @php $cur = fn ($v) => \App\Support\FormatSettings::currency((float) ($v ?? 0)); @endphp

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/packages') }}" class="hover:text-ink-900">{{ __('Packages') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>
        <form method="GET" action="" class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <select name="package" onchange="this.form.submit()"
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option value="" @selected($packageFilter === '')>{{ __('All packages') }}</option>
                @foreach ($packages as $pkg)
                    <option value="{{ $pkg->pname }}" @selected($packageFilter === $pkg->pname)>{{ $pkg->pname }}</option>
                @endforeach
            </select>
            <select name="days" onchange="this.form.submit()"
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option value="30" @selected($days === 30)>{{ __('Last 30 days') }}</option>
                <option value="90" @selected($days === 90)>{{ __('Last 90 days') }}</option>
                <option value="365" @selected($days === 365)>{{ __('This year') }}</option>
            </select>
        </form>
        {{--
            Export → real CSV download (NOT a re-submit of the filter form).
            Carries the same `days` + `package` query params the page is
            currently viewing so the CSV always matches what's on screen.
            Lives OUTSIDE the GET form on purpose — a `<button type="submit">`
            inside the filter form just reloaded the page with no download.
        --}}
        <a href="{{ route('admin.packages.analytics.export', ['days' => $days, 'package' => $packageFilter]) }}"
           class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
            </svg>
            {{ __('Export CSV') }}
        </a>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <!-- Heading -->
        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Package analytics') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Package') }}
                    <span class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('How each plan is performing — subscriber growth, MRR contribution, churn cohorts, upgrade paths, and feature adoption.') }}
                </p>
            </div>
        </div>

        <!-- KPI hero -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total MRR') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{!! $cur($totalMrr) !!}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $newPaid30d }} paid · last {{ $days }}d</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Subscribers') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ number_format($subsTotal) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">+{{ $newPaid30d }} {{ __('net new') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('ARPA') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{!! $cur($arpa) !!}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('avg revenue / account') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('LTV avg') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{!! $cur($ltvAvg) !!}</div>
                <div class="text-[11px] text-ink-500 mt-2">28-mo lifetime</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">Net churn {{ $days }}d</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $churnPct }}%</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $cancelled30d }} cancels · {{ $newPaid30d }}
                    {{ __('new') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Trial → paid') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $trialToPaidPct }}%</div>
                <div class="text-[11px] text-wa-deep mt-2">of {{ $trialActive }} {{ __('trials') }}</div>
            </div>
        </section>

        <!-- MRR by plan area chart + plan distribution donut -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ $days }}-day trend</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('MRR by package') }}</h2>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        @php $colors = ['bg-wa-deep','bg-[#13478A]','bg-accent-amber']; @endphp
                        @foreach ($mrrSeries['series'] ?? [] as $i => $s)
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2.5 h-2.5 rounded-full {{ $colors[$i] ?? 'bg-paper-300' }}"></span>{{ $s['name'] }}</span>
                        @endforeach
                    </div>
                </div>
                <div id="chart-mrr" class="h-[260px]" data-series="{{ json_encode($mrrSeries['series'] ?? []) }}"
                    data-categories="{{ json_encode($mrrSeries['categories'] ?? []) }}"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Distribution') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Plan share') }}</h2>
                <div id="chart-share" class="h-[200px] mt-2"
                    data-labels="{{ json_encode(array_column($share, 'label')) }}"
                    data-series="{{ json_encode(array_map(fn($r) => $r['count'], $share)) }}"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    @php $sharePalette = ['bg-wa-deep','bg-[#13478A]','bg-accent-amber','bg-paper-300','bg-accent-coral']; @endphp
                    @foreach ($share as $i => $row)
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                    class="w-2.5 h-2.5 rounded-full {{ $sharePalette[$i] ?? 'bg-paper-300' }}"></span>{{ $row['label'] }}</span><span
                                class="font-mono text-ink-700">{{ $row['count'] }} · {!! $cur($row['mrr']) !!}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Per-package leaderboard -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Per-package performance') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Leaderboard') }}</h2>
                </div>
                <span class="font-mono text-[11px] text-ink-500">Last {{ $days }} {{ __('days') }}</span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[900px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-3">{{ __('Package') }}</th>
                        <th class="text-right px-3 py-3 w-[110px]">{{ __('Subscribers') }}</th>
                        <th class="text-right px-3 py-3 w-[100px]">{{ __('Net new') }}</th>
                        <th class="text-right px-3 py-3 w-[100px]">{{ __('MRR') }}</th>
                        <th class="text-right px-3 py-3 w-[100px]">{{ __('ARPA') }}</th>
                        <th class="text-right px-3 py-3 w-[90px]">{{ __('Churn') }}</th>
                        <th class="text-right px-3 py-3 w-[110px]">{{ __('Avg LTV') }}</th>
                        <th class="text-center px-4 py-3 w-[90px]">{{ __('Trend') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @php
                        // Per-row badge styles cycle through the prototype palette so
                        // ranks 1..N look like Pro / Enterprise / Starter / Free in
                        // the original mock.
                        $rowPalette = [
                            ['bg' => 'bg-wa-bubble', 'text' => 'text-wa-deep'],
                            ['bg' => 'bg-[#D9E5F2]', 'text' => 'text-[#13478A]'],
                            ['bg' => 'bg-[#FFF4E0]', 'text' => 'text-[#7B5A14]'],
                            ['bg' => 'bg-paper-100', 'text' => 'text-ink-700'],
                            ['bg' => 'bg-accent-coral/10', 'text' => 'text-accent-coral'],
                        ];
                    @endphp
                    @foreach ($leaderboard as $i => $row)
                        @php
                            $pkg = $row['package'];
                            $initials = strtoupper(substr(preg_replace('/\s+/', '', $pkg->pname), 0, 2));
                            $palette = $rowPalette[$i] ?? $rowPalette[0];
                            $trendIcon = ['up' => '↑', 'flat' => '→', 'down' => '↓'][$row['trend']] ?? '·';
                            $trendCls =
                                ['up' => 'text-wa-deep', 'flat' => 'text-accent-amber', 'down' => 'text-accent-coral'][
                                    $row['trend']
                                ] ?? '';
                            $priceLabel = $pkg->free
                                ? 'Free'
                                : ($pkg->lifetime
                                    ? 'Lifetime'
                                    : \App\Support\FormatSettings::currency(
                                            (float) ($pkg->offer_price ?: $pkg->plan_amount),
                                        ) .
                                        '/' .
                                        ($pkg->plan_duration ?: 'mo'));
                        @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2"><span
                                        class="w-7 h-7 rounded-lg {{ $palette['bg'] }} {{ $palette['text'] }} grid place-items-center text-[10px] font-bold">{{ $initials }}</span>
                                    <div>
                                        <div class="font-semibold">{{ $pkg->pname }}</div>
                                        <div class="text-[10.5px] text-ink-500 font-mono">{!! $priceLabel !!}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">{{ $row['count'] }}</td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ $row['net_new'] > 0 ? 'text-wa-deep' : '' }}">
                                {{ $row['net_new'] > 0 ? '+' . $row['net_new'] : ($row['net_new'] === 0 ? 0 : $row['net_new']) }}
                            </td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ $row['mrr'] > 0 ? 'text-wa-deep' : 'text-ink-500' }}">
                                {!! $row['mrr'] > 0 ? $cur($row['mrr']) : '—' !!}</td>
                            <td class="px-3 py-3 text-right font-mono {{ $row['arpa'] > 0 ? '' : 'text-ink-500' }}">
                                {!! $row['arpa'] > 0 ? $cur($row['arpa']) : '—' !!}</td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ $row['churn_pct'] >= 5 ? 'text-accent-coral' : ($row['churn_pct'] > 0 ? 'text-accent-amber' : '') }}">
                                {{ $row['churn_pct'] }}%</td>
                            <td class="px-3 py-3 text-right font-mono {{ $row['ltv'] > 0 ? '' : 'text-ink-500' }}">
                                {!! $row['ltv'] > 0 ? $cur($row['ltv']) : '—' !!}</td>
                            <td class="px-4 py-3 text-center font-mono {{ $trendCls }}">{{ $trendIcon }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>

        <!-- Upgrade flow + churn cohort -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Upgrade paths ·
                    {{ $days }}d</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Where do upgrades come from?') }}
                </h2>
                <div class="space-y-3 text-[12.5px]">
                    @forelse ($upgrades as $u)
                        @php
                            $fromColor = match (strtolower($u['from'])) {
                                'trial', 'first paid' => 'bg-paper-100 text-ink-700',
                                default => match (strtolower($u['from'])) {
                                    'starter' => 'bg-[#FFF4E0] text-[#7B5A14]',
                                    'pro' => 'bg-wa-bubble text-wa-deep',
                                    'enterprise' => 'bg-[#D9E5F2] text-[#13478A]',
                                    'any plan' => 'bg-paper-100 text-ink-700',
                                    default => 'bg-paper-100 text-ink-700',
                                },
                            };
                            $toColor = match (strtolower($u['to'])) {
                                'starter' => 'bg-[#FFF4E0] text-[#7B5A14]',
                                'pro', 'first paid' => 'bg-wa-bubble text-wa-deep',
                                'enterprise' => 'bg-[#D9E5F2] text-[#13478A]',
                                'cancel' => 'bg-accent-coral/10 text-accent-coral',
                                default => 'bg-paper-100 text-ink-700',
                            };
                            $barColor =
                                $u['kind'] === 'cancel'
                                    ? 'bg-accent-coral'
                                    : ($u['kind'] === 'downgrade'
                                        ? 'bg-accent-amber'
                                        : 'bg-wa-deep');
                            $countLabel =
                                $u['kind'] === 'cancel'
                                    ? $u['count'] . ' cancels'
                                    : ($u['kind'] === 'downgrade'
                                        ? $u['count'] . ' downgrades'
                                        : $u['count'] . ' conversions');
                            $countCls =
                                $u['kind'] === 'cancel'
                                    ? 'text-accent-coral'
                                    : ($u['kind'] === 'downgrade'
                                        ? 'text-accent-amber'
                                        : 'text-wa-deep');
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="flex items-center gap-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full {{ $fromColor }} text-[10px] font-semibold">{{ $u['from'] }}</span>
                                    <span class="text-ink-500">→</span>
                                    <span
                                        class="px-2 py-0.5 rounded-full {{ $toColor }} text-[10px] font-semibold">{{ $u['to'] }}</span>
                                </span>
                                <span class="font-mono {{ $countCls }}">{{ $countLabel }}</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $barColor }}" style="width: {{ $u['pct'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[11.5px] text-ink-500 italic py-6 text-center">No plan transitions in the last
                            {{ $days }} days.</div>
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Cancellation reasons') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Why people leave') }}</h2>
                <ul class="space-y-2 text-[12.5px]">
                    @forelse ($reasons as $r)
                        @php $pct = (int) round($r->n / max(1, $maxReason) * 100); @endphp
                        <li class="flex items-center justify-between"><span>{{ $r->label }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-32 h-2 bg-paper-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $loop->first ? 'bg-accent-coral' : ($loop->index < 4 ? 'bg-accent-amber' : 'bg-paper-300') }}"
                                        style="width: {{ $pct }}%"></div>
                                </div><span class="font-mono text-[11px] w-8 text-right">{{ $r->n }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="text-[11.5px] text-ink-500 italic py-6 text-center">
                            {{ __('No cancellations on record yet.') }}</li>
                    @endforelse
                </ul>
                <div class="mt-4 pt-4 border-t border-paper-200 text-[11px] text-ink-500">
                    @if ($cancelled30d > 0)
                        {{ $cancelled30d }} customers cancelled ·
                        {{ $reasons->count() > 0 ? round((($reasons->first()->n ?? 0) / max(1, $cancelled30d)) * 100) : 0 }}%
                        cite "{{ $reasons->first()->label ?? '—' }}"
                    @else
                        Customer cancellations + the top reason show here once they happen.
                    @endif
                </div>
            </div>
        </section>

        <!-- Feature adoption -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Feature adoption') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('What workspaces actually use') }}
                    </h2>
                </div>
                <span class="font-mono text-[11px] text-ink-500">% of subscribers using each feature</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($featureAdoption as $f)
                    @php
                        $low = $f['pct'] < 15;
                        $cardCls = $low
                            ? 'rounded-xl border border-accent-amber/40 p-3'
                            : 'rounded-xl border border-paper-200 p-3';
                        $pctCls = $low ? 'font-serif text-[24px] text-accent-amber' : 'font-serif text-[24px]';
                        $barCls = $low ? 'h-full bg-accent-amber' : 'h-full bg-wa-deep';
                    @endphp
                    <div class="{{ $cardCls }}">
                        <div class="{{ $pctCls }}">{{ $f['pct'] }}%</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ $f['label'] }}</div>
                        <div class="h-1 bg-paper-100 rounded-full overflow-hidden mt-2">
                            <div class="{{ $barCls }}" style="width: {{ $f['pct'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

    </main>

</x-layouts.admin>
