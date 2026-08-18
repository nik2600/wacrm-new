<x-layouts.user :title="__('WhatsApp Warmer')" nav-key="more" page="user-warmer-index">

    @php
        // KPI rollups across all numbers (computed from the rows the controller passed).
        $enabledRows = $rows->filter(fn ($r) => !empty($r['cfg']['enabled']));
        $numWarming  = $enabledRows->count();
        $totalBudget = (int) $enabledRows->sum('budget');
        $sentToday   = (int) $enabledRows->sum('sent');
        $avgHealth   = $rows->count() ? (int) round($rows->avg('health')) : 0;

        // Plain-words explanation shown under each setting field.
        $hints = [
            'daily_base'   => 'How many messages this number may send on day one.',
            'step_pct'     => 'Grow that daily limit by this percent each step.',
            'step_days'    => 'How often the limit grows (every N days).',
            'max_daily'    => 'The highest the daily limit ever reaches.',
            'gap_min'      => 'Shortest wait between two messages.',
            'gap_max'      => 'Longest wait between two messages.',
            'active_start' => 'Only start sending after this hour (24-hour clock).',
            'active_end'   => 'Stop sending after this hour.',
        ];
    @endphp

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start">

            {{-- =============== LEFT RAIL =============== --}}
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">

                {{-- Identity card --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div class="flex items-start justify-between gap-2">
                        <span class="w-11 h-11 rounded-xl shrink-0 grid place-items-center bg-wa-mint text-wa-deep">
                            <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4">
                                <path d="M8 1.5c1.6 2 1 3.5 0 4.5C6.5 7.5 5.5 9 6 10.5 6.4 11.7 8 12 8 12s1.6-.3 2-1.5c.5-1.5-.5-3-2-4.5" />
                                <path d="M8 12c2.2 0 3.5-1.6 3.5-3.5M8 12c-2.2 0-3.5-1.6-3.5-3.5" />
                            </svg>
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono {{ $numWarming ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-500 border border-paper-200' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $numWarming ? 'bg-wa-green' : 'bg-paper-200' }}"></span>
                            {{ $numWarming }} {{ __('warming') }}
                        </span>
                    </div>
                    <div class="font-serif text-[18px] leading-tight mt-3">{{ __('WhatsApp Warmer') }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">{{ __('Number warm-up') }}</div>
                </div>

                {{-- How it works --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ __('How it works') }}</div>
                    <ol class="px-1 space-y-0.5">
                        @foreach ([
                            __('Turn warming on for a number.'),
                            __('Each day it may send a little more than the day before.'),
                            __('Messages go out slowly, only during the hours you choose.'),
                            __('Campaigns, broadcasts and scheduled sends all respect the limit.'),
                        ] as $i => $step)
                            <li class="flex items-start gap-2 px-3 py-2 rounded-lg text-[12.5px] text-ink-700">
                                <span class="w-5 h-5 rounded-full grid place-items-center text-[10px] font-mono bg-paper-100 text-ink-600 shrink-0">{{ $i + 1 }}</span>
                                <span>{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- Honest help card --}}
                <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>{{ __('Good to know') }}
                    </div>
                    {{ __('Warming lowers the risk of a ban on Unofficial API numbers — it is not a guarantee. For high volume, use an official WhatsApp Business (Cloud API) number, which needs no warming.') }}
                </div>
            </aside>

            {{-- =============== MAIN =============== --}}
            <section class="space-y-5">

                {{-- Header --}}
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ url('/more') }}" class="hover:text-wa-deep">{{ __('More') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span>{{ __('Warmer') }}</span>
                        </div>
                        <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                            {{ __('Warm up your') }} <span class="italic text-wa-deep">{{ __('numbers') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Ramp each Unofficial API number\'s daily send limit with human-like gaps and active hours to reduce ban risk. Each number warms up on its own schedule.') }}
                        </p>
                    </div>
                </div>

                @if (session('warmer_status'))
                    <div class="rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[12.5px] text-wa-deep">
                        {{ session('warmer_status') }}
                    </div>
                @endif

                {{-- KPI strip --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.12em] text-ink-500">{{ __('Numbers warming') }}</div>
                        <div class="text-[26px] font-serif text-wa-deep leading-tight mt-1">{{ $numWarming }}</div>
                        <div class="text-[10.5px] text-ink-500">{{ __('of') }} {{ $rows->count() }} {{ __('connected') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.12em] text-ink-500">{{ __("Today's budget") }}</div>
                        <div class="text-[26px] font-serif leading-tight mt-1">{{ number_format($totalBudget) }}</div>
                        <div class="text-[10.5px] text-ink-500">{{ __('messages allowed today') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.12em] text-ink-500">{{ __('Sent today') }}</div>
                        <div class="text-[26px] font-serif leading-tight mt-1">{{ number_format($sentToday) }}</div>
                        <div class="text-[10.5px] text-ink-500">{{ __('counted toward warm-up') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.12em] text-ink-500">{{ __('Avg health') }}</div>
                        <div class="text-[26px] font-serif leading-tight mt-1 {{ $avgHealth >= 70 ? 'text-wa-deep' : ($avgHealth >= 40 ? 'text-[#7B5A14]' : 'text-accent-coral') }}">{{ $avgHealth }}</div>
                        <div class="text-[10.5px] text-ink-500">{{ __('across all numbers') }}</div>
                    </div>
                </div>

                {{-- Per-number cards --}}
                @forelse ($rows as $r)
                    @php
                        $d = $r['device']; $cfg = $r['cfg']; $health = $r['health'];
                        $hClass = $health >= 70 ? 'bg-wa-mint text-wa-deep' : ($health >= 40 ? 'bg-accent-amber/20 text-[#7B5A14]' : 'bg-accent-coral/15 text-accent-coral');
                        $on = (bool) ($cfg['enabled'] ?? false);
                        // Which engine this number belongs to — so a multi-engine
                        // workspace can tell its Unofficial / WABA / Twilio numbers apart.
                        $engBadge = match ($r['engine'] ?? 'baileys') {
                            'waba'   => [__('WABA'),   'bg-wa-mint/40 text-wa-deep border border-wa-deep/20'],
                            'twilio' => [__('Twilio'), 'bg-accent-coral/10 text-accent-coral border border-accent-coral/30'],
                            default  => [__('Unofficial API'), 'bg-ink-900/5 text-ink-700 border border-ink-200'],
                        };
                    @endphp
                    <form method="POST" action="{{ route('user.warmer.update', $r['key']) }}"
                        class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        @csrf

                        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-paper-200">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-serif text-[17px] truncate">{{ $d->device_name ?: __('Number') }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono {{ $engBadge[1] }}">{{ $engBadge[0] }}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono {{ $hClass }}" title="{{ __('Health score (higher = safer to ramp)') }}">
                                        <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 9h3l1.5-4 3 8 1.5-4H14"/></svg>
                                        {{ __('Health') }} {{ $health }}
                                    </span>
                                </div>
                                <div class="text-[11.5px] font-mono text-ink-500 mt-0.5">{{ $r['phone'] ?: '—' }} · {{ ucfirst((string) $d->status) }}</div>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer shrink-0">
                                <span class="text-[11.5px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Warming') }}</span>
                                <input type="checkbox" name="enabled" value="1" {{ $on ? 'checked' : '' }} class="w-4 h-4 accent-wa-deep">
                            </label>
                        </div>

                        <div class="px-5 py-4">
                            {{-- live status strip --}}
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4 text-center">
                                <div class="rounded-lg bg-paper-50 border border-paper-200 px-2 py-2">
                                    <div class="text-[10px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __("Today's budget") }}</div>
                                    <div class="text-[18px] font-semibold text-wa-deep">{{ $r['budget'] }}</div>
                                </div>
                                <div class="rounded-lg bg-paper-50 border border-paper-200 px-2 py-2">
                                    <div class="text-[10px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Remaining today') }}</div>
                                    <div class="text-[18px] font-semibold">{{ $r['remaining'] }}</div>
                                </div>
                                <div class="rounded-lg bg-paper-50 border border-paper-200 px-2 py-2">
                                    <div class="text-[10px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Sent today') }}</div>
                                    <div class="text-[18px] font-semibold">{{ (int) ($r['sent'] ?? 0) }}</div>
                                </div>
                            </div>

                            @php
                                $fields = [
                                    ['daily_base',   __('Start budget/day'), $cfg['daily_base'], 1, 5000],
                                    ['step_pct',     __('Ramp +% / step'),   $cfg['step_pct'], 0, 200],
                                    ['step_days',    __('Ramp every (days)'),$cfg['step_days'], 1, 60],
                                    ['max_daily',    __('Max budget/day'),   $cfg['max_daily'], 1, 100000],
                                    ['gap_min',      __('Min gap (sec)'),    $cfg['gap_min'], 0, 3600],
                                    ['gap_max',      __('Max gap (sec)'),    $cfg['gap_max'], 0, 3600],
                                    ['active_start', __('Active from (hr)'), $cfg['active_start'], 0, 23],
                                    ['active_end',   __('Active to (hr)'),   $cfg['active_end'], 0, 24],
                                ];
                            @endphp
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                @foreach ($fields as [$name, $label, $val, $min, $max])
                                    <label class="block">
                                        <span class="block text-[10.5px] font-mono uppercase tracking-[0.1em] text-ink-500 mb-1">{{ $label }}</span>
                                        <input type="number" name="{{ $name }}" value="{{ $val }}" min="{{ $min }}" max="{{ $max }}" required
                                            class="w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                        <span class="block mt-1 text-[10px] text-ink-400 leading-snug">{{ $hints[$name] ?? '' }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                                <label class="inline-flex items-start gap-2 cursor-pointer max-w-md">
                                    <input type="checkbox" name="spintax" value="1" {{ !empty($cfg['spintax']) ? 'checked' : '' }} class="mt-0.5 w-4 h-4 accent-wa-deep">
                                    <span>
                                        <span class="block text-[12px] font-semibold">{{ __('Spintax variety') }}</span>
                                        <span class="block text-[10px] text-ink-400 leading-snug">{{ __('Send slightly different wording to each person so messages don\'t look identical. Write choices like {Hi|Hello|Hey} there.') }}</span>
                                    </span>
                                </label>
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal shrink-0">{{ __('Save warm-up') }}</button>
                            </div>
                        </div>
                    </form>
                @empty
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-8 text-center shadow-card">
                        <p class="text-[13px] text-ink-600">{{ __('No connected numbers yet.') }}</p>
                        <a href="{{ url('/devices') }}" class="inline-block mt-3 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Connect a number') }}</a>
                    </div>
                @endforelse

            </section>
        </div>
    </main>
</x-layouts.user>
