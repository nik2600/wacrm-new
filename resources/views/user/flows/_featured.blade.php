@php
    $cat = $featured->category ?: 'flow';
    $catLabel = ucfirst(str_replace('-', ' ', $cat));
    $data = $featured->decoded_flow_data;
    $nodes = is_array($data['flowNodes'] ?? null) ? $data['flowNodes'] : [];
    $edges = is_array($data['flowEdges'] ?? null) ? $data['flowEdges'] : [];
    $stepCount = count($nodes);
    $messageCount = count(
        array_filter(
            $nodes,
            fn($n) => in_array($n['type'] ?? '', ['message', 'template', 'media', 'buttons', 'list', 'cta'], true),
        ),
    );
    $waitTotal = 0;
    foreach ($nodes as $n) {
        if (($n['type'] ?? '') === 'delay') {
            $amount = (int) ($n['data']['amount'] ?? 0);
            $unit = $n['data']['unit'] ?? 'min';
            $mins = match ($unit) {
                'sec' => max(1, intdiv($amount, 60)),
                'hour' => $amount * 60,
                'day' => $amount * 1440,
                default => $amount,
            };
            $waitTotal += $mins;
        }
    }
    $waitLabel =
        $waitTotal > 0
            ? '~' . ($waitTotal >= 60 ? round($waitTotal / 60, 1) . ' h' : $waitTotal . ' min') . ' total'
            : 'no waits';

    // Pick up to 3 nodes for the mini preview pane on the right.
    $previewable = array_slice(array_values(array_filter($nodes, fn($n) => !empty($n['type']))), 0, 4);

    $nodeStyle = function (string $type) {
        return match ($type) {
            'trigger' => 'start',
            'delay' => 'wait',
            'condition' => 'cond',
            'message', 'template', 'media', 'buttons', 'list' => 'send',
            'tag', 'assign' => 'tag',
            'end' => 'end',
            default => '',
        };
    };

    $nodeLabel = function (array $n) {
        $t = $n['type'] ?? '';
        $d = $n['data'] ?? [];
        return match ($t) {
            'trigger' => 'Trigger / ' . ($d['kind'] ?? 'manual'),
            'message' => \Illuminate\Support\Str::limit((string) ($d['text'] ?? 'Send message'), 28),
            'template' => 'Send template ' . ($d['tpl'] ?? ''),
            'media' => 'Send ' . ($d['kind'] ?? 'media'),
            'buttons' => 'Quick replies',
            'list' => 'List message',
            'ask' => \Illuminate\Support\Str::limit((string) ($d['prompt'] ?? 'Ask'), 28),
            'condition' => 'If ' . ($d['var'] ?? 'value') . ' ' . ($d['op'] ?? '==') . ' ' . ($d['value'] ?? ''),
            'delay' => 'Wait ' . ($d['amount'] ?? '?') . ' ' . ($d['unit'] ?? 'min'),
            'webhook' => 'Webhook ' . ($d['method'] ?? 'POST'),
            'ai' => 'AI ' . ($d['model'] ?? ''),
            'tag' => 'Tag: ' . ($d['action'] ?? 'add') . ' ' . ($d['tag'] ?? ''),
            'assign' => 'Assign to ' . ($d['team'] ?? 'team'),
            'end' => 'End flow',
            default => ucfirst($t),
        };
    };

    $stateBadge = $featured->is_published
        ? ['bg-wa-mint', 'text-wa-deep', 'bg-wa-green', 'Live']
        : ['bg-paper-50', 'text-ink-500', 'bg-paper-200', 'Draft'];
@endphp

