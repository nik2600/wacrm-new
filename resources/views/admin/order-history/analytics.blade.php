<x-layouts.admin :title="__('Admin · Order analytics')" admin-key="order-history" page="order-history-analytics">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/order-history') }}" class="hover:text-ink-900">{{ __('Order history') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <select
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option>{{ __('Last 30 days') }}</option>
                <option selected>{{ __('Last 90 days') }}</option>
                <option>{{ __('This year') }}</option>
            </select>
            <a href="{{ url('/admin/order-history') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View ledger') }}</a>
            <button
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                </svg>
                Export
            </button>
        </div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Order analytics') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Order') }}
                    <span class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __("Subscriptions, upgrades, downgrades, add-ons, and cancellations — what's driving net revenue motion across the platform.") }}
                </p>
            </div>
        </div>

        <!-- KPI hero -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total orders') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['total'] }}</div>
                <div class="text-[11px] {{ $stats['totalDeltaPos'] ? 'text-wa-deep' : 'text-accent-coral' }} mt-2">
                    {{ $stats['totalDelta'] }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Net new MRR') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['newMrr'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['upgrades'] }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Lost MRR') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['lostMrr'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['cancels'] }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Avg order value') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['aov'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('paid orders') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Add-on attach') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['attachPct'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['attachLabel'] }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Conversion rate') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['convPct'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('signup → paid') }}</div>
            </div>
        </section>

        <!-- Net revenue motion -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Daily order motion') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">
                            {{ __('New, upgrades, downgrades, cancels') }}</h2>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>New</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Upgrade</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>Downgrade</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Cancel</span>
                    </div>
                </div>
                <div id="chart-motion" class="h-[280px]"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Order type mix') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('By type') }}</h2>
                <div id="chart-type" class="h-[200px] mt-2"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    @foreach ($typeMix['rows'] as $r)
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                    class="w-2.5 h-2.5 rounded-full {{ $r['tone'] }}"></span>{{ $r['label'] }}</span><span
                                class="font-mono">{{ number_format($r['count']) }}</span></div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Top add-ons + Conversion funnel + Top countries -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Add-ons sold') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Top performers') }}</h2>
                <ul class="space-y-2 text-[12.5px]">
                    @forelse ($addons as $a)
                        <li>
                            <div class="flex items-center justify-between mb-1"><span
                                    class="font-semibold">{{ $a['label'] }}</span><span
                                    class="font-mono">{{ $a['count'] }} · {{ $a['total'] }}</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width: {{ $a['pct'] }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-[12px] text-ink-500 italic">{{ __('No add-on orders in this window.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Conversion funnel') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Visit → paid') }}</h2>
                <div class="space-y-3 text-[12px]">
                    @foreach ($funnel as $i => $f)
                        @php
                            $barColor = match (true) {
                                $i === 0 => 'bg-wa-deep',
                                $i === 1 => 'bg-wa-deep',
                                $i === 2 => 'bg-wa-teal',
                                $i === 3 => 'bg-accent-amber',
                                default => 'bg-accent-coral',
                            };
                            $textTone = $i === count($funnel) - 1 ? 'text-wa-deep' : '';
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span>{{ $f['label'] }}</span>
                                <span class="font-mono {{ $textTone }}">{{ number_format($f['count']) }}
                                    @if ($f['pct'])
                                        · {{ $f['pct'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $barColor }}" style="width: {{ $f['bar'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-3 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('By country') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Top markets') }}</h2>
                <ul class="space-y-2.5 text-[12.5px]">
                    @php $countryTones = ['#FFF4E0','#D9E5F2','var(--color-wa-bubble)','#F3E9FF','#FEE4E2','var(--color-paper-100)']; @endphp
                    @forelse ($byCountry as $i => $c)
                        <li class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                    class="w-5 h-5 rounded-full grid place-items-center text-[9.5px] font-semibold text-ink-700"
                                    style="background: {{ $countryTones[$i % count($countryTones)] }}">{{ $c['code'] }}</span>{{ $c['code'] }}</span><span
                                class="font-mono">{{ number_format($c['count']) }}</span></li>
                    @empty
                        <li class="text-[12px] text-ink-500 italic">{{ __('No country data yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <!-- Cohort retention -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card overflow-hidden">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Cohort retention') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">% of customers still subscribed N months
                        later</h2>
                </div>
                <span class="font-mono text-[11px] text-ink-500">{{ __('last 6 cohorts') }}</span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-3 py-2 font-mono text-[10px] uppercase tracking-[0.14em] w-[140px]">
                            {{ __('Cohort') }}</th>
                        <th class="text-right px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em] w-[80px]">
                            {{ __('Size') }}</th>
                        <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em]">M0</th>
                        <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em]">M1</th>
                        <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em]">M2</th>
                        <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em]">M3</th>
                        <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em]">M4</th>
                        <th class="text-center px-2 py-2 font-mono text-[10px] uppercase tracking-[0.14em]">M5</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200 font-mono text-[11.5px] text-center">
                    @forelse ($cohorts as $row)
                        <tr>
                            <td class="px-3 py-2 text-left">{{ $row['label'] }} · {{ $row['size'] }}</td>
                            <td class="px-2 py-2 text-right">{{ $row['size'] }}</td>
                            @foreach ($row['cells'] as $k => $cell)
                                @if ($cell === null)
                                    <td class="px-2 py-2 text-ink-500">—</td>
                                @else
                                    @php $opacity = ['', '/85', '/75', '/65', '/55', '/45'][$k] ?? '/45'; @endphp
                                    <td class="px-2 py-2 bg-wa-deep{{ $opacity }} text-paper-0">
                                        {{ $cell }}%</td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-ink-500">{{ __('No workspace signups yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>

        <script>
            window.adminOrderAnalytics = {
                motion: @json($motion),
                typeMix: @json($typeMix),
            };
        </script>
    </main>

</x-layouts.admin>
