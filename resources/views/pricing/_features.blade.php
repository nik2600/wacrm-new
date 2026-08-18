{{-- Feature checklist inside a plan card. Driven by Package::featureCatalog()
 so it lists EVERYTHING the plan grants. The first few show by default; the
 rest collapse behind a pure-CSS "Show all" toggle (a hidden peer checkbox)
 so long plans don't make giant cards.
 @param $p Package
 @param $iconCls CSS class for the check ticks
 @param $textCls CSS class for the feature text (dark vs light card)
 @param $moreCls CSS class for the show-more link --}}
@php
    $catalog = \App\Models\Package::featureCatalog();
    $textCls = $textCls ?? 'text-ink-700';
    $moreCls = $moreCls ?? 'text-wa-deep';
    $tick =
        '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 ' .
        $iconCls .
        ' shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>';
    $fmt = function ($v) {
        if ($v === null || $v === '') {
            return null;
        }
        if ((int) $v === 0) {
            return 'Unlimited';
        }
        if ($v >= 1000) {
            return number_format($v / 1000, $v >= 100000 ? 0 : 1) . 'k';
        }
        return number_format($v);
    };

    // Build the full ordered list of rendered <li> rows.
    $items = [];
    foreach ($catalog['limits'] as $key => $label) {
        $v = $p->{$key} ?? null;
        if ($v !== null && $v !== '') {
            $items[] =
                '<li class="flex gap-2">' .
                $tick .
                '<span><span class="font-mono">' .
                e($fmt($v)) .
                '</span> ' .
                e($label) .
                '</span></li>';
        }
    }
    foreach ($catalog['capabilities'] as $key => $label) {
        if ((bool) ($p->{$key} ?? false)) {
            $items[] = '<li class="flex gap-2">' . $tick . '<span>' . e($label) . '</span></li>';
        }
    }

    $visibleCount = 7;
    $first = array_slice($items, 0, $visibleCount);
    $rest = array_slice($items, $visibleCount);
    $chevDown =
        '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>';
    $chevUp =
        '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10l4-4 4 4"/></svg>';
@endphp

<ul class="space-y-2 text-[12px] {{ $textCls }}">
    @foreach ($first as $li)
        {!! $li !!}
    @endforeach
</ul>

@if (!empty($rest))
    {{-- Hidden checkbox is the peer; the rest-list + both labels are its siblings. --}}
    <input type="checkbox" id="feat-more-{{ $p->id }}" class="peer sr-only" aria-hidden="true">
    <ul class="space-y-2 text-[12px] {{ $textCls }} mt-2 hidden peer-checked:block">
        @foreach ($rest as $li)
            {!! $li !!}
        @endforeach
    </ul>
    <label for="feat-more-{{ $p->id }}"
        class="peer-checked:hidden mt-3 inline-flex items-center gap-1 cursor-pointer text-[11.5px] font-semibold {{ $moreCls }} hover:underline">
        {{ __('Show all :n features', ['n' => count($items)]) }} {!! $chevDown !!}
    </label>
    <label for="feat-more-{{ $p->id }}"
        class="hidden peer-checked:inline-flex mt-3 items-center gap-1 cursor-pointer text-[11.5px] font-semibold {{ $moreCls }} hover:underline">
        {{ __('Show less') }} {!! $chevUp !!}
    </label>
@endif
