<x-layouts.admin :title="__('Shopify settings')" admin-key="settings" page="admin-settings-shopify">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Shopify') }}</span>
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
        @if (session('success'))
            <div
                class="px-4 py-2.5 rounded-xl bg-wa-bubble border border-wa-green/30 text-[12.5px] text-wa-deep flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="m4 8 3 3 5-6" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if (isset($errors) && $errors->any())
            <div
                class="px-4 py-2.5 rounded-xl bg-accent-coral/10 border border-accent-coral/30 text-[12.5px] text-accent-coral">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.shopify.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Shopify') }}
                        <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Configure Shopify app credentials so workspaces can connect their stores and receive WhatsApp notifications for orders, customers, and abandoned checkouts.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- ───────────────────────────────────────────────────────
 Step-by-step "How to create your Shopify app" guide.
 Mirrors the setup-steps pattern on /shopify so admins
 see the same affordances customers see.
 ─────────────────────────────────────────────────────── --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Getting started') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">
                                {{ __('How to create your Shopify app') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Follow these six steps on the Shopify Dev Dashboard to issue the credentials you paste below. This only needs to happen once for the whole platform — every workspace then uses the same app via OAuth.') }}
                            </p>
                        </div>

                        <ol class="divide-y divide-paper-100">
                            {{-- Step 1 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">1</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">
                                        {{ __('Create a Shopify Partners account') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Go to <a href="https://partners.shopify.com/signup" target="_blank"
                                            rel="noopener"
                                            class="text-wa-deep font-medium underline">{{ __('partners.shopify.com/signup') }}</a>
                                        and sign in with the email that will own this integration. Partners accounts are
                                        free.
                                    </p>
                                </div>
                            </li>

                            {{-- Step 2 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">2</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">
                                        {{ __('Open the Dev Dashboard and create an app') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        From your Partner account open the
                                        <a href="https://shopify.dev/dashboard" target="_blank" rel="noopener"
                                            class="text-wa-deep font-medium underline">{{ __('Shopify Dev Dashboard') }}</a>
                                        →
                                        <span class="font-mono text-ink-900">{{ __('Apps → Create app') }}</span>.
                                        Pick <b>Public app</b> (so any Shopify merchant can install). Name it after your
                                        platform.
                                    </p>
                                    <p class="text-[11px] text-ink-500 mt-1.5 leading-relaxed">
                                        <span class="font-mono text-accent-amber">{{ __('Note (2026):') }}</span>
                                        legacy custom apps created from <span
                                            class="font-mono">{{ __('Shopify Admin → Apps') }}</span> can no longer be
                                        created since Jan 1, 2026 — you must use the Dev Dashboard or Shopify CLI.
                                    </p>
                                </div>
                            </li>

                            {{-- Step 3 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">3</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">
                                        {{ __('Set the App URL and Allowed redirection URL') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        In the app's <span
                                            class="font-mono text-ink-900">{{ __('Configuration') }}</span> tab, scroll
                                        to <span class="font-mono text-ink-900">{{ __('URLs') }}</span>. Paste these
                                        exactly — the redirect URL must match what we send to Shopify or OAuth fails
                                        with <span
                                            class="font-mono text-accent-coral">{{ __('redirect_uri mismatch') }}</span>:
                                    </p>
                                    <div class="mt-2 space-y-2">
                                        <div>
                                            <div class="font-mono text-[10px] uppercase tracking-wide text-ink-500">
                                                {{ __('App URL') }}</div>
                                            <div class="flex gap-2 mt-1">
                                                <input value="{{ url('/shopify') }}" readonly id="copy-app-url"
                                                    class="flex-1 rounded-lg border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] font-mono">
                                                <button type="button" data-copy="copy-app-url"
                                                    class="rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[11.5px] font-medium">{{ __('Copy') }}</button>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-mono text-[10px] uppercase tracking-wide text-ink-500">
                                                {{ __('Allowed redirection URL(s)') }}</div>
                                            <div class="flex gap-2 mt-1">
                                                <input value="{{ url('/shopify/oauth/callback') }}" readonly
                                                    id="copy-redirect"
                                                    class="flex-1 rounded-lg border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] font-mono">
                                                <button type="button" data-copy="copy-redirect"
                                                    class="rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[11.5px] font-medium">{{ __('Copy') }}</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>

                            {{-- Step 4 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">4</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Copy the API credentials') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Open <span
                                            class="font-mono text-ink-900">{{ __('Configuration → Client credentials') }}</span>.
                                        Reveal both values and paste them into
                                        <span class="text-wa-deep font-medium">{{ __('Client ID') }}</span> (a.k.a.
                                        <span class="font-mono">{{ __('API Key') }}</span>) and
                                        <span class="text-wa-deep font-medium">{{ __('Client secret') }}</span>
                                        (a.k.a. <span class="font-mono">{{ __('API Secret Key') }}</span>) below.
                                        The secret is shown once — copy it carefully.
                                    </p>
                                </div>
                            </li>

                            {{-- Step 5 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">5</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Add mandatory GDPR webhooks') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        In <span
                                            class="font-mono text-ink-900">{{ __('Configuration → Compliance webhooks') }}</span>,
                                        set all three URLs to the endpoint below.
                                        Shopify <b>requires</b> these before App Store submission and silently fails
                                        install otherwise.
                                    </p>
                                    <div class="mt-2 flex gap-2">
                                        <input value="{{ url('/shopify/webhook/{webhook_secret}') }}" readonly
                                            id="copy-gdpr"
                                            class="flex-1 rounded-lg border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] font-mono">
                                        <button type="button" data-copy="copy-gdpr"
                                            class="rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[11.5px] font-medium">{{ __('Copy') }}</button>
                                    </div>
                                    <div class="mt-1.5 text-[11px] text-ink-500 leading-relaxed">
                                        Topics: <span
                                            class="font-mono text-ink-700">{{ __('customers/data_request') }}</span>,
                                        <span class="font-mono text-ink-700">{{ __('customers/redact') }}</span>,
                                        <span class="font-mono text-ink-700">{{ __('shop/redact') }}</span>.
                                        Each Shopify webhook is HMAC-SHA256 signed with your Client Secret — we verify
                                        <span class="font-mono">{{ __('X-Shopify-Hmac-Sha256') }}</span> on every
                                        delivery.
                                    </div>
                                </div>
                            </li>

                            {{-- Step 6 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">6</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Enable Shopify here & save') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Tick the <span
                                            class="font-mono text-ink-900">{{ __('Enable Shopify') }}</span> switch in
                                        the credentials box and hit <span
                                            class="text-wa-deep font-medium">{{ __('Save changes') }}</span>.
                                        Workspace owners can now connect their stores at
                                        <a href="{{ url('/shopify') }}" target="_blank"
                                            class="text-wa-deep font-medium underline">/shopify</a>.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('shopify · oauth credentials') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('App credentials') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable Shopify') }}</span>
                                <input type="hidden" name="shopify_enabled" value="0">
                                <input type="checkbox" name="shopify_enabled" value="1"
                                    @checked($enabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client ID') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="shopify_client_id" value="{{ old('shopify_client_id', $clientId) }}"
                                    placeholder="1c8c228159afb0074aed13ba73958eaf"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __("Also known as the API Key. Found in your Shopify app's API credentials tab.") }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client secret') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="password" name="shopify_client_secret"
                                    value="{{ old('shopify_client_secret') }}"
                                    placeholder="{{ $hasSecret ? __('•••••••• saved — leave blank to keep') : __('shpss_xxxxxxxxxxxxxxxxxxxxxxxx') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Also known as the API Secret Key. Stored encrypted; never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('API access scopes') }}</span>
                                <input name="shopify_scopes" value="{{ old('shopify_scopes', $scopes) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">
                                    Comma-separated.
                                    Default <span
                                        class="font-mono">{{ __('read_products,read_orders,write_orders,read_customers,read_checkouts,read_inventory') }}</span>
                                    is enough for order/customer messaging + product sync.
                                    Note: <span class="font-mono">{{ __('read_orders') }}</span> only exposes the last
                                    60 days — request <span class="font-mono">{{ __('read_all_orders') }}</span> from
                                    Shopify if you need historical data.
                                    Changing scopes forces every store to re-authorize.
                                </span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Redirect URI') }}</span>
                                <div class="flex gap-2">
                                    <input name="shopify_redirect_uri"
                                        value="{{ old('shopify_redirect_uri', $redirectUri) }}" id="redirect-uri"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    <button type="button" data-copy="redirect-uri"
                                        class="rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[12px]">{{ __('Copy') }}</button>
                                </div>
                                <span class="text-[11px] text-ink-500">Paste this in your Shopify app's "Allowed
                                    redirection URL(s)" field. Leave blank to auto-generate.</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Webhook endpoint') }} <span
                                        class="text-ink-500 font-normal">(auto-generated)</span></span>
                                <div class="flex gap-2">
                                    <input value="{{ url('/shopify/webhook/{webhook_secret}') }}" readonly
                                        id="webhook-url"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono">
                                    <button type="button" data-copy="webhook-url"
                                        class="rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[12px]">{{ __('Copy') }}</button>
                                </div>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Each connected store gets its own webhook secret — this is the URL pattern.') }}</span>
                            </label>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('install-status') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Install readiness') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3 text-[11px] font-mono">
                            @php
                                $checks = [
                                    'credentials saved' => $clientId !== '' && $hasSecret,
                                    'feature enabled' => $enabled,
                                    'redirect set' => $redirectUri !== '',
                                ];
                            @endphp
                            @foreach ($checks as $label => $ok)
                                <span
                                    class="rounded-full px-3 py-1.5 text-center {{ $ok ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-500 border border-paper-200' }}">
                                    {{ $ok ? '✓ ' : '○ ' }}{{ $label }}
                                </span>
                            @endforeach
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('usage') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Live across workspaces') }}
                            </h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Stores connected') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($integrationsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Active automations') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($eventsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Webhook events received') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($logsCount) }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Where to find these') }}
                            </h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">
                                    {{ __('Client ID / Client Secret') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('In the') }} <a
                                        href="https://shopify.dev/dashboard" target="_blank" rel="noopener"
                                        class="text-wa-deep underline">{{ __('Dev Dashboard') }}</a> → your app →
                                    <span
                                        class="font-mono text-[11px]">{{ __('Configuration → Client credentials') }}</span>.
                                    Rotate the secret if it leaks.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Access scopes') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('See') }} <a
                                        href="https://shopify.dev/docs/api/usage/access-scopes" target="_blank"
                                        rel="noopener"
                                        class="text-wa-deep underline">{{ __('access-scopes docs') }}</a>. Adding
                                    scopes after install requires every store to re-authorize.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Redirect URI') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Must match') }} <span
                                        class="font-mono text-[11px]">{{ __('Allowed redirection URL(s)') }}</span>
                                    in the app config <b>exactly</b> — protocol, host, path, no trailing slash.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('API version') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('We target the stable') }} <span
                                        class="font-mono text-[11px]">2026-01</span> Admin API. Shopify ships a new
                                    version each quarter; non-breaking on minor releases.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Webhook security') }}
                                </div>
                                <p class="text-ink-600 mt-0.5">{{ __('Every incoming Shopify webhook carries') }}
                                    <span class="font-mono text-[11px]">{{ __('X-Shopify-Hmac-Sha256') }}</span>; we
                                    verify against the raw request body using your Client Secret and reject mismatches
                                    with <span class="font-mono text-[11px]">401</span>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Reference') }}</div>
                            <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Official docs') }}</h3>
                        </div>
                        <div class="p-4 space-y-1.5 text-[11.5px]">
                            <a href="https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/authorization-code-grant"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('OAuth authorization code grant →') }}</a>
                            <a href="https://shopify.dev/docs/apps/build/webhooks/subscribe/https" target="_blank"
                                rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('HTTPS webhook delivery →') }}</a>
                            <a href="https://shopify.dev/docs/apps/build/privacy-law-compliance" target="_blank"
                                rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Privacy law compliance webhooks →') }}</a>
                            <a href="https://shopify.dev/docs/api/usage/versioning" target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('API versioning →') }}</a>
                            <a href="https://changelog.shopify.com/posts/legacy-custom-apps-can-t-be-created-after-january-1-2026"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Legacy custom apps deprecated (Jan 2026) →') }}</a>
                        </div>
                    </div>

                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Test before launch') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Install on a development store first and walk through OAuth + a test order webhook before enabling for paying merchants.') }}
                        </p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

    {{-- Copy-button JS lives in resources/js/charts/admin-settings-shopify.js
 (auto-loaded via the page="admin-settings-shopify" key in app.js). --}}
</x-layouts.admin>
