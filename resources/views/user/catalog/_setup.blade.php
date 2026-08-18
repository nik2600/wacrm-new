@php
    // Three possible states the Setup tab caters for:
    // meta = Meta Commerce catalog connected → full sync UI
    // baileys = no Meta but a Baileys device is paired → ready
    // none = no device at all → prompt to /devices
    $state = $catalog ? 'meta' : ($hasBaileysDevice ? 'baileys' : 'none');
@endphp

@if ($state === 'none')
    {{-- ───────────────────── NONE ─────────────────────── --}}
    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
        <div class="w-12 h-12 rounded-xl bg-accent-coral/15 grid place-items-center shrink-0">
            <svg viewBox="0 0 24 24" class="w-6 h-6 text-accent-coral" fill="none" stroke="currentColor"
                stroke-width="1.6">
                <path d="M20.5 12A8.5 8.5 0 1 1 4.6 16.3L3.5 20.5l4.3-1.1A8.5 8.5 0 0 0 20.5 12Z" />
                <path d="M12 8v4M12 16h.01" />
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-serif text-[22px] leading-tight">{{ __('Connect a device first') }}</div>
            <p class="text-[12.5px] text-ink-600 mt-1.5">
                {{ __('Catalog sending needs at least one WhatsApp device on this account — either an Unofficial API QR pair (free) or a Meta-verified WABA number. It only takes 30 seconds.') }}</p>
            <button type="button" data-connect-device
                class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">
                {{ __('Connect a device') }}
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M3 8h10M9 4l4 4-4 4" />
                </svg>
            </button>
        </div>
    </div>
