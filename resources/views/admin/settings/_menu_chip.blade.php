{{-- One movable nav item. Props: $key, $it (label/desc/locked), $locked --}}
<li data-key="{{ $key }}" data-label="{{ $it['label'] }}" @if($locked) data-locked="1" @endif
    draggable="{{ $locked ? 'false' : 'true' }}"
    class="group flex items-center gap-3 border border-paper-200 rounded-xl px-3 py-2.5 bg-paper-0 {{ $locked ? 'opacity-90' : 'hover:border-wa-deep/40 cursor-grab active:cursor-grabbing' }}">
    {{-- drag handle --}}
    <span class="text-ink-300 {{ $locked ? 'invisible' : '' }} shrink-0">
        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor"><circle cx="5" cy="4" r="1"/><circle cx="11" cy="4" r="1"/><circle cx="5" cy="8" r="1"/><circle cx="11" cy="8" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="11" cy="12" r="1"/></svg>
    </span>
    <span class="min-w-0 flex-1">
        <span class="block text-[13px] font-semibold text-ink-900 truncate">{{ $it['label'] }}</span>
        <span class="block text-[11px] text-ink-500 truncate">{{ $it['desc'] ?? '' }}@if($locked) · {{ __('always in the bar') }}@endif</span>
    </span>
    {{-- move-to-other-zone button --}}
    <button type="button" data-move
        class="shrink-0 w-7 h-7 rounded-lg border border-paper-200 text-ink-500 hover:border-wa-deep hover:text-wa-deep grid place-items-center text-[14px] leading-none">
        <span>⤓</span>
    </button>
</li>
