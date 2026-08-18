<x-layouts.user :title="__('AI Call Assistants')" nav-key="more" page="user-ai-assistants-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        @if (session('success'))
            <div
                class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <aside class="space-y-3">
                <x-side-tip>
                    Each assistant handles one phone-line role — sales triage, after-hours support, order-status
                    callbacks. Different system prompts, different voices, different tools. Live ones answer calls;
                    drafts stay parked.
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Status') }}</div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]">
                        <span>{{ __('All') }}</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $counts['all'] }}</span></div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]"><span
                            class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-wa-green"></span>Live</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $counts['live'] }}</span></div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]"><span
                            class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-paper-200"></span>Draft</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $counts['draft'] }}</span></div>
                    <div class="w-full flex items-center justify-between px-3 py-2 text-[13px]"><span
                            class="flex items-center gap-2"><span
                                class="w-2 h-2 rounded-full bg-accent-amber"></span>Paused</span><span
                            class="font-mono text-[11px] text-ink-500">{{ $counts['paused'] }}</span></div>
                </div>

                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>Setup tip
                    </div>
                    {{ __('Phone calls need THREE admin keys at /admin/api-keys: Deepgram (speech-to-text, so the AI can hear the caller), an LLM (OpenAI or Gemini, to think), and ElevenLabs (voice, to speak). If the Deepgram key is missing the assistant cannot hear callers, so it declines the call instead of answering. Leave the BYOK fields blank unless this assistant needs its own voice/quota.') }}
                </div>
            </aside>

            <section class="space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('AI Call') }}
                            <span class="italic text-wa-deep">{{ __('Assistants') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Voice agents that answer phone calls — configure once, log every call.') }}</p>
                    </div>
                    <div class="flex items-center flex-wrap gap-2">
                        <a href="{{ url('/call-logs') }}"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="8" cy="8" r="5.5" />
                                <path d="M8 5v3l2 2" />
                            </svg>
                            Call logs
                        </a>
                        <a href="{{ url('/ai-assistants/create') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            New assistant
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $counts['all'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none text-wa-deep">{{ $counts['live'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Drafts') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $counts['draft'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Paused') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $counts['paused'] }}</div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                  <div class="overflow-x-auto">
                   <div class="min-w-[760px] lg:min-w-0">
                    <div
                        class="px-4 py-2.5 grid grid-cols-[1.4fr_140px_120px_100px_220px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div>{{ __('Name') }}</div>
                        <div>{{ __('Provider · Voice') }}</div>
                        <div>{{ __('Tools') }}</div>
                        <div>{{ __('Calls 24h') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>

                    @forelse ($assistants as $a)
                        <div
                            class="px-4 py-3 grid grid-cols-[1.4fr_140px_120px_100px_220px] items-center gap-3 border-b border-paper-200 hover:bg-paper-50 transition">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] {{ $a->status === 'live' ? 'bg-wa-mint text-wa-deep' : ($a->status === 'paused' ? 'bg-accent-amber/15 text-[#7B5A14]' : 'bg-paper-100 text-ink-500') }}">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full {{ $a->status === 'live' ? 'bg-wa-green' : ($a->status === 'paused' ? 'bg-accent-amber' : 'bg-paper-200') }}"></span>
                                        {{ $a->status }}
                                    </span>
                                    <a href="{{ route('user.ai-assistants.edit', $a->id) }}"
                                        class="font-serif text-[15px] leading-tight text-ink-900 hover:text-wa-deep truncate">{{ $a->name }}</a>
                                </div>
                                <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 truncate">{{ $a->slug }}
                                </div>
                            </div>
                            <div class="font-mono text-[11px] text-ink-700">
                                <div>{{ $a->ai_model }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ $a->voice_provider }}{{ $a->voice_id ? ' · ' . $a->voice_id : '' }}</div>
                            </div>
                            <div class="font-mono text-[11px] text-ink-700">{{ $a->tools_count }}</div>
                            <div class="font-mono text-[11px] text-ink-700">
                                {{ \App\Models\AiCallLog::where('assistant_id', $a->id)->where('started_at', '>=', now()->subDay())->count() }}
                            </div>
                            <div class="text-right pr-2 flex items-center justify-end gap-2">
                                <form action="{{ route('user.ai-assistants.toggle', $a->id) }}" method="POST"
                                    class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">{{ $a->status === 'live' ? 'Pause' : 'Go live' }}</button>
                                </form>
                                <a href="{{ route('user.ai-assistants.edit', $a->id) }}"
                                    class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">Edit</a>
                                <form action="{{ route('user.ai-assistants.duplicate', $a->id) }}" method="POST"
                                    class="inline">@csrf<button type="submit"
                                        class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">{{ __('Duplicate') }}</button>
                                </form>
                                <form action="{{ route('user.ai-assistants.destroy', $a->id) }}" method="POST"
                                    class="inline"
                                    onsubmit="return confirm('Delete this assistant? Active calls in progress will continue but new calls go unanswered.');">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="px-2.5 py-1 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11px]">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <span
                                class="inline-flex w-12 h-12 rounded-2xl bg-[#F3E9FF] text-[#5B3D8A] items-center justify-center mb-3">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M8 1a3 3 0 0 0-3 3v4a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                                    <path d="M3 8a5 5 0 0 0 10 0M8 13v2" />
                                </svg>
                            </span>
                            <div class="font-serif text-[18px] leading-tight mb-1">{{ __('No call assistants yet') }}
                            </div>
                            <p class="text-[12.5px] text-ink-500 max-w-[420px] mx-auto mb-4">
                                {{ __('Configure your first voice AI. The 5-step wizard covers identity, intelligence, tools, voice, and recording in about 3 minutes.') }}
                            </p>
                            <a href="{{ url('/ai-assistants/create') }}"
                                class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                Create assistant
                            </a>
                        </div>
                    @endforelse
                   </div>
                  </div>

                    <div
                        class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                        <div>{{ __('Showing') }} <span
                                class="font-mono text-ink-900">{{ $assistants->count() }}</span> of <span
                                class="font-mono text-ink-900">{{ $assistants->total() }}</span></div>
                        @if ($assistants->hasPages())
                            <div>{{ $assistants->links() }}</div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
