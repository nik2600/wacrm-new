@php
    $title = $title ?? 'No data found';
    $message = $message ?? 'Try clearing filters, or create a new item.';
    $class = $class ?? '';
    $id = $id ?? null;
    $resetHref = $resetHref ?? null;
    $resetLabel = $resetLabel ?? 'Reset filters';
    $resetButtonAttrs = $resetButtonAttrs ?? null;
    $actionHref = $actionHref ?? null;
    $actionLabel = $actionLabel ?? 'Create';
    $actionButtonAttrs = $actionButtonAttrs ?? null;
@endphp

<div @if ($id) id="{{ $id }}" @endif data-list-grid-empty
    class="{{ trim('bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 md:p-10 shadow-card text-center ' . $class) }}">
    <div class="font-serif text-[22px] md:text-[24px] leading-tight text-ink-900">{{ $title }}</div>
    <p class="mt-2 text-[13px] text-ink-500 max-w-2xl mx-auto">{{ $message }}</p>

    @if ($resetHref || $resetButtonAttrs || $actionHref || $actionButtonAttrs)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            @if ($resetHref)
                <a href="{{ $resetHref }}"
                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                    {{ $resetLabel }} </a>
            @elseif ($resetButtonAttrs)
                <button type="button" {!! $resetButtonAttrs !!}
                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                    {{ $resetLabel }} </button>
            @endif

            @if ($actionHref)
                <a href="{{ $actionHref }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                    {{ $actionLabel }} </a>
            @elseif ($actionButtonAttrs)
                <button type="button" {!! $actionButtonAttrs !!}
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                    {{ $actionLabel }} </button>
            @endif
        </div>
    @endif
</div>
