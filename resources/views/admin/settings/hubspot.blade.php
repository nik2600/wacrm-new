<x-layouts.admin :title="__('HubSpot settings')" admin-key="settings" page="admin-settings-hubspot">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('HubSpot') }}</span>
        </div>
        <div class="relative flex-1 min-w-0 max-w-[520px] ml-4">
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

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
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

        <form method="POST" action="{{ route('admin.settings.hubspot.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('HubSpot') }}
                        <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Configure your HubSpot app credentials once for the whole platform. Workspaces then connect their own HubSpot portal via OAuth — WaDesk pushes WhatsApp customers and orders into HubSpot as contacts and deals.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings/integration') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All integrations') }}</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- ───────────────────────────────────────────────────────
 Step-by-step "How to create your HubSpot app" guide.
 ─────────────────────────────────────────────────────── --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Getting started') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">
                                {{ __('How to create your HubSpot app') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Follow these five steps on the HubSpot developer portal to issue the credentials you paste below. This only happens once for the whole platform — every workspace then connects its own HubSpot account via OAuth.') }}
                            </p>
                        </div>

                        <ol class="divide-y divide-paper-100">
                            {{-- Step 1 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">1</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">
                                        {{ __('Create a HubSpot developer account') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Go to <a href="https://developers.hubspot.com/get-started" target="_blank"
                                            rel="noopener"
                                            class="text-wa-deep font-medium underline">{{ __('developers.hubspot.com') }}</a>
                                        and create a free <b>developer (app) account</b>. This is separate from your
                                        normal HubSpot CRM login.
                                    </p>
                                </div>
                            </li>

                            {{-- Step 2 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">2</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Create an app') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        In the developer account open <span
                                            class="font-mono text-ink-900">{{ __('Apps → Create app') }}</span>. Give
                                        it your platform name and logo (shown to merchants on the OAuth consent screen).
                                    </p>
                                </div>
                            </li>

                            {{-- Step 3 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">3</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Set the redirect URL') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        In the app's <span class="font-mono text-ink-900">{{ __('Auth') }}</span>
                                        tab, under <span
                                            class="font-mono text-ink-900">{{ __('Redirect URLs') }}</span>, paste this
                                        exactly. It must match what we send or OAuth fails with <span
                                            class="font-mono text-accent-coral">{{ __('redirect_uri mismatch') }}</span>:
                                    </p>
                                    <div class="mt-2 flex gap-2">
                                        <input value="{{ url('/hubspot/oauth/callback') }}" readonly id="copy-redirect"
                                            class="flex-1 rounded-lg border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] font-mono">
                                        <button type="button" data-copy="copy-redirect"
                                            class="rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[11.5px] font-medium">{{ __('Copy') }}</button>
                                    </div>
                                    <p class="text-[11px] text-ink-500 mt-1.5 leading-relaxed">
                                        <span class="font-mono text-accent-amber">{{ __('Note:') }}</span>
                                        non-localhost redirect URLs must be <b>HTTPS</b> in production. For local
                                        testing HubSpot allows <span
                                            class="font-mono">{{ __('http://localhost') }}</span> (the literal host
                                        <span class="font-mono">localhost</span>, not an IP).
                                    </p>
                                </div>
                            </li>

                            {{-- Step 4 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">4</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Select scopes & copy credentials') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Still in the <span class="font-mono text-ink-900">{{ __('Auth') }}</span>
                                        tab, add the scopes below under <span
                                            class="font-mono text-ink-900">{{ __('Scopes') }}</span>, then copy the
                                        <span class="text-wa-deep font-medium">{{ __('Client ID') }}</span> and
                                        <span class="text-wa-deep font-medium">{{ __('Client secret') }}</span> into
                                        the box below.
                                        The scope list in the app and the scopes you paste here must match.
                                    </p>
                                </div>
                            </li>

                            {{-- Step 5 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">5</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Enable HubSpot here & save') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        Tick the <span
                                            class="font-mono text-ink-900">{{ __('Enable HubSpot') }}</span> switch in
                                        the credentials box and hit <span
                                            class="text-wa-deep font-medium">{{ __('Save changes') }}</span>.
                                        Workspace owners can now connect their HubSpot account at
                                        <a href="{{ url('/hubspot') }}" target="_blank"
                                            class="text-wa-deep font-medium underline">/hubspot</a>.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('hubspot · oauth credentials') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('App credentials') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable HubSpot') }}</span>
                                <input type="hidden" name="hubspot_enabled" value="0">
                                <input type="checkbox" name="hubspot_enabled" value="1"
                                    @checked($enabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client ID') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="hubspot_client_id" value="{{ old('hubspot_client_id', $clientId) }}"
                                    placeholder="00000000-0000-0000-0000-000000000000"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __("Found in your HubSpot app's Auth tab.") }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client secret') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="password" name="hubspot_client_secret"
                                    value="{{ old('hubspot_client_secret') }}"
                                    placeholder="{{ $hasSecret ? __('•••••••• saved — leave blank to keep') : __('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Stored encrypted; never rendered back. Leave blank to keep the saved one. Rotate it in HubSpot if it leaks.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('OAuth scopes') }}</span>
                                <input name="hubspot_scopes" value="{{ old('hubspot_scopes', $scopes) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">
                                    {{ __('Space-separated (HubSpot requirement — not commas).') }}
                                    {{ __('Default') }} <span
                                        class="font-mono">{{ __('crm.objects.contacts.read crm.objects.contacts.write crm.objects.deals.read crm.objects.deals.write') }}</span>
                                    {{ __('covers contact + deal sync. These must exactly match the scopes selected in the app, or the connect fails.') }}
                                </span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Redirect URI') }}</span>
                                <div class="flex gap-2">
                                    <input name="hubspot_redirect_uri"
                                        value="{{ old('hubspot_redirect_uri', $redirectUri) }}" id="redirect-uri"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    <button type="button" data-copy="redirect-uri"
                                        class="rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[12px]">{{ __('Copy') }}</button>
                                </div>
                                <span
                                    class="text-[11px] text-ink-500">{{ __("Paste this in your HubSpot app's Redirect URLs (Auth tab). Leave blank to auto-generate.") }}</span>
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
                                    {{ __('Portals connected') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($integrationsCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Active connections') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($activeCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Records pushed') }}</div>
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
                                        href="https://developers.hubspot.com" target="_blank" rel="noopener"
                                        class="text-wa-deep underline">{{ __('developer portal') }}</a> → your app →
                                    <span class="font-mono text-[11px]">{{ __('Auth') }}</span>.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Scopes') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Space-separated, and must match the app exactly. Adding scopes later forces every workspace to re-authorize.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Tokens') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Access tokens last 30 minutes and refresh automatically; refresh tokens are stored encrypted and long-lived.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('What syncs') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Each new WhatsApp order creates/updates a HubSpot contact (deduped by email) and an associated deal in the default pipeline.') }}
                                </p>
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
                            <a href="https://developers.hubspot.com/docs/guides/api/app-management/oauth-tokens"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('OAuth tokens guide →') }}</a>
                            <a href="https://developers.hubspot.com/docs/apps/developer-platform/build-apps/authentication/scopes"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Scopes reference →') }}</a>
                            <a href="https://developers.hubspot.com/docs/api-reference/crm-contacts-v3/guide"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Contacts API →') }}</a>
                            <a href="https://developers.hubspot.com/docs/api-reference/latest/crm/associations/associate-records/guide"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Associations →') }}</a>
                        </div>
                    </div>

                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Test before launch') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Connect a test HubSpot account at /hubspot, place a test storefront order, and confirm a contact + deal appears in HubSpot before enabling for everyone.') }}
                        </p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>
