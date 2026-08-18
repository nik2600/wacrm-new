@php
    $appId = (string) \App\Models\SystemSetting::get('waba_app_id', '');
    $configId = (string) \App\Models\SystemSetting::get('waba_config_id', '');
    $missing = $appId === '' || $configId === '';
    $cred = $existing && $existing->provider === 'waba' ? $existing->creds() : [];
@endphp

<h1 class="font-serif text-[32px] tracking-[-0.02em] leading-tight mt-2">{{ __('Connect with') }} <span
        class="italic text-wa-deep">{{ __('Meta') }}</span>.</h1>
<p class="text-[13px] text-ink-600 mt-2 max-w-xl">
    {{ __("Sign in to Meta, pick the WhatsApp number to connect, and we'll provision the rest — webhook subscription, phone register, catalog link.") }}
</p>

@if ($missing)
    <div class="mt-6 bg-accent-amber/15 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
        <div class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-lg bg-accent-amber/20 text-[#7B5A14] grid place-items-center shrink-0"><svg
                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M8 5v3M8 11h.01" />
                </svg></span>
            <div class="text-[12.5px] text-ink-700 leading-relaxed">
                <div class="font-semibold text-ink-900">{{ __('Meta App not configured') }}</div>
                Your platform admin needs to add the Meta App ID + Config ID at <a href="{{ url('/admin/settings') }}"
                    class="font-semibold text-wa-deep hover:underline">/admin/settings → Sending providers</a>. Once
                set, this page will let you connect with one click.
            </div>
        </div>
    </div>
@else
    <!-- Embedded Signup launcher. The actual FB.login glue lives in the
 JS bundle for this page; here we just render the button + status
 container that the JS targets. -->
    <div class="mt-6 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
        <div class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg viewBox="0 0 16 16"
                    class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M5.5 8.5l2 2 3-4" />
                </svg></span>
            <div class="flex-1 min-w-0">
                <div class="font-serif text-[18px] leading-tight">{{ __('Embedded Signup') }}</div>
                <p class="text-[12px] text-ink-500 mt-1">
                    {{ __("We'll never see your Meta password. You pick which WhatsApp number we get access to.") }}</p>
            </div>
        </div>

        <div id="waba-status" class="mt-4 hidden"></div>

        <button id="waba-signup-btn" type="button" data-app-id="{{ $appId }}"
            data-config-id="{{ $configId }}"
            data-graph-version="{{ \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0') }}"
            class="mt-5 w-full px-4 py-3 rounded-full bg-[#1877F2] hover:bg-[#1864d6] text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor">
                <path
                    d="M16 8a8 8 0 1 0-9.25 7.9V10.3H4.72V8h2.03V6.24c0-2 1.2-3.12 3.02-3.12.87 0 1.79.16 1.79.16v1.97h-1.01c-.99 0-1.3.62-1.3 1.25V8h2.21l-.35 2.3H9.25v5.6A8 8 0 0 0 16 8z" />
            </svg>
            Continue with Meta
        </button>
        <p class="text-[10.5px] text-ink-500 mt-2 text-center">
            {{ __('Requires a Meta Business account, a verified business, and a phone not already on the consumer WhatsApp app.') }}
        </p>
    </div>

    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-3.5 text-[12px] text-ink-700 leading-snug">
            <div class="font-semibold mb-1">1. Webhook</div>We auto-subscribe Meta's webhook to your WABA so messages
            flow to /chat.
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-3.5 text-[12px] text-ink-700 leading-snug">
            <div class="font-semibold mb-1">2. Register phone</div>We register your number on Cloud API with a secret
            PIN we generate.
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-3.5 text-[12px] text-ink-700 leading-snug">
            <div class="font-semibold mb-1">3. Catalog</div>We link your products to a Meta catalog so you can send
            single-product / list messages in chat.
        </div>
    </div>
@endif

@if ($existing && $existing->provider === 'waba' && $existing->isConnected())
    <div class="mt-6 bg-wa-mint/30 border border-wa-green/40 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
            <div class="font-serif text-[16px] leading-tight">{{ __("You're connected ✓") }}</div>
            <div class="text-[12px] text-ink-700 mt-0.5 break-words">{{ __('Phone:') }} <span
                    class="font-mono">{{ $existing->phone_number ? mask_phone($existing->phone_number) : '—' }}</span> · Connected
                {{ optional($existing->connected_at)->diffForHumans() }}</div>
        </div>
        <a href="{{ url('/store') }}"
            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
            Go to store
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </a>
    </div>
@endif
