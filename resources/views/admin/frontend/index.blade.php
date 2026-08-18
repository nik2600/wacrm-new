<x-layouts.admin :title="__('Frontend editor')" admin-key="frontend" page="admin-frontend-index">

    @php
        // Per-page preview URLs (with the editor flag) for the JS tab switcher.
        $pageData = [];
        foreach ($pages as $slug => $def) {
            $pageData[$slug] = [
                'label' => $def['label'],
                'url' => route($def['route']) . '?fc_edit=1',
                'sections' => $def['sections'],
            ];
        }

        $editorData = [
            'csrf' => csrf_token(),
            'activePage' => $activePage,
            'pages' => $pageData,
            'sectionMeta' => $sections,
            'hidden' => $hidden,
            'order' => $order,
            'endpoints' => [
                'draft' => route('admin.frontend.draft'),
                'preset' => route('admin.frontend.preset'),
                'section' => route('admin.frontend.section'),
                'reorder' => route('admin.frontend.reorder'),
                'publish' => route('admin.frontend.publish'),
                'discard' => route('admin.frontend.discard'),
                'reset' => route('admin.frontend.reset'),
                'upload' => route('admin.frontend.upload'),
            ],
        ];
    @endphp

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Frontend editor') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main id="fc-editor-main" class="flex flex-col h-[calc(100vh-4rem)] overflow-y-auto lg:overflow-hidden bg-paper-0">

        {{-- ── Toolbar ── --}}
        <div class="shrink-0 bg-paper-0 border-b border-paper-200 px-5 py-2.5 flex items-center gap-3 flex-wrap">
            {{-- Page switcher --}}
            <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 flex-wrap">
                @foreach ($pages as $slug => $def)
                    <button type="button" data-fc-page="{{ $slug }}"
                        class="px-4 py-[6px] rounded-full text-[12.5px] font-semibold transition {{ $slug === $activePage ? 'bg-wa-deep text-paper-0 shadow-sm' : 'text-ink-600 hover:text-ink-900' }}">
                        {{ $def['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Device preview switcher (centred) --}}
            <div class="mx-auto flex items-center gap-0.5 bg-paper-50 rounded-full p-1">
                @php
                    $devices = [
                        'desktop' => [
                            'Desktop',
                            '<rect x="2" y="3" width="12" height="8" rx="1"/><path d="M6 13h4M8 11v2"/>',
                        ],
                        'tablet' => [
                            'Tablet',
                            '<rect x="4" y="2" width="8" height="12" rx="1.3"/><path d="M7.5 12h1"/>',
                        ],
                        'mobile' => [
                            'Mobile',
                            '<rect x="5" y="2" width="6" height="12" rx="1.3"/><path d="M7.5 12.5h1"/>',
                        ],
                    ];
                @endphp
                @foreach ($devices as $dk => [$dlabel, $dicon])
                    <button type="button" data-fc-device="{{ $dk }}" title="{{ __($dlabel) }}"
                        aria-label="{{ __($dlabel) }}"
                        class="inline-flex items-center justify-center w-8 h-7 rounded-full transition {{ $dk === 'desktop' ? 'bg-paper-0 text-wa-deep shadow-sm' : 'text-ink-500 hover:text-ink-800' }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.5">{!! $dicon !!}</svg>
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                {{-- Autosave status pill --}}
                <span id="fc-status" data-state="idle"
                    class="inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-ink-500">
                    <svg id="fc-status-dot" viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-green" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path d="M3.5 8.5 7 12l5.5-7" />
                    </svg>
                    <span id="fc-status-text">{{ __('All changes saved') }}</span>
                </span>

                <span id="fc-pending" data-count="{{ $pendingCount }}"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-accent-amber/15 text-accent-amber text-[11px] font-semibold"
                    style="{{ $pendingCount > 0 ? '' : 'display:none' }}">
                    {{ $pendingCount }} {{ $pendingCount === 1 ? __('draft') : __('drafts') }}
                </span>

                <button type="button" id="fc-fullscreen" title="{{ __('Full-screen editor') }}"
                    aria-label="{{ __('Full-screen editor') }}"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700">
                    <svg data-fc-fs-open viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 2H2v4M10 2h4v4M6 14H2v-4M10 14h4v-4" />
                    </svg>
                    <svg data-fc-fs-close viewBox="0 0 16 16" class="w-4 h-4 hidden" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M2 6h4V2M14 6h-4V2M2 10h4v4M14 10h-4v4" />
                    </svg>
                </button>
                <a href="{{ route('frontend.home') }}" target="_blank" rel="noopener"
                    title="{{ __('Open the live site') }}"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M6 3H3v10h10V10" />
                        <path d="M9 3h4v4M13 3 7 9" />
                    </svg>
                </a>
                {{-- Frontend on/off — when OFF, the public homepage redirects to login;
                     pricing / privacy / terms / other pages keep working. --}}
                {{-- Homepage on/off — uses the shared admin .toggle component (CSS-driven, clear on/off).
                     When OFF the public homepage redirects to login; legal/pricing/other pages keep working. --}}
                <label data-fc-frontend-toggle data-url="{{ route('admin.frontend.toggle-frontend') }}"
                    title="{{ __('When off, the public homepage redirects visitors to login. Pricing, privacy, terms & other pages still work.') }}"
                    class="inline-flex items-center gap-2.5 h-9 px-3.5 rounded-full border border-paper-200 bg-paper-0 text-[12px] font-semibold text-ink-700 cursor-pointer select-none">
                    <span>{{ __('Homepage') }}</span>
                    <span class="toggle"><input type="checkbox" data-fc-input {{ ($frontendEnabled ?? true) ? 'checked' : '' }}><span class="track"></span><span class="thumb"></span></span>
                </label>
                <button type="button" data-fc-discard title="{{ __('Discard unpublished drafts') }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold text-ink-700">
                    {{ __('Discard') }}
                </button>
                <button type="button" id="fc-publish"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold tracking-[0.04em] uppercase disabled:opacity-50">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M8 13V4M4.5 7.5 8 4l3.5 3.5" />
                        <path d="M3 3h10" />
                    </svg>
                    {{ __('Publish') }}
                </button>
            </div>
        </div>

        {{-- ── Body: preview + panels ── --}}
        <div class="flex-1 flex flex-col lg:flex-row min-h-0">

            {{-- Live preview iframe — wrapper centres + sizes for device modes --}}
            <div class="flex-1 min-w-0 min-h-[60vh] lg:min-h-0 bg-paper-100 p-4 flex items-stretch justify-center overflow-auto">
                <div id="fc-frame-wrap"
                    class="w-full h-full mx-auto rounded-2xl overflow-hidden border border-paper-300 shadow-card bg-paper-0 transition-[max-width] duration-300 ease-out">
                    <iframe id="fc-frame" src="{{ $previewUrl }}" class="w-full h-full border-0"
                        title="{{ __('Site preview') }}"></iframe>
                </div>
            </div>

            {{-- Right panel --}}
            <aside class="w-full lg:w-[360px] shrink-0 border-t lg:border-t-0 lg:border-l border-paper-200 bg-paper-0 overflow-y-auto">

                {{-- How-to hint --}}
                <div class="px-5 py-4 border-b border-paper-200 bg-wa-bubble/40">
                    <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-wa-deep mb-1">
                        {{ __('How to edit') }}</div>
                    <p class="text-[12px] text-ink-700 leading-relaxed">
                        {{ __('Click any text in the preview to edit inline — Enter saves, Esc cancels. Select text to bold or italicise it. Try a brand preset or tweak colours below, preview on tablet/mobile from the top bar, and Publish when ready. Nothing is public until then.') }}
                    </p>
                </div>

                {{-- Inspector --}}
                <section class="px-5 py-4 border-b border-paper-200">
                    <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-3">
                        {{ __('Selected field') }}</div>
                    <p id="fc-insp-empty" class="text-[12px] text-ink-500">
                        {{ __('Click a text element in the preview to select it.') }}</p>
                    <div id="fc-insp-card" style="display:none">
                        <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                            <div id="fc-insp-key" class="font-mono text-[11.5px] text-ink-800 break-all"></div>
                            <div class="mt-1 text-[10.5px] text-ink-500">{{ __('Type') }}: <span id="fc-insp-type"
                                    class="font-mono">text</span></div>
                        </div>

                        {{-- Link target — shown only when the selected element is a link. --}}
                        <div id="fc-insp-link" style="display:none" class="mt-2.5">
                            <label
                                class="block text-[10.5px] font-mono uppercase tracking-[0.12em] text-ink-500 mb-1">{{ __('Link target') }}</label>
                            <div class="flex items-center gap-1.5">
                                <input type="text" id="fc-insp-url"
                                    class="flex-1 min-w-0 rounded-lg border border-paper-200 bg-paper-0 px-2.5 py-1.5 text-[11.5px] focus:outline-none focus:border-wa-deep"
                                    placeholder="https://...">
                                <button type="button" id="fc-insp-url-save"
                                    class="shrink-0 px-3 py-1.5 rounded-lg bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11px] font-semibold">{{ __('Set') }}</button>
                            </div>
                        </div>

                        <button type="button" id="fc-insp-reset"
                            class="mt-2.5 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold text-ink-700">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 8a5 5 0 1 0 1.5-3.5M3 3v2.5h2.5" />
                            </svg>
                            {{ __('Reset this field') }}
                        </button>
                    </div>
                </section>

                {{-- Sections --}}
                <section class="px-5 py-4 border-b border-paper-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Sections') }}</div>
                        <span class="text-[10.5px] text-ink-400">{{ __('drag · show / hide') }}</span>
                    </div>
                    <div id="fc-sections" class="space-y-1.5"></div>
                </section>

                {{-- Theme --}}
                <section class="px-5 py-4 border-b border-paper-200">
                    <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-2.5">
                        {{ __('Brand presets') }}</div>
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        @foreach ($themePresets as $pkey => $preset)
                            <button type="button" data-fc-preset="{{ $pkey }}"
                                class="group flex flex-col items-center gap-1.5 px-2 py-2.5 rounded-xl border border-paper-200 hover:border-wa-deep hover:bg-paper-50 transition">
                                <span class="flex -space-x-1">
                                    @foreach (array_slice(array_values($preset['tokens']), 0, 4) as $hex)
                                        <span class="w-4 h-4 rounded-full ring-1 ring-paper-0"
                                            style="background: {{ $hex }}"></span>
                                    @endforeach
                                </span>
                                <span
                                    class="text-[11px] font-semibold text-ink-700 group-hover:text-wa-deep">{{ $preset['label'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-3">
                        {{ __('Theme colours') }}</div>
                    @foreach ($themeTokens as $group => $tokens)
                        <div class="mb-3.5">
                            <div class="text-[11px] font-semibold text-ink-700 mb-1.5">{{ $group }}</div>
                            <div class="space-y-1.5">
                                @foreach ($tokens as $key => [$default, $label])
                                    <label class="flex items-center gap-2.5">
                                        <input type="color" data-fc-color="{{ $key }}"
                                            value="{{ $theme[$key] ?? $default }}"
                                            class="w-7 h-7 rounded-lg border border-paper-200 cursor-pointer bg-paper-0 p-0.5 shrink-0">
                                        <span class="flex-1 text-[12px] text-ink-700">{{ $label }}</span>
                                        <span data-fc-color-text
                                            class="font-mono text-[10.5px] text-ink-500">{{ $theme[$key] ?? $default }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <button type="button" data-fc-reset-scope="theme"
                        class="mt-1 w-full px-3 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold text-ink-600">
                        {{ __('Reset all colours') }}
                    </button>
                </section>

                {{-- Danger / reset --}}
                <section class="px-5 py-4">
                    <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-3">
                        {{ __('Reset') }}</div>
                    <div class="space-y-2">
                        <button type="button" data-fc-reset-scope="{{ $activePage }}" id="fc-reset-page"
                            class="w-full px-3 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold text-ink-700">
                            {{ __('Reset this page') }}
                        </button>
                        <button type="button" data-fc-reset-scope="all"
                            class="w-full px-3 py-2 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/5 text-[12px] font-semibold">
                            {{ __('Reset entire site') }}
                        </button>
                    </div>
                </section>

            </aside>
        </div>
    </main>

    {{-- Server → JS data bridge (data only; logic lives in frontend-editor.js). --}}
    <script type="application/json" id="fc-editor-data">@json($editorData)</script>

    @push('scripts')
        <script src="{{ asset('js/frontend-editor.js') }}"></script>
    @endpush

</x-layouts.admin>
