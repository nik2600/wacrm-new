@php
    /** @var \App\Models\WaTemplate $template */
    $template = $template ?? null;
    $provider = $provider ?? null;
    $wabaSubmittable = $wabaSubmittable ?? false;

    $metaStatus = strtoupper((string) $template->meta_status);
    $localStatus = (string) $template->status;

    // Every Meta-side state we have to render. APPROVED + REJECTED are
    // terminal-ish; PENDING/IN_APPEAL/PENDING_DELETION are transient;
    // PAUSED/DISABLED/LIMIT_EXCEEDED/FLAGGED block sends. DELETED keeps
    // the row visible for audit but locks edit/send.
    $isPending = in_array($metaStatus, ['PENDING', 'IN_APPEAL', 'PENDING_DELETION'], true)
        // A locally-submitted template whose Meta status hasn't synced back yet
        // (meta_status still empty) is also "in review" — otherwise the pending
        // banner + its Refresh button never rendered for a freshly-submitted row.
        || ($metaStatus === '' && $localStatus === 'pending');
    $isApproved = $metaStatus === 'APPROVED';
    $isRejected = $metaStatus === 'REJECTED';
    $isPaused = $metaStatus === 'PAUSED' || ($template->paused_until && now()->lt($template->paused_until));
    $isDisabled = in_array($metaStatus, ['DISABLED', 'LIMIT_EXCEEDED', 'FLAGGED', 'DELETED'], true);
    $quality = strtoupper((string) ($template->quality_score ?: 'UNKNOWN'));

    // Whether the template actually exists on Meta. A locally-"pending" row with
    // NO meta_template_id means the submit FAILED (or never ran) — it isn't
    // really in review (that's why Status/Template ID show blank), so we show a
    // "Submit to Meta" recovery banner instead of the misleading "Submitted for
    // review" one.
    $onMeta       = (bool) $template->meta_template_id;
    $notSubmitted = !$onMeta && in_array($localStatus, ['pending', 'draft'], true) && !$isApproved && !$isRejected;

    $statusPillClass = match (true) {
        $isApproved => 'bg-wa-mint text-wa-deep ring-1 ring-wa-green/30',
        $isRejected => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        $isPaused => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        $isDisabled => 'bg-zinc-100 text-zinc-700 ring-1 ring-zinc-300',
        $isPending => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
        default => 'bg-paper-50 text-ink-700 ring-1 ring-paper-200',
    };
    $statusLabel = match ($metaStatus) {
        'APPROVED' => 'Approved',
        'REJECTED' => 'Rejected',
        'PENDING' => 'In review',
        'IN_APPEAL' => 'In appeal',
        'PENDING_DELETION' => 'Deleting',
        'DELETED' => 'Deleted',
        'DISABLED' => 'Disabled',
        'LIMIT_EXCEEDED' => 'Limit exceeded',
        'FLAGGED' => 'Flagged',
        'PAUSED' => 'Paused',
        default => $isPaused ? 'Paused' : ucfirst(strtolower($metaStatus ?: $localStatus ?: 'Draft')),
    };

    // A locally-pending row that never reached Meta reads as "Not submitted"
    // (amber), not a spinning "Pending" — the pill must not imply active review.
    if ($notSubmitted) {
        $statusLabel     = 'Not submitted';
        $statusPillClass = 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
    }

    $qualityPillClass = match ($quality) {
        'GREEN' => 'bg-wa-mint text-wa-deep ring-1 ring-wa-green/30',
        'YELLOW' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'RED' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-paper-50 text-ink-500 ring-1 ring-paper-200',
    };

    $rejectionFriendly = match ($template->rejection_reason_code) {
        'ABUSIVE_CONTENT' => 'Content was flagged as abusive or threatening.',
        'INVALID_FORMAT' => 'Format violates Meta\'s template rules (placeholders, line breaks, length).',
        'PROMOTIONAL' => 'Marketing language not allowed in this category.',
        'TAG_CONTENT_MISMATCH' => 'Category does not match the content (e.g. promotional copy in a UTILITY template).',
        'SCAM' => 'Meta flagged the template as a potential scam — review claims and language.',
        'NONE' => null,
        default => $template->rejection_reason ?: null,
    };

    $lintWarnings = (array) session('lint_warnings', []);

    // Channel drives the accent + which side-cards make sense: an Instagram
    // template is local (synced to Instaflow) and never touches Meta, so the
    // Meta-sync / WABA cards are swapped for an Instagram card.
    $isInstagram = ($template->channel ?? '') === 'instagram'
        || (method_exists($template, 'engineKey') && $template->engineKey() === 'instagram');
