@forelse ($rows as $r)
    <tr class="msg-row hover:bg-paper-50/60 cursor-pointer" data-msg="{{ $r['id'] }}" data-name="{{ $r['name'] }}"
        data-initials="{{ $r['initials'] }}" data-phone="{{ $r['phone'] }}" data-direction="{{ $r['directionLabel'] }}"
        data-status="{{ $r['statusLabel'] }}" data-type="{{ $r['typeLabel'] }}"
        data-time="{{ $r['date'] }} {{ $r['time'] }}" data-avatar="{{ $r['avatar'] }}"
        data-body="{{ $r['bodyShort'] }}">
        <td class="px-4 py-3"><input type="checkbox"
                class="msg-pick rounded border-paper-200 text-wa-deep focus:ring-wa-deep" value="{{ $r['id'] }}"
                onclick="event.stopPropagation()" /></td>
        <td class="px-2 py-3 font-mono text-[11px] text-ink-700">
            <div>{{ $r['time'] }}</div>
            <div class="text-[10px] text-ink-500">{{ $r['date'] }}</div>
        </td>
        <td class="px-2 py-3">
            @if ($r['direction'] === 'out' && $r['status'] === 'failed')
                <span
                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-accent-coral/15 text-accent-coral"
                    title="{{ __('Failed') }}"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                        stroke="currentColor" stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg></span>
            @elseif ($r['direction'] === 'out')
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-wa-mint text-wa-deep"
                    title="{{ __('Outgoing') }}"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                        stroke="currentColor" stroke-width="1.8">
                        <path d="M3 8h10M9 4l4 4-4 4" />
                    </svg></span>
            @else
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-paper-100 text-ink-700"
                    title="{{ __('Incoming') }}"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                        stroke="currentColor" stroke-width="1.8">
                        <path d="M13 8H3M7 4l-4 4 4 4" />
                    </svg></span>
            @endif
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2.5">
                <span
                    class="w-7 h-7 rounded-full bg-gradient-to-br {{ $r['avatar'] }} text-paper-0 grid place-items-center text-[10px] font-semibold">{{ $r['initials'] }}</span>
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ $r['name'] }}</div>
                    <div class="text-[10.5px] text-ink-500 font-mono">{{ $r['phone'] ?: '—' }}</div>
                </div>
            </div>
        </td>
        <td class="px-2 py-3 text-ink-700 truncate max-w-[280px]">
            @if ($r['mediaIcon'] && $r['mediaLabel'])
                <span
                    class="inline-flex items-center gap-1 mr-1.5 px-1.5 py-0.5 rounded bg-paper-50 text-ink-500 text-[10px] font-mono">{!! $r['mediaIcon'] !!}
                    {{ $r['mediaLabel'] }}</span>
            @endif
            {{ $r['bodyShort'] ?: '—' }}
        </td>
        <td class="px-2 py-3">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $r['typePill'] }} text-[10.5px] font-mono">{{ $r['typeLabel'] }}</span>
        </td>
        <td class="px-2 py-3">
            <span class="inline-flex items-center gap-1 {{ $r['statusBadge']['fg'] }} text-[10.5px] font-mono">
                <span class="w-1.5 h-1.5 rounded-full {{ $r['statusBadge']['dot'] }}"></span>{{ $r['statusLabel'] }}
            </span>
        </td>
        <td class="px-4 py-3 text-right">
            @php
                // Route the "open" icon to the source-specific detail
                // page instead of `/chat` for every row. The id field
                // is source-prefixed (`sch-26`, `brd-58`, `camp-12`,
                // `inbox-1234`, `auto-7`, `leg-…`) — strip the prefix
                // and link to the right module so a scheduled row
                // jumps to /scheduled/26, a broadcast jumps to
                // /broadcasts/58, etc. Inbox/legacy/auto-reply
                // currently live in /chat (no per-message page) so
                // they keep the existing destination.
                $rid = (string) ($r['id'] ?? '');
                $source = (string) ($r['source'] ?? '');
                $numeric = preg_replace('/^[a-z]+-/i', '', $rid);
                $openHref = match ($source) {
                    'scheduled' => url('/scheduled/' . $numeric),
                    'broadcast' => url('/broadcasts/' . $numeric),
                    'campaign' => url('/wa-campaigns/' . ($r['meta']['campaign_id'] ?? $numeric)),
                    default => url('/chat'),
                };
            @endphp
            <a href="{{ $openHref }}"
                class="inline-flex items-center justify-center w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep"
                title="{{ __('Open thread') }}" onclick="event.stopPropagation()">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                    <circle cx="8" cy="8" r="2" />
                </svg>
            </a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No messages match the current filters. Try clearing search or picking a wider date range.',
                'resetHref' => url('/message-history'),
            ])
        </td>
    </tr>
@endforelse
