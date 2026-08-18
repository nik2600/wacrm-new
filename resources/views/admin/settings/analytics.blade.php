{{--
 /admin/settings/analytics — every third-party tracker keyed to a
 consent category. Each ID is read on every request by
 partials/site-analytics.blade.php which emits the matching official
 vendor snippet only when the visitor's wadesk_cookie_consent cookie
 allows the category (analytics or marketing).
--}}
<x-layouts.admin :title="__('Analytics integrations')" admin-key="settings">
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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.analytics.update') }}" class="contents">
        @csrf @method('PATCH')

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Tracking') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">
                        {{ __('Analytics') }} <span class="italic text-wa-deep">{{ __('integrations') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Paste each provider ID once. We render the official vendor snippet on every page — but only after the visitor consents to the matching cookie category.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <a href="{{ url('/admin/settings/privacy') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Privacy & GDPR') }}</a>
                    <button type="reset"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset draft') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div
                    class="rounded-xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-3 text-[12.5px] font-medium">
                    {{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div
                    class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                    <div class="font-semibold mb-1">{{ __('Please fix the highlighted fields:') }}</div>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- Analytics bucket --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-deep"></span>{{ __('Analytics cookies') }}
                            </div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Site analytics') }}</h2>
                            <p class="text-[11.5px] text-ink-500 mt-1">
                                {{ __('Visitor-level reporting. Emits only when the visitor consents to the "Analytics" category.') }}
                            </p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Google Analytics 4 (Measurement ID)') }}</span>
                                <input name="analytics_google_ga4"
                                    value="{{ old('analytics_google_ga4', $analytics['google_analytics_id']) }}"
                                    placeholder="{{ __('G-XXXXXXXXXX') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Find at') }} <a
                                        href="https://analytics.google.com" target="_blank" rel="noopener"
                                        class="text-wa-deep hover:underline">{{ __('analytics.google.com') }}</a> →
                                    Admin → Data streams.</span>
                            </label>
                            <label class="space-y-1.5">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Google Tag Manager (Container ID)') }}</span>
                                <input name="analytics_google_gtm"
                                    value="{{ old('analytics_google_gtm', $analytics['google_tag_manager_id']) }}"
                                    placeholder="{{ __('GTM-XXXXXXX') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Find at') }} <a
                                        href="https://tagmanager.google.com" target="_blank" rel="noopener"
                                        class="text-wa-deep hover:underline">{{ __('tagmanager.google.com') }}</a>.</span>
                            </label>
                            <label class="space-y-1.5">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Microsoft Clarity (Project ID)') }}</span>
                                <input name="analytics_microsoft_clarity"
                                    value="{{ old('analytics_microsoft_clarity', $analytics['microsoft_clarity_id']) }}"
                                    placeholder="{{ __('abcd1234ef') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500"><a href="https://clarity.microsoft.com"
                                        target="_blank" rel="noopener"
                                        class="text-wa-deep hover:underline">{{ __('clarity.microsoft.com') }}</a> —
                                    {{ __('free heatmaps + session recording.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Plausible (Domain)') }}</span>
                                <input name="analytics_plausible_domain"
                                    value="{{ old('analytics_plausible_domain', $analytics['plausible_domain']) }}"
                                    placeholder="{{ __('example.com') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500"><a href="https://plausible.io" target="_blank"
                                        rel="noopener"
                                        class="text-wa-deep hover:underline">{{ __('plausible.io') }}</a> —
                                    {{ __('privacy-friendly, GDPR-compliant.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('PostHog (API key)') }}</span>
                                <input name="analytics_posthog_key"
                                    value="{{ old('analytics_posthog_key', $analytics['posthog_api_key']) }}"
                                    placeholder="{{ __('phc_…') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('PostHog (Host)') }}</span>
                                <input name="analytics_posthog_host"
                                    value="{{ old('analytics_posthog_host', $analytics['posthog_host']) }}"
                                    placeholder="https://app.posthog.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Hotjar (Site ID)') }}</span>
                                <input name="analytics_hotjar_site_id"
                                    value="{{ old('analytics_hotjar_site_id', $analytics['hotjar_site_id']) }}"
                                    placeholder="1234567"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Numeric site ID from') }} <a
                                        href="https://hotjar.com" target="_blank" rel="noopener"
                                        class="text-wa-deep hover:underline">{{ __('hotjar.com') }}</a>.</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Mixpanel (Project Token)') }}</span>
                                <input name="analytics_mixpanel_token"
                                    value="{{ old('analytics_mixpanel_token', $analytics['mixpanel_token']) }}"
                                    placeholder="{{ __('abc…') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </section>

                    {{-- Marketing bucket --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-2">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>{{ __('Marketing cookies') }}
                            </div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Ad pixels') }}</h2>
                            <p class="text-[11.5px] text-ink-500 mt-1">
                                {{ __('Conversion + retargeting pixels. Emits only when the visitor consents to the "Marketing" category.') }}
                            </p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Meta (Facebook) Pixel ID') }}</span>
                                <input name="analytics_meta_pixel"
                                    value="{{ old('analytics_meta_pixel', $analytics['meta_pixel_id']) }}"
                                    placeholder="123456789012345"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('From') }} <a
                                        href="https://business.facebook.com/events_manager" target="_blank"
                                        rel="noopener"
                                        class="text-wa-deep hover:underline">{{ __('Meta Events Manager') }}</a>.</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('TikTok Pixel (Pixel ID)') }}</span>
                                <input name="analytics_tiktok_pixel"
                                    value="{{ old('analytics_tiktok_pixel', $analytics['tiktok_pixel_id']) }}"
                                    placeholder="{{ __('CXXX…') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('LinkedIn Insight Tag (Partner ID)') }}</span>
                                <input name="analytics_linkedin_partner"
                                    value="{{ old('analytics_linkedin_partner', $analytics['linkedin_partner_id']) }}"
                                    placeholder="1234567"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('X (Twitter) Pixel ID') }}</span>
                                <input name="analytics_twitter_pixel"
                                    value="{{ old('analytics_twitter_pixel', $analytics['twitter_pixel_id']) }}"
                                    placeholder="o…"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Tracker → category') }}</div>
                            <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('When each fires') }}</h3>
                        </div>
                        <div class="p-4 space-y-2 text-[11.5px] text-ink-700">
                            <div><span class="font-semibold text-wa-deep">{{ __('Analytics:') }}</span> GA4, GTM,
                                Clarity, Plausible, PostHog, Hotjar, Mixpanel</div>
                            <div><span class="font-semibold text-[#7B5A14]">{{ __('Marketing:') }}</span> Meta Pixel,
                                TikTok, LinkedIn, X Pixel</div>
                            <div class="text-[11px] text-ink-500 pt-2 border-t border-paper-200">
                                {{ __('GTM container scripts may include further trackers — pause them with category triggers inside GTM.') }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Where loaded') }}</div>
                            <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Layout coverage') }}</h3>
                        </div>
                        <div class="p-4 space-y-2 text-[12px] text-ink-700">
                            <div class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>{{ __('Logged-in user dashboard') }}</div>
                            <div class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>{{ __('Public / guest pages') }}</div>
                            <div class="flex items-center gap-2 text-ink-500"><svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                    <path d="M4 4l8 8M12 4l-8 8" />
                                </svg>{{ __('Admin pages (intentionally off)') }}</div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Related') }}</div>
                        </div>
                        <div class="p-2">
                            <a href="{{ url('/admin/settings/privacy') }}"
                                class="block px-3 py-2.5 rounded-xl hover:bg-paper-50 text-[13px]">
                                <div class="font-semibold">{{ __('Privacy, GDPR & ADA') }} →</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Drives the consent gate.') }}
                                </div>
                            </a>
                            <a href="{{ url('/admin/settings/pwa') }}"
                                class="block px-3 py-2.5 rounded-xl hover:bg-paper-50 text-[13px]">
                                <div class="font-semibold">{{ __('PWA settings') }} →</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Installable manifest.') }}</div>
                            </a>
                        </div>
                    </div>
                </aside>
            </section>
        </main>

    </form>

</x-layouts.admin>