@endphp

<x-layouts.user :title="__('Template — :name', ['name' => $template->template_name])" nav-key="templates" page="user-templates-show">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7" data-tpl-show data-tpl-id="{{ $template->id }}"
        data-tpl-meta-status="{{ $metaStatus }}"
        data-tpl-refresh-url="{{ route('user.templates.refresh', $template->id) }}">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            {{-- =============== LEFT RAIL =============== --}}
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">

                {{-- Info card — channel glyph, name, category·lang·type, status pill --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    @if ($isInstagram)
                        <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center text-white"
                            style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737)">
                            <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.7">
                                <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                            </svg>
                        </div>
                    @else
                        <div class="w-12 h-12 rounded-xl mb-3 grid place-items-center bg-wa-bubble">
                            <svg viewBox="0 0 24 24" class="w-7 h-7 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="4" /><path d="M3 8h18M8 21V8" />
                            </svg>
                        </div>
                    @endif
                    <div class="font-serif text-[18px] leading-tight break-words">{{ $template->template_name }}</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                        {{ $isInstagram ? __('Instagram') : strtoupper($template->meta_category ?: $template->category) }}
                        · {{ ucfirst($template->template_type ?: 'standard') }}</div>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $statusPillClass }}"
                            data-status-pill>
                            @if ($isPending && $onMeta)
                                <svg viewBox="0 0 16 16" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6" stroke-dasharray="20 8" /></svg>
                            @elseif ($isApproved)
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 8.5l3 3 6-6" /></svg>
                            @elseif ($isRejected)
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8" /></svg>
                            @endif
                            <span data-status-label>{{ $statusLabel }}</span>
                        </span>
                        @if ($isApproved && !$isInstagram)
                            <span class="mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $qualityPillClass }}" data-quality-pill>
                                Quality · <span data-quality-label>{{ $quality }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Facts card — Meta sync (WhatsApp) OR Instagram sync info --}}
                @if ($isInstagram)
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-4 pt-3 pb-1">{{ __('Instagram') }}</div>
                        <dl class="divide-y divide-paper-100 text-[12px]">
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Type') }}</dt>
                                <dd class="text-ink-900 font-medium">{{ __('Local DM template') }}</dd>
                            </div>
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Synced to') }}</dt>
                                <dd class="text-ink-900">{{ ig_brand_name() }}</dd>
                            </div>
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Buttons') }}</dt>
                                <dd class="text-ink-900">{{ is_array($template->buttons) ? count($template->buttons) : 0 }}</dd>
                            </div>
                        </dl>
                    </div>
                @else
                    <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-4 pt-3 pb-1">{{ __('Meta sync') }}</div>
                        <dl class="divide-y divide-paper-100 text-[12px]">
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Status') }}</dt>
                                <dd class="text-ink-900 font-medium" data-meta-status>{{ $metaStatus ?: '—' }}</dd>
                            </div>
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Quality') }}</dt>
                                <dd class="text-ink-900" data-quality-text>{{ $quality }}</dd>
                            </div>
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Meta category') }}</dt>
                                <dd class="text-ink-900">{{ strtoupper($template->meta_category ?: '—') }}</dd>
                            </div>
                            <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                                <dt class="text-ink-500 shrink-0">{{ __('Template ID') }}</dt>
                                <dd class="text-ink-900 font-mono text-[11px] min-w-0 truncate text-right">{{ $template->meta_template_id ?: '—' }}</dd>
                            </div>
                            @if ($template->submitted_at)
                                <div class="px-4 py-2.5 flex items-center justify-between">
                                    <dt class="text-ink-500">{{ __('Submitted') }}</dt>
                                    <dd class="text-ink-900">{{ $template->submitted_at->format('M j, H:i') }}</dd>
                                </div>
                            @endif
                            <div class="px-4 py-2.5 flex items-center justify-between">
                                <dt class="text-ink-500">{{ __('Last synced') }}</dt>
                                <dd class="text-ink-900" data-last-synced>{{ optional($template->last_synced_at)->diffForHumans() ?: '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if ($provider)
                        <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card p-4">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('WABA account') }}</div>
                            <div class="text-[12px] text-ink-900 font-medium">{{ $provider->meta_json['verified_name'] ?? ($provider->display_label ?? 'Connected WABA') }}</div>
                            <div class="text-[12px] text-ink-500">{{ $provider->phone_number ?? ($provider->meta_json['display_phone_number'] ?? '') }}</div>
                        </div>
                    @elseif (!$wabaSubmittable)
                        <div class="border border-amber-200 bg-amber-50 rounded-2xl px-4 py-3 text-[12px] text-amber-900">
                            No WABA account connected. <button type="button" data-connect-device class="font-semibold underline cursor-pointer">{{ __('Connect one') }}</button> to submit templates to Meta.
                        </div>
                    @endif
                @endif

                {{-- Related links --}}
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">{{ __('Related') }}</div>
                    <a href="{{ route('user.templates.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] text-ink-700 hover:bg-paper-50">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 4l-4 4 4 4" /></svg>
                        {{ __('Back to templates') }}
                    </a>
                    <a href="{{ route('user.templates.edit', $template->id) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] text-ink-700 hover:bg-paper-50 {{ $isPending ? 'opacity-50 pointer-events-none' : '' }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 3l2 2-7 7H4v-2z" /></svg>
                        {{ __('Edit template') }}
                    </a>
                    @if ($isApproved)
                        <a href="{{ route('user.broadcasts.create') ?? '#' }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-[13px] text-wa-deep font-semibold hover:bg-paper-50">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 6v4l8 3V3L2 6Z" /><path d="M10 5l3-1v8l-3-1" /></svg>
                            {{ __('Use in a broadcast') }}
                        </a>
                    @endif
                </div>
            </aside>

            {{-- =============== MAIN =============== --}}
            <section class="space-y-5 min-w-0">

                {{-- Page header (SLA-style: breadcrumb + big title + actions) --}}
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2 flex items-center gap-2">
                            <a href="{{ route('user.templates.index') }}" class="hover:text-wa-deep">{{ __('Templates') }}</a>
                            <span class="text-ink-400">/</span>
                            <span class="text-ink-700 normal-case tracking-normal">{{ __('Detail') }}</span>
                        </div>
                        <h1 class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none text-ink-900 break-words">{{ $template->template_name }}</h1>
                        <p class="text-[11px] text-ink-500 mt-2 font-mono uppercase tracking-[0.12em]">
                            {{ $isInstagram ? 'INSTAGRAM' : strtoupper($template->meta_category ?: $template->category) }} ·
                            {{ $template->language ?: 'en_US' }} · {{ ucfirst($template->template_type ?: 'standard') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        @unless ($isInstagram)
                            <button type="button" data-refresh-now
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-800">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" data-refresh-icon>
                                    <path d="M13.5 3.5v3h-3M2.5 12.5v-3h3" /><path d="M12.4 6a4.5 4.5 0 0 0-8.2-.8M3.6 10a4.5 4.5 0 0 0 8.2.8" />
                                </svg>
                                {{ __('Refresh') }}
                            </button>
                        @endunless
                        <a href="{{ route('user.templates.edit', $template->id) }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[12px] font-semibold bg-wa-deep text-paper-0 hover:bg-wa-teal {{ $isPending ? 'opacity-50 pointer-events-none' : '' }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 3l2 2-7 7H4v-2z" /></svg>
                            {{ __('Edit') }}
                        </a>
                    </div>
                </div>

        {{-- ============================================================ --}}
        {{-- BANNERS ----------------------------------------------------- --}}
        {{-- ============================================================ --}}

        {{-- Meta submission error — set by store()/submit via withErrors('meta').
             Shows the REAL reason (lint failure, Meta rejection, no WABA) that
             the template never got a Template ID. --}}
        @if ($errors->has('meta'))
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="9" /><path d="M12 7v5M12 16v.5" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-rose-900">{{ __('Meta submission failed') }}</div>
                    <div class="text-[12px] text-rose-800 mt-0.5 whitespace-pre-line">{{ $errors->first('meta') }}</div>
                </div>
            </div>
        @endif

        {{-- Not on Meta yet — the initial submit failed, so there's no
             meta_template_id and the row can't be reviewed or sent. Offer a
             one-click resubmit (POST templates.submit → submitToMetaAction). --}}
        @if ($notSubmitted && $wabaSubmittable)
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 flex flex-wrap items-center gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M12 3L2 20h20L12 3z" /><path d="M12 9v4M12 16.5v.5" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-amber-900">{{ __('Not submitted to Meta yet') }}</div>
                    <div class="text-[12px] text-amber-800 mt-0.5">
                        {{ __('This template is saved locally but has no Meta Template ID — Meta never accepted it, which is why the status shows blank and it can’t be reviewed or sent. Submit it now to start the review.') }}
                    </div>
                </div>
                {{-- Multi-WABA: when >1 WhatsApp Business account is connected, let the
                     operator pick WHICH one to (re)submit this template on. The media
                     header/carousel upload + the Meta submit both use it (submitToMeta →
                     resolveTemplateConfig reads this provider_config_id first). Pre-selects
                     the template's remembered WABA. Hidden with 0/1 WABA (uses primary). --}}
                <form method="POST" action="{{ route('user.templates.submit', $template->id) }}"
                    class="shrink-0 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    @csrf
                    @if (($wabaConfigs ?? collect())->count() > 1)
                        <select name="provider_config_id" aria-label="{{ __('WhatsApp Business account') }}"
                            class="px-3 py-1.5 rounded-full text-[12px] font-medium border border-amber-300 bg-white text-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-500/30">
                            @foreach ($wabaConfigs as $wc)
                                <option value="{{ $wc->id }}" @selected((int) ($selectedWabaId ?? 0) === (int) $wc->id)>
                                    {{ $wc->display_label ?: ('WABA #' . $wc->id) }}{{ $wc->phone_number ? ' · ' . $wc->phone_number : '' }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[12px] font-semibold bg-amber-600 text-paper-0 hover:bg-amber-700">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 8h9M8 5l3 3-3 3M11 3h1a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-1" />
                        </svg>
                        {{ __('Submit to Meta') }}
                    </button>
                </form>
            </div>
        @elseif ($notSubmitted)
            {{-- No WABA connected → can't submit; point them to connect one. --}}
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M12 3L2 20h20L12 3z" /><path d="M12 9v4M12 16.5v.5" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-amber-900">{{ __('Not submitted to Meta yet') }}</div>
                    <div class="text-[12px] text-amber-800 mt-0.5">
                        {{ __('This template has no Meta Template ID. Connect a WhatsApp Business (WABA) account, then submit it for review.') }}
                        <button type="button" data-connect-device class="font-semibold underline cursor-pointer">{{ __('Connect one') }}</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($isPending && $onMeta)
            <div class="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-sky-600 flex-shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-sky-900">{{ __('Submitted for Meta review') }}</div>
                    <div class="text-[12px] text-sky-800 mt-0.5">
                        {{ __('Most templates are approved within minutes — Meta allows up to 48 hours. This page refreshes automatically every 30 seconds while the status is pending.') }}
                    </div>
                    @if ($template->submitted_at)
                        <div class="text-[11px] text-sky-700 mt-1">
                            Submitted {{ $template->submitted_at->diffForHumans() }}
                            @if ($template->last_synced_at)
                                · last checked {{ $template->last_synced_at->diffForHumans() }}
                            @endif
                        </div>
                    @endif
                </div>
                <button type="button" data-refresh-now
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium border border-sky-300 bg-paper-0 hover:bg-sky-100 text-sky-800">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 8a5 5 0 019-3M13 8a5 5 0 01-9 3M12 4v3h-3M4 12V9h3" />
                    </svg>
                    Refresh now
                </button>
            </div>
        @endif

        @if ($isRejected)
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
                <div class="flex items-start gap-3">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M9 9l6 6M15 9l-6 6" />
                    </svg>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] font-semibold text-rose-900">{{ __('Rejected by Meta') }}</div>
                        @if ($rejectionFriendly)
                            <div class="text-[12px] text-rose-800 mt-0.5">{{ $rejectionFriendly }}</div>
                        @endif
                        @if ($template->rejection_reason_code)
                            <div class="text-[11px] font-mono text-rose-700 mt-1">Code:
                                {{ $template->rejection_reason_code }}</div>
                        @endif
                    </div>
                    <a href="{{ route('user.templates.edit', $template->id) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium bg-rose-600 text-paper-0 hover:bg-rose-700">
                        Edit & resubmit
                    </a>
                </div>
            </div>
        @endif

        @if ($isPaused)
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M10 8v8M14 8v8" />
                </svg>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-amber-900">{{ __('Paused by Meta') }}</div>
                    <div class="text-[12px] text-amber-800 mt-0.5">
                        Repeated negative feedback (blocks/spam reports) paused this template. Quality score must
                        recover before it can be sent again.
                        @if ($template->paused_until)
                            Auto-unpause after {{ $template->paused_until->format('M j, H:i') }}.
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @foreach ($lintWarnings as $warning)
            <div
                class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 flex items-start gap-2.5 text-[12px] text-amber-900">
                <svg viewBox="0 0 16 16" class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <path d="M8 2L1 14h14z" />
                    <path d="M8 6v4M8 12v.5" />
                </svg>
                <span>{{ $warning }}</span>
            </div>
        @endforeach

                {{-- Preview card --}}
                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-hidden">
                    <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                        <h2 class="text-[13px] font-semibold text-ink-900">{{ __('Preview') }}</h2>
                        <span class="text-[11px] text-ink-500">{{ $isInstagram ? __('How it appears on Instagram') : __('How it appears on WhatsApp') }}</span>
                    </div>
                    <div class="p-5 bg-[#ECE5DD]">
                        @php
                            // WhatsApp-style formatting — same as the list cards:
                            // *bold* _italic_ ~strike~ and {{variable}} pills. Escape first.
                            $fmt = e((string) $template->template_body);
                            $fmt = preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $fmt);
                            $fmt = preg_replace('/_(.+?)_/s', '<em>$1</em>', $fmt);
                            $fmt = preg_replace('/~(.+?)~/s', '<del>$1</del>', $fmt);
                            $fmt = preg_replace('/\{\{\s*([^{}]+?)\s*\}\}/', '<span class="inline-block px-1 rounded bg-wa-bubble text-wa-deep font-medium">{{$1}}</span>', $fmt);
                            $fmt = nl2br($fmt);
                            $att = strtolower((string) $template->attachment_type);
                            $hasMedia = in_array($att, ['image', 'video', 'document', 'media', 'location'], true);
                        @endphp
                        {{-- Received-message bubble (tail on the top-left, like an inbound WA msg) --}}
                        <div class="w-full max-w-sm bg-white rounded-lg rounded-tl-sm shadow-sm px-3 py-2.5">
                            @if ($hasMedia)
                                {{-- Prefer an uploaded header image; else fall back to Meta's OWN
                                     approved sample captured at sync (header_sample_url) — the exact
                                     image this template was approved with, and the one the send
                                     uploads to /media. Without this fallback the preview showed the
                                     grey placeholder even when we DID hold an image. --}}
                                @php
                                    $previewImg = $att === 'image'
                                        ? ($template->attachment_file
                                            ? media_url($template->attachment_file)
                                            : ($template->header_sample_url ?: null))
                                        : null;
                                @endphp
                                @if ($previewImg)
                                    <img src="{{ $previewImg }}"
                                        class="w-full max-h-44 object-cover rounded-md mb-2" alt="{{ __('header media') }}">
                                @else
                                    <div class="mb-2 h-24 rounded-md bg-paper-100 border border-paper-200 grid place-items-center text-ink-400">
                                        <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.4">
                                            @if ($att === 'video')
                                                <rect x="2" y="3.5" width="12" height="9" rx="1.5" /><path d="M6.5 6.5 10 8l-3.5 1.5z" fill="currentColor" stroke="none" />
                                            @elseif ($att === 'document')
                                                <path d="M4 1.5h5l3 3V14a.5.5 0 0 1-.5.5h-7A.5.5 0 0 1 4 14zM9 1.5V4.5h3M6 8h4M6 10.5h4" />
                                            @elseif ($att === 'location')
                                                <path d="M8 14s4.5-4 4.5-7A4.5 4.5 0 0 0 8 2.5 4.5 4.5 0 0 0 3.5 7c0 3 4.5 7 4.5 7z" /><circle cx="8" cy="7" r="1.5" />
                                            @else
                                                <rect x="2" y="3" width="12" height="10" rx="1.5" /><circle cx="6" cy="6.5" r="1.2" /><path d="m3 12 3-3 2.5 2 2-1.5L13 12" />
                                            @endif
                                        </svg>
                                    </div>
                                @endif
                            @endif
                            @if ($template->header && !$hasMedia)
                                <div class="text-[13px] font-semibold text-ink-900 leading-snug mb-1 break-words">{{ $template->header }}</div>
                            @endif
                            <div class="text-[13px] leading-[1.5] text-ink-800 break-words">{!! $fmt !== '' ? $fmt : '<span class="text-ink-400">' . e(__('No body text')) . '</span>' !!}</div>
                            @if ($template->footer)
                                <div class="text-[11px] text-ink-400 mt-1.5 break-words">{{ $template->footer }}</div>
                            @endif
                            <div class="text-[10px] text-ink-400 flex items-center justify-end gap-1 mt-1">
                                <span>10:30</span>
                                <svg viewBox="0 0 18 12" class="w-3.5 h-3 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M1 6.5 4 9.5 9.5 3" /><path d="M7.5 9.5 8.5 8.5M9 6.5 13.5 2" /></svg>
                            </div>
                        </div>
                        @if (is_array($template->buttons) && count($template->buttons))
                            {{-- Interactive buttons render as tappable rows BELOW the bubble --}}
                            <div class="w-full max-w-sm space-y-1 mt-1">
                                @foreach ($template->buttons as $btn)
                                    <div class="bg-white rounded-lg shadow-sm text-center text-[13px] text-wa-deep font-medium py-2 flex items-center justify-center gap-1.5 break-words">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5">
                                            @if (($btn['type'] ?? '') === 'URL' || !empty($btn['url']))
                                                <path d="M6.5 9.5 9.5 6.5M7 4.5 8.5 3a2.5 2.5 0 0 1 3.5 3.5L10.5 8M9 11.5 7.5 13A2.5 2.5 0 0 1 4 9.5L5.5 8" />
                                            @elseif (($btn['type'] ?? '') === 'PHONE_NUMBER' || !empty($btn['phone_number']))
                                                <path d="M4 2.5c0 5 4.5 9.5 9.5 9.5v-2l-2.5-1-1.5 1a7 7 0 0 1-3-3l1-1.5-1-2.5z" />
                                            @else
                                                <path d="M13 8a5 5 0 1 1-1.5-3.5M13 3v2h-2" />
                                            @endif
                                        </svg>
                                        <span class="truncate">{{ $btn['text'] ?? __('Button') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($template->template_type === 'carousel' && is_array($template->carousel_data) && count($template->carousel_data))
                            <div class="mt-3 flex gap-3 overflow-x-auto pb-2 -mx-2 px-2">
                                @foreach ($template->carousel_data as $card)
                                    <div
                                        class="min-w-[200px] max-w-[200px] bg-paper-0 rounded-lg shadow-card overflow-hidden flex-shrink-0">
                                        @if (!empty($card['image']))
                                            <img src="{{ media_url($card['image']) }}"
                                                class="w-full h-28 object-cover" alt="">
                                        @endif
                                        <div class="px-3 py-2 text-[12px] text-ink-800">{{ $card['body'] ?? '' }}
                                        </div>
                                        @if (!empty($card['buttons']))
                                            <div class="border-t border-paper-200">
                                                @foreach ($card['buttons'] as $b)
                                                    <div
                                                        class="px-3 py-2 text-[12px] text-center text-sky-600 border-b last:border-b-0 border-paper-100">
                                                        {{ $b['text'] ?? '' }}</div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Components breakdown --}}
                <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 shadow-card">
                    <div class="px-5 py-3 border-b border-paper-200">
                        <h2 class="text-[13px] font-semibold text-ink-900">{{ __('Components') }}</h2>
                    </div>
                    <dl class="divide-y divide-paper-100 text-[12px]">
                        @if ($template->header)
                            <div class="px-5 py-3 grid grid-cols-[100px_1fr] gap-3">
                                <dt class="text-ink-500 font-medium">{{ __('Header') }}</dt>
                                <dd class="text-ink-800">{{ $template->header }}</dd>
                            </div>
                        @endif
                        <div class="px-5 py-3 grid grid-cols-[100px_1fr] gap-3">
                            <dt class="text-ink-500 font-medium">{{ __('Body') }}</dt>
                            <dd class="text-ink-800 whitespace-pre-line">{{ $template->template_body }}</dd>
                        </div>
                        @if ($template->footer)
                            <div class="px-5 py-3 grid grid-cols-[100px_1fr] gap-3">
                                <dt class="text-ink-500 font-medium">{{ __('Footer') }}</dt>
                                <dd class="text-ink-800">{{ $template->footer }}</dd>
                            </div>
                        @endif
                        @if (is_array($template->buttons) && count($template->buttons))
                            <div class="px-5 py-3 grid grid-cols-[100px_1fr] gap-3">
                                <dt class="text-ink-500 font-medium">{{ __('Buttons') }}</dt>
                                <dd class="text-ink-800 space-y-1">
                                    @foreach ($template->buttons as $btn)
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[10px] font-mono uppercase tracking-wider px-1.5 py-0.5 rounded bg-paper-100 text-ink-600">{{ $btn['type'] ?? 'quick_reply' }}</span>
                                            <span>{{ $btn['text'] ?? '' }}</span>
                                            @if (!empty($btn['value']))
                                                <span class="text-ink-500 text-[11px]">→ {{ $btn['value'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

            </section>
        </div>
    </main>

</x-layouts.user>
