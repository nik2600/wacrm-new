<x-layouts.admin :title="__('Support customers')" admin-key="support-customers" page="admin-support-customers">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Customers') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Support · Customers') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Customer') }} <span
                    class="italic text-wa-deep">{{ __('workspaces') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ __('Workspaces ranked by support load. Click through to view all their tickets + impersonate.') }}
            </p>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([['Customers', $kpi['customers']], ['Total tickets', $kpi['total']], ['Open', $kpi['open']], ['Top open', $kpi['top']]] as [$label, $val])
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="text-[11px] text-ink-600 font-medium">{{ $label }}</div>
                    <div class="font-serif text-[34px] leading-none mt-1">{{ $val }}</div>
                </div>
            @endforeach
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center flex-wrap gap-3 justify-between">
                <h2 class="font-serif text-[20px]">{{ __('Workspaces with tickets') }}</h2>
                <form method="GET" action="{{ route('admin.support.customers') }}" class="relative w-full max-w-[280px]">
                    <input type="search" name="q" value="{{ $q }}"
                        placeholder="{{ __('Filter workspace…') }}"
                        class="w-full px-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0">
                </form>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50 text-ink-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">{{ __('Workspace') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('Open') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('Total') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('Avg resolution') }}</th>
                        <th class="px-3 py-2.5 font-medium">{{ __('Last ticket') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($rows as $r)
                        @php
                            $ws = $workspaces[$r->workspace_id] ?? null;
                            $avg = $r->resolved_count > 0 ? (int) round($r->resolution_minutes_sum / $r->resolved_count) : null;
                        @endphp
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $ws?->name ?? 'Workspace #' . $r->workspace_id }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono">{{ $ws?->slug }}</div>
                            </td>
                            <td
                                class="px-3 py-3 text-right font-mono {{ $r->open_tickets > 0 ? 'text-accent-coral' : 'text-ink-500' }}">
                                {{ $r->open_tickets }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ $r->total_tickets }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ $avg !== null ? $avg . 'm' : '—' }}</td>
                            <td class="px-3 py-3 text-[11px] font-mono">
                                {{ $r->last_ticket_at ? \Carbon\Carbon::parse($r->last_ticket_at)->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($ws)
                                    <a href="{{ route('admin.support.customers.show', $ws->id) }}"
                                        class="text-wa-deep text-[11px] hover:underline">Open →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-ink-500 text-[13px]">
                                {{ __('No customers have raised tickets yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>

    </main>

</x-layouts.admin>
