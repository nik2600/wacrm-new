<x-layouts.admin :title="__('General settings')" admin-key="settings" page="admin-settings-general">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('General') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('General') }}
                        <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Core application identity, contact information, default locale and currency, and platform-level service switches.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <x-admin.flash inline />
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

                <div class="space-y-5 min-w-0">

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('general-setting.blade.php') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Application identity') }}
                                </h2>
                            </div>
                            <span
                                class="rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 px-2.5 py-1 text-[11px] font-mono">{{ __('healthy') }}</span>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('App name') }} <span
                                        class="text-accent-coral">*</span></span>
                                <input name="app_name" value="{{ old('app_name', $settings['app_name']) }}" required
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Platform message footer') }} <span
                                        class="text-ink-500 font-normal">(applied to workspaces without <code
                                            class="font-mono">remove_branding</code> plan)</span></span>
                                <input name="platform_branding_footer" maxlength="60"
                                    value="{{ old('platform_branding_footer', $settings['platform_branding_footer'] ?? '') }}"
                                    placeholder="Sent via {{ $settings['app_name'] }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[10.5px] text-ink-500">{{ __('Appended to plain text + interactive bubbles on outbound messages. 60 char max. Templates skip this — they carry their own footer. Workspaces with the') }}
                                    <code class="font-mono">remove_branding</code> plan feature can override this with
                                    their own footer (or none) in <code class="font-mono">/account →
                                        Branding</code>.</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('App URL') }}</span>
                                <input name="app_url" value="{{ old('app_url', $settings['app_url']) }}"
                                    placeholder="https://app.wadesk.in"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Support email') }}</span>
                                <input type="email" name="support_email"
                                    value="{{ old('support_email', $settings['support_email']) }}"
                                    placeholder="support@…"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Contact number') }}</span>
                                <input name="contact_number"
                                    value="{{ old('contact_number', $settings['contact_number']) }}"
                                    placeholder="+91 …"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('From email (outbound)') }}</span>
                                <input type="email" name="from_email"
                                    value="{{ old('from_email', $settings['from_email']) }}" placeholder="hello@…"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default timezone') }}</span>
                                <select id="gen-tz" name="default_timezone"
                                    data-value="{{ old('default_timezone', $settings['default_timezone']) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    <option value="{{ $settings['default_timezone'] }}">
                                        {{ $settings['default_timezone'] }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default language') }}</span>
                                <select name="default_language"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @foreach ($languages as $l)
                                        <option value="{{ $l->code }}" @selected(($settings['default_language'] ?? '') === $l->code)>
                                            {{ $l->name }} ({{ $l->code }})</option>
                                    @endforeach
                                    @if (!$languages->contains('code', $settings['default_language']))
                                        <option value="{{ $settings['default_language'] }}" selected>
                                            {{ $settings['default_language'] }} (custom)</option>
                                    @endif
                                </select>
                                <a href="{{ url('/admin/languages') }}"
                                    class="text-[10.5px] text-wa-deep hover:underline">{{ __('Manage languages →') }}</a>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default currency') }}</span>
                                <select name="default_currency"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->code }}" @selected(($settings['default_currency'] ?? '') === $c->code)>
                                            {{ $c->code }} — {{ $c->name }}</option>
                                    @endforeach
                                    @if (!$currencies->contains('code', $settings['default_currency']))
                                        <option value="{{ $settings['default_currency'] }}" selected>
                                            {{ $settings['default_currency'] }} (custom)</option>
                                    @endif
                                </select>
                                <a href="{{ url('/admin/currencies') }}"
                                    class="text-[10.5px] text-wa-deep hover:underline">{{ __('Manage currencies →') }}</a>
                            </label>
                            {{--
                                Default country — applies to every phone-input picker across the platform
                                (Profile, Devices, Contacts, Connect, WA-links, Chatbot widgets, Register).
                                Changing this single dropdown flips every "+91 / 🇮🇳" default to the chosen
                                country, so a customer white-labeling for Indonesia just picks "Indonesia"
                                here and saves — no code edits, no rebuild.
                                Stored as two columns: dial code (+62) + ISO (id).
                            --}}
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Default country (phone pickers)') }}</span>
                                @php
                                    // Curated short-list — first row is "current"; rest are the most common
                                    // phone-picker defaults. Each option ships BOTH the dial code and the
                                    // ISO so the JS picker shows the right flag without a second lookup.
                                    // Full ISO country list (all 250) from config/countries.php so
                                    // every country is selectable — not a curated subset. Falls back to
                                    // a minimal set only if the config is somehow missing.
                                    $countryOpts = config('countries') ?: [
                                        ['code' => '+91', 'iso' => 'in', 'label' => 'India (+91)'],
                                        ['code' => '+1',  'iso' => 'us', 'label' => 'United States (+1)'],
                                    ];
                                    $currentKey = ($settings['default_country_code'] ?? '+91') . '|' . ($settings['default_country_iso'] ?? 'in');
                                @endphp
                                <select name="default_country_picker"
                                    onchange="(function(s){var p=s.value.split('|');document.getElementById('def-cc').value=p[0]||'+91';document.getElementById('def-iso').value=p[1]||'in';})(this)"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    @foreach ($countryOpts as $opt)
                                        @php $key = $opt['code'] . '|' . $opt['iso']; @endphp
                                        <option value="{{ $key }}" @selected($currentKey === $key)>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                {{-- Hidden inputs the controller actually reads — the visible <select> just
                                     updates these on change. Pre-filled with the current values so a save
                                     without touching the picker still persists the existing choice. --}}
                                <input id="def-cc"  type="hidden" name="default_country_code" value="{{ $settings['default_country_code'] ?? '+91' }}">
                                <input id="def-iso" type="hidden" name="default_country_iso"  value="{{ $settings['default_country_iso']  ?? 'in' }}">
                                <span class="text-[10.5px] text-ink-500">{{ __('Applies to every phone-input picker on the platform — Profile, Devices, Contacts, Connect, Register.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Font family') }}</span>
                                <select name="font_family"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="" @selected(($settings['font_family'] ?? '') === '')>{{ __('Theme default') }}</option>
                                    @foreach (app_font_catalog() as $fkey => $f)
                                        <option value="{{ $fkey }}" @selected(($settings['font_family'] ?? '') === $fkey) style="font-family: {{ $f['stack'] }}">{{ $f['label'] }}</option>
                                    @endforeach
                                </select>
                                <span class="text-[10.5px] text-ink-500">{{ __('Loads the selected Google font across the whole app.') }}</span>
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Address') }}</span>
                                <textarea name="address" rows="3"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] resize-none focus:outline-none focus:border-wa-deep">{{ old('address', $settings['address']) }}</textarea>
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Map iframe URL') }}</span>
                                <input name="map_iframe_url"
                                    value="{{ old('map_iframe_url', $settings['map_iframe_url']) }}"
                                    placeholder="https://www.google.com/maps/embed?pb=..."
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('brand-assets') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Brand assets') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">
                                {{ __('Favicon is shared across every theme. Logo is per-theme — upload an inverted/light variant for dark mode, a doodle-friendly variant for the doodle theme, etc.') }}
                            </p>
                        </div>

                        <div class="p-5 space-y-5">
                            {{-- Favicon — single shared --}}
                            <div class="grid grid-cols-[120px_1fr] gap-4 items-center">
                                <div id="favicon-preview"
                                    class="rounded-2xl border border-paper-200 bg-paper-50 h-[120px] grid place-items-center overflow-hidden">
                                    @if ($settings['brand_favicon'])
                                        <img src="{{ asset('storage/' . $settings['brand_favicon']) }}"
                                            alt="{{ __('Favicon') }}" class="max-h-16 max-w-16 object-contain">
                                    @else
                                        <span
                                            class="text-[10px] font-mono text-ink-500 uppercase tracking-[0.14em]">{{ __('ico / png') }}</span>
                                    @endif
                                </div>
                                <div>
                                    <div class="font-semibold text-[13px]">{{ __('Favicon') }}</div>
                                    <p class="text-[11.5px] text-ink-600 mt-0.5">
                                        {{ __('Shown in browser tabs + bookmarks. Recommended') }} <span
                                            class="font-mono">35×35 px</span>. PNG or ICO.</p>
                                    <input type="file" name="favicon" data-preview-target="favicon-preview"
                                        data-preview-class="max-h-16 max-w-16 object-contain"
                                        accept=".png,.ico,.jpg,.jpeg,.svg,.webp"
                                        class="mt-2 block w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[11.5px] file:font-medium file:cursor-pointer">
                                    @if ($settings['brand_favicon'])
                                        <div class="text-[10.5px] font-mono text-ink-500 mt-1">Current:
                                            {{ basename($settings['brand_favicon']) }} · uploading a new file replaces
                                            it</div>
                                    @endif
                                </div>
                            </div>

                            {{-- Per-theme logos --}}
                            <div>
                                <div class="font-semibold text-[13px] mb-1">{{ __('Logo per theme') }}</div>
                                <p class="text-[11.5px] text-ink-600 mb-3">
                                    {{ __('Each theme uses its own logo. Falls back to "Paper" if no theme-specific logo is uploaded.') }}
                                </p>

                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                    @foreach ($brandThemes as $t)
                                        @php $cur = $settings['brand_logo_' . $t['id']] ?? ''; @endphp
                                        <div class="rounded-2xl border border-paper-200 p-3 bg-paper-0">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="font-semibold text-[12.5px]">{{ $t['label'] }}</div>
                                                <span
                                                    class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500">{{ $t['id'] }}</span>
                                            </div>
                                            <div id="logo-preview-{{ $t['id'] }}"
                                                class="rounded-xl border border-paper-200 {{ $t['id'] === 'dark' ? 'bg-[#0B1F1C]' : ($t['id'] === 'doodle' ? 'bg-wa-bubble' : 'bg-paper-50') }} h-20 grid place-items-center overflow-hidden mb-2">
                                                @if ($cur)
                                                    <img src="{{ asset('storage/' . $cur) }}"
                                                        alt="Logo · {{ $t['id'] }}"
                                                        class="max-h-14 max-w-[140px] object-contain">
                                                @else
                                                    <span
                                                        class="text-[10px] font-mono {{ $t['id'] === 'dark' ? 'text-paper-200' : 'text-ink-500' }} uppercase tracking-[0.14em]">{{ __('no logo') }}</span>
                                                @endif
                                            </div>
                                            <input type="file" name="logos[{{ $t['id'] }}]"
                                                data-preview-target="logo-preview-{{ $t['id'] }}"
                                                data-preview-class="max-h-14 max-w-[140px] object-contain"
                                                accept=".png,.jpg,.jpeg,.svg,.webp"
                                                class="block w-full text-[11px] file:mr-2 file:px-2.5 file:py-1 file:rounded-full file:border-0 file:bg-paper-100 file:text-ink-700 file:text-[10.5px] file:font-medium hover:file:bg-paper-200 file:cursor-pointer">
                                            <p class="text-[10.5px] text-ink-500 mt-1.5">{{ $t['note'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('platform-toggles') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Platform toggles') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <label
                                class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                                <span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Preloader') }}</span><span
                                        class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Show the loading splash on every page') }}</span></span>
                                <span class="toggle"><input type="hidden" name="preloader" value="0"><input
                                        type="checkbox" name="preloader" value="1"
                                        @checked($settings['preloader'])><span class="track"></span><span
                                        class="thumb"></span></span>
                            </label>
                            <label
                                class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                                <span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Maintenance mode') }}</span><span
                                        class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('All user pages show "be right back"') }}</span></span>
                                <span class="toggle"><input type="hidden" name="maintenance_mode"
                                        value="0"><input type="checkbox" name="maintenance_mode" value="1"
                                        @checked($settings['maintenance_mode'])><span class="track"></span><span
                                        class="thumb"></span></span>
                            </label>
                            <label
                                class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                                <span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Public registration') }}</span><span
                                        class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Anyone can sign up at /register') }}</span></span>
                                <span class="toggle"><input type="hidden" name="public_registration"
                                        value="0"><input type="checkbox" name="public_registration"
                                        value="1" @checked($settings['public_registration'])><span class="track"></span><span
                                        class="thumb"></span></span>
                            </label>
                            <label
                                class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                                <span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Auto-verify email on signup') }}</span><span
                                        class="block text-[10.5px] text-ink-500 mt-0.5">{{ __("Skip the verify-email screen — useful when SMTP isn't configured or for invite-only deployments") }}</span></span>
                                <span class="toggle"><input type="hidden" name="auto_verify_email"
                                        value="0"><input type="checkbox" name="auto_verify_email"
                                        value="1" @checked($settings['auto_verify_email'] ?? false)><span class="track"></span><span
                                        class="thumb"></span></span>
                            </label>
                        </div>
                    </section>

                    {{-- Default plan a workspace lands on at the end of signup, plus the
 free-trial length. A FREE default plan starts a countdown
 (trial_ends_at = now + days) and shows the user a trial bar;
 a paid default plan starts no trial. --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('signups-and-trial') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Signups & free trial') }}
                            </h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span
                                    class="text-[11.5px] font-semibold">{{ __('Default plan for new signups') }}</span>
                                <select name="registration_default_plan_id"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="">{{ __('Auto — first active free plan') }}</option>
                                    @foreach ($signupPlans as $p)
                                        <option value="{{ $p->plan_id }}" @selected(old('registration_default_plan_id', $settings['registration_default_plan_id']) === $p->plan_id)>
                                            {{ $p->pname }} —
                                            {{ $p->isFreePlan() ? __('Free trial') : __('Paid plan') }}
                                        </option>
                                    @endforeach
                                </select>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Assigned automatically when a new account finishes registration. Manage plans at') }}
                                    <a href="{{ url('/admin/packages') }}"
                                        class="text-wa-deep">/admin/packages</a>.</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Free trial length (days)') }}</span>
                                <input name="registration_trial_days" type="number" min="0" max="365"
                                    value="{{ old('registration_trial_days', $settings['registration_trial_days']) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Only applies when the default plan is free. The user sees a countdown bar; set to') }}
                                    <code class="font-mono text-[11px]">0</code>
                                    {{ __('for a free plan with no expiry.') }}</span>
                            </label>
                            <label class="space-y-1.5 sm:col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Plan scope') }}</span>
                                @php $planScope = old('billing_plan_scope', $settings['billing_plan_scope'] ?? 'workspace'); @endphp
                                <select name="billing_plan_scope"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                    <option value="workspace" @selected($planScope === 'workspace')>
                                        {{ __('Per workspace — each workspace billed separately (default)') }}
                                    </option>
                                    <option value="account" @selected($planScope === 'account')>
                                        {{ __("Per account — an owner's plan unlocks all their workspaces") }}
                                    </option>
                                </select>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Per workspace: every workspace has its own plan + trial (agency model, max revenue). Per account: when an owner buys a plan on any workspace, all workspaces they own are unlocked — no second trial or purchase.') }}</span>
                            </label>
                        </div>
                    </section>

                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Where things go') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('App name') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Used in page titles, emails, and the in-app brand. Keep short — ideally one or two words.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('App URL') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('The canonical address. All callbacks, emails and OAuth redirects derive from this.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Default language') }}
                                </div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Applied to new accounts. Existing users keep whatever they picked. Manage the list at') }}
                                    <a href="{{ url('/admin/languages') }}"
                                        class="text-wa-deep">/admin/languages</a>.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Default currency') }}
                                </div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Used for invoices and wallets without an explicit override. Configure rates at') }}
                                    <a href="{{ url('/admin/currencies') }}"
                                        class="text-wa-deep">/admin/currencies</a>.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Maintenance mode') }}
                                </div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('When ON every non-admin route shows a placeholder. Admin can still reach') }}
                                    <code class="font-mono text-[11px]">/admin</code>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Production rule') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Save only after testing the affected user flow. Identity changes propagate to invoices and outbound emails.') }}
                        </p>
                    </div>
                </aside>

            </section>

            {{-- Sticky save bar — keeps the primary action reachable on this long
 form without scrolling back to the top action row. --}}
            <div
                class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit"
                        class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

        </main>

    </form>

</x-layouts.admin>
