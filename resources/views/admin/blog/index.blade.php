<x-layouts.admin :title="__('Blog')" admin-key="blog" page="admin-blog-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Blog') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4 hidden md:block">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" />
            </svg>
            <form method="GET" action="{{ route('admin.blog.index') }}">
                <input name="q" value="{{ request('q') }}" class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition" placeholder="{{ __('Search posts...') }}" />
            </form>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Marketing · Blog') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Blog') }} <span class="italic text-wa-deep">{{ __('posts') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Write, publish, and SEO-optimise articles for the public marketing site. Drafts stay hidden until you publish them.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.blog.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    {{ __('New post') }}
                </a>
            </div>
        </div>

        {{-- KPI strip --}}
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total posts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total'] ?? 0) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in catalog') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Published') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['published'] ?? 0) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('live on site') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Drafts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['draft'] ?? 0) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('not yet published') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total views') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['views'] ?? 0) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('all-time reads') }}</div>
            </div>
        </section>

        {{-- Toolbar: search + status filter --}}
        <form method="GET" action="{{ route('admin.blog.index') }}" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" />
                </svg>
                <input name="q" value="{{ request('q') }}" class="w-full rounded-full bg-paper-0 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep transition" placeholder="{{ __('Search title or slug...') }}" />
            </div>
            <select name="status" onchange="this.form.submit()" class="rounded-full border border-paper-200 bg-paper-0 px-4 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep">
                <option value="" @selected(request('status', '') === '')>{{ __('All statuses') }}</option>
                <option value="published" @selected(request('status') === 'published')>{{ __('Published') }}</option>
                <option value="draft" @selected(request('status') === 'draft')>{{ __('Draft') }}</option>
            </select>
            <button type="submit" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Filter') }}</button>
            @if (request('q') || request('status'))
                <a href="{{ route('admin.blog.index') }}" class="px-4 py-2 text-[12px] text-ink-500 hover:text-ink-900">{{ __('Clear') }}</a>
            @endif
        </form>

        <x-admin.flash />

        {{-- Posts table --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('All posts') }}</div>
                    <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Detailed list') }}</h2>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] min-w-[860px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5 w-[64px]"></th>
                            <th class="text-left px-2 py-2.5">{{ __('Post') }}</th>
                            <th class="text-left px-2 py-2.5 w-[150px]">{{ __('Category') }}</th>
                            <th class="text-center px-2 py-2.5 w-[100px]">{{ __('Status') }}</th>
                            <th class="text-right px-2 py-2.5 w-[80px]">{{ __('Views') }}</th>
                            <th class="text-left px-2 py-2.5 w-[130px]">{{ __('Date') }}</th>
                            <th class="text-right px-2 py-2.5 w-[180px]">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($posts as $p)
                            @php $published = $p->status === 'published'; @endphp
                            <tr class="hover:bg-paper-50/60 align-top">
                                <td class="px-3 py-3">
                                    @if ($p->image_url)
                                        <img src="{{ $p->image_url }}" alt="" class="w-12 h-12 rounded-lg object-cover border border-paper-200">
                                    @else
                                        <span class="w-12 h-12 rounded-lg bg-paper-100 border border-paper-200 grid place-items-center text-ink-400">
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                                                <rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M5 17l4-4 3 3 3-3 4 4" />
                                            </svg>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-2 py-3">
                                    <div class="font-semibold leading-snug text-[13px] text-ink-900">{{ $p->title }}</div>
                                    <div class="text-[10.5px] text-ink-500 mt-1 font-mono">/blog/{{ $p->slug }}</div>
                                </td>
                                <td class="px-2 py-3 text-[12px] text-ink-700">{{ $p->category->name ?? '—' }}</td>
                                <td class="px-2 py-3 text-center">
                                    @if ($published)
                                        <span class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Published') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-400 text-[10px] font-semibold">{{ __('Draft') }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-3 text-right font-mono text-ink-700">{{ number_format($p->views) }}</td>
                                <td class="px-2 py-3 text-[11.5px] text-ink-600">
                                    {{ optional($p->published_at)->format('M j, Y') ?? optional($p->created_at)->format('M j, Y') }}
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('admin.blog.edit', $p->id) }}"
                                            class="px-2.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11px] font-medium">{{ __('Edit') }}</a>
                                        <form action="{{ route('admin.blog.toggle', $p->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11px] font-medium">
                                                {{ $published ? __('Unpublish') : __('Publish') }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.blog.destroy', $p->id) }}" method="POST" class="inline"
                                            onsubmit="return confirm('{{ __('Delete this post? This cannot be undone.') }}');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1.5 rounded-full text-accent-coral hover:bg-accent-coral/10 text-[11px] font-medium" title="{{ __('Delete') }}">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                                    <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-ink-500">
                                    <div class="font-serif text-[18px] text-ink-700 mb-1">{{ __('No posts yet') }}</div>
                                    <div class="text-[12.5px]">{{ __('Write your first article to start the blog.') }}
                                        <a href="{{ route('admin.blog.create') }}" class="text-wa-deep hover:underline">{{ __('Create a post →') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($posts->hasPages())
                <div class="px-5 py-4 border-t border-paper-200">
                    {{ $posts->withQueryString()->links() }}
                </div>
            @endif
        </div>

    </main>

</x-layouts.admin>
