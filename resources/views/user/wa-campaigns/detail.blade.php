<x-layouts.user :title="__('WhatsApp Campaign Analytics')" nav-key="wa-campaigns" page="user-wa-campaigns-detail">

    @if (session('status') || $errors->any())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    @if (session('status'))
                        window.toast(@json(session('status')), 'success');
                    @endif
                    @foreach ($errors->all() as $err)
                        window.toast(@json($err), 'error');
                    @endforeach
                });
            </script>
        @endpush
    @endif

    @php
        $statusKey = strtolower((string) $campaign->status);
        $statusBadge = match ($statusKey) {
            'completed' => 'bg-ink-900 text-paper-0',
            'running' => 'bg-wa-green/15 text-wa-deep border border-wa-green/30',
            'scheduled' => 'bg-accent-amber/15 text-ink-800 border border-accent-amber/30',
            'failed' => 'bg-accent-coral/15 text-accent-coral border border-accent-coral/30',
            'cancelled' => 'bg-paper-100 text-ink-600 border border-paper-200',
            'paused' => 'bg-paper-100 text-ink-700 border border-paper-200',
            default => 'bg-paper-50 text-ink-700 border border-paper-200',
        };
    @endphp

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('user.wa-campaigns.index') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to campaigns') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">WA Campaigns / Analytics
                        / #{{ $campaign->id }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ $campaign->campaign_name }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap" data-campaign-id="{{ $campaign->id }}">
                <span id="campaignStatusPill"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $statusBadge }}">{{ ucfirst($statusKey ?: 'draft') }}</span>
                @if (in_array($statusKey, ['scheduled', 'paused', 'draft'], true))
                    <form method="POST" action="{{ route('user.wa-campaigns.send-now', $campaign->id) }}"
                        data-ajax="campaign-action" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2"
                            title="{{ __('Send now') }}">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8l4 4 6-8" />
                            </svg>
                            Send now
                        </button>
                    </form>
                @endif
                @if ($statusKey === 'running')
                    <form method="POST" action="{{ route('user.wa-campaigns.cancel', $campaign->id) }}"
                        data-ajax="campaign-action" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2"
                            title="{{ __('Cancel campaign') }}">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M5 5l6 6M11 5l-6 6" />
                            </svg>
                            Cancel
                        </button>
                    </form>
                @endif
                @if (in_array($statusKey, ['paused', 'cancelled'], true))
                    <form method="POST" action="{{ route('user.wa-campaigns.resume', $campaign->id) }}"
                        data-ajax="campaign-action" class="inline">
                        @csrf
                        <button type="submit"
                            class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2"
                            title="{{ __('Resume campaign') }}">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M5 4l7 4-7 4z" />
                            </svg>
                            Resume
                        </button>
                    </form>
                @endif
                {{-- Edit ⇄ Resend, status-gated + mutually exclusive. Edit only for
 draft / scheduled / paused; Resend only for completed / failed /
 cancelled. Running shows neither — it's in flight. --}}
                @if (in_array($statusKey, ['draft', 'scheduled', 'paused'], true))
                    <a href="{{ route('user.wa-campaigns.edit', $campaign->id) }}"
                        class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                        </svg>
                        Edit
                    </a>
                @elseif (in_array($statusKey, ['completed', 'failed', 'cancelled'], true))
                    {{-- Resend used to be one button that always re-sent to EVERY
                         recipient — which re-messages people who already got it
                         and burns quota. Split into scoped actions; "failed
                         only" is first because it is almost always the intent
                         and a wrong resend can't be undone. --}}
                    <div class="relative inline-block" data-wac-resend>
                        <button type="button" data-wac-resend-toggle
                            class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-wa-bubble text-wa-deep text-[12px] font-semibold flex items-center gap-2"
                            title="{{ __('Resend campaign') }}">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M13 8a5 5 0 1 1-1.5-3.5" />
                                <path d="M13 2.5V5h-2.5" />
                            </svg>
                            {{ __('Resend') }}
                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor"
                                stroke-width="2"><path d="M4 6l4 4 4-4" /></svg>
                        </button>

                        <div data-wac-resend-menu
                            class="hidden absolute right-0 mt-1 z-30 w-60 bg-paper-0 border border-paper-200 rounded-xl shadow-lg overflow-hidden">

                            <form method="POST" action="{{ route('user.wa-campaigns.resend', $campaign->id) }}"
                                data-ajax="campaign-action"
                                data-confirm="{{ __('Resend to the :n recipient(s) that failed?', ['n' => (int) $failedCount]) }}"
                                data-action-label="Resend">
                                @csrf
                                <input type="hidden" name="scope" value="failed">
                                <button type="submit" @disabled((int) $failedCount < 1)
                                    class="w-full text-left px-3 py-2.5 text-[12.5px] hover:bg-paper-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <span class="font-semibold text-wa-deep">{{ __('Resend to failed') }}</span>
                                    <span class="block text-[11px] text-ink-500">
                                        {{ (int) $failedCount > 0
                                            ? trans_choice(':count recipient|:count recipients', (int) $failedCount, ['count' => (int) $failedCount])
                                            : __('No failed recipients') }}
                                    </span>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('user.wa-campaigns.resend', $campaign->id) }}"
                                data-ajax="campaign-action"
                                data-confirm="{{ __('Resend to EVERY recipient again, including those who already received it?') }}"
                                data-action-label="Resend" class="border-t border-paper-200">
                                @csrf
                                <input type="hidden" name="scope" value="all">
                                <button type="submit"
                                    class="w-full text-left px-3 py-2.5 text-[12.5px] hover:bg-paper-50">
                                    <span class="font-semibold text-wa-deep">{{ __('Resend to everyone') }}</span>
                                    <span class="block text-[11px] text-ink-500">
                                        {{ trans_choice(':count recipient|:count recipients', (int) $campaign->total_recipients, ['count' => (int) $campaign->total_recipients]) }}
                                    </span>
                                </button>
                            </form>

                            {{-- Specific recipients — the picker lives in the
                                 Recipients tab, where the rows and their
                                 statuses already are. --}}
                            <button type="button" data-wac-resend-pick
                                class="w-full text-left px-3 py-2.5 text-[12.5px] hover:bg-paper-50 border-t border-paper-200">
                                <span class="font-semibold text-wa-deep">{{ __('Resend to specific…') }}</span>
                                <span class="block text-[11px] text-ink-500">{{ __('Pick recipients from the list') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
                <form method="POST" action="{{ route('user.wa-campaigns.destroy', $campaign->id) }}"
                    data-ajax="delete-campaign" data-confirm="Delete this campaign?" class="inline">
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
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-5">
        @php
            // "Sent but not delivered" = WhatsApp accepted the message but hasn't
            // confirmed delivery (or reported a failure). Surface the EXACT Meta
            // reason here so the operator never has to dig through logs.
            $sentN      = (int) ($campaign->sent_count ?? 0);
            $deliveredN = (int) ($campaign->delivered_count ?? 0);
            $undeliveredN = max(0, $sentN - $deliveredN);
            // WABA-ONLY banner. The wording ("WhatsApp has accepted these
            // messages…") describes Meta Cloud's async accept→webhook delivery
            // model. Unofficial (Baileys) sends directly and Twilio reports via
            // its own StatusCallback, so a "not confirmed by WhatsApp" notice is
            // misleading there — show it only for WABA campaigns.
            $isWabaCampaign = strtolower((string) ($campaign->provider ?? '')) === 'waba';
        @endphp
        @if ($isWabaCampaign && ($undeliveredN > 0 || !empty($deliveryIssueReason)))
            <section class="rounded-2xl border border-accent-amber/40 bg-accent-amber/10 px-4 sm:px-6 py-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-accent-amber mt-0.5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-ink-900">
                            {{ $undeliveredN }} {{ __('sent, delivery not confirmed by WhatsApp') }}
                        </div>
                        {{-- Show ONLY the exact message WhatsApp/Meta returned — never our
                             own explanation. Empty until Meta reports a reason for a recipient. --}}
                        @if (!empty($deliveryIssueReason))
                            <div class="text-[12.5px] text-ink-700 mt-1">
                                <span class="font-mono text-[11px] uppercase tracking-wide text-ink-500">{{ __("WhatsApp's response") }}:</span>
                                <span class="font-medium">{{ $deliveryIssueReason }}</span>
                            </div>
                        @endif
                        <div class="text-[11.5px] text-ink-500 mt-1.5">
                            @if ((int) $failedCount > 0)
                                {{ __('Exact per-recipient responses from WhatsApp appear under the Failures tab.') }}
                            @else
                                {{ __('WhatsApp has accepted these messages but has not yet returned a delivery or failure update for them.') }}
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-4 sm:px-6 py-5 border-b border-paper-200 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500">
                        <span>{{ ucfirst((string) $campaign->campaign_type) }} {{ __('campaign') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span>{{ wa_local($campaign->created_at, $campaign->timezone)?->format('M j, Y H:i') ?? '—' }}</span>
                    </div>
                    <h1 class="font-serif text-[28px] sm:text-[34px] lg:text-[40px] leading-none mt-2">{{ __('Campaign analytics') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Full delivery, engagement, recipient, reply, and error performance for this WhatsApp broadcast.') }}
                    </p>
                </div>
                {{-- Header tiles. ROI/Audience/Cost/CPC/Quality are derived in the controller from
 campaign counters + the recipient log's contact-group makeup. The "Device" tile
 is read directly off the campaign->device relation (TODO: replace with real
 cost-tracking + revenue tables when those land). --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full lg:w-[480px]">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Delivered') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">
                            {{ $header['delivery_rate'] }}<span class="text-[16px]">%</span></div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Read') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">
                            {{ $header['read_rate'] }}<span class="text-[16px]">%</span></div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Replied') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">
                            {{ $header['reply_rate'] }}<span class="text-[16px]">%</span></div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Clicked') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">
                            {{ $header['click_rate'] }}<span class="text-[16px]">%</span></div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Audience') }}</div>
                        {{-- Wrap to 2 lines instead of a hard truncate — a group
                             name + "N manual" never fits one line in this tile.
                             Full value stays available as the tooltip. --}}
                        <div class="font-serif text-[16px] leading-tight mt-1.5 line-clamp-2 break-words"
                            title="{{ $header['audience'] }}">{{ $header['audience'] }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Device') }}</div>
                        {{-- Name / number, not the raw row id. Resolved in the
                             controller (device_id is polymorphic per engine). --}}
                        <div class="font-serif text-[16px] leading-tight mt-1.5 line-clamp-2 break-words"
                            title="{{ $header['device_label'] ?? '—' }}">{{ $header['device_label'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="px-4 sm:px-6 py-3 flex items-center gap-1 border-b border-paper-200 bg-white overflow-x-auto">
                <button type="button" data-tab="overview"
                    class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold transition bg-wa-deep text-paper-0">{{ __('Overview') }}</button>
                <button type="button" data-tab="messages"
                    class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Messages') }}</button>
                <button type="button" data-tab="engagement"
                    class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Engagement') }}</button>
                <button type="button" data-tab="recipients"
                    class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Recipients') }}</button>
                <button type="button" data-tab="optouts"
                    class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Opt-outs') }}@if (count($optOutRows))
                        <span
                            class="ml-1.5 font-mono text-[11px] text-accent-coral">{{ count($optOutRows) }}</span>
                    @endif
                </button>
                <button type="button" data-tab="failures"
                    class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Failures') }}</button>
                
            </div>
        </section>

        <section data-panel="overview" class="tab-panel space-y-5">
            @php
                $deliveredPct =
                    $campaign->total_recipients > 0
                        ? ($campaign->delivered_count / max($campaign->total_recipients, 1)) * 100
                        : 0;
                $readPct =
                    $campaign->delivered_count > 0
                        ? ($campaign->read_count / max($campaign->delivered_count, 1)) * 100
                        : 0;
                $replyPct =
                    $campaign->total_recipients > 0
                        ? ($campaign->responded_count / max($campaign->total_recipients, 1)) * 100
                        : 0;
                $clickPct =
                    $campaign->total_recipients > 0
                        ? ($campaign->clicked_count / max($campaign->total_recipients, 1)) * 100
                        : 0;
                $failedPct =
                    $campaign->total_recipients > 0
                        ? ($campaign->failed_count / max($campaign->total_recipients, 1)) * 100
                        : 0;
            @endphp
            {{-- KPI tiles — every counter + percent span carries a
 data-wac-detail-totals hook so user-wa-campaigns-detail.js
 can repaint live as the Node bridge fires delivered / read /
 failed callbacks. data-campaign-id on the wrapper lets the JS
 find the right id even if the URL is rewritten. --}}
            {{-- Live failure reasons — filled by the 15s poll (paintFailures).
                 Shows the actual blocker (template not approved, channel
                 disabled, …) without a page reload; hidden while nothing has
                 failed. Server-rendered on first paint so it's correct even
                 before the first poll. --}}
            @php
                $liveFailSummary = \App\Models\WpCampaignContact::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')->whereNotNull('error_message')
                    ->selectRaw('error_message, COUNT(*) as c')
                    ->groupBy('error_message')->orderByDesc('c')->limit(5)->get();
            @endphp
            <div data-wac-failure-live
                 class="mb-3 rounded-2xl border border-accent-coral/30 bg-accent-coral/5 px-4 py-3 {{ $liveFailSummary->isEmpty() ? 'hidden' : '' }}">
                @if ($liveFailSummary->isNotEmpty())
                    <div class="mono text-[10px] uppercase tracking-[0.14em] text-accent-coral mb-2">{{ __('Why sends are failing') }}</div>
                    <ul class="space-y-1.5">
                        @foreach ($liveFailSummary as $f)
                            <li class="flex items-start gap-2">
                                <span class="shrink-0 mono text-[10px] px-1.5 py-0.5 rounded-full bg-accent-coral/15 text-accent-coral">{{ $f->c }}</span>
                                <span class="text-[12px] text-ink-700 leading-snug">{{ $f->error_message }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" data-wac-detail-grid data-campaign-id="{{ $campaign->id }}">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Total recipients') }}</div>
                    <div class="font-serif text-[42px] leading-none mt-2" data-wac-detail-totals="recipients">
                        {{ number_format($campaign->total_recipients) }}</div>
                    <div class="text-[11px] text-ink-500 mt-2">{{ __('Audience size after duplicate cleanup') }}</div>
                </div>
                <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Delivered') }}</div>
                    <div class="font-serif text-[42px] leading-none mt-2" data-wac-detail-totals="delivered">
                        {{ number_format($campaign->delivered_count) }}</div>
                    <div class="text-[11px] text-wa-deep mt-2"><span
                            data-wac-detail-totals="delivered_pct">{{ number_format($deliveredPct, 1) }}</span>%
                        delivery rate</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Read') }}</div>
                    <div class="font-serif text-[42px] leading-none mt-2" data-wac-detail-totals="read">
                        {{ number_format($campaign->read_count) }}</div>
                    <div class="text-[11px] text-ink-500 mt-2"><span
                            data-wac-detail-totals="read_pct">{{ number_format($readPct, 1) }}</span>% of delivered
                    </div>
                </div>
                <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Replies') }}</div>
                    <div class="font-serif text-[42px] leading-none mt-2" data-wac-detail-totals="replies">
                        {{ number_format($campaign->responded_count) }}</div>
                    <div class="text-[11px] text-ink-500 mt-2"><span
                            data-wac-detail-totals="replies_pct">{{ number_format($replyPct, 1) }}</span>%
                        conversation rate</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Button taps') }}</div>
                    <div class="font-serif text-[42px] leading-none mt-2" data-wac-detail-totals="clicks">
                        {{ number_format($campaign->clicked_count) }}</div>
                    <div class="text-[11px] text-ink-500 mt-2"><span
                            data-wac-detail-totals="clicks_pct">{{ number_format($clickPct, 1) }}</span>% of total
                    </div>
                </div>
                <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Failed') }}</div>
                    <div class="font-serif text-[42px] leading-none mt-2" data-wac-detail-totals="failed">
                        {{ number_format($campaign->failed_count) }}</div>
                    <div class="text-[11px] text-accent-coral mt-2"><span
                            data-wac-detail-totals="failed_pct">{{ number_format($failedPct, 1) }}</span>% failure
                        rate</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Delivery curve') }}</div>
                            <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Sent, delivered, read') }}
                            </h2>
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
                <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Outcome mix') }}</div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Final status') }}</h2>
                    <div id="chart-status" class="mt-2"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Conversion funnel') }}</div>
                    <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('From send to reply') }}</h2>
                    <div class="space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Recipients') }}</span><span
                                    class="font-mono text-ink-900">{{ number_format($funnel['recipients']) }}</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep" style="width:100%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Delivered') }}</span><span
                                    class="font-mono text-ink-900">{{ number_format($funnel['delivered']) }} /
                                    {{ $funnel['delivered_pct'] }}%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep"
                                    style="width:{{ min($funnel['delivered_pct'], 100) }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Read') }}</span><span
                                    class="font-mono text-ink-900">{{ number_format($funnel['read']) }} /
                                    {{ $funnel['read_pct'] }}%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-teal" style="width:{{ min($funnel['read_pct'], 100) }}%">
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Clicked') }}</span><span
                                    class="font-mono text-ink-900">{{ number_format($funnel['clicked']) }} /
                                    {{ $funnel['clicked_pct'] }}%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-accent-amber"
                                    style="width:{{ min($funnel['clicked_pct'], 100) }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Replied') }}</span><span
                                    class="font-mono text-ink-900">{{ number_format($funnel['replied']) }} /
                                    {{ $funnel['replied_pct'] }}%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-accent-coral"
                                    style="width:{{ min($funnel['replied_pct'], 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Active hours') }}</div>
                    <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Read heatmap') }}</h2>
                    <div id="chart-read-heatmap"></div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Best segments') }}</div>
                    <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('Top performers') }}</h2>
                    @if (empty($segments))
                        <div
                            class="rounded-xl border border-dashed border-paper-200 p-4 text-center text-[12px] text-ink-500">
                            {{ __('No segment-level data yet. Once recipients reply or read, the breakdown by contact group will appear here.') }}
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($segments as $i => $seg)
                                <div
                                    class="rounded-xl @if ($i === 0) border border-wa-green/30 bg-wa-bubble/40 @else border border-paper-200 @endif p-3">
                                    <div class="flex justify-between text-[13px] font-semibold">
                                        <span>{{ $seg['name'] }}</span>
                                        <span>{{ $seg['read_pct'] }}% read</span>
                                    </div>
                                    <div class="text-[11px] text-ink-500 mt-1">
                                        {{ number_format($seg['recipients']) }}
                                        recipient{{ $seg['recipients'] === 1 ? '' : 's' }},
                                        {{ number_format($seg['replies']) }}
                                        repl{{ $seg['replies'] === 1 ? 'y' : 'ies' }},
                                        {{ $seg['recipients'] > 0 ? round(($seg['opt_outs'] / $seg['recipients']) * 100, 1) : 0 }}%
                                        opt-out.
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section data-panel="messages" class="tab-panel hidden space-y-5">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="lg:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Throughput') }}
                    </div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Queue speed by hour') }}</h2>
                    <div id="chart-throughput"></div>
                </div>
                <div class="lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Message preview') }}</div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Sent content') }}</h2>
                    <div class="mt-4 rounded-2xl bg-wa-chat p-4 border border-paper-200">
                        @if ($previewHeader || $previewBody || $previewFooter || count($previewButtons))
                            <div
                                class="ml-auto max-w-[360px] bg-wa-bubble border border-wa-green/30 rounded-2xl rounded-tr-md px-4 py-3">
                                @if ($previewHeader)
                                    <div class="text-[12.5px] font-semibold text-ink-900 mb-1">{{ $previewHeader }}
                                    </div>
                                @endif
                                @if ($previewBody)
                                    <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $previewBody }}
                                    </p>
                                @endif
                                @if ($previewFooter)
                                    <div class="text-[10px] text-ink-500 mt-2 border-t border-wa-green/20 pt-2">
                                        {{ $previewFooter }}</div>
                                @endif
                                @if (count($previewButtons))
                                    <div class="mt-2 grid grid-cols-{{ min(count($previewButtons), 2) }} gap-2">
                                        @foreach ($previewButtons as $btn)
                                            @php $label = is_array($btn) ? ($btn['text'] ?? 'Button') : 'Button'; @endphp
                                            <button type="button"
                                                class="rounded-lg bg-paper-0 border border-wa-green/30 px-3 py-1.5 text-[11px] font-semibold text-wa-deep">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            @include('user.partials.empty-state', [
                                'message' => 'No content saved on this campaign yet.',
                            ])
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-4 text-[12px]">
                        <div class="rounded-xl bg-paper-50 border border-paper-200 p-3"><span
                                class="block text-ink-500">{{ __('Template') }}</span><b
                                class="block truncate">{{ $previewTemplateName }}</b></div>
                        <div class="rounded-xl bg-paper-50 border border-paper-200 p-3"><span
                                class="block text-ink-500">{{ __('Category') }}</span><b>{{ $previewCategory }}</b>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Message log') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">
                            {{ __('Recent recipient delivery rows') }}</h2>
                        <div class="text-[11px] text-ink-500 mt-0.5">{{ number_format($recipientTotal) }}
                            {{ $recipientTotal === 1 ? __('recipient') : __('recipients') }}</div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <form method="GET" action="{{ route('user.wa-campaigns.detail', $campaign->id) }}" class="contents">
                            <input type="search" name="q" value="{{ request('q') }}" data-msglog-search
                                class="w-full sm:w-64 px-3 py-1.5 rounded-full border border-paper-200 bg-white text-[12px] focus:outline-none focus:border-wa-deep"
                                placeholder="{{ __('Search recipient or phone — press Enter') }}">
                        </form>
                        <a href="{{ route('user.wa-campaigns.export', $campaign->id) }}"
                            class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 text-[12px] font-semibold hover:bg-paper-50 inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5v8m0 0L5 6.5m3 3l3-3M2.5 11v2.5h11V11"/></svg>
                            {{ __('Export rows') }}</a>
                    </div>
                </div>
                {{-- Resend picker. The rows and their statuses already live
                     here, so the selection happens in place rather than in a
                     second modal that would have to re-fetch the same list.
                     Posts contact_ids[] with scope=selected. --}}
                <form method="POST" action="{{ route('user.wa-campaigns.resend', $campaign->id) }}"
                    data-ajax="campaign-action" data-wac-pick-form
                    data-confirm="{{ __('Resend to the selected recipients?') }}"
                    data-action-label="Resend">
                    @csrf
                    <input type="hidden" name="scope" value="selected">

                <div data-wac-pick-bar
                    class="hidden items-center justify-between gap-3 px-4 py-2.5 bg-wa-bubble border-b border-paper-200">
                    <div class="text-[12px] text-wa-deep">
                        <span data-wac-pick-count class="font-semibold">0</span> {{ __('selected') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-wac-pick-clear
                            class="px-3 py-1 rounded-full border border-paper-200 bg-paper-0 text-[11.5px] hover:bg-paper-50">{{ __('Clear') }}</button>
                        <button type="submit"
                            class="px-3.5 py-1 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal">{{ __('Resend selected') }}</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table data-msglog-table class="w-full text-[12.5px] table-fixed">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="px-3 py-3 w-[40px]">
                                    <input type="checkbox" data-wac-pick-all
                                        title="{{ __('Select all on this page') }}"
                                        class="align-middle accent-wa-deep">
                                </th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-3 w-[210px]">
                                    {{ __('Recipient') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[130px]">
                                    {{ __('Phone') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[120px]">
                                    {{ __('Segment') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[110px]">
                                    {{ __('Status') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[90px]">
                                    {{ __('Read') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[90px]">
                                    {{ __('Clicked') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[100px]">
                                    {{ __('Reply') }}</th>
                                <th
                                    class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-3 w-[120px]">
                                    {{ __('Last event') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($messages as $m)
                                @php
                                    $rowStatus = strtolower((string) $m->status);
                                    $isUnsub   = $rowStatus === 'unsubscribed';
                                    $statusColor = in_array($rowStatus, ['failed'], true)
                                        ? 'text-accent-coral'
                                        : ($isUnsub ? 'text-ink-500' : 'text-wa-deep');
                                    $statusDot = in_array($rowStatus, ['failed'], true)
                                        ? 'bg-accent-coral'
                                        : ($isUnsub ? 'bg-ink-300' : 'bg-wa-green');
                                    // An unsubscribed recipient was SKIPPED (never sent) for compliance —
                                    // show that plainly instead of a misleading "Queued".
                                    $lastEvent = $isUnsub
                                        ? 'Skipped · opted out'
                                        : ($m->responded_at
                                            ? 'Reply'
                                            : ($m->clicked_at
                                                ? 'Button tap'
                                                : ($m->read_at
                                                    ? 'Read'
                                                    : ($m->delivered_at
                                                        ? 'Delivered'
                                                        : ($m->sent_at
                                                            ? 'Sent'
                                                            : 'Queued')))));
                                @endphp
                                <tr>
                                    <td class="px-3 py-3">
                                        @if ($m->contact_id)
                                            <input type="checkbox" name="contact_ids[]" value="{{ (int) $m->contact_id }}"
                                                data-wac-pick-row class="align-middle accent-wa-deep">
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold truncate">
                                            {{ $m->recipient_name ?: 'Recipient #' . $m->id }}</div>
                                        <div class="text-[10.5px] text-ink-500">Contact ID
                                            C-{{ $m->contact_id ?? '—' }}</div>
                                    </td>
                                    <td class="px-3 py-3 font-mono text-[11px]">{{ mask_phone($m->phone_number) ?: '—' }}</td>
                                    <td class="px-3 py-3">{{ $m->variant ?: '—' }}</td>
                                    <td class="px-3 py-3"><span
                                            class="inline-flex items-center gap-1 {{ $statusColor }} font-mono text-[10.5px]"><span
                                                class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>{{ ucfirst($rowStatus ?: 'queued') }}</span>
                                    </td>
                                    <td class="px-3 py-3 font-mono">{{ wa_local($m->read_at, $campaign->timezone)?->format('H:i') ?? '—' }}</td>
                                    <td class="px-3 py-3 font-mono">{{ $m->clicked ? 'Yes' : 'No' }}</td>
                                    <td class="px-3 py-3">{{ $m->responded_at ? 'Reply received' : '—' }}</td>
                                    <td class="px-4 py-3 text-ink-500">{{ $lastEvent }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-4">
                                        @include('user.partials.empty-state', [
                                            'message' =>
                                                'No messages found. This campaign has not started sending to recipients.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Server-side pager (Laravel paginator, ?mpage=). Only 50 rows
                     render per page, so all recipients are reachable without
                     dumping thousands into the DOM. --}}
                @if ($messages->hasPages())
                    <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-paper-200 text-[12px]">
                        <div class="text-ink-500">{{ __('Showing') }}
                            <b>{{ number_format($messages->firstItem() ?? 0) }}</b>–<b>{{ number_format($messages->lastItem() ?? 0) }}</b>
                            {{ __('of') }} <b>{{ number_format($messages->total()) }}</b></div>
                        <div class="flex items-center gap-1.5">
                            @if ($messages->onFirstPage())
                                <span class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 opacity-50 select-none">{{ __('Prev') }}</span>
                            @else
                                <a href="{{ $messages->previousPageUrl() }}" class="px-3 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50">{{ __('Prev') }}</a>
                            @endif
                            <span class="font-mono text-[11px] text-ink-600">{{ $messages->currentPage() }} / {{ $messages->lastPage() }}</span>
                            @if ($messages->hasMorePages())
                                <a href="{{ $messages->nextPageUrl() }}" class="px-3 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50">{{ __('Next') }}</a>
                            @else
                                <span class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 opacity-50 select-none">{{ __('Next') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
                </form>
            </div>
        </section>

        <section data-panel="engagement" class="tab-panel hidden space-y-5">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Opened') }}</div>
                    <div class="font-serif text-[38px] mt-1">{{ $engagement['opened_pct'] }}%</div>
                    <div class="text-[11px] text-ink-500">{{ number_format($engagement['opened_n']) }}
                        read{{ $engagement['opened_n'] === 1 ? '' : 's' }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Clicked') }}</div>
                    <div class="font-serif text-[38px] mt-1">{{ $engagement['clicked_pct'] }}%</div>
                    <div class="text-[11px] text-ink-500">{{ number_format($engagement['clicked_n']) }} button
                        tap{{ $engagement['clicked_n'] === 1 ? '' : 's' }}</div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Replies') }}</div>
                    <div class="font-serif text-[38px] mt-1">{{ $engagement['replied_pct'] }}%</div>
                    <div class="text-[11px] text-ink-500">{{ number_format($engagement['replied_n']) }}
                        repl{{ $engagement['replied_n'] === 1 ? 'y' : 'ies' }}</div>
                </div>
                <div
                    class="bg-paper-0 border @if ($engagement['optout_n']) border-accent-coral/30 @else border-paper-200 @endif rounded-2xl p-5 shadow-card">
                    <div class="text-[12px] text-ink-600">{{ __('Opt outs') }}</div>
                    <div class="font-serif text-[38px] mt-1">{{ $engagement['optout_pct'] }}%</div>
                    <div
                        class="text-[11px] @if ($engagement['optout_n']) text-accent-coral @else text-ink-500 @endif">
                        {{ number_format($engagement['optout_n']) }}
                        contact{{ $engagement['optout_n'] === 1 ? '' : 's' }}</div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Engagement split') }}</div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Clicks and replies by hour') }}</h2>
                    <div id="chart-engagement"></div>
                </div>
                <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Reply intent') }}</div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('What people asked') }}</h2>
                    <div id="chart-intents"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Top buttons') }}</div>
                    @if (empty($btnRows))
                        <div
                            class="mt-4 rounded-lg border border-dashed border-paper-200 p-4 text-center text-[12px] text-ink-500">
                            {{ __('No buttons attached to this campaign.') }}
                        </div>
                    @else
                        <div class="mt-4 space-y-3">
                            @foreach ($btnRows as $i => $row)
                                <div>
                                    <div class="flex justify-between text-[12px] mb-1">
                                        <span>{{ $row['label'] }}</span>
                                        @if ($row['trackable'])
                                            <span class="font-mono">{{ number_format($row['count']) }}</span>
                                        @else
                                            {{-- Quick-reply / call buttons carry no URL, so there is
                                                 nothing to wrap and no click can ever be recorded.
                                                 Saying so beats printing a 0 that reads as "nobody
                                                 tapped it". --}}
                                            <span
                                                class="font-mono text-[10.5px] text-ink-400">{{ __('not trackable') }}</span>
                                        @endif
                                    </div>
                                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                        <div class="h-full {{ $i === 0 ? 'bg-wa-deep' : 'bg-wa-teal' }}"
                                            style="width:{{ $row['trackable'] ? min($row['pct'], 100) : 0 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-[10.5px] text-ink-500 mt-3 font-mono">
                            {{ __('Real clicks per destination URL. Only URL buttons can be tracked.') }}
                        </div>
                    @endif
                </div>
                <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recent replies') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Conversation starts') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px] table-fixed">
                        <thead class="bg-paper-50 text-ink-500">
                            <tr>
                                <th class="text-left px-4 py-3 w-[180px]">{{ __('Recipient') }}</th>
                                <th class="text-left px-3 py-3">{{ __('Reply text') }}</th>
                                <th class="text-left px-3 py-3 w-[130px]">{{ __('Intent') }}</th>
                                <th class="text-left px-4 py-3 w-[90px]">{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($replies as $r)
                                <tr>
                                    <td class="px-4 py-3 font-semibold">
                                        {{ $r->recipient_name ?: (mask_phone($r->phone_number) ?: 'Recipient #' . $r->id) }}
                                    </td>
                                    {{-- TODO: surface the actual reply text once the inbound webhook persists message bodies onto WpCampaignContact->response. --}}
                                    <td class="px-3 py-3 truncate">{{ __('Reply received') }}</td>
                                    <td class="px-3 py-3">{{ $r->clicked ? 'Button tap' : 'Reply' }}</td>
                                    <td class="px-4 py-3 font-mono text-[11px] text-ink-500">
                                        {{ wa_local($r->responded_at, $campaign->timezone)?->format('H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4">
                                        @include('user.partials.empty-state', [
                                            'message' =>
                                                'No replies found. Replies and button taps will appear here after recipients respond.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </section>

        <section data-panel="recipients" class="tab-panel hidden space-y-5">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="flex items-center justify-between gap-4 mb-3">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Segment performance') }}</div>
                            <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Audience groups') }}</h2>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-ink-500">
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-wa-deep"></span>Read</span>
                            <span class="flex items-center gap-1.5"><span
                                    class="w-2 h-2 rounded-full bg-accent-amber"></span>Reply</span>
                        </div>
                    </div>
                    <div id="chart-segments"></div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                        {{ __('Segment totals') }}</div>
                    @if (empty($segmentTotals))
                        <div
                            class="rounded-xl border border-dashed border-paper-200 p-4 text-center text-[12px] text-ink-500">
                            {{ __("No segment data — recipients in this campaign aren't grouped.") }}
                        </div>
                    @else
                        @php $maxSeg = max(array_column($segmentTotals, 'recipients')) ?: 1; @endphp
                        <div class="space-y-3">
                            @foreach ($segmentTotals as $i => $seg)
                                @php $colour = ['bg-wa-deep','bg-wa-teal','bg-accent-amber'][$i] ?? 'bg-paper-300'; @endphp
                                <div class="rounded-xl border border-paper-200 p-3">
                                    <div class="flex justify-between text-[13px] font-semibold">
                                        <span>{{ $seg['name'] }}</span><span>{{ number_format($seg['recipients']) }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-paper-100 overflow-hidden mt-2">
                                        <div class="h-2 rounded-full {{ $colour }}"
                                            style="width:{{ min(round(($seg['recipients'] / $maxSeg) * 100), 100) }}%">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-1 gap-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Recipient table') }}</div>
                            <h2 class="font-serif text-[24px] leading-tight mt-1">
                                {{ __('Recipient-level analytics') }}</h2>
                        </div>
                        <a href="{{ route('user.wa-campaigns.export', $campaign->id) }}"
                            class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50 inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5v8m0 0L5 6.5m3 3l3-3M2.5 11v2.5h11V11"/></svg>
                            {{ __('Download CSV') }}</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px] table-fixed">
                            <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-3 w-[220px]">
                                        {{ __('Name') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[135px]">
                                        {{ __('Phone') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[125px]">
                                        {{ __('Segment') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[100px]">
                                        {{ __('Sent') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[100px]">
                                        {{ __('Delivered') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[90px]">
                                        {{ __('Read') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-3 w-[95px]">
                                        {{ __('Clicked') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-3 w-[115px]">
                                        {{ __('Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200">
                                @forelse ($recipientRows as $r)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold truncate">
                                                {{ $r->recipient_name ?: 'Recipient #' . $r->id }}</div>
                                            <div class="text-[10.5px] text-ink-500">Contact
                                                C-{{ $r->contact_id ?? '—' }}</div>
                                        </td>
                                        <td class="px-3 py-3 font-mono text-[11px]">{{ mask_phone($r->phone_number) ?: '—' }}
                                        </td>
                                        <td class="px-3 py-3">{{ $r->variant ?: '—' }}</td>
                                        <td class="px-3 py-3 font-mono">{{ wa_local($r->sent_at, $campaign->timezone)?->format('H:i') ?? '—' }}</td>
                                        <td
                                            class="px-3 py-3 font-mono @if ($r->status === 'failed') text-accent-coral @endif">
                                            {{ $r->status === 'failed' ? 'Failed' : (wa_local($r->delivered_at, $campaign->timezone)?->format('H:i') ?? '—') }}
                                        </td>
                                        <td class="px-3 py-3 font-mono">{{ wa_local($r->read_at, $campaign->timezone)?->format('H:i') ?? '—' }}</td>
                                        <td class="px-3 py-3">{{ $r->clicked ? 'Yes' : 'No' }}</td>
                                        <td class="px-4 py-3 font-mono text-ink-500">—</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-4">
                                            @include('user.partials.empty-state', [
                                                'message' =>
                                                    'No recipients on this campaign yet. Once it runs, every recipient row appears here.',
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{-- Server-side pager (Laravel paginator, ?rpage=). --}}
                    @if ($recipientRows->hasPages())
                        <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-paper-200 text-[12px]">
                            <div class="text-ink-500">{{ __('Showing') }}
                                <b>{{ number_format($recipientRows->firstItem() ?? 0) }}</b>–<b>{{ number_format($recipientRows->lastItem() ?? 0) }}</b>
                                {{ __('of') }} <b>{{ number_format($recipientRows->total()) }}</b></div>
                            <div class="flex items-center gap-1.5">
                                @if ($recipientRows->onFirstPage())
                                    <span class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 opacity-50 select-none">{{ __('Prev') }}</span>
                                @else
                                    <a href="{{ $recipientRows->previousPageUrl() }}" class="px-3 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50">{{ __('Prev') }}</a>
                                @endif
                                <span class="font-mono text-[11px] text-ink-600">{{ $recipientRows->currentPage() }} / {{ $recipientRows->lastPage() }}</span>
                                @if ($recipientRows->hasMorePages())
                                    <a href="{{ $recipientRows->nextPageUrl() }}" class="px-3 py-1 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50">{{ __('Next') }}</a>
                                @else
                                    <span class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 opacity-50 select-none">{{ __('Next') }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Opt-outs — WHO left, not just how many. The page previously carried
             only a bare opt-out count, so there was no way to follow up, clean a
             list, or evidence that an opt-out was honoured. --}}
        <section data-panel="optouts" class="tab-panel hidden">
            <div class="grid grid-cols-1 lg:grid-cols-[420px_1fr] gap-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Subscription') }}</div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">
                        {{ number_format(count($optOutRows)) }}
                        {{ count($optOutRows) === 1 ? __('opt-out') : __('opt-outs') }}</h2>
                    <p class="text-[12px] text-ink-600 mt-1.5">
                        {{ __('Recipients who replied STOP or were unsubscribed. They are skipped automatically by every future campaign, broadcast and scheduled send.') }}
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        <div class="rounded-xl border border-paper-200 p-3">
                            <div class="font-serif text-[20px] leading-none text-wa-deep">
                                {{ number_format($optInCount) }}</div>
                            <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">
                                {{ __('Still subscribed') }}</div>
                        </div>
                        <div class="rounded-xl border border-paper-200 p-3">
                            <div class="font-serif text-[20px] leading-none text-accent-coral">
                                {{ number_format(count($optOutRows)) }}</div>
                            <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-1.5">
                                {{ __('Opted out') }}</div>
                        </div>
                    </div>

                    @php
                        $optOutPct = ($optInCount + count($optOutRows)) > 0
                            ? round(count($optOutRows) / ($optInCount + count($optOutRows)) * 100, 1)
                            : 0;
                    @endphp
                    <div class="mt-4">
                        <div class="flex justify-between text-[11.5px] mb-1">
                            <span class="text-ink-600">{{ __('Opt-out rate') }}</span>
                            <span class="font-mono">{{ $optOutPct }}%</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral" style="width:{{ min($optOutPct, 100) }}%"></div>
                        </div>
                        <div class="text-[10.5px] text-ink-500 mt-2">
                            {{ __('Above ~2% usually means the audience was too broad or the message was off-target.') }}
                        </div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Who opted out') }}</div>
                    </div>
                    @if (empty($optOutRows))
                        <div class="p-8 text-center text-[12.5px] text-ink-500">
                            {{ __('Nobody opted out of this campaign.') }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="bg-paper-50 text-ink-500">
                                    <tr>
                                        <th class="text-left font-medium px-5 py-2.5">{{ __('Name') }}</th>
                                        <th class="text-left font-medium px-5 py-2.5">{{ __('Phone') }}</th>
                                        <th class="text-left font-medium px-5 py-2.5">{{ __('Message sent') }}</th>
                                        <th class="text-left font-medium px-5 py-2.5">{{ __('Opted out') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-100">
                                    @foreach ($optOutRows as $r)
                                        <tr class="hover:bg-paper-50/60">
                                            <td class="px-5 py-2.5">{{ $r['name'] }}</td>
                                            <td class="px-5 py-2.5 font-mono text-[11.5px]">{{ $r['phone'] }}</td>
                                            <td class="px-5 py-2.5 text-ink-500">
                                                {{ $r['sent_at'] ? \Illuminate\Support\Carbon::parse($r['sent_at'])->format('M j, H:i') : '—' }}
                                            </td>
                                            <td class="px-5 py-2.5 text-ink-500">
                                                {{ $r['opted_at'] ? \Illuminate\Support\Carbon::parse($r['opted_at'])->format('M j, H:i') : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section data-panel="failures" class="tab-panel hidden">
            <div class="grid grid-cols-1 lg:grid-cols-[420px_1fr] gap-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Failure reasons') }}</div>
                    <h2 class="font-serif text-[26px] leading-tight mt-1">{{ number_format($failedCount) }} failed
                        send{{ $failedCount === 1 ? '' : 's' }}</h2>
                    <div id="chart-failures"></div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Recent errors') }}</div>
                            <h2 class="font-serif text-[24px]">{{ __('Carrier responses') }}</h2>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12px] table-fixed">
                        <thead class="bg-paper-50 text-ink-500">
                            <tr>
                                <th class="text-left px-4 py-3 w-[210px]">{{ __('Recipient') }}</th>
                                <th class="text-left px-3 py-3 w-[140px]">{{ __('Number') }}</th>
                                <th class="text-left px-3 py-3">{{ __('Reason') }}</th>
                                <th class="text-left px-3 py-3 w-[110px]">{{ __('Retry') }}</th>
                                <th class="text-left px-4 py-3 w-[90px]">{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($failureRows as $f)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold truncate">
                                            {{ $f->recipient_name ?: 'Recipient #' . $f->id }}</div>
                                        <div class="text-[10.5px] text-ink-500">{{ $f->variant ?: 'Campaign send' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 font-mono">
                                        {{ mask_phone($f->phone_number) ?: '—' }}
                                    </td>
                                    <td class="px-3 py-3 truncate">{{ $f->error_message ?: 'Unknown error' }}</td>
                                    <td class="px-3 py-3 text-ink-500">
                                        {{ $f->whatsapp_message_id ? 'Retry available' : 'Manual' }}</td>
                                    <td class="px-4 py-3 text-ink-500">{{ wa_local($f->updated_at, $campaign->timezone)?->format('H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4">
                                        @include('user.partials.empty-state', [
                                            'message' =>
                                                'No failed sends. Failed recipients will appear here with the carrier error message.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </section>

        
    </main>

    @push('scripts')
        {{-- Server-rendered chart payload consumed by resources/js/charts/user-wa-campaigns-detail.js. --}}
        <script>
            window.WA_CAMPAIGN_DATA = @json($chartData);
            window.WA_CAMPAIGN_HEATMAP = @json($heatmap);
        </script>
    @endpush

</x-layouts.user>