@elseif ($state === 'baileys')
    {{-- ───────────────────── READY ─────────────── --}}
    @php $isOfficial = in_array(($engine ?? 'baileys'), ['waba', 'twilio'], true); @endphp
    <div class="bg-wa-deep text-paper-0 rounded-2xl p-6 shadow-soft flex items-center justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">✓ Ready to send</div>
            <div class="font-serif text-[24px] leading-tight mt-1">
                {{ $isOfficial ? __('Official WhatsApp catalog mode') : __('Unofficial API carousel mode') }}</div>
            <p class="text-[12.5px] text-paper-0/80 mt-2 max-w-2xl">
                You have {{ $devices->count() }}
                {{ \Illuminate\Support\Str::plural($isOfficial ? 'number' : 'device', $devices->count()) }}
                connected.
                @if ($isOfficial)
                    Catalog sends go out through your connected {{ strtoupper((string) $engine) }} number.
                    For interactive product messages, link a Meta Commerce Catalog below.
                @else
                    Catalog sends work right now via native WhatsApp carousels through the Unofficial API — no Meta setup,
                    no waiting for approval.
                @endif
                Head to the <b>Send tab</b> to push products to any customer's number.
            </p>
        </div>
        <a href="{{ route('user.catalog.send') }}"
            class="px-5 py-2.5 rounded-full bg-paper-0 text-wa-deep text-[12.5px] font-semibold inline-flex items-center gap-2 shrink-0">
            Open Send tab
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M3 8h10M9 4l4 4-4 4" />
            </svg>
        </a>
    </div>

    {{-- Connected devices summary --}}
    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Connected devices') }}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($devices as $d)
                @php $phone = trim(($d->country_code ?? '') . ' ' . ($d->phone_number ?? '')); @endphp
                <div class="border border-paper-200 rounded-xl p-3 flex items-center gap-3">
                    <span class="w-9 h-9 rounded-full bg-wa-bubble/70 grid place-items-center shrink-0">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <rect x="7" y="2" width="10" height="20" rx="2" />
                            <path d="M11 18h2" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-[13px] truncate">{{ $d->device_name ?: 'Device #' . $d->id }}
                        </div>
                        <div class="font-mono text-[11px] text-ink-500 truncate">{{ $phone ?: 'No number' }}</div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-mono shrink-0"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ $d->status }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Quick stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Products') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2">{{ number_format($totalProducts) }}</div>
            <a href="{{ url('/store/products') }}"
                class="text-[10.5px] text-wa-deep font-semibold hover:underline mt-1 inline-block">{{ __('Manage →') }}</a>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Shops') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2">{{ $shops->count() }}</div>
            <a href="{{ url('/connect?platform=wa-store') }}"
                class="text-[10.5px] text-wa-deep font-semibold hover:underline mt-1 inline-block">{{ __('Manage →') }}</a>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Recent sends') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2">{{ $recentSends->count() }}</div>
            <a href="{{ route('user.catalog.activity') }}"
                class="text-[10.5px] text-wa-deep font-semibold hover:underline mt-1 inline-block">{{ __('View →') }}</a>
        </div>
    </div>

    {{-- One-click link — a connected WABA number already carries the Meta
     token + catalog id, so the operator never needs to paste keys. --}}
    @if ($isOfficial && ($engine ?? '') === 'waba')
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-start gap-4 flex-wrap">
                <div class="w-11 h-11 rounded-xl bg-wa-mint grid place-items-center shrink-0">
                    <svg viewBox="0 0 24 24" class="w-6 h-6 text-wa-deep" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M4 7h16M4 12h16M4 17h10" />
                        <path d="M20 15l2 2-2 2" />
                    </svg>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <div class="font-serif text-[18px] leading-tight">{{ __('Link your Meta Commerce Catalog') }}</div>
                    <div class="text-[11.5px] text-ink-500 mt-1">
                        {{ __('Your WhatsApp number is already connected, so we can pull the catalog straight from Meta — no Catalog ID, WABA ID or access token to paste. Unlocks the official catalog button, cart, and the "order placed" webhook.') }}
                    </div>
                    @error('autodetect')
                        <div class="mt-2 text-[11.5px] text-accent-coral">{{ $message }}</div>
                    @enderror
                </div>
                <form method="POST" action="{{ route('user.catalog.autodetect') }}" class="shrink-0">
                    @csrf
                    <button type="submit"
                        class="px-5 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                        {{ __('Fetch from my WhatsApp account') }}
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Optional: connect Meta Commerce manually (fallback) --}}
    <details class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card" @error('autodetect') open @enderror>
        <summary class="cursor-pointer px-5 py-4 flex items-center justify-between gap-3 list-none">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Optional · advanced') }}</div>
                <div class="font-serif text-[18px] leading-tight mt-0.5">
                    {{ $isOfficial ? __('Or link a catalog manually') : __('Also link a Meta Commerce Catalog') }}</div>
                <div class="text-[11.5px] text-ink-500 mt-1">Unlocks the official catalog button on your WhatsApp
                    Business profile + cart + the "order placed" webhook. Only needed if you're on a verified WABA. The
                    Unofficial API works fine without it.</div>
            </div>
            <span class="text-[18px] text-ink-500 shrink-0">▾</span>
        </summary>
        <div class="px-5 pb-5 border-t border-paper-200">
            @include('user.catalog._connect-form', ['compact' => true])
        </div>
    </details>
