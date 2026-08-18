@props([
    'title' => 'Tip',
])

{{--
 Top-of-sidebar tip block. Same visual treatment used on /meta-ads
 (campaigns.index) — keeps every list page's left rail consistent.
--}}
<div
    {{ $attributes->merge(['class' => 'hairline border border-paper-200 rounded-2xl bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-relaxed']) }}>
    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-1.5">
        <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">
            <circle cx="8" cy="8" r="6" />
        </svg>
        {{ $title }}
    </div>
    {{ $slot }}
</div>
