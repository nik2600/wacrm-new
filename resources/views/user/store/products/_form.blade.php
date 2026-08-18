@php
    $p = $product ?? null;
    $tagsCsv = $p && is_array($p->tags_json) ? implode(', ', $p->tags_json) : '';
    $galleryUrls = $p && is_array($p->gallery_json) ? $p->gallery_json : [];
@endphp
<form method="POST" action="{{ $p ? route('user.store.products.update', $p->id) : route('user.store.products.store') }}"
    enctype="multipart/form-data" data-product-form class="space-y-5">
    @csrf
    @if ($p)
        @method('PUT')
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    {{-- Two-column shell. Left column = wide editing area, right = sticky organization rail. --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5 items-start">

        {{-- ============================== LEFT COLUMN ============================== --}}
        <div class="space-y-5">

            {{-- Basics card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-3">{{ __('Basics') }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="block md:col-span-2">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Name') }} <span
                                class="text-accent-coral">*</span></span>
                        <input type="text" name="name" required maxlength="191"
                            value="{{ old('name', $p?->name) }}" placeholder="{{ __('e.g. Spring Cotton Tee') }}"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('SKU') }} <span
                                class="text-ink-500 font-normal">(optional)</span></span>
                        <input type="text" name="sku" maxlength="96" value="{{ old('sku', $p?->sku) }}"
                            placeholder="{{ __('ST-001') }}"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </label>
                </div>

                <label class="block mt-4">
                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Short description') }}</span>
                    <textarea name="description" rows="2" maxlength="2000"
                        placeholder="{{ __('Shown in catalog cards + WhatsApp share links.') }}"
                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">{{ old('description', $p?->description) }}</textarea>
                </label>

                <label class="block mt-4">
                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Full description') }} <span
                            class="text-ink-500 font-normal">(product page body)</span></span>
                    <textarea name="body_html" rows="6"
                        placeholder="{{ __('Sizing, fabric, care instructions — anything that helps the buyer decide. HTML allowed.') }}"
                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">{{ old('body_html', $p?->body_html) }}</textarea>
                </label>
            </section>

            {{-- Media card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Media') }}
                    </div>
                    <span class="text-[10.5px] text-ink-500">{{ __('Primary image · drop or paste a URL') }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-4 items-start">
                    <label for="product-image" data-product-dropzone
                        class="cursor-pointer block border-2 border-dashed border-paper-200 rounded-xl p-4 hover:border-wa-deep hover:bg-wa-mint/10 transition text-center">
                        <div data-image-frame
                            class="mx-auto w-full aspect-square rounded-lg overflow-hidden bg-paper-50 grid place-items-center mb-3">
                            @if ($p && $p->image_url)
                                <img data-image-preview src="{{ $p->image_url }}" class="w-full h-full object-cover" />
                            @else
                                <svg viewBox="0 0 24 24" class="w-10 h-10 text-ink-400" fill="none"
                                    stroke="currentColor" stroke-width="1.4">
                                    <rect x="3" y="5" width="18" height="14" rx="2" />
                                    <circle cx="8" cy="10" r="1.5" />
                                    <path d="m4 17 5-5 4 4 3-3 4 4" />
                                </svg>
                            @endif
                        </div>
                        <div class="text-[12px] font-semibold text-ink-900">{{ __('Click or drop an image') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('PNG, JPG, WebP · up to 4 MB') }}</div>
                        <input type="file" id="product-image" name="image" accept="image/*" class="sr-only"
                            data-image-input />
                        <div data-image-filename class="text-[10.5px] font-mono text-wa-deep mt-2 truncate"></div>
                    </label>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Or image URL') }}</span>
                            <input type="url" name="image_url" maxlength="1024"
                                value="{{ old('image_url', $p?->image_url) }}" placeholder="https://..."
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <span
                                class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Skip the upload — paste any direct image URL.') }}</span>
                        </label>

                        <div class="rounded-lg border border-paper-200 bg-paper-50/50 p-3 text-[11.5px] text-ink-700">
                            <div class="font-semibold text-ink-900 mb-0.5">{{ __('Tips for great product photos') }}
                            </div>
                            {{ __('Use square (1:1) images at 1024×1024 px or larger. Plain backgrounds convert better than busy ones.') }}
                        </div>
                    </div>
                </div>

                {{-- Gallery — extra product images (read by storefront product detail page) --}}
                <div class="mt-5 pt-4 border-t border-paper-200">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Gallery') }}</div>
                            <div class="text-[11px] text-ink-500 mt-0.5">
                                {{ __('Add more images to the product detail page. Buyers see these as thumbnails.') }}
                            </div>
                        </div>
                        <button type="button" data-gallery-add
                            class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            Add image URL
                        </button>
                    </div>

                    <div data-gallery-list class="space-y-2">
                        @foreach ($galleryUrls as $url)
                            <div class="flex items-center gap-2 group" data-gallery-row>
                                <div
                                    class="w-12 h-12 rounded-lg overflow-hidden bg-paper-50 grid place-items-center shrink-0 border border-paper-200">
                                    <img src="{{ $url }}" class="w-full h-full object-cover"
                                        onerror="this.outerHTML='<span class=&quot;text-ink-400 text-[10px]&quot;>404</span>'" />
                                </div>
                                <input type="url" name="gallery_urls[]" value="{{ $url }}"
                                    maxlength="1024"
                                    class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[12px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <button type="button" data-gallery-remove
                                    class="w-9 h-9 rounded-full border border-paper-200 hover:bg-accent-coral/10 hover:border-accent-coral/40 hover:text-accent-coral grid place-items-center text-ink-500"
                                    aria-label="{{ __('Remove') }}">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path d="M4 4l8 8M12 4l-8 8" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                        @if (empty($galleryUrls))
                            <div data-gallery-empty class="text-[11.5px] text-ink-500 italic">
                                {{ __('No gallery images yet — click "Add image URL" above to add one.') }}</div>
                        @endif
                    </div>

                    {{-- Hidden template that JS clones on Add --}}
                    <template data-gallery-template>
                        <div class="flex items-center gap-2 group" data-gallery-row>
                            <div
                                class="w-12 h-12 rounded-lg overflow-hidden bg-paper-50 grid place-items-center shrink-0 border border-paper-200">
                                <span class="text-ink-400 text-[10px]">{{ __('img') }}</span>
                            </div>
                            <input type="url" name="gallery_urls[]" value="" maxlength="1024"
                                placeholder="https://..."
                                class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[12px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <button type="button" data-gallery-remove
                                class="w-9 h-9 rounded-full border border-paper-200 hover:bg-accent-coral/10 hover:border-accent-coral/40 hover:text-accent-coral grid place-items-center text-ink-500"
                                aria-label="{{ __('Remove') }}">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M4 4l8 8M12 4l-8 8" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </section>

            {{-- Pricing card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-3">{{ __('Pricing') }}
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Price') }} <span
                                class="text-accent-coral">*</span></span>
                        <input type="number" name="price_major" required min="0" step="0.01"
                            value="{{ old('price_major', $p ? number_format($p->price_minor / 100, 2, '.', '') : '') }}"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Compare-at price') }} <span
                                class="text-ink-500 font-normal">(was)</span></span>
                        <input type="number" name="compare_price_major" min="0" step="0.01"
                            value="{{ old('compare_price_major', $p && $p->compare_price_minor ? number_format($p->compare_price_minor / 100, 2, '.', '') : '') }}"
                            placeholder="0.00"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        <span
                            class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Original price · shows as strikethrough.') }}</span>
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Currency') }}</span>
                        <select name="currency_code"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            @foreach ([
        'INR' => '₹ INR · Indian Rupee',
        'USD' => '$ USD · US Dollar',
        'EUR' => '€ EUR · Euro',
        'GBP' => '£ GBP · British Pound',
        'AED' => 'د.إ AED · UAE Dirham',
        'KES' => 'KSh KES · Kenyan Shilling',
        'NGN' => '₦ NGN · Nigerian Naira',
        'ZAR' => 'R ZAR · South African Rand',
        'BRL' => 'R$ BRL · Brazilian Real',
        'MXN' => '$ MXN · Mexican Peso',
        'CRC' => '₡ CRC · Costa Rican Colón',
        'PHP' => '₱ PHP · Philippine Peso',
        'IDR' => 'Rp IDR · Indonesian Rupiah',
        'SGD' => 'S$ SGD · Singapore Dollar',
        'MYR' => 'RM MYR · Malaysian Ringgit',
        'THB' => '฿ THB · Thai Baht',
        'VND' => '₫ VND · Vietnamese Đồng',
        'EGP' => 'E£ EGP · Egyptian Pound',
        'PKR' => '₨ PKR · Pakistani Rupee',
        'BDT' => '৳ BDT · Bangladeshi Taka',
        'LKR' => 'Rs LKR · Sri Lankan Rupee',
    ] as $c => $l)
                                <option value="{{ $c }}" @selected(old('currency_code', $p?->currency_code ?? 'INR') === $c)>{{ $l }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Profit indicator') }}</span>
                        <div data-profit-output
                            class="mt-1 px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono bg-paper-50 text-ink-700">
                            —</div>
                        <span
                            class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Auto · price vs compare-at.') }}</span>
                    </label>
                </div>
            </section>

            {{-- Inventory + shipping card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-3">
                    {{ __('Inventory & shipping') }}</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Stock qty') }}</span>
                        <input type="number" name="stock_qty" min="0" max="1000000"
                            value="{{ old('stock_qty', $p?->stock_qty) }}"
                            placeholder="{{ __('Blank = unlimited') }}"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Weight') }} <span
                                class="text-ink-500 font-normal">(g)</span></span>
                        <input type="number" name="weight_grams" min="0" max="1000000"
                            value="{{ old('weight_grams', $p?->weight_grams) }}" placeholder="e.g. 250"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        <span
                            class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Used for shipping cost calculations.') }}</span>
                    </label>
                    <label class="inline-flex items-start gap-2 text-[12.5px] mt-5">
                        <input type="hidden" name="in_stock" value="0" />
                        <input type="checkbox" name="in_stock" value="1" @checked(old('in_stock', $p?->in_stock ?? 1))
                            class="mt-0.5 rounded border-paper-200 text-wa-deep" />
                        <span>
                            <span class="font-semibold block">{{ __('Available for sale') }}</span>
                            <span
                                class="text-[10.5px] text-ink-500">{{ __('Uncheck to mark sold-out while keeping the product visible.') }}</span>
                        </span>
                    </label>
                    <label class="block">
                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Sort order') }}</span>
                        <input type="number" name="sort_order" min="0" max="9999"
                            value="{{ old('sort_order', $p?->sort_order ?? 0) }}"
                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        <span
                            class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Lower numbers appear first.') }}</span>
                    </label>
                </div>
            </section>
        </div>

        {{-- ============================== RIGHT COLUMN (sticky rail) ============================== --}}
        <aside class="space-y-4 lg:sticky lg:top-4">

            {{-- Status card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                    {{ __('Visibility') }}</div>
                <label class="block">
                    <select name="status"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @foreach ([
        'active' => 'Active — live on storefront',
        'draft' => 'Draft — hidden, editable',
        'archived' => 'Archived — hidden, kept in records',
    ] as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $p?->status ?? 'active') === $key)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </section>

            {{-- Organization card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                    {{ __('Organization') }}</div>
                <label class="block mb-3">
                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Category') }}</span>
                    <input type="text" name="category" maxlength="96"
                        value="{{ old('category', $p?->category) }}" list="category-suggestions"
                        placeholder="{{ __('e.g. Apparel, Snacks') }}"
                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    <datalist id="category-suggestions">
                        <option value="Apparel"></option>
                        <option value="Accessories"></option>
                        <option value="Beauty"></option>
                        <option value="Home"></option>
                        <option value="Food &amp; Drink"></option>
                        <option value="Electronics"></option>
                    </datalist>
                </label>
                <label class="block">
                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Tags') }} <span
                            class="text-ink-500 font-normal">(comma-separated)</span></span>
                    <input type="text" name="tags" maxlength="1024" value="{{ old('tags', $tagsCsv) }}"
                        placeholder="{{ __('cotton, summer, bestseller') }}"
                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                </label>
            </section>

            {{-- Live preview card --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                    {{ __('Storefront preview') }}</div>
                <div class="rounded-lg border border-paper-200 overflow-hidden">
                    <div data-preview-image class="aspect-square bg-paper-50 grid place-items-center">
                        @if ($p && $p->image_url)
                            <img src="{{ $p->image_url }}" class="w-full h-full object-cover" />
                        @else
                            <span class="text-ink-400 text-[10.5px]">{{ __('No image yet') }}</span>
                        @endif
                    </div>
                    <div class="p-3">
                        <div data-preview-name class="font-serif text-[15px] leading-tight">
                            {{ $p?->name ?: 'Product name' }}</div>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span data-preview-price
                                class="font-semibold text-[14px] text-ink-900">{{ $p?->price_display ?? \App\Support\FormatSettings::formatIn(0, $p?->currency_code ?? \App\Models\SystemSetting::get('default_currency', 'USD')) }}</span>
                            <span data-preview-compare
                                class="text-[11.5px] text-ink-500 line-through {{ $p && $p->on_sale ? '' : 'hidden' }}">{{ $p?->compare_price_display }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    {{-- Action bar. Kept IN-FLOW (not sticky): the app <body> is `overflow-x-clip`,
         which makes a `sticky bottom` bar pin to the bottom of the whole page
         (far below the fold, unreachable) instead of the viewport — the client
         couldn't scroll to Save. A normal footer sits at the end of the form and
         is reached by scrolling the page normally. `space-y-5` on the form spaces
         it from the cards above. --}}
    <div
        class="bg-paper-0 border border-paper-200 rounded-2xl p-3 shadow-card flex items-center justify-end gap-2">
        <a href="{{ route('user.store.products.index') }}"
            class="px-4 py-2 border border-paper-200 rounded-full text-[12px] font-medium hover:bg-paper-50">{{ __('Cancel') }}</a>
        <button type="submit"
            class="px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">
            {{ $p ? 'Save changes' : 'Create product' }}
        </button>
    </div>
</form>
