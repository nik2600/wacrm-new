@php
    $phone   = is_array($health['phone'] ?? null) ? $health['phone'] : [];
    $wabaN   = is_array($health['waba'] ?? null) ? $health['waba'] : [];
    $token   = is_array($health['token'] ?? null) ? $health['token'] : [];
    $webhook = is_array($health['webhook'] ?? null) ? $health['webhook'] : [];
    $tpls    = is_array($health['templates'] ?? null) ? $health['templates'] : [];
    $issues  = (array) ($health['issues'] ?? []);
    // Show ONLY hard blockers (red / critical). Amber warnings + neutral notices
    // are noise for the operator, so they're hidden from the Issues list.
    $issues  = array_values(array_filter($issues, fn ($i) => (($i['severity'] ?? '') === 'critical')));
    $errors  = (array) ($health['errors'] ?? []);
    $ids     = (array) ($health['ids'] ?? []);
    $overall = (string) ($health['overall'] ?? 'healthy');

    $title = $phone['verified_name'] ?? ($waba->display_label ?: ($waba->phone_number ?: 'WABA number'));

    // Overall status — drives the first KPI card + the eyebrow live-dot.
    $status = match ($overall) {
        'blocked'   => ['label' => __('Blocked'),   'sub' => __('Sending is blocked or restricted'), 'dot' => 'bg-accent-coral', 'text' => 'text-accent-coral'],
        'attention' => ['label' => __('Attention'), 'sub' => __('Some checks need a look'),           'dot' => 'bg-accent-amber', 'text' => 'text-accent-amber'],
        default     => ['label' => __('Healthy'),   'sub' => __('This number can send messages'),     'dot' => 'bg-wa-green',     'text' => 'text-wa-deep'],
    };

    $sevStyle = fn ($s) => match ($s) {
        'critical' => ['bg-accent-coral/10 border-accent-coral/40', 'bg-accent-coral', 'text-accent-coral'],
        'warning'  => ['bg-accent-amber/10 border-accent-amber/40', 'bg-accent-amber', 'text-accent-amber'],
        default    => ['bg-paper-100 border-paper-200', 'bg-ink-400', 'text-ink-600'],
    };

    // Generic value pill: good / warn / bad / neutral.
    $tone = function ($val, array $good = [], array $warn = []) {
        $u = strtoupper((string) $val);
        if ($u === '') return 'bg-paper-100 text-ink-600 border-paper-200';
        if (in_array($u, array_map('strtoupper', $good), true)) return 'bg-wa-mint text-wa-deep border-wa-green/40';
        if (in_array($u, array_map('strtoupper', $warn), true)) return 'bg-accent-amber/10 text-accent-amber border-accent-amber/40';
        return 'bg-accent-coral/10 text-accent-coral border-accent-coral/40';
    };

    $quality = strtoupper((string) ($phone['quality_rating'] ?? ''));
    $qMeta   = match ($quality) {
        'GREEN'  => ['label' => __('Green'),  'text' => 'text-wa-deep',      'dot' => 'bg-wa-green'],
        'YELLOW' => ['label' => __('Yellow'), 'text' => 'text-accent-amber', 'dot' => 'bg-accent-amber'],
        'RED'    => ['label' => __('Red'),    'text' => 'text-accent-coral', 'dot' => 'bg-accent-coral'],
        default  => ['label' => __('Unrated'),'text' => 'text-ink-500',      'dot' => 'bg-ink-300'],
    };

    $tierRaw = (string) ($phone['messaging_limit_tier'] ?? '');
    $tier    = $tierRaw ? str_replace(['TIER_', 'UNLIMITED'], ['', '∞'], $tierRaw) : '—';
    $thr     = $phone['throughput']['level'] ?? null;

    $tplTotal    = (int) ($tpls['total'] ?? 0);
    $tplApproved = (int) ($tpls['by_status']['APPROVED'] ?? 0);

    $fetched = \Illuminate\Support\Carbon::parse($health['fetched_at'] ?? now());

    // KPI card shell — identical tokens to the operator dashboard stat cards.
    $kpi = 'col-span-12 md:col-span-6 xl:col-span-3 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card relative overflow-hidden';
@endphp

