<x-layouts.admin :title="__('Overview')" admin-key="overview" page="admin-overview">

    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <span class="uppercase tracking-[0.16em]">{{ __('Admin') }}</span>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Overview') }}</span>
        </div>

        <!-- Search -->
        <div class="relative flex-1 max-w-[480px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search workspaces, users, invoices…') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">⌘K</kbd>
        </div>

        <!-- Right cluster (injected by admin-shell.js) -->
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <!-- Page body -->
    <main class="px-4 sm:px-7 py-7 space-y-5">

        <!-- Heading -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Workspace · Admin') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Admin') }}
                    <span class="italic text-wa-deep">{{ __('dashboard') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Platform health, revenue, usage, and workspace activity in one place.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
                {{-- "Customize" + "Filter" buttons removed — they had no
 backing action. The window picker below already drives
 the dashboard date range, which was their only useful
 purpose. --}}
                {{-- Window picker — re-renders the page with ?window=<key>. --}}
                <form method="get" action="{{ url()->current() }}" class="inline">
                    <select name="window" onchange="this.form.submit()"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                        @php $win = $window ?? '7d'; @endphp
                        <option value="24h" @selected($win === '24h')>{{ __('Last 24 hours') }}</option>
                        <option value="7d" @selected($win === '7d')>{{ __('Last 7 days') }}</option>
                        <option value="30d" @selected($win === '30d')>{{ __('Last 30 days') }}</option>
                        <option value="90d" @selected($win === '90d')>{{ __('Last 90 days') }}</option>
                        <option value="1y" @selected($win === '1y')>{{ __('Last year') }}</option>
                    </select>
                </form>
                <a href="{{ route('admin.workspaces.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-medium hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add workspace
                </a>
            </div>
        </div>

        {{-- KPI row — values + deltas come from OverviewController::kpis(). --}}
        @php
            $kpiCards = [
                ['key' => 'income', 'label' => 'Total income'],
                ['key' => 'profit', 'label' => 'Profit'],
                ['key' => 'views', 'label' => 'Total views'],
                ['key' => 'conversion', 'label' => 'Conversion rate'],
            ];
        @endphp
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($kpiCards as $card)
                @php $k = $kpis[$card['key']]; @endphp
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-500">{{ $card['label'] }}</div>
                    <div class="font-semibold text-[24px] mt-2">{{ $k['display'] }}</div>
                    <div
                        class="mt-2 inline-flex items-center gap-1 rounded-full {{ $k['positive'] ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-accent-coral' }} px-2 py-0.5 text-[10px] font-mono">
                        {{ ($k['positive'] ? '+' : '') . number_format($k['delta'], 2) }}%
                    </div>
                    <span class="text-[10.5px] text-ink-400 ml-1">{{ __('vs previous period') }}</span>
                </div>
            @endforeach
        </section>

        <!-- Revenue + Country -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Revenue over time') }}</h2>
                        <div class="flex items-center gap-5 mt-2 text-[11px]">
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-wa-deep"></span>Total revenue
                                <b>{{ $revenue['total'] }}</b></span>
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-accent-amber"></span>Total target
                                <b>{{ $revenue['totalTarget'] }}</b></span>
                        </div>
                    </div>
                </div>
                <div id="admin-revenue-chart"></div>
            </div>

            <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Session by country') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Top sessions by region') }}</p>
                    </div>
                </div>
                @php
                    $countryTones = ['#D9E5F2', '#FBE9E7', 'var(--color-wa-bubble)', '#FFF4E0', '#F3E9FF'];
                    $maxPct = collect($countries)->max('percent') ?: 1;
                @endphp
                <div class="space-y-4">
                    @forelse ($countries as $i => $c)
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full grid place-items-center text-[10px]"
                                        style="background: {{ $countryTones[$i % count($countryTones)] }}">{{ $c['code'] }}</span>
                                    {{ $c['name'] }}
                                </span>
                                <span class="font-mono">{{ number_format($c['count']) }} · {{ $c['percent'] }}%</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep"
                                    style="width: {{ round(($c['percent'] / $maxPct) * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[12px] text-ink-500">
                            {{ __("No country data yet — users haven't shared their region.") }}</div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Three secondary charts -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Sales by region') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Platform revenue share') }}</p>
                    </div>
                </div>
                <div id="admin-region-chart"></div>
            </div>

            <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Sales by platform') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('E-commerce integrations') }}</p>
                    </div>
                </div>
                <div id="admin-platform-chart"></div>
            </div>

            <div class="sm:col-span-2 lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Registered users') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Overview of user seats') }}</p>
                    </div>
                </div>
                <div id="admin-users-chart"></div>
                <div class="grid grid-cols-2 gap-3 mt-2 text-[12px]">
                    <div class="border-t border-paper-200 pt-3">
                        <div class="font-semibold">{{ number_format($plans['premium']) }}</div>
                        <div class="text-[10.5px] text-ink-500">{{ __('Premium plan') }}</div>
                    </div>
                    <div class="border-t border-paper-200 pt-3">
                        <div class="font-semibold">{{ number_format($plans['basic']) }}</div>
                        <div class="text-[10.5px] text-ink-500">{{ __('Basic plan') }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Workspace activity + alerts -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Workspace activity') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Recent high-value admin events') }}</p>
                    </div>
                    <a href="{{ route('admin.workspaces.index') }}"
                        class="rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 py-1.5 text-[11.5px] font-semibold">{{ __('View all') }}</a>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[860px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3 w-[210px]">{{ __('Workspace') }}</th>
                            <th class="text-left px-3 py-3">{{ __('Owner') }}</th>
                            <th class="text-left px-3 py-3 w-[120px]">{{ __('Plan') }}</th>
                            <th class="text-left px-3 py-3 w-[120px]">{{ __('Messages') }}</th>
                            <th class="text-left px-3 py-3 w-[120px]">{{ __('Health') }}</th>
                            <th class="text-left px-4 py-3 w-[90px]">{{ __('MRR') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($workspaces as $ws)
                            <tr class="hover:bg-paper-50/60 cursor-pointer"
                                onclick="window.location='{{ $ws['detailUrl'] }}'">
                                <td class="px-4 py-3">
                                    <div class="font-semibold truncate">{{ $ws['name'] }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ $ws['industry'] }}</div>
                                </td>
                                <td class="px-3 py-3">{{ $ws['owner'] }}</td>
                                <td class="px-3 py-3"><span class="px-2 py-1 rounded-full text-[10px] font-semibold"
                                        style="background: {{ $ws['planTone']['bg'] }}; color: {{ $ws['planTone']['text'] }}">{{ $ws['plan'] }}</span>
                                </td>
                                <td class="px-3 py-3 font-mono">{{ $ws['messages'] }}</td>
                                <td class="px-3 py-3 text-{{ $ws['health']['tone'] }} font-mono">
                                    {{ $ws['health']['label'] }}</td>
                                <td class="px-4 py-3 font-mono">{{ $ws['mrr'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-ink-500">
                                    {{ __('No workspaces yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Admin alerts') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Priority queue') }}</p>
                    </div>
                    <span
                        class="rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/30 px-2 py-0.5 text-[10px] font-semibold">{{ $alerts['open'] }}
                        {{ __('open') }}</span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($alerts['items'] as $a)
                        @php $sev = $a['severity'] ?? 'low'; @endphp
                        <div
                            class="rounded-xl p-3 {{ $sev === 'high' ? 'border border-accent-coral/30 bg-accent-coral/10' : 'border border-paper-200' }}">
                            <div class="text-[13px] font-semibold">{{ $a['title'] }}</div>
                            <div class="text-[11.5px] text-ink-600 mt-1">{{ $a['detail'] }}</div>
                        </div>
                    @empty
                        <div class="text-[12px] text-ink-500 px-1 py-3">{{ __('All systems healthy — no alerts.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- Chart hydration data — admin-overview.js reads window.adminOverview. --}}
        <script>
            window.adminOverview = {
                revenue: @json($revenue),
                region: @json($region),
                platform: @json($platform),
                plans: @json($plans),
            };
        </script>

    </main>

</x-layouts.admin>
