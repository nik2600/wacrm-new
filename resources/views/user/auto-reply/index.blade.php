@php
    $rows = $rows ?? collect();
    $totals = $totals ?? [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'total_triggers' => 0,
        'rules_fired_24h' => 0,
        'top_performers' => collect(),
    ];
    $devices = $devices ?? collect();
    $currentSearch = $currentSearch ?? '';
    $currentDevice = $currentDevice ?? 'all';
    $currentStatus = $currentStatus ?? 'all';
    $currentType = $currentType ?? 'all';
    $currentView = in_array($currentView ?? 'list', ['list', 'grid'], true) ? $currentView : 'list';
    $awayMode = $awayMode ?? false;
    $currentPage = method_exists($rows, 'currentPage') ? $rows->currentPage() : 1;
    $shownCount = method_exists($rows, 'count') ? $rows->count() : 0;
    $filteredTotal = method_exists($rows, 'total') ? $rows->total() : $shownCount;

    $tilePalette = [
        ['cls' => 'bg-wa-mint text-wa-deep'],
        ['cls' => 'bg-[#D9E5F2] text-[#13478A]'],
        ['cls' => 'bg-accent-amber/20 text-[#7B5A14]'],
        ['cls' => 'bg-[#F3E9FF] text-[#5B3D8A]'],
        ['cls' => 'bg-accent-coral/15 text-[#A1431F]'],
        ['cls' => 'bg-[#E8F5E9] text-wa-deep'],
    ];
@endphp

