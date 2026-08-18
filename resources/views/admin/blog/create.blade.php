@php
    // create() passes no $post; edit() passes one. Define it so every
    // $post?->… reference below resolves on the create route too.
    $post = $post ?? null;
    $isEdit = (bool) $post;
    $action = $isEdit ? route('admin.blog.update', $post->id) : route('admin.blog.store');
@endphp

<x-layouts.admin :title="$isEdit ? __('Edit post') : __('New post')" admin-key="blog" page="admin-blog-create">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.blog.index') }}" class="hover:text-ink-900">{{ __('Blog') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $isEdit ? __('Edit') : __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('admin.blog.index') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="blogForm"
                class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9" /></svg>
                {{ $isEdit ? __('Save changes') : __('Create post') }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Blog · ') }}{{ $isEdit ? __('Edit') : __('New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">
                {{ $isEdit ? __('Edit') : __('Write a') }} <span class="italic text-wa-deep">{{ __('post') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('Title, content and a category are the essentials. Fill the SEO card to control how the article looks in search results and link previews.') }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">

        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <x-admin.flash />

        <form id="blogForm" method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">

                {{-- Left: main fields --}}
                <div class="space-y-5 min-w-0">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('content') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Article') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Title') }} <span class="text-accent-coral">*</span></span>
                                <input name="title" value="{{ old('title', $post?->title) }}" required maxlength="200"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[14px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Slug') }}</span>
                                <input name="slug" value="{{ old('slug', $post?->slug) }}" maxlength="200"
                                    placeholder="{{ __('auto from title') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Leave blank to generate from the title. Used in the public URL /blog/{slug}.') }}</span>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Excerpt') }}</span>
                                <textarea name="excerpt" rows="2" maxlength="500"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="{{ __('Short summary shown on cards and listings.') }}">{{ old('excerpt', $post?->excerpt) }}</textarea>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Content (HTML)') }} <span class="text-accent-coral">*</span></span>
                                <textarea name="body" rows="18" required
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono leading-relaxed focus:outline-none focus:border-wa-deep"
                                    placeholder="<p>Write the article body here. Plain HTML is supported.</p>">{{ old('body', $post?->body) }}</textarea>
                                <span class="text-[11px] text-ink-500">{{ __('Full article HTML — headings, paragraphs, lists, images and links.') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('media & meta') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Featured image & details') }}</h2>
                        </div>
                        <div class="p-5 space-y-4">
                            <div class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold block">{{ __('Featured image') }}</span>
                                @if ($post?->image_url)
                                    <img src="{{ $post->image_url }}" alt="" class="h-28 w-auto rounded-xl border border-paper-200 object-cover mb-2">
                                @endif
                                <input type="file" name="featured_image" accept="image/*"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[12.5px] file:mr-3 file:rounded-full file:border-0 file:bg-paper-100 file:px-3 file:py-1.5 file:text-[12px] file:font-semibold focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Shown at the top of the article and on listing cards.') }}@if ($post?->image_url) {{ __('Leave blank to keep the current image.') }}@endif</span>
                            </div>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Tags') }}</span>
                                <input name="tags" value="{{ old('tags', is_array($post?->tags) ? implode(', ', $post->tags) : '') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="comma, separated, tags">
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Author name') }}</span>
                                <input name="author_name" value="{{ old('author_name', $post?->author_name) }}" maxlength="120"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="{{ brand_name() }}">
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Right: aside cards --}}
                <aside class="space-y-4 lg:sticky lg:top-[88px]">

                    {{-- Publish --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('publish') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Visibility') }}</h3>
                        </div>
                        <div class="p-4 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Status') }}</span>
                                @php $statusVal = old('status', $post?->status ?? 'draft'); @endphp
                                <select name="status" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="draft" @selected($statusVal === 'draft')>{{ __('Draft') }}</option>
                                    <option value="published" @selected($statusVal === 'published')>{{ __('Published') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Publish date') }}</span>
                                <input type="datetime-local" name="published_at"
                                    value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Set a future time to schedule.') }}</span>
                            </label>
                            <label class="flex items-center gap-3 px-3 py-2 border border-paper-200 rounded-xl cursor-pointer hover:bg-paper-50">
                                <input type="hidden" name="is_featured" value="0">
                                <span class="relative inline-block w-[34px] h-5 shrink-0">
                                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post?->is_featured))>
                                    <span class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                                </span>
                                <span>
                                    <span class="block text-[12.5px] font-semibold">{{ __('Feature this post') }}</span>
                                    <span class="block text-[10.5px] text-ink-500">{{ __('Pinned to the top of the public blog.') }}</span>
                                </span>
                            </label>
                            <button type="submit" form="blogForm"
                                class="w-full px-4 py-2.5 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">
                                {{ $isEdit ? __('Save changes') : __('Create post') }}
                            </button>
                        </div>
                    </div>

                    {{-- Category --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('category') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Filing') }}</h3>
                        </div>
                        <div class="p-4 space-y-4">
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Category') }}</span>
                                @php $catVal = old('category_id', $post?->category_id); @endphp
                                <select name="category_id" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="">{{ __('— None —') }}</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}" @selected((string) $catVal === (string) $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1.5 block">
                                <span class="text-[11.5px] font-semibold">{{ __('Or create new') }}</span>
                                <input name="new_category" value="{{ old('new_category') }}" maxlength="120"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="{{ __('New category name') }}">
                                <span class="text-[11px] text-ink-500">{{ __('If filled, a new category is created and assigned.') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- Per-post SEO removed: SEO is managed centrally in
                         Admin → Settings → SEO. A post's title, excerpt and
                         featured image drive its search + social previews
                         automatically. --}}

                </aside>
            </section>
        </form>
    </main>

</x-layouts.admin>
