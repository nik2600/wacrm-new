{{--
 Global Quick-access editor modal. Opened by any [data-qa-open] trigger (the
 dashboard pencil + the edge-drawer pencil). Wired by initQuickAccessModal()
 in resources/js/app.js. Self-contained — computes its own data.
--}}
@php
    $qmTiles = \App\Support\QuickAccess::forUser(auth()->user());
    $quickCatalog = \App\Support\QuickAccess::catalog();
    $quickSelectedKeys = collect($qmTiles)->pluck('key')->filter()->values()->all();
    $quickCustom = collect($qmTiles)->where('custom', true)->values();
@endphp
<div id="qa-modal" class="fixed inset-0 z-[130] hidden items-center justify-center p-4" aria-modal="true" role="dialog">
    <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" data-qa-close></div>
    <div class="relative w-full max-w-2xl max-h-[88vh] overflow-y-auto bg-paper-0 rounded-2xl shadow-2xl border border-paper-200">
        <div class="sticky top-0 bg-paper-0 px-5 py-4 border-b border-paper-200 flex items-center justify-between">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick access') }}</div>
                <h3 class="font-serif text-[20px] leading-tight">{{ __('Customise your shortcuts') }}</h3>
            </div>
            <button type="button" data-qa-close class="text-ink-400 hover:text-ink-900">
                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4l8 8M12 4l-8 8" /></svg>
            </button>
        </div>
        <div class="p-5 space-y-5">
            <div>
                <div class="text-[11.5px] font-semibold text-ink-700 mb-2">{{ __('App pages') }} <span class="text-ink-400 font-normal">{{ __('· tap to pin (max 10)') }}</span></div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach ($quickCatalog as $key => $c)
                        <label class="qa-cat-row flex items-center gap-2 rounded-xl border border-paper-200 px-3 py-2 cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                            <input type="checkbox" class="qa-cat accent-wa-deep" value="{{ $key }}" @checked(in_array($key, $quickSelectedKeys, true))>
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.5">{!! $c['icon'] !!}</svg>
                            <span class="text-[12px] text-ink-800 truncate">{{ __($c['label']) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-paper-200 pt-4">
                <div class="text-[11.5px] font-semibold text-ink-700 mb-2">{{ __('Custom links') }}</div>
                <div id="qa-custom-list" class="space-y-2">
                    @foreach ($quickCustom as $cu)
                        <div class="qa-custom-row flex items-center gap-2">
                            <input type="text" class="qa-cu-label flex-1 rounded-xl border border-paper-200 px-3 py-2 text-[12.5px]" value="{{ $cu['label'] }}" placeholder="{{ __('Label') }}">
                            <input type="text" class="qa-cu-url flex-1 rounded-xl border border-paper-200 px-3 py-2 text-[12.5px] font-mono" value="{{ $cu['url'] }}" placeholder="https://… or /path">
                            <button type="button" class="qa-cu-del text-ink-400 hover:text-accent-coral"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4.5h10M6 4.5V3h4v1.5M5 4.5l.5 8h5l.5-8" /></svg></button>
                        </div>
                    @endforeach
                </div>
                <button type="button" id="qa-add-custom" class="mt-2 text-[12px] text-wa-deep font-semibold hover:underline">+ {{ __('Add a custom link') }}</button>
            </div>
        </div>
        <div class="sticky bottom-0 bg-paper-0 px-5 py-4 border-t border-paper-200 flex items-center justify-between">
            <span id="qa-count" class="text-[11.5px] text-ink-500"></span>
            <div class="flex items-center gap-2">
                <button type="button" data-qa-close class="px-4 py-2 rounded-full border border-paper-200 text-[12px] font-medium hover:bg-paper-50">{{ __('Cancel') }}</button>
                <button type="button" id="qa-save" data-url="{{ route('quick-access.update') }}" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save shortcuts') }}</button>
            </div>
        </div>
    </div>
</div>
