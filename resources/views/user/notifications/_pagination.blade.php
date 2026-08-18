@if ($notifications->hasPages())
    @php
        $current = $notifications->currentPage();
        $last = $notifications->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
    @endphp
    <nav class="px-4 py-3 border-t border-paper-200 flex flex-wrap items-center justify-between gap-3 text-[12px]"
        aria-label="{{ __('Notifications pagination') }}">
        <div class="text-ink-500">
            Page <span class="font-mono text-ink-900">{{ $current }}</span> of <span
                class="font-mono text-ink-900">{{ $last }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            @if ($notifications->onFirstPage())
                <span
                    class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-400 bg-paper-50 cursor-not-allowed">{{ __('Prev') }}</span>
            @else
                <a href="{{ $notifications->previousPageUrl() }}" data-notif-page="{{ $current - 1 }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 font-medium">Prev</a>
            @endif

            @if ($start > 1)
                <a href="{{ $notifications->url(1) }}" data-notif-page="1"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center font-mono">1</a>
                @if ($start > 2)
                    <span class="px-1 text-ink-400">...</span>
                @endif
            @endif

            @for ($page = $start; $page <= $end; $page++)
                @if ($page === $current)
                    <span
                        class="w-8 h-8 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono">{{ $page }}</span>
                @else
                    <a href="{{ $notifications->url($page) }}" data-notif-page="{{ $page }}"
                        class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center font-mono">{{ $page }}</a>
                @endif
            @endfor

            @if ($end < $last)
                @if ($end < $last - 1)
                    <span class="px-1 text-ink-400">...</span>
                @endif
                <a href="{{ $notifications->url($last) }}" data-notif-page="{{ $last }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center font-mono">{{ $last }}</a>
            @endif

            @if ($notifications->hasMorePages())
                <a href="{{ $notifications->nextPageUrl() }}" data-notif-page="{{ $current + 1 }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 font-medium">Next</a>
            @else
                <span
                    class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-400 bg-paper-50 cursor-not-allowed">{{ __('Next') }}</span>
            @endif
        </div>
    </nav>
@endif
