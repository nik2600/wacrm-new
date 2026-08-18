@props([
    // A Collection of sender arrays from WorkspaceEngine::senders():
    //   ['key' => 'engine:id', 'engine', 'id', 'phone', 'label', 'descriptor', 'is_default']
    'senders' => null,
    // Submitted field name. Single mode posts `{name}`; multi mode posts `{name}[]`.
    'name' => 'sender',
    'multiple' => false,
    // Pre-selected composite key(s): a string (single) or array (multi).
    'selected' => null,
    'id' => null,
    'required' => false,
    'placeholder' => null,
])

@php
    use Illuminate\Support\Collection;
    use App\Services\WorkspaceEngine;

    $senders = $senders instanceof Collection ? $senders : collect($senders ?? []);
    $pickerId = $id ?: ('senderpicker-' . \Illuminate\Support\Str::random(6));
    $placeholder = $placeholder ?: __('— Select a sender —');

    // Normalise pre-selected value(s) to a set of composite keys.
    $selectedKeys = collect(is_array($selected) ? $selected : ($selected !== null && $selected !== '' ? [$selected] : []))
        ->map(fn ($v) => (string) $v)->filter()->values()->all();

    // Group by engine, preserving senders() order (default engine first).
    $byEngine = $senders->groupBy('engine');
    $engineOrder = $senders->pluck('engine')->unique()->values();
    $multiEngine = $engineOrder->count() > 1;

    // Engine heading uses the descriptor label (baileys → "Unofficial API").
    $engineLabel = function ($engine) use ($senders) {
        $first = $senders->firstWhere('engine', $engine);
        return $first['descriptor']['label'] ?? WorkspaceEngine::descriptor($engine)['label'];
    };
@endphp

{{-- Wrapped in [data-device-picker] so the global Connect-device popover can
     refresh this picker in place (app.js → refreshDevicePickers) after a new
     device connects — without reloading the page or losing the form. The
     `contents` class means the wrapper adds no box of its own. --}}
<div data-device-picker class="contents">
@if ($senders->isEmpty())
    <div class="text-[11px] text-accent-coral border border-accent-coral/30 rounded-lg px-3 py-2 bg-accent-coral/5 flex items-center gap-2 flex-wrap">
        {{ __('No connected senders.') }}
        <button type="button" data-connect-device class="font-semibold text-wa-deep hover:underline cursor-pointer">{{ __('Connect one →') }}</button>
    </div>
@elseif ($multiple)
    {{-- Multi-select: grouped checkbox list. One engine = no headers (looks
         identical to a flat list); 2+ engines = a sticky header per engine. --}}
    <div class="rounded-lg border border-paper-200 bg-white max-h-56 overflow-y-auto" data-sender-picker>
        @foreach ($engineOrder as $engine)
            @if ($multiEngine)
                <div class="px-3 py-1.5 bg-paper-50 border-b border-paper-100 flex items-center gap-2 sticky top-0 z-10">
                    <span class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">{{ $engineLabel($engine) }}</span>
                </div>
            @endif
            @foreach ($byEngine[$engine] as $s)
                <label
                    class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] cursor-pointer hover:bg-paper-50 border-b border-paper-100 last:border-b-0 has-[:checked]:bg-wa-mint/40">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $s['key'] }}"
                        @checked(in_array($s['key'], $selectedKeys, true))
                        class="w-4 h-4 rounded accent-wa-deep shrink-0">
                    <span class="flex-1 min-w-0">
                        <span class="block truncate">{{ $s['label'] }}@if ($s['is_default'] && $multiEngine)
                                <span class="text-[9px] text-wa-deep font-mono">· {{ __('default') }}</span>
                            @endif</span>
                        <span class="block font-mono text-[10px] text-ink-500 truncate">{{ $s['phone'] }}</span>
                    </span>
                </label>
            @endforeach
        @endforeach
    </div>
@else
    {{-- Single-select dropdown. 2+ engines = grouped via <optgroup>. --}}
    <select id="{{ $pickerId }}" name="{{ $name }}" @if ($required) required @endif
        {{ $attributes->merge(['class' => 'w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10']) }}>
        <option value="">{{ $placeholder }}</option>
        @if ($multiEngine)
            @foreach ($engineOrder as $engine)
                <optgroup label="{{ $engineLabel($engine) }}">
                    @foreach ($byEngine[$engine] as $s)
                        <option value="{{ $s['key'] }}" @selected(in_array($s['key'], $selectedKeys, true))>
                            {{ $s['label'] }} · {{ $s['phone'] }}{{ $s['is_default'] ? ' · ' . __('default') : '' }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        @else
            @foreach ($senders as $s)
                <option value="{{ $s['key'] }}" @selected(in_array($s['key'], $selectedKeys, true))>
                    {{ $s['label'] }} · {{ $s['phone'] }}</option>
            @endforeach
        @endif
    </select>
@endif

@unless ($senders->isEmpty())
    {{-- Always-available: add another number without leaving this form. --}}
    <button type="button" data-connect-device
        class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold text-wa-deep hover:underline cursor-pointer">
        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
        {{ __('Connect new device') }}
    </button>
@endunless
</div>{{-- /data-device-picker --}}
