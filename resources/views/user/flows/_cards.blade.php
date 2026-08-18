@php
    /**
     * Flow card markup — visual design mirrors the static mockup at
     * tutot (3)/flows.html. Round category icon · status pill ·
     * title + description · 3-item check bullet list · "Open
 * builder" CTA + inline icon actions.
     *
     * The data-* hooks (data-flow-toggle, data-flow-delete,
     * data-flow-card) are unchanged so the existing JS keeps working.
     */
    $categoryStyles = [
        'welcome' => [
            'icon' => 'M3 11s2-1 5-1 5 1 5 1M5 6.5h.01M11 6.5h.01M8 9.5s1 .8 0 1.5',
            'bg' => 'bg-wa-bubble',
            'text' => 'text-wa-deep',
        ],
        'cart' => ['icon' => 'M3 5h8l1 6H4z', 'bg' => 'bg-wa-mint', 'text' => 'text-wa-deep'],
        'cart-recovery' => ['icon' => 'M3 5h8l1 6H4z', 'bg' => 'bg-wa-mint', 'text' => 'text-wa-deep'],
        'post-purchase' => [
            'icon' => 'M8 1.5l1.6 4.2H14l-3.5 2.5 1.4 4.3L8 9.9l-3.9 2.6 1.4-4.3L2 5.7h4.4z',
            'bg' => 'bg-[#FCE0D5]',
            'text' => 'text-[#A1431F]',
        ],
        're-engagement' => [
            'icon' => 'M3 8a5 5 0 0 1 8.5-3.5L13 6M13 3v3h-3M13 8a5 5 0 0 1-8.5 3.5L3 10M3 13v-3h3',
            'bg' => 'bg-[#F3E9FF]',
            'text' => 'text-[#5B3D8A]',
        ],
        'lead' => [
            'icon' => 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 14c0-3 2.7-5 6-5s6 2 6 5',
            'bg' => 'bg-[#D9E5F2]',
            'text' => 'text-[#13478A]',
        ],
        'lead-nurture' => [
            'icon' => 'M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 14c0-3 2.7-5 6-5s6 2 6 5',
            'bg' => 'bg-[#D9E5F2]',
            'text' => 'text-[#13478A]',
        ],
        'event' => ['icon' => 'M3 4h10v9H3zM3 7h10M6 2v3M10 2v3', 'bg' => 'bg-wa-bubble', 'text' => 'text-wa-deep'],
        'special-events' => [
            'icon' => 'M3 4h10v9H3zM3 7h10M6 2v3M10 2v3',
            'bg' => 'bg-wa-bubble',
            'text' => 'text-wa-deep',
        ],
    ];
    $stateStyle = [
        'live' => [
            'bg' => 'bg-wa-green/10',
            'text' => 'text-wa-deep',
            'border' => 'border-wa-green/30',
            'dot' => 'bg-wa-green',
            'label' => 'Live',
        ],
        'paused' => [
            'bg' => 'bg-[#EFE5F5]',
            'text' => 'text-[#5B3D8A]',
            'border' => 'border-[#D9C7E8]',
            'dot' => 'bg-[#5B3D8A]',
            'label' => 'Paused',
        ],
        'draft' => [
            'bg' => 'bg-paper-50',
            'text' => 'text-ink-500',
            'border' => 'border-paper-200',
            'dot' => 'bg-paper-300',
            'label' => 'Draft',
        ],
    ];
@endphp

@forelse ($flows as $flow)
    @php
        $state = $flow->is_published ? ($flow->is_active ? 'live' : 'paused') : 'draft';
        $sty = $stateStyle[$state];
        $cat = $flow->category ?: 'uncategorized';
        $catSty = $categoryStyles[$cat] ?? [
            'icon' => 'M2 4h12v8H2zM2 7h12',
            'bg' => 'bg-paper-100',
            'text' => 'text-ink-700',
        ];

        $decoded = $flow->decoded_flow_data ?? [];
        $nodeCount = is_array($decoded['flowNodes'] ?? null) ? count($decoded['flowNodes']) : 0;
        $edgeCount = is_array($decoded['flowEdges'] ?? null) ? count($decoded['flowEdges']) : 0;

        // Short purpose copy — derived from category since we don't
