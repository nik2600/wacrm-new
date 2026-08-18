@php
    /** @var \Illuminate\Support\Collection $campaigns */
    $campaigns = $campaigns ?? collect();
    $statusCounts = $statusCounts ?? [
        'all' => 0,
        'ACTIVE' => 0,
        'PAUSED' => 0,
        'SCHEDULED' => 0,
        'DRAFT' => 0,
        'FAILED' => 0,
    ];
    $objectiveCounts = $objectiveCounts ?? [];
    $totals = $totals ?? ['total' => 0, 'active' => 0, 'spend' => 0, 'clicks' => 0];
    $currentStatus = $currentStatus ?? 'all';
    $currentObjective = $currentObjective ?? 'all';
    $currentRange = $currentRange ?? 'all';
    $currentSearch = $currentSearch ?? '';
    $currentSort = $currentSort ?? 'date-desc';

    $statuses = [
        ['key' => 'all', 'label' => 'All campaigns', 'dot' => null, 'icon' => true],
        ['key' => 'ACTIVE', 'label' => 'Active', 'dot' => 'bg-wa-green'],
        ['key' => 'PAUSED', 'label' => 'Paused', 'dot' => 'bg-accent-amber'],
        ['key' => 'SCHEDULED', 'label' => 'Scheduled', 'dot' => 'bg-[#13478A]'],
        ['key' => 'DRAFT', 'label' => 'Drafts', 'dot' => 'bg-paper-200'],
        ['key' => 'FAILED', 'label' => 'Failed', 'dot' => 'bg-accent-coral'],
    ];

    $objList = [
        [
            'key' => 'all',
            'label' => 'All objectives',
            'icon' => '<rect x="2" y="3" width="12" height="10" rx="2"/>
',
        ],
        ['key' => 'MESSAGES', 'label' => 'Messages', 'icon' => '<path d="M2 4l12-2v12L2 12V4Z"/>'],
        ['key' => 'LINK_CLICKS', 'label' => 'Link clicks', 'icon' => '<path d="M2 8l5-5v3h7v4H7v3z"/>'],
        [
            'key' => 'LEAD_GENERATION',
            'label' => 'Lead gen',
            'icon' => '<circle cx="8" cy="6" r="3"/><path d="M2 14c0-3 3-4 6-4s6 1 6 4"/>',
        ],
        [
            'key' => 'CONVERSIONS',
            'label' => 'Conversions',
            'icon' => '<path d="M3 10l4-4 3 3 3-3"/><path d="M2 13h12"/>',
        ],
        [
            'key' => 'BRAND_AWARENESS',
            'label' => 'Brand awareness',
            'icon' => '<circle cx="8" cy="8" r="6"/><circle cx="8" cy="8" r="2.5"/>',
        ],
    ];
@endphp