@else
    {{-- ───────────────────── META CONNECTED ────────────── --}}
    @php
        $synced = (int) ($statusBuckets['synced'] ?? 0);
        $pending = (int) ($statusBuckets['pending'] ?? 0);
        $failed = (int) ($statusBuckets['failed'] ?? 0);
        $unsynced = (int) ($statusBuckets['unsynced'] ?? 0);
    @endphp

    <div class="bg-wa-deep text-paper-0 rounded-2xl p-5 shadow-soft flex items-center justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">Connected ·
                {{ strtoupper(str_replace('_', ' ', $catalog->provider)) }}</div>
            <div class="font-serif text-[22px] leading-tight mt-1">
                {{ $catalog->catalog_name ?: 'Meta Commerce Catalog' }}</div>
            <div class="font-mono text-[11px] text-paper-0/80 mt-1">ID {{ $catalog->catalog_id }}</div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.catalog.send') }}"
                class="px-3 py-2 rounded-full bg-paper-0/10 border border-paper-0/30 hover:bg-paper-0/20 text-paper-0 text-[11.5px] font-medium">{{ __('Send products →') }}</a>
            <form method="POST" action="{{ route('user.catalog.disconnect') }}"
                onsubmit="return confirm('Disconnect the catalog? Products stay on Meta; we just stop syncing.')">
                @csrf
                <button
                    class="px-3 py-2 rounded-full border border-paper-0/30 hover:bg-paper-0/10 text-paper-0 text-[11.5px]">{{ __('Disconnect') }}</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Total') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2" data-stat-total>{{ number_format($totalProducts) }}
            </div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Synced') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2 text-wa-deep" data-stat-synced>
                {{ number_format($synced) }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Pending') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2 text-accent-amber" data-stat-pending>
                {{ number_format($pending) }}</div>
        </div>
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Failed') }}</div>
            <div class="font-serif text-[28px] leading-none mt-2 text-accent-coral" data-stat-failed>
                {{ number_format($failed) }}</div>
        </div>
    </div>

    {{-- ── Catalog health ── A product with no image / no price / a sync
 error never shows to buyers — pure lost sales. Score = share of
 sellable rows; the list lets the operator fix the offenders. --}}
    @if (!empty($health) && (int) ($health['total'] ?? 0) > 0)
        @php
            $score = (int) $health['score'];
            $scoreCls = $score >= 90 ? 'text-wa-deep' : ($score >= 70 ? 'text-accent-amber' : 'text-accent-coral');
            $barCls = $score >= 90 ? 'bg-wa-deep' : ($score >= 70 ? 'bg-accent-amber' : 'bg-accent-coral');
            $ringCls = $score >= 90 ? 'border-wa-deep' : ($score >= 70 ? 'border-accent-amber' : 'border-accent-coral');
        @endphp
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-center gap-4 flex-wrap">
                <div class="w-16 h-16 rounded-full border-4 {{ $ringCls }} grid place-items-center shrink-0">
                    <span class="font-serif text-[22px] leading-none {{ $scoreCls }}">{{ $score }}</span>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <div class="font-serif text-[16px] leading-tight">{{ __('Catalog health') }}</div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                        @if (($health['flagged'] ?? 0) > 0)
                            {{ trans_choice('{1}:count product needs attention before it can sell.|[2,*]:count products need attention before they can sell.', $health['flagged'], ['count' => number_format($health['flagged'])]) }}
                        @else
                            {{ __('Every product is catalog-ready. Nice.') }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-wrap text-[11px]">
                    @if (($health['no_image'] ?? 0) > 0)
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-accent-coral/12 text-accent-coral font-medium">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                <circle cx="5.5" cy="6.5" r="1" />
                                <path d="m3 12 3.5-3.5 2.5 2.5L11 9l2 2" />
                            </svg>
                            {{ number_format($health['no_image']) }} {{ __('no image') }}
                        </span>
                    @endif
                    @if (($health['no_price'] ?? 0) > 0)
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-accent-amber/15 text-accent-amber font-medium">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M8 2v12M5 5.5h4.5a1.8 1.8 0 0 1 0 3.5H6a1.8 1.8 0 0 0 0 3.5h5" />
                            </svg>
                            {{ number_format($health['no_price']) }} {{ __('no price') }}
                        </span>
                    @endif
                    @if (($health['errored'] ?? 0) > 0)
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-accent-coral/12 text-accent-coral font-medium">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 5v3.5M8 11h.01" />
                            </svg>
                            {{ number_format($health['errored']) }} {{ __('sync error') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-3 h-1.5 bg-paper-100 rounded-full overflow-hidden">
                <div class="h-full {{ $barCls }} transition-all" style="width:{{ $score }}%"></div>
            </div>

            @if ($health['samples']->isNotEmpty())
                <details class="mt-4 group">
                    <summary
                        class="cursor-pointer list-none flex items-center gap-1.5 text-[11.5px] font-semibold text-wa-deep">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 transition-transform group-open:rotate-90"
                            fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m6 4 4 4-4 4" />
                        </svg>
                        {{ __('Show products to fix') }}
                    </summary>
                    <ul class="mt-2 divide-y divide-paper-200 border border-paper-200 rounded-xl overflow-hidden">
                        @foreach ($health['samples'] as $s)
                            @php
                                $reasons = [];
                                if (empty($s->image_url)) {
                                    $reasons[] = __('no image');
                                }
                                if (empty($s->price_minor)) {
                                    $reasons[] = __('no price');
                                }
                                if ($s->meta_sync_status === 'error') {
                                    $reasons[] = __('sync error');
                                }
                            @endphp
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 bg-paper-0">
                                <div class="min-w-0">
                                    <div class="font-medium text-[12.5px] truncate">{{ $s->name ?: '#' . $s->id }}
                                    </div>
                                    <div class="text-[10.5px] text-accent-coral font-mono">
                                        {{ implode(' · ', $reasons) }}</div>
                                </div>
                                <a href="{{ route('user.store.products.edit', $s->id) }}"
                                    class="shrink-0 text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Fix →') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card flex items-center gap-3 flex-wrap">
        <div class="flex-1 min-w-0">
            <div class="font-serif text-[16px] leading-tight">{{ __('Sync products to Meta') }}</div>
            <div class="text-[11.5px] text-ink-500 mt-0.5">Pushes up to 1000 per request, loops until done.
                {{ $unsynced + $failed }} waiting.</div>
        </div>
        <button type="button" data-sync-all
            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M2 8a6 6 0 0 1 11-3M14 8a6 6 0 0 1-11 3" />
                <path d="M13 2v3h-3M3 14v-3h3" />
            </svg>
            <span data-sync-all-label>{{ __('Sync to Meta') }}</span>
        </button>
        <button type="button" data-poll
            class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12.5px] font-medium inline-flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M2 8a6 6 0 1 1 12 0 6 6 0 0 1-12 0z" />
                <path d="m6 8 2 2 4-4" />
            </svg>
            <span data-poll-label>{{ __('Refresh status') }}</span>
        </button>
        <button type="button" data-import-meta
            class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12.5px] font-medium inline-flex items-center gap-2"
            title="{{ __('Pull products created directly in Meta Commerce Manager into WaDesk.') }}">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M8 2v8M5 7l3 3 3-3M2.5 13.5h11" />
            </svg>
            <span data-import-meta-label>{{ __('Import from Meta') }}</span>
        </button>
    </div>
    <div data-sync-progress class="hidden bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
        <div class="font-mono text-[10.5px] text-ink-700" data-sync-log></div>
        <div class="mt-2 h-2 bg-paper-100 rounded-full overflow-hidden">
            <div data-sync-bar class="h-full bg-wa-deep transition-all" style="width:0%"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('user.catalog.commerce') }}"
        class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
        @csrf
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Commerce settings') }}
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="flex items-start gap-3 text-[12.5px]">
                <input type="hidden" name="is_catalog_visible" value="0">
                <input type="checkbox" name="is_catalog_visible" value="1" @checked($catalog->is_catalog_visible)
                    class="mt-0.5 rounded border-paper-200 text-wa-deep">
                <span><span class="font-semibold block">{{ __('Show catalog in business profile') }}</span><span
                        class="text-[10.5px] text-ink-500">{{ __('Adds the "Catalog" button to your WhatsApp Business profile.') }}</span></span>
            </label>
            <label class="flex items-start gap-3 text-[12.5px]">
                <input type="hidden" name="is_cart_enabled" value="0">
                <input type="checkbox" name="is_cart_enabled" value="1" @checked($catalog->is_cart_enabled)
                    class="mt-0.5 rounded border-paper-200 text-wa-deep">
                <span><span class="font-semibold block">{{ __('Enable cart') }}</span><span
                        class="text-[10.5px] text-ink-500">{{ __('Buyers can add multiple products before sending the order.') }}</span></span>
            </label>
        </div>
        <div class="flex justify-end mt-4 pt-3 border-t border-paper-200">
            <button
                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
        </div>
    </form>

    {{-- Automation: cart-order acknowledgement + inbound concierge --}}
    @php $auto = is_array($catalog->meta_json) ? $catalog->meta_json : []; @endphp
    <form method="POST" action="{{ route('user.catalog.automation') }}"
        class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
        @csrf
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">{{ __('Automation') }}</div>

        {{-- Order acknowledgement (C1) --}}
        <div class="border border-paper-200 rounded-xl p-4">
            <label class="flex items-start gap-3 text-[12.5px]">
                <input type="hidden" name="order_ack_enabled" value="0">
                <input type="checkbox" name="order_ack_enabled" value="1" @checked($auto['order_ack_enabled'] ?? true)
                    class="mt-0.5 rounded border-paper-200 text-wa-deep">
                <span>
                    <span class="font-semibold block">{{ __('Auto-reply when a customer sends a cart') }}</span>
                    <span
                        class="text-[10.5px] text-ink-500">{{ __('The buyer instantly gets an order-received message (items + total) instead of silence — the #1 catalog conversion fix.') }}</span>
                </span>
            </label>
            <label class="block mt-3">
                <span
                    class="text-[10.5px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Payment link base (optional)') }}</span>
                <input type="url" name="order_ack_pay_url" value="{{ $auto['order_ack_pay_url'] ?? '' }}"
                    placeholder="https://pay.example.com/checkout"
                    class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep">
                <span
                    class="text-[10px] text-ink-400">{{ __('We append the order id; leave blank to just confirm receipt.') }}</span>
            </label>
        </div>

        {{-- Concierge (C5) --}}
        <div class="border border-paper-200 rounded-xl p-4 mt-3">
            <label class="flex items-start gap-3 text-[12.5px]">
                <input type="hidden" name="concierge_enabled" value="0">
                <input type="checkbox" name="concierge_enabled" value="1" @checked($auto['concierge_enabled'] ?? false)
                    class="mt-0.5 rounded border-paper-200 text-wa-deep">
                <span>
                    <span
                        class="font-semibold block">{{ __('Catalog concierge — auto-answer product questions') }}</span>
                    <span
                        class="text-[10.5px] text-ink-500">{{ __('When a customer types e.g. "red shoes under 2000", reply automatically with a matching Multi-Product Message. Off by default.') }}</span>
                </span>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                <label class="block md:col-span-2">
                    <span
                        class="text-[10.5px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Reply header') }}</span>
                    <input type="text" name="concierge_header" maxlength="60"
                        value="{{ $auto['concierge_header'] ?? '' }}" placeholder="{{ __("Here's what I found") }}"
                        class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep">
                </label>
                <label class="block">
                    <span
                        class="text-[10.5px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Max products') }}</span>
                    <input type="number" name="concierge_max" min="1" max="30"
                        value="{{ $auto['concierge_max'] ?? 10 }}"
                        class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep">
                </label>
            </div>
            <label class="flex items-start gap-3 text-[12.5px] mt-3">
                <input type="hidden" name="concierge_reply_on_empty" value="0">
                <input type="checkbox" name="concierge_reply_on_empty" value="1" @checked($auto['concierge_reply_on_empty'] ?? false)
                    class="mt-0.5 rounded border-paper-200 text-wa-deep">
                <span><span class="font-semibold block">{{ __('Reply even when nothing matches') }}</span><span
                        class="text-[10.5px] text-ink-500">{{ __('Otherwise the concierge stays silent on no match (recommended).') }}</span></span>
            </label>
            <label class="block mt-3">
                <span
                    class="text-[10.5px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('No-match reply') }}</span>
                <input type="text" name="concierge_empty_text" maxlength="1024"
                    value="{{ $auto['concierge_empty_text'] ?? '' }}"
                    placeholder="{{ __("Sorry, I couldn't find a match. Try another keyword or budget.") }}"
                    class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep">
            </label>
        </div>

        <div class="flex justify-end mt-4 pt-3 border-t border-paper-200">
            <button
                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save automation') }}</button>
        </div>
    </form>

    {{-- Products table — sync status per row --}}
    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
        <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
            <div>
                <h3 class="font-serif text-[18px] leading-tight">{{ __('Products') }}</h3>
                <div class="text-[11px] text-ink-500 mt-0.5">{{ __('Most recently updated · click') }} <b>Refresh
                        status</b> to poll Meta.</div>
            </div>
            <a href="{{ url('/store/products') }}"
                class="text-[11.5px] text-wa-deep font-semibold hover:underline">{{ __('Manage products →') }}</a>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                <tr>
                    <th class="px-5 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('Product') }}</th>
                    <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('SKU') }}</th>
                    <th class="px-2 py-2.5 text-right font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('Price') }}</th>
                    <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('Availability') }}</th>
                    <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                        {{ __('Meta status') }}</th>
                    <th class="px-5 py-2.5 text-right font-mono text-[10px] uppercase tracking-[0.14em]"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-paper-200">
                @forelse ($products as $p)
                    @php
                        $status = $p->meta_sync_status ?: 'unsynced';
                        $cls = [
                            'unsynced' => 'bg-paper-50 text-ink-500',
                            'pending' => 'bg-accent-amber/15 text-accent-amber',
                            'synced' => 'bg-wa-mint text-wa-deep',
                            'failed' => 'bg-accent-coral/15 text-accent-coral',
                        ];
                    @endphp
                    <tr data-product-id="{{ $p->id }}">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @php $boxSvg = '<svg viewBox=&quot;0 0 16 16&quot; class=&quot;w-4 h-4 text-ink-400&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;1.5&quot;><path d=&quot;M2 5l6-3 6 3v6l-6 3-6-3z&quot;/><path d=&quot;M2 5l6 3 6-3M8 8v6&quot;/></svg>'; @endphp
                                @if ($p->image_url)
                                    <img src="{{ $p->image_url }}" class="w-10 h-10 rounded-lg object-cover"
                                        onerror="this.outerHTML='<span class=&quot;w-10 h-10 rounded-lg bg-paper-50 grid place-items-center&quot;>{{ $boxSvg }}</span>'">
                                @else
                                    <span
                                        class="w-10 h-10 rounded-lg bg-paper-50 grid place-items-center">{!! str_replace('&quot;', '"', $boxSvg) !!}</span>
                                @endif
                                <div class="min-w-0">
                                    <div class="font-medium truncate">{{ $p->name }}</div>
                                    @if ($p->category)
                                        <div class="text-[10.5px] text-ink-500 font-mono">{{ $p->category }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-2 py-3 font-mono text-[11px]">{{ $p->sku ?: '—' }}</td>
                        <td class="px-2 py-3 text-right font-semibold">{{ $p->price_display }}</td>
                        <td class="px-2 py-3 text-[11.5px] text-ink-700">{{ $p->effective_availability }}</td>
                        <td class="px-2 py-3">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $cls[$status] }}">{{ $status }}</span>
                            @if ($p->meta_last_error)
                                <div class="text-[10px] text-accent-coral mt-1 truncate max-w-[200px]"
                                    title="{{ $p->meta_last_error }}">
                                    {{ \Illuminate\Support\Str::limit($p->meta_last_error, 60) }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button type="button" data-sync-one="{{ $p->id }}"
                                class="text-[11px] text-wa-deep font-semibold hover:underline">Sync</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-ink-500">{{ __('No products yet.') }}
                            <a href="{{ url('/store/products/create') }}"
                                class="text-wa-deep font-semibold hover:underline">{{ __('Add one →') }}</a></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <script>
        (function() {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const $progress = document.querySelector('[data-sync-progress]');
            const $log = document.querySelector('[data-sync-log]');
            const $bar = document.querySelector('[data-sync-bar]');
            const $syncAll = document.querySelector('[data-sync-all]');
            const $poll = document.querySelector('[data-poll]');

            function log(msg) {
                $progress.classList.remove('hidden');
                $log.innerHTML = (new Date().toLocaleTimeString()) + ' · ' + msg + '<br>' + $log.innerHTML;
            }

            function setStat(k, v) {
                const el = document.querySelector('[data-stat-' + k + ']');
                if (el) el.textContent = new Intl.NumberFormat().format(v);
            }

            async function postJson(url, body) {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body || {})
                });
                return {
                    ok: r.ok,
                    status: r.status,
                    json: await r.json().catch(() => ({}))
                };
            }

            $syncAll?.addEventListener('click', async () => {
                $syncAll.disabled = true;
                $syncAll.querySelector('[data-sync-all-label]').textContent = 'Syncing…';
                $bar.style.width = '0%';
                log('Starting…');
                let total = 0;
                while (true) {
                    const r = await postJson(@json(route('user.catalog.sync-chunk')), {});
                    if (!r.ok) {
                        log('<span style="color:#A1431F">✗ ' + (r.json.error || ('HTTP ' + r.status)) +
                            '</span>');
                        break;
                    }
                    const j = r.json;
                    total += j.pushed || 0;
                    log('Pushed ' + j.pushed + ' · pending ' + j.total_pending + ' · synced ' + j
                        .total_synced + (j.total_failed ? ' · failed ' + j.total_failed : ''));
                    setStat('synced', j.total_synced || 0);
                    setStat('pending', j.total_pending || 0);
                    setStat('failed', j.total_failed || 0);
                    $bar.style.width = (j.has_more ? Math.min(95, (j.total_synced + j.total_pending) / Math
                            .max(1, j.total_synced + j.total_pending + (j.total_failed || 0) + 1) * 100
                            ) : 100) + '%';
                    if (!j.has_more) break;
                }
                log('<span style="color:var(--color-wa-deep)">✓ done · ' + total + ' queued</span>');
                $syncAll.disabled = false;
                $syncAll.querySelector('[data-sync-all-label]').textContent = 'Sync to Meta';
            });

            document.querySelectorAll('[data-sync-one]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = parseInt(btn.dataset.syncOne, 10);
                    const original = btn.textContent;
                    btn.textContent = '…';
                    const r = await postJson(@json(route('user.catalog.sync-chunk')), {
                        product_ids: [id]
                    });
                    btn.textContent = r.ok && r.json.ok ? 'Queued ✓' : 'Failed';
                    setTimeout(() => btn.textContent = original, 1500);
                });
            });

            $poll?.addEventListener('click', async () => {
                $poll.disabled = true;
                $poll.querySelector('[data-poll-label]').textContent = 'Polling…';
                const r = await postJson(@json(route('user.catalog.poll')), {});
                if (r.ok && r.json.ok) {
                    log('Polled · synced+' + (r.json.synced || 0) + ' · failed+' + (r.json.failed || 0));
                    if ((r.json.synced || 0) > 0 || (r.json.failed || 0) > 0) setTimeout(() => location
                        .reload(), 600);
                }
                $poll.disabled = false;
                $poll.querySelector('[data-poll-label]').textContent = 'Refresh status';
            });

            // Import FROM Meta — pull products built directly in Commerce Manager
            // into WaDesk (WaDesk only pushes up, so a Meta-side catalog shows 0).
            const $importMeta = document.querySelector('[data-import-meta]');
            $importMeta?.addEventListener('click', async () => {
                $importMeta.disabled = true;
                const lbl = $importMeta.querySelector('[data-import-meta-label]');
                const orig = lbl.textContent;
                lbl.textContent = 'Importing…';
                log('Import from Meta · starting…');
                const r = await postJson(@json(route('user.catalog.import-from-meta')), {});
                if (r.ok && r.json.ok) {
                    log('<span style="color:var(--color-wa-deep)">✓ Imported ' + (r.json.imported || 0) + ' · updated ' +
                        (r.json.updated || 0) + ' · skipped ' + (r.json.skipped || 0) + ' of ' + (r.json.total || 0) +
                        '</span>');
                    setTimeout(() => location.reload(), 900);
                } else {
                    log('<span style="color:#A1431F">✗ ' + (r.json.error || ('HTTP ' + r.status)) + '</span>');
                }
                $importMeta.disabled = false;
                lbl.textContent = orig;
            });
        })();
    </script>
@endif
