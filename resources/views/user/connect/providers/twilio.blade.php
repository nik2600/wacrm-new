@php
    $cred = $existing && $existing->provider === 'twilio' ? $existing->creds() : [];
    $sharedSid = (string) \App\Models\SystemSetting::get('twilio_account_sid', '');
    $sharedFrom = (string) \App\Models\SystemSetting::get('twilio_whatsapp_number', '');
@endphp

<h1 class="font-serif text-[32px] tracking-[-0.02em] leading-tight mt-2">{{ __('Connect via') }} <span
        class="italic text-wa-deep">{{ __('Twilio') }}</span>.</h1>
<p class="text-[13px] text-ink-600 mt-2 max-w-xl">
    {{ __('Paste your Twilio Account SID + Auth Token, plus the WhatsApp From number Twilio assigned you. Use the Sandbox while testing, then switch to a paid number when you go live.') }}
</p>

<form method="POST" action="{{ url('/connect/wa-store/twilio') }}"
    class="mt-6 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-4">
    @csrf

    @if ($sharedSid !== '')
        <div class="bg-wa-mint/30 border border-wa-green/30 rounded-lg px-3 py-2 text-[11.5px] text-ink-700 font-mono">
            {{ __('Your platform admin has shared default Twilio credentials. Leave the fields blank to use them, or paste workspace-specific creds to override.') }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <label class="flex flex-col gap-1.5">
            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Account SID') }}</span>
            <input type="text" name="account_sid" maxlength="64"
                value="{{ old('account_sid', $cred['account_sid'] ?? '') }}"
                placeholder="{{ $sharedSid !== '' ? 'using admin default' : 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' }}"
                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Auth Token') }}</span>
            <input type="password" name="auth_token" maxlength="128" value=""
                placeholder="{{ __('paste from twilio.com/console') }}"
                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('From number') }}</span>
            <input type="text" name="from_number" maxlength="32"
                value="{{ old('from_number', $cred['from_number'] ?? '') }}"
                placeholder="{{ $sharedFrom !== '' ? 'using admin default' : '+14155238886' }}"
                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Mode') }}</span>
            <select name="sandbox"
                class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                <option value="0" @selected(($cred['sandbox'] ?? '0') === '0' || ($cred['sandbox'] ?? false) === false)>{{ __('Production') }}</option>
                <option value="1" @selected(($cred['sandbox'] ?? '0') === '1' || ($cred['sandbox'] ?? false) === true)>{{ __('Sandbox (testing)') }}</option>
            </select>
        </label>
    </div>

    <div class="flex justify-end pt-3 border-t border-paper-200">
        <button type="submit"
            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
            Save &amp; test
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </button>
    </div>
</form>

@if ($existing && $existing->provider === 'twilio' && $existing->isConnected())
    <div class="mt-6 bg-wa-mint/30 border border-wa-green/40 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="min-w-0">
            <div class="font-serif text-[16px] leading-tight">{{ __("You're connected ✓") }}</div>
            <div class="text-[12px] text-ink-700 mt-0.5 break-words">{{ __('From:') }} <span
                    class="font-mono">{{ $existing->phone_number ? mask_phone($existing->phone_number) : '—' }}</span> · Saved
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
