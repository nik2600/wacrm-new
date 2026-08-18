<x-layouts.admin :title="__('Premium')" admin-key="premium" page="admin-premium">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Premium') }}</span>
        </div>
        <div class="relative flex-1 max-w-[480px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search plans, workspaces...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Plans & subscriptions') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Premium') }}
                    <span class="italic text-wa-deep">{{ __('dashboard') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Plan distribution, revenue contribution, upgrades and conversion across the platform.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <form method="get" action="{{ url()->current() }}">
                    <select name="window" onchange="this.form.submit()"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                        <option value="7d" @selected($window === '7d')>{{ __('Last 7 days') }}</option>
                        <option value="30d" @selected($window === '30d')>{{ __('Last 30 days') }}</option>
                        <option value="90d" @selected($window === '90d')>{{ __('Last 90 days') }}</option>
                        <option value="1y" @selected($window === '1y')>{{ __('Last year') }}</option>
                    </select>
                </form>
                <a href="{{ route('admin.packages.index') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Packages') }}</a>
                <a href="{{ route('admin.packages.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-medium hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    New plan
                </a>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-500">{{ __('Paid workspaces') }}</div>
                <div class="font-semibold text-[24px] mt-2">{{ $kpis['paid']['display'] }}</div>
                <div class="mt-2 text-[10.5px] text-ink-400">{{ __('On a non-free plan') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-500">{{ __('Free workspaces') }}</div>
                <div class="font-semibold text-[24px] mt-2">{{ $kpis['free']['display'] }}</div>
                <div class="mt-2 text-[10.5px] text-ink-400">{{ __('Upgrade target') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-500">{{ __('ARPU') }}</div>
                <div class="font-semibold text-[24px] mt-2">{{ $kpis['arpu']['display'] }}</div>
                <div class="mt-2 text-[10.5px] text-ink-400">{{ __('Avg revenue / workspace') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-500">{{ __('Trial → paid') }}</div>
                <div class="font-semibold text-[24px] mt-2">{{ $kpis['conv']['display'] }}</div>
                <div
                    class="mt-2 inline-flex items-center gap-1 rounded-full {{ $kpis['conv']['positive'] ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-accent-coral' }} px-2 py-0.5 text-[10px] font-mono">
                    {{ ($kpis['conv']['positive'] ? '+' : '') . number_format($kpis['conv']['delta'], 2) }}%
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px]">{{ __('Plan distribution') }}</h2>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Workspaces per plan') }}</p>
                <div id="premium-mix-chart"></div>
            </div>
            <div class="lg:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px]">{{ __('Revenue by plan') }}</h2>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Window total per plan') }}</p>
                <div id="premium-revenue-chart"></div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px]">{{ __('New paid signups') }}</h2>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Workspaces moving to a paid plan, daily') }}</p>
                <div id="premium-upgrades-chart"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px]">{{ __('Most popular plans') }}</h2>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Last 90 days') }}</p>
                <ul class="mt-4 space-y-3">
                    @forelse ($topPlans as $p)
                        <li class="flex items-center justify-between">
                            <div>
                                <div class="text-[13px] font-semibold">{{ $p['name'] }}</div>
                                <div class="text-[11px] text-ink-500">{{ $p['orders'] }} {{ __('orders') }}</div>
                            </div>
                            <div class="font-mono text-[12px]">{{ $p['revenue'] }}</div>
                        </li>
                    @empty
                        <li class="text-[12px] text-ink-500">{{ __('No paid orders in last 90 days.') }}</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-[14px]">{{ __('All plans') }}</h2>
                    <p class="text-[11px] text-ink-500 mt-1">
                        {{ __('Workspace count, MRR contribution, window revenue') }}</p>
                </div>
                <a href="{{ route('admin.packages.index') }}"
                    class="rounded-full border border-paper-200 px-3 py-1.5 text-[11.5px] font-semibold hover:bg-paper-50">{{ __('Manage packages') }}</a>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-3">{{ __('Plan') }}</th>
                        <th class="text-left px-3 py-3 w-[120px]">{{ __('Price') }}</th>
                        <th class="text-left px-3 py-3 w-[120px]">{{ __('Duration') }}</th>
                        <th class="text-left px-3 py-3 w-[100px]">{{ __('Workspaces') }}</th>
                        <th class="text-left px-3 py-3 w-[140px]">{{ __('MRR') }}</th>
                        <th class="text-left px-3 py-3 w-[140px]">{{ __('Revenue (window)') }}</th>
                        <th class="text-left px-3 py-3 w-[100px]">{{ __('Status') }}</th>
                        <th class="text-left px-4 py-3 w-[80px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($planTable as $p)
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-semibold">{{ $p['name'] }} @if ($p['free'])
                                    <span class="ml-2 text-[10px] text-ink-500 font-mono">{{ __('FREE') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono">{{ $p['price'] }}</td>
                            <td class="px-3 py-3">{{ $p['duration'] }}</td>
                            <td class="px-3 py-3 font-mono">{{ $p['count'] }}</td>
                            <td class="px-3 py-3 font-mono">{{ $p['mrr'] }}</td>
                            <td class="px-3 py-3 font-mono">{{ $p['revenue'] }}</td>
                            <td class="px-3 py-3">
                                <span
                                    class="px-2 py-1 rounded-full {{ $p['status'] ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-500' }} text-[10px] font-semibold">{{ $p['status'] ? 'Active' : 'Off' }}</span>
                            </td>
                            <td class="px-4 py-3"><a class="text-wa-deep hover:underline text-[11.5px]"
                                    href="{{ $p['edit'] }}">{{ __('Edit') }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-ink-500">
                                {{ __('No plans defined yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>

        <script>
            window.adminPremium = {
                planMix: @json($planMix),
                planRevenue: @json($planRevenue),
                upgradesDaily: @json($upgradesDaily),
            };
        </script>
    </main>

</x-layouts.admin>
