<x-layouts.admin :title="__('Google integration settings')" admin-key="settings" page="admin-settings-google-calendar">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Google') }}</span>
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

        <form method="POST" action="{{ route('admin.settings.google-calendar.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Google') }}
                        <span class="italic text-wa-deep">{{ __('integration') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Configure one Google Cloud OAuth app for the whole platform. Workspaces then connect their own Google account once — unlocking Appointments scheduling, Google Meet links, and the Google Sheets / Docs / Forms flow nodes.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings/integration') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All integrations') }}</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            {{-- What a single OAuth app unlocks --}}
            <section class="bg-wa-bubble border border-wa-green/30 rounded-2xl px-5 py-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep/80 mb-2">
                    {{ __('What this unlocks') }}</div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-[12px]">
                    <div class="flex items-start gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 text-wa-deep shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <rect x="2.5" y="3" width="11" height="10.5" rx="1.5" />
                            <path d="M2.5 6h11M5.5 1.5v3M10.5 1.5v3" />
                        </svg>
                        <span><b>{{ __('Appointments') }}</b><br>{{ __('free/busy slots') }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 text-wa-deep shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="4.5" width="8" height="7" rx="1.5" />
                            <path d="m10 7 4-2v6l-4-2z" />
                        </svg>
                        <span><b>{{ __('Google Meet') }}</b><br>{{ __('auto video links') }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 text-wa-deep shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="2" width="10" height="12" rx="1.5" />
                            <path d="M5.5 6h5M5.5 8.5h5M5.5 11h3" />
                        </svg>
                        <span><b>{{ __('Sheets') }}</b><br>{{ __('append rows') }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 text-wa-deep shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <path d="M4 2h5l3 3v9H4z" />
                            <path d="M9 2v3h3" />
                        </svg>
                        <span><b>{{ __('Docs') }}</b><br>{{ __('generate copies') }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 text-wa-deep shrink-0" fill="none"
                            stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="2.5" width="10" height="11" rx="1.5" />
                            <path d="M5.5 6h5M5.5 9h5" />
                        </svg>
                        <span><b>{{ __('Forms') }}</b><br>{{ __('capture replies') }}</span>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- Step-by-step "How to create your Google OAuth app". --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Getting started') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">
                                {{ __('How to create your Google OAuth app') }}</h2>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">
                                {{ __('Follow these five steps in the Google Cloud Console to issue the credentials you paste below. This only happens once for the whole platform — every workspace then connects its own Google account via OAuth.') }}
                            </p>
                        </div>

                        <ol class="divide-y divide-paper-100">
                            {{-- Step 1 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">1</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Create a Google Cloud project') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        {{ __('Go to') }} <a href="https://console.cloud.google.com/"
                                            target="_blank" rel="noopener"
                                            class="text-wa-deep font-medium underline">{{ __('console.cloud.google.com') }}</a>
                                        {{ __('and create a project (or pick an existing one).') }}
                                    </p>
                                </div>
                            </li>

                            {{-- Step 2 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">2</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Enable the APIs') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        {{ __('In') }} <span
                                            class="font-mono text-ink-900">{{ __('APIs & Services → Library') }}</span>,
                                        {{ __('enable') }} <b>{{ __('Google Calendar API') }}</b>,
                                        <b>{{ __('Sheets API') }}</b>, <b>{{ __('Docs API') }}</b>,
                                        <b>{{ __('Drive API') }}</b> {{ __('and') }}
                                        <b>{{ __('Forms API') }}</b>.
                                        {{ __('A scope whose API is not enabled fails at runtime with') }} <span
                                            class="font-mono text-accent-coral">{{ __('accessNotConfigured') }}</span>.
                                    </p>
                                </div>
                            </li>

                            {{-- Step 3 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">3</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Configure the consent screen') }}
                                    </div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        {{ __('Under') }} <span
                                            class="font-mono text-ink-900">{{ __('OAuth consent screen') }}</span>,
                                        {{ __('set the app name + support email and add the same scopes you paste below. Add test users while in "Testing", or publish the app for any Google account to connect.') }}
                                    </p>
                                </div>
                            </li>

                            {{-- Step 4 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">4</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">
                                        {{ __('Create an OAuth client & set the redirect URI') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        {{ __('In') }} <span
                                            class="font-mono text-ink-900">{{ __('Credentials → Create credentials → OAuth client ID') }}</span>
                                        ({{ __('type') }} <span
                                            class="font-mono">{{ __('Web application') }}</span>),
                                        {{ __('paste this exactly under') }} <span
                                            class="font-mono text-ink-900">{{ __('Authorized redirect URIs') }}</span>
                                        — {{ __('it must match or OAuth fails with') }} <span
                                            class="font-mono text-accent-coral">{{ __('redirect_uri_mismatch') }}</span>:
                                    </p>
                                    <div class="mt-2 flex gap-2">
                                        <input value="{{ $redirectUri }}" readonly id="copy-redirect"
                                            class="flex-1 rounded-lg border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] font-mono">
                                        <button type="button" data-copy="copy-redirect"
                                            class="rounded-lg border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[11.5px] font-medium">{{ __('Copy') }}</button>
                                    </div>
                                    <p class="text-[11px] text-ink-500 mt-1.5 leading-relaxed">
                                        <span class="font-mono text-accent-amber">{{ __('Note:') }}</span>
                                        {{ __('production redirect URIs must be HTTPS. Then copy the generated') }}
                                        <span class="text-wa-deep font-medium">{{ __('Client ID') }}</span>
                                        {{ __('and') }} <span
                                            class="text-wa-deep font-medium">{{ __('Client secret') }}</span>
                                        {{ __('into the box below.') }}
                                    </p>
                                </div>
                            </li>

                            {{-- Step 5 --}}
                            <li class="px-5 py-4 flex items-start gap-4">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-bubble text-wa-deep grid place-items-center font-mono text-[12px] font-semibold shrink-0">5</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[13px]">{{ __('Enable Google here & save') }}</div>
                                    <p class="text-[12px] text-ink-600 mt-1 leading-relaxed">
                                        {{ __('Tick the') }} <span
                                            class="font-mono text-ink-900">{{ __('Enable Google') }}</span>
                                        {{ __('switch in the credentials box and hit') }} <span
                                            class="text-wa-deep font-medium">{{ __('Save changes') }}</span>.
                                        {{ __('Workspace owners can then connect their Google account at') }}
                                        <a href="{{ url('/google-account') }}" target="_blank"
                                            class="text-wa-deep font-medium underline">/google-account</a>.
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('google · oauth credentials') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('App credentials') }}</h2>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable Google') }}</span>
                                <input type="hidden" name="google_calendar_enabled" value="0">
                                <input type="checkbox" name="google_calendar_enabled" value="1"
                                    @checked($enabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client ID') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="google_calendar_client_id"
                                    value="{{ old('google_calendar_client_id', $clientId) }}"
                                    placeholder="000000000000-xxxxxxxxxxxxxxxx.apps.googleusercontent.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Found in Google Cloud Console → Credentials → your OAuth client.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client secret') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="password" name="google_calendar_client_secret"
                                    value="{{ old('google_calendar_client_secret') }}"
                                    placeholder="{{ $hasSecret ? __('saved — leave blank to keep') : 'GOCSPX-xxxxxxxxxxxxxxxxxxxx' }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Stored encrypted; never rendered back. Leave blank to keep the saved one. Rotate it in Google Cloud if it leaks.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('OAuth scopes') }}</span>
                                <textarea name="google_calendar_scopes" rows="3"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[12px] font-mono focus:outline-none focus:border-wa-deep">{{ old('google_calendar_scopes', $scopes) }}</textarea>
                                <span class="text-[11px] text-ink-500">
                                    {{ __('Space-separated (Google requirement — not commas). The default bundles Calendar + Sheets + Docs + Drive.file + Forms so one consent unlocks every flow node. Drop scopes you do not want — but the consent screen must list the same set.') }}
                                </span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Redirect URI') }}</span>
                                <div class="flex gap-2">
                                    <input name="google_calendar_redirect_uri"
                                        value="{{ old('google_calendar_redirect_uri', $redirectUri) }}"
                                        id="redirect-uri"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    <button type="button" data-copy="redirect-uri"
                                        class="rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[12px]">{{ __('Copy') }}</button>
                                </div>
                                <span
                                    class="text-[11px] text-ink-500">{{ __("Must match a URI in your OAuth client's Authorized redirect URIs. Leave blank to auto-generate from the app URL.") }}</span>
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
                                    class="rounded-full px-3 py-1.5 text-center inline-flex items-center justify-center gap-1.5 {{ $ok ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-500 border border-paper-200' }}">
                                    @if ($ok)
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path d="m4 8 3 3 5-6" />
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <circle cx="8" cy="8" r="5.5" />
                                        </svg>
                                    @endif
                                    {{ $label }}
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
                                    {{ __('Accounts connected') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 tabular-nums">
                                    {{ number_format($connectedCount) }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Platform status') }}</div>
                                <div class="font-serif text-[20px] leading-none mt-2">
                                    {{ $enabled ? __('Enabled') : __('Disabled') }}</div>
                            </div>
                            <div>
                                <div class="font-mono text-[10px] uppercase text-ink-500 tracking-wide">
                                    {{ __('Credentials') }}</div>
                                <div class="font-serif text-[20px] leading-none mt-2">
                                    {{ $clientId !== '' && $hasSecret ? __('Set') : __('Missing') }}</div>
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
                                        href="https://console.cloud.google.com/apis/credentials" target="_blank"
                                        rel="noopener" class="text-wa-deep underline">{{ __('Cloud Console') }}</a>
                                    → <span class="font-mono text-[11px]">{{ __('Credentials') }}</span> →
                                    {{ __('your OAuth client.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Scopes') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Space-separated, and must match the consent screen. Adding scopes later forces every workspace to re-authorize.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Tokens') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Access tokens last ~1 hour and refresh automatically; refresh tokens are stored encrypted per workspace. Google only returns a refresh token with access_type=offline + prompt=consent, which WaDesk always sends.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('What syncs') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Bookings create Calendar events (with Meet links), and flow nodes append to Sheets, generate Docs, and read Form responses.') }}
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
                            <a href="https://developers.google.com/identity/protocols/oauth2/web-server"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('OAuth 2.0 web server flow →') }}</a>
                            <a href="https://developers.google.com/calendar/api/guides/overview" target="_blank"
                                rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Calendar API →') }}</a>
                            <a href="https://developers.google.com/identity/protocols/oauth2/scopes" target="_blank"
                                rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Scopes reference →') }}</a>
                            <a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank"
                                rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Consent screen →') }}</a>
                        </div>
                    </div>

                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Test before launch') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Connect a test Google account at /google-account, book a test appointment, and confirm a Calendar event with a Meet link is created before enabling for everyone.') }}
                        </p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>
