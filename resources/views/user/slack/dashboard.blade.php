<x-layouts.user :title="__('Slack')" nav-key="more" page="user-slack-dashboard">
    @php $isConnected = $integration && $integration->isConnected(); @endphp

    <style>
        .sl-accent { color: #4A154B; }
        .sl-bg { background-color: #4A154B; }
        .sl-bg:hover { background-color: #3A1039; }
        .sl-soft { background-color: #F4F0F7; }
    </style>

    @php
        $slackIcon = '<svg viewBox="0 0 24 24" class="w-7 h-7"><path fill="#36C5F0" d="M5.04 15.17a2.52 2.52 0 1 1-2.52-2.52h2.52v2.52zM6.3 15.17a2.52 2.52 0 0 1 5.04 0v6.31a2.52 2.52 0 0 1-5.04 0v-6.31z"/><path fill="#2EB67D" d="M8.83 5.04a2.52 2.52 0 1 1 2.52-2.52v2.52H8.83zM8.83 6.3a2.52 2.52 0 0 1 0 5.04H2.52a2.52 2.52 0 0 1 0-5.04h6.31z"/><path fill="#ECB22E" d="M18.96 8.83a2.52 2.52 0 1 1 2.52 2.52h-2.52V8.83zM17.7 8.83a2.52 2.52 0 0 1-5.04 0V2.52a2.52 2.52 0 0 1 5.04 0v6.31z"/><path fill="#E01E5A" d="M15.17 18.96a2.52 2.52 0 1 1-2.52 2.52v-2.52h2.52zM15.17 17.7a2.52 2.52 0 0 1 0-5.04h6.31a2.52 2.52 0 0 1 0 5.04h-6.31z"/></svg>';
        $cmd = $integration->slash_command ?? '/wa';
    @endphp

    @if (!$isConnected)
        {{-- ═══════════════ NOT CONNECTED ═══════════════ --}}
        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <aside class="space-y-3">
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                        <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center sl-soft">{!! $slackIcon !!}</div>
                        <div class="font-serif text-[18px] leading-tight">{{ __('Slack') }}</div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">{{ __('Integration') }}</div>
                        <div class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-paper-50 border border-paper-200 text-ink-700"><span class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>{{ __('Not connected') }}</div>
                    </div>
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ __('Setup steps') }}</div>
                        <ol class="px-1 space-y-0.5">
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $enabled ? 'text-ink-700' : 'text-ink-500' }}"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $enabled ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $enabled ? '✓' : '1' }}</span>{{ __('Admin enables Slack') }}</li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] {{ $enabled ? 'bg-wa-deep/8 text-wa-deep font-semibold' : 'text-ink-500' }}"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono {{ $enabled ? 'text-paper-0' : 'bg-paper-100 text-ink-500' }}" @style(['background:#4A154B' => $enabled])>2</span>{{ __('Create a Slack app + slash command') }}</li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">3</span>{{ __('Paste Bot Token + Signing Secret') }}</li>
                            <li class="flex items-center gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-500"><span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-500">4</span>{{ __('Send WhatsApp messages from Slack') }}</li>
                        </ol>
                    </div>
                    <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-wa-green"></span>{{ __('Need help?') }}</div>
                        {{ __('Every workspace makes its own Slack app — about 5 minutes.') }} <a href="{{ url('/support') }}" class="text-wa-deep font-semibold underline">{{ __('Contact support') }}</a>.
                    </div>
                </aside>

                <section class="space-y-5">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2"><a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a><span class="mx-1.5 text-ink-500/60">/</span><span>{{ __('Slack') }}</span></div>
                            <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">{{ __('Connect') }} <span class="italic sl-accent">{{ __('Slack') }}</span></h1>
                            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Type a slash command in Slack to send a WhatsApp message to one of your contacts — no switching apps.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ url('/integrations') }}" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4" /></svg>{{ __('Back') }}</a>
                        </div>
                    </div>

                    @if (session('error') || $errors->any())
                        <div class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F] inline-flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 5v3M8 11v.01" /><circle cx="8" cy="8" r="6" /></svg>{{ session('error') ?: $errors->first() }}</div>
                    @endif

                    @if (!$enabled)
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
                            <div class="w-12 h-12 rounded-xl bg-accent-amber/20 grid place-items-center shrink-0"><svg viewBox="0 0 24 24" class="w-6 h-6 text-accent-amber" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 9v3M12 16h.01" /><circle cx="12" cy="12" r="9" /></svg></div>
                            <div class="flex-1 min-w-0"><div class="font-serif text-[22px] leading-tight">{{ __("Slack isn't enabled yet") }}</div><p class="text-[12.5px] text-ink-600 mt-1.5 max-w-2xl">{{ __('An administrator must enable the Slack integration before your workspace can connect.') }}</p></div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-[1fr_330px] gap-5 items-start">
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 3') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-0.5 mb-4">{{ __('App credentials') }}</h2>
                                <form method="POST" action="{{ route('user.slack.connect') }}" class="space-y-4">
                                    @csrf
                                    <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Bot User OAuth Token') }} <span class="text-accent-coral">*</span></label><input type="password" name="bot_token" required autocomplete="new-password" placeholder="xoxb-…" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                    <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Signing Secret') }} <span class="text-accent-coral">*</span></label><input type="password" name="signing_secret" required autocomplete="new-password" placeholder="{{ __('from Basic Information') }}" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                    <div><label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Slash command') }} <span class="text-ink-500 font-normal">({{ __('optional') }})</span></label><input name="slash_command" maxlength="32" value="/wa" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"></div>
                                    @if ($senders->isNotEmpty())
                                        <div>
                                            <label class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Send from') }} <span class="text-ink-500 font-normal">({{ __('which WhatsApp number') }})</span></label>
                                            <select name="send_from" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                                <option value="">{{ __('Workspace default') }}</option>
                                                @foreach ($senders as $s)
                                                    <option value="{{ $s['key'] }}" @selected($currentSender === $s['key'])>{{ $s['label'] }} · {{ $s['engine'] }}{{ $s['phone'] ? ' · +' . $s['phone'] : '' }}</option>
                                                @endforeach
                                            </select>
                                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('The connected number every command sends through. You run more than one — pick the right one.') }}</div>
                                        </div>
                                    @endif
                                    <div class="rounded-lg bg-paper-50/60 border border-paper-200 p-3"><div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">{{ __('Slash command Request URL · paste into Slack') }}</div><div class="font-mono text-[11px] text-ink-700 break-all">{{ $commandUrl }}</div></div>
                                    <div class="flex items-center gap-2 pt-2"><button type="submit" class="px-4 py-2 rounded-full sl-bg text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 ml-auto"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 8l5 5 7-9" /></svg>{{ __('Connect Slack') }}</button></div>
                                </form>
                            </div>
                            <aside class="space-y-4">
                                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('How to create the app') }}</div>
                                    <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">{{ __('At api.slack.com/apps') }}</h3>
                                    <ol class="space-y-3 text-[12.5px] text-ink-700">
                                        @foreach ([__('Create New App → From scratch.'), __('OAuth & Permissions → add scopes commands and chat:write.'), __('Slash Commands → Create /wa with the Request URL.'), __('Install to Workspace → copy the Bot Token (xoxb-…).'), __('Basic Information → copy the Signing Secret.')] as $i => $step)
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
        @endphp

        @if (session('status'))
            <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-4">
                <div class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 8 3 3 5-6" /></svg>{{ session('status') }}</div>
            </div>
        @endif

        <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            {{-- Breadcrumb --}}
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500"><a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a><span class="mx-1.5 text-ink-500/60">/</span><span class="text-ink-700">{{ __('Slack') }}</span></div>

            {{-- HERO BANNER --}}
            <div class="rounded-2xl border border-paper-200 shadow-card overflow-hidden">
                <div class="px-6 py-5 flex flex-wrap items-center gap-4 bg-paper-50">
                    <span class="w-14 h-14 rounded-2xl bg-paper-0 border border-paper-200 grid place-items-center shrink-0 shadow-card">{!! $slackIcon !!}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h1 class="font-serif text-[26px] sm:text-[30px] leading-none truncate text-ink-900">{{ $integration->team_name ?: __('Slack workspace') }}</h1>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40"><span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Connected') }}</span>
                        </div>
                        <div class="text-[12.5px] text-ink-600 mt-1.5">{{ __('Send WhatsApp messages from Slack with') }} <span class="font-mono sl-accent font-semibold">{{ $cmd }}</span></div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ url('/integrations') }}" class="px-3.5 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All integrations') }}</a>
                        <a href="#settings" class="px-4 py-2 rounded-full sl-bg text-paper-0 text-[12px] font-semibold">{{ __('Manage') }}</a>
                    </div>
                </div>
            </div>

            {{-- KPI ROW --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Commands') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums">{{ number_format($total) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ __('recent total') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivered') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums text-wa-deep">{{ number_format($okCount) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ __('sent to WhatsApp') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</div>
                    <div class="font-serif text-[30px] leading-none mt-2 tabular-nums {{ $failCount ? 'text-accent-coral' : 'text-ink-400' }}">{{ number_format($failCount) }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ __('no contact / error') }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Last active') }}</div>
                    <div class="font-serif text-[19px] leading-tight mt-2.5">{{ $lastLog ? $lastLog->created_at->diffForHumans(null, true) : '—' }}</div>
                    <div class="text-[11px] text-ink-500 mt-1">{{ $lastLog ? __('ago') : __('no commands yet') }}</div>
                </div>
            </div>

            {{-- ACTIVITY + USAGE --}}
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-4 items-start">

                {{-- Activity table --}}
                <div id="activity" class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-paper-100 flex items-center justify-between">
                        <h3 class="font-serif text-[18px] leading-tight">{{ __('Recent activity') }}</h3>
                        <span class="font-mono text-[10px] px-2 py-0.5 rounded-full bg-paper-100 text-ink-600">{{ $total }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 text-left font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                <tr><th class="px-5 py-2">{{ __('Command') }}</th><th class="px-4 py-2 whitespace-nowrap">{{ __('When') }}</th><th class="px-4 py-2 text-right">{{ __('Status') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-paper-100">
                                @forelse ($logs as $log)
                                    <tr class="hover:bg-paper-50">
                                        <td class="px-5 py-2.5 text-ink-700"><span class="block truncate max-w-[420px]">{{ $log->detail ?: $log->event }}</span></td>
                                        <td class="px-4 py-2.5 font-mono text-[11px] text-ink-500 whitespace-nowrap">{{ $log->created_at?->diffForHumans() }}</td>
                                        <td class="px-4 py-2.5 text-right"><span class="font-mono text-[10px] uppercase px-2 py-0.5 rounded-full {{ $log->status === 'ok' ? 'bg-wa-green/15 text-wa-deep' : 'bg-accent-coral/15 text-accent-coral' }}">{{ $log->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-10 text-center text-[12px] text-ink-500">{{ __('No commands run yet. Type /wa in Slack to get started.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Usage rail --}}
                <aside class="space-y-4">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <h3 class="font-serif text-[18px] leading-tight mb-2">{{ __('How a teammate uses it') }}</h3>
                        <p class="text-[12px] text-ink-600">{{ __('In any Slack channel, type:') }}</p>
                        <div class="mt-2 font-mono text-[12px] bg-paper-50 border border-paper-200 rounded-lg px-3 py-2.5 leading-relaxed break-words"><span class="sl-accent font-semibold">{{ $cmd }}</span> send Rahul: your order is ready</div>
                        <p class="text-[11.5px] text-ink-500 mt-2.5">{{ __('We match the name to a WhatsApp contact (or use a phone number) and send via your connected device.') }}</p>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px] mb-1.5">{{ __('Good to know') }}</div>
                        <ul class="text-[11.5px] text-ink-700 space-y-1.5">
                            <li class="flex gap-2"><span class="sl-accent">•</span>{{ __('Works in any channel or DM in your Slack.') }}</li>
                            <li class="flex gap-2"><span class="sl-accent">•</span>{{ __('Use a phone number when the name is ambiguous.') }}</li>
                            <li class="flex gap-2"><span class="sl-accent">•</span>{{ __('Every command is signature-verified before sending.') }}</li>
                        </ul>
                    </div>
                </aside>
            </div>

            {{-- HOW IT WORKS — end-to-end explainer + usage cheatsheet --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Reference') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('How it works') }}</h3>
                    </div>
                    <span class="font-mono text-[11px] sl-accent font-semibold">{{ $cmd }}</span>
                </div>

                {{-- 4-step flow --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ([
                        ['1', __('Type in Slack'), __('A teammate types the slash command in any channel or DM — no need to open WaDesk.')],
                        ['2', __('We verify it'), __('WaDesk checks the request signature with your Signing Secret, so only your Slack can send.')],
                        ['3', __('Match the contact'), __('The name is matched to a WhatsApp contact — or a phone number is used as-is.')],
                        ['4', __('Send + confirm'), __('The message goes out from your connected WhatsApp device and Slack shows the result.')],
                    ] as [$n, $t, $d])
                        <div class="rounded-xl border border-paper-200 bg-paper-50 p-3.5">
                            <div class="w-6 h-6 rounded-full grid place-items-center font-mono text-[11px] text-paper-0 mb-2 sl-bg">{{ $n }}</div>
                            <div class="text-[12.5px] font-semibold text-ink-900">{{ $t }}</div>
                            <p class="text-[11.5px] text-ink-600 mt-1 leading-relaxed">{{ $d }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Commands + prerequisites --}}
                <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-paper-200 p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Commands to use') }}</div>
                        <div class="space-y-2 text-[12px]">
                            <div class="font-mono bg-paper-50 border border-paper-200 rounded-lg px-3 py-2 break-words"><span class="sl-accent font-semibold">{{ $cmd }}</span> send Rahul: your order is ready</div>
                            <div class="font-mono bg-paper-50 border border-paper-200 rounded-lg px-3 py-2 break-words"><span class="sl-accent font-semibold">{{ $cmd }}</span> send 919812345678: on my way</div>
                            <div class="font-mono bg-paper-50 border border-paper-200 rounded-lg px-3 py-2 break-words"><span class="sl-accent font-semibold">{{ $cmd }}</span> help</div>
                        </div>
                        <p class="text-[11px] text-ink-500 mt-2">{{ __('Format') }}: <span class="font-mono">{{ $cmd }} send &lt;name or number&gt;: &lt;message&gt;</span></p>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-4">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Before it can send') }}</div>
                        <ul class="text-[12px] text-ink-700 space-y-2">
                            <li class="flex gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 8 3 3 6-7"/></svg>{{ __('The Slack slash command points its Request URL at the address below.') }}</li>
                            <li class="flex gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 8 3 3 6-7"/></svg>{{ __('The Slack app has the scopes') }} <span class="font-mono">commands</span> + <span class="font-mono">chat:write</span>.</li>
                            <li class="flex gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 8 3 3 6-7"/></svg>{{ __('A WhatsApp device is connected in WaDesk to actually deliver the message.') }}</li>
                        </ul>
                        <div class="mt-3 rounded-lg bg-paper-50 border border-paper-200 p-2.5">
                            <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-1">{{ __('Slash command Request URL') }}</div>
                            <div class="font-mono text-[11px] text-ink-700 break-all">{{ $commandUrl }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SENDING DEVICE — which connected number /wa sends through --}}
            @if ($senders->isNotEmpty())
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start gap-3">
                        <span class="w-9 h-9 rounded-xl sl-soft grid place-items-center shrink-0 sl-accent">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="1.5" width="8" height="13" rx="2"/><path d="M7 12.5h2"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-serif text-[18px] leading-tight">{{ __('Sending device') }}</h3>
                            <p class="text-[12px] text-ink-600 mt-1 max-w-xl">{{ __('You run more than one WhatsApp number. Pick which connected number every command sends through — otherwise it uses the workspace default.') }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('user.slack.settings', $integration->id) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="flex-1 min-w-[260px]">
                            <label class="block text-[11.5px] font-semibold text-ink-700 mb-1.5">{{ __('Send from') }}</label>
                            <select name="send_from" class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Workspace default') }}</option>
                                @foreach ($senders as $s)
                                    <option value="{{ $s['key'] }}" @selected($currentSender === $s['key'])>{{ $s['label'] }} · {{ $s['engine'] }}{{ $s['phone'] ? ' · +' . $s['phone'] : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-full sl-bg text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                    </form>
                </div>
            @endif

            {{-- SETTINGS --}}
            <div id="settings" class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h3 class="font-serif text-[18px] leading-tight mb-3">{{ __('Connection') }}</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[12.5px]">
                    <div><dt class="font-mono text-[10px] uppercase text-ink-500">{{ __('Workspace') }}</dt><dd class="text-ink-800 mt-0.5">{{ $integration->team_name ?: '—' }}</dd></div>
                    <div><dt class="font-mono text-[10px] uppercase text-ink-500">{{ __('Slash command') }}</dt><dd class="font-mono text-ink-800 mt-0.5">{{ $cmd }}</dd></div>
                    <div><dt class="font-mono text-[10px] uppercase text-ink-500">{{ __('Connected') }}</dt><dd class="text-ink-800 mt-0.5">{{ optional($integration->connected_at)->diffForHumans() ?? '—' }}</dd></div>
                    <div class="sm:col-span-3"><dt class="font-mono text-[10px] uppercase text-ink-500">{{ __('Request URL') }}</dt><dd class="font-mono text-[11px] text-ink-700 mt-0.5 break-all">{{ $commandUrl }}</dd></div>
                </dl>
                <div class="mt-4 pt-4 border-t border-paper-100 flex items-center justify-between gap-3">
                    <p class="text-[11.5px] text-ink-500">{{ __('Disconnecting removes the stored token + secret.') }}</p>
                    <form method="POST" action="{{ route('user.slack.disconnect', $integration->id) }}" onsubmit="return confirm('{{ __('Disconnect Slack?') }}')">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-full border border-accent-coral/40 bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium text-accent-coral whitespace-nowrap">{{ __('Disconnect Slack') }}</button>
                    </form>
                </div>
            </div>
        </main>
    @endif
</x-layouts.user>
