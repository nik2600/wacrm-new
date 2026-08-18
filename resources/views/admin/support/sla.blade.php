<x-layouts.admin :title="__('SLA board')" admin-key="support-sla" page="admin-support-sla">
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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('SLA board') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Support · SLA') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('SLA') }} <span
                    class="italic text-wa-deep">{{ __('board') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ __('Policies define how fast first response + resolution must happen. Breaches list shows tickets that already missed their window.') }}
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([['Policies', $kpi['policies']], ['Open breaches', $kpi['open_breaches']], ['At risk now', $kpi['at_risk']], ['Compliance · 7d', $kpi['compliance_7d'] . '%']] as [$label, $val])
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ $label }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1">{{ $val }}</div>
                </div>
            @endforeach
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="space-y-5 min-w-0">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h2 class="font-serif text-[20px] leading-tight">{{ __('Policies') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 text-ink-500 text-left">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">{{ __('Name') }}</th>
                                <th class="px-3 py-2.5 text-right font-medium">1st reply</th>
                                <th class="px-3 py-2.5 text-right font-medium">{{ __('Resolution') }}</th>
                                <th class="px-3 py-2.5 text-center font-medium">{{ __('Hours') }}</th>
                                <th class="px-3 py-2.5 text-center font-medium">{{ __('Default') }}</th>
                                <th class="px-4 py-2.5 text-right font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($policies as $p)
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                                    <td class="px-3 py-3 text-right font-mono">{{ $p->first_response_minutes }}m</td>
                                    <td class="px-3 py-3 text-right font-mono">{{ $p->resolution_minutes }}m</td>
                                    <td class="px-3 py-3 text-center text-[11px]">
                                        {{ $p->respect_business_hours ? 'biz' : '24/7' }}</td>
                                    <td class="px-3 py-3 text-center">
                                        @if ($p->is_default)
                                            <span
                                                class="px-2 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-mono">{{ __('default') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.support.sla.destroy', $p->id) }}"
                                            class="inline" data-confirm="Delete policy '{{ $p->name }}'?">@csrf
                                            @method('DELETE')
                                            <button
                                                class="text-accent-coral text-[11px] hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                        {{ __('No SLA policies yet. Create one to set response/resolution targets.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h2 class="font-serif text-[20px] leading-tight">{{ __('Recent breaches') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 text-ink-500 text-left">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">{{ __('When') }}</th>
                                <th class="px-3 py-2.5 font-medium">{{ __('Ticket') }}</th>
                                <th class="px-3 py-2.5 font-medium">{{ __('Type') }}</th>
                                <th class="px-3 py-2.5 text-right font-medium">{{ __('Over by') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Severity') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($breaches as $b)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-[11px]">
                                        {{ \Carbon\Carbon::parse($b->breached_at)->diffForHumans() }}</td>
                                    <td class="px-3 py-3">#{{ $b->ticket_number ?? $b->ticket_id }} <span
                                            class="text-ink-500">· {{ Str::limit($b->subject, 50) }}</span></td>
                                    <td class="px-3 py-3"><span
                                            class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-mono">{{ $b->breach_type }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono">
                                        {{ $b->over_by_minutes ? $b->over_by_minutes . 'm' : '—' }}</td>
                                    <td class="px-4 py-3">
                                        @php $cls = ['warn' => 'bg-accent-amber/15 text-accent-amber', 'breach' => 'bg-accent-coral/15 text-accent-coral', 'hard_breach' => 'bg-accent-coral/30 text-accent-coral'][$b->severity] ?? ''; @endphp
                                        <span
                                            class="px-2 py-0.5 rounded-full {{ $cls }} text-[10px] font-mono uppercase">{{ $b->severity }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                        {{ __('No breaches yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <aside class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card lg:sticky lg:top-[88px]">
                <form method="POST" action="{{ route('admin.support.sla.store') }}">
                    @csrf
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('New policy') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Add SLA target') }}</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Name *') }}</span>
                            <input name="name" required placeholder="{{ __('Standard / Premium / Urgent') }}"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">1st reply
                                    (min)</span>
                                <input type="number" name="first_response_minutes" min="1" value="60"
                                    required
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono">
                            </label>
                            <label class="block">
                                <span
                                    class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Resolve (min)') }}</span>
                                <input type="number" name="resolution_minutes" min="1" value="1440"
                                    required
                                    class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono">
                            </label>
                        </div>
                        <label
                            class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2 text-[12px]"><span>{{ __('Business hours only') }}</span>
                            <span class="toggle"><input type="hidden" name="respect_business_hours"
                                    value="0"><input type="checkbox" name="respect_business_hours"
                                    value="1" checked><span class="track"></span><span
                                    class="thumb"></span></span>
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2 text-[12px]"><span>{{ __('Pause on customer wait') }}</span>
                            <span class="toggle"><input type="hidden" name="pause_when_waiting_on_customer"
                                    value="0"><input type="checkbox" name="pause_when_waiting_on_customer"
                                    value="1"><span class="track"></span><span class="thumb"></span></span>
                        </label>
                        <label
                            class="flex items-center justify-between rounded-xl border border-paper-200 px-3 py-2 text-[12px]"><span>{{ __('Set as default') }}</span>
                            <span class="toggle"><input type="hidden" name="is_default" value="0"><input
                                    type="checkbox" name="is_default" value="1"><span
                                    class="track"></span><span class="thumb"></span></span>
                        </label>
                        <button
                            class="w-full px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Add policy') }}</button>
                    </div>
                </form>
            </aside>
        </section>

    </main>

</x-layouts.admin>
