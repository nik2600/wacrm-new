@php
    /** @var \Illuminate\Support\Collection $devices */
    $statusPill = [
        'connected' => [
            'bg' => 'bg-wa-mint',
            'text' => 'text-wa-deep',
            'border' => '',
            'dot' => 'bg-wa-green',
            'label' => 'Connected',
        ],
        'disconnected' => [
            'bg' => 'bg-paper-50',
            'text' => 'text-ink-500',
            'border' => '',
            'dot' => 'bg-paper-200',
            'label' => 'Disconnected',
        ],
        'needs_pair' => [
            'bg' => 'bg-accent-amber/15',
            'text' => 'text-[#7B5A14]',
            'border' => 'border border-accent-amber/40',
            'dot' => 'bg-accent-amber',
            'label' => 'Needs re-pair',
        ],
        'failed' => [
            'bg' => 'bg-accent-coral/15',
            'text' => 'text-[#A1431F]',
            'border' => 'border border-accent-coral/40',
            'dot' => 'bg-accent-coral',
            'label' => 'Failed',
        ],
    ];

    // Rotating accent palette for the device icon avatar — picks a
    // colour per device id so the same row paints the same swatch
    // across reloads. Mirrors the four swatches in the mockup
    // (mint / blue / purple / paper).
    $accentPalette = [
        ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep'],
        ['bg' => 'bg-[#D9E5F2]', 'text' => 'text-[#13478A]'],
        ['bg' => 'bg-[#F3E9FF]', 'text' => 'text-[#5B3D8A]'],
        ['bg' => 'bg-paper-100', 'text' => 'text-ink-700'],
    ];
@endphp

@forelse ($devices as $d)
    @php
        $pill = $statusPill[$d->status] ?? $statusPill['disconnected'];
        $active = (bool) $d->active;
        $accent =
            $d->status === 'connected'
                ? $accentPalette[$d->id % 3] // connected rows: rotate among the three colourful swatches
                : $accentPalette[3]; // disconnected/failed/needs_pair: neutral paper swatch

        // Resolve the assigned user — eager-loaded by the controller
        // when possible, falls back to a per-row Find for legacy rows
        // that don't have assigned_user_id set.
$assignee = null;
$assignedId = $d->assigned_user_id ?? ($d->user_id ?? null);
if ($assignedId) {
    $assignee = $d->relationLoaded('assignedUser') ? $d->assignedUser : \App\Models\User::find($assignedId);
}
$assigneeName = $assignee?->name ?: 'Unassigned';
$assigneeInitials =
    mb_strtoupper(
        mb_substr(
            collect(preg_split('/\s+/', $assigneeName))
                ->map(fn($p) => mb_substr($p, 0, 1))
                ->take(2)
                ->implode(''),
            0,
            2,
        ),
    ) ?:
    '?';

// Last-active label — relative ("just now" / "2 min ago") and
// an absolute clock time underneath ("14:42 IST"). Falls back
// to "—" when the device has never been seen.
$lastSeen = $d->last_seen_at;
$lastRelative = $lastSeen ? $lastSeen->diffForHumans(short: true) : '—';
$lastAbsolute = $lastSeen
    ? $lastSeen->copy()->setTimezone(config('app.timezone'))->format('H:i') . ' ' . now()->format('T')
    : ($active
        ? 'live'
        : 'disconnected');

