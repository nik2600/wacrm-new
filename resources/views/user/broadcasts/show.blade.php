@php
    /** Controller payload: $broadcast, $recipients, $header, $templatePreview,
     * $deviceLabel, $devicePhone, $chartData, $failureRows, $events */
    $statusKey = strtolower((string) $broadcast->status);
    $statusBadge = match ($statusKey) {
        'completed' => 'bg-wa-green/15 text-wa-deep border border-wa-green/30',
        'completed_with_errors' => 'bg-accent-amber/15 text-ink-800 border border-accent-amber/30',
        'processing' => 'bg-accent-amber/15 text-ink-800 border border-accent-amber/30',
        'scheduled' => 'bg-paper-100 text-ink-700 border border-paper-200',
        'failed' => 'bg-accent-coral/15 text-accent-coral border border-accent-coral/30',
        default => 'bg-paper-50 text-ink-700 border border-paper-200',
    };

    $rowStatusClass = fn($s) => match ($s) {
        'read' => 'bg-wa-green/10 text-wa-deep border border-wa-green/30',
        'delivered' => 'bg-wa-mint/50 text-wa-deep border border-wa-green/30',
        'sent' => 'bg-paper-100 text-ink-700 border border-paper-200',
        'failed' => 'bg-accent-coral/15 text-accent-coral border border-accent-coral/30',
        default => 'bg-paper-50 text-ink-500 border border-paper-200',
    };

    // Render recipient times in the broadcast's own timezone, not raw UTC —
    // wa_local() converts the stored (UTC) timestamp before formatting.
    $fmt = fn($iso) => $iso ? (wa_local($iso, $broadcast->timezone)?->format('M j, H:i:s') ?? '—') : '—';
@endphp

