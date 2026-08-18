@php
    /** @var array $providerAllowed */
    /** @var \App\Models\WaProviderConfig|null $providerConfig */
    $allowed = $providerAllowed ?? ['waba', 'baileys', 'twilio'];
    $cfg = $providerConfig ?? null;
    $current = $cfg?->provider;

    $appIdSet = (bool) \App\Models\SystemSetting::get('waba_app_id', '');
    $appConfigIdSet = (bool) \App\Models\SystemSetting::get('waba_config_id', '');
    $sharedNodeUrl = (string) \App\Models\SystemSetting::get('baileys_server_url', env('SERVER_URL', ''));
    $sharedTwilioSid = (string) \App\Models\SystemSetting::get('twilio_account_sid', '');

    $cards = [
        'waba' => [
            'title' => 'Official WABA',
            'subtitle' => 'Meta Cloud · catalog · in-chat orders',
            'iconBg' => 'bg-wa-mint',
            'iconFg' => 'text-wa-deep',
            'icon' =>
                '<svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M5.5 8.5l2 2 3-4"/></svg>',
        ],
        'baileys' => [
            'title' => 'Unofficial API · QR',
            'subtitle' => 'Pair your phone (free, no Meta verification)',
            'iconBg' => 'bg-[#D9E5F2]',
            'iconFg' => 'text-[#13478A]',
            'icon' =>
                '<svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h4v4H3zM9 3h4v4H9zM3 9h4v4H3zM9 9h2v2h-2zM13 9v2M9 13h2"/></svg>',
        ],
        'twilio' => [
            'title' => 'Twilio',
            'subtitle' => 'Sandbox or paid · Account SID + token',
            'iconBg' => 'bg-accent-amber/20',
            'iconFg' => 'text-[#7B5A14]',
            'icon' =>
                '<svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 5l5-3 5 3v6l-5 3-5-3z"/><circle cx="8" cy="8" r="1.5"/></svg>',
        ],
    ];
@endphp

