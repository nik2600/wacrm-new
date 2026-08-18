{{-- Inbound-wired verdict badge for a WABA number.
     Expects $wired: true (Meta delivers this number's incoming messages here),
     false (it does NOT — inbound needs fixing), or null (not checked yet). --}}
@php $w = $wired ?? null; @endphp
@if ($w === true)
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold bg-wa-mint text-wa-deep"
        title="{{ __('Meta is delivering this number\'s incoming messages to this platform.') }}">
        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.2 3.2L13 5" /></svg>
        {{ __('Inbound wired') }}
    </span>
@elseif ($w === false)
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold bg-accent-coral/10 text-accent-coral"
        title="{{ __('Meta is NOT delivering this number\'s incoming messages here — click Fix inbound.') }}">
        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6.2" /><path d="M8 4.7v4M8 11h.01" /></svg>
        {{ __('Inbound not wired') }}
    </span>
@else
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold bg-paper-100 text-ink-500"
        title="{{ __('Inbound not verified yet — click Fix inbound to check.') }}">
        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6.2" /><path d="M6.3 6.1a1.8 1.8 0 1 1 2.5 1.8c-.5.2-.8.6-.8 1.1M8 11h.01" /></svg>
        {{ __('Inbound: check') }}
    </span>
@endif
