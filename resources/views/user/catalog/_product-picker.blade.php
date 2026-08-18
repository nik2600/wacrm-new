{{--
 Reusable product checkbox grid for building a collection.
 Params:
 $pickProducts Collection of WaProduct (id, name, sku, image_url, price_minor, currency_code)
 $selected array of pre-checked product ids (edit mode)
 $fieldName input name (default product_ids[])
--}}
@php
    $selected = $selected ?? [];
    $fieldName = $fieldName ?? 'product_ids[]';
@endphp
<div data-picker class="border border-paper-200 rounded-xl overflow-hidden">
    <div class="px-3 py-2 border-b border-paper-200 bg-paper-50 flex items-center gap-2">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6">
            <circle cx="7" cy="7" r="4.5" />
            <path d="m11 11 3 3" />
        </svg>
        <input type="search" data-picker-search placeholder="{{ __('Filter products…') }}"
            class="flex-1 bg-transparent text-[12px] focus:outline-none">
        <span class="text-[10.5px] text-ink-500 font-mono"><span
                data-picker-count>{{ count($selected) }}</span>/30</span>
    </div>
    <div class="max-h-72 overflow-y-auto p-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-1.5">
        @forelse ($pickProducts as $p)
            <label data-pick-card data-name="{{ Str::lower($p->name . ' ' . $p->sku) }}"
                class="relative block cursor-pointer">
                <input type="checkbox" name="{{ $fieldName }}" value="{{ $p->id }}"
                    @checked(in_array($p->id, $selected)) class="sr-only peer">
                <div
                    class="border border-paper-200 rounded-lg p-2 peer-checked:border-wa-deep peer-checked:ring-1 peer-checked:ring-wa-deep/40 hover:bg-paper-50 transition">
                    <div class="aspect-square rounded-md bg-paper-50 overflow-hidden mb-1.5 grid place-items-center">
                        @if ($p->image_url)
                            <img src="{{ $p->image_url }}" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <svg viewBox="0 0 16 16" class="w-5 h-5 text-ink-400" fill="none" stroke="currentColor"
                                stroke-width="1.4">
                                <path d="M2 5l6-3 6 3v6l-6 3-6-3z" />
                                <path d="M2 5l6 3 6-3M8 8v6" />
                            </svg>
                        @endif
                    </div>
                    <div class="text-[11px] font-medium leading-tight line-clamp-2">{{ $p->name }}</div>
                    <div class="text-[10px] text-ink-500 font-mono mt-0.5">{{ $p->price_display }}</div>
                </div>
                <span
                    class="absolute top-1 right-1 w-4 h-4 rounded-full bg-wa-deep text-paper-0 hidden peer-checked:grid place-items-center">
                    <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor"
                        stroke-width="2.4">
                        <path d="m3 8 3 3 7-7" />
                    </svg>
                </span>
            </label>
        @empty
            <div class="col-span-full text-center text-ink-500 text-[12px] py-6">{{ __('No active products yet.') }} <a
                    href="{{ url('/store/products/create') }}"
                    class="text-wa-deep font-semibold hover:underline">{{ __('Add one →') }}</a></div>
        @endforelse
    </div>
</div>
