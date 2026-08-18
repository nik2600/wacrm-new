{{--
 /devices when active engine = twilio.
 Renders the operator's connected Twilio account or the connect form.
 Mirrors the WABA section's empty-state + card layout so multi-engine
 workspaces feel consistent when they switch between modes.

 Vars expected (passed from DevicesController):
 $twilioAccount — WaProviderConfig row for provider=twilio, or null
 $twilioAdminDefaults — ['account_sid' => str, 'whatsapp_number' => str]
 — soft-hint admin-shared creds the user can
 inherit by leaving fields blank
--}}
@php
    $tw = $twilioAccount ?? null;
    $creds = $tw ? $tw->creds() : [];
    $sandbox = !empty($tw?->meta_json['sandbox']);
    $isConnected = $tw && $tw->isConnected();
    $defaults = is_array($twilioAdminDefaults ?? null)
        ? $twilioAdminDefaults
        : ['account_sid' => '', 'whatsapp_number' => ''];
    $hasAdminDefaults = $defaults['account_sid'] !== '' && $defaults['whatsapp_number'] !== '';
@endphp

<section class="space-y-5">

    @if (!$isConnected)
        @if (!empty($multiEngine))
            {{-- Multi-engine: NO big connect card on the page. Connect happens via
                 "Add device" → Twilio modal. Just a slim "not connected" line. --}}
            <div
                class="bg-paper-0 border border-dashed border-paper-200 rounded-[14px] px-5 py-4 text-[12.5px] text-ink-500">
                {{ __('No Twilio sender connected yet. Click "Add device" above, then choose Twilio.') }}
            </div>
        @else
            {{-- Single-engine empty state: full inline connect form. --}}
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center gap-3 flex-wrap">
                    <div
                        class="inline-flex items-center justify-center w-10 h-10 rounded-2xl bg-[#F22F46]/10 text-[#A12534] shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 7l9-4 9 4-9 4-9-4zm0 5l9 4 9-4M3 17l9 4 9-4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Twilio · WhatsApp') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Connect your Twilio account') }}</h2>
                    </div>
                </div>
                <div class="p-5 md:p-7">
                    @include('user.devices._twilio_form')
                </div>
            </div>
        @endif
    @else
        {{-- Connected — show the account card with disconnect + rotate options. --}}
        <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-2 text-[12px]">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Workspace · Twilio account') }}</span>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#F22F46]/10 text-[#A12534] border border-[#F22F46]/25 text-[10px] font-mono font-semibold uppercase tracking-wider">
                        {{ $sandbox ? __('Sandbox') : __('Production') }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint/40 text-wa-deep text-[10px] font-mono font-semibold uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('connected') }}
                    </span>
                </div>
                <form method="POST" action="{{ url('/connect/wa-store/twilio') }}"
                    onsubmit="return confirm('Replace these credentials with new ones?');">
                    @csrf
                    <button type="submit"
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold">
                        {{ __('Reconnect / rotate') }}
                    </button>
                </form>
            </div>

            <div class="p-5">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-[12px]">
                    <div class="min-w-0">
                        <dt class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                            {{ __('From number') }}</dt>
                        <dd class="font-mono text-ink-900">{{ $tw->phone_number ?: '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                            {{ __('Account SID') }}</dt>
                        <dd class="font-mono text-ink-700 truncate" title="{{ $creds['account_sid'] ?? '' }}">
                            {{ $creds['account_sid'] ? substr($creds['account_sid'], 0, 8) . '…' . substr($creds['account_sid'], -4) : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                            {{ __('Connected at') }}</dt>
                        <dd class="font-mono text-ink-700">{{ optional($tw->connected_at)->diffForHumans() ?: '—' }}
                        </dd>
                    </div>
                </dl>

                <p class="text-[11.5px] text-ink-500 mt-4 leading-relaxed max-w-2xl">
                    {{ __('All broadcasts, campaigns, scheduled sends, auto-replies, and team-inbox replies on this workspace use this Twilio account. Inbound messages from your customers will be received via the Twilio webhook configured in your Twilio console.') }}
                </p>
            </div>
        </div>

    @endif

</section>
