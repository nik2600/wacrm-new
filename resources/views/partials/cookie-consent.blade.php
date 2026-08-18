{{--
 GDPR / CCPA cookie consent UI — two layers:

 1) A compact sticky banner (bottom or top per the admin setting,
 or modal if the admin picked "Centered modal" style). This is
 the FIRST surface a visitor sees — Decline, Customize, Accept.

 2) A detailed preferences modal with three category toggles
 (Strictly Necessary locked-on, Analytics, Marketing). Hidden
 by default; opens only when the visitor clicks "Customize" or
 any [data-cookie-prefs-open] link (footer, account page).

 Server-side, partials/site-analytics.blade.php reads the
 wadesk_cookie_consent cookie on every request and only emits the
 trackers matching each enabled category.
--}}
@php
    $cookieBannerEnabled = (bool) \App\Models\SystemSetting::get('privacy_cookie_banner_enabled', true);
    $cookieBannerStyle = (string) \App\Models\SystemSetting::get('privacy_cookie_banner_style', 'bottom-bar');
    $cookieMessage = (string) \App\Models\SystemSetting::get(
        'privacy_cookie_message',
        'We use cookies to improve your experience and analyze site traffic. By continuing, you agree to our Privacy Policy.',
    );
    $privacyPolicyUrl = (string) \App\Models\SystemSetting::get('privacy_policy_url', '');
    $cookiesPolicyUrl = (string) \App\Models\SystemSetting::get('privacy_cookies_policy_url', '');
    $learnMoreUrl = $cookiesPolicyUrl ?: $privacyPolicyUrl;
    $dntRespect = (bool) \App\Models\SystemSetting::get('privacy_dnt_respect', true);

    // Position class for the sticky banner. Modal style still shows a
    // banner at the bottom — the modal is only triggered on Customize.
    $isTop = $cookieBannerStyle === 'top-bar';
    $isModal = $cookieBannerStyle === 'modal';
    $position = $isTop ? 'top-3' : 'bottom-3';
@endphp

@if ($cookieBannerEnabled)
    {{-- Mark <html> so cookie-consent.js can read the DNT preference. --}}
    <script>
        document.documentElement.dataset.dntRespect = @json($dntRespect ? '1' : '0');
        document.documentElement.dataset.cookieBannerStyle = @json($cookieBannerStyle);
    </script>

    {{-- ── 1. Compact banner (default surface) ────────────────────── --}}
    <div id="wa-cookie-bar" data-cookie-bar
        class="fixed left-1/2 -translate-x-1/2 {{ $position }} z-[75] hidden w-[min(960px,calc(100%-1.5rem))] rounded-2xl border border-paper-200 bg-paper-0/95 backdrop-blur shadow-[0_18px_60px_-30px_rgba(11,31,28,0.5)] px-5 py-3.5"
        role="dialog" aria-live="polite" aria-label="{{ __('Cookie preferences') }}">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
            <div class="flex items-start gap-3 min-w-0 flex-1">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-wa-deep shrink-0 mt-0.5" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <circle cx="12" cy="12" r="9" />
                    <circle cx="9" cy="9" r="0.6" fill="currentColor" />
                    <circle cx="14" cy="13" r="0.6" fill="currentColor" />
                    <circle cx="10" cy="15" r="0.6" fill="currentColor" />
                    <circle cx="15.5" cy="9.5" r="0.6" fill="currentColor" />
                </svg>
                <div class="min-w-0">
                    <div class="text-[14px] font-semibold leading-snug">{{ __('We use cookies') }}</div>
                    <p class="text-[12.5px] text-ink-600 leading-relaxed mt-0.5">
                        {{ $cookieMessage }}
                        @if ($learnMoreUrl)
                            <a href="{{ $learnMoreUrl }}"
                                class="text-wa-deep font-semibold hover:underline whitespace-nowrap">{{ $cookiesPolicyUrl !== '' ? __('Cookie Policy') : __('Privacy Policy') }}</a>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap w-full sm:w-auto">
                <button type="button" data-cookie-action="reject"
                    class="px-3.5 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold uppercase tracking-wider">
                    {{ __('Decline') }}
                </button>
                <button type="button" data-cookie-prefs-open
                    class="px-3.5 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">
                    {{ __('Customize') }}
                </button>
                <button type="button" data-cookie-action="accept"
                    class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[12px] font-bold uppercase tracking-wider">
                    {{ __('Accept All') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ── 2. Granular modal (opens on Customize) ─────────────────── --}}
    <div id="wa-cookie-consent" data-cookie-modal
        class="fixed inset-0 z-[80] hidden items-center justify-center px-4 bg-[rgba(11,31,28,0.45)]" role="dialog"
        aria-modal="true" aria-labelledby="wa-cookie-consent-title">
        <div
            class="w-full max-w-[520px] bg-paper-0 rounded-2xl border border-paper-200 shadow-[0_28px_80px_-35px_rgba(11,31,28,0.6)] overflow-hidden">
            <div class="px-6 pt-5 pb-3 border-b border-paper-200">
                <h2 id="wa-cookie-consent-title" class="font-serif text-[19px] leading-tight font-semibold">
                    {{ __('Your Privacy Preferences') }}</h2>
            </div>
            <div class="px-6 py-4">
                <p class="text-[12.5px] text-ink-700 leading-relaxed">
                    {{ $cookieMessage }}
                    @if ($learnMoreUrl)
                        <a href="{{ $learnMoreUrl }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Learn more') }}</a>
                    @endif
                </p>

                <div class="mt-5 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[13px] font-semibold">{{ __('Strictly Necessary') }}</div>
                            <p class="text-[11.5px] text-ink-500 mt-0.5">
                                {{ __('Required for the website to function.') }}</p>
                        </div>
                        <label
                            class="relative inline-flex items-center cursor-not-allowed opacity-80 shrink-0 mt-0.5 w-10 h-5">
                            <input type="checkbox" checked disabled class="sr-only peer">
                            <span class="absolute inset-0 bg-wa-deep rounded-full"></span>
                            <span class="absolute top-0.5 left-[22px] w-4 h-4 bg-paper-0 rounded-full"></span>
                        </label>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[13px] font-semibold">{{ __('Analytics Cookies') }}</div>
                            <p class="text-[11.5px] text-ink-500 mt-0.5">
                                {{ __('Help us improve our website by collecting and reporting information on its usage.') }}
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5 w-10 h-5">
                            <input type="checkbox" data-cookie-toggle="analytics" class="sr-only peer">
                            <span
                                class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                            <span
                                class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                        </label>
                    </div>

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[13px] font-semibold">{{ __('Marketing Cookies') }}</div>
                            <p class="text-[11.5px] text-ink-500 mt-0.5">
                                {{ __('Used to track visitors across websites to display relevant advertisements.') }}
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5 w-10 h-5">
                            <input type="checkbox" data-cookie-toggle="marketing" class="sr-only peer">
                            <span
                                class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                            <span
                                class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div
                class="px-6 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap items-center justify-end gap-2">
                <button type="button" data-cookie-action="reject"
                    class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">
                    {{ __('Reject All') }}
                </button>
                <button type="button" data-cookie-action="save"
                    class="px-4 py-2 rounded-full border border-wa-deep text-wa-deep hover:bg-wa-bubble text-[12px] font-semibold">
                    {{ __('Save Preferences') }}
                </button>
                <button type="button" data-cookie-action="accept"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal text-[12px] font-bold uppercase tracking-wider">
                    {{ __('Accept All') }}
                </button>
            </div>
        </div>
    </div>
@endif
