{{--
 /guidebook/{slug} — single-article view, mirroring the prototype's
 "article view" pane. Same sidebar + sub-header as the index so users
 can jump categories without going back first.
--}}
<x-layouts.user :title="$article->title" nav-key="more" page="user-guidebook-show">

    @php
        $words = str_word_count(strip_tags((string) $article->body));
        $readMin = max(1, (int) ceil($words / 200));
    @endphp

    {{-- Sub header --}}
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('user.guidebook') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Guidebook') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">More / Guidebook /
                        {{ $article->category }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate"><span
                            class="italic text-wa-deep">{{ $article->title }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/support') }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="5.5" />
                        <path d="M5.5 6.5a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01" />
                    </svg>
                    {{ __("Can't find it? Ask support") }}
                </a>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-5 items-start">

            @include('user.guidebook._sidebar', ['activeCat' => $article->category])

            <article class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-4 sm:px-7 py-5 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                    <a href="{{ route('user.guidebook') }}"
                        class="inline-flex items-center gap-1.5 text-[13px] text-wa-deep font-semibold hover:underline">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        {{ __('Back to articles') }}
                    </a>
                    <div class="flex items-center gap-2 text-[11px]">
                        <span class="font-mono text-ink-500">{{ $readMin }} {{ __('min read') }}</span>
                        <span class="text-ink-500">·</span>
                        <span class="text-ink-500">Updated {{ $article->updated_at?->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="px-4 sm:px-7 py-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span
                            class="text-[10.5px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">{{ $article->category }}</span>
                    </div>
                    <h1 class="font-serif text-[32px] leading-tight tracking-[-0.02em]">{{ $article->title }}</h1>

                    @if (session('success'))
                        <div
                            class="mt-4 rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                            {{ session('success') }}</div>
                    @endif

                    {{-- Markdown body. We strip raw HTML in the converter to keep
 the rendered output safe. Falls back to plain pre-wrap. --}}
                    <div class="mt-5 max-w-[760px] text-[14px] text-ink-700 leading-[1.7] space-y-4 prose prose-sm">
                        @php
                            $bodyHtml = '';
                            try {
                                if (class_exists(\League\CommonMark\CommonMarkConverter::class)) {
                                    $converter = new \League\CommonMark\CommonMarkConverter([
                                        'html_input' => 'strip',
                                        'allow_unsafe_links' => false,
                                    ]);
                                    $bodyHtml = (string) $converter->convert((string) ($article->body ?? ''));
                                }
                            } catch (\Throwable $e) {
                            }
                        @endphp
                        @if ($bodyHtml)
                            {!! $bodyHtml !!}
                        @else
                            <pre class="whitespace-pre-wrap font-sans text-[14px] leading-relaxed text-ink-700">{{ $article->body }}</pre>
                        @endif
                    </div>

                    <div class="mt-7 pt-5 border-t border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                        <div class="text-[12.5px] flex items-center gap-2">
                            <span class="text-ink-500">{{ __('Was this helpful?') }}</span>
                            <form method="POST" action="{{ route('user.guidebook.vote', $article->slug) }}"
                                class="inline">@csrf
                                <input type="hidden" name="vote" value="helpful">
                                <button
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11.5px]">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path
                                            d="M3 7.5h2.5V14H3zM5.5 13.5l1.5.5h4.5a1.5 1.5 0 0 0 1.5-1.5l.5-3.5a1.5 1.5 0 0 0-1.5-1.7H9V4a1.5 1.5 0 0 0-3 0c0 1.5-1.5 2.5-1.5 3.5" />
                                    </svg>
                                    {{ __('Yes') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('user.guidebook.vote', $article->slug) }}"
                                class="inline">@csrf
                                <input type="hidden" name="vote" value="not_helpful">
                                <button
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep text-[11.5px]">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path
                                            d="M13 8.5h-2.5V2H13zM10.5 2.5l-1.5-.5H4.5A1.5 1.5 0 0 0 3 3.5l-.5 3.5A1.5 1.5 0 0 0 4 8.7H7V12a1.5 1.5 0 0 0 3 0c0-1.5 1.5-2.5 1.5-3.5" />
                                    </svg>
                                    {{ __('No') }}
                                </button>
                            </form>
                        </div>
                        <a href="{{ url('/support') }}"
                            class="text-[12px] text-wa-deep font-semibold hover:underline">{{ __('Still stuck? Open a ticket →') }}</a>
                    </div>
                </div>

                @if ($related->count())
                    <div class="px-4 sm:px-7 py-5 border-t border-paper-200 bg-paper-50/40 rounded-b-[14px]">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                            {{ __('Related') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($related->take(4) as $r)
                                @php
                                    $rWords = str_word_count(strip_tags((string) $r->body));
                                    $rRead = max(1, (int) ceil($rWords / 200));
                                @endphp
                                <a href="{{ route('user.guidebook.show', $r->slug) }}"
                                    class="block bg-paper-0 border border-paper-200 rounded-[10px] p-4 hover:border-wa-deep transition">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span
                                            class="text-[10.5px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">{{ $r->category }}</span>
                                        <span class="text-[10.5px] font-mono text-ink-500">{{ $rRead }}
                                            {{ __('min read') }}</span>
                                    </div>
                                    <div class="font-serif text-[15px] leading-tight">{{ $r->title }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>

        </div>
    </main>

</x-layouts.user>
