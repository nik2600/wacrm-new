{{-- Language switcher dropdown — used in both user + admin headers.
 POSTs to /locale and reloads the page so the new locale takes
 effect everywhere. Closes on outside click. No external JS dep. --}}
@php
    $active = \App\Support\LocaleSettings::active();
    $current = app()->getLocale();
    $curRow = collect($active)->firstWhere('code', $current) ?? collect($active)->first();
    $curLabel = $curRow['code'] ?? strtoupper($current);
@endphp

@if (count($active) > 1)
    <div class="relative inline-block" data-locale-switcher>
        <button type="button" data-locale-toggle title="{{ __('Change language') }}"
            class="px-2 py-1.5 rounded-full hover:bg-paper-100 text-ink-700 hover:text-ink-900 text-[11.5px] font-medium inline-flex items-center gap-1.5">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="8" cy="8" r="6" />
                <path d="M2 8h12M8 2c2 2 2.5 4 2.5 6S10 12 8 14M8 2c-2 2-2.5 4-2.5 6S6 12 8 14" />
            </svg>
            <span class="uppercase tracking-wider">{{ $curLabel }}</span>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M3 5l3 3 3-3" />
            </svg>
        </button>
        <div data-locale-menu
            class="hidden absolute right-0 mt-1 w-56 max-h-[400px] overflow-y-auto bg-paper-0 border border-paper-200 rounded-xl shadow-card z-40 py-1">
            @foreach ($active as $lang)
                <button type="button" data-locale-pick="{{ $lang['code'] }}"
                    data-locale-dir="{{ $lang['direction'] ?? 'ltr' }}"
                    class="w-full text-left px-3 py-2 text-[12px] hover:bg-paper-50 flex items-center justify-between gap-2 {{ $current === $lang['code'] ? 'bg-wa-mint/40 text-wa-deep font-semibold' : 'text-ink-700' }}">
                    <span>
                        <span class="font-medium">{{ $lang['native_name'] ?: $lang['name'] }}</span>
                        <span class="text-ink-500 text-[10.5px] ml-1">{{ $lang['name'] }}</span>
                    </span>
                    <span class="font-mono text-[10px] uppercase text-ink-500">{{ $lang['code'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Behavior lives in resources/js/locale-switcher.js (bundled by
 app.js). The earlier inline @push('scripts') was kept being
 captured into the View::pushes stack on every component render
 and held in memory after @stack('scripts') had already flushed
 in the admin layout — moving it out of the blade eliminates
 that path entirely. --}}
@endif
