<x-layouts.user :title="__('Storefront')" nav-key="connect" page="user-store-storefront-edit">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $cnameTarget = config('storefront.cname_target', parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $settings = $sf->settings_json ?? [];
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'storefront', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 max-w-4xl">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            {{ __('Store / Storefront') }}</div>
                        <h1 class="font-serif text-[26px] sm:text-[30px] lg:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Storefront') }} <span
                                class="italic text-wa-deep">{{ __('design') }}</span></h1>
                    </div>
                    <a href="{{ $sf->public_url }}" target="_blank"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">View
                        live →</a>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('user.store.storefront.update') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-5">
                    @csrf @method('PUT')

                    @if ($errors->any())
                        <div
                            class="rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                            @foreach ($errors->all() as $e)
                                <div>{{ $e }}</div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Theme picker -->
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Theme') }}</div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach ($themes as $key => $name)
                                @php $active = old('theme_key', $sf->theme_key) === $key; @endphp
                                <label class="cursor-pointer block">
                                    <input type="radio" name="theme_key" value="{{ $key }}"
                                        @checked($active) class="sr-only peer" />
                                    <div
                                        class="border peer-checked:border-wa-deep peer-checked:ring-2 peer-checked:ring-wa-deep/15 rounded-xl overflow-hidden transition">
                                        <div class="aspect-[4/3] grid place-items-center text-paper-0 text-[12px] font-mono uppercase tracking-wider"
                                            style="background:{{ ['aurora' => 'linear-gradient(135deg,#FBFAF6,#E5DFD0)', 'meridian' => 'linear-gradient(135deg,#0B1F1C,#3A5A55)', 'verdure' => 'linear-gradient(135deg,#D5E8C6,#8AAB7B)', 'bazaar' => 'linear-gradient(135deg,#F4A261,#E76F51)', 'noir' => 'linear-gradient(135deg,#0a0a0a,#262626)', 'kraft' => 'linear-gradient(135deg,#D4B896,#A6845A)', 'mercato' => 'linear-gradient(135deg,#C8553D,#F4D35E)', 'studio' => 'linear-gradient(135deg,#264653,#2A9D8F)'][$key] ?? '#075E54' }}">
                                            <span class="font-serif text-[18px] capitalize"
                                                style="text-shadow: 0 1px 2px rgba(0,0,0,.2)">{{ $key }}</span>
                                        </div>
                                        <div class="px-3 py-2 text-[11.5px] text-ink-700 bg-paper-0">
                                            {{ $name }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Slug + custom domain -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-paper-200">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Storefront slug') }}</span>
                            <input type="text" name="slug" required pattern="[a-z0-9-]+" maxlength="64"
                                value="{{ old('slug', $sf->slug) }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Your store will be at') }} <span
                                    class="font-mono">{{ $sf->slug }}.{{ config('storefront.subdomain_host') }}</span></span>
                        </label>
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Custom domain') }} <span
                                    class="text-ink-500 font-normal">(optional)</span></span>
                            <input type="text" name="custom_domain" maxlength="191"
                                value="{{ old('custom_domain', $sf->custom_domain) }}"
                                placeholder="{{ __('shop.yourbiz.com') }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            @if ($sf->custom_domain)
                                <div
                                    class="mt-2 text-[11px] {{ $sf->custom_domain_verified ? 'text-wa-deep' : 'text-accent-amber' }}">
                                    @if ($sf->custom_domain_verified)
                                        ✓ Verified — your domain is live
                                    @else
                                        ⚠ Add this CNAME at your DNS provider:
                                    @endif
                                </div>
                                @if (!$sf->custom_domain_verified)
                                    <div
                                        class="mt-1 px-2 py-1.5 bg-paper-50 rounded-lg font-mono text-[10.5px] text-ink-700">
                                        {{ __('CNAME') }} <strong>{{ explode('.', $sf->custom_domain)[0] }}</strong>
                                        → <strong>{{ $cnameTarget }}</strong></div>
                                    <button type="button" onclick="verifyDomain()"
                                        class="mt-2 px-3 py-1 border border-paper-200 rounded-full text-[11.5px] hover:bg-paper-50">{{ __('Verify now') }}</button>
                                    <span id="dns-result" class="text-[11px] ml-2"></span>
                                @endif
                            @endif
                        </label>
                    </div>

                    <!-- Brand -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-paper-200">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Logo URL') }}</span>
                            <input type="url" name="logo_url" maxlength="1024"
                                value="{{ old('logo_url', $settings['logo_url'] ?? '') }}" placeholder="https://..."
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </label>
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Brand color (hex)') }}</span>
                            <div class="flex gap-2 mt-1">
                                <input type="color" name="brand_color"
                                    value="{{ old('brand_color', $settings['brand_color'] ?? '#075E54') }}"
                                    class="w-10 h-10 rounded-lg border border-paper-200" />
                                <input type="text" pattern="#[0-9a-fA-F]{6}" maxlength="7"
                                    value="{{ old('brand_color', $settings['brand_color'] ?? '#075E54') }}"
                                    oninput="this.previousElementSibling.value=this.value"
                                    class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Hero text') }}</span>
                            <input type="text" name="hero_text" maxlength="280"
                                value="{{ old('hero_text', $settings['hero_text'] ?? '') }}"
                                placeholder="{{ __('Welcome to our store') }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Footer text') }}</span>
                            <input type="text" name="footer_text" maxlength="280"
                                value="{{ old('footer_text', $settings['footer_text'] ?? '') }}"
                                placeholder="© 2026 Your store"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </label>
                    </div>

                    {{-- Currency --}}
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Currency') }}</div>
                        <label class="block max-w-xs">
                            <select name="currency_code"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
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
                                    <option value="{{ $c }}" @selected(old('currency_code', $sf->currency_code ?? 'INR') === $c)>
                                        {{ $l }}</option>
                                @endforeach
                            </select>
                            <span
                                class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Default for new products + the symbol shown in the cart drawer.') }}</span>
                        </label>
                    </div>

                    {{-- Shipping --}}
                    @php $shipping = $sf->shipping_json ?? []; @endphp
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Shipping') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700">{{ __('Flat shipping fee') }}</span>
                                <input type="number" name="shipping_flat" min="0" step="0.01"
                                    value="{{ old('shipping_flat', isset($shipping['flat_minor']) ? number_format($shipping['flat_minor'] / 100, 2, '.', '') : '') }}"
                                    placeholder="0"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Added to every order. Blank or 0 = free.') }}</span>
                            </label>
                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700">{{ __('Free shipping above') }}</span>
                                <input type="number" name="shipping_free_above" min="0" step="0.01"
                                    value="{{ old('shipping_free_above', isset($shipping['free_above_minor']) ? number_format($shipping['free_above_minor'] / 100, 2, '.', '') : '') }}"
                                    placeholder="999"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Carts over this amount ship free.') }}</span>
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Shipping note') }} <span
                                        class="text-ink-500 font-normal">(optional)</span></span>
                                <input type="text" name="shipping_note" maxlength="160"
                                    value="{{ old('shipping_note', $shipping['note'] ?? '') }}"
                                    placeholder="2–5 business days · Tracked"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Shown in the cart and on the product page.') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- Payment --}}
                    @php $pay = $sf->payment_config_json ?? []; @endphp
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Payment link') }}</div>
                        <p class="text-[11.5px] text-ink-500 mb-3">
                            {{ __("Pick a payment provider and we'll append the payment link to every WhatsApp order message. Buyer pays via the link, you confirm in chat. No checkout flow built into the storefront.") }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Provider') }}</span>
                                <select name="payment_provider" data-payment-provider
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @foreach ([
        '' => 'None · cash on delivery',
        'upi' => 'UPI (India) — VPA + name',
        'razorpay_link' => 'Razorpay Payment Link (paste static link)',
        'razorpay_api' => 'Razorpay (auto-generate links + auto-confirm)',
        'stripe_link' => 'Stripe Payment Link',
        'paypal_me' => 'PayPal.me handle',
        'bank_transfer' => 'Bank transfer instructions',
    ] as $key => $label)
                                        <option value="{{ $key }}" @selected(old('payment_provider', $sf->payment_provider) === $key)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700">{{ __('Payment detail') }}</span>
                                <input type="text" name="payment_handle" maxlength="255"
                                    value="{{ old('payment_handle', $pay['handle'] ?? '') }}"
                                    placeholder="e.g. shop@upi · https://rzp.io/l/xxx"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('UPI VPA, payment link URL, PayPal.me handle, or bank account.') }}</span>
                            </label>
                        </div>

                        {{-- Razorpay API keys — only used when provider = "Razorpay (auto-generate)".
 Lets the store mint a real payment link per order + auto-mark paid via webhook.
 Secrets are encrypted at rest and never echoed back. --}}
                        @php
                            $hasRzpSecret = !empty($sf->payment_config_json['key_secret']);
                            $hasRzpWh = !empty($sf->payment_config_json['webhook_secret']);
                        @endphp
                        <div class="mt-3 border border-paper-200 rounded-xl p-3 bg-paper-50/50">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                                {{ __('Razorpay API (auto links)') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="block"><span
                                        class="text-[11px] text-ink-700">{{ __('Key ID') }}</span>
                                    <input type="text" name="rzp_key_id"
                                        value="{{ old('rzp_key_id', $sf->payment_config_json['key_id'] ?? '') }}"
                                        placeholder="rzp_live_… / rzp_test_…"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep"></label>
                                <label class="block"><span
                                        class="text-[11px] text-ink-700">{{ __('Key secret') }}</span>
                                    <input type="password" name="rzp_key_secret" autocomplete="new-password"
                                        placeholder="{{ $hasRzpSecret ? __('saved — leave blank to keep') : '' }}"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep"></label>
                                <label class="block"><span
                                        class="text-[11px] text-ink-700">{{ __('Webhook secret') }}</span>
                                    <input type="password" name="rzp_webhook_secret" autocomplete="new-password"
                                        placeholder="{{ $hasRzpWh ? __('saved — leave blank to keep') : '' }}"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            </div>
                            <span
                                class="text-[10.5px] text-ink-500 mt-2 block">{{ __('In Razorpay dashboard, set the webhook URL to') }}
                                <code class="font-mono">{{ url('/webhooks/storefront-pay') }}</code>
                                {{ __('for the payment_link.paid event.') }}</span>
                        </div>
                    </div>

                    {{-- Abandoned-cart recovery (S3) --}}
                    @php $rc = is_array($sf->settings_json) ? $sf->settings_json : []; @endphp
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Abandoned-cart recovery') }}</div>
                        <label class="inline-flex items-start gap-2 text-[12.5px]">
                            <input type="hidden" name="cart_recovery_enabled" value="0" />
                            <input type="checkbox" name="cart_recovery_enabled" value="1"
                                @checked(old('cart_recovery_enabled', $rc['cart_recovery_enabled'] ?? false)) class="mt-0.5 rounded border-paper-200 text-wa-deep" />
                            <span><span
                                    class="font-semibold block">{{ __('Send a WhatsApp nudge to customers who start checkout but don\'t finish') }}</span>
                                <span
                                    class="text-[10.5px] text-ink-500">{{ __('Captures the phone at checkout; the nudge auto-cancels if they order. Needs a connected device.') }}</span></span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
                            <label class="block"><span
                                    class="text-[11px] text-ink-700">{{ __('Delay (minutes)') }}</span>
                                <input type="number" name="cart_recovery_delay_min" min="5" max="1440"
                                    value="{{ old('cart_recovery_delay_min', $rc['cart_recovery_delay_min'] ?? 30) }}"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-wa-deep"></label>
                            <label class="block md:col-span-3"><span
                                    class="text-[11px] text-ink-700">{{ __('Message') }} <span
                                        class="text-ink-400">{{ __('(blank = default; {name} {shop} {url} {total})') }}</span></span>
                                <input type="text" name="cart_recovery_message" maxlength="1024"
                                    value="{{ old('cart_recovery_message', $rc['cart_recovery_message'] ?? '') }}"
                                    placeholder="Hi {name}! You left items in your cart at {shop}. Finish here: {url}"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-wa-deep"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-paper-200">
                        <label class="inline-flex items-center gap-2 text-[12.5px]">
                            <input type="hidden" name="enabled" value="0" />
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $sf->enabled))
                                class="rounded border-paper-200 text-wa-deep" />
                            Storefront live (unchecking takes it offline)
                        </label>
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <script>
        async function verifyDomain() {
            const r = await fetch(@json(route('user.store.storefront.verify')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    Accept: 'application/json'
                }
            });
            const j = await r.json();
            const el = document.getElementById('dns-result');
            el.textContent = j.message || (j.ok ? 'Verified ✓' : 'Not verified');
            el.className = 'text-[11px] ml-2 ' + (j.ok ? 'text-wa-deep' : 'text-accent-coral');
            if (j.ok) setTimeout(() => location.reload(), 800);
        }
    </script>
</x-layouts.user>
