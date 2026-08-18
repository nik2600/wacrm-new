@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator|null $paginator */
    $paginator = $paginator ?? null;
    $dataAttr = $dataAttr ?? 'data-page';
    $label = $label ?? 'items';
    $compact = (bool) ($compact ?? false);
@endphp

@if ($paginator && $paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 1);
        $end = min($last, $current + 1);
        $pages = [];
        if ($last <= 7) {
            $pages = range(1, $last);
        } else {
            $pages[] = 1;
            if ($start > 2) {
                $pages[] = 'gap-a';
            }
            foreach (range($start, $end) as $page) {
                if ($page > 1 && $page < $last) {
                    $pages[] = $page;
                }
            }
            if ($end < $last - 1) {
                $pages[] = 'gap-b';
            }
            $pages[] = $last;
        }
    @endphp
    <nav class="{{ $compact ? 'mt-3' : 'mt-4' }} border border-paper-200 rounded-2xl bg-paper-0 shadow-card px-4 py-3 flex flex-wrap items-center justify-between gap-3 text-[12px]"
        aria-label="{{ ucfirst($label) }} pagination">
        <div class="font-mono text-[11px] text-ink-500">
            Showing <span
                class="text-ink-900">{{ number_format($paginator->firstItem()) }}-{{ number_format($paginator->lastItem()) }}</span>
            of <span class="text-ink-900">{{ number_format($paginator->total()) }}</span> {{ $label }}
        </div>
        @if ($last > 1)
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span
                        class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Prev') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" {!! $dataAttr !!}="{{ $current - 1 }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700">Prev</a>
                @endif

                @foreach ($pages as $page)
                    @if (is_string($page))
                        <span class="px-2 py-1.5 text-ink-400">...</span>
                    @elseif ($page === $current)
                        <span aria-current="page"
                            class="min-w-8 px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-center font-semibold">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" {!! $dataAttr !!}="{{ $page }}"
                            class="min-w-8 px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 text-center">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" {!! $dataAttr !!}="{{ $current + 1 }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700">Next</a>
                @else
                    <span
                        class="px-3 py-1.5 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Next') }}</span>
                @endif
            </div>
        @endif
    </nav>
@endif
