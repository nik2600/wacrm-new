<x-layouts.admin :title="__('System Message Setting')" admin-key="wadesk-message" page="settings-wadesk-message">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('System Message Setting') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4 hidden md:block">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search inside settings...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Project settings') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('System message') }} <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Enable any combination of messaging engines for the platform — click a card to toggle it on or off. Each workspace then uses whatever it connects within the enabled set. Mark one engine as the default for sends that do not pin a specific sender.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/settings') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                <button type="submit" form="wadesk-providers-form"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>
        </div>

        <x-admin.flash />

        {{-- The actual <form>; existing card UI lives inside it. The
 "Save changes" button at the page header submits via the
 form="wadesk-providers-form" attribute. --}}
        <form id="wadesk-providers-form" method="POST" action="{{ route('admin.settings.providers.update') }}">@csrf
        </form>

        @php
            // Multi-engine: the platform-enabled set drives which provider
            // sections + cred panes render. Computed once at the top so gates
            // BELOW the engine cards (e.g. the WABA dispatch section) work.
            $allowedEngines = $settings['allowed_send_methods'] ?? ['baileys'];
            $allowedEngines = is_array($allowedEngines) ? $allowedEngines : [$allowedEngines];
            $wabaEnabled = in_array('waba', $allowedEngines, true);
        @endphp

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <div class="space-y-5 min-w-0">

                {{-- Phase 5 — WABA multi-tenant dispatcher toggle. Surfaces
 the feature flag + Graph API version so admin can flip
 the new path on/off without touching .env. Only shown
 when WABA is the active engine. --}}
                @if ($wabaEnabled)
                    <section class="bg-paper-0 border border-wa-green/40 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('waba · multi-tenant dispatch') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">
                                    {{ __('Per-workspace WABA routing') }}</h2>
                                <p class="text-[12px] text-ink-600 mt-1 max-w-2xl">
                                    Routes every outbound /chat send through the workspace's own connected WABA account
                                    instead of a single shared platform token. Required for multi-merchant
                                    production. Default <strong>{{ __('OFF') }}</strong> so existing installs keep
                                    using the legacy single-tenant path.
                                </p>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                                <span
                                    class="text-[12px] text-ink-700">{{ $settings['waba_dispatch_v2_enabled'] ? 'Enabled' : 'Disabled' }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input form="wadesk-providers-form" type="checkbox" name="waba_dispatch_v2_enabled"
                                        value="1" @checked($settings['waba_dispatch_v2_enabled']) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                            <label class="space-y-1.5 col-span-1 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Graph API version') }}</span>
                                <input form="wadesk-providers-form" name="waba_graph_api_version"
                                    value="{{ $settings['waba_graph_api_version'] }}" placeholder="v23.0"
                                    pattern="v\d{1,2}\.\d{1,2}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Default') }} <span
                                        class="font-mono">v23.0</span>. Latest stable is <span
                                        class="font-mono">v25.0</span> (Feb 2026) — bump only after verifying payload
                                    backwards-compat.</span>
                            </label>
                            <div class="rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-center">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Connected WABAs') }}</div>
                                <div class="font-serif text-[26px] leading-none mt-1">
                                    {{ number_format($settings['waba_connected_count']) }}</div>
                                <div class="text-[11px] text-ink-500 mt-1">{{ __('across all workspaces') }}</div>
                            </div>
                        </div>
                        @if ($settings['waba_dispatch_v2_enabled'] && $settings['waba_connected_count'] === 0)
                            <div
                                class="px-5 py-3 border-t border-accent-amber/40 bg-accent-amber/10 text-[12px] text-ink-700">
                                <strong>{{ __('Warning:') }}</strong> v2 is enabled but no workspace has connected a
                                WABA at <span class="font-mono">/devices</span> yet. Outbound sends will return <em>"No
                                    connected WABA account"</em> until at least one workspace connects.
                            </div>
                        @endif
                    </section>

                    {{-- Registration WhatsApp OTP verification. Sends a code to the
 signup mobile from an admin-selected device on ANY engine:
 Unofficial (text), WABA (approved auth template), Twilio (text). --}}
                    <section class="bg-paper-0 border border-wa-green/40 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('auth · signup verification') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">
                                    {{ __('Registration OTP verification') }}</h2>
                                <p class="text-[12px] text-ink-600 mt-1 max-w-2xl">
                                    When ON, new users must confirm a one-time code sent to their WhatsApp before the
                                    account is created. The code is sent from the device you pick below. On
                                    <strong>{{ __('WABA') }}</strong> it uses your approved
                                    <strong>{{ __('authentication') }}</strong> template; on
                                    <strong>{{ __('Unofficial') }}</strong> and <strong>{{ __('Twilio') }}</strong> it is
                                    sent as a text message. Default <strong>{{ __('OFF') }}</strong>.
                                </p>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                                <span
                                    class="text-[12px] text-ink-700">{{ $settings['registration_otp_enabled'] ? 'Enabled' : 'Disabled' }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input form="wadesk-providers-form" type="checkbox" name="registration_otp_enabled"
                                        value="1" @checked($settings['registration_otp_enabled']) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5 items-start">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Sender channel') }}</span>
                                <select form="wadesk-providers-form" name="registration_otp_sender"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="">{{ __('— none —') }}</option>
                                    @foreach ($otpSenders as $s)
                                        <option value="{{ $s['value'] }}" @selected($settings['registration_otp_sender'] === $s['value'])>
                                            {{ $s['label'] }}</option>
                                    @endforeach
                                </select>
                                <span class="text-[11px] text-ink-500">{{ __('The connected channel that sends the codes — Unofficial, WABA, or Twilio.') }}</span>
                            </label>
                            <div class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold block">{{ __('WABA auth template') }}</span>
                                <select form="wadesk-providers-form" name="registration_otp_template_id"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="0">{{ __('— none (required for WABA) —') }}</option>
                                    @foreach ($otpTemplates as $t)
                                        <option value="{{ $t['id'] }}" @selected($settings['registration_otp_template_id'] === $t['id'])>
                                            {{ $t['label'] }}</option>
                                    @endforeach
                                </select>
                                {{-- One-click: builds the auth template in code + submits it to
 Meta against the selected WABA device. `formaction` posts THIS
 form (so the picked device rides along) to the create route. --}}
                                <button type="submit" form="wadesk-providers-form"
                                    formaction="{{ route('admin.settings.otp-template.create') }}"
                                    class="mt-1 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full border border-wa-deep text-wa-deep text-[12px] font-semibold hover:bg-wa-deep/5">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    {{ __('Create & submit OTP template') }}
                                </button>
                                <span class="text-[11px] text-ink-500 block">{{ __('Creates the OTP message for the selected channel. WABA is submitted to Meta for approval; Unofficial and Twilio are approved instantly and ready to use.') }}</span>
                            </div>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Code validity (minutes)') }}</span>
                                <input form="wadesk-providers-form" type="number" name="registration_otp_ttl_minutes"
                                    value="{{ $settings['registration_otp_ttl_minutes'] }}" min="1" max="90"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Code length') }}</span>
                                <input form="wadesk-providers-form" type="number" name="registration_otp_length"
                                    value="{{ $settings['registration_otp_length'] }}" min="4" max="8"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Resend cooldown (seconds)') }}</span>
                                <input form="wadesk-providers-form" type="number" name="registration_otp_resend_cooldown_sec"
                                    value="{{ $settings['registration_otp_resend_cooldown_sec'] }}" min="15" max="600"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Max attempts') }}</span>
                                <input form="wadesk-providers-form" type="number" name="registration_otp_max_attempts"
                                    value="{{ $settings['registration_otp_max_attempts'] }}" min="1" max="20"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </section>

                    {{-- Phase 6 — Templates v2. Submits new /templates to Meta
 /message_templates immediately on save, polls status,
 enforces ban-prevention lint rules. --}}
                    <section class="bg-paper-0 border border-wa-green/40 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('waba · template lifecycle') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">
                                    {{ __('Meta template submission & sync') }}</h2>
                                <p class="text-[12px] text-ink-600 mt-1 max-w-2xl">
                                    When ON, every new template POSTs to <span
                                        class="font-mono">/{WABA_ID}/message_templates</span> on save. Approval status
                                    updates live via the <span
                                        class="font-mono">{{ __('message_template_status_update') }}</span> webhook
                                    plus an AJAX poll while the user has the detail page open. Default
                                    <strong>{{ __('OFF') }}</strong> — existing local-approval flow keeps working.
                                </p>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                                <span
                                    class="text-[12px] text-ink-700">{{ $settings['waba_templates_v2_enabled'] ? 'Enabled' : 'Disabled' }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input form="wadesk-providers-form" type="checkbox" name="waba_templates_v2_enabled"
                                        value="1" @checked($settings['waba_templates_v2_enabled']) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                            <label class="space-y-1.5">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Server poll interval (min)') }}</span>
                                <input form="wadesk-providers-form" type="number" name="waba_template_polling_min"
                                    value="{{ $settings['waba_template_polling_min'] }}" min="5"
                                    max="240"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Sweep for PENDING templates older than 1h. Webhook is primary; this is the safety-net poll. 30 min recommended.') }}</span>
                            </label>
                            <label class="space-y-1.5 flex flex-col">
                                <span class="text-[11.5px] font-semibold">{{ __('Strict linter') }}</span>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                        <input form="wadesk-providers-form" type="checkbox"
                                            name="waba_template_lint_strict" value="1"
                                            @checked($settings['waba_template_lint_strict']) class="sr-only peer">
                                        <span
                                            class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                        <span
                                            class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                    </span>
                                    <span class="text-[12px] text-ink-700">{{ __('Block on warnings') }}</span>
                                </label>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('When ON, trigger phrases (guaranteed, 100%, act now…) block submit. OFF only shows a banner.') }}</span>
                            </label>
                            <div class="rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-center">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Webhook poll') }}</div>
                                <div class="font-serif text-[26px] leading-none mt-1">30<span
                                        class="text-[13px] text-ink-500">s</span></div>
                                <div class="text-[11px] text-ink-500 mt-1">{{ __('client-side, while pending') }}
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

                <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h2 class="font-serif text-[25px] leading-tight">{{ __('Enabled messaging engines') }}</h2>
                    </div>

                    <div class="p-5 space-y-5">

                        {{-- Engine cards — MULTI select. Click a card to enable/
 disable that engine for the whole platform (any subset of the
 three). The small "default" radio on an enabled card sets the
 fallback engine for sends that don't pin a sender. The hidden
 checkbox (allowed_send_methods[]) + radio (default_engine) are
 what the form submits; clicking the card toggles them via JS. --}}
                        @php
                            $allowed = $settings['allowed_send_methods'] ?? ['baileys'];
                            $allowed = is_array($allowed) ? $allowed : [$allowed];
                            $defaultProvider = $settings['default_send_method'] ?? ($allowed[0] ?? 'baileys');
                            // Three engines — Business API covers both the manual-
                            // token paste workflow AND Embedded Signup (WABA login),
                            // since they target the same Meta Cloud API + App creds.
                            $engines = [
                                'twilio' => [
                                    'key' => 'twilio',
                                    'title' => 'Twilio',
                                    'desc' => "Send via Twilio's WhatsApp sandbox or production sender.",
                                ],
                                'wa-api' => [
                                    'key' => 'baileys',
                                    'title' => 'Unofficial API',
                                    'desc' => 'Self-hosted Node bridge. Lower cost, unofficial.',
                                ],
                                'business-api' => [
                                    'key' => 'waba',
                                    'title' => 'Business API (WABA)',
                                    'desc' => 'Meta Cloud API — manual token or Embedded Signup.',
                                ],
                            ];
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" data-engine-tabs>
                            @foreach ($engines as $slug => $eng)
                                @php
                                    $on = in_array($eng['key'], $allowed, true);
                                    $isDefault = $eng['key'] === $defaultProvider;
                                @endphp
                                <div data-engine="{{ $slug }}" data-active="{{ $on ? 'true' : 'false' }}"
                                    class="engine-tab relative px-4 py-4 rounded-2xl text-left transition cursor-pointer border bg-paper-0 border-paper-200 hover:border-wa-deep/40 data-[active=true]:border-wa-deep data-[active=true]:ring-2 data-[active=true]:ring-wa-deep/15 data-[active=true]:bg-[#F0F8F6]">
                                    <div class="flex items-center justify-between gap-2 mb-2.5">
                                        {{-- Visible enable checkbox — tick the engines you want (any subset). --}}
                                        <span class="flex items-center gap-2">
                                            <input form="wadesk-providers-form" type="checkbox" name="allowed_send_methods[]"
                                                value="{{ $slug }}" @checked($on)
                                                class="engine-cb w-4 h-4 rounded accent-wa-deep cursor-pointer"
                                                data-engine-checkbox="{{ $slug }}" />
                                            <span
                                                class="engine-state text-[10.5px] font-mono uppercase tracking-[0.14em] {{ $on ? 'text-wa-deep' : 'text-ink-400' }}">{{ $on ? __('enabled') : __('off') }}</span>
                                        </span>
                                        {{-- Default-engine picker — only selectable when this engine is ticked. --}}
                                        <label data-default-ctl
                                            class="flex items-center gap-1 text-[10.5px] text-ink-500 cursor-pointer data-[active=false]:opacity-40"
                                            title="{{ __('Use this engine for sends that do not pick a specific sender') }}">
                                            <input form="wadesk-providers-form" type="radio" name="default_engine"
                                                value="{{ $slug }}" @checked($isDefault) @disabled(!$on)
                                                data-engine-default="{{ $slug }}" class="accent-wa-deep" />
                                            {{ __('default') }}
                                        </label>
                                    </div>
                                    <div class="font-serif text-[18px] leading-tight">{{ $eng['title'] }}</div>
                                    <div class="text-[11.5px] text-ink-600 mt-1">{{ $eng['desc'] }}</div>
                                    {{-- Active marker — shown only on the default engine (the one that
                                         handles sends with no specific sender). Other cards show nothing. --}}
                                    <div class="engine-optlabel mt-3 pt-2 border-t border-paper-100 text-center font-mono text-[10.5px] uppercase tracking-[0.16em] {{ $isDefault ? 'text-wa-deep' : 'text-transparent select-none' }}">
                                        @if ($isDefault)
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-wa-deep"></span>{{ __('Active') }}
                                            </span>
                                        @else
                                            &nbsp;
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p class="text-[11.5px] text-ink-500">
                            {{ __('Tick the engines you want available platform-wide. The "default" radio marks which engine handles sends that do not pick a specific number — every other send uses whichever number the operator chooses.') }}
                        </p>

                        <div
                            class="rounded-2xl border border-accent-amber/40 bg-accent-amber/10 p-3 text-[12px] text-ink-700 flex items-start gap-2">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-amber shrink-0 mt-0.5" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <path d="M8 2v6M8 11v.5" />
                                <circle cx="8" cy="8" r="6.5" />
                            </svg>
                            <span>{{ __('Switching engines mid-traffic drops in-flight queues. Pause campaigns first, then change.') }}</span>
                        </div>

                        <div data-engine-pane="twilio" @unless (in_array('twilio', $allowedEngines, true)) hidden @endunless class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="space-y-1.5"><span
                                        class="text-[11.5px] font-semibold">{{ __('Account SID') }} <span
                                            class="text-accent-coral">*</span></span><input
                                        form="wadesk-providers-form" name="twilio_account_sid"
                                        value="{{ $settings['twilio_account_sid'] }}"
                                        placeholder="{{ __('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxx') }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('From Twilio Console dashboard.') }}</span></label>
                                <label class="space-y-1.5"><span
                                        class="text-[11.5px] font-semibold">{{ __('Auth token') }} <span
                                            class="text-accent-coral">*</span></span><input
                                        form="wadesk-providers-form" name="twilio_auth_token" type="password"
                                        placeholder="{{ $settings['twilio_auth_token_set'] ? '••• stored, leave blank to keep' : 'paste from console' }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('Pair from the same panel as the SID.') }}</span></label>
                                <label class="space-y-1.5 col-span-2"><span
                                        class="text-[11.5px] font-semibold">{{ __('WhatsApp sender') }} <span
                                            class="text-accent-coral">*</span></span><input
                                        form="wadesk-providers-form" name="twilio_whatsapp_number"
                                        value="{{ $settings['twilio_whatsapp_number'] }}" placeholder="+14155238886"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('Format') }} <span
                                            class="font-mono">+E164</span> (with country code).</span></label>
                                <label class="space-y-1.5 col-span-2"><span
                                        class="text-[11.5px] font-semibold">{{ __('Status callback URL') }}</span><input
                                        value="{{ url('/webhooks/whatsapp/inbound') }}" readonly
                                        class="w-full rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono"><span
                                        class="text-[11px] text-ink-500">{{ __('Paste in your Twilio Messaging Service → Integration tab.') }}</span></label>
                            </div>
                        </div>

                        <div data-engine-pane="wa-api" @unless (in_array('baileys', $allowedEngines, true)) hidden @endunless class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="space-y-1.5 col-span-2"><span
                                        class="text-[11.5px] font-semibold">{{ __('Server URL') }} <span
                                            class="text-accent-coral">*</span></span><input
                                        form="wadesk-providers-form" name="baileys_server_url" type="url"
                                        value="{{ $settings['baileys_server_url'] }}"
                                        placeholder="http://localhost:8888"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('Where your Unofficial API node bridge is reachable. Inbound + status webhooks are handled automatically — your Node bridge picks our URL up from its') }}
                                        <span class="font-mono">{{ __('APP_DOMAIN_NAME') }}</span> env
                                        var.</span></label>
                                <label class="space-y-1.5 col-span-2"><span
                                        class="text-[11.5px] font-semibold">{{ __('Node webhook token') }}</span><input
                                        form="wadesk-providers-form" name="node_webhook_token" type="password"
                                        autocomplete="off"
                                        placeholder="{{ $settings['node_webhook_token_set'] ? '••• stored, leave blank to keep' : 'shared X-Node-Token secret' }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('Shared secret the Node bridge sends as X-Node-Token. Must match the token set in your Node bridge config. Hidden after save; re-paste only to rotate.') }}</span></label>
                            </div>
                        </div>

                        <div data-engine-pane="business-api" @unless (in_array('waba', $allowedEngines, true)) hidden @endunless class="space-y-4">
                            {{-- Platform-level Meta App credentials. Shared by both
 the manual-token paste flow AND the Embedded Signup
 (WABA login) flow — they hit the same Cloud API. --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="space-y-1.5"><span
                                        class="text-[11.5px] font-semibold">{{ __('Meta App ID') }} <span
                                            class="text-accent-coral">*</span></span><input
                                        form="wadesk-providers-form" name="waba_app_id"
                                        value="{{ $settings['waba_app_id'] }}" placeholder="666572953476"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('developers.facebook.com → your app → Basic settings.') }}</span></label>
                                <label class="space-y-1.5"><span
                                        class="text-[11.5px] font-semibold">{{ __('App Secret') }} <span
                                            class="text-accent-coral">*</span></span><input
                                        form="wadesk-providers-form" name="waba_app_secret" type="password"
                                        placeholder="{{ $settings['waba_app_secret_set'] ? '••• stored, leave blank to keep' : 'paste secret' }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('Hidden after save; re-paste only to rotate.') }}</span></label>
                                <label class="space-y-1.5"><span
                                        class="text-[11.5px] font-semibold">{{ __('Webhook verify token') }}</span><input
                                        form="wadesk-providers-form" name="waba_webhook_verify_token"
                                        value="{{ $settings['waba_webhook_verify_token'] }}"
                                        placeholder="{{ __('any random string') }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                        class="text-[11px] text-ink-500">{{ __('Meta echoes this back during subscription handshake.') }}</span></label>
                                <label class="space-y-1.5 col-span-2"><span
                                        class="text-[11.5px] font-semibold">{{ __('Webhook URL') }} <span
                                            class="text-ink-500 font-normal">(copy once, paste in Meta
                                            dashboard)</span></span>
                                    <div class="flex gap-2"><input value="{{ url('/webhooks/whatsapp/inbound') }}"
                                            readonly
                                            class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono"><button
                                            type="button"
                                            onclick="navigator.clipboard.writeText('{{ url('/webhooks/whatsapp/inbound') }}'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 1500)"
                                            class="rounded-xl border border-paper-200 px-3 text-[12px]">Copy</button>
                                    </div><span
                                        class="text-[11px] text-ink-500">{{ __('Meta needs this URL once when you set up the app. Paste in app dashboard → WhatsApp → Configuration. Subscribe to') }}
                                        <span class="font-mono">{{ __('messages') }}</span> + <span
                                            class="font-mono">{{ __('message_status') }}</span>.</span>
                                </label>
                            </div>

                            {{-- WhatsApp Coexistence — live toggle (was previously in a
 hidden section; now on the real providers form so it
 actually persists). Drives SystemSetting waba_coexistence,
 which the /devices WABA modal reads as data-coexistence to
 launch the Business-App onboarding sub-flow. --}}
                            <div class="mt-2 pt-4 border-t border-paper-200">
                                <label class="inline-flex items-start gap-2.5 cursor-pointer">
                                    <input type="checkbox" form="wadesk-providers-form" name="waba_coexistence" value="1"
                                        @checked(old('waba_coexistence', $settings['waba_coexistence'] ?? false))
                                        class="mt-0.5 w-4 h-4 rounded border-paper-300 text-wa-deep focus:ring-wa-deep/20">
                                    <span class="text-[11.5px] text-ink-700 leading-relaxed">
                                        {{ __('Enable WhatsApp Coexistence — merchants keep using the WhatsApp Business App on the phone while the Cloud API + webhooks run on the same number (no migration). Adds a "Connect Business-App number" option to the /devices WABA login flow.') }}
                                    </span>
                                </label>
                                <div class="mt-2 rounded-xl border border-paper-200 bg-paper-50 p-3">
                                    <div class="text-[10px] font-mono uppercase tracking-[0.16em] text-ink-500 mb-1.5">{{ __('One-time Meta App setup for Coexistence') }}</div>
                                    <p class="text-[11px] text-ink-600 leading-relaxed">
                                        {{ __('In your Meta App → Webhooks → WhatsApp Business Account, subscribe to these THREE extra fields (in addition to "messages"). Without them, replies typed on the phone app, app contacts, and old chat history will NOT sync into the inbox:') }}
                                    </p>
                                    <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                        <li class="px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep font-mono text-[10.5px]">smb_message_echoes</li>
                                        <li class="px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep font-mono text-[10.5px]">smb_app_state_sync</li>
                                        <li class="px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep font-mono text-[10.5px]">history</li>
                                    </ul>
                                    <p class="text-[10.5px] text-ink-500 mt-1.5">{{ __('We handle all three automatically once Meta delivers them — phone-typed replies appear as outbound, app contacts sync to the contact book, and past chats backfill the inbox.') }}</p>
                                    <p class="text-[10px] text-ink-400 mt-1.5 leading-relaxed">{{ __('Meta limits (not configurable): WhatsApp Business App v2.24.17+, open the app at least every 14 days; history import is 1:1 chats from the last 6 months only (no groups); ~5 msg/sec throughput. Meta enforces number eligibility server-side per number at onboarding.') }}</p>

                                    {{-- Live self-check: asks Meta which fields THIS App is actually
                                         subscribed to. Its own form (own POST) so it never submits the
                                         provider settings; pure server round-trip, no JS. --}}
                                    <form method="POST" action="{{ route('admin.settings.coexistence.check') }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-wa-deep text-white text-[11px] font-medium hover:bg-wa-deep/90">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9"/><path d="M13.5 3v3h-3"/></svg>
                                            {{ __('Check webhook subscription') }}
                                        </button>
                                    </form>

                                    @if ($check = session('coex_check'))
                                        <div class="mt-3 rounded-xl border {{ ($check['ok'] ?? false) ? 'border-wa-green/40 bg-wa-mint' : 'border-amber-300 bg-amber-50' }} p-3">
                                            @if (!empty($check['error']))
                                                <p class="text-[11.5px] text-amber-800 leading-relaxed">{{ $check['error'] }}</p>
                                            @else
                                                <p class="text-[11px] font-semibold {{ ($check['ok'] ?? false) ? 'text-wa-deep' : 'text-amber-800' }}">
                                                    {{ ($check['ok'] ?? false)
                                                        ? __('All coexistence fields are subscribed. Phone-typed replies will sync.')
                                                        : __('Some fields are NOT subscribed — that is why phone replies are missing.') }}
                                                </p>
                                                <ul class="mt-2 space-y-1">
                                                    @foreach (($check['present'] ?? []) as $field => $ok)
                                                        <li class="flex items-center gap-2 text-[11px]">
                                                            @if ($ok)
                                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8.5l3 3 6-7"/></svg>
                                                                <span class="font-mono text-ink-700">{{ $field }}</span>
                                                                <span class="text-wa-deep">{{ __('subscribed') }}</span>
                                                            @else
                                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                                                <span class="font-mono text-ink-700">{{ $field }}</span>
                                                                <span class="text-amber-700">{{ __('MISSING — subscribe it in Meta App → Webhooks') }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                @if (!empty($check['callback_url']))
                                                    <p class="text-[10px] text-ink-500 mt-2 leading-relaxed break-all">
                                                        {{ __('Callback URL Meta has on file:') }}
                                                        <span class="font-mono text-ink-600">{{ $check['callback_url'] }}</span>
                                                        · {{ ($check['active'] ?? false) ? __('active') : __('INACTIVE') }}
                                                    </p>
                                                @endif

                                                {{-- One-click fix: subscribe the missing fields on the Meta
                                                     App via API. Only shown while something is missing. --}}
                                                @if (!empty($check['missing']))
                                                    <form method="POST" action="{{ route('admin.settings.coexistence.subscribe') }}" class="mt-3">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-wa-deep text-white text-[11px] font-medium hover:bg-wa-teal">
                                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3.5v9M3.5 8h9"/></svg>
                                                            {{ __('Subscribe missing fields now') }}
                                                        </button>
                                                        <span class="ml-2 text-[10px] text-ink-500">{{ __('Subscribes them on the Meta App using the App Secret + verify token above.') }}</span>
                                                    </form>
                                                @endif

                                                @if (!empty($check['subscribed_now']))
                                                    <p class="text-[11px] text-wa-deep font-medium mt-2">{{ __('Subscribed successfully — Meta will now deliver phone-typed replies and history.') }}</p>
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Call Flow "Search web" node — provider + key. Powers
                                 the AI voice flow's live web lookups. Key is stored
                                 encrypted (SystemSetting web_search_key). --}}
                            <div class="mt-2 pt-4 border-t border-paper-200">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Call Flow · web search') }}</div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="text-[11.5px] text-ink-700">{{ __('Provider') }}</span>
                                        <select form="wadesk-providers-form" name="web_search_provider"
                                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                            @php $wsp = \App\Models\SystemSetting::get('web_search_provider', 'tavily'); @endphp
                                            <option value="tavily"  @selected($wsp==='tavily')>Tavily (recommended)</option>
                                            <option value="serpapi" @selected($wsp==='serpapi')>SerpAPI</option>
                                            <option value="brave"   @selected($wsp==='brave')>Brave Search</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] text-ink-700">{{ __('API key') }}</span>
                                        <input form="wadesk-providers-form" name="web_search_key" type="password" autocomplete="off"
                                            placeholder="{{ \App\Models\SystemSetting::get('web_search_key') ? '•••••••• (saved)' : 'paste key' }}"
                                            class="mt-1 w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    </label>
                                </div>
                                <p class="text-[10.5px] text-ink-500 mt-1.5">{{ __('Used by the "Search web" node in AI Call Flows. Leave the key blank to keep the saved one. Without a key the node just returns nothing and the call continues.') }}</p>
                            </div>

                            {{-- Embedded Signup (WABA login) — optional sub-section.
 Fill these to enable the one-click "Sign in with
 Meta" connect flow at /connect; leave blank if you
 prefer workspaces to paste their token manually. --}}
                            <div class="mt-2 pt-4 border-t border-paper-200">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                            {{ __('embedded signup · optional') }}</div>
                                        <h3 class="font-serif text-[16px] leading-tight mt-0.5">
                                            {{ __('WABA login (1-click Meta sign-in)') }}</h3>
                                    </div>
                                    <span
                                        class="text-[11px] text-ink-500">{{ __('Same App ID + Secret above. Leave blank to disable.') }}</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label class="space-y-1.5 col-span-2"><span
                                            class="text-[11.5px] font-semibold">{{ __('Embedded Signup Config ID') }}</span><input
                                            form="wadesk-providers-form" name="waba_config_id"
                                            value="{{ $settings['waba_config_id'] }}"
                                            placeholder="{{ __('cfg_...') }}"
                                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"><span
                                            class="text-[11px] text-ink-500">{{ __('Meta Business Suite → Login Configurations → copy the ID. Empty = workspaces paste their token manually instead.') }}</span></label>
                                    <label class="space-y-1.5 col-span-2"><span
                                            class="text-[11.5px] font-semibold">{{ __('OAuth redirect URI') }} <span
                                                class="text-ink-500 font-normal">(auto)</span></span>
                                        <div class="flex gap-2"><input value="{{ url('/connect/wa-store/waba') }}"
                                                readonly
                                                class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono"><button
                                                type="button"
                                                onclick="navigator.clipboard.writeText('{{ url('/connect/wa-store/waba') }}'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 1500)"
                                                class="rounded-xl border border-paper-200 px-3 text-[12px]">Copy</button>
                                        </div><span
                                            class="text-[11px] text-ink-500">{{ __('Paste in app dashboard → Facebook Login → Valid OAuth redirect URIs.') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-paper-200 bg-paper-50 p-3 text-[11.5px] text-ink-600">
                                <strong>{{ __('Note:') }}</strong> Per-workspace fields (phone number ID, access
                                token) are collected from each tenant during their <span class="font-mono">/devices →
                                    Add device</span> connect flow. You only configure platform-level App credentials
                                here.
                            </div>
                        </div>

                    </div>
                </section>

                {{-- AI assistance section removed — AI keys aren't entered
 here. Platform admins manage provider keys at
 /admin/api-keys, and workspace owners enter their own
 BYOK keys at /settings (per-workspace). --}}

                {{-- Sender pacing — these values are what Node's broadcast/
 campaign/scheduled services USE to throttle outbound
 sends. Key names match what Node reads in
 node/index.js (app.locals.messageSettings):
 msg_gap — seconds between consecutive sends
 batches_gap — recipients per batch
 bw_msg_gap — minutes between batches
 enable_batches — 0/1 flag
 --}}
                <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('pacing') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Sender pacing & batching') }}
                        </h2>
                    </div>
                    <div class="p-5 grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <label class="space-y-1.5">
                            <span class="text-[11.5px] font-semibold">{{ __('Message gap (sec)') }}</span>
                            <input form="wadesk-providers-form" type="number" name="msg_gap" min="1"
                                max="600" value="{{ (int) ($settings['msg_gap'] ?? 3) }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            <span
                                class="text-[10.5px] text-ink-500">{{ __('Seconds between consecutive sends.') }}</span>
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-[11.5px] font-semibold">{{ __('Batch size') }}</span>
                            <input form="wadesk-providers-form" type="number" name="batches_gap" min="1"
                                max="10000" value="{{ (int) ($settings['batches_gap'] ?? 50) }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            <span class="text-[10.5px] text-ink-500">{{ __('Recipients per batch.') }}</span>
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-[11.5px] font-semibold">{{ __('Between batch (min)') }}</span>
                            <input form="wadesk-providers-form" type="number" name="bw_msg_gap" min="1"
                                max="1440" value="{{ (int) ($settings['bw_msg_gap'] ?? 5) }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            <span class="text-[10.5px] text-ink-500">{{ __('Minutes between batches.') }}</span>
                        </label>
                        <label
                            class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between cursor-pointer">
                            <span class="text-[13px] font-semibold">{{ __('Enable batches') }}</span>
                            <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                <input form="wadesk-providers-form" type="checkbox" name="enable_batches"
                                    value="1" @checked(!empty($settings['enable_batches'])) class="sr-only peer">
                                <span
                                    class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                <span
                                    class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                    </div>
                    {{-- Quick "Update timing" — saves ONLY the four pacing fields
                         above (and pushes them to the Node bridge) without
                         submitting the whole providers form. --}}
                    <div class="px-5 pb-5 pt-4 border-t border-paper-200 flex items-center justify-end gap-3">
                        <span id="pacing-status" class="text-[11.5px] text-ink-500" aria-live="polite"></span>
                        <button type="button" id="pacing-update-btn"
                            data-url="{{ route('admin.settings.pacing.update') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M13 8a5 5 0 1 1-1.46-3.54M13 3v2.5h-2.5" />
                            </svg>
                            {{ __('Update timing') }}
                        </button>
                    </div>
                </section>

                {{-- Campaign auto-end (expiry). Fields use form="wadesk-providers-form"
                     so they save with the main Save button at the top of the page. --}}
                <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('campaigns') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Campaign auto-end') }}</h2>
                        <p class="text-[12px] text-ink-500 mt-1">{{ __('Stops a one-off campaign from sending for days (e.g. an event blast still going a week later). A campaign that uses a daily cap or a send-window is deliberately multi-day and is never auto-ended. Operators can also set an exact end date per campaign.') }}</p>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between cursor-pointer">
                            <span>
                                <span class="text-[13px] font-semibold block">{{ __('Auto-end campaigns') }}</span>
                                <span class="text-[11px] text-ink-500">{{ __('When ON, a one-off campaign with no end date auto-ends after the window below.') }}</span>
                            </span>
                            <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                <input form="wadesk-providers-form" type="checkbox" name="campaign_auto_expiry_enabled" value="1" @checked(!empty($settings['campaign_auto_expiry_enabled'])) class="sr-only peer">
                                <span class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                        <label class="space-y-1.5">
                            <span class="text-[11.5px] font-semibold">{{ __('Auto-end after (hours from first send)') }}</span>
                            <input form="wadesk-providers-form" type="number" name="campaign_default_expiry_hours" min="1" max="8760"
                                value="{{ (int) ($settings['campaign_default_expiry_hours'] ?? 24) }}"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            <span class="text-[10.5px] text-ink-500">{{ __('Default 24 = one day. Only applies when the operator did not set a per-campaign end date.') }}</span>
                        </label>
                    </div>
                </section>

            </div>

            <aside class="space-y-4 lg:sticky lg:top-[88px]">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Quick guide') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5" data-guide-title>
                            {{ __('WhatsApp API') }}</h3>
                    </div>
                    <div class="p-4 text-[12px] text-ink-700">

                        <div data-guide="twilio" hidden>
                            <p class="text-ink-600">
                                {{ __('Use Twilio when you want a managed WhatsApp sender without your own Meta WABA. Best for low-volume or sandbox testing.') }}
                            </p>
                            <ul class="mt-3 space-y-2.5">
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">
                                        {{ __('Account SID + token') }}</div>
                                    <p class="text-ink-600 mt-0.5">{{ __('Open') }} <a
                                            href="https://console.twilio.com" target="_blank"
                                            class="text-wa-deep underline">{{ __('console.twilio.com') }}</a> →
                                        dashboard.</p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Sender') }}</div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('Get an approved sender from Messaging → Senders, or use the sandbox number.') }}
                                    </p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Status callback') }}
                                    </div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('Paste the URL above in Messaging Service → Integration → Status callback.') }}
                                    </p>
                                </li>
                            </ul>
                        </div>

                        <div data-guide="wa-api">
                            <p class="text-ink-600">
                                {{ __('Use this when you run an unofficial WhatsApp Web bridge. Lower cost but unofficial.') }}
                            </p>
                            <ul class="mt-3 space-y-2.5">
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Server URL') }}
                                    </div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('The HTTPS endpoint where your bridge node listens. Must reach :app servers.', ['app' => brand_name()]) }}
                                    </p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('API key') }}</div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('Generated when you first boot the bridge. Rotate via the bridge admin.') }}
                                    </p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Inbound webhook') }}
                                    </div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('Paste in bridge admin under "Inbound message webhook".') }}</p>
                                </li>
                            </ul>
                        </div>

                        <div data-guide="business-api" hidden>
                            <p class="text-ink-600">
                                {{ __("Meta's official Cloud API. Required for high-volume traffic, template messages, and verified business profile. Supports both manual token paste AND Embedded Signup (WABA login).") }}
                            </p>
                            <ul class="mt-3 space-y-2.5">
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">
                                        {{ __('Facebook app ID + secret') }}</div>
                                    <p class="text-ink-600 mt-0.5"><a href="https://developers.facebook.com/apps"
                                            target="_blank"
                                            class="text-wa-deep underline">{{ __('developers.facebook.com/apps') }}</a>
                                        → Basic settings.</p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Webhook') }}</div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('App dashboard → WhatsApp → Configuration → Callback URL + Verify token. Subscribe to') }}
                                        <span class="font-mono">{{ __('messages') }}</span> + <span
                                            class="font-mono">{{ __('message_status') }}</span>.</p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Per-workspace') }}
                                    </div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('Phone number ID + permanent token are collected from each tenant at') }}
                                        <span class="font-mono">/devices → Add device</span>.</p>
                                </li>
                                <li>
                                    <div class="font-semibold text-[12.5px] text-ink-900">
                                        {{ __('WABA login (optional)') }}</div>
                                    <p class="text-ink-600 mt-0.5">
                                        {{ __('Fill the Embedded Signup Config ID in the form to enable 1-click "Sign in with Meta". Requires') }}
                                        <span class="font-mono">{{ __('Tech Provider') }}</span> role on the app.
                                        Leave blank to use manual paste only.</p>
                                </li>
                            </ul>
                        </div>

                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('How multi-engine works') }}</div>
                    </div>
                    <div class="p-4 text-[12px] text-ink-700 space-y-3">
                        <p class="text-ink-600">
                            {{ __('Tick every engine you want available platform-wide. A workspace can then run any combination of them at the same time.') }}
                        </p>
                        <ul class="space-y-2.5">
                            <li class="flex gap-2">
                                <span class="font-semibold text-ink-900">1.</span>
                                <span>{{ __('Enable the engines here (Unofficial API / Meta WABA / Twilio).') }}</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="font-semibold text-ink-900">2.</span>
                                <span>{{ __('Each workspace connects its own numbers at') }} <span
                                        class="font-mono">/devices</span>
                                    {{ __('— one connect panel per enabled engine.') }}</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="font-semibold text-ink-900">3.</span>
                                <span>{{ __('When sending a campaign, broadcast, inbox reply, or template, the operator picks which number to send from — its engine is used automatically.') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                    <div class="font-semibold text-[12.5px]">{{ __('Pacing tip') }}</div>
                    <p class="text-[11.5px] text-ink-600 mt-1">
                        {{ __('Lower the message gap only after a 24h soak — Meta throttles aggressive senders.') }}
                        <span class="font-mono">3 sec</span> is the safe default.</p>
                </div>
            </aside>

        </section>

    </main>

    <script>
        (function() {
            // Multi-engine selector. Clicking a card toggles whether that engine
            // is enabled platform-wide (allowed_send_methods[]). The small
            // "default" radio on an enabled card sets default_engine. The cred
            // pane for EVERY enabled engine is shown; the sidebar guide follows
            // the default engine. At least one engine must stay enabled.
            const cards   = Array.from(document.querySelectorAll('.engine-tab'));
            const panes   = document.querySelectorAll('[data-engine-pane]');
            const guides  = document.querySelectorAll('[data-guide]');
            const titleEl = document.querySelector('[data-guide-title]');

            const cbOf  = (c) => c.querySelector('[data-engine-checkbox]');
            const defOf = (c) => c.querySelector('[data-engine-default]');
            const enabledCount = () => cards.filter(c => cbOf(c)?.checked).length;

            function sync() {
                cards.forEach(c => {
                    const on = !!cbOf(c)?.checked;
                    c.setAttribute('data-active', on ? 'true' : 'false');
                    const dot = c.querySelector('.engine-dot');
                    if (dot) dot.className = 'engine-dot w-2 h-2 rounded-full ' + (on ? 'bg-wa-deep' : 'bg-paper-300');
                    const state = c.querySelector('.engine-state');
                    if (state) {
                        state.textContent = on ? 'enabled' : 'off';
                        state.className = 'engine-state text-[10.5px] font-mono uppercase tracking-[0.14em] ' + (on ? 'text-wa-deep' : 'text-ink-400');
                    }
                    const def = defOf(c);
                    if (def) def.disabled = !on;
                });
                // Show a cred pane for each enabled engine.
                panes.forEach(p => {
                    const card = document.querySelector('.engine-tab[data-engine="' + p.dataset.enginePane + '"]');
                    p.hidden = !(card && cbOf(card)?.checked);
                });
                // Exactly one default among the enabled engines.
                const defs = cards.map(defOf).filter(Boolean);
                let chosen = defs.find(d => d.checked && !d.disabled);
                if (!chosen) {
                    chosen = defs.find(d => !d.disabled);
                    if (chosen) chosen.checked = true;
                }
                // Sidebar guide + title follow the default engine.
                const slug = chosen ? chosen.dataset.engineDefault : cards[0]?.dataset.engine;
                guides.forEach(g => g.hidden = (g.dataset.guide !== slug));
                if (titleEl && slug) {
                    const card = document.querySelector('.engine-tab[data-engine="' + slug + '"]');
                    titleEl.textContent = card?.querySelector('.font-serif')?.textContent || '';
                }
            }

            cards.forEach(card => {
                const cb = cbOf(card);
                // NOTE: clicking the card no longer toggles the engine — that
                // surprised admins (switching/viewing a card silently unchecked
                // it). Enable/disable is controlled ONLY by the visible checkbox.
                if (cb) {
                    cb.addEventListener('click', (e) => e.stopPropagation());
                    cb.addEventListener('change', () => {
                        // Never allow zero engines — revert an attempt to untick the last one.
                        if (enabledCount() === 0) { cb.checked = true; }
                        sync();
                    });
                }
                const def = defOf(card);
                if (def) {
                    def.addEventListener('click', (e) => e.stopPropagation());
                    def.addEventListener('change', sync);
                }
            });

            sync();
        })();

        // "Update timing" — quick AJAX save of just the four pacing fields so
        // the admin doesn't have to submit the whole providers form. The save
        // also pushes the new values to the Node bridge immediately.
        (function() {
            const btn = document.getElementById('pacing-update-btn');
            if (!btn) return;
            const statusEl = document.getElementById('pacing-status');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ||
                document.querySelector('#wadesk-providers-form input[name="_token"]')?.value || '';
            const field = (name) => document.querySelector('[name="' + name + '"]');

            btn.addEventListener('click', async () => {
                const body = new FormData();
                body.append('msg_gap', field('msg_gap')?.value || '');
                body.append('batches_gap', field('batches_gap')?.value || '');
                body.append('bw_msg_gap', field('bw_msg_gap')?.value || '');
                if (field('enable_batches')?.checked) body.append('enable_batches', '1');

                const prev = btn.innerHTML;
                btn.disabled = true;
                btn.style.opacity = '0.6';
                if (statusEl) statusEl.textContent = '';

                try {
                    const res = await fetch(btn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body,
                    });
                    let json = null;
                    try { json = await res.json(); } catch (_) {}

                    if (res.ok && json && json.ok) {
                        window.toast?.(json.message || 'Timing updated.', 'success');
                        if (statusEl) {
                            // Always treat a DB save as success. If the bridge
                            // confirmed the value, show it; otherwise just say
                            // "Saved" (no bridge-offline warning).
                            statusEl.classList.remove('text-accent-coral');
                            statusEl.classList.add('text-wa-deep');
                            statusEl.textContent = (json.bridge && json.bridge.msg_gap != null) ?
                                ('Saved · ' + json.bridge.msg_gap + 's gap active on bridge') :
                                'Saved.';
                        }
                    } else {
                        const err = json?.errors ? Object.values(json.errors)[0]?.[0] :
                            (json?.message || ('Update failed (' + res.status + ').'));
                        window.toast?.(err, 'error');
                        if (statusEl) {
                            statusEl.textContent = err;
                            statusEl.classList.remove('text-wa-deep');
                            statusEl.classList.add('text-accent-coral');
                        }
                    }
                } catch (e) {
                    window.toast?.('Network error. Please try again.', 'error');
                    if (statusEl) statusEl.textContent = 'Network error.';
                } finally {
                    btn.disabled = false;
                    btn.style.opacity = '';
                    btn.innerHTML = prev;
                }
            });
        })();
    </script>

</x-layouts.admin>
