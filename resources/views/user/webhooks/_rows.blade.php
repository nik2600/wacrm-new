@php
    $iconPalettes = [
        'wa-mint' => ['bg' => 'bg-wa-mint', 'fg' => 'text-wa-deep'],
        'blue' => ['bg' => 'bg-[#D9E5F2]', 'fg' => 'text-[#13478A]'],
        'purple' => ['bg' => 'bg-[#F3E9FF]', 'fg' => 'text-[#5B3D8A]'],
        'green' => ['bg' => 'bg-[#E8F5E9]', 'fg' => 'text-wa-deep'],
        'paused' => ['bg' => 'bg-paper-100', 'fg' => 'text-ink-700'],
    ];
@endphp
@forelse ($hooks as $hook)
    @php
        $events = $hook->events ?? [];
        $shownTags = array_slice($events, 0, 2);
        $extra = max(0, count($events) - count($shownTags));
        $palette = $iconPalettes[$hook->icon_color ?? 'wa-mint'] ?? $iconPalettes['wa-mint'];
        if ($hook->state_label === 'paused') {
            $palette = $iconPalettes['paused'];
        }
        $rate = $hook->success_rate;
        $rateBar =
            $hook->state_label === 'failing' ? 'bg-accent-amber' : ($rate >= 95 ? 'bg-wa-deep' : 'bg-accent-amber');
        $rateColor = $hook->state_label === 'failing' ? 'text-accent-amber' : 'text-ink-900';
        $totalTries = $hook->success_count + $hook->failure_count;
        // "—" not "/" for the no-data state — a slash looked like an
        // accidental separator on the listing.
        $lastFiredAgo = $hook->last_fired_at ? $hook->last_fired_at->diffForHumans() : 'never';
        $lastFiredTime = $hook->last_fired_at ? $hook->last_fired_at->format('H:i') : '—';
    @endphp
    <tr class="hover:bg-paper-50/60" data-hook-row="{{ $hook->id }}">
        <td class="px-4 py-3"><input type="checkbox" class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep" />
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2.5">
                <span
                    class="w-8 h-8 rounded-lg {{ $palette['bg'] }} {{ $palette['fg'] }} grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M3 8h3l1.5-4 2 8 1.5-4h2" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="font-mono text-[11.5px] text-ink-900 truncate">{{ $hook->webhook_url }}</div>
                    <div class="text-[10.5px] text-ink-500 font-mono truncate">{{ $hook->environment ?: 'Production' }}
                        / {{ $hook->http_method ?: 'POST' }}{{ $hook->name ? ' / ' . $hook->name : '' }}</div>
                </div>
            </div>
        </td>
        <td class="px-2 py-3">
            <div class="flex flex-wrap gap-1 max-w-[260px]">
                @foreach ($shownTags as $ev)
                    <span
                        class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-wa-deep/10 text-wa-deep">{{ $ev }}</span>
                @endforeach
                @if ($extra > 0)
                    <span
                        class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-700">+{{ $extra }}
                        {{ __('more') }}</span>
                @endif
                @if (empty($shownTags))
                    <span
                        class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-500">{{ __('no events') }}</span>
                @endif
            </div>
        </td>
        <td class="px-2 py-3">
            <div class="font-mono text-[11.5px] text-ink-900">{{ $lastFiredTime }}</div>
            <div class="text-[10px] text-ink-500 font-mono">{{ $lastFiredAgo }}</div>
        </td>
        <td class="px-2 py-3">
            @if ($totalTries === 0)
                <span class="text-[11px] text-ink-500 font-mono">— no fires yet</span>
            @else
                <div class="flex items-center gap-2">
                    <span class="font-mono {{ $rateColor }} text-[12px]">{{ number_format($rate, 1) }}%</span>
                    <div class="w-16 h-1.5 bg-paper-100 rounded-full overflow-hidden">
                        <div class="h-full {{ $rateBar }}" style="width:{{ max(2, (int) round($rate)) }}%"></div>
                    </div>
                </div>
                <div class="text-[10px] text-ink-500 font-mono mt-0.5">{{ number_format($hook->success_count) }} /
                    {{ number_format($totalTries) }}{{ $hook->retry_count ? ' / ' . number_format($hook->retry_count) . ' retries' : '' }}
                </div>
            @endif
        </td>
        <td class="px-2 py-3">
            @if ($hook->last_latency_ms)
                <span
                    class="font-mono text-[11.5px] {{ $hook->last_latency_ms > 800 ? 'text-accent-amber' : 'text-ink-900' }}">{{ $hook->last_latency_ms < 1000 ? $hook->last_latency_ms . 'ms' : number_format($hook->last_latency_ms / 1000, 1) . 's' }}</span>
                <div
                    class="text-[10px] font-mono mt-0.5
 {{ $hook->last_status_code >= 200 && $hook->last_status_code < 300
     ? 'text-wa-deep'
     : ($hook->last_status_code >= 400
         ? 'text-accent-coral'
         : 'text-ink-500') }}">
                    {{ $hook->last_status_code ? 'HTTP ' . $hook->last_status_code : 'last' }}
                </div>
            @else
                <span class="text-[11px] text-ink-500 font-mono">—</span>
            @endif
        </td>
        <td class="px-2 py-3">
            @php $tooltip = $hook->last_error ? mb_substr((string) $hook->last_error, 0, 240) : ''; @endphp
            @if ($hook->state_label === 'active')
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
            @elseif ($hook->state_label === 'failing')
                <span title="{{ $tooltip ?: 'Webhook has failed 3+ times in a row.' }}"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent-coral/15 text-accent-coral text-[10.5px] font-mono cursor-help"><span
                        class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>Failing</span>
                @if ($tooltip)
                    <div class="text-[10px] text-ink-500 mt-1 truncate max-w-[180px]" title="{{ $tooltip }}">
                        {{ $tooltip }}</div>
                @endif
            @else
                <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-500 text-[10.5px] font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>Paused</span>
            @endif
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <a href="{{ url('/webhooks/' . $hook->id) }}"
                class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                title="{{ __('Analytics') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                    <circle cx="8" cy="8" r="2" />
                </svg></a>
            <button type="button" data-hook-test="{{ $hook->id }}"
                class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                title="{{ __('Test fire') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M3 11h10M8 4v9M5 7l3-3 3 3" />
                </svg></button>
            <a href="{{ url('/webhooks/' . $hook->id . '/edit') }}"
                class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                title="{{ __('Edit') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                </svg></a>
            <button type="button" data-hook-toggle="{{ $hook->id }}"
                class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                title="{{ $hook->status ? 'Pause' : 'Resume' }}">
                @if ($hook->status)
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M5 3v10M11 3v10" />
                    </svg>
                @else
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M5 3l7 5-7 5z" />
                    </svg>
                @endif
            </button>
            <button type="button" data-hook-delete="{{ $hook->id }}"
                data-name="{{ $hook->name ?: $hook->webhook_url }}"
                class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                title="{{ __('Delete') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                </svg></button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No webhook endpoints match the current filters. Try clearing filters or add an endpoint.',
                'resetHref' => url('/webhooks'),
                'actionHref' => url('/webhooks/create'),
                'actionLabel' => 'Add endpoint',
            ])
        </td>
    </tr>
@endforelse
