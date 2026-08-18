<x-layouts.user :title="__('Store overview')" nav-key="connect" page="user-store-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            @include('user.store._sidebar', ['current' => 'overview', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div class="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            {{ __('Store / Overview') }}</div>
                        <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Sales') }} <span
                                class="italic text-wa-deep">{{ __('overview') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-1">
                            {{ __('Last 30 days. Orders are merged from every channel — WABA, storefront, Twilio.') }}
                        </p>
                    </div>
                    <a href="{{ route('user.store.products.create') }}"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2"><svg
                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M8 3v10M3 8h10" />
                        </svg>{{ __('Add product') }}</a>
                </div>

                <!-- KPI strip -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @php
                        // Currency used for storefront sales display.
                        $storeCur =
                            (string) ($sf->currency_code ?? \App\Models\SystemSetting::get('default_currency', 'USD'));
                        $kpis = [
                            [
                                'label' => 'Revenue (30d)',
                                'value' => \App\Support\FormatSettings::formatIn(
                                    ($stats['revenue30'] ?? 0) / 100,
                                    $storeCur,
                                ),
                            ],
                            ['label' => 'Orders (30d)', 'value' => number_format($stats['orders30'] ?? 0)],
                            [
                                'label' => 'Avg order',
                                'value' => \App\Support\FormatSettings::formatIn(($stats['aov'] ?? 0) / 100, $storeCur),
                            ],
                            ['label' => 'Products', 'value' => number_format($stats['products'] ?? 0)],
                            ['label' => 'Visits (30d)', 'value' => number_format($stats['storefrontViews30'] ?? 0)],
                        ];
                    @endphp
                    @foreach ($kpis as $k)
                        <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card min-w-0">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ $k['label'] }}</div>
                            <div class="mt-2 font-serif text-[24px] leading-none truncate">{{ $k['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                <!-- Sales chart + top products -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Sales trend') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Revenue over time') }}</h3>
                        <div id="store-sales-chart" class="h-[260px]" data-labels='@json($salesByDay['labels'])'
                            data-revenue='@json($salesByDay['revenue'])' data-orders='@json($salesByDay['orders'])'>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top products (30d)') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Bestsellers') }}</h3>
                        <div class="space-y-3">
                            @forelse ($topProducts as $tp)
                                @php $max = max(array_column($topProducts, 'qty') ?: [1]); @endphp
                                <div>
                                    <div class="flex items-center justify-between text-[12px] mb-1">
                                        <span class="font-mono text-ink-700 truncate">{{ $tp['name'] }}</span>
                                        <span class="font-mono text-ink-900">{{ $tp['qty'] }}</span>
                                    </div>
                                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-wa-deep"
                                            style="width:{{ max(2, (int) round(($tp['qty'] / $max) * 100)) }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <div
                                    class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-6 text-center text-[12px] text-ink-500">
                                    {{ __('No sales yet — share your store link.') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Recent orders -->
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                        <h3 class="font-serif text-[18px]">{{ __('Recent orders') }}</h3>
                        <a href="{{ route('user.store.orders.index') }}"
                            class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('View all') }}</a>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('When') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Customer') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Source') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Items') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Total') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Status') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Open') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($recentOrders as $o)
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-3 font-mono text-[11px] text-ink-700">
                                        {{ $o->created_at->format('M d, H:i') }}</td>
                                    <td class="px-2 py-3">
                                        <div class="font-medium">{{ $o->customer_name ?: '—' }}</div>
                                        <div class="text-[10.5px] text-ink-500 font-mono">{{ $o->customer_phone }}
                                        </div>
                                    </td>
                                    <td class="px-2 py-3"><span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $o->source }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-[11.5px] text-ink-700">{{ count($o->items_json ?? []) }}
                                        item{{ count($o->items_json ?? []) === 1 ? '' : 's' }}</td>
                                    <td class="px-2 py-3 text-right font-semibold">{{ $o->total_display }}</td>
                                    <td class="px-2 py-3"><span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-{{ $o->status === 'paid' ? 'wa-mint text-wa-deep' : ($o->status === 'cancelled' ? 'accent-coral/15 text-accent-coral' : 'paper-100 text-ink-700') }} text-[10.5px] font-mono">{{ $o->status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right"><a
                                            href="{{ route('user.store.orders.show', $o->id) }}"
                                            class="text-[11px] text-wa-deep font-semibold hover:underline">Open</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-ink-500">
                                        <div class="font-serif text-[18px]">{{ __('No orders yet') }}</div>
                                        <p class="mt-1 text-[12px]">
                                            {{ __("Once customers order from your storefront or WhatsApp, they'll appear here.") }}
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
