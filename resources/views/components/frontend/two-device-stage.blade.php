{{--
 Two-device stage — sender (dashboard) → center pipeline → receiver (chat).
 3 fly-msg bubbles arc over the center column and dissolve at the chat edge,
 the persistent chat bubbles flash on landing, and an animated arc + trail
 dots back up the journey. Pure CSS animation, no JS.
--}}
@php
    // Demo browser-chrome host for the mockups below — derive from the
    // real request host so a white-labelled deploy (chatkar.com, agendiz.cloud)
    // shows its OWN domain, never a hard-coded "wadesk.io". Strip a leading
    // www./app. then re-prefix app. for the dashboard URL.
    $__rootHost  = preg_replace('/^(www|app)\./i', '', request()->getHost() ?: (parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com'));
    $__appHost   = 'app.' . $__rootHost;
    $__brandWord = \Illuminate\Support\Str::lower(brand_name());
@endphp
<div class="relative mt-20 overflow-x-clip" data-fc-section="two-device-stage">

    {{-- guide arc behind the devices --}}
    <svg class="absolute inset-x-0 top-0 w-full h-[560px] pointer-events-none" viewBox="0 0 1200 560"
        preserveAspectRatio="none">
        <defs>
            <linearGradient id="arcg" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#075E54" stop-opacity=".0" />
                <stop offset=".18" stop-color="#075E54" stop-opacity=".5" />
                <stop offset=".5" stop-color="#25D366" stop-opacity="1" />
                <stop offset=".82" stop-color="#075E54" stop-opacity=".5" />
                <stop offset="1" stop-color="#075E54" stop-opacity=".0" />
            </linearGradient>
        </defs>
        <path d="M 340 360 Q 600 -40, 860 320" stroke="#25D366" stroke-opacity=".18" stroke-width="10" fill="none"
            stroke-linecap="round" />
        <path class="arc-line" d="M 340 360 Q 600 -40, 860 320" stroke="url(#arcg)" stroke-width="2.5" fill="none"
            stroke-linecap="round" />
    </svg>

    {{-- "data in flight" pill at the top center --}}
    <div class="absolute left-1/2 -translate-x-1/2 top-2 z-30">
        <div class="hairline glass rounded-full px-3 py-1.5 inline-flex items-center gap-2 shadow-sm">
            <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="none" stroke="currentColor"
                stroke-width="1.8">
                <path d="M2 8h12M14 8l-3-3M14 8l-3 3" />
            </svg>
            <span class="mono text-[10px] uppercase tracking-widest text-ink-700">{{ __('message') }} · 18ms ·
                {{ __('encrypted') }}</span>
            <span class="text-ink-400">·</span>
            <span class="mono text-[10px] uppercase tracking-widest text-wa-deep">{{ __('delivered') }}</span>
        </div>
    </div>

    {{-- flying messages --}}
    <div class="fly-msg fly-1" data-fc="two-device-stage.fly1">{!! fc('two-device-stage.fly1', '🌷 ' . __("Mother's Day Promo — 20% off")) !!}</div>
    <div class="fly-msg fly-2" data-fc="two-device-stage.fly2">
        {{ fc('two-device-stage.fly2', __('Order #4218 is out for delivery')) }}</div>
    <div class="fly-msg fly-3" data-fc="two-device-stage.fly3">{!! fc('two-device-stage.fly3', __('Hi Maya') . ' 👋 ' . __('your cart is waiting!')) !!}</div>

    {{-- trail dots --}}
    <div class="trail-dot t1"></div>
    <div class="trail-dot t2"></div>
    <div class="trail-dot t3"></div>

    {{-- landing pulses --}}
    <div class="landing-pulse lp1"></div>
    <div class="landing-pulse lp2"></div>
    <div class="landing-pulse lp3"></div>

    {{-- DEVICE GRID --}}
    <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-6 items-center pt-12">

        {{-- LEFT · sender dashboard --}}
        <div class="col-span-12 lg:col-span-5 relative reveal">
            <div class="hairline rounded-3xl bg-white shadow-2xl overflow-hidden float-y"
                style="box-shadow:0 30px 80px -30px rgba(7,94,84,.35), 0 0 0 1px rgba(7,94,84,.05);">
                <div class="flex items-center gap-2 px-4 py-3 hairline-b bg-paper-50">
                    <div class="flex gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-accent-coral/70"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-accent-amber/80"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-wa-green"></span>
                    </div>
                    <div class="hairline rounded-md bg-white px-3 py-1 mono text-[10px] text-ink-500 ml-2 min-w-0 truncate">
                        {{ $__appHost }}/campaigns/spring</div>
                    <span
                        class="ml-auto mono text-[9.5px] uppercase tracking-widest text-ink-500">{{ __('sender') }}</span>
                </div>

                <div class="p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-[10px] font-bold"
                                style="background:linear-gradient(135deg,#E87A5D,#E5A04E);">SP</span>
                            <div>
                                <div class="text-[12.5px] font-semibold">Spring Promo · Tier-2 EU</div>
                                <div class="mono text-[9.5px] text-ink-500">spring_promo_v3 · 48,210
                                    {{ __('recipients') }}</div>
                            </div>
                        </div>
                        <span class="pill bg-wa-green/15 text-wa-deep border border-wa-green/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('Sending') }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex-1 h-2 rounded-full bg-paper-100 overflow-hidden flex">
                            <div class="h-full bg-wa-deep" style="width:55%"></div>
                            <div class="h-full bg-wa-green" style="width:33%"></div>
                        </div>
                        <span class="mono text-[10px] tabular font-semibold">88%</span>
                    </div>

                    <div class="hairline rounded-xl bg-paper-50 p-3 mb-4">
                        <div class="mono text-[9.5px] uppercase tracking-widest text-ink-500 mb-1.5">{{ __('message') }}
                            · {{ __('template') }}</div>
                        <div class="text-[12.5px] leading-snug">
                            🌷 <b>{{ __("Mother's Day Promo") }}</b> — 20% {{ __('off blooms, free delivery in EU.') }}
                            <span class="text-ink-500">{{ __('Use code') }} <span
                                    class="mono text-wa-deep">MOM20</span></span>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <span class="flex items-center gap-1.5 mono text-[10px] text-ink-500">
                                <span class="w-1 h-1 rounded-full bg-wa-deep typing-dot"></span>
                                <span class="w-1 h-1 rounded-full bg-wa-deep typing-dot"></span>
                                <span class="w-1 h-1 rounded-full bg-wa-deep typing-dot"></span>
                                <span class="ml-1">{{ __('sending wave 3 of 5…') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="grid grid-cols-3 gap-2 flex-1">
                            <div class="hairline rounded-lg bg-paper-50 py-2 text-center">
                                <div class="mono text-[9px] text-ink-500">{{ __('SENT') }}</div>
                                <div class="serif text-[18px] tabular">42.1k</div>
                            </div>
                            <div class="hairline rounded-lg bg-wa-bubble border-wa-green/30 py-2 text-center">
                                <div class="mono text-[9px] text-wa-deep">{{ __('READ') }}</div>
                                <div class="serif text-[18px] tabular text-wa-deep">86%</div>
                            </div>
                            <div class="hairline rounded-lg bg-paper-50 py-2 text-center">
                                <div class="mono text-[9px] text-ink-500">{{ __('CTR') }}</div>
                                <div class="serif text-[18px] tabular">11.4%</div>
                            </div>
                        </div>
                        <div class="relative ml-4 w-12 h-12">
                            <span class="ring-out"></span>
                            <span class="ring-out r2"></span>
                            <span class="ring-out r3"></span>
                            <button
                                class="relative w-12 h-12 rounded-full bg-wa-green text-ink-900 flex items-center justify-center shadow-lg hover:scale-105 transition-transform">
                                <svg viewBox="0 0 24 24" class="w-5 h-5 -translate-x-[1px] translate-y-[1px]"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M22 2L11 13" />
                                    <path d="M22 2l-7 20-4-9-9-4 20-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 mono text-[10.5px] uppercase tracking-widest text-ink-500 text-center">
                {{ __('sender · dashboard') }} · {{ $__brandWord }}</div>
        </div>

        {{-- CENTER · transmission pillar (above the bubbles via z-index) --}}
        <div class="hidden lg:flex col-span-2 flex-col items-center justify-end gap-2.5 relative"
            style="z-index:35; padding-top:120px;">
            <div
                class="absolute top-[125px] left-1/2 -translate-x-1/2 w-px h-[calc(100%-130px)] bg-gradient-to-b from-wa-green/0 via-wa-green/60 to-wa-deep/0">
            </div>

            <div class="relative hairline glass rounded-2xl bg-white px-5 py-4 shadow-md text-center w-[140px]">
                <div
                    class="mono text-[9px] uppercase tracking-[0.18em] text-wa-deep mb-1.5 flex items-center justify-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('in flight') }}
                </div>
                <div class="serif text-[40px] leading-none text-wa-deep tabular">18<span
                        class="text-[18px] text-ink-500 ml-0.5">ms</span></div>
                <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">{{ __('avg latency') }}
                </div>
            </div>

            <div class="relative hairline glass rounded-xl bg-white px-3 py-2 w-[140px]">
                <div class="mono text-[9px] text-ink-500 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                    <svg viewBox="0 0 12 12" class="w-2.5 h-2.5 text-wa-deep" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M6 1l5 3v4l-5 3-5-3V4z" />
                    </svg>
                    {{ __('payload') }}
                </div>
                <div class="mono text-[10px] text-ink-700">whatsapp/v17</div>
                <div class="mono text-[10px] text-wa-deep">cloud_api · enc</div>
            </div>

            <div class="relative hairline glass rounded-xl bg-white px-3 py-2 w-[140px]">
                <div class="mono text-[9px] text-ink-500 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                    <svg viewBox="0 0 12 12" class="w-2.5 h-2.5 text-wa-deep" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M2 6l3 3 5-6" />
                    </svg>
                    {{ __('checks · 3/3') }}
                </div>
                <div class="flex items-center gap-1.5 text-[10.5px]"><span
                        class="text-wa-green">✓</span>{{ __('opt-in') }}</div>
                <div class="flex items-center gap-1.5 text-[10.5px]"><span
                        class="text-wa-green">✓</span>{{ __('quiet hours') }}</div>
                <div class="flex items-center gap-1.5 text-[10.5px]"><span
                        class="text-wa-green">✓</span>{{ __('rate limit') }}</div>
            </div>
        </div>

        {{-- RIGHT · WhatsApp chat window (no phone frame) --}}
        <div class="col-span-12 lg:col-span-5 relative flex justify-center reveal" style="--d:200ms">
            <div class="relative w-full max-w-[340px] float-y-2">
                <div class="hairline rounded-2xl overflow-hidden shadow-2xl bg-white"
                    style="box-shadow:0 30px 80px -30px rgba(7,94,84,.45), 0 0 0 1px rgba(7,94,84,.05);">

                    <div class="bg-wa-deep text-paper-0 px-4 py-3 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-[11px] font-semibold shrink-0"
                            style="background:linear-gradient(135deg,#E87A5D,#E5A04E);">BL</div>
                        <div class="flex-1 leading-tight">
                            <div class="text-[13px] font-semibold">Bloomly Flowers</div>
                            <div class="text-[10px] text-paper-0/70 flex items-center gap-1">
                                <span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('online') }}
                            </div>
                        </div>
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-paper-0/80" fill="none"
                            stroke="currentColor" stroke-width="1.5">
                            <path d="M11.5 3a3 3 0 013 3v4a3 3 0 01-3 3h-7a3 3 0 01-3-3V6a3 3 0 013-3M2 4l6 4 6-4" />
                        </svg>
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-paper-0/80" fill="currentColor">
                            <circle cx="8" cy="3" r="1.4" />
                            <circle cx="8" cy="8" r="1.4" />
                            <circle cx="8" cy="13" r="1.4" />
                        </svg>
                    </div>

                    <div class="chat-grid px-3 py-3 space-y-2 min-h-[320px]">
                        <div class="flex justify-center">
                            <span
                                class="bg-white/80 hairline rounded-full px-2.5 py-0.5 text-[9.5px] mono text-ink-500">{{ __('today') }}
                                · 14:08</span>
                        </div>
                        <div class="flex">
                            <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 max-w-[80%] shadow-sm">
                                <div class="text-[12px]">{{ __('Hi Maya') }} 👋 {{ __('quick note from the team.') }}
                                </div>
                                <div class="text-[9px] text-ink-500 mono text-right mt-0.5">14:08</div>
                            </div>
                        </div>

                        <div class="flex recv-pop">
                            <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 max-w-[80%] shadow-sm">
                                <div class="text-[12px]" data-fc="two-device-stage.chat1">{!! fc('two-device-stage.chat1', '🌷 ' . __("Mother's Day — 20% off blooms!")) !!}
                                </div>
                                <div class="text-[9px] text-ink-500 mono text-right mt-0.5">14:08 ·
                                    {{ __('just now') }}</div>
                            </div>
                        </div>
                        <div class="flex recv-pop-2">
                            <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 max-w-[80%] shadow-sm">
                                <div class="text-[12px]" data-fc="two-device-stage.chat2">{!! fc('two-device-stage.chat2', __('Order #4218 is out for delivery') . ' 🚚') !!}
                                </div>
                                <div class="text-[9px] text-ink-500 mono text-right mt-0.5">14:08 ·
                                    {{ __('just now') }}</div>
                            </div>
                        </div>
                        <div class="flex recv-pop-3">
                            <div class="bg-white rounded-lg rounded-tl-sm px-3 py-2 max-w-[80%] shadow-sm">
                                <div class="text-[12px]" data-fc="two-device-stage.chat3">{!! fc('two-device-stage.chat3', __('Hi Maya') . ' 👋 ' . __('your cart is waiting!')) !!}
                                </div>
                                <div class="text-[9px] text-ink-500 mono text-right mt-0.5">14:09 ·
                                    {{ __('just now') }}</div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <div class="bg-wa-bubble rounded-lg rounded-tr-sm px-3 py-2 max-w-[78%] shadow-sm">
                                <div class="text-[12px]">{{ __('Yes please!') }} 💐</div>
                                <div class="text-[9px] text-wa-deep/60 mono text-right mt-0.5">14:09 ✓✓</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-paper-50 hairline-t px-3 py-2.5 flex items-center gap-2">
                        <span
                            class="w-8 h-8 rounded-full bg-white hairline flex items-center justify-center text-ink-500">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                        </span>
                        <div class="flex-1 bg-white hairline rounded-full px-3 py-2 text-[11px] text-ink-400">
                            {{ __('Type a message…') }}</div>
                        <button
                            class="w-8 h-8 rounded-full bg-wa-green text-ink-900 flex items-center justify-center shadow-sm hover:scale-105 transition-transform">
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 2L11 13" />
                                <path d="M22 2l-7 20-4-9-9-4 20-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="absolute -top-3 -right-3 hairline glass rounded-xl px-3 py-2 shadow-md float-y-2">
                    <div class="flex items-center gap-2">
                        <span class="relative flex w-2 h-2">
                            <span class="absolute inset-0 rounded-full bg-wa-green pulse-dot"></span>
                            <span class="relative w-2 h-2 rounded-full bg-wa-green"></span>
                        </span>
                        <div class="leading-tight">
                            <div class="text-[11px] font-semibold">3 {{ __('new messages') }}</div>
                            <div class="mono text-[9px] text-ink-500">{{ __('just now') }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="absolute -bottom-6 mono text-[10.5px] uppercase tracking-widest text-ink-500 text-center w-full">
                {{ __('receiver · whatsapp · maya r.') }}</div>
        </div>
    </div>

    {{-- live activity stream pill --}}
    <x-frontend.live-stream />
</div>
