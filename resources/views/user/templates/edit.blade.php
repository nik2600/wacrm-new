@php
    $tplName = $template->template_name ?? '';
    $tplBody = $template->template_body ?? '';
    $tplHeader = $template->header ?? '';
    $tplFooter = $template->footer ?? '';
    $tplCategory = $template->meta_category ?? 'utility';
    $tplLang = $template->language ?? 'en_US';
    $tplType = $template->template_type ?? 'standard';
    $tplAttach = $template->attachment_type ?? 'none';
    $tplStatus = $template->status ?? 'pending';
    $tplButtons = is_array($template->buttons ?? null) ? $template->buttons : [];
    $tplLoc = is_array($template->header_location ?? null) ? $template->header_location : [];
    // If the template carries a location, the Attachment dropdown defaults to "Location".
    $tplAttachEff = !empty($tplLoc) ? 'location' : ($tplAttach ?: 'none');
    $tplCarousel = is_array($template->carousel_data ?? null) ? $template->carousel_data : [];
    $tplFile = $template->attachment_file ?? null;
    $tplFileUrl = $tplFile ? media_url($tplFile) : null;

    // Rebuild the picker's { slot: attribute_key } JSON from the saved
// variable_map's stored [{num,key}] shape so the mapping panel +
    // hidden `variable_map_json` field load pre-populated. Slots that
    // were left at the literal number (unmapped) come back as-is.
    $savedVmap = is_array($template->variable_map ?? null) ? $template->variable_map : [];
    $tplVarMapJson = [];
    // Per-section {slot => key} so we can both seed the picker's hidden
// field AND rewrite stored positional {{ N }} back to the NAMED token
// {{ key }} for display — so the editor shows meaning, not a number.
$sectionSlotKey = ['header' => [], 'body' => []];
foreach (['header', 'body'] as $vmapSection) {
    foreach ($savedVmap[$vmapSection] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $num = (string) ($entry['num'] ?? '');
        $key = (string) ($entry['key'] ?? '');
        if ($num === '' || $key === '') {
            continue;
        }
        $tplVarMapJson[$num] = $key;
        $sectionSlotKey[$vmapSection][$num] = $key;
    }
}
$tplVarMapJson = json_encode((object) $tplVarMapJson, JSON_UNESCAPED_SLASHES);

