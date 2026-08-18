<x-layouts.user :title="__('Products')" nav-key="connect" page="user-store-products-index">

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
            @include('user.store._sidebar', ['current' => 'products', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 min-w-0">
                <div class="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            {{ __('Store / Products') }}</div>
                        <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Catalog') }}</h1>
                    </div>
                    <a href="{{ route('user.store.products.create') }}"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2"><svg
                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M8 3v10M3 8h10" />
                        </svg>{{ __('Add product') }}</a>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif

                {{-- Card is a plain <div>, NOT a <form> — the per-row Delete forms
                     live inside this card, and nesting them in a wrapping <form>
                     is invalid HTML (the browser drops the inner form, so clicking
                     Delete just re-submitted the search). The search box is now its
                     OWN small form so it still filters, and Delete works. --}}
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <form method="GET" class="relative max-w-[480px]">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input type="search" name="q" value="{{ $q }}"
                                placeholder="{{ __('Search by name or SKU...') }}"
                                class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                            <tr>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                    {{ __('Image') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Name') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('SKU') }}</th>
                                <th class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Price') }}</th>
                                <th class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                    {{ __('Stock') }}</th>
                                <th
                                    class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5 w-[280px]">
                                    {{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            @forelse ($rows as $p)
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-2.5">
                                        @if ($p->image_url)
                                            <img src="{{ $p->image_url }}" alt=""
                                                class="w-12 h-12 object-cover rounded-lg" />
                                        @else
                                            <span
                                                class="w-12 h-12 rounded-lg bg-paper-100 grid place-items-center text-ink-500"><svg
                                                    viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                                    stroke="currentColor" stroke-width="1.5">
                                                    <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                                    <circle cx="6" cy="7" r="1" />
                                                    <path d="m3 12 3-3 2 2 3-3 2 2" />
                                                </svg></span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="font-medium">{{ $p->name }}</div>
                                        @if ($p->description)
                                            <div class="text-[10.5px] text-ink-500 max-w-md truncate">
                                                {{ \Illuminate\Support\Str::limit($p->description, 80) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">{{ $p->sku ?: '—' }}
                                    </td>
                                    <td class="px-2 py-2.5 text-right font-semibold">{{ $p->price_display }}</td>
                                    <td class="px-2 py-2.5">
                                        @if ($p->in_stock)
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>In stock</span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-500 text-[10.5px] font-mono"><span
                                                    class="w-1.5 h-1.5 rounded-full bg-paper-200"></span>Out</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        <button type="button" onclick="shareProduct({{ $p->id }})"
                                            class="text-[11px] text-wa-deep font-semibold hover:underline mr-2">Share</button>
                                        <a href="{{ route('user.store.products.edit', $p->id) }}"
                                            class="text-[11px] text-ink-700 hover:underline mr-2">Edit</a>
                                        <form method="POST"
                                            action="{{ route('user.store.products.destroy', $p->id) }}"
                                            class="inline-block" onsubmit="return confirm('Delete this product?')">@csrf
                                            @method('DELETE')<button type="submit"
                                                class="text-[11px] text-accent-coral hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-ink-500">
                                        <div class="font-serif text-[20px]">{{ __('No products yet') }}</div>
                                        <p class="mt-1 text-[12.5px]">
                                            {{ __('Click "Add product" to create your first one.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                    @if ($rows->hasPages())
                        <div class="px-4 py-3 border-t border-paper-200">{{ $rows->links() }}</div>
                    @endif
                </div>
            </section>
        </div>
    </main>

    <!-- Share modal -->
    <div id="share-modal" class="hidden fixed inset-0 z-50 bg-ink-900/40 grid place-items-center px-4"
        onclick="if(event.target===this){this.classList.add('hidden')}">
        <div class="bg-paper-0 rounded-2xl shadow-soft max-w-md w-full p-5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Share product') }}</div>
            <h3 class="font-serif text-[20px] mt-1" id="share-product-name">{{ __('Product') }}</h3>
            <div class="mt-4 space-y-2.5 text-[12.5px]">
                <div>
                    <div class="font-semibold mb-1">{{ __('Storefront link') }}</div>
                    <div class="flex items-center gap-2">
                        <input id="share-public" readonly
                            class="flex-1 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-50 text-[11.5px] font-mono" />
                        <button type="button" onclick="copyAndFlash('share-public', this)"
                            class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('Copy') }}</button>
                    </div>
                </div>
                <div>
                    <div class="font-semibold mb-1">{{ __('WhatsApp deep link') }}</div>
                    <div class="flex items-center gap-2">
                        <input id="share-wa" readonly
                            class="flex-1 px-2.5 py-1.5 border border-paper-200 rounded-lg bg-paper-50 text-[11.5px] font-mono" />
                        <button type="button" onclick="copyAndFlash('share-wa', this)"
                            class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('Copy') }}</button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button type="button" onclick="document.getElementById('share-modal').classList.add('hidden')"
                    class="px-4 py-2 border border-paper-200 rounded-full text-[12px] font-medium hover:bg-paper-50">{{ __('Close') }}</button>
            </div>
        </div>
    </div>

    <script>
        async function shareProduct(id) {
            const res = await fetch(`/store/products/${id}/share-links`, {
                headers: {
                    Accept: 'application/json'
                }
            });
            if (!res.ok) return;
            const j = await res.json();
            document.getElementById('share-product-name').textContent = j.product.name;
            document.getElementById('share-public').value = j.public_url || '(set up your storefront slug first)';
            document.getElementById('share-wa').value = j.wa_link || '(connect a phone number first)';
            document.getElementById('share-modal').classList.remove('hidden');
        }

        function copyAndFlash(id, btn) {
            navigator.clipboard.writeText(document.getElementById(id).value);
            const t = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = t, 1500);
        }
    </script>

</x-layouts.user>
