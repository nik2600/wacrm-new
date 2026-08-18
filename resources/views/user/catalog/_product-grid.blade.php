{{-- Compact product picker — shared between server-render and
 client-render on shop change. 5 cols on desktop, 80px square
 thumbnails (NOT aspect-square; that made cards huge). --}}
<div class="space-y-2" data-product-categories>
    @foreach ($productsByCategory as $catName => $catProducts)
        <details open class="border border-paper-200 rounded-xl bg-paper-50/30">
            <summary class="cursor-pointer flex items-center justify-between px-3 py-2 list-none">
                <span class="font-mono text-[11px] uppercase tracking-[0.14em] text-ink-500">{{ $catName }} <span
                        class="text-ink-700">· {{ $catProducts->count() }}</span></span>
                <span class="text-[10.5px] text-ink-500 font-mono">▾</span>
            </summary>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1.5 p-2 border-t border-paper-200"
                data-product-grid>
                @foreach ($catProducts as $p)
                    @php $rid = $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id); @endphp
                    <label data-product-card data-name="{{ strtolower($p->name) }}"
                        data-sku="{{ strtolower((string) $p->sku) }}" data-retailer-id="{{ $rid }}"
                        class="relative block cursor-pointer">
                        <input type="checkbox" name="product_ids[]" value="{{ $p->id }}"
                            class="sr-only peer product-pick">
                        <div
                            class="border peer-checked:border-wa-deep peer-checked:ring-2 peer-checked:ring-wa-deep/20 peer-checked:bg-wa-mint/30 rounded-lg overflow-hidden bg-paper-0 transition">
                            <div class="h-20 bg-paper-50 overflow-hidden grid place-items-center">
                                @if ($p->image_url)
                                    <img src="{{ $p->image_url }}" class="w-full h-full object-cover" loading="lazy"
                                        onerror="this.outerHTML='<span class=&quot;text-[20px]&quot;>📦</span>'">
                                @else
                                    <span class="text-[20px]">📦</span>
                                @endif
                            </div>
                            <div class="px-1.5 py-1">
                                <div class="text-[10.5px] font-semibold leading-tight truncate">{{ $p->name }}
                                </div>
                                <div class="text-[9.5px] font-mono text-wa-deep">{{ $p->price_display }}</div>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
