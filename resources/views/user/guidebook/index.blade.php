{{--
 /guidebook — list view, mirroring the prototype at
 D:\wadesk_2806\whatsnap\tutot (3)\guidebook.html.

 Sub-header breadcrumb + 2-column layout (sidebar | search+cards grid).
 No inline JS — category filtering is a server-side GET via ?category=…
 and search is a GET form on ?q=… so each click is a full page load.
--}}
<x-layouts.user :title="__('Guidebook')" nav-key="more" page="user-guidebook-index">

    {{-- Sub header --}}
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/more') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to More') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('More / Guidebook') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate"><span
                            class="italic text-wa-deep">{{ __('Guidebook') }}</span> &amp; {{ __('help articles') }}
                    </div>
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

            @include('user.guidebook._sidebar', ['activeCat' => $catSlug])

            <div>
                {{-- Search + popular row --}}
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-3 mb-5">
                    <form method="GET" action="{{ route('user.guidebook') }}" class="relative">
                        @if ($catSlug)
                            <input type="hidden" name="category" value="{{ $catSlug }}">
                        @endif
                        <svg viewBox="0 0 16 16" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="7" cy="7" r="5" />
                            <path d="m11 11 3 3" />
                        </svg>
                        <input name="q" type="search" value="{{ $q }}"
                            placeholder="{{ __('Search guidebook articles…') }}"
                            class="w-full pl-10 pr-3 py-3 border border-paper-200 rounded-lg bg-white text-[13.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </form>
                    <div class="mt-3 flex items-center gap-1.5 flex-wrap text-[11px]">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mr-1">{{ __('Popular') }}</span>
                        @foreach (['Why was my template rejected?', 'Best send time per region', 'Verify a webhook signature', 'Stuck queued message'] as $pop)
                            <a href="{{ route('user.guidebook', ['q' => $pop]) }}"
                                class="px-2.5 py-1 rounded-full border border-paper-200 hover:border-wa-deep">{{ $pop }}</a>
                        @endforeach
                    </div>
                </div>

                {{-- Section title --}}
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-serif text-[20px] leading-tight">{{ $catSlug ?: __('All articles') }}</h2>
                    <span class="font-mono text-[10.5px] text-ink-500">{{ $articles->count() }}
                        {{ \Illuminate\Support\Str::plural('article', $articles->count()) }}</span>
                </div>

                @if ($articles->isEmpty())
                    <div class="text-center py-12 border border-dashed border-paper-200 rounded-[14px] bg-paper-0/40">
                        <div class="font-serif text-[18px]">{{ __('No articles match.') }}</div>
                        <div class="text-[12px] text-ink-500 mt-1">
                            {{ __('Try a different keyword or pick a different category.') }}</div>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($articles as $a)
                            @php
                                $words = str_word_count(strip_tags((string) $a->body));
                                $readMin = max(1, (int) ceil($words / 200));
                            @endphp
                            <a href="{{ route('user.guidebook.show', $a->slug) }}"
                                class="block bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card hover:border-wa-deep hover:shadow-soft transition group">
                                <div class="flex items-center gap-2 mb-2">
                                    <span
                                        class="text-[10.5px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep">{{ $a->category }}</span>
                                    <span class="text-[10.5px] font-mono text-ink-500">{{ $readMin }}
                                        {{ __('min read') }}</span>
                                </div>
                                <h3 class="font-serif text-[18px] leading-tight mb-1.5">{{ $a->title }}</h3>
                                @if ($a->excerpt)
                                    <p class="text-[12.5px] text-ink-500 leading-snug">{{ $a->excerpt }}</p>
                                @endif
                                <span
                                    class="mt-3 inline-flex items-center gap-1 text-[12px] text-wa-deep font-semibold group-hover:underline">{{ __('Read article') }}
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M6 3l5 5-5 5" />
                                    </svg>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>

</x-layouts.user>