// Device meta line (under the name) — Region · model / "Test number"-style
// hint. We don't have a model field yet, so just show region + a
        // role hint based on active state.
        $metaLine = trim(($d->region ?: '') . ($active ? ' · active' : ''), ' ·') ?: 'Spare line';
    @endphp
    <div class="device-row min-w-[900px] grid grid-cols-[40px_1.4fr_150px_140px_120px_90px_140px_220px] items-center gap-3 px-4 py-3 border-b border-paper-200 last:border-0 hover:bg-paper-50/60"
        data-device-id="{{ $d->id }}">
        {{-- ☑️ row checkbox (for the future bulk-action bar) --}}
        <div class="px-1">
            <input type="checkbox" data-device-select="{{ $d->id }}"
                class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep">
        </div>

        {{-- Device: coloured icon avatar + name + meta line --}}
        <div class="min-w-0 flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-lg grid place-items-center shrink-0 {{ $accent['bg'] }} {{ $accent['text'] }}">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                    <circle cx="8" cy="11.5" r="0.8" />
                </svg>
            </span>
            <div class="min-w-0">
                <div class="font-semibold text-ink-900 text-[12.5px] truncate">{{ $d->device_name }}</div>
                <div class="text-[10.5px] text-ink-500 font-mono truncate">@if (!empty($channelTag))<span
                            class="text-wa-deep">{{ __('Unofficial API') }}</span> · @endif{{ $metaLine }}</div>
            </div>
        </div>

        {{-- Mobile number (masked — last 4 digits only) --}}
        <div class="font-mono text-[11.5px] text-ink-700 truncate">{{ mask_phone($d->display_phone) }}</div>

        {{-- User (avatar + name) --}}
        <div class="flex items-center gap-2 min-w-0">
            <span
                class="w-6 h-6 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[9px] font-semibold shrink-0">{{ $assigneeInitials }}</span>
            <span class="text-[12px] text-ink-700 truncate">{{ $assigneeName }}</span>
        </div>

        {{-- Last active — relative on top, absolute clock under --}}
        <div class="min-w-0">
            <div class="font-mono text-[11.5px] {{ $lastSeen ? 'text-ink-900' : 'text-ink-500' }} truncate">
                {{ $lastRelative }}</div>
            <div class="text-[10px] text-ink-500 font-mono truncate">{{ $lastAbsolute }}</div>
        </div>

        {{-- Sent (24h) --}}
        <div class="font-mono text-[11.5px] {{ $d->sent_24h > 0 ? 'text-ink-900' : 'text-ink-500' }}">
            {{ $d->sent_24h > 0 ? number_format($d->sent_24h) : '—' }}
        </div>

        {{-- Status pill --}}
        <div>
            <span
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $pill['bg'] }} {{ $pill['text'] }} {{ $pill['border'] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $pill['dot'] }}"></span>{{ $pill['label'] }}
            </span>
        </div>

        {{-- Actions: Analytics · QR · Edit · Refresh · (Connect / Disconnect) · Delete --}}
        <div class="flex items-center gap-0.5 justify-end whitespace-nowrap">
            <a href="{{ route('user.devices.detail', $d->id) }}"
                class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                title="{{ __('View analytics') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                    <circle cx="8" cy="8" r="2" />
                </svg>
            </a>

            @if ($active)
                {{-- Connected: refresh QR / edit / disconnect / delete --}}
                <button data-device-connect="{{ $d->id }}" data-name="{{ $d->device_name }}" type="button"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Show QR / re-pair') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="2" width="5" height="5" />
                        <rect x="9" y="2" width="5" height="5" />
                        <rect x="2" y="9" width="5" height="5" />
                        <path d="M9 9h2v2M11 13v1M13 9h1M13 11v3" />
                    </svg>
                </button>
                <a href="{{ route('user.devices.detail', $d->id) }}"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Edit') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                    </svg>
                </a>
                <button data-device-toggle="{{ $d->id }}" type="button"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Toggle active') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                    </svg>
                </button>
                <button data-device-disconnect="{{ $d->id }}" type="button"
                    class="w-7 h-7 rounded-full hover:bg-accent-amber/15 text-[#7B5A14] inline-flex items-center justify-center"
                    title="{{ __('Disconnect') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M5 7L3 9l4 4 2-2M11 9l2-2-4-4-2 2" />
                    </svg>
                </button>
            @else
                {{-- Disconnected: QR connect / edit / delete --}}
                <button data-device-connect="{{ $d->id }}" data-name="{{ $d->device_name }}" type="button"
                    class="w-7 h-7 rounded-full hover:bg-wa-mint text-wa-deep inline-flex items-center justify-center"
                    title="{{ __('Connect') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="2" width="5" height="5" />
                        <rect x="9" y="2" width="5" height="5" />
                        <rect x="2" y="9" width="5" height="5" />
                        <path d="M9 9h2v2M11 13v1M13 9h1M13 11v3" />
                    </svg>
                </button>
                <a href="{{ route('user.devices.detail', $d->id) }}"
                    class="w-7 h-7 rounded-full hover:bg-paper-100 inline-flex items-center justify-center"
                    title="{{ __('Edit') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                    </svg>
                </a>
            @endif

            <button data-device-delete="{{ $d->id }}" data-name="{{ $d->device_name }}" type="button"
                class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center"
                title="{{ __('Delete') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                </svg>
            </button>
        </div>
    </div>
@empty
    {{-- Suppress the "no devices" empty-state when the caller is appending
         connected WABA/Twilio channel rows after this table (multi-engine) —
         otherwise the empty-state shows ABOVE the connected channels. --}}
    @unless (!empty($hideEmpty))
        @include('user.partials.empty-state', [
            'class' => 'm-4',
            'message' => 'No devices match the current filters. Try clearing filters or pair a new device.',
            'resetHref' => url('/devices'),
            'actionButtonAttrs' => 'onclick="document.getElementById(\'devices-add-btn\')?.click()"',
            'actionLabel' => 'Add device',
        ])
    @endunless
@endforelse
