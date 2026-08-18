@php
    $tabs = [
        'overview' => ['Overview', 'M2 11l3-5 3 3 3-6 3 4', null],
        'orders' => [
            'Orders',
            'M2 4h2l1.5 8h7l1-5H6 M6 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z M11 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
            \App\Models\WaOrder::forWorkspace(auth()->user()->current_workspace_id ?? 0)->count(),
        ],
        'products' => [
            'Products',
            'M2 5l6-3 6 3v6l-6 3-6-3z M2 5l6 3 6-3 M8 8v6',
            \App\Models\WaProduct::forWorkspace(auth()->user()->current_workspace_id ?? 0)->count(),
        ],
        'groups' => [
            'Groups',
            'M5 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4z M1.5 13c0-2 1.3-3 3.5-3s3.5 1 3.5 3 M7.5 13c0-2 1.3-3 3.5-3s3.5 1 3.5 3',
            \App\Models\WaGroup::where('workspace_id', auth()->user()->current_workspace_id ?? 0)->count(),
        ],
        'storefront' => ['Storefront', 'M2 5h12v8H2z M2 8h12 M5 5v3', null],
        'coupons' => [
            'Coupons',
            'M2 6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2 1.5 1.5 0 0 0 0 4 2 2 0 0 1-2 2H4a2 2 0 0 1-2-2 1.5 1.5 0 0 0 0-4z M9 5l-2 6',
            \App\Models\WaCoupon::where('workspace_id', auth()->user()->current_workspace_id ?? 0)->count(),
        ],
        'reviews' => [
            'Reviews',
            'M8 2l1.8 3.6L14 6.2l-3 2.9.7 4.1L8 11.3 4.3 13.2 5 9.1 2 6.2l4.2-.6z',
            \App\Models\WaProductReview::where('workspace_id', auth()->user()->current_workspace_id ?? 0)
                ->where('status', 'pending')
                ->count(),
        ],
        'payments' => [
            'Payments',
            'M1.5 4.5h13v7h-13z M1.5 7h13 M4 9.5h3',
            \App\Models\WorkspacePaymentConfig::where('workspace_id', auth()->user()->current_workspace_id ?? 0)->count(),
        ],
        'customers' => [
            'Customers',
            'M8 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z M2.5 14c0-2.5 2.2-4 5.5-4s5.5 1.5 5.5 4',
            \App\Models\WaCustomerProfile::where('workspace_id', auth()->user()->current_workspace_id ?? 0)->count(),
        ],
    ];
    $current = $current ?? request()->segment(2) ?: 'overview';
    $publicUrl = isset($sf) && $sf ? $sf->public_url : null;

    // "Live" / "Setup" should reflect ANY route to WhatsApp:
    // • workspace-level provider config (WABA/Twilio/etc) connected, OR
    // • storefront has a bound device that's still 'connected', OR
// • current user owns any connected device row.
$cfgConnected = isset($cfg) && $cfg && $cfg->isConnected();
$sfDevice = isset($sf) && $sf && $sf->device_id ? \App\Models\Device::find($sf->device_id) : null;
$sfDeviceLive = $sfDevice && $sfDevice->status === 'connected';
$anyDeviceLive = \App\Models\Device::query()->forCurrentWorkspace()->where('status', 'connected')->exists();
$waLive = $cfgConnected || $sfDeviceLive || $anyDeviceLive;

if ($cfgConnected) {
    $cfgLabel = \App\Enums\WaProvider::tryFrom($cfg->provider)?->label() ?? strtoupper($cfg->provider);
} elseif ($sfDeviceLive) {
    $cfgLabel = 'Unofficial API · ' . trim(($sfDevice->country_code ?? '') . ' ' . ($sfDevice->phone_number ?? ''));
} elseif ($anyDeviceLive) {
    $cfgLabel = 'Device connected · /devices';
} else {
    $cfgLabel = 'Not connected';
    }
@endphp

