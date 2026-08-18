@php
    /** @var \App\Models\MetaCampaign $campaign */
    $targeting = is_array($campaign->targeting ?? null) ? $campaign->targeting : [];
    $countries = is_array($targeting['countries'] ?? null)
        ? implode(', ', $targeting['countries'])
        : $targeting['countries'] ?? '';
    $interests = is_array($targeting['interests'] ?? null)
        ? implode(', ', $targeting['interests'])
        : $targeting['interests'] ?? '';
    $ageMin = $targeting['age_min'] ?? 18;
    $ageMax = $targeting['age_max'] ?? 45;
    $genderVal = $targeting['gender'] ?? '';
    $metrics = $campaign->metrics;
    $statusLbl = ucfirst(strtolower($campaign->status));
    $isActive = $campaign->status === 'ACTIVE';
@endphp

<x-layouts.user :title="__('Edit Meta Ads Campaign')" nav-key="metaads" page="user-meta-ads-edit">

    <div class="hairline-b border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/meta-ads') }}"
                    class="w-8 h-8 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Meta Ads') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Meta Ads / Edit /
                        {{ $campaign->facebook_id ?: '#' . $campaign->id }}</div>
                    <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] leading-tight truncate">
                        {{ __('Edit') }} <span class="italic text-wa-deep">{{ $campaign->name }}</span></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $isActive ? 'bg-wa-green/15 text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-700 border border-paper-200' }}">
                    @php $adCur = $adAccount?->currency ?? \App\Models\SystemSetting::get('default_currency', 'USD'); @endphp
                    {{ $statusLbl }} / {{ number_format($metrics['clicks']) }} clicks / {!! \App\Support\FormatSettings::display($metrics['spend'], $adCur) !!}
                    {{ __('spend') }}
                </span>

                <form action="{{ route('user.meta-ads.destroy', $campaign->id) }}" method="POST" data-confirm-form
                    data-confirm-title="{{ __('Delete campaign?') }}"
                    data-confirm-message="Delete &quot;{{ $campaign->name }}&quot;? This can't be undone."
                    class="inline-block">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-accent-coral/10 hover:border-accent-coral text-accent-coral text-[12px] font-medium">{{ __('Delete') }}</button>
                </form>

                <form action="{{ route('user.meta-ads.toggle', $campaign->id) }}" method="POST" class="inline-block">
                    @csrf
                    <button type="submit"
                        class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                        {{ $isActive ? 'Pause' : 'Activate' }}
                    </button>
                </form>

                <button type="submit" form="campaignForm"
                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 8l5 5 7-9" />
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
                <div class="font-semibold mb-1">{{ __('Could not save the campaign:') }}</div>
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="campaignForm" method="POST" action="{{ route('user.meta-ads.update', $campaign->id) }}"
            enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">
            @csrf
            @method('PUT')

            <div class="space-y-4">

                {{-- Live metrics from $campaign->metrics — populated by sync(). --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Spend') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">{!! \App\Support\FormatSettings::display($metrics['spend'], $adCur) !!}
                        </div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Impressions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                            {{ number_format($metrics['impressions']) }}</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CTR') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">
                            {{ number_format($metrics['ctr'], 2) }}%</div>
                    </div>
                    <div class="metric bg-wa-bubble border border-wa-deep/20 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-wa-deep uppercase">
                            {{ __('Revenue') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 text-wa-deep">
                            {!! \App\Support\FormatSettings::display($metrics['revenue'], $adCur) !!}</div>
                    </div>
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card">
                    <div class="sec px-[18px] py-4 hairline-b border-b border-paper-200">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Campaign details') }}</span>
                            <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('editable') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="campaign-name">{{ __('Campaign name') }} <span
                                        class="req text-accent-coral">*</span></label>
                                <input id="campaign-name" name="name" type="text"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('name', $campaign->name) }}" required>
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="objective">{{ __('Objective') }} <span
                                        class="req text-accent-coral">*</span></label>
                                @php $goal = old('optimization_goal', $campaign->optimization_goal); @endphp
                                <select id="objective" name="optimization_goal"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    required>
                                    @foreach (['MESSAGES' => 'Messages — WhatsApp inbox', 'LINK_CLICKS' => 'Link Clicks', 'CONVERSIONS' => 'Conversions', 'LEAD_GENERATION' => 'Lead Generation', 'REACH' => 'Reach', 'BRAND_AWARENESS' => 'Brand Awareness', 'VIDEO_VIEWS' => 'Video Views'] as $v => $lbl)
                                        <option value="{{ $v }}" @selected($goal === $v)>
                                            {{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="adset-name">{{ __('Ad set name') }}</label>
                                <input id="adset-name" name="adset_name" type="text"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('adset_name') }}" placeholder="{{ __('Ad set') }}">
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="budget">{{ __('Daily budget') }} <span
                                        class="req text-accent-coral">*</span></label>
                                <input id="budget" name="daily_budget" type="number" min="1" step="0.01"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('daily_budget', $campaign->daily_budget) }}" required>
                                <div class="hint text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Changes apply after the next Meta sync.') }}</div>
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="status-mode">{{ __('Status') }}</label>
                                @php $st = old('status', $campaign->status); @endphp
                                <select id="status-mode" name="status"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @foreach (\App\Models\MetaCampaign::STATUSES as $v)
                                        <option value="{{ $v }}" @selected($st === $v)>
                                            {{ ucfirst(strtolower($v)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label
                                    class="hairline border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 w-full">
                                    <span>
                                        <span
                                            class="block text-[12.5px] font-semibold">{{ __('Click to WhatsApp') }}</span>
                                        <span
                                            class="block text-[10.5px] text-ink-500">{{ $campaign->ctwa_enabled ? 'Currently enabled' : 'Currently disabled' }}</span>
                                    </span>
                                    <span class="switch relative inline-block w-[34px] h-5 shrink-0">
                                        <input class="peer opacity-0 w-0 h-0" type="checkbox" id="ctwa-enabled"
                                            name="ctwa_enabled" value="1" @checked(old('ctwa_enabled', $campaign->ctwa_enabled))>
                                        <span
                                            class="switch-slider absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                    {{-- Ad type + placement (Instagram Ads) — restored from the campaign. --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ad-type">{{ __('Ad type') }}</label>
                            @php $adType = old('ad_type', $campaign->ad_type ?: 'ctwa'); @endphp
                            <select id="ad-type" name="ad_type"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="ctwa" @selected($adType === 'ctwa')>{{ __('Click to WhatsApp') }}</option>
                                <option value="link" @selected($adType === 'link')>{{ __('Instagram / Facebook link ad') }}</option>
                                <option value="ig_direct" @selected($adType === 'ig_direct')>{{ __('Click to Instagram DM') }}</option>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label
                                class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Placement') }}</label>
                            <input type="hidden" name="placement_edited" value="1">
                            @php $pp = (array) old('publisher_platforms', (array) ($campaign->publisher_platforms ?? [])); $ip = (array) old('instagram_positions', (array) ($campaign->instagram_positions ?? [])); @endphp
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
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="ctwa-phone">{{ __('WhatsApp number') }}</label>
                                <input id="ctwa-phone" name="ctwa_phone" type="text"
                                    list="ctwa-numbers" autocomplete="off"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('ctwa_phone', $campaign->ctwa_phone) }}"
                                    placeholder="{{ __('Number with country code') }}">
                                <datalist id="ctwa-numbers">
                                    @foreach (($wabaNumbers ?? []) as $n)
                                        <option value="{{ $n['digits'] }}">{{ $n['label'] }}</option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="ctwa-message">{{ __('Prefilled message') }}</label>
                                <input id="ctwa-message" name="ctwa_message" type="text"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('ctwa_message', $campaign->ctwa_message) }}"
                                    placeholder="{{ __('Hi, I am interested...') }}">
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="ctwa-cta">{{ __('CTA button') }}</label>
                                @php $cta = old('ctwa_cta', $campaign->ctwa_cta ?? 'WHATSAPP_MESSAGE'); @endphp
                                <select id="ctwa-cta" name="ctwa_cta"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="WHATSAPP_MESSAGE" @selected($cta === 'WHATSAPP_MESSAGE')>
                                        {{ __('WhatsApp Message') }}</option>
                                    <option value="LEARN_MORE" @selected($cta === 'LEARN_MORE')>{{ __('Learn More') }}
                                    </option>
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
                            <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('ad set') }}</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="countries">{{ __('Target countries') }}</label>
                                <input id="countries" name="target_countries" type="text"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('target_countries', $countries) }}"
                                    placeholder="{{ __('IN, AE') }}">
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="age-min">{{ __('Min age') }}</label>
                                <input id="age-min" name="age_min" type="number" min="13" max="80"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('age_min', $ageMin) }}">
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="age-max">{{ __('Max age') }}</label>
                                <input id="age-max" name="age_max" type="number" min="13" max="80"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('age_max', $ageMax) }}">
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="gender">{{ __('Gender') }}</label>
                                @php $gen = old('gender', $genderVal); @endphp
                                <select id="gender" name="gender"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="" @selected($gen === '' || $gen === 'all')>{{ __('All genders') }}
                                    </option>
                                    <option value="male" @selected($gen === 'male')>{{ __('Male') }}</option>
                                    <option value="female" @selected($gen === 'female')>{{ __('Female') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="interests">{{ __('Interests') }} <span
                                    class="sec-meta font-mono text-[10px] text-ink-500">{{ $interests ? count(array_filter(array_map('trim', explode(',', $interests)))) : 0 }}/50</span></label>
                            <textarea id="interests" name="interests"
                                class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                rows="2" placeholder="{{ __('Comma-separated') }}">{{ old('interests', $interests) }}</textarea>
                        </div>
                    </div>

                    <div class="sec px-[18px] py-4">
                        @include('user.campaigns._advanced-targeting', ['campaign' => $campaign])
                    </div>

                    <div class="sec px-[18px] py-4">
                        <div class="sec-head flex items-center gap-2.5 mb-3">
                            <span
                                class="sec-num w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="sec-title font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Ad creative') }}</span>
                            <span class="sec-meta font-mono text-[10px] text-ink-500">{{ __('live preview') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="headline">{{ __('Ad headline') }} <span
                                        class="req text-accent-coral">*</span></label>
                                <input id="headline" name="creative_title" type="text" maxlength="100"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('creative_title', $campaign->creative_title) }}">
                            </div>
                            <div>
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="destination">{{ __('Destination URL') }}</label>
                                <input id="destination" name="creative_link_url" type="url"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="{{ old('creative_link_url', $campaign->creative_link_url) }}"
                                    placeholder="https://...">
                            </div>
                            <div class="md:col-span-2">
                                <label class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                    for="body">{{ __('Ad text') }} <span
                                        class="req text-accent-coral">*</span></label>
                                <textarea id="body" name="creative_body"
                                    class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    rows="4">{{ old('creative_body', $campaign->creative_body) }}</textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label
                                    class="lbl text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Ad image') }}</label>
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
                                        <div class="file-title text-[12px] font-semibold text-ink-900 truncate">
                                            {{ $campaign->creative_image ? basename($campaign->creative_image) : 'Upload an ad image' }}
                                        </div>
                                        <div class="file-sub text-[10.5px] text-ink-500 font-mono">
                                            {{ $campaign->creative_image ? 'Current image / replace optional' : 'PNG or JPG / up to 10 MB' }}
                                        </div>
                                    </div>
                                    <span
                                        class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0">{{ $campaign->creative_image ? 'Replace' : 'Browse' }}</span>
                                    <input type="file" name="creative_image_file" accept="image/*"
                                        class="hidden">
                                </label>
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
                            <span
                                class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-wa-green animate-pulse' : 'bg-paper-300' }}"></span>
                            {{ __('Current ad preview') }}
                        </div>
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium {{ $isActive ? 'bg-wa-green/15 text-wa-deep border border-wa-green/40' : 'bg-paper-50 text-ink-700 border border-paper-200' }}">{{ $statusLbl }}</span>
                    </div>
                    <div class="ad-preview bg-white border border-paper-200 rounded-[10px] overflow-hidden">
                        <div class="ad-media h-[168px] bg-[#DFF1ED] flex items-center justify-center text-wa-deep text-[11px] font-mono bg-cover bg-center"
                            @if ($campaign->creative_image) style="background-image:url('{{ asset('storage/' . $campaign->creative_image) }}')" @endif>
                            @unless ($campaign->creative_image)
                                <span>{{ __('No creative uploaded') }}</span>
                            @endunless
                        </div>
                        <div class="p-3">
                            <div class="text-[10px] uppercase tracking-[0.12em] text-ink-500 mono font-mono mb-1">
                                {{ __('Sponsored') }}</div>
                            <div class="text-[14px] font-semibold text-ink-900">
                                {{ $campaign->creative_title ?: $campaign->name }}</div>
                            <p class="text-[12px] text-ink-600 leading-relaxed mt-1">
                                {{ $campaign->creative_body ?: '—' }}</p>
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span
                                    class="text-[11px] text-ink-500 truncate">{{ $campaign->creative_link_url ?: ($campaign->ctwa_phone ? 'wa.me/' . preg_replace('/\D+/', '', $campaign->ctwa_phone) : '—') }}</span>
                                <span
                                    class="px-3 py-1.5 rounded-md bg-paper-100 text-[11px] font-semibold text-ink-900">{{ $campaign->ctwa_cta === 'LEARN_MORE' ? 'Learn More' : 'Send Message' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="mono font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Change summary') }}</div>
                    <div class="space-y-2 text-[11.5px] text-ink-700">
                        <div class="flex justify-between gap-3"><span>{{ __('Meta campaign id') }}</span><span
                                class="mono font-mono text-ink-500">{{ $campaign->facebook_id ?: '#' . $campaign->id }}</span>
                        </div>
                        <div class="flex justify-between gap-3"><span>{{ __('Last synced') }}</span><span
                                class="mono font-mono text-ink-500">{{ $campaign->updated_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-3"><span>{{ __('Ad sets') }}</span><span
                                class="mono font-mono text-ink-500">{{ $campaign->ad_set_count }}</span></div>
                        <div class="flex justify-between gap-3"><span>{{ __('Ads') }}</span><span
                                class="mono font-mono text-ink-500">{{ $campaign->ad_count }}</span></div>
                    </div>
                </div>
            </aside>
        </form>
    </section>

</x-layouts.user>
