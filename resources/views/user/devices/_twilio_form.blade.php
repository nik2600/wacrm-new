{{--
 Twilio connect form — shared between the inline single-engine empty state
 (_twilio_section) and the multi-engine "Add device → Twilio" modal
 (index). Posts to /connect/wa-store/twilio. Expects $twilioAdminDefaults.
--}}
@php
    $defaults = is_array($twilioAdminDefaults ?? null)
        ? $twilioAdminDefaults
        : ['account_sid' => '', 'whatsapp_number' => ''];
    $hasAdminDefaults = $defaults['account_sid'] !== '' && $defaults['whatsapp_number'] !== '';
@endphp

<p class="text-[12.5px] text-ink-600 mb-5 max-w-2xl">
    {{ __('Paste your Twilio Account SID, Auth Token, and the WhatsApp From number Twilio assigned. Use the Sandbox while testing, then switch to a paid Twilio number when you go live.') }}
</p>

@if ($hasAdminDefaults)
    <div class="mb-5 px-4 py-3 rounded-xl bg-wa-mint/40 border border-wa-deep/15 text-[12px] text-ink-700">
        {{ __('Your platform admin has shared default Twilio credentials. Leave the fields below blank to use them, or paste workspace-specific creds to override.') }}
    </div>
@endif

<form method="POST" action="{{ url('/connect/wa-store/twilio') }}" class="space-y-4">
    @csrf
    {{-- This form lives on /devices — return here after a successful connect
         instead of the store-onboarding wizard's default /store. --}}
    <input type="hidden" name="return_to" value="/devices">

    <div class="grid md:grid-cols-2 gap-4">
        <label class="block">
            <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Account SID') }}
                <span class="text-accent-coral">*</span></span>
            <input name="account_sid" maxlength="64" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                value="{{ old('account_sid') }}"
                class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Found at twilio.com/console') }}</span>
        </label>
        <label class="block">
            <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auth Token') }}
                <span class="text-accent-coral">*</span></span>
            <input name="auth_token" type="password" maxlength="128"
                placeholder="{{ __('paste from twilio.com/console') }}"
                class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
            <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Encrypted at rest — only used to call api.twilio.com on your behalf.') }}</span>
        </label>
    </div>

    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('WhatsApp From number') }}
            <span class="text-accent-coral">*</span></span>
        <input name="from_number" maxlength="32" placeholder="+14155238886 (sandbox) or your paid Twilio number"
            value="{{ old('from_number') }}"
            class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
        <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Format: +E.164. Sandbox default is +14155238886.') }}</span>
    </label>

    <label class="inline-flex items-center gap-2.5 px-4 py-3 rounded-xl border border-paper-200">
        <input name="sandbox" type="checkbox" value="1" {{ old('sandbox', '1') ? 'checked' : '' }}
            class="w-4 h-4 accent-wa-deep" />
        <span class="text-[12.5px]">
            {{ __('Use Twilio Sandbox') }}
            <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Required while your number is in WhatsApp Sandbox mode. Uncheck once Twilio has approved a paid sender.') }}</span>
        </span>
    </label>

    @error('account_sid')
        <div class="px-3 py-2 rounded-lg bg-accent-coral/10 border border-accent-coral/25 text-[12px] text-accent-coral">
            {{ $message }}</div>
    @enderror
    @error('from_number')
        <div class="px-3 py-2 rounded-lg bg-accent-coral/10 border border-accent-coral/25 text-[12px] text-accent-coral">
            {{ $message }}</div>
    @enderror

    <div class="flex items-center justify-end gap-2 pt-2 border-t border-paper-100">
        <button type="submit"
            class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
            {{ __('Verify + connect Twilio') }}
        </button>
    </div>
</form>
