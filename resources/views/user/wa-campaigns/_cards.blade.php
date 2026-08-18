@php
    /**
     * Shared card markup for /wa-campaigns — visual design mirrors
     * the admin /admin/meta-ads cards (round status icon, title +
     * type tag, status pill + action group, KPI strip, metadata
     * footer with icons).
     *
     * @var \Illuminate\Support\Collection $campaigns
     */
    $statusPillClasses = [
        'running' => [
            'bg' => 'bg-wa-green/10',
            'text' => 'text-wa-deep',
            'border' => 'border-wa-green/30',
            'dot' => 'bg-wa-green',
        ],
        'paused' => [
            'bg' => 'bg-[#EFE5F5]',
            'text' => 'text-[#5B3D8A]',
            'border' => 'border-[#D9C7E8]',
            'dot' => 'bg-[#5B3D8A]',
        ],
        'scheduled' => [
            'bg' => 'bg-[#13478A]/10',
            'text' => 'text-[#13478A]',
            'border' => 'border-[#13478A]/30',
            'dot' => 'bg-[#13478A]',
        ],
        'completed' => [
            'bg' => 'bg-ink-900',
            'text' => 'text-paper-0',
            'border' => 'border-ink-900',
            'dot' => 'bg-paper-0',
        ],
        'failed' => [
            'bg' => 'bg-accent-coral/10',
            'text' => 'text-[#A1431F]',
            'border' => 'border-accent-coral/30',
            'dot' => 'bg-accent-coral',
        ],
        'cancelled' => [
            'bg' => 'bg-paper-100',
            'text' => 'text-ink-600',
            'border' => 'border-paper-200',
            'dot' => 'bg-paper-300',
        ],
        'draft' => [
            'bg' => 'bg-paper-50',
            'text' => 'text-ink-700',
            'border' => 'border-paper-200',
            'dot' => 'bg-paper-300',
        ],
    ];
    $typeLabels = [
        'template' => 'Template',
        'flow' => 'Flow',
        'text' => 'Text',
        'media' => 'Media',
        'button' => 'Buttons',
        'custom' => 'Custom',
    ];
@endphp

