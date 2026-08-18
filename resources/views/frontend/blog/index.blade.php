<x-layouts.frontend
    :title="__('Blog').' — '.brand_name()"
    :description="__('Insights, guides and product updates from ').brand_name()"
    navKey="blog"
    page="frontend-blog">

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
        <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 pt-24 pb-12">
            <div class="inline-flex items-center gap-2 hairline rounded-full px-3 py-1.5 bg-white text-[11px] mono uppercase tracking-widest text-ink-700">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
                <span>{{ __('The') }} {{ brand_name() }} {{ __('blog') }}</span>
            </div>
            <h1 class="serif text-[44px] sm:text-[72px] lg:text-[96px] leading-[0.94] tracking-[-0.025em] mt-6 reveal">
                {{ __('Ideas that') }} <span class="italic text-wa-deep">{{ __('start conversations') }}</span>.
            </h1>
            <p class="text-[15.5px] text-ink-700 leading-relaxed max-w-2xl mt-5 reveal" style="--d:120ms">
                {{ __('Insights, guides and product updates on growing your business on WhatsApp.') }}
            </p>
        </div>
    </section>

    {{-- Featured post --}}
    @if ($featured)
        <section class="bg-white">
            <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 pb-4">
                <a href="{{ $featured->url }}"
                    class="group grid grid-cols-1 lg:grid-cols-2 gap-8 items-center hairline rounded-3xl bg-paper-50 overflow-hidden hover:border-wa-deep transition reveal">
                    <div class="aspect-[16/10] lg:aspect-auto lg:h-full min-h-[280px] overflow-hidden">
                        @if ($featured->image_url)
                            <img src="{{ $featured->image_url }}" alt="{{ $featured->title }}"
                                class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-500">
                        @else
                            <div class="w-full h-full min-h-[280px] bg-gradient-to-br from-wa-deep via-wa-teal to-wa-green"></div>
                        @endif
                    </div>
                    <div class="p-7 lg:p-10">
                        <div class="flex items-center gap-3 text-[11px] mono uppercase tracking-widest text-ink-500 mb-4">
                            <span class="hairline rounded-full bg-white px-2.5 py-1 text-wa-deep">{{ __('Featured') }}</span>
                            @if ($featured->category)
                                <span>{{ $featured->category->name }}</span>
                            @endif
                        </div>
                        <h2 class="serif text-[32px] sm:text-[44px] leading-[1.0] tracking-[-0.02em] group-hover:text-wa-deep transition">
                            {{ $featured->title }}</h2>
                        @if ($featured->excerpt)
                            <p class="text-[15px] text-ink-700 leading-relaxed mt-4 max-w-xl">{{ $featured->excerpt }}</p>
                        @endif
                        <div class="mt-6 flex items-center gap-3 text-[11.5px] mono text-ink-500">
                            <span>{{ optional($featured->published_at)->format('M j, Y') }}</span>
                            <span class="text-ink-400">·</span>
                            <span>{{ $featured->readingTimeMinutes() }} {{ __('min read') }}</span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 mt-6 text-[13.5px] font-semibold text-wa-deep">
                            {{ __('Read more') }}
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4l4 4-4 4" /></svg>
                        </span>
                    </div>
                </a>
            </div>
        </section>
    @endif

    {{-- Category filter + grid --}}
    <section class="bg-white">
        <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-12">

            @if ($categories->count())
                <div class="flex flex-wrap gap-2 mb-10">
                    <a href="{{ route('frontend.blog') }}"
                        class="rounded-full px-4 py-1.5 text-[12px] mono transition {{ $activeCategory === '' ? 'bg-wa-deep text-paper-0' : 'hairline bg-white text-ink-700 hover:border-wa-deep hover:text-wa-deep' }}">
                        {{ __('All') }}
                    </a>
                    @foreach ($categories as $c)
                        <a href="{{ route('frontend.blog', ['category' => $c->slug]) }}"
                            class="rounded-full px-4 py-1.5 text-[12px] mono transition {{ $activeCategory === $c->slug ? 'bg-wa-deep text-paper-0' : 'hairline bg-white text-ink-700 hover:border-wa-deep hover:text-wa-deep' }}">
                            {{ $c->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($posts->count())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($posts as $p)
                        <a href="{{ $p->url }}"
                            class="group flex flex-col hairline rounded-3xl bg-paper-50 overflow-hidden hover:border-wa-deep transition reveal">
                            <div class="aspect-[16/10] overflow-hidden">
                                @if ($p->image_url)
                                    <img src="{{ $p->image_url }}" alt="{{ $p->title }}"
                                        class="w-full h-full object-cover group-hover:scale-[1.04] transition duration-500">
                                @else
                                    <div class="w-full h-full bg-gradient-to-br from-wa-deep via-wa-teal to-wa-green"></div>
                                @endif
                            </div>
                            <div class="flex flex-col flex-1 p-6">
                                @if ($p->category)
                                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">{{ $p->category->name }}</div>
                                @endif
                                <h3 class="serif text-[24px] leading-tight tracking-[-0.01em] group-hover:text-wa-deep transition">{{ $p->title }}</h3>
                                @if ($p->excerpt)
                                    <p class="text-[13px] text-ink-700 leading-relaxed mt-3 line-clamp-3">{{ $p->excerpt }}</p>
                                @endif
                                <div class="mt-auto pt-5 flex items-center gap-2.5 text-[11px] mono text-ink-500">
                                    <span>{{ optional($p->published_at)->format('M j, Y') }}</span>
                                    <span class="text-ink-400">·</span>
                                    <span>{{ $p->readingTimeMinutes() }} {{ __('min read') }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-12">
                    {{ $posts->withQueryString()->links() }}
                </div>
            @else
                <div class="hairline rounded-3xl bg-paper-50 py-20 px-6 text-center">
                    <div class="serif text-[28px] text-ink-900">{{ __('Nothing here yet') }}</div>
                    <p class="text-[14px] text-ink-600 mt-2">{{ __('New articles are on the way — check back soon.') }}</p>
                </div>
            @endif

        </div>
    </section>

</x-layouts.frontend>
