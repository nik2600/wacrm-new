@php
    $iconMap = [
        'campaign' => ['bg' => 'bg-wa-mint', 'fg' => 'text-wa-deep', 'svg' => '<path d="M2 4l12-2v12L2 12V4Z"/>'],
        'broadcast' => ['bg' => 'bg-wa-mint', 'fg' => 'text-wa-deep', 'svg' => '<path d="M3 8h3l1.5-4 2 8 1.5-4h2"/>'],
        'webhook' => ['bg' => 'bg-[#E8F5E9]', 'fg' => 'text-wa-deep', 'svg' => '<path d="M3 8h3l1.5-4 2 8 1.5-4h2"/>'],
        'chat' => [
            'bg' => 'bg-[#D9E5F2]',
            'fg' => 'text-[#13478A]',
            'svg' =>
                '<path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z"/>',
        ],
        'mention' => [
            'bg' => 'bg-[#D9E5F2]',
            'fg' => 'text-[#13478A]',
            'svg' => '<circle cx="8" cy="8" r="3"/><path d="M11 8a3 3 0 0 0 3 3v-3a6 6 0 1 0-2 4.5"/>',
        ],
        'system' => [
            'bg' => 'bg-accent-amber/20',
            'fg' => 'text-[#7B5A14]',
            'svg' => '<path d="M8 1.5L1.5 13.5h13zM8 6v3M8 11.5h.01"/>',
        ],
        'billing' => [
            'bg' => 'bg-accent-amber/20',
            'fg' => 'text-[#7B5A14]',
            'svg' => '<rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 7h12"/>',
        ],
        'device' => [
            'bg' => 'bg-[#F3E9FF]',
            'fg' => 'text-[#5B3D8A]',
            'svg' => '<rect x="4" y="2" width="8" height="12" rx="1.5"/><path d="M7 11h2"/>',
        ],
        'template' => [
            'bg' => 'bg-wa-mint',
            'fg' => 'text-wa-deep',
            'svg' => '<rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M2 6h12M5 9h6M5 11h4"/>',
        ],
        'contact' => [
            'bg' => 'bg-[#E8F5E9]',
            'fg' => 'text-wa-deep',
            'svg' => '<circle cx="8" cy="6" r="2.5"/><path d="M3 13a5 5 0 0 1 10 0"/>',
        ],
    ];
    $catBadgeMap = [
        'campaign' => ['bg-wa-mint', 'text-wa-deep'],
        'broadcast' => ['bg-wa-mint', 'text-wa-deep'],
        'webhook' => ['bg-[#E8F5E9]', 'text-wa-deep'],
        'chat' => ['bg-paper-50', 'text-ink-700'],
        'mention' => ['bg-[#D9E5F2]', 'text-[#13478A]'],
        'system' => ['bg-accent-amber/20', 'text-[#7B5A14]'],
        'billing' => ['bg-accent-amber/20', 'text-[#7B5A14]'],
        'device' => ['bg-[#F3E9FF]', 'text-[#5B3D8A]'],
        'template' => ['bg-wa-mint', 'text-wa-deep'],
        'contact' => ['bg-[#E8F5E9]', 'text-wa-deep'],
    ];
@endphp

@forelse ($grouped as $label => $items)
    <div
        class="px-5 pt-{{ $loop->first ? '4' : '5' }} pb-2 flex items-center justify-between {{ $loop->first ? '' : 'border-t border-paper-200' }}">
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ $label }}</div>
        <button type="button" class="text-[10.5px] font-mono text-wa-deep hover:underline">{{ __('Collapse') }}</button>
    </div>
    <ul class="divide-y divide-paper-200">
        @foreach ($items as $n)
            @php
                $icon = $iconMap[$n->category] ?? $iconMap['system'];
                $cat = $catBadgeMap[$n->category] ?? $catBadgeMap['system'];
                $unread = (bool) $n->status;
                $title = $n->notification_title ?? '';
                $msg = $n->notification_msg ?? '';
            @endphp
            <li class="notif px-5 py-3 flex items-start gap-3 hover:bg-paper-50/60 cursor-pointer relative"
                data-cat="{{ $n->category }}" data-unread="{{ $unread ? '1' : '0' }}" data-notif-id="{{ $n->id }}">
                @if ($unread)
                    <span class="unread-dot absolute left-0 top-0 bottom-0 w-[3px] bg-wa-deep"></span>
                @endif
                <span
                    class="w-9 h-9 rounded-xl {{ $icon['bg'] }} {{ $icon['fg'] }} grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.6">{!! $icon['svg'] !!}</svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span
                            class="text-[13px] {{ $unread ? 'font-semibold' : 'font-medium text-ink-700' }}">{{ $title }}</span>
                        <span
                            class="text-[10px] font-mono px-1.5 py-0.5 rounded {{ $cat[0] }} {{ $cat[1] }}">{{ strtoupper($n->category) }}</span>
                        @if ($n->is_urgent)
                            <span
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-700">{{ __('URGENT') }}</span>
                        @endif
                        @if ($n->verb)
                            <span
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-paper-50 text-ink-500">{{ strtoupper($n->verb) }}</span>
                        @endif
                    </div>
                    <p class="text-[12px] {{ $unread ? 'text-ink-700' : 'text-ink-500' }} mt-0.5 leading-snug">
                        {{ $msg }}</p>
                    <div class="mt-1.5 flex items-center gap-2 text-[10.5px] font-mono text-ink-500">
                        <span>{{ $n->created_at->format('H:i') }}</span><span>/</span><span>{{ $n->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    @if ($n->action_url)
                        <a href="{{ $n->action_url }}"
                            class="text-[11px] text-wa-deep font-semibold hover:underline">View</a>
                    @endif
                    @if ($unread)
                        <button type="button" data-notif-read="{{ $n->id }}"
                            class="ml-1 text-[11px] text-wa-deep font-semibold hover:underline"
                            title="{{ __('Mark read') }}">Read</button>
                    @endif
                    <button type="button" data-notif-dismiss="{{ $n->id }}"
                        class="ml-1 w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500"
                        title="{{ __('Dismiss') }}"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                            stroke="currentColor" stroke-width="1.7">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg></button>
                </div>
            </li>
        @endforeach
    </ul>
@empty
    @include('user.partials.empty-state', [
        'class' => 'm-4',
        'message' =>
            'No notifications match the current filters. Notifications appear here when campaigns, broadcasts, webhooks, or devices need attention.',
        'resetHref' => url('/notifications'),
        'actionHref' => url('/settings?tab=notifications'),
        'actionLabel' => 'Preferences',
    ])
@endforelse
