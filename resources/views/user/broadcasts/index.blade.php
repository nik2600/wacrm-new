@php
    $broadcasts = $broadcasts ?? collect();
    $stats = $stats ?? [
        'total' => 0,
        'sent' => 0,
        'delivered' => 0,
        'read' => 0,
        'failed' => 0,
        'processing' => 0,
        'queued' => 0,
    ];
    $statusCounts = $statusCounts ?? [
        'all' => 0,
        'scheduled' => 0,
        'processing' => 0,
        'completed' => 0,
        'completed_with_errors' => 0,
        'failed' => 0,
    ];
    $currentStatus = $currentStatus ?? 'all';
    $currentRange = $currentRange ?? 'all';
    $currentSearch = $currentSearch ?? '';
    $deliveryPct = $stats['sent'] > 0 ? round(($stats['delivered'] / max($stats['sent'], 1)) * 100, 1) : 0;
    $readPct = $stats['delivered'] > 0 ? round(($stats['read'] / max($stats['delivered'], 1)) * 100, 1) : 0;
    $failedPct = $stats['sent'] > 0 ? round(($stats['failed'] / max($stats['sent'], 1)) * 100, 1) : 0;
    $recentBroadcasts = $broadcasts->take(5);
@endphp

<x-layouts.user :title="__('Broadcasts')" nav-key="more" page="user-broadcasts-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7" data-bc-state data-bc-status="{{ $currentStatus }}"
        data-bc-range="{{ $currentRange }}" data-bc-search="{{ $currentSearch }}"
        data-bc-page="{{ method_exists($broadcasts, 'currentPage') ? $broadcasts->currentPage() : 1 }}">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            <aside class="lg:sticky lg:top-6 self-start space-y-3">
                <div
                    class="hairline border border-paper-200 rounded-2xl bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">
                            <circle cx="8" cy="8" r="6" />
                        </svg>
                        Tip
                    </div>
                    {{ __('Use approved templates for outbound broadcasts, then watch delivery and read counts before scaling the next send.') }}
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-2">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Broadcast views') }}</div>
                    @php
                        $sideFilters = [
                            ['key' => 'all', 'label' => 'All broadcasts', 'dot' => 'bg-wa-green'],
                            ['key' => 'scheduled', 'label' => 'Scheduled', 'dot' => 'bg-accent-amber'],
                            ['key' => 'processing', 'label' => 'Processing', 'dot' => 'bg-[#13478A]'],
                            ['key' => 'completed', 'label' => 'Completed', 'dot' => 'bg-wa-deep'],
                            ['key' => 'failed', 'label' => 'Failed', 'dot' => 'bg-accent-coral'],
                        ];
                    @endphp
                    @foreach ($sideFilters as $f)
                        @php $active = $currentStatus === $f['key']; @endphp
                        <button data-bc-filter="status" data-bc-value="{{ $f['key'] }}" type="button"
                            class="side-tab w-full flex items-center justify-between px-3 py-2 rounded-xl {{ $active ? 'bg-wa-deep text-paper-0' : 'text-ink-700 hover:bg-paper-50' }} text-[13px] font-medium">
                            <span class="flex items-center gap-2"><span
                                    class="w-2 h-2 rounded-full {{ $f['dot'] }}"></span>{{ $f['label'] }}</span>
                            <span data-bc-status-count="{{ $f['key'] }}"
                                class="mono font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}">{{ number_format($statusCounts[$f['key']] ?? 0) }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-2">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Recent broadcasts') }}</div>
                    @forelse ($recentBroadcasts as $rb)
                        @php $rbCounts = $rb->status_counts; @endphp
                        <a href="#" data-recent-broadcast="{{ $rb->id }}"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="group-dot w-[7px] h-[7px] rounded-full shrink-0 bg-wa-deep"></span>
                                <span class="truncate">{{ $rb->name }}</span>
                            </span>
                            <span
                                class="mono font-mono text-[11px] text-ink-500">{{ number_format($rbCounts['delivered'] ?? 0) }}</span>
                        </a>
                    @empty
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No broadcasts found. Create a broadcast and recent items will appear here.',
                            'actionHref' => route('user.broadcasts.create'),
                            'actionLabel' => 'Create broadcast',
                        ])
                    @endforelse
                </div>

                <div class="card bg-wa-deep rounded-[14px] shadow-card p-4 text-paper-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70 mb-1">
                        {{ __('Get started') }}</div>
                    <div class="font-serif text-[22px] leading-tight">{{ __('Create broadcast') }}</div>
                    <p class="mt-2 text-[11.5px] text-paper-0/75 leading-relaxed">
                        {{ __('Pick a template, select contacts, then send now or schedule for later.') }}</p>
                    <a href="{{ route('user.broadcasts.create') }}"
                        class="mt-3 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-3.5 py-1.5 text-[12px] font-semibold">
                        New broadcast
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </a>
                </div>
            </aside>

            <section>
                <div class="flex items-end justify-between mb-5 gap-4 flex-wrap">
                    <div>
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }} · {{ auth()->user()?->currentWorkspace?->name ?: __('Workspace') }}</div>
                        <h1
                            class="serif font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[36px] lg:text-[44px] leading-[1.0] tracking-tight">
                            {{ __('Template') }} <span class="italic text-wa-deep">{{ __('broadcasts') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Send approved template messages to selected contacts, then track queued, sent, delivered, read, and failed status per recipient.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button id="bc-refresh" type="button"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                                <path d="M13 3v3h-3M3 13v-3h3" />
                            </svg>
                            Refresh
                        </button>
                        <a href="{{ route('user.broadcasts.create') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            New broadcast
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-3 lg:grid-cols-6 gap-3 mb-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent') }}
                        </div>
                        <div class="mt-2 font-serif text-[34px] leading-none" data-bc-totals="sent">
                            {{ number_format($stats['sent']) }}</div>
                        <div class="mt-2 text-[11px] text-wa-deep"><span
                                data-bc-totals="total">{{ number_format($stats['total']) }}</span> broadcasts</div>
                    </div>
                    <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Delivered') }}</div>
                        <div class="mt-2 font-serif text-[34px] leading-none" data-bc-totals="delivered">
                            {{ number_format($stats['delivered']) }}</div>
                        <div class="mt-2 text-[11px] text-wa-deep"><span
                                data-bc-totals="delivery_pct">{{ number_format($deliveryPct, 1) }}</span>% delivery
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Read') }}
                        </div>
                        <div class="mt-2 font-serif text-[34px] leading-none" data-bc-totals="read">
                            {{ number_format($stats['read']) }}</div>
                        <div class="mt-2 text-[11px] text-ink-500"><span
                                data-bc-totals="read_pct">{{ number_format($readPct, 1) }}</span>% read rate</div>
                    </div>
                    <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}
                        </div>
                        <div class="mt-2 font-serif text-[34px] leading-none" data-bc-totals="failed">
                            {{ number_format($stats['failed']) }}</div>
                        <div class="mt-2 text-[11px] text-accent-coral"><span
                                data-bc-totals="failed_pct">{{ number_format($failedPct, 1) }}</span>% failed</div>
                    </div>
                    <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Processing') }}</div>
                        <div class="mt-2 font-serif text-[34px] leading-none" data-bc-totals="processing">
                            {{ number_format($stats['processing']) }}</div>
                        <div class="mt-2 text-[11px] text-ink-500">{{ __('in flight') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Queued') }}
                        </div>
                        <div class="mt-2 font-serif text-[34px] leading-none" data-bc-totals="queued">
                            {{ number_format($stats['queued']) }}</div>
                        <div class="mt-2 text-[11px] text-ink-500">{{ __('awaiting send') }}</div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden" data-list-grid
                    data-list-grid-key="broadcasts">
                    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Broadcast list') }}</div>
                            <h2 class="font-serif text-[24px] leading-tight mt-0.5">{{ __('Recent broadcasts') }}</h2>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach ([['all', 'All time'], ['7d', '7d'], ['30d', '30d'], ['90d', '90d']] as [$rk, $rl])
                                @php $active = $currentRange === $rk; @endphp
                                <button data-bc-filter="range" data-bc-value="{{ $rk }}" type="button"
                                    class="px-3 py-1.5 rounded-full text-[12px] font-medium {{ $active ? 'bg-wa-deep text-paper-0' : 'border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700' }}">{{ $rl }}</button>
                            @endforeach
                            <div class="relative">
                                <svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                    fill="none" stroke="currentColor" stroke-width="1.6">
                                    <circle cx="7" cy="7" r="5" />
                                    <path d="m11 11 3 3" />
                                </svg>
                                <input id="bc-search" type="search" value="{{ $currentSearch }}"
                                    class="w-full sm:w-72 pl-9 pr-3 py-2 rounded-full border border-paper-200 bg-white text-[12px] focus:outline-none focus:border-wa-deep"
                                    placeholder="{{ __('Search broadcasts') }}">
                            </div>
                            <x-list-grid-toggle />
                        </div>
                    </div>
                    <div class="overflow-x-auto transition-opacity" id="bc-list" data-list-grid-list>
                        <table class="w-full text-[12.5px] table-fixed" data-list-grid-source>
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-3 w-[220px]">
                                        {{ __('Name') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[180px]">
                                        {{ __('Template') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[90px]">
                                        {{ __('Contacts') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[80px]">
                                        {{ __('Sent') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[95px]">
                                        {{ __('Delivered') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[80px]">
                                        {{ __('Read') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[80px]">
                                        {{ __('Failed') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[80px]">
                                        {{ __('Clicked') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[110px]">
                                        {{ __('Status') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-3 w-[130px]">
                                        {{ __('Schedule') }}</th>
                                    <th
                                        class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[60px]">
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200" id="bc-rows">
                                @include('user.broadcasts._rows', ['broadcasts' => $broadcasts])
                            </tbody>
                        </table>
                    </div>
                    <div class="hidden p-4 transition-opacity" data-list-grid-grid></div>
                    <div id="bc-results-footer"
                        class="px-5 py-3 border-t border-paper-200 text-[11px] text-ink-500 mono font-mono text-center {{ (method_exists($broadcasts, 'total') ? $broadcasts->total() : $statusCounts['all'] ?? 0) > 0 ? '' : 'hidden' }}">
                        Showing <span data-bc-shown>{{ $broadcasts->count() }}</span> of <span
                            data-bc-total>{{ method_exists($broadcasts, 'total') ? number_format($broadcasts->total()) : number_format($statusCounts['all']) }}</span>
                        filtered broadcasts
                    </div>
                </div>
                <div id="bc-pagination">
                    @include('user.partials.pagination', [
                        'paginator' => $broadcasts,
                        'dataAttr' => 'data-bc-page',
                        'label' => 'broadcasts',
                    ])
                </div>

                <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 01') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('What is a broadcast?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('A template-based send to selected contacts, with per-recipient queued, sent, delivered, read, and failed tracking.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 02') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('When should I schedule it?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Schedule larger audience sends for quiet support hours, and use smaller immediate sends for urgent updates.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 03') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('How do I improve results?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Keep the audience focused, choose the most relevant template, and review failed counts before sending again.') }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