<x-layouts.user :title="__('Broadcast Analytics')" nav-key="more" page="user-broadcasts-show">

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/broadcasts') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Broadcasts / Analytics /
                        #{{ $broadcast->id }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ $broadcast->name }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span data-broadcast-status-pill
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $statusBadge }}">{{ ucfirst(str_replace('_', ' ', $statusKey ?: 'draft')) }}</span>
                @if (($header['failed'] ?? 0) > 0)
                    <button type="button" data-broadcast-retry="{{ $broadcast->id }}"
                        data-retry-url="{{ route('user.broadcasts.retry-failed', $broadcast->id) }}"
                        class="px-3.5 py-1.5 rounded-full bg-wa-deep/10 text-wa-deep hover:bg-wa-deep/20 text-[12px] font-semibold flex items-center gap-2"
                        title="{{ __('Re-send to recipients that failed') }}">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M2 8a6 6 0 1 1 1.6 4.1M2 13v-3h3" />
                        </svg>
                        {{ __('Retry failed') }} ({{ number_format($header['failed']) }})
                    </button>
                @endif
                @if (in_array($statusKey, ['scheduled', 'failed'], true))
                    <form method="POST" action="{{ route('user.broadcasts.destroy', $broadcast->id) }}"
                        onsubmit="return confirm('Delete this broadcast?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="px-3.5 py-1.5 rounded-full bg-accent-coral/10 text-accent-coral hover:bg-accent-coral/20 text-[12px] font-semibold flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 4h10M6 4V2.8h4V4M5 6v7h6V6" />
                            </svg>
                            Delete
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-5">

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-6 py-5 border-b border-paper-200 flex items-start justify-between gap-5 flex-wrap">
                <div class="min-w-0">
                    @php
                        $bProv = strtolower((string) ($broadcast->provider ?? ''));
                        $bProvBadge = match ($bProv) {
                            'baileys' => ['Unofficial API', 'bg-ink-900/5 text-ink-700 border border-ink-200'],
                            'waba' => ['WABA', 'bg-wa-mint/40 text-wa-deep border border-wa-deep/20'],
                            'twilio' => ['Twilio', 'bg-[#F22F46]/10 text-[#A12534] border border-[#F22F46]/25'],
                            default => null,
                        };
                    @endphp
                    <div
                        class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500 flex-wrap">
                        <span>{{ __('Broadcast') }}</span>
                        @if ($bProvBadge)
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded-full {{ $bProvBadge[1] }} text-[9.5px] font-mono font-semibold uppercase tracking-wider">{{ $bProvBadge[0] }}</span>
                        @endif
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span>{{ wa_local($broadcast->created_at, $broadcast->timezone)?->format('M j, Y H:i') ?? '—' }}</span>
                        @if ($deviceLabel)
                            <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                            <span class="inline-flex items-center gap-1">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="4.5" y="2" width="7" height="12" rx="1.5" />
                                    <path d="M7 12.5h2" />
                                </svg>
                                {{ $deviceLabel }} · {{ $devicePhone }}
                            </span>
                        @endif
                    </div>
                    <h1 class="font-serif text-[28px] sm:text-[34px] lg:text-[40px] leading-none mt-2">{{ __('Broadcast analytics') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Per-recipient delivery, read, and failure timeline for this template send.') }}</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full max-w-[480px]" data-broadcast-live
                    data-broadcast-id="{{ $broadcast->id }}"
                    data-live-url="{{ route('user.broadcasts.live-stats', $broadcast->id) }}">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Sent') }}
                        </div>
                        <div data-live="sent" class="font-serif text-[28px] leading-none mt-1 text-wa-deep">
                            {{ number_format($header['sent']) }}</div>
                        <div data-live="sent-pct" class="text-[10px] text-wa-deep mt-1">{{ $header['sent_pct'] }}% of
                            audience</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Delivered') }}</div>
                        <div data-live="delivered" class="font-serif text-[28px] leading-none mt-1">
                            {{ number_format($header['delivered']) }}</div>
                        <div data-live="delivered-pct" class="text-[10px] text-ink-500 mt-1">
                            {{ $header['delivered_pct'] }}% delivery rate</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Read') }}
                        </div>
                        <div data-live="read" class="font-serif text-[28px] leading-none mt-1">
                            {{ number_format($header['read']) }}</div>
                        <div data-live="read-pct" class="text-[10px] text-ink-500 mt-1">{{ $header['read_pct'] }}% of
                            delivered</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Audience') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">{{ number_format($header['total']) }}
                        </div>
                        <div class="text-[10px] text-ink-500 mt-1">{{ __('total recipients') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Queued') }}
                        </div>
                        <div data-live="queued" class="font-serif text-[28px] leading-none mt-1">
                            {{ number_format($header['queued']) }}</div>
                        <div class="text-[10px] text-ink-500 mt-1">{{ __('awaiting send') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-accent-coral/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Failed') }}
                        </div>
                        <div data-live="failed" class="font-serif text-[28px] leading-none mt-1 text-accent-coral">
                            {{ number_format($header['failed']) }}</div>
                        <div data-live="failed-pct" class="text-[10px] text-accent-coral mt-1">
                            {{ $header['failed_pct'] }}% failure rate</div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-3 flex items-center gap-1 border-b border-paper-200 bg-white overflow-x-auto">
                <button type="button" data-tab="overview"
                    class="tab-btn px-4 py-2 rounded-full text-[13px] font-semibold transition bg-wa-deep text-paper-0">{{ __('Overview') }}</button>
                <button type="button" data-tab="recipients"
                    class="tab-btn px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Recipients') }}
                    <span class="font-mono text-[10px] text-ink-500 ml-1">{{ $recipients->count() }}</span></button>
                <button type="button" data-tab="failures"
                    class="tab-btn px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Failures') }}
                    <span class="font-mono text-[10px] text-ink-500 ml-1">{{ $failureRows->count() }}</span></button>
                <button type="button" data-tab="events"
                    class="tab-btn px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Events') }}</button>
            </div>
        </section>

        {{-- ─── OVERVIEW TAB ─── --}}
        <section data-panel="overview" class="tab-panel space-y-5">
            <div class="grid grid-cols-12 gap-5">
                <div class="col-span-12 xl:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Delivery curve') }}</div>
                            <h2 class="font-serif text-[24px] leading-tight mt-1">
                                {{ __('Sent, delivered, read over time') }}</h2>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] text-ink-500">
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Sent</span>
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Delivered</span>
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>Read</span>
                        </div>
                    </div>
                    <div id="chart-delivery"></div>
                </div>
                <div class="col-span-12 xl:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status mix') }}
                    </div>
                    <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Where each message landed') }}</h2>
                    <div id="chart-status" class="mt-2"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
                <div class="xl:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Conversion funnel') }}</div>
                    <h2 class="font-serif text-[24px] leading-tight mt-1 mb-5">{{ __('From send to read') }}</h2>
                    @php
                        $funnel = [
                            ['label' => 'Audience', 'count' => $header['total'], 'pct' => 100, 'cls' => 'bg-ink-900'],
                            [
                                'label' => 'Sent',
                                'count' => $header['sent'],
                                'pct' => $header['sent_pct'],
                                'cls' => 'bg-wa-deep',
                            ],
                            [
                                'label' => 'Delivered',
                                'count' => $header['delivered'],
                                'pct' => $header['delivered_pct'],
                                'cls' => 'bg-wa-teal',
                            ],
                            [
                                'label' => 'Read',
                                'count' => $header['read'],
                                'pct' => $header['read_pct'],
                                'cls' => 'bg-accent-amber',
                            ],
                            [
                                'label' => 'Failed',
                                'count' => $header['failed'],
                                'pct' => $header['failed_pct'],
                                'cls' => 'bg-accent-coral',
                            ],
                        ];
                    @endphp
                    <div class="space-y-3">
                        @foreach ($funnel as $f)
                            <div>
                                <div class="flex items-center justify-between text-[12px] mb-1">
                                    <span>{{ $f['label'] }}</span>
                                    <span class="font-mono text-ink-900">{{ number_format($f['count']) }} /
                                        {{ $f['pct'] }}%</span>
                                </div>
                                <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $f['cls'] }}"
                                        style="width: {{ min((float) $f['pct'], 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($templatePreview)
                    <aside class="xl:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="flex items-center justify-between mb-3">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Template preview') }}</span>
                            <span
                                class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep">{{ ucfirst($templatePreview['category']) }}</span>
                        </div>
                        <div class="rounded-[20px] border border-ink-900/10 bg-ink-900 p-2 shadow-soft">
                            <div class="rounded-[16px] overflow-hidden bg-wa-chat">
                                <div class="h-9 bg-wa-deep text-paper-0 flex items-center gap-2 px-3">
                                    <span
                                        class="w-6 h-6 rounded-full bg-paper-0 text-wa-deep grid place-items-center text-[10px] font-semibold">WA</span>
                                    <span
                                        class="text-[11px] font-semibold truncate">{{ $templatePreview['name'] }}</span>
                                </div>
                                <div
                                    class="p-3 min-h-[200px] bg-[radial-gradient(circle_at_1px_1px,rgba(7,94,84,0.09)_1px,transparent_0)] bg-[length:16px_16px]">
                                    <div
                                        class="ml-auto max-w-[255px] rounded-2xl rounded-tr-md bg-wa-bubble border border-wa-green/30 px-3 py-2 shadow-card">
                                        @if ($templatePreview['header'])
                                            <div class="text-[12px] font-semibold text-ink-900">
                                                {{ $templatePreview['header'] }}</div>
                                        @endif
                                        <p class="text-[12px] leading-relaxed text-ink-800 mt-1 whitespace-pre-wrap">
                                            {{ $templatePreview['body'] }}</p>
                                        @if ($templatePreview['footer'])
                                            <div
                                                class="text-[10px] text-ink-500 mt-2 pt-2 border-t border-wa-green/20">
                                                {{ $templatePreview['footer'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                @endif
            </div>
        </section>

        {{-- ─── RECIPIENTS TAB ─── --}}
        <section data-panel="recipients" class="tab-panel hidden">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recipients') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Who got it, when') }}</h2>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <input id="rcptSearch" type="text" placeholder="{{ __('Search name / phone') }}"
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12px] focus:outline-none focus:bg-paper-0 focus:border-wa-deep w-full sm:w-[240px]">
                        <select id="rcptStatus"
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12px] focus:outline-none focus:bg-paper-0 focus:border-wa-deep">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="pending">{{ __('Pending') }}</option>
                            <option value="sent">{{ __('Sent') }}</option>
                            <option value="delivered">{{ __('Delivered') }}</option>
                            <option value="read">{{ __('Read') }}</option>
                            <option value="failed">{{ __('Failed') }}</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left px-4 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Name') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Phone') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Status') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Sent') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Delivered') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Read') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Clicks') }}</th>
                                <th class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Error / WA id') }}</th>
                            </tr>
                        </thead>
                        <tbody id="rcptTbody" class="divide-y divide-paper-200">
                            @forelse ($recipients as $r)
                                <tr data-rcpt data-status="{{ $r['status'] }}"
                                    data-search="{{ mb_strtolower($r['name'] . ' ' . $r['phone']) }}"
                                    class="hover:bg-paper-50/40">
                                    <td class="px-4 py-2.5">
                                        <div class="font-semibold truncate">{{ $r['name'] }}</div>
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-[11px]">{{ $r['phone'] ?: '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase tracking-wide {{ $rowStatusClass($r['status']) }}">{{ $r['status'] }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 font-mono text-[10.5px] text-ink-700">
                                        {{ $fmt($r['sent_at']) }}</td>
                                    <td class="px-3 py-2.5 font-mono text-[10.5px] text-wa-deep">
                                        {{ $fmt($r['delivered_at']) }}</td>
                                    <td class="px-3 py-2.5 font-mono text-[10.5px] text-wa-teal">
                                        {{ $fmt($r['read_at']) }}</td>
                                    <td class="px-3 py-2.5 font-mono text-[10.5px] {{ ($r['click_count'] ?? 0) > 0 ? 'text-wa-deep font-semibold' : 'text-ink-400' }}"
                                        @if ($r['last_click_at'] ?? null) title="Last click: {{ $r['last_click_at'] }}" @endif>
                                        {{ ($r['click_count'] ?? 0) > 0 ? number_format($r['click_count']) : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($r['error'])
                                            <div class="text-[11px] text-accent-coral truncate"
                                                title="{{ $r['error'] }}">{{ $r['error'] }}</div>
                                        @elseif ($r['wa_message_id'])
                                            <div class="text-[10.5px] font-mono text-ink-500 truncate"
                                                title="{{ $r['wa_message_id'] }}">
                                                {{ \Illuminate\Support\Str::limit($r['wa_message_id'], 16) }}</div>
                                        @else
                                            <span class="text-ink-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-[12px] text-ink-500">
                                        {{ __('No recipients attached.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- ─── FAILURES TAB ─── --}}
        <section data-panel="failures" class="tab-panel hidden">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
                <div class="xl:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Failure reasons') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Top errors') }}</h2>
                    @if ($failureRows->isEmpty())
                        <div
                            class="mt-6 text-center text-[12px] text-ink-500 py-12 border border-dashed border-paper-200 rounded-xl">
                            {{ __('No failures so far. Every recipient was reached.') }}</div>
                    @else
                        <div id="chart-failures" class="mt-3"></div>
                    @endif
                </div>
                <div class="xl:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Affected recipients') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ $failureRows->count() }}
                            failure{{ $failureRows->count() === 1 ? '' : 's' }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left px-4 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                        {{ __('Name') }}</th>
                                    <th
                                        class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                        {{ __('Phone') }}</th>
                                    <th
                                        class="text-left px-3 py-2.5 font-mono text-[10px] uppercase tracking-[0.14em]">
                                        {{ __('Error') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200">
                                @forelse ($failureRows as $r)
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <div class="font-semibold truncate">{{ $r['name'] }}</div>
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-[11px]">{{ $r['phone'] ?: '—' }}</td>
                                        <td class="px-3 py-2.5 text-[11.5px] text-accent-coral">
                                            {{ $r['error'] ?: 'Unknown' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-[12px] text-ink-500">—
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- ─── EVENTS TAB ─── --}}
        <section data-panel="events" class="tab-panel hidden">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Lifecycle') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-5">{{ __('Timeline of this broadcast') }}
                </h2>
                <ol class="relative border-l border-paper-200 ml-3 space-y-5">
                    @foreach ($events as $e)
                        @php
                            // Some events are timestamps ('at' key), others are
                            // status snapshots ('value' key). Render whichever the
                            // controller actually populated.
                            $at = $e['at'] ?? null;
                            $value = $e['value'] ?? null;
                            $display = $at ? $fmt($at) : ($value ?: '—');
                        @endphp
                        <li class="ml-5">
                            <span
                                class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full bg-wa-deep border-2 border-paper-0"></span>
                            <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ $e['label'] }}</div>
                            <div class="text-[14px] text-ink-900">{{ $display }}</div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    </main>

    {{-- Server-rendered chart payload — the user-broadcasts-show JS
 reads window.WA_BROADCAST_DATA and ApexCharts the overview tab. --}}
    <script>
        window.WA_BROADCAST_DATA = @json($chartData);
    </script>

</x-layouts.user>