<x-layouts.user :title="__('Account Health')" nav-key="devices" page="user-devices-waba-health">

    {{-- ========== PAGE HEADER (operator-dashboard style) ========== --}}
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-5 md:pt-7 pb-4">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3 mb-2 text-[11px] font-mono uppercase tracking-[0.18em] text-ink-500">
                    <a href="{{ route('user.devices.index') }}" class="hover:text-wa-deep">{{ __('Devices') }}</a>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span>{{ __('Meta (WABA) health') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>{{ __('checked') }} {{ $fetched->diffForHumans() }}</span>
                </div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[32px] md:text-[42px] xl:text-[48px] leading-[1.05] truncate">
                    {{ $title }}
                </h1>
                <p class="text-[13px] text-ink-600 mt-2">
                    <span class="font-mono">{{ $phone['display_phone_number'] ?? ($waba->phone_number ?: '+— unknown') }}</span>
                    · {{ $status['sub'] }} · {{ __('Graph') }} {{ $health['version'] ?? 'v23.0' }}
                </p>
            </div>
            <div class="flex items-center gap-2 mt-2 md:mt-0 flex-wrap">
                <a href="{{ route('user.devices.waba.health', $waba->id) }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2v3h-3" />
                    </svg>
                    {{ __('Re-check') }}
                </a>
                @if (!empty($ids['waba_id']))
                    <a href="https://business.facebook.com/wa/manage/phone-numbers/?waba_id={{ $ids['waba_id'] }}"
                        target="_blank" rel="noopener"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium text-ink-700 hover:bg-paper-50 inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M6 3H3v10h10v-3M9 3h4v4M13 3l-7 7" />
                        </svg>
                        {{ __('Open in Meta') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- ========== VERIFIED BADGE (BLUE TICK) + USERNAME ========== --}}
    @php
        $isOba      = filter_var($phone['is_official_business_account'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $bizVerif   = strtolower((string) ($wabaN['business_verification_status'] ?? ''));
        $nameStatus = strtoupper((string) ($phone['name_status'] ?? ''));
        $pinOn      = filter_var($phone['is_pin_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        // Username we claimed via the Cloud API is stored on the config's meta_json
        // (falls back to whatever the phone-number node reports).
        $wcMeta           = is_array($waba->meta_json) ? $waba->meta_json : [];
        $waUsername       = (string) ($wcMeta['wa_username'] ?? ($phone['username'] ?? ''));
        $waUsernameStatus = strtolower((string) ($wcMeta['wa_username_status'] ?? 'reserved'));
        $unApproved       = $waUsernameStatus === 'approved';
        // This page's top @php block reassigns $errors to $health['errors'] (an
        // array), which shadows Laravel's ViewErrorBag — so the Blade error
        // directive would fatal (getBag on array). Pull the real validation bag
        // straight from the session instead.
        $vErrors          = session('errors') ? session('errors')->getBag('default') : null;
        // Readable prerequisites. Meta grants the badge on brand notability OR a
        // Meta Verified subscription — those two can't be read via the API, so
        // they're shown as static guidance below.
        $checks = [
            ['ok' => $bizVerif === 'verified',   'label' => __('Business verified with Meta')],
            ['ok' => $nameStatus === 'APPROVED', 'label' => __('Display name approved')],
            ['ok' => $quality !== 'RED',         'label' => __('Message quality not low')],
            ['ok' => $pinOn,                     'label' => __('Two-step verification (PIN) on')],
        ];
        $metaMgr = !empty($ids['waba_id'])
            ? 'https://business.facebook.com/wa/manage/phone-numbers/?waba_id=' . $ids['waba_id']
            : 'https://business.facebook.com/wa/manage/';
    @endphp
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-12 gap-3">
            {{-- Verified badge (blue tick) --}}
            <div class="col-span-12 md:col-span-6 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-8 h-8 rounded-full grid place-items-center {{ $isOba ? 'text-[#1DA1F2]' : 'text-ink-300' }}">
                        <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="8" cy="8" r="6.5" fill="currentColor" stroke="none" />
                            <path d="M5 8.2l2 2 4-4.4" stroke="#fff" />
                        </svg>
                    </span>
                    <div>
                        <div class="font-serif text-[19px] leading-tight">{{ __('Verified badge (blue tick)') }}</div>
                        <div class="text-[11px] {{ $isOba ? 'text-[#1DA1F2]' : 'text-ink-500' }}">{{ $isOba ? __('Active — your number shows the verified badge') : __('Not verified yet') }}</div>
                    </div>
                </div>
                <ul class="space-y-1.5 mb-4">
                    @foreach ($checks as $c)
                        <li class="flex items-center gap-2 text-[12.5px] {{ $c['ok'] ? 'text-ink-800' : 'text-ink-500' }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0 {{ $c['ok'] ? 'text-wa-green' : 'text-ink-300' }}" fill="none" stroke="currentColor" stroke-width="2">
                                @if ($c['ok'])<path d="M3 8.5l3 3 7-7" />@else<circle cx="8" cy="8" r="6" />@endif
                            </svg>
                            {{ $c['label'] }}
                        </li>
                    @endforeach
                    <li class="flex items-center gap-2 text-[12.5px] text-ink-500">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0 text-ink-300" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6" /></svg>
                        {{ __('Brand notability (press coverage) OR a Meta Verified subscription') }}
                    </li>
                </ul>
                <div class="text-[11px] text-ink-500 mb-3">{{ __('Meta grants the badge manually — there is no in-app apply. Submit the request from WhatsApp Manager:') }}</div>
                <a href="{{ $metaMgr }}" target="_blank" rel="noopener"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3H3v10h10v-3M9 3h4v4M13 3l-7 7" /></svg>
                    {{ __('Apply on Meta (WhatsApp Manager)') }}
                </a>
            </div>
            {{-- Business username (@handle) --}}
            <div class="col-span-12 md:col-span-6 bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-serif text-[19px] leading-tight mb-1">{{ __('Business username') }}</div>
                <div class="text-[11px] text-ink-500 mb-3">{{ __('A public @handle so customers can find you without your number. Claiming does not hide your phone number.') }}</div>

                @if (session('status'))
                    <div class="mb-3 text-[12px] text-wa-deep bg-wa-mint/60 rounded-lg px-3 py-2">{{ session('status') }}</div>
                @endif
                @if ($vErrors && $vErrors->has('username'))
                    <div class="mb-3 text-[12px] text-accent-coral bg-accent-coral/10 rounded-lg px-3 py-2">{{ $vErrors->first('username') }}</div>
                @endif

                @if ($waUsername !== '')
                    <div class="flex items-center gap-2 mb-3 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[13px] font-mono {{ $unApproved ? 'bg-wa-mint text-wa-deep' : 'bg-accent-amber/15 text-accent-amber' }}">{{ '@' . $waUsername }}</span>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $unApproved ? 'text-wa-deep' : 'text-accent-amber' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $unApproved ? 'bg-wa-green' : 'bg-accent-amber' }}"></span>
                            {{ $unApproved ? __('Approved — visible to customers') : __('Reserved — activates when WhatsApp rolls out usernames') }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route('user.devices.waba.username.delete', $waba->id) }}"
                        onsubmit="return confirm('{{ __('Release this username? You may not be able to reclaim it right away.') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium text-accent-coral hover:bg-accent-coral/10 inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6.5 4V3h3v1M5 4l.5 9h5l.5-9" /></svg>
                            {{ __('Release username') }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('user.devices.waba.username.claim', $waba->id) }}" class="flex items-center gap-2 flex-wrap">
                        @csrf
                        <div class="flex items-center hairline border border-paper-200 rounded-full bg-paper-0 pl-3 pr-1 py-1 focus-within:border-wa-green">
                            <span class="text-ink-400 font-mono text-[14px]">{{ '@' }}</span>
                            <input type="text" name="username" required minlength="3" maxlength="24"
                                pattern="[a-z0-9._]{3,24}" autocapitalize="none" autocomplete="off" spellcheck="false"
                                oninput="this.value=this.value.toLowerCase()"
                                placeholder="{{ __('yourbrand') }}"
                                class="bg-transparent outline-none text-[14px] font-mono px-1.5 py-0.5 w-40" />
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Claim') }}</button>
                    </form>
                    @if (!empty($usernameSuggestions ?? []))
                        <div class="flex items-center gap-1.5 flex-wrap mt-3">
                            <span class="text-[11px] text-ink-500">{{ __('Suggested') }}:</span>
                            @foreach ($usernameSuggestions as $sug)
                                <form method="POST" action="{{ route('user.devices.waba.username.claim', $waba->id) }}" class="inline"
                                    onsubmit="return confirm('{{ __('Claim @:name?', ['name' => $sug]) }}');">
                                    @csrf
                                    <input type="hidden" name="username" value="{{ $sug }}" />
                                    <button type="submit" class="px-2.5 py-1 rounded-full hairline border border-paper-200 bg-paper-0 text-[11.5px] font-mono text-wa-deep hover:bg-wa-mint">{{ '@' . $sug }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                    <div class="text-[11px] text-ink-500 mt-2">{{ __('3–24 characters · lowercase letters, numbers, . and _ · must contain a letter') }}</div>
                @endif
            </div>
        </div>
    </section>

    {{-- ========== WHATSAPP CALLING ========== --}}
    @php $callingOn = (bool) ($waba->calling_enabled ?? false); @endphp
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl p-5 shadow-card flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-8 h-8 rounded-full grid place-items-center shrink-0 {{ $callingOn ? 'text-wa-deep bg-wa-mint/50' : 'text-ink-400 bg-paper-100' }}">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M3.5 3.5a1.5 1.5 0 0 1 1.5-1.5h1.6l1.2 3-1.4 1a8.5 8.5 0 0 0 4.6 4.6l1-1.4 3 1.2v1.6a1.5 1.5 0 0 1-1.5 1.5C7 14 2 9 2 5z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="font-serif text-[19px] leading-tight">{{ __('WhatsApp calling') }}</div>
                    <div class="text-[11px] {{ $callingOn ? 'text-wa-deep' : 'text-ink-500' }}">{{ $callingOn ? __('Enabled — the green Call button now shows on this number\'s chats in the inbox.') : __('Off — turn it on so the Call button appears on chats with this number.') }}</div>
                </div>
            </div>
            <button id="wa-calling-toggle" type="button" data-enabled="{{ $callingOn ? '1' : '0' }}"
                class="px-4 py-2 rounded-full text-[12px] font-semibold shrink-0 {{ $callingOn ? 'hairline border border-paper-200 bg-paper-0 text-accent-coral hover:bg-accent-coral/10' : 'bg-wa-deep text-paper-0 hover:bg-wa-teal' }}">
                {{ $callingOn ? __('Turn off calling') : __('Enable calling') }}
            </button>
        </div>
    </section>
    <script>
        (function () {
            var btn = document.getElementById('wa-calling-toggle');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var enable = btn.dataset.enabled !== '1';
                btn.disabled = true; btn.style.opacity = '0.6';
                fetch(@json(route('user.wa-calling.toggle', $waba->id)), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' },
                    body: JSON.stringify({ enable: enable })
                }).then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }).catch(function () { return { status: r.status, d: null }; }); })
                  .then(function (res) {
                      if (res.d && res.d.ok) { location.reload(); return; }
                      var msg = (res.d && res.d.error) ? res.d.error
                          : (res.status === 403 ? 'Your plan does not include WhatsApp calling.' : 'Could not change calling (status ' + res.status + ').');
                      window.alert(msg);
                      btn.disabled = false; btn.style.opacity = '1';
                  }).catch(function () { btn.disabled = false; btn.style.opacity = '1'; window.alert('Network error — please try again.'); });
            });
        })();
    </script>

    {{-- ========== KPI STATS ROW ========== --}}
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
        <div class="grid grid-cols-12 gap-3">

            {{-- Overall status --}}
            <div class="{{ $kpi }}">
                <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full bg-[repeating-linear-gradient(135deg,rgba(7,94,84,0.05)_0_6px,transparent_6px_12px)] opacity-50"></div>
                <div class="flex items-start justify-between relative">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Overall status') }}</span>
                    <span class="w-2 h-2 rounded-full {{ $status['dot'] }}"></span>
                </div>
                <div class="mt-4 font-serif font-normal tracking-[-0.01em] text-[30px] md:text-[38px] leading-none {{ $status['text'] }}">{{ $status['label'] }}</div>
                <div class="mt-3 text-[11px] text-ink-600">{{ $status['sub'] }}</div>
            </div>

            {{-- Quality rating --}}
            <div class="{{ $kpi }}">
                <div class="flex items-start justify-between relative">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Quality rating') }}</span>
                    <span class="w-2 h-2 rounded-full {{ $qMeta['dot'] }}"></span>
                </div>
                <div class="mt-4 font-serif font-normal tracking-[-0.01em] text-[30px] md:text-[38px] leading-none {{ $qMeta['text'] }}">{{ $qMeta['label'] }}</div>
                <div class="mt-3 text-[11px] text-ink-600">{{ __('Meta message quality (last rolling window)') }}</div>
            </div>

            {{-- Messaging limit --}}
            <div class="{{ $kpi }}">
                <div class="flex items-start justify-between relative">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Messaging limit') }}</span>
                </div>
                <div class="mt-4 flex items-baseline gap-1.5">
                    <span class="font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular-nums">{{ $tier }}</span>
                    @if ($tier !== '—')<span class="text-[12px] text-ink-500 font-mono">/ 24h</span>@endif
                </div>
                <div class="mt-3 text-[11px] text-ink-600">{{ __('Unique customers you can start per day') }}{{ $thr ? ' · ' . $thr : '' }}</div>
            </div>

            {{-- Free conversations this month — Meta's free monthly allowance --}}
            @php $conv = $health['conversations'] ?? null; @endphp
            @if ($conv)
            <div class="{{ $kpi }}">
                <div class="flex items-start justify-between relative">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Free this month') }}</span>
                </div>
                <div class="mt-4 flex items-baseline gap-1.5">
                    <span class="font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular-nums">{{ number_format($conv['free_left']) }}</span>
                    <span class="text-[12px] text-ink-500 font-mono">/ {{ number_format($conv['free_total']) }}</span>
                </div>
                <div class="mt-3 text-[11px] text-ink-600">{{ __('Free service conversations left') }} · {{ number_format($conv['free_used']) }} {{ __('used') }}@if (($conv['paid'] ?? 0) > 0) · {{ number_format($conv['paid']) }} {{ __('paid') }}@endif</div>
            </div>
            @endif

            {{-- Templates --}}
            <div class="{{ $kpi }}">
                <div class="flex items-start justify-between relative">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Message templates') }}</span>
                </div>
                <div class="mt-4 font-serif font-normal tracking-[-0.01em] text-[40px] md:text-[52px] leading-none tabular-nums">{{ $tplTotal }}</div>
                <div class="mt-3 flex items-center gap-3 text-[11px] text-ink-600">
                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ $tplApproved }} {{ __('approved') }}</span>
                    @if ($tplTotal - $tplApproved > 0)
                        <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>{{ $tplTotal - $tplApproved }} {{ __('other') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-8 space-y-4">

        {{-- Issues / blocks --}}
        @if (!empty($issues))
            <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-3 border-b border-paper-200 font-mono text-[10.5px] uppercase tracking-[0.14em] text-ink-500">
                    {{ __('Issues & blocks') }} ({{ count($issues) }})
                </div>
                <div class="divide-y divide-paper-100">
                    @foreach ($issues as $iss)
                        @php [$ibg, $idot, $itext] = $sevStyle($iss['severity']); @endphp
                        <div class="px-5 py-3.5 flex items-start gap-3">
                            <span class="mt-1.5 w-2 h-2 rounded-full {{ $idot }} shrink-0"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-[12.5px] text-ink-900">{{ $iss['title'] }}</span>
                                    <span class="px-1.5 py-0.5 rounded border text-[9.5px] font-mono uppercase {{ $ibg }} {{ $itext }}">{{ $iss['area'] }}</span>
                                    @if (!empty($iss['code']))
                                        <span class="font-mono text-[10px] text-ink-400">#{{ $iss['code'] }}</span>
                                    @endif
                                </div>
                                @if (!empty($iss['detail']))
                                    <div class="text-[11.5px] text-ink-600 mt-0.5">{{ $iss['detail'] }}</div>
                                @endif
                                @if (!empty($iss['solution']))
                                    <div class="text-[11.5px] text-wa-deep mt-1">
                                        <span class="font-semibold">{{ __('Fix') }}:</span> {{ $iss['solution'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Detail grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Number status --}}
            <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-serif text-[17px] mb-3">{{ __('Phone number') }}</div>
                <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[12px]">
                    <dt class="text-ink-500">{{ __('Verified name') }}</dt>
                    <dd class="text-right text-ink-800 truncate min-w-0">{{ $phone['verified_name'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Connection') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $tone($phone['status'] ?? '', ['CONNECTED']) }}">
                            {{ $phone['status'] ?? '—' }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Quality rating') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $tone($quality, ['GREEN'], ['YELLOW']) }}">
                            {{ $quality ?: __('Unrated') }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Messaging limit') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $tier !== '—' ? $tier . ' / 24h' : '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Throughput') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $thr ?: '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Number verification') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $tone($phone['code_verification_status'] ?? '', ['VERIFIED']) }}">
                            {{ $phone['code_verification_status'] ?? '—' }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Display name status') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $tone($phone['name_status'] ?? '', ['APPROVED','AVAILABLE_WITHOUT_REVIEW'], ['PENDING_REVIEW']) }}">
                            {{ $phone['name_status'] ?? '—' }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Account mode') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $phone['account_mode'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Platform') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $phone['platform_type'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Official business') }}</dt>
                    <dd class="text-right text-ink-800">{{ ($phone['is_official_business_account'] ?? false) ? __('Yes (blue tick)') : __('No') }}</dd>

                    <dt class="text-ink-500">{{ __('Two-step PIN') }}</dt>
                    <dd class="text-right text-ink-800">{{ ($phone['is_pin_enabled'] ?? false) ? __('Enabled') : __('Off') }}</dd>
                </dl>
            </div>

            {{-- WABA account --}}
            <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-serif text-[17px] mb-3">{{ __('Business account') }}</div>
                <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[12px]">
                    <dt class="text-ink-500">{{ __('WABA name') }}</dt>
                    <dd class="text-right text-ink-800 truncate min-w-0">{{ $wabaN['name'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Account review') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $tone($wabaN['account_review_status'] ?? '', ['APPROVED'], ['PENDING']) }}">
                            {{ $wabaN['account_review_status'] ?? '—' }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Business verification') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $tone($wabaN['business_verification_status'] ?? '', ['VERIFIED'], ['PENDING']) }}">
                            {{ $wabaN['business_verification_status'] ?? '—' }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Owner business') }}</dt>
                    <dd class="text-right text-ink-800 truncate min-w-0">{{ $wabaN['owner_business_info']['name'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Currency') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $wabaN['currency'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Timezone') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $wabaN['timezone_id'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Country') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $wabaN['country'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('Ownership') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $wabaN['ownership_type'] ?? '—' }}</dd>
                </dl>
            </div>

            {{-- Permissions / token --}}
            <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-serif text-[17px] mb-3">{{ __('Access & permissions') }}</div>
                <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[12px]">
                    <dt class="text-ink-500">{{ __('Token valid') }}</dt>
                    <dd class="text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ ($token['is_valid'] ?? false) ? 'bg-wa-mint text-wa-deep border-wa-green/40' : 'bg-accent-coral/10 text-accent-coral border-accent-coral/40' }}">
                            {{ ($token['is_valid'] ?? false) ? __('Valid') : __('Invalid') }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Token type') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $token['type'] ?? '—' }}</dd>

                    <dt class="text-ink-500">{{ __('App') }}</dt>
                    <dd class="text-right text-ink-800 truncate min-w-0">{{ $token['application'] ?? ($token['app_id'] ?? '—') }}</dd>

                    <dt class="text-ink-500">{{ __('Expires') }}</dt>
                    <dd class="text-right font-mono text-ink-800">
                        @if ($token['expires_never'] ?? false)
                            {{ __('Never (permanent)') }}
                        @elseif (!empty($token['expires_at']))
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($token['expires_at'])->format('M j, Y') }}
                        @else
                            —
                        @endif
                    </dd>
                </dl>

                {{-- The number's actual Meta access token. Masked by default; a
                     native <details> reveals the full value (no JS). The reveal
                     block is `select-all` so one click + Ctrl+C copies it. --}}
                @php
                    $accessToken = (string) ($accessToken ?? '');
                    $atMasked = $accessToken === '' ? ''
                        : (strlen($accessToken) > 22
                            ? substr($accessToken, 0, 12) . '…' . substr($accessToken, -6)
                            : $accessToken);
                @endphp
                <div class="mt-3 pt-3 border-t border-paper-100">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <div class="text-ink-500 text-[11px]">{{ __('Access token') }}</div>
                        @if ($accessToken !== '')
                            <span class="text-[10px] font-mono text-ink-400">{{ strlen($accessToken) }} {{ __('chars') }}</span>
                        @endif
                    </div>
                    @if ($accessToken !== '')
                        <div class="font-mono text-[11px] text-ink-700 break-all bg-paper-50 border border-paper-200 rounded-lg px-2.5 py-2">{{ $atMasked }}</div>
                        <details class="mt-1.5">
                            <summary class="cursor-pointer text-[11px] text-wa-deep font-semibold select-none inline-flex items-center gap-1">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8Z"/><circle cx="8" cy="8" r="1.8"/></svg>
                                {{ __('Reveal full token') }}
                            </summary>
                            <div class="mt-1.5 font-mono text-[11px] text-ink-800 break-all bg-paper-50 border border-paper-200 rounded-lg px-2.5 py-2 select-all">{{ $accessToken }}</div>
                            <p class="mt-1 text-[10px] text-ink-400 leading-snug">{{ __('Click the token to select it, then copy. Treat it like a password — anyone with it can send from this number.') }}</p>
                        </details>
                    @else
                        <div class="font-mono text-[11px] text-accent-coral">{{ __('No token stored — template sync + sending will fail. Paste this number\'s access token below.') }}</div>
                    @endif

                    @if ($accessToken !== '')
                        {{-- One-click FIX: exchange the current token with Meta
                             (fb_exchange_token) for a fresh long-lived one and save it
                             IN PLACE — repairs an invalid/expiring token WITHOUT
                             reconnecting the device. Sending keeps working. --}}
                        <form method="POST" action="{{ route('user.devices.waba.token-refresh', $waba->id) }}" class="mt-2"
                              onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='{{ __('Renewing with Meta…') }}';">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal disabled:opacity-60">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9M13.5 2.5V5H11"/></svg>
                                {{ __('Fix / renew token') }}
                            </button>
                            <span class="ml-2 text-[10px] text-ink-400">{{ __('Renews with Meta in place — no reconnect needed.') }}</span>
                            @if ($vErrors && $vErrors->has('token'))<div class="mt-1 text-[10.5px] text-accent-coral">{{ $vErrors->first('token') }}</div>@endif
                        </form>
                    @endif

                    {{-- Paste / replace the access token directly — a fast fix when a
                         connect left it empty, without re-running embedded signup.
                         Verified against Meta before it's saved. Opens automatically
                         when there's no token yet. --}}
                    <details class="mt-2" @if ($accessToken === '') open @endif>
                        <summary class="cursor-pointer text-[11px] text-wa-deep font-semibold select-none inline-flex items-center gap-1">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v10M3 8h10"/></svg>
                            {{ $accessToken === '' ? __('Add access token') : __('Replace access token') }}
                        </summary>
                        <form method="POST" action="{{ route('user.devices.waba.set-token', $waba->id) }}" class="mt-2 space-y-2">
                            @csrf
                            <input type="password" name="access_token" required autocomplete="new-password"
                                placeholder="EAAG… (System-User or Business access token)"
                                class="w-full px-2.5 py-2 border border-paper-200 rounded-lg bg-white text-[11px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            @if ($vErrors && $vErrors->has('access_token'))<div class="text-[10.5px] text-accent-coral">{{ $vErrors->first('access_token') }}</div>@endif
                            <div class="flex items-center gap-2">
                                <button type="submit" class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal">{{ __('Verify & save') }}</button>
                                <span class="text-[10px] text-ink-400">{{ __('Checked against Meta before saving.') }}</span>
                            </div>
                        </form>
                    </details>
                </div>

                <div class="mt-3 pt-3 border-t border-paper-100">
                    <div class="text-ink-500 text-[11px] mb-1.5">{{ __('Granted permissions') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse (($token['scopes'] ?? []) as $sc)
                            <span class="px-2 py-0.5 rounded-full bg-paper-100 border border-paper-200 text-[10.5px] font-mono text-ink-700">{{ $sc }}</span>
                        @empty
                            <span class="text-[11.5px] text-ink-400">{{ __('No permissions reported') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Webhook + templates + IDs --}}
            <div class="bg-paper-0 hairline border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-serif text-[17px] mb-3">{{ __('Delivery & content') }}</div>
                <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[12px]">
                    <dt class="text-ink-500">{{ __('Webhook subscribed') }}</dt>
                    <dd class="text-right">
                        @php $sub = $webhook['subscribed'] ?? null; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10.5px] font-mono uppercase {{ $sub === true ? 'bg-wa-mint text-wa-deep border-wa-green/40' : ($sub === false ? 'bg-accent-coral/10 text-accent-coral border-accent-coral/40' : 'bg-paper-100 text-ink-600 border-paper-200') }}">
                            {{ $sub === true ? __('Yes') : ($sub === false ? __('No') : '—') }}
                        </span>
                    </dd>

                    <dt class="text-ink-500">{{ __('Inbound to this platform') }}</dt>
                    <dd class="text-right">
                        @include('user.devices._inbound_badge', ['wired' => (is_array($waba->meta_json ?? null) ? ($waba->meta_json['inbound_wired'] ?? null) : null)])
                    </dd>

                    <dt class="text-ink-500">{{ __('Templates total') }}</dt>
                    <dd class="text-right font-mono text-ink-800">{{ $tpls['total'] ?? '—' }}</dd>
                </dl>

                {{-- Fix inbound = re-apply the webhook override + verify that incoming
                     messages for this number are routed to this platform. --}}
                <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/resubscribe') }}"
                    class="mt-4" data-confirm="{{ __('Re-check & fix inbound for this number? No re-login needed.') }}">
                    @csrf
                    <button type="submit"
                        class="w-full px-3 py-2 rounded-xl border border-paper-200 hover:border-wa-deep text-[12px] font-semibold text-ink-700 inline-flex items-center justify-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5v3h-3M2.5 12.5v-3h3" /><path d="M12.4 6a4.5 4.5 0 0 0-8.2-.8M3.6 10a4.5 4.5 0 0 0 8.2.8" /></svg>
                        {{ __('Fix inbound — re-subscribe & verify') }}
                    </button>
                </form>

                {{-- Sync chat history = manual fallback for the Coexistence 6-month
                     history import. Meta has no history-PULL API — it only PUSHES
                     history once after a Coexistence onboard. Re-subscribing is the
                     only lever that can prompt that push again. --}}
                <form method="POST" action="{{ url('/devices/waba/' . $waba->id . '/sync-history') }}"
                    class="mt-2" data-confirm="{{ __('Request a chat-history sync from Meta? For Coexistence numbers Meta re-pushes the last ~6 months into the Unified Inbox.') }}">
                    @csrf
                    <button type="submit"
                        class="w-full px-3 py-2 rounded-xl border border-paper-200 hover:border-wa-deep text-[12px] font-semibold text-ink-700 inline-flex items-center justify-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 8a6 6 0 0 1 10.2-4.3L14 5.5M14 8a6 6 0 0 1-10.2 4.3L2 10.5" /><path d="M11 2.5v3h3M5 13.5v-3H2" /><circle cx="8" cy="8" r="1.5" /></svg>
                        {{ __('Sync chat history (Coexistence)') }}
                    </button>
                </form>
                <p class="mt-1.5 text-[10.5px] text-ink-500 leading-snug">
                    {{ __('Only Coexistence numbers have history to sync — Meta pushes ~6 months once after connecting. A standard Cloud API number has none.') }}
                </p>

                @if (!empty($tpls['by_status']))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($tpls['by_status'] as $st => $n)
                            <span class="px-2 py-0.5 rounded-full border text-[10.5px] font-mono {{ $tone($st, ['APPROVED'], ['PENDING','PAUSED','IN_APPEAL']) }}">
                                {{ $st }} · {{ $n }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 pt-3 border-t border-paper-100">
                    <dl class="grid grid-cols-[120px_1fr] gap-x-3 gap-y-1.5 text-[11px]">
                        <dt class="text-ink-500 font-mono uppercase tracking-wide text-[9.5px] pt-0.5">{{ __('Phone ID') }}</dt>
                        <dd class="text-right font-mono text-ink-700 break-all">{{ $ids['phone_number_id'] ?: '—' }}</dd>
                        <dt class="text-ink-500 font-mono uppercase tracking-wide text-[9.5px] pt-0.5">{{ __('WABA ID') }}</dt>
                        <dd class="text-right font-mono text-ink-700 break-all">{{ $ids['waba_id'] ?: '—' }}</dd>
                        <dt class="text-ink-500 font-mono uppercase tracking-wide text-[9.5px] pt-0.5">{{ __('Business ID') }}</dt>
                        <dd class="text-right font-mono text-ink-700 break-all">{{ $ids['business_id'] ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Fetch errors (a Graph node we couldn't read) --}}
        @if (!empty($errors))
            <div class="bg-paper-0 hairline border border-accent-amber/40 rounded-2xl shadow-card p-5">
                <div class="font-mono text-[10.5px] uppercase tracking-[0.14em] text-accent-amber mb-2">
                    {{ __('Could not read some data from Meta') }}
                </div>
                <ul class="space-y-1.5 text-[11.5px] text-ink-600 list-disc pl-4">
                    @foreach ($errors as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

    </section>
</x-layouts.user>
