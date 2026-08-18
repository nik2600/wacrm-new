@php
    /** @var \App\Models\Device $device */
    $isConnected = (bool) $device->active;
    $statusPill = match ($device->status) {
        'connected' => ['bg' => 'bg-wa-mint', 'text' => 'text-wa-deep', 'dot' => 'bg-wa-green', 'label' => 'Connected'],
        'needs_pair' => [
            'bg' => 'bg-accent-amber/15',
            'text' => 'text-[#7B5A14]',
            'dot' => 'bg-accent-amber',
            'label' => 'Needs re-pair',
        ],
        'failed' => [
            'bg' => 'bg-accent-coral/15',
            'text' => 'text-[#A1431F]',
            'dot' => 'bg-accent-coral',
            'label' => 'Failed',
        ],
        default => [
            'bg' => 'bg-paper-50',
            'text' => 'text-ink-700',
            'dot' => 'bg-paper-300',
            'label' => 'Disconnected',
        ],
    };

    // Real metrics pulled from the messages table by DevicesController::show().
    // The blade only falls back to defaults if the controller didn't
    // pass them (e.g. when this view is included from an admin page).
    $sentSeries = $sentSeries ?? array_fill(0, 7, 0);
    $failSeries = $failSeries ?? array_fill(0, 7, 0);
    $sent7d = $sent7d ?? 0;
    $failed7d = $failed7d ?? 0;
    $delivered7d = $delivered7d ?? 0;
    $deliveryPct = $deliveryPct ?? 0;
    $sent24 = $sent24 ?? 0;
    $failed24 = $failed24 ?? 0;
@endphp

