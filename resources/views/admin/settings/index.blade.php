<x-layouts.admin :title="__('Settings')" admin-key="settings">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Settings') }}</span>
        </div>

        <div class="relative flex-1 min-w-0 max-w-[520px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search project settings...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>

        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Project settings') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Project') }}
                    <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Configure app identity, messaging providers, mail, e-commerce integrations, storefront metadata, footer content, PWA, SEO, and custom code.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                {{-- Add-ons sit BEFORE the Updater: you add a feature far more
                     often than you replace the whole build, and both are the
                     same operator job (bring new code in) behind the same
                     licence gate.

                     NOTE: this "Add-ons" installer (code) is distinct from the
                     BILLING add-ons at /admin/addons (feature packs a customer
                     buys on top of a plan). Same word, two surfaces — the
                     installer page links across to disambiguate. --}}
                <a href="{{ route('admin.extensions.index') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="5" height="5" rx="1" />
                        <rect x="9" y="2" width="5" height="5" rx="1" />
                        <rect x="2" y="9" width="5" height="5" rx="1" />
                        <path d="M11.5 9.5v4M9.5 11.5h4" />
                    </svg>
                    {{ __('Add-ons') }}
                </a>
                <a href="{{ route('admin.update.index') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M8 2v7" />
                        <path d="M5 6l3 3 3-3" />
                        <path d="M3 11v1a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1" />
                    </svg>
                    {{ __('Updater') }}
                </a>
                <a href="{{ url('/admin/legal-pages') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M4 2h6l3 3v9H4z" />
                        <path d="M6.5 8h4M6.5 10.5h4M6.5 5.5h2" />
                    </svg>
                    {{ __('Legal pages') }}
                </a>
                <a href="{{ url('/admin/settings/general') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="2" />
                        <path
                            d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z" />
                    </svg>
                    {{ __('Open general') }}
                </a>
                <a href="{{ route('admin.settings.export') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    {{ __('Export config') }}
                </a>
            </div>
        </div>

        {{-- Live KPIs computed from the system_settings table — see
 AdminPagesController::settings() for the math. A fresh
 install reads as 0/0/none/0/—. --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Saved settings') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($kpi['total_rows']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('rows in system_settings') }}</div>
            </div>
            <div
                class="bg-paper-0 border {{ $kpi['healthy'] > 0 ? 'border-wa-green/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Healthy') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($kpi['healthy']) }}</div>
                <div class="text-[11px] {{ $kpi['healthy'] > 0 ? 'text-wa-deep' : 'text-ink-500' }} mt-2">
                    {{ __('with a non-empty value') }}</div>
            </div>
            <div
                class="bg-paper-0 border {{ count($kpi['needs_test']) > 0 ? 'border-accent-amber/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Needs test') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ count($kpi['needs_test']) }}</div>
                <div
                    class="text-[11px] {{ count($kpi['needs_test']) > 0 ? 'text-accent-amber' : 'text-ink-500' }} mt-2">
                    {{ count($kpi['needs_test']) > 0 ? implode(', ', $kpi['needs_test']) : __('all verified') }}
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Secret fields') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">{{ number_format($kpi['secret_count']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('encrypted / masked') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Last updated') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">
                    {{ $kpi['last_updated'] ? \Carbon\Carbon::parse($kpi['last_updated'])->diffForHumans(null, true) : '—' }}
                </div>
                <div class="text-[11px] text-ink-500 mt-2">
                    {{ $kpi['last_updated_by'] ? __('by') . ' ' . $kpi['last_updated_by'] : __('no edits yet') }}</div>
            </div>
        </section>

        {{-- Settings tiles ordered roughly most-used → least-used.
 Reasoning: brand/identity > revenue + AI keys > comms +
 investigation > compliance + tracking > one-time platform
 setup > optional / one-time-only branding. --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            {{-- ─── Appearance — recolour the whole dashboard ─── --}}
            <a href="{{ route('admin.settings.appearance') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-mint text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M8 2a6 6 0 1 0 0 12c1 0 1.4-.8 1-1.6-.4-.8.1-1.6 1-1.6h1A2.5 2.5 0 0 0 14 8 6 6 0 0 0 8 2Z" />
                            <circle cx="5.5" cy="7" r=".7" /><circle cx="8" cy="5" r=".7" /><circle cx="10.5" cy="7" r=".7" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">★</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Appearance & colours') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Recolour every page of the user + admin dashboards — brand, surfaces, text and accents. Applies live.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('theme.color.*') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 01 · General — brand, app name, logos, contact ─── --}}
            <a href="{{ url('/admin/settings/general') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="2" />
                            <path
                                d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">01</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('General settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('App name, contact details, logos, preloader, URL, address, and global service toggles.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('APP_NAME / APP_URL / logos') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 02 · Payment gateways — revenue lifeline ─── --}}
            <a href="{{ url('/admin/payment-gateways') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="4" width="12" height="9" rx="1.5" />
                            <path d="M2 7h12M5 10h2M9 10h2" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">02</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Payment gateways') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Stripe, Razorpay, PayPal, Paystack, Flutterwave, MercadoPago, Square + offline gateways — keys, currencies, and live toggles.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Live / sandbox · per-gateway keys') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 03 · Currencies — pricing display + conversion ─── --}}
            <a href="{{ url('/admin/currencies') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-accent-amber/15 text-[#7B5A14] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path
                                d="M10 5.5c-.7-.5-1.5-.7-2.2-.6-1.1.2-1.5 1-1.4 1.7.1.6.7 1 1.7 1.3 1.5.4 2 1.1 2 1.9 0 .9-.8 1.6-2.1 1.6-.7 0-1.5-.2-2-.5M8 4v1M8 11v1" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">03</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Currencies') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('ISO-4217 list, symbols, decimal places, default + display fallback. Drives every price shown across the platform.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('USD / INR / EUR / GBP · 60+') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 04 · AI / API keys — drives every AI feature ─── --}}
            <a href="{{ url('/admin/api-keys') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#F3E9FF] text-[#5B2E91] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="6" cy="10" r="2.5" />
                            <path d="M8 10l5-5M11 7l1.5 1.5M9.5 5.5L11 4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">04</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('AI / API keys') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Provider credentials for OpenAI, Anthropic, Google, Groq + default models per provider. Drives every AI feature in the platform.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('BYOK / default models') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 05 · Mail — every transactional email depends on it ─── --}}
            <a href="{{ url('/admin/settings/mail') }}"
                class="group bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-accent-amber/15 text-[#7B5A14] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="2" y="4" width="12" height="9" rx="1.5" />
                            <path d="m3 5 5 4 5-4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-accent-amber">{{ __('test') }}</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Mail settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('SMTP driver, host, port, sender name, encryption, welcome mail, verification mail, and test send.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('SMTP / verify / welcome') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 06 · Audit log — debugging + security investigations ─── --}}
            <a href="{{ url('/admin/audit-log') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-paper-100 text-ink-700 grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M2 4h12M2 8h12M2 12h8" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">06</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Audit log') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Searchable history of every admin action — workspace edits, user changes, billing events, security incidents, settings updates.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Filter / detail / CSV export') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 07 · Privacy / GDPR / ADA — compliance + visitor consent ─── --}}
            <a href="{{ url('/admin/settings/privacy') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M8 1.5l5.5 2v4.4c0 3.3-2.2 5.6-5.5 6.6-3.3-1-5.5-3.3-5.5-6.6V3.5z" />
                            <path d="M5.8 8l1.5 1.5L10.4 6" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">07</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Privacy, GDPR & ADA') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Cookie consent banner, GDPR / CCPA compliance, ADA / WCAG accessibility toggles, and policy URLs.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Consent / policies / a11y') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── Menu order — user-panel nav sequence ─── --}}
            <a href="{{ url('/admin/settings/menu-order') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M5 4h9M5 8h9M5 12h9" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">MENU</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Menu order') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Set the sequence of the top-navigation items (Dashboard, Campaigns, Flows, Templates…) shown in every user workspace.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Drag to reorder') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── Auth pages — edit login / register / forgot ─── --}}
            <a href="{{ url('/admin/settings/auth-pages') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="3" width="12" height="10" rx="1.5" />
                            <path d="M2 6h12M5 9.5h2M5 11h4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">AUTH</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Auth pages') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Edit the login, register & forgot-password pages — headline, sub-text, accent colour, and the side image / video / GIF. Live preview.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Login / register / forgot') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 08 · Analytics — tracking trackers ─── --}}
            <a href="{{ url('/admin/settings/analytics') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#F3E9FF] text-[#5B2E91] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M2 14h12M3 11l2.5-4 2.5 2 4-6" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">08</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Analytics integrations') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Google Analytics, GTM, Meta Pixel, Clarity, Plausible, PostHog, Hotjar, TikTok, LinkedIn, X — paste the ID, we render the script.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('GA4 / Pixel / Clarity / GTM') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 09 · SEO — marketing surfaces ─── --}}
            <a href="{{ url('/admin/settings/seo') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-accent-amber/15 text-[#7B5A14] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                            <path d="M5 7h4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">09</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('SEO settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Meta title, description, OpenGraph, Twitter cards, robots, canonical, and Google + Bing site verification.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Meta / OG / Twitter') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 10 · Languages — i18n catalog ─── --}}
            <a href="{{ url('/admin/languages') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M2 4h6M5 3v1M3 7l3-3 3 3M4 11l2-4 2 4M10 14l3-7 3 7M11 12h4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">10</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Languages') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Available locales, default language, RTL flag, and per-language enable toggle. Drives the language picker in user account settings.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('en / es / fr / ar · RTL') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 11 · PWA — installable app manifest ─── --}}
            <a href="{{ url('/admin/settings/pwa') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="4" y="1.8" width="8" height="12.4" rx="1.4" />
                            <circle cx="8" cy="11.5" r="0.7" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">11</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('PWA settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('App name, install icons, splash image, theme color, background color, display mode, and PWA enable switch.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Icons / colors / install') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 12 · Catalog — WhatsApp Commerce ─── --}}
            <a href="{{ url('/admin/settings/catalog') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-paper-100 text-ink-700 grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="2.5" y="3" width="11" height="10" rx="1.4" />
                            <path d="M5 6h6M5 9h4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-accent-amber">{{ __('setup') }}</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Catalog settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('WhatsApp Catalog feature gate, Graph API version, default currency, and merchant walkthrough.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('WABA / Commerce Manager') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 13 · Shopify — one-time platform OAuth app ─── --}}
            <a href="{{ url('/admin/settings/shopify') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M5 5.5V4a3 3 0 0 1 6 0v1.5" />
                            <path d="M3 5h10l-1 9H4z" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">13</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Shopify settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Shopify app enablement, client ID, client secret, scopes, redirect URLs, and install status.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('OAuth app / scopes') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 14 · WooCommerce — toggle only ─── --}}
            <a href="{{ url('/admin/settings/woocommerce') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#E0EBF7] text-[#13478A] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M2 5h12l-1 8H3z" />
                            <path d="M5 5a3 3 0 0 1 6 0" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">14</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('WooCommerce settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('WooCommerce enablement, setup instructions, webhook requirements, and store connection guidance.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Store setup / webhooks') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 15 · HubSpot CRM — one-time platform OAuth app ─── --}}
            <a href="{{ url('/admin/settings/hubspot') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#FCE7DD] text-[#E8632A] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="2.2" />
                            <path d="M8 5.8V3.2M10.2 8h2.6M8 10.2v2.6M5.8 8H3.2" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">15</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('HubSpot CRM settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('One-time OAuth app: Client ID/secret, scopes, redirect URI. Workspaces then push WhatsApp customers and orders into HubSpot as contacts and deals.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('OAuth app / scopes') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── Google integration — one OAuth client powers Calendar/Meet/Sheets/Docs/Forms ─── --}}
            <a href="{{ url('/admin/settings/google-calendar') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#E8F0FE] text-[#1A73E8] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M14 8a6 6 0 1 1-2-4.5" />
                            <path d="M14 3v3h-3" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">16</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Google integration settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Single OAuth client + secret powers Calendar slot pickers, Google Meet links, Sheets/Docs/Forms nodes, and the team-inbox Send Meet button. Workspaces then connect their own Google account.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('OAuth client / scopes') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── Slack → WhatsApp — per-workspace slash command ─── --}}
            <a href="{{ url('/admin/settings/slack') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#F3ECFA] text-[#4A154B] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M4 9h7M5 5v7M11 7h-7M11 11V4" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">17</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Slack settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Turn the Slack integration on/off platform-wide. Workspaces connect their own Slack app and send WhatsApp messages with a /wa slash command.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Enable / setup') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── Trello → WhatsApp — per-workspace board webhook ─── --}}
            <a href="{{ url('/admin/settings/trello') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#E8F0FE] text-[#0079BF] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="3" width="10" height="10" rx="1.6" />
                            <path d="M6 5v4M10 5v2" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">18</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Trello settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Turn the Trello integration on/off platform-wide. Workspaces connect a board so card assignments and changes notify on WhatsApp.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Enable / setup') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 16 · Footer — one-time branding ─── --}}
            <a href="{{ url('/admin/settings/footer') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-paper-100 text-ink-700 grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="2" y="3" width="12" height="10" rx="1.5" />
                            <path d="M2 10h12" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">15</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Footer settings') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Footer title, logo, social links, copyright text, and public footer description.') }}</p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('Logo / socials / copyright') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 16 · Site info — public-site shared identity ─── --}}
            <a href="{{ url('/admin/site-settings') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-wa-bubble text-wa-deep grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3l2 1" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">16</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Site info') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Contact emails, phone, WhatsApp, address, and social links shared across the public footer, contact, and about pages.') }}
                </p>
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ __('emails / phone / socials') }}</span><span
                        class="text-wa-deep group-hover:underline">{{ __('Open') }}</span></div>
            </a>

            {{-- ─── 17 · Social login & captcha — Google/Facebook sign-in + reCAPTCHA ─── --}}
            <a href="{{ url('/admin/settings/social-login') }}"
                class="group bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card hover:border-wa-deep transition">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-11 h-11 rounded-2xl bg-[#E8F0FE] text-[#1A73E8] grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M8 8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                            <path d="M3 13a5 5 0 0 1 10 0" />
                        </svg></span>
                    <span class="font-mono text-[10px] text-ink-500">17</span>
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-4">{{ __('Social login & captcha') }}</h2>
                <p class="text-[12.5px] text-ink-600 mt-2">
                    {{ __('Toggle Google and Facebook sign-in on the login and register pages, paste OAuth keys, and enable Google reCAPTCHA (v2 or v3) to block bot signups.') }}
                </p>
                @php
                    $__socialSvc = app(\App\Services\SocialAuthService::class);
                    $__reSvc = app(\App\Services\RecaptchaService::class);
                    $__socialChips = [];
                    if ($__socialSvc->enabled('google')) {
                        $__socialChips[] = 'Google';
                    }
                    if ($__socialSvc->enabled('facebook')) {
                        $__socialChips[] = 'Facebook';
                    }
                    if ($__reSvc->enabled()) {
                        $__socialChips[] = 'reCAPTCHA';
                    }
                @endphp
                <div class="mt-4 flex items-center justify-between text-[11px] font-mono text-ink-500">
                    <span>{{ count($__socialChips) ? implode(' · ', $__socialChips) : __('not configured') }}</span>
                    <span class="text-wa-deep group-hover:underline">{{ __('Open') }}</span>
                </div>
            </a>

        </section>

        {{-- Provider toggles moved to /admin/settings/wadesk-message --}}
        <section class="mt-10 hidden">
            <div class="flex items-end justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Platform') }}
                    </div>
                    <h2 class="font-serif text-[28px] tracking-[-0.02em] leading-tight">{{ __('Sending providers') }}
                    </h2>
                    <p class="text-[12.5px] text-ink-600 mt-1 max-w-2xl">
                        {{ __('Pick which WhatsApp send methods workspaces can connect to at') }} <span
                            class="font-mono">/connect</span>. You can enable any combination — workspaces will see
                        only the enabled options.</p>
                </div>
            </div>
            {{-- NEUTRALISED (multi-engine Phase 0): provider toggles live at
                 /admin/settings/wadesk-message. This obsolete hidden block is kept
                 inert — a <div> with no action — so it can never POST the old
                 waba/baileys/twilio + default_send_method contract to the now
                 re-validated providers.update endpoint. --}}
            <div
                class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-5">
                @php
                    $allowed = old('allowed_send_methods', $settings['allowed_send_methods']);
                    $defaultMethod = old('default_send_method', $settings['default_send_method']);
                @endphp

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- WABA --}}
                    <label
                        class="border {{ in_array('waba', $allowed) ? 'border-wa-deep ring-2 ring-wa-deep/15' : 'border-paper-200' }} rounded-xl p-4 cursor-pointer">
                        <div class="flex items-start justify-between">
                            <span class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="8" r="6" />
                                    <path d="M5.5 8.5l2 2 3-4" />
                                </svg></span>
                            <input type="checkbox" name="allowed_send_methods[]" value="waba"
                                @checked(in_array('waba', $allowed))
                                class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep mt-1" />
                        </div>
                        <div class="mt-2 font-serif text-[16px] leading-tight">{{ __('Official WABA') }}</div>
                        <p class="text-[11.5px] text-ink-500 mt-1 leading-snug">
                            {{ __('Meta Cloud API. Verified businesses, native catalog, in-chat orders.') }}</p>
                    </label>
                    {{-- Baileys --}}
                    <label
                        class="border {{ in_array('baileys', $allowed) ? 'border-wa-deep ring-2 ring-wa-deep/15' : 'border-paper-200' }} rounded-xl p-4 cursor-pointer">
                        <div class="flex items-start justify-between">
                            <span class="w-9 h-9 rounded-lg bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M2 4h12v8H2zM5 4v8M11 4v8" />
                                </svg></span>
                            <input type="checkbox" name="allowed_send_methods[]" value="baileys"
                                @checked(in_array('baileys', $allowed))
                                class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep mt-1" />
                        </div>
                        <div class="mt-2 font-serif text-[16px] leading-tight">{{ __('Unofficial API') }}</div>
                        <p class="text-[11.5px] text-ink-500 mt-1 leading-snug">
                            {{ __('QR pair only. Free, no Meta verification. Custom storefront for orders.') }}</p>
                    </label>
                    {{-- Twilio --}}
                    <label
                        class="border {{ in_array('twilio', $allowed) ? 'border-wa-deep ring-2 ring-wa-deep/15' : 'border-paper-200' }} rounded-xl p-4 cursor-pointer">
                        <div class="flex items-start justify-between">
                            <span
                                class="w-9 h-9 rounded-lg bg-accent-amber/20 text-[#7B5A14] grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M3 5l5-3 5 3v6l-5 3-5-3z" />
                                    <circle cx="8" cy="8" r="1.5" />
                                </svg></span>
                            <input type="checkbox" name="allowed_send_methods[]" value="twilio"
                                @checked(in_array('twilio', $allowed))
                                class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep mt-1" />
                        </div>
                        <div class="mt-2 font-serif text-[16px] leading-tight">{{ __('Twilio WhatsApp') }}</div>
                        <p class="text-[11.5px] text-ink-500 mt-1 leading-snug">
                            {{ __('Twilio Sandbox or Production. SID + token + From number.') }}</p>
                    </label>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 pt-3 border-t border-paper-200">
                    <label class="flex flex-col gap-1.5">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Default method (when workspace not yet connected)') }}</span>
                        <select name="default_send_method"
                            class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="waba" @selected($defaultMethod === 'waba')>{{ __('Official WABA') }}</option>
                            <option value="baileys" @selected($defaultMethod === 'baileys')>{{ __('Unofficial API') }}</option>
                            <option value="twilio" @selected($defaultMethod === 'twilio')>{{ __('Twilio') }}</option>
                        </select>
                        <span
                            class="text-[10.5px] text-ink-500">{{ __('Must match one of the enabled methods above.') }}</span>
                    </label>
                </div>

                {{-- WABA shared credentials (used as fallback when workspace doesn't override) --}}
                <details class="border-t border-paper-200 pt-4 group" {{ in_array('waba', $allowed) ? 'open' : '' }}>
                    <summary
                        class="cursor-pointer font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 hover:text-ink-900">
                        {{ __('WABA · Embedded Signup credentials') }}</summary>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-3">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Meta App ID') }}</span>
                            <input type="text" name="waba_app_id"
                                value="{{ old('waba_app_id', $settings['waba_app_id']) }}"
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="1234567890123456" />
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Meta App Secret') }}</span>
                            <input type="password" name="waba_app_secret" value=""
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ $settings['waba_app_secret_set'] ? '••• stored, leave blank to keep' : 'paste your secret' }}" />
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700">{{ __('Configuration ID') }}</span>
                            <input type="text" name="waba_config_id"
                                value="{{ old('waba_config_id', $settings['waba_config_id']) }}"
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('from Meta Login for Business') }}" />
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700">{{ __('Webhook verify token') }}</span>
                            <input type="text" name="waba_webhook_verify_token"
                                value="{{ old('waba_webhook_verify_token', $settings['waba_webhook_verify_token']) }}"
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('any random string') }}" />
                        </label>
                        {{-- WhatsApp Coexistence toggle moved to the LIVE providers form
                             at /admin/settings/wadesk-message (this section is hidden /
                             dead). Do not re-add it here — it would never persist. --}}
                    </div>
                </details>

                {{-- Baileys default URL --}}
                <details class="border-t border-paper-200 pt-4" {{ in_array('baileys', $allowed) ? 'open' : '' }}>
                    <summary
                        class="cursor-pointer font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 hover:text-ink-900">
                        {{ __('Unofficial API · Node bridge') }}</summary>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-3">
                        <label class="flex flex-col gap-1.5">
                            <span
                                class="text-[11.5px] font-semibold text-ink-700">{{ __('Default Node server URL') }}</span>
                            <input type="url" name="baileys_server_url"
                                value="{{ old('baileys_server_url', $settings['baileys_server_url']) }}"
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="http://localhost:8888" />
                            <span
                                class="text-[10.5px] text-ink-500">{{ __('Workspaces can override this in their own connect step.') }}</span>
                        </label>
                    </div>
                </details>

                {{-- Twilio shared credentials --}}
                <details class="border-t border-paper-200 pt-4" {{ in_array('twilio', $allowed) ? 'open' : '' }}>
                    <summary
                        class="cursor-pointer font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 hover:text-ink-900">
                        {{ __('Twilio · WhatsApp credentials') }}</summary>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-3">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Account SID') }}</span>
                            <input type="text" name="twilio_account_sid"
                                value="{{ old('twilio_account_sid', $settings['twilio_account_sid']) }}"
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') }}" />
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Auth Token') }}</span>
                            <input type="password" name="twilio_auth_token" value=""
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ $settings['twilio_auth_token_set'] ? '••• stored, leave blank to keep' : 'paste your auth token' }}" />
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('From number') }}</span>
                            <input type="text" name="twilio_whatsapp_number"
                                value="{{ old('twilio_whatsapp_number', $settings['twilio_whatsapp_number']) }}"
                                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="+14155238886" />
                        </label>
                    </div>
                </details>

                <div class="flex justify-end pt-3 border-t border-paper-200">
                    <button type="button" disabled
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save providers') }}</button>
                </div>
            </div>
        </section>

        {{-- Wallet rules moved to its own page at /admin/settings/wallet-rules
 — accessible from the "Wallet rules" entry under Billing & plans
 in the sidebar. --}}

    </main>

</x-layouts.admin>
