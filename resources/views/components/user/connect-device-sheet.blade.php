{{--
    Global "Connect device" popover. Opened from any device picker via
    window.openConnectDevice() or a [data-connect-device] click (wired in
    app.js → initConnectDevice). It iframes the devices page in embed mode
    (/devices?embed=1) so the EXISTING channel chooser + connect flows
    (Unofficial QR / Official WABA / Twilio) run unchanged. On a successful
    connect the iframe posts `wadesk:device-connected` → app.js closes the sheet
    and dispatches a `device:connected` event so the picker behind it refreshes
    in place — the half-filled campaign/broadcast form is never lost.
--}}
@auth
    <div id="connect-device-sheet" class="hidden fixed inset-0 z-[80]" aria-hidden="true">
        <div class="absolute inset-0 bg-[rgba(11,31,28,0.5)]" data-cd-overlay></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:max-w-2xl sm:h-[660px] sm:rounded-2xl
                    h-[90vh] bg-paper-0 rounded-t-2xl shadow-[0_-24px_70px_-30px_rgba(11,31,28,0.6)] overflow-hidden flex flex-col"
            style="animation: plan-paywall-rise .28s cubic-bezier(.22,1,.36,1)">
            <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between shrink-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Connect') }}</div>
                    <h2 class="font-serif text-[18px] leading-tight">{{ __('Add a WhatsApp device') }}</h2>
                </div>
                <button type="button" data-cd-close
                    class="w-8 h-8 grid place-items-center rounded-full hover:bg-paper-50 text-ink-500" title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8" /></svg>
                </button>
            </div>
            <div class="flex-1 min-h-0 relative bg-paper-50">
                <div data-cd-loading class="absolute inset-0 grid place-items-center text-[12px] text-ink-500">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full border-2 border-paper-300 border-t-wa-deep animate-spin"></span>{{ __('Loading…') }}
                    </span>
                </div>
                {{-- src is set lazily by app.js on first open (and cleared on close to stop the QR poller) --}}
                <iframe data-cd-frame title="{{ __('Connect device') }}"
                    class="w-full h-full border-0 bg-transparent relative z-10" referrerpolicy="same-origin"></iframe>
            </div>
        </div>
    </div>
@endauth
