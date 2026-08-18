<x-layouts.user :title="__('Trello')" nav-key="more" page="user-trello-dashboard">
    @php $isConnected = $integration && $integration->isConnected(); @endphp

    <style>
        .tr-accent { color: #0079BF; }
        .tr-bg { background-color: #0079BF; }
        .tr-bg:hover { background-color: #005A8C; }
        .tr-soft { background-color: #E4F0F9; }
    </style>

    @php
        $trelloIcon = '<svg viewBox="0 0 24 24" class="w-7 h-7"><rect width="24" height="24" rx="4" fill="#0079BF"/><rect x="4" y="4" width="6.5" height="16" rx="1.2" fill="#fff"/><rect x="13.5" y="4" width="6.5" height="9" rx="1.2" fill="#fff"/></svg>';
    @endphp

    @if (!$isConnected)
        {{-- ═══════════════ NOT CONNECTED ═══════════════ --}}
        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <aside class="space-y-3">
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                        <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center tr-soft">{!! $trelloIcon !!}</div>
                        <div class="font-serif text-[18px] leading-tight">{{ __('Trello') }}</div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">{{ __('Integration') }}</div>
                        <div class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-paper-50 border border-paper-200 text-ink-700"><span class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>{{ __('Not connected') }}</div>
                    </div>
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ __('Setup steps') }}</div>
                        <ol class="px-1 space-y-0.5">
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $enabled ? 'text-ink-700' : 'text-ink-500' }}"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $enabled ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $enabled ? '✓' : '1' }}</span>{{ __('Admin enables Trello') }}</li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $enabled ? 'bg-wa-deep/8 text-wa-deep font-semibold' : 'text-ink-500' }}"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $enabled ? 'text-paper-0' : 'bg-paper-100 text-ink-500' }}" @style(['background:#0079BF' => $enabled])>2</span>{{ __('Generate API key + token') }}</li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">3</span>{{ __('Paste keys + board below') }}</li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">4</span>{{ __('Card events notify on WhatsApp') }}</li>
                        </ol>
                    </div>
                    <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-wa-green"></span>{{ __('Need help?') }}</div>
                        {{ __('The webhook installs automatically. Your site must be reachable over public HTTPS.') }} <a href="{{ url('/support') }}" class="text-wa-deep font-semibold underline">{{ __('Contact support') }}</a>.
                    </div>
                </aside>

                <section class="space-y-5">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2"><a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a><span class="mx-1.5 text-ink-500/60">/</span><span>{{ __('Trello') }}</span></div>
                            <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">{{ __('Connect') }} <span class="italic tr-accent">{{ __('Trello') }}</span></h1>
                            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('When a card is assigned (or added, changed, or deleted) on your board, the right person gets a WhatsApp message — so tasks never sit unseen.') }}</p>
                        </div>
                        <div class="flex items-center gap-2"><a href="{{ url('/integrations') }}" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4" /></svg>{{ __('Back') }}</a></div>
                    </div>

                    @if (session('error') || $errors->any())
                        <div class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F] inline-flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 5v3M8 11v.01" /><circle cx="8" cy="8" r="6" /></svg>{{ session('error') ?: $errors->first() }}</div>
                    @endif

                    @if (!$enabled)
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
                            <div class="w-12 h-12 rounded-xl bg-accent-amber/20 grid place-items-center shrink-0"><svg viewBox="0 0 24 24" class="w-6 h-6 text-accent-amber" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v3M12 16h.01" /><circle cx="12" cy="12" r="9" /></svg></div>
                            <div class="flex-1 min-w-0"><div class="font-serif text-[22px] leading-tight">{{ __("Trello isn't enabled yet") }}</div><p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">{{ __('An administrator must enable the Trello integration before your workspace can connect.') }}</p></div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_330px] gap-5 items-start">
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 3') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-0.5 mb-4">{{ __('Board credentials') }}</h2>
                                <form method="POST" action="{{ route('user.trello.connect') }}" class="space-y-4">
                                    @csrf
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('API key') }} <span class="text-accent-coral">*</span></label><input name="api_key" required class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                        <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('OAuth secret') }} <span class="text-accent-coral">*</span></label><input type="password" name="api_secret" required autocomplete="new-password" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                    </div>
                                    <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Token') }} <span class="text-accent-coral">*</span></label><input type="password" name="token" required autocomplete="new-password" placeholder="{{ __('expiration=never, read scope') }}" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                    <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Board') }} <span class="text-accent-coral">*</span></label><input name="board" required placeholder="{{ __('board ID or board URL') }}" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                    @if ($senders->isNotEmpty())
                                        <div>
                                            <label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Send from') }} <span class="text-ink-500 font-normal">({{ __('which WhatsApp number') }})</span></label>
                                            <select name="send_from" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                                <option value="">{{ __('Workspace default') }}</option>
                                                @foreach ($senders as $s)
                                                    <option value="{{ $s['key'] }}" @selected($currentSender === $s['key'])>{{ $s['label'] }} · {{ $s['engine'] }}{{ $s['phone'] ? ' · +' . $s['phone'] : '' }}</option>
                                                @endforeach
                                            </select>
                                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('The connected number card notifications send through. You run more than one — pick the right one.') }}</div>
                                        </div>
                                    @endif
                                    <div class="rounded-lg bg-paper-50/60 border border-paper-200 p-3"><div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">{{ __('Webhook callback · auto-registered on connect') }}</div><div class="font-mono text-[11px] text-ink-700 break-all">{{ $callbackUrl }}</div></div>
                                    <div class="flex items-center gap-2 pt-2"><button type="submit" class="px-4 py-2 rounded-full tr-bg text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 ml-auto"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 8l5 5 7-9" /></svg>{{ __('Connect board') }}</button></div>
                                </form>
                            </div>
                            <aside class="space-y-4">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('How to get the keys') }}</div>
                                    <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">{{ __('At trello.com/power-ups/admin') }}</h3>
                                    <ol class="space-y-3 text-[12.5px] text-ink-700">
                                        @foreach ([__('Create a Power-Up (a placeholder is fine).'), __('API Key tab → Generate. Copy key + OAuth secret.'), __('Authorize a token (expiration=never) and copy it.'), __('Copy the board ID or board URL.'), __('Paste everything on the left and Connect.')] as $i => $step)
                                            <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center font-mono text-[10px] shrink-0 mt-0.5">{{ $i + 1 }}</span><span>{{ $step }}</span></li>
                                        @endforeach
                                    </ol>
                                </div>
                            </aside>
                        </div>
                    @endif
                </section>
            </div>
        </main>
    @else
        {{-- ═══════════════ CONNECTED — redesigned ═══════════════ --}}
        @php
            $total = $logs->count();
            $okCount = $logs->filter(fn ($l) => $l->status === 'ok')->count();
            $failCount = $total - $okCount;
            $lastLog = $logs->first();
            $ev = $integration->enabledEvents();
        @endphp

        @if (session('status'))
            <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
                <div class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 8 3 3 5-6" /></svg>{{ session('status') }}</div>
            </div>
        @endif

        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500"><a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a><span class="mx-1.5 text-ink-500/60">/</span><span class="text-ink-700">{{ __('Trello') }}</span></div>

            {{-- HERO BANNER --}}
            <div class="rounded-2xl border border-paper-200 shadow-card overflow-hidden">
                <div class="px-6 py-5 flex flex-wrap items-center gap-4 bg-paper-50">
                    <span class="w-14 h-14 rounded-2xl bg-paper-0 border border-paper-200 grid place-items-center shrink-0 shadow-card">{!! $trelloIcon !!}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif text-[26px] sm:text-[30px] leading-none truncate text-ink-900">{{ $integration->board_name ?: __('Trello board') }}</h1>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40"><span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Connected') }}</span>
                        </div>
                        <div class="text-[12.5px] text-ink-600 mt-1.5">{{ __('Card activity on this board notifies the right person on WhatsApp.') }}</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @unless ($integration->webhook_id)
                            <form method="POST" action="{{ route('user.trello.register', $integration->id) }}">@csrf<button class="px-3.5 py-2 border border-accent-coral/40 rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium text-accent-coral">{{ __('Re-register webhook') }}</button></form>
                        @endunless
                        <a href="#settings" class="px-4 py-2 rounded-full tr-bg text-paper-0 text-[12px] font-semibold">{{ __('Manage') }}</a>
                    </div>
                </div>
            </div>

            {{-- KPI ROW --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Events') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">{{ number_format($total) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ __('recent total') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Notified') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums text-wa-deep">{{ number_format($okCount) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ __('sent to WhatsApp') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums {{ $failCount ? 'text-accent-coral' : 'text-ink-400' }}">{{ number_format($failCount) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ __('no match / error') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Webhook') }}</div>
                    <div class="font-serif text-[19px] leading-tight mt-2.5 {{ $integration->webhook_id ? 'text-wa-deep' : 'text-accent-coral' }}">{{ $integration->webhook_id ? __('Active') : __('Missing') }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ $integration->webhook_id ? __('delivering events') : __('re-register it') }}</div>
                </div>
            </div>

            {{-- ACTIVITY + TRIGGERS --}}
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-4 items-start">
                <div id="activity" class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-paper-100 flex items-center justify-between">
                        <h3 class="font-serif text-[18px] leading-tight">{{ __('Recent activity') }}</h3>
                        <span class="font-mono text-[10px] px-2 py-0.5 rounded-full bg-paper-100 text-ink-600">{{ $total }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 text-left font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                <tr><th class="px-5 py-2">{{ __('Event') }}</th><th class="px-4 py-2 whitespace-nowrap">{{ __('When') }}</th><th class="px-4 py-2 text-right">{{ __('Status') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-paper-100">
                                @forelse ($logs as $log)
                                    <tr class="hover:bg-paper-50">
                                        <td class="px-5 py-2.5 text-ink-700"><span class="block truncate max-w-[420px]">{{ $log->detail ?: $log->event }}</span></td>
                                        <td class="px-4 py-2.5 font-mono text-[11px] text-ink-500 whitespace-nowrap">{{ $log->created_at?->diffForHumans() }}</td>
                                        <td class="px-4 py-2.5 text-right"><span class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $log->status === 'ok' ? 'bg-wa-green/15 text-wa-deep' : 'bg-accent-coral/15 text-accent-coral' }}">{{ $log->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-10 text-center text-[12px] text-ink-500">{{ __('No card events yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <h3 class="font-serif text-[18px] leading-tight mb-2">{{ __('What triggers a message') }}</h3>
                        <ul class="text-[12.5px] text-ink-700 space-y-2">
                            <li class="flex items-center gap-2"><span class="font-mono text-[10.5px] px-1.5 py-0.5 rounded bg-paper-100">addMemberToCard</span> {{ __('assignee notified') }}</li>
                            <li class="flex items-center gap-2"><span class="font-mono text-[10.5px] px-1.5 py-0.5 rounded bg-paper-100">createCard</span> {{ __('card added') }}</li>
                            <li class="flex items-center gap-2"><span class="font-mono text-[10.5px] px-1.5 py-0.5 rounded bg-paper-100">updateCard</span> {{ __('moved / due') }}</li>
                            <li class="flex items-center gap-2"><span class="font-mono text-[10.5px] px-1.5 py-0.5 rounded bg-paper-100">deleteCard</span> {{ __('card deleted') }}</li>
                        </ul>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px] mb-1">{{ __('Good to know') }}</div>
                        <p class="text-[11.5px] text-ink-700">{{ __('Assignees are matched to a WhatsApp contact by their Trello name — make sure they exist as contacts. Every delivery is HMAC-verified.') }}</p>
                    </div>
                </aside>
            </div>

            {{-- SETTINGS (events + notify) --}}
            <form id="settings" method="POST" action="{{ route('user.trello.settings', $integration->id) }}" class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-4">
                @csrf
                <h3 class="font-serif text-[18px] leading-tight">{{ __('Notification settings') }}</h3>
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Notify on') }}</div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[12.5px]">
                        @foreach (['addMemberToCard' => __('Card assigned'), 'createCard' => __('Card added'), 'updateCard' => __('Card updated'), 'deleteCard' => __('Card deleted')] as $key => $label)
                            <label class="flex items-center gap-2 border border-paper-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <input type="checkbox" name="events[]" value="{{ $key }}" class="accent-wa-deep" {{ in_array($key, $ev, true) ? 'checked' : '' }} {{ $key === 'addMemberToCard' ? 'disabled checked' : '' }}>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-paper-100">
                    <label class="block space-y-1.5"><span class="text-[11.5px] font-semibold text-ink-700">{{ __('Add/update/delete go to') }}</span>
                        <select name="notify_mode" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                            <option value="assignee" {{ $integration->notify_mode === 'assignee' ? 'selected' : '' }}>{{ __('Nobody (assignment only)') }}</option>
                            <option value="fixed" {{ $integration->notify_mode === 'fixed' ? 'selected' : '' }}>{{ __('A fixed number') }}</option>
                        </select>
                    </label>
                    <label class="block space-y-1.5"><span class="text-[11.5px] font-semibold text-ink-700">{{ __('Fixed number') }} <span class="text-ink-500 font-normal">({{ __('with country code') }})</span></span>
                        <input name="notify_number" value="{{ $integration->notify_number }}" placeholder="9198…" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                    </label>
                </div>
                @if ($senders->isNotEmpty())
                    <div class="pt-3 border-t border-paper-100">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Send from') }} <span class="text-ink-500 font-normal">({{ __('which WhatsApp number') }})</span></span>
                        <select name="send_from" class="mt-1.5 w-full sm:w-1/2 px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                            <option value="">{{ __('Workspace default') }}</option>
                            @foreach ($senders as $s)
                                <option value="{{ $s['key'] }}" @selected($currentSender === $s['key'])>{{ $s['label'] }} · {{ $s['engine'] }}{{ $s['phone'] ? ' · +' . $s['phone'] : '' }}</option>
                            @endforeach
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('You run more than one number — pick which one card notifications send through.') }}</div>
                    </div>
                @endif
                <div class="flex items-center justify-end gap-3 pt-1">
                    @unless ($integration->webhook_id)
                        <span class="text-[11px] text-accent-coral mr-auto">{{ __('Webhook not registered — re-register it above to receive events.') }}</span>
                    @endunless
                    <button class="px-4 py-2 rounded-full tr-bg text-paper-0 text-[12px] font-semibold">{{ __('Save settings') }}</button>
                </div>
            </form>

            {{-- Disconnect (separate card — never nest forms) --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card flex items-center justify-between gap-3">
                <div>
                    <div class="text-[12.5px] font-semibold text-ink-800">{{ __('Disconnect this board') }}</div>
                    <p class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Removes the registered Trello webhook and clears the stored credentials.') }}</p>
                </div>
                <form method="POST" action="{{ route('user.trello.disconnect', $integration->id) }}" onsubmit="return confirm('{{ __('Disconnect Trello?') }}')">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-full border border-accent-coral/40 bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium text-accent-coral whitespace-nowrap">{{ __('Disconnect') }}</button>
                </form>
            </div>
        </main>
    @endif
</x-layouts.user>
