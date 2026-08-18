{{-- Register form column — shared by the original (variant 1) layout AND the
     <x-auth-shell> variants 2–5. Wrapper supplies chrome + logo. --}}
<div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Step 1 of 3 / Create account') }}</div>
<h2 class="font-serif text-[30px] leading-tight tracking-[-0.01em]">{{ __('Start your') }} <span
        class="italic text-wa-deep">{{ __('workspace') }}</span>.</h2>

<ol class="flex items-center gap-2 mt-3 mb-3 text-[10.5px] font-mono uppercase tracking-wider">
    <li class="text-wa-deep flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">1</span>Account</li>
    <li class="w-4 h-px bg-paper-300"></li>
    <li class="text-ink-500 flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">2</span>Workspace</li>
    <li class="w-4 h-px bg-paper-200"></li>
    <li class="text-ink-500 flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>Plan</li>
</ol>

@include('auth._social', ['compact' => true])

@if ($errors->any())
    <div class="mb-2 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
        @foreach ($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('register') }}" class="space-y-2.5">
    @csrf
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Full name') }}</label>
        <input required type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('Your name') }}"
            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Work email') }}</label>
        <input required type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com"
            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Mobile number') }}</label>
        <div class="wa-iti-wrap">
            <input id="reg-phone" required type="tel" name="mobile" value="{{ old('mobile') }}" placeholder="{{ __('Your number without country code') }}"
                class="px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </div>
        <input id="reg-country-code" type="hidden" name="country_code" value="{{ old('country_code', app_default_country()['code']) }}" />
        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('We use this to reach you for account verification and important updates.') }}</div>
    </div>
    @php
        $prefilledRef = strtoupper((string) (request()->query('ref') ?: request()->cookie(\App\Http\Middleware\CaptureReferral::COOKIE_NAME) ?: old('ref') ?: ''));
    @endphp
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Referral code') }}
            <span class="text-ink-500 font-normal">(optional)</span></label>
        <input type="text" name="ref" value="{{ $prefilledRef }}" placeholder="{{ __("Got a friend's code? Paste it here") }}" maxlength="16"
            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono uppercase tracking-wider focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Password') }}</label>
        <div class="relative">
            <input id="pw" required type="password" name="password" placeholder="{{ __('At least 8 characters') }}" minlength="8" autocomplete="new-password"
                class="w-full px-3 py-2 pr-10 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            <button type="button" id="pw-toggle" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900" title="{{ __('Show password') }}">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" /><circle cx="8" cy="8" r="2" /></svg>
            </button>
        </div>
    </div>
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Confirm password') }}</label>
        <input required type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>

    <label class="flex items-start gap-2 cursor-pointer pt-0.5">
        <input type="checkbox" name="agree" value="1" required class="w-4 h-4 accent-wa-deep mt-0.5" />
        @php
            $authTermsUrl   = \App\Models\SystemSetting::get('privacy_terms_url', '')   ?: url('/legal/terms');
            $authPrivacyUrl = \App\Models\SystemSetting::get('privacy_policy_url', '')  ?: url('/legal/privacy');
        @endphp
        <span class="text-[11px] text-ink-700 leading-snug">{{ __('I agree to') }}
            <a href="{{ $authTermsUrl }}" target="_blank" rel="noopener" class="text-wa-deep font-semibold hover:underline">{{ __('Terms') }}</a> {{ __('and') }}
            <a href="{{ $authPrivacyUrl }}" target="_blank" rel="noopener" class="text-wa-deep font-semibold hover:underline">{{ __('Privacy policy') }}</a>.</span>
    </label>

    @include('auth._recaptcha', ['action' => 'register'])

    <button type="submit"
        class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-1">
        {{ __('Continue') }}
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8h10M9 4l4 4-4 4" /></svg>
    </button>

    <p class="text-[12px] text-ink-600 text-center">{{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Sign in') }}</a></p>
</form>
