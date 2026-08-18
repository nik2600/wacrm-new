@php
    $f = $filters ?? [];
    $rangeKey = $f['range']['key'] ?? '90d';
    $bucketCur = $f['bucket'] ?? 'daily';
    $qCur = $f['q'] ?? '';
    $deltaPct = $stats['deltaPct'] ?? 0;
    $deltaCls = $deltaPct >= 0 ? 'text-wa-deep' : 'text-accent-coral';
    $deltaSign = $deltaPct > 0 ? '+' : '';
    $rangeLabels = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'all' => 'All time'];
@endphp
<x-layouts.user :title="__('Affiliate history')" nav-key="more" page="user-affiliate-history-index">

    <!-- Sub header -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Affiliate history') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Affiliate') }} <span
                            class="italic text-wa-deep">{{ __('history') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <select id="ah-range"
                    class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    @foreach ($rangeLabels as $k => $label)
                        <option value="{{ $k }}" @selected($rangeKey === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <a id="ah-export" href="{{ url('/affiliate-history/export?range=' . $rangeKey) }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    Export CSV
                </a>
                <button type="button" id="ah-copy-link"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2"
                    data-url="{{ $referralUrl }}">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <rect x="3" y="3" width="9" height="9" rx="1.5" />
                        <path d="M5.5 5.5h-2v9h9v-2" />
                    </svg>
                    Copy share link
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6">

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Signups (lifetime)') }}</span>
                    <span class="text-[10px] {{ $deltaCls }} font-mono">{{ $deltaSign }}{{ $deltaPct }}%
                        vs prev</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-ah="signupsLifetime">{{ number_format($stats['signupsLifetime']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('people') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Credits earned') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('lifetime') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-ah="creditsLifetime">{{ number_format($stats['creditsLifetime']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('credits') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Signups (30d)') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono"
                        data-ah="credits30d">+{{ number_format($stats['credits30d']) }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-ah="signups30d">{{ number_format($stats['signups30d']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('last 30 days') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Avg per signup') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono">{{ __('credits') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none"
                        data-ah="avgPerSignup">{{ number_format($stats['avgPerSignup']) }}</span>
                    <span class="text-[11px] text-ink-500">/ signup</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Your code') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('share to earn') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[24px] leading-none truncate">{{ $referralCode }}</span>
                </div>
                <div class="mt-1 text-[10.5px] text-ink-500 font-mono">+{{ number_format($signupReward) }}
                    {{ __('credits per signup') }}</div>
            </div>
        </section>

        <!-- Volume chart + top codes -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Volume') }}
                        </div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Signups & credits over time') }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500" id="ah-bucket-tabs">
                        @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $key => $label)
                            <button data-bucket="{{ $key }}"
                                class="px-2.5 py-1 rounded-full {{ $bucketCur === $key ? 'bg-wa-deep text-paper-0' : 'hover:bg-paper-100' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div id="chart-volume" class="h-[260px]" data-labels='@json($volume['labels'])'
                    data-signups='@json($volume['signups'])' data-credits='@json($volume['credits'])'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Top codes') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ $rangeKey }}</h3>
                <div class="space-y-3">
                    @forelse ($topCodes as $row)
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span class="font-mono text-ink-700">{{ $row['code'] }}</span>
                                <span class="font-mono text-ink-900">{{ number_format($row['signups']) }}
                                    signup{{ $row['signups'] === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width:{{ $row['pct'] }}%"></div>
                            </div>
                            <div class="text-[10.5px] text-ink-500 font-mono mt-0.5">
                                +{{ number_format($row['credits']) }} {{ __('credits') }}</div>
                        </div>
                    @empty
                        @include('user.partials.empty-state', [
                            'message' => 'No signups found. Share your code to start earning.',
                        ])
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Filter bar + table + side detail -->
        <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card flex flex-col min-h-[460px]">
                <div class="px-4 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                    <div class="relative flex-1 min-w-[260px] max-w-[480px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="ah-search" type="search" value="{{ $qCur }}"
                            placeholder="{{ __('Search by referee name, email, or code...') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <button id="ah-clear-filters"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] hover:bg-paper-50 inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M2 4h12M4 8h8M6 12h4" />
                        </svg>
                        Reset
                    </button>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[120px]">
                                    {{ __('When') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Referee') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5 w-[140px]">
                                    {{ __('Code used') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5 w-[160px]">
                                    {{ __('Credits') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5 w-[110px]">
                                    {{ __('Status') }}</th>
                                <th
                                    class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[120px]">
                                    {{ __('Wallet tx') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200" id="ah-rows">
                            @include('user.affiliate-history._rows', ['rows' => $rows])
                        </tbody>
                    </table>
                </div>

                <div id="ah-results-footer"
                    class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500 {{ $total > 0 ? '' : 'hidden' }}">
                    <div>{{ __('Showing') }} <span class="font-mono text-ink-900"
                            data-ah="shownRange">{{ $shownFrom }}–{{ $shownTo }}</span> of <span
                            class="font-mono text-ink-900" data-ah="totalRows">{{ number_format($total) }}</span>
                    </div>
                    <div class="flex items-center gap-1" id="ah-pagination">
                        <button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]"
                            data-ah-page="prev" {{ $page <= 1 ? 'disabled' : '' }}>{{ __('Prev') }}</button>
                        @for ($i = max(1, $page - 2); $i <= min($pageCount, $page + 2); $i++)
                            <button
                                class="px-2.5 py-1 rounded-md {{ $i === $page ? 'bg-wa-deep text-paper-0 font-semibold' : 'hover:bg-paper-50' }} text-[11px]"
                                data-ah-page="{{ $i }}">{{ $i }}</button>
                        @endfor
                        <button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]"
                            data-ah-page="next" {{ $page >= $pageCount ? 'disabled' : '' }}>Next</button>
                    </div>
                </div>
            </div>

            <!-- Side panel — share link + recent payouts -->
            <aside class="space-y-4 xl:sticky xl:top-[20px]">
                <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">
                        {{ __('Your share link') }}</div>
                    <div class="mt-2 font-serif text-[22px] tracking-[-0.01em]">{{ $referralCode }}</div>
                    <p class="mt-2 text-[11.5px] text-paper-0/80 leading-relaxed">+{{ number_format($signupReward) }}
                        credits per signup. Share this link anywhere — bio, email signature, group chats.</p>
                    <div
                        class="mt-3 px-3 py-2 rounded-lg bg-paper-0/15 border border-paper-0/15 font-mono text-[11px] break-all">
                        {{ $referralUrl }}</div>
                    <button type="button" data-url="{{ $referralUrl }}"
                        class="ah-copy-link mt-3 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-full bg-paper-0 text-wa-deep text-[12px] font-semibold">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <rect x="3" y="3" width="9" height="9" rx="1.5" />
                            <path d="M5.5 5.5h-2v9h9v-2" />
                        </svg>
                        Copy link
                    </button>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                    <div class="flex items-center justify-between mb-3">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Recent payouts') }}</span>
                        <a href="{{ url('/account?tab=wallet') }}"
                            class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Wallet →') }}</a>
                    </div>
                    <div class="space-y-2.5">
                        @forelse ($payouts as $p)
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="w-7 h-7 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0 text-[10px] font-mono">+{{ $p['amount'] >= 1000 ? round($p['amount'] / 1000) . 'k' : $p['amount'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[12px] font-medium leading-tight truncate">
                                        +{{ number_format($p['amount']) }} {{ __('credits') }}</div>
                                    <div class="text-[11px] text-ink-500 font-mono mt-0.5 truncate">
                                        {{ $p['human'] }} · balance {{ number_format($p['balanceAfter']) }}</div>
                                </div>
                            </div>
                        @empty
                            @include('user.partials.empty-state', [
                                'message' => 'No payouts found. Share your link to earn credits.',
                            ])
                        @endforelse
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('How it works') }}</div>
                    <ul class="space-y-1.5 text-[11.5px] text-ink-700 leading-snug">
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-deep shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>Share your link <span class="font-mono">?ref={{ $referralCode }}</span></li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-deep shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Friend signs up using it') }}</li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-deep shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>+{{ number_format($signupReward) }} {{ __('credits land in your wallet') }}</li>
                        <li class="flex items-start gap-2"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-deep shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>Credits spend at {{ $creditsPerMessage }}
                            credit{{ $creditsPerMessage === 1 ? '' : 's' }} {{ __('per message') }}</li>
                    </ul>
                </div>
            </aside>
        </section>

    </main>

</x-layouts.user>
