<x-layouts.user :title="__('Create Meta Ads Campaign')" nav-key="metaads" page="user-campaigns-create">

    <div class="hairline-b border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/meta-ads') }}"
                    class="w-8 h-8 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center shrink-0"
                    title="{{ __('Back to Meta Ads') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Meta Ads / New') }}</div>
                    <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] leading-tight truncate">
                        {{ __('Create Meta Ads') }} <span class="italic text-wa-deep">{{ __('campaign') }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
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
                <button type="submit" form="campaignForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
                    </svg>
                    Publish campaign
                </button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12px] text-[#A1431F]">
                <div class="font-semibold mb-1">{{ __('Could not save the campaign:') }}</div>
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="campaignForm" method="POST" action="{{ route('user.meta-ads.store') }}" enctype="multipart/form-data"
            class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">
            @csrf
            <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card">
                <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                    <div class="sec-head flex items-center gap-2.5 mb-3">
                        <span
                            class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span
                            class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Campaign details') }}</span>
                        <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="campaign-name">{{ __('Campaign name') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="campaign-name" name="name" type="text" value="{{ old('name') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Meta CTWA - Summer sale') }}" required>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="objective">{{ __('Objective') }} <span
                                    class="req text-accent-coral">*</span></label>
                            @php $goal = old('optimization_goal', 'MESSAGES'); @endphp
                            <select id="objective" name="optimization_goal"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                @foreach (['MESSAGES' => 'Messages — WhatsApp inbox', 'LINK_CLICKS' => 'Link Clicks — Website traffic', 'CONVERSIONS' => 'Conversions — Sales or signup', 'LEAD_GENERATION' => 'Lead Generation — Meta lead form', 'REACH' => 'Reach — Maximum people', 'BRAND_AWARENESS' => 'Brand Awareness', 'VIDEO_VIEWS' => 'Video Views'] as $v => $lbl)
                                    <option value="{{ $v }}" @selected($goal === $v)>
                                        {{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="adset-name">{{ __('Ad set name') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="adset-name" name="adset_name" type="text" value="{{ old('adset_name') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('IN Adults 18-45') }}" required>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="budget">{{ __('Daily budget') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="budget" name="daily_budget" type="number" min="1" step="0.01"
                                value="{{ old('daily_budget') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="25.00" required>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">Minimum recommended budget
                                is $1/day.</div>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="status-mode">{{ __('Status') }}</label>
                            <select id="status-mode" name="status"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="ACTIVE">{{ __('Active after publish') }}</option>
                                <option value="PAUSED">{{ __('Publish paused') }}</option>
                                <option value="DRAFT">{{ __('Save as draft') }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label
                                class="hairline border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 w-full">
                                <span>
                                    <span
                                        class="block text-[12.5px] font-semibold">{{ __('Click to WhatsApp') }}</span>
                                    <span
                                        class="block text-[10.5px] text-ink-500">{{ __('Send ad clicks to WhatsApp') }}</span>
                                </span>
                                <span class="switch relative inline-block w-[34px] h-5 shrink-0"><input
                                        class="peer opacity-0 w-0 h-0" type="checkbox" id="ctwa-enabled"
                                        name="ctwa_enabled" value="1" @checked(old('ctwa_enabled', true))><span
                                        class="switch-slider absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                            </label>
                        </div>
                    </div>

                    {{-- Ad type + placement (Instagram Ads). ad_type drives the
                         destination/creative; placement drives where it shows. --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ad-type">{{ __('Ad type') }}</label>
                            @php $adType = old('ad_type', 'ctwa'); @endphp
                            <select id="ad-type" name="ad_type"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="ctwa" @selected($adType === 'ctwa')>{{ __('Click to WhatsApp') }}</option>
                                <option value="link" @selected($adType === 'link')>{{ __('Instagram / Facebook link ad') }}</option>
                                <option value="ig_direct" @selected($adType === 'ig_direct')>{{ __('Click to Instagram DM') }}</option>
                            </select>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('A WhatsApp chat, a website link, or an Instagram Direct message.') }}</div>
                        </div>
                        <div class="lg:col-span-2">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Placement') }}</label>
                            {{-- Sentinel: tells the controller the placement UI was present,
                                 so unchecking every box means "automatic" (not "unchanged"). --}}
                            <input type="hidden" name="placement_edited" value="1">
                            @php $pp = (array) old('publisher_platforms', []); $ip = (array) old('instagram_positions', []); @endphp
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="hairline border border-paper-200 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 cursor-pointer hover:bg-paper-50 text-[12px]">
                                    <input type="checkbox" name="publisher_platforms[]" value="facebook" class="accent-wa-deep" @checked(in_array('facebook', $pp, true))> {{ __('Facebook') }}
                                </label>
                                <label class="hairline border border-paper-200 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 cursor-pointer hover:bg-paper-50 text-[12px]">
                                    <input type="checkbox" name="publisher_platforms[]" value="instagram" class="accent-wa-deep" @checked(in_array('instagram', $pp, true))> {{ __('Instagram') }}
                                </label>
                                <span class="text-ink-300">·</span>
                                <label class="hairline border border-paper-200 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 cursor-pointer hover:bg-paper-50 text-[12px]">
                                    <input type="checkbox" name="instagram_positions[]" value="stream" class="accent-wa-deep" @checked(in_array('stream', $ip, true))> {{ __('IG Feed') }}
                                </label>
                                <label class="hairline border border-paper-200 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 cursor-pointer hover:bg-paper-50 text-[12px]">
                                    <input type="checkbox" name="instagram_positions[]" value="story" class="accent-wa-deep" @checked(in_array('story', $ip, true))> {{ __('IG Stories') }}
                                </label>
                                <label class="hairline border border-paper-200 rounded-lg px-2.5 py-1.5 flex items-center gap-1.5 cursor-pointer hover:bg-paper-50 text-[12px]">
                                    <input type="checkbox" name="instagram_positions[]" value="reels" class="accent-wa-deep" @checked(in_array('reels', $ip, true))> {{ __('IG Reels') }}
                                </label>
                            </div>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('Leave all unchecked for automatic placements. Instagram positions apply only when Instagram is ticked; your connected Instagram account is used as the ad identity.') }}</div>
                        </div>
                    </div>

                    <div id="ctwa-fields" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-phone">{{ __('WhatsApp number') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="ctwa-phone" name="ctwa_phone" type="text"
                                value="{{ old('ctwa_phone', ($wabaNumbers[0]['digits'] ?? '')) }}"
                                list="ctwa-numbers" autocomplete="off"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-mono placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Number with country code') }}">
                            <datalist id="ctwa-numbers">
                                @foreach (($wabaNumbers ?? []) as $n)
                                    <option value="{{ $n['digits'] }}">{{ $n['label'] }}</option>
                                @endforeach
                            </datalist>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                @if (!empty($wabaNumbers))
                                    {{ __('Pick one of your connected numbers, or type another.') }}
                                @else
                                    {{ __('Digits only, no plus sign.') }}
                                @endif
                            </div>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-message">{{ __('Prefilled message') }}</label>
                            <input id="ctwa-message" name="ctwa_message" type="text"
                                value="{{ old('ctwa_message') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __("Hi, I'm interested in…") }}">
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-cta">{{ __('CTA button') }}</label>
                            <select id="ctwa-cta" name="ctwa_cta"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="WHATSAPP_MESSAGE">{{ __('WhatsApp Message') }}</option>
                                <option value="LEARN_MORE">{{ __('Learn More') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                    <div class="sec-head flex items-center gap-2.5 mb-3">
                        <span
                            class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                        <span
                            class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Audience targeting') }}</span>
                        <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('Meta ad set') }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="countries">{{ __('Target countries') }} <span
                                    class="req text-accent-coral">*</span></label>
                            @php $oldCountries = (array) old('target_countries', []); @endphp
                            <select id="countries" name="target_countries[]" multiple class="ctrl w-full" required>
                                @foreach ($countries ?? [] as $code => $label)
                                    <option value="{{ $code }}" @selected(in_array($code, $oldCountries, true))>
                                        {{ $label }} ({{ $code }})</option>
                                @endforeach
                            </select>
                            <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                                {{ __('Pick one or more — Meta uses ISO codes.') }}</div>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="age-min">{{ __('Min age') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="age-min" name="age_min" type="number" min="13" max="80"
                                value="{{ old('age_min', 18) }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="age-max">{{ __('Max age') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="age-max" name="age_max" type="number" min="13" max="80"
                                value="{{ old('age_max', 45) }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                        </div>
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="gender">{{ __('Gender') }}</label>
                            <select id="gender" name="gender"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('All genders') }}</option>
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label
                            class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="interests">{{ __('Interests') }} <span
                                class="sec-meta font-mono text-[10px] text-ink-500">{{ __('pick from curated list') }}</span></label>
                        @php $oldInterests = (array) old('interests', []); @endphp
                        <select id="interests" name="interests[]" multiple class="ctrl w-full">
                            @foreach ($interestGroups ?? [] as $group => $items)
                                <optgroup label="{{ $group }}">
                                    @foreach ($items as $item)
                                        <option value="{{ $item }}" @selected(in_array($item, $oldInterests, true))>
                                            {{ $item }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="hint text-[10.5px] text-ink-500 mt-1 leading-[1.35]">
                            {{ __("Pick from Meta's curated interest taxonomy — leave empty for broad targeting.") }}
                        </div>
                    </div>
                </div>

                <div class="sec px-[18px] py-4">
                    @include('user.campaigns._advanced-targeting', ['campaign' => null])
                </div>

                <div class="sec px-[18px] py-4">
                    <div class="sec-head flex items-center gap-2.5 mb-3">
                        <span
                            class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                        <span
                            class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Ad creative') }}</span>
                        <span
                            class="sec-meta font-mono text-[10px] text-ink-500">{{ __('headline, text, media') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="headline">{{ __('Ad headline') }} <span class="req text-accent-coral">*</span>
                                <span class="sec-meta font-mono text-[10px] text-ink-500"><span
                                        id="headline-count">0</span>/100</span></label>
                            <input id="headline" name="creative_title" type="text" maxlength="100"
                                value="{{ old('creative_title') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Summer Sale is live') }}" required>
                        </div>
                        <div id="destination-wrap">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="destination">{{ __('Destination URL') }} <span
                                    class="req text-accent-coral">*</span></label>
                            <input id="destination" name="creative_link_url" type="url"
                                value="{{ old('creative_link_url') }}"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="https://yourwebsite.com">
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="body">{{ __('Ad text') }} <span class="req text-accent-coral">*</span> <span
                                    class="sec-meta font-mono text-[10px] text-ink-500"><span id="body-count">0</span>
                                    chars</span></label>
                            <textarea id="body" name="creative_body"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                rows="4" placeholder="{{ __('Get up to 40% off today. Message us on WhatsApp to claim your offer.') }}">{{ old('creative_body') }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Ad image') }}
                                <span class="req text-accent-coral">*</span></label>
                            <label
                                class="file-tile flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid">
                                <span
                                    class="file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                        <circle cx="6" cy="7" r="1.5" />
                                        <path d="M2 11l4-3 4 3 4-2" />
                                    </svg></span>
                                <div class="file-meta flex-1 min-w-0">
                                    <div class="file-title text-[12px] font-semibold text-ink-900 truncate"
                                        data-file-label>{{ __('Choose image') }}</div>
                                    <div class="file-sub text-[10.5px] text-ink-500 font-mono">
                                        {{ __('Meta needs 1080×1080+ for CTWA / max 10MB') }}</div>
                                    <div class="text-[10px] text-accent-coral mt-0.5 hidden" data-image-warn></div>
                                </div>
                                <span
                                    class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0">{{ __('Browse') }}</span>
                                <input type="file" name="creative_image_file" accept="image/jpeg,image/png"
                                    class="hidden" data-creative-image>
                            </label>
                            {{-- Live preview thumbnail — appears after file pick. Dimension
 validation runs client-side so the user catches under-1080
 images BEFORE the server round-trip. --}}
                            <div class="mt-3 hidden" data-image-preview-row>
                                <img alt="{{ __('Preview') }}" data-image-preview
                                    class="w-20 h-20 object-cover rounded-lg border border-paper-200">
                                <div class="text-[10px] text-ink-500 mt-1" data-image-dims></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="preview-col sticky top-[78px] self-start space-y-3">
                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <div
                            class="mono font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                            Ad preview
                        </div>
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono"
                            id="preview-objective-pill">{{ __('Messages') }}</span>
                    </div>
                    <div class="ad-preview bg-white border border-paper-200 rounded-[10px] overflow-hidden">
                        <div class="ad-media h-[168px] bg-[#DFF1ED] flex items-center justify-center text-wa-deep text-[11px] font-mono bg-cover bg-center"
                            id="ad-media">
                            <img id="ad-media-img" class="hidden w-full h-full object-cover" alt="">
                            <span id="ad-media-label">{{ __('Image preview') }}</span>
                        </div>
                        <div class="p-3">
                            <div class="text-[10px] uppercase tracking-[0.12em] text-ink-500 mono font-mono mb-1">
                                {{ __('Sponsored') }}</div>
                            <div id="ad-headline" class="text-[14px] font-semibold text-ink-900">
                                {{ __('Your headline appears here') }}</div>
                            <p id="ad-body" class="text-[12px] text-ink-600 leading-relaxed mt-1">
                                {{ __('Write ad copy to preview it here.') }}</p>
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span id="ad-url"
                                    class="text-[11px] text-ink-500 truncate">{{ __('yourwebsite.com') }}</span>
                                <span id="ad-cta"
                                    class="px-3 py-1.5 rounded-md bg-paper-100 text-[11px] font-semibold text-ink-900">{{ __('Send Message') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="mono font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 px-1">
                        {{ __('WhatsApp click preview') }}</div>
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
                                    W</div>
                                <div class="leading-tight">
                                    <div class="text-[11.5px] font-semibold">
                                        {{ brand_name() }}
                                        {{ __('Business') }}</div>
                                    <div class="text-[9px] opacity-70">{{ __('online') }}</div>
                                </div>
                            </div>
                            <div
                                class="phone-body flex-1 p-3 bg-wa-chat [background-image:radial-gradient(rgba(7,94,84,0.06)_1px,transparent_1px)] bg-[length:14px_14px]">
                                <div
                                    class="pp-bubble bg-paper-0 rounded-[7px] rounded-tl-[2px] px-[9px] py-2 max-w-[88%] shadow-[0_1px_1px_rgba(0,0,0,0.06)] mb-[5px] text-[12px] leading-[1.4] break-words">
                                    <div class="text-[11px] text-ink-500 mb-1">{{ __('Prefilled message') }}</div>
                                    <div id="phone-message">{{ __('Hi, I am interested in the summer sale.') }}</div>
                                    <div class="pp-time text-[9px] text-ink-500 text-right mt-1 font-mono"
                                        id="phone-time">10:30</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-3 bg-wa-bubble/40">
                    <div class="text-[11px] text-ink-700 leading-snug"><b>Checklist:</b> objective selected, budget
                        set, audience valid, creative image uploaded, and destination or WhatsApp number ready.</div>
                </div>
            </aside>
        </form>
    </section>

    {{-- Build-with-AI modal — vanilla Tailwind, same overlay/panel
 pattern as /templates/create#ai-modal so the visual language
 stays consistent. Opened by #open-ai-modal in the sticky bar.
 POST submission lives in user-campaigns-create.js. --}}
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
                        {{ __('Meta Ads / AI') }}</div>
                    <div class="serif font-serif text-[18px] leading-tight">{{ __('Build with') }} <span
                            class="italic text-wa-deep">AI</span></div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('Draft headline, body, targeting, and a CTWA message from a short brief.') }}</div>
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

                    <div>
                        <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-product">{{ __('Product or service') }}</label>
                        <input id="ai-product" type="text" maxlength="255"
                            class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Seasonal bouquets · same-day delivery') }}">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-objective">{{ __('Objective') }}</label>
                            <select id="ai-objective"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select objective') }}</option>
                                <option>{{ __('Messages (start WhatsApp chats)') }}</option>
                                <option>{{ __('Link clicks') }}</option>
                                <option>{{ __('Conversions') }}</option>
                                <option>{{ __('Lead generation') }}</option>
                                <option>{{ __('Reach') }}</option>
                                <option>{{ __('Brand awareness') }}</option>
                                <option>{{ __('Video views') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-cta">{{ __('Preferred CTA') }}</label>
                            <select id="ai-cta"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select CTA') }}</option>
                                <option>{{ __('Send WhatsApp message') }}</option>
                                <option>{{ __('Learn more') }}</option>
                                <option>{{ __('Shop now') }}</option>
                                <option>{{ __('Sign up') }}</option>
                                <option>{{ __('Book now') }}</option>
                                <option>{{ __('Get offer') }}</option>
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
                                <option>{{ __('Urgent') }}</option>
                                <option>{{ __('Playful') }}</option>
                                <option>{{ __('Premium') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="ai-countries">{{ __('Markets') }}</label>
                            <input id="ai-countries" type="text" maxlength="255"
                                class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('US, GB, IN (or describe in words)') }}">
                        </div>
                    </div>

                    <div>
                        <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-audience">{{ __('Target audience') }}</label>
                        <textarea id="ai-audience" rows="2" maxlength="500"
                            class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-y"
                            placeholder="{{ __('Working professionals, 25-45, gift-buyers, urban metros') }}"></textarea>
                    </div>

                    <div>
                        <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                            for="ai-destination">{{ __('Destination URL') }}</label>
                        <input id="ai-destination" type="url" maxlength="1024"
                            class="ctrl w-full px-[11px] py-[8px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="https://yourbrand.com/landing">
                    </div>

                    <label class="flex items-center gap-2 text-[12px] text-ink-700">
                        <input id="ai-whatsapp" type="checkbox"
                            class="w-4 h-4 rounded border-paper-300 accent-wa-deep">
                        <span>{{ __('Include a Click-to-WhatsApp message (the customer lands in WhatsApp pre-filled)') }}</span>
                    </label>

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
                            placeholder="{{ __('Anything specific — must-have phrases, what to avoid, key benefit, offer details…') }}"></textarea>
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
                    class="btn-ai-shine relative isolate overflow-hidden px-5 py-2 rounded-full text-[12px] font-semibold flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg id="ai-generate-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <path
                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                    </svg>
                    <svg id="ai-generate-spin" viewBox="0 0 16 16" class="w-3.5 h-3.5 hidden animate-spin"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M8 1.5a6.5 6.5 0 1 1-6.5 6.5" stroke-linecap="round" />
                    </svg>
                    <span id="ai-generate-label">{{ __('Generate ad copy') }}</span>
                </button>
            </div>
        </div>
    </div>

</x-layouts.user>
