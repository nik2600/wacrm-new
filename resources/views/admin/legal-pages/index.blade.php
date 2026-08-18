<x-layouts.admin :title="__('Admin · Legal pages')" admin-key="legal-pages" page="admin-legal-pages-index">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Legal pages') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
            {{ __('Admin · Content · Legal') }}</div>
        <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[32px] lg:text-[36px] leading-[1.0]">
            {{ __('Legal pages') }}</h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
            {{ __('Edit every word of the documents linked in your site footer — title, intro, dates and each numbered section.') }}
        </p>
    </div>

    <main class="px-4 sm:px-6 lg:px-7 pb-10">
        @if (session('success'))
            <div class="mb-4 rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($pages as $page)
                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 flex flex-col">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-serif text-[20px] leading-tight truncate">{{ $page->title }}</div>
                            <div class="font-mono text-[10.5px] text-ink-500 mt-1">/legal/{{ $page->slug }}</div>
                        </div>
                        @if ($page->is_published)
                            <span class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Live') }}</span>
                        @else
                            <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-[10.5px] font-medium bg-paper-50 text-ink-700 border border-paper-200 font-mono">{{ __('Hidden') }}</span>
                        @endif
                    </div>

                    <div class="mt-3 text-[12px] text-ink-600">
                        {{ count($page->sections ?? []) }} {{ __('sections') }}
                        @if ($page->updated_label)
                            · {{ __('Updated') }} {{ $page->updated_label }}
                        @endif
                    </div>

                    <div class="mt-5 pt-4 hairline-t border-t border-paper-200 flex items-center gap-2">
                        <a href="{{ route('admin.legal-pages.edit', $page->slug) }}"
                            class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M11 2.5l2.5 2.5L5 13.5H2.5V11z" />
                                <path d="M9.5 4l2.5 2.5" />
                            </svg>
                            {{ __('Edit') }}
                        </a>
                        <a href="{{ url('/legal/' . $page->slug) }}" target="_blank" rel="noopener"
                            class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View') }}</a>
                        <form method="POST" action="{{ route('admin.legal-pages.toggle', $page->slug) }}" class="ml-auto">
                            @csrf
                            <button type="submit"
                                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                                {{ $page->is_published ? __('Hide') : __('Publish') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
</x-layouts.admin>
