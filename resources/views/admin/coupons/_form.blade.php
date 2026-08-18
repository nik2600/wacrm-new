@php
    $c = $coupon ?? null;
    $action = $c ? route('admin.coupons.update', $c->id) : route('admin.coupons.store');
    $selectedPkg = old('applicable_package_ids', $c?->applicable_package_ids ?? []);
    $currencies = $currencies ?? ['' => 'Any currency'];
@endphp
<form id="couponForm" method="POST" action="{{ $action }}" class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    @csrf
    @if ($c)
        @method('PATCH')
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
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block" for="co-code">{{ __('Code') }}
                    <span class="text-accent-coral">*</span></label>
                <input id="co-code" type="text" name="code" required maxlength="64"
                    value="{{ old('code', $c?->code) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono uppercase focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('WELCOME10') }}">
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('Uppercased on save. This is what customers type at checkout.') }}</div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="co-desc">{{ __('Description') }} <span
                        class="text-ink-500 font-normal">{{ __('(shown to customer)') }}</span></label>
                <textarea id="co-desc" name="description" rows="2" maxlength="255"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('10% off the first month for new signups') }}">{{ old('description', $c?->description) }}</textarea>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="co-note">{{ __('Admin note') }} <span
                        class="text-ink-500 font-normal">{{ __('(internal, not shown)') }}</span></label>
                <textarea id="co-note" name="admin_note" rows="2" maxlength="1000"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="{{ __('Black Friday batch · Slack #marketing · expires after promo') }}">{{ old('admin_note', $c?->admin_note) }}</textarea>
            </div>
            <label
                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                <span>
                    <span class="block text-[12.5px] font-semibold">{{ __('Active') }}</span>
                    <span
                        class="block text-[10.5px] text-ink-500">{{ __('Customers can redeem it at checkout') }}</span>
                </span>
                <input type="hidden" name="is_active" value="0">
                <span class="relative inline-block w-[34px] h-5 shrink-0">
                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="is_active" value="1"
                        @checked(old('is_active', $c?->is_active ?? 1))>
                    <span
                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                </span>
            </label>
        </div>
    </div>

    {{-- ── Section 2: Discount ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Discount') }}</span>
        </div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-type">{{ __('Type') }} <span class="text-accent-coral">*</span></label>
                    <select id="co-type" name="type"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <option value="percent" @selected(old('type', $c?->type ?? 'percent') === 'percent')>{{ __('Percent (%)') }}</option>
                        <option value="fixed" @selected(old('type', $c?->type ?? 'percent') === 'fixed')>{{ __('Fixed amount') }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-amount">{{ __('Amount') }} <span class="text-accent-coral">*</span></label>
                    <input id="co-amount" type="number" name="amount" required step="0.01" min="0"
                        value="{{ old('amount', $c?->amount) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="10">
                    <div class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('% type: 10 = 10%. Fixed: 10 = 10 currency units.') }}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-max-discount">{{ __('Max discount cap') }} <span
                            class="text-ink-500 font-normal">{{ __('(% type only)') }}</span></label>
                    <input id="co-max-discount" type="number" name="max_discount_amount" step="0.01" min="0"
                        value="{{ old('max_discount_amount', $c?->max_discount_amount) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('blank = no cap') }}">
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('e.g. 20% off, but never more than $50.') }}
                    </div>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-currency">{{ __('Currency lock') }}</label>
                    <select id="co-currency" name="currency_code"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @foreach ($currencies as $code => $label)
                            <option value="{{ $code }}" @selected(old('currency_code', $c?->currency_code) === $code)>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="text-[10.5px] text-ink-500 mt-1">
                        {{ __('Restrict redemption to one currency (fixed-amount coupons).') }}</div>
                </div>
            </div>
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                    for="co-min">{{ __('Minimum order amount') }}</label>
                <input id="co-min" type="number" name="min_order_amount" step="0.01" min="0"
                    value="{{ old('min_order_amount', $c?->min_order_amount) }}"
                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                    placeholder="0 = no minimum">
            </div>
        </div>
    </div>

    {{-- ── Section 3: Eligibility ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Eligibility') }}</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Applicable plans') }}
                    <span class="text-ink-500 font-normal">(empty = any plan)</span></label>
                <select id="co-plans" name="applicable_package_ids[]" multiple class="w-full"
                    placeholder="{{ __('Pick one or more plans...') }}">
                    @foreach ($packages as $p)
                        <option value="{{ $p->id }}" @selected(in_array($p->id, (array) $selectedPkg))>{{ $p->pname }}</option>
                    @endforeach
                </select>
                <div class="text-[10.5px] text-ink-500 mt-1">
                    {{ __('Type to filter. Click to add. Click chip × to remove.') }}</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-start">{{ __('Starts at') }}</label>
                    <input id="co-start" type="datetime-local" name="starts_at"
                        value="{{ old('starts_at', $c?->starts_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Blank = available now.') }}</div>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-exp">{{ __('Expires at') }}</label>
                    <input id="co-exp" type="datetime-local" name="expires_at"
                        value="{{ old('expires_at', $c?->expires_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Blank = never expires.') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Section 4: Limits & behavior ── --}}
    <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
        <div class="flex items-center gap-2.5 mb-4">
            <span
                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
            <span class="font-serif text-[18px] leading-none">{{ __('Limits & behavior') }}</span>
        </div>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-max-uses">{{ __('Max total redemptions') }}</label>
                    <input id="co-max-uses" type="number" name="max_uses" min="1"
                        value="{{ old('max_uses', $c?->max_uses) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('blank = unlimited') }}">
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Across all customers.') }}</div>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                        for="co-per-user">{{ __('Per-user limit') }}</label>
                    <input id="co-per-user" type="number" name="per_user_limit" min="1"
                        value="{{ old('per_user_limit', $c?->per_user_limit) }}"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                        placeholder="{{ __('blank = unlimited') }}">
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('How many times one customer can redeem.') }}
                    </div>
                </div>
            </div>
            <label
                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                <span>
                    <span class="block text-[12.5px] font-semibold">{{ __('First purchase only') }}</span>
                    <span
                        class="block text-[10.5px] text-ink-500">{{ __('Workspaces with no paid orders yet') }}</span>
                </span>
                <input type="hidden" name="first_purchase_only" value="0">
                <span class="relative inline-block w-[34px] h-5 shrink-0">
                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="first_purchase_only" value="1"
                        @checked(old('first_purchase_only', $c?->first_purchase_only ?? false))>
                    <span
                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                </span>
            </label>
            <label
                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                <span>
                    <span class="block text-[12.5px] font-semibold">{{ __('Stackable with other coupons') }}</span>
                    <span
                        class="block text-[10.5px] text-ink-500">{{ __('Allow combining with another active code at checkout') }}</span>
                </span>
                <input type="hidden" name="stackable_with_other" value="0">
                <span class="relative inline-block w-[34px] h-5 shrink-0">
                    <input class="peer opacity-0 w-0 h-0" type="checkbox" name="stackable_with_other" value="1"
                        @checked(old('stackable_with_other', $c?->stackable_with_other ?? false))>
                    <span
                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                </span>
            </label>
        </div>
    </div>

    {{-- Footer actions span all columns. --}}
    <div class="xl:col-span-2 flex justify-end gap-2 pt-1">
        <a href="{{ route('admin.coupons.index') }}"
            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12.5px] font-medium">{{ __('Cancel') }}</a>
        <button type="submit"
            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 8l5 5 7-9" />
            </svg>
            {{ $c ? 'Save changes' : 'Create coupon' }}
        </button>
    </div>
</form>
