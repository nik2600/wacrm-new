@php
    $p = $package ?? null;
    $currencies = $currencies ?? [
        'INR' => '₹ INR',
        'USD' => '$ USD',
        'EUR' => '€ EUR',
        'GBP' => '£ GBP',
        'AED' => 'AED',
    ];
    $action = $p ? route('admin.credit-packages.update', $p->id) : route('admin.credit-packages.store');
@endphp
<form id="creditPkgForm" method="POST" action="{{ $action }}" class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    @csrf
    @if ($p)
        @method('PUT')
    @endif

    {{-- ── Section 1: Identity ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Identity') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="cp-name">{{ __('Name') }}
                    <span class="text-accent-coral">*</span></label>
                <input id="cp-name" type="text" name="name" required maxlength="96"
                    value="{{ old('name', $p?->name) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('Starter / Growth / Scale') }}">
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="cp-slug">{{ __('Slug') }}</label>
                <input id="cp-slug" type="text" name="slug" maxlength="96" value="{{ old('slug', $p?->slug) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('auto-generated from name') }}">
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('Used in the checkout URL; leave blank to auto-generate.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="cp-description">{{ __('Description') }}</label>
                <textarea id="cp-description" name="description" rows="3" maxlength="500"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('Short blurb shown on the wallet page.') }}">{{ old('description', $p?->description) }}</textarea>
            </div>
        </div>
    </div>

    {{-- ── Section 2: Price & credits ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Pricing') }}</span>
        </div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="cp-price">{{ __('Price') }} <span class="text-accent-coral">*</span></label>
                    <input id="cp-price" type="number" name="price_major" required min="0" step="0.01"
                        value="{{ old('price_major', $p ? number_format($p->price_minor / 100, 2, '.', '') : '') }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="100">
                    <div class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('In whole currency units (100 = 100 of the unit picked at right).') }}</div>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="cp-currency">{{ __('Currency') }}</label>
                    <select id="cp-currency" name="currency_code"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @foreach ($currencies as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency_code', $p?->currency_code ?? 'INR') === $code)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="cp-credits">{{ __('Credits') }} <span class="text-accent-coral">*</span></label>
                <input id="cp-credits" type="number" name="credits" required min="1" max="100000000"
                    value="{{ old('credits', $p?->credits) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="5000">
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('Whole number of message credits this package buys.') }}</div>
            </div>
        </div>
    </div>

    {{-- ── Section 3: Display & visibility ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Display') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="cp-badge">{{ __('Badge') }}</label>
                <input id="cp-badge" type="text" name="badge" maxlength="32"
                    value="{{ old('badge', $p?->badge) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('Most popular / Best value') }}">
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Optional label shown above the price.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="cp-sort">{{ __('Sort order') }}</label>
                <input id="cp-sort" type="number" name="sort_order" min="0" max="9999"
                    value="{{ old('sort_order', $p?->sort_order ?? 0) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Lower numbers appear first on the wallet page.') }}
                </div>
            </div>
            <div class="space-y-2 pt-2">
                <label
                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                    <span>
                        <span class="block text-[12.5px] font-semibold">{{ __('Active') }}</span>
                        <span
                            class="block text-[10.5px] text-ink-500">{{ __('Visible on the user-side wallet') }}</span>
                    </span>
                    <input type="hidden" name="is_active" value="0">
                    <span class="relative inline-block w-[34px] h-5 shrink-0">
                        <input class="peer opacity-0 w-0 h-0" type="checkbox" name="is_active" value="1"
                            @checked(old('is_active', $p?->is_active ?? 1))>
                        <span
                            class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                    </span>
                </label>
                <label
                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                    <span>
                        <span class="block text-[12.5px] font-semibold">{{ __('Featured') }}</span>
                        <span
                            class="block text-[10.5px] text-ink-500">{{ __('Highlighted card on wallet page') }}</span>
                    </span>
                    <input type="hidden" name="is_featured" value="0">
                    <span class="relative inline-block w-[34px] h-5 shrink-0">
                        <input class="peer opacity-0 w-0 h-0" type="checkbox" name="is_featured" value="1"
                            @checked(old('is_featured', $p?->is_featured ?? 0))>
                        <span
                            class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    {{-- Footer actions span all 3 columns. --}}
    <div class="xl:col-span-3 flex justify-end gap-2 pt-1">
        <a href="{{ route('admin.credit-packages.index') }}"
            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12.5px] font-medium">{{ __('Cancel') }}</a>
        <button type="submit"
            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 8l5 5 7-9" />
            </svg>
            {{ $p ? 'Save changes' : 'Create package' }}
        </button>
    </div>
</form>
