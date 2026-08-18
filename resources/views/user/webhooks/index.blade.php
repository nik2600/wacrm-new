<x-layouts.user :title="__('Webhooks')" nav-key="more" page="user-webhooks-index">

    @php
        $stats = $stats ?? [
            'endpoints' => 0,
            'active' => 0,
            'paused' => 0,
            'events24' => 0,
            'successRate' => 100,
            'latencyP95' => 0,
        ];
        $statusCounts = $statusCounts ?? ['all' => 0, 'active' => 0, 'paused' => 0, 'failing' => 0];
        $eventMix = $eventMix ?? [];
        $currentStatus = $currentStatus ?? 'all';
        $currentQuery = $currentQuery ?? '';
    @endphp

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
                        {{ __('More / Webhooks') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate"><span
                            class="italic text-wa-deep">{{ __('Webhooks') }}</span> &amp; events</div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"
                    data-stat="endpoints">{{ $stats['endpoints'] }} {{ __('endpoints') }}</span>
                <button type="button" id="wh-test-fire-all"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 11h10M8 4v9M5 7l3-3 3 3" />
                    </svg>
                    Test fire
                </button>
                <a href="{{ route('user.webhooks.incoming') }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5"
                    title="{{ __('Generate a URL that receives data from any service') }}">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M13 8H3m0 0l4-4M3 8l4 4" />
                    </svg>
                    {{ __('Incoming') }}
                </a>
                <a href="{{ url('/docs') }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View docs') }}</a>
                <a href="{{ url('/webhooks/create') }}"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    New webhook
                </a>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6" data-wh-state data-wh-status="{{ $currentStatus }}"
        data-wh-search="{{ $currentQuery }}"
        data-wh-page="{{ method_exists($hooks, 'currentPage') ? $hooks->currentPage() : 1 }}">

        <!-- KPI strip -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Endpoints') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono"><span
                            data-stat="active">{{ $stats['active'] }}</span> active</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="endpoints">{{ $stats['endpoints'] }}</span>
                    <span class="text-[11px] text-ink-500"><span data-stat="paused">{{ $stats['paused'] }}</span>
                        paused</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Events fired (24h)') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('live') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="events24">{{ number_format($stats['events24']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('across all') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Success rate') }}</span>
                    <span
                        class="text-[10px] {{ $stats['successRate'] >= 95 ? 'text-wa-deep' : 'text-accent-amber' }} font-mono">{{ $stats['successRate'] >= 95 ? 'healthy' : 'degraded' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="successRate">{{ number_format($stats['successRate'], 1) }}%</span>
                    <span class="text-[11px] text-ink-500">{{ __('last 24h') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Avg latency') }}</span>
                    <span
                        class="text-[10px] {{ $stats['latencyP95'] > 500 ? 'text-accent-amber' : 'text-wa-deep' }} font-mono">target
                        500ms</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-stat="latencyP95">{{ $stats['latencyP95'] }}ms</span>
                    <span class="text-[11px] text-ink-500">p95</span>
                </div>
            </div>
        </section>

        <!-- Activity chart + event mix -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Delivery activity') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Events over time') }}</h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500">
                        <button class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0">24h</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">7d</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">30d</button>
                    </div>
                </div>
                <div id="chart-activity" class="h-[260px]"></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Event mix') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Top events (24h)') }}</h3>
                <div class="space-y-3" id="wh-event-mix">
                    @forelse ($eventMix as $row)
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span class="font-mono text-ink-700">{{ $row['name'] }}</span>
                                <span class="font-mono text-ink-900">{{ number_format($row['count']) }}</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $row['color'] }}" style="width:{{ $row['pct'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No recent events found. Events fired in the last 24 hours will appear here.',
                        ])
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Endpoints table -->
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card" data-list-grid
            data-list-grid-key="webhooks">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 overflow-x-auto max-w-full" id="status-tabs">
                    @foreach (['all' => 'All', 'active' => 'Active', 'paused' => 'Paused', 'failing' => 'Failing'] as $key => $label)
                        <button data-wh-filter="status" data-wh-value="{{ $key }}"
                            class="status-tab shrink-0 whitespace-nowrap px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">
                            {{ $label }} <span class="ml-1 font-mono text-[10px] opacity-80"
                                data-wh-status-count="{{ $key }}">{{ $statusCounts[$key] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2 flex-1 min-w-0 sm:min-w-[260px] justify-end">
                    <div class="relative flex-1 max-w-[320px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="wh-search" type="search" value="{{ $currentQuery }}"
                            placeholder="{{ __('Search by URL or event...') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <x-list-grid-toggle />
                </div>
            </div>

            <div class="overflow-x-auto" data-list-grid-list>
                <table class="w-full text-[12.5px]" data-list-grid-source>
                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                        <tr>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-10">
                                <input type="checkbox"
                                    class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep" /></th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Endpoint') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Events') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Last fired') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Success (24h)') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Latency') }}</th>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                {{ __('Status') }}</th>
                            <th
                                class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[170px]">
                                {{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="wh-rows" class="divide-y divide-paper-200">
                        @include('user.webhooks._rows', ['hooks' => $hooks])
                    </tbody>
                </table>
            </div>
            <div class="hidden p-4" data-list-grid-grid></div>

            <div id="wh-results-footer"
                class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500 {{ (method_exists($hooks, 'total') ? $hooks->total() : $statusCounts['all'] ?? 0) > 0 ? '' : 'hidden' }}">
                <div>{{ __('Showing') }} <span class="font-mono text-ink-900"
                        data-wh-shown>{{ $hooks->count() }}</span> of <span class="font-mono text-ink-900"
                        data-wh-total>{{ method_exists($hooks, 'total') ? number_format($hooks->total()) : number_format($statusCounts['all']) }}</span>
                    filtered</div>
            </div>
        </section>
        <div id="wh-pagination">
            @include('user.partials.pagination', [
                'paginator' => $hooks,
                'dataAttr' => 'data-wh-page',
                'label' => 'webhooks',
            ])
        </div>

        <!-- Recent deliveries + Tip -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recent deliveries') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">Last {{ $recent->count() ?: 8 }}
                            firings</h3>
                    </div>
                    @if ($hooks->count() > 0)
                        <a href="{{ url('/webhooks/' . $hooks->first()->id) }}"
                            class="text-[11px] text-wa-deep font-semibold hover:underline">View all</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Time') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Event') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Endpoint') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Code') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Latency') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="wh-recent" class="divide-y divide-paper-200">
                            @include('user.webhooks._recent', ['recent' => $recent])
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">{{ __('Tip') }}
                </div>
                <div class="font-serif text-[22px] leading-tight mt-1">{{ __('Verify with HMAC') }}</div>
                <p class="mt-2 text-[12px] text-paper-0/80 leading-relaxed">
                    {{ __('Every payload is signed with your endpoint secret in the') }} <span
                        class="font-mono">{{ \App\Support\Brand::webhookSignatureHeader() }}</span> header. Verify it before processing /
                    that's how you know the event is really from us.</p>
                <a href="{{ url('/webhooks/create') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">
                    Add an endpoint
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg>
                </a>
            </div>
        </section>

    </main>

</x-layouts.user>
