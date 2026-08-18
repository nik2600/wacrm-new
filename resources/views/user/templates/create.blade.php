<x-layouts.user :title="__('Create Template')" nav-key="templates" page="user-templates-create">

    {{-- Instagram templates are local reusable DMs — no Meta category/language,
         no header/footer, and no "submit for review". When the channel is
         Instagram (chosen on the New-template modal) we hide those Meta-only
         sections so the form shows only what Instagram actually supports:
         name, body, media, and buttons. --}}
    @php
        $isIg = ($defaultChannel ?? '') === 'instagram';
        // Built here (raw PHP) rather than inline in the textarea's Blade echo —
        // a literal {{name}} inside a {{ }} echo makes the Blade compiler choke
        // ("Unclosed '(' does not match '}'").
        $bodyPlaceholder = $isIg
            ? __('Type your Instagram DM. Press / to insert a variable like {{name}}.')
            : 'Type your message. Press / to insert a variable. Meta requires positional placeholders like {{1}} {{2}}.';
    @endphp

    <div class="hairline-b border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/templates') }}"
                    class="w-8 h-8 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to templates') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div id="fmt-crumb" class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Templates / New') }}</div>
                    <div id="fmt-title"
                        class="serif font-serif font-normal tracking-[-0.01em] text-[20px] leading-tight truncate">
                        {{ __('Create message') }} <span class="italic text-wa-deep">{{ __('template') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span id="fmt-pill"
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 mono font-mono">{{ __('Standard') }}</span>
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="button" id="open-ai-modal"
                    class="btn-ai-shine relative isolate overflow-hidden px-3.5 py-1.5 rounded-full text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                    Build with AI
                </button>
                <button type="button"
                    class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
                <button type="submit" form="templateForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    {{ $isIg ? __('Save template') : __('Submit for review') }}
                </button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12px] text-[#A1431F]">
                <div class="font-semibold mb-1">{{ __('Could not save the template:') }}</div>
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="templateForm" method="POST" action="{{ route('user.templates.store') }}"
            enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">
            @csrf
            <input type="hidden" name="template_type" id="template-type-input"
                value="{{ request('type', 'standard') }}">
            <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card">

                <!-- 01 Identity -->
                <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                    <div class="sec-head flex items-center gap-2.5 mb-3">
                        <span
                            class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span
                            class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Identity') }}</span>
                        <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="tpl-name">{{ __('Template name') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="tpl-name" name="template_name" type="text"
                                value="{{ old('template_name') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('spring_promo_v3') }}" maxlength="60" required>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('a-z, 0-9, _ only / max 60.') }}</div>
                        </div>
                        <div class="{{ $isIg ? 'hidden' : '' }}">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="tpl-category">{{ __('Category') }} <span
                                    class="req text-accent-coral">*</span></label>
                            {{-- Meta-approved category drives the review path. The
 separate `category` (industry tag, default utility)
 is sent as a hidden input so the controller's
 validate('in:travel,…,utility') still passes
 without the operator picking. --}}
                            <select id="tpl-category" name="meta_category"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option value="marketing" @selected(old('meta_category') === 'marketing')>{{ __('Marketing') }}</option>
                                <option value="utility" @selected(old('meta_category') === 'utility' || !old('meta_category'))>{{ __('Utility') }}</option>
                                <option value="authentication" @selected(old('meta_category') === 'authentication')>{{ __('Authentication') }}
                                </option>
                            </select>
                            <input type="hidden" name="category" value="{{ old('category', 'utility') }}">
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('Determines Meta review path.') }}</div>
                        </div>
                        <div class="{{ $isIg ? 'hidden' : '' }}">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="tpl-language">{{ __('Language') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <select id="tpl-language" name="language"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                @foreach (wa_template_languages() as $code => $label)
                                    <option value="{{ $code }}" @selected(old('language', 'en_US') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('One locale per template.') }}</div>
                        </div>
                    </div>

                    {{-- Send channel (multi-engine): which engine this template is for.
 Only WABA is submitted to Meta for approval; Unofficial API + Twilio
 are saved locally and ready to send. Hidden when only one engine is
 available (single-engine workspaces are unchanged). --}}
                    @php
                        // Instagram is chosen via the "New template" modal's Instagram
                        // card (?channel=instagram) — it is NOT a WhatsApp send channel,
                        // so drop it from the picker on the normal (WhatsApp) create page.
                        // In IG mode we keep it (checked, section hidden) so submit still
                        // sees channel=instagram.
                        $pickerChannels = ($defaultChannel ?? '') === 'instagram'
                            ? ($channels ?? [])
                            : array_values(array_filter($channels ?? [], fn ($c) => $c !== 'instagram'));
                    @endphp
                    @if (count($pickerChannels) > 1)
                        {{-- When the channel was already chosen on the "New template"
                             modal (Instagram card → ?channel=instagram), the picker is
                             redundant. Keep the radios in the DOM (instagram stays
                             checked, so the field-toggling JS + submit still see the
                             channel) but hide the whole section. --}}
                        <div class="mt-3 {{ ($defaultChannel ?? '') === 'instagram' ? 'hidden' : '' }}">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center gap-2 mb-[5px]">
                                {{ __('Send channel') }} <span class="req text-accent-coral">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                @foreach ($pickerChannels as $ck)
                                    @php
                                        $cmeta =
                                            [
                                                'baileys' => [__('Unofficial API'), __('Ready instantly — sent as text + buttons. No Meta approval.')],
                                                'waba' => [__('Meta (WABA)'), __('Submitted to Meta for approval before it can send.')],
                                                'twilio' => [__('Twilio'), __('Uses the Twilio Content SID below.')],
                                                'instagram' => [__('Instagram'), __('A reusable Instagram DM — ready instantly, no Meta approval.')],
                                            ][$ck] ?? [$ck, ''];
                                    @endphp
                                    <label
                                        class="flex flex-col gap-1 border border-paper-200 rounded-xl px-3 py-2.5 cursor-pointer hover:border-wa-deep/50">
                                        <span class="flex items-center gap-2">
                                            <input type="radio" name="channel" value="{{ $ck }}"
                                                class="accent-wa-deep" @checked(($defaultChannel ?? '') === $ck)>
                                            <span class="text-[12.5px] font-semibold text-ink-900">{{ $cmeta[0] }}</span>
                                        </span>
                                        <span
                                            class="text-[10.5px] text-ink-500 leading-tight pl-[26px]">{{ $cmeta[1] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('Pick which channel this template is for. Only WABA goes to Meta for approval.') }}
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="channel" value="{{ $pickerChannels[0] ?? 'baileys' }}">
                    @endif

                    {{-- WABA account picker (multi-WABA). When more than one WhatsApp
 Business account is connected, the operator chooses WHICH one this
 template is built on. The header/carousel media upload AND the Meta
 submit both use THIS account — using different ones makes Meta reject
 the carousel with "Bad parameter: Invalid parameter" (a media handle
 minted on WABA A is not valid on WABA B). Hidden with 0/1 WABA
 connected, where it uses the workspace's primary WABA as before. --}}
                    @if (($wabaConfigs ?? collect())->count() > 1)
                        <div class="mt-3">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center gap-2 mb-[5px]"
                                for="tpl-waba-config">
                                {{ __('WhatsApp Business account') }} <span class="req text-accent-coral">*</span>
                            </label>
                            <select id="tpl-waba-config" name="provider_config_id"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                @foreach ($wabaConfigs as $wc)
                                    <option value="{{ $wc->id }}" @selected((int) old('provider_config_id', $primaryWabaId ?? 0) === (int) $wc->id)>
                                        {{ $wc->display_label ?: ('WABA #' . $wc->id) }}{{ $wc->phone_number ? ' · ' . $wc->phone_number : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('You have more than one WABA connected — the template and its images are created on the account you pick here.') }}
                            </div>
                        </div>
                    @endif

                    {{-- Twilio ContentSid: optional per-template pointer to a Twilio
 Content Builder template (HX...). When set, Twilio sends use
 the approved-template path (compliant for MARKETING /
 UTILITY / AUTHENTICATION categories) instead of plain Body
 text. Leave blank for Baileys-only / WABA-only workspaces.
 Only shown when the workspace's active engine is Twilio. --}}
                    @if (in_array('twilio', $channels ?? []))
                        {{-- Only relevant for the Twilio channel — hidden by default,
 JS reveals it when the Twilio radio is picked. --}}
                        <div data-twilio-sid-block
                            class="grid grid-cols-1 md:grid-cols-1 gap-3 mt-3 {{ ($defaultChannel ?? '') === 'twilio' ? '' : 'hidden' }}">
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="tpl-twilio-sid">
                                    {{ __('Twilio Content SID') }} <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
                                </label>
                                <input id="tpl-twilio-sid" type="text" name="twilio_content_sid"
                                    value="{{ old('twilio_content_sid') }}" pattern="HX[0-9a-fA-F]{32}"
                                    maxlength="34" placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 font-mono transition leading-[1.4] placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                    {{ __('Paste the HX… ContentSid from Twilio Content Builder. Required for compliant Twilio sends of MARKETING / UTILITY / AUTHENTICATION templates.') }}
                                </div>
                            </div>
                            {{-- For Twilio the content (header/body/buttons/carousel) is built in
 Twilio's Content Template Builder, not here — only the SID is sent.
 The builder sections below apply to Unofficial API + WABA. --}}
                            <div
                                class="rounded-xl border border-accent-amber/40 bg-accent-amber/10 px-3 py-2.5 text-[11.5px] text-ink-700 flex items-start gap-2">
                                <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-amber shrink-0 mt-0.5" fill="none"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path d="M8 2v6M8 11v.5" />
                                    <circle cx="8" cy="8" r="6.5" />
                                </svg>
                                <span>{{ __('Twilio templates are built in Twilio\'s Content Template Builder — only the Content SID above is sent. The Header / Body / Buttons / Carousel fields below apply to Unofficial API & WABA templates only.') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Content builder (Header / Body / Buttons / Carousel) — used for
 Unofficial API + WABA. Hidden by JS for the Twilio channel, whose
 content is defined in Twilio's Content Template Builder (sent by SID). --}}
                <div data-builder-sections>
                <!-- ===== STANDARD ===== -->
                <div id="standard-sections">

                    <!-- 03 Header -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200 {{ $isIg ? 'hidden' : '' }}">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Header') }}</span>
                            <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-3">
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="header-type">{{ __('Type') }}</label>
                                <select id="header-type"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="text">{{ __('Text') }}</option>
                                    <option value="none">{{ __('None') }}</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="tpl-header">{{ __('Header text') }} <span
                                        class="sec-meta font-mono text-[10px] text-ink-500">max 60 / supports
                                        @{{ 1 }}</span></label>
                                <input id="tpl-header" name="header" type="text" value="{{ old('header') }}"
                                    maxlength="60"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="Hi @{{ 1 }}, welcome aboard!">
                            </div>
                        </div>
                    </div>

                    <!-- 04 Body -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Body') }}</span>
                            <span class="sec-meta font-mono text-[10px] text-ink-500"><span
                                    class="req text-accent-coral">{{ __('required') }}</span> / <span
                                    id="char-count">0</span>/1024</span>
                        </div>
                        <div data-attr-form>
                            <div
                                class="ed border border-paper-200 rounded-lg bg-white transition overflow-hidden focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10">
                                <div
                                    class="ed-tb flex items-center gap-px px-1.5 py-[5px] border-b border-paper-100 bg-paper-0">
                                    <span
                                        class="ed-btn w-6 h-6 rounded-[5px] inline-flex items-center justify-center cursor-pointer text-ink-600 text-[11.5px] font-semibold transition hover:bg-white hover:text-wa-deep"
                                        onclick="format('bold')" title="{{ __('Bold') }}"><b>B</b></span>
                                    <span
                                        class="ed-btn w-6 h-6 rounded-[5px] inline-flex items-center justify-center cursor-pointer text-ink-600 text-[11.5px] font-semibold transition hover:bg-white hover:text-wa-deep italic"
                                        onclick="format('italic')" title="{{ __('Italic') }}"><i>I</i></span>
                                    <span
                                        class="ed-btn w-6 h-6 rounded-[5px] inline-flex items-center justify-center cursor-pointer text-ink-600 text-[11.5px] font-semibold transition hover:bg-white hover:text-wa-deep line-through"
                                        onclick="format('strike')" title="{{ __('Strikethrough') }}">S</span>
                                    <span
                                        class="ed-btn w-6 h-6 rounded-[5px] inline-flex items-center justify-center cursor-pointer text-ink-600 text-[11.5px] font-semibold transition hover:bg-white hover:text-wa-deep mono font-mono"
                                        onclick="format('code')" title="{{ __('Code') }}">‹›</span>
                                    <span class="ed-sep w-px h-[14px] bg-paper-200 mx-[3px]"></span>
                                    {{-- Named-only authoring: this pill opens the `/` attribute
 picker on the body so the operator inserts a NAMED token
 ({{name}}) — the server normalizes it to positional {{1}}
 on save. (Replaces the old {{1}}–{{4}} numbered chips.) --}}
                                    <span
                                        class="ed-pill inline-flex items-center gap-1 px-[7px] py-[3px] rounded-[5px] bg-wa-bubble text-wa-deep text-[10.5px] font-medium cursor-pointer transition hover:bg-wa-deep hover:text-paper-0"
                                        title="{{ __('Insert a variable') }}"
                                        onclick="(function(){var t=document.getElementById('tpl-body');if(!t)return;t.focus();var s=t.selectionStart,e=t.selectionEnd,v=t.value,b=s>0?v[s-1]:'',ins=(b&&!/\s/.test(b)?' /':'/');t.value=v.slice(0,s)+ins+v.slice(e);var c=s+ins.length;t.setSelectionRange(c,c);t.dispatchEvent(new Event('input',{bubbles:true}));})()">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="1.8">
                                            <path d="M8 3.5v9M3.5 8h9" />
                                        </svg>
                                        {{ __('Variable') }}</span>
                                    <span class="ml-auto sec-meta font-mono text-[10px] text-ink-500 pr-1"><span
                                            id="char-count2">0</span>/1024</span>
                                </div>
                                {{-- Empty by default — the example used to live inside
 the textarea, which meant submitting without any
 edits silently sent that placeholder copy. Moved
 to the `placeholder` attr so the body is real. --}}
                                <textarea id="tpl-body" name="template_body" data-attr-input maxlength="1024" rows="5" required
                                    class="ed-ta w-full border-0 px-[11px] py-[9px] text-[12.5px] text-ink-900 resize-y min-h-[110px] leading-[1.5] font-sans outline-none placeholder:text-[#9CA8A4]"
                                    placeholder="{{ $bodyPlaceholder }}">{{ old('template_body') }}</textarea>
                            </div>

                            {{-- Variable mapping — records WHICH attribute each positional
 placeholder ({{1}}, {{2}}, …) resolves to at send time.
 The hidden `variable_map_json` field carries the
 { slot: attribute_key } object the controller turns into
 `variable_map`. The attribute-picker (`/` in the body)
 writes here too via the `data-attr-map` wrapper. The
 visible panel below is populated by user-templates-create.js
 from the placeholders currently in the body. --}}
                            <input type="hidden" name="variable_map_json" data-attr-map
                                value="{{ old('variable_map_json', '{}') }}">
                            {{-- Panel hidden: named tokens ({{company}}) already name their
 attribute, so a manual slot→attribute map is redundant (and
 its auto-guess could show a wrong attribute). The server
 derives variable_map from the token names on save. The
 hidden field above is kept for the legacy positional path. --}}
                            <div id="var-map-panel"
                                class="mt-2.5 rounded-lg border border-paper-200 bg-paper-50/60 px-3 py-2.5 {{ $isIg ? 'hidden' : '' }}">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <path d="M3 8h10M9 4l4 4-4 4" />
                                    </svg>
                                    <span
                                        class="text-[11.5px] font-semibold text-ink-700">{{ __('Variable mapping') }}</span>
                                    <span
                                        class="sec-meta font-mono text-[10px] text-ink-500">{{ __('which attribute fills each slot') }}</span>
                                </div>
                                <div id="var-map-rows" class="space-y-1.5"></div>
                                <div id="var-map-empty" class="text-[11px] text-ink-500 leading-[1.4]">
                                    No variables yet. Add a placeholder like @{{ 1 }} above (or press / in
                                    the body) to map it to an attribute.
                                </div>
                            </div>
                        </div>
                        <div
                            class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35] mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span>{{ __('Markdown:') }}</span>
                            <code class="mono font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">*bold*</code>
                            <code class="mono font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">_italic_</code>
                            <code class="mono font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">~strike~</code>
                            <code
                                class="mono font-mono px-1.5 py-0.5 bg-paper-50 rounded text-[10px]">```code```</code>
                        </div>
                    </div>

                    <!-- 05 Attachment -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200 {{ $isIg ? 'hidden' : '' }}">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Attachment') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional / image, video, or PDF') }}</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-3">
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="attach-type">{{ __('Attachment type') }}</label>
                                <select id="attach-type" name="attachment_type"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="none">{{ __('None') }}</option>
                                    <option value="image">{{ __('Image') }}</option>
                                    <option value="video">{{ __('Video') }}</option>
                                    <option value="document">{{ __('Document') }}</option>
                                    <option value="location">{{ __('Location') }}</option>
                                </select>
                            </div>
                            <div>
                                {{-- File picker (image / video / document) --}}
                                <div id="attach-file-wrap">
                                    <label
                                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Sample file') }}
                                        <span
                                            class="sec-meta font-mono text-[10px] text-ink-500">{{ \App\Http\Controllers\TemplatesController::mediaSizeHint() }}</span></label>
                                    <div class="file-tile flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid [&.has-file]:border-solid [&.has-file]:bg-wa-bubble"
                                        data-file-tile data-accept="image/*,video/*,application/pdf">
                                        <span
                                            class="file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"><svg
                                                viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                                stroke-width="1.6">
                                                <path d="M5 1h6l3 3v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h2" />
                                                <path d="M11 1v3h3" />
                                            </svg></span>
                                        <div class="file-meta flex-1 min-w-0">
                                            <div
                                                class="file-title text-[12px] font-semibold text-ink-900 whitespace-nowrap overflow-hidden text-ellipsis">
                                                {{ __('Choose sample file') }}</div>
                                            <div class="file-sub text-[10.5px] text-ink-500 font-mono">
                                                {{ __('required by Meta for media templates') }}</div>
                                        </div>
                                        <span
                                            class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0 [&.danger]:text-accent-coral [&.danger]:border-accent-coral">{{ __('Browse') }}</span>
                                    </div>
                                </div>
                                {{-- Location inputs (shown when Attachment type = Location) --}}
                                <div id="attach-loc-wrap" class="hidden">
                                    <label
                                        class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Coordinates') }}
                                        <span
                                            class="sec-meta font-mono text-[10px] text-ink-500">{{ __('sent as a map pin') }}</span></label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input name="latitude" type="text" value="{{ old('latitude') }}" inputmode="decimal"
                                            class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                            placeholder="{{ __('Latitude e.g. 19.0760') }}">
                                        <input name="longitude" type="text" value="{{ old('longitude') }}" inputmode="decimal"
                                            class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                            placeholder="{{ __('Longitude e.g. 72.8777') }}">
                                        <input name="location_name" type="text" value="{{ old('location_name') }}" maxlength="100"
                                            class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                            placeholder="{{ __('Place name (optional)') }}">
                                        <input name="location_address" type="text" value="{{ old('location_address') }}" maxlength="200"
                                            class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                            placeholder="{{ __('Address (optional)') }}">
                                    </div>
                                    <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                        {{ __('Latitude + longitude required. Sent as a WhatsApp location pin after the message.') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 06 Footer -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200 {{ $isIg ? 'hidden' : '' }}">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Footer') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional / max 60') }}</span>
                        </div>
                        <input id="tpl-footer" name="footer" type="text" value="{{ old('footer') }}"
                            maxlength="60"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Reply STOP to unsubscribe') }}">
                        <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                            {{ __('Plain text under the body. No variables.') }}</div>
                    </div>

                    <!-- 06b Code validity (Authentication / OTP templates only) -->
                    {{-- Hidden until the operator picks the Authentication category —
                         OTP expiry is meaningless for Marketing / Utility. Shown by
                         user-templates-create.js watching #tpl-category. --}}
                    <div id="otp-section" class="sec px-[18px] py-4 hairline-b border-b border-paper-200 hidden">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">OTP</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Code validity') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('authentication only') }}</span>
                        </div>
                        <input id="tpl-code-expiry" name="code_expiration_minutes" type="number" min="1" max="90"
                            value="{{ old('code_expiration_minutes') }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="10">
                        <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                            {{ __('For Authentication (OTP) templates only: how long the code stays valid — 1 to 90 minutes. Leave blank for 10. WhatsApp shows this to the user and uses it for one-tap autofill; your own backend still decides when a submitted code is actually expired.') }}</div>
                    </div>


                    <!-- 07 Buttons -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">06</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Buttons') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional / up to 3') }}</span>
                        </div>
                        <div class="seg inline-flex max-w-full overflow-x-auto p-[3px] rounded-full bg-paper-50 border border-paper-200 gap-0.5 mb-3"
                            id="btn-type">
                            <span
                                class="seg-btn shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium text-ink-600 cursor-pointer transition whitespace-nowrap hover:text-ink-900 [&.active]:bg-ink-900 [&.active]:text-paper-0 active"
                                data-bt="cta">{{ __('Call to action') }}</span>
                            <span
                                class="seg-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium text-ink-600 cursor-pointer transition whitespace-nowrap hover:text-ink-900 [&.active]:bg-ink-900 [&.active]:text-paper-0"
                                data-bt="reply">{{ __('Quick reply') }}</span>
                            <span
                                class="seg-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12px] font-medium text-ink-600 cursor-pointer transition whitespace-nowrap hover:text-ink-900 [&.active]:bg-ink-900 [&.active]:text-paper-0"
                                data-bt="mix">{{ __('Mix') }}</span>
                        </div>
                        <div id="btn-list" class="space-y-2">
                            {{-- Static seed row — operators usually want at least
 one CTA. Names match the controller's
 processButtons() input arrays. --}}
                            <div class="btn-row grid grid-cols-[140px_1fr_1fr_28px] gap-1.5 items-center"
                                data-kind="cta">
                                <select name="button_type[]"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-action">
                                    <option value="visit_website">{{ __('Visit website') }}</option>
                                    <option value="quick_reply">{{ __('Quick reply') }}</option>
                                    <option value="call_phone">{{ __('Call phone') }}</option>
                                    <option value="copy_code">{{ __('Copy code') }}</option>
                                </select>
                                <input type="text" name="button_text[]"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-text"
                                    maxlength="25" placeholder="{{ __('Button text') }}">
                                <input type="text" name="button_value[]"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-value"
                                    placeholder="https://...">
                                <span
                                    class="iconbtn w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral"
                                    onclick="removeBtn(this)" title="{{ __('Remove') }}"><svg viewBox="0 0 16 16"
                                        class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M4 4l8 8M12 4l-8 8" />
                                    </svg></span>
                            </div>
                        </div>
                        <button type="button" id="btn-add" onclick="addBtnRow()"
                            class="mt-2.5 inline-flex items-center gap-1.5 text-[12px] font-medium text-wa-deep hover:underline">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            <span data-add-label>{{ __('Add CTA button') }}</span>
                        </button>
                    </div>

                    {{-- The "Interactive (List menu / Poll)" section that lived
 here was removed — those controls aren't part of the
 WhatsApp Business template surface and were confusing
 operators. Buttons + Quick replies remain in section
 06 above and cover every interactive option that's
 actually supported. --}}
                </div>

                <!-- ===== CAROUSEL ===== -->
                <div id="carousel-sections" class="hidden">
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Intro message') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('shown above cards') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="car-header">{{ __('Header') }} <span
                                        class="sec-meta font-mono text-[10px] text-ink-500">{{ __('max 60') }}</span></label>
                                <input id="car-header" name="header" type="text" maxlength="60"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Spring picks for you') }}">
                            </div>
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="car-footer">{{ __('Footer') }} <span
                                        class="sec-meta font-mono text-[10px] text-ink-500">{{ __('max 60') }}</span></label>
                                <input id="car-footer" name="footer" type="text" maxlength="60"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="Free shipping over $40">
                            </div>
                        </div>
                        <label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="car-body">{{ __('Body') }} <span class="req text-accent-coral">*</span> <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('max 1024') }}</span></label>
                        <textarea id="car-body" name="template_body" rows="3" maxlength="1024" required
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-none"
                            placeholder="{{ __('Hand-picked styles, just for the season.') }}"></textarea>
                    </div>

                    <div class="sec px-[18px] py-4">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Cards') }}
                                <span class="sec-meta font-mono text-[10px] text-ink-500 ml-2"
                                    id="card-counter">0/10</span></span>
                            <button type="button" onclick="addCarouselCard()"
                                class="ml-auto inline-flex items-center gap-1.5 text-[12px] font-medium text-wa-deep hover:underline">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                Add card
                            </button>
                        </div>
                        <div id="car-cards" class="space-y-2">
                            <div
                                class="hairline border border-paper-200 rounded-lg border-dashed py-6 text-center text-ink-500 text-[12px] bg-paper-50">
                                {{ __('No cards yet / add up to 10 swipeable cards.') }}</div>
                        </div>
                    </div>
                </div>
                </div>{{-- /data-builder-sections --}}

            </div>

            <!-- ===== PREVIEW ===== -->
            <aside class="preview-col sticky top-[78px] self-start space-y-3">
                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <div
                            class="mono font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>
                            Live preview
                        </div>
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono"
                            id="lang-pill">{{ __('en_US') }}</span>
                    </div>
                    <div
                        class="phone-frame bg-ink-900 rounded-[24px] p-[7px] shadow-[0_12px_36px_-16px_rgba(11,31,28,0.4)] max-w-[300px] mx-auto">
                        <div
                            class="phone-screen {{ $isIg ? 'bg-white' : 'bg-wa-chat' }} rounded-[18px] min-h-[420px] flex flex-col overflow-hidden">
                            @if ($isIg)
                                {{-- Instagram DM header — gradient bar, back chevron, avatar,
                                     @username + "Active now". Mirrors the Instaflow preview. --}}
                                <div class="phone-bar text-white px-3 py-2 flex items-center gap-[7px] text-[11.5px]"
                                    style="background:linear-gradient(90deg,#833AB4,#E1306C,#F77737)">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M10 4l-4 4 4 4" />
                                    </svg>
                                    <div class="w-6 h-6 rounded-full bg-white/25 text-white flex items-center justify-center text-[9px] font-semibold">B</div>
                                    <div class="leading-tight">
                                        <div class="text-[11.5px] font-semibold">bloomly</div>
                                        <div class="text-[9px] opacity-80">{{ __('Active now') }}</div>
                                    </div>
                                </div>
                            @else
                                <div
                                    class="phone-bar bg-wa-deep text-paper-0 px-3 py-2 flex items-center gap-[7px] text-[11.5px]">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                                        <path d="M9 3l-4 5 4 5V9h5V7H9z" />
                                    </svg>
                                    <div
                                        class="w-6 h-6 rounded-full bg-wa-mint text-wa-deep flex items-center justify-center text-[9px] font-semibold">
                                        B</div>
                                    <div class="leading-tight">
                                        <div class="text-[11.5px] font-semibold">{{ __('Bloomly') }}</div>
                                        <div class="text-[9px] opacity-70">{{ __('online') }}</div>
                                    </div>
                                </div>
                            @endif
                            <div
                                class="phone-body flex-1 p-3 {{ $isIg ? 'bg-white' : 'bg-wa-chat [background-image:radial-gradient(rgba(7,94,84,0.06)_1px,transparent_1px)] bg-[length:14px_14px]' }}">
                                <div class="pp-bubble {{ $isIg ? 'bg-[#EFEFEF] rounded-[18px] rounded-tl-[4px]' : 'bg-paper-0 rounded-[7px] rounded-tl-[2px] shadow-[0_1px_1px_rgba(0,0,0,0.06)]' }} px-[9px] py-2 max-w-[88%] mb-[5px] text-[12px] leading-[1.4] break-words"
                                    id="pp-card">
                                    <div class="pp-attachment bg-[#DFF1ED] rounded-[5px] h-20 mb-[5px] flex items-center justify-center text-wa-deep text-[10.5px] font-mono hidden"
                                        id="pp-attach"></div>
                                    <div class="pp-header font-semibold text-[12px] mb-[3px] hidden" id="pp-header">
                                    </div>
                                    <div id="pp-body">{{ __('your message will appear here...') }}</div>
                                    <div class="pp-footer text-[10.5px] text-ink-500 mt-[5px] hidden" id="pp-footer">
                                    </div>
                                    <div class="pp-time text-[9px] text-ink-500 text-right mt-1 font-mono"
                                        id="pp-time">14:08</div>
                                </div>
                                <div class="pp-btns max-w-[88%] flex flex-col gap-[3px] mt-[3px] hidden"
                                    id="pp-btn-list"></div>
                                {{-- Location pin preview (separate bubble, like the real send) --}}
                                <div class="pp-location max-w-[88%] mt-[3px] hidden" id="pp-location">
                                    <div class="bg-paper-0 rounded-[7px] px-[7px] py-[6px] shadow-[0_1px_1px_rgba(0,0,0,0.06)]">
                                        <div class="rounded-[5px] h-16 mb-[5px] flex items-center justify-center bg-[#cfe0db] text-wa-deep">
                                            <svg viewBox="0 0 16 16" class="w-5 h-5" fill="currentColor"><path d="M8 1a4.5 4.5 0 0 0-4.5 4.5c0 3.2 4.5 9 4.5 9s4.5-5.8 4.5-9A4.5 4.5 0 0 0 8 1zm0 6.2a1.7 1.7 0 1 1 0-3.4 1.7 1.7 0 0 1 0 3.4z"/></svg>
                                        </div>
                                        <div class="text-[11px] font-semibold" id="pp-loc-name">{{ __('Location') }}</div>
                                        <div class="text-[10px] text-ink-500 font-mono" id="pp-loc-coords">0.00000, 0.00000</div>
                                    </div>
                                </div>
                                <div id="pp-carousel"
                                    class="hidden flex overflow-x-auto gap-2 snap-x snap-mandatory pb-2 mt-[3px] mx-[-4px] px-1 [&::-webkit-scrollbar]:hidden">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-3 bg-wa-bubble/40">
                    <div class="text-[11px] text-ink-700 leading-snug"><b>Tip:</b> Templates with one variable + one
                        CTA usually approve in under 24 h.</div>
                </div>
            </aside>
        </form>
    </section>

    {{-- Build-with-AI modal — vanilla Tailwind, same overlay/panel
 pattern as /templates#type-modal so the visual language stays
 consistent. Opened by #open-ai-modal in the sticky bar above.
 POST submission lives in user-templates-create.js. --}}
    <div id="ai-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center px-4"
        style="background-color:rgba(11,31,28,0.45);">
        <div
            class="ai-modal-panel bg-paper-0 rounded-2xl shadow-soft border border-paper-200 w-full max-w-[640px] max-h-[92vh] overflow-hidden flex flex-col">
            <div class="px-5 py-4 hairline-b border-b border-paper-200 flex items-start gap-3">
                <span
                    class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep inline-flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Templates / AI') }}</div>
                    <div class="serif font-serif text-[18px] leading-tight">{{ __('Build with') }} <span
                            class="italic text-wa-deep">AI</span></div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('Draft a high-converting WhatsApp template from a short brief.') }}</div>
                </div>
                <button type="button" id="ai-modal-close"
                    class="w-8 h-8 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Close') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <div class="px-5 py-4 overflow-y-auto flex-1">
                {{-- Empty-state when admin hasn't enabled any provider. The
 form below stays hidden in that case so the operator
 can't submit a request that 422s. --}}
                <div id="ai-empty"
                    class="hidden rounded-xl border border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-700">
                    No AI providers are enabled. Ask an admin to add a key in
                    <span class="mono font-mono">/admin/api-keys</span> before this can run.
                </div>

                <div id="ai-form" class="space-y-4">
                    <div>
                        <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-model">{{ __('AI model') }} <span class="req text-accent-coral">*</span></label>
                        <select id="ai-model"
                            class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('Loading models…') }}</option>
                        </select>
                        <div class="hint text-[10.5px] text-ink-500 mt-1">{{ __('Models come from') }} <span
                                class="mono font-mono">/admin/api-keys</span> — admin controls what's available.</div>
                    </div>

                    <div>
                        <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-business">{{ __('Business name') }} <span
                                class="req text-accent-coral">*</span></label>
                        <input id="ai-business" type="text" maxlength="120"
                            class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Bloomly Florals') }}">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-industry">{{ __('Industry') }}</label>
                            <select id="ai-industry"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select industry') }}</option>
                                <option>{{ __('Retail / E-commerce') }}</option>
                                <option>{{ __('Food & Beverage') }}</option>
                                <option>{{ __('Healthcare') }}</option>
                                <option>{{ __('Education') }}</option>
                                <option>{{ __('Travel & Hospitality') }}</option>
                                <option>{{ __('Finance & Banking') }}</option>
                                <option>{{ __('Real Estate') }}</option>
                                <option>{{ __('Beauty & Wellness') }}</option>
                                <option>{{ __('Logistics & Delivery') }}</option>
                                <option>{{ __('Professional Services') }}</option>
                                <option>{{ __('Non-profit') }}</option>
                                <option>{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-occasion">{{ __('Occasion') }}</label>
                            <select id="ai-occasion"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select occasion') }}</option>
                                <option>{{ __('Product launch') }}</option>
                                <option>{{ __('Sale / discount') }}</option>
                                <option>{{ __('Order confirmation') }}</option>
                                <option>{{ __('Shipping update') }}</option>
                                <option>{{ __('Appointment reminder') }}</option>
                                <option>{{ __('Welcome / onboarding') }}</option>
                                <option>{{ __('Feedback request') }}</option>
                                <option>{{ __('Abandoned cart') }}</option>
                                <option>{{ __('Re-engagement') }}</option>
                                <option>{{ __('Event invitation') }}</option>
                                <option>{{ __('Festival greeting') }}</option>
                                <option>{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-purpose">{{ __('Purpose') }}</label>
                            <select id="ai-purpose"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select purpose') }}</option>
                                <option>{{ __('Inform') }}</option>
                                <option>{{ __('Promote') }}</option>
                                <option>{{ __('Confirm') }}</option>
                                <option>{{ __('Remind') }}</option>
                                <option>{{ __('Collect feedback') }}</option>
                                <option>{{ __('Re-engage') }}</option>
                                <option>{{ __('Drive a purchase') }}</option>
                                <option>{{ __('Drive a booking') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-action">{{ __('Primary action') }}</label>
                            <select id="ai-action"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select primary action') }}</option>
                                <option>{{ __('Visit website') }}</option>
                                <option>{{ __('Call us') }}</option>
                                <option>{{ __('Book an appointment') }}</option>
                                <option>{{ __('Place an order') }}</option>
                                <option>{{ __('View catalog') }}</option>
                                <option>{{ __('Reply with a code') }}</option>
                                <option>{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-tone">{{ __('Tone') }}</label>
                            <select id="ai-tone"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select tone') }}</option>
                                <option>{{ __('Friendly') }}</option>
                                <option>{{ __('Professional') }}</option>
                                <option>{{ __('Concise') }}</option>
                                <option>{{ __('Empathetic') }}</option>
                                <option>{{ __('Excited') }}</option>
                                <option>{{ __('Formal') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Language') }}</label>
                            <div
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-paper-50 text-[12.5px] text-ink-700 leading-[1.4]">
                                <span id="ai-language-label">{{ __('en_US') }}</span>
                                <span class="text-[10.5px] text-ink-500 ml-1">(from the page)</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] flex items-center justify-between gap-2"
                            for="ai-prompt">
                            Custom prompt
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional / max 2000') }}</span>
                        </label>
                        <textarea id="ai-prompt" rows="4" maxlength="2000"
                            class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-y"
                            placeholder="{{ __('Anything specific you want included — variables, links, must-have phrasing, what to avoid…') }}"></textarea>
                    </div>

                    <div id="ai-error"
                        class="hidden rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[11.5px] text-[#A1431F]">
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 bg-paper-0 hairline-t border-t border-paper-200 flex items-center justify-end gap-2">
                <button type="button" id="ai-modal-cancel"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</button>
                <button type="button" id="ai-generate"
                    class="px-5 py-2 rounded-full text-paper-0 text-[12px] font-semibold flex items-center gap-2 shadow-[0_1px_2px_rgba(11,31,28,0.12)] disabled:opacity-60 disabled:cursor-not-allowed"
                    style="background-image:linear-gradient(135deg,#7B57C7,#3D7CD3);">
                    <svg id="ai-generate-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                    <svg id="ai-generate-spin" viewBox="0 0 16 16" class="w-3.5 h-3.5 hidden animate-spin"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M8 1.5a6.5 6.5 0 1 1-6.5 6.5" stroke-linecap="round" />
                    </svg>
                    <span id="ai-generate-label">{{ __('Generate Template') }}</span>
                </button>
            </div>
        </div>
    </div>

</x-layouts.user>
