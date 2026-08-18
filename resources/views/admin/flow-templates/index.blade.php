<x-layouts.admin :title="__('Bot Flow templates')" admin-key="flow-templates" page="admin-flow-templates-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Flow templates') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Automation · Templates') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Bot Flow') }} <span class="italic text-wa-deep">{{ __('templates') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Build a standard flow once — a restaurant welcome, a lead qualifier — and every tenant can clone it into their workspace from the "Start from a template" gallery on Flows.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.flow-templates.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                    {{ __('New template') }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-mint/50 px-4 py-3 text-[12.5px] text-wa-deep font-medium">{{ session('success') }}</div>
        @endif

        {{-- KPI strip --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Templates') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total'] ?? 0) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('total') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Visible to tenants') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active'] ?? 0) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('active') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Times cloned') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['clones'] ?? 0) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('by tenants') }}</div>
            </div>
        </section>

        @php
            $typeBadge = [
                'chat'      => ['Chat', 'bg-wa-mint text-wa-deep'],
                'call'      => ['Call', 'bg-accent-amber/15 text-[#7B5A14]'],
                'instagram' => ['Instagram', 'bg-[#EFE5F5] text-[#5B3D8A]'],
            ];
        @endphp

        <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
            <div class="overflow-x-auto">
                <div class="min-w-[760px]">
                    <div class="px-4 py-2.5 grid grid-cols-[1.6fr_120px_120px_90px_90px_120px] items-center gap-3 border-b border-paper-200 bg-paper-50 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        <div>{{ __('Template') }}</div>
                        <div>{{ __('Type') }}</div>
                        <div>{{ __('Category') }}</div>
                        <div>{{ __('Steps') }}</div>
                        <div>{{ __('Clones') }}</div>
                        <div class="text-right pr-2">{{ __('Actions') }}</div>
                    </div>

                    @forelse ($templates as $t)
                        @php $tb = $typeBadge[$t->flow_type] ?? ['—', 'bg-paper-100 text-ink-500']; @endphp
                        <div class="px-4 py-3 grid grid-cols-[1.6fr_120px_120px_90px_90px_120px] items-center gap-3 border-b border-paper-200 hover:bg-paper-50 transition">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-[13px] text-ink-900 truncate">{{ $t->name }}</span>
                                    @unless ($t->is_active)
                                        <span class="text-[9.5px] font-mono uppercase tracking-wider px-1.5 py-0.5 rounded bg-paper-100 text-ink-500">{{ __('hidden') }}</span>
                                    @endunless
                                </div>
                                @if ($t->description)
                                    <div class="text-[11.5px] text-ink-500 truncate">{{ \Illuminate\Support\Str::limit($t->description, 90) }}</div>
                                @endif
                            </div>
                            <div><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-medium {{ $tb[1] }}">{{ $tb[0] }}</span></div>
                            <div class="text-[12px] text-ink-600 truncate">{{ $t->category ?: '—' }}</div>
                            <div class="font-mono text-[12px] text-ink-700">{{ $t->node_count }}</div>
                            <div class="font-mono text-[12px] text-ink-700">{{ number_format($t->clone_count) }}</div>
                            <div class="flex items-center gap-1 justify-end">
                                <form method="POST" action="{{ route('admin.flow-templates.toggle', $t->id) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="w-8 h-8 rounded-lg grid place-items-center hover:bg-paper-100 {{ $t->is_active ? 'text-wa-deep' : 'text-ink-400' }} transition"
                                        title="{{ $t->is_active ? __('Hide from tenants') : __('Show to tenants') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                                            @if ($t->is_active)
                                                <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8z" /><circle cx="8" cy="8" r="1.8" />
                                            @else
                                                <path d="M2 2l12 12M6.5 6.6a2 2 0 0 0 2.8 2.8M4 4.6C2.4 5.7 1.5 8 1.5 8s2.5 4.5 6.5 4.5c1 0 1.9-.2 2.7-.6M9.5 4.1A6.6 6.6 0 0 1 14.5 8s-.5.9-1.5 1.9" />
                                            @endif
                                        </svg>
                                    </button>
                                </form>
                                <a href="{{ route('admin.flow-templates.edit', $t->id) }}"
                                    class="w-8 h-8 rounded-lg grid place-items-center hover:bg-paper-100 text-ink-600 transition" title="{{ __('Edit') }}">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 3l2 2-7 7H4v-2z" /></svg>
                                </a>
                                <form method="POST" action="{{ route('admin.flow-templates.destroy', $t->id) }}" class="inline"
                                    data-confirm="{{ __('Delete this template? Tenants who already cloned it keep their copy.') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-8 h-8 rounded-lg grid place-items-center text-accent-coral hover:bg-accent-coral/10 transition" title="{{ __('Delete') }}">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2.5 4h11M6 4V2.5h4V4M4.3 4l.6 9.5h6.2l.6-9.5" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <div class="font-serif text-[18px] mb-1">{{ __('No templates yet') }}</div>
                            <p class="text-[12.5px] text-ink-500 max-w-[460px] mx-auto">
                                {{ __('Build a flow in the builder, export it, then add it here — or paste its JSON. Active templates show up in every tenant\'s Flows page.') }}
                            </p>
                            <a href="{{ route('admin.flow-templates.create') }}" class="inline-block mt-3 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Add your first template') }}</a>
                        </div>
                    @endforelse
                </div>
            </div>
            @if ($templates->hasPages())
                <div class="px-4 py-3 border-t border-paper-200">{{ $templates->links() }}</div>
            @endif
        </div>
    </main>
</x-layouts.admin>