<section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sending providers') }}
            </div>
            <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Connect WhatsApp to this workspace') }}</h2>
            <p class="text-[12px] text-ink-500 mt-1">
                {{ __('Your messages — chat, broadcasts, scheduled, auto-reply, store — all flow through whichever provider you connect here. Pick one.') }}
            </p>
        </div>
        @if ($cfg && $cfg->isConnected())
            <span
                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40"><span
                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected via
                {{ ucfirst($cfg->provider) }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 p-4">
        @foreach (['waba', 'baileys', 'twilio'] as $key)
            @php
                $card = $cards[$key];
                $isAllowed = in_array($key, $allowed, true);
                $isCurrent = $current === $key && $cfg?->isConnected();
                $borderCls = $isCurrent
                    ? 'border-wa-deep ring-2 ring-wa-deep/15'
                    : ($isAllowed
                        ? 'border-paper-200'
                        : 'border-paper-100 opacity-50');
            @endphp
            <div class="border {{ $borderCls }} rounded-xl p-4 flex flex-col">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <span
                        class="w-10 h-10 rounded-lg {{ $card['iconBg'] }} {{ $card['iconFg'] }} grid place-items-center">{!! $card['icon'] !!}</span>
                    @if (!$isAllowed)
                        <span class="text-[10px] font-mono text-ink-500">{{ __('Disabled by admin') }}</span>
                    @elseif ($isCurrent)
                        <span class="inline-flex items-center gap-1 text-[10.5px] font-mono text-wa-deep"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected</span>
                    @endif
                </div>
                <div class="font-serif text-[18px] leading-tight">{{ $card['title'] }}</div>
                <p class="text-[11.5px] text-ink-500 mt-1 leading-snug flex-1">{{ $card['subtitle'] }}</p>

                @if (!$isAllowed)
                    <div class="mt-3 pt-3 border-t border-paper-200 text-[11px] text-ink-500 font-mono">
                        {{ __('Ask your admin to enable this method at /admin/settings.') }}</div>
                @elseif ($isCurrent)
                    <div class="mt-3 pt-3 border-t border-paper-200 flex items-center justify-between">
                        <span
                            class="text-[11px] text-ink-500 font-mono truncate">{{ $cfg->phone_number ? mask_phone($cfg->phone_number) : $cfg->display_label }}</span>
                        <button type="button" data-disconnect-provider="1"
                            class="text-[11px] text-accent-coral font-semibold hover:underline">{{ __('Disconnect') }}</button>
                    </div>
                @else
                    <button type="button" data-toggle-provider="{{ $key }}"
                        class="mt-3 w-full px-3 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center justify-center gap-1.5">
                        Set up
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Inline expansion panels — only one shows at a time, controlled by the JS --}}
    <div class="border-t border-paper-200" id="provider-setup-panel" style="display:none">
        @if (in_array('waba', $allowed))
            <div data-provider-form="waba" class="hidden p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                    {{ __('WABA · Embedded Signup') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Connect with Meta') }}</h3>
                @if (!$appIdSet || !$appConfigIdSet)
                    <div
                        class="bg-accent-amber/15 border border-accent-amber/40 rounded-lg p-3 text-[12px] text-ink-700">
                        <strong>{{ __('Meta App not configured.') }}</strong> Your platform admin needs to add the App
                        ID + Config ID at <a href="{{ url('/admin/settings') }}"
                            class="text-wa-deep font-semibold hover:underline">/admin/settings</a>.
                    </div>
                @else
                    <p class="text-[12.5px] text-ink-600 mb-3">
                        {{ __("Click below — you'll pick which WhatsApp number to connect from a Meta-hosted dialog. We provision webhooks, register the phone, and link a catalog automatically.") }}
                    </p>
                    <button id="waba-signup-btn" type="button"
                        data-app-id="{{ \App\Models\SystemSetting::get('waba_app_id') }}"
                        data-config-id="{{ \App\Models\SystemSetting::get('waba_config_id') }}"
                        data-graph-version="{{ \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0') }}"
                        class="px-4 py-2.5 rounded-full bg-[#1877F2] hover:bg-[#1864d6] text-paper-0 text-[13px] font-semibold inline-flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor">
                            <path
                                d="M16 8a8 8 0 1 0-9.25 7.9V10.3H4.72V8h2.03V6.24c0-2 1.2-3.12 3.02-3.12.87 0 1.79.16 1.79.16v1.97h-1.01c-.99 0-1.3.62-1.3 1.25V8h2.21l-.35 2.3H9.25v5.6A8 8 0 0 0 16 8z" />
                        </svg>
                        Continue with Meta
                    </button>
                    <div id="waba-status" class="hidden mt-3"></div>
                @endif
            </div>
        @endif

        @if (in_array('baileys', $allowed))
            <div data-provider-form="baileys" class="hidden p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                    {{ __('Unofficial API · QR pair') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Pair your phone') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] gap-4">
                    <div
                        class="bg-paper-50 border border-paper-200 rounded-2xl p-4 flex items-center justify-center aspect-square">
                        <div id="baileys-qr"
                            class="w-full h-full flex items-center justify-center text-[11px] font-mono text-ink-500 text-center">
                            {{ __('Enter your phone number, then click Generate QR.') }}
                        </div>
                    </div>
                    <form id="baileys-form" class="space-y-3">
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('WhatsApp number') }}</span>
                            <div class="wa-iti-wrap">
                                <input id="baileys-phone" type="tel" name="phone_number" required
                                    class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Your number without country code') }}" />
                            </div>
                            <input id="baileys-cc" type="hidden" name="country_code" value="{{ app_default_country()['code'] }}" />
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Device label') }}
                                <span class="text-ink-500 font-normal">(optional)</span></span>
                            <input type="text" name="device_name" maxlength="64"
                                placeholder="{{ __('e.g. Bloomly Support') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep" />
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Node bridge URL') }}
                                <span class="text-ink-500 font-normal">(optional)</span></span>
                            <input type="url" name="server_url" maxlength="191"
                                placeholder="{{ $sharedNodeUrl ?: 'http://localhost:8888' }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                            <span class="text-[10.5px] text-ink-500 mt-1 block">Leave blank to use platform default
                                ({{ $sharedNodeUrl ?: 'not configured' }}).</span>
                        </label>
                        <button id="baileys-generate" type="button"
                            class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center justify-center gap-2">
                            {{ __('Generate QR') }}
                        </button>
                        <div id="baileys-status" class="hidden text-[11.5px] font-mono"></div>
                    </form>
                </div>
            </div>
        @endif

        @if (in_array('twilio', $allowed))
            <div data-provider-form="twilio" class="hidden p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                    {{ __('Twilio · credentials') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Connect Twilio') }}</h3>
                <form method="POST" action="{{ route('user.connect.wa-store.twilio') }}"
                    class="space-y-3 max-w-2xl">
                    @csrf
                    @if ($sharedTwilioSid !== '')
                        <div
                            class="bg-wa-mint/30 border border-wa-green/30 rounded-lg p-2 text-[11.5px] text-ink-700 font-mono">
                            {{ __('Platform admin has shared default Twilio creds. Leave fields blank to use them.') }}
                        </div>
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Account SID') }}</span>
                            <input type="text" name="account_sid" maxlength="64"
                                placeholder="{{ $sharedTwilioSid !== '' ? 'using admin default' : 'ACxxxxxxxx...' }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auth Token') }}</span>
                            <input type="password" name="auth_token" maxlength="128"
                                placeholder="{{ __('paste from twilio.com/console') }}"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('From number') }}</span>
                            <input type="text" name="from_number" maxlength="32" placeholder="+14155238886"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                        </label>
                        <label class="block">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Mode') }}</span>
                            <select name="sandbox"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="0">{{ __('Production') }}</option>
                                <option value="1">{{ __('Sandbox (testing)') }}</option>
                            </select>
                        </label>
                    </div>
                    <div class="pt-2">
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                            Save &amp; test
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</section>

<form id="provider-disconnect-form" method="POST" action="{{ route('user.connect.wa-store.disconnect') }}"
    class="hidden">@csrf</form>

<script>
    (function() {
        const panel = document.getElementById('provider-setup-panel');
        document.querySelectorAll('[data-toggle-provider]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.toggleProvider;
                panel.style.display = 'block';
                panel.querySelectorAll('[data-provider-form]').forEach(f => {
                    f.classList.toggle('hidden', f.dataset.providerForm !== target);
                });
                panel.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });
        document.querySelectorAll('[data-disconnect-provider]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!confirm('Disconnect? Sends will start failing until you reconnect.')) return;
                document.getElementById('provider-disconnect-form').submit();
            });
        });
    })();
</script>
