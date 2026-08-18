@forelse ($recent as $row)
    @php
        $code = $row->status_code;
        $isOk = $code !== null && $code >= 200 && $code < 300;
        $isRetry = !$isOk && $code !== null && $code >= 500;
        $codeBadge = $isOk
            ? 'bg-wa-mint text-wa-deep'
            : ($isRetry
                ? 'bg-accent-coral/15 text-[#A1431F]'
                : 'bg-accent-coral/15 text-[#A1431F]');
        $codeText = $isOk ? "{$code} OK" : ($code ? "{$code} retry" : 'failed');
        $latency =
            $row->latency_ms === null
                ? '/'
                : ($row->latency_ms < 1000
                    ? $row->latency_ms . 'ms'
                    : number_format($row->latency_ms / 1000, 1) . 's');
        $host = $row->webhook ? parse_url($row->webhook->webhook_url, PHP_URL_HOST) : '/';
    @endphp
    <tr>
        <td class="px-4 py-2.5 font-mono text-[11px] text-ink-700">{{ optional($row->fired_at)->format('H:i:s') ?: '/' }}
        </td>
        <td class="px-2 py-2.5 font-mono text-[11px] text-wa-deep">{{ $row->event_name }}</td>
        <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700 truncate max-w-[260px]">{{ $host }}</td>
        <td class="px-2 py-2.5">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $codeBadge }} text-[10.5px] font-mono">{{ $codeText }}</span>
            @if (($row->attempts ?? 1) > 1)
                <span
                    class="inline-flex items-center gap-1 ml-1 px-2 py-0.5 rounded-full bg-accent-amber/15 text-[#8A5A00] text-[10.5px] font-mono"
                    title="{{ __('Delivered after auto-retry') }}">
                    <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2.5h-2.5" />
                    </svg>
                    {{ $row->attempts }}&times;
                </span>
            @endif
        </td>
        <td class="px-2 py-2.5 font-mono text-[11px] {{ $isOk ? 'text-ink-700' : 'text-accent-coral' }}">
            {{ $latency }}</td>
        <td class="px-4 py-2.5 text-right">
            @if ($isOk)
                <button type="button"
                    class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Payload') }}</button>
            @else
                <button type="button" data-hook-retry="{{ $row->webhook_id }}"
                    class="text-[11px] text-wa-deep font-semibold hover:underline">Retry</button>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No recent webhook deliveries found. Deliveries appear here after an endpoint receives events.',
            ])
        </td>
    </tr>
@endforelse
