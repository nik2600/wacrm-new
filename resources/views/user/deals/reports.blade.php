<x-layouts.user :title="__('Pipeline Reports')" nav-key="deals" page="user-deals-reports">

{{-- Sales Pipeline analytics — styled to match /analytics. Charts render from
     the JSON in #dl-report-data (user-deals-reports.js). --}}

@php
    $openDeals = (int) $byStage->sum('count');
    $closed    = $won + $lost;
@endphp

{{-- ========== HERO BAND ========== --}}
<section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-7 pb-4">
    <div class="flex items-end justify-between flex-wrap gap-3">
        <div>
            <div class="flex items-center gap-3 mb-2 text-[11px] font-mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Pipeline · Workspace') }}</span>
                <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>{{ __('Live') }}</span>
            </div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[38px] lg:text-[44px] xl:text-[52px] leading-[1.02]">
                <span class="italic text-wa-deep">{{ $winRate }}%</span> {{ __('of your closed deals were won.') }}
            </h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                @if($openDeals || $closed)
                    <b class="tabular-nums">{{ $openDeals }}</b> {{ __('open deals') }} ·
                    <b class="tabular-nums">{{ $openValue }}</b> {{ __('in play') }} ·
                    <b class="tabular-nums">{{ $forecast }}</b> {{ __('weighted forecast') }}.
                @else
                    {{ __('No deals yet — create a few on the board and this dashboard fills in.') }}
                @endif
            </p>
        </div>
        <a href="{{ route('user.deals.index') }}"
           class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 3 6 8l4 5"/></svg>
            {{ __('Back to board') }}
        </a>
    </div>
</section>

{{-- Chart data bridge --}}
<script type="application/json" id="dl-report-data">@json($report)</script>

{{-- ========== HERO KPI ROW ========== --}}
<section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

        {{-- Hero: dark deep-teal card --}}
        <div class="lg:col-span-5 bg-wa-deep text-paper-0 rounded-2xl p-5 shadow-soft relative overflow-hidden">
            <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.10)_1px,transparent_0)] bg-[length:14px_14px] opacity-30"></div>
            <div class="absolute -right-12 -top-12 w-56 h-56 rounded-full bg-[radial-gradient(circle,rgba(37,211,102,0.4)_0%,transparent_60%)]"></div>
            <div class="relative">
                <div class="flex items-start justify-between">
                    <span class="font-mono text-[10px] uppercase tracking-widest text-paper-0/60">{{ __('Open pipeline value') }}</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-green/20 text-wa-green border border-wa-green/40">{{ $winRate }}% {{ __('win rate') }}</span>
                </div>
                <div class="mt-4 flex items-end gap-3">
                    <span class="font-serif font-normal tracking-[-0.01em] text-[44px] sm:text-[54px] lg:text-[60px] leading-[0.9] tabular-nums">{{ $openValue }}</span>
                </div>
                <div class="mt-4 border-t border-paper-0/15 pt-3 grid grid-cols-3 gap-3 text-[11px] font-mono text-paper-0/70">
                    <div><span class="block text-paper-0/50">{{ __('open deals') }}</span><span class="font-serif text-[18px] tabular-nums text-paper-0">{{ $openDeals }}</span></div>
                    <div><span class="block text-paper-0/50">{{ __('forecast') }}</span><span class="font-serif text-[18px] tabular-nums text-paper-0">{{ $forecast }}</span></div>
                    <div><span class="block text-paper-0/50">{{ __('won (mo)') }}</span><span class="font-serif text-[18px] tabular-nums text-wa-green">{{ $wonValue }}</span></div>
                </div>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="lg:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-3">
            @php
                $stat = function ($label, $value, $sub, $bar, $iconBg, $svg) {
                    return ['label' => $label, 'value' => $value, 'sub' => $sub, 'bar' => $bar, 'iconBg' => $iconBg, 'svg' => $svg];
                };
                $cards = [
                    $stat(__('Weighted forecast'), $forecast, __('by probability'), 'before:bg-wa-teal', '#DFF1ED', '<path d="M2 8l8-3.5-3 8z"/>'),
                    $stat(__('Won · this month'), $wonValue, $won . ' ' . __('all-time won'), 'before:bg-wa-green', 'color-mix(in srgb, var(--color-wa-green) 18%, transparent)', '<path d="M2 6l3 3 5-6" fill="none" stroke="currentColor" stroke-width="2"/>'),
                    $stat(__('Win rate'), $winRate . '%', $won . '/' . $closed . ' ' . __('closed'), 'before:bg-[#7B61FF]', 'rgba(123,97,255,0.18)', '<circle cx="8" cy="8" r="5" fill="none" stroke="#7B61FF" stroke-width="1.6"/><path d="M8 5v3l2 2" fill="none" stroke="#7B61FF" stroke-width="1.6"/>'),
                    $stat(__('Open deals'), $openDeals, __('in pipeline'), 'before:bg-[#13478A]', '#D9E5F2', '<rect x="2" y="3" width="12" height="9" rx="1.5" fill="none" stroke="#13478A" stroke-width="1.6"/>'),
                    $stat(__('Lost'), (string) $lost, __('closed lost'), 'before:bg-accent-coral', 'rgba(232,122,93,0.18)', '<path d="M5 5l6 6M11 5l-6 6" fill="none" stroke="#A1431F" stroke-width="1.8"/>'),
                    $stat(__('Open value'), $openValue, __('total in play'), 'before:bg-accent-amber', 'rgba(229,160,78,0.22)', '<circle cx="8" cy="8" r="5" fill="none" stroke="#7B5A14" stroke-width="1.6"/><path d="M8 5v3l2 2" fill="none" stroke="#7B5A14" stroke-width="1.6"/>'),
                ];
            @endphp
            @foreach($cards as $c)
                <div class="bg-white border border-paper-200 rounded-[14px] px-[18px] py-4 relative overflow-hidden before:content-[''] before:absolute before:left-0 before:top-0 before:bottom-0 before:w-[3px] {{ $c['bar'] }}">
                    <div class="flex items-center justify-between">
                        <div class="text-[11px] text-ink-600 font-medium flex items-center gap-1.5">
                            <span class="w-7 h-7 rounded-lg inline-flex items-center justify-center" style="background: {{ $c['iconBg'] }}">
                                <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">{!! $c['svg'] !!}</svg>
                            </span>{{ $c['label'] }}
                        </div>
                    </div>
                    <div class="font-serif text-[32px] leading-none tracking-[-0.02em] mt-1.5 tabular-nums text-ink-900">{{ $c['value'] }}</div>
                    <div class="text-[11px] text-ink-500 mt-1 font-mono">{{ $c['sub'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========== CHARTS ROW ========== --}}
<section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-3">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">

        <div class="lg:col-span-7 bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Distribution') }}</div>
            <h3 class="font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">{{ __('Pipeline value by stage') }}</h3>
            <div id="dl-chart-stage" class="mt-2" style="min-height:300px"></div>
        </div>

        <div class="lg:col-span-5 bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Momentum') }}</div>
                    <h3 class="font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">{{ __('Won vs lost') }}</h3>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 border border-paper-200 font-mono">{{ __('6 mo') }}</span>
            </div>
            <div id="dl-chart-trend" class="mt-2" style="min-height:300px"></div>
        </div>
    </div>
</section>

{{-- ========== LEADERBOARD ========== --}}
<section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-8">
    <div class="bg-white border border-paper-200 rounded-[18px] px-5 py-[18px] shadow-card">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Most won value') }}</div>
                <h3 class="font-serif font-normal tracking-[-0.01em] text-[24px] leading-tight">{{ __('Top performers') }}</h3>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ $leaders->count() }} {{ __('agents') }}</span>
        </div>
        @php
            $grads = ['from-wa-teal to-wa-deep', 'from-accent-coral to-[#A1431F]', 'from-accent-amber to-[#7B5A14]', 'from-[#7B61FF] to-[#5B3D8A]', 'from-[#13478A] to-[#0B1F1C]'];
        @endphp
        @forelse($leaders as $i => $l)
            @php $ini = strtoupper(substr(preg_replace('/[^a-z]/i', '', (string) $l['name']) ?: 'AG', 0, 2)); @endphp
            <div class="flex items-center gap-3 py-2.5 {{ $loop->last ? '' : 'border-b border-paper-200' }}">
                <span class="w-9 h-9 rounded-full bg-gradient-to-br {{ $grads[$i % count($grads)] }} text-paper-0 text-[11px] font-semibold flex items-center justify-center">{{ $ini }}</span>
                <div class="flex-1 min-w-0">
                    <div class="text-[12.5px] font-medium truncate text-ink-900">{{ $l['name'] }}</div>
                    <div class="text-[10px] text-ink-500 font-mono">{{ $l['won'] }} {{ __('won') }}</div>
                </div>
                <div class="text-right">
                    <div class="font-serif text-[18px] tabular-nums text-wa-deep">{{ $symbol }}{{ number_format($l['value'], 0) }}</div>
                    <div class="text-[9px] font-mono text-ink-500">{{ __('won value') }}</div>
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-[12px] text-ink-500">{{ __('No won deals yet — close a few and they show up here.') }}</div>
        @endforelse
    </div>
</section>

</x-layouts.user>
