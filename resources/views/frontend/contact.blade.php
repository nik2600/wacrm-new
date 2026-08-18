{{--
 Public contact page — composed of editorial sections in the prototype's
 exact design system: serif headlines, hairline cards, mono micro-labels.
 Form is a visual / preview; wire it to a real ContactController route
 before going live.
--}}
<x-layouts.frontend :title="__('Contact')" nav-key="contact" page="frontend-contact">

    {{-- ============== HERO ============== --}}
    <section data-fc-section="hero" class="relative overflow-hidden bg-paper-0">
        <div class="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
        <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-wa-mint/50 blur-bub"></div>
        <div class="absolute -bottom-40 -left-32 w-[460px] h-[460px] rounded-full bg-accent-amber/15 blur-bub"></div>

        <div class="relative max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 items-end">
                <div class="col-span-12 lg:col-span-8 reveal">
                    <span class="badge-num mb-6 inline-block" data-fc="contact.hero.eyebrow">—
                        {{ fc('contact.hero.eyebrow', __('Contact us')) }}</span>
                    <h1 class="serif text-[80px] lg:text-[104px] leading-[0.92] tracking-[-0.025em]"
                        data-fc="contact.hero.headline" data-fc-type="richtext">
                        {!! fc(
                            'contact.hero.headline',
                            __('A real human') .
                                '<br>' .
                                __('replies inside') .
                                ' <span class="italic text-wa-deep">' .
                                __('four hours.') .
                                '</span>',
                        ) !!}
                    </h1>
                </div>
                <div class="col-span-12 lg:col-span-4 reveal" style="--d:120ms">
                    <p class="text-[15.5px] text-ink-700 leading-relaxed border-l-2 border-wa-deep pl-4"
                        data-fc="contact.hero.intro">
                        {{ fc('contact.hero.intro', __('Sales, support, partnerships, security — every email lands in the same inbox, and someone on our team replies. No tickets, no bots, no escalation queues.')) }}
                    </p>
                    {{-- Hero stat trio — value + label of each live-editable so a
 business sets its own reply time / hours / languages. --}}
                    <div class="mt-6 grid grid-cols-3 gap-0 hairline-t hairline-b py-4">
                        <div class="hairline-r pr-4">
                            <div data-fc="contact.hero.stat1-value" class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('contact.hero.stat1-value', '4h') }}</div>
                            <div data-fc="contact.hero.stat1-label" class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('contact.hero.stat1-label', __('avg reply')) }}</div>
                        </div>
                        <div class="hairline-r px-4">
                            <div data-fc="contact.hero.stat2-value" class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('contact.hero.stat2-value', '24/7') }}</div>
                            <div data-fc="contact.hero.stat2-label" class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('contact.hero.stat2-label', __('on Scale')) }}</div>
                        </div>
                        <div class="pl-4">
                            <div data-fc="contact.hero.stat3-value" class="serif text-[32px] leading-none tabular text-wa-deep">{{ fc('contact.hero.stat3-value', '9') }}</div>
                            <div data-fc="contact.hero.stat3-label" class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1.5">
                                {{ fc('contact.hero.stat3-label', __('languages')) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Sections below the hero are captured by slug and echoed in the
 admin-defined order (fc_section_order), hidden ones skipped. The hero
 above is pinned. --}}
    @php $sec = []; @endphp

    {{-- ============== CHANNELS ============== --}}
    @php ob_start(); @endphp
    <section data-fc-section="channels" class="bg-white">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">01</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                        data-fc="contact.channels.eyebrow">— {{ fc('contact.channels.eyebrow', __('Pick a channel')) }}
                    </div>
                    <div class="text-[13px] font-semibold" data-fc="contact.channels.sublabel">
                        {{ fc('contact.channels.sublabel', __('Whichever works for you')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span data-fc="contact.channels.caption1">{{ fc('contact.channels.caption1', __('email · WhatsApp · form')) }}</span><span class="text-ink-300">·</span>
                    <span data-fc="contact.channels.caption2" class="text-wa-deep">{{ fc('contact.channels.caption2', __('all reach the same team')) }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 reveal" style="--d:120ms">

                {{-- Email --}}
                <a href="mailto:{{ brand_email('support') }}"
                    class="hairline rounded-3xl bg-paper-50 p-7 hover:border-wa-deep transition block">
                    <span class="w-14 h-14 rounded-2xl bg-wa-deep text-paper-0 flex items-center justify-center">
                        <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M3 7l9 7 9-7" />
                        </svg>
                    </span>
                    <div class="mt-5">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500" data-fc="contact.ch1.label">{{ fc('contact.ch1.label', __('channel · 01')) }}
                        </div>
                        <div class="serif text-[36px] leading-none mt-1" data-fc="contact.ch1.title">{{ fc('contact.ch1.title', __('Email')) }}</div>
                    </div>
                    <h3 class="text-[15px] font-semibold mt-4">{{ brand_email('support') }}</h3>
                    <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="contact.ch1.desc">
                        {{ fc('contact.ch1.desc', __('Goes straight to the founders + customer team. Median reply inside four hours, weekends included.')) }}
                    </p>
                    <ul class="mt-5 space-y-1.5 hairline-t pt-4">
                        <li class="flex items-center justify-between text-[12px]"><span
                                class="text-ink-700" data-fc="contact.ch.bestfor-label">{{ fc('contact.ch.bestfor-label', __('Best for')) }}</span><span
                                class="mono text-[10.5px] text-wa-deep font-semibold" data-fc="contact.ch1.bestfor">{{ fc('contact.ch1.bestfor', __('anything · everything')) }}</span>
                        </li>
                        <li class="flex items-center justify-between text-[12px]"><span
                                class="text-ink-700" data-fc="contact.ch.replytime-label">{{ fc('contact.ch.replytime-label', __('Reply time')) }}</span><span
                                class="mono text-[10.5px] text-wa-deep font-semibold" data-fc="contact.ch1.replytime">{{ fc('contact.ch1.replytime', '~4h') }}</span></li>
                    </ul>
                </a>

                {{-- WhatsApp --}}
                <a href="https://wa.me/{{ site_info('whatsapp', '918012345678') }}" target="_blank" rel="noopener"
                    class="hairline rounded-3xl bg-paper-50 p-7 hover:border-wa-deep transition block relative overflow-hidden">
                    <div class="absolute top-5 right-5"><span
                            class="pill bg-wa-bubble text-wa-deep border border-wa-green/40"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green pulse-dot"></span><span data-fc="contact.ch2.badge">{{ fc('contact.ch2.badge', __('online')) }}</span></span>
                    </div>
                    <span class="w-14 h-14 rounded-2xl bg-wa-green text-ink-900 flex items-center justify-center">
                        <svg viewBox="0 0 24 24" class="w-7 h-7" fill="currentColor">
                            <path
                                d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24z" />
                        </svg>
                    </span>
                    <div class="mt-5">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500" data-fc="contact.ch2.label">{{ fc('contact.ch2.label', __('channel · 02')) }}
                        </div>
                        <div class="serif text-[36px] leading-none mt-1" data-fc="contact.ch2.title">{{ fc('contact.ch2.title', 'WhatsApp') }}</div>
                    </div>
                    <h3 class="text-[15px] font-semibold mt-4">{{ site_info('phone', '+91 80123 45678') }}</h3>
                    <p class="text-[12.5px] text-ink-600 mt-2 leading-relaxed" data-fc="contact.ch2.desc">
                        {{ fc('contact.ch2.desc', __('Yes, the company that sells WhatsApp tools also takes support on WhatsApp. Send a message — same team replies.')) }}
                    </p>
                    <ul class="mt-5 space-y-1.5 hairline-t pt-4">
                        <li class="flex items-center justify-between text-[12px]"><span
                                class="text-ink-700" data-fc="contact.ch.bestfor-label">{{ fc('contact.ch.bestfor-label', __('Best for')) }}</span><span
                                class="mono text-[10.5px] text-wa-deep font-semibold" data-fc="contact.ch2.bestfor">{{ fc('contact.ch2.bestfor', __('quick questions')) }}</span>
                        </li>
                        <li class="flex items-center justify-between text-[12px]"><span
                                class="text-ink-700" data-fc="contact.ch.replytime-label">{{ fc('contact.ch.replytime-label', __('Reply time')) }}</span><span
                                class="mono text-[10.5px] text-wa-deep font-semibold" data-fc="contact.ch2.replytime">{{ fc('contact.ch2.replytime', '~30m') }}</span></li>
                    </ul>
                </a>

                {{-- Sales --}}
                <a href="#contact-form"
                    class="hairline rounded-3xl bg-ink-950 text-paper-0 p-7 hover:border-wa-green transition block relative overflow-hidden">
                    <div class="absolute inset-0 dot-pattern opacity-15"></div>
                    <div class="relative">
                        <span class="w-14 h-14 rounded-2xl bg-wa-green text-ink-900 flex items-center justify-center">
                            <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path
                                    d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z" />
                            </svg>
                        </span>
                        <div class="mt-5">
                            <div class="mono text-[10px] uppercase tracking-widest text-paper-0/60" data-fc="contact.ch3.label">
                                {{ fc('contact.ch3.label', __('channel · 03')) }}</div>
                            <div class="serif text-[36px] leading-none mt-1" data-fc="contact.ch3.title">{{ fc('contact.ch3.title', __('Sales')) }}</div>
                        </div>
                        <h3 class="text-[15px] font-semibold mt-4" data-fc="contact.ch3.headline">{{ fc('contact.ch3.headline', __('Talk to a human about Scale plans.')) }}</h3>
                        <p class="text-[12.5px] text-paper-0/75 mt-2 leading-relaxed" data-fc="contact.ch3.desc">
                            {{ fc('contact.ch3.desc', __('Custom contracts, security review, data residency, migration. Book a 30-minute call with our founder team.')) }}
                        </p>
                        <ul class="mt-5 space-y-1.5 border-t border-paper-0/15 pt-4">
                            <li class="flex items-center justify-between text-[12px]"><span
                                    class="text-paper-0/75" data-fc="contact.ch.bestfor-label">{{ fc('contact.ch.bestfor-label', __('Best for')) }}</span><span
                                    class="mono text-[10.5px] text-wa-green font-semibold" data-fc="contact.ch3.bestfor">{{ fc('contact.ch3.bestfor', __('Scale plan · enterprise')) }}</span>
                            </li>
                            <li class="flex items-center justify-between text-[12px]"><span
                                    class="text-paper-0/75" data-fc="contact.ch.replytime-label">{{ fc('contact.ch.replytime-label', __('Reply time')) }}</span><span
                                    class="mono text-[10.5px] text-wa-green font-semibold" data-fc="contact.ch3.replytime">{{ fc('contact.ch3.replytime', __('same day')) }}</span>
                            </li>
                        </ul>
                    </div>
                </a>
            </div>
        </div>
    </section>

    @php $sec['channels'] = ob_get_clean(); @endphp

    {{-- ============== FORM ============== --}}
    @php ob_start(); @endphp
    <section id="contact-form" data-fc-section="form" class="bg-paper-50 hairline-t hairline-b">
        <div class="max-w-[1360px] mx-auto px-7 py-28">
            <div class="grid grid-cols-12 gap-8 mb-12">
                <div class="col-span-2">
                    <div class="feature-num">02</div>
                </div>
                <div class="col-span-3 flex flex-col justify-end pb-3">
                    <div class="mono text-[10px] uppercase tracking-[0.22em] text-ink-500 mb-1"
                        data-fc="contact.form.eyebrow">— {{ fc('contact.form.eyebrow', __('Write to us')) }}</div>
                    <div class="text-[13px] font-semibold" data-fc="contact.form.sublabel">
                        {{ fc('contact.form.sublabel', __('Send a message')) }}</div>
                </div>
                <div class="col-span-7 flex items-end justify-end pb-3 gap-3 text-[11px] mono text-ink-500">
                    <span class="text-wa-deep" data-fc="contact.form.caption">{{ fc('contact.form.caption', __('lands in the founders\' inbox')) }}</span>
                </div>
            </div>

            <h2 class="serif text-[88px] leading-[0.92] tracking-[-0.02em] mb-12 reveal"
                data-fc="contact.form.headline" data-fc-type="richtext">
                {!! fc(
                    'contact.form.headline',
                    __('Tell us what') . '<br>' . __('you are') . ' <span class="italic text-wa-deep">' . __('shipping.') . '</span>',
                ) !!}
            </h2>

            <div class="grid grid-cols-12 gap-8 reveal" style="--d:160ms">
                <div class="col-span-12 lg:col-span-7">
                    @if (session('contact_status') === 'success')
                        <div
                            class="mb-5 rounded-2xl bg-wa-bubble border border-wa-green/40 px-5 py-4 flex items-start gap-3">
                            <svg viewBox="0 0 16 16" class="w-5 h-5 text-wa-deep shrink-0 mt-0.5" fill="none"
                                stroke="currentColor" stroke-width="1.8">
                                <circle cx="8" cy="8" r="6.5" />
                                <path d="M5 8.2 7 10l4-4.5" />
                            </svg>
                            <div>
                                <div class="text-[14px] font-semibold text-wa-deep" data-fc="contact.form.success-title">{{ fc('contact.form.success-title', __('Message sent')) }}</div>
                                <p class="text-[12.5px] text-ink-700 mt-0.5" data-fc="contact.form.success-body">
                                    {{ fc('contact.form.success-body', __('Thanks — your message landed in our inbox. We reply inside 4 hours, weekends included.')) }}
                                </p>
                            </div>
                        </div>
                    @endif
                    @if (isset($errors) && $errors->any())
                        <div
                            class="mb-5 rounded-2xl bg-accent-coral/10 border border-accent-coral/30 px-5 py-4 text-[12.5px] text-accent-coral">
                            <span data-fc="contact.form.error">{{ fc('contact.form.error', __('Please check the form — some fields need attention.')) }}</span>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('frontend.contact.submit') }}"
                        class="hairline rounded-3xl bg-white p-8 space-y-5">
                        @csrf
                        {{-- Honeypot — hidden from humans, bots fill it; submissions with it set are silently dropped. --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden"
                            aria-hidden="true">
                        @php $topic = old('topic'); @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2 block" data-fc="contact.form.label-name">{{ fc('contact.form.label-name', __('Your name')) }}</label>
                                <input type="text" name="name" value="{{ old('name') }}" required
                                    maxlength="120"
                                    class="w-full hairline rounded-xl bg-paper-50 px-4 py-3 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="Maya Ramaswamy">
                            </div>
                            <div>
                                <label
                                    class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2 block" data-fc="contact.form.label-email">{{ fc('contact.form.label-email', __('Work email')) }}</label>
                                <input type="email" name="email" value="{{ old('email') }}" required
                                    maxlength="190"
                                    class="w-full hairline rounded-xl bg-paper-50 px-4 py-3 text-[13px] focus:outline-none focus:border-wa-deep"
                                    placeholder="maya@company.com">
                            </div>
                        </div>

                        <div>
                            <label
                                class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2 block" data-fc="contact.form.label-company">{{ fc('contact.form.label-company', __('Company')) }}</label>
                            <input type="text" name="company" value="{{ old('company') }}" maxlength="160"
                                class="w-full hairline rounded-xl bg-paper-50 px-4 py-3 text-[13px] focus:outline-none focus:border-wa-deep"
                                placeholder="Bloomly Flowers">
                        </div>

                        <div>
                            <label
                                class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2 block" data-fc="contact.form.label-topic">{{ fc('contact.form.label-topic', __('How can we help?')) }}</label>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <label
                                    class="hairline rounded-lg bg-paper-50 px-3 py-2 text-[12px] flex items-center gap-2 cursor-pointer hover:border-wa-deep">
                                    <input type="radio" name="topic" value="sales" @checked($topic === 'sales')
                                        class="text-wa-deep">{{ __('Sales') }}
                                </label>
                                <label
                                    class="hairline rounded-lg bg-paper-50 px-3 py-2 text-[12px] flex items-center gap-2 cursor-pointer hover:border-wa-deep">
                                    <input type="radio" name="topic" value="support" @checked($topic === 'support')
                                        class="text-wa-deep">{{ __('Support') }}
                                </label>
                                <label
                                    class="hairline rounded-lg bg-paper-50 px-3 py-2 text-[12px] flex items-center gap-2 cursor-pointer hover:border-wa-deep">
                                    <input type="radio" name="topic" value="partnership"
                                        @checked($topic === 'partnership') class="text-wa-deep">{{ __('Partnership') }}
                                </label>
                                <label
                                    class="hairline rounded-lg bg-paper-50 px-3 py-2 text-[12px] flex items-center gap-2 cursor-pointer hover:border-wa-deep">
                                    <input type="radio" name="topic" value="other" @checked($topic === 'other')
                                        class="text-wa-deep">{{ __('Other') }}
                                </label>
                            </div>
                        </div>

                        <div>
                            <label
                                class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2 block" data-fc="contact.form.label-message">{{ fc('contact.form.label-message', __('Your message')) }}</label>
                            <textarea name="message" rows="5" required maxlength="5000"
                                class="w-full hairline rounded-xl bg-paper-50 px-4 py-3 text-[13px] focus:outline-none focus:border-wa-deep resize-none"
                                placeholder="{{ __('What are you trying to ship? Be as specific as you like — the more context, the faster we can help.') }}">{{ old('message') }}</textarea>
                        </div>

                        <div class="hairline-t pt-5 flex items-center justify-between">
                            <div class="flex items-center gap-2 text-[11.5px] text-ink-600">
                                <span class="w-2 h-2 rounded-full bg-wa-green pulse-dot"></span>
                                <span data-fc="contact.form.sla">{{ fc('contact.form.sla', __('We reply inside 4 hours, weekends included.')) }}</span>
                            </div>
                            <button type="submit"
                                class="px-6 py-3 rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                                <span data-fc="contact.form.submit">{{ fc('contact.form.submit', __('Send message')) }}</span>
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M5 4l4 4-4 4" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Office — driven entirely by Admin → Site Settings
                     (/admin/site-settings). One place to edit; no per-page
                     editing. Blocks that have no value are simply hidden. --}}
                @php
                    $officeName = site_info('company_name');
                    $officeAddr = site_info('address');
                    $officeCity = site_info('city_country');
                    $officeEmail = site_info('email_general', site_info('email_support'));
                    $officePhone = site_info('phone');
                @endphp
                @if ($officeName || $officeAddr || $officeCity || $officeEmail || $officePhone)
                <div class="col-span-12 lg:col-span-5">
                    <div class="hairline rounded-3xl bg-white p-7">
                        <div class="flex items-center justify-between mb-3">
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500" data-fc="contact.office.label">
                                {{ fc('contact.office.label', __('Head office')) }}</div>
                            <span class="pill bg-wa-green/15 text-wa-deep text-[10px]">HQ</span>
                        </div>
                        @if ($officeName)
                            <h3 class="serif text-[34px] leading-none">{{ $officeName }}</h3>
                        @endif
                        @if ($officeAddr || $officeCity)
                            <p class="text-[13px] text-ink-700 mt-3 leading-relaxed">
                                @if ($officeAddr){{ $officeAddr }}@if ($officeCity)<br>@endif @endif
                                @if ($officeCity){{ $officeCity }}@endif
                            </p>
                        @endif
                        @if ($officeEmail || $officePhone)
                        <div class="hairline-t mt-5 pt-4 grid grid-cols-2 gap-4 text-[11px]">
                            @if ($officeEmail)
                            <div>
                                <div class="mono text-[9px] uppercase tracking-widest text-ink-500">
                                    {{ __('email') }}</div>
                                <a href="mailto:{{ $officeEmail }}" class="text-[13px] text-wa-deep mt-1 block break-all">{{ $officeEmail }}</a>
                            </div>
                            @endif
                            @if ($officePhone)
                            <div>
                                <div class="mono text-[9px] uppercase tracking-widest text-ink-500">
                                    {{ __('phone') }}</div>
                                <a href="tel:{{ preg_replace('/\s+/', '', $officePhone) }}" class="text-[13px] text-wa-deep mt-1 block">{{ $officePhone }}</a>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>

    @php $sec['form'] = ob_get_clean(); @endphp

    {{-- ============== FAQ ============== --}}
    @php ob_start(); @endphp
    <x-frontend.faq :kicker="__('Quick answers')" scope="contact" :headline="__('Before you <span class=\'italic text-wa-deep\'>email.</span>')" :subtitle="__('Most questions answered here in 2 minutes.')"
        :items="[
            ['q' => __('How fast do you reply?'), 'a' => __(
                    'Median 4 hours, including weekends. Scale customers get 24/7 coverage with a 1-hour SLA.'),
                'open' => true
            ],
            ['q' => __('Do you offer demos?'), 'a' => __(
                'Yes — book one through the Sales card above. 30 minutes, founder-led, no pitch deck.')],
            ['q' => __('Do you have a phone number for support?'), 'a' => __(
                'We do support on WhatsApp instead (yes, dogfooding). For Scale plans we offer a direct line during business hours.'
                )],
            ['q' => __('Where do I report a security issue?'), 'a' => __(
                'Email :email — a security engineer reviews every report inside 24 hours.', ['email' => brand_email('security')])],
            ['q' => __('Are you hiring?'), 'a' => __(
                'Always. Email :email with what you want to build — we read every email.', ['email' => brand_email('careers')])],
        ]" />
    @php $sec['faq'] = ob_get_clean(); @endphp

    @php ob_start(); @endphp
    <x-frontend.cta-final />
    @php $sec['cta-final'] = ob_get_clean(); @endphp

    @php
        foreach (fc_section_order('contact', array_keys($sec)) as $slug) {
            if (fc_section_visible('contact', $slug)) {
                echo $sec[$slug];
            }
        }
    @endphp

</x-layouts.frontend>
