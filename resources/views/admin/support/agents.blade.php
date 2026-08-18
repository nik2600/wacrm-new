<x-layouts.admin :title="__('Support agents')" admin-key="support-agents" page="admin-support-agents">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Agents') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Support · Agents') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Support') }} <span
                    class="italic text-wa-deep">{{ __('agents') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ __('Promote any platform user to a support agent. Active agents appear in the ticket-assign dropdown and rotate into auto-assignment.') }}
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([['Total agents', $kpi['total'], 'paper-200'], ['Active', $kpi['active'], 'wa-green/40'], ['Open tickets', $kpi['open_total'], 'paper-200'], ['Unassigned', $kpi['unassigned'], 'accent-coral/40']] as [$label, $val, $border])
                <div class="bg-paper-0 border border-{{ $border }} rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ $label }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1">{{ $val }}</div>
                </div>
            @endforeach
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <h2 class="font-serif text-[22px] leading-tight">{{ __('Agent roster') }}</h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200 text-left">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">{{ __('Name') }}</th>
                            <th class="px-3 py-2.5 font-medium">{{ __('Specialty') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('Open') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('Resolved 30d') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('Avg 1st reply') }}</th>
                            <th class="px-3 py-2.5 text-center font-medium">{{ __('Active') }}</th>
                            <th class="px-4 py-2.5 text-right font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($agents as $a)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $a->user?->name ?? 'User #' . $a->user_id }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">{{ $a->user?->email }}</div>
                                </td>
                                <td class="px-3 py-3 text-[11.5px]">{{ $a->specialty ?: '—' }}</td>
                                <td class="px-3 py-3 text-right font-mono">{{ $a->open_count }}</td>
                                <td class="px-3 py-3 text-right font-mono">{{ $a->resolved_30d }}</td>
                                <td class="px-3 py-3 text-right font-mono">
                                    {{ $a->avg_first_response_min ? $a->avg_first_response_min . 'm' : '—' }}</td>
                                <td class="px-3 py-3 text-center">
                                    <form method="POST" action="{{ route('admin.support.agents.toggle', $a->id) }}"
                                        class="inline">@csrf
                                        <button
                                            class="px-2.5 py-1 rounded-full {{ $a->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-600 border border-paper-200' }} text-[10.5px] font-mono">{{ $a->is_active ? 'On' : 'Off' }}</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.support.agents.destroy', $a->id) }}"
                                        class="inline" data-confirm="Remove {{ $a->user?->name ?? 'this agent' }}?">
                                        @csrf @method('DELETE')
                                        <button
                                            class="text-accent-coral text-[11px] hover:underline">{{ __('Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                    {{ __('No agents yet. Add one on the right.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <aside class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card lg:sticky lg:top-[88px]">
                <form method="POST" action="{{ route('admin.support.agents.store') }}">
                    @csrf
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Promote user') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('New agent') }}</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('User *') }}</span>
                            <select name="user_id" required
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <option value="">— pick a user —</option>
                                @foreach ($candidates as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Specialty') }}</span>
                            <input name="specialty" placeholder="{{ __('billing / integrations / general') }}"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <button
                            class="w-full px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Add agent') }}</button>
                    </div>
                </form>
            </aside>
        </section>

    </main>

</x-layouts.admin>