<x-layouts.user :title="__('Device Analytics')" nav-key="devices" page="user-devices-detail">
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/devices') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Devices /
                        {{ $device->display_phone }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ $device->device_name }} <span
                            class="italic text-wa-deep">{{ __('analytics') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($isConnected)
                    <form action="{{ route('user.devices.kill-session', $device->id) }}" method="POST"
                        class="inline-block">
                        @csrf
                        <button type="submit"
                            class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Disconnect') }}</button>
                    </form>
                @else
                    {{-- Re-pair returns to the index page where the connect modal lives. --}}
                    <a href="{{ url('/devices') }}#device-{{ $device->id }}"
                        class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">Re-pair</a>
                @endif
                <form action="{{ route('user.devices.toggle', $device->id) }}" method="POST" class="inline-block">
                    @csrf
                    <button type="submit"
                        class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                        </svg>
                        {{ $isConnected ? 'Refresh session' : 'Mark connected' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6 space-y-6">

        @if (session('status'))
            <div class="rounded-xl border border-wa-green/40 bg-wa-mint/60 px-4 py-2.5 text-[12.5px] text-wa-deep">
                {{ session('status') }}</div>
        @endif

        {{-- Edit device — name / number / region. Posts to DevicesController::update().
 This is the working target of the "Edit" pencil on the device list. --}}
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="flex items-center gap-2.5 mb-4">
                <span
                    class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center shrink-0"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                    </svg></span>
                <h2 class="font-serif text-[18px] leading-none">{{ __('Edit device') }}</h2>
            </div>
            <form action="{{ route('user.devices.update', $device->id) }}" method="POST"
                class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                @csrf
                @method('PUT')
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Device name') }}</label>
                    <input name="device_name" value="{{ old('device_name', $device->device_name) }}" maxlength="191"
                        required
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Country code') }}</label>
                    <input name="country_code" value="{{ old('country_code', $device->country_code) }}" maxlength="8"
                        placeholder="+91"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Phone number') }}</label>
                    <input name="phone_number" value="{{ old('phone_number', $device->phone_number) }}" maxlength="32"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Region') }} <span
                            class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span></label>
                    <input name="region" value="{{ old('region', $device->region) }}" maxlength="16"
                        placeholder="{{ __('e.g. IN') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                </div>
                <div class="md:col-span-4 flex items-center justify-between gap-3 pt-1">
                    <p class="text-[11px] text-ink-500">
                        {{ __('Updates this device only. Connection status is managed by pairing, not here.') }}</p>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save changes') }}</button>
                </div>
            </form>
            @if ($errors->any())
                <div class="mt-3 text-[11.5px] text-accent-coral leading-relaxed">
                    @foreach ($errors->all() as $e)
                        <div>{{ $e }}</div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Per-number proxy / dedicated IP (Unofficial-API) --}}
        @php
            $canProxy = (bool) (auth()->user()?->currentWorkspace?->effectiveLimit('access_proxy_isolation', false));
        @endphp
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-5">
            <div class="flex items-center gap-2.5 mb-1">
                <span class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                </span>
                <h2 class="text-[14px] font-semibold text-ink-900">{{ __('Proxy / dedicated IP') }}</h2>
            </div>
            <p class="text-[11.5px] text-ink-500 mb-4 leading-relaxed">
                {{ __('Route this number through its own proxy IP so multiple numbers on this server don\'t share one IP — this lowers the ban risk when sending in bulk. Use a residential or mobile proxy for real isolation.') }}
            </p>

            @if (!$canProxy)
                <div class="rounded-xl border border-paper-200 bg-paper-50 p-4 flex items-start gap-3">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-ink-400 shrink-0 mt-0.5"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                    <div>
                        <p class="text-[12.5px] font-medium text-ink-800">{{ __('Available on a higher plan') }}</p>
                        <p class="text-[11.5px] text-ink-500">{{ __('Upgrade your plan to assign a dedicated proxy IP per number.') }}</p>
                    </div>
                </div>
            @else
                <form id="proxy-form" class="grid grid-cols-1 md:grid-cols-2 gap-4"
                      data-save="{{ route('user.devices.proxy.save', $device->id) }}"
                      data-test="{{ route('user.devices.proxy.test', $device->id) }}">
                    @csrf
                    <label class="md:col-span-2 inline-flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="proxy_enabled" value="1" @checked($device->proxy_enabled)
                               class="w-4 h-4 rounded border-paper-300 text-wa-deep focus:ring-wa-deep/30">
                        <span class="text-[12.5px] text-ink-800">{{ __('Send this number through a proxy') }}</span>
                    </label>

                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Type') }}</label>
                        <select name="proxy_type"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="http" @selected(($device->proxy_type ?? 'http') === 'http')>HTTP / HTTPS</option>
                            <option value="socks5" @selected($device->proxy_type === 'socks5')>SOCKS5</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Port') }}</label>
                        <input type="number" name="proxy_port" value="{{ $device->proxy_port }}" min="1" max="65535" placeholder="1080"
                               class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Host / IP') }}</label>
                        <input type="text" name="proxy_host" value="{{ $device->proxy_host }}" placeholder="proxy.example.com"
                               class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Username (optional)') }}</label>
                        <input type="text" name="proxy_username" value="{{ $device->proxy_username }}" autocomplete="off"
                               class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Password (optional)') }}</label>
                        <input type="password" name="proxy_password" autocomplete="new-password"
                               placeholder="{{ $device->proxy_password ? __('•••••• (leave blank to keep)') : '' }}"
                               class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>

                    <div class="md:col-span-2 flex items-center justify-between gap-3 pt-1 flex-wrap">
                        <div id="proxy-status-line" class="text-[11.5px]">
                            @if ($device->proxy_status === 'ok')
                                <span class="text-wa-deep">{{ __('Verified') }} · {{ __('egress IP') }} {{ $device->proxy_egress_ip }}</span>
                            @elseif ($device->proxy_status === 'unreachable')
                                <span class="text-accent-coral">{{ __('Last check: unreachable') }}</span>
                            @else
                                <span class="text-ink-400">{{ __('Not tested yet') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="proxy-test-btn"
                                class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Test') }}</button>
                            <button type="submit" id="proxy-save-btn"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save proxy') }}</button>
                        </div>
                    </div>
                </form>
                <p class="text-[11px] text-ink-400 mt-2 leading-relaxed">
                    {{ __('After saving, re-pair / reconnect the number to apply the proxy. If the proxy is unreachable the number stays disconnected — it never falls back to the server IP.') }}
                </p>

                <script>
                    (() => {
                        const form = document.getElementById('proxy-form');
                        if (!form) return;
                        const csrf = form.querySelector('input[name=_token]').value;
                        const statusLine = document.getElementById('proxy-status-line');
                        const testBtn = document.getElementById('proxy-test-btn');
                        const saveBtn = document.getElementById('proxy-save-btn');

                        async function post(url, btn, busyLabel) {
                            const old = btn.textContent;
                            btn.disabled = true; btn.textContent = busyLabel;
                            try {
                                const res = await fetch(url, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                                    body: new FormData(form),
                                });
                                const data = await res.json().catch(() => ({}));
                                return { ok: res.ok, data };
                            } catch (e) {
                                return { ok: false, data: { message: '{{ __('Network error') }}' } };
                            } finally {
                                btn.disabled = false; btn.textContent = old;
                            }
                        }
                        function show(ok, msg) {
                            statusLine.textContent = msg || (ok ? 'OK' : 'Failed');
                            statusLine.className = 'text-[11.5px] ' + (ok ? 'text-wa-deep' : 'text-accent-coral');
                        }
                        testBtn?.addEventListener('click', async () => {
                            const { ok, data } = await post(form.dataset.test, testBtn, '{{ __('Testing…') }}');
                            show(ok, data.message);
                        });
                        form.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            const { ok, data } = await post(form.dataset.save, saveBtn, '{{ __('Saving…') }}');
                            show(ok, data.message);
                        });
                    })();
                </script>
            @endif
        </section>

        {{-- Device header card --}}
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
            <div class="flex items-start justify-between gap-6">
                <div class="flex items-start gap-4 min-w-0">
                    <span
                        class="w-12 h-12 rounded-xl {{ $isConnected ? 'bg-wa-mint text-wa-deep' : 'bg-paper-50 text-ink-700' }} grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                            <circle cx="8" cy="11.5" r="0.8" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ $isConnected ? 'Connected device' : 'Saved device' }}</div>
                        <h1 class="font-serif text-[26px] leading-tight tracking-[-0.01em] mt-0.5">
                            {{ $device->device_name }}</h1>
                        <div class="mt-1 font-mono text-[13px] text-ink-700">{{ $device->display_phone }}</div>
                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $statusPill['bg'] }} {{ $statusPill['text'] }} text-[10.5px] font-mono"><span
                                    class="w-1.5 h-1.5 rounded-full {{ $statusPill['dot'] }}"></span>{{ $statusPill['label'] }}</span>
                            @if ($device->region)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $device->region }}</span>
                            @endif
                            @if ($device->country_code)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">Dial
                                    code {{ $device->country_code }}</span>
                            @endif
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">
                                {{ $device->last_seen_at ? 'Last sync ' . $device->last_seen_at->diffForHumans() : 'Never connected' }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">Added
                                {{ $device->created_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- KPI strip — pulls from the device's stored metrics --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (7d)') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($sent7d) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('outgoing') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivered') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">{{ $deliveryPct }}%</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($deliveryPct, 1) }}%</span><span
                        class="text-[11px] text-ink-500">{{ number_format($delivered7d) }} ok</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (24h)') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($sent24) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('today') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</span><span
                        class="text-[10px] {{ $failed24 > 0 ? 'text-accent-coral' : 'text-wa-deep' }} font-mono">{{ $sent24 ? round(($failed24 / max($sent24, 1)) * 100, 1) : 0 }}%</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">{{ number_format($failed24) }}</span><span
                        class="text-[11px] text-ink-500">{{ __('retry queue') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status') }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[22px] leading-none {{ $statusPill['text'] }}">{{ $statusPill['label'] }}</span>
                </div>
            </div>
        </section>

        {{-- Charts: send volume + status mix --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Activity') }}</div>
                        <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Sent vs failed (7d)') }}</h3>
                    </div>
                </div>
                <div id="chart-volume" class="h-[260px]" data-sent="{{ json_encode($sentSeries) }}"
                    data-failed="{{ json_encode($failSeries) }}"></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status mix') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Delivery breakdown') }}</h3>
                <div id="chart-status" class="h-[200px]" data-delivered="{{ $delivered7d }}"
                    data-failed="{{ $failed7d }}"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Delivered</span><span
                            class="font-mono text-ink-700">{{ number_format($delivered7d) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Failed</span><span
                            class="font-mono text-ink-700">{{ number_format($failed7d) }}</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-paper-300"></span>Total sent</span><span
                            class="font-mono text-ink-700">{{ number_format($sent7d) }}</span></div>
                </div>
            </div>
        </section>

        {{-- Top recipients + recent failures.
 Note: messages.from_number is encrypted-at-rest so we can't SQL-WHERE
 on it. Instead we walk the conversation chain (conversations.device_id
 is a plain FK) to fetch only the rows that came from this device. --}}
        @php
            $deviceMessages = \App\Models\Message::query()
                ->whereHas('conversation', fn($q) => $q->where('device_id', $device->id))
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();
            $topRecipients = $deviceMessages
                ->where('direction', 'out')
                ->groupBy('to_number')
                ->map(fn($g) => ['to_number' => $g->first()->to_number, 'count' => $g->count()])
                ->sortByDesc('count')
                ->take(5)
                ->values();
            $recentFailures = $deviceMessages->where('direction', 'out')->where('status', 'failed')->take(8);
            $recentSends = $deviceMessages->where('direction', 'out')->take(20);
        @endphp

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Top recipients') }}
                </div>
                <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">{{ __('Most-messaged') }}</h3>
                @if ($topRecipients->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($topRecipients as $r)
                            <a href="{{ url('/chat') }}"
                                class="flex items-center gap-2.5 hover:bg-paper-50 rounded-lg p-1 -m-1 transition">
                                <span
                                    class="w-8 h-8 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[10px] font-semibold shrink-0">
                                    {{ strtoupper(substr(preg_replace('/\D+/', '', $r['to_number'] ?? ''), -2)) }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12.5px] font-semibold truncate">{{ mask_phone($r['to_number'] ?? '') }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">{{ __('via this device') }}
                                    </div>
                                </div>
                                <span class="text-[12px] font-mono text-ink-700">{{ $r['count'] }}×</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-4">
                        @include('user.partials.empty-state', [
                            'message' =>
                                'No outbound messages found. Once this device starts sending, top recipients will appear here.',
                        ])
                    </div>
                @endif
            </div>
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Failures') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Recent failed sends') }}</h3>
                    </div>
                    <a href="{{ url('/message-history') }}"
                        class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('All →') }}</a>
                </div>
                @if ($recentFailures->count())
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                        {{ __('Time') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Recipient') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Reason') }}</th>
                                    <th
                                        class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                        {{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="device-failures-tbody" class="divide-y divide-paper-200">
                                @foreach ($recentFailures as $m)
                                    <tr>
                                        <td class="px-4 py-2.5 font-mono text-[11px] text-ink-700">
                                            {{ wa_local($m->created_at)?->format('H:i') ?? '—' }}</td>
                                        <td class="px-2 py-2.5">
                                            <div class="font-medium">{{ mask_phone($m->to_number) ?: '—' }}</div>
                                        </td>
                                        <td class="px-2 py-2.5"><span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent-coral/15 text-[#A1431F] text-[10.5px] font-mono">{{ $m->failure_reason ?: 'unknown error' }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right"><span
                                                class="text-[11px] text-ink-500 font-mono">{{ $m->media_path ? 'media' : 'text' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4">
                        @include('user.partials.empty-state', [
                            'message' => 'No failed sends found. Failed sends from this device will appear here.',
                        ])
                    </div>
                @endif
            </div>
        </section>

        {{-- Live send feed for this device. Pulled from real Messages joined
 to conversations.device_id, so it stays fresh as queues fire and
 scheduled sends complete. Refreshes via the page-level meta-refresh
 below (the lightweight /chat polling lives in JS only). --}}
        <section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
            <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live feed') }}
                    </div>
                    <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Recent sends from this device') }}
                    </h3>
                </div>
                <div class="flex items-center gap-2">
                    <span id="device-feed-status"
                        class="text-[10.5px] font-mono text-ink-500">{{ __('auto-refresh 5s') }}</span>
                    <a href="{{ url('/chat') }}"
                        class="text-[11px] text-wa-deep font-semibold hover:underline">{{ __('Open chat →') }}</a>
                </div>
            </div>
            {{-- Per-source totals strip — chat / campaign / scheduled counts
 show at a glance what's flowing through this device. --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 px-5 pt-4">
                <div class="rounded-lg bg-paper-50 border border-paper-200 p-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Chat sends') }}
                    </div>
                    <div class="font-serif text-[22px] leading-tight mt-1">
                        {{ number_format($kindCounts['chat'] ?? 0) }}</div>
                    <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('via /chat composer') }}</div>
                </div>
                <div class="rounded-lg bg-paper-50 border border-paper-200 p-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        {{ __('Campaign sends') }}</div>
                    <div class="font-serif text-[22px] leading-tight mt-1">
                        {{ number_format($kindCounts['campaign'] ?? 0) }}</div>
                    <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('via /wa-campaigns') }}</div>
                </div>
                <div class="rounded-lg bg-paper-50 border border-paper-200 p-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                        {{ __('Scheduled sends') }}</div>
                    <div class="font-serif text-[22px] leading-tight mt-1">
                        {{ number_format($kindCounts['scheduled'] ?? 0) }}</div>
                    <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('via /scheduled') }}</div>
                </div>
            </div>

            @if ($recentRows->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Time') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Source') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Recipient') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Body / Media') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="device-sends-tbody" class="divide-y divide-paper-200">
                            @foreach ($recentRows as $m)
                                @php
                                    $statusBadge = match ($m->status) {
                                        'sent', 'delivered' => 'bg-wa-mint text-wa-deep border border-wa-deep/30',
                                        'read' => 'bg-[#E5F5FE] text-[#13478A] border border-[#53BDEB]/40',
                                        'failed' => 'bg-accent-coral/15 text-[#A1431F] border border-accent-coral/40',
                                        'scheduled',
                                        'queued'
                                            => 'bg-[#FFF7E2] text-[#8A5F0E] border border-[#E0B445]/40',
                                        default => 'bg-paper-100 text-ink-700 border border-paper-300',
                                    };
                                    $kindBadge = match ($m->kind) {
                                        'campaign' => 'bg-[#E5F5FE] text-[#13478A] border border-[#53BDEB]/30',
                                        'scheduled' => 'bg-[#FFF7E2] text-[#8A5F0E] border border-[#E0B445]/40',
                                        default => 'bg-wa-mint text-wa-deep border border-wa-deep/20',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-2.5 font-mono text-[11px] text-ink-700">
                                        {{ wa_local($m->ts)?->format('M d · H:i') ?? '—' }}</td>
                                    <td class="px-2 py-2.5"><span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase tracking-wide {{ $kindBadge }}">{{ $m->kind }}</span>
                                    </td>
                                    <td class="px-2 py-2.5 font-mono text-[11.5px]">{{ $m->to ?: '—' }}</td>
                                    <td class="px-2 py-2.5">
                                        @if ($m->media_type)
                                            <span class="inline-flex items-center gap-1 text-[11.5px] text-ink-700">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path d="M3 4l5-2 5 2v8l-5 2-5-2zM8 6v6" />
                                                </svg>
                                                {{ $m->media_type }}
                                            </span>
                                        @else
                                            <div class="truncate max-w-[420px] text-[12px]">
                                                {{ \Illuminate\Support\Str::limit($m->body ?: '—', 80) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5"><span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase tracking-wide {{ $statusBadge }}">{{ $m->status }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4">
                    @include('user.partials.empty-state', [
                        'message' =>
                            'No sends yet. Once you send messages from this device they will appear here.',
                    ])
                </div>
            @endif
        </section>

        {{-- ───────────────────────────────────────────────────────────────────
 All messages for this device (full paginated stream)
 Mirrors /message-history but scoped to this device's id. Pulls
 from 5 source tables: inbox, auto-reply, campaign, scheduled,
 legacy direct. Broadcast is excluded — broadcasts fan-out across
 every connected number so attributing them to one device lies.
 ─────────────────────────────────────────────────────────────── --}}
        @php
            $sourceMeta = [
                'inbox' => ['label' => 'Team inbox', 'pill' => 'bg-wa-mint text-wa-deep'],
                'auto_reply' => ['label' => 'Auto-reply', 'pill' => 'bg-[#F3E9FF] text-[#5B3D8A]'],
                'campaign' => ['label' => 'Campaign', 'pill' => 'bg-accent-amber/20 text-[#7B5A14]'],
                'scheduled' => ['label' => 'Scheduled', 'pill' => 'bg-[#FFF7E2] text-[#8A5F0E]'],
                'legacy' => ['label' => 'Direct', 'pill' => 'bg-paper-100 text-ink-700'],
            ];
            $activeSources = $msgSources ?: array_keys($sourceMeta);
        @endphp

        <section class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Message history') }}</div>
                    <h3 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('All messages for this device') }}
                    </h3>
                    <div class="mt-1 text-[11.5px] text-ink-500 font-mono">
                        {{ number_format($msgPaginator->total()) }} total · across
                        {{ count(array_filter($msgSourceCounts, fn($v, $k) => $k !== 'total' && $v > 0, ARRAY_FILTER_USE_BOTH)) }}
                        {{ __('sources') }}
                    </div>
                </div>
                <form method="GET" class="flex items-center gap-2 flex-wrap">
                    <input type="search" name="q" value="{{ $msgQ }}"
                        placeholder="{{ __('Search body / phone / contact…') }}"
                        class="px-3.5 py-1.5 rounded-full bg-paper-50 border border-paper-200 text-[12.5px] w-full sm:w-64 focus:outline-none focus:border-wa-deep" />
                    @foreach (['dir' => $msgDirection !== 'all' ? $msgDirection : null] as $k => $v)
                        @if ($v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
                        @endif
                    @endforeach
                    @foreach ($msgSources as $s)
                        <input type="hidden" name="sources[]" value="{{ $s }}" />
                    @endforeach
                    <button type="submit"
                        class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Search') }}</button>
                    @if ($msgQ !== '' || $msgDirection !== 'all' || count($msgSources) < 5)
                        <a href="{{ url('/devices/' . $device->id) }}"
                            class="text-[11.5px] text-ink-500 hover:text-wa-deep font-mono">reset</a>
                    @endif
                </form>
            </div>

            {{-- Source filter pills + direction filter --}}
            <div
                class="px-5 py-3 border-b border-paper-200 bg-paper-50/40 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-1.5 flex-wrap">
                    @php
                        $allPicked = count($msgSources) === 5;
                        $allUrl =
                            url()->current() .
                            '?' .
                            http_build_query(
                                array_filter([
                                    'q' => $msgQ ?: null,
                                    'dir' => $msgDirection !== 'all' ? $msgDirection : null,
                                ]),
                            );
                    @endphp
                    <a href="{{ $allUrl }}"
                        class="px-3 py-1 rounded-full text-[11.5px] font-mono uppercase tracking-wide {{ $allPicked ? 'bg-wa-deep text-paper-0' : 'bg-paper-0 border border-paper-200 text-ink-700 hover:bg-paper-50' }}">
                        All <span class="opacity-70 ml-1">{{ number_format($msgSourceCounts['total']) }}</span>
                    </a>
                    @foreach ($sourceMeta as $key => $meta)
                        @php
                            $isOnly = count($msgSources) === 1 && $msgSources[0] === $key;
                            $u =
                                url()->current() .
                                '?' .
                                http_build_query(
                                    array_filter([
                                        'q' => $msgQ ?: null,
                                        'dir' => $msgDirection !== 'all' ? $msgDirection : null,
                                        'sources' => [$key],
                                    ]),
                                );
                        @endphp
                        <a href="{{ $u }}"
                            class="px-3 py-1 rounded-full text-[11.5px] font-mono uppercase tracking-wide {{ $isOnly ? 'bg-wa-deep text-paper-0' : 'bg-paper-0 border border-paper-200 text-ink-700 hover:bg-paper-50' }}">
                            {{ $meta['label'] }} <span
                                class="opacity-70 ml-1">{{ number_format($msgSourceCounts[$key] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="flex items-center gap-1.5">
                    @foreach ([['all', 'All'], ['out', 'Sent'], ['in', 'Received'], ['fail', 'Failed']] as [$k, $label])
                        @php
                            $u =
                                url()->current() .
                                '?' .
                                http_build_query(
                                    array_filter([
                                        'q' => $msgQ ?: null,
                                        'dir' => $k !== 'all' ? $k : null,
                                        'sources' => count($msgSources) < 5 ? $msgSources : null,
                                    ]),
                                );
                        @endphp
                        <a href="{{ $u }}"
                            class="px-3 py-1 rounded-full text-[11.5px] font-mono uppercase tracking-wide {{ $msgDirection === $k ? 'bg-wa-deep text-paper-0' : 'bg-paper-0 border border-paper-200 text-ink-700 hover:bg-paper-50' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            {{-- The actual table --}}
            @if ($msgPaginator->total() === 0)
                <div class="p-6">
                    @include('user.partials.empty-state', [
                        'message' =>
                            'No messages found for this device with the current filters. Try resetting filters or expanding the source set.',
                    ])
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('When') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Source') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Direction') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Contact / Phone') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Body') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @foreach ($msgPaginator as $r)
                                @php
                                    $dir = $r['direction'] ?? 'in';
                                    $status = $r['status'] ?? '';
                                    $statusBadge = match (true) {
                                        in_array($status, ['read'], true)
                                            => 'bg-[#E5F5FE] text-[#13478A] border border-[#53BDEB]/40',
                                        in_array($status, ['delivered', 'sent', 'fired', 'paid'], true)
                                            => 'bg-wa-mint text-wa-deep border border-wa-deep/30',
                                        in_array($status, ['failed', 'error'], true)
                                            => 'bg-accent-coral/15 text-[#A1431F] border border-accent-coral/40',
                                        in_array($status, ['scheduled', 'queued', 'pending'], true)
                                            => 'bg-[#FFF7E2] text-[#8A5F0E] border border-[#E0B445]/40',
                                        default => 'bg-paper-100 text-ink-700 border border-paper-300',
                                    };
                                    $sm = $sourceMeta[$r['source']] ?? [
                                        'label' => $r['source_label'] ?? '—',
                                        'pill' => 'bg-paper-100 text-ink-700',
                                    ];
                                    $contact = $r['contact_name'] ?: ($r['phone'] ?: '—');
                                @endphp
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-2.5 font-mono text-[11px] text-ink-700 whitespace-nowrap"
                                        title="{{ optional($r['when'])->toIso8601String() }}">
                                        {{ wa_local($r['when'])?->format('M d · H:i') ?? '—' }}
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase tracking-wide {{ $sm['pill'] }}">{{ $sm['label'] }}</span>
                                    </td>
                                    <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">
                                        @if ($dir === 'out')
                                            <span class="inline-flex items-center gap-1 text-wa-deep"><svg
                                                    viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                    stroke="currentColor" stroke-width="1.7">
                                                    <path d="m3 8 10 0M9 4l4 4-4 4" />
                                                </svg>{{ __('out') }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-ink-700"><svg
                                                    viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                    stroke="currentColor" stroke-width="1.7">
                                                    <path d="m13 8 -10 0M7 4l-4 4 4 4" />
                                                </svg>in</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="font-medium text-[12.5px] truncate max-w-[180px]">
                                            {{ $contact }}</div>
                                        @if ($r['phone'] && $r['contact_name'])
                                            <div class="font-mono text-[10.5px] text-ink-500 truncate">
                                                {{ $r['phone'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="truncate max-w-[340px] text-[12px]">
                                            {{ \Illuminate\Support\Str::limit((string) ($r['body'] ?? ''), 90) ?: '—' }}
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono uppercase tracking-wide {{ $statusBadge }}">{{ $status ?: '—' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($msgPaginator->hasPages())
                    <div
                        class="px-5 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between text-[11.5px] text-ink-500">
                        <div class="font-mono">
                            Showing {{ $msgPaginator->firstItem() }}–{{ $msgPaginator->lastItem() }} of
                            {{ number_format($msgPaginator->total()) }} · Page {{ $msgPaginator->currentPage() }} of
                            {{ $msgPaginator->lastPage() }}
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if ($msgPaginator->onFirstPage())
                                <span
                                    class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Prev') }}</span>
                            @else
                                <a href="{{ $msgPaginator->previousPageUrl() }}"
                                    class="px-3 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-ink-900 font-semibold">Prev</a>
                            @endif
                            @php
                                $pages = [];
                                for (
                                    $i = max(1, $msgPaginator->currentPage() - 2);
                                    $i <= min($msgPaginator->lastPage(), $msgPaginator->currentPage() + 2);
                                    $i++
                                ) {
                                    $pages[] = $i;
                                }
                                if (!in_array(1, $pages, true)) {
                                    array_unshift($pages, 1);
                                }
                                if (!in_array($msgPaginator->lastPage(), $pages, true)) {
                                    $pages[] = $msgPaginator->lastPage();
                                }
                            @endphp
                            @foreach ($pages as $p)
                                @if ($p === $msgPaginator->currentPage())
                                    <span
                                        class="px-3 py-1 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold">{{ $p }}</span>
                                @else
                                    <a href="{{ $msgPaginator->url($p) }}"
                                        class="px-3 py-1 rounded-full hover:bg-paper-50 text-ink-700">{{ $p }}</a>
                                @endif
                            @endforeach
                            @if ($msgPaginator->hasMorePages())
                                <a href="{{ $msgPaginator->nextPageUrl() }}"
                                    class="px-3 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-ink-900 font-semibold">Next</a>
                            @else
                                <span
                                    class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Next') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </section>

        {{-- Health sidebar --}}
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-[14px] p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Health checks') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-4">{{ __('Things to watch') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @if ($isConnected)
                        <div class="flex items-start gap-2.5 p-3 rounded-lg bg-wa-deep/5 border border-wa-deep/15">
                            <span
                                class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Bridge online') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">Last sync
                                    {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}.</div>
                            </div>
                        </div>
                    @else
                        <div
                            class="flex items-start gap-2.5 p-3 rounded-lg bg-accent-amber/10 border border-accent-amber/30">
                            <span
                                class="w-7 h-7 rounded-full bg-accent-amber text-paper-0 grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 4v5M8 12h.01" />
                                </svg></span>
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Not connected') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">
                                    {{ __('Re-pair from the device list to start sending again.') }}</div>
                            </div>
                        </div>
                    @endif
                    @if ($deliveryPct >= 95 && $sent7d > 0)
                        <div class="flex items-start gap-2.5 p-3 rounded-lg bg-wa-deep/5 border border-wa-deep/15">
                            <span
                                class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M3 8l3 3 7-8" />
                                </svg></span>
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Delivery rate is healthy') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">{{ $deliveryPct }}% over the last 7
                                    days.</div>
                            </div>
                        </div>
                    @elseif ($sent7d > 0)
                        <div
                            class="flex items-start gap-2.5 p-3 rounded-lg bg-accent-amber/10 border border-accent-amber/30">
                            <span
                                class="w-7 h-7 rounded-full bg-accent-amber text-paper-0 grid place-items-center shrink-0"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 4v5M8 12h.01" />
                                </svg></span>
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Delivery rate slipping') }}</div>
                                <div class="text-[11px] text-ink-500 mt-0.5">{{ $deliveryPct }}% — review failure
                                    reasons in the table above.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="bg-wa-deep rounded-[14px] p-5 shadow-soft text-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60">
                    {{ __('Linked sessions') }}</div>
                <div class="font-serif text-[20px] leading-tight mt-1">{{ $device->device_name }}</div>
                <ul class="mt-3 space-y-2 text-[12px]">
                    <li class="flex items-center justify-between"><span>{{ __('Region') }}</span><span
                            class="font-mono text-paper-0/70">{{ $device->region ?: '—' }}</span></li>
                    <li class="flex items-center justify-between"><span>{{ __('Dial code') }}</span><span
                            class="font-mono text-paper-0/70">{{ $device->country_code ?: '—' }}</span></li>
                    <li class="flex items-center justify-between"><span>{{ __('Status') }}</span><span
                            class="font-mono text-paper-0/70">{{ $statusPill['label'] }}</span></li>
                    <li class="flex items-center justify-between"><span>{{ __('Added') }}</span><span
                            class="font-mono text-paper-0/70">{{ $device->created_at?->format('M d, Y') ?? '—' }}</span>
                    </li>
                </ul>
                <a href="{{ url('/devices') }}"
                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-paper-0 text-wa-deep px-4 py-2 text-[12px] font-semibold">{{ __('Manage devices') }}<svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3l5 5-5 5" />
                    </svg></a>
            </div>
        </section>

        {{-- Auto-refresh the page in-place every 5s so the live feed
 reflects scheduled sends + status flips without forcing a
 manual reload. Pauses when the tab is hidden. --}}
        <script>
            (() => {
                const POLL_MS = 5000;
                const TBODIES = ['device-sends-tbody', 'device-failures-tbody'];
                let timer = null;
                const status = document.getElementById('device-feed-status');
                const sigCache = new Map();

                async function tick() {
                    if (document.hidden) return;
                    try {
                        const res = await fetch(window.location.href, {
                            headers: {
                                'Accept': 'text/html'
                            },
                            cache: 'no-store'
                        });
                        if (!res.ok) return;
                        const html = await res.text();
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        let anyChanged = false;
                        for (const id of TBODIES) {
                            const fresh = doc.getElementById(id);
                            const here = document.getElementById(id);
                            if (!fresh || !here) continue;
                            const sig = fresh.innerHTML.trim();
                            if (sig !== sigCache.get(id)) {
                                here.innerHTML = fresh.innerHTML;
                                sigCache.set(id, sig);
                                anyChanged = true;
                            }
                        }
                        if (status) {
                            status.textContent = anyChanged ?
                                'updated ' + new Date().toLocaleTimeString() :
                                'auto-refresh 5s · last check ' + new Date().toLocaleTimeString();
                        }
                    } catch (e) {
                        /* silent */ }
                }

                function start() {
                    if (!timer) timer = setInterval(tick, POLL_MS);
                }

                function stop() {
                    if (timer) {
                        clearInterval(timer);
                        timer = null;
                    }
                }
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) stop();
                    else {
                        tick();
                        start();
                    }
                });
                // Capture initial signatures so the first tick doesn't flash.
                for (const id of TBODIES) {
                    const el = document.getElementById(id);
                    if (el) sigCache.set(id, el.innerHTML.trim());
                }
                start();
            })();
        </script>

    </main>

</x-layouts.user>
