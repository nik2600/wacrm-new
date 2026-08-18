{{-- "+ Connect new device" — opens the GLOBAL connect popover (app.js →
     initConnectDevice). Drop next to any device/sender picker so a user can add
     a number inline without leaving the page. Optional $label override. --}}
<button type="button" data-connect-device
    class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-wa-deep hover:underline cursor-pointer">
    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
    {{ $label ?? __('Connect new device') }}
</button>
