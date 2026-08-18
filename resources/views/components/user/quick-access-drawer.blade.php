{{--
 Global Quick-access edge drawer — a Samsung-edge-panel-style strip reachable
 from EVERY user page. A thin grip on the right edge slides out a vertical
 column of the user's pinned shortcuts (same set as the dashboard Quick access,
 editable there). Toggle JS lives in resources/js/app.js (initQuickAccessDrawer).
--}}
@php $qadTiles = \App\Support\QuickAccess::forUser(auth()->user()); @endphp
@if (!empty($qadTiles))
    {{-- Edge grip — always visible, vertically centred on the right edge. --}}
    {{-- Default position = near the TOP; draggable up/down (JS persists the
         position to localStorage 'qadHandleTop'). --}}
    <button id="qad-handle" type="button" aria-label="{{ __('Quick access') }}"
        class="fixed right-0 top-30 z-[115] flex items-center group/handle cursor-grab touch-none select-none">
        <span class="flex flex-col items-center justify-center gap-1 py-3 pl-1 pr-0.5 rounded-l-2xl bg-wa-deep/85 group-hover/handle:bg-wa-deep shadow-lg transition-colors">
            <span class="block w-1 h-1 rounded-full bg-paper-0/90"></span>
            <span class="block w-1 h-1 rounded-full bg-paper-0/90"></span>
            <span class="block w-1 h-1 rounded-full bg-paper-0/90"></span>
        </span>
    </button>

    {{-- Click-away backdrop. --}}
    <div id="qad-backdrop" class="fixed inset-0 z-[116] bg-ink-900/15 opacity-0 pointer-events-none transition-opacity duration-200"></div>

    {{-- The sliding panel. translate-x-full = off-screen; JS removes it to open. --}}
    <div id="qad-panel" class="fixed inset-y-0 right-0 z-[117] flex items-center pr-2 pointer-events-none translate-x-full transition-transform duration-300 ease-out">
        <div class="pointer-events-auto bg-paper-0/95 backdrop-blur border border-paper-200 rounded-[28px] shadow-2xl p-2.5 flex flex-col items-center gap-2 max-h-[82vh] overflow-y-auto">
            {{-- Hover cards: data-qtip/data-qdesc drive a styled popup card that
                 initQuickAccessDrawer() renders on <body> (NOT inside this
                 scroll container, whose overflow would clip it) and positions to
                 the left of the hovered icon. aria-label keeps it accessible. --}}
            @foreach ($qadTiles as $t)
                <a href="{{ $t['url'] }}" aria-label="{{ __($t['label']) }}"
                    data-qtip="{{ __($t['label']) }}" data-qdesc="{{ !empty($t['desc']) ? __($t['desc']) : '' }}"
                    class="group/icon w-11 h-11 rounded-2xl bg-wa-bubble hover:bg-wa-deep text-wa-deep hover:text-paper-0 flex items-center justify-center transition">
                    <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">{!! $t['icon'] !!}</svg>
                </a>
            @endforeach

            <div class="w-7 h-px bg-paper-200 my-0.5"></div>

            <a href="{{ url('/dashboard') }}" aria-label="{{ __('Dashboard') }}"
                data-qtip="{{ __('Dashboard') }}" data-qdesc="{{ __('Your home overview') }}"
                class="w-11 h-11 rounded-2xl border border-paper-200 text-ink-600 hover:border-wa-deep hover:text-wa-deep flex items-center justify-center transition">
                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 7.5L8 3l5.5 4.5M4 7v6h8V7" /></svg>
            </a>
            <a href="{{ url('/more') }}" aria-label="{{ __('All apps') }}"
                data-qtip="{{ __('All apps') }}" data-qdesc="{{ __('Browse every app and feature') }}"
                class="w-11 h-11 rounded-2xl border border-paper-200 text-ink-600 hover:border-wa-deep hover:text-wa-deep flex items-center justify-center transition">
                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="4" cy="4" r="1.2" /><circle cx="8" cy="4" r="1.2" /><circle cx="12" cy="4" r="1.2" /><circle cx="4" cy="8" r="1.2" /><circle cx="8" cy="8" r="1.2" /><circle cx="12" cy="8" r="1.2" /><circle cx="4" cy="12" r="1.2" /><circle cx="8" cy="12" r="1.2" /><circle cx="12" cy="12" r="1.2" /></svg>
            </a>
            <button type="button" data-qa-open aria-label="{{ __('Edit shortcuts') }}"
                data-qtip="{{ __('Edit shortcuts') }}" data-qdesc="{{ __('Pin or reorder these shortcuts') }}"
                class="w-11 h-11 rounded-2xl border border-paper-200 text-ink-600 hover:border-wa-deep hover:text-wa-deep flex items-center justify-center transition">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 2.5l2.5 2.5L6 12.5 3 13l.5-3z" /></svg>
            </button>
            <button id="qad-close" type="button" aria-label="{{ __('Close') }}"
                data-qtip="{{ __('Close') }}"
                class="w-11 h-9 rounded-2xl text-ink-400 hover:text-ink-900 flex items-center justify-center transition">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8" /></svg>
            </button>
        </div>
    </div>
@endif
