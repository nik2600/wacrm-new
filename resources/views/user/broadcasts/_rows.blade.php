@php
    /** @var \Illuminate\Support\Collection $broadcasts */
    $statusBadge = [
        'completed' => 'bg-ink-900 text-paper-0',
        'completed_with_errors' => 'bg-accent-amber/15 text-ink-800 border border-accent-amber/30',
        'processing' => 'bg-wa-green/15 text-wa-deep border border-wa-green/30',
        'scheduled' => 'bg-accent-amber/15 text-ink-800 border border-accent-amber/30',
        'failed' => 'bg-accent-coral/15 text-accent-coral border border-accent-coral/30',
    ];
    // Provider chip palette — same hues as /devices + /team-inbox so the
    // operator can recognise the engine at a glance across the app.
    $providerBadge = [
        'baileys' => ['Unofficial API', 'bg-ink-900/5 text-ink-700 border border-ink-200'],
        'waba' => ['WABA', 'bg-wa-mint/40 text-wa-deep border border-wa-deep/20'],
        'twilio' => ['Twilio', 'bg-[#F22F46]/10 text-[#A12534] border border-[#F22F46]/25'],
    ];
    // Resolve template names in one batch so the rows don't N+1 against
// wa_templates. Pre-fetch by the ids on this page.
$tplIds = collect($broadcasts->items() ?? $broadcasts)
    ->pluck('template_id')
    ->filter()
    ->unique()
    ->all();
$tplNames = $tplIds ? \App\Models\WaTemplate::whereIn('id', $tplIds)->pluck('template_name', 'id')->all() : [];
@endphp

@forelse ($broadcasts as $b)
    @php
        $counts = $b->status_counts;
        $cls = $statusBadge[$b->status] ?? 'bg-paper-50 text-ink-700 border border-paper-200';
        $prov = strtolower((string) ($b->provider ?? ''));
        [$provLabel, $provCls] = $providerBadge[$prov] ?? ['—', 'bg-paper-50 text-ink-500 border border-paper-200'];
        $tplName = $b->template_id ? $tplNames[$b->template_id] ?? 'Template #' . $b->template_id : null;
    @endphp
    <tr data-broadcast-row data-broadcast-id="{{ $b->id }}" class="hover:bg-paper-50/50">
        <td class="px-4 py-3">
            <div class="flex items-center gap-2 min-w-0">
                <div class="font-semibold truncate min-w-0">{{ $b->name }}</div>
                <span
                    class="inline-flex items-center px-1.5 py-0.5 rounded-full {{ $provCls }} text-[9.5px] font-mono font-semibold uppercase tracking-wider shrink-0">{{ $provLabel }}</span>
            </div>
            <div class="text-[10.5px] text-ink-500">Broadcast #BR-{{ str_pad((string) $b->id, 4, '0', STR_PAD_LEFT) }}
            </div>
        </td>
        <td class="px-3 py-3 truncate">{{ $tplName ?? '—' }}</td>
        <td class="px-3 py-3 font-mono">{{ number_format($b->total_recipients) }}</td>
        <td class="px-3 py-3 font-mono text-wa-deep">{{ number_format($counts['sent']) }}</td>
        <td class="px-3 py-3 font-mono">{{ number_format($counts['delivered']) }}</td>
        <td class="px-3 py-3 font-mono">{{ number_format($counts['read']) }}</td>
        <td class="px-3 py-3 font-mono {{ $counts['failed'] > 0 ? 'text-accent-coral' : 'text-ink-500' }}">
            {{ number_format($counts['failed']) }}</td>
        <td class="px-3 py-3 font-mono {{ ($counts['clicked'] ?? 0) > 0 ? 'text-wa-deep' : 'text-ink-500' }}">
            {{ number_format($counts['clicked'] ?? 0) }}
        </td>
        <td class="px-3 py-3">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $cls }} text-[10.5px] font-mono">
                {{ ucwords(str_replace('_', ' ', $b->status)) }}
            </span>
        </td>
        <td class="px-4 py-3 font-mono text-[11px]">
            @if ($b->scheduled_at)
                {{ $b->scheduled_at->isToday() ? 'Today, ' . $b->scheduled_at->format('H:i') : $b->scheduled_at->format('M d, H:i') }}
            @else
                —
            @endif
        </td>
        <td class="px-3 py-3 text-right">
            <div class="inline-flex items-center gap-1">
                {{-- View deep analytics: per-recipient timeline,
 funnel, template preview, device used. --}}
                <a href="{{ route('user.broadcasts.detail', $b->id) }}"
                    class="w-7 h-7 rounded-full border border-paper-200 bg-paper-0 hover:bg-wa-mint/40 hover:border-wa-deep hover:text-wa-deep grid place-items-center"
                    title="{{ __('View analytics') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 13V3M2 13h12M5 11V7m3 4V5m3 6V8" />
                    </svg>
                </a>
                @if (in_array($b->status, ['scheduled', 'failed'], true))
                    <button data-broadcast-delete="{{ $b->id }}" data-name="{{ $b->name }}" type="button"
                        class="w-7 h-7 rounded-full border border-paper-200 bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral grid place-items-center"
                        title="{{ __('Delete') }}">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10" />
                        </svg>
                    </button>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="11" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No broadcasts match the current filters. Try clearing filters or create your first broadcast.',
                'resetHref' => url('/broadcasts'),
                'actionHref' => route('user.broadcasts.create'),
                'actionLabel' => 'Create broadcast',
            ])
        </td>
    </tr>
@endforelse
