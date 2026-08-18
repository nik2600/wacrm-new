@php
    /** Shared card markup for /meta-ads — rendered both in the
     * full page and via the JSON `partial` mode the JS uses for
     * filter / search / sort updates without a full reload.
     */
    $statusPillClasses = [
        'ACTIVE' => [
            'bg' => 'bg-wa-green/10',
            'text' => 'text-wa-deep',
            'border' => 'border-wa-green/30',
            'dot' => 'bg-wa-green',
        ],
        'PAUSED' => [
            'bg' => 'bg-[#EFE5F5]',
            'text' => 'text-[#5B3D8A]',
            'border' => 'border-[#D9C7E8]',
            'dot' => 'bg-[#5B3D8A]',
        ],
        'SCHEDULED' => [
            'bg' => 'bg-[#13478A]/10',
            'text' => 'text-[#13478A]',
            'border' => 'border-[#13478A]/30',
            'dot' => 'bg-[#13478A]',
        ],
        'DRAFT' => [
            'bg' => 'bg-paper-50',
            'text' => 'text-ink-700',
            'border' => 'border-paper-200',
            'dot' => 'bg-paper-300',
        ],
        'FAILED' => [
            'bg' => 'bg-accent-coral/10',
            'text' => 'text-[#A1431F]',
            'border' => 'border-accent-coral/30',
            'dot' => 'bg-accent-coral',
        ],
    ];
    $objectiveLabels = [
        'MESSAGES' => 'Messages',
        'LINK_CLICKS' => 'Link clicks',
        'LEAD_GENERATION' => 'Lead gen',
        'CONVERSIONS' => 'Conversions',
        'BRAND_AWARENESS' => 'Brand awareness',
        'REACH' => 'Reach',
        'VIDEO_VIEWS' => 'Video views',
    ];
@endphp

