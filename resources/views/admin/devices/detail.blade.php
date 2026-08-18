<x-layouts.admin :title="__('Admin · Device analytics')" admin-key="devices" page="devices-detail">

    @php
        $phone = trim((string) $device->country_code . ' ' . (string) $device->phone_number);
        $pill = match ($device->status) {
            'connected' => ['bg-wa-mint text-wa-deep', 'bg-wa-green', __('Connected')],
            'needs_pair' => ['bg-accent-amber/15 text-accent-amber', 'bg-accent-amber', __('Needs re-pair')],
            'failed' => ['bg-accent-coral/10 text-accent-coral', 'bg-accent-coral', __('Failed')],
            default => ['bg-paper-100 text-ink-700', 'bg-paper-300', __('Disconnected')],
        };
        $sent = (int) $device->sent_24h;
        $fail = (int) $device->failed_24h;
        $ok = max(0, $sent - $fail);
        $delivPct = $sent > 0 ? round($ok / $sent * 100, 1) : 0;

        // Lifetime, per-device (conversations.device_id) — independent of the
        // range picker so these cards always read all-time.
        $lifeRead    = (int) ($analytics['lifetime']['read'] ?? 0);
        $lifeFailed  = (int) ($analytics['lifetime']['failed'] ?? 0);
        $lifeTotal   = (int) ($analytics['lifetime']['total'] ?? 0);
        $readRate    = $lifeTotal > 0 ? round($lifeRead / $lifeTotal * 100, 1) : 0;
        $failedPct   = $lifeTotal > 0 ? round($lifeFailed / $lifeTotal * 100, 1) : 0;
    @endphp



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/devices') }}" class="hover:text-ink-900">{{ __('Devices') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span
                class="text-ink-900 normal-case tracking-normal truncate max-w-[280px]">{{ $device->device_name ?: __('Device #:id', ['id' => $device->id]) }}{{ optional($device->workspace)->name ? ' · ' . optional($device->workspace)->name : '' }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $pill[0] }} font-mono"><span
                    class="w-1.5 h-1.5 rounded-full {{ $pill[1] }}"></span>{{ $pill[2] }}</span>
            {{-- Drives the volume chart + status donut window. Plain reload —
                 keeps the URL shareable and needs no JS. --}}
            <select
                onchange="window.location.search = '?range=' + this.value"
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option value="24h" @selected($range === '24h')>{{ __('Last 24 hours') }}</option>
                <option value="7d" @selected($range === '7d')>{{ __('Last 7 days') }}</option>
                <option value="30d" @selected($range === '30d')>{{ __('Last 30 days') }}</option>
            </select>
            {{-- Re-sync pulls this device's live status from the bridge, like
                 the user-side reconnect. --}}
            <form method="POST" action="{{ route('admin.devices.resync', $device->id) }}" class="inline">
                @csrf
                <button type="submit"
                    class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                    </svg>
                    {{ __('Re-sync session') }}
                </button>
            </form>
            @if ($device->status === 'connected')
                <form method="POST" action="{{ route('admin.devices.disconnect', $device->id) }}" class="inline"
                    data-confirm="{{ __('Force-disconnect this device? Its live WhatsApp session will be terminated.') }}">
                    @csrf
                    <button type="submit"
                        class="px-3.5 py-1.5 hairline border border-accent-coral/40 text-accent-coral rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Force disconnect') }}</button>
                </form>
            @endif
            <a href="{{ route('admin.devices.export') }}"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                </svg>
                {{ __('Export CSV') }}
            </a>
        </div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <!-- Device header card -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                <div class="flex items-start gap-4 min-w-0">
                    <span class="w-12 h-12 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                            <circle cx="8" cy="11.5" r="0.8" />
                        </svg></span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Device') }} #{{ $device->id }}</div>
                        <h1 class="font-serif text-[28px] leading-tight tracking-[-0.01em] mt-0.5">{{ $device->device_name ?: __('Device #:id', ['id' => $device->id]) }}</h1>
                        <div class="mt-1 font-mono text-[13px] text-ink-700">{{ $phone !== '' ? mask_phone($phone) : '—' }}</div>
                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px] font-semibold">{{ optional($device->workspace)->name ?: '—' }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ optional($device->workspace)->plan ?: '—' }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $device->region ?: '—' }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('Status') }}: {{ $device->status }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('Owner') }}: {{ optional($device->user)->name ?: '—' }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('Paired') }} {{ optional($device->created_at)->diffForHumans() ?: '—' }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form method="POST" action="{{ route('admin.devices.destroy', $device->id) }}" class="inline"
                        data-confirm="{{ __('Remove this device permanently? This cannot be undone.') }}" data-danger="1">
                        @csrf @method('DELETE')
                        <button type="submit"
                            class="px-3.5 py-1.5 hairline border border-accent-coral/40 text-accent-coral rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Remove device') }}</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (24h)') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($sent) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('outgoing') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivered') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">{{ __('healthy') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ $delivPct }}%</span><span
                        class="text-[11px] text-ink-500">{{ number_format($ok) }} ok</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Read rate') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ $readRate }}%</span><span
                        class="text-[11px] text-ink-500">{{ number_format($lifeRead) }} {{ __('read') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</span><span
                        class="text-[10px] text-accent-coral font-mono">{{ $failedPct }}%</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($lifeFailed) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('all time') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total sent') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($lifeTotal) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('all time') }}</span></div>
            </div>
        </section>

        <!-- Volume + status -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                @php
                    $rangeLabel = ['24h' => __('last 24h'), '7d' => __('last 7 days'), '30d' => __('last 30 days')][$range] ?? __('last 7 days');
                @endphp
                <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Activity') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Sent vs failed') }} · {{ $rangeLabel }}</h3>
                    </div>
                </div>
                <div id="chart-volume" class="h-[260px]"
                    data-labels='@json($analytics["volume"]["labels"])'
                    data-sent='@json($analytics["volume"]["sent"])'
                    data-failed='@json($analytics["volume"]["failed"])'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status mix') }}
                </div>
                <h3 class="font-serif text-[22px] leading-tight mt-0.5 mb-3">{{ __('Delivery breakdown') }}</h3>
                <div id="chart-status" class="h-[200px]"
                    data-read='{{ (int) $analytics["status"]["read"] }}'
                    data-delivered='{{ (int) $analytics["status"]["delivered"] }}'
                    data-pending='{{ (int) $analytics["status"]["pending"] }}'
                    data-failed='{{ (int) $analytics["status"]["failed"] }}'></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>{{ __('Read') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($analytics['status']['read']) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>{{ __('Delivered') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($analytics['status']['delivered']) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>{{ __('Pending') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($analytics['status']['pending']) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>{{ __('Failed') }}</span><span
                            class="font-mono text-ink-700">{{ number_format($analytics['status']['failed']) }}</span></div>
                </div>
            </div>
        </section>

        <!-- Hour heatmap -->
        <section>
            <div class="min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('When it fires') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Hour-of-day heatmap') }}</h3>
                    </div>
                    <span class="text-[11px] font-mono text-ink-500">{{ __('last 7 days · IST') }}</span>
                </div>
                <div id="chart-heatmap" class="h-[260px]"
                    data-labels='@json($analytics["heat_labels"])'
                    data-grid='@json($analytics["heatmap"])'></div>
            </div>
        </section>

        <!-- Recent events table + Admin audit -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recent events') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Session log') }}</h2>
                    </div>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[560px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3 w-[140px]">{{ __('When') }}</th>
                            <th class="text-left px-3 py-3 w-[130px]">{{ __('Event') }}</th>
                            <th class="text-left px-3 py-3">{{ __('Detail') }}</th>
                            <th class="text-left px-4 py-3 w-[100px]">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($events as $ev)
                            @php
                                $act = (string) $ev->action;
                                $short = \Illuminate\Support\Str::afterLast($act, '.');
                                $tone = str_contains($act, 'destroy') || str_contains($act, 'remove')
                                    ? 'bg-accent-coral/10 text-accent-coral'
                                    : (str_contains($act, 'disconnect') ? 'bg-accent-amber/15 text-accent-amber' : 'bg-wa-mint text-wa-deep');
                                $meta = is_array($ev->meta ?? null) ? $ev->meta : [];
                            @endphp
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-4 py-3 font-mono text-[11px]">
                                    {{ optional($ev->created_at)->diffForHumans() ?: '—' }}</td>
                                <td class="px-3 py-3"><span
                                        class="px-2 py-0.5 rounded-full {{ $tone }} text-[10.5px] font-mono">{{ $short }}</span>
                                </td>
                                <td class="px-3 py-3 text-ink-700">{{ $meta ? json_encode($meta) : $act }}</td>
                                <td class="px-4 py-3 text-[11px] text-ink-500">
                                    {{ optional($ev->actor)->name ?: __('system') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-ink-500 text-[12px]">
                                    {{ __('No recorded events for this device yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @if ($events->hasPages())
                    <div class="px-4 py-3 border-t border-paper-200">
                        {{ $events->links() }}
                    </div>
                @endif
            </div>

            <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Admin audit') }}
                </div>
                <h2 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('Action log') }}</h2>
                <ol class="space-y-2.5 text-[11.5px]">
                    @forelse ($events as $ev)
                        @php
                            $dot = str_contains((string) $ev->action, 'destroy')
                                ? 'bg-accent-coral'
                                : (str_contains((string) $ev->action, 'disconnect') ? 'bg-accent-amber' : 'bg-wa-green');
                        @endphp
                        <li class="flex gap-2"><span
                                class="w-1.5 h-1.5 rounded-full {{ $dot }} mt-1.5 shrink-0"></span><span><b>{{ optional($ev->actor)->name ?: __('system') }}</b>
                                {{ \Illuminate\Support\Str::afterLast((string) $ev->action, '.') }} ·
                                <span
                                    class="text-ink-500">{{ optional($ev->created_at)->format('Y-m-d H:i') }}</span></span>
                        </li>
                    @empty
                        <li class="text-ink-500">{{ __('No admin actions recorded for this device yet.') }}</li>
                    @endforelse
                </ol>
            </div>
        </section>

    </main>

</x-layouts.admin>
