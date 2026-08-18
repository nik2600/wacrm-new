{{--
 /admin/settings/seo — full SEO admin pane.

 Every field maps 1:1 to a system_settings row (seo_*) that the layout
 partials/seo-meta.blade.php reads on every request. The live preview
 + character counters are driven by resources/js/charts/admin-settings-seo.js
 (no inline JS — page="admin-settings-seo" key in app.js).
--}}
<x-layouts.admin :title="__('SEO settings')" admin-key="settings" page="admin-settings-seo">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('SEO') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.seo.update') }}" class="contents" id="seo-form">
        @csrf
        @method('PATCH')

        <main class="px-4 sm:px-7 py-7 space-y-5">

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('SEO') }}
                        <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Search-engine titles, descriptions, OpenGraph, Twitter Cards, robots, canonical, and site verification. Values here apply across admin and user pages.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <button type="reset"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset draft') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div
                    class="rounded-xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-3 text-[12.5px] font-medium">
                    {{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div
                    class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                    <div class="font-semibold mb-1">{{ __('Please fix the highlighted fields:') }}</div>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    {{-- Meta tags --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Search engines') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Meta tags') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="flex items-center justify-between text-[11.5px] font-semibold">
                                    <span>{{ __('Meta title') }}</span>
                                    <span class="font-mono text-[10.5px] text-ink-500" data-seo-counter="meta_title"
                                        data-target="50-60">0</span>
                                </span>
                                <input name="meta_title" value="{{ old('meta_title', $seo['meta_title']) }}"
                                    maxlength="160" data-seo-input="meta_title"
                                    placeholder="{{ $brandName }} — WhatsApp business platform"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Shown in search results and browser tabs. Aim for 50–60 characters.') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="flex items-center justify-between text-[11.5px] font-semibold">
                                    <span>{{ __('Meta description') }}</span>
                                    <span class="font-mono text-[10.5px] text-ink-500"
                                        data-seo-counter="meta_description" data-target="140-160">0</span>
                                </span>
                                <textarea name="meta_description" rows="6" maxlength="320" data-seo-input="meta_description"
                                    placeholder="{{ __('A 1–2 sentence pitch shown under the title in Google.') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('meta_description', $seo['meta_description']) }}</textarea>
                                <span class="text-[11px] text-ink-500">{{ __('Aim for 140–160 characters.') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Meta keywords') }}</span>
                                <textarea name="meta_keywords" rows="6" maxlength="500"
                                    placeholder="{{ __('whatsapp marketing, whatsapp crm, campaigns, automation, inbox') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('meta_keywords', $seo['meta_keywords']) }}</textarea>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Comma-separated. Most engines ignore this — kept for completeness.') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Robots directive') }}</span>
                                <select name="robots"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @php $rb = old('robots', $seo['robots'] ?: 'index, follow'); @endphp
                                    <option value="index, follow" @selected($rb === 'index, follow')>
                                        {{ __('Index, follow') }} — {{ __('public site') }}</option>
                                    <option value="index, nofollow" @selected($rb === 'index, nofollow')>
                                        {{ __('Index, nofollow') }}</option>
                                    <option value="noindex, follow" @selected($rb === 'noindex, follow')>
                                        {{ __('Noindex, follow') }}</option>
                                    <option value="noindex, nofollow" @selected($rb === 'noindex, nofollow')>
                                        {{ __('Noindex, nofollow') }} — {{ __('hide from search') }}</option>
                                </select>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Tell search crawlers whether to list and follow links.') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Author') }}</span>
                                <input name="author" value="{{ old('author', $seo['author']) }}"
                                    placeholder="{{ $brandName }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Emitted as') }} <code
                                        class="font-mono text-[10.5px]">&lt;meta name="author"&gt;</code>.</span>
                            </label>

                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Canonical URL') }}</span>
                                <input name="canonical" value="{{ old('canonical', $seo['canonical']) }}"
                                    placeholder="https://app.example.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Default canonical URL. Leave blank to use each page\'s own URL.') }}</span>
                            </label>
                        </div>
                    </section>

                    {{-- OpenGraph (Facebook, LinkedIn, WhatsApp link previews) --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Social previews') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('OpenGraph') }}</h2>
                            <p class="text-[11.5px] text-ink-500 mt-1">
                                {{ __('Cards shown by Facebook, LinkedIn, WhatsApp, Slack and Discord when someone shares your link.') }}
                            </p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('OG image URL') }}</span>
                                <input name="og_image" value="{{ old('og_image', $seo['og_image']) }}"
                                    data-seo-input="og_image" placeholder="https://app.example.com/og.png"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Recommended') }} <span
                                        class="font-mono">1200×630 px</span>. {{ __('PNG or JPG.') }}</span>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('OG title') }}</span>
                                <input name="og_title" value="{{ old('og_title', $seo['og_title']) }}"
                                    data-seo-input="og_title" placeholder="{{ __('Defaults to Meta title') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('OG type') }}</span>
                                <select name="og_type"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @php $ot = old('og_type', $seo['og_type'] ?: 'website'); @endphp
                                    <option value="website" @selected($ot === 'website')>{{ __('website') }}</option>
                                    <option value="article" @selected($ot === 'article')>{{ __('article') }}</option>
                                    <option value="product" @selected($ot === 'product')>{{ __('product') }}</option>
                                    <option value="profile" @selected($ot === 'profile')>{{ __('profile') }}</option>
                                </select>
                            </label>

                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('OG description') }}</span>
                                <textarea name="og_description" rows="3" maxlength="320" data-seo-input="og_description"
                                    placeholder="{{ __('Defaults to Meta description') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('og_description', $seo['og_description']) }}</textarea>
                            </label>

                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('OG canonical URL') }}</span>
                                <input name="og_url" value="{{ old('og_url', $seo['og_url']) }}"
                                    placeholder="https://app.example.com"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </section>

                    {{-- Twitter Cards --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Twitter / X') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Twitter cards') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Card type') }}</span>
                                <select name="twitter_card"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @php $tc = old('twitter_card', $seo['twitter_card'] ?: 'summary_large_image'); @endphp
                                    <option value="summary" @selected($tc === 'summary')>{{ __('summary') }}</option>
                                    <option value="summary_large_image" @selected($tc === 'summary_large_image')>
                                        {{ __('summary_large_image') }}</option>
                                    <option value="app" @selected($tc === 'app')>{{ __('app') }}</option>
                                    <option value="player" @selected($tc === 'player')>{{ __('player') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Site handle') }}</span>
                                <input name="twitter_site" value="{{ old('twitter_site', $seo['twitter_site']) }}"
                                    placeholder="@yourbrand"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Creator handle') }}</span>
                                <input name="twitter_creator"
                                    value="{{ old('twitter_creator', $seo['twitter_creator']) }}"
                                    placeholder="@author"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
 </label>
 </div>
 </section>

 {{-- Site verification --}}
 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Webmaster tools') }}</div>
 <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Site verification') }}</h2>
 <p class="text-[11.5px] text-ink-500 mt-1">{{ __('Paste the content value from Google Search Console / Bing Webmaster verification tags.') }}</p>
 </div>
 <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
 <label class="space-y-1.5">
 <span class="text-[11.5px] font-semibold">{{ __('Google Search Console') }}</span>
 <input name="google_verification" value="{{ old('google_verification', $seo['google_verification']) }}"
 placeholder="{{ __('google-site-verification=…') }}"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
 </label>
 <label class="space-y-1.5">
 <span class="text-[11.5px] font-semibold">{{ __('Bing Webmaster') }}</span>
 <input name="bing_verification" value="{{ old('bing_verification', $seo['bing_verification']) }}"
 placeholder="{{ __('msvalidate.01=…') }}"
 class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
 </label>
 </div>
 </section>

 {{-- Live preview --}}
 <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
 <div class="px-5 py-4 border-b border-paper-200">
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</div>
 <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Search & social preview') }}</h2>
 </div>
 <div class="p-5 space-y-5">
 {{-- Google result --}}
 <div>
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Google result') }}</div>
 <div class="rounded-2xl border border-paper-200 p-4 max-w-2xl">
 <div class="text-[13px] font-semibold text-[#13478A]" data-seo-preview="meta_title">{{ $seo['meta_title'] ?: $brandName }}</div>
 <div class="text-[11.5px] text-wa-deep mt-1" data-seo-preview="og_url">{{ $seo['canonical'] ?: $seo['og_url'] ?: url('/') }}</div>
 <div class="text-[12px] text-ink-600 mt-2" data-seo-preview="meta_description">{{ $seo['meta_description'] }}</div>
 </div>
 </div>
 {{-- OG card --}}
 <div>
 <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Social share card') }}</div>
 <div class="rounded-2xl border border-paper-200 overflow-hidden max-w-2xl">
 <div class="bg-paper-100 aspect-[1200/630] flex items-center justify-center text-ink-500 text-[12px]"
 data-seo-preview-img="og_image"
 style="@if ($seo['og_image']) background-image: url('{{ $seo['og_image'] }}'); background-size: cover; background-position: center; @endif">
 @if (!$seo['og_image'])<span>{{ __('No OG image set') }}</span> @endif
 </div>
 <div class="px-4
                                    py-3">
                                <div class="text-[10.5px] font-mono text-ink-500 uppercase tracking-[0.12em]"
                                    data-seo-preview="og_url">
                                    {{ parse_url($seo['canonical'] ?: url('/'), PHP_URL_HOST) }}</div>
                                <div class="text-[13.5px] font-semibold mt-0.5" data-seo-preview="og_title">
                                    {{ $seo['og_title'] ?: $seo['meta_title'] ?: $brandName }}</div>
                                <div class="text-[12px] text-ink-600 mt-1" data-seo-preview="og_description">
                                    {{ $seo['og_description'] ?: $seo['meta_description'] }}</div>
                        </div>
                </div>
                </div>
                </div>
            </section>
            </div>

            {{-- Sticky guide --}}
            <aside class="space-y-4 lg:sticky lg:top-[88px]">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Quick guide') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('SEO basics') }}</h3>
                    </div>
                    <div class="p-4 space-y-3 text-[12px] text-ink-700">
                        <div>
                            <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Meta title') }}</div>
                            <p class="text-ink-600 mt-0.5">
                                {{ __('Lead with the brand and one strong descriptor. 50–60 chars.') }}</p>
                        </div>
                        <div>
                            <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Meta description') }}</div>
                            <p class="text-ink-600 mt-0.5">
                                {{ __('A 1–2 sentence pitch. Shows under the title in Google. 140–160 chars.') }}</p>
                        </div>
                        <div>
                            <div class="font-semibold text-[12.5px] text-ink-900">{{ __('OG image') }}</div>
                            <p class="text-ink-600 mt-0.5">
                                {{ __('Used by Slack, X, WhatsApp, Discord. Include the wordmark. Keep text large.') }}
                            </p>
                        </div>
                        <div>
                            <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Robots') }}</div>
                            <p class="text-ink-600 mt-0.5">
                                {{ __('Use noindex for staging/private deployments to keep search engines away.') }}
                            </p>
                        </div>
                        <div>
                            <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Validate') }}</div>
                            <p class="text-ink-600 mt-0.5">
                                <a href="https://www.opengraph.xyz" target="_blank" rel="noopener"
                                    class="text-wa-deep underline">{{ __('opengraph.xyz') }}</a>
                                ·
                                <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener"
                                    class="text-wa-deep underline">{{ __('Google Rich Results') }}</a>
                                ·
                                <a href="https://cards-dev.twitter.com/validator" target="_blank" rel="noopener"
                                    class="text-wa-deep underline">{{ __('Twitter Card') }}</a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Applied to') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Where this shows') }}</h3>
                    </div>
                    <div class="p-4 space-y-2 text-[12px] text-ink-700">
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Admin pages (browser tab + OG)') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Logged-in user pages') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Public / guest pages (login, marketing)') }}</div>
                        <div class="flex items-center gap-2"><svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Link previews (WhatsApp, Slack, Discord, X)') }}</div>
                    </div>
                </div>

                {{-- Sitemap & robots — generated from public pages + published
                     blog posts. Download + quick view links. --}}
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sitemap & robots') }}</div>
                    </div>
                    <div class="p-4 space-y-2.5 text-[12.5px]">
                        <a href="{{ route('admin.settings.seo.sitemap.download') }}"
                            class="flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-wa-deep text-paper-0 font-semibold hover:bg-wa-teal">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v8M5 7l3 3 3-3M3 13h10" /></svg>
                            {{ __('Download sitemap.xml') }}
                        </a>
                        <a href="{{ url('/sitemap.xml') }}" target="_blank" rel="noopener"
                            class="flex items-center justify-between px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50"><span class="font-mono text-[11.5px]">/sitemap.xml</span><span class="text-ink-500">{{ __('view') }}</span></a>
                        <a href="{{ url('/robots.txt') }}" target="_blank" rel="noopener"
                            class="flex items-center justify-between px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50"><span class="font-mono text-[11.5px]">/robots.txt</span><span class="text-ink-500">{{ __('view') }}</span></a>
                        <p class="text-[11px] text-ink-500">{{ __('Auto-generated from public pages + published blog posts. Submit /sitemap.xml to Google Search Console.') }}</p>
                    </div>
                </div>
            </aside>
            </section>
        </main>

    </form>

</x-layouts.admin>
