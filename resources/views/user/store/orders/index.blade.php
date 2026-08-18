<x-layouts.user :title="__('Orders')" nav-key="connect" page="user-store-orders-index">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'orders', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                        {{ __('Store / Orders') }}</div>
                    <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('All orders') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-1">
                        {{ __('Every order from every channel — WABA, storefront, Twilio, manual.') }}</p>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif

                <form method="GET" class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center gap-2 flex-wrap">
                        <div class="relative flex-1 min-w-[260px] max-w-[420px]">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input name="q" type="search" value="{{ $q }}"
                                placeholder="{{ __('Search by phone or name...') }}"
                                class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                        <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 overflow-x-auto max-w-full">
                            @foreach (['all' => 'All', 'new' => 'New', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'processing' => 'Processing', 'completed' => 'Completed', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'] as $k => $label)
                                <button type="submit" name="status" value="{{ $k }}"
                                    class="px-3 py-1 rounded-full text-[11.5px] font-semibold whitespace-nowrap shrink-0 {{ $status === $k ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-100' }}">{{ $label }}
                                    <span
                                        class="ml-1 font-mono text-[10px] opacity-80">{{ number_format($counts[$k] ?? 0) }}</span></button>
                            @endforeach
                        </div>
                        <select name="source" onchange="this.form.submit()"
                            class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep">
                            @foreach (['all' => 'All sources', 'whatsapp_ai' => 'AI Order', 'waba' => 'WABA', 'storefront' => 'Storefront', 'twilio' => 'Twilio', 'manual' => 'Manual'] as $k => $label)
                                <option value="{{ $k }}" @selected($source === $k)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('When') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Customer') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Source') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Items') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Total') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Status') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Open') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($rows as $o)
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-3 font-mono text-[11px] text-ink-700">
                                        <div>{{ $o->created_at->format('M d, H:i') }}</div>
                                        <div class="text-[10px] text-ink-500">{{ $o->created_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="px-2 py-3">
                                        @php $omType = (string) (is_array($o->meta_json ?? null) ? ($o->meta_json['order_media_type'] ?? '') : ''); @endphp
                                        <div class="font-medium flex items-center gap-1.5">
                                            {{ $o->customer_name ?: '—' }}
                                            @if ($omType === 'voice')
                                                <span title="{{ __('Ordered by voice note') }}"
                                                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[9px] font-mono uppercase tracking-wide">
                                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                                                    {{ __('Voice') }}
                                                </span>
                                            @elseif ($omType === 'image')
                                                <span title="{{ __('Ordered by photo') }}"
                                                      class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[9px] font-mono uppercase tracking-wide">
                                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                                    {{ __('Photo') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 font-mono">{{ $o->customer_phone }}
                                        </div>
                                    </td>
                                    <td class="px-2 py-3"><span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ $o->source }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-[11.5px]">{{ count($o->items_json ?? []) }}
                                        item{{ count($o->items_json ?? []) === 1 ? '' : 's' }}</td>
                                    <td class="px-2 py-3 text-right font-semibold">{{ $o->total_display }}</td>
                                    <td class="px-2 py-3">
                                        @php
                                            $cls = match ($o->status) {
                                                'paid' => 'bg-wa-mint text-wa-deep',
                                                'confirmed' => 'bg-[#D9E5F2] text-[#13478A]',
                                                'shipped' => 'bg-[#E8F5E9] text-wa-deep',
                                                'cancelled' => 'bg-accent-coral/15 text-accent-coral',
                                                default => 'bg-paper-100 text-ink-700',
                                            };
                                        @endphp
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $cls }} text-[10.5px] font-mono">{{ $o->status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right"><a
                                            href="{{ route('user.store.orders.show', $o->id) }}"
                                            class="text-[11px] text-wa-deep font-semibold hover:underline">Open</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-ink-500">
                                        <div class="font-serif text-[20px]">{{ __('No orders yet') }}</div>
                                        <p class="mt-1 text-[12.5px]">
                                            {{ __("When customers order from the storefront or via WABA, they'll appear here.") }}
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    @if ($rows->hasPages())
                        <div class="px-4 py-3 border-t border-paper-200">{{ $rows->links() }}</div>
                    @endif
                </form>
            </section>
        </div>
    </main>
</x-layouts.user>
