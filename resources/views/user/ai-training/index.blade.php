<x-layouts.user :title="__('AI Training')" nav-key="more" page="user-ai-training-index">

    @php
        $currentStatus = $currentStatus ?? 'all';
        $statusPill = [
            'active' => ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'Active'],
            'paused' => ['bg' => 'bg-paper-50', 'text' => 'text-ink-500', 'dot' => 'bg-paper-200', 'label' => 'Paused'],
        ];
        $providerPill = [
            'openai' => ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'OpenAI'],
            'anthropic' => [
                'bg' => 'bg-[#F3E9FF]',
                'text' => 'text-[#5B3D8A]',
                'dot' => 'bg-[#7A52B2]',
                'label' => 'Anthropic',
            ],
            'gemini' => [
                'bg' => 'bg-[#D9E5F2]',
                'text' => 'text-[#13478A]',
                'dot' => 'bg-[#3D6FB5]',
                'label' => 'Gemini',
            ],
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
        @if (session('status'))
            <div class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('status') }}</div>
        @endif

        {{-- Meta Business Agent coexistence — choose who answers, never reply
             twice. $_mode / $_metaOn / $modes are passed from
             AiTrainingController::index (not inline) so they're always bound
             even if a host serves a stale-compiled view. --}}
        <form method="POST" action="{{ route('user.ai-training.responder-mode') }}"
            class="mb-6 bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            @csrf
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="font-serif text-[19px] leading-tight">{{ __('Who answers your WhatsApp?') }}</h2>
                    <p class="text-[12.5px] text-ink-600 mt-1 max-w-2xl">{{ __('If you turn on Meta’s own Business Agent, our AI + keyword auto-replies stand down for this workspace — so your customer never gets two answers to the same message.') }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-[12.5px] text-ink-700 shrink-0">
                    <input type="checkbox" name="meta_agent_enabled" value="1" @checked($_metaOn) class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep">
                    {{ __('I use Meta Business Agent') }}
                </label>
            </div>

            {{-- $modes is passed from AiTrainingController::index (not defined
                 inline) so it's always bound; the ?? [] is a final safety net. --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
                @foreach (($modes ?? []) as $key => [$label, $desc])
                    <label class="cursor-pointer border rounded-[14px] p-3.5 {{ $_mode === $key ? 'border-wa-deep bg-wa-mint/30' : 'border-paper-200 hover:bg-paper-50' }}">
                        <div class="flex items-center gap-2">
                            <input type="radio" name="ai_responder_mode" value="{{ $key }}" @checked($_mode === $key) class="text-wa-deep focus:ring-wa-deep">
                            <span class="text-[13px] font-semibold">{{ $label }}</span>
                        </div>
                        <p class="text-[11.5px] text-ink-500 mt-1.5 leading-relaxed">{{ $desc }}</p>
                    </label>
                @endforeach
            </div>
            <div class="flex items-center gap-2 mt-4">
                <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Save') }}</button>
                <span class="text-[11px] text-ink-500">{{ __('Meta’s agent needs a WhatsApp Business (Cloud API) number — Unofficial-API numbers always use our AI.') }}</span>
            </div>
        </form>

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <aside class="space-y-3">
                <x-side-tip>
                    Smart agents power your <a href="{{ url('/chatbot-widgets') }}"
                        class="font-semibold text-wa-deep underline">{{ __('chatbot widgets') }}</a> and any text channel
                    we add later. The 5-step builder walks you through identity, persona, brain, safety, and knowledge.
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Agent status') }}</div>
                    <button type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $currentStatus === 'all' ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                        <span>{{ __('All agents') }}</span><span
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
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>Knowledge tip
                    </div>
                    {{ __('Single FAQ Q&A is often enough. A few help-doc URLs cover most support questions — no need to dump your whole site.') }}
                </div>
            </aside>

            <section class="space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">AI <span
                                class="italic text-wa-deep">{{ __('Training') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __("Build smart agents that speak in your brand's voice — train them on URLs, text, Q&A pairs, and plain-text files.") }}
                        </p>
                    </div>
                    <div class="flex items-center flex-wrap gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                            {{ $stats['active'] }} {{ __('active') }}
                        </span>
                        <a href="{{ url('/ai-training/create') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            New smart agent
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total agents') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $stats['all'] }}</span><span
                                class="text-[11px] text-ink-500">{{ $stats['active'] }} {{ __('active') }}</span>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Knowledge entries') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ number_format($stats['sources']) }}</span><span
                                class="text-[11px] text-ink-500">{{ $stats['ready'] }} {{ __('indexed') }}</span>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="flex items-center justify-between"><span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Avg / agent') }}</span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $stats['all'] > 0 ? number_format($stats['sources'] / $stats['all'], 1) : '0' }}</span><span
                                class="text-[11px] text-ink-500">{{ __('entries') }}</span></div>
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
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <div class="relative w-full sm:w-auto">
                                <svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                    fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="7" cy="7" r="5" />
                                    <path d="m11 11 3 3" />
                                </svg>
                                <input id="ait-search" type="search" placeholder="{{ __('Search by name or slug…') }}"
                                    class="hairline border border-paper-200 rounded-lg pl-9 pr-3 py-2 text-[12.5px] bg-white w-full sm:w-72 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                        </div>
                    </div>

                  <div class="overflow-x-auto">
                   <div class="min-w-[820px] lg:min-w-0">
                    <div
                        class="px-4 py-2.5 grid grid-cols-[1.6fr_120px_120px_120px_140px_180px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div>{{ __('Agent') }}</div>
                        <div>{{ __('Provider') }}</div>
                        <div>{{ __('Knowledge') }}</div>
                        <div>{{ __('Tone') }}</div>
                        <div>{{ __('Updated') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>

                    <div id="ait-list">
                        @forelse ($assistants as $a)
                            @php
                                $accent = $accentPalette[$a->id % 4];
                                $status = $statusPill[$a->status] ?? $statusPill['active'];
                                $provider = $providerPill[$a->ai_provider] ?? $providerPill['openai'];
                            @endphp
                            <div class="ait-row grid grid-cols-[1.6fr_120px_120px_120px_140px_180px] items-center gap-3 px-4 py-3 border-b border-paper-200 last:border-0 hover:bg-paper-50/60"
                                data-search-haystack="{{ Str::lower($a->name . ' ' . $a->slug) }}">

                                <div class="min-w-0 flex items-center gap-2.5">
                                    <span
                                        class="w-9 h-9 rounded-lg grid place-items-center shrink-0 {{ $accent['bg'] }} {{ $accent['text'] }}">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                            stroke-width="1.6">
                                            <circle cx="8" cy="8" r="6" />
                                            <path d="M6 7.5a1.5 1.5 0 1 1 2.5 1.2c-.6.3-1 .7-1 1.3M8 11.2v.05" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ url('/ai-training/' . $a->id . '/edit') }}"
                                                class="font-semibold text-ink-900 text-[12.5px] truncate hover:text-wa-deep">{{ $a->name }}</a>
                                            <span
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] {{ $status['bg'] }} {{ $status['text'] }}">
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>{{ $status['label'] }}
                                            </span>
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 font-mono truncate">
                                            /{{ $a->slug }} · {{ $a->ai_model }}</div>
                                    </div>
                                </div>

                                <div>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $provider['bg'] }} {{ $provider['text'] }}">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full {{ $provider['dot'] }}"></span>{{ $provider['label'] }}
                                    </span>
                                </div>

                                <div
                                    class="font-mono text-[11.5px] {{ ($a->training_sources_count ?? 0) > 0 ? 'text-ink-900' : 'text-ink-500' }}">
                                    {{ ($a->training_sources_count ?? 0) > 0 ? number_format($a->training_sources_count) . ' entries' : '—' }}
                                </div>

                                <div class="text-[12px] text-ink-700 capitalize">{{ $a->tone ?? 'helpful' }}</div>

                                <div class="min-w-0">
                                    <div class="font-mono text-[11.5px] text-ink-900 truncate">
                                        {{ $a->updated_at->diffForHumans(short: true) }}</div>
                                    <div class="text-[10px] text-ink-500 font-mono truncate">
                                        {{ $a->updated_at->format('M d, H:i') }}</div>
                                </div>

                                <div class="flex items-center gap-0.5 justify-end whitespace-nowrap">
                                    <a href="{{ url('/ai-training/' . $a->id . '/edit') }}"
                                        class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                                        title="{{ __('Edit agent') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ url('/ai-training/' . $a->id . '/duplicate') }}"
                                        class="inline">@csrf
                                        <button type="submit"
                                            class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                                            title="{{ __('Duplicate agent') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <rect x="3" y="3" width="9" height="9" rx="1.5" />
                                                <rect x="6" y="6" width="7" height="7" rx="1.5"
                                                    fill="white" />
                                            </svg>
                                        </button>
                                    </form>
                                    <button data-delete data-id="{{ $a->id }}"
                                        data-name="{{ $a->name }}" type="button"
                                        class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                                        title="{{ __('Delete agent') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-14 text-center">
                                <div class="font-serif text-[20px] mb-1">{{ __('No smart agents yet') }}</div>
                                <p class="text-[12.5px] text-ink-500 mb-4">
                                    {{ __('Build your first agent — the 5-step builder walks you through identity, persona, brain, safety, and the knowledge it should know.') }}
                                </p>
                                <a href="{{ url('/ai-training/create') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    Build smart agent
                                </a>
                            </div>
                        @endforelse
                    </div>
                   </div>
                  </div>

                    <div
                        class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                        <div>{{ __('Showing') }} <span
                                class="font-mono text-ink-900">{{ $assistants->count() }}</span> of <span
                                class="font-mono text-ink-900">{{ method_exists($assistants, 'total') ? number_format($assistants->total()) : number_format($stats['all']) }}</span>
                        </div>
                        <div class="font-mono text-[10.5px]">Workspace · {{ $stats['all'] }} {{ __('agents') }}
                        </div>
                    </div>
                </div>

                <div>
                    @if (method_exists($assistants, 'links'))
                        {{ $assistants->links() }}
                    @endif
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
