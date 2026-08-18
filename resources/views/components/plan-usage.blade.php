@props([
    'workspace' => null,
    'detailed' => false,
])

@php
    $ws = $workspace ?: auth()->user()?->currentWorkspace;
    $u = $ws ? \App\Services\PlanUsage::summary($ws) : null;
@endphp

@if ($u)
    <div class="space-y-5">

        {{-- ============ HERO PLAN CARD ============ --}}
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-wa-deep to-wa-teal text-paper-0 shadow-card">
            <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-paper-0/10"></div>
            <div class="absolute -right-16 top-20 w-56 h-56 rounded-full bg-paper-0/5"></div>
            <div class="relative p-6 sm:p-7">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.2em] text-paper-0/70">
                            {{ __('Current plan') }}</div>
                        <div class="flex items-center gap-2.5 mt-1.5">
                            <h2 class="font-serif text-[34px] leading-none">{{ $u['plan_name'] }}</h2>
                            <span
                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-paper-0/15 border border-paper-0/25">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('active') }}
                            </span>
                        </div>
                        <div class="text-[12.5px] text-paper-0/75 mt-1.5">
                            {{ __('Resets :date · :days days left in this cycle', ['date' => $u['cycle_reset'], 'days' => $u['days_left']]) }}
                        </div>
                        {{-- Plan VALIDITY — the subscription's own expiry, separate from the
                             monthly message cycle above. For a yearly plan this reads a year
                             out, so a customer never mistakes "days left in this cycle" (the
                             monthly quota reset) for their plan ending soon. --}}
                        @if (!empty($u['valid_until']))
                            <div class="inline-flex items-center gap-1.5 mt-2.5 px-2.5 py-1 rounded-full bg-paper-0/12 border border-paper-0/25 text-[11.5px] font-medium">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none"
                                    stroke="currentColor" stroke-width="1.5">
                                    <rect x="2.5" y="3.5" width="11" height="10" rx="1.5" />
                                    <path d="M2.5 6.5h11M5.5 2v3M10.5 2v3" />
                                </svg>
                                {{ __('Plan valid until :date', ['date' => $u['valid_until']]) }}@if (!empty($u['valid_until_days'])) <span class="text-paper-0/55">· {{ $u['valid_until_days'] }} {{ __('days left') }}</span>@endif
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ url('/account?tab=wallet') }}"
                            class="px-3.5 py-2 rounded-full bg-paper-0/10 hover:bg-paper-0/20 border border-paper-0/25 text-[12px] font-semibold transition">{{ __('Top up wallet') }}</a>
                        <a href="{{ url('/account/plans') }}"
                            class="px-3.5 py-2 rounded-full bg-paper-0 text-wa-deep hover:bg-paper-50 text-[12px] font-semibold transition">{{ __('Upgrade plan') }}</a>
                    </div>
                </div>

                {{-- Big monthly-usage meter --}}
                <div class="mt-6">
                    <div class="flex items-end justify-between gap-3 mb-2">
                        <div class="text-[12.5px] text-paper-0/80">{{ __('Messages used') }} <span
                                class="text-paper-0/50">· {{ $u['month_label'] }}</span></div>
                        <div class="font-mono text-[12.5px]">
                            <span class="text-[16px] font-semibold">{{ number_format($u['messages_used']) }}</span>
                            @if ($u['messages_unlimited'])
                                <span class="text-paper-0/60">/ {{ __('Unlimited') }}</span>
                            @else
                                <span class="text-paper-0/60">/ {{ number_format($u['messages_limit']) }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="h-2.5 rounded-full bg-paper-0/15 overflow-hidden">
                        @php $heroBar = $u['messages_pct'] >= 90 ? 'bg-accent-coral' : 'bg-paper-0'; @endphp
                        <div class="h-full {{ $u['messages_unlimited'] ? 'bg-paper-0/50' : $heroBar }} rounded-full transition-all"
                            style="width: {{ $u['messages_unlimited'] ? 8 : max(3, $u['messages_pct']) }}%"></div>
                    </div>
                    <div class="text-[11.5px] text-paper-0/70 mt-1.5">
                        @if ($u['messages_unlimited'])
                            {{ __('Your plan has no monthly message cap.') }}
                        @else
                            {{ __(':n messages remaining', ['n' => number_format($u['messages_remaining'])]) }}
                            @if ($u['messages_remaining'] === 0)
                                · {{ __('extra sends bill from wallet credits') }}
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ STAT TILES ============ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @php
                $tiles = [
                    [
                        'label' => __('Wallet credits'),
                        'value' => number_format($u['credits']),
                        'href' => url('/account?tab=wallet'),
                    ],
                    [
                        'label' => __('Features unlocked'),
                        'value' => $u['unlocked_count'] . ' / ' . $u['feature_total'],
                        'href' => url('/account/plans'),
                    ],
                    ['label' => __('Sent this month'), 'value' => number_format($u['messages_used']), 'href' => null],
                    ['label' => __('Days left in cycle'), 'value' => $u['days_left'], 'href' => null],
                ];
            @endphp
            @foreach ($tiles as $t)
                <{{ $t['href'] ? 'a' : 'div' }} @if ($t['href']) href="{{ $t['href'] }}" @endif
                    class="rounded-2xl bg-paper-0 border border-paper-200 shadow-card p-4 {{ $t['href'] ? 'hover:border-wa-deep transition' : '' }}">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ $t['label'] }}
                    </div>
                    <div class="font-serif text-[26px] leading-none mt-1.5 tabular-nums">{{ $t['value'] }}</div>
                    </{{ $t['href'] ? 'a' : 'div' }}>
            @endforeach
        </div>

        @if ($detailed)
            {{-- ============ USAGE & LIMITS ============ --}}
            <div class="rounded-2xl bg-paper-0 border border-paper-200 shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Usage & limits') }}</div>
                    <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('This billing cycle') }}</h3>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    @foreach ($u['meters'] as $m)
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1.5">
                                <span class="font-medium text-ink-800">{{ __($m['label']) }}</span>
                                <span class="font-mono text-[11.5px] text-ink-600">{{ number_format($m['used']) }}
                                    <span class="text-ink-400">/
                                        {{ $m['unlimited'] ? '∞' : number_format($m['limit']) }}</span></span>
                            </div>
                            <div class="h-2 rounded-full bg-paper-100 overflow-hidden">
                                <div class="h-full {{ $m['unlimited'] ? 'bg-wa-green/40' : ($m['pct'] >= 90 ? 'bg-accent-coral' : 'bg-wa-deep') }} rounded-full"
                                    style="width: {{ $m['unlimited'] ? 8 : max(3, $m['pct']) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============ FEATURES (all, with included / locked state) ============ --}}
        <div class="rounded-2xl bg-paper-0 border border-paper-200 shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Features') }}
                    </div>
                    <h3 class="font-serif text-[20px] leading-tight mt-0.5">
                        {{ __('What :plan includes', ['plan' => $u['plan_name']]) }}</h3>
                </div>
                <span
                    class="shrink-0 text-[11.5px] font-mono px-2.5 py-1 rounded-full bg-wa-mint text-wa-deep border border-wa-green/30">{{ $u['unlocked_count'] }}/{{ $u['feature_total'] }}
                    {{ __('on') }}</span>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach ($u['unlocked'] as $label)
                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-wa-mint/40 border border-wa-green/25">
                        <span class="w-5 h-5 rounded-full bg-wa-green/20 text-wa-deep grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2.4">
                                <path d="m3.5 8.5 3 3 6-7" />
                            </svg>
                        </span>
                        <span class="text-[12.5px] text-ink-800 truncate">{{ __($label) }}</span>
                    </div>
                @endforeach
                @foreach ($u['locked'] as $label)
                    <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-paper-50 border border-paper-200">
                        <span class="w-5 h-5 rounded-full bg-paper-100 text-ink-400 grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <rect x="3.5" y="7" width="9" height="6" rx="1.2" />
                                <path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" />
                            </svg>
                        </span>
                        <span class="text-[12.5px] text-ink-400 truncate">{{ __($label) }}</span>
                    </div>
                @endforeach
            </div>
            @if (count($u['locked']))
                <div
                    class="px-5 py-4 border-t border-paper-200 bg-paper-50/50 flex items-center justify-between gap-3 flex-wrap">
                    <span
                        class="text-[12px] text-ink-600">{{ __(':n more features unlock on a higher plan.', ['n' => count($u['locked'])]) }}</span>
                    <a href="{{ url('/account/plans') }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal transition">
                        {{ __('Compare plans') }}
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </a>
                </div>
            @endif
        </div>

    </div>
@else
    {{-- No active workspace (or plan summary unavailable) — never leave the
 tab blank. Give a clear call to action instead of an empty panel. --}}
    <div class="rounded-3xl bg-gradient-to-br from-wa-deep to-wa-teal text-paper-0 shadow-card p-7 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-paper-0/15 grid place-items-center">
            <svg viewBox="0 0 16 16" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 8l6-5 6 5M3.5 7v6h9V7" />
                <path d="M6.5 13V9.5h3V13" />
            </svg>
        </div>
        <h2 class="font-serif text-[26px] mt-4">{{ __('No active plan yet') }}</h2>
        <p class="text-[13px] text-paper-0/80 mt-1.5 max-w-md mx-auto">
            {{ __('Pick a plan to unlock messaging limits, automations, and team features. Your usage meters appear here once a workspace is active.') }}
        </p>
        <a href="{{ url('/account/plans') }}"
            class="inline-flex items-center gap-1.5 mt-5 px-5 py-2.5 rounded-full bg-paper-0 text-wa-deep text-[13px] font-semibold hover:bg-paper-50 transition">
            {{ __('View plans') }}
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </a>
    </div>
@endif
