<x-layouts.admin :title="__('Translation usage')" page="admin-translation-usage">

    <main class="max-w-none mx-auto px-4 sm:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4 flex-wrap">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · System') }}</div>
                <h1 class="font-serif text-[30px] sm:text-[44px] leading-none">{{ __('Translation usage') }}</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                    {{ __("Volume + estimated cost of every auto-reply translation. Bundled dictionary hits and 24h cache hits aren't billable and don't appear here — these are the wire calls.") }}
                </p>
            </div>
            <form method="GET" class="flex items-end gap-2 text-[12px]">
                <label>{{ __('From') }} <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"
                        class="ml-1 px-2 py-1 border border-paper-200 rounded font-mono" /></label>
                <label>To <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"
                        class="ml-1 px-2 py-1 border border-paper-200 rounded font-mono" /></label>
                <button
                    class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Apply') }}</button>
            </form>
        </div>

        {{-- Headline cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Wire calls') }}</div>
                <div class="font-serif text-[32px] leading-none mt-1">{{ number_format((int) ($totals->calls ?? 0)) }}
                </div>
                @if (!is_null($cacheRatio))
                    <div class="text-[11px] text-ink-500 mt-1.5 font-mono">~{{ $cacheRatio }}% served from cache /
                        dictionary</div>
                @endif
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                    {{ __('Chars translated') }}</div>
                <div class="font-serif text-[32px] leading-none mt-1">
                    {{ number_format((int) ($totals->chars_in ?? 0)) }}</div>
                <div class="text-[11px] text-ink-500 mt-1.5 font-mono">out:
                    {{ number_format((int) ($totals->chars_out ?? 0)) }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Estimated cost') }}
                </div>
                <div class="font-serif text-[32px] leading-none mt-1">{!! \App\Support\FormatSettings::display(((int) ($totals->cost_micros ?? 0)) / 1_000_000, 'USD') !!}</div>
                <div class="text-[11px] text-ink-500 mt-1.5 font-mono">{{ __('based on published per-char rates') }}
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Fallback hits') }}
                </div>
                <div class="font-serif text-[32px] leading-none mt-1">
                    {{ number_format((int) ($totals->fallback_calls ?? 0)) }}</div>
                <div class="text-[11px] text-ink-500 mt-1.5 font-mono">{{ __('primary driver failed, chain caught') }}
                </div>
            </div>
        </div>

        {{-- Per-provider --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200">
                <h3 class="font-serif text-[20px]">{{ __('By provider') }}</h3>
            </div>
            @if ($perProvider->isEmpty())
                <div class="px-5 py-8 text-center text-[12px] text-ink-500">
                    {{ __('No translation calls in this range. Send a non-English inbound to a device with an auto-reply, then refresh.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] min-w-[640px]">
                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                        <tr>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                {{ __('Provider') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                {{ __('Calls') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                {{ __('Chars in') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                {{ __('Cost') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                {{ __('Rate / 1M chars') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @foreach ($perProvider as $row)
                            @php $rate = (\App\Models\TranslationUsage::PROVIDER_COST_MICROS_PER_CHAR[$row->provider_slug] ?? 0); @endphp
                            <tr>
                                <td class="px-5 py-2.5 font-medium font-mono">{{ $row->provider_slug }}</td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ number_format((int) $row->calls) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ number_format((int) $row->chars_in) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">{!! \App\Support\FormatSettings::display(((int) $row->cost_micros) / 1_000_000, 'USD') !!}</td>
                                <td class="px-5 py-2.5 text-right font-mono text-ink-500">{!! $rate > 0 ? \App\Support\FormatSettings::display($rate, 'USD') : 'free' !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        {{-- Daily timeline (last 30d) --}}
        @if ($timeline->isNotEmpty())
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <h3 class="font-serif text-[20px] mb-1">{{ __('Last 30 days') }}</h3>
                @php $maxChars = max($timeline->max('chars_in'), 1); @endphp
                <div class="overflow-x-auto">
                <div class="mt-4 grid grid-cols-30 gap-1 items-end h-32 min-w-[560px]">
                    @foreach ($timeline as $day)
                        @php $h = max(2, (int) round(120 * ($day->chars_in / $maxChars))); @endphp
                        <div class="flex flex-col items-center gap-1"
                            title="{{ $day->day }}: {{ number_format($day->chars_in) }} chars · {{ \App\Support\FormatSettings::display($day->cost_micros / 1_000_000, 'USD') }}">
                            <div class="w-full bg-wa-deep/80 rounded-sm" style="height:{{ $h }}px"></div>
                        </div>
                    @endforeach
                </div>
                </div>
                <div class="text-[10.5px] font-mono text-ink-500 mt-2">
                    {{ __('hover each bar for date / chars / cost') }}</div>
            </div>
        @endif

        {{-- Top workspaces --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200">
                <h3 class="font-serif text-[20px]">{{ __('Top spending workspaces') }}</h3>
            </div>
            @if ($topWorkspaces->isEmpty())
                <div class="px-5 py-8 text-center text-[12px] text-ink-500">
                    {{ __('No paid-tier translations in this range.') }}</div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] min-w-[560px]">
                    <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                        <tr>
                            <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                {{ __('Workspace') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                {{ __('Calls') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                {{ __('Chars') }}</th>
                            <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                {{ __('Cost') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @foreach ($topWorkspaces as $row)
                            <tr>
                                <td class="px-5 py-2.5">
                                    @if ($row->workspace_id)
                                        <a href="{{ route('admin.workspaces.detail', $row->workspace_id) }}"
                                            class="font-medium hover:underline">{{ $row->workspace_name ?: 'Workspace #' . $row->workspace_id }}</a>
                                    @else
                                        <span class="font-mono text-ink-500">{{ __('unassigned') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ number_format((int) $row->calls) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ number_format((int) $row->chars_in) }}
                                </td>
                                <td class="px-5 py-2.5 text-right font-mono">{!! \App\Support\FormatSettings::display(((int) $row->cost_micros) / 1_000_000, 'USD') !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

    </main>

</x-layouts.admin>
