<x-layouts.admin :title="__('Admin · Edit') . ' · ' . $page->title" admin-key="legal-pages" page="admin-legal-pages-edit">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 min-w-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900 shrink-0">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.legal-pages.index') }}" class="hover:text-ink-900 shrink-0">{{ __('Legal pages') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal truncate">{{ $page->title }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 shrink-0">
            <a href="{{ url('/legal/' . $page->slug) }}" target="_blank" rel="noopener"
                class="hidden sm:inline-flex px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View') }}</a>
            <button type="submit" form="legalForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l5 5 7-9" /></svg>
                {{ __('Save') }}
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Content · Legal · Edit') }}</div>
        <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[32px] lg:text-[36px] leading-[1.0]">{{ $label }}</h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Everything below is exactly what visitors see on') }}
            <a href="{{ url('/legal/' . $page->slug) }}" target="_blank" rel="noopener" class="text-wa-deep underline">/legal/{{ $page->slug }}</a>.
        </p>
    </div>

    <main class="px-4 sm:px-6 lg:px-7 pb-10">
        @if (session('success'))
            <div class="mb-4 rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form id="legalForm" method="POST" action="{{ route('admin.legal-pages.update', $page->slug) }}">
            @csrf
            @method('PATCH')

            {{-- ── Page header fields ── --}}
            <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 mb-6">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Page header') }}</div>

                <label class="block mb-4">
                    <span class="block text-[12px] font-medium text-ink-700 mb-1.5">{{ __('Title') }}</span>
                    <input name="title" value="{{ old('title', $page->title) }}" required
                        class="w-full hairline border border-paper-200 rounded-xl px-3 py-2 text-[14px] bg-paper-0 focus:outline-none focus:border-wa-deep" />
                </label>

                <label class="block mb-4">
                    <span class="block text-[12px] font-medium text-ink-700 mb-1.5">{{ __('Subtitle') }}
                        <span class="text-ink-400 font-normal">({{ __('one line under the title') }})</span></span>
                    <textarea name="subtitle" rows="2"
                        class="w-full hairline border border-paper-200 rounded-xl px-3 py-2 text-[14px] bg-paper-0 focus:outline-none focus:border-wa-deep resize-y">{{ old('subtitle', $page->subtitle) }}</textarea>
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-[12px] font-medium text-ink-700 mb-1.5">{{ __('Updated date label') }}</span>
                        <input name="updated_label" value="{{ old('updated_label', $page->updated_label) }}" placeholder="March 14, 2026"
                            class="w-full hairline border border-paper-200 rounded-xl px-3 py-2 text-[14px] bg-paper-0 focus:outline-none focus:border-wa-deep" />
                    </label>
                    <label class="block">
                        <span class="block text-[12px] font-medium text-ink-700 mb-1.5">{{ __('Effective date label') }}</span>
                        <input name="effective_label" value="{{ old('effective_label', $page->effective_label) }}" placeholder="April 1, 2026"
                            class="w-full hairline border border-paper-200 rounded-xl px-3 py-2 text-[14px] bg-paper-0 focus:outline-none focus:border-wa-deep" />
                    </label>
                </div>

                <label class="mt-4 flex items-center gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published))
                        class="w-4 h-4 rounded border-paper-300 text-wa-deep focus:ring-wa-deep" />
                    <span class="text-[13px] text-ink-700">{{ __('Published — visible on the site') }}</span>
                </label>
            </div>

            {{-- ── Sections ── --}}
            <div class="flex items-center justify-between mb-3">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sections') }}</div>
                <button type="button" data-add-section
                    class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                    {{ __('Add section') }}
                </button>
            </div>

            <div id="sections-list" class="space-y-4">
                @foreach ($page->orderedSections() as $i => $s)
                    <div class="section-row hairline border border-paper-200 rounded-2xl bg-paper-0 p-4" data-section-row>
                        <div class="flex items-center gap-2 mb-3">
                            <input name="sections[{{ $i }}][n]" value="{{ $s['n'] }}" placeholder="01" data-num
                                class="w-16 hairline border border-paper-200 rounded-lg px-2 py-1.5 text-[13px] font-mono text-center bg-paper-0 focus:outline-none focus:border-wa-deep" />
                            <input name="sections[{{ $i }}][title]" value="{{ $s['title'] }}" placeholder="{{ __('Section heading') }}" data-title
                                class="flex-1 min-w-0 hairline border border-paper-200 rounded-lg px-3 py-1.5 text-[14px] font-medium bg-paper-0 focus:outline-none focus:border-wa-deep" />
                            <button type="button" data-move-up title="{{ __('Move up') }}" class="w-8 h-8 shrink-0 rounded-lg hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center text-ink-600">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 12V4M4.5 7.5L8 4l3.5 3.5" /></svg></button>
                            <button type="button" data-move-down title="{{ __('Move down') }}" class="w-8 h-8 shrink-0 rounded-lg hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center text-ink-600">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 4v8M4.5 8.5L8 12l3.5-3.5" /></svg></button>
                            <button type="button" data-remove title="{{ __('Remove') }}" class="w-8 h-8 shrink-0 rounded-lg hairline border border-paper-200 bg-paper-0 hover:bg-accent-coral/10 hover:text-accent-coral hover:border-accent-coral/40 flex items-center justify-center text-ink-600">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" /></svg></button>
                        </div>
                        <textarea name="sections[{{ $i }}][body]" rows="6" data-body spellcheck="true"
                            class="w-full hairline border border-paper-200 rounded-xl px-3 py-2 text-[13px] font-mono leading-relaxed bg-paper-0 focus:outline-none focus:border-wa-deep resize-y">{{ $s['body'] }}</textarea>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <span class="text-[11px] text-ink-400">{{ __('HTML supported:') }} <code class="text-ink-500">&lt;p&gt; &lt;ul&gt;&lt;li&gt; &lt;h3&gt; &lt;strong&gt; &lt;a href&gt; &lt;code&gt;</code></span>
                            <button type="button" data-preview-toggle class="text-[11.5px] font-semibold text-wa-deep hover:underline">{{ __('Preview') }}</button>
                        </div>
                        <div class="legal-prose mt-3 hidden" data-preview>
                            <div class="prose-body text-[14px] leading-[1.7] text-ink-800 hairline border border-paper-200 rounded-xl bg-paper-50 p-4"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="text-[12px] text-ink-500 mt-4">{{ __('Tip: leave a section completely empty to delete it on save. Numbers left blank are filled in automatically.') }}</p>
        </form>

        {{-- Hidden template the editor clones for new sections --}}
        <template id="section-row-tpl">
            <div class="section-row hairline border border-paper-200 rounded-2xl bg-paper-0 p-4" data-section-row>
                <div class="flex items-center gap-2 mb-3">
                    <input name="sections[__IDX__][n]" value="" placeholder="01" data-num
                        class="w-16 hairline border border-paper-200 rounded-lg px-2 py-1.5 text-[13px] font-mono text-center bg-paper-0 focus:outline-none focus:border-wa-deep" />
                    <input name="sections[__IDX__][title]" value="" placeholder="{{ __('Section heading') }}" data-title
                        class="flex-1 min-w-0 hairline border border-paper-200 rounded-lg px-3 py-1.5 text-[14px] font-medium bg-paper-0 focus:outline-none focus:border-wa-deep" />
                    <button type="button" data-move-up title="{{ __('Move up') }}" class="w-8 h-8 shrink-0 rounded-lg hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center text-ink-600">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 12V4M4.5 7.5L8 4l3.5 3.5" /></svg></button>
                    <button type="button" data-move-down title="{{ __('Move down') }}" class="w-8 h-8 shrink-0 rounded-lg hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center text-ink-600">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 4v8M4.5 8.5L8 12l3.5-3.5" /></svg></button>
                    <button type="button" data-remove title="{{ __('Remove') }}" class="w-8 h-8 shrink-0 rounded-lg hairline border border-paper-200 bg-paper-0 hover:bg-accent-coral/10 hover:text-accent-coral hover:border-accent-coral/40 flex items-center justify-center text-ink-600">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" /></svg></button>
                </div>
                <textarea name="sections[__IDX__][body]" rows="6" data-body spellcheck="true"
                    class="w-full hairline border border-paper-200 rounded-xl px-3 py-2 text-[13px] font-mono leading-relaxed bg-paper-0 focus:outline-none focus:border-wa-deep resize-y"></textarea>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="text-[11px] text-ink-400">{{ __('HTML supported:') }} <code class="text-ink-500">&lt;p&gt; &lt;ul&gt;&lt;li&gt; &lt;h3&gt; &lt;strong&gt; &lt;a href&gt; &lt;code&gt;</code></span>
                    <button type="button" data-preview-toggle class="text-[11.5px] font-semibold text-wa-deep hover:underline">{{ __('Preview') }}</button>
                </div>
                <div class="legal-prose mt-3 hidden" data-preview>
                    <div class="prose-body text-[14px] leading-[1.7] text-ink-800 hairline border border-paper-200 rounded-xl bg-paper-50 p-4"></div>
                </div>
            </div>
        </template>
    </main>
</x-layouts.admin>
