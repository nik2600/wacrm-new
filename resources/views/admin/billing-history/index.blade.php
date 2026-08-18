<x-layouts.admin :title="__('Admin · Billing history')" admin-key="billing-history" page="admin-billing-history-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Billing history') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Billing & plans · Billing history') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Billing') }}
                    <span class="italic text-wa-deep">{{ __('history') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every payment event the platform has processed — charges, refunds, and retries across every workspace.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                <form method="get" action="{{ url()->current() }}" class="inline">
                    @foreach (['status' => $statusF, 'gateway' => $gatewayF, 'q' => $q] as $k => $v)
                        @if ($v !== null && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <select name="window" onchange="this.form.submit()"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                        <option value="7d" @selected($window === '7d')>{{ __('Last 7 days') }}</option>
                        <option value="30d" @selected($window === '30d')>{{ __('Last 30 days') }}</option>
                        <option value="90d" @selected($window === '90d')>{{ __('Last 90 days') }}</option>
                        <option value="1y" @selected($window === '1y')>{{ __('Last year') }}</option>
                        <option value="all" @selected($window === 'all')>{{ __('All time') }}</option>
                    </select>
                </form>
                <a href="{{ route('admin.billing-history.analytics') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                    </svg>
                    Analytics
                </a>
            </div>
        </div>

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Gross revenue') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['gross'] }}</div>
                <div
                    class="mt-2 inline-flex items-center gap-1 rounded-full {{ $stats['grossDelta']['positive'] ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-accent-coral' }} px-2 py-0.5 text-[10px] font-mono">
                    {{ ($stats['grossDelta']['positive'] ? '+' : '') . $stats['grossDelta']['pct'] }}%
                </div>
                <span class="text-[10.5px] text-ink-400 ml-1">{{ __('vs prev period') }}</span>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Successful charges') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['charges'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['successPct'] }}% success rate</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Failed') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['failed'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('to retry / dunning') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Refunds issued') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['refunds'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ $stats['refundsAmt'] }} {{ __('total') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Chargebacks') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">{{ $stats['chargebacks'] }}
                </div>
                <div class="text-[11px] text-accent-coral mt-2">{{ $stats['chargebacksAmt'] }} {{ __('disputed') }}
                </div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Trend') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Daily charges & refunds') }}</h2>
                </div>
                <div class="flex items-center gap-3 text-[11px] text-ink-500">
                    <span class="flex items-center gap-1.5"><span
                            class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Charges</span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Refunds</span>
                </div>
            </div>
            <div id="chart-billing" class="h-[260px]"></div>
        </section>

        <form method="get" action="{{ url()->current() }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            <input type="hidden" name="window" value="{{ $window }}">
            @php $statusPills = ['all' => 'All', 'paid' => 'Successful', 'failed' => 'Failed', 'refunded' => 'Refunded', 'pending' => 'Pending']; @endphp
            @foreach ($statusPills as $k => $label)
                <button type="submit" name="status" value="{{ $k }}" @class([
                    'inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition',
                    'bg-ink-900 text-paper-0' => $statusF === $k,
                    'text-ink-600 hover:bg-paper-50' => $statusF !== $k,
                ])>
                    {{ $label }}
                </button>
            @endforeach
            <div class="flex-1"></div>
            <select name="gateway" onchange="this.form.submit()"
                class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                <option value="">{{ __('All gateways') }}</option>
                @foreach ($gateways as $g)
                    <option value="{{ $g }}" @selected($gatewayF === $g)>{{ ucfirst($g) }}</option>
                @endforeach
            </select>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                    fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input name="q" value="{{ $q }}"
                    placeholder="{{ __('Search order #, workspace, customer...') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-72 focus:outline-none focus:border-wa-deep">
            </div>
        </form>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[1040px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 w-[150px]">{{ __('Date') }}</th>
                        <th class="text-left px-3 py-2.5 w-[170px]">{{ __('Order #') }}</th>
                        <th class="text-left px-3 py-2.5">{{ __('Workspace') }}</th>
                        <th class="text-left px-3 py-2.5 w-[110px]">{{ __('Gateway') }}</th>
                        <th class="text-left px-3 py-2.5 w-[140px]">{{ __('Plan') }}</th>
                        <th class="text-right px-3 py-2.5 w-[110px]">{{ __('Amount') }}</th>
                        <th class="text-center px-3 py-2.5 w-[110px]">{{ __('Status') }}</th>
                        <th class="text-center px-3 py-2.5 w-[60px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($orders as $o)
                        @php
                            $tone = match ($o->status) {
                                'paid' => 'bg-wa-mint text-wa-deep border-wa-green/40',
                                'pending' => 'bg-accent-amber/10 text-accent-amber border-accent-amber/40',
                                'failed' => 'bg-accent-coral/10 text-accent-coral border-accent-coral/40',
                                'refunded' => 'bg-[#F3E9FF] text-[#5B3D8A] border-[#D9CFFF]',
                                default => 'bg-paper-100 text-ink-600 border-paper-200',
                            };
                            $rowTone =
                                $o->status === 'failed'
                                    ? 'bg-accent-amber/5'
                                    : ($o->status === 'refunded'
                                        ? 'bg-[#F3E9FF]/30'
                                        : '');
                            $ws = $o->workspace ?? \App\Models\Workspace::find($o->workspace_id);
                            $package = $o->package_id ? \App\Models\Package::find($o->package_id) : null;
                        @endphp
                        <tr class="hover:bg-paper-50/60 {{ $rowTone }}">
                            <td class="px-4 py-3 font-mono text-[11px]">{{ $o->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-3 font-mono text-[11px]">{{ $o->order_number }}</td>
                            <td class="px-3 py-3 min-w-0">
                                <div class="font-semibold leading-tight truncate">
                                    {{ $ws?->name ?? 'Workspace #' . $o->workspace_id }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono truncate">
                                    {{ $o->customer_email ?? ($o->customer_name ?? '—') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px]">
                                {{ $o->gateway_slug ? ucfirst($o->gateway_slug) : '—' }}</td>
                            <td class="px-3 py-3 text-[11.5px] truncate">{{ $package?->pname ?? '—' }}</td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ $o->status === 'paid' ? 'text-wa-deep' : ($o->status === 'refunded' || $o->status === 'failed' ? 'text-accent-coral' : '') }}">
                                {{ $o->status === 'refunded' ? '-' : '' }}{!! \App\Support\FormatSettings::formatIn((float) ($o->total_amount ?? $o->amount), $o->currency) !!}
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tone }} text-[10.5px] font-mono border">
                                    <span
                                        class="w-1.5 h-1.5 rounded-full {{ $o->status === 'paid' ? 'bg-wa-green' : ($o->status === 'failed' ? 'bg-accent-coral' : 'bg-paper-300') }}"></span>{{ ucfirst($o->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if ($ws)
                                    <a href="{{ route('admin.workspaces.detail', $ws->id) }}"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-paper-200 hover:border-wa-deep hover:bg-wa-bubble text-wa-deep mx-auto"
                                        title="{{ __('Open workspace') }}">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="1.7">
                                            <path d="M3 8h10M9 4l4 4-4 4" />
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-ink-500">
                                {{ __('No payment events match.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 rounded-b-2xl flex items-center justify-between">
                <div class="text-[11px] font-mono text-ink-500">
                    Showing {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} of
                    {{ number_format($orders->total()) }} {{ __('events') }}
                </div>
                <div>{{ $orders->onEachSide(1)->links() }}</div>
            </div>
        </div>

        <script>
            window.adminBillingHistory = {
                trend: @json($trend)
            };
        </script>
    </main>

</x-layouts.admin>
