{{-- Meta Commerce Catalog connect form. Used in two places:
 1. Standalone (no Baileys, no Meta yet) — full setup page
 2. Inside the "Optional · advanced" disclosure on the Baileys
 ready state, when the operator decides to also link Meta.
 `$compact = true` tightens vertical spacing for the disclosure. --}}
<form method="POST" action="{{ route('user.catalog.connect') }}"
    class="@if (!($compact ?? false)) mt-5 @else mt-4 @endif grid grid-cols-1 sm:grid-cols-2 gap-4">
    @csrf
    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Provider') }}</span>
        <select name="provider" required
            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
            <option value="meta_cloud">{{ __('Meta Cloud API (direct)') }}</option>
            <option value="dialog_360">360dialog (BSP)</option>
        </select>
    </label>
    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Catalog ID') }} <span
                class="text-accent-coral">*</span></span>
        <input type="text" name="catalog_id" required maxlength="64" placeholder="194836011239..."
            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
    </label>
    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Catalog name') }} <span
                class="text-ink-500 font-normal">(label)</span></span>
        <input type="text" name="catalog_name" maxlength="191" placeholder="{{ __('e.g. Main Catalog') }}"
            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
    </label>
    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('WABA ID') }}</span>
        <input type="text" name="waba_id" maxlength="64" placeholder="123456789012345"
            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
    </label>
    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Phone number ID') }}</span>
        <input type="text" name="phone_number_id" maxlength="64" placeholder="106540352242922"
            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
    </label>
    <label class="block">
        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Access token / API key') }} <span
                class="text-accent-coral">*</span></span>
        <input type="password" name="access_token" required minlength="20" maxlength="4096"
            placeholder="{{ __('EAAxx... or D360-API-KEY...') }}"
            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
        <span
            class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Stored encrypted at rest. Verified against Meta on save.') }}</span>
    </label>
    <div class="md:col-span-2 flex items-center justify-end gap-2 pt-2 border-t border-paper-200">
        @if (!($compact ?? false))
            <a href="{{ url('/integrations') }}"
                class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</a>
        @endif
        <button type="submit"
            class="px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Connect & verify') }}</button>
    </div>
</form>
