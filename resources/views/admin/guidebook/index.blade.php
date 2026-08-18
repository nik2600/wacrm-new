<x-layouts.admin :title="__('Guidebook')" admin-key="guidebook" page="admin-guidebook-index">

    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Guidebook') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Marketing · Guidebook') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Help') }}
                    <span class="italic text-wa-deep">{{ __('guidebook') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">{{ __('Write the articles users see at') }} <code
                        class="font-mono text-[11px] bg-paper-50 px-1 rounded">/guidebook</code>. Markdown supported.
                    Stats below count real views + helpful votes.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.guidebook.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    {{ __('New article') }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['total'] }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Published') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['published'] }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Drafts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['drafts'] }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Views') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($kpi['views_30d']) }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1">+{{ number_format($kpi['helpful']) }} helpful ·
                    {{ number_format($kpi['not_helpful']) }} {{ __('not') }}</div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <form method="GET" action="{{ route('admin.guidebook.index') }}"
                class="px-5 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                <select name="category" onchange="this.form.submit()"
                    class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12px]">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search title / body…') }}"
                    class="flex-1 max-w-[320px] px-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12.5px]">
                @if ($q || $category)
                    <a href="{{ route('admin.guidebook.index') }}"
                        class="text-[11px] text-ink-500 hover:text-wa-deep">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] min-w-[720px]">
                <thead class="bg-paper-50 text-ink-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 w-[60px] font-medium">#</th>
                        <th class="px-3 py-2.5 font-medium">{{ __('Title') }}</th>
                        <th class="px-3 py-2.5 w-[130px] font-medium">{{ __('Category') }}</th>
                        <th class="px-3 py-2.5 w-[80px] text-right font-medium">{{ __('Views') }}</th>
                        <th class="px-3 py-2.5 w-[90px] text-center font-medium">{{ __('Helpful') }}</th>
                        <th class="px-3 py-2.5 w-[100px] text-center font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-2.5 w-[120px] text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($articles as $a)
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11px]">{{ $a->sort_order }}</td>
                            <td class="px-3 py-3">
                                <div class="font-semibold">{{ $a->title }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono">/guidebook/{{ $a->slug }}</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10.5px] font-mono">{{ $a->category }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format($a->views_count) }}</td>
                            <td class="px-3 py-3 text-center font-mono text-[11px]">
                                @if ($a->helpful_count + $a->not_helpful_count > 0)
                                    {{ round(($a->helpful_count / ($a->helpful_count + $a->not_helpful_count)) * 100) }}%
                                @else
                                    <span class="text-ink-500">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <form method="POST" action="{{ route('admin.guidebook.toggle', $a->id) }}"
                                    class="inline">@csrf
                                    <button
                                        class="px-2.5 py-1 rounded-full {{ $a->is_published ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-600 border border-paper-200' }} text-[10.5px] font-mono">
                                        {{ $a->is_published ? __('Live') : __('Draft') }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.guidebook.edit', $a->id) }}"
                                    class="text-wa-deep text-[11px] hover:underline mr-3">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.guidebook.destroy', $a->id) }}"
                                    class="inline" data-confirm="Delete '{{ $a->title }}'?">@csrf @method('DELETE')
                                    <button
                                        class="text-accent-coral text-[11px] hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-ink-500 text-[13px]">
                                No articles yet. <a href="{{ route('admin.guidebook.create') }}"
                                    class="text-wa-deep hover:underline">{{ __('Write the first one →') }}</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40">{{ $articles->links() }}</div>
        </section>

    </main>

</x-layouts.admin>
