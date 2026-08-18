<x-layouts.admin :title="__('Admin · Billing analytics')" admin-key="billing-history" page="admin-billing-history-analytics">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ route('admin.billing-history.index') }}"
                class="hover:text-ink-900">{{ __('Billing history') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>
        <div class="ml-auto flex items-center flex-wrap gap-2">
            <form method="get" action="{{ url()->current() }}" class="inline">
                <select name="window" onchange="this.form.submit()"
                    class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    <option value="7d" @selected($window === '7d')>{{ __('Last 7 days') }}</option>
                    <option value="30d" @selected($window === '30d')>{{ __('Last 30 days') }}</option>
                    <option value="90d" @selected($window === '90d')>{{ __('Last 90 days') }}</option>
                    <option value="1y" @selected($window === '1y')>{{ __('Last year') }}</option>
                </select>
            </form>
            <a href="{{ route('admin.billing-history.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View ledger') }}</a>
        </div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Billing analytics') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Billing') }}
                    <span class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Cash flow, payment success, gateway split, refund trends across the platform.') }}</p>
            </div>
        </div>

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Gross revenue') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['gross'] }}</div>
                <div
                    class="mt-2 inline-flex items-center gap-1 rounded-full {{ $stats['grossDelta']['positive'] ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-accent-coral' }} px-2 py-0.5 text-[10px] font-mono">
                    {{ ($stats['grossDelta']['positive'] ? '+' : '') . $stats['grossDelta']['pct'] }}%
                </div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Net revenue') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['net'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['netPct'] }}% net</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Auth success') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['successPct'] }}%</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['paidCount'] }} / {{ $stats['totalCharges'] }}
                </div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Failed') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['failedAmount'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['failedCount'] }} {{ __('events') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Refunds') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1">{{ $stats['refundsAmt'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['refundsCount'] }} {{ __('events') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Chargebacks') }}</div>
                <div class="font-serif text-[30px] leading-none mt-1 text-accent-coral">{{ $stats['chargebacksAmt'] }}
                </div>
                <div class="text-[11px] text-accent-coral mt-2">{{ $stats['chargebacksCount'] }} {{ __('disputes') }}
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Cash flow') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Charges, refunds & failures') }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Charges</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Refunds</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>Failed</span>
                    </div>
                </div>
                <div id="chart-billing-trend" class="h-[280px]"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px]">{{ __('Gateway share') }}</h2>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Paid revenue split by provider') }}</p>
                <div id="chart-gateway-mix" class="mt-2"></div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-[14px]">{{ __('Top paying workspaces') }}</h2>
                        <p class="text-[11px] text-ink-500 mt-1">{{ __('Highest spend in window') }}</p>
                    </div>
                    <a href="{{ route('admin.workspaces.index') }}"
                        class="rounded-full border border-paper-200 px-3 py-1.5 text-[11.5px] font-semibold hover:bg-paper-50">{{ __('All workspaces') }}</a>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Workspace') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('Orders') }}</th>
                            <th class="text-left px-4 py-3 w-[140px]">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($topWorkspaces as $w)
                            <tr class="hover:bg-paper-50/60 cursor-pointer"
                                onclick="window.location='{{ $w['href'] }}'">
                                <td class="px-4 py-3 font-semibold">{{ $w['name'] }}</td>
                                <td class="px-3 py-3 font-mono">{{ $w['orders'] }}</td>
                                <td class="px-4 py-3 font-mono">{{ $w['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-ink-500">
                                    {{ __('No paid orders yet in this window.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h2 class="font-semibold text-[14px]">{{ __('Order status mix') }}</h2>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Count by lifecycle state') }}</p>
                <div id="chart-status-mix" class="mt-2"></div>
            </div>
        </section>

        <script>
            window.adminBillingAnalytics = {
                trend: @json($trend),
                gatewayMix: @json($gatewayMix),
                statusMix: @json($statusMix),
            };
        </script>
    </main>

</x-layouts.admin>
