<x-layouts.admin :title="__('Analytics')" admin-key="analytics" page="admin-analytics-index">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>

        <div class="relative flex-1 min-w-0 max-w-[480px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search reports, workspaces, metrics...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>

        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Platform') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Admin') }}
                    <span class="italic text-wa-deep">{{ __('analytics') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('A complete control-room view of revenue, workspace growth, WhatsApp usage, campaign delivery, device health, and support pressure.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                {{-- Date-range select wired via JS to a ?days=N query. Mirrors
 the original prototype dropdown but each option carries
 the days value. --}}
                <select onchange="window.location.search = '?days=' + this.value"
                    class="px-3.5 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    <option value="7" @selected($days == 7)>{{ __('Last 7 days') }}</option>
                    <option value="30" @selected($days == 30)>{{ __('Last 30 days') }}</option>
                    <option value="90" @selected($days == 90)>{{ __('Last 90 days') }}</option>
                    <option value="365" @selected($days == 365)>{{ __('This year') }}</option>
                </select>
                {{-- "Filters" removed — the days dropdown above already
 filters the analytics window. --}}
                <a href="{{ url('/admin/analytics?export=csv&days=' . $days) }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    Export
                </a>
            </div>
        </div>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card overflow-x-auto"
            data-wa-tabs>
            <button data-wa-tab="overview"
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold">{{ __('Overview') }}</button>
            <button data-wa-tab="revenue"
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Revenue') }}</button>
            <button data-wa-tab="usage"
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Usage') }}</button>
            <button data-wa-tab="workspaces"
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Workspaces') }}</button>
            <button data-wa-tab="campaigns"
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Campaigns') }}</button>
            <button data-wa-tab="support"
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Support') }}</button>
            <div class="flex-1"></div>
            <span
                class="inline-flex shrink-0 whitespace-nowrap items-center gap-1.5 px-3 py-1.5 rounded-full bg-wa-mint text-wa-deep text-[11px] font-mono border border-wa-green/40">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                refreshed just now
            </span>
        </section>

        <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3" data-wa-tab-panel="overview">
            {{-- KPI 1: Revenue in window (was MRR placeholder) --}}
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">Revenue · {{ $days }}d</div>
                    <span
                        class="rounded-full bg-wa-bubble text-wa-deep px-2 py-0.5 text-[10px] font-mono">{{ __('paid') }}</span>
                </div>
                <div class="font-serif text-[31px] leading-none mt-2">{!! $kpi['revenue_window'] !== null
                    ? \App\Support\FormatSettings::currency((float) $kpi['revenue_window'])
                    : '—' !!}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $kpi['workspaces_new'] }} {{ __('new workspaces') }}
                </div>
            </div>
            {{-- KPI 2: Active workspaces --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Workspaces') }}</div>
                    <span
                        class="rounded-full bg-wa-bubble text-wa-deep px-2 py-0.5 text-[10px] font-mono">+{{ $kpi['workspaces_new'] }}</span>
                </div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($kpi['workspaces_total']) }}
                </div>
                <div class="text-[11px] text-ink-500 mt-2">{{ number_format($kpi['users_total']) }}
                    {{ __('total users') }}</div>
            </div>
            {{-- KPI 3: Messages --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Messages') }}</div>
                    <span
                        class="rounded-full bg-wa-bubble text-wa-deep px-2 py-0.5 text-[10px] font-mono">{{ $days }}d</span>
                </div>
                <div class="font-serif text-[31px] leading-none mt-2">
                    {{ $kpi['messages_sent'] === null ? '—' : number_format($kpi['messages_sent']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">
                    {{ $kpi['messages_sent'] && $days ? number_format($kpi['messages_sent'] / $days) . ' / day avg' : '' }}
                </div>
            </div>
            {{-- KPI 4: Devices online --}}
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Devices online') }}</div>
                    <span
                        class="rounded-full bg-wa-bubble text-wa-deep px-2 py-0.5 text-[10px] font-mono">{{ $kpi['devices_total'] ? round(($kpi['devices_online'] / max(1, $kpi['devices_total'])) * 100) . '%' : '—' }}</span>
                </div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($kpi['devices_online']) }}<span
                        class="text-[14px] text-ink-500">/{{ number_format($kpi['devices_total']) }}</span></div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('connected now') }}</div>
            </div>
            {{-- KPI 5: Users new --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">New users · {{ $days }}d</div>
                    <span
                        class="rounded-full bg-wa-bubble text-wa-deep px-2 py-0.5 text-[10px] font-mono">{{ __('signups') }}</span>
                </div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($kpi['users_new']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ number_format($kpi['users_total']) }}
                    {{ __('total') }}</div>
            </div>
            {{-- KPI 6: Open tickets --}}
            <div
                class="bg-paper-0 border {{ $kpi['tickets_open'] > 0 ? 'border-accent-coral/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="text-[11px] text-ink-600 font-medium">{{ __('Open tickets') }}</div>
                    <span
                        class="rounded-full {{ $kpi['tickets_open'] > 0 ? 'bg-accent-coral/10 text-accent-coral' : 'bg-wa-bubble text-wa-deep' }} px-2 py-0.5 text-[10px] font-mono">{{ $kpi['tickets_open'] > 0 ? 'watch' : 'clear' }}</span>
                </div>
                <div
                    class="font-serif text-[31px] leading-none mt-2 {{ $kpi['tickets_open'] > 0 ? 'text-accent-coral' : '' }}">
                    {{ number_format($kpi['tickets_open']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('support backlog') }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview revenue usage workspaces">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Growth command center') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">User signups, last {{ $days }}
                            days</h2>
                        <div class="flex items-center gap-4 mt-2 text-[11px] text-ink-500">
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-wa-deep"></span>Signups</span>
                        </div>
                    </div>
                    <button class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center"
                        title="{{ __('More') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="3.5" cy="8" r="1" />
                            <circle cx="8" cy="8" r="1" />
                            <circle cx="12.5" cy="8" r="1" />
                        </svg>
                    </button>
                </div>
                <div id="chart-platform-growth" class="h-[315px]"
                    data-series="{{ json_encode($signupSeries->pluck('n')->all()) }}"
                    data-categories="{{ json_encode($signupSeries->pluck('d')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M j'))->all()) }}">
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Live health') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Platform pulse') }}</h2>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-wa-mint text-wa-deep px-2 py-0.5 text-[10px] font-mono border border-wa-green/40">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>normal
                    </span>
                </div>
                <div class="mt-4 space-y-3">
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="flex items-center justify-between text-[12.5px]"><span
                                class="font-semibold">{{ __('Workspaces online') }}</span><span
                                class="font-mono text-wa-deep">{{ $kpi['workspaces_total'] }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-wa-deep" style="width: {{ min(100, $kpi['workspaces_total']) }}%">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="flex items-center justify-between text-[12.5px]"><span
                                class="font-semibold">{{ __('Devices connected') }}</span><span
                                class="font-mono text-wa-deep">{{ $kpi['devices_total'] ? round(($kpi['devices_online'] / max(1, $kpi['devices_total'])) * 100) : 0 }}%</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-wa-deep"
                                style="width: {{ $kpi['devices_total'] ? round(($kpi['devices_online'] / max(1, $kpi['devices_total'])) * 100) : 0 }}%">
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl {{ $kpi['tickets_open'] > 0 ? 'border border-accent-amber/40 bg-accent-amber/5' : 'border border-paper-200' }} p-3">
                        <div class="flex items-center justify-between text-[12.5px]"><span
                                class="font-semibold">{{ __('Open tickets') }}</span><span
                                class="font-mono {{ $kpi['tickets_open'] > 0 ? 'text-accent-amber' : 'text-wa-deep' }}">{{ $kpi['tickets_open'] }}</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden mt-2">
                            <div class="h-full {{ $kpi['tickets_open'] > 0 ? 'bg-accent-amber' : 'bg-wa-deep' }}"
                                style="width: {{ min(100, $kpi['tickets_open'] * 5) }}%"></div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="flex items-center justify-between text-[12.5px]"><span class="font-semibold">New
                                signups · {{ $days }}d</span><span
                                class="font-mono text-wa-deep">{{ $kpi['users_new'] }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden mt-2">
                            <div class="h-full bg-wa-teal" style="width: {{ min(100, $kpi['users_new']) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview usage campaigns">
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Plan distribution') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Workspaces by plan') }}</h2>
                    </div>
                    <span class="font-mono text-[11px] text-ink-500">{{ __('live') }}</span>
                </div>
                <div id="chart-module-mix" class="h-[225px] mt-1"
                    data-labels="{{ json_encode($planDistribution->pluck('plan')->all()) }}"
                    data-series="{{ json_encode($planDistribution->pluck('n')->map(fn($v) => (int) $v)->all()) }}">
                </div>
                <div class="space-y-1.5 text-[12px]">
                    @php
                        $planSum = max(1, (int) $planDistribution->sum('n'));
                        $planColors = ['var(--color-wa-deep)', 'var(--color-wa-teal)', '#13478A', 'var(--color-accent-amber)', 'var(--color-accent-coral)'];
                    @endphp
                    @foreach ($planDistribution->take(5) as $i => $p)
                        <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                    class="w-2.5 h-2.5 rounded-full"
                                    style="background:{{ $planColors[$i] ?? '#9DB' }}"></span>{{ $p->plan }}</span><span
                                class="font-mono">{{ round(($p->n / $planSum) * 100) }}%</span></div>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Acquisition') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Users → Workspace') }}</h2>
                @php
                    $usersCount = max(0, (int) $kpi['users_total']);
                    $usersNew = max(0, (int) $kpi['users_new']);
                    $wsCount = max(0, (int) $kpi['workspaces_total']);
                    $wsNew = max(0, (int) $kpi['workspaces_new']);
                    $denomA = max(1, $usersCount);
                    $denomB = max(1, $usersNew);
                    $denomC = max(1, $wsNew);
                @endphp
                <div class="space-y-3 text-[12px]">
                    <div>
                        <div class="flex items-center justify-between mb-1"><span>{{ __('Total users') }}</span><span
                                class="font-mono">{{ number_format($usersCount) }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep w-full"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span>New users ·
                                {{ $days }}d</span><span class="font-mono">{{ number_format($usersNew) }} ·
                                {{ round(($usersNew / $denomA) * 100, 1) }}%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep"
                                style="width: {{ min(100, ($usersNew / $denomA) * 100) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Workspaces created') }}</span><span
                                class="font-mono">{{ number_format($wsCount) }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-teal"
                                style="width: {{ min(100, ($wsCount / $denomA) * 100) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span>New workspaces ·
                                {{ $days }}d</span><span class="font-mono">{{ number_format($wsNew) }} ·
                                {{ round(($wsNew / max(1, $usersNew)) * 100, 1) }}% of new users</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber"
                                style="width: {{ min(100, ($wsNew / $denomB) * 100) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Devices connected') }}</span><span
                                class="font-mono text-wa-deep">{{ number_format($kpi['devices_online']) }} /
                                {{ number_format($kpi['devices_total']) }}</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral"
                                style="width: {{ $kpi['devices_total'] ? round(($kpi['devices_online'] / max(1, $kpi['devices_total'])) * 100) : 0 }}%">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-paper-200 grid grid-cols-2 gap-3 text-[12px]">
                    <div>
                        <div class="font-serif text-[24px] leading-none">{{ number_format($kpi['users_new']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">new users · {{ $days }}d</div>
                    </div>
                    <div>
                        <div class="font-serif text-[24px] leading-none">{{ number_format($kpi['workspaces_new']) }}
                        </div>
                        <div class="text-[10.5px] text-ink-500 mt-1">new workspaces · {{ $days }}d</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top devices · 24h') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Send volume') }}</h2>
                    </div>
                    <a href="{{ url('/admin/workspaces') }}"
                        class="text-[12px] font-semibold text-wa-deep hover:underline">{{ __('Workspaces') }}</a>
                </div>
                <div id="chart-delivery" class="h-[225px] mt-1" data-labels="{{ json_encode($deviceLabels) }}"
                    data-series="{{ json_encode($deviceData) }}"></div>
                <div class="grid grid-cols-3 gap-2 text-center text-[11px]">
                    <div class="rounded-xl border border-paper-200 p-2">
                        <div class="font-serif text-[22px] leading-none">{{ $devicesTotalCount }}</div>
                        <div class="text-ink-500 mt-1">{{ __('devices') }}</div>
                    </div>
                    <div class="rounded-xl border border-wa-green/40 p-2">
                        <div class="font-serif text-[22px] leading-none text-wa-deep">{{ $devicesOnlineCount }}</div>
                        <div class="text-ink-500 mt-1">{{ __('online') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-2">
                        <div class="font-serif text-[22px] leading-none">{{ number_format(array_sum($deviceData)) }}
                        </div>
                        <div class="text-ink-500 mt-1">{{ __('24h sends') }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview workspaces">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Workspace leaderboard') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">Top workspaces by paid revenue · last
                            {{ $days }}d</h2>
                    </div>
                    <a href="{{ url('/admin/workspaces') }}"
                        class="rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 py-1.5 text-[11.5px] font-semibold">{{ __('View all') }}</a>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Workspace') }}</th>
                            <th class="text-right px-3 py-3 w-[110px]">{{ __('Orders') }}</th>
                            <th class="text-right px-3 py-3 w-[140px]">{{ __('Paid revenue') }}</th>
                            <th class="text-right px-4 py-3 w-[100px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($topWorkspaces as $w)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3">
                                    <div class="font-semibold truncate">{{ $w->name }}</div>
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ number_format($w->n) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-wa-deep">{!! \App\Support\FormatSettings::currency((float) $w->paid) !!}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ url('/admin/workspaces/' . $w->id) }}"
                                        class="text-wa-deep text-[11px] hover:underline">{{ __('Open →') }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                    {{ __('No paid orders in this window.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Signup cohorts') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">Recent {{ $days }} days</h2>
                    </div>
                    <span class="text-[11px] font-mono text-ink-500">{{ __('daily') }}</span>
                </div>
                <div id="chart-retention" class="h-[285px] mt-2"
                    data-series="{{ json_encode($signupSeries->pluck('n')->all()) }}"
                    data-categories="{{ json_encode($signupSeries->pluck('d')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M j'))->all()) }}">
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview revenue support">
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Revenue composition') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">Top contributors · {{ $days }}d
                </h2>
                <div class="space-y-3 text-[12.5px]">
                    @php $revSum = max(1, (float) $topWorkspaces->sum('paid')); @endphp
                    @forelse ($topWorkspaces->take(5) as $w)
                        <div>
                            <div class="flex items-center justify-between mb-1"><span
                                    class="font-semibold truncate max-w-[200px]">{{ $w->name }}</span><span
                                    class="font-mono">{!! \App\Support\FormatSettings::currency((float) $w->paid) !!} ·
                                    {{ round(($w->paid / $revSum) * 100) }}%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width: {{ ($w->paid / $revSum) * 100 }}%">
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[12px] text-ink-500 text-center py-4">{{ __('No paid revenue yet.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Support load') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Tickets state') }}</h2>
                @php
                    try {
                        $ticketStates = \DB::table('support_tickets')
                            ->select('status', \DB::raw('COUNT(*) as n'))
                            ->groupBy('status')
                            ->get();
                        $maxState = max(1, (int) $ticketStates->max('n'));
                    } catch (\Throwable $e) {
                        $ticketStates = collect();
                        $maxState = 1;
                    }
                @endphp
                <div class="space-y-2.5 text-[12px]">
                    @forelse ($ticketStates as $s)
                        <div>
                            <div class="flex items-center justify-between mb-1"><span
                                    class="font-mono uppercase">{{ str_replace('_', ' ', $s->status) }}</span><span
                                    class="font-mono">{{ $s->n }}</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width: {{ ($s->n / $maxState) * 100 }}%">
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[12px] text-ink-500 text-center py-4">{{ __('No tickets yet.') }}</div>
                    @endforelse
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-[11px] mt-4">
                    <div class="rounded-xl border border-paper-200 p-2">
                        <div class="font-serif text-[22px] leading-none">{{ $kpi['tickets_open'] }}</div>
                        <div class="text-ink-500 mt-1">{{ __('open') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-2">
                        <div class="font-serif text-[22px] leading-none">
                            {{ (int) ($ticketStates->where('status', 'resolved')->first()->n ?? 0) }}</div>
                        <div class="text-ink-500 mt-1">{{ __('resolved') }}</div>
                    </div>
                    <div class="rounded-xl border border-wa-green/40 p-2">
                        <div class="font-serif text-[22px] leading-none text-wa-deep"><a
                                href="{{ url('/admin/support') }}">→</a></div>
                        <div class="text-ink-500 mt-1">{{ __('inbox') }}</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Admin attention') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Action queue') }}</h2>
                    </div>
                    <span
                        class="rounded-full {{ $kpi['tickets_open'] > 0 ? 'bg-accent-coral/10 text-accent-coral border-accent-coral/30' : 'bg-wa-mint text-wa-deep border-wa-green/40' }} border px-2 py-0.5 text-[10px] font-semibold">{{ $kpi['tickets_open'] }}
                        {{ __('open') }}</span>
                </div>
                <div class="mt-4 space-y-3">
                    @if ($kpi['tickets_open'] > 0)
                        <a href="{{ url('/admin/support') }}"
                            class="block rounded-xl border border-accent-coral/30 bg-accent-coral/10 p-3 hover:bg-accent-coral/15">
                            <div class="flex items-center justify-between">
                                <div class="text-[13px] font-semibold">{{ $kpi['tickets_open'] }} open support
                                    ticket(s)</div><span
                                    class="font-mono text-[10px] text-accent-coral">{{ __('urgent') }}</span>
                            </div>
                            <div class="text-[11.5px] text-ink-600 mt-1">
                                {{ __('Triage at /admin/support before SLA breach.') }}</div>
                        </a>
                    @endif
                    @if ($kpi['devices_total'] > 0 && $kpi['devices_online'] / max(1, $kpi['devices_total']) < 0.5)
                        <a href="{{ url('/admin/workspaces') }}"
                            class="block rounded-xl border border-accent-amber/30 bg-accent-amber/5 p-3 hover:bg-accent-amber/10">
                            <div class="flex items-center justify-between">
                                <div class="text-[13px] font-semibold">
                                    {{ $kpi['devices_total'] - $kpi['devices_online'] }} {{ __('devices offline') }}
                                </div><span
                                    class="font-mono text-[10px] text-accent-amber">{{ __('watch') }}</span>
                            </div>
                            <div class="text-[11.5px] text-ink-600 mt-1">
                                {{ __('Customers may not be receiving messages.') }}</div>
                        </a>
                    @endif
                    @if ($kpi['users_new'] > 0)
                        <a href="{{ url('/admin/users') }}"
                            class="block rounded-xl border border-paper-200 p-3 hover:bg-paper-50">
                            <div class="flex items-center justify-between">
                                <div class="text-[13px] font-semibold">{{ $kpi['users_new'] }} new user(s) this
                                    window</div><span
                                    class="font-mono text-[10px] text-ink-500">{{ __('queue') }}</span>
                            </div>
                            <div class="text-[11.5px] text-ink-600 mt-1">
                                {{ __('Review at /admin/users — check KYC if required.') }}</div>
                        </a>
                    @endif
                    @if ($kpi['tickets_open'] === 0 && $kpi['users_new'] === 0)
                        <div class="rounded-xl border border-wa-green/40 bg-wa-bubble p-3">
                            <div class="flex items-center justify-between">
                                <div class="text-[13px] font-semibold text-wa-deep">{{ __('All clear') }}</div><span
                                    class="font-mono text-[10px] text-wa-deep">{{ __('normal') }}</span>
                            </div>
                            <div class="text-[11.5px] text-ink-600 mt-1">
                                {{ __('No open tickets and no pending reviews.') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

    </main>

    {{-- Charts wired by resources/js/charts/admin-analytics-index.js
 (auto-loaded via the page="admin-analytics-index" key in app.js). --}}

</x-layouts.admin>