<aside class="space-y-3">
    <!-- Store info card -->
    <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
        <div class="flex items-start justify-between gap-2">
            <span class="w-11 h-11 rounded-xl bg-wa-mint text-wa-deep grid place-items-center"><svg viewBox="0 0 16 16"
                    class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 5h10l-1 9H4z" />
                    <path d="M5 5a3 3 0 1 1 6 0" />
                </svg></span>
            @if ($waLive)
                <span
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Live</span>
            @else
                <span
                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-paper-50 text-ink-500 border border-paper-200"><span
                        class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>Setup</span>
            @endif
        </div>
        <div class="font-serif text-[18px] leading-tight mt-3">
            {{ $sf->shop_name ?: optional(auth()->user()->currentWorkspaceRel)->name ?? 'Your store' }}</div>
        <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 truncate">{{ $cfgLabel }}</div>
    </div>

    {{-- Shop switcher (only renders when the workspace owns 2+ shops) --}}
    @if (isset($allShops) && $allShops->count() > 1)
        <div class="border border-paper-200 rounded-2xl bg-paper-0 p-3 shadow-card">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-1 mb-1.5">
                {{ __('Switch shop') }}</div>
            <select onchange="if (this.value) window.location.href = this.value"
                class="w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-50 text-[12.5px] focus:outline-none focus:border-wa-deep">
                @foreach ($allShops as $row)
                    <option value="{{ url('/store?shop=' . $row->id) }}" @selected($row->id === $sf->id)>
                        {{ $row->shop_name ?: 'Shop #' . $row->id }}</option>
                @endforeach
            </select>
            <a href="{{ url('/connect?platform=wa-store') }}"
                class="block mt-2 text-[11px] text-wa-deep font-semibold hover:underline px-1">{{ __('Manage all shops →') }}</a>
        </div>
    @endif

    @if ($publicUrl)
        <div class="border border-paper-200 rounded-2xl bg-paper-0 p-3 shadow-card">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5 px-1">
                {{ __('Public URL') }}</div>
            <div class="px-2 py-1.5 bg-paper-50 rounded-lg font-mono text-[11px] text-ink-700 break-all">
                {{ $publicUrl }}</div>
            <div class="flex items-center gap-1 mt-2">
                <a href="{{ $publicUrl }}" target="_blank"
                    class="flex-1 text-[11px] text-wa-deep font-semibold hover:underline px-2 py-1 text-center">{{ __('Open ↗') }}</a>
                <button type="button" class="flex-1 text-[11px] text-wa-deep font-semibold hover:underline px-2 py-1"
                    onclick="navigator.clipboard.writeText('{{ $publicUrl }}'); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy', 1500)">Copy</button>
            </div>
        </div>
    @endif

    <nav class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card space-y-0.5">
        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
            {{ __('Store') }}</div>
        @foreach ($tabs as $key => [$label, $iconPath, $count])
            @php
                $href = match ($key) {
                    'overview' => route('user.store.index'),
                    'orders' => route('user.store.orders.index'),
                    'products' => route('user.store.products.index'),
                    'groups' => route('user.store.groups.index'),
                    'storefront' => route('user.store.storefront.edit'),
                    'coupons' => route('user.store.coupons.index'),
                    'reviews' => route('user.store.reviews.index'),
                    'payments' => route('user.store.payments.index'),
                    'customers' => route('user.store.customers.index'),
                };
                $active = $current === $key;
            @endphp
            <a href="{{ $href }}"
                class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] {{ $active ? 'bg-wa-mint/40 text-wa-deep font-semibold' : 'hover:bg-paper-50 text-ink-700' }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="{{ $iconPath }}" />
                </svg>
                {{ $label }}
                @if ($count !== null && $count > 0)
                    <span class="ml-auto text-[10px] font-mono opacity-80">{{ number_format($count) }}</span>
                @endif
            </a>
        @endforeach

        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-3 pb-1.5">
            {{ __('Settings') }}</div>
        <a href="{{ url('/connect?platform=wa-store') }}"
            class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] hover:bg-paper-50 text-ink-700">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
            </svg>
            Connection
        </a>
    </nav>

    @if ($waLive)
        <div class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
            <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2"><span
                    class="w-2 h-2 rounded-full bg-wa-green"></span>Provider live</div>
            @if ($cfgConnected)
                Connected via <span class="font-mono">{{ $cfg->phone_number ?: $cfg->display_label }}</span>.
            @elseif ($sfDeviceLive)
                Sending from <span
                    class="font-mono">{{ trim(($sfDevice->country_code ?? '') . ' ' . ($sfDevice->phone_number ?? '')) }}</span>.
            @else
                {{ __('No device connected.') }}
                <button type="button" data-connect-device class="text-wa-deep font-semibold hover:underline cursor-pointer">{{ __('Connect one') }}</button>,
                {{ __('then bind it to this store from the') }} <a
                    href="{{ url('/connect?platform=wa-store') }}"
                    class="text-wa-deep font-semibold hover:underline">{{ __('connect wizard') }}</a>.
            @endif
        </div>
    @endif
</aside>
