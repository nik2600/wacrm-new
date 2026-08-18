<x-layouts.user :title="__('Order') . ' #' . $order->id" nav-key="connect" page="user-store-orders-show">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
        $items = $order->items_json ?? [];
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'orders', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 max-w-4xl min-w-0">
                <div class="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            <a href="{{ route('user.store.orders.index') }}"
                                class="hover:text-wa-deep">{{ __('Orders') }}</a> / #{{ $order->id }}
                        </div>
                        <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">Order #{{ $order->id }}
                        </h1>
                        @if (($order->payment_method ?? 'prepaid') === 'cod')
                            @php
                                $band = $order->rto_band ?? 'low';
                                $bc = $band === 'high' ? 'bg-accent-coral/15 text-accent-coral' : ($band === 'medium' ? 'bg-accent-amber/15 text-accent-amber' : 'bg-wa-mint text-wa-deep');
                            @endphp
                            <div class="mt-2 flex items-center gap-2 flex-wrap">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono bg-paper-50 text-ink-700 border border-paper-200">{{ __('COD') }}</span>
                                @if ($order->rto_score !== null)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $bc }}"
                                        title="{{ __('Return-to-origin risk') }}">{{ __('RTO risk') }}:
                                        {{ ucfirst($band) }} ({{ $order->rto_score }})</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-[11px] text-ink-500 font-mono">{{ $order->source }} ·
                            {{ $order->created_at->format('M d, Y H:i') }}</div>
                    </div>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">
                    <!-- Items + status -->
                    <div class="space-y-5 min-w-0">
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <h3 class="font-serif text-[18px] mb-3">{{ __('Items') }}</h3>
                            <div class="divide-y divide-paper-200">
                                @php $orderCur = $order->currency ?? \App\Models\SystemSetting::get('default_currency', 'USD'); @endphp
                                @foreach ($items as $item)
                                    <div class="py-3 flex items-center gap-3">
                                        @if (!empty($item['image']))
                                            <img src="{{ $item['image'] }}"
                                                class="w-12 h-12 rounded-lg object-cover" />
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-paper-100"></div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium">{{ $item['name'] ?? 'Item' }}</div>
                                            <div class="text-[11px] text-ink-500 font-mono">qty:
                                                {{ $item['qty'] ?? 1 }} · {!! \App\Support\FormatSettings::formatIn(($item['price_minor'] ?? 0) / 100, $orderCur) !!}</div>
                                        </div>
                                        <div class="font-semibold">{!! \App\Support\FormatSettings::formatIn(
                                            (((int) ($item['price_minor'] ?? 0)) * ((int) ($item['qty'] ?? 1))) / 100,
                                            $orderCur,
                                        ) !!}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="border-t border-paper-200 mt-3 pt-3 flex items-center justify-between">
                                <span class="font-mono text-[11px] text-ink-500">{{ __('TOTAL') }}</span>
                                <span class="font-serif text-[24px]">{{ $order->total_display }}</span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('user.store.orders.update', $order->id) }}"
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-3">
                            @csrf @method('PUT')
                            <h3 class="font-serif text-[18px]">{{ __('Update status') }}</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Status') }}</span>
                                    <select name="status"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                        @foreach (\App\Models\WaOrder::STATUSES as $s)
                                            <option value="{{ $s }}" @selected($order->status === $s)>
                                                {{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Payment link') }}
                                        <span class="text-ink-500 font-normal">(optional)</span></span>
                                    <input type="url" name="payment_link" maxlength="1024"
                                        value="{{ $order->payment_link }}" placeholder="https://rzp.io/..."
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                </label>
                            </div>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Notes') }}</span>
                                <textarea name="notes" rows="2" maxlength="1000"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">{{ $order->notes }}</textarea>
                            </label>
                            <div class="flex justify-end gap-2 pt-2 border-t border-paper-200 flex-wrap">
                                <button type="button" onclick="generatePaymentLink()"
                                    class="px-3 py-1.5 border border-wa-deep/40 text-wa-deep rounded-full text-[12px] hover:bg-wa-mint/40 font-semibold">{{ __('Generate Razorpay link + send') }}</button>
                                @if ($order->payment_link)
                                    <button type="button" onclick="sendPaymentLink()"
                                        class="px-3 py-1.5 border border-paper-200 rounded-full text-[12px] hover:bg-paper-50">{{ __('Send payment link via WhatsApp') }}</button>
                                @endif
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                            </div>
                        </form>
                    </div>

                    <!-- Customer + meta -->
                    <aside class="space-y-3">
                        {{-- WhatsApp Pay — native in-chat charge (India) --}}
                        @php
                            $wps = $order->wa_payment_status;
                            $wpsCls = match ($wps) {
                                'captured' => 'bg-wa-mint text-wa-deep border-wa-green/40',
                                'failed'   => 'bg-red-50 text-red-700 border-red-200',
                                'refunded' => 'bg-paper-50 text-ink-500 border-paper-200',
                                'pending'  => 'bg-amber-50 text-amber-700 border-amber-200',
                                default    => null,
                            };
                        @endphp
                        <form method="POST" action="{{ route('user.store.orders.whatsapp-pay', $order->id) }}"
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            @csrf
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('WhatsApp Pay') }}</div>
                                @if ($wpsCls)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono border {{ $wpsCls }}">{{ ucfirst($wps) }}</span>
                                @endif
                            </div>
                            <p class="text-[12px] text-ink-600 mt-1.5 leading-relaxed">{{ __('Send a native in-chat payment request. The customer pays without leaving WhatsApp (India only).') }}</p>
                            @if ($order->wa_payment_reference_id)
                                <div class="mt-2 font-mono text-[10.5px] text-ink-500 break-all">ref: {{ $order->wa_payment_reference_id }}</div>
                            @endif
                            <button type="submit"
                                class="mt-3 w-full px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                                {{ $wps === 'captured' ? __('Re-send order details') : __('Request payment on WhatsApp') }}</button>
                            <a href="{{ route('user.store.payments.index') }}" class="block mt-2 text-center text-[11px] text-wa-deep hover:underline">{{ __('Configure WhatsApp Pay →') }}</a>
                        </form>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Customer') }}</div>
                            <div class="font-serif text-[18px] mt-1">{{ $order->customer_name ?: 'Anonymous' }}</div>
                            <div class="font-mono text-[11px] text-ink-700 mt-1">{{ $order->customer_phone }}</div>
                            @if ($order->customer_email)
                                <div class="font-mono text-[11px] text-ink-500">{{ $order->customer_email }}</div>
                            @endif
                            <a href="{{ url('/chat') }}"
                                class="mt-3 inline-flex items-center gap-1 text-[11.5px] text-wa-deep font-semibold hover:underline">
                                {{ __('Reply on WhatsApp →') }}
                            </a>
                        </div>

                        {{-- Shipping address — captured by the order flow (Jessica #2).
                             customer_name/address live on wa_orders; company rides in
                             meta_json.ship_company. Shows "not captured" when empty so
                             the operator can see whether the flow saved it. --}}
                        @php
                            $oMeta       = (array) ($order->meta_json ?? []);
                            $shipAddr    = trim((string) ($order->customer_address ?? ''));
                            $shipCompany = trim((string) ($oMeta['ship_company'] ?? ''));
                        @endphp
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Shipping address') }}</div>
                            @if ($shipAddr !== '' || $shipCompany !== '')
                                @if ($order->customer_name)
                                    <div class="text-[13px] font-semibold mt-1.5">{{ $order->customer_name }}</div>
                                @endif
                                @if ($shipCompany !== '')
                                    <div class="text-[11.5px] text-ink-700">{{ $shipCompany }}</div>
                                @endif
                                @if ($shipAddr !== '')
                                    <div class="text-[11.5px] text-ink-700 mt-1 whitespace-pre-line leading-relaxed">{{ $shipAddr }}</div>
                                @endif
                            @else
                                <div class="text-[11.5px] text-ink-400 mt-1.5 italic">{{ __('No address captured for this order.') }}</div>
                            @endif
                        </div>

                        {{-- Voice / Photo order (Jessica #3) — the ORIGINAL voice note or
                             photo the customer sent, so the merchant can replay/inspect
                             it. Path + transcript live on meta_json; media_url() is
                             cloud-aware (local public disk or configured cloud storage). --}}
                        @php
                            $omPath  = trim((string) ($oMeta['order_media_path'] ?? ''));
                            $omType  = (string) ($oMeta['order_media_type'] ?? '');
                            $omText  = trim((string) ($oMeta['order_media_transcript'] ?? ''));
                            $omUrl   = $omPath !== '' ? media_url($omPath) : '';
                        @endphp
                        @if ($omUrl !== '')
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                                    @if ($omType === 'voice')
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                                        {{ __('Voice order') }}
                                    @else
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                        {{ __('Photo order') }}
                                    @endif
                                </div>
                                @if ($omType === 'voice')
                                    <audio controls preload="none" class="w-full mt-2.5" src="{{ $omUrl }}"></audio>
                                @else
                                    <a href="{{ $omUrl }}" target="_blank" rel="noopener">
                                        <img src="{{ $omUrl }}" alt="{{ __('Customer photo') }}" class="mt-2.5 rounded-xl border border-paper-200 max-h-64 w-auto object-contain">
                                    </a>
                                @endif
                                @if ($omText !== '')
                                    <div class="mt-2.5 text-[11.5px] text-ink-600 italic whitespace-pre-line leading-relaxed">
                                        <span class="not-italic font-mono text-[9.5px] uppercase tracking-wider text-ink-400">{{ __('AI transcript') }}</span><br>
                                        “{{ $omText }}”
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Order meta') }}</div>
                            <dl class="text-[11.5px] mt-2 space-y-1">
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ __('Order ID') }}</dt>
                                    <dd class="font-mono">#{{ $order->id }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ __('Source') }}</dt>
                                    <dd class="font-mono">{{ $order->source }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ __('Channel msg id') }}</dt>
                                    <dd class="font-mono truncate ml-2">{{ $order->wa_message_id ?: '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </aside>
                </div>
            </section>
        </div>
    </main>

    <script>
        async function sendPaymentLink() {
            const link = document.querySelector('[name=payment_link]').value;
            if (!link) return alert('Save the payment link first.');
            const r = await fetch(@json(route('user.store.orders.payment-link', $order->id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    Accept: 'application/json'
                },
                body: JSON.stringify({
                    payment_link: link
                }),
            });
            const j = await r.json();
            alert(j.ok ? 'Payment link sent.' : 'Failed to send.');
        }
        async function generatePaymentLink() {
            const r = await fetch(@json(route('user.store.orders.generate-payment-link', $order->id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    Accept: 'application/json'
                },
            });
            const j = await r.json().catch(() => ({}));
            if (j.ok) {
                alert('Payment link generated' + (j.sent ? ' and sent on WhatsApp.' : '.'));
                location.reload();
            } else {
                alert(j.message || 'Could not generate link.');
            }
        }
    </script>
</x-layouts.user>