// Display-only: rewrite each {{ N }} in the stored body/header to the
// mapped attribute key {{ key }}. Storage stays positional — the
// controller re-normalizes named → positional on save. A slot with no
// mapped key (or that maps to its own number) is left as {{ N }}.
$namedFor = function (?string $text, array $slotKey): string {
    $text = (string) $text;
    if ($text === '') {
        return $text;
    }
    return preg_replace_callback(
        '/\{\{\s*(\d+)\s*\}\}/u',
        function ($m) use ($slotKey) {
            $slot = $m[1];
            $key = $slotKey[$slot] ?? null;
            return $key !== null && $key !== '' && !ctype_digit($key) ? '{{ ' . $key . ' }}' : $m[0];
        },
        $text,
    );
};
$tplBodyDisplay = $namedFor($tplBody, $sectionSlotKey['body']);
$tplHeaderDisplay = $namedFor($tplHeader, $sectionSlotKey['header']);
@endphp
<x-layouts.user :title="__('Edit Template / ') . $tplName" nav-key="templates" page="user-templates-edit">

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
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Templates / Edit /
                        tpl_{{ str_pad($template->id, 4, '0', STR_PAD_LEFT) }}</div>
                    <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] leading-tight truncate">
                        {{ __('Edit') }} <span class="italic text-wa-deep">{{ $tplName }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @php
                    $statusBadge = match ($tplStatus) {
                        'approved' => ['bg-wa-green/15 text-wa-deep border-wa-green/40', 'Approved'],
                        'rejected' => ['bg-accent-coral/10 text-accent-coral border-accent-coral/40', 'Rejected'],
                        default => ['bg-amber-100 text-amber-800 border-amber-300', 'Pending'],
                    };
                @endphp
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                <button type="button" data-template-delete="{{ $template->id }}" data-name="{{ $tplName }}"
                    class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral text-accent-coral text-[12px] font-medium">Delete</button>
                <button type="submit" form="templateForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                    </svg>
                    Save changes
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
        <form id="templateForm" method="POST"
            action="{{ isset($template) ? route('user.templates.update', $template->id) : '#' }}"
            data-channel="{{ $template->channel ?? 'waba' }}"
            enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">
            @csrf
            @method('PUT')
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
                                value="{{ old('template_name', $template->template_name ?? '') }}" maxlength="60"
                                required
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('a-z, 0-9, _ only / max 60.') }}</div>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="tpl-category">{{ __('Category') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input type="hidden" name="category"
                                value="{{ old('category', $template->category ?? 'utility') }}">
                            <select id="tpl-category" name="meta_category" required
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="marketing" @selected(old('meta_category', $tplCategory) === 'marketing')>{{ __('Marketing') }}</option>
                                <option value="utility" @selected(old('meta_category', $tplCategory) === 'utility')>{{ __('Utility') }}</option>
                                <option value="authentication" @selected(old('meta_category', $tplCategory) === 'authentication')>{{ __('Authentication') }}
                                </option>
                            </select>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('Determines Meta review path.') }}</div>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="tpl-language">{{ __('Language') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <select id="tpl-language" name="language" required
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                @php
                                    $tplLangs = wa_template_languages();
                                    // If this template was imported/created with a code that isn't in
                                    // the curated list (legacy ar_AR etc.), keep it as a selectable
                                    // option so the edit form never silently blanks the language.
                                    if (!array_key_exists($tplLang, $tplLangs)) {
                                        $tplLangs = [$tplLang => $tplLang] + $tplLangs;
                                    }
                                @endphp
                                @foreach ($tplLangs as $code => $label)
                                    <option value="{{ $code }}" @selected(old('language', $tplLang) === $code)>
                                        {{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('One locale per template.') }}</div>
                        </div>
                    </div>
                    {{-- Twilio ContentSid: per-template pointer to a Twilio Content
 Builder template. Required for Twilio MARKETING / UTILITY /
 AUTHENTICATION sends to remain compliant. Leave blank for
 Baileys-only / WABA-only workspaces.
 Only shown when THIS template's saved channel is Twilio — mirrors the
 create form, which only reveals the SID field for the Twilio channel.
 An Unofficial-API or WABA template never shows it, even in a workspace
 that also has Twilio enabled. --}}
                    @if (($template->channel ?? '') === 'twilio')
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-3 mt-3">
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="tpl-twilio-sid">
                                    {{ __('Twilio Content SID') }} <span
                                        class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
                                </label>
                                <input id="tpl-twilio-sid" type="text" name="twilio_content_sid"
                                    value="{{ old('twilio_content_sid', $template->twilio_content_sid ?? '') }}"
                                    pattern="HX[0-9a-fA-F]{32}" maxlength="34"
                                    placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 font-mono transition leading-[1.4] placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                    {{ __('Paste the HX… ContentSid from Twilio Content Builder. Required for compliant Twilio sends of MARKETING / UTILITY / AUTHENTICATION templates.') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Format is fixed at create-time and not editable; we just
 keep the hidden input so the controller still receives
 the original template_type on submit, and an empty #type-seg
 so the JS that toggles standard/carousel sections still
 has its expected hooks (it only acts on click). --}}
                <input type="hidden" name="template_type" id="template-type-input"
                    value="{{ old('template_type', $tplType) }}">
                <div id="type-seg" class="hidden">
                    <span class="seg-btn {{ $tplType !== 'carousel' ? 'active' : '' }}" data-type="standard"></span>
                    <span class="seg-btn {{ $tplType === 'carousel' ? 'active' : '' }}" data-type="carousel"></span>
                </div>

                <!-- ===== STANDARD ===== -->
                <div id="standard-sections" class="{{ $tplType === 'carousel' ? 'hidden' : '' }}">

                    <!-- 03 Header -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
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
                                <input id="tpl-header" name="header" type="text" maxlength="60"
                                    value="{{ old('header', $tplHeaderDisplay) }}"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </div>
                        </div>
                    </div>

                    <!-- 04 Body -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
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
                                    {{-- Named-only authoring: this pill opens the `/` attribute picker on
 the body so the operator inserts a NAMED token ({{name}}) — the
 server normalizes it to positional {{1}} on save. (Replaces the
 old {{1}}–{{4}} numbered chips.) --}}
                                    <span
                                        class="ed-pill inline-flex items-center gap-1 px-[7px] py-[3px] rounded-[5px] bg-wa-bubble text-wa-deep text-[10.5px] font-medium cursor-pointer transition hover:bg-wa-deep hover:text-paper-0"
                                        title="{{ __('Insert a variable') }}"
                                        onclick="(function(){var t=document.getElementById('tpl-body');if(!t)return;t.focus();var s=t.selectionStart,e=t.selectionEnd,v=t.value,b=s>0?v[s-1]:'',ins=(b&&!/\s/.test(b)?' /':'/');t.value=v.slice(0,s)+ins+v.slice(e);var c=s+ins.length;t.setSelectionRange(c,c);t.dispatchEvent(new Event('input',{bubbles:true}));})()"><svg
                                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path d="M8 3.5v9M3.5 8h9" />
                                        </svg>{{ __('Variable') }}</span>
                                    <span class="ml-auto sec-meta font-mono text-[10px] text-ink-500 pr-1"><span
                                            id="char-count2">0</span>/1024</span>
                                </div>
                                <textarea id="tpl-body" name="template_body" data-attr-input maxlength="1024" rows="5"
                                    class="ed-ta w-full border-0 px-[11px] py-[9px] text-[12.5px] text-ink-900 resize-y min-h-[110px] leading-[1.5] font-sans outline-none placeholder:text-[#9CA8A4]"
                                    placeholder="Type your message. Press / to insert a variable. Meta requires positional placeholders like @{{ 1 }} @{{ 2 }}.">{{ old('template_body', $tplBodyDisplay) }}</textarea>
                            </div>

                            {{-- Variable mapping — pre-populated from the saved template's
 variable_map. Records which attribute each {{N}} slot
 resolves to at send time. Hidden `variable_map_json` is
 what the controller turns back into `variable_map`. --}}
                            <input type="hidden" name="variable_map_json" data-attr-map
                                value="{{ old('variable_map_json', $tplVarMapJson) }}">
                            <div id="var-map-panel"
                                class="mt-2.5 rounded-lg border border-paper-200 bg-paper-50/60 px-3 py-2.5">
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
                                <div id="var-map-empty" class="text-[11px] text-ink-500 leading-[1.4]">No variables
                                    yet. Add a placeholder like {{ 1 }} above (or press / in the body) to
                                    map it to an attribute.</div>
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
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
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
                                    <option value="none" @selected(old('attachment_type', $tplAttachEff) === 'none')>{{ __('None') }}</option>
                                    <option value="image" @selected(old('attachment_type', $tplAttachEff) === 'image')>{{ __('Image') }}</option>
                                    <option value="video" @selected(old('attachment_type', $tplAttachEff) === 'video')>{{ __('Video') }}</option>
                                    <option value="document" @selected(old('attachment_type', $tplAttachEff) === 'document')>{{ __('Document') }}
                                    </option>
                                    <option value="location" @selected(old('attachment_type', $tplAttachEff) === 'location')>{{ __('Location') }}</option>
                                </select>
                            </div>
                            <div>
                              <div id="attach-file-wrap">
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Sample file') }}
                                    <span
                                        class="sec-meta font-mono text-[10px] text-ink-500">{{ \App\Http\Controllers\TemplatesController::mediaSizeHint() }}</span></label>
                                <input type="hidden" name="existing_attachment_file" value="{{ $tplFile ?? '' }}">
                                <div class="file-tile flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid [&.has-file]:border-solid [&.has-file]:bg-wa-bubble {{ $tplFile ? 'has-file' : '' }}"
                                    data-file-tile data-accept="image/*,video/*,application/pdf"
                                    @if ($tplFileUrl) style="background-image:url('{{ $tplFileUrl }}'); background-size:cover; background-position:center;" @endif>
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
                                            {{ $tplFile ? basename($tplFile) : 'Choose sample file' }}</div>
                                        <div class="file-sub text-[10.5px] text-ink-500 font-mono">
                                            {{ $tplFile ? 'tap to replace' : 'required by Meta for media templates' }}
                                        </div>
                                    </div>
                                    <span
                                        class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0 [&.danger]:text-accent-coral [&.danger]:border-accent-coral">{{ $tplFile ? 'Replace' : 'Browse' }}</span>
                                </div>
                              </div>
                              {{-- Location inputs (shown when Attachment type = Location) --}}
                              <div id="attach-loc-wrap" class="hidden">
                                  <label
                                      class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Coordinates') }}
                                      <span
                                          class="sec-meta font-mono text-[10px] text-ink-500">{{ __('sent as a map pin') }}</span></label>
                                  <div class="grid grid-cols-2 gap-2">
                                      <input name="latitude" type="text" value="{{ old('latitude', $tplLoc['latitude'] ?? '') }}" inputmode="decimal"
                                          class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                          placeholder="{{ __('Latitude e.g. 19.0760') }}">
                                      <input name="longitude" type="text" value="{{ old('longitude', $tplLoc['longitude'] ?? '') }}" inputmode="decimal"
                                          class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                          placeholder="{{ __('Longitude e.g. 72.8777') }}">
                                      <input name="location_name" type="text" value="{{ old('location_name', $tplLoc['name'] ?? '') }}" maxlength="100"
                                          class="ctrl px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                          placeholder="{{ __('Place name (optional)') }}">
                                      <input name="location_address" type="text" value="{{ old('location_address', $tplLoc['address'] ?? '') }}" maxlength="200"
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
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">06</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Footer') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('optional / max 60') }}</span>
                        </div>
                        <input id="tpl-footer" name="footer" type="text" maxlength="60"
                            value="{{ old('footer', $tplFooter) }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                            {{ __('Plain text under the body. No variables.') }}</div>
                    </div>

                    <!-- 06b Code validity (Authentication / OTP templates only) -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">OTP</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Code validity') }}</span>
                            <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('authentication only') }}</span>
                        </div>
                        <input id="tpl-code-expiry" name="code_expiration_minutes" type="number" min="1" max="90"
                            value="{{ old('code_expiration_minutes', $template->code_expiration_minutes) }}"
                            class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="10">
                        <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                            {{ __('For Authentication (OTP) templates only: how long the code stays valid — 1 to 90 minutes. Leave blank for 10.') }}</div>
                    </div>


                    <!-- 07 Buttons -->
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">07</span>
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
                            @foreach ($tplButtons as $btn)
                                @php
                                    $btnType = $btn['type'] ?? 'visit_website';
                                    $btnText = $btn['text'] ?? '';
                                    $btnValue = $btn['value'] ?? '';
                                @endphp
                                <div class="btn-row grid grid-cols-[140px_1fr_1fr_28px] gap-1.5 items-center"
                                    data-kind="cta">
                                    <select name="button_type[]"
                                        class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-action">
                                        <option value="visit_website" @selected($btnType === 'visit_website')>
                                            {{ __('Visit website') }}</option>
                                        <option value="call_phone" @selected($btnType === 'call_phone')>{{ __('Call phone') }}
                                        </option>
                                        <option value="copy_code" @selected($btnType === 'copy_code')>{{ __('Copy code') }}
                                        </option>
                                        <option value="quick_reply" @selected($btnType === 'quick_reply')>
                                            {{ __('Quick reply') }}</option>
                                    </select>
                                    <input type="text" name="button_text[]"
                                        class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-text"
                                        maxlength="25" placeholder="{{ __('Button text') }}"
                                        value="{{ $btnText }}">
                                    <input type="text" name="button_value[]"
                                        class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-value"
                                        placeholder="https://..." value="{{ $btnValue }}">
                                    <span
                                        class="iconbtn w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral"
                                        onclick="removeBtn(this)" title="{{ __('Remove') }}"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.8">
                                            <path d="M4 4l8 8M12 4l-8 8" />
                                        </svg></span>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="btn-add" onclick="addBtnRow()"
                            class="mt-2.5 inline-flex items-center gap-1.5 text-[12px] font-medium text-wa-deep hover:underline">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            <span data-add-label>{{ __('Add button') }}</span>
                        </button>
                    </div>

                    {{-- The "Interactive (List menu / Poll)" section that lived
 here was removed — those controls aren't part of the
 WhatsApp Business template surface. Buttons + Quick
 replies in section 06 above cover every interactive
 option that actually goes to WhatsApp. --}}
                </div>

                <!-- ===== CAROUSEL ===== -->
                @php
                    $carHeader = $tplCarousel['header'] ?? '';
                    $carFooter = $tplCarousel['footer'] ?? '';
                    $carBody = $tplCarousel['body'] ?? '';
                    $carCards = $tplCarousel['cards'] ?? [];
                @endphp
                <div id="carousel-sections" class="{{ $tplType === 'carousel' ? '' : 'hidden' }}">
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
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
                                    value="{{ old('header', $carHeader) }}"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Spring picks for you') }}">
                            </div>
                            <div>
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="car-footer">{{ __('Footer') }} <span
                                        class="sec-meta font-mono text-[10px] text-ink-500">{{ __('max 60') }}</span></label>
                                <input id="car-footer" name="footer" type="text" maxlength="60"
                                    value="{{ old('footer', $carFooter) }}"
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
                            placeholder="{{ __('Hand-picked styles, just for the season.') }}">{{ old('template_body', $carBody) }}</textarea>
                    </div>

                    <div class="sec px-[18px] py-4">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
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
                            class="phone-screen bg-wa-chat rounded-[18px] min-h-[420px] flex flex-col overflow-hidden">
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
                            <div
                                class="phone-body flex-1 p-3 bg-wa-chat [background-image:radial-gradient(rgba(7,94,84,0.06)_1px,transparent_1px)] bg-[length:14px_14px]">
                                <div class="pp-bubble bg-paper-0 rounded-[7px] rounded-tl-[2px] px-[9px] py-2 max-w-[88%] shadow-[0_1px_1px_rgba(0,0,0,0.06)] mb-[5px] text-[12px] leading-[1.4] break-words"
                                    id="pp-card">
                                    {{-- Media preview — the JS renders an <img>/<video>/doc element here
                                         based on the attachment type (a CSS background can't paint a video). --}}
                                    <div class="pp-attachment bg-[#DFF1ED] rounded-[5px] h-20 mb-[5px] flex items-center justify-center text-wa-deep text-[10.5px] font-mono hidden"
                                        id="pp-attach"
                                        data-existing-url="{{ $tplFileUrl ?? '' }}"
                                        data-existing-type="{{ in_array($tplAttach, ['image', 'video', 'document'], true) ? $tplAttach : '' }}">
                                    </div>
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

    <script>
        // Hydrate the live preview pane from the saved template data so
        // the user can see the existing message + image straight away,
        // before the dynamic input listeners take over.
        (function() {
            @php
                $hydrate = [
                    'header' => $tplHeader,
                    'body' => $tplBody,
                    'footer' => $tplFooter,
                    'language' => $tplLang,
                    'attachment' => $tplFileUrl,
                    'buttons' => $tplButtons,
                ];
            @endphp
            var tpl = {!! json_encode($hydrate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!};

            var ppHeader = document.getElementById('pp-header');
            var ppBody = document.getElementById('pp-body');
            var ppFooter = document.getElementById('pp-footer');
            var ppLang = document.getElementById('lang-pill');
            var ppBtns = document.getElementById('pp-btn-list');

            if (ppHeader && tpl.header) {
                ppHeader.textContent = tpl.header;
                ppHeader.classList.remove('hidden');
            }
            if (ppBody && tpl.body) {
                ppBody.textContent = tpl.body;
            }
            if (ppFooter && tpl.footer) {
                ppFooter.textContent = tpl.footer;
                ppFooter.classList.remove('hidden');
            }
            if (ppLang && tpl.language) {
                ppLang.textContent = tpl.language;
            }

            if (ppBtns && Array.isArray(tpl.buttons) && tpl.buttons.length) {
                ppBtns.innerHTML = '';
                tpl.buttons.forEach(function(b) {
                    var row = document.createElement('div');
                    row.className =
                        'bg-paper-0 rounded-[5px] px-2 py-[5px] text-[11.5px] text-wa-deep font-medium text-center';
                    row.textContent = b.text || '(no label)';
                    ppBtns.appendChild(row);
                });
                ppBtns.classList.remove('hidden');
            }
        })();
    </script>

    {{--
        Delete button wiring — the [data-template-delete] handler lives in
        user-templates-index.js, which is NOT loaded on this edit page
        (page="user-templates-edit"). So the Delete button here did nothing
        when clicked. This inline handler does the DELETE + redirects back to
        the list. Mirrors the index page's deleteTemplate() but navigates to
        /templates on success (there's no in-place grid to refresh here).
    --}}
    <script>
    (function () {
        const btn = document.querySelector('[data-template-delete]');
        if (!btn) return;

        const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const flash = (kind, msg) => {
            if (window.WaToaster && typeof window.WaToaster[kind] === 'function') window.WaToaster[kind](msg);
            else if (kind === 'error') alert(msg);
        };

        btn.addEventListener('click', () => {
            const id   = btn.dataset.templateDelete;
            const name = btn.dataset.name || 'this template';
            const run  = async () => {
                btn.disabled = true;
                try {
                    const res = await fetch('{{ url('/templates') }}/' + encodeURIComponent(id), {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!res.ok) {
                        let msg = 'Delete failed (HTTP ' + res.status + ').';
                        try { const d = await res.json(); msg = d.message || d.error || msg; } catch (_) {}
                        flash('error', msg);
                        btn.disabled = false;
                        return;
                    }
                    flash('success', 'Template deleted.');
                    window.location.href = '{{ url('/templates') }}';
                } catch (e) {
                    flash('error', 'Delete failed: ' + (e?.message || 'network error'));
                    btn.disabled = false;
                }
            };

            const message = 'Delete "' + name + '"? This can\'t be undone.';
            if (typeof window.confirmDialog === 'function') {
                window.confirmDialog({
                    title: 'Delete template?',
                    message,
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    tone: 'danger',
                    onConfirm: run,
                });
            } else if (window.confirm(message)) {
                run();
            }
        });
    })();
    </script>

</x-layouts.user>
