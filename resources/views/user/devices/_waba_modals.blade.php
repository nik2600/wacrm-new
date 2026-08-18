{{--
 WABA connect modals. Two flavours:
 1. Manual paste — Phone Number ID + WABA ID + Access Token form.
 2. Embedded Signup — Meta JS SDK iframe ("Continue with Facebook").
 Both POST to controller endpoints which probe Meta, save the row,
 and subscribe the WABA to our app's webhooks.

 Triggered by any button with [data-waba-connect="manual"] or
 [data-waba-connect="embedded"]. JS wiring is in app.js (user devices key).
--}}

{{-- ── 1. Manual paste modal ─────────────────────────────────── --}}
<div id="waba-manual-modal" class="fixed inset-0 z-[80] hidden items-center justify-center px-4 bg-[rgba(11,31,28,0.45)]"
    role="dialog" aria-modal="true" aria-labelledby="waba-manual-title">
    <div
        class="w-full max-w-[560px] max-h-[90vh] flex flex-col bg-paper-0 rounded-2xl border border-paper-200 shadow-[0_28px_80px_-35px_rgba(11,31,28,0.6)] overflow-hidden">
        <div class="px-6 pt-5 pb-3 border-b border-paper-200 flex items-start justify-between gap-3 shrink-0">
            <div class="min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Add WABA · manual') }}
                </div>
                <h2 id="waba-manual-title" class="font-serif text-[20px] leading-tight font-semibold mt-0.5">
                    {{ __('Paste your WABA credentials') }}</h2>
            </div>
            <button type="button" data-waba-modal-close
                class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
            </button>
        </div>
        <form method="POST" action="{{ url('/devices/waba/connect/manual') }}" class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4">
            @csrf

            {{-- No webhook step shown to the client. It's wired AUTOMATICALLY on
                 connect — DevicesController@wabaConnectManual calls Meta's
                 subscribed_apps with override_callback_uri, so the customer only
                 pastes credentials → connect. (Token must carry
                 whatsapp_business_management for the auto-subscribe to succeed.) --}}
            <div class="rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-600">
                <span class="font-semibold text-wa-deep">{{ __('Paste your WABA credentials.') }}</span>
                {{ __('Find these in') }} <a href="https://business.facebook.com/wa/manage/" target="_blank" rel="noopener"
                    class="text-wa-deep underline">{{ __('Meta Business Suite → WhatsApp Manager → API Setup') }}</a>.
                {{ __('Token must include') }} <span class="font-mono">{{ __('whatsapp_business_messaging') }}</span> + <span
                    class="font-mono">{{ __('whatsapp_business_management') }}</span>.
            </div>

            <label class="block space-y-1.5">
                <span class="text-[11.5px] font-semibold">{{ __('Phone Number ID') }} <span
                        class="text-accent-coral">*</span></span>
                <input name="phone_number_id" required maxlength="40" placeholder="e.g. 533524166787123"
                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
            </label>
            <label class="block space-y-1.5">
                <span class="text-[11.5px] font-semibold">{{ __('WABA ID') }} <span
                        class="text-accent-coral">*</span></span>
                <input name="waba_id" required maxlength="40" placeholder="e.g. 102938475647382"
                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
            </label>
            <label class="block space-y-1.5">
                <span class="text-[11.5px] font-semibold">{{ __('Business Manager ID') }} <span
                        class="text-ink-500 font-normal">(optional)</span></span>
                <input name="business_id" maxlength="40" placeholder="{{ __('auto-resolved if left blank') }}"
                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
            </label>
            <label class="block space-y-1.5">
                <span class="text-[11.5px] font-semibold">{{ __('Access Token') }} <span
                        class="text-accent-coral">*</span></span>
                <input type="password" name="access_token" required maxlength="500" autocomplete="new-password"
                    placeholder="{{ __('EAA…') }}"
                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                <span
                    class="text-[11px] text-ink-500">{{ __('Use a permanent System User token (never expires) for production. 24-hour tokens work but stop sending tomorrow.') }}</span>
            </label>

            <label class="block space-y-1.5">
                <span class="text-[11.5px] font-semibold">{{ __('Display label') }} <span
                        class="text-ink-500 font-normal">(optional)</span></span>
                <input name="display_label" maxlength="120" placeholder="{{ __('auto-populates with verified name') }}"
                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
            </label>

            {{-- Verify-token self-check: tests the pasted token LIVE (validity,
                 scopes, WABA + template read) BEFORE saving, so a broken token is
                 caught here instead of failing silently after connect. --}}
            <div data-waba-verify-result class="hidden rounded-xl border px-3.5 py-2.5 text-[12px] space-y-1.5"></div>

            <div class="pt-3 mt-1 border-t border-paper-200 flex items-center justify-between gap-2 sticky bottom-0 -mx-6 px-6 pb-1 bg-paper-0">
                <button type="button" data-waba-verify-token data-verify-url="{{ url('/devices/waba/verify-token') }}"
                    class="px-4 py-2 rounded-full border border-wa-deep/40 text-wa-deep bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6.2 8.4 7.6 9.8l3-3.6"/><circle cx="8" cy="8" r="6.3"/></svg>
                    <span data-waba-verify-label>{{ __('Verify token') }}</span>
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" data-waba-modal-close
                        class="px-4 py-2 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-bold hover:bg-wa-teal">
                        {{ __('Probe + connect') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── 2. Embedded Signup modal ───────────────────────────────── --}}
