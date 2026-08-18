<x-layouts.admin :title="__('Team inbox')" admin-key="support-team" page="admin-support-team">

    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/support') }}" class="hover:text-ink-900">{{ __('Support') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Team inbox') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Support · Team') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Team') }}
                    <span class="italic text-wa-deep">{{ __('kanban') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                    {{ __('Drag a ticket between columns to flip its status. "Resolved" sets resolved_at to now. Filter to your own tickets if you want to focus.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/admin/support/team-inbox' . ($mineOnly ? '' : '?mine=1')) }}"
                    class="px-3.5 py-2 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                    {{ $mineOnly ? 'Show all tickets' : 'Mine only' }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach (\App\Http\Controllers\Admin\Support\TeamInboxController::COLUMNS as $col)
                @php
                    $tickets = $columns[$col] ?? collect();
                    $title =
                        [
                            'open' => 'New',
                            'in_progress' => 'In progress',
                            'pending' => 'Awaiting customer',
                            'resolved' => 'Resolved',
                        ][$col] ?? ucfirst($col);
                    $accent =
                        [
                            'open' => 'bg-accent-amber/15 text-accent-amber',
                            'in_progress' => 'bg-wa-bubble text-wa-deep',
                            'pending' => 'bg-accent-coral/10 text-accent-coral',
                            'resolved' => 'bg-wa-mint text-wa-deep',
                        ][$col] ?? 'bg-paper-100 text-ink-700';
                @endphp
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden flex flex-col min-h-[400px]"
                    data-kanban-col="{{ $col }}">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ $title }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-full {{ $accent }} text-[10px] font-mono">{{ $tickets->count() }}</span>
                        </div>
                    </div>
                    <div data-kanban-list class="p-3 flex-1 overflow-y-auto space-y-2">
                        @forelse ($tickets as $t)
                            <article draggable="true" data-kanban-card data-ticket-id="{{ $t->id }}"
                                class="bg-paper-50/40 border border-paper-200 hover:border-wa-deep rounded-xl p-3 cursor-grab">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-[12.5px] truncate">
                                            {{ $t->subject ?: '(no subject)' }}</div>
                                        <div class="text-[10.5px] text-ink-500 font-mono truncate">
                                            {{ $t->ticket_number }} · {{ optional($t->created_at)->diffForHumans() }}
                                        </div>
                                    </div>
                                    @php $prCls = ['urgent'=>'bg-accent-coral/15 text-accent-coral','high'=>'bg-accent-amber/15 text-accent-amber','normal'=>'bg-paper-100 text-ink-700','low'=>'bg-paper-100 text-ink-500'][$t->priority] ?? 'bg-paper-100 text-ink-700'; @endphp
                                    <span
                                        class="px-1.5 py-0.5 rounded-full {{ $prCls }} text-[9.5px] font-mono uppercase border {{ $t->priority === 'urgent' ? 'border-accent-coral/30' : 'border-paper-200' }}">{{ $t->priority }}</span>
                                </div>
                                <div class="mt-2 text-[11px] text-ink-600 line-clamp-2">
                                    {{ Str::limit($t->message, 100) }}</div>
                                <div class="mt-2 text-[10px] text-ink-500 font-mono">{{ $t->name ?: $t->email }}</div>
                            </article>
                        @empty
                            <div class="text-[11px] text-ink-500 text-center py-6">{{ __('No tickets') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>

    </main>

</x-layouts.admin>
