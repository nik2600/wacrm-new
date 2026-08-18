<x-layouts.user :title="__('Coupons')" nav-key="connect" page="user-store-coupons-index">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
        $cur = $sf?->currency_code ?: 'INR';
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'coupons', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                        {{ __('Store / Coupons') }}</div>
                    <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Discount codes') }}</h1>
                    <p class="text-[13px] text-ink-600 mt-1">
                        {{ __('Codes customers can apply at checkout — percent or flat off, with optional minimum spend, cap, free shipping, expiry and usage limit.') }}
                    </p>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div
                        class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                        @foreach ($errors->all() as $e)
                            <div>{{ $e }}</div>
                        @endforeach
                    </div>
                @endif

                {{-- Create --}}
                <form method="POST" action="{{ route('user.store.coupons.store') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    @csrf
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-3">
                        {{ __('New coupon') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Code') }}</span>
                            <input name="code" required maxlength="64" placeholder="SUMMER10"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] uppercase focus:outline-none focus:border-wa-deep"></label>
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Type') }}</span>
                            <select name="type"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] bg-paper-0 focus:outline-none focus:border-wa-deep">
                                <option value="percent">{{ __('Percent (%)') }}</option>
                                <option value="flat">{{ __('Flat amount') }} ({{ $cur }})</option>
                            </select></label>
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Amount') }}</span>
                            <input name="amount" type="number" step="0.01" min="0" required
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Min spend') }}
                                ({{ $cur }})</span>
                            <input name="min_subtotal" type="number" step="0.01" min="0"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Max discount cap') }}
                                ({{ $cur }})</span>
                            <input name="max_discount" type="number" step="0.01" min="0"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Usage limit') }}</span>
                            <input name="usage_limit" type="number" min="1" placeholder="{{ __('unlimited') }}"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="block"><span
                                class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-500">{{ __('Expires') }}</span>
                            <input name="expires_at" type="date"
                                class="mt-1 w-full border border-paper-200 rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                        <label class="flex items-center gap-2 text-[12.5px] mt-6"><input type="hidden"
                                name="free_shipping" value="0"><input type="checkbox" name="free_shipping"
                                value="1"
                                class="rounded border-paper-200 text-wa-deep">{{ __('Free shipping') }}</label>
                    </div>
                    <div class="flex justify-end mt-4 pt-3 border-t border-paper-200">
                        <button
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Create coupon') }}</button>
                    </div>
                </form>

                {{-- List --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="px-5 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Code') }}</th>
                                <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Discount') }}</th>
                                <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Conditions') }}</th>
                                <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Used') }}</th>
                                <th class="px-2 py-2.5 text-left font-mono text-[10px] uppercase tracking-[0.14em]">
                                    {{ __('Status') }}</th>
                                <th class="px-5 py-2.5 text-right font-mono text-[10px] uppercase tracking-[0.14em]">
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($coupons as $c)
                                <tr>
                                    <td class="px-5 py-3 font-mono font-semibold">{{ $c->code }}</td>
                                    <td class="px-2 py-3">
                                        {{ $c->type === 'percent' ? $c->amount . '%' : \App\Models\WaProduct::formatCurrency($c->amount, $cur) }}
                                        @if ($c->free_shipping)
                                            <span class="ml-1 text-[10px] text-wa-deep">+ {{ __('free ship') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-ink-600 text-[11.5px]">
                                        @if ($c->min_subtotal_minor)
                                            {{ __('min') }}
                                            {{ \App\Models\WaProduct::formatCurrency($c->min_subtotal_minor, $cur) }}
                                        @endif
                                        @if ($c->expires_at)
                                            · {{ __('exp') }} {{ $c->expires_at->format('d M Y') }}
                                        @endif
                                        @if ($c->usage_limit)
                                            · {{ __('limit') }} {{ $c->usage_limit }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-3">{{ $c->used_count }}</td>
                                    <td class="px-2 py-3">
                                        <form method="POST" action="{{ route('user.store.coupons.update', $c->id) }}"
                                            class="inline">@csrf @method('PUT')<input type="hidden" name="toggle"
                                                value="1">
                                            <button
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $c->active ? 'bg-wa-mint text-wa-deep' : 'bg-paper-50 text-ink-500' }}">{{ $c->active ? __('active') : __('off') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('user.store.coupons.destroy', $c->id) }}"
                                            class="inline"
                                            onsubmit="return confirm('{{ __('Delete this coupon?') }}')">@csrf
                                            @method('DELETE')
                                            <button
                                                class="text-[11px] text-accent-coral font-semibold hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-ink-500">
                                        {{ __('No coupons yet. Create one above.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
                @if ($coupons->hasPages())
                    <div>{{ $coupons->links() }}</div>
                @endif
            </section>
        </div>
    </main>
</x-layouts.user>
