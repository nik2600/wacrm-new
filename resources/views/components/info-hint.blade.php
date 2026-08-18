@props([
    'text' => '',      // help copy shown in the bubble
    'title' => '',     // optional bold heading inside the bubble
    'align' => 'center', // center | left | right — where the bubble anchors
])

@php
    $panelPos = match ($align) {
        'left'  => 'left-0',
        'right' => 'right-0',
        default => 'left-1/2 -translate-x-1/2',
    };
@endphp

{{-- Reusable help tooltip: a small ⓘ button that reveals a click-to-open
     bubble. Wired globally by resources/js/lib/info-hint.js — no per-page JS. --}}
<span class="relative inline-flex align-middle" data-info-hint>
    <button type="button" data-info-hint-toggle aria-label="{{ __('More info') }}"
        class="w-[15px] h-[15px] rounded-full text-paper-300 hover:text-wa-deep inline-flex items-center justify-center leading-none shrink-0 transition-colors">
        <svg viewBox="0 0 16 16" class="w-[15px] h-[15px]" fill="currentColor">
            <circle cx="8" cy="8" r="8" />
            <circle cx="8" cy="4.6" r="1.15" fill="#fff" />
            <rect x="7.05" y="6.8" width="1.9" height="5.2" rx="0.95" fill="#fff" />
        </svg>
    </button>
    <span data-info-hint-panel role="tooltip"
        class="hidden absolute {{ $panelPos }} top-full mt-1.5 z-50 w-[230px] max-w-[80vw] p-2.5 rounded-lg bg-ink-900 text-paper-0 text-[11px] leading-snug shadow-soft text-left font-normal normal-case tracking-normal">
        @if ($title)
            <span class="block font-semibold mb-0.5">{{ $title }}</span>
        @endif
        {{ $text ?: $slot }}
    </span>
</span>
