@php
    /** @var \App\Models\KeywordReply|null $row */
    $row = $row ?? null;
    $analytics = $analytics ?? [
        'recent' => collect(),
        'topUsers' => collect(),
        'variantStats' => collect(),
        'hourBuckets' => array_fill(0, 24, 0),
        'dayBuckets' => collect(),
        'fired7d' => 0,
        'fired30d' => 0,
        'uniqueUsers' => 0,
        'funnel' => [],
        'latencyAvgMs' => null,
        'latencyP95Ms' => null,
    ];

    // Mask phone numbers for display — first 4 + last 3, hide middle digits
    // so the operator can identify the contact without leaking full PII
    // into screenshots/exports.
    $maskPhone = function (?string $p): string {
        $p = (string) $p;
        if (mb_strlen($p) < 8) {
            return $p ?: '—';
        }
        return mb_substr($p, 0, 4) . '••••' . mb_substr($p, -3);
    };
    $phoneInitials = function (?string $p): string {
        $p = (string) $p;
        return mb_strtoupper(mb_substr(preg_replace('/[^0-9]/', '', $p), -2));
    };

    $keyword = $row?->keyword ?: '—';
    $tag = mb_strtoupper(mb_substr($keyword, 0, 2));
    $matchSub = $row
        ? match ($row->matching_method) {
            'fuzzy' => 'fuzzy / ' . ($row->fuzzy_similarity ?: 80) . '%',
            'contains' => 'contains',
            default => 'exact',
        }
        : '—';
    $deviceLabel = optional($row?->device)->phone_number ?: '—';
    $statusActive = $row && $row->status;
    $totalTriggers = (int) ($row?->trigger_count ?? 0);
    $lastFired = $row?->last_triggered_at;
    $contents = $row?->contents ?? collect();

    $typePill = function ($r) {
        if (!$r) {
            return ['cls' => 'bg-paper-100 text-ink-500', 'label' => '—'];
        }
        $kind = $r->reply_type === 'flow' ? 'flow' : ($r->message_type ?: 'text');
        return match ($kind) {
            'flow' => [
                'cls' => 'bg-[#D9E5F2] text-[#13478A]',
                'label' => 'Flow / ' . (optional($r->flow)->flow_name ?: 'Flow'),
            ],
            'image' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Custom · Image'],
            'video' => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Custom · Video'],
            'document' => ['cls' => 'bg-accent-amber/20 text-[#7B5A14]', 'label' => 'Custom · Document'],
            'template' => ['cls' => 'bg-[#F3E9FF] text-[#5B3D8A]', 'label' => 'Custom · Template'],
            default => ['cls' => 'bg-wa-deep/10 text-wa-deep', 'label' => 'Custom · Text'],
        };
    };
    $tp = $typePill($row);
@endphp