@if (!empty($embeddedSignupReady ?? false))
    <div id="waba-embedded-modal"
        class="fixed inset-0 z-[80] hidden items-center justify-center px-4 bg-[rgba(11,31,28,0.45)]" role="dialog"
        aria-modal="true" aria-labelledby="waba-embedded-title">
        <div
            class="w-full max-w-[560px] bg-paper-0 rounded-2xl border border-paper-200 shadow-[0_28px_80px_-35px_rgba(11,31,28,0.6)] overflow-hidden">
            <div class="px-6 pt-5 pb-3 border-b border-paper-200 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Add WABA · Embedded Signup') }}</div>
                    <h2 id="waba-embedded-title" class="font-serif text-[20px] leading-tight font-semibold mt-0.5">
                        {{ __('Continue with Facebook') }}</h2>
                </div>
                <button type="button" data-waba-modal-close
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <p class="text-[12.5px] text-ink-700 leading-relaxed">
                    {{ __('Sign in to Meta to authorize WhatsApp Business access for this workspace. We never see your Facebook password — Meta hands us a token that only lets us send WhatsApp messages on your behalf.') }}
                </p>
                {{-- Primary: brand-new number onboarded the normal Cloud API way
                     (Meta runs SMS/voice OTP inside its own popup). --}}
                <button id="waba-launch-embedded-signup" type="button" data-app-id="{{ $wabaAppId ?? '' }}"
                    data-config-id="{{ $embeddedSignupConfigId ?? '' }}"
                    data-graph-version="{{ \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0') }}"
                    class="w-full px-4 py-3 rounded-xl bg-[#1877F2] hover:bg-[#0E60D4] text-white text-[14px] font-semibold inline-flex items-center justify-center gap-2">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                        <path
                            d="M24 12c0-6.6-5.4-12-12-12S0 5.4 0 12c0 6 4.4 11 10.1 11.9V15.5H7v-3.5h3.1V9.4c0-3.1 1.8-4.8 4.6-4.8 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.5h-2.9v8.4C19.6 23 24 18 24 12z" />
                    </svg>
                    {{ __('Continue with Facebook (new number)') }}
                </button>

                @if (\App\Models\SystemSetting::get('waba_coexistence', false))
                    {{-- Coexistence: link a number ALREADY on the WhatsApp Business
                         App. Meta shows a QR INSIDE its popup; the owner scans it from
                         their Business App. The app keeps working alongside Cloud API
                         automation on the same number. First-class user choice now —
                         not a hidden global toggle. --}}
                    <div class="relative text-center">
                        <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-paper-200"></span></div>
                        <span class="relative px-2 bg-paper-0 text-[10.5px] font-mono uppercase tracking-[0.16em] text-ink-400">{{ __('or') }}</span>
                    </div>
                    <button id="waba-launch-embedded-coex" type="button" data-app-id="{{ $wabaAppId ?? '' }}"
                        data-config-id="{{ $embeddedSignupConfigId ?? '' }}"
                        data-graph-version="{{ \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0') }}"
                        class="w-full px-4 py-3 rounded-xl border-2 border-[#1877F2] text-[#1877F2] hover:bg-[#1877F2]/5 text-[14px] font-semibold inline-flex items-center justify-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2.3" y="2.3" width="4.4" height="4.4" rx="1" />
                            <rect x="9.3" y="2.3" width="4.4" height="4.4" rx="1" />
                            <rect x="2.3" y="9.3" width="4.4" height="4.4" rx="1" />
                            <path d="M9.3 9.3h2.2M13.7 9.3v2.2M9.3 13.7h4.4M13.7 12.5v1.2" />
                        </svg>
                        {{ __('Connect my existing Business App number') }}
                    </button>
                    <div class="text-[11px] text-ink-500 leading-snug bg-paper-50 border border-paper-200 rounded-lg px-3 py-2">
                        {{ __('Scan the QR Meta shows from your WhatsApp Business App to link it — you keep using the app AND get Cloud API automation on the same number. Note: ~5 messages/sec cap, no blue tick, and companion (linked) devices get unlinked while connected.') }}
                    </div>
                @endif

                <div class="text-[11.5px] text-ink-500 text-center">
                    Need to paste credentials instead? <button type="button" data-waba-connect="manual"
                        class="text-wa-deep font-semibold hover:underline">{{ __('Switch to manual mode') }}</button>.
                </div>
                <form id="waba-embedded-form" method="POST" action="{{ url('/devices/waba/connect/embedded') }}"
                    class="hidden">
                    @csrf
                    <input type="hidden" name="code" value="">
                    <input type="hidden" name="waba_id" value="">
                    <input type="hidden" name="phone_number_id" value="">
                    <input type="hidden" name="business_id" value="">
                    {{-- Coexistence onboard marker — JS sets this from the launch
                         button's data-coexistence so the server skips /register. --}}
                    <input type="hidden" name="coexistence" value="0">
                </form>
            </div>
        </div>
    </div>

    {{-- Meta JS SDK — loaded once per page render of the WABA modal --}}
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
@endif
