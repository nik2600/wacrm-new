<x-layouts.user :title="__('Incoming webhooks')" nav-key="more" page="user-webhooks-incoming">

    @php
        $total    = $hooks->count();
        $active   = $hooks->where('is_active', true)->count();
        $received = $hooks->sum('received_count');
    @endphp

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            {{-- ════════════════ LEFT RAIL ════════════════ --}}
            <aside class="space-y-3">
                {{-- Listener card --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center bg-wa-bubble text-wa-deep">
                        <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12H7m0 0l5-5m-5 5l5 5" />
                            <circle cx="4" cy="12" r="1.4" fill="currentColor" stroke="none" />
                        </svg>
                    </div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('Incoming webhooks') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">{{ __('Listener') }}</div>
                    <div class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono {{ $active ? 'bg-wa-mint border border-wa-green/40 text-wa-deep' : 'bg-paper-50 border border-paper-200 text-ink-700' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $active ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                        {{ $active ? $active . ' ' . __('active') : __('None active') }}
                    </div>
                    @if ($total)
                        <div class="mt-3 pt-3 border-t border-paper-100 grid grid-cols-2 gap-2 text-center">
                            <div>
                                <div class="font-serif text-[20px] text-ink-900 leading-none">{{ $total }}</div>
                                <div class="font-mono text-[9.5px] uppercase tracking-wide text-ink-500 mt-1">{{ __('URLs') }}</div>
                            </div>
                            <div>
                                <div class="font-serif text-[20px] text-ink-900 leading-none">{{ $received }}</div>
                                <div class="font-mono text-[9.5px] uppercase tracking-wide text-ink-500 mt-1">{{ __('received') }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- How it works --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('How it works') }}</div>
                    <ol class="px-1 space-y-0.5">
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-wa-deep text-paper-0 shrink-0 mt-0.5">1</span>
                            <span>{{ __('Generate a webhook URL here.') }}</span>
                        </li>
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-700 shrink-0 mt-0.5">2</span>
                            <span>{{ __('Paste it into any external service (Zapier, a form, your own app).') }}</span>
                        </li>
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-700 shrink-0 mt-0.5">3</span>
                            <span>{{ __('When that service sends data, we capture it and show you exactly what arrived.') }}</span>
                        </li>
                        <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                            <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-700 shrink-0 mt-0.5">4</span>
                            <span>{{ __('Optionally auto-forward each payload to your own destination URL.') }}</span>
                        </li>
                    </ol>
                </div>

                {{-- Help --}}
                <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                        {{ __('Good to know') }}
                    </div>
                    {{ __('The 40-character token in each URL is its password — keep it private. We never store auth/cookie headers, and keep the last 100 requests per URL.') }}
                </div>
            </aside>

            {{-- ════════════════ MAIN ════════════════ --}}
            <section class="space-y-5">

                {{-- Title row --}}
                <div class="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ route('user.webhooks.index') }}" class="hover:text-wa-deep">{{ __('Webhooks') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span>{{ __('Incoming') }}</span>
                        </div>
                        <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[42px] leading-none">
                            {{ __('Incoming') }} <span class="italic text-wa-deep">{{ __('webhooks') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('A URL we host that receives data FROM other services — the opposite of an outgoing webhook. Generate one, give it out, and every request shows up below for you to inspect or relay onward.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('user.webhooks.index') }}"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M10 4l-4 4 4 4" /></svg>
                            {{ __('Outgoing') }}
                        </a>
                    </div>
                </div>

                {{-- Flash --}}
                @if (session('status'))
                    <div class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 8.5l2.5 2.5L12 5.5" /></svg>
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="px-4 py-2.5 rounded-xl bg-accent-coral/10 border border-accent-coral/40 text-[12.5px] text-accent-coral">{{ $errors->first() }}</div>
                @endif

                {{-- Generate card --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('New listener') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Generate a webhook URL') }}</h2>
                    <form method="POST" action="{{ route('user.webhooks.incoming.store') }}" class="flex items-end gap-2 flex-wrap">
                        @csrf
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Label') }} <span class="text-ink-400 font-normal">{{ __('(optional)') }}</span></label>
                            <input type="text" name="name" maxlength="128" placeholder="{{ __('e.g. Zapier order feed') }}"
                                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        </div>
                        <button type="submit"
                            class="px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                            {{ __('Generate webhook') }}
                        </button>
                    </form>
                </div>

                {{-- The list of generated URLs --}}
                @forelse ($hooks as $hook)
                    @php $url = $hook->publicUrl(); @endphp
                    <div class="bg-paper-0 border {{ $hook->is_active ? 'border-paper-200' : 'border-paper-200 opacity-75' }} rounded-2xl shadow-card overflow-hidden">
                        {{-- Card header --}}
                        <div class="px-5 py-3.5 border-b border-paper-200 flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-serif text-[16px] text-ink-900 truncate">{{ $hook->name ?: __('Incoming webhook') }}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono uppercase {{ $hook->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-500 border border-paper-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $hook->is_active ? 'bg-wa-green' : 'bg-ink-400' }}"></span>
                                        {{ $hook->is_active ? __('Live') : __('Paused') }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-ink-500 mt-0.5 font-mono">
                                    {{ $hook->received_count }} {{ __('received') }}
                                    @if ($hook->last_received_at) · {{ __('last') }} {{ $hook->last_received_at->diffForHumans() }} @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <form method="POST" action="{{ route('user.webhooks.incoming.toggle', $hook->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700">{{ $hook->is_active ? __('Pause') : __('Activate') }}</button>
                                </form>
                                <form method="POST" action="{{ route('user.webhooks.incoming.clear', $hook->id) }}" class="inline" data-confirm="{{ __('Clear all captured requests for this webhook?') }}">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11px] font-semibold text-ink-700">{{ __('Clear log') }}</button>
                                </form>
                                <form method="POST" action="{{ route('user.webhooks.incoming.destroy', $hook->id) }}" class="inline" data-confirm="{{ __('Delete this webhook? Its URL stops working immediately.') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[11px] font-semibold">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>

                        <div class="px-5 py-4 grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">
                            {{-- LEFT: URL + captured requests --}}
                            <div class="space-y-4 min-w-0">
                                {{-- URL --}}
                                <div>
                                    <label class="text-[11px] font-semibold text-ink-700 mb-1.5 block">{{ __('Your webhook URL') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="text" readonly value="{{ $url }}" id="wh-url-{{ $hook->id }}"
                                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[12px] font-mono text-ink-800 focus:outline-none">
                                        <button type="button" data-copy="wh-url-{{ $hook->id }}"
                                            class="px-3 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal shrink-0">{{ __('Copy') }}</button>
                                    </div>
                                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Accepts POST / GET / PUT / PATCH. Anyone with this URL can post to it — keep it private.') }}</div>
                                </div>

                                {{-- Captured requests --}}
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-semibold text-ink-700">{{ __('Captured requests') }}</span>
                                        <a href="{{ route('user.webhooks.incoming') }}" class="text-[11px] text-wa-deep hover:underline">{{ __('Refresh') }}</a>
                                    </div>
                                    @if ($hook->events->isEmpty())
                                        <div class="border border-dashed border-paper-200 rounded-lg px-4 py-6 text-center text-[12px] text-ink-500">
                                            {{ __('Nothing received yet. Send a test request to the URL above and it appears here instantly.') }}
                                        </div>
                                    @else
                                        <div class="space-y-1.5">
                                            @foreach ($hook->events as $ev)
                                                <details class="border border-paper-200 rounded-lg bg-paper-0">
                                                    <summary class="px-3 py-2 cursor-pointer flex items-center gap-2 text-[12px]">
                                                        <span class="px-1.5 py-0.5 rounded bg-paper-100 text-ink-700 font-mono text-[10px] uppercase">{{ $ev->method }}</span>
                                                        <span class="text-ink-500 font-mono text-[10.5px]">{{ optional($ev->received_at)->diffForHumans() }}</span>
                                                        <span class="text-ink-400 font-mono text-[10.5px] truncate">{{ $ev->source_ip }}</span>
                                                        @if ($ev->forwarded)
                                                            <span class="ml-auto px-1.5 py-0.5 rounded text-[9.5px] font-mono {{ $ev->forward_error ? 'bg-accent-coral/10 text-accent-coral' : 'bg-wa-mint text-wa-deep' }}">
                                                                {{ __('relayed') }}{{ $ev->forward_status ? ' ' . $ev->forward_status : '' }}
                                                            </span>
                                                        @endif
                                                    </summary>
                                                    <div class="px-3 pb-3 space-y-2">
                                                        @if ($ev->content_type)
                                                            <div class="text-[10.5px] font-mono text-ink-500">{{ $ev->content_type }}</div>
                                                        @endif
                                                        <div>
                                                            <div class="text-[10px] font-mono uppercase tracking-wide text-ink-500 mb-1">{{ __('Headers') }}</div>
                                                            <pre class="text-[10.5px] bg-paper-50 border border-paper-100 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all text-ink-700">{{ json_encode($ev->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] font-mono uppercase tracking-wide text-ink-500 mb-1">{{ __('Body') }}</div>
                                                            <pre class="text-[11px] bg-paper-50 border border-paper-100 rounded p-2 overflow-x-auto whitespace-pre-wrap break-all text-ink-800">{{ $ev->payload ?: '(empty)' }}</pre>
                                                        </div>
                                                        @if ($ev->forward_error)
                                                            <div class="text-[10.5px] text-accent-coral">{{ __('Relay error') }}: {{ $ev->forward_error }}</div>
                                                        @endif
                                                    </div>
                                                </details>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- RIGHT: forward / relay --}}
                            <aside>
                                <form method="POST" action="{{ route('user.webhooks.incoming.forward', $hook->id) }}"
                                    class="rounded-2xl border border-paper-200 bg-paper-50/40 p-4">
                                    @csrf
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Relay') }}</div>
                                    <h3 class="font-serif text-[16px] leading-tight mt-0.5 mb-2">{{ __('Forward to my system') }}</h3>
                                    <label class="flex items-center gap-2 text-[12px] font-semibold text-ink-800 mb-2.5">
                                        <input type="checkbox" name="forward_enabled" value="1" class="accent-wa-deep" @checked($hook->forward_enabled)>
                                        {{ __('Forward each request') }}
                                    </label>
                                    <input type="url" name="forward_url" value="{{ $hook->forward_url }}" placeholder="https://your-system.com/receive"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12px] font-mono focus:outline-none focus:border-wa-deep mb-2">
                                    <button type="submit" class="w-full px-3 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save relay') }}</button>
                                    <p class="text-[10.5px] text-ink-500 mt-2 leading-relaxed">{{ __('When on, every received request is re-sent (same body + content-type) to this URL.') }}</p>
                                </form>
                            </aside>
                        </div>
                    </div>
                @empty
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-10 text-center shadow-card">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-wa-bubble text-wa-deep mb-3">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path d="M21 12H7m0 0l5-5m-5 5l5 5" /></svg>
                        </div>
                        <div class="font-serif text-[22px] leading-tight">{{ __('No incoming webhooks yet') }}</div>
                        <p class="text-[12.5px] text-ink-600 mt-2 max-w-md mx-auto">
                            {{ __('Use the form above to generate your first URL. Whatever any service sends to it will appear right here, and you can forward it to your own system.') }}
                        </p>
                    </div>
                @endforelse
            </section>
        </div>
    </main>
</x-layouts.user>
