@php
    /** @var \App\Models\Webhook $hook */
    /** @var \Illuminate\Support\Collection $deliveries */
    $deliveries = $deliveries ?? collect();
    $analytics = $analytics ?? [
        'total7d' => 0,
        'success_pct' => null,
        'p95_ms' => null,
        'retries' => 0,
        'failed' => 0,
        'codes' => [],
        'days' => collect(),
    ];

    $hookName = $hook?->name ?: 'Webhook #' . optional($hook)->id;
    $hookUrl = $hook?->webhook_url ?: '—';
    $events = collect($hook?->events ?? []);
    $isActive = $hook?->state_label === 'active';
    $isFailing = $hook?->state_label === 'failing';

    // Pretty status pill for an HTTP code.
    $codePill = function (?int $c) {
        if ($c === null) {
            return ['cls' => 'bg-paper-100 text-ink-500', 'label' => '—'];
        }
        if ($c >= 200 && $c < 300) {
            return ['cls' => 'bg-wa-mint text-wa-deep', 'label' => $c . ' OK'];
        }
        if ($c >= 300 && $c < 400) {
            return ['cls' => 'bg-paper-100 text-ink-700', 'label' => (string) $c];
        }
        if ($c >= 400 && $c < 500) {
            return ['cls' => 'bg-accent-amber/20 text-[#7B5A14]', 'label' => (string) $c];
        }
        return ['cls' => 'bg-accent-coral/15 text-[#A1431F]', 'label' => (string) $c];
    };

    $fmtLatency = function (?int $ms) {
        if ($ms === null) {
            return '—';
        }
        return $ms < 1000 ? $ms . 'ms' : number_format($ms / 1000, 1) . 's';
    };
@endphp

