@php
    // Pre-populate from a saved campaign on the edit screen ($campaign may
    // be null on create). All advanced targeting lives in the encrypted
    // `targeting` array; the JS reads these data-preset JSON blobs to
    // restore Tom Select state.
    $tgt = ($campaign ?? null) && is_array($campaign->targeting) ? $campaign->targeting : [];
    $preset = fn ($k) => json_encode(array_values((array) ($tgt[$k] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $adv    = array_key_exists('advantage_audience', $tgt) ? (int) $tgt['advantage_audience'] : 1;
    $locTypes = (array) ($tgt['location_types'] ?? []);
    $budgetLevel = ($campaign->budget_level ?? 'adset');
    $bidStrategy = ($campaign->bid_strategy ?? 'LOWEST_COST_WITHOUT_CAP');
    $bidAmount   = ($campaign->bid_amount ?? null) ? number_format(((int) $campaign->bid_amount) / 100, 2, '.', '') : '';
    $specialCats = (array) ($campaign->special_ad_categories ?? []);
    $fbPos = (array) ($tgt['facebook_positions'] ?? []);
    $mePos = (array) ($tgt['messenger_positions'] ?? []);
    $anPos = (array) ($tgt['audience_network_positions'] ?? []);
    $devs  = (array) ($tgt['device_platforms'] ?? []);
@endphp

<section id="adv-targeting" data-adv-targeting
    data-search-url="{{ route('user.meta-ads.targeting-search') }}"
    data-audiences-url="{{ route('user.meta-ads.audiences') }}"
    data-estimate-url="{{ ($campaign ?? null) ? route('user.meta-ads.estimate', $campaign->id) : '' }}"
    class="mt-4 rounded-2xl border border-paper-200 bg-paper-0">

    <button type="button" data-adv-toggle
        class="w-full flex items-center justify-between px-5 py-4 text-left">
        <span class="font-serif text-[18px] text-ink-900">{{ __('Advanced targeting') }}
            <span class="ml-2 align-middle text-[10px] font-mono uppercase tracking-[0.14em] text-wa-deep bg-wa-bubble border border-wa-green/30 rounded-full px-2 py-0.5">{{ __('Meta Ads Manager parity') }}</span>
        </span>
        <svg data-adv-caret viewBox="0 0 16 16" class="w-4 h-4 text-ink-500 transition-transform" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <div data-adv-body class="hidden px-5 pb-5 space-y-6">

        {{-- ADVANTAGE+ AUDIENCE ------------------------------------------------ --}}
        <div class="rounded-xl border border-paper-200 p-4 flex items-start justify-between gap-4">
            <div>
                <div class="text-[13px] font-semibold text-ink-900">{{ __('Advantage+ Audience') }}</div>
                <p class="mt-1 text-[11.5px] text-ink-500 max-w-lg">{{ __('ON = Meta’s AI expands beyond your targeting to find more customers (simplest, recommended for cold reach). OFF = deliver only to the exact audience you define below.') }}</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                <input type="checkbox" data-adv-advantage class="sr-only peer" {{ $adv ? 'checked' : '' }}>
                <input type="hidden" name="advantage_audience" data-adv-advantage-val value="{{ $adv }}">
                <div class="w-11 h-6 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        {{-- GEO — city / region / zip ---------------------------------------- --}}
        <div class="space-y-3">
            <div class="text-[13px] font-semibold text-ink-900">{{ __('Locations') }}</div>
            <p class="text-[11.5px] text-ink-500 -mt-1">{{ __('Add cities, regions or postal codes to go beyond country-level. Leave empty to use the countries you picked above.') }}</p>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Cities') }}</label>
                    <select multiple data-geo-picker="city" data-preset="{{ $preset('cities') }}" class="w-full"></select>
                </div>
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Regions / states') }}</label>
                    <select multiple data-geo-picker="region" data-preset="{{ $preset('regions') }}" class="w-full"></select>
                </div>
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Postal codes') }}</label>
                    <select multiple data-geo-picker="zip" data-preset="{{ $preset('zips') }}" class="w-full"></select>
                </div>
            </div>
            <div class="flex items-end gap-3">
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('City radius') }}</label>
                    <input type="number" min="1" max="80" data-geo-radius placeholder="—"
                        class="ctrl w-28 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono">
                </div>
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Unit') }}</label>
                    <select data-geo-unit class="ctrl px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px]">
                        <option value="kilometer">{{ __('km') }}</option>
                        <option value="mile">{{ __('mi') }}</option>
                    </select>
                </div>
                <label class="inline-flex items-center gap-2 text-[11.5px] text-ink-600 pb-2">
                    <input type="checkbox" name="location_types[]" value="recent" {{ in_array('recent', $locTypes) ? 'checked' : '' }} class="rounded border-paper-200">
                    {{ __('Include people recently in these locations') }}
                </label>
            </div>
            {{-- Hidden JSON payloads assembled by the JS on submit. --}}
            <input type="hidden" name="geo_cities"  data-geo-json="city">
            <input type="hidden" name="geo_regions" data-geo-json="region">
            <input type="hidden" name="geo_zips"    data-geo-json="zip">
        </div>

        {{-- DETAILED TARGETING ---------------------------------------------- --}}
        <div class="space-y-3">
            <div class="text-[13px] font-semibold text-ink-900">{{ __('Detailed targeting') }}</div>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Interests') }}</label>
                    <select multiple data-tt-picker="interest" data-json="interests_json" data-preset="{{ $preset('interests_resolved') }}" class="w-full"></select>
                    <input type="hidden" name="interests_json">
                </div>
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Behaviors') }}</label>
                    <select multiple data-tt-picker="behavior" data-json="behaviors_json" data-preset="{{ $preset('behaviors') }}" class="w-full"></select>
                    <input type="hidden" name="behaviors_json">
                </div>
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Life events') }}</label>
                    <select multiple data-tt-picker="life_event" data-json="life_events_json" data-preset="{{ $preset('life_events') }}" class="w-full"></select>
                    <input type="hidden" name="life_events_json">
                </div>
            </div>
        </div>

        {{-- AUDIENCES + EXCLUSIONS ------------------------------------------- --}}
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-2">
                <div class="text-[13px] font-semibold text-ink-900">{{ __('Custom & Lookalike audiences') }}</div>
                <label class="lbl text-[11px] text-ink-600 block">{{ __('Include') }}</label>
                <select multiple data-aud-picker="include" data-json="custom_audiences_json" data-preset="{{ $preset('custom_audiences') }}" class="w-full"></select>
                <input type="hidden" name="custom_audiences_json">
                <label class="lbl text-[11px] text-ink-600 block mt-2">{{ __('Exclude') }}</label>
                <select multiple data-aud-picker="exclude" data-json="excluded_custom_audiences_json" data-preset="{{ $preset('excluded_custom_audiences') }}" class="w-full"></select>
                <input type="hidden" name="excluded_custom_audiences_json">
            </div>
            <div class="space-y-2">
                <div class="text-[13px] font-semibold text-ink-900">{{ __('Exclusions') }}</div>
                <label class="lbl text-[11px] text-ink-600 block">{{ __('Exclude interests') }}</label>
                <select multiple data-tt-picker="interest" data-json="exclude_interests_json" data-preset="{{ $preset('exclude_interests') }}" class="w-full"></select>
                <input type="hidden" name="exclude_interests_json">
                <label class="lbl text-[11px] text-ink-600 block mt-2">{{ __('Exclude behaviors') }}</label>
                <select multiple data-tt-picker="behavior" data-json="exclude_behaviors_json" data-preset="{{ $preset('exclude_behaviors') }}" class="w-full"></select>
                <input type="hidden" name="exclude_behaviors_json">
            </div>
        </div>

        {{-- LANGUAGE + PLACEMENTS + DEVICES ---------------------------------- --}}
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-2">
                <div class="text-[13px] font-semibold text-ink-900">{{ __('Languages') }}</div>
                <select multiple name="locales[]" data-locale-picker data-preset="{{ $preset('locales') }}" class="w-full"></select>
                <p class="text-[11px] text-ink-500">{{ __('Empty = all languages.') }}</p>
            </div>
            <div class="space-y-2">
                <div class="text-[13px] font-semibold text-ink-900">{{ __('Devices') }}</div>
                <div class="flex flex-wrap gap-3 text-[11.5px] text-ink-700">
                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="device_platforms[]" value="mobile" {{ in_array('mobile', $devs) ? 'checked' : '' }} class="rounded border-paper-200">{{ __('Mobile') }}</label>
                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="device_platforms[]" value="desktop" {{ in_array('desktop', $devs) ? 'checked' : '' }} class="rounded border-paper-200">{{ __('Desktop') }}</label>
                </div>
                <div class="text-[13px] font-semibold text-ink-900 mt-3">{{ __('Refined placements') }}</div>
                <div class="text-[11px] text-ink-500 -mt-1">{{ __('Only apply when you selected the matching platform above. Empty = Advantage+ automatic placements.') }}</div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-[11px] text-ink-700">
                    @foreach (['feed'=>'FB Feed','story'=>'FB Story','marketplace'=>'Marketplace','video_feeds'=>'FB Video'] as $val=>$lbl)
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="facebook_positions[]" value="{{ $val }}" {{ in_array($val,$fbPos)?'checked':'' }} class="rounded border-paper-200">{{ $lbl }}</label>
                    @endforeach
                    @foreach (['messenger_home'=>'Messenger Inbox','story'=>'Messenger Story'] as $val=>$lbl)
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="messenger_positions[]" value="{{ $val }}" {{ in_array($val,$mePos)?'checked':'' }} class="rounded border-paper-200">{{ $lbl }}</label>
                    @endforeach
                    @foreach (['classic'=>'AN Native','rewarded_video'=>'AN Rewarded'] as $val=>$lbl)
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="audience_network_positions[]" value="{{ $val }}" {{ in_array($val,$anPos)?'checked':'' }} class="rounded border-paper-200">{{ $lbl }}</label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- BUDGET + BIDDING + CATEGORIES ------------------------------------ --}}
        <div class="rounded-xl border border-paper-200 p-4 space-y-3">
            <div class="text-[13px] font-semibold text-ink-900">{{ __('Budget & bidding') }}</div>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Budget level') }}</label>
                    <select name="budget_level" class="ctrl w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px]">
                        <option value="adset"    {{ $budgetLevel==='adset'?'selected':'' }}>{{ __('Ad set budget') }}</option>
                        <option value="campaign" {{ $budgetLevel==='campaign'?'selected':'' }}>{{ __('Campaign budget (Advantage+ / CBO)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Bid strategy') }}</label>
                    <select name="bid_strategy" data-bid-strategy class="ctrl w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px]">
                        <option value="LOWEST_COST_WITHOUT_CAP" {{ $bidStrategy==='LOWEST_COST_WITHOUT_CAP'?'selected':'' }}>{{ __('Highest volume (lowest cost)') }}</option>
                        <option value="COST_CAP" {{ $bidStrategy==='COST_CAP'?'selected':'' }}>{{ __('Cost cap') }}</option>
                        <option value="LOWEST_COST_WITH_BID_CAP" {{ $bidStrategy==='LOWEST_COST_WITH_BID_CAP'?'selected':'' }}>{{ __('Bid cap') }}</option>
                    </select>
                </div>
                <div data-bid-amount-wrap class="{{ $bidStrategy==='LOWEST_COST_WITHOUT_CAP' ? 'hidden' : '' }}">
                    <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Cap amount') }}</label>
                    <input type="number" name="bid_amount" min="0" step="0.01" value="{{ $bidAmount }}" placeholder="0.00"
                        class="ctrl w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono">
                </div>
            </div>
            <div>
                <label class="lbl text-[11px] text-ink-600 block mb-1">{{ __('Special ad category') }}</label>
                <div class="flex flex-wrap gap-3 text-[11px] text-ink-700">
                    @foreach (['HOUSING'=>'Housing','CREDIT'=>'Credit','EMPLOYMENT'=>'Employment','ISSUES_ELECTIONS_POLITICS'=>'Social / Politics'] as $val=>$lbl)
                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="special_ad_categories[]" value="{{ $val }}" {{ in_array($val,$specialCats)?'checked':'' }} class="rounded border-paper-200">{{ $lbl }}</label>
                    @endforeach
                </div>
                <p class="text-[11px] text-ink-500 mt-1">{{ __('Required by Meta for regulated ads — restricts detailed & location targeting when set.') }}</p>
            </div>
        </div>

        {{-- ESTIMATED REACH ------------------------------------------------- --}}
        @if (($campaign ?? null))
            <div class="rounded-xl border border-wa-green/30 bg-wa-bubble px-4 py-3 flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Estimated audience') }}</div>
                    <div data-estimate-out class="font-serif text-[20px] text-wa-deep mt-0.5">—</div>
                </div>
                <button type="button" data-estimate-btn class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Estimate reach') }}</button>
            </div>
        @endif
    </div>
</section>