<x-layouts.user :title="__('Auto Reply')" nav-key="more" page="user-auto-reply-index">

    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Auto Reply') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Auto') }} <span
                            class="italic text-wa-deep">{{ __('Reply') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                        data-ar-stat="active">{{ $totals['active'] }}</span> active rules</span>
                {{-- Manual "Away mode" switch. Clicking the pill POSTs the flip;
                     while ON, `away` trigger-type auto-replies fire on every inbound. --}}
                <form method="POST" action="{{ route('user.auto-reply.away-mode') }}" class="inline-flex">
                    @csrf
                    <input type="hidden" name="away" value="{{ $awayMode ? 0 : 1 }}">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-[11px] font-medium font-mono border transition {{ $awayMode ? 'bg-accent-amber/20 text-[#7B5A14] border-accent-amber/50' : 'bg-paper-0 text-ink-500 border-paper-200 hover:bg-paper-50' }}"
                        title="{{ __('Away mode: when ON, your Away auto-replies fire on every incoming message until you switch it off.') }}">
                        <span class="relative inline-flex items-center">
                            <span class="w-7 h-4 rounded-full {{ $awayMode ? 'bg-accent-amber' : 'bg-paper-200' }} transition-colors"></span>
                            <span class="absolute top-0.5 left-0.5 w-3 h-3 rounded-full bg-paper-0 shadow transition-transform {{ $awayMode ? 'translate-x-[12px]' : '' }}"></span>
                        </span>
                        {{ __('Away') }} {{ $awayMode ? __('on') : __('off') }}
                    </button>
                </form>
                <button type="button" id="ar-import-open"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M8 3v8M5 6l3-3 3 3M3 13h10" />
                    </svg>
                    Import
                </button>
                <a href="{{ url('/auto-reply/create') }}"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    New auto reply
                </a>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6" data-ar-state data-ar-search="{{ $currentSearch }}"
        data-ar-device="{{ $currentDevice }}" data-ar-status="{{ $currentStatus }}" data-ar-type="{{ $currentType }}"
        data-ar-view="{{ $currentView }}" data-ar-page="{{ $currentPage }}">
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Active') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono"><span
                            data-ar-stat="inactive">{{ $totals['inactive'] }}</span> paused</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-ar-stat="active">{{ $totals['active'] }}</span>
                    <span class="text-[11px] text-ink-500">/ <span data-ar-stat="total">{{ $totals['total'] }}</span>
                        total</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total triggered') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('all-time') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-ar-stat="total_triggers">{{ number_format($totals['total_triggers']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('replies sent') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Fired (24h)') }}</span>
                    <span
                        class="text-[10px] text-wa-deep font-mono">{{ $totals['active'] > 0 ? round(($totals['rules_fired_24h'] / max($totals['active'], 1)) * 100) : 0 }}%
                        of active</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none"
                        data-ar-stat="rules_fired_24h">{{ number_format($totals['rules_fired_24h']) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('rules touched') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Devices') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono">{{ __('connected') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[30px] leading-none">{{ $devices->count() }}</span>
                    <span class="text-[11px] text-ink-500">{{ $devices->count() === 1 ? 'sender' : 'senders' }}</span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top performers') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">
                            {{ __('Most-triggered rules this week') }}</h3>
                    </div>
                    <a href="{{ url('/analytics') }}"
                        class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Open analytics') }}</a>
                </div>
                @if ($totals['top_performers']->isEmpty())
                    <div class="text-[12.5px] text-ink-500 py-6 text-center">
                        {{ __('Nothing fired yet. Once your rules start matching incoming messages, the top performers will appear here.') }}
                    </div>
                @else
                    @php $maxTrig = max(1, (int) $totals['top_performers']->max('trigger_count')); @endphp
                    <div class="space-y-3">
                        @foreach ($totals['top_performers'] as $i => $p)
                            @php $pct = max(2, round(($p->trigger_count / $maxTrig) * 100)); @endphp
                            <a href="{{ url('/auto-reply/create') }}?id={{ $p->id }}"
                                class="flex items-center gap-3 hover:bg-paper-50 rounded-lg p-1 -m-1 transition">
                                <span
                                    class="w-7 h-7 rounded-lg {{ $tilePalette[$i % count($tilePalette)]['cls'] }} grid place-items-center text-[10px] font-mono">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12.5px] font-semibold truncate">{{ $p->keyword }}</div>
                                    <div class="h-1.5 bg-paper-100 rounded-full overflow-hidden mt-1">
                                        <div class="h-full bg-wa-deep" style="width:{{ $pct }}%"></div>
                                    </div>
                                </div>
                                <div class="text-[12px] font-mono text-ink-700 w-16 text-right">
                                    {{ number_format($p->trigger_count) }}</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">{{ __('Tip') }}
                </div>
                <div class="font-serif text-[22px] leading-tight mt-1">{{ __('Stack 3 keywords per rule') }}</div>
                <p class="mt-2 text-[12px] text-paper-0/80 leading-relaxed">
                    {{ __('Customers spell things differently. Group variants in one rule with fuzzy match at 75-80% to catch typos without false positives.') }}
                </p>
                <a href="{{ url('/auto-reply/create') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">
                    Build a rule
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg>
                </a>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
            <div class="px-4 py-3 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-2 flex-1 min-w-[260px] flex-wrap">
                    <div class="relative flex-1 min-w-[240px] max-w-[420px]">
                        <svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input id="ar-search" type="search" value="{{ $currentSearch }}"
                            placeholder="{{ __('Search by keyword, device, or reply text...') }}"
                            class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <select id="ar-device-filter"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <option value="all" @selected($currentDevice === 'all')>{{ __('All devices') }}</option>
                        @foreach ($devices as $d)
                            <option value="{{ $d->id }}" @selected((string) $currentDevice === (string) $d->id)>
                                {{ $d->phone_number }}{{ $d->device_name ? ' / ' . $d->device_name : '' }}</option>
                        @endforeach
                    </select>
                    <select id="ar-status-filter"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <option value="all" @selected($currentStatus === 'all')>{{ __('All status') }}</option>
                        <option value="active" @selected($currentStatus === 'active')>{{ __('Active') }}</option>
                        <option value="paused" @selected($currentStatus === 'paused')>{{ __('Paused') }}</option>
                    </select>
                    <select id="ar-type-filter"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <option value="all" @selected($currentType === 'all')>{{ __('All reply types') }}</option>
                        <option value="custom" @selected($currentType === 'custom')>{{ __('Custom message') }}</option>
                        <option value="flow" @selected($currentType === 'flow')>{{ __('Flow') }}</option>
                        <option value="text" @selected($currentType === 'text')>{{ __('Text') }}</option>
                        <option value="template" @selected($currentType === 'template')>{{ __('Template') }}</option>
                        <option value="image" @selected($currentType === 'image')>{{ __('Image') }}</option>
                        <option value="video" @selected($currentType === 'video')>{{ __('Video') }}</option>
                        <option value="document" @selected($currentType === 'document')>{{ __('Document') }}</option>
                    </select>
                </div>
                <div class="inline-flex items-center gap-1 p-1 rounded-full border border-paper-200 bg-paper-0">
                    <button type="button" data-ar-view-button="list" title="{{ __('List view') }}"
                        class="w-8 h-8 rounded-full inline-flex items-center justify-center transition {{ $currentView === 'list' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M5 4h9M5 8h9M5 12h9" />
                            <path d="M2 4h.01M2 8h.01M2 12h.01" stroke-linecap="round" />
                        </svg>
                    </button>
                    <button type="button" data-ar-view-button="grid" title="{{ __('Grid view') }}"
                        class="w-8 h-8 rounded-full inline-flex items-center justify-center transition {{ $currentView === 'grid' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="2.5" y="2.5" width="4" height="4" rx="1" />
                            <rect x="9.5" y="2.5" width="4" height="4" rx="1" />
                            <rect x="2.5" y="9.5" width="4" height="4" rx="1" />
                            <rect x="9.5" y="9.5" width="4" height="4" rx="1" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Bulk-action bar — appears when at least one [data-bulk-row]
 checkbox is ticked. Wires to /auto-reply/bulk (activate / pause / delete).
 The endpoint already existed but had no UI before this. --}}
            <div id="ar-bulk-bar" data-bulk-bar data-bulk-url="{{ route('user.auto-reply.bulk') }}"
                class="hidden items-center justify-between gap-3 px-4 py-2.5 bg-wa-mint/40 border border-wa-deep/20 rounded-xl mb-3">
                <div class="text-[12px] text-wa-deep font-semibold">
                    <span data-bulk-count>0</span> {{ __('selected') }}
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-bulk-action="activate"
                        class="px-3 py-1.5 rounded-full bg-paper-0 border border-paper-200 hover:border-wa-deep text-[11.5px] font-semibold text-ink-700 hover:text-wa-deep">{{ __('Activate') }}</button>
                    <button type="button" data-bulk-action="deactivate"
                        class="px-3 py-1.5 rounded-full bg-paper-0 border border-paper-200 hover:border-wa-deep text-[11.5px] font-semibold text-ink-700 hover:text-wa-deep">{{ __('Pause') }}</button>
                    <button type="button" data-bulk-action="delete"
                        class="px-3 py-1.5 rounded-full bg-accent-coral/10 hover:bg-accent-coral/20 text-[11.5px] font-semibold text-accent-coral">{{ __('Delete') }}</button>
                    <button type="button" data-bulk-clear
                        class="px-2.5 py-1.5 rounded-full hover:bg-paper-50 text-[11px] text-ink-500"
                        title="{{ __('Clear selection') }}">×</button>
                </div>
            </div>

            <div id="ar-list-view"
                class="transition-opacity duration-150 {{ $currentView === 'grid' ? 'hidden opacity-0' : 'opacity-100' }}">
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-y border-paper-200 text-ink-500">
                            <tr>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-10">
                                    <input type="checkbox" data-bulk-all
                                        class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep"
                                        title="{{ __('Select all') }}" /></th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Keyword / match') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Device') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Reply type') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Triggered') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Status') }}</th>
                                <th
                                    class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[140px]">
                                    {{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="ar-tbody" class="divide-y divide-paper-200">
                            @include('user.auto-reply._rows', [
                                'rows' => $rows,
                                'tilePalette' => $tilePalette,
                            ])
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="ar-grid-view"
                class="px-4 pb-4 transition-opacity duration-150 {{ $currentView === 'grid' ? 'opacity-100' : 'hidden opacity-0' }}">
                @include('user.auto-reply._grid', ['rows' => $rows, 'tilePalette' => $tilePalette])
            </div>
        </section>

        <div id="ar-pagination">
            @include('user.partials.pagination', [
                'paginator' => $rows,
                'dataAttr' => 'data-ar-page',
                'label' => 'auto replies',
            ])
        </div>

        <div id="ar-results-footer"
            class="text-[11px] text-ink-500 mono font-mono text-center {{ $filteredTotal > 0 ? '' : 'hidden' }}">
            Showing <span data-ar-shown>{{ number_format($shownCount) }}</span> of <span
                data-ar-total>{{ number_format($filteredTotal) }}</span> filtered auto replies
        </div>
    </main>

    <div id="ar-import-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]">
        <form method="POST" action="{{ route('user.auto-reply.import') }}" enctype="multipart/form-data"
            id="ar-import-form"
            class="w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col bg-white border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)]">
            @csrf
            <div class="px-5 py-4 bg-paper-0 border-b border-paper-200 flex items-start justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Bulk import') }}</div>
                    <h2 class="font-serif text-[24px] leading-tight">{{ __('Upload auto replies from CSV') }}</h2>
                </div>
                <button type="button" data-ar-import-close
                    class="w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto px-5 py-4 space-y-4">
                <label class="block">
                    <span
                        class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('File') }}
                        <span class="text-accent-coral">*</span></span>
                    <span
                        class="flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid"
                        data-ar-file-tile>
                        <span
                            class="w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.5">
                                <path d="M8 3v8M5 6l3-3 3 3M3 13h10" />
                            </svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span
                                class="block text-[12px] font-semibold text-ink-900 whitespace-nowrap overflow-hidden text-ellipsis"
                                data-ar-file-label>{{ __('Choose CSV file') }}</span>
                            <span class="block text-[10.5px] text-ink-500 font-mono">{{ __('CSV / max 5 MB') }}</span>
                        </span>
                        <span
                            class="text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0">{{ __('Browse') }}</span>
                        <input id="ar-import-file" type="file" name="file" accept=".csv,.txt" required
                            class="hidden">
                    </span>
                </label>

                <div class="border border-paper-200 rounded-lg overflow-hidden">
                    <div
                        class="px-3 py-2 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 flex items-center justify-between gap-3">
                        <span>{{ __('Expected columns') }}</span>
                        <a href="{{ route('user.auto-reply.demo-csv') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Download demo CSV') }}</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 text-[12px] divide-x divide-paper-100">
                        <div class="p-3"><b>keyword</b><br><span class="text-ink-500">{{ __('Required') }}</span>
                        </div>
                        <div class="p-3"><b>reply_text</b><br><span
                                class="text-ink-500">{{ __('Required for custom') }}</span></div>
                        <div class="p-3"><b>device_id</b><br><span
                                class="text-ink-500">{{ __('Optional') }}</span></div>
                        <div class="p-3"><b>status</b><br><span
                                class="text-ink-500">{{ __('active / paused') }}</span></div>
                    </div>
                </div>
                <p class="text-[12px] text-ink-500 leading-relaxed">
                    {{ __('Leave device_id blank to use the first active device in this workspace. Supported optional columns include matching_method, fuzzy_similarity, reply_type, message_type, cooldown, timeout, flow_id, and template_id.') }}
                </p>
            </div>

            <div class="px-5 py-4 bg-paper-0 border-t border-paper-200 flex justify-end gap-2">
                <button type="button" data-ar-import-close
                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Upload') }}</button>
            </div>
        </form>
    </div>

</x-layouts.user>