// have a `description` column. Falls back to a generic line.
$purpose = match ($cat) {
    'welcome' => 'Routes new subscribers into catalog, support, or first-order incentive paths.',
    'cart', 'cart-recovery' => 'Triggers after checkout drop-off with product context and a support fallback.',
    'post-purchase' => 'Follows up after delivery to drive reviews, repeat orders, and feedback.',
    're-engagement' => 'Re-activates dormant contacts with a personalised offer or check-in.',
    'lead', 'lead-nurture' => 'Nurtures new leads with educational touches before handing to sales.',
    'event', 'special-events' => 'Time-boxed broadcast around a launch, sale, or seasonal moment.',
    default => 'Automated WhatsApp journey built in the flow editor.',
        };
    @endphp
    <div class="flow-card bg-white border border-paper-200 rounded-[14px] p-[18px] transition flex flex-col hover:border-wa-deep hover:shadow-soft hover:-translate-y-px"
        data-flow-card="{{ $flow->id }}">
        <div class="flex items-start justify-between gap-2 mb-3">
            @if ($flow->flow_type === 'instagram')
                {{-- Native Instagram flow (flow_type=instagram) — Instagram glyph in brand gradient. --}}
                <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-white"
                    style="background:linear-gradient(135deg,#833AB4,#E1306C,#F77737)">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.9">
                        <rect x="3" y="3" width="18" height="18" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                    </svg>
                </div>
            @else
                <div
                    class="w-9 h-9 rounded-full {{ $catSty['bg'] }} {{ $catSty['text'] }} flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="{{ $catSty['icon'] }}" />
                    </svg>
                </div>
            @endif
            <div class="flex items-center gap-1.5 flex-wrap justify-end">
                @if ($flow->flow_type === 'instagram')
                    <span class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border"
                        style="background:#FCE7F3;color:#9D174D;border-color:#F9A8D4">
                        <span class="w-1.5 h-1.5 rounded-full" style="background:#E1306C"></span>{{ __('Instagram') }}
                    </span>
                @elseif ($flow->flow_type === 'call')
                    <span class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border bg-accent-amber/15 text-[#7B5A14] border-accent-amber/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>{{ __('Call') }}
                    </span>
                @endif
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border {{ $sty['bg'] }} {{ $sty['text'] }} {{ $sty['border'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $sty['dot'] }}"></span>{{ $sty['label'] }}
                </span>
            </div>
        </div>

        <div class="text-[14px] font-semibold mb-1">{{ $flow->flow_name ?: __('Untitled flow') }}</div>
        <p class="text-[12.5px] text-ink-500 leading-relaxed">{{ $purpose }}</p>

        @php
            // Audience trigger label — derived from flows.trigger_kind which
            // is denormed at save time from the trigger node's data.kind.
$triggerLabel = match ($flow->trigger_kind) {
    'tag_added' => 'Auto · on tag',
    'group_join' => 'Auto · on group join',
    'manual_enroll' => 'Manual enroll',
    default => 'Keyword',
};
$hasSubscribers =
    $flow->active_subscriber_count + $flow->completed_subscriber_count + $flow->failed_subscriber_count > 0;
$isManual = $flow->trigger_kind === 'manual_enroll';
        @endphp

        <ul class="mt-3 space-y-1 text-[12px] text-ink-600">
            <li class="flex items-center gap-2">
                <svg viewBox="0 0 12 12" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                    stroke-width="1.8">
                    <path d="M2 6.5l3 2 5-6" />
                </svg>
                {{ number_format($nodeCount) }} {{ \Illuminate\Support\Str::plural('node', $nodeCount) }} ·
                {{ number_format($edgeCount) }} {{ \Illuminate\Support\Str::plural('edge', $edgeCount) }}
            </li>
            <li class="flex items-center gap-2">
                <svg viewBox="0 0 12 12" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                    stroke-width="1.8">
                    <path d="M2 6.5l3 2 5-6" />
                </svg>
                {{ $triggerLabel }}
            </li>
            <li class="flex items-center gap-2">
                <svg viewBox="0 0 12 12" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                    stroke-width="1.8">
                    <path d="M2 6.5l3 2 5-6" />
                </svg>
                @if ($hasSubscribers)
                    <span class="font-mono">{{ $flow->active_subscriber_count }}</span> active ·
                    <span class="font-mono">{{ $flow->completed_subscriber_count }}</span> done
                    @if ($flow->failed_subscriber_count > 0)
                        · <span class="text-accent-coral font-mono">{{ $flow->failed_subscriber_count }}</span> failed
                    @endif
                @else
                    Edited {{ $flow->updated_at?->diffForHumans() ?? 'just now' }}
                @endif
            </li>
        </ul>

        <div class="mt-4 flex items-center gap-2">
            @if ($isManual)
                <button type="button" data-flow-enroll="{{ $flow->id }}" data-name="{{ $flow->flow_name }}"
                    class="flex-1 text-center rounded-full px-3 py-1.5 text-[11.5px] font-semibold bg-wa-deep text-paper-0 hover:bg-wa-teal">{{ __('Enroll contacts') }}</button>
            @else
                <a href="{{ url('/flows/builder/' . $flow->id) }}"
                    class="flex-1 text-center hairline border border-paper-200 rounded-full px-3 py-1.5 text-[11.5px] font-medium hover:bg-paper-50">{{ __('Open builder') }}</a>
            @endif

            <button type="button" data-flow-toggle="{{ $flow->id }}"
                class="hairline border border-paper-200 rounded-full w-7 h-7 hover:bg-paper-50 flex items-center justify-center"
                title="{{ $flow->is_active ? __('Pause') : __('Resume') }}">
                @if ($flow->is_active)
                    <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                        <rect x="3" y="2" width="2" height="8" rx="0.5" />
                        <rect x="7" y="2" width="2" height="8" rx="0.5" />
                    </svg>
                @else
                    <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                        <polygon points="3,2 10,6 3,10" />
                    </svg>
                @endif
            </button>

            <form method="POST" action="{{ url('/flows/' . $flow->id . '/duplicate') }}" class="inline">
                @csrf
                <button type="submit"
                    class="hairline border border-paper-200 rounded-full w-7 h-7 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Duplicate') }}">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <rect x="3" y="3" width="8" height="9" rx="1" />
                        <path d="M5 3V2h8v9h-1" />
                    </svg>
                </button>
            </form>

            {{-- Export this flow to a portable .json (re-importable in any workspace). --}}
            <a href="{{ url('/flows/' . $flow->id . '/export') }}"
                class="hairline border border-paper-200 rounded-full w-7 h-7 hover:bg-paper-50 flex items-center justify-center"
                title="{{ __('Export flow (.json)') }}">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 9V2M5 5l3-3 3 3" /><path d="M2.5 11v1.5a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1V11" />
                </svg>
            </a>

            <button type="button" data-flow-delete="{{ $flow->id }}" data-name="{{ $flow->flow_name }}"
                class="hairline border border-paper-200 rounded-full w-7 h-7 hover:bg-accent-coral/10 hover:border-accent-coral hover:text-accent-coral flex items-center justify-center"
                title="{{ __('Delete') }}">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                </svg>
            </button>
        </div>
    </div>
@empty
    @include('user.partials.empty-state', [
        'class' => 'col-span-full',
        'message' =>
            'No flows match the current filters. Try clearing filters or create your first automated flow.',
        'resetHref' => url('/flows'),
        'actionHref' => url('/flows/builder'),
        'actionLabel' => 'Create flow',
    ])
@endforelse
