<x-layouts.user :title="__('Call detail · #') . $log->id" nav-key="more" page="user-call-logs-detail">

    <main class="max-w-[1100px] mx-auto px-4 sm:px-6 lg:px-7 py-7">

        <div class="flex items-center gap-2 text-[11.5px] text-ink-500 mb-3">
            <a href="{{ url('/call-logs') }}" class="hover:text-wa-deep">{{ __('Call logs') }}</a>
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M6 3l5 5-5 5" />
            </svg>
            <span class="text-ink-700 font-mono">#{{ $log->id }}</span>
        </div>

        <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
            <div class="min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ $log->direction }} ·
                    {{ optional($log->started_at)->format('M d, Y · H:i') }}</div>
                <h1 class="font-serif text-[28px] leading-tight">{{ $log->caller_phone }}</h1>
                <p class="text-[13px] text-ink-500 mt-1">
                    @if ($log->assistant)
                        {{-- Link the assistant name straight into the wizard so an
 operator can iterate on persona / model / voice without
 hunting through /ai-assistants list. --}}
                        <a href="{{ url('/ai-assistants/' . $log->assistant->id . '/edit') }}"
                            class="text-wa-deep hover:underline">{{ $log->assistant->name }}</a>
                    @else
                        {{ __('Unassigned') }}
                    @endif
                    · {{ $log->duration_display }} ·
                    <span class="font-mono">{{ $log->status }}</span>
                    @if (!empty($log->cost_minor))
                        · <span class="font-mono">{{ number_format($log->cost_minor / 100, 2) }}
                            {{ $log->currency_code ?: 'USD' }}</span>
                    @endif
                </p>
                @if (!empty($log->failure_reason))
                    {{-- Surface the failure reason inline on failed/no-answer rows
 so the operator doesn't have to dig into the database
 to find why a call dropped. --}}
                    <div
                        class="mt-2 inline-flex items-start gap-2 px-3 py-1.5 rounded-lg bg-accent-coral/10 border border-accent-coral/25 text-[11.5px] text-accent-coral max-w-2xl">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.7">
                            <path d="M8 4v5M8 12h.01" />
                            <circle cx="8" cy="8" r="6.5" />
                        </svg>
                        <span>{{ $log->failure_reason }}</span>
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if ($log->conversation_id)
                    <a href="{{ url('/team-inbox?conv=' . $log->conversation_id) }}"
                        class="px-4 py-2 border border-paper-200 rounded-full hover:bg-paper-50 text-[12px]">Open in
                        inbox</a>
                @endif
                @if ($log->recording_url_mixed)
                    <a href="{{ $log->recording_url_mixed }}" target="_blank" rel="noopener"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M5 3l8 5-8 5z" />
                        </svg>
                        Play recording
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Tokens (AI)') }}
                </div>
                <div class="mt-2 font-serif text-[24px] leading-none">
                    {{ number_format($log->ai_tokens_in + $log->ai_tokens_out) }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ number_format($log->ai_tokens_in) }} in /
                    {{ number_format($log->ai_tokens_out) }} {{ __('out') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('STT seconds') }}
                </div>
                <div class="mt-2 font-serif text-[24px] leading-none">{{ number_format($log->stt_seconds) }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('TTS chars') }}</div>
                <div class="mt-2 font-serif text-[24px] leading-none">{{ number_format($log->tts_chars) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">

            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                    <h2 class="font-serif text-[16px]">{{ __('Transcript') }}</h2>
                    <span class="font-mono text-[10.5px] text-ink-500">{{ count($log->transcript_json ?? []) }}
                        {{ __('turns') }}</span>
                </div>
                <div class="p-5 space-y-3 max-h-[600px] overflow-y-auto">
                    @forelse (($log->transcript_json ?? []) as $turn)
                        @php $isAgent = ($turn['role'] ?? '') === 'agent'; @endphp
                        <div class="flex {{ $isAgent ? 'justify-start' : 'justify-end' }}">
                            <div
                                class="max-w-[80%] px-3 py-2 rounded-lg text-[12.5px] whitespace-pre-wrap leading-snug {{ $isAgent ? 'bg-paper-0 border border-paper-200 rounded-bl-[4px]' : 'bg-wa-mint rounded-br-[4px]' }}">
                                <div
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] {{ $isAgent ? 'text-wa-deep' : 'text-ink-700' }} mb-0.5">
                                    {{ $isAgent ? 'AI agent' : 'Caller' }}{{ isset($turn['t']) ? ' · ' . gmdate('i:s', (int) ($turn['t'] / 1000)) : '' }}
                                </div>
                                {{ $turn['text'] ?? '' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-[12.5px] text-ink-500 italic">
                            {{ __('No transcript captured for this call.') }}</div>
                    @endforelse
                </div>
            </div>

            <aside class="space-y-4">
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-5">
                    <h3 class="font-serif text-[15px] mb-3">{{ __('Tool calls fired') }}</h3>
                    @forelse (($log->tool_calls_json ?? []) as $tc)
                        <div class="border-l-2 border-wa-deep pl-3 mb-3 last:mb-0">
                            <div class="font-mono text-[11px] text-wa-deep font-semibold">{{ $tc['name'] ?? 'tool' }}
                            </div>
                            <div class="font-mono text-[10.5px] text-ink-500">
                                {{ isset($tc['t']) ? gmdate('i:s', (int) ($tc['t'] / 1000)) : '' }}</div>
                            @if (!empty($tc['args']))
                                <pre class="mt-1 px-2 py-1 bg-paper-50 rounded text-[10.5px] font-mono overflow-x-auto">{{ json_encode($tc['args'], JSON_PRETTY_PRINT) }}</pre>
                            @endif
                        </div>
                    @empty
                        <p class="text-[12px] text-ink-500 italic">
                            {{ __('No tools were called during this conversation.') }}</p>
                    @endforelse
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-5">
                    <h3 class="font-serif text-[15px] mb-3">{{ __('Recordings') }}</h3>
                    @if ($log->recording_url_agent)
                        <div class="mb-3">
                            <div class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                                {{ __('Agent side') }}</div>
                            <audio src="{{ $log->recording_url_agent }}" controls class="w-full"></audio>
                        </div>
                    @endif
                    @if ($log->recording_url_user)
                        <div class="mb-3">
                            <div class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                                {{ __('Caller side') }}</div>
                            <audio src="{{ $log->recording_url_user }}" controls class="w-full"></audio>
                        </div>
                    @endif
                    @if ($log->recording_url_mixed)
                        <div class="mb-3">
                            <div class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                                {{ __('Full call') }}</div>
                            <audio src="{{ $log->recording_url_mixed }}" controls preload="metadata"
                                class="w-full"></audio>
                        </div>
                    @endif
                    @if (!$log->recording_url_agent && !$log->recording_url_user && !$log->recording_url_mixed)
                        <p class="text-[12px] text-ink-500 italic">{{ __('Recording disabled for this call.') }}</p>
                    @endif
                </div>
            </aside>
        </div>
    </main>

</x-layouts.user>
