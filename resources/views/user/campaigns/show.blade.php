@php
    /** @var \App\Models\MetaCampaign $campaign */
    /** @var \App\Services\MetaGraphClient $graph */
    $isFailed = $campaign->status === 'FAILED';
    $isDraft = $campaign->status === 'DRAFT';
    $isActive = $campaign->status === 'ACTIVE';
    $isPaused = $campaign->status === 'PAUSED';
    $hasMeta = (bool) $campaign->facebook_id;
    $insights = is_array($campaign->insights) ? $campaign->insights : [];
    $currency = \App\Models\SystemSetting::get('default_currency', 'USD');

    $statusPill = match (true) {
        $isActive => 'bg-wa-mint text-wa-deep ring-1 ring-wa-green/30',
        $isPaused => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        $isFailed => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        $isDraft => 'bg-paper-50 text-ink-700 ring-1 ring-paper-200',
        default => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
    };
@endphp

<x-layouts.user :title="__('Campaign — :name', ['name' => $campaign->name])" nav-key="meta-ads" page="user-meta-ads-show">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-7 py-7" data-meta-show data-meta-id="{{ $campaign->id }}"
        data-meta-status="{{ $campaign->status }}"
        data-meta-refresh-url="{{ route('user.meta-ads.refresh', $campaign->id) }}"
        data-meta-retry-url="{{ route('user.meta-ads.retry', $campaign->id) }}"
        data-meta-toggle-url="{{ route('user.meta-ads.toggle', $campaign->id) }}">

        {{-- ============= Header ============= --}}
        <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1 text-[11px] text-ink-500">
                    <a href="{{ route('user.meta-ads.index') }}" class="hover:text-ink-900">{{ __('Meta Ads') }}</a>
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    <span class="text-ink-700">{{ $campaign->name }}</span>
                </div>
                <h1 class="text-[20px] font-semibold text-ink-900 leading-tight truncate">{{ $campaign->name }}</h1>
                <p class="text-[13px] text-ink-500 mt-1">
                    {{ ucfirst(strtolower((string) $campaign->objective)) ?: 'Click-to-WhatsApp' }} ·
                    Daily budget {{ number_format((float) $campaign->daily_budget, 2) }} {{ $currency }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $statusPill }}"
                    data-status-pill>
                    <span data-status-label>{{ ucfirst(strtolower($campaign->status)) }}</span>
                </span>
                @if ($hasMeta)
                    <button type="button" data-meta-toggle-btn
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium {{ $isActive ? 'border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-800' : 'bg-wa-deep text-paper-0 hover:bg-wa-teal' }}">
                        {{ $isActive ? 'Pause' : 'Activate on Meta' }}
                    </button>
                @endif
                @if ($isFailed)
                    <button type="button" data-meta-retry-btn
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium bg-rose-600 text-paper-0 hover:bg-rose-700">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 8a5 5 0 019-3M13 8a5 5 0 01-9 3M12 4v3h-3M4 12V9h3" />
                        </svg>
                        Retry sync
                    </button>
                @endif
                <a href="{{ route('user.meta-ads.edit', $campaign->id) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-800">
                    Edit
                </a>
            </div>
        </div>

        {{-- ============= Banners ============= --}}
        @if ($isFailed && !empty($campaign->meta_last_error))
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M9 9l6 6M15 9l-6 6" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-rose-900">{{ __('Sync to Meta failed') }}</div>
                    <div class="text-[12px] text-rose-800 mt-0.5" data-meta-error>{{ $campaign->meta_last_error }}
                    </div>
                    <div class="text-[11px] text-rose-700 mt-2">{{ __('Fix the issue + click') }}
                        <strong>{{ __('Retry sync') }}</strong>. Common causes: missing fb_page_id on workspace, ad
                        image below 1080 px, token without ads_management scope.</div>
                </div>
            </div>
        @endif
        @if ($isDraft)
            <div class="mb-5 rounded-xl border border-paper-200 bg-paper-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink-500 flex-shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-ink-900">{{ __('Draft — not on Meta yet') }}</div>
                    <div class="text-[12px] text-ink-600 mt-0.5">
                        {{ __('This campaign is saved locally. Edit it and choose status') }}
                        <strong>{{ __('Paused') }}</strong> or <strong>{{ __('Active') }}</strong> to push the
                        5-step CTWA pipeline to Meta.</div>
                </div>
            </div>
        @endif
        @if (!$isCtwaReady && !$isDraft)
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M12 2L2 22h20z" />
                    <path d="M12 9v5M12 17v.5" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-amber-900">{{ __('CTWA prerequisites missing') }}</div>
                    <div class="text-[12px] text-amber-800 mt-0.5">
                        {{ __('This workspace needs Meta page_id + WABA id + phone_number_id configured before the ad can route taps to WhatsApp. Add them at') }}
                        <a href="{{ route('user.devices.index') }}" class="font-semibold underline">/devices</a>.</div>
                </div>
            </div>
        @endif

        {{-- ============= Two-column body ============= --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">

            {{-- ===== LEFT: Insights + creative preview ===== --}}
            <div class="space-y-5">

                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                        <h2 class="text-[13px] font-semibold text-ink-900">{{ __('Performance (last 7 days)') }}</h2>
                        <span class="text-[11px] text-ink-500" data-meta-last-synced>
                            @if ($campaign->meta_synced_at)
                                Last synced {{ $campaign->meta_synced_at->diffForHumans() }}
                            @endif
                        </span>
                    </div>
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-paper-100 text-[12px]">
                        @php
                            $tiles = [
                                [
                                    'Spend',
                                    (string) number_format((float) ($insights['spend'] ?? 0), 2) . ' ' . $currency,
                                ],
                                ['Impressions', (string) number_format((int) ($insights['impressions'] ?? 0))],
                                ['Clicks', (string) number_format((int) ($insights['clicks'] ?? 0))],
                                ['Reach', (string) number_format((int) ($insights['reach'] ?? 0))],
                                ['Conversations', (string) number_format((int) ($insights['conversions'] ?? 0))],
                                ['CTR', (string) number_format((float) ($insights['ctr'] ?? 0), 2) . '%'],
                                ['CPC', (string) number_format((float) ($insights['cpc'] ?? 0), 2) . ' ' . $currency],
                                ['Frequency', (string) number_format((float) ($insights['frequency'] ?? 0), 2)],
                            ];
                        @endphp
                        @foreach ($tiles as [$label, $value])
                            <div class="bg-paper-0 px-4 py-3"
                                data-tile="{{ str_replace(' ', '-', strtolower($label)) }}">
                                <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ $label }}
                                </div>
                                <div class="text-[16px] font-semibold text-ink-900 mt-0.5">{{ $value }}</div>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                    <div class="px-5 py-3 border-b border-paper-200">
                        <h2 class="text-[13px] font-semibold text-ink-900">{{ __('Creative preview') }}</h2>
                    </div>
                    <div class="p-5 bg-paper-50">
                        <div class="max-w-sm mx-auto bg-paper-0 rounded-xl shadow-card overflow-hidden">
                            @php
                                // Prefer a locally-uploaded creative; else the image url synced
                                // from Meta (refresh() stores insights.creative_image_url).
                                $__creativeSrc = $campaign->creative_image
                                    ? asset('storage/' . ltrim($campaign->creative_image, '/'))
                                    : (is_array($campaign->insights ?? null) ? ($campaign->insights['creative_image_url'] ?? '') : '');
                                // Fallback: first ad's image (the SAME source the "Ads & sets"
                                // thumbnails use). Carousel/dynamic creatives can leave
                                // creative_image_url blank while each ad still has a thumbnail,
                                // so this keeps the preview from showing "No image".
                                if (!$__creativeSrc && is_array($campaign->insights['ads'] ?? null)) {
                                    foreach ($campaign->insights['ads'] as $__ad) {
                                        if (!empty($__ad['image_url'])) { $__creativeSrc = $__ad['image_url']; break; }
                                    }
                                }
                            @endphp
                            @if ($__creativeSrc)
                                <img src="{{ $__creativeSrc }}"
                                    class="w-full aspect-square object-cover" alt="">
                            @else
                                <div
                                    class="w-full aspect-square bg-paper-100 grid place-items-center text-[12px] text-ink-500">
                                    {{ __('No image') }}</div>
                            @endif
                            <div class="p-4">
                                <div class="text-[13px] font-semibold text-ink-900">
                                    {{ $campaign->creative_title ?: $campaign->name }}</div>
                                <p class="text-[12px] text-ink-700 mt-1 whitespace-pre-line">
                                    {{ $campaign->creative_body }}</p>
                                @if ($campaign->ctwa_enabled)
                                    <button
                                        class="w-full mt-3 py-2 rounded-lg bg-wa-green text-paper-0 text-[12px] font-semibold inline-flex items-center justify-center gap-1.5">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                                            <path d="M13 8a5 5 0 11-9.6-2L2 14l8-1.4A5 5 0 0013 8z" />
                                        </svg>
                                        Send WhatsApp message
                                    </button>
                                    @if ($campaign->ctwa_message)
                                        <div class="mt-2 text-[10.5px] text-ink-500 italic">
                                            "{{ $campaign->ctwa_message }}"</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== RIGHT: Meta tree IDs + targeting ===== --}}
            <aside class="space-y-3">

                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <h3 class="text-[12px] font-semibold text-ink-900 uppercase tracking-wide">
                            {{ __('Meta entity tree') }}</h3>
                    </div>
                    <dl class="divide-y divide-paper-100 text-[11px]">
                        @php
                            $rows = [
                                ['Campaign id', $campaign->facebook_id, 'data-id="campaign"'],
                                ['Ad set id', $campaign->meta_adset_id, 'data-id="adset"'],
                                ['Creative id', $campaign->meta_creative_id, 'data-id="creative"'],
                                ['Ad id', $campaign->meta_ad_id, 'data-id="ad"'],
                                [
                                    'Image hash',
                                    $campaign->meta_image_hash
                                        ? mb_substr($campaign->meta_image_hash, 0, 20) . '…'
                                        : null,
                                    '',
                                ],
                            ];
                        @endphp
                        @foreach ($rows as [$label, $val, $attr])
                            <div class="px-4 py-2.5 flex items-center justify-between gap-3" {!! $attr !!}>
                                <dt class="text-ink-500">{{ $label }}</dt>
                                <dd class="font-mono text-[10.5px] text-ink-900 truncate max-w-[200px]"
                                    title="{{ $val }}">{{ $val ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <h3 class="text-[12px] font-semibold text-ink-900 uppercase tracking-wide">
                            {{ __('Targeting') }}</h3>
                    </div>
                    @php $t = is_array($campaign->targeting) ? $campaign->targeting : []; @endphp
                    <dl class="divide-y divide-paper-100 text-[12px]">
                        <div class="px-4 py-2.5 flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Countries') }}</dt>
                            <dd class="text-ink-900">
                                {{ !empty($t['countries']) ? implode(', ', (array) $t['countries']) : '—' }}</dd>
                        </div>
                        <div class="px-4 py-2.5 flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Age') }}</dt>
                            <dd class="text-ink-900">{{ $t['age_min'] ?? 18 }}–{{ $t['age_max'] ?? 65 }}</dd>
                        </div>
                        <div class="px-4 py-2.5 flex items-center justify-between">
                            <dt class="text-ink-500">{{ __('Gender') }}</dt>
                            <dd class="text-ink-900">{{ ucfirst((string) ($t['gender'] ?? 'all')) }}</dd>
                        </div>
                        @if (!empty($t['interests']))
                            <div class="px-4 py-2.5">
                                <dt class="text-ink-500 mb-1">{{ __('Interests') }}</dt>
                                <dd class="text-ink-900">
                                    {{ is_array($t['interests']) ? implode(', ', $t['interests']) : $t['interests'] }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

            </aside>
        </div>
    </div>

</x-layouts.user>
