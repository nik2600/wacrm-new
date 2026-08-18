@php
    $steps = [
        ['num' => 1, 'label' => 'Welcome'],
        ['num' => 2, 'label' => 'Requirements'],
        ['num' => 3, 'label' => 'Database'],
        ['num' => 4, 'label' => 'Application'],
        ['num' => 5, 'label' => 'Admin account'],
        ['num' => 6, 'label' => 'Node bridge'],
        ['num' => 7, 'label' => 'Install'],
    ];
@endphp

<div class="space-y-0.5">
    @foreach ($steps as $step)
        @php
            $isDone = $currentStep > $step['num'];
            $isActive = $currentStep == $step['num'];
        @endphp
        <div @class([
            'flex items-center gap-2.5 py-1.5 px-2.5 rounded-xl transition-all',
            'bg-wa-mint/40' => $isActive,
        ])>
            @if ($isDone)
                <span data-step-done="{{ $step['num'] }}"
                    class="w-7 h-7 rounded-full bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l3 3 7-8" />
                    </svg>
                </span>
            @elseif ($isActive)
                <span data-step-active="{{ $step['num'] }}"
                    class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[12px] font-semibold shrink-0 shadow-card">
                    {{ $step['num'] }}
                </span>
            @else
                <span
                    class="w-7 h-7 rounded-full bg-paper-100 border border-paper-200 grid place-items-center font-mono text-[11px] text-ink-500 shrink-0">
                    {{ $step['num'] }}
                </span>
            @endif

            <span @class([
                'text-[12px] font-mono uppercase tracking-[0.14em] transition-colors',
                'text-wa-deep font-semibold' => $isActive,
                'text-ink-700' => $isDone,
                'text-ink-500' => !$isActive && !$isDone,
            ])>
                {{ $step['label'] }}
            </span>
        </div>
    @endforeach
</div>