@forelse ($campaigns as $c)
    @php
        $pill = $statusPillClasses[$c->status] ?? $statusPillClasses['DRAFT'];
        $metrics = $c->metrics;
        $isPaused = $c->status === 'PAUSED';
        $iconBg = $c->status === 'ACTIVE' ? 'bg-wa-bubble text-wa-deep' : 'bg-paper-100 text-ink-700';
    @endphp
    <div class="camp-card bg-white border border-paper-200 rounded-2xl px-5 py-[18px] transition hover:border-wa-deep/25 hover:shadow-soft"
        data-campaign-id="{{ $c->id }}">
        @if ($c->status === 'FAILED' && !empty($c->meta_last_error))
            <div
                class="mb-3 rounded-xl border border-accent-coral/30 bg-accent-coral/10 px-3 py-2 flex items-start gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-accent-coral flex-shrink-0 mt-0.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <circle cx="8" cy="8" r="6" />
                    <path d="M8 5v3M8 11v.5" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[11px] font-semibold text-accent-coral">{{ __('Meta sync failed') }}</div>
                    <div class="text-[11px] text-ink-700 mt-0.5 line-clamp-2" title="{{ $c->meta_last_error }}">
                        {{ mb_substr((string) $c->meta_last_error, 0, 180) }}</div>
                    <a href="{{ route('user.meta-ads.show', $c->id) }}"
                        class="text-[11px] font-semibold text-accent-coral underline mt-1 inline-block">View details
                        &amp; retry</a>
                </div>
            </div>
        @endif

        <div class="flex items-start gap-3 mb-3">
            <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 {{ $iconBg }}">
                @if ($isPaused)
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                        <rect x="5" y="4" width="2" height="8" rx="0.5" />
                        <rect x="9" y="4" width="2" height="8" rx="0.5" />
                    </svg>
                @else
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                        <polygon points="6,4 12,8 6,12" />
                    </svg>
                @endif
            </span>
            <div class="flex-1 min-w-0">
                <div class="text-[15px] font-semibold break-words">{{ $c->name }}</div>
                {{-- Use the Meta ad-account's OWN currency (persisted from the Graph
                     API's account_currency, e.g. IDR → Rp) — same source the
                     analytics page uses. The old `$c->ad_account?->currency` read a
                     relationship that doesn't exist on MetaCampaign, so it always
                     fell through to the USD default and mislabeled Rupiah spend. --}}
                @php $adCur = strtoupper((string) ($c->insights['account_currency'] ?? '')) ?: \App\Models\SystemSetting::get('default_currency', 'USD'); @endphp
                <div class="text-[12px] text-ink-500 mt-0.5">
                    {{ $objectiveLabels[$c->optimization_goal] ?? $c->optimization_goal }} · Budget:
                    {!! \App\Support\FormatSettings::display($c->daily_budget, $adCur) !!}/day</div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                <a href="{{ route('user.meta-ads.show', $c->id) }}"
                    class="hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 w-7 h-7 rounded-full inline-flex items-center justify-center"
                    title="{{ __('View details') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                        <circle cx="8" cy="8" r="2" />
                    </svg>
                </a>
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $pill['bg'] }} {{ $pill['text'] }} border {{ $pill['border'] }}">
                    <span
                        class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>{{ ucfirst(strtolower($c->status)) }}
                </span>

                <button data-meta-toggle="{{ $c->id }}" type="button"
                    class="rounded-full px-3 py-1.5 text-[12px] font-{{ $isPaused ? 'semibold' : 'medium' }} flex items-center gap-1.5 {{ $isPaused ? 'bg-wa-deep hover:bg-wa-teal text-paper-0' : 'hairline border border-paper-200 bg-paper-0 hover:bg-paper-50' }}">
                    @if ($isPaused)
                        <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                            <polygon points="3,2 10,6 3,10" />
                        </svg>
                        Activate
                    @else
                        <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                            <rect x="3" y="2" width="2" height="8" rx="0.5" />
                            <rect x="7" y="2" width="2" height="8" rx="0.5" />
                        </svg>
                        Pause
                    @endif
                </button>

                <a href="{{ route('user.meta-ads.analytics', ['id' => $c->id]) }}"
                    class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-wa-bubble text-wa-deep flex items-center justify-center"
                    title="{{ __('View analytics') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                    </svg>
                </a>
                <a href="{{ route('user.meta-ads.edit', $c->id) }}"
                    class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Edit campaign') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 11.5V13h1.5L11.8 5.7l-1.5-1.5L3 11.5Z" />
                        <path d="M9.4 5.1l1.5 1.5" />
                    </svg>
                </a>
                <button data-meta-delete="{{ $c->id }}" data-name="{{ $c->name }}" type="button"
                    class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral flex items-center justify-center"
                    title="{{ __('Delete campaign') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-3">
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Spend') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">{!! \App\Support\FormatSettings::display($metrics['spend'], $adCur) !!}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Impressions') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($metrics['impressions']) }}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Clicks') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($metrics['clicks']) }}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Reach') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($metrics['reach']) }}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('Conversions') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($metrics['conversions']) }}</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('CTR') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                    {{ number_format($metrics['ctr'], 2) }}%</div>
            </div>
            <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                    {{ __('CPC') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">{!! \App\Support\FormatSettings::display($metrics['cpc'], $adCur) !!}</div>
            </div>
            <div class="metric bg-wa-bubble border border-wa-deep/20 rounded-[10px] px-3 py-2">
                <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-wa-deep uppercase">
                    {{ __('Revenue') }}</div>
                <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 text-wa-deep">
                    {!! \App\Support\FormatSettings::display($metrics['revenue'], $adCur) !!}</div>
            </div>
        </div>

        <div
            class="hairline-t border-t border-paper-200 pt-3 meta-row flex flex-wrap items-center gap-x-[18px] gap-y-2 text-[11.5px] text-ink-500 font-mono [&_svg]:text-ink-600">
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.5">
                    <rect x="2" y="3" width="12" height="11" rx="1" />
                    <path d="M2 6h12M5 1.5v3M11 1.5v3" />
                </svg>Created: {{ $c->created_at?->format('Y-m-d') }}</span>
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                    stroke="currentColor" stroke-width="1.5">
                    <rect x="2" y="3" width="9" height="9" rx="1" />
                    <rect x="5" y="6" width="9" height="9" rx="1" />
                </svg>{{ $c->ad_set_count }} Ad Set(s)</span>
            <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                    fill="currentColor">
                    <polygon points="6,4 12,8 6,12" />
                </svg>{{ $c->ad_count }} Ad(s)</span>
            @if ($c->facebook_id)
                <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.5">
                        <path d="M7 5l-2 2a2.83 2.83 0 0 0 4 4l1-1M9 11l2-2a2.83 2.83 0 0 0-4-4l-1 1" />
                    </svg>FB ID: {{ \Illuminate\Support\Str::limit($c->facebook_id, 12, '…') }}</span>
            @endif
            @if ($c->ctwa_enabled)
                <span class="flex items-center gap-1.5 text-wa-deep"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <path
                            d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
                    </svg>{{ __('Click-to-WhatsApp') }}</span>
            @endif
        </div>
    </div>
@empty
    @include('user.partials.empty-state', [
        'message' =>
            'No Meta Ads campaigns match the current filters. Try clearing filters or create a new campaign.',
        'resetHref' => url('/meta-ads'),
        'actionHref' => route('user.meta-ads.create'),
        'actionLabel' => 'Create campaign',
    ])
@endforelse
