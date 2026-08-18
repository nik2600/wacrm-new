@php
    $stats = $stats ?? [
        'total' => 0,
        'queued' => 0,
        'running' => 0,
        'sent' => 0,
        'failed' => 0,
        'sent_total' => 0,
        'delivered_total' => 0,
        'read_total' => 0,
        'failed_total' => 0,
        'processing' => 0,
        'messageTypes' => [],
        'statusCounts' => [],
        'deliveryHealth' => ['avg_delivery_rate' => 0, 'failing_campaigns' => 0, 'status' => 'healthy'],
        'queueHealth' => ['template_approval_rate' => null, 'template_approved' => 0, 'template_total' => 0, 'devices_ready' => 'N/A', 'retry_backlog' => 0],
    ];
    $campaigns = $campaigns ?? collect();
    $currentStatus = $currentStatus ?? 'all';
    $currentType = $currentType ?? 'all';
    $currentRange = $currentRange ?? 'all';
    $currentSearch = $currentSearch ?? '';
    $statusCounts = $stats['statusCounts'] ?? [];
@endphp

<x-layouts.user :title="__('WhatsApp Campaigns')" nav-key="wa-campaigns" page="user-wa-campaigns-index">

    @if (session('status') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    @if (session('status'))
                        window.WaToaster?.success(@json(session('status')));
                    @endif
                    @foreach ($errors->all() as $err)
                        window.WaToaster?.error(@json($err));
                    @endforeach
                });
            </script>
        @endpush
    @endif

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7" data-wac-state data-wac-status="{{ $currentStatus }}"
        data-wac-type="{{ $currentType }}" data-wac-range="{{ $currentRange }}" data-wac-search="{{ $currentSearch }}"
        data-wac-page="{{ method_exists($campaigns, 'currentPage') ? $campaigns->currentPage() : 1 }}">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start">
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">
                <x-side-tip>
                    Schedule campaigns when your customers are active. Pair each broadcast with an approved template so
                    failed sends are flagged before they leave the queue.
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Campaign status') }}</div>
                    @php
                        $sidebarStatus = [
                            [
                                'key' => 'all',
                                'label' => 'All campaigns',
                                'dot' => null,
                                'count' => $statusCounts['all'] ?? $stats['total'],
                            ],
                            [
                                'key' => 'recently_created',
                                'label' => 'Recently created',
                                'dot' => 'bg-paper-200',
                                'count' => $statusCounts['recently_created'] ?? 0,
                            ],
                            [
                                'key' => 'recently_updated',
                                'label' => 'Recently updated',
                                'dot' => 'bg-wa-green',
                                'count' => $statusCounts['recently_updated'] ?? 0,
                            ],
                            [
                                'key' => 'scheduled',
                                'label' => 'Scheduled',
                                'dot' => 'bg-accent-amber',
                                'count' => $statusCounts['scheduled'] ?? $stats['queued'],
                            ],
                            [
                                'key' => 'running',
                                'label' => 'Processing',
                                'dot' => 'bg-wa-teal',
                                'count' => $statusCounts['running'] ?? $stats['running'],
                            ],
                            [
                                'key' => 'completed',
                                'label' => 'Completed',
                                'dot' => 'bg-ink-500',
                                'count' => $statusCounts['completed'] ?? $stats['sent'],
                            ],
                            [
                                'key' => 'failed',
                                'label' => 'Failed',
                                'dot' => 'bg-accent-coral',
                                'count' => $statusCounts['failed'] ?? $stats['failed'],
                            ],
                        ];
                    @endphp
                    @foreach ($sidebarStatus as $s)
                        @php $active = $currentStatus === $s['key']; @endphp
                        <button data-wac-filter="status" data-wac-value="{{ $s['key'] }}" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span class="flex items-center gap-2">
                                @if ($s['dot'])
                                    <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>
                                @endif
                                {{ $s['label'] }}
                            </span>
                            <span data-wac-status-count="{{ $s['key'] }}"
                                class="font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}">{{ number_format($s['count']) }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Message type') }}</div>
                    @php $allTypeActive = $currentType === 'all'; @endphp
                    <button data-wac-filter="type" data-wac-value="all" type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $allTypeActive ? 'bg-paper-50 text-ink-900 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                        <span>{{ __('All types') }}</span>
                        <span data-wac-type-count="all"
                            class="font-mono text-[11px] text-ink-500">{{ number_format($stats['total']) }}</span>
                    </button>
                    @foreach (['text' => 'Custom message', 'template' => 'Template', 'flow' => 'Flow builder'] as $key => $label)
                        @php $active = $currentType === $key; @endphp
                        <button data-wac-filter="type" data-wac-value="{{ $key }}" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-paper-50 text-ink-900 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span>{{ $label }}</span>
                            <span data-wac-type-count="{{ $key }}"
                                class="font-mono text-[11px] text-ink-500">{{ number_format($stats['messageTypes'][$key] ?? 0) }}</span>
                        </button>
                    @endforeach
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span
                            class="w-2 h-2 rounded-full {{ $stats['deliveryHealth']['status'] === 'healthy' ? 'bg-wa-green' : 'bg-accent-amber' }}"></span>
                        {{ $stats['deliveryHealth']['status'] === 'healthy' ? 'All systems healthy' : 'Delivery needs attention' }}
                    </div>
                    {{ number_format($stats['deliveryHealth']['avg_delivery_rate'], 1) }}% avg delivery &middot;
                    {{ number_format($stats['deliveryHealth']['failing_campaigns']) }}
                    {{ $stats['deliveryHealth']['failing_campaigns'] === 1 ? 'campaign' : 'campaigns' }} need review.
                </div>
            </aside>

            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }} · {{ auth()->user()?->currentWorkspace?->name ?: __('Workspace') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('WhatsApp') }}
                            <span class="italic text-wa-deep">{{ __('campaigns') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Broadcast queues, templates, flows, schedule status, and delivery outcomes in one place.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="wac-refresh" type="button"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                                <path d="M13 3v3h-3M3 13v-3h3" />
                            </svg>
                            Refresh
                        </button>
                        <a href="{{ route('user.wa-campaigns.create') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            New WA campaign
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Sent') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-wac-totals="sent_total">
                            {{ number_format($stats['sent_total']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2"><span
                                data-wac-totals="total">{{ number_format($stats['total']) }}</span> campaigns</div>
                    </div>
                    <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Delivered') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-wac-totals="delivered_total">
                            {{ number_format($stats['delivered_total']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2"><span data-wac-totals="delivery_pct">
                                @if ($stats['sent_total'] > 0)
                                    {{ number_format(($stats['delivered_total'] / max($stats['sent_total'], 1)) * 100, 1) }}
                                @else
                                    0
                                @endif
                            </span>% delivery</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Read') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-wac-totals="read_total">
                            {{ number_format($stats['read_total']) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2"><span data-wac-totals="read_pct">
                                @if ($stats['delivered_total'] > 0)
                                    {{ number_format(($stats['read_total'] / max($stats['delivered_total'], 1)) * 100, 1) }}
                                @else
                                    0
                                @endif
                            </span>% read rate</div>
                    </div>
                    <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Failed') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-wac-totals="failed_total">
                            {{ number_format($stats['failed_total']) }}</div>
                        <div class="text-[11px] text-accent-coral mt-2"><span
                                data-wac-totals="failed">{{ number_format($stats['failed']) }}</span> failed campaigns
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Processing') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-wac-totals="processing">
                            {{ number_format($stats['processing']) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2"><span
                                data-wac-totals="queued">{{ number_format($stats['queued']) }}</span> queued</div>
                    </div>
                </div>

                {{-- Top filter strip — date range pills + live search --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card">
                    @foreach ([['all', 'All time'], ['7d', 'Last 7 days'], ['30d', 'Last 30 days'], ['90d', 'Last 90 days']] as [$rk, $rl])
                        @php $active = $currentRange === $rk; @endphp
                        <button data-wac-filter="range" data-wac-value="{{ $rk }}" type="button"
                            class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $active ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ $rl }}</button>
                    @endforeach
                    <div class="flex-1"></div>
                    <div class="relative w-full lg:w-auto">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.5">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="wac-search" type="search" value="{{ $currentSearch }}"
                            placeholder="{{ __('Search campaigns…') }}"
                            class="border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full lg:w-72 focus:outline-none focus:border-wa-deep">
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="space-y-3 transition-opacity" id="campaignsList">
                        @include('user.wa-campaigns._cards', ['campaigns' => $campaigns])
                    </div>
                    <div id="wac-pagination">
                        @include('user.partials.pagination', [
                            'paginator' => $campaigns,
                            'dataAttr' => 'data-wac-page',
                            'label' => 'campaigns',
                        ])
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help - 01') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('What is a WhatsApp campaign?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('A broadcast queue that sends a custom message, approved template, or flow to selected contacts through your connected device.') }}
                            </p>
                        </div>
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help - 02') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('How do I improve delivery?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('Keep lists clean, use approved templates for outbound sends, and schedule larger broadcasts instead of pushing every contact at once.') }}
                            </p>
                        </div>
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help - 03') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('Which campaign should I launch first?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('Begin with a template update or re-engagement broadcast, then compare delivered, read, and failed counts before scaling.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div id="wac-results-footer"
                    class="text-[11px] text-ink-500 mono font-mono text-center {{ (method_exists($campaigns, 'total') ? $campaigns->total() : $stats['total'] ?? 0) > 0 ? '' : 'hidden' }}">
                    Showing <span data-wac-shown>{{ $campaigns->count() }}</span> of <span
                        data-wac-total>{{ method_exists($campaigns, 'total') ? number_format($campaigns->total()) : number_format($stats['total']) }}</span>
                    filtered campaigns
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