<x-layouts.user :title="__('Meta Ads')" nav-key="metaads" page="user-meta-ads-index">

    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7" data-meta-state data-meta-status="{{ $currentStatus }}"
        data-meta-objective="{{ $currentObjective }}" data-meta-range="{{ $currentRange }}"
        data-meta-search="{{ $currentSearch }}" data-meta-sort="{{ $currentSort }}"
        data-meta-page="{{ method_exists($campaigns, 'currentPage') ? $campaigns->currentPage() : 1 }}">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <aside class="lg:sticky lg:top-6 self-start space-y-3">

                <x-side-tip>
                    Use Click-to-WhatsApp objective to send Facebook traffic straight to your WABA inbox and run replies
                    through your active flows.
                </x-side-tip>

                {{-- Status filter — clicks fire AJAX via data-meta-filter --}}
                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Status') }}</div>
                    @foreach ($statuses as $s)
                        @php $active = $currentStatus === $s['key']; @endphp
                        <button data-meta-filter="status" data-meta-value="{{ $s['key'] }}" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span class="flex items-center gap-2">
                                @if (!empty($s['icon']))
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="2" />
                                    </svg>
                                @else
                                    <span class="w-2 h-2 rounded-full {{ $s['dot'] }}"></span>
                                @endif
                                {{ $s['label'] }}
                            </span>
                            <span data-status-count="{{ $s['key'] }}"
                                class="mono font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}">{{ $statusCounts[$s['key']] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Objective filter --}}
                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Objective') }}</div>
                    @foreach ($objList as $o)
                        @php
                            $active = $currentObjective === $o['key'];
                            $count = $o['key'] === 'all' ? $statusCounts['all'] ?? 0 : $objectiveCounts[$o['key']] ?? 0;
                        @endphp
                        <button data-meta-filter="objective" data-meta-value="{{ $o['key'] }}" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span class="flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 {{ $active ? '' : 'text-wa-deep' }}"
                                    fill="none" stroke="currentColor"
                                    stroke-width="1.6">{!! $o['icon'] !!}</svg>
                                {{ $o['label'] }}
                            </span>
                            <span data-obj-count="{{ $o['key'] }}"
                                class="mono font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>

            </aside>

            <div>

                <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
                    <div class="min-w-0">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }} · {{ auth()->user()?->currentWorkspace?->name ?: __('Workspace') }}</div>
                        <h1
                            class="serif font-serif font-normal tracking-[-0.01em] text-[36px] md:text-[44px] leading-[1.0] tracking-tight">
                            {{ __('Meta Ads') }} <span class="italic text-wa-deep">{{ __('campaigns') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">{{ __('Auto-refreshes every 5 minutes. Last sync') }}
                            <b id="meta-last-sync">just now</b>.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-2 md:mt-0">
                        <button id="meta-sync-btn" type="button"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                                <path d="M13 3v3h-3M3 13v-3h3" />
                            </svg>
                            Sync now
                        </button>
                        <form method="POST" action="{{ route('user.meta-ads.import') }}" class="inline">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M8 2v8M5 7l3 3 3-3M3 13h10" />
                                </svg>
                                {{ __('Fetch from Meta') }}
                            </button>
                        </form>
                        <a href="{{ route('user.meta-ads.analytics') }}"
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                            </svg>
                            Analytics
                        </a>
                        <button type="button" data-open-keys
                            class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M6 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-1.5L13 12M11 10l-1.5 1.5" />
                            </svg>
                            {{ __('Keys') }}
                        </button>
                        <a href="{{ route('user.meta-ads.create') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            Create Meta campaign
                        </a>
                        {{-- P7 — Instagram-linked (via Instaflow) workspaces get a
                             deep-link into Instaflow's own ad manager, which runs IG
                             ads under the correct IG identity. WaDesk's native Meta
                             Ads (Create Meta campaign) already build IG-placement
                             creatives for a workspace with its own Meta ad account. --}}
                        @if (!empty($instaflowAdsUrl))
                            <a href="{{ $instaflowAdsUrl }}" target="_blank" rel="noopener"
                                class="px-4 py-2 rounded-full border border-[#5B3D8A] text-[#5B3D8A] text-[12px] font-semibold hover:bg-[#5B3D8A]/10 flex items-center gap-2"
                                title="{{ __('Manage Instagram ads under your Instagram identity in :igbrand', ['igbrand' => ig_brand_name()]) }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <rect x="3" y="3" width="10" height="10" rx="3"/><circle cx="8" cy="8" r="2.2"/><circle cx="11" cy="5" r=".7" fill="currentColor" stroke="none"/>
                                </svg>
                                {{ __('Instagram ads') }}
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Stat row -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Total campaigns') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-totals="total">
                            {{ $totals['total'] }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ __('across all platforms') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-totals="active">
                            {{ $totals['active'] }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ __('running now') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Total spend') }}</div>
                        @php $adCur = $adAccount?->currency ?? \App\Models\SystemSetting::get('default_currency', 'USD'); @endphp
                        <div class="font-serif text-[34px] leading-none mt-1" data-totals="spend">
                            {!! \App\Support\FormatSettings::display($totals['spend'], $adCur) !!}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ __('selected range') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="text-[11px] text-ink-600 font-medium">{{ __('Total clicks') }}</div>
                        <div class="font-serif text-[34px] leading-none mt-1" data-totals="clicks">
                            {{ number_format($totals['clicks']) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ __('live insights') }}</div>
                    </div>
                </div>

                {{-- Top filter / sort / live search — every control fires AJAX --}}
                <div
                    class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-2 shadow-card mb-3">

                    <div class="flex items-center gap-1 overflow-x-auto scroll-x pb-1 md:pb-0 scrollbar-none min-w-0 w-full md:w-auto"
                        style="scrollbar-width: none;">
                        <button data-meta-pill="range" data-meta-value="all" type="button"
                            class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $currentRange === 'all' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ __('All time') }}</button>
                        <button data-meta-pill="range" data-meta-value="7d" type="button"
                            class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $currentRange === '7d' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ __('Last 7 days') }}</button>
                        <button data-meta-pill="range" data-meta-value="30d" type="button"
                            class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $currentRange === '30d' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ __('Last 30 days') }}</button>
                        <button data-meta-pill="sort" data-meta-value="spend-desc" type="button"
                            class="filter-pill inline-flex items-center shrink-0 gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition {{ $currentSort === 'spend-desc' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ __('Best performers') }}</button>
                    </div>

                    <div class="hidden md:block flex-1"></div>

                    <div
                        class="flex flex-wrap md:flex-nowrap items-center gap-2 md:gap-1.5 w-full md:w-auto mt-2 md:mt-0">
                        <select id="meta-sort"
                            class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                            <option value="date-desc" @selected($currentSort === 'date-desc')>{{ __('Newest') }}</option>
                            <option value="date-asc" @selected($currentSort === 'date-asc')>{{ __('Oldest') }}</option>
                            <option value="name-asc" @selected($currentSort === 'name-asc')>{{ __('Name A→Z') }}</option>
                            <option value="name-desc" @selected($currentSort === 'name-desc')>{{ __('Name Z→A') }}</option>
                            <option value="spend-desc" @selected($currentSort === 'spend-desc')>{{ __('Spend (high→low)') }}
                            </option>
                        </select>
                        <div class="relative w-full md:w-auto">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="meta-search" type="search" value="{{ $currentSearch }}"
                                placeholder="{{ __('Search campaigns…') }}"
                                class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full md:w-72 focus:outline-none focus:border-wa-deep">
                        </div>
                    </div>
                </div>

                <div id="meta-campaign-list" class="space-y-3 transition-opacity">
                    @include('user.campaigns._cards', compact('campaigns'))
                </div>
                <div id="meta-pagination">
                    @include('user.partials.pagination', [
                        'paginator' => $campaigns,
                        'dataAttr' => 'data-meta-page',
                        'label' => 'campaigns',
                    ])
                </div>

                <div class="mt-7 grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 01') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('What is a Meta campaign?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('A synced ad campaign from Meta Marketing API, including status, spend, clicks, objective, budget, and click-to-WhatsApp performance.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 02') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('How should I read the filters?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Use All time for the full workspace view, then narrow to 7, 30, or 90 days when you need recent spend and click trends.') }}
                        </p>
                    </div>
                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                        <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Help - 03') }}</div>
                        <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                            {{ __('Which ads should I scale first?') }}</div>
                        <p class="text-[12.5px] text-ink-600 leading-relaxed">
                            {{ __('Start with active message or lead campaigns that have steady clicks, controlled CPC, and replies coming into WhatsApp.') }}
                        </p>
                    </div>
                </div>

                <div id="meta-results-footer"
                    class="mt-6 text-[11px] text-ink-500 mono font-mono text-center {{ (method_exists($campaigns, 'total') ? $campaigns->total() : $statusCounts['all'] ?? 0) > 0 ? '' : 'hidden' }}">
                    Showing <span data-meta-shown>{{ $campaigns->count() }}</span> of <span
                        data-meta-total>{{ method_exists($campaigns, 'total') ? number_format($campaigns->total()) : number_format($statusCounts['all']) }}</span>
                    filtered campaigns · synced from Meta Marketing API
                </div>

            </div>
        </div>
    </div>

    {{-- ============================================================
 Meta Ads — keys / connection modal.
 A small form, so it opens inline rather than on its own page
 (the /meta-ads/connect page stays as a deep-link fallback).
 Opened by the "Keys" button [data-open-keys] in the header,
 and auto-opened when the Create-gate redirects here with
 ?connect=1 or when saveKeys() validation fails ($errors).
 Submits to the SAME route as the standalone page.
 ============================================================ --}}
    <div id="meta-keys-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center px-4"
        style="background-color:rgba(11,31,28,0.45);" @if (request()->boolean('connect') || $errors->any()) data-autoopen @endif>
        <div
            class="bg-paper-0 rounded-2xl shadow-soft border border-paper-200 w-full max-w-[640px] max-h-[92vh] overflow-hidden flex flex-col">
            <div class="px-5 py-4 hairline-b border-b border-paper-200 flex items-start gap-3">
                <span
                    class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep inline-flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-1.5L13 12M11 10l-1.5 1.5" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Meta Ads / Connection') }}</div>
                    <div class="serif font-serif text-[18px] leading-tight">{{ __('Connect your') }} <span
                            class="italic text-wa-deep">{{ __('Meta Ads account') }}</span></div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('Your own keys bill ads to your account. Stored encrypted.') }}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($connected)
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 mono font-mono"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Connected') }}</span>
                    @elseif ($adminFallback)
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('Using platform keys') }}</span>
                    @else
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent-amber/10 text-[#7B5A14] mono font-mono">{{ __('Not connected') }}</span>
                    @endif
                    <button type="button" data-close-keys
                        class="w-8 h-8 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                        title="{{ __('Close') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('user.meta-ads.keys.save') }}"
                class="flex flex-col min-h-0 flex-1">
                @csrf
                <div class="overflow-y-auto flex-1">
                    @if ($errors->any())
                        <div
                            class="mx-[18px] mt-4 rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12px] text-[#A1431F]">
                            <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach ($errors->all() as $msg)
                                    <li>{{ $msg }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($adminFallback && !$connected)
                        <div
                            class="mx-[18px] mt-4 rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-700 flex items-start gap-2.5">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 shrink-0 text-wa-deep" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 7.5v3M8 5h.01" />
                            </svg>
                            <span>{{ __('A platform Meta Ads account is available as a fallback, so you can run ads without connecting your own. Add your keys below to bill ads to your own account instead.') }}</span>
                        </div>
                    @endif

                    @include('user.meta-ads._keys-fields')
                </div>

                <div
                    class="px-5 py-3.5 hairline-t border-t border-paper-200 bg-paper-0 flex items-center justify-between gap-3">
                    <div>
                        @if ($connected)
                            <button type="submit" form="metaDisconnectForm"
                                class="text-[12px] font-medium text-accent-coral hover:underline">{{ __('Remove keys') }}</button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-close-keys
                            class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                        <button type="submit"
                            class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2 8l5 5 7-9" />
                            </svg>
                            {{ $connected ? __('Save') : __('Connect') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($connected)
        <form id="metaDisconnectForm" method="POST" action="{{ route('user.meta-ads.keys.destroy') }}"
            class="hidden">@csrf @method('DELETE')</form>
    @endif

</x-layouts.user>
