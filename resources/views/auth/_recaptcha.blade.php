{{--
 Google reCAPTCHA widget for the guest auth forms. Place INSIDE the
 <form> so the response field is submitted with it.

 v2 (checkbox): Google's api.js auto-renders the .g-recaptcha box and
 injects the g-recaptcha-response field on submit — no custom JS.
 v3 (invisible): the token is fetched on submit by
 resources/js/charts/auth-recaptcha.js (loaded via app.js) and dropped
 into the hidden #recaptcha-token field. Site key + action come from
 data- attributes so nothing is hardcoded.

 The server verifies via RecaptchaService::verify() in AuthPagesController,
 reading either g-recaptcha-response (v2) or recaptcha_token (v3).

 @param string $action v3 action label (login / register). Defaults to login.
--}}
@php
    /** @var \App\Services\RecaptchaService $__re */
    $__re = app(\App\Services\RecaptchaService::class);
    $__action = $action ?? 'login';
@endphp

@if ($__re->enabled())
    @if ($__re->version() === 'v3')
        <input type="hidden" name="recaptcha_token" id="recaptcha-token" value="">
        <div data-recaptcha-v3 data-sitekey="{{ $__re->siteKey() }}" data-action="{{ $__action }}"></div>
        <p class="text-[10px] text-ink-500 leading-snug">
            {{ __('Protected by reCAPTCHA.') }}
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener"
                class="text-wa-deep hover:underline">{{ __('Privacy') }}</a> &amp;
            <a href="https://policies.google.com/terms" target="_blank" rel="noopener"
                class="text-wa-deep hover:underline">{{ __('Terms') }}</a>.
        </p>
    @else
        <div class="g-recaptcha" data-sitekey="{{ $__re->siteKey() }}"></div>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
@endif
