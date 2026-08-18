<x-layouts.admin :title="$article ? __('Edit · ' . $article->title) : __('New article')" admin-key="guidebook" page="admin-guidebook-edit">

    @php $isNew = ! $article; @endphp

    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ route('admin.guidebook.index') }}" class="hover:text-ink-900">{{ __('Guidebook') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $isNew ? __('New') : __('Edit') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST"
        action="{{ $isNew ? route('admin.guidebook.store') : route('admin.guidebook.update', $article->id) }}">
        @csrf
        @if (!$isNew)
            @method('PATCH')
        @endif

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Guidebook') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">
                        {{ $isNew ? __('New article') : __('Edit article') }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.guidebook.index') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ $isNew ? __('Create article') : __('Save changes') }}</button>
                </div>
            </div>

            @if ($errors->any())
                <div
                    class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-[#A1431F] px-4 py-3 text-[12.5px]">
                    <div class="font-semibold mb-1">{{ __("Couldn't save:") }}</div>
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <h2 class="font-serif text-[20px]">{{ __('Content') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="block">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Title *') }}</span>
                                <input name="title" value="{{ old('title', $article?->title) }}" required
                                    maxlength="200"
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="block">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Excerpt') }}</span>
                                <input name="excerpt" value="{{ old('excerpt', $article?->excerpt) }}" maxlength="500"
                                    placeholder="{{ __('One-line summary shown on /guidebook') }}"
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="block">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Body') }}
                                    <span class="text-ink-500 normal-case">(Markdown)</span></span>
                                <textarea name="body" rows="18"
                                    placeholder="# Heading&#10;&#10;Paragraph. Use **bold**, _italic_, lists, code fences..."
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[13px] font-mono focus:outline-none focus:border-wa-deep">{{ old('body', $article?->body) }}</textarea>
                            </label>
                        </div>
                    </div>
                </div>

                <aside class="w-full min-w-0 bg-paper-0 border border-paper-200 rounded-2xl shadow-card lg:sticky lg:top-[88px]">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h3 class="font-serif text-[18px]">{{ __('Publishing') }}</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Slug') }}</span>
                            <input name="slug" value="{{ old('slug', $article?->slug) }}"
                                placeholder="{{ __('auto from title') }}" pattern="[a-z0-9-]*"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                        </label>
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Category *') }}</span>
                            <input name="category" value="{{ old('category', $article?->category ?? 'general') }}"
                                required list="cat-suggest" maxlength="80"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                            <datalist id="cat-suggest">
                                @foreach ($categories as $c)
                                    <option value="{{ $c }}">
                                @endforeach
                            </datalist>
                        </label>
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Sort order') }}</span>
                            <input type="number" name="sort_order"
                                value="{{ old('sort_order', $article?->sort_order ?? 0) }}" min="0"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2 text-[12.5px]">
                            <span>{{ __('Published') }}</span>
                            <span class="toggle"><input type="hidden" name="is_published" value="0"><input
                                    type="checkbox" name="is_published" value="1"
                                    @checked(old('is_published', $article?->is_published ?? true))><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        @if (!$isNew)
                            <div class="text-[10.5px] font-mono text-ink-500 border-t border-paper-100 pt-3 space-y-1">
                                <div>Views: {{ number_format($article->views_count) }}</div>
                                <div>👍 {{ $article->helpful_count }} · 👎 {{ $article->not_helpful_count }}</div>
                                @if ($article->published_at)
                                    <div>First published {{ $article->published_at->format('M j, Y') }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </aside>
            </section>

        </main>

    </form>

</x-layouts.admin>
