{{--
 /admin/settings/privacy — GDPR / CCPA cookie consent + ADA accessibility.

 Drives partials/cookie-consent.blade.php (sticky bar + granular modal)
 and partials/site-analytics.blade.php (which honours the consent cookie
 category-by-category).
--}}
<x-layouts.admin :title="__('Privacy, GDPR & ADA')" admin-key="settings">
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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Privacy & ADA') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.privacy.update') }}" class="contents">
        @csrf @method('PATCH')

        <main class="px-4 sm:px-7 py-7 space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Compliance') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Privacy,') }}
                        <span class="italic text-wa-deep">{{ __('GDPR & ADA') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Cookie consent banner, policy URLs, GDPR/CCPA compliance, and WCAG accessibility toggles. Same setting feeds the consent UI and gates every analytics integration.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
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
                    {{-- Cookie consent --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Cookie consent') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Banner & policy') }}</h2>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('Banner enabled') }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_cookie_banner_enabled" value="1"
                                        @checked(old('privacy_cookie_banner_enabled', $privacy['cookie_banner_enabled'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Banner style') }}</span>
                                <select name="privacy_cookie_banner_style"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @php $st = old('privacy_cookie_banner_style', $privacy['cookie_banner_style'] ?: 'bottom-bar'); @endphp
                                    <option value="bottom-bar" @selected($st === 'bottom-bar')>
                                        {{ __('Bottom sticky bar') }} — {{ __('recommended') }}</option>
                                    <option value="top-bar" @selected($st === 'top-bar')>{{ __('Top sticky bar') }}
                                    </option>
                                    <option value="modal" @selected($st === 'modal')>{{ __('Centered modal') }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Respect Do-Not-Track') }}</span>
                                <div
                                    class="rounded-xl border border-paper-200 px-3 py-2.5 flex items-center justify-between h-[42px]">
                                    <span
                                        class="text-[12px] text-ink-700">{{ __('Browser sends DNT=1 → reject all') }}</span>
                                    <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                        <input type="checkbox" name="privacy_dnt_respect" value="1"
                                            @checked(old('privacy_dnt_respect', $privacy['dnt_respect'])) class="sr-only peer">
                                        <span
                                            class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                        <span
                                            class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                    </span>
                                </div>
                            </label>

                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Consent message') }}</span>
                                <textarea name="privacy_cookie_message" rows="3" maxlength="1000"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('privacy_cookie_message', $privacy['cookie_message']) }}</textarea>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Privacy policy URL') }}</span>
                                <input name="privacy_policy_url"
                                    value="{{ old('privacy_policy_url', $privacy['privacy_policy_url']) }}"
                                    placeholder="https://example.com/privacy"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Terms of service URL') }}</span>
                                <input name="privacy_terms_url"
                                    value="{{ old('privacy_terms_url', $privacy['terms_url']) }}"
                                    placeholder="https://example.com/terms"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5 col-span-2">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Cookies policy URL (Learn more)') }}</span>
                                <input name="privacy_cookies_policy_url"
                                    value="{{ old('privacy_cookies_policy_url', $privacy['cookies_policy_url']) }}"
                                    placeholder="https://example.com/cookies"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </section>

                    {{-- Regional compliance --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Regional compliance') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('GDPR & CCPA') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
                                <div>
                                    <div class="text-[12.5px] font-semibold">{{ __('GDPR compliance (EU)') }}</div>
                                    <div class="text-[11px] text-ink-500 mt-0.5">
                                        {{ __('Opt-in consent before any analytics fires.') }}</div>
                                </div>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_gdpr_compliance" value="1"
                                        @checked(old('privacy_gdpr_compliance', $privacy['gdpr_compliance'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <label
                                class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
                                <div>
                                    <div class="text-[12.5px] font-semibold">{{ __('CCPA compliance (California)') }}
                                    </div>
                                    <div class="text-[11px] text-ink-500 mt-0.5">
                                        {{ __('Show "Do not sell my personal information" option.') }}</div>
                                </div>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_ccpa_compliance" value="1"
                                        @checked(old('privacy_ccpa_compliance', $privacy['ccpa_compliance'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                    </section>

                    {{-- ADA / WCAG --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Accessibility') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('ADA / WCAG') }}</h2>
                            <p class="text-[11.5px] text-ink-500 mt-1">
                                {{ __('US Americans-with-Disabilities-Act + WCAG 2.1 AA-level affordances visitors can toggle.') }}
                            </p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
                                <span class="text-[12.5px] font-semibold">{{ __('Skip-to-content link') }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_ada_skip_link" value="1"
                                        @checked(old('privacy_ada_skip_link', $privacy['ada_skip_link'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <label
                                class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
                                <span class="text-[12.5px] font-semibold">{{ __('Offer high-contrast mode') }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_ada_high_contrast" value="1"
                                        @checked(old('privacy_ada_high_contrast', $privacy['ada_high_contrast'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <label
                                class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
                                <span class="text-[12.5px] font-semibold">{{ __('Offer large-text mode') }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_ada_large_text" value="1"
                                        @checked(old('privacy_ada_large_text', $privacy['ada_large_text'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <label
                                class="rounded-xl border border-paper-200 px-4 py-3 flex items-center justify-between cursor-pointer">
                                <span
                                    class="text-[12.5px] font-semibold">{{ __('Respect prefers-reduced-motion') }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="privacy_ada_reduced_motion" value="1"
                                        @checked(old('privacy_ada_reduced_motion', $privacy['ada_reduced_motion'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('How consent works') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Order of operations') }}
                            </h3>
                        </div>
                        <div class="p-4 space-y-2.5 text-[12px] text-ink-700">
                            <div class="flex gap-2.5"><span
                                    class="font-mono text-[10px] w-4 h-4 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0">1</span><span>{{ __('Visitor lands → cookie banner opens (or skipped if DNT respected).') }}</span>
                            </div>
                            <div class="flex gap-2.5"><span
                                    class="font-mono text-[10px] w-4 h-4 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0">2</span><span>{{ __('Choice saved to') }}
                                    <code class="font-mono text-[10px]">wadesk_cookie_consent</code>
                                    {{ __('cookie for 1 year.') }}</span></div>
                            <div class="flex gap-2.5"><span
                                    class="font-mono text-[10px] w-4 h-4 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0">3</span><span>{{ __('Server reads cookie + emits only the allowed trackers.') }}</span>
                            </div>
                            <div class="flex gap-2.5"><span
                                    class="font-mono text-[10px] w-4 h-4 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0">4</span><span>{{ __('Visitor reopens picker from') }}
                                    <code class="font-mono text-[10px]">[data-cookie-prefs-open]</code>.</span></div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Linked settings') }}</div>
                            <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Configure trackers') }}
                            </h3>
                        </div>
                        <div class="p-2">
                            <a href="{{ url('/admin/settings/analytics') }}"
                                class="block px-3 py-2.5 rounded-xl hover:bg-paper-50 text-[13px]">
                                <div class="font-semibold">{{ __('Analytics integrations') }} →</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('GA4, GTM, Pixel, Clarity, and more.') }}</div>
                            </a>
                            <a href="{{ url('/admin/settings/pwa') }}"
                                class="block px-3 py-2.5 rounded-xl hover:bg-paper-50 text-[13px]">
                                <div class="font-semibold">{{ __('PWA settings') }} →</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Installable app manifest.') }}
                                </div>
                            </a>
                        </div>
                    </div>
                </aside>
            </section>
        </main>

    </form>

</x-layouts.admin>
