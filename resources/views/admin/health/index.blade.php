{{--
 /admin/health — System Health monitor. One screen for "is everything up?":
 database, cache, queue, the Node WhatsApp bridge, media storage, the error
 log, every connected engine, and host vitals. Polls ?format=json to stay live.
--}}
<x-layouts.admin :title="__('System Health')" admin-key="health">

    @php
        $tone = [
            'up'   => ['dot' => 'bg-wa-green', 'text' => 'text-wa-deep', 'bg' => 'bg-wa-mint', 'ring' => 'ring-wa-green/15', 'word' => __('Operational')],
            'warn' => ['dot' => 'bg-amber-500',   'text' => 'text-amber-700',   'bg' => 'bg-amber-50',   'ring' => 'ring-amber-500/15',   'word' => __('Degraded')],
            'down' => ['dot' => 'bg-red-500',      'text' => 'text-red-700',     'bg' => 'bg-red-50',     'ring' => 'ring-red-500/15',     'word' => __('Down')],
            'idle' => ['dot' => 'bg-ink-300',      'text' => 'text-ink-500',     'bg' => 'bg-paper-100',  'ring' => 'ring-ink-500/10',     'word' => __('Idle')],
        ];
        $T = fn($s) => $tone[$s] ?? $tone['idle'];
        $ov = $T($overall);
    @endphp

    {{-- Header --}}
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('System Health') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-5 space-y-5">

        {{-- Overall status banner --}}
        <div class="rounded-2xl border border-paper-200 shadow-card overflow-hidden">
            <div class="flex items-center gap-4 px-5 py-4 {{ $ov['bg'] }} ring-1 {{ $ov['ring'] }}">
                <span class="relative flex h-3.5 w-3.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $ov['dot'] }} opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-3.5 w-3.5 {{ $ov['dot'] }}"></span>
                </span>
                <div class="flex-1">
                    <div class="font-serif text-[20px] leading-tight {{ $ov['text'] }}">{{ __('All systems') }} — {{ $ov['word'] }}</div>
                    <div class="text-[12px] text-ink-500 mt-0.5">{{ __('Last checked') }} <span data-hk-ts>{{ now()->format('H:i:s') }}</span> · {{ __('auto-refreshes every 20s') }}</div>
                </div>
                <button type="button" data-hk-refresh class="px-3 py-1.5 rounded-lg border border-paper-300 bg-paper-0/70 text-[12px] font-medium hover:bg-paper-0">{{ __('Refresh now') }}</button>
            </div>
        </div>

        {{-- Last 24h activity --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $acts = [
                    ['label' => __('Messages · 24h'), 'value' => $activity['messages'] ?? 0, 'icon' => '<path d="M2 3h12v8H6l-3 3V3Z"/>'],
                    ['label' => __('AI calls · 24h'), 'value' => $activity['ai_calls'] ?? 0, 'icon' => '<circle cx="8" cy="8" r="2.4"/><path d="M8 2v2M8 12v2M2 8h2M12 8h2"/>'],
                    ['label' => __('Conversations · 24h'), 'value' => $activity['conversations'] ?? 0, 'icon' => '<path d="M2 4h12v7H5l-3 2V4Z"/>'],
                    ['label' => __('Webhooks · 24h'), 'value' => $activity['webhooks'] ?? 0, 'icon' => '<path d="M4 8a4 4 0 1 1 6 3.5M8 8l3 5M6 13H3"/>'],
                ];
            @endphp
            @foreach ($acts as $a)
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl bg-wa-deep/10 text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5">{!! $a['icon'] !!}</svg>
                    </span>
                    <div>
                        <div class="font-serif text-[24px] leading-none text-ink-900">{{ number_format($a['value']) }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ $a['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Core service probes --}}
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Core services') }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($checks as $key => $c)
                    @php $t = $T($c['state']); @endphp
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card" data-hk-card="{{ $key }}">
                        <div class="flex items-center justify-between">
                            <div class="text-[13px] font-semibold text-ink-900">{{ $c['label'] }}</div>
                            <span class="flex items-center gap-1.5 text-[10px] font-mono uppercase tracking-wide px-2 py-0.5 rounded {{ $t['bg'] }} {{ $t['text'] }}" data-hk-badge>
                                <span class="w-2 h-2 rounded-full {{ $t['dot'] }}" data-hk-dot></span><span data-hk-state>{{ $t['word'] }}</span>
                            </span>
                        </div>
                        <div class="font-serif text-[19px] text-ink-900 mt-2.5 capitalize" data-hk-value>{{ $c['value'] }}</div>
                        <div class="text-[11.5px] text-ink-500 mt-1" data-hk-detail>{{ $c['detail'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Latency + throughput --}}
        <div class="grid lg:grid-cols-[1fr_1.5fr] gap-5">
            {{-- Response-time bars --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Response time') }}</div>
                @php $maxMs = max(1, collect($latency)->max('ms') ?: 1); @endphp
                @forelse ($latency as $l)
                    @php $lt = $T($l['state']); @endphp
                    <div class="mb-4 last:mb-0">
                        <div class="flex items-center justify-between text-[12.5px] mb-1">
                            <span class="font-medium text-ink-900">{{ $l['label'] }}</span>
                            <span class="tabular-nums text-ink-500">{{ $l['ms'] }} ms</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-paper-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $lt['dot'] }}" style="width: {{ max(3, round(($l['ms'] / $maxMs) * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-[12.5px] text-ink-400 py-4">{{ __('No timing data.') }}</div>
                @endforelse
            </div>

            {{-- 14-day message throughput --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                @php
                    $tvals = array_values($throughput['series'] ?? []);
                    $tmax = max(1, count($tvals) ? max($tvals) : 1);
                    $tn = max(1, count($tvals));
                    $TW = 1000; $TH = 180;
                    $tpts = [];
                    foreach ($tvals as $i => $v) {
                        $x = $tn > 1 ? ($i / ($tn - 1)) * $TW : 0;
                        $y = $TH - ($v / $tmax) * ($TH - 12) - 4;
                        $tpts[] = round($x, 1) . ',' . round($y, 1);
                    }
                    $tline = implode(' ', $tpts);
                    $tarea = $tn > 1 ? "0,{$TH} " . $tline . " {$TW},{$TH}" : '';
                @endphp
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Message throughput · 14 days') }}</div>
                    <div class="text-[11px] text-ink-400">{{ __('peak') }} {{ number_format($tmax) }}/{{ __('day') }}</div>
                </div>
                @if (array_sum($tvals) > 0)
                    <svg viewBox="0 0 {{ $TW }} {{ $TH }}" preserveAspectRatio="none" class="w-full h-40 text-wa-deep">
                        <defs>
                            <linearGradient id="hbFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="currentColor" stop-opacity="0.20" />
                                <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        @if ($tarea)<polygon points="{{ $tarea }}" fill="url(#hbFill)" />@endif
                        <polyline points="{{ $tline }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    </svg>
                @else
                    <div class="h-40 grid place-items-center text-[12.5px] text-ink-400">{{ __('No message activity in the last 14 days.') }}</div>
                @endif
            </div>
        </div>

        {{-- Engine / channel health --}}
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('WhatsApp engines & connected numbers') }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse ($engines as $e)
                    @php $t = $T($e['state']); @endphp
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="flex items-center justify-between">
                            <div class="text-[13px] font-semibold text-ink-900">{{ $e['label'] }}</div>
                            <span class="w-2.5 h-2.5 rounded-full {{ $t['dot'] }}"></span>
                        </div>
                        <div class="font-serif text-[26px] text-ink-900 mt-2 leading-none">{{ $e['connected'] }}<span class="text-[14px] text-ink-400"> / {{ $e['total'] }}</span></div>
                        <div class="text-[11.5px] text-ink-500 mt-1">{{ __('connected of total numbers') }}</div>
                        @if (!empty($e['breakdown']))
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                @foreach ($e['breakdown'] as $status => $count)
                                    <span class="text-[10.5px] font-mono px-1.5 py-0.5 rounded bg-paper-100 text-ink-600">{{ $status }} {{ $count }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-[12.5px] text-ink-400 py-4">{{ __('No engines configured yet.') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Host vitals --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Host vitals') }}</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @php
                    $vit = [
                        ['PHP', $system['php'] ?? '—'],
                        ['Laravel', $system['laravel'] ?? '—'],
                        ['Environment', $system['env'] ?? '—'],
                        ['Debug', $system['debug'] ?? '—'],
                        ['Disk free', $system['disk_free'] ?? '—'],
                        ['Memory limit', $system['memory_limit'] ?? '—'],
                    ];
                @endphp
                @foreach ($vit as [$k, $v])
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.12em] text-ink-400">{{ $k }}</div>
                        <div class="text-[13.5px] font-medium text-ink-900 mt-1 break-words">{{ $v }}</div>
                    </div>
                @endforeach
            </div>
            @if (isset($system['disk_used_pct']))
                <div class="mt-4">
                    <div class="flex items-center justify-between text-[11px] text-ink-500 mb-1"><span>{{ __('Disk used') }}</span><span>{{ $system['disk_used_pct'] }}%</span></div>
                    <div class="h-2 rounded-full bg-paper-100 overflow-hidden">
                        <div class="h-full rounded-full {{ $system['disk_used_pct'] > 90 ? 'bg-red-500' : ($system['disk_used_pct'] > 75 ? 'bg-amber-500' : 'bg-wa-deep') }}" style="width: {{ $system['disk_used_pct'] }}%"></div>
                    </div>
                </div>
            @endif
        </div>

    </main>

    <script>
        (function () {
            var TONE = {
                up:   { dot: 'bg-wa-green', text: 'text-wa-deep', bg: 'bg-wa-mint', word: @json(__('Operational')) },
                warn: { dot: 'bg-amber-500',   text: 'text-amber-700',   bg: 'bg-amber-50',   word: @json(__('Degraded')) },
                down: { dot: 'bg-red-500',      text: 'text-red-700',     bg: 'bg-red-50',     word: @json(__('Down')) },
                idle: { dot: 'bg-ink-300',      text: 'text-ink-500',     bg: 'bg-paper-100',  word: @json(__('Idle')) },
            };
            var url = @json(url('/admin/health')) + '?format=json';

            function paint(data) {
                (Object.keys(data.checks || {})).forEach(function (key) {
                    var card = document.querySelector('[data-hk-card="' + key + '"]');
                    if (!card) return;
                    var c = data.checks[key], t = TONE[c.state] || TONE.idle;
                    var dot = card.querySelector('[data-hk-dot]');
                    var state = card.querySelector('[data-hk-state]');
                    var badge = card.querySelector('[data-hk-badge]');
                    var val = card.querySelector('[data-hk-value]');
                    var det = card.querySelector('[data-hk-detail]');
                    if (dot) dot.className = 'w-2 h-2 rounded-full ' + t.dot;
                    if (state) state.textContent = t.word;
                    if (badge) badge.className = 'flex items-center gap-1.5 text-[10px] font-mono uppercase tracking-wide px-2 py-0.5 rounded ' + t.bg + ' ' + t.text;
                    if (val) val.textContent = c.value;
                    if (det) det.textContent = c.detail;
                });
                var ts = document.querySelector('[data-hk-ts]');
                if (ts) ts.textContent = new Date().toLocaleTimeString();
            }
            function tick() {
                fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) { if (d) paint(d); })
                    .catch(function () {});
            }
            var btn = document.querySelector('[data-hk-refresh]');
            if (btn) btn.addEventListener('click', tick);
            setInterval(tick, 20000);
        })();
    </script>
</x-layouts.admin>
