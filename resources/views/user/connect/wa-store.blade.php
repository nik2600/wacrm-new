<x-layouts.user :title="__('Connect WhatsApp Store')" nav-key="connect" page="user-connect-wa-store">

    @php
        $defaultShopName = $storefront?->shop_name ?: ($workspace?->name ?: '');
        $defaultSlug = $storefront?->slug ?: \Illuminate\Support\Str::slug($defaultShopName ?: 'shop');
        $defaultDomain = $storefront?->custom_domain ?: '';
        $defaultDeviceId = $storefront?->device_id ?: $connectedDevices->first()->id ?? null;
    @endphp

    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ $mode === 'list' ? url('/integrations') : url('/connect?platform=wa-store') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back') }}"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg></a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Connect / WhatsApp Store') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        @if ($mode === 'list')
                            Your <span class="italic text-wa-deep">{{ __('shops') }}</span>
                        @elseif ($mode === 'edit')
                            Edit <span class="italic text-wa-deep">{{ $storefront?->shop_name ?: 'shop' }}</span>
                        @else
                            Set up your <span class="italic text-wa-deep">{{ __('store') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <a href="{{ url('/devices') }}"
                class="text-[11.5px] text-wa-deep font-semibold hover:underline">{{ __('Manage all connections at /devices →') }}</a>
        </div>
    </div>

    <main class="max-w-[1100px] mx-auto px-4 sm:px-6 lg:px-7 py-8 space-y-5">

        @if (session('status'))
            <div class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                {{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div
                class="rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12.5px] text-[#A1431F]">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        @if ($connectedDevices->isEmpty() && !$hasWaba)
            {{-- No way to send WhatsApp yet — block the wizard regardless of mode. --}}
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start gap-5">
                <div class="w-12 h-12 rounded-xl bg-wa-bubble/70 grid place-items-center shrink-0">
                    <svg viewBox="0 0 24 24" class="w-6 h-6 text-wa-deep" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M20.5 12A8.5 8.5 0 1 1 4.6 16.3L3.5 20.5l4.3-1.1A8.5 8.5 0 0 0 20.5 12Z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 1 of 3') }}
                    </div>
                    <div class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Connect WhatsApp first') }}</div>
                    <p class="text-[12.5px] text-ink-600 mt-1.5">
                        {{ __('Your store needs a connected WhatsApp number to send order confirmations and let customers chat. Head to') }}
                        <span class="font-mono">/devices</span> to pair one — it takes 30 seconds.</p>
                    <a href="{{ url('/devices') }}"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold">
                        Go to devices
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </a>
                </div>
            </div>
        @elseif ($mode === 'list')
            {{-- ============================================ --}}
            {{-- ===== MODE: LIST — show existing shops ===== --}}
            {{-- ============================================ --}}
            <div class="flex items-end justify-between gap-3 flex-wrap">
                <div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[32px] leading-tight">
                        {{ $shops->count() }} {{ \Illuminate\Support\Str::plural('shop', $shops->count()) }} in <span
                            class="italic text-wa-deep">{{ $workspace?->name }}</span>
                    </h1>
                    <p class="text-[12.5px] text-ink-600 mt-1.5">
                        {{ __('Each shop has its own public URL, theme, and sending device. Add as many as you need — different brands, languages, or regions.') }}
                    </p>
                </div>
                <a href="{{ url('/connect?platform=wa-store&action=add') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add shop
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($shops as $shop)
                    @php
                        $device = $shop->device_id ? \App\Models\Device::find($shop->device_id) : null;
                        $devicePhone = $device
                            ? trim(($device->country_code ?? '') . ' ' . ($device->phone_number ?? ''))
                            : null;
                    @endphp
                    <div
                        class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden flex flex-col">
                        {{-- Header strip in the shop's brand colour --}}
                        @php $shopBrand = ($shop->settings_json['brand_color'] ?? '') ?: 'var(--color-wa-deep)'; @endphp
                        <div class="h-20 bg-gradient-to-br relative"
                            style="background: linear-gradient(135deg, {{ $shopBrand }}, color-mix(in srgb, {{ $shopBrand }} 80%, transparent))">
                            <span
                                class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono bg-paper-0/95 text-ink-700">
                                @if ($shop->enabled)
                                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span> Live
                                @else
                                    <span class="w-1.5 h-1.5 rounded-full bg-ink-400"></span> Disabled
                                @endif
                            </span>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ $shop->theme_key }} {{ __('theme') }}</div>
                            <h3 class="font-serif text-[22px] leading-tight mt-0.5">
                                {{ $shop->shop_name ?: 'Untitled shop' }}</h3>
                            <div class="font-mono text-[11px] text-ink-700 mt-1.5 break-all">{{ $shop->public_url }}
                            </div>

                            <div class="mt-3 pt-3 border-t border-paper-200 grid grid-cols-2 gap-3 text-[11.5px]">
                                <div>
                                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                        {{ __('Sending from') }}</div>
                                    <div class="text-ink-900 mt-0.5 truncate">{{ $devicePhone ?: 'No device' }}</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">
                                        {{ __('Domain') }}</div>
                                    <div class="text-ink-900 mt-0.5 truncate">
                                        @if ($shop->custom_domain)
                                            {{ $shop->custom_domain }} @if ($shop->custom_domain_verified)
                                                ✓
                                            @else
                                                · pending
                                            @endif
                                        @else
                                            Built-in URL
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-2 flex-wrap">
                                <a href="{{ url('/store?shop=' . $shop->id) }}"
                                    class="flex-1 px-3 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold text-center inline-flex items-center justify-center gap-1.5"
                                    title="{{ __('Open admin dashboard') }}">
                                    Manage
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M3 8h10M9 4l4 4-4 4" />
                                    </svg>
                                </a>
                                <a href="{{ $shop->public_url }}" target="_blank"
                                    class="px-3 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center justify-center gap-1.5"
                                    title="{{ __('Open public storefront in a new tab') }}">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path
                                            d="M11 3h2v2M13 3l-6 6M9 3H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7" />
                                    </svg>
                                    Preview
                                </a>
                                <a href="{{ url('/connect?platform=wa-store&shop=' . $shop->id) }}"
                                    class="px-3 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center justify-center gap-1.5"
                                    title="{{ __('Edit shop settings') }}">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M11 2l3 3-8 8H3v-3z" />
                                    </svg>
                                    Edit
                                </a>
                                <button type="button" data-share-shop="{{ $shop->id }}"
                                    data-share-url="{{ $shop->public_url }}"
                                    data-share-name="{{ $shop->shop_name ?: 'My shop' }}"
                                    class="w-9 h-9 rounded-full border border-paper-200 hover:bg-wa-bubble/40 hover:border-wa-deep/40 hover:text-wa-deep grid place-items-center"
                                    title="{{ __('Share') }}">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.7">
                                        <circle cx="4" cy="8" r="2" />
                                        <circle cx="12" cy="4" r="2" />
                                        <circle cx="12" cy="12" r="2" />
                                        <path d="m6 7 4-2M6 9l4 2" />
                                    </svg>
                                </button>
                                <button type="button" data-delete-shop="{{ $shop->id }}"
                                    data-shop-name="{{ $shop->shop_name ?: 'this shop' }}"
                                    class="w-9 h-9 rounded-full border border-paper-200 hover:bg-accent-coral/10 hover:border-accent-coral/40 hover:text-accent-coral grid place-items-center"
                                    title="{{ __('Delete shop') }}">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.7">
                                        <path
                                            d="M3 4h10M6 4V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1M5 4l1 9a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1l1-9" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Add-shop tile (always last, dashed) --}}
                <a href="{{ url('/connect?platform=wa-store&action=add') }}"
                    class="border-2 border-dashed border-paper-200 hover:border-wa-deep hover:bg-wa-mint/10 rounded-2xl p-8 flex flex-col items-center justify-center text-center transition min-h-[260px]">
                    <div class="w-12 h-12 rounded-xl bg-wa-bubble/60 grid place-items-center mb-3">
                        <svg viewBox="0 0 16 16" class="w-5 h-5 text-wa-deep" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                    </div>
                    <div class="font-serif text-[18px]">{{ __('Add another shop') }}</div>
                    <div class="text-[11.5px] text-ink-500 mt-1 max-w-[220px]">
                        {{ __('Run a separate storefront with its own URL, theme, and WhatsApp number.') }}</div>
                </a>
            </div>
        @else
            {{-- ============================================ --}}
            {{-- ===== MODE: ADD / EDIT — wizard form ======== --}}
            {{-- ============================================ --}}

            @if ($mode === 'add' && $shops->isNotEmpty())
                <div class="flex items-center gap-2 text-[12px]">
                    <a href="{{ url('/connect?platform=wa-store') }}"
                        class="text-wa-deep font-semibold hover:underline">← Back to shops</a>
                    <span class="text-ink-400">·</span>
                    <span class="text-ink-500">Creating shop #{{ $shops->count() + 1 }}</span>
                </div>
            @endif

            {{-- Progress strip --}}
            <ol class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 overflow-x-auto">
                <li class="px-3 py-1.5 rounded-full bg-wa-mint/50 text-wa-deep font-semibold whitespace-nowrap shrink-0">1 · Shop details</li>
                <span class="text-ink-400 shrink-0">→</span>
                <li class="px-3 py-1.5 rounded-full bg-wa-mint/50 text-wa-deep font-semibold whitespace-nowrap shrink-0">2 · Public URL</li>
                <span class="text-ink-400 shrink-0">→</span>
                <li class="px-3 py-1.5 rounded-full bg-wa-mint/50 text-wa-deep font-semibold whitespace-nowrap shrink-0">3 · Sending device</li>
            </ol>

            <form method="POST" action="{{ route('user.connect.wa-store.save') }}" data-wa-store-wizard
                data-subdomain-host="{{ $subdomainHost }}"
                data-subdomain-usable="{{ $subdomainUsable ? '1' : '0' }}"
                data-base-url="{{ rtrim(url('/'), '/') }}"
                data-slug-locked="{{ $storefront ? '1' : '0' }}"
                class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                @csrf
                @if ($storefront)
                    <input type="hidden" name="shop_id" value="{{ $storefront->id }}">
                @endif

                {{-- Step 1: Shop details --}}
                <section class="p-6 border-b border-paper-200">
                    <div class="flex items-center gap-3 mb-4">
                        <span
                            class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[12px]">1</span>
                        <div>
                            <div class="font-serif text-[18px] leading-tight">{{ __('Shop details') }}</div>
                            <div class="text-[11.5px] text-ink-500">
                                {{ __('Customer-facing name + the URL slug under which products will live.') }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Shop name') }}</span>
                            <input type="text" name="shop_name" required maxlength="191"
                                value="{{ old('shop_name', $defaultShopName) }}"
                                placeholder="{{ $mode === 'add' && $shops->isNotEmpty() ? 'e.g. ' . $workspace?->name . ' · Mumbai' : 'Media City Threads' }}"
                                data-shop-name
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <span
                                class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Shown on the storefront header and on order confirmations.') }}</span>
                        </label>

                        <div class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Storefront slug') }} <span
                                    class="text-ink-500 font-normal">(auto)</span></span>
                            <div class="mt-1 flex">
                                <span data-preview-host
                                    class="px-3 py-2 border border-r-0 border-paper-200 rounded-l-lg bg-paper-50 text-[11.5px] font-mono text-ink-500 grid place-items-center whitespace-nowrap">{{ $subdomainUsable ? 'https://' : '/s/' }}</span>
                                <div data-slug-readonly
                                    class="flex-1 px-3 py-2 border border-paper-200 rounded-r-lg bg-paper-50 text-[13px] font-mono text-ink-700 select-all truncate">
                                    {{ old('slug', $defaultSlug) }}</div>
                            </div>
                            <span class="text-[10.5px] text-ink-500 mt-1 block">
                                @if ($storefront)
                                    Locked once the store is live so shared links keep working.
                                @else
                                    Generated from your shop name — finalised when you save.
                                @endif
                            </span>
                        </div>
                    </div>
                </section>

                {{-- Step 2: Public URL --}}
                <section class="p-6 border-b border-paper-200">
                    <div class="flex items-center gap-3 mb-4">
                        <span
                            class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[12px]">2</span>
                        <div>
                            <div class="font-serif text-[18px] leading-tight">{{ __('Public URL') }}</div>
                            <div class="text-[11.5px] text-ink-500">
                                {{ __('Choose how buyers reach your store — built-in URL is free, custom domain looks more professional.') }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="border border-paper-200 rounded-xl p-3 bg-paper-50/40">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                                {{ __('Built-in URL · free') }}</div>
                            <div data-preview-url class="font-mono text-[12px] text-ink-900 break-all"></div>
                            <div class="text-[10.5px] text-ink-500 mt-2">
                                @if ($subdomainUsable)
                                    Works as soon as you save. Hosted at <span
                                        class="font-mono">{{ $subdomainHost }}</span>.
                                @else
                                    Works as soon as you save. Path-based URL because no public storefront host is
                                    configured — set <span class="font-mono">{{ __('STOREFRONT_HOST') }}</span> in
                                    <span class="font-mono">.env</span> to enable subdomains.
                                @endif
                            </div>
                        </div>

                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Custom domain') }} <span
                                    class="text-ink-500 font-normal">(optional)</span></span>
                            <input type="text" name="custom_domain" maxlength="191"
                                value="{{ old('custom_domain', $defaultDomain) }}"
                                placeholder="{{ __('shop.yourbiz.com') }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <span class="text-[10.5px] text-ink-500 mt-1 block">
                                {{ __("You'll add a CNAME at your DNS provider and verify from the storefront settings page after save. Leave blank to use the built-in URL.") }}
                            </span>
                            @if ($storefront && $storefront->custom_domain)
                                <div
                                    class="mt-2 text-[11px] {{ $storefront->custom_domain_verified ? 'text-wa-deep' : 'text-accent-amber' }}">
                                    @if ($storefront->custom_domain_verified)
                                        ✓ {{ $storefront->custom_domain }} is verified.
                                    @else
                                        ⚠ {{ $storefront->custom_domain }} pending DNS verification.
                                    @endif
                                </div>
                            @endif
                        </label>
                    </div>
                </section>

                {{-- Step 3: Sending device --}}
                <section class="p-6 border-b border-paper-200">
                    <div class="flex items-center gap-3 mb-4">
                        <span
                            class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[12px]">3</span>
                        <div>
                            <div class="font-serif text-[18px] leading-tight">{{ __('Sending device') }}</div>
                            <div class="text-[11.5px] text-ink-500">
                                {{ __('Which WhatsApp number sends order confirmations and replies to buyers.') }}
                            </div>
                        </div>
                    </div>

                    @if ($connectedDevices->isEmpty())
                        <div
                            class="rounded-lg border border-paper-200 bg-paper-50/60 px-4 py-3 text-[12px] text-ink-700">
                            No connected devices on this account. <a href="{{ url('/devices') }}"
                                class="text-wa-deep font-semibold hover:underline">{{ __('Pair one at /devices →') }}</a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($connectedDevices as $d)
                                @php
                                    $phone = trim(($d->country_code ?? '') . ' ' . ($d->phone_number ?? ''));
                                    $active = (int) old('device_id', $defaultDeviceId) === (int) $d->id;
                                @endphp
                                <label class="cursor-pointer block">
                                    <input type="radio" name="device_id" value="{{ $d->id }}"
                                        @checked($active) class="sr-only peer" />
                                    <div
                                        class="border peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 peer-checked:ring-2 peer-checked:ring-wa-deep/20 rounded-xl p-3 flex items-center gap-3 transition">
                                        <span
                                            class="w-9 h-9 rounded-full bg-wa-bubble/70 grid place-items-center shrink-0">
                                            <svg viewBox="0 0 24 24" class="w-4 h-4 text-wa-deep" fill="none"
                                                stroke="currentColor" stroke-width="1.7">
                                                <rect x="7" y="2" width="10" height="20" rx="2" />
                                                <path d="M11 18h2" />
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-[12.5px] truncate">
                                                {{ $d->device_name ?: 'Device #' . $d->id }}</div>
                                            <div class="font-mono text-[11px] text-ink-500 truncate">
                                                {{ $phone ?: 'No number' }}</div>
                                        </div>
                                        <span
                                            class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-mono"><span
                                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ $d->status }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </section>

                <div class="flex items-center gap-3 px-6 py-4 bg-paper-50/60">
                    <a href="{{ $shops->isNotEmpty() ? url('/connect?platform=wa-store') : url('/dashboard') }}"
                        class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">Cancel</a>
                    @if ($storefront)
                        <button type="button" data-open-reset
                            class="px-3 py-2 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[12px]">{{ __('Delete this shop') }}</button>
                    @endif
                    <button type="submit"
                        class="ml-auto px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">
                        {{ $storefront ? 'Save changes' : 'Create shop →' }}
                    </button>
                </div>
            </form>

            @if ($storefront)
                <div
                    class="bg-wa-deep text-paper-0 rounded-2xl p-5 shadow-soft flex items-center justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">
                            {{ __('This shop is live') }}</div>
                        <div class="font-serif text-[22px] leading-tight mt-1">
                            {{ $storefront->shop_name ?: 'Your shop' }} →</div>
                        <p class="text-[12.5px] text-paper-0/80 mt-1">
                            {{ __('Add products, pick a theme, view orders, and share your link.') }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <a href="{{ $storefront->public_url }}" target="_blank"
                            class="px-4 py-2.5 rounded-full bg-paper-0/10 hover:bg-paper-0/20 text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2 border border-paper-0/30">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M11 3h2v2M13 3l-6 6M9 3H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7" />
                            </svg>
                            View shop
                        </a>
                        <button type="button" data-share-shop="{{ $storefront->id }}"
                            data-share-url="{{ $storefront->public_url }}"
                            data-share-name="{{ $storefront->shop_name ?: 'My shop' }}"
                            class="px-4 py-2.5 rounded-full bg-paper-0/10 hover:bg-paper-0/20 text-paper-0 text-[12.5px] font-semibold inline-flex items-center gap-2 border border-paper-0/30">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="4" cy="8" r="2" />
                                <circle cx="12" cy="4" r="2" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="m6 7 4-2M6 9l4 2" />
                            </svg>
                            Share shop
                        </button>
                        <a href="{{ url('/store') }}"
                            class="px-5 py-2.5 rounded-full bg-paper-0 text-wa-deep text-[12.5px] font-semibold inline-flex items-center gap-2">
                            Open dashboard
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </a>
                    </div>
                </div>
            @endif

        @endif

    </main>

    {{-- ===== Share shop modal (WhatsApp / Copy / Email / QR) ===== --}}
    <div id="share-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-share-backdrop></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-5">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Share your shop') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight mt-0.5" data-share-name>{{ __('Shop') }}</h3>
                </div>
                <button type="button" data-close-share
                    class="w-8 h-8 rounded-full hover:bg-paper-50 text-ink-500 text-[18px]"
                    aria-label="{{ __('Close') }}">×</button>
            </div>

            <div class="px-5 py-4">
                <div class="flex items-center gap-2">
                    <input type="text" readonly data-share-url-input
                        class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[12.5px] font-mono text-ink-700" />
                    <button type="button" data-share-copy
                        class="px-3 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Copy') }}</button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    {{-- WhatsApp — uses official wa.me deep link --}}
                    <a data-share-wa target="_blank" rel="noopener" href="#"
                        class="flex items-center gap-3 px-3 py-3 rounded-xl border border-paper-200 hover:border-wa-deep hover:bg-wa-bubble/30 transition">
                        <span class="w-9 h-9 rounded-full bg-wa-mint grid place-items-center shrink-0">
                            <svg viewBox="0 0 24 24" class="w-4 h-4 text-wa-deep" fill="currentColor">
                                <path
                                    d="M17.5 14.4c-.3-.1-1.5-.7-1.8-.8-.2-.1-.4-.1-.5.1-.2.3-.7.8-.8 1-.1.1-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.4.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.5-1.3-.7-1.8-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.7.3-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3 4.8 4.2 1.7.7 2.3.8 3.1.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2 0-.1-.2-.2-.5-.3zM12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.5A10 10 0 1 0 12 2z" />
                            </svg>
                        </span>
                        <div class="text-left min-w-0">
                            <div class="font-semibold text-[13px]">{{ __('WhatsApp') }}</div>
                            <div class="text-[11px] text-ink-500">{{ __('Send to a chat') }}</div>
                        </div>
                    </a>

                    {{-- Email --}}
                    <a data-share-email href="#"
                        class="flex items-center gap-3 px-3 py-3 rounded-xl border border-paper-200 hover:border-wa-deep hover:bg-wa-bubble/30 transition">
                        <span class="w-9 h-9 rounded-full bg-paper-100 grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <rect x="2" y="3" width="12" height="10" rx="1" />
                                <path d="m2 4 6 5 6-5" />
                            </svg>
                        </span>
                        <div class="text-left min-w-0">
                            <div class="font-semibold text-[13px]">{{ __('Email') }}</div>
                            <div class="text-[11px] text-ink-500">{{ __('Open mail app') }}</div>
                        </div>
                    </a>

                    {{-- Telegram --}}
                    <a data-share-tg target="_blank" rel="noopener" href="#"
                        class="flex items-center gap-3 px-3 py-3 rounded-xl border border-paper-200 hover:border-wa-deep hover:bg-wa-bubble/30 transition">
                        <span class="w-9 h-9 rounded-full bg-[#E7F4FB] grid place-items-center shrink-0">
                            <svg viewBox="0 0 24 24" class="w-4 h-4 text-[#229ED9]" fill="currentColor">
                                <path d="M22 4 2 11.5l5.5 2 2 6.5 3-3.5 5.5 4Z" />
                            </svg>
                        </span>
                        <div class="text-left min-w-0">
                            <div class="font-semibold text-[13px]">{{ __('Telegram') }}</div>
                            <div class="text-[11px] text-ink-500">{{ __('Share to channel') }}</div>
                        </div>
                    </a>

                    {{-- Native share (mobile) --}}
                    <button type="button" data-share-native
                        class="flex items-center gap-3 px-3 py-3 rounded-xl border border-paper-200 hover:border-wa-deep hover:bg-wa-bubble/30 transition text-left">
                        <span class="w-9 h-9 rounded-full bg-paper-100 grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <path d="M8 2v8M5 5l3-3 3 3M3 10v3a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-[13px]">{{ __('More…') }}</div>
                            <div class="text-[11px] text-ink-500">{{ __('Phone share menu') }}</div>
                        </div>
                    </button>
                </div>

                <details class="mt-4 group">
                    <summary
                        class="cursor-pointer text-[12px] font-semibold text-wa-deep hover:underline list-none flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3 group-open:rotate-90 transition" fill="none"
                            stroke="currentColor" stroke-width="1.8">
                            <path d="m6 4 4 4-4 4" />
                        </svg>
                        Show QR code
                    </summary>
                    <div
                        class="mt-3 flex flex-col items-center justify-center p-4 rounded-xl bg-paper-50 border border-paper-200">
                        <img data-share-qr alt="{{ __('QR code') }}" class="w-48 h-48 bg-paper-0 rounded-lg" />
                        <div class="text-[11px] text-ink-500 mt-2">{{ __('Scan to open the shop') }}</div>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- ===== Delete-shop confirmation modal (shared between list + edit modes) ===== --}}
    <div id="reset-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-reset-backdrop></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-md p-5 shadow-2xl">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral">{{ __('Destructive') }}
            </div>
            <h3 class="font-serif text-[22px] leading-tight mt-1">{{ __('Delete') }} <span
                    data-reset-shop-name>{{ __('this shop') }}</span>?</h3>
            <p class="text-[12.5px] text-ink-700 mt-2">
                {{ __("Permanently deletes the storefront and its orders. Products in your workspace are not affected. There's no undo.") }}
            </p>
            <form method="POST" action="{{ route('user.connect.wa-store.reset') }}"
                class="mt-4 flex items-center gap-2">
                @csrf
                <input type="hidden" name="confirm" value="yes" />
                <input type="hidden" name="shop_id" data-reset-shop-id value="{{ $storefront?->id }}" />
                <button type="button" data-close-reset
                    class="ml-auto px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                <button type="submit"
                    class="px-3 py-1.5 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:opacity-90">{{ __('Yes, delete') }}</button>
            </form>
        </div>
    </div>

</x-layouts.user>
