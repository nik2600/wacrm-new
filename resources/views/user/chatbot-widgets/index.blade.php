<x-layouts.user :title="__('Chatbot Widgets')" nav-key="more" page="user-chatbot-widgets-index">

    @php
        $currentStatus = $currentStatus ?? 'all';
        $modePill = [
            'ai' => ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'AI'],
            'whatsapp' => [
                'bg' => 'bg-[#D9E5F2]',
                'text' => 'text-[#13478A]',
                'dot' => 'bg-[#3D6FB5]',
                'label' => 'WhatsApp',
            ],
            'both' => [
                'bg' => 'bg-[#F3E9FF]',
                'text' => 'text-[#5B3D8A]',
                'dot' => 'bg-[#7A52B2]',
                'label' => 'AI + WA',
            ],
        ];
        $statusPill = [
            'active' => ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'Active'],
            'paused' => ['bg' => 'bg-paper-50', 'text' => 'text-ink-500', 'dot' => 'bg-paper-200', 'label' => 'Paused'],
        ];
        $accentPalette = [
            ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep'],
            ['bg' => 'bg-[#D9E5F2]', 'text' => 'text-[#13478A]'],
            ['bg' => 'bg-[#F3E9FF]', 'text' => 'text-[#5B3D8A]'],
            ['bg' => 'bg-paper-100', 'text' => 'text-ink-700'],
        ];
    @endphp

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        @if (session('success'))
            <div
                class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <aside class="space-y-3">
                <x-side-tip>
                    Drop a one-line snippet onto any site to launch a floating chat bubble. Each widget can be powered
                    by a smart agent, route to WhatsApp, or both — visitor conversations land in your team inbox.
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Widget status') }}</div>
                    <button type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $currentStatus === 'all' ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                        <span>{{ __('All widgets') }}</span><span
                            class="font-mono text-[11px] {{ $currentStatus === 'all' ? 'opacity-90' : 'text-ink-500' }}">{{ $stats['all'] }}</span>
                    </button>
                    <button type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $currentStatus === 'active' ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                        <span class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-wa-green"></span>Active</span><span
                            class="font-mono text-[11px] {{ $currentStatus === 'active' ? 'opacity-90' : 'text-ink-500' }}">{{ $stats['active'] }}</span>
                    </button>
                    <button type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $currentStatus === 'paused' ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                        <span class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-paper-200"></span>Paused</span><span
                            class="font-mono text-[11px] {{ $currentStatus === 'paused' ? 'opacity-90' : 'text-ink-500' }}">{{ max(0, $stats['all'] - $stats['active']) }}</span>
                    </button>
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>Train first
                    </div>
                    Build a smart agent + add knowledge in <a href="{{ url('/ai-training') }}"
                        class="font-semibold text-wa-deep underline">{{ __('AI Training') }}</a>, then attach it here
                    so your widget answers in your voice.
                </div>
            </aside>

            <section class="space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Chatbot') }}
                            <span class="italic text-wa-deep">{{ __('widgets') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Embed a floating chat bubble on any page. Smart-agent replies in-browser, hands off to Unified Inbox when needed.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                            {{ $stats['active'] }} {{ __('active') }}
                        </span>
                        <a href="{{ url('/chatbot-widgets/create') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            New widget
                        </a>
                    </div>
                </div>

                {{-- Stats cards — same 4-column shape as /devices --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total widgets') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $stats['all'] }}</span><span
                                class="text-[11px] text-ink-500">{{ $stats['active'] }} {{ __('active') }}</span>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Visitors') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ number_format($stats['visitors']) }}</span><span
                                class="text-[11px] text-ink-500">{{ __('all-time') }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Avg / widget') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $stats['all'] > 0 ? number_format($stats['visitors'] / $stats['all'], 1) : '0' }}</span><span
                                class="text-[11px] text-ink-500">{{ __('visitors') }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Health') }}</span><span
                                class="text-[10px] text-wa-deep font-mono">{{ $stats['all'] > 0 ? round(($stats['active'] / max($stats['all'], 1)) * 100) : 0 }}%</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $stats['all'] > 0 && $stats['active'] === $stats['all'] ? 'healthy' : ($stats['all'] === 0 ? 'empty' : 'attention') }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">

                    {{-- Top bar: status tabs + search (matches /devices) --}}
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1">
                            <button type="button"
                                class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'all' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">{{ __('All') }}
                                <span class="ml-1 font-mono text-[10px] opacity-80">{{ $stats['all'] }}</span></button>
                            <button type="button"
                                class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'active' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">{{ __('Active') }}
                                <span
                                    class="ml-1 font-mono text-[10px] opacity-80">{{ $stats['active'] }}</span></button>
                            <button type="button"
                                class="status-tab px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $currentStatus === 'paused' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">{{ __('Paused') }}
                                <span
                                    class="ml-1 font-mono text-[10px] opacity-80">{{ max(0, $stats['all'] - $stats['active']) }}</span></button>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                    fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="7" cy="7" r="5" />
                                    <path d="m11 11 3 3" />
                                </svg>
                                <input id="widgets-search" type="search"
                                    placeholder="{{ __('Search by name or slug…') }}"
                                    class="hairline border border-paper-200 rounded-lg pl-9 pr-3 py-2 text-[12.5px] bg-white w-full sm:w-72 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                        </div>
                    </div>

                    {{-- Column headers — same column template as the data rows --}}
                    <div class="overflow-x-auto">
                    <div
                        class="px-4 py-2.5 grid grid-cols-[1.6fr_120px_140px_120px_140px_220px] min-w-[940px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div>{{ __('Widget') }}</div>
                        <div>{{ __('Engine') }}</div>
                        <div>{{ __('Smart agent') }}</div>
                        <div>{{ __('Visitors') }}</div>
                        <div>{{ __('Updated') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>

                    <div id="widgets-list">
                        @forelse ($widgets as $widget)
                            @php
                                $accent = $accentPalette[$widget->id % 4];
                                $mode = $modePill[$widget->mode] ?? $modePill['ai'];
                                $status = $statusPill[$widget->status] ?? $statusPill['active'];
                                $assistant = $widget->relationLoaded('assistant')
                                    ? $widget->assistant
                                    : $widget->assistant()->first();
                                $agentName = $assistant?->name ?: '—';
                            @endphp
                            <div class="widget-row grid grid-cols-[1.6fr_120px_140px_120px_140px_220px] min-w-[940px] items-center gap-3 px-4 py-3 border-b border-paper-200 last:border-0 hover:bg-paper-50/60"
                                data-widget-id="{{ $widget->id }}"
                                data-search-haystack="{{ Str::lower($widget->name . ' ' . $widget->slug) }}">

                                {{-- Widget: icon avatar + name + slug --}}
                                <div class="min-w-0 flex items-center gap-2.5">
                                    <span
                                        class="w-9 h-9 rounded-lg grid place-items-center shrink-0 {{ $accent['bg'] }} {{ $accent['text'] }}">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ url('/chatbot-widgets/' . $widget->id . '/edit') }}"
                                                class="font-semibold text-ink-900 text-[12.5px] truncate hover:text-wa-deep">{{ $widget->name }}</a>
                                            <span
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] {{ $status['bg'] }} {{ $status['text'] }}">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>{{ $status['label'] }}
                                            </span>
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 font-mono truncate">
                                            /{{ $widget->slug }}</div>
                                    </div>
                                </div>

                                {{-- Engine mode pill --}}
                                <div>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $mode['bg'] }} {{ $mode['text'] }}">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full {{ $mode['dot'] }}"></span>{{ $mode['label'] }}
                                    </span>
                                </div>

                                {{-- Smart agent --}}
                                <div class="text-[12px] text-ink-700 truncate">{{ $agentName }}</div>

                                {{-- Visitors count --}}
                                <div
                                    class="font-mono text-[11.5px] {{ ($widget->visitors_count ?? 0) > 0 ? 'text-ink-900' : 'text-ink-500' }}">
                                    {{ ($widget->visitors_count ?? 0) > 0 ? number_format($widget->visitors_count) : '—' }}
                                </div>

                                {{-- Updated --}}
                                <div class="min-w-0">
                                    <div class="font-mono text-[11.5px] text-ink-900 truncate">
                                        {{ $widget->updated_at->diffForHumans(short: true) }}</div>
                                    <div class="text-[10px] text-ink-500 font-mono truncate">
                                        {{ $widget->updated_at->format('M d, H:i') }}</div>
                                </div>

                                {{-- Actions: Preview · Copy snippet · Edit · Rotate token · Duplicate · Delete --}}
                                <div class="flex items-center gap-0.5 justify-end whitespace-nowrap">
                                    <a href="{{ url('/widget/' . $widget->embed_token . '/chat') }}" target="_blank"
                                        rel="noopener"
                                        class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                                        title="{{ __('Open standalone preview') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                            <circle cx="8" cy="8" r="2" />
                                        </svg>
                                    </a>
                                    <button data-copy-snippet data-widget-id="{{ $widget->id }}"
                                        data-token="{{ $widget->embed_token }}" type="button"
                                        class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                                        title="{{ __('Copy embed snippet') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="4" y="4" width="9" height="9" rx="1.5" />
                                            <path d="M3 3h8M3 3v8" />
                                        </svg>
                                    </button>
                                    <a href="{{ url('/chatbot-widgets/' . $widget->id . '/edit') }}"
                                        class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                                        title="{{ __('Edit widget') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                                        </svg>
                                    </a>
                                    <button data-rotate-token data-id="{{ $widget->id }}" type="button"
                                        class="w-7 h-7 rounded-full hover:bg-accent-amber/15 text-[#7B5A14] inline-flex items-center justify-center"
                                        title="{{ __('Rotate embed token') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                        </svg>
                                    </button>
                                    <form method="POST"
                                        action="{{ url('/chatbot-widgets/' . $widget->id . '/duplicate') }}"
                                        class="inline">@csrf
                                        <button type="submit"
                                            class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                                            title="{{ __('Duplicate widget') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <rect x="3" y="3" width="9" height="9" rx="1.5" />
                                                <rect x="6" y="6" width="7" height="7" rx="1.5"
                                                    fill="white" />
                                            </svg>
                                        </button>
                                    </form>
                                    <button data-delete data-id="{{ $widget->id }}"
                                        data-name="{{ $widget->name }}" type="button"
                                        class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                                        title="{{ __('Delete widget') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-14 text-center">
                                <div class="font-serif text-[20px] mb-1">{{ __('No widgets yet') }}</div>
                                <p class="text-[12.5px] text-ink-500 mb-4">
                                    {{ __("Build your first chat widget — it'll live as a floating bubble on the page you embed it on.") }}
                                </p>
                                <a href="{{ url('/chatbot-widgets/create') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    Create widget
                                </a>
                            </div>
                        @endforelse
                    </div>
                    </div>

                    {{-- Footer with counts --}}
                    <div
                        class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                        <div>{{ __('Showing') }} <span class="font-mono text-ink-900">{{ $widgets->count() }}</span>
                            of <span
                                class="font-mono text-ink-900">{{ method_exists($widgets, 'total') ? number_format($widgets->total()) : number_format($stats['all']) }}</span>
                        </div>
                        <div class="font-mono text-[10.5px]">Workspace · {{ $stats['all'] }} {{ __('widgets') }}
                        </div>
                    </div>
                </div>

                <div>
                    @if (method_exists($widgets, 'links'))
                        {{ $widgets->links() }}
                    @endif
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
