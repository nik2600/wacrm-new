<x-layouts.admin :title="__('Appearance')" admin-key="settings" page="admin-settings-appearance">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.settings.index') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Appearance') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    {{-- Reset form lives OUTSIDE the colours form (forms can't nest). The reset
         button in the aside submits it via its form="appearance-reset" attribute. --}}
    <form id="appearance-reset" method="POST" action="{{ route('admin.settings.appearance.reset') }}"
        onsubmit="return confirm('{{ __('Reset every colour back to the shipped defaults?') }}')" class="hidden">
        @csrf
    </form>

    @php $groups = collect($palette)->groupBy(fn ($m) => $m[2], true); @endphp

    <form method="POST" action="{{ route('admin.settings.appearance.update') }}">
        @csrf

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Appearance') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Dashboard') }}
                        <span class="italic text-wa-deep">{{ __('colours') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Recolour the entire app — every page of the user dashboard AND the admin panel — by changing these theme colours. Saving applies instantly to everyone; no rebuild needed. Leave a colour at its default to keep the shipped look.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    @if (session('status'))
                        <span class="px-3 py-1.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 text-[11.5px] font-mono">{{ session('status') }}</span>
                    @endif
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save colours') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">

                {{-- LEFT — sliders, then colour groups --}}
                <div class="space-y-5 min-w-0">

                    {{-- Size + opacity sliders. Both write a plain integer percent;
                         theme_css() turns them into html{zoom} and body{opacity}. --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Size & opacity') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Interface') }}</h2>
                            </div>
                            <span class="rounded-full bg-paper-50 text-ink-500 border border-paper-200 px-2.5 py-1 text-[11px] font-mono">{{ count($metrics) }} {{ __('controls') }}</span>
                        </div>
                        <div class="p-5 space-y-5">
                            @foreach ($metrics as $key => $m)
                                @php $val = $metricValues[$key] ?? $m[1]; @endphp
                                <div>
                                    <div class="flex items-baseline justify-between gap-3 mb-2">
                                        <label for="metric-{{ $key }}" class="text-[12.5px] font-semibold text-ink-900">{{ __($m[0]) }}</label>
                                        <span class="font-mono text-[11.5px] text-ink-500">
                                            <span data-metric-out="{{ $key }}">{{ $val }}</span>{{ $m[4] }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button type="button" data-metric-step="{{ $key }}" data-step="-5"
                                            class="w-8 h-8 shrink-0 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center"
                                            aria-label="{{ __('Decrease') }} {{ __($m[0]) }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3.5 8h9" /></svg>
                                        </button>
                                        <input type="range" id="metric-{{ $key }}" name="metrics[{{ $key }}]"
                                            min="{{ $m[2] }}" max="{{ $m[3] }}" step="1" value="{{ $val }}"
                                            data-metric="{{ $key }}"
                                            class="flex-1 min-w-0 h-1.5 appearance-none rounded-full bg-paper-100 accent-wa-deep cursor-pointer">
                                        <button type="button" data-metric-step="{{ $key }}" data-step="5"
                                            class="w-8 h-8 shrink-0 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center"
                                            aria-label="{{ __('Increase') }} {{ __($m[0]) }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3.5v9M3.5 8h9" /></svg>
                                        </button>
                                    </div>
                                    <p class="text-[11.5px] text-ink-600 mt-2">{{ __($m[5]) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @foreach ($groups as $groupName => $tokens)
                        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ \Illuminate\Support\Str::slug($groupName) }}</div>
                                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __($groupName) }}</h2>
                                </div>
                                <span class="rounded-full bg-paper-50 text-ink-500 border border-paper-200 px-2.5 py-1 text-[11px] font-mono">{{ count($tokens) }} {{ __('tokens') }}</span>
                            </div>
                            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($tokens as $key => $m)
                                    @php $hex = $values[$key] ?? ($m[1] ?? '#000000'); @endphp
                                    <label class="flex items-center gap-3 border border-paper-200 rounded-xl p-3 hover:bg-paper-50 cursor-pointer">
                                        <input type="color" name="colors[{{ $key }}]" value="{{ $hex }}"
                                            class="w-10 h-10 rounded-lg cursor-pointer border border-paper-200 bg-transparent shrink-0 p-0">
                                        <span class="min-w-0">
                                            <span class="block text-[12.5px] font-semibold text-ink-900 truncate">{{ __($m[0]) }}</span>
                                            <span class="block text-[10.5px] font-mono text-ink-500">{{ $key }} · {{ $hex }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                {{-- RIGHT — live preview, then guide + reset --}}
                <aside class="space-y-4 lg:sticky lg:top-[88px]">

                    {{-- LIVE PREVIEW.
                         The colour tokens are re-declared as inline custom properties on
                         #appearance-preview, so every Tailwind token class inside this box
                         (bg-wa-deep, text-ink-500, …) resolves against the PREVIEW value
                         instead of :root. That means the mock below reacts to the pickers
                         without touching the surrounding admin page. --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-2">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</div>
                                <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Before you save') }}</h3>
                            </div>
                            <span class="w-2 h-2 rounded-full bg-wa-green shrink-0" title="{{ __('Updates as you drag') }}"></span>
                        </div>

                        <div class="p-3 bg-paper-50">
                            <div id="appearance-preview" class="rounded-xl overflow-hidden border border-paper-200 origin-top transition-[opacity] duration-150">
                                <div class="bg-paper-0">

                                    {{-- mock top bar --}}
                                    <div class="flex items-center gap-2 px-3 py-2 border-b border-paper-200">
                                        <span class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[8px] shrink-0">WD</span>
                                        <span class="px-2 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[9px] font-semibold">{{ __('Dashboard') }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-ink-600 text-[9px]">{{ __('Campaigns') }}</span>
                                        <span class="ml-auto w-4 h-4 rounded-full bg-wa-green shrink-0"></span>
                                    </div>

                                    <div class="flex">
                                        {{-- mock sidebar --}}
                                        <div class="w-[74px] shrink-0 border-r border-paper-200 p-2 space-y-1">
                                            <div class="px-1.5 py-1 rounded-md bg-wa-deep text-paper-0 text-[8.5px] font-semibold">{{ __('Active') }}</div>
                                            <div class="px-1.5 py-1 rounded-md text-ink-600 text-[8.5px]">{{ __('Inbox') }}</div>
                                            <div class="px-1.5 py-1 rounded-md text-ink-600 text-[8.5px]">{{ __('Flows') }}</div>
                                        </div>

                                        {{-- mock content --}}
                                        <div class="flex-1 min-w-0 p-2.5 space-y-2">
                                            <div class="font-serif text-[15px] leading-none text-ink-900">{{ __('Overview') }}
                                                <span class="italic text-wa-deep">{{ __('today') }}</span>
                                            </div>

                                            <div class="grid grid-cols-2 gap-1.5">
                                                <div class="border border-paper-200 rounded-lg p-1.5">
                                                    <div class="text-[8px] text-ink-500">{{ __('Sent') }}</div>
                                                    <div class="font-serif text-[15px] leading-none text-ink-900">1,284</div>
                                                </div>
                                                <div class="border border-wa-green/40 bg-wa-bubble rounded-lg p-1.5">
                                                    <div class="text-[8px] text-ink-500">{{ __('Replies') }}</div>
                                                    <div class="font-serif text-[15px] leading-none text-wa-deep">312</div>
                                                </div>
                                            </div>

                                            {{-- mock chart --}}
                                            <div class="flex items-end gap-1 h-9">
                                                <span class="flex-1 rounded-sm bg-wa-deep" style="height:40%"></span>
                                                <span class="flex-1 rounded-sm bg-wa-deep" style="height:65%"></span>
                                                <span class="flex-1 rounded-sm bg-wa-green" style="height:100%"></span>
                                                <span class="flex-1 rounded-sm bg-wa-deep" style="height:55%"></span>
                                                <span class="flex-1 rounded-sm bg-accent-amber" style="height:75%"></span>
                                                <span class="flex-1 rounded-sm bg-accent-sky" style="height:35%"></span>
                                            </div>

                                            <div class="flex items-center gap-1.5">
                                                <span class="px-2 py-1 rounded-full bg-wa-deep text-paper-0 text-[8.5px] font-semibold">{{ __('Primary') }}</span>
                                                <span class="px-2 py-1 rounded-full border border-paper-200 text-ink-700 text-[8.5px]">{{ __('Cancel') }}</span>
                                                <span class="px-1.5 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[8px] font-mono">{{ __('active') }}</span>
                                            </div>

                                            <p class="text-[8.5px] text-ink-500 leading-relaxed">
                                                {{ __('Muted body copy sits at this weight against the page surface.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-[10.5px] text-ink-500 mt-2 px-0.5">
                                {{ __('Colours, size and opacity update as you drag. Nothing is applied to anyone until you save.') }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('How colours work') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Applies instantly') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Saving rewrites the live theme tokens for everyone — no rebuild, no deploy.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('One token, one job') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Each swatch maps to a single CSS variable used across both the user dashboard and the admin panel.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Change Primary, the rest follows') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Primary (hover), Accent, Soft fill and Chat bubble are derived from Primary automatically, so one colour recolours hovers, badges and selected rows too. Set any of them yourself and your value wins.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Leave defaults alone') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Any colour left at its shipped value keeps the original WaDesk look — only changed tokens are overridden.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-accent-coral/30 rounded-2xl shadow-card p-4">
                        <div class="text-[12.5px] font-semibold text-ink-900">{{ __('Reset to defaults') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Clears all overrides and restores the original WaDesk palette.') }}</p>
                        <button type="submit" form="appearance-reset"
                            class="mt-3 w-full px-4 py-2 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[12.5px] font-semibold">{{ __('Reset all colours') }}</button>
                    </div>

                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Tip') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Check contrast after big changes — text colours and background colours both come from these tokens.') }}</p>
                    </div>
                </aside>

            </section>

            {{-- Sticky save bar --}}
            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Applies to the whole dashboard the moment you save.') }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit"
                        class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save colours') }}</button>
                </div>
            </div>

        </main>
    </form>

</x-layouts.admin>
