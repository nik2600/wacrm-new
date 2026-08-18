<x-layouts.admin :title="__('Support playbooks')" admin-key="support-playbooks" page="admin-support-playbooks">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Playbooks') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Support · Playbooks') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Reply') }} <span
                    class="italic text-wa-deep">{{ __('playbooks') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ __('Reusable sequences of actions — apply with one click from any ticket. Each playbook runs its steps in order (set status, send reply, assign agent, etc.).') }}
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Total') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['total'] }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['active'] }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Runs (all-time)') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['uses'] }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-stretch">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-paper-200">
                    <h2 class="font-serif text-[20px]">{{ __('Playbook library') }}</h2>
                </div>
                <div class="flex-1 divide-y divide-paper-200">
                    @forelse ($playbooks as $p)
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-serif text-[16px]">{{ $p->name }}</h3>
                                        <span
                                            class="px-1.5 py-0.5 rounded-full {{ $p->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-700' }} text-[10px] font-mono">{{ $p->is_active ? 'active' : 'paused' }}</span>
                                        <span
                                            class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-mono">{{ $p->trigger_type }}{{ $p->trigger_value ? ': ' . $p->trigger_value : '' }}</span>
                                    </div>
                                    <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                        {{ count((array) $p->steps) }} steps · used {{ $p->use_count }}× ·
                                        {{ $p->slug }}</div>
                                    @if (!empty($p->steps))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ((array) $p->steps as $s)
                                                <span
                                                    class="px-2 py-0.5 rounded-full bg-paper-50 border border-paper-200 text-[10px] font-mono">{{ $actions[$s['action'] ?? '']['label'] ?? ($s['action'] ?? '?') }}{{ !empty($s['value']) ? ': ' . Str::limit($s['value'], 24) : '' }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <form method="POST" action="{{ route('admin.support.playbooks.toggle', $p->id) }}"
                                        class="inline">@csrf
                                        <button
                                            class="px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[10.5px] font-mono">{{ $p->is_active ? 'Pause' : 'Activate' }}</button>
                                    </form>
                                    <form method="POST"
                                        action="{{ route('admin.support.playbooks.destroy', $p->id) }}" class="inline"
                                        data-confirm="Delete playbook '{{ $p->name }}'?">@csrf @method('DELETE')
                                        <button
                                            class="text-accent-coral text-[11px] hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        {{-- Helpful empty state: explains what playbooks are, shows two
 common starter examples, balances the layout against the
 tall builder card on the right. --}}
                        <div class="flex-1 p-8 flex flex-col items-center justify-center text-center">
                            <div class="w-14 h-14 rounded-2xl bg-wa-bubble grid place-items-center mb-3">
                                <svg viewBox="0 0 24 24" class="w-6 h-6 text-wa-deep" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <path d="M4 4h12l4 4v12H4z" />
                                    <path d="M8 9h8M8 13h6M8 17h4" />
                                </svg>
                            </div>
                            <h3 class="font-serif text-[20px]">{{ __('No playbooks yet') }}</h3>
                            <p class="text-[12.5px] text-ink-600 mt-1 max-w-xs">
                                {{ __("A playbook bundles a few actions you'd normally do one-by-one — apply with one click from any ticket.") }}
                            </p>
                            <div class="mt-5 w-full max-w-md">
                                <div
                                    class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2 text-left">
                                    {{ __('Common starters') }}</div>
                                <div class="space-y-2 text-left">
                                    <div class="rounded-xl border border-paper-200 bg-paper-50/40 p-3">
                                        <div class="font-semibold text-[12.5px]">{{ __('Refund · standard') }}</div>
                                        <div class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __('Send refund template → Mark resolved → Tag "refund"') }}</div>
                                    </div>
                                    <div class="rounded-xl border border-paper-200 bg-paper-50/40 p-3">
                                        <div class="font-semibold text-[12.5px]">{{ __('Escalate · urgent') }}</div>
                                        <div class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __('Set priority urgent → Assign on-call agent → Internal note') }}
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-paper-200 bg-paper-50/40 p-3">
                                        <div class="font-semibold text-[12.5px]">{{ __('Pause · awaiting customer') }}
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 mt-1">
                                            {{ __('Set status pending → Send "we need more info" reply') }}</div>
                                    </div>
                                </div>
                                <p class="text-[10.5px] text-ink-500 mt-3 font-mono">
                                    {{ __('Build any of these on the right →') }}</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card lg:sticky lg:top-[88px]">
                <form method="POST" action="{{ route('admin.support.playbooks.store') }}">
                    @csrf
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('New playbook') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Build a macro') }}</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Name *') }}</span>
                            <input name="name" required placeholder="{{ __('Refund · standard') }}"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="block">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Trigger') }}</span>
                                <select name="trigger_type"
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                    <option value="manual">{{ __('Manual') }}</option>
                                    <option value="status_change">{{ __('Status change') }}</option>
                                    <option value="tag_added">{{ __('Tag added') }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Value (if applicable)') }}</span>
                                <input name="trigger_value" placeholder="{{ __('refund') }}"
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                                {{ __('Steps') }}</div>
                            <div class="space-y-2">
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="grid grid-cols-[140px_1fr] gap-2">
                                        <select name="steps[{{ $i }}][action]"
                                            class="px-2 py-1.5 border border-paper-200 rounded-lg bg-white text-[11.5px]">
                                            <option value="">— skip —</option>
                                            @foreach ($actions as $k => $a)
                                                <option value="{{ $k }}">{{ $a['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <input name="steps[{{ $i }}][value]"
                                            placeholder="{{ __('value') }}"
                                            class="px-2 py-1.5 border border-paper-200 rounded-lg bg-white text-[11.5px]">
                                    </div>
                                @endfor
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1.5">
                                {{ __('Empty rows are ignored. 3 step slots — clone the playbook to chain more.') }}
                            </div>
                        </div>
                        <button
                            class="w-full px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Create playbook') }}</button>
                    </div>
                </form>
            </aside>
        </section>

    </main>

</x-layouts.admin>
