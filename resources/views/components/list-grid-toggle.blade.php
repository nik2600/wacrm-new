@props(['active' => 'list', 'target' => null])

<div
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 p-1 rounded-full border border-paper-200 bg-paper-0']) }}>
    <button type="button" data-list-grid-toggle="list"
        @if ($target) data-list-grid-target="{{ $target }}" @endif
        title="{{ __('List view') }}"
        class="w-8 h-8 rounded-full inline-flex items-center justify-center transition {{ $active === 'list' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M5 4h9M5 8h9M5 12h9" />
            <path d="M2 4h.01M2 8h.01M2 12h.01" stroke-linecap="round" />
        </svg>
    </button>
    <button type="button" data-list-grid-toggle="grid"
        @if ($target) data-list-grid-target="{{ $target }}" @endif
        title="{{ __('Grid view') }}"
        class="w-8 h-8 rounded-full inline-flex items-center justify-center transition {{ $active === 'grid' ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">
        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
            <rect x="2.5" y="2.5" width="4" height="4" rx="1" />
            <rect x="9.5" y="2.5" width="4" height="4" rx="1" />
            <rect x="2.5" y="9.5" width="4" height="4" rx="1" />
            <rect x="9.5" y="9.5" width="4" height="4" rx="1" />
        </svg>
    </button>
</div>
