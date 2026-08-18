{{--
 /admin/ai-dashboard — AI usage / token-burn console.
 Reads the ai_token_usage ledger: provider mix, daily burn, model mix, top
 workspaces, platform-key vs BYOK split, estimated cost, and live key health.
 Charts are inline SVG (server-rendered) — no JS chart lib / Vite dependency.
--}}
<x-layouts.admin :title="__('AI Dashboard')" admin-key="ai-dashboard">

    @php
        $fmt = function ($n) {
            $n = (int) $n;
            if ($n >= 1000000000) return number_format($n / 1000000000, 2) . 'B';
            if ($n >= 1000000) return number_format($n / 1000000, 2) . 'M';
            if ($n >= 1000) return number_format($n / 1000, 1) . 'k';
            return number_format($n);
        };
        $windows = ['7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days'), '1y' => __('1 year')];
        $providerColor = [
            'openai' => '#10A37F', 'anthropic' => '#D97757', 'gemini' => '#4285F4',
            'google' => '#4285F4', 'deepseek' => '#4D6BFE', 'grok' => '#111827', 'mistral' => '#FF7000',
        ];
        $pc = fn($p) => $providerColor[strtolower((string) $p)] ?? 'var(--color-wa-deep)';
        $maxProv = collect($byProvider)->max('tokens') ?: 1;
        $splitTotal = max(1, ($sourceSplit['admin'] ?? 0) + ($sourceSplit['workspace'] ?? 0));
        $adminPct = round((($sourceSplit['admin'] ?? 0) / $splitTotal) * 100);
        $byokPct = 100 - $adminPct;
        // daily sparkline geometry
        $vals = array_values($daily);
        $maxDay = max(1, count($vals) ? max($vals) : 1);
        $n = max(1, count($vals));
        $W = 1000; $H = 200;
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $n > 1 ? ($i / ($n - 1)) * $W : 0;
            $y = $H - ($v / $maxDay) * ($H - 12) - 4;
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        $line = implode(' ', $pts);
        $area = $n > 1 ? "0,{$H} " . $line . " {$W},{$H}" : '';
    @endphp

    {{-- Header --}}
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('AI Dashboard') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-5 space-y-5">

        {{-- Hero + window switch --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-serif text-[26px] leading-tight">{{ __('AI usage & token burn') }}</h1>
                <p class="text-[12.5px] text-ink-500 mt-1 max-w-2xl">{{ __('Every AI call across the platform — which provider, which model, which workspace, on platform keys vs their own (BYOK) keys, with an estimated spend.') }}</p>
            </div>
            <div class="flex items-center gap-1 bg-paper-0 border border-paper-200 rounded-xl p-1 shadow-card">
                @foreach ($windows as $key => $label)
                    <a href="{{ url('/admin/ai-dashboard') }}?window={{ $key }}"
                        class="px-3 py-1.5 rounded-lg text-[12px] font-medium {{ $window === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        {{-- KPI strip --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $cards = [
                    ['label' => __('Tokens burned'), 'value' => $fmt($kpis['tokens']), 'sub' => __('this window'), 'accent' => 'text-wa-deep'],
                    ['label' => __('Estimated cost'), 'value' => \App\Support\FormatSettings::display($kpis['cost'], 'USD'), 'sub' => __('blended est.'), 'accent' => 'text-ink-900'],
                    ['label' => __('AI calls'), 'value' => $fmt($kpis['calls']), 'sub' => __('requests logged'), 'accent' => 'text-ink-900'],
                    ['label' => __('Workspaces using AI'), 'value' => $fmt($kpis['workspaces']), 'sub' => __('active'), 'accent' => 'text-ink-900'],
                ];
            @endphp
            @foreach ($cards as $c)
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ $c['label'] }}</div>
                    <div class="font-serif text-[28px] leading-none mt-2 {{ $c['accent'] }}">{{ $c['value'] }}</div>
                    <div class="text-[11px] text-ink-400 mt-1.5">{{ $c['sub'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Daily trend --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Daily token burn') }}</div>
                <div class="text-[11px] text-ink-400">{{ __('peak') }} {{ $fmt($maxDay) }}/{{ __('day') }}</div>
            </div>
            @if (array_sum($vals) > 0)
                <svg viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="none" class="w-full h-44 text-wa-deep">
                    <defs>
                        <linearGradient id="aiFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="currentColor" stop-opacity="0.22" />
                            <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    @if ($area)<polygon points="{{ $area }}" fill="url(#aiFill)" />@endif
                    <polyline points="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </svg>
            @else
                <div class="h-44 grid place-items-center text-[12.5px] text-ink-400">{{ __('No AI calls recorded in this window yet.') }}</div>
            @endif
        </div>

        <div class="grid lg:grid-cols-[1.4fr_1fr] gap-5">
            {{-- By provider --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Tokens by provider') }}</div>
                @forelse ($byProvider as $p)
                    <div class="mb-3.5 last:mb-0">
                        <div class="flex items-center justify-between text-[12.5px] mb-1">
                            <span class="font-medium text-ink-900 capitalize flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $pc($p['provider']) }}"></span>
                                {{ $p['provider'] }}
                            </span>
                            <span class="text-ink-500 tabular-nums">{{ $fmt($p['tokens']) }} · {{ $fmt($p['calls']) }} {{ __('calls') }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-paper-100 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ max(2, round(($p['tokens'] / $maxProv) * 100)) }}%; background: {{ $pc($p['provider']) }}"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-[12.5px] text-ink-400 py-6 text-center">{{ __('No usage yet.') }}</div>
                @endforelse
            </div>

            {{-- Platform vs BYOK --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-4">{{ __('Who pays — platform vs BYOK') }}</div>
                <div class="flex h-4 rounded-full overflow-hidden bg-paper-100 mb-4">
                    <div class="h-full bg-wa-deep" style="width: {{ $adminPct }}%"></div>
                    <div class="h-full bg-[#D97757]" style="width: {{ $byokPct }}%"></div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[12.5px] text-ink-700 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-wa-deep"></span>{{ __('Platform keys') }}</span>
                        <span class="text-[12.5px] tabular-nums text-ink-900 font-medium">{{ $fmt($sourceSplit['admin']) }} <span class="text-ink-400">({{ $adminPct }}%)</span></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[12.5px] text-ink-700 flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm bg-[#D97757]"></span>{{ __('Own keys (BYOK)') }}</span>
                        <span class="text-[12.5px] tabular-nums text-ink-900 font-medium">{{ $fmt($sourceSplit['workspace']) }} <span class="text-ink-400">({{ $byokPct }}%)</span></span>
                    </div>
                    <div class="pt-3 mt-1 border-t border-paper-200 text-[11.5px] text-ink-500 leading-relaxed">
                        {{ __('Only platform-key tokens count against a plan\'s monthly AI cap. BYOK workspaces spend on their own provider account.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            {{-- Top workspaces --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Top token-burning workspaces') }}</div>
                <div class="divide-y divide-paper-100">
                    @forelse ($topWorkspaces as $w)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <a href="{{ url('/admin/workspaces/' . $w['workspace_id']) }}" class="text-[13px] font-medium text-ink-900 hover:text-wa-deep truncate block">{{ $w['name'] }}</a>
                                <div class="text-[11px] text-ink-400 mt-0.5">{{ $fmt($w['calls']) }} {{ __('calls') }}@if ($w['byok'] > 0) · <span class="text-[#D97757]">{{ $fmt($w['byok']) }} {{ __('BYOK') }}</span>@endif</div>
                            </div>
                            <div class="text-[14px] font-serif text-wa-deep tabular-nums shrink-0 ml-3">{{ $fmt($w['tokens']) }}</div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-[12.5px] text-ink-400">{{ __('No workspace AI usage yet.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- By model --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Top models') }}</div>
                <div class="divide-y divide-paper-100">
                    @forelse ($byModel as $m)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <div class="text-[13px] font-medium text-ink-900 truncate flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full shrink-0" style="background: {{ $pc($m['provider']) }}"></span>{{ $m['model'] }}
                                </div>
                                <div class="text-[11px] text-ink-400 mt-0.5 capitalize">{{ $m['provider'] }} · {{ $fmt($m['calls']) }} {{ __('calls') }}</div>
                            </div>
                            <div class="text-[13.5px] font-serif text-ink-900 tabular-nums shrink-0 ml-3">{{ $fmt($m['tokens']) }}</div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-[12.5px] text-ink-400">{{ __('No model usage yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- AI key health --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('AI key health') }}</div>
                <a href="{{ url('/admin/api-keys') }}" class="text-[11.5px] text-wa-deep font-semibold hover:underline">{{ __('Manage keys') }}</a>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @forelse ($keys['platform'] as $k)
                    <div class="flex items-center gap-3 border border-paper-200 rounded-xl px-3.5 py-3">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $k['active'] ? 'bg-wa-green' : 'bg-ink-300' }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-medium text-ink-900 capitalize">{{ $k['provider'] }}</div>
                            <div class="text-[11px] text-ink-400 truncate">{{ $k['model'] ?: __('no default model') }}</div>
                        </div>
                        <span class="text-[10px] font-mono uppercase tracking-wide px-2 py-0.5 rounded {{ $k['active'] ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">{{ $k['active'] ? __('Live') : __('Off') }}</span>
                    </div>
                @empty
                    <div class="text-[12.5px] text-ink-400 col-span-full py-2">{{ __('No platform AI keys configured.') }}</div>
                @endforelse
            </div>
            <div class="mt-4 pt-4 border-t border-paper-200 flex flex-wrap items-center gap-x-8 gap-y-2 text-[12.5px]">
                <div><span class="text-ink-500">{{ __('BYOK keys active') }}:</span> <span class="font-semibold text-ink-900">{{ $keys['byok']['count'] }}</span></div>
                <div><span class="text-ink-500">{{ __('Workspaces on BYOK') }}:</span> <span class="font-semibold text-ink-900">{{ $keys['byok']['workspaces'] }}</span></div>
                @if ($voice['calls'] > 0)<div><span class="text-ink-500">{{ __('AI voice calls') }}:</span> <span class="font-semibold text-ink-900">{{ $fmt($voice['calls']) }}</span></div>@endif
            </div>
        </div>

    </main>
</x-layouts.admin>
