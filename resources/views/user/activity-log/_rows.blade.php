@forelse ($rows as $r)
    <tr class="al-row hover:bg-paper-50/60 cursor-pointer" data-id="{{ $r['id'] }}"
        data-action="{{ $r['actionLabel'] }}" data-category="{{ $r['categoryLabel'] }}" data-actor="{{ $r['actorName'] }}"
        data-when="{{ $r['date'] }} {{ $r['when'] }}" data-subject="{{ $r['subject'] }}"
        data-ip="{{ $r['ip'] }}">
        <td class="px-4 py-3"><input type="checkbox"
                class="al-pick rounded border-paper-200 text-wa-deep focus:ring-wa-deep" value="{{ $r['id'] }}"
                onclick="event.stopPropagation()" /></td>
        <td class="px-2 py-3 font-mono text-[11px] text-ink-700">
            <div>{{ $r['when'] }}</div>
            <div class="text-[10px] text-ink-500">{{ $r['date'] }}</div>
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2.5">
                <span
                    class="w-8 h-8 rounded-lg {{ $r['iconBg'] }} {{ $r['iconFg'] }} grid place-items-center shrink-0">
                    {!! $r['iconHtml'] !!}
                </span>
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ $r['actionLabel'] }}</div>
                    <div class="text-[10.5px] text-ink-500 font-mono truncate">{{ $r['action'] }}</div>
                </div>
            </div>
        </td>
        <td class="px-2 py-3">
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $r['iconBg'] }} {{ $r['iconFg'] }} text-[10.5px] font-mono">{{ $r['categoryLabel'] }}</span>
        </td>
        <td class="px-2 py-3">
            <div class="flex items-center gap-2">
                <span
                    class="w-6 h-6 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[9.5px] font-semibold">{{ $r['actorInitials'] }}</span>
                <span class="text-[12px] text-ink-700 truncate">{{ $r['actorName'] }}</span>
            </div>
        </td>
        <td class="px-2 py-3 font-mono text-[11px] text-ink-700 truncate max-w-[180px]">{{ $r['subject'] }}</td>
        <td class="px-2 py-3 font-mono text-[11px] text-ink-700">{{ $r['ip'] }}</td>
        <td class="px-4 py-3 text-right">
            <button type="button"
                class="al-open inline-flex items-center justify-center w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep"
                title="{{ __('View payload') }}" data-id="{{ $r['id'] }}" onclick="event.stopPropagation()">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                    <circle cx="8" cy="8" r="2" />
                </svg>
            </button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="px-4 py-4">
            @include('user.partials.empty-state', [
                'message' =>
                    'No activity matches the current filters. Try widening the date range or clearing search.',
                'resetHref' => url('/activity-log'),
            ])
        </td>
    </tr>
@endforelse
