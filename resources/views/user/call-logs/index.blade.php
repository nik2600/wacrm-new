<x-layouts.user :title="__('Call Logs')" nav-key="more" page="user-call-logs-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <aside class="lg:sticky lg:top-6 self-start space-y-3">
                <x-side-tip>
                    Every call your AI assistant handled — transcripts, recordings, and tool calls fired
                    mid-conversation. Filter by date, status, or assistant to debug a specific outcome.
                </x-side-tip>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Time range') }}</div>
                    @foreach ([['24h', 'Last 24h'], ['7d', 'Last 7 days'], ['30d', 'Last 30 days']] as [$k, $l])
                        @php $active = request('range', '30d') === $k; @endphp
                        <a href="{{ url('/call-logs?range=' . $k) }}"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-semibold' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span>{{ $l }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Status') }}</div>
                    @foreach ([['', 'All', null], ['completed', 'Completed', 'bg-wa-green'], ['in-progress', 'Live now', 'bg-wa-deep'], ['no-answer', 'No answer', 'bg-accent-amber'], ['failed', 'Failed', 'bg-accent-coral']] as [$k, $l, $dot])
                        @php $active = request('status') === $k; @endphp
                        <a href="{{ url('/call-logs?' . http_build_query(array_merge(request()->all(), ['status' => $k]))) }}"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-paper-50 text-ink-900 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                            <span class="flex items-center gap-2">
                                @if ($dot)
                                    <span class="w-2 h-2 rounded-full {{ $dot }}"></span>
                                @endif
                                {{ $l }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </aside>

            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            {{ __('Workspace') }}</div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Call') }}
                            <span class="italic text-wa-deep">{{ __('Logs') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2">
                            {{ __('Every voice call your AI handled — transcripts, recordings, tool timelines.') }}</p>
                    </div>
                    <a href="{{ url('/ai-assistants') }}"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M8 1a3 3 0 0 0-3 3v4a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                            <path d="M3 8a5 5 0 0 0 10 0" />
                        </svg>
                        Assistants
                    </a>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total') }}
                        </div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $totals['total'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Last 24h') }}</div>
                        <div class="mt-2 flex items-baseline gap-2"><span
                                class="font-serif text-[30px] leading-none">{{ $totals['last_24h'] }}</span><span
                                class="text-[11px] text-ink-500">{{ $totals['minutes_24h'] }}
                                {{ __('min') }}</span></div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Completed') }}</div>
                        <div class="mt-2 font-serif text-[30px] leading-none">{{ $totals['completed'] }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}
                        </div>
                        <div
                            class="mt-2 font-serif text-[30px] leading-none {{ $totals['failed'] > 0 ? 'text-accent-coral' : '' }}">
                            {{ $totals['failed'] }}</div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4">
                        <form method="GET" action="{{ url('/call-logs') }}"
                            class="flex items-center gap-2 flex-wrap">
                            <input type="hidden" name="range" value="{{ request('range', '30d') }}" />
                            <input type="hidden" name="status" value="{{ request('status', '') }}" />
                            <div class="relative">
                                <svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                    fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="7" cy="7" r="5" />
                                    <path d="m11 11 3 3" />
                                </svg>
                                <input name="q" value="{{ request('q') }}"
                                    placeholder="{{ __('Caller phone…') }}"
                                    class="pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] w-56 focus:outline-none focus:border-wa-deep" />
                            </div>
                            <select name="assistant_id"
                                class="px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] bg-paper-0">
                                <option value="0">{{ __('All assistants') }}</option>
                                @foreach ($assistants as $a)
                                    <option value="{{ $a->id }}"
                                        {{ request('assistant_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit"
                                class="px-3 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold">{{ __('Filter') }}</button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                    <div class="min-w-[720px]">
                    <div
                        class="px-4 py-2.5 grid grid-cols-[1fr_140px_140px_100px_100px_110px_120px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div>{{ __('Caller') }}</div>
                        <div>{{ __('Assistant') }}</div>
                        <div>{{ __('Started') }}</div>
                        <div>{{ __('Duration') }}</div>
                        <div>{{ __('Status') }}</div>
                        <div>{{ __('Cost') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>

                    @forelse ($logs as $log)
                        <div
                            class="px-4 py-3 grid grid-cols-[1fr_140px_140px_100px_100px_110px_120px] items-center gap-3 border-b border-paper-200 hover:bg-paper-50 transition">
                            <div class="min-w-0">
                                <a href="{{ route('user.call-logs.show', $log->id) }}"
                                    class="font-semibold text-[13px] text-ink-900 hover:text-wa-deep truncate">{{ $log->caller_phone }}</a>
                                <div class="font-mono text-[10.5px] text-ink-500 truncate">{{ $log->direction }} ·
                                    {{ $log->callee_phone }}</div>
                            </div>
                            <div class="text-[12px] truncate">{{ $log->assistant?->name ?: '—' }}</div>
                            <div class="font-mono text-[11px] text-ink-700">
                                {{ optional($log->started_at)->diffForHumans() ?: '—' }}</div>
                            <div class="font-mono text-[11px] text-ink-700">{{ $log->duration_display }}</div>
                            <div>
                                <span
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] {{ ['completed' => 'bg-wa-mint text-wa-deep', 'in-progress' => 'bg-wa-bubble text-wa-deep', 'no-answer' => 'bg-accent-amber/15 text-[#7B5A14]', 'failed' => 'bg-accent-coral/15 text-accent-coral'][$log->status] ?? 'bg-paper-100 text-ink-500' }}">
                                    {{ $log->status }}
                                </span>
                            </div>
                            <div class="font-mono text-[11px] text-ink-700">{{ $log->cost_display }}</div>
                            <div class="text-right pr-2">
                                <a href="{{ route('user.call-logs.show', $log->id) }}"
                                    class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11px]">Open</a>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <div class="font-serif text-[18px] mb-1">{{ __('No calls yet') }}</div>
                            <p class="text-[12.5px] text-ink-500 max-w-[460px] mx-auto">
                                {{ __('Once a phone number is routed to one of your assistants, calls will land here with transcripts, recordings, and any tool calls.') }}
                            </p>
                        </div>
                    @endforelse
                    </div>
                    </div>

                </div>

                <div id="calllog-pagination">
                    @include('user.partials.pagination', [
                        'paginator' => $logs,
                        'dataAttr' => 'data-calllog-page',
                        'label' => 'calls',
                    ])
                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
