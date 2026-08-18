@php
    $cred = $existing && $existing->provider === 'baileys' ? $existing->creds() : [];
    $sharedUrl = (string) \App\Models\SystemSetting::get('baileys_server_url', env('SERVER_URL', ''));
@endphp

<h1 class="font-serif text-[32px] tracking-[-0.02em] leading-tight mt-2">{{ __('Pair your') }} <span
        class="italic text-wa-deep">{{ __('phone') }}</span>.</h1>
<p class="text-[13px] text-ink-600 mt-2 max-w-xl">
    {{ __("Enter the WhatsApp number you want to use. We'll generate a QR — open WhatsApp on that phone → Settings → Linked Devices → Link a device → scan.") }}
</p>

<div class="mt-6 grid grid-cols-1 md:grid-cols-[280px_1fr] gap-5">
    <!-- QR slot — JS polls /api/baileys/qr/:configId every 2s and replaces this -->
    <div
        class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card flex items-center justify-center aspect-square">
        <div id="baileys-qr"
            class="w-full h-full flex items-center justify-center text-[11px] font-mono text-ink-500 text-center">
            {{ __('Enter your phone number, then click Generate QR.') }}
        </div>
    </div>

    <div class="space-y-3">
        <form id="baileys-form" class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-3">
            <label class="block">
                <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('WhatsApp number') }}</span>
                <div class="wa-iti-wrap">
                    <input id="baileys-phone" type="tel" name="phone_number" required
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('Your number without country code') }}" />
                </div>
                <input id="baileys-cc" type="hidden" name="country_code" value="{{ app_default_country()['code'] }}" />
                <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __("The phone you'll scan the QR on.") }}</span>
            </label>
            <label class="block">
                <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Device label') }} <span
                        class="text-ink-500 font-normal">(optional)</span></span>
                <input type="text" name="device_name" maxlength="64" placeholder="{{ __('e.g. Bloomly Support') }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            </label>
            <label class="block">
                <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Node bridge URL') }} <span
                        class="text-ink-500 font-normal">(optional override)</span></span>
                <input type="url" name="server_url" maxlength="191"
                    placeholder="{{ $sharedUrl ?: 'http://localhost:8888' }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                <span class="text-[10.5px] text-ink-500 mt-1 block">Leave blank to use the platform default
                    ({{ $sharedUrl ?: 'not configured' }}).</span>
            </label>
            <button id="baileys-generate" type="button"
                class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center justify-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M3 3h4v4H3zM9 3h4v4H9zM3 9h4v4H3zM9 9h2v2h-2zM13 9v2M9 13h2M13 13h0" />
                </svg>
                Generate QR
            </button>
        </form>

        <div id="baileys-status"
            class="hidden bg-paper-50 border border-paper-200 rounded-2xl p-3 text-[12px] text-ink-700 font-mono"></div>

        <div class="bg-paper-50 border border-paper-200 rounded-2xl p-4 text-[12px] text-ink-700 leading-relaxed">
            <div class="font-semibold text-ink-900 mb-1">{{ __('How it works') }}</div>
            {{ __('Once paired, your phone runs in the background — every send goes out from this WhatsApp account. Keep the phone online with internet for sends to fly.') }}
        </div>
    </div>
</div>

@if ($existing && $existing->provider === 'baileys' && $existing->isConnected())
    <div class="mt-6 bg-wa-mint/30 border border-wa-green/40 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
            <div class="font-serif text-[16px] leading-tight">{{ __("You're connected ✓") }}</div>
            <div class="text-[12px] text-ink-700 mt-0.5 break-words">{{ __('Phone:') }} <span
                    class="font-mono">{{ mask_phone($existing->phone_number) }}</span> · Paired
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