<x-layouts.user :title="__('Webhook Analytics')" nav-key="more" page="user-webhooks-detail">

    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/webhooks') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Webhooks / Endpoint') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Webhook') }} <span
                            class="italic text-wa-deep">{{ __('analytics') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <select
                    class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                    <option>{{ __('Last 24 hours') }}</option>
                    <option selected>{{ __('Last 7 days') }}</option>
                    <option>{{ __('Last 30 days') }}</option>
                </select>
                <button data-row-action="test-fire" data-row-id="{{ $hook->id }}"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5"><svg
                        viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M3 11h10M8 4v9M5 7l3-3 3 3" />
                    </svg>{{ __('Test fire') }}</button>
                <a href="{{ url('/webhooks/' . $hook->id . '/edit') }}"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2"><svg
                        viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                    </svg>{{ __('Edit') }}</a>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6">

        <!-- Endpoint header card -->
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
            <div class="flex flex-col sm:flex-row items-start justify-between gap-6">
                <div class="flex items-start gap-4 min-w-0">
                    <span class="w-12 h-12 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0"
                        @if ($hook->icon_color) style="background:{{ $hook->icon_color }}20;color:{{ $hook->icon_color }}" @endif><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 8h3l1.5-4 2 8 1.5-4h2" />
                        </svg></span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Endpoint ·
                            {{ $hook->environment ?: 'Production' }}</div>
                        <h1 class="font-serif text-[26px] leading-tight tracking-[-0.01em] mt-0.5">{{ $hookName }}
                        </h1>
                        <div class="mt-1.5 font-mono text-[12.5px] text-ink-700 truncate max-w-full lg:max-w-[640px]">
                            {{ $hookUrl }}</div>
                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep text-[10.5px] font-mono">{{ $hook->http_method ?: 'POST' }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $events->count() }}
                                {{ $events->count() === 1 ? 'event' : 'events' }}</span>
                            @if ($hook->secret)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('HMAC signed') }}</span>
                            @endif
                            @if ($isActive)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
                            @elseif ($isFailing)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent-coral/15 text-accent-coral text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>Failing</span>
                            @else
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-100 text-ink-500 text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-ink-500/40"></span>Paused</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button data-row-action="toggle" data-row-id="{{ $hook->id }}"
                        class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ $hook->status ? 'Pause' : 'Resume' }}</button>
                </div>
            </div>
        </section>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Events') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">7d</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($analytics['total7d']) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('fired') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Success') }}</span>
                    <span
                        class="text-[10px] {{ ($analytics['success_pct'] ?? 0) >= 99 ? 'text-wa-deep' : (($analytics['success_pct'] ?? 0) >= 95 ? 'text-accent-amber' : 'text-accent-coral') }} font-mono">2xx
                        rate</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ $analytics['success_pct'] !== null ? $analytics['success_pct'] . '%' : '—' }}</span><span
                        class="text-[11px] text-ink-500">{{ $analytics['total7d'] ? 'last 7d' : 'no data' }}</span>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Latency') }}</span>
                    <span
                        class="text-[10px] {{ ($analytics['p95_ms'] ?? 0) < 500 ? 'text-wa-deep' : 'text-accent-amber' }} font-mono">{{ __('target 500ms') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ $analytics['p95_ms'] !== null ? $fmtLatency($analytics['p95_ms']) : '—' }}</span><span
                        class="text-[11px] text-ink-500">p95</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Retries') }}</span><span
                        class="text-[10px] text-accent-amber font-mono">{{ $analytics['total7d'] ? round(($analytics['retries'] / max($analytics['total7d'], 1)) * 100, 1) . '%' : '—' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($analytics['retries']) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('last 7d') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</span><span
                        class="text-[10px] text-accent-coral font-mono">{{ $analytics['total7d'] ? round(($analytics['failed'] / max($analytics['total7d'], 1)) * 100, 1) . '%' : '—' }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($analytics['failed']) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('non-2xx') }}</span></div>
            </div>
        </section>

        <!-- Charts -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Activity') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Success vs failure (7d)') }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500">
                        <button class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0">{{ __('Volume') }}</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">{{ __('Latency') }}</button>
                    </div>
                </div>
                <div id="chart-activity" class="h-[260px]" data-series='@json($analytics['days']->values()->all())'></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('By status code') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Response codes') }}</h3>
                <div id="chart-codes" class="h-[200px]" data-codes='@json(array_values(array_filter($analytics['codes'], fn($c) => ($c['count'] ?? 0) > 0)))'></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    @forelse ($analytics['codes'] as $c)
                        @if ($c['count'] > 0)
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2"><span
                                        class="w-2.5 h-2.5 rounded-full {{ $c['cls'] }}"></span>{{ $c['label'] }}</span>
                                <span class="font-mono text-ink-700">{{ number_format($c['count']) }}</span>
                            </div>
                        @endif
                    @empty
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No deliveries found. Deliveries will appear here after this endpoint receives events.',
                        ])
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Recent deliveries + payload viewer -->
        <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-4 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Deliveries') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Recent firings') }}</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                            <option>{{ __('All events') }}</option>
                            <option>{{ __('message.delivered') }}</option>
                            <option>{{ __('message.read') }}</option>
                            <option>{{ __('message.failed') }}</option>
                        </select>
                        <select
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                            <option>{{ __('All codes') }}</option>
                            <option>2xx</option>
                            <option>4xx</option>
                            <option>5xx</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Time') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Event') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Code') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Latency') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Attempt') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('View') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200" id="del-rows">
                            @forelse ($deliveries as $d)
                                @php
                                    $cp = $codePill($d->status_code);
                                    $is2xx =
                                        $d->status_code !== null && $d->status_code >= 200 && $d->status_code < 300;
                                    $latencyEl =
                                        $d->status_code !== null && $d->status_code >= 400 && $d->error
                                            ? $d->error
                                            : $fmtLatency($d->latency_ms);
                                    // Embed the full delivery so the JS can swap the payload pane
                                    // without an extra fetch. Strings only — pre-decrypted view
                                    // models keep tinker-clean encryption-at-rest semantics.
                                    $payload = (string) ($d->payload ?? '');
                                    $response = (string) ($d->response_body ?? '');
                                @endphp
                                <tr class="del-row hover:bg-paper-50/60 cursor-pointer" data-id="{{ $d->id }}"
                                    data-event="{{ $d->event_name }}" data-code="{{ $d->status_code ?? '' }}"
                                    data-code-label="{{ $cp['label'] }}"
                                    data-latency="{{ $fmtLatency($d->latency_ms) }}"
                                    data-attempt="{{ $d->is_retry ? 'retry' : '1 / 3' }}"
                                    data-time="{{ optional($d->fired_at)->format('M j H:i:s') }}"
                                    data-payload="{{ $payload }}" data-response="{{ $response }}"
                                    data-error="{{ (string) ($d->error ?? '') }}">
                                    <td class="px-4 py-2.5 font-mono text-[11px] text-ink-700">
                                        {{ optional($d->fired_at)->format('M j H:i:s') }}</td>
                                    <td class="px-2 py-2.5 font-mono text-[11px] text-wa-deep">
                                        {{ $d->event_name ?: '—' }}</td>
                                    <td class="px-2 py-2.5"><span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $cp['cls'] }} text-[10.5px] font-mono">{{ $cp['label'] }}</span>
                                    </td>
                                    <td
                                        class="px-2 py-2.5 font-mono text-[11px] {{ $is2xx ? 'text-ink-700' : 'text-accent-coral' }}">
                                        {{ $latencyEl }}</td>
                                    <td
                                        class="px-2 py-2.5 font-mono text-[11px] {{ $d->is_retry ? 'text-accent-amber' : 'text-ink-700' }}">
                                        {{ $d->is_retry ? 'retry' : 'first' }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span
                                            class="inline-flex w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep items-center justify-center"><svg
                                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                                <circle cx="8" cy="8" r="2" />
                                            </svg></span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4">
                                        @include('user.partials.empty-state', [
                                            'message' =>
                                                'No deliveries found. Once your endpoint receives events, they will appear here.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div
                    class="px-4 py-3 border-t border-paper-200 flex items-center justify-between text-[12px] text-ink-500">
                    <div>{{ __('Showing') }} <span
                            class="font-mono text-ink-900">{{ $deliveries->isEmpty() ? '0' : '1–' . $deliveries->count() }}</span>
                        of <span class="font-mono text-ink-900">{{ number_format($analytics['total7d']) }}</span> in
                        last 7 days</div>
                </div>
            </div>

            <!-- Payload inspector -->
            <aside
                class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden xl:sticky xl:top-[20px]">
                <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Payload') }}</div>
                        <div id="p-event" class="font-mono text-[12px] text-wa-deep mt-0.5">
                            {{ __('message.delivered') }}</div>
                    </div>
                    <button id="p-replay"
                        class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Replay') }}</button>
                </div>
                <div class="px-4 py-3 border-b border-paper-200 grid grid-cols-2 gap-2 text-[11.5px]">
                    <div class="bg-paper-50 rounded-md p-2">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Code') }}</div>
                        <div id="p-code" class="font-mono text-ink-900 mt-0.5">200 OK</div>
                    </div>
                    <div class="bg-paper-50 rounded-md p-2">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Latency') }}</div>
                        <div id="p-latency" class="font-mono text-ink-900 mt-0.5">198ms</div>
                    </div>
                    <div class="bg-paper-50 rounded-md p-2">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Attempt') }}</div>
                        <div id="p-attempt" class="font-mono text-ink-900 mt-0.5">1 / 3</div>
                    </div>
                    <div class="bg-paper-50 rounded-md p-2">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                            {{ __('When') }}</div>
                        <div id="p-time" class="font-mono text-ink-900 mt-0.5">14:42:08</div>
                    </div>
                </div>

                <div class="border-b border-paper-200">
                    <div class="px-4 py-2 flex items-center gap-1 bg-paper-50/40" id="p-tabs">
                        <button
                            class="p-tab px-2.5 py-1 rounded-full text-[11px] font-mono font-semibold bg-wa-deep text-paper-0"
                            data-pane="body">{{ __('Body') }}</button>
                        <button
                            class="p-tab px-2.5 py-1 rounded-full text-[11px] font-mono font-semibold text-ink-600 hover:bg-paper-100"
                            data-pane="headers">{{ __('Headers') }}</button>
                        <button
                            class="p-tab px-2.5 py-1 rounded-full text-[11px] font-mono font-semibold text-ink-600 hover:bg-paper-100"
                            data-pane="response">{{ __('Response') }}</button>
                    </div>
                    <pre id="p-body"
                        class="p-pane p-4 text-[11px] font-mono leading-snug text-ink-700 bg-paper-50/40 max-h-[320px] overflow-auto whitespace-pre-wrap">{
 "id": "evt_01HRJX2Y9C3T8",
 "type": "message.delivered",
 "created": 1714122488,
 "data": {
 "message_id": "wamid.HBgL...8c4321",
 "to": "+919876543210",
 "device": "+919876500000",
 "delivered_at": "2026-04-26T14:42:08Z",
 "template": "spring_promo_v3",
 "campaign": "Spring promo / VIP"
 }
}</pre>
                    <pre id="p-headers"
                        class="p-pane hidden p-4 text-[11px] font-mono leading-snug text-ink-700 bg-paper-50/40 max-h-[320px] overflow-auto whitespace-pre-wrap">POST /webhooks/events HTTP/1.1
Host: api.brand.example
Content-Type: application/json
User-Agent: {{ brand_name() }}-Webhook/1.0
{{ \App\Support\Brand::webhookEventHeader() }}: message.delivered
{{ \App\Support\Brand::webhookSignatureHeader() }}: t=1714122488,v1=8a3f...c2e1
{{ \App\Support\Brand::webhookHookIdHeader() }}: del_01HRJX2Z0
X-API-Key: [redacted]</pre>
                    <pre id="p-response"
                        class="p-pane hidden p-4 text-[11px] font-mono leading-snug text-ink-700 bg-paper-50/40 max-h-[320px] overflow-auto whitespace-pre-wrap">HTTP/1.1 200 OK
Content-Type: application/json
Server: nginx/1.25.4

{
 "ok": true,
 "received": "evt_01HRJX2Y9C3T8"
}</pre>
                </div>

                <div class="px-4 py-3 flex items-center gap-2 bg-paper-50/40">
                    <button
                        class="flex-1 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Copy curl') }}</button>
                    <button
                        class="flex-1 px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium">{{ __('Copy payload') }}</button>
                </div>
            </aside>
        </section>

        <!-- Health checks -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Health checks') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Things to watch') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-wa-deep/5 border border-wa-deep/15"><span
                            class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0"><svg
                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-8" />
                            </svg></span>
                        <div>
                            <div class="text-[12.5px] font-semibold">{{ __('Up for 14 days straight') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">
                                {{ __('No retries needed since the last deploy. Solid.') }}</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5 p-3 rounded-lg bg-wa-deep/5 border border-wa-deep/15"><span
                            class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0"><svg
                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-8" />
                            </svg></span>
                        <div>
                            <div class="text-[12.5px] font-semibold">{{ __('Latency well under target') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">218ms p95 vs 500ms target — feels instant.
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-start gap-2.5 p-3 rounded-lg bg-accent-amber/10 border border-accent-amber/30">
                        <span
                            class="w-7 h-7 rounded-full bg-accent-amber text-paper-0 grid place-items-center shrink-0"><svg
                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 4v5M8 12h.01" />
                            </svg></span>
                        <div>
                            <div class="text-[12.5px] font-semibold">12 retries last week</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">
                                {{ __('All within retry budget. Most recovered on attempt 2 — investigate spike at 13:54.') }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex items-start gap-2.5 p-3 rounded-lg bg-accent-amber/10 border border-accent-amber/30">
                        <span
                            class="w-7 h-7 rounded-full bg-accent-amber text-paper-0 grid place-items-center shrink-0"><svg
                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 4v5M8 12h.01" />
                            </svg></span>
                        <div>
                            <div class="text-[12.5px] font-semibold">4 unrecovered failures</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">
                                {{ __('All 404 — endpoint may have changed. Check route / API version.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">{{ __('Signature') }}
                </div>
                <div class="font-serif text-[20px] leading-tight mt-1">{{ __('HMAC verification') }}</div>
                <pre
                    class="mt-3 bg-paper-0/10 border border-paper-0/15 rounded-lg p-3 text-[11px] font-mono leading-snug whitespace-pre-wrap">const sig = req.headers['{{ strtolower(\App\Support\Brand::webhookSignatureHeader()) }}'];
const expected = hmacSHA256(secret, raw);
if (!safeEqual(sig, expected)) reject();</pre>
                <a href="{{ url('/webhooks/' . $hook->id . '/edit') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">{{ __('Edit endpoint') }}<svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg></a>
            </div>
        </section>

    </main>

</x-layouts.user>