<div
    class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-hidden mb-5 grid grid-cols-1 xl:grid-cols-[1.5fr_1fr]">
    <div class="p-6 md:p-7">
        <div class="flex items-center gap-2 flex-wrap mb-4">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-bubble text-wa-deep">{{ $catLabel }}</span>
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 flex gap-1">
                <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M2 6.5l3 2 5-6" />
                </svg>
                {{ __('Most used') }}
            </span>
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $stateBadge[0] }} {{ $stateBadge[1] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $stateBadge[2] }}"></span>{{ $stateBadge[3] }}
            </span>
        </div>
        <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
            {{ __('Your most-used flow') }}</div>
        <h2 class="serif font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[32px] lg:text-[36px] leading-[1.05] tracking-tight">
            {{ $featured->flow_name }}</h2>
        <div class="mt-3 flex items-center flex-wrap gap-x-3 gap-y-1.5 text-[12.5px] text-ink-500 mono font-mono">
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M4 4v8M12 4v8M4 8h8" />
                </svg>{{ $stepCount }} {{ \Illuminate\Support\Str::plural('step', $stepCount) }}</span>
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <rect x="2" y="3" width="12" height="9" rx="1.5" />
                    <path d="M5 15h6" />
                </svg>{{ $messageCount }} {{ \Illuminate\Support\Str::plural('message', $messageCount) }}</span>
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M8 5v3l2 2" />
                </svg>{{ $waitLabel }}</span>
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M3 8l3 3 7-7" />
                </svg>{{ count($edges) }} {{ \Illuminate\Support\Str::plural('connection', count($edges)) }}</span>
        </div>
        <p class="mt-4 text-[14px] leading-[1.65] text-ink-700 max-w-xl">
            Updated {{ $featured->updated_at?->diffForHumans() ?? '/' }}. Open it to keep iterating, or duplicate it as
            the starting point for a new flow.
        </p>
        <div class="mt-6 flex items-center gap-2">
            <a href="{{ url('/flows/builder/' . $featured->id) }}"
                class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Open flow') }}</a>
            <form method="POST" action="{{ url('/flows/' . $featured->id . '/duplicate') }}" class="inline">@csrf
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">{{ __('Duplicate') }}
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 13l10-10M5 3h8v8" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <div
        class="hairline-t border-t border-paper-200 xl:hairline-t-0 xl:hairline-l p-5 stripe-bg bg-[repeating-linear-gradient(135deg,rgba(7,94,84,0.05)_0_6px,transparent_6px_12px)] flex items-center justify-center">
        <div class="w-full max-w-xs">
            @if (empty($previewable))
                <div
                    class="hairline border border-dashed border-paper-200 bg-paper-0 rounded-[10px] px-3 py-6 text-center text-[11px] text-ink-500">
                    {{ __('This flow has no nodes yet. Open the builder to add steps.') }}
                </div>
            @else
                @foreach ($previewable as $idx => $n)
                    @php
                        $cls = $nodeStyle($n['type'] ?? '');
                        $label = $nodeLabel($n);
                        $base =
                            'fl-node bg-white border border-dashed border-[#C9D7CE] rounded-[10px] px-2.5 py-2 text-[11px] text-ink-700 flex items-center gap-1.5';
                        $variant = match ($cls) {
                            'start' => 'bg-wa-bubble border-solid border-wa-green text-wa-deep font-medium',
                            'send' => 'bg-wa-bubble border-solid border-wa-green/40 text-wa-deep',
                            'wait' => 'bg-[#FFF6E0] border-solid border-accent-amber text-[#7B5A14]',
                            'cond' => 'bg-[#F4E9C9] border-solid border-[#D9B864] text-[#7B5A14]',
                            'tag' => 'bg-[#E4DAF1] border-solid border-[#B59FE0] text-[#5B3D8A]',
                            'end' => 'bg-[#FCE0D5] border-solid border-accent-coral text-[#A1431F] font-medium',
                            default => '',
                        };
                    @endphp
                    <div class="{{ $base }} {{ $variant }}">
                        <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="6" cy="6" r="3" />
                        </svg>
                        {{ $label }}
                    </div>
                    @if (!$loop->last)
                        <div class="flex justify-center text-ink-500 my-1.5">↓</div>
                    @endif
                @endforeach
                @if ($stepCount > count($previewable))
                    <div class="text-center text-[10px] mono font-mono text-ink-500 mt-2">/
                        {{ $stepCount - count($previewable) }} more
                        {{ \Illuminate\Support\Str::plural('step', $stepCount - count($previewable)) }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
