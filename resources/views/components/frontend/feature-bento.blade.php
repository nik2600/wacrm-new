{{--
 18-card feature bento — each card has a real mini visual mock so
 visitors can see what the feature actually does (inbox preview, flow
 canvas, A/B variant bars, brand-tile SVG grids, etc.) rather than
 text-only descriptions. Hover lifts the card + slides the arrow.

 Anchors: #feat-1 … #feat-18 — wired up by the hero's "Jump to" card.
--}}
<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="feature-bento">
 <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28">

 {{-- ROW 1: Team Inbox (big) + Broadcasts --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

 {{-- 01 · Team Inbox --}}
 <a id="feat-1" href="{{ url('/register') }}" class="col-span-12 lg:col-span-7 feat-card hairline rounded-3xl bg-white p-8 block overflow-hidden">
 <div class="flex items-start justify-between mb-4">
 <div>
 <div class="feature-num text-[64px]">01</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-2" data-fc="feature-bento.card1_eyebrow">{{ fc('feature-bento.card1_eyebrow', __('Team inbox · service desk')) }}</div>
 </div>
 <span class="pill bg-wa-bubble text-wa-deep"><span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span><span data-fc="feature-bento.card1_badge">{{ fc('feature-bento.card1_badge', 'SLA · 42s') }}</span></span>
 </div>
 <h3 class="serif text-[40px] leading-[0.95]" data-fc="feature-bento.card1_title">{!! fc('feature-bento.card1_title', __('Slack-fast') . '<br>' . __('service desk for') . ' <span class="italic text-wa-deep">' . __('WhatsApp.') . '</span>') !!}</h3>
 <p class="text-[13px] text-ink-600 mt-3 leading-relaxed max-w-md" data-fc="feature-bento.card1_body">{{ fc('feature-bento.card1_body', __('Kanban, SLA timers, routing rules, presence, internal notes, playbook macros, AI agents — built for teams that reply.')) }}</p>

 <div class="feat-mock mt-6 hairline rounded-2xl bg-paper-50 overflow-hidden grid grid-cols-12">
 <div class="col-span-3 hairline-r p-2.5 bg-white">
 <div class="mono text-[8px] uppercase tracking-widest text-ink-500 mb-2">{{ __('All open · 14') }}</div>
 <div class="space-y-1.5">
 <div class="bg-wa-bubble/40 hairline rounded-md px-2 py-1.5">
 <div class="flex items-center justify-between text-[10px] font-semibold">Maya R.<span class="mono text-[8px] text-wa-deep">2m</span></div>
 <div class="flex gap-1 mt-1">
 <span class="pill bg-paper-50 text-ink-700 text-[8px]">vip</span>
 <span class="pill bg-accent-coral/15 text-accent-coral text-[8px]">delivery</span>
 </div>
 </div>
 <div class="px-2 py-1.5">
 <div class="flex items-center justify-between text-[10px]">Anish K.<span class="mono text-[8px] text-accent-coral">SLA</span></div>
 </div>
 </div>
 </div>
 <div class="col-span-5 chat-grid p-2 space-y-1">
 <div class="flex"><div class="bg-white rounded-md rounded-tl-sm px-2 py-1 max-w-[80%] shadow-sm"><div class="text-[9.5px]">order #4218 says delivered 😕</div></div></div>
 <div class="flex justify-end"><div class="bg-wa-bubble rounded-md rounded-tr-sm px-2 py-1 max-w-[80%] shadow-sm"><div class="text-[8px] text-wa-deep font-semibold mb-0.5">⟳ FLOW · order_lookup</div><div class="text-[9.5px]">Left at apt 4B</div></div></div>
 <div class="flex justify-center"><div class="hairline border-accent-amber/30 bg-accent-amber/10 rounded-md px-2 py-1"><div class="text-[8px] text-[#8B5A14]">@sara · offer 10% credit</div></div></div>
 </div>
 <div class="col-span-4 p-2 bg-white">
 <div class="mono text-[8px] uppercase tracking-widest text-ink-500 mb-1.5">{{ __('Customer 360') }}</div>
 <div class="space-y-1 text-[9.5px]">
 <div class="flex justify-between"><span class="text-ink-500">{{ __('Status') }}</span><span class="font-semibold text-wa-deep">VIP</span></div>
 <div class="flex justify-between"><span class="text-ink-500">{{ __('Orders') }}</span><span class="font-semibold">12</span></div>
 <div class="flex justify-between"><span class="text-ink-500">LTV</span><span class="font-semibold">$1,840</span></div>
 </div>
 <div class="hairline-t mt-2 pt-1.5">
 <div class="mono text-[8px] text-wa-deep">SLA · 42s</div>
 <div class="mono text-[8px] text-ink-500">Sara K. · {{ __('assigned') }}</div>
 </div>
 </div>
 </div>

 <ul class="mt-5 grid grid-cols-2 gap-x-4 gap-y-1 text-[12px] text-ink-700">
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('Kanban board · drag-drop') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('Presence · typing · collision') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('Routing rules · auto-assign') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('Internal notes · @mentions') }}</li>
 </ul>

 <div class="hairline-t mt-6 pt-4 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('SLA · routing · AI agents · 14k/day') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open team inbox →') }}</span>
 </div>
 </a>

 {{-- 02 · Broadcasts --}}
 <a id="feat-2" href="{{ url('/register') }}" class="col-span-12 lg:col-span-5 feat-card hairline rounded-3xl bg-white p-7 block overflow-hidden">
 <div class="flex items-start justify-between mb-4">
 <div>
 <div class="feature-num text-[64px]">02</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-2" data-fc="feature-bento.card2_eyebrow">{{ fc('feature-bento.card2_eyebrow', __('Broadcasts')) }}</div>
 </div>
 <span class="pill bg-wa-bubble text-wa-deep" data-fc="feature-bento.card2_badge">{{ fc('feature-bento.card2_badge', __('link tracking')) }}</span>
 </div>
 <h3 class="serif text-[32px] leading-[0.95]" data-fc="feature-bento.card2_title">{!! fc('feature-bento.card2_title', __('Mass-message,') . '<br>' . __('per-recipient') . ' <span class="italic text-wa-deep">' . __('tracked.') . '</span>') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-3 leading-relaxed" data-fc="feature-bento.card2_body">{{ fc('feature-bento.card2_body', __('Multi-recipient one-time. Sent/delivered/opened/clicked tracked per contact.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-2xl bg-paper-50 p-4">
 <div class="flex items-center justify-between mb-3">
 <div class="flex items-center gap-2">
 <span class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-[10px] font-bold" style="background:linear-gradient(135deg,#E87A5D,#E5A04E);">SP</span>
 <div class="text-[11px] font-semibold">Spring Promo · 48,210</div>
 </div>
 <span class="pill bg-wa-green/15 text-wa-deep text-[9px]"><span class="w-1 h-1 rounded-full blink-green"></span>{{ __('Sending') }}</span>
 </div>
 <div class="flex items-center gap-2 mb-3">
 <div class="flex-1 h-2 rounded-full bg-paper-100 overflow-hidden flex">
 <div class="h-full bg-wa-deep bar-grow" style="width:55%"></div>
 <div class="h-full bg-wa-green bar-grow" style="width:33%"></div>
 </div>
 <span class="mono text-[10px] font-semibold ticker">88%</span>
 </div>
 <div class="grid grid-cols-4 gap-1.5 text-center">
 <div class="hairline rounded-lg bg-white py-1.5"><div class="mono text-[8px] text-ink-500">{{ __('SENT') }}</div><div class="serif text-[14px] ticker">42.1k</div></div>
 <div class="hairline rounded-lg bg-white py-1.5"><div class="mono text-[8px] text-ink-500">{{ __('DELIV') }}</div><div class="serif text-[14px]">98%</div></div>
 <div class="hairline border-wa-green/40 rounded-lg bg-wa-bubble py-1.5"><div class="mono text-[8px] text-wa-deep">{{ __('OPEN') }}</div><div class="serif text-[14px] text-shimmer">86%</div></div>
 <div class="hairline rounded-lg bg-white py-1.5"><div class="mono text-[8px] text-ink-500">{{ __('CLICK') }}</div><div class="serif text-[14px]">11.4%</div></div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('per-recipient ledger') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>
 </div>

 {{-- ROW 2: Flow Builder + WA Campaigns --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

 {{-- 03 · Flow Builder --}}
 <a id="feat-3" href="{{ url('/register') }}" class="col-span-12 lg:col-span-5 feat-card hairline rounded-3xl bg-white p-7 block overflow-hidden">
 <div class="flex items-start justify-between mb-4">
 <div>
 <div class="feature-num text-[64px]">03</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-2" data-fc="feature-bento.card3_eyebrow">{{ fc('feature-bento.card3_eyebrow', __('Flow builder · 15 nodes')) }}</div>
 </div>
 <span class="pill bg-wa-bubble text-wa-deep" data-fc="feature-bento.card3_badge">{{ fc('feature-bento.card3_badge', __('AI · 2.4s')) }}</span>
 </div>
 <h3 class="serif text-[32px] leading-[0.95]" data-fc="feature-bento.card3_title">{!! fc('feature-bento.card3_title', __('15 node types.') . '<br><span class="italic text-wa-deep">' . __('AI') . '</span>-' . __('generated graphs.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-3 leading-relaxed" data-fc="feature-bento.card3_body">{{ fc('feature-bento.card3_body', __('Sheets, Docs, appointments, catalog, AI responder — describe it in English, AI builds the graph.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-2xl bg-paper-50 grid-bg p-4 relative h-[170px] overflow-hidden">
 <div class="absolute top-3 left-3 hairline rounded-lg bg-white shadow-md p-2 w-[100px] float-y">
 <div class="flex items-center gap-1 mono text-[7px] uppercase tracking-widest text-accent-coral">
 <span class="w-1 h-1 rounded-full bg-accent-coral"></span>{{ __('trigger') }}
 </div>
 <div class="text-[10px] font-semibold mt-0.5">{{ __('Cart abandon') }}</div>
 </div>
 <svg class="absolute top-[30px] left-[110px] w-[55px] h-[35px] text-wa-green" viewBox="0 0 55 35" fill="none">
 <path class="flow-line" d="M0 5 Q 27 5, 27 17 T 55 30" stroke="currentColor" stroke-width="1.4"/>
 </svg>
 <div class="absolute top-[50px] left-[155px] hairline rounded-lg bg-white shadow-md p-2 w-[90px] float-y-2">
 <div class="flex items-center gap-1 mono text-[7px] uppercase tracking-widest text-accent-amber">
 <span class="w-1 h-1 rounded-full bg-accent-amber"></span>{{ __('delay') }}
 </div>
 <div class="text-[10px] font-semibold mt-0.5">{{ __('Wait 1h') }}</div>
 </div>
 <svg class="absolute top-[80px] left-[240px] w-[40px] h-[30px] text-wa-green" viewBox="0 0 40 30" fill="none">
 <path class="flow-line" d="M0 5 Q 20 5, 20 15 T 40 25" stroke="currentColor" stroke-width="1.4"/>
 </svg>
 <div class="absolute top-[100px] right-3 hairline border-wa-green/40 rounded-lg bg-wa-bubble shadow-md p-2 w-[110px] float-y-3">
 <div class="flex items-center gap-1 mono text-[7px] uppercase tracking-widest text-wa-deep">
 <span class="w-1 h-1 rounded-full bg-wa-green pulse-dot"></span>{{ __('ai responder') }}
 </div>
 <div class="text-[10px] font-semibold mt-0.5">cart_back · 5%</div>
 </div>
 <div class="absolute bottom-2 left-2 mono text-[8px] text-ink-500 flex items-center gap-1.5">
 <span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>
 <span class="text-shimmer font-semibold">{{ __('14 nodes · AI gen 2.4s') }}</span>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('Sheets · Docs · Forms · catalog') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 04 · WA Campaigns A/B --}}
 <a id="feat-4" href="{{ url('/register') }}" class="col-span-12 lg:col-span-7 feat-card hairline rounded-3xl bg-white p-8 block overflow-hidden">
 <div class="flex items-start justify-between mb-4">
 <div>
 <div class="feature-num text-[64px]">04</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-2" data-fc="feature-bento.card4_eyebrow">{{ fc('feature-bento.card4_eyebrow', __('WA campaigns · A/B test')) }}</div>
 </div>
 <span class="pill bg-wa-bubble text-wa-deep" data-fc="feature-bento.card4_badge">{{ fc('feature-bento.card4_badge', __('6 types')) }}</span>
 </div>
 <h3 class="serif text-[40px] leading-[0.95]" data-fc="feature-bento.card4_title">{!! fc('feature-bento.card4_title', 'Text · template · button ·<br>flow · media · <span class="italic text-wa-deep">' . __('carousel.') . '</span>') !!}</h3>
 <p class="text-[13px] text-ink-600 mt-3 leading-relaxed max-w-md" data-fc="feature-bento.card4_body">{{ fc('feature-bento.card4_body', __('A/B variants, random assignment, AI copy generation. Start/pause/resume lifecycle, bulk delete drafts.')) }}</p>

 <div class="feat-mock mt-6 grid grid-cols-2 gap-3">
 <div class="hairline rounded-xl bg-paper-50 p-3">
 <div class="flex items-center justify-between mb-2">
 <span class="text-[11px] font-semibold">{{ __('Variant A · "🌸 Mom\'s Day"') }}</span>
 <span class="pill bg-wa-deep text-paper-0 text-[8px]">{{ __('Winner') }}</span>
 </div>
 <div class="mono text-[9px] text-ink-500">CTR 13.2% · CVR 4.1%</div>
 <div class="mt-2 h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full bg-wa-deep" style="width:78%"></div></div>
 </div>
 <div class="hairline rounded-xl bg-paper-50 p-3">
 <div class="flex items-center justify-between mb-2">
 <span class="text-[11px] font-semibold">{{ __('Variant B · "Mom deserves"') }}</span>
 <span class="pill bg-paper-100 text-ink-500 text-[8px]">{{ __('Lost') }}</span>
 </div>
 <div class="mono text-[9px] text-ink-500">CTR 9.6% · CVR 2.8%</div>
 <div class="mt-2 h-1.5 rounded-full bg-paper-100 overflow-hidden"><div class="h-full bg-ink-400" style="width:52%"></div></div>
 </div>
 </div>

 <ul class="mt-5 grid grid-cols-2 gap-x-4 gap-y-1 text-[12px] text-ink-700">
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('6 message types') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('AI copy generation') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('A/B variants · auto-winner') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('Start/pause/resume') }}</li>
 </ul>

 <div class="hairline-t mt-6 pt-4 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('link tracking · opt-out · scheduled') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open campaigns →') }}</span>
 </div>
 </a>
 </div>

 {{-- ROW 3: Templates + Auto-Reply + Meta Ads (3 equal) --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

 {{-- 05 · Templates --}}
 <a id="feat-5" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">05</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card5_eyebrow">{{ fc('feature-bento.card5_eyebrow', __('Templates · linter')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card5_title">{!! fc('feature-bento.card5_title', __('30+ lint rules.') . '<br><span class="italic text-wa-deep">' . __('Meta') . '</span> ' . __('pre-flight.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card5_body">{{ fc('feature-bento.card5_body', __('Standard, carousel, auth. AI generation. Multi-language. Approve/refresh status.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3 space-y-1.5">
 @foreach ([__('Button count'), __('{{var}} syntax'), __('Header constraint'), __('Auth source')] as $rule)
 <div class="flex items-center justify-between text-[10.5px]"><span class="text-ink-700">✓ {{ $rule }}</span><span class="mono text-wa-deep text-[9px]">pass</span></div>
 @endforeach
 <div class="flex items-center justify-between text-[10.5px] text-wa-deep font-semibold"><span>30/30 {{ __('rules') }}</span><span class="mono text-[9px]">{{ __('submit ready') }}</span></div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('carousel · auth · standard') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 06 · Auto-Reply --}}
 <a id="feat-6" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">06</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card6_eyebrow">{{ fc('feature-bento.card6_eyebrow', __('Auto-reply · keywords')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card6_title">{!! fc('feature-bento.card6_title', __('Exact · fuzzy ·') . ' <span class="italic text-wa-deep">' . __('regex.') . '</span>') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card6_body">{{ fc('feature-bento.card6_body', __('Levenshtein typo tolerance. Multi-language with auto-translate. Reply with text · template · flow · catalog.')) }}</p>

 <div class="feat-mock mt-5 space-y-2">
 <div class="hairline rounded-xl bg-paper-50 p-2.5">
 <div class="mono text-[9px] uppercase text-ink-500 mb-1.5">{{ __('match · fuzzy') }}</div>
 <div class="flex flex-wrap gap-1">
 <span class="pill bg-wa-bubble text-wa-deep text-[9px]">price</span>
 <span class="pill bg-wa-bubble text-wa-deep text-[9px]">prce</span>
 <span class="pill bg-wa-bubble text-wa-deep text-[9px]">priceing</span>
 </div>
 </div>
 <div class="flex items-center justify-center text-wa-deep mono text-[9px]">↓ {{ __('trigger') }}</div>
 <div class="hairline rounded-xl bg-wa-bubble/40 border-wa-green/30 p-2.5">
 <div class="mono text-[9px] uppercase text-wa-deep">{{ __('A/B variant') }}</div>
 <div class="mono text-[10px] text-wa-deep font-semibold">pricing_v3 · 54%</div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('CSV import · A/B · multi-lang') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 07 · Meta Ads CTWA --}}
 <a id="feat-7" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden relative">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">07</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card7_eyebrow">{{ fc('feature-bento.card7_eyebrow', __('Meta ads · CTWA')) }}</div>
 </div>
 <div class="flex items-center gap-1">
 <span class="brand-tile w-8 h-8 rounded-lg bg-gradient-to-br from-[#0866FF] to-[#0064E0] text-white flex items-center justify-center p-1.5">
 <svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M6.915 4.03c-1.968 0-3.683 1.28-4.871 3.113C.704 9.502 0 12.541 0 15.41c0 .917.165 1.704.488 2.334.493.965 1.262 1.583 2.343 1.583 1.21 0 2.142-.501 3.354-1.95.402-.476.789-1.001 1.156-1.572.367.571.754 1.096 1.156 1.572 1.212 1.449 2.144 1.95 3.354 1.95 1.081 0 1.85-.618 2.343-1.583.323-.63.488-1.417.488-2.334 0-2.869-.704-5.908-2.044-7.967C13.749 5.31 12.034 4.03 10.066 4.03c-1.213 0-2.244.448-3.151.448-.907 0-1.939-.448-3.151-.448z" opacity=".95"/></svg>
 </span>
 <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4l4 4-4 4"/></svg>
 <span class="brand-tile w-8 h-8 rounded-lg bg-[#25D366] text-white flex items-center justify-center p-1.5">
 <svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg>
 </span>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card7_title">{!! fc('feature-bento.card7_title', __('Click-to-') . '<span class="italic text-wa-deep">' . __('WhatsApp') . '</span><br>' . __('ads.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card7_body">{{ fc('feature-bento.card7_body', __('5-step wizard. AI copy from brief. Targeting, scheduling, sync from Meta ad account.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3">
 <div class="flex items-center gap-1 mb-3">
 <span class="w-5 h-5 rounded-full bg-wa-green text-ink-900 text-[8px] font-bold flex items-center justify-center">✓</span>
 <div class="flex-1 h-px bg-wa-green"></div>
 <span class="w-5 h-5 rounded-full bg-wa-green text-ink-900 text-[8px] font-bold flex items-center justify-center">✓</span>
 <div class="flex-1 h-px bg-wa-green"></div>
 <span class="w-5 h-5 rounded-full bg-wa-green text-ink-900 text-[8px] font-bold flex items-center justify-center">✓</span>
 <div class="flex-1 h-px bg-gradient-to-r from-wa-green to-paper-200"></div>
 <span class="relative w-5 h-5">
 <span class="pulse-ring"></span>
 <span class="absolute inset-0 rounded-full bg-wa-deep text-paper-0 text-[8px] font-bold flex items-center justify-center">4</span>
 </span>
 <div class="flex-1 h-px bg-paper-200"></div>
 <span class="w-5 h-5 rounded-full hairline text-ink-400 text-[8px] flex items-center justify-center">5</span>
 </div>
 <div class="grid grid-cols-3 gap-2 mt-3 text-center">
 <div><div class="serif text-[15px] text-wa-deep tabular ticker">2.3k</div><div class="mono text-[8px] text-ink-500">{{ __('impr') }}</div></div>
 <div><div class="serif text-[15px] text-wa-deep tabular ticker">418</div><div class="mono text-[8px] text-ink-500">{{ __('clicks') }}</div></div>
 <div><div class="serif text-[15px] text-wa-deep tabular text-shimmer">18.1%</div><div class="mono text-[8px] text-ink-500">CTR</div></div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('brief → AI copy → preview') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>
 </div>

 {{-- ROW 4: AI Agents + Catalog + Appointments --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

 {{-- 08 · AI Agents --}}
 <a id="feat-8" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">08</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card8_eyebrow">{{ fc('feature-bento.card8_eyebrow', __('AI agents · RAG')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card8_title">{!! fc('feature-bento.card8_title', __('5 providers.') . '<br>' . __('Train on') . ' <span class="italic text-wa-deep">' . __('your data.') . '</span>') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card8_body">{{ fc('feature-bento.card8_body', 'OpenAI · Anthropic · Gemini · Mistral · ElevenLabs. ' . __('RAG knowledge base from PDF/DOCX/URL.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3">
 <div class="flex items-center gap-2 mb-2">
 <span class="relative w-7 h-7">
 <span class="absolute inset-0 rounded-lg bg-wa-deep"></span>
 <span class="absolute inset-0 rounded-lg flex items-center justify-center text-paper-0">
 <svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor"><circle cx="8" cy="8" r="3" opacity=".4"/><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1" opacity=".5"/><circle cx="8" cy="8" r="1.5"/></svg>
 </span>
 </span>
 <div class="flex-1">
 <div class="text-[10.5px] font-semibold">{{ __('Sales agent · GPT-4o') }}</div>
 <div class="mono text-[8px] text-wa-deep flex items-center gap-1"><span class="w-1 h-1 rounded-full bg-wa-green pulse-dot"></span>{{ __('active · 142 queries') }}</div>
 </div>
 </div>
 <div class="text-[9.5px] text-ink-600 leading-snug mb-2.5 hairline-t pt-2">"{{ __('Pricing FAQs · 142 chunks · 4 sources indexed') }}"</div>
 <div class="grid grid-cols-5 gap-1">
 <span class="brand-tile aspect-square rounded bg-[#10A37F] text-white flex items-center justify-center p-1"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M22.282 9.821a5.985 5.985 0 00-.516-4.91A6.046 6.046 0 0014.99 2.01 6.065 6.065 0 005.026 4.18 5.985 5.985 0 001.028 7.08a6.046 6.046 0 00.743 7.097 5.98 5.98 0 00.51 4.911 6.051 6.051 0 006.515 2.9A5.985 5.985 0 0013.26 24a6.056 6.056 0 005.772-4.206 5.99 5.99 0 003.997-2.9 6.056 6.056 0 00-.747-7.073z"/></svg></span>
 <span class="brand-tile aspect-square rounded bg-[#D97706] text-white flex items-center justify-center p-1.5"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M13.827 3.52h3.603L24 20.477h-3.603l-6.57-16.957zm-7.258 0h3.767L16.7 20.477h-3.674l-1.343-3.461H5.398l-1.344 3.46H.43L6.57 3.522zm.86 10.642h4.06l-2.03-5.226-2.03 5.226z"/></svg></span>
 <span class="brand-tile aspect-square rounded bg-gradient-to-br from-[#4285F4] to-[#9B72CB] text-white flex items-center justify-center p-1.5"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M12 0L9.6 9.6 0 12l9.6 2.4L12 24l2.4-9.6L24 12l-9.6-2.4z"/></svg></span>
 <span class="brand-tile aspect-square rounded bg-[#FA520F] text-white flex items-center justify-center p-1.5"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M3 4h4v4H3V4zm14 0h4v4h-4V4zM3 10h4v4H3v-4zm6 0h4v4H9v-4zm8 0h4v4h-4v-4zM3 16h4v4H3v-4zm14 0h4v4h-4v-4z"/></svg></span>
 <span class="brand-tile aspect-square rounded bg-ink-950 text-white flex items-center justify-center p-1.5"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><rect x="6" y="3" width="3" height="18" rx="1.5"/><rect x="15" y="3" width="3" height="18" rx="1.5"/></svg></span>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('BYOK · 5 providers · RAG') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 09 · Catalog + Storefront --}}
 <a id="feat-9" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">09</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card9_eyebrow">{{ fc('feature-bento.card9_eyebrow', __('Catalog · storefront')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card9_title">{!! fc('feature-bento.card9_title', __('Sell in chat.') . '<br><span class="italic text-wa-deep">' . __('Branded') . '</span> ' . __('store.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card9_body">{{ fc('feature-bento.card9_body', __('Meta catalog sync. SPM / MPM / carousel. Custom domain, themed storefront, orders + payment links.')) }}</p>

 <div class="feat-mock mt-5 chat-grid rounded-xl p-2.5">
 <div class="bg-white rounded-lg overflow-hidden shadow-sm">
 <div class="flex gap-0.5 p-1">
 @foreach (['🌷' => '€18', '💐' => '€24', '🌸' => '€32'] as $emoji => $price)
 <div class="relative w-12 h-12 rounded bg-gradient-to-br from-accent-coral/80 via-accent-amber/80 to-wa-green/80 overflow-hidden">
 <div class="absolute inset-0 flex items-end justify-center pb-1"><span class="text-[10px]">{{ $emoji }}</span></div>
 <div class="absolute bottom-0 inset-x-0 bg-black/40 text-white text-[7px] mono text-center py-0.5">{{ $price }}</div>
 </div>
 @endforeach
 </div>
 <div class="px-2 py-0.5 text-[9.5px] font-semibold">{{ __('Spring picks 🌷 · 3 items') }}</div>
 <div class="hairline-t flex">
 <button class="flex-1 py-1 text-[9px] text-wa-deep font-semibold">{{ __('Browse') }}</button>
 <button class="flex-1 py-1 text-[9px] text-wa-deep font-semibold hairline-l">{{ __('Buy') }}</button>
 </div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('Shopify · Woo · domain') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 10 · Appointments --}}
 <a id="feat-10" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">10</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card10_eyebrow">{{ fc('feature-bento.card10_eyebrow', __('Appointments · Calendar')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card10_title">{!! fc('feature-bento.card10_title', __('Book in') . ' <span class="italic text-wa-deep">' . __('chat.') . '</span><br>' . __('Google Meet auto.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card10_body">{{ fc('feature-bento.card10_body', __('Slot picker via Calendar. Auto Meet link. Customer-tz conversion. Cancel/reschedule.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3 space-y-1.5">
 <div class="mono text-[9px] uppercase text-ink-500 mb-1">{{ __('Pick a slot · Tue 16') }}</div>
 <div class="grid grid-cols-3 gap-1">
 <span class="text-center text-[9.5px] hairline rounded-md bg-white py-1 text-ink-500">10:00</span>
 <span class="text-center text-[9.5px] hairline border-wa-green/40 rounded-md bg-wa-bubble py-1 text-wa-deep font-semibold">11:30</span>
 <span class="text-center text-[9.5px] hairline rounded-md bg-white py-1 text-ink-500">14:00</span>
 <span class="text-center text-[9.5px] hairline rounded-md bg-white py-1 text-ink-400 line-through">15:00</span>
 <span class="text-center text-[9.5px] hairline rounded-md bg-white py-1 text-ink-500">16:30</span>
 <span class="text-center text-[9.5px] hairline rounded-md bg-white py-1 text-ink-500">17:00</span>
 </div>
 <div class="hairline-t pt-1.5 mt-1 flex items-center gap-1 text-[9px] text-wa-deep">
 <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/></svg>
 {{ __('Google Meet link auto-generated') }}
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('Calendar OAuth · Meet · tz') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>
 </div>

 {{-- ROW 5: Widgets + Forms + Links --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

 {{-- 11 · Chatbot Widgets --}}
 <a id="feat-11" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">11</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card11_eyebrow">{{ fc('feature-bento.card11_eyebrow', __('Chatbot widgets')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card11_title">{!! fc('feature-bento.card11_title', __('One-line JS.') . '<br><span class="italic text-wa-deep">' . __('Any') . '</span> ' . __('website.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card11_body">{{ fc('feature-bento.card11_body', __('Embed an AI chatbot. Custom colors, greeting, linked assistant. Per-IP rate limit.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3 relative h-[100px]">
 <div class="absolute bottom-3 right-3 w-12 h-12 rounded-full bg-wa-green text-ink-900 flex items-center justify-center shadow-lg">
 <svg viewBox="0 0 16 16" class="w-5 h-5" fill="currentColor"><path d="M8 1C4.5 1 2 3.5 2 7c0 1.2.4 2.3 1 3.2L2 13l2.8-1C5.6 12.6 6.8 13 8 13c3.5 0 6-2.5 6-6S11.5 1 8 1z"/></svg>
 </div>
 <div class="absolute bottom-16 right-3 bg-white hairline rounded-lg rounded-br-sm shadow-sm px-2 py-1 text-[10px]">{{ __('Hi 👋 how can I help?') }}</div>
 <div class="absolute top-3 left-3 mono text-[9px] text-ink-500">{{ __('browser · your-site.com') }}</div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('JS embed · rotate token') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 12 · WA Forms --}}
 <a id="feat-12" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">12</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card12_eyebrow">{{ fc('feature-bento.card12_eyebrow', __('WA forms · Flows API')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card12_title">{!! fc('feature-bento.card12_title', __('Interactive forms') . '<br>' . __('in') . ' <span class="italic text-wa-deep">' . __('chat.') . '</span>') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card12_body">{{ fc('feature-bento.card12_body', __('Single/multi choice, short/long text. Push to Meta Flows API. Submissions tracked.')) }}</p>

 <div class="feat-mock mt-5 chat-grid rounded-xl p-2.5">
 <div class="bg-white rounded-lg overflow-hidden shadow-sm">
 <div class="px-2 py-1.5">
 <div class="text-[10px] font-semibold mb-2">{{ __('Customer feedback') }}</div>
 <div class="space-y-1">
 <div class="hairline rounded-md bg-paper-50 px-2 py-1 text-[9px]">○ {{ __('How was your delivery?') }}</div>
 <div class="hairline border-wa-green/40 rounded-md bg-wa-bubble px-2 py-1 text-[9px] text-wa-deep">● {{ __('Excellent') }}</div>
 <div class="hairline rounded-md bg-paper-50 px-2 py-1 text-[9px]">○ {{ __('Anything to improve?') }}</div>
 </div>
 <button class="mt-2 w-full bg-wa-deep text-paper-0 rounded py-1 text-[9px] font-semibold">{{ __('Submit →') }}</button>
 </div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('Meta Flows · 4 field types') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 13 · WA Chat Links --}}
 <a id="feat-13" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">13</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card13_eyebrow">{{ fc('feature-bento.card13_eyebrow', __('WA chat links · wa.me')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card13_title">{!! fc('feature-bento.card13_title', __('Trackable') . '<br>wa.me <span class="italic text-wa-deep">' . __('links.') . '</span>') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card13_body">{{ fc('feature-bento.card13_body', __('Custom slugs · pre-filled message · per-link click analytics. Use anywhere.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3">
 <div class="hairline rounded-md bg-white px-2.5 py-1.5 mono text-[10px] text-wa-deep mb-2">{{ preg_replace('/^(www|app)\./i', '', request()->getHost() ?: (parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com')) }}/l/sales</div>
 <div class="space-y-1 text-[10px]">
 <div class="flex items-center justify-between"><span class="text-ink-700">{{ __('Clicks · 24h') }}</span><span class="mono font-semibold text-wa-deep">418</span></div>
 <div class="flex items-center justify-between"><span class="text-ink-700">{{ __('All time') }}</span><span class="mono font-semibold">12,408</span></div>
 <div class="flex items-center justify-between"><span class="text-ink-700">{{ __('Top source') }}</span><span class="mono text-ink-500">instagram</span></div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('slug · prefill · analytics') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>
 </div>

 {{-- ROW 6: Devices + Integrations + Scheduled --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

 {{-- 14 · Devices · 3 engines --}}
 <a id="feat-14" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">14</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card14_eyebrow">{{ fc('feature-bento.card14_eyebrow', __('Devices · 3 engines')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card14_title">{!! fc('feature-bento.card14_title', 'WABA. Twilio.<br><span class="italic text-wa-deep">Unofficial</span> API.') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card14_body">{{ fc('feature-bento.card14_body', __('Mix engines per workspace. Embedded signup, OAuth, or scan a QR.')) }}</p>

 <div class="feat-mock mt-5 grid grid-cols-3 gap-2">
 <div class="hairline rounded-xl bg-paper-50 p-2.5 text-center relative">
 <div class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full blink-green"></div>
 <span class="brand-tile w-9 h-9 rounded-lg bg-[#25D366] text-white flex items-center justify-center mx-auto float-y">
 <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z"/></svg>
 </span>
 <div class="serif text-[11px] mt-1.5">{{ __('Cloud API') }}</div>
 <div class="mono text-[8px] text-ink-500">{{ __('primary') }}</div>
 </div>
 <div class="hairline rounded-xl bg-paper-50 p-2.5 text-center">
 <span class="brand-tile w-9 h-9 rounded-lg bg-[#F22F46] text-white flex items-center justify-center mx-auto float-y-2">
 <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M12 0C5.382 0 0 5.382 0 12s5.382 12 12 12 12-5.382 12-12S18.618 0 12 0zm0 21.479C6.768 21.479 2.521 17.232 2.521 12 2.521 6.768 6.768 2.521 12 2.521S21.479 6.768 21.479 12 17.232 21.479 12 21.479zm5.439-11.336a1.711 1.711 0 11-3.421 0 1.711 1.711 0 013.421 0zm0 4.18a1.711 1.711 0 11-3.421 0 1.711 1.711 0 013.421 0zm-4.18 0a1.711 1.711 0 11-3.421 0 1.711 1.711 0 013.421 0zm0-4.18a1.711 1.711 0 11-3.421 0 1.711 1.711 0 013.421 0z"/></svg>
 </span>
 <div class="serif text-[11px] mt-1.5">Twilio</div>
 <div class="mono text-[8px] text-ink-500">prod</div>
 </div>
 <div class="hairline rounded-xl bg-paper-50 p-2.5 text-center">
 <span class="brand-tile w-9 h-9 rounded-lg bg-ink-950 text-white flex items-center justify-center mx-auto float-y-3">
 <svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M3 3h6v6H3V3zm2 2v2h2V5H5zm10-2h6v6h-6V3zm2 2v2h2V5h-2zM3 15h6v6H3v-6zm2 2v2h2v-2H5zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 0h2v2h-2v-2zm-2-4h-2v-2h2v2zm-2 4h2v2h-2v-2zm6-4v2h-2v-2h2z"/></svg>
 </span>
 <div class="serif text-[11px] mt-1.5">Unofficial API</div>
 <div class="mono text-[8px] text-ink-500">sandbox</div>
 </div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('multi-device · auto-reconnect') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 15 · Integrations --}}
 <a id="feat-15" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">15</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card15_eyebrow">{{ fc('feature-bento.card15_eyebrow', __('Integrations')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card15_title">{!! fc('feature-bento.card15_title', 'Shopify. Woo.<br>HubSpot. <span class="italic text-wa-deep">Google.</span>') !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card15_body">{{ fc('feature-bento.card15_body', __('OAuth, order webhooks, contact sync, Sheets/Docs/Forms/Calendar. Apps Script ingest.')) }}</p>

 <div class="feat-mock mt-5 grid grid-cols-4 gap-1.5">
 <span class="brand-tile aspect-square rounded-md bg-[#96BF48] text-white flex items-center justify-center p-2"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M15.4 4.16l-3.42.39c-.32-1.11-1.36-1.93-2.59-1.93-.07 0-.13.01-.2.02-.11-.13-.24-.27-.41-.44C8.18 1.74 7.5 1.5 6.83 1.5c-1.43 0-2.59 1.16-2.59 2.59 0 .56.18 1.08.48 1.5l-1.7.21c-.18.02-.32.16-.36.34L.96 19.45c-.05.21.09.42.31.46l13.55 2.45c.21.04.42-.1.45-.31l3.36-14.78c.04-.18-.07-.36-.25-.41z"/></svg></span>
 <span class="brand-tile aspect-square rounded-md bg-[#7F54B3] text-white flex items-center justify-center p-1.5"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M2.227 4C1 4 .009 4.997 0 6.224v8.13c0 1.234.998 2.232 2.226 2.232h10.609l4.853 2.7-1.1-2.7H21.78a2.23 2.23 0 002.226-2.232v-8.13A2.23 2.23 0 0021.779 4H2.227z"/></svg></span>
 <span class="brand-tile aspect-square rounded-md bg-[#FF7A59] text-white flex items-center justify-center p-2"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M18.164 7.93V5.084c.612-.292 1.012-.91 1.012-1.61 0-.99-.802-1.796-1.794-1.796-.99 0-1.794.802-1.794 1.793 0 .703.4 1.318 1.012 1.61V7.92a5.073 5.073 0 00-2.4.94L8.94 4.704c.052-.193.087-.392.087-.6 0-1.32-1.07-2.39-2.39-2.39A2.39 2.39 0 004.246 4.103c0 1.32 1.072 2.39 2.392 2.39.444 0 .855-.13 1.21-.337l5.155 4.013a5.057 5.057 0 00.815 7.142l-1.546 1.546a1.643 1.643 0 00-.412-.073c-.46 0-.836.376-.836.836s.376.836.836.836.836-.376.836-.836c0-.144-.038-.282-.106-.402l1.527-1.528a5.054 5.054 0 002.917.93c2.795 0 5.062-2.267 5.062-5.062 0-2.534-1.86-4.633-4.292-5.005l.36-2.624zm-2.797 7.625a2.6 2.6 0 11.001-5.2 2.6 2.6 0 010 5.2z"/></svg></span>
 <span class="brand-tile aspect-square rounded-md bg-white hairline flex items-center justify-center p-1.5"><svg viewBox="0 0 24 24" class="w-full h-full"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A10.99 10.99 0 0012 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A11 11 0 001 12c0 1.78.43 3.47 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg></span>
 <span class="brand-tile aspect-square rounded-md bg-[#0F9D58] text-white flex items-center justify-center p-2"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M4 2h11l5 5v13a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm10 1.5V8h4.5L14 3.5zM6 11v2h4v-2H6zm6 0v2h6v-2h-6zm-6 4v2h4v-2H6zm6 0v2h6v-2h-6z"/></svg></span>
 <span class="brand-tile aspect-square rounded-md bg-[#4285F4] text-white flex items-center justify-center p-2"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M4 2h11l5 5v13a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm10 1.5V8h4.5L14 3.5zM7 12h10v1.5H7V12zm0 3h10v1.5H7V15zm0 3h7v1.5H7V18z"/></svg></span>
 <span class="brand-tile aspect-square rounded-md bg-[#673AB7] text-white flex items-center justify-center p-2"><svg viewBox="0 0 24 24" class="w-full h-full" fill="currentColor"><path d="M4 2h11l5 5v13a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2zm10 1.5V8h4.5L14 3.5zM8 11v1.5h8V11H8zm0 4v1.5h6V15H8z"/><circle cx="6.5" cy="11.75" r=".8"/><circle cx="6.5" cy="15.75" r=".8"/></svg></span>
 <span class="brand-tile aspect-square rounded-md hairline text-ink-700 flex items-center justify-center bg-paper-50 p-2"><svg viewBox="0 0 24 24" class="w-full h-full" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M8 4l-6 8 6 8M16 4l6 8-6 8"/></svg></span>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('OAuth · webhooks · Sheets API') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>

 {{-- 16 · Scheduled --}}
 <a id="feat-16" href="{{ url('/register') }}" class="col-span-12 lg:col-span-4 feat-card hairline rounded-3xl bg-white p-6 block overflow-hidden">
 <div class="flex items-start justify-between mb-3">
 <div>
 <div class="feature-num text-[52px]">16</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-1.5" data-fc="feature-bento.card16_eyebrow">{{ fc('feature-bento.card16_eyebrow', __('Scheduled · recurring')) }}</div>
 </div>
 </div>
 <h3 class="serif text-[26px] leading-tight" data-fc="feature-bento.card16_title">{!! fc('feature-bento.card16_title', __('One-time.') . '<br><span class="italic text-wa-deep">' . __('Cron-style') . '</span> ' . __('too.')) !!}</h3>
 <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="feature-bento.card16_body">{{ fc('feature-bento.card16_body', __('Daily / weekly / monthly / cron. Pause/resume, retry failed, run-now.')) }}</p>

 <div class="feat-mock mt-5 hairline rounded-xl bg-paper-50 p-3 space-y-1.5">
 <div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span><span class="text-[10px] font-semibold flex-1">{{ __('Weekly digest') }}</span><span class="mono text-[9px] text-wa-deep">Mon 9:00</span></div>
 <div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span><span class="text-[10px] flex-1 text-ink-700">{{ __('Cart reminder · 4h') }}</span><span class="mono text-[9px] text-ink-500">{{ __('paused') }}</span></div>
 <div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span><span class="text-[10px] flex-1 text-ink-700">{{ __('Renewal · monthly') }}</span><span class="mono text-[9px] text-wa-deep">+2d</span></div>
 </div>

 <div class="hairline-t mt-5 pt-3 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('pause · run-now · retry') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open →') }}</span>
 </div>
 </a>
 </div>

 {{-- ROW 7: WA Calling (big) + Security (dark) --}}
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

 {{-- 17 · WA Calling --}}
 <a id="feat-17" href="{{ url('/register') }}" class="col-span-12 lg:col-span-7 feat-card hairline rounded-3xl bg-white p-8 block overflow-hidden">
 <div class="flex items-start justify-between mb-4">
 <div>
 <div class="feature-num text-[64px]">17</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mt-2" data-fc="feature-bento.card17_eyebrow">{{ fc('feature-bento.card17_eyebrow', __('WA calling · WebRTC')) }}</div>
 </div>
 <span class="pill bg-wa-bubble text-wa-deep" data-fc="feature-bento.card17_badge">{{ fc('feature-bento.card17_badge', __('recording')) }}</span>
 </div>
 <h3 class="serif text-[40px] leading-[0.95]" data-fc="feature-bento.card17_title">{!! fc('feature-bento.card17_title', __('Voice calls.') . '<br>' . __('In') . ' <span class="italic text-wa-deep">' . __('WhatsApp') . '</span>.') !!}</h3>
 <p class="text-[13px] text-ink-600 mt-3 leading-relaxed max-w-md" data-fc="feature-bento.card17_body">{{ fc('feature-bento.card17_body', __('Outbound dial. Inbound toast with accept/reject. WebRTC SDP. AI voicemail fallback. 3-way recording with transcript playback.')) }}</p>

 <div class="feat-mock mt-6 hairline rounded-2xl bg-paper-50 p-5">
 <div class="flex items-center gap-4">
 <div class="relative">
 <span class="absolute inset-0 rounded-full border-2 border-wa-green animate-ping"></span>
 <span class="w-14 h-14 rounded-full flex items-center justify-center text-paper-0 font-semibold text-[14px] relative" style="background:linear-gradient(135deg,#E87A5D,#E5A04E);">MR</span>
 </div>
 <div class="flex-1">
 <div class="text-[14px] font-semibold">Maya Ramaswamy</div>
 <div class="mono text-[10px] text-wa-deep flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span>{{ __('connected · 2:14') }}</div>
 <div class="mono text-[10px] text-ink-500 mt-0.5">{{ __('recording · transcript live') }}</div>
 </div>
 <div class="flex gap-2">
 <button class="w-9 h-9 rounded-full bg-paper-100 flex items-center justify-center"><svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-700" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="6" y="2" width="4" height="8" rx="2"/><path d="M3 8a5 5 0 0010 0M8 13v2"/></svg></button>
 <button class="w-9 h-9 rounded-full bg-accent-coral flex items-center justify-center text-paper-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="currentColor"><path d="M3 5C5 3.5 11 3.5 13 5c1 .8 1 2 0 3l-2 .5c-.6 0-1-.5-1-1V6c-1.3-.4-2.7-.4-4 0v1.5c0 .5-.4 1-1 1L3 8c-1-1-1-2.2 0-3z" transform="rotate(135 8 8)"/></svg></button>
 </div>
 </div>
 </div>

 <ul class="mt-5 grid grid-cols-2 gap-x-4 gap-y-1 text-[12px] text-ink-700">
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('WebRTC SDP') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('AI voicemail fallback') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('3-way recording') }}</li>
 <li class="flex gap-1.5"><span class="text-wa-deep">→</span>{{ __('Transcript playback') }}</li>
 </ul>

 <div class="hairline-t mt-6 pt-4 flex items-center justify-between text-[12px]">
 <span class="mono text-ink-500">{{ __('in/out · duration · status logs') }}</span>
 <span class="text-wa-deep font-semibold feat-arrow">{{ __('Open WA calling →') }}</span>
 </div>
 </a>

 {{-- 18 · Security (dark) --}}
 <a id="feat-18" href="{{ url('/register') }}" class="col-span-12 lg:col-span-5 feat-card rounded-3xl bg-ink-950 text-paper-0 p-8 block relative overflow-hidden">
 <div class="absolute inset-0 dot-pattern opacity-15"></div>
 <div class="absolute -bottom-32 -right-32 w-[300px] h-[300px] rounded-full bg-wa-green/15 blur-bub"></div>
 <div class="relative">
 <div class="flex items-start justify-between mb-4">
 <div>
 <div class="feature-num text-[64px] text-paper-0/25">18</div>
 <div class="mono text-[10px] uppercase tracking-[0.22em] text-paper-0/60 mt-2" data-fc="feature-bento.card18_eyebrow">{{ fc('feature-bento.card18_eyebrow', __('Security · audit · 30+ toggles')) }}</div>
 </div>
 <span class="pill bg-wa-green text-ink-900" data-fc="feature-bento.card18_badge">{{ fc('feature-bento.card18_badge', __('2FA · IP · SCAM')) }}</span>
 </div>
 <h3 class="serif text-[36px] leading-[0.95]" data-fc="feature-bento.card18_title">{!! fc('feature-bento.card18_title', __('Locked down') . '<br>' . __('by') . ' <span class="italic text-wa-green">' . __('policy.') . '</span>') !!}</h3>
 <p class="text-[13px] text-paper-0/75 mt-3 leading-relaxed" data-fc="feature-bento.card18_body">{{ fc('feature-bento.card18_body', __('2FA · IP allowlist · lockout · WA guardrails · scam patterns · abuse filters · webhook signatures · device trust · audit log.')) }}</p>

 <div class="feat-mock mt-6 grid grid-cols-2 gap-2.5">
 @foreach ([
 [__('2FA · all admins'), __('required')],
 [__('IP allowlist'), __('CIDR · 4 ranges')],
 [__('Scam patterns'), __('hold + alert')],
 [__('Emergency stop'), __('platform halt')],
 ] as [$title, $sub])
 <div class="border border-paper-0/15 rounded-xl bg-paper-0/5 p-2.5">
 <div class="flex items-center gap-1.5 mb-0.5"><span class="text-wa-green">✓</span><div class="text-[11px] font-semibold">{{ $title }}</div></div>
 <div class="mono text-[8.5px] text-paper-0/55">{{ $sub }}</div>
 </div>
 @endforeach
 </div>

 <div class="border-t border-paper-0/15 mt-6 pt-4 flex items-center justify-between text-[12px]">
 <span class="mono text-paper-0/55">{{ __('SOC 2 · ISO · GDPR · audit log') }}</span>
 <span class="text-wa-green font-semibold feat-arrow">{{ __('Open security →') }}</span>
 </div>
 </div>
 </a>
 </div>
 </div>
</section>
