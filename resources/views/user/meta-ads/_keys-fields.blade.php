{{-- Shared Meta Ads key-entry fields. Used by the standalone
 /meta-ads/connect page AND the modal on /meta-ads. Expects:
 $hasToken, $adAccountId, $pageId, $phone, $wabaId, $phoneNumberId. --}}
<div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
    <div class="sec-head flex items-center gap-2.5 mb-3">
        <span
            class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
        <span
            class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('API credentials') }}</span>
        <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
    </div>
    {{-- Auto-detect from the token already granted via WhatsApp embedded signup /
         coexistence — so the operator doesn't have to paste raw ad-account/page
         IDs. Fills the fields below (or connects outright when only one exists). --}}
    <div class="mb-3 rounded-lg border border-wa-green/30 bg-wa-bubble/40 px-3 py-2.5 flex items-center gap-3">
        <div class="flex-1 min-w-0">
            <div class="text-[12px] font-semibold text-wa-deep">{{ __('Connected WhatsApp through Meta?') }}</div>
            <div class="text-[10.5px] text-ink-600 mt-0.5">{{ __('Auto-detect your ad account & page from that login — no need to paste IDs.') }}</div>
        </div>
        <button type="button" data-meta-discover
            class="shrink-0 text-[11.5px] font-semibold px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-deep/90 transition inline-flex items-center gap-1.5">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2" />
            </svg><span data-meta-discover-label>{{ __('Auto-detect') }}</span>
        </button>
    </div>
    <div data-meta-discover-msg class="hidden mb-3 text-[11px] rounded-lg px-3 py-2"></div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
            <label
                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Access token (System User)') }}
                <span class="req text-accent-coral">*</span></label>
            <input name="token" type="password" autocomplete="new-password"
                placeholder="{{ $hasToken ? __('•••••••••• saved — leave blank to keep') : 'EAAG…' }}"
                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-mono placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
            <p class="mt-1 text-[10.5px] text-ink-500">{{ __('System-User token with') }} <code
                    class="font-mono">ads_management</code> (+ <code class="font-mono">pages_manage_ads</code>).
                {{ __('Encrypted at rest.') }}</p>
            @if ($hasToken)
                <label class="mt-1.5 flex items-center gap-2 text-[11px] text-ink-500"><input type="checkbox"
                        name="clear_token" value="1" class="rounded border-paper-300">
                    {{ __('Clear the saved token') }}</label>
            @endif
        </div>
        <div>
            <label
                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Ad account ID') }}
                <span class="req text-accent-coral">*</span></label>
            <div
                class="flex items-center rounded-lg border border-paper-200 bg-white overflow-hidden focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10 transition">
                <span
                    class="px-2.5 py-[7px] text-[12.5px] font-mono text-ink-500 bg-paper-50 border-r border-paper-200">act_</span>
                <input name="ad_account_id" type="text" value="{{ old('ad_account_id', $adAccountId) }}"
                    placeholder="1234567890" required
                    class="flex-1 px-[11px] py-[7px] text-[12.5px] text-ink-900 font-mono leading-[1.4] focus:outline-none">
            </div>
        </div>
        <div>
            <label
                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Facebook Page ID') }}
                <span class="req text-accent-coral">*</span></label>
            <input name="page_id" type="text" value="{{ old('page_id', $pageId) }}" placeholder="1029384756"
                required
                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-mono placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
        </div>
    </div>
</div>

<div class="sec px-[18px] py-4">
    <div class="sec-head flex items-center gap-2.5 mb-3">
        <span
            class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
        <span
            class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('WhatsApp destination') }}</span>
        <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label
                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('WhatsApp number') }}</label>
            <input name="phone" type="text" value="{{ old('phone', $phone) }}" placeholder="{{ __('Number with country code') }}"
                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-mono placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
            <p class="mt-1 text-[10.5px] text-ink-500">{{ __('E.164 digits — where ad chats land.') }}</p>
        </div>
        <div>
            <label
                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('WABA ID') }}</label>
            <input name="waba_id" type="text" value="{{ old('waba_id', $wabaId) }}" placeholder="{{ __('Meta WABA ID') }}"
                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-mono placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
        </div>
        <div>
            <label
                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Phone number ID') }}</label>
            <input name="phone_number_id" type="text" value="{{ old('phone_number_id', $phoneNumberId) }}"
                placeholder="{{ __('Meta phone number ID') }}"
                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-mono placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
            <p class="mt-1 text-[10.5px] text-ink-500">{{ __("Meta's internal id — messaging only.") }}</p>
        </div>
    </div>
</div>
