<x-layouts.admin :title="__('Support reports')" admin-key="support-reports" page="admin-support-reports">

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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Reports') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Support · Reports') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Support') }}
                    <span class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                    {{ __('Last 30 days. Response, resolution, and SLA compliance at a glance — drill down by exporting raw rows.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2">
                @foreach ([7 => '7d', 30 => '30d', 90 => '90d', 365 => '1y'] as $d => $lbl)
                    <a href="{{ route('admin.support.reports', ['days' => $d]) }}"
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-medium {{ $days == $d ? 'bg-wa-deep text-paper-0' : 'hairline border border-paper-200 bg-paper-0 hover:bg-paper-50' }}">{{ $lbl }}</a>
                @endforeach
                <a href="{{ route('admin.support.reports.export') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Download CSV') }}</a>
            </div>
        </div>

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Tickets · 30d') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['tickets_30d'] }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Resolved · 30d') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['resolved_30d'] }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Avg 1st reply') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['avg_first_resp'] ?: '—' }}<span
                        class="text-[14px] text-ink-500">m</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('Avg resolution') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['avg_resolution'] ?: '—' }}<span
                        class="text-[14px] text-ink-500">m</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600">{{ __('SLA compliance') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $kpi['compliance'] }}%</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <h2 class="font-serif text-[18px]">{{ __('Volume · last 30 days') }}</h2>
                </div>
                <div class="p-5">
                    @if ($volume->isEmpty())
                        <div class="text-center text-ink-500 text-[12.5px] py-8">
                            {{ __('No tickets in the last 30 days.') }}</div>
                    @else
                        @php $max = max($volume->pluck('n')->toArray() ?: [1]); @endphp
                        <div class="flex items-end gap-1 h-[140px]">
                            @foreach ($volume as $v)
                                <div class="flex-1 bg-wa-deep/80 rounded-sm"
                                    style="height: {{ max(4, (int) round(($v->n / $max) * 130)) }}px"
                                    title="{{ $v->d }}: {{ $v->n }} tickets"></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <h2 class="font-serif text-[18px]">{{ __('By status') }}</h2>
                </div>
                <div class="p-5 space-y-2">
                    @php $totalStatus = max(1, (int) $byStatus->sum('n')); @endphp
                    @foreach ($byStatus as $s)
                        <div>
                            <div class="flex items-center justify-between text-[11.5px]">
                                <span class="font-mono text-ink-600">{{ $s->status }}</span>
                                <span class="font-mono">{{ $s->n }} ·
                                    {{ round(($s->n / $totalStatus) * 100) }}%</span>
                            </div>
                            <div class="h-1.5 bg-paper-100 rounded-full mt-1 overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width: {{ ($s->n / $totalStatus) * 100 }}%">
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if ($byStatus->isEmpty())
                        <div class="text-[12.5px] text-ink-500 text-center py-4">{{ __('No tickets yet.') }}</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200">
                <h2 class="font-serif text-[18px]">{{ __('Top agents · 30 days') }}</h2>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50 text-ink-500 text-left">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">{{ __('Agent') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('Resolved') }}</th>
                        <th class="px-3 py-2.5 text-right font-medium">{{ __('Avg 1st reply') }}</th>
                        <th class="px-4 py-2.5 text-right font-medium">{{ __('Avg resolution') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    @forelse ($topAgents as $a)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $a->name }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ $a->resolved }}</td>
                            <td class="px-3 py-3 text-right font-mono">
                                {{ $a->avg_first_resp ? (int) round($a->avg_first_resp) . 'm' : '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ $a->avg_resolution ? (int) round($a->avg_resolution) . 'm' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-ink-500 text-[13px]">
                                {{ __('No resolved tickets yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </section>

    </main>

</x-layouts.admin>
