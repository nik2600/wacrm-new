<x-layouts.admin :title="__('Social login & captcha')" admin-key="settings" page="admin-settings-social-login">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Social login') }}</span>
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

        <form method="POST" action="{{ route('admin.settings.social-login.update') }}" class="space-y-5">
            @csrf

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Authentication') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                        {{ __('Social login') }} <span class="italic text-wa-deep">{{ __('& captcha') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Let users sign in with Google or Facebook, and protect the login & register pages with Google reCAPTCHA. Each provider only appears on the auth pages once you enable it and paste its keys below.') }}
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

                    {{-- ─────────────────────────── GOOGLE ─────────────────────────── --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-paper-50 border border-paper-200 grid place-items-center">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4">
                                        <path fill="#4285F4"
                                            d="M15.6 8.18c0-.55-.05-1.07-.14-1.58H8v3h4.27c-.18.97-.74 1.79-1.58 2.34v1.94h2.55c1.5-1.38 2.36-3.41 2.36-5.7z" />
                                        <path fill="#34A853"
                                            d="M8 16c2.13 0 3.92-.71 5.23-1.92l-2.55-1.97c-.71.47-1.61.75-2.68.75-2.06 0-3.81-1.39-4.43-3.27H1v2.05A8 8 0 0 0 8 16z" />
                                        <path fill="#FBBC05"
                                            d="M3.57 9.59A4.8 4.8 0 0 1 3.32 8c0-.55.09-1.09.25-1.59V4.36H1A8 8 0 0 0 0 8c0 1.29.31 2.51.86 3.59L3.57 9.59z" />
                                        <path fill="#EA4335"
                                            d="M8 3.16c1.16 0 2.2.4 3.02 1.18L13.27 2.1A8 8 0 0 0 8 0a8 8 0 0 0-7 4.36l2.57 2.05C4.19 4.55 5.94 3.16 8 3.16z" />
                                    </svg>
                                </span>
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('OAuth 2.0 · Google Cloud') }}</div>
                                    <h2 class="font-serif text-[24px] leading-tight">{{ __('Google sign-in') }}</h2>
                                </div>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <input type="hidden" name="social_google_enabled" value="0">
                                <input type="checkbox" name="social_google_enabled" value="1"
                                    @checked($googleEnabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client ID') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="social_google_client_id"
                                    value="{{ old('social_google_client_id', $googleClientId) }}"
                                    placeholder="000000000000-xxxxxxxx.apps.googleusercontent.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('From Google Cloud → APIs & Services → Credentials → OAuth client ID (Web application).') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Client secret') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="password" name="social_google_client_secret" value=""
                                    placeholder="{{ $googleHasSecret ? __('•••••••• saved — leave blank to keep') : 'GOCSPX-xxxxxxxxxxxxxxxx' }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Stored encrypted; never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Authorized redirect URI') }}</span>
                                <div class="flex gap-2">
                                    <input value="{{ $googleRedirect }}" readonly id="copy-google-redirect"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    <button type="button" data-copy="copy-google-redirect"
                                        class="rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[12px]">{{ __('Copy') }}</button>
                                </div>
                                <span
                                    class="text-[11px] text-ink-500">{{ __("Paste this exactly into the OAuth client's Authorized redirect URIs, or Google rejects the sign-in with redirect_uri_mismatch.") }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- ────────────────────────── FACEBOOK ────────────────────────── --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-paper-50 border border-paper-200 grid place-items-center">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="#1877F2">
                                        <path
                                            d="M16 8a8 8 0 1 0-9.25 7.9v-5.59H4.72V8h2.03V6.24c0-2 1.2-3.11 3.02-3.11.87 0 1.79.16 1.79.16v1.97H10.55c-.99 0-1.3.62-1.3 1.25V8h2.22l-.36 2.31H9.25v5.59A8 8 0 0 0 16 8z" />
                                    </svg>
                                </span>
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Facebook Login · Meta for Developers') }}</div>
                                    <h2 class="font-serif text-[24px] leading-tight">{{ __('Facebook sign-in') }}</h2>
                                </div>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <input type="hidden" name="social_facebook_enabled" value="0">
                                <input type="checkbox" name="social_facebook_enabled" value="1"
                                    @checked($fbEnabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('App ID') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="social_facebook_client_id"
                                    value="{{ old('social_facebook_client_id', $fbClientId) }}"
                                    placeholder="0000000000000000"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('From developers.facebook.com → your app → Settings → Basic → App ID.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('App secret') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="password" name="social_facebook_client_secret" value=""
                                    placeholder="{{ $fbHasSecret ? __('•••••••• saved — leave blank to keep') : __('32-character app secret') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Stored encrypted; never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Valid OAuth Redirect URI') }}</span>
                                <div class="flex gap-2">
                                    <input value="{{ $fbRedirect }}" readonly id="copy-fb-redirect"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    <button type="button" data-copy="copy-fb-redirect"
                                        class="rounded-xl border border-paper-200 bg-paper-0 hover:bg-paper-50 px-3 text-[12px]">{{ __('Copy') }}</button>
                                </div>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Add this under Facebook Login → Settings → Valid OAuth Redirect URIs. Add the Facebook Login product and request the email + public_profile permissions.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- ────────────────────────── reCAPTCHA ───────────────────────── --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-paper-50 border border-paper-200 grid place-items-center text-wa-deep">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="M8 1.5 3 3.5v4c0 3 2.1 5.4 5 6.5 2.9-1.1 5-3.5 5-6.5v-4L8 1.5Z" />
                                        <path d="m5.8 8 1.6 1.6L10.4 6.5" />
                                    </svg>
                                </span>
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Google reCAPTCHA · bot protection') }}</div>
                                    <h2 class="font-serif text-[24px] leading-tight">{{ __('reCAPTCHA') }}</h2>
                                </div>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Enable') }}</span>
                                <input type="hidden" name="recaptcha_enabled" value="0">
                                <input type="checkbox" name="recaptcha_enabled" value="1"
                                    @checked($reEnabled) class="w-5 h-5 accent-wa-deep">
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Version') }}</span>
                                <select name="recaptcha_version"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="v2" @selected($reVersion === 'v2')>
                                        {{ __('v2 — "I\'m not a robot" checkbox') }}</option>
                                    <option value="v3" @selected($reVersion === 'v3')>
                                        {{ __('v3 — invisible score') }}</option>
                                </select>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('v2 shows a checkbox. v3 is invisible and scores each request.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('v3 score threshold') }}</span>
                                <input name="recaptcha_v3_threshold"
                                    value="{{ old('recaptcha_v3_threshold', $reThreshold) }}" type="number"
                                    step="0.1" min="0" max="1" placeholder="0.5"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('v3 only. 0.0 = likely bot, 1.0 = likely human. Google suggests 0.5. Ignored for v2.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Site key') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="recaptcha_site_key" value="{{ old('recaptcha_site_key', $reSiteKey) }}"
                                    placeholder="6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Public key (sent to the browser). From the reCAPTCHA admin console.') }}</span>
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Secret key') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input type="password" name="recaptcha_secret" value=""
                                    placeholder="{{ $reHasSecret ? __('•••••••• saved — leave blank to keep') : '6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Server key used to verify tokens. Stored encrypted; never rendered back. Leave blank to keep the saved one.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- Install readiness --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('install-status') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('What\'s live right now') }}
                            </h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3 text-[11px] font-mono">
                            @php
                                $checks = [
                                    'Google live' => $googleEnabled && $googleClientId !== '' && $googleHasSecret,
                                    'Facebook live' => $fbEnabled && $fbClientId !== '' && $fbHasSecret,
                                    'reCAPTCHA live' => $reEnabled && $reSiteKey !== '' && $reHasSecret,
                                ];
                            @endphp
                            @foreach ($checks as $label => $ok)
                                <span
                                    class="rounded-full px-3 py-1.5 text-center flex items-center justify-center gap-1.5 {{ $ok ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-500 border border-paper-200' }}">
                                    @if ($ok)
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2.2">
                                            <path d="M3 8l3 3 7-7" />
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <circle cx="8" cy="8" r="5" />
                                        </svg>
                                    @endif
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                        <p class="px-5 pb-4 -mt-1 text-[11px] text-ink-500">
                            {{ __('A provider only shows on the login & register pages when it is enabled and both keys are saved.') }}
                        </p>
                    </section>
                </div>

                {{-- ── Right rail: setup guide + docs ── --}}
                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Quick setup') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Google in 4 steps') }}</h3>
                        </div>
                        <ol class="p-4 space-y-2.5 text-[12px] text-ink-700 list-decimal list-inside">
                            <li>{{ __('Open Google Cloud Console → create / pick a project.') }}</li>
                            <li>{{ __('Configure the OAuth consent screen (External, add your email).') }}</li>
                            <li>{{ __('Credentials → Create OAuth client ID → Web application.') }}</li>
                            <li>{{ __('Paste the redirect URI above, then copy the Client ID + secret here.') }}</li>
                        </ol>
                        <div class="px-4 pb-4">
                            <a href="https://console.cloud.google.com/apis/credentials" target="_blank"
                                rel="noopener"
                                class="block text-[11.5px] text-wa-deep hover:underline">{{ __('Google Cloud credentials →') }}</a>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Quick setup') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Facebook in 4 steps') }}
                            </h3>
                        </div>
                        <ol class="p-4 space-y-2.5 text-[12px] text-ink-700 list-decimal list-inside">
                            <li>{{ __('Open Meta for Developers → create an app (Consumer / Authenticate).') }}</li>
                            <li>{{ __('Add the "Facebook Login" product.') }}</li>
                            <li>{{ __('Login → Settings → add the redirect URI above.') }}</li>
                            <li>{{ __('Settings → Basic → copy App ID + App secret here, then set the app Live.') }}
                            </li>
                        </ol>
                        <div class="px-4 pb-4">
                            <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener"
                                class="block text-[11.5px] text-wa-deep hover:underline">{{ __('Meta for Developers →') }}</a>
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
                                class="block text-wa-deep hover:underline">{{ __('Google OAuth 2.0 →') }}</a>
                            <a href="https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow"
                                target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('Facebook manual login flow →') }}</a>
                            <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('reCAPTCHA admin console →') }}</a>
                            <a href="https://developers.google.com/recaptcha/docs/v3" target="_blank" rel="noopener"
                                class="block text-wa-deep hover:underline">{{ __('reCAPTCHA v3 docs →') }}</a>
                        </div>
                    </div>

                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('No SDK required') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Sign-in runs through the standard OAuth endpoints directly — no extra packages to install. Just paste the keys and enable.') }}
                        </p>
                    </div>
                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>