@forelse ($campaigns as $c)
    @php
        $statusKey = strtolower((string) $c->status) ?: 'draft';
        $pill = $statusPillClasses[$statusKey] ?? $statusPillClasses['draft'];
        $typeKey = strtolower((string) $c->campaign_type);
        $typeLabel = $typeLabels[$typeKey] ?? ucfirst($typeKey ?: 'Broadcast');

        $cardBorder = match ($statusKey) {
            'failed' => 'border-accent-coral/30 hover:border-accent-coral',
            'completed' => 'border-paper-200 hover:border-wa-deep/25',
            default => 'border-paper-200 hover:border-wa-deep/25',
        };

        $iconBg = match ($statusKey) {
            'running' => 'bg-wa-bubble text-wa-deep',
            'paused' => 'bg-[#EFE5F5] text-[#5B3D8A]',
            'scheduled' => 'bg-[#13478A]/10 text-[#13478A]',
            'completed' => 'bg-wa-bubble text-wa-deep',
            'failed' => 'bg-accent-coral/10 text-accent-coral',
            'cancelled' => 'bg-paper-100 text-ink-500',
            default => 'bg-paper-100 text-ink-600',
        };

        $sent = (int) $c->sent_count;
        $delivered = (int) $c->delivered_count;
        $read = (int) $c->read_count;
        $responded = (int) $c->responded_count;
        $clicked = (int) $c->clicked_count;
        $failed = (int) $c->failed_count;
        $total = (int) $c->total_recipients;
        $deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0;
        $readRate = $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0;

        $device = $c->device_id ? \App\Models\Device::find($c->device_id) : null;
        $deviceLabel = $device ? (trim($device->device_name) ?: 'Device #' . $device->id) : null;
    @endphp
    <div class="camp-card bg-white border rounded-2xl px-5 py-[18px] transition hover:shadow-soft {{ $cardBorder }}"
        data-campaign-row data-campaign-id="{{ $c->id }}">
        <div class="flex items-start gap-3 mb-3">
            <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 {{ $iconBg }}">
                @switch($statusKey)
                    @case('running')
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>
                    @break

                    @case('paused')
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                            <rect x="5" y="4" width="2" height="8" rx="0.5" />
                            <rect x="9" y="4" width="2" height="8" rx="0.5" />
                        </svg>
                    @break

                    @case('scheduled')
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3l2 2" />
                        </svg>
                    @break

                    @case('completed')
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 8l3 3 7-7" />
                        </svg>
                    @break

                    @case('failed')
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M8 1l7 13H1z" />
                            <path d="M8 6v3M8 11.5h.01" />
                        </svg>
                    @break

                    @case('cancelled')
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M5 5l6 6M11 5l-6 6" />
                        </svg>
                    @break

                    @default
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 4h10v8H3z" />
                            <path d="M3 7h10" />
                        </svg>
                @endswitch
            </span>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[15px] font-semibold truncate">{{ $c->campaign_name }}</span>
                    <span
                        class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ $typeLabel }}</span>
                    @if ($c->ab_testing)
                        <span
                            class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">A/B</span>
                    @endif
                </div>
                <div class="text-[12px] text-ink-500 mt-0.5">
                    {{ $typeLabel }}@if ($deviceLabel)
                        · Device: {{ $deviceLabel }}
                    @endif · {{ number_format($total) }} recipient{{ $total === 1 ? '' : 's' }}
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border {{ $pill['bg'] }} {{ $pill['text'] }} {{ $pill['border'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>{{ ucfirst($statusKey) }}
                </span>

                @if ($statusKey === 'running' || $statusKey === 'scheduled')
                    <form method="POST" action="{{ route('user.wa-campaigns.cancel', $c->id) }}"
                        data-ajax="cancel-campaign" data-confirm="Cancel this campaign? Pending sends will stop."
                        class="inline">
                        @csrf
                        <button type="submit"
                            class="hairline border border-accent-coral/40 text-accent-coral rounded-full px-3 py-1.5 text-[12px] font-medium bg-paper-0 hover:bg-accent-coral/10 flex items-center gap-1.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <circle cx="6" cy="6" r="5" />
                                <path d="M3.5 3.5l5 5" />
                            </svg>
                            Cancel
                        </button>
                    </form>
                @elseif ($statusKey === 'paused' || $statusKey === 'failed' || $statusKey === 'cancelled')
                    <form method="POST" action="{{ route('user.wa-campaigns.resume', $c->id) }}"
                        data-ajax="resume-campaign" class="inline">
                        @csrf
                        <button type="submit"
                            class="rounded-full px-3 py-1.5 text-[12px] font-semibold bg-wa-deep hover:bg-wa-teal text-paper-0 flex items-center gap-1.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                                <polygon points="3,2 10,6 3,10" />
                            </svg>
                            Resume
                        </button>
                    </form>
                @endif

                <a href="{{ route('user.wa-campaigns.detail', $c->id) }}"
                    class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-wa-bubble text-wa-deep flex items-center justify-center"
                    title="{{ __('View analytics') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                    </svg>
                </a>
                {{-- Edit ⇄ Resend are mutually exclusive and status-gated:
 Edit shows for mutable campaigns (draft / scheduled / paused);
 Resend shows for finished ones (completed / failed / cancelled).
 A running campaign is in flight, so neither appears. --}}
                @if (in_array($statusKey, ['draft', 'scheduled', 'paused'], true))
                    <a href="{{ route('user.wa-campaigns.edit', $c->id) }}"
                        class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                        title="{{ __('Edit campaign') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 11.5V13h1.5L11.8 5.7l-1.5-1.5L3 11.5Z" />
                            <path d="M9.4 5.1l1.5 1.5" />
                        </svg>
                    </a>
                @elseif (in_array($statusKey, ['completed', 'failed', 'cancelled'], true))
                    <form method="POST" action="{{ route('user.wa-campaigns.resend', $c->id) }}"
                        data-ajax="resend-campaign"
                        data-confirm="Re-queue this campaign and send to every recipient again?" class="inline">
                        @csrf
                        <button type="submit"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-wa-bubble text-wa-deep flex items-center justify-center"
                            title="{{ __('Resend campaign') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M13 8a5 5 0 1 1-1.5-3.5" />
                                <path d="M13 2.5V5h-2.5" />
                            </svg>
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('user.wa-campaigns.destroy', $c->id) }}"
                    data-ajax="delete-campaign" data-confirm="Delete this campaign?" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral flex items-center justify-center"
                        title="{{ __('Delete campaign') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        @if ($statusKey === 'failed' && $failed > 0)
            <div class="rounded-xl border border-accent-coral/30 bg-accent-coral/10 p-3 mb-3">
                <div class="flex items-start gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-coral mt-0.5" fill="none"
                        stroke="currentColor" stroke-width="1.7">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3.5M8 11h.01" />
                    </svg>
                    <div class="flex-1">
                        <div class="text-[12.5px] font-semibold text-accent-coral">Failed:
                            {{ number_format($failed) }} message{{ $failed === 1 ? '' : 's' }} didn't send</div>
                        <div class="text-[11px] text-ink-700 mt-0.5">{{ __('Click') }} <b>Resume</b> to retry the
                            failures, or open analytics to see which recipients bounced.</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-3">
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Sent') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">{{ number_format($sent) }}
                </div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Delivered') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($delivered) }}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Read') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">{{ number_format($read) }}
                </div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Replied') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($responded) }}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Clicked') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">{{ number_format($clicked) }}
                </div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Failed') }}</div>
                <div
                    class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 {{ $failed > 0 ? 'text-accent-coral' : '' }}">
                    {{ number_format($failed) }}</div>
            </div>
            <div class="metric bg-wa-bubble border border-wa-deep/20 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-wa-deep uppercase">
                    {{ __('Delivery') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 text-wa-deep">
                    {{ number_format($deliveryRate, 1) }}%</div>
            </div>
        </div>

        <div
            class="hairline-t border-t border-paper-200 pt-3 flex items-center gap-[18px] text-[11.5px] text-ink-500 font-mono [&_svg]:text-ink-600 flex-wrap">
            <span class="flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                    stroke-width="1.5">
                    <rect x="2" y="3" width="12" height="11" rx="1" />
                    <path d="M2 6h12M5 1.5v3M11 1.5v3" />
                </svg>
                Created: {{ $c->created_at?->format('Y-m-d') }}
            </span>
            <span class="flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                    stroke-width="1.5">
                    <path d="M4 14a4 4 0 0 1 8 0" />
                    <circle cx="8" cy="6" r="3" />
                </svg>
                {{ number_format($total) }} recipient{{ $total === 1 ? '' : 's' }}
            </span>
            @if ($c->schedule_type === 'scheduled' && $c->send_date)
                <span class="flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3l2 2" />
                    </svg>
                    Scheduled: {{ \Illuminate\Support\Carbon::parse($c->send_date)->format('Y-m-d') }}@if ($c->send_time)
                        · {{ \Illuminate\Support\Str::limit($c->send_time, 5, '') }}
                    @endif
                </span>
            @else
                <span class="flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                        <polygon points="6,4 12,8 6,12" />
                    </svg>
                    Send: instant
                </span>
            @endif
            @if ($c->template_id)
                <span class="flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <rect x="2" y="3" width="12" height="10" rx="1" />
                        <path d="M2 6h12M5 9h3" />
                    </svg>
                    Template #{{ $c->template_id }}
                </span>
            @elseif ($c->flow_id)
                <span class="flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <circle cx="4" cy="4" r="1.5" />
                        <circle cx="12" cy="4" r="1.5" />
                        <circle cx="8" cy="12" r="1.5" />
                        <path d="M4 5.5v3.5l4 1.5 4-1.5V5.5" />
                    </svg>
                    Flow #{{ $c->flow_id }}
                </span>
            @endif
            <span
                class="ml-auto inline-flex items-center gap-1.5 {{ $statusKey === 'failed' ? 'text-accent-coral' : ($statusKey === 'running' ? 'text-wa-deep' : 'text-ink-500') }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>
                @if ($statusKey === 'running')
                    Delivering now
                @elseif ($statusKey === 'scheduled')
                    Waiting for send window
                @elseif ($statusKey === 'completed')
                    Read rate: {{ number_format($readRate, 1) }}%
                @elseif ($statusKey === 'failed')
                    Needs attention
                @elseif ($statusKey === 'paused')
                    Paused
                @elseif ($statusKey === 'cancelled')
                    Cancelled
                @else
                    Draft
                @endif
            </span>
        </div>
    </div>
    @empty
        @include('user.partials.empty-state', [
            'message' =>
                'No WhatsApp campaigns match the current filters. Try clearing filters or launch your first broadcast.',
            'resetHref' => url('/wa-campaigns'),
            'actionHref' => route('user.wa-campaigns.create'),
            'actionLabel' => 'Create campaign',
        ])
    @endforelse