<x-layouts.user :title="__('Keyword Analytics')" nav-key="more" page="user-auto-reply-keyword">

    <!-- Sub header -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/auto-reply') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Auto Reply') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Auto Reply / Analytics') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Keyword') }} <span
                            class="italic text-wa-deep">{{ __('analytics') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <select
                    class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    <option>{{ __('Last 7 days') }}</option>
                    <option selected>{{ __('Last 30 days') }}</option>
                    <option>{{ __('Last 90 days') }}</option>
                    <option>{{ __('This year') }}</option>
                </select>
                <button
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6">

        <!-- Keyword header card -->
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
            <div class="flex flex-col sm:flex-row items-start justify-between gap-6">
                <div class="flex items-start gap-4 min-w-0">
                    <span
                        class="w-12 h-12 rounded-xl bg-wa-mint text-wa-deep grid place-items-center text-[14px] font-mono font-semibold shrink-0">{{ $tag }}</span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Keyword group') }}</div>
                        <h1 class="font-serif text-[28px] leading-tight tracking-[-0.01em] mt-0.5">{{ $keyword }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $matchSub }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tp['cls'] }} text-[10.5px] font-mono">{{ $tp['label'] }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $deviceLabel }}</span>
                            @if ($statusActive)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-100 text-ink-500 text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-ink-500/40"></span>Paused</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($row)
                        <button data-row-action="toggle" data-row-id="{{ $row->id }}"
                            class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ $statusActive ? 'Pause rule' : 'Resume rule' }}</button>
                        <a href="{{ url('/auto-reply/create?id=' . $row->id) }}"
                            class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                            </svg>
                            Edit rule
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Triggered') }}</span>
                    <span class="text-[10px] text-wa-deep font-mono">{{ __('all-time') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ number_format($totalTriggers) }}</span>
                    <span class="text-[11px] text-ink-500">{{ __('replies sent') }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Last fired') }}</span>
                    <span
                        class="text-[10px] text-ink-500 font-mono">{{ $lastFired ? $lastFired->format('M j H:i') : '—' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-serif text-[28px] leading-none">{{ $lastFired ? $lastFired->diffForHumans(['short' => true, 'parts' => 1]) : '—' }}</span>
                    <span class="text-[11px] text-ink-500">{{ $lastFired ? 'ago' : 'never' }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Match method') }}</span>
                    <span
                        class="text-[10px] text-wa-deep font-mono">{{ optional($row)->matching_method ?? '—' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-serif text-[28px] leading-none">{{ optional($row)->fuzzy_similarity ?? '—' }}{{ optional($row)->matching_method === 'fuzzy' ? '%' : '' }}</span>
                    <span
                        class="text-[11px] text-ink-500">{{ optional($row)->matching_method === 'fuzzy' ? 'similarity' : 'rule' }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Cooldown') }}</span>
                    <span class="text-[10px] text-ink-500 font-mono">{{ __('per contact') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="font-serif text-[28px] leading-none">{{ optional($row)->cooldown ?? '—' }}</span>
                    <span class="text-[11px] text-ink-500">{{ optional($row)->cooldown ? 'sec' : 'no limit' }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Reply latency') }}</span>
                    <span
                        class="text-[10px] {{ ($analytics['latencyAvgMs'] ?? 9999) < 1500 ? 'text-wa-deep' : 'text-accent-amber' }} font-mono">{{ __('target 1.5 s') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-serif text-[28px] leading-none">{{ $analytics['latencyAvgMs'] !== null ? number_format($analytics['latencyAvgMs']) . ' ms' : '—' }}</span>
                    <span
                        class="text-[11px] text-ink-500">{{ $analytics['latencyP95Ms'] !== null ? 'p95 ' . number_format($analytics['latencyP95Ms']) . ' ms' : 'no data' }}</span>
                </div>
            </div>
        </section>

        <!-- Charts: triggers over time + breakdown by variant -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Triggers over time') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Daily firing pattern') }}</h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500">
                        <button class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0">{{ __('Triggers') }}</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">{{ __('Match rate') }}</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">{{ __('Latency') }}</button>
                    </div>
                </div>
                <div id="chart-triggers" class="h-[260px]" data-series='@json($analytics['dayBuckets']->values()->all())'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Variant breakdown') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Which spelling fires') }}</h3>
                <div id="chart-variants" class="h-[200px]"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    @php
                        $variantTotal = max(1, $analytics['variantStats']->sum());
                        $variantDots = ['bg-wa-deep', 'bg-wa-teal', 'bg-accent-amber', 'bg-[#13478A]', 'bg-[#5B3D8A]'];
                    @endphp
                    @forelse ($analytics['variantStats'] as $variant => $count)
                        @php $pct = round(($count / $variantTotal) * 100); @endphp
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2"><span
                                    class="w-2.5 h-2.5 rounded-full {{ $variantDots[$loop->index % count($variantDots)] }}"></span>{{ $variant }}</span>
                            <span class="font-mono text-ink-700">{{ number_format($count) }} /
                                {{ $pct }}%</span>
                        </div>
                    @empty
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No variants found. Variant performance appears after this rule starts firing.',
                        ])
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Hour heatmap + Funnel -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('When it fires') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Hour-of-day heatmap') }}</h3>
                    </div>
                    <span class="text-[11px] font-mono text-ink-500">{{ __('last 30 days') }}</span>
                </div>
                <div id="chart-heatmap" class="h-[260px]" data-hours='@json(array_values($analytics['hourBuckets']))'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Conversion funnel') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('From keyword to reply') }}</h3>
                <div class="space-y-3">
                    @forelse ($analytics['funnel'] as $step)
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ $step['label'] }}</span>
                                <span
                                    class="font-mono text-ink-900">{{ number_format($step['count']) }}{{ $step['pct'] !== 100.0 ? ' / ' . $step['pct'] . '%' : '' }}</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width:{{ max(0, min(100, $step['pct'])) }}%">
                                </div>
                            </div>
                        </div>
                    @empty
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No inbound traffic found on this device. Matching traffic will appear here after the rule fires.',
                        ])
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Recent triggers feed + Top users -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recent triggers') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Last 8 firings') }}</h3>
                    </div>
                    <span class="text-[11px] font-mono text-ink-500">{{ number_format($analytics['fired30d']) }} fires
                        · last 30d</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Time') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('From') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Message') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Variant') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Latency') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($analytics['recent'] as $log)
                                <tr>
                                    <td class="px-4 py-2.5 font-mono text-[11px] text-ink-700">
                                        {{ optional($log->fired_at)->format('M j H:i') }}</td>
                                    <td class="px-2 py-2.5">{{ $maskPhone($log->contact_phone) }}</td>
                                    <td class="px-2 py-2.5 text-ink-700 truncate max-w-[260px]">
                                        "{{ \Illuminate\Support\Str::limit($log->matched_text, 60) }}"</td>
                                    <td class="px-2 py-2.5 font-mono text-[11px]">{{ $log->matched_variant ?: '—' }}
                                    </td>
                                    <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">—</td>
                                    <td class="px-4 py-2.5"><span
                                            class="inline-flex items-center gap-1 text-wa-deep text-[10.5px] font-mono"><span
                                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Sent</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4">
                                        @include('user.partials.empty-state', [
                                            'message' =>
                                                'No fires found. Once your bot starts matching this rule, fires will appear here.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Top users') }}
                </div>
                <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">{{ __('Most-frequent senders') }}</h3>
                <div class="space-y-3">
                    @php
                        $userGradients = [
                            'from-wa-teal to-wa-deep',
                            'from-accent-amber to-accent-coral',
                            'from-wa-deep to-ink-900',
                            'from-[#5B3D8A] to-[#13478A]',
                            'from-[#7B5A14] to-accent-amber',
                        ];
                    @endphp
                    @forelse ($analytics['topUsers'] as $u)
                        <div class="flex items-center gap-2.5">
                            <span
                                class="w-8 h-8 rounded-full bg-gradient-to-br {{ $userGradients[$loop->index % count($userGradients)] }} text-paper-0 grid place-items-center text-[10px] font-semibold">{{ $phoneInitials($u['phone']) ?: '••' }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12.5px] font-semibold truncate">{{ $maskPhone($u['phone']) }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono">{{ __('contact') }}</div>
                            </div>
                            <div class="text-[12px] font-mono text-ink-700">{{ number_format($u['count']) }}×</div>
                        </div>
                    @empty
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No senders found. Frequent senders will appear here after inbound traffic starts.',
                        ])
                    @endforelse
                </div>
                <div class="mt-4 inline-flex items-center gap-1 text-[12px] text-ink-500 font-mono">
                    {{ number_format($analytics['uniqueUsers']) }} unique
                    {{ $analytics['uniqueUsers'] === 1 ? 'sender' : 'senders' }}
                </div>
            </div>
        </section>

        @php
            // Real health-check signals — derived from analytics already
            // computed by AutoReplyController::keyword(). When we have NO
            // lookup data for this device we skip the rate tiles entirely
            // rather than display fabricated percentages.
            $matchedThisPct = (float) ($analytics['funnel'][2]['pct'] ?? 0);
            $latencyAvg = $analytics['latencyAvgMs'] ?? null;
            $fired7d = (int) ($analytics['fired7d'] ?? 0);
            $fired30d = (int) ($analytics['fired30d'] ?? 0);
            $hasLookupData = ($analytics['funnel'][0]['count'] ?? 0) > 0;
        @endphp
        <!-- Tip / health -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Health checks') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Things to watch') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @if ($hasLookupData)
                        @php
                            $rateOk = $matchedThisPct >= 60;
                            $tileClass = $rateOk
                                ? 'bg-wa-deep/5 border-wa-deep/15'
                                : 'bg-accent-amber/10 border-accent-amber/30';
                            $iconBg = $rateOk ? 'bg-wa-deep' : 'bg-accent-amber';
                        @endphp
                        <div class="flex items-start gap-2.5 p-3 rounded-lg border {{ $tileClass }}">
                            <span
                                class="w-7 h-7 rounded-full {{ $iconBg }} text-paper-0 grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M3 8l3 3 7-8" />
                                </svg>
                            </span>
                            <div>
                                <div class="text-[12.5px] font-semibold">
                                    {{ $rateOk ? __('Match rate is healthy') : __('Match rate is low') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">{{ $matchedThisPct }}%
                                    {{ __('of incoming messages on this device matched this rule.') }}</div>
                            </div>
                        </div>
                    @endif
                    @if ($latencyAvg !== null)
                        @php
                            $latencyOk = $latencyAvg <= 1500;
                            $latTileCls = $latencyOk
                                ? 'bg-wa-deep/5 border-wa-deep/15'
                                : 'bg-accent-amber/10 border-accent-amber/30';
                            $latIconBg = $latencyOk ? 'bg-wa-deep' : 'bg-accent-amber';
                        @endphp
                        <div class="flex items-start gap-2.5 p-3 rounded-lg border {{ $latTileCls }}">
                            <span
                                class="w-7 h-7 rounded-full {{ $latIconBg }} text-paper-0 grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M3 8l3 3 7-8" />
                                </svg>
                            </span>
                            <div>
                                <div class="text-[12.5px] font-semibold">
                                    {{ $latencyOk ? __('Latency under target') : __('Latency above target') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">
                                    {{ number_format($latencyAvg / 1000, 2) }} s {{ __('server-side lookup avg.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-paper-50/60 border border-paper-200">
                        <span class="w-7 h-7 rounded-full bg-paper-200 text-ink-700 grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M8 2v6l4 2" />
                                <circle cx="8" cy="8" r="6" />
                            </svg>
                        </span>
                        <div>
                            <div class="text-[12.5px] font-semibold">{{ number_format($fired7d) }}
                                {{ __('fires in last 7 days') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">{{ number_format($fired30d) }}
                                {{ __('total in the last 30 days.') }}</div>
                        </div>
                    </div>
                    @if (!$hasLookupData && $latencyAvg === null)
                        <div class="flex items-start gap-2.5 p-3 rounded-lg bg-paper-50/60 border border-paper-200">
                            <span
                                class="w-7 h-7 rounded-full bg-paper-200 text-ink-700 grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 4v5M8 12h.01" />
                                </svg>
                            </span>
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Not enough activity yet') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">
                                    {{ __('Health stats appear once this rule starts firing on real inbound messages.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">
                    {{ __('Reply preview') }}</div>
                <div class="font-serif text-[20px] leading-tight mt-1">{{ __('What customers see') }}</div>
                <div class="mt-4 bg-paper-0/10 border border-paper-0/15 rounded-lg p-3 text-[12.5px] leading-snug">Hi
                    @{{ name }}! Thanks for reaching out — happy to help.</div>
                <div class="mt-3 text-[10.5px] font-mono text-paper-0/70">{{ __('Custom · Text · 53 chars') }}</div>
                <a href="{{ url('/auto-reply/create') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">
                    Edit reply
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg>
                </a>
            </div>
        </section>

    </main>

</x-layouts.user>
