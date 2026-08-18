<x-layouts.frontend
    :title="$post->seoTitle().' — '.brand_name()"
    :description="$post->seoDescription()"
    :og-image="$post->og_image_url"
    :og-type="'article'"
    :canonical="$post->canonical_url ?: $post->url"
    :no-index="$post->noindex"
    :json-ld="[
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->seoDescription(),
        'image' => $post->og_image_url,
        'datePublished' => optional($post->published_at)->toAtomString(),
        'dateModified' => optional($post->updated_at)->toAtomString(),
        'author' => ['@type' => 'Organization', 'name' => $post->author_name ?: brand_name()],
        'publisher' => ['@type' => 'Organization', 'name' => brand_name()],
        'mainEntityOfPage' => $post->url,
    ]"
    navKey="blog"
    page="frontend-blog-show">

    {{-- Prose typography for the article body. Tailwind CDN has no prose
         plugin, so these styles are scoped to .wd-article only. --}}
    <style>
        .wd-article { color: #1F4540; font-size: 17px; line-height: 1.78; }
        .wd-article > * + * { margin-top: 1.35em; }
        .wd-article h2 { font-family: "Instrument Serif", serif; font-size: 32px; line-height: 1.12; letter-spacing: -0.02em; color: #0B1F1C; margin-top: 1.8em; }
        .wd-article h3 { font-family: "Instrument Serif", serif; font-size: 25px; line-height: 1.2; color: #0B1F1C; margin-top: 1.6em; }
        .wd-article h4 { font-weight: 700; font-size: 18px; color: #0B1F1C; margin-top: 1.4em; }
        .wd-article p { color: #1F4540; }
        .wd-article a { color: #075E54; text-decoration: underline; text-underline-offset: 2px; }
        .wd-article a:hover { color: #128C7E; }
        .wd-article strong { color: #0B1F1C; font-weight: 700; }
        .wd-article ul, .wd-article ol { padding-left: 1.4em; }
        .wd-article ul { list-style: disc; }
        .wd-article ol { list-style: decimal; }
        .wd-article li { margin-top: 0.5em; }
        .wd-article img { border-radius: 16px; max-width: 100%; height: auto; }
        .wd-article blockquote { border-left: 3px solid #075E54; padding-left: 1.1em; color: #3A5A55; font-style: italic; }
        .wd-article pre { background: #0B1F1C; color: #F5F3EC; padding: 1.1em 1.3em; border-radius: 14px; overflow-x: auto; font-family: "JetBrains Mono", monospace; font-size: 13.5px; }
        .wd-article code { font-family: "JetBrains Mono", monospace; font-size: 0.9em; }
        .wd-article :not(pre) > code { background: #EFEBE0; padding: 0.12em 0.4em; border-radius: 6px; }
        .wd-article hr { border: 0; border-top: 1px solid #E5DFD0; margin: 2em 0; }
    </style>

    {{-- Article header --}}
    <article class="bg-paper-0">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 grid-bg opacity-25 pointer-events-none"></div>
            <div class="absolute -top-28 -right-24 w-[440px] h-[440px] rounded-full bg-wa-mint/40 blur-bub"></div>

            <header class="relative max-w-[760px] mx-auto px-4 sm:px-6 pt-20 pb-8">
                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-2 text-[11px] mono uppercase tracking-widest text-ink-500 mb-7">
                    <a href="{{ url('/') }}" class="hover:text-wa-deep">{{ __('Home') }}</a>
                    <span class="text-ink-400">/</span>
                    <a href="{{ route('frontend.blog') }}" class="hover:text-wa-deep">{{ __('Blog') }}</a>
                    <span class="text-ink-400">/</span>
                    <span class="text-ink-700 normal-case tracking-normal truncate max-w-[220px]">{{ $post->title }}</span>
                </nav>

                @if ($post->category)
                    <a href="{{ $post->category->url }}"
                        class="inline-flex items-center gap-2 hairline rounded-full px-3 py-1.5 bg-white text-[11px] mono uppercase tracking-widest text-wa-deep hover:border-wa-deep mb-5">
                        {{ $post->category->name }}
                    </a>
                @endif

                <h1 class="serif text-[40px] sm:text-[56px] leading-[0.98] tracking-[-0.025em] text-ink-900">{{ $post->title }}</h1>

                @if ($post->excerpt)
                    <p class="text-[17px] text-ink-700 leading-relaxed mt-5">{{ $post->excerpt }}</p>
                @endif

                <div class="mt-7 flex flex-wrap items-center gap-3 text-[12px] mono text-ink-500">
                    <span class="text-ink-700">{{ $post->author_name ?: brand_name() }}</span>
                    <span class="text-ink-400">·</span>
                    <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                    <span class="text-ink-400">·</span>
                    <span>{{ $post->readingTimeMinutes() }} {{ __('min read') }}</span>
                </div>
            </header>
        </div>

        @if ($post->image_url)
            <div class="max-w-[920px] mx-auto px-4 sm:px-6">
                <img src="{{ $post->image_url }}" alt="{{ $post->title }}"
                    class="w-full rounded-3xl border border-paper-200 object-cover">
            </div>
        @endif

        {{-- Body --}}
        <div class="max-w-[760px] mx-auto px-4 sm:px-6 py-12">
            <div class="wd-article max-w-[720px]">
                {!! $post->body !!}
            </div>

            {{-- Tags --}}
            @if (!empty($post->tags) && is_array($post->tags))
                <div class="mt-12 pt-7 hairline-t flex flex-wrap gap-2">
                    @foreach ($post->tags as $tag)
                        <span class="hairline rounded-full bg-white px-3 py-1.5 text-[11.5px] mono text-ink-700">#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </article>

    {{-- Related posts --}}
    @if ($related->count())
        <section class="bg-white">
            <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-16 hairline-t">
                <div class="flex items-end justify-between mb-8">
                    <h2 class="serif text-[32px] sm:text-[44px] leading-tight tracking-[-0.02em]">
                        {{ __('Keep') }} <span class="italic text-wa-deep">{{ __('reading') }}</span></h2>
                    <a href="{{ route('frontend.blog') }}" class="text-[13px] font-semibold text-wa-deep hover:underline">{{ __('All posts →') }}</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($related as $r)
                        <a href="{{ $r->url }}"
                            class="group flex flex-col hairline rounded-3xl bg-paper-50 overflow-hidden hover:border-wa-deep transition">
                            <div class="aspect-[16/10] overflow-hidden">
                                @if ($r->image_url)
                                    <img src="{{ $r->image_url }}" alt="{{ $r->title }}"
                                        class="w-full h-full object-cover group-hover:scale-[1.04] transition duration-500">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-wa-deep via-wa-teal to-wa-green"></div>
                                @endif
                            </div>
                            <div class="flex flex-col flex-1 p-6">
                                @if ($r->category)
                                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">{{ $r->category->name }}</div>
                                @endif
                                <h3 class="serif text-[22px] leading-tight group-hover:text-wa-deep transition">{{ $r->title }}</h3>
                                <div class="mt-auto pt-5 flex items-center gap-2.5 text-[11px] mono text-ink-500">
                                    <span>{{ optional($r->published_at)->format('M j, Y') }}</span>
                                    <span class="text-ink-400">·</span>
                                    <span>{{ $r->readingTimeMinutes() }} {{ __('min read') }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA back to blog --}}
    <section class="bg-paper-0">
        <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-14 text-center">
            <a href="{{ route('frontend.blog') }}"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-wa-deep text-paper-0 text-[13.5px] font-semibold hover:bg-wa-teal">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4l-4 4 4 4" /></svg>
                {{ __('Back to all posts') }}
            </a>
        </div>
    </section>

</x-layouts.frontend>
