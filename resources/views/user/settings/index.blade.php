<x-layouts.user :title="__('Settings')" nav-key="more" page="user-settings-index">
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <!-- LEFT NAV -->
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Workspace') }}</div>
                    <a data-tab="general" href="?tab=general" class="set-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M2 8h12M8 2a8 8 0 0 1 0 12M8 2a8 8 0 0 0 0 12" />
                        </svg>{{ __('General') }}</a>
                    <a data-tab="branding" href="?tab=branding" class="set-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 3l10 4-4 1.5L7 13z" />
                        </svg>{{ __('Branding') }}</a>
                    <a data-tab="team" href="?tab=team" class="set-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="6" cy="6" r="3" />
                            <path d="M2 14c0-3 2.5-5 4-5s4 2 4 5" />
                            <circle cx="11.5" cy="5.5" r="2" />
                            <path d="M10.5 10c1.9.2 3.5 1.7 3.5 4" />
                        </svg>{{ __('Team & roles') }}</a>
                    <a data-tab="notifications" href="?tab=notifications" class="set-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M8 2a4 4 0 0 0-4 4v2.5L3 11h10l-1-2.5V6a4 4 0 0 0-4-4zM6.5 13a1.7 1.7 0 0 0 3 0" />
                        </svg>{{ __('Notifications') }}</a>
                    <a data-tab="aikeys" href="?tab=aikeys" class="set-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="6" cy="10" r="2.5" />
                            <path d="M8 10l5-5M11 7l1.5 1.5M9.5 5.5L11 4" />
                        </svg>AI keys <span
                            class="ml-auto text-[9px] font-mono px-1.5 py-px rounded bg-[#F3E9FF] text-[#5B3D8A]">3</span></a>

                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-3 pb-1.5">
                        {{ __('System') }}</div>
                    <a data-tab="security" href="?tab=security" class="set-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="7" width="10" height="7" rx="1.5" />
                            <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                        </svg>{{ __('Security') }}</a>
                    <a data-tab="api" href="?tab=api" class="set-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M5 4l-3 4 3 4M11 4l3 4-3 4M9 3l-2 10" />
                        </svg>{{ __('API & webhooks') }}</a>
                    <a data-tab="data" href="?tab=data" class="set-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <ellipse cx="8" cy="4" rx="5" ry="2" />
                            <path d="M3 4v8c0 1.1 2.2 2 5 2s5-.9 5-2V4M3 8c0 1.1 2.2 2 5 2s5-.9 5-2" />
                        </svg>{{ __('Data & export') }}</a>
                    <a data-tab="appearance" href="?tab=appearance" class="set-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 2a6 6 0 0 0 0 12V2z" />
                        </svg>{{ __('Appearance') }}</a>
                </div>

                <a href="{{ url('/account') }}"
                    class="block border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card hover:border-wa-deep transition">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Personal') }}
                    </div>
                    <div class="font-serif text-[15px] mt-0.5">{{ __('Your account →') }}</div>
                    <p class="text-[11px] text-ink-500 mt-1">{{ __('Profile, password, wallet, affiliate.') }}</p>
                </a>
            </aside>

            <!-- MAIN -->
            <section class="space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ url('/more') }}" class="hover:text-wa-deep">{{ __('More') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span id="bc-tab">{{ __('General') }}</span>
                        </div>
                        <h1 id="page-title" class="font-serif font-normal tracking-tight text-[30px] sm:text-[36px] lg:text-[44px] leading-none">
                            {{ __('Workspace') }} <span class="italic text-wa-deep">{{ __('settings') }}</span></h1>
                        <p id="page-desc" class="text-[13px] text-ink-600 mt-2">
                            {{ __('Configure your workspace, team, and integrations.') }}</p>
                    </div>
                </div>

                @php
                    $authUser = auth()->user();
                    $workspace = $authUser?->currentWorkspace;
                    $isOwner = $workspace && (int) $workspace->owner_user_id === (int) $authUser->id;
                    $currencies = \App\Models\Currency::query()
                        ->where('is_active', true)
                        ->orderBy('code')
                        ->get(['code', 'name', 'symbol']);
                    $currentCurrency =
                        $workspace?->currency ?:
                        strtoupper((string) \App\Models\SystemSetting::get('default_currency', 'USD'));
                    $currentTz = $workspace?->timezone ?: 'UTC';
                    try {
                        $allZones = \DateTimeZone::listIdentifiers();
                    } catch (\Throwable $e) {
                        $allZones = ['UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Europe/London', 'America/New_York'];
                    }
                @endphp
                <!-- GENERAL -->
                <div data-pane="general" class="space-y-5">
                    @if (session('settings_status'))
                        <div
                            class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                            {{ session('settings_status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div
                            class="bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-2 text-[12.5px] text-[#A1431F]">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('user.settings.update') }}"
                        class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        @csrf
                        <h3 class="font-serif text-[20px] mb-4">{{ __('Workspace') }}</h3>

                        @if (!$isOwner)
                            <p class="text-[11.5px] text-ink-500 mb-3 italic">
                                {{ __('Only the workspace owner can change timezone, currency, or workspace name.') }}
                            </p>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Workspace name') }}</label>
                                <input name="workspace_name" value="{{ old('workspace_name', $workspace?->name) }}"
                                    @disabled(!$isOwner) maxlength="191"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep disabled:bg-paper-50" />
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Workspace URL') }}</label>
                                <div
                                    class="flex items-stretch border border-paper-200 rounded-lg overflow-hidden bg-paper-50">
                                    <span
                                        class="px-3 py-2 bg-paper-50 text-[13px] text-ink-500 font-mono">{{ request()->getHost() }}/</span>
                                    <input value="{{ $workspace?->slug }}" disabled
                                        class="flex-1 px-3 py-2 bg-paper-50 text-[13px] font-mono text-ink-500" />
                                </div>
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __("URL slug isn't editable here — contact support to change.") }}</span>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Default timezone') }}</label>
                                <select name="timezone" @disabled(!$isOwner)
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep disabled:bg-paper-50">
                                    @foreach ($allZones as $tz)
                                        <option value="{{ $tz }}" @selected(old('timezone', $currentTz) === $tz)>
                                            {{ $tz }}</option>
                                    @endforeach
                                </select>
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('All scheduled sends, business-hours checks, and SLA timers run in this zone.') }}</span>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Currency') }}</label>
                                <select name="currency" @disabled(!$isOwner)
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep disabled:bg-paper-50">
                                    @foreach ($currencies as $c)
                                        <option value="{{ $c->code }}" @selected(old('currency', $currentCurrency) === $c->code)>
                                            {{ $c->code }} — {{ $c->name }} ({{ $c->symbol }})</option>
                                    @endforeach
                                </select>
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Used on plan checkout, invoices, and wallet display.') }}</span>
                            </div>
                        </div>
                        <div class="mt-5 pt-4 border-t border-paper-200 flex items-center justify-end gap-2">
                            <a href="{{ url('/settings') }}"
                                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</a>
                            <button type="submit" @disabled(!$isOwner)
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal disabled:bg-paper-200 disabled:text-ink-500 text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                        </div>
                    </form>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-4">{{ __('Working hours') }}</h3>
                        <div class="space-y-2">
                            <div class="grid grid-cols-2 sm:grid-cols-[100px_1fr_120px_120px] gap-3 items-center text-[12.5px]">
                                <span class="font-medium">{{ __('Mon - Fri') }}</span>
                                <label class="toggle"><input type="checkbox" checked /><span
                                        class="track"></span><span class="thumb"></span></label>
                                <input type="time" value="09:00"
                                    class="px-3 py-1.5 border border-paper-200 rounded-lg text-[12px] font-mono" />
                                <input type="time" value="18:00"
                                    class="px-3 py-1.5 border border-paper-200 rounded-lg text-[12px] font-mono" />
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-[100px_1fr_120px_120px] gap-3 items-center text-[12.5px]">
                                <span class="font-medium">{{ __('Saturday') }}</span>
                                <label class="toggle"><input type="checkbox" checked /><span
                                        class="track"></span><span class="thumb"></span></label>
                                <input type="time" value="10:00"
                                    class="px-3 py-1.5 border border-paper-200 rounded-lg text-[12px] font-mono" />
                                <input type="time" value="14:00"
                                    class="px-3 py-1.5 border border-paper-200 rounded-lg text-[12px] font-mono" />
                            </div>
                            <div
                                class="grid grid-cols-2 sm:grid-cols-[100px_1fr_120px_120px] gap-3 items-center text-[12.5px] opacity-60">
                                <span class="font-medium">{{ __('Sunday') }}</span>
                                <label class="toggle"><input type="checkbox" /><span class="track"></span><span
                                        class="thumb"></span></label>
                                <input type="time" value="00:00" disabled
                                    class="px-3 py-1.5 border border-paper-200 rounded-lg text-[12px] font-mono bg-paper-50" />
                                <input type="time" value="00:00" disabled
                                    class="px-3 py-1.5 border border-paper-200 rounded-lg text-[12px] font-mono bg-paper-50" />
                            </div>
                        </div>
                    </div>

                    {{-- Personal UX preferences. Auto AI summarize wires the
 <x-compose-textarea> component into a debounced AI
 review flow — every keystroke (debounced) gets analysed
 and the operator sees red/green annotations + a "best
 version" below the textarea. When off, the same flow
 runs only when the operator clicks the sparkle icon. --}}
                    @php $autoAiOn = (bool) ($authUser->auto_ai_summarize_enabled ?? false); @endphp
                    <form method="POST" action="{{ route('user.settings.preferences') }}"
                        class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        @csrf
                        <div class="flex items-start justify-between gap-4 mb-2">
                            <div class="flex items-start gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep inline-flex items-center justify-center shrink-0">
                                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path
                                            d="M8 2v3M8 11v3M2 8h3M11 8h3M4.2 4.2l2.1 2.1M9.7 9.7l2.1 2.1M11.8 4.2L9.7 6.3M6.3 9.7l-2.1 2.1" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="font-serif text-[20px] mb-1">{{ __('Auto AI summarize') }}</h3>
                                    <p class="text-[12px] text-ink-500 leading-snug max-w-[460px]">
                                        Get a live AI review on every message-body textarea — what's working in green,
                                        what to fix in red, plus a one-tap "best version" you can paste back. When off,
                                        the same review runs on demand from the sparkle icon inside the toolbar.
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <input type="hidden" name="auto_ai_summarize_enabled" value="0">
                                <label class="toggle">
                                    <input type="checkbox" name="auto_ai_summarize_enabled" value="1"
                                        @checked($autoAiOn)>
                                    <span class="track"></span>
                                    <span class="thumb"></span>
                                </label>
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- BRANDING -->
                <div data-pane="branding" class="space-y-5 hidden">
                    @php
                        $bp = old('brand_primary', $workspace?->brand_primary ?? '#075E54');
                        $ba = old('brand_accent', $workspace?->brand_accent ?? '#25D366');
                        $bg = old('brand_background', $workspace?->brand_background ?? '#FBFAF6');
                    @endphp
                    <form method="POST" action="{{ route('user.settings.branding') }}"
                        enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                            <h3 class="font-serif text-[20px] mb-1">{{ __('Logo & favicon') }}</h3>
                            <p class="text-[12px] text-ink-500 mb-5">
                                {{ __('Used in invoices, emails, and the customer-facing portal.') }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                {{-- Logo dropzone --}}
                                <div>
                                    <div class="text-[11.5px] font-semibold text-ink-700 mb-1.5">{{ __('Logo') }}
                                    </div>
                                    <label for="branding-logo-input"
                                        class="relative block border-2 border-dashed border-paper-200 rounded-xl p-6 text-center {{ $isOwner ? 'hover:border-wa-deep hover:bg-paper-50/60 cursor-pointer' : 'opacity-60 cursor-not-allowed' }} transition group"
                                        data-dropzone="logo">
                                        <input id="branding-logo-input" type="file" name="logo"
                                            accept="image/png,image/svg+xml,image/jpeg,image/webp"
                                            @disabled(!$isOwner) class="sr-only" />
                                        <div data-dz-preview="logo" class="hidden mb-3">
                                            <img data-dz-preview-img
                                                class="h-12 max-w-[140px] object-contain mx-auto rounded border border-paper-200 bg-paper-50 p-1" />
                                            <div class="text-[10.5px] text-ink-500 mt-1.5 font-mono"
                                                data-dz-preview-name></div>
                                        </div>
                                        @if ($workspace?->brand_logo_path)
                                            <div data-dz-current="logo" class="mb-3">
                                                <img src="{{ asset('storage/' . $workspace->brand_logo_path) }}"
                                                    alt="{{ __('Current logo') }}"
                                                    class="h-12 max-w-[140px] object-contain mx-auto rounded border border-paper-200 bg-paper-50 p-1" />
                                                <div
                                                    class="text-[10px] text-ink-500 mt-1 font-mono uppercase tracking-[0.14em]">
                                                    {{ __('Current') }}</div>
                                            </div>
                                        @else
                                            <svg viewBox="0 0 24 24"
                                                class="w-8 h-8 mx-auto text-ink-500 group-hover:text-wa-deep transition"
                                                fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M3 16l5-5 4 4 3-3 6 6" />
                                                <rect x="2.5" y="3.5" width="19" height="17" rx="2" />
                                                <circle cx="8.5" cy="9" r="1.5" />
                                            </svg>
                                        @endif
                                        <div class="mt-2 text-[12.5px] text-ink-700 font-medium">
                                            <span
                                                class="text-wa-deep group-hover:underline">{{ __('Click to upload') }}</span>
                                            or drag &amp; drop
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 mt-0.5">
                                            {{ __('PNG / SVG / JPG · max 2 MB · recommended 240×80') }}</div>
                                    </label>
                                </div>

                                {{-- Favicon dropzone --}}
                                <div>
                                    <div class="text-[11.5px] font-semibold text-ink-700 mb-1.5">{{ __('Favicon') }}
                                    </div>
                                    <label for="branding-favicon-input"
                                        class="relative block border-2 border-dashed border-paper-200 rounded-xl p-6 text-center {{ $isOwner ? 'hover:border-wa-deep hover:bg-paper-50/60 cursor-pointer' : 'opacity-60 cursor-not-allowed' }} transition group"
                                        data-dropzone="favicon">
                                        <input id="branding-favicon-input" type="file" name="favicon"
                                            accept="image/png,image/x-icon,image/vnd.microsoft.icon"
                                            @disabled(!$isOwner) class="sr-only" />
                                        <div data-dz-preview="favicon" class="hidden mb-3">
                                            <img data-dz-preview-img
                                                class="h-10 w-10 object-contain mx-auto rounded border border-paper-200 bg-paper-50 p-1" />
                                            <div class="text-[10.5px] text-ink-500 mt-1.5 font-mono"
                                                data-dz-preview-name></div>
                                        </div>
                                        @if ($workspace?->brand_favicon_path)
                                            <div data-dz-current="favicon" class="mb-3">
                                                <img src="{{ asset('storage/' . $workspace->brand_favicon_path) }}"
                                                    alt="{{ __('Current favicon') }}"
                                                    class="h-10 w-10 object-contain mx-auto rounded border border-paper-200 bg-paper-50 p-1" />
                                                <div
                                                    class="text-[10px] text-ink-500 mt-1 font-mono uppercase tracking-[0.14em]">
                                                    {{ __('Current') }}</div>
                                            </div>
                                        @else
                                            <svg viewBox="0 0 24 24"
                                                class="w-8 h-8 mx-auto text-ink-500 group-hover:text-wa-deep transition"
                                                fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="4" y="4" width="16" height="16" rx="3" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        @endif
                                        <div class="mt-2 text-[12.5px] text-ink-700 font-medium">
                                            <span
                                                class="text-wa-deep group-hover:underline">{{ __('Click to upload') }}</span>
                                            or drag &amp; drop
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 mt-0.5">32×32 PNG / ICO · max 512 KB
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                            <h3 class="font-serif text-[20px] mb-1">{{ __('Brand colors') }}</h3>
                            <p class="text-[12px] text-ink-500 mb-4">
                                {{ __('Applied to dashboard chrome, buttons, and accent strokes. Save to see them on every page.') }}
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Primary') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" value="{{ $bp }}"
                                            @disabled(!$isOwner)
                                            class="w-10 h-10 rounded-lg border border-paper-200"
                                            oninput="this.nextElementSibling.value=this.value" />
                                        <input type="text" name="brand_primary" value="{{ $bp }}"
                                            @disabled(!$isOwner) pattern="#[0-9a-fA-F]{6}"
                                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono" />
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Accent') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" value="{{ $ba }}"
                                            @disabled(!$isOwner)
                                            class="w-10 h-10 rounded-lg border border-paper-200"
                                            oninput="this.nextElementSibling.value=this.value" />
                                        <input type="text" name="brand_accent" value="{{ $ba }}"
                                            @disabled(!$isOwner) pattern="#[0-9a-fA-F]{6}"
                                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono" />
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Background') }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" value="{{ $bg }}"
                                            @disabled(!$isOwner)
                                            class="w-10 h-10 rounded-lg border border-paper-200"
                                            oninput="this.nextElementSibling.value=this.value" />
                                        <input type="text" name="brand_background" value="{{ $bg }}"
                                            @disabled(!$isOwner) pattern="#[0-9a-fA-F]{6}"
                                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono" />
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 pt-4 border-t border-paper-200 flex items-center justify-end gap-2">
                                <button type="submit" @disabled(!$isOwner)
                                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal disabled:bg-paper-200 disabled:text-ink-500 text-paper-0 text-[12px] font-semibold">{{ __('Save branding') }}</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TEAM -->
                <div data-pane="team" class="space-y-5 hidden">
                    @php
                        $seatLimit = $workspace?->effectiveLimit('user_seat_limit', null);
                        $seatsUsed = count($workspaceMembers ?? []);
                    @endphp
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <h3 class="font-serif text-[20px]">{{ __('Team members') }}</h3>
                                <p class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ $seatsUsed }}{{ $seatLimit !== null ? ' of ' . $seatLimit : '' }}
                                    seat{{ $seatsUsed === 1 ? '' : 's' }} {{ __('used') }}</p>
                            </div>
                            <a href="{{ route('user.team-inbox.members') }}"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>{{ __('Manage members') }}</a>
                        </div>
                        @if (empty($workspaceMembers))
                            <div class="px-5 py-8 text-center text-[12px] text-ink-500">
                                No members yet. <a href="{{ route('user.team-inbox.members') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('Invite the first one →') }}</a>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                    <tr>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                            {{ __('Name') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Email') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Role') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200">
                                    @foreach ($workspaceMembers as $m)
                                        @php $isOwnerRow = $workspace && (int) $workspace->owner_user_id === (int) $m->id; @endphp
                                        <tr>
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        class="w-7 h-7 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[10px] font-semibold grid place-items-center">{{ \Illuminate\Support\Str::of($m->name ?: 'X')->upper()->limit(2, '') }}</span>
                                                    <span class="font-medium">{{ $m->name }}@if ((int) $m->id === (int) $authUser->id)
                                                            <span
                                                                class="font-mono text-[10px] text-ink-500">(you)</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-2 py-2.5">{{ $m->email }}</td>
                                            <td class="px-2 py-2.5">
                                                <span
                                                    class="text-[10.5px] font-mono px-2 py-0.5 rounded-full {{ $isOwnerRow ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-50 border border-paper-200' }}">{{ $isOwnerRow ? 'Owner' : $m->role ?? 'member' }}</span>
                                            </td>
                                            <td class="px-2 py-2.5">
                                                <span
                                                    class="text-[10.5px] font-mono px-2 py-0.5 rounded-full {{ ($m->status ?? 'active') === 'invited' ? 'bg-accent-amber/15 text-[#7B5A14] border border-accent-amber/40' : 'bg-paper-50 border border-paper-200' }}">{{ $m->status ?? 'active' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- NOTIFICATIONS -->
                <div data-pane="notifications" class="space-y-5 hidden">
                    @php
                        // High-signal events the user wants to mute individually.
                        // The full list comes from Workspace::NOTIFICATION_EVENTS so
                        // the dispatcher (NotificationHelper) and the UI never drift.
                        $events = \App\Models\Workspace::NOTIFICATION_EVENTS;
                        $eventDefs = \App\Models\Workspace::DEFAULT_NOTIFICATION_CHANNELS;
                        $current = is_array($workspace?->notification_prefs) ? $workspace->notification_prefs : [];
                        $catCurrent = $current['_categories'] ?? [];

                        // Categories cover the auto-record() path (create/update/delete
                        // on Device, Contact, Campaign, etc.). One toggle row each.
                        $categories = [
                            'device' => 'Devices',
                            'contact' => 'Contacts & groups',
                            'campaign' => 'Campaigns',
                            'broadcast' => 'Broadcasts',
                            'template' => 'Templates',
                            'chat' => 'Chat messages',
                            'webhook' => 'Webhooks',
                            'billing' => 'Billing & plans',
                            'system' => 'System / other',
                        ];

                        $rowFor = function (string $key, string $channel) use ($current, $eventDefs) {
                            if (isset($current[$key][$channel])) {
                                return (bool) $current[$key][$channel];
                            }
                            return (bool) ($eventDefs[$key][$channel] ?? ($eventDefs['_default'][$channel] ?? false));
                        };
                        $catRowFor = function (string $cat, string $channel) use ($catCurrent) {
                            if (isset($catCurrent[$cat][$channel])) {
                                return (bool) $catCurrent[$cat][$channel];
                            }
                            return $channel === 'inapp'; // categories default to in-app only
                        };
                    @endphp

                    <form method="POST" action="{{ route('user.settings.notifications') }}" class="space-y-5">
                        @csrf

                        {{-- Sticky save bar --}}
                        <div
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <h3 class="font-serif text-[20px] mb-1">{{ __('Workspace notifications') }}</h3>
                                <p class="text-[12px] text-ink-500">
                                    {{ __('Toggle delivery per channel. Anything turned off here will never produce an in-app bell entry, email, or Slack message — the dispatcher checks these settings before sending.') }}
                                </p>
                                @if (!$isOwner)
                                    <p class="text-[11px] italic text-ink-500 mt-2">
                                        {{ __('Read-only — workspace owner can edit these.') }}</p>
                                @endif
                            </div>
                            <button type="submit" @disabled(!$isOwner)
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal disabled:bg-paper-200 disabled:text-ink-500 text-paper-0 text-[12px] font-semibold shrink-0">{{ __('Save preferences') }}</button>
                        </div>

                        {{-- Specific events --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200">
                                <h4 class="font-serif text-[16px]">{{ __('Specific events') }}</h4>
                                <p class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __("Individual events you'll want fine control over — disconnect alerts, SLA breaches, payment failures.") }}
                                </p>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                    <tr>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                            {{ __('Event') }}</th>
                                        <th class="font-mono text-[10px] uppercase tracking-[0.14em] py-2.5 w-24">
                                            {{ __('In-app') }}</th>
                                        <th class="font-mono text-[10px] uppercase tracking-[0.14em] py-2.5 w-24">
                                            {{ __('Email') }}</th>
                                        <th class="font-mono text-[10px] uppercase tracking-[0.14em] py-2.5 w-24">
                                            {{ __('Slack') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200">
                                    @foreach ($events as $key => $label)
                                        <tr>
                                            <td class="px-5 py-2.5 font-medium">{{ $label }}</td>
                                            <td class="text-center">
                                                <input type="hidden" name="prefs[{{ $key }}][inapp]"
                                                    value="0" />
                                                <label class="toggle"><input type="checkbox"
                                                        name="prefs[{{ $key }}][inapp]" value="1"
                                                        @checked($rowFor($key, 'inapp'))
                                                        @disabled(!$isOwner) /><span
                                                        class="track"></span><span class="thumb"></span></label>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="prefs[{{ $key }}][email]"
                                                    value="0" />
                                                <label class="toggle"><input type="checkbox"
                                                        name="prefs[{{ $key }}][email]" value="1"
                                                        @checked($rowFor($key, 'email'))
                                                        @disabled(!$isOwner) /><span
                                                        class="track"></span><span class="thumb"></span></label>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="prefs[{{ $key }}][slack]"
                                                    value="0" />
                                                <label class="toggle"><input type="checkbox"
                                                        name="prefs[{{ $key }}][slack]" value="1"
                                                        @checked($rowFor($key, 'slack'))
                                                        @disabled(!$isOwner) /><span
                                                        class="track"></span><span class="thumb"></span></label>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>

                        {{-- Category defaults --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200">
                                <h4 class="font-serif text-[16px]">{{ __('By category') }}</h4>
                                <p class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('Catch-all toggles for the auto-recorded create / update / delete events on each object type. A specific-event toggle above always overrides these.') }}
                                </p>
                            </div>
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                    <tr>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                            {{ __('Category') }}</th>
                                        <th class="font-mono text-[10px] uppercase tracking-[0.14em] py-2.5 w-24">
                                            {{ __('In-app') }}</th>
                                        <th class="font-mono text-[10px] uppercase tracking-[0.14em] py-2.5 w-24">
                                            {{ __('Email') }}</th>
                                        <th class="font-mono text-[10px] uppercase tracking-[0.14em] py-2.5 w-24">
                                            {{ __('Slack') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200">
                                    @foreach ($categories as $cat => $label)
                                        <tr>
                                            <td class="px-5 py-2.5 font-medium">{{ $label }}</td>
                                            <td class="text-center">
                                                <input type="hidden" name="cat_prefs[{{ $cat }}][inapp]"
                                                    value="0" />
                                                <label class="toggle"><input type="checkbox"
                                                        name="cat_prefs[{{ $cat }}][inapp]" value="1"
                                                        @checked($catRowFor($cat, 'inapp'))
                                                        @disabled(!$isOwner) /><span
                                                        class="track"></span><span class="thumb"></span></label>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="cat_prefs[{{ $cat }}][email]"
                                                    value="0" />
                                                <label class="toggle"><input type="checkbox"
                                                        name="cat_prefs[{{ $cat }}][email]" value="1"
                                                        @checked($catRowFor($cat, 'email'))
                                                        @disabled(!$isOwner) /><span
                                                        class="track"></span><span class="thumb"></span></label>
                                            </td>
                                            <td class="text-center">
                                                <input type="hidden" name="cat_prefs[{{ $cat }}][slack]"
                                                    value="0" />
                                                <label class="toggle"><input type="checkbox"
                                                        name="cat_prefs[{{ $cat }}][slack]" value="1"
                                                        @checked($catRowFor($cat, 'slack'))
                                                        @disabled(!$isOwner) /><span
                                                        class="track"></span><span class="thumb"></span></label>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>

                        {{-- Slack destination + channel reality check --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-4">
                            <label class="space-y-1.5 block">
                                <span
                                    class="text-[11.5px] font-semibold uppercase tracking-[0.14em] text-ink-600">{{ __('Slack incoming webhook URL') }}</span>
                                <input type="url" name="slack_webhook"
                                    value="{{ old('slack_webhook', $current['_slack_webhook'] ?? '') }}"
                                    placeholder="https://hooks.slack.com/services/T000/B000/XXXX"
                                    @disabled(!$isOwner)
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Create one in Slack → Apps → Incoming Webhooks. Events with the Slack toggle on post here.') }}</span>
                            </label>
                            <div>
                                <div class="text-[11.5px] text-ink-500 font-mono uppercase tracking-[0.14em] mb-2">
                                    {{ __('Channel status') }}</div>
                                <ul class="text-[12.5px] text-ink-700 space-y-1.5">
                                    <li class="flex items-center gap-2"><span
                                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('In-app bell — live, drives the badge in the top header.') }}
                                    </li>
                                    <li class="flex items-center gap-2"><span
                                            class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>{{ __('Email — sent when SMTP is configured in') }}
                                        <a href="{{ url('/admin/settings/mail') }}"
                                            class="text-wa-deep font-semibold hover:underline">{{ __('admin · mail') }}</a>.
                                    </li>
                                    <li class="flex items-center gap-2"><span
                                            class="w-1.5 h-1.5 rounded-full {{ !empty($current['_slack_webhook']) ? 'bg-wa-green' : 'bg-paper-200 border border-paper-200' }}"></span>{{ !empty($current['_slack_webhook']) ? __('Slack — live, posting to your webhook.') : __('Slack — add a webhook URL above to activate.') }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- AI KEYS -->
                <div data-pane="aikeys" class="space-y-5 hidden">
                    @php
                        // Platform admins own the install — never plan-gate them.
                        $allowsByok = (bool) (auth()->user()?->isAdmin())
                            || (bool) $workspace?->effectiveLimit('allow_byok_ai_keys', false);
                        $tokenLimit = $workspace?->effectiveLimit('ai_token_limit_monthly', null);
                        $tokensUsed = $workspace ? \App\Services\AiTokenMeter::usedThisMonth($workspace) : 0;
                        $usagePct =
                            $tokenLimit && $tokenLimit > 0
                                ? min(100, (int) round(($tokensUsed / $tokenLimit) * 100))
                                : 0;
                        $providers = [
                            'openai' => [
                                'name' => 'OpenAI',
                                'docs' => 'platform.openai.com/api-keys',
                                'sub' => 'GPT-5.x, GPT-4.1, GPT-4o',
                            ],
                            'anthropic' => [
                                'name' => 'Anthropic Claude',
                                'docs' => 'console.anthropic.com/settings/keys',
                                'sub' => 'Claude Opus 4.7, Sonnet 4.6, Haiku 4.5',
                            ],
                            'gemini' => [
                                'name' => 'Google Gemini',
                                'docs' => 'aistudio.google.com/app/apikey',
                                'sub' => 'Gemini 3.5 Flash, 3.1 Pro, 2.5 Pro',
                            ],
                            'mistral' => [
                                'name' => 'Mistral',
                                'docs' => 'console.mistral.ai/api-keys',
                                'sub' => 'Mistral Large, Codestral, Magistral',
                            ],
                            'elevenlabs' => [
                                'name' => 'ElevenLabs',
                                'docs' => 'elevenlabs.io/app/settings/api-keys',
                                'sub' => 'Eleven v3, multilingual, turbo',
                            ],
                        ];
                        $adminKeysByProvider = \App\Models\AdminAiKey::query()
                            ->whereIn('provider', array_keys($providers))
                            ->get()
                            ->keyBy('provider');
                    @endphp

                    {{-- Top status card — explains BYOK state + token usage --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-serif text-[20px]">{{ __('AI provider keys') }}</h3>
                                <p class="text-[12px] text-ink-500 mt-1">
                                    @if ($allowsByok)
                                        Your plan includes <strong>{{ __('Bring your own keys') }}</strong>. Add a key
                                        for any provider below and we'll use it for all AI features in this workspace.
                                    @else
                                        Your plan uses
                                        {{ brand_name() }}'s
                                        shared AI service. <a href="{{ url('/account/plans') }}"
                                            class="text-wa-deep font-semibold hover:underline">{{ __('Upgrade') }}</a>
                                        to a BYOK plan to plug in your own provider keys.
                                    @endif
                                </p>
                            </div>
                            <span
                                class="text-[10px] font-mono uppercase px-2 py-1 rounded-full {{ $allowsByok ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-700 border border-paper-200' }}">
                                {{ $allowsByok ? 'BYOK enabled' : 'Shared AI' }}
                            </span>
                        </div>
                        @if (!$allowsByok)
                            <div class="mt-4 px-4 py-3 rounded-lg bg-paper-50/70 border border-paper-200">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="font-mono text-[11.5px] text-ink-700">
                                        Tokens this month
                                        @if ($tokenLimit !== null)
                                            <span
                                                class="text-ink-900 font-semibold">{{ number_format($tokensUsed) }}</span>
                                            / <span
                                                class="text-ink-500">{{ number_format((int) $tokenLimit) }}</span>
                                        @else
                                            <span
                                                class="text-ink-900 font-semibold">{{ number_format($tokensUsed) }}</span>
                                            <span class="text-ink-500">· unlimited</span>
                                        @endif
                                    </div>
                                    @if ($tokenLimit !== null)
                                        <div class="text-[11px] font-mono text-ink-500">{{ $usagePct }}%</div>
                                    @endif
                                </div>
                                @if ($tokenLimit !== null)
                                    <div class="mt-2 h-1.5 rounded-full bg-paper-200 overflow-hidden">
                                        <div class="h-full bg-wa-deep" style="width: {{ $usagePct }}%"></div>
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div
                            class="mt-4 px-3 py-2 rounded-lg bg-paper-50/60 border border-paper-200 text-[11px] text-ink-700 flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <rect x="3" y="7" width="10" height="7" rx="1.5" />
                                <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                            </svg>
                            {{ __('Keys are encrypted at rest and never sent to other tenants.') }}
                        </div>
                    </div>

                    {{-- Provider cards — one per supported provider --}}
                    @foreach ($providers as $slug => $meta)
                        @php
                            $userKey = $byokKeys[$slug] ?? null;
                            $adminKey = $adminKeysByProvider[$slug] ?? null;
                            $hasUserKey = $userKey && !empty($userKey->api_key);
                            $effectiveSource =
                                $hasUserKey && $allowsByok ? 'workspace' : ($adminKey?->is_active ? 'admin' : 'none');
                        @endphp
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-6 py-4 border-b border-paper-200 flex items-center gap-3 flex-wrap">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-serif text-[18px] leading-tight">{{ $meta['name'] }}</h3>
                                        @if ($effectiveSource === 'workspace')
                                            <span
                                                class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 inline-flex items-center gap-1.5"><span
                                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Your key</span>
                                        @elseif ($effectiveSource === 'admin')
                                            <span
                                                class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 border border-paper-200">{{ __('Shared key') }}</span>
                                        @else
                                            <span
                                                class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/40">{{ __('Not configured') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-[11.5px] text-ink-500 mt-0.5">{{ $meta['sub'] }}</p>
                                </div>
                            </div>

                            @if ($allowsByok)
                                <form method="POST" action="{{ route('user.settings.aikeys.update', $slug) }}"
                                    class="px-6 py-5 grid grid-cols-2 gap-4">
                                    @csrf
                                    <div class="col-span-2">
                                        <label
                                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('API key') }}</label>
                                        <div class="flex items-stretch gap-2">
                                            {{-- data-no-reveal: this row has its own eye button below, so skip
                                                 the global password-reveal toggle (it was injecting a 2nd eye). --}}
                                            <input type="password" name="api_key" data-no-reveal="1"
                                                placeholder="{{ $hasUserKey ? '••••••• (leave blank to keep)' : 'Paste your ' . $meta['name'] . ' key' }}"
                                                @disabled(!$isOwner) autocomplete="off"
                                                class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep disabled:bg-paper-50" />
                                            <button type="button"
                                                onclick="(function(b){const i=b.previousElementSibling;i.type=i.type==='password'?'text':'password';})(this)"
                                                class="px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" stroke-width="1.5">
                                                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                                    <circle cx="8" cy="8" r="2" />
                                                </svg>
                                            </button>
                                            <button type="submit" @disabled(!$isOwner)
                                                class="px-3 py-2 rounded-lg bg-wa-deep hover:bg-wa-teal disabled:bg-paper-200 disabled:text-ink-500 text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                                        </div>
                                        <p class="text-[10.5px] text-ink-500 mt-1">{{ __('Generate at') }} <a
                                                class="text-wa-deep font-semibold hover:underline"
                                                href="https://{{ $meta['docs'] }}" target="_blank"
                                                rel="noopener">{{ $meta['docs'] }}</a></p>
                                    </div>
                                </form>
                                @if ($hasUserKey)
                                    <div
                                        class="px-6 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between text-[11px] text-ink-500">
                                        <span
                                            class="font-mono">{{ __('Your key is active. Workspace AI features will use it instead of the shared key.') }}</span>
                                        <form method="POST"
                                            action="{{ route('user.settings.aikeys.remove', $slug) }}"
                                            class="inline"
                                            onsubmit="return confirm('Remove your {{ $meta['name'] }} key?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" @disabled(!$isOwner)
                                                class="text-accent-coral font-semibold hover:underline disabled:opacity-50">{{ __('Remove') }}</button>
                                        </form>
                                    </div>
                                @endif
                            @else
                                <div class="px-6 py-5">
                                    <div
                                        class="px-4 py-3 rounded-lg bg-paper-50 border border-paper-200 flex items-center justify-between gap-3">
                                        <div class="text-[12px] text-ink-700">
                                            @if ($adminKey?->is_active)
                                                Using
                                                {{ brand_name() }}'s
                                                shared <span class="font-semibold">{{ $meta['name'] }}</span> key.
                                                Token usage is metered against your plan's monthly cap.
                                            @else
                                                <span class="text-accent-coral font-semibold">{{ $meta['name'] }}
                                                    isn't configured</span> by the platform admin. Contact support, or
                                                upgrade to a BYOK plan and add your own key.
                                            @endif
                                        </div>
                                        <a href="{{ url('/account/plans') }}"
                                            class="px-3 py-1.5 rounded-full border border-wa-deep text-wa-deep text-[11.5px] font-semibold hover:bg-wa-deep hover:text-paper-0 shrink-0">{{ __('Upgrade') }}</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <!-- Where keys are used -->
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-3">{{ __('Where keys are used') }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[12.5px]">
                            <a href="{{ url('/flows/builder') }}"
                                class="border border-paper-200 rounded-xl p-3 hover:border-wa-deep transition flex items-start gap-2.5">
                                <span
                                    class="w-8 h-8 rounded-lg bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.5">
                                        <circle cx="3.5" cy="8" r="1.8" />
                                        <circle cx="12.5" cy="3.5" r="1.8" />
                                        <circle cx="12.5" cy="12.5" r="1.8" />
                                        <path d="M5 7l6-3M5 9l6 3" />
                                    </svg></span>
                                <div>
                                    <div class="font-semibold">{{ __('AI Assist node') }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ __('in flow builder') }}</div>
                                </div>
                            </a>
                            <a href="{{ url('/flows/builder') }}"
                                class="border border-paper-200 rounded-xl p-3 hover:border-wa-deep transition flex items-start gap-2.5">
                                <span
                                    class="w-8 h-8 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.5">
                                        <path d="M8 1l1.5 4 4 1.5-4 1.5L8 12l-1.5-4-4-1.5 4-1.5z" />
                                    </svg></span>
                                <div>
                                    <div class="font-semibold">{{ __('Generate flow with AI') }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ __('prompt → flow') }}</div>
                                </div>
                            </a>
                            <a href="{{ url('/team-inbox') }}"
                                class="border border-paper-200 rounded-xl p-3 hover:border-wa-deep transition flex items-start gap-2.5">
                                <span
                                    class="w-8 h-8 rounded-lg bg-[#FFF4E0] text-[#7B5A14] grid place-items-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.5">
                                        <rect x="2" y="3" width="12" height="9" rx="1.5" />
                                        <path d="M5 6h6M5 9h4" />
                                    </svg></span>
                                <div>
                                    <div class="font-semibold">{{ __('Smart canned replies') }}</div>
                                    <div class="text-[10.5px] text-ink-500">{{ __('in team inbox') }}</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- SECURITY -->
                <div data-pane="security" class="space-y-5 hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Two-factor authentication') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-4">
                            {{ __('Require a 6-digit code from your authenticator app on every login.') }}</p>

                        @if ($authUser->two_factor_enabled)
                            <div
                                class="px-4 py-3 rounded-lg bg-wa-mint border border-wa-green/40 text-[12.5px] text-wa-deep mb-4 flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M3 8.5l3 3 7-7" />
                                </svg>
                                Two-factor is enabled (since
                                {{ $authUser->two_factor_confirmed_at?->format('M j, Y') }}).
                            </div>
                            <form method="POST" action="{{ route('user.settings.2fa.disable') }}"
                                class="space-y-3" onsubmit="return confirm('Disable two-factor authentication?')">
                                @csrf
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 block">{{ __('Confirm with password') }}</label>
                                <input type="password" name="current_password" required
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono" />
                                @error('current_password')
                                    <p class="text-[11px] text-accent-coral">{{ $message }}</p>
                                @enderror
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-accent-coral hover:bg-[#C56B4F] text-paper-0 text-[12px] font-semibold">{{ __('Disable 2FA') }}</button>
                            </form>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_240px] gap-6 items-start">
                                <form method="POST" action="{{ route('user.settings.2fa.enable') }}"
                                    class="space-y-3">
                                    @csrf
                                    <ol class="text-[12.5px] text-ink-700 space-y-1 list-decimal pl-4">
                                        <li>{{ __('Open Google Authenticator, 1Password, or Authy.') }}</li>
                                        <li>{{ __('Scan the QR code (or paste the secret below).') }}</li>
                                        <li>{{ __('Type the 6-digit code your authenticator shows.') }}</li>
                                    </ol>
                                    <div
                                        class="font-mono text-[11px] text-ink-500 bg-paper-50 px-3 py-2 rounded-lg border border-paper-200 break-all">
                                        Secret: <span class="text-ink-900">{{ $twoFactorSecret }}</span>
                                    </div>
                                    <label class="text-[11.5px] font-semibold text-ink-700 block">6-digit code</label>
                                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*"
                                        maxlength="6" autocomplete="one-time-code" required
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[16px] font-mono tracking-[0.3em] text-center" />
                                    @error('code')
                                        <p class="text-[11px] text-accent-coral">{{ $message }}</p>
                                    @enderror
                                    <button type="submit"
                                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Enable 2FA') }}</button>
                                </form>
                                <div>
                                    <div class="text-[11px] font-semibold text-ink-700 mb-2">
                                        {{ __('Scan this QR code') }}</div>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($otpAuthUrl ?? '') }}"
                                        alt="2FA QR code" class="border border-paper-200 rounded-lg bg-white p-1"
                                        width="180" height="180" />
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div
                            class="px-6 py-4 border-b border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <h3 class="font-serif text-[20px]">{{ __('Active sessions') }}</h3>
                                <p class="text-[12px] text-ink-500 mt-0.5">Devices signed in within the last
                                    {{ (int) (config('session.lifetime', 120) / 60) }}h. Same browser collapsed into
                                    one row.</p>
                            </div>
                            <form method="POST" action="{{ route('user.settings.sessions.revoke-others') }}"
                                class="inline" onsubmit="return confirm('Sign out everywhere except here?')">
                                @csrf
                                <button type="submit"
                                    class="px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral text-[11.5px] font-semibold hover:bg-accent-coral/10">{{ __('Sign out of all others') }}</button>
                            </form>
                        </div>
                        @if ($sessions->total() === 0)
                            <p class="px-6 py-8 text-center text-[12px] text-ink-500 italic">
                                {{ __('No active sessions. Switch SESSION_DRIVER to') }} <code>database</code> in your
                                .env if you expect to see them.</p>
                        @else
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px]">
                                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                    <tr>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-5 py-2.5">
                                            {{ __('Device') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                            {{ __('IP address') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                            {{ __('Last active') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-3 py-2.5">
                                            {{ __('Sessions') }}</th>
                                        <th class="px-5 py-2.5 w-20"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200">
                                    @foreach ($sessions as $s)
                                        @php
                                            $isCurrent = !empty($s->is_current);
                                            $ua = (string) ($s->user_agent ?? '');
                                            $isMobile = (bool) preg_match('/iPhone|Android|iPad/i', $ua);
                                            $browser = preg_match('/Edg\//i', $ua)
                                                ? 'Edge'
                                                : (preg_match('/Chrome/i', $ua)
                                                    ? 'Chrome'
                                                    : (preg_match('/Firefox/i', $ua)
                                                        ? 'Firefox'
                                                        : (preg_match('/Safari/i', $ua)
                                                            ? 'Safari'
                                                            : 'Browser')));
                                            $os = preg_match('/Windows/i', $ua)
                                                ? 'Windows'
                                                : (preg_match('/Mac OS/i', $ua)
                                                    ? 'macOS'
                                                    : (preg_match('/Linux/i', $ua)
                                                        ? 'Linux'
                                                        : (preg_match('/Android/i', $ua)
                                                            ? 'Android'
                                                            : (preg_match('/iPhone|iPad/i', $ua)
                                                                ? 'iOS'
                                                                : 'Unknown'))));
                                        @endphp
                                        <tr class="{{ $isCurrent ? 'bg-wa-mint/30' : '' }}">
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <span
                                                        class="w-8 h-8 rounded-lg bg-paper-50 grid place-items-center shrink-0">
                                                        @if ($isMobile)
                                                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                                stroke="currentColor" stroke-width="1.5">
                                                                <rect x="3.5" y="2" width="9" height="12"
                                                                    rx="1.5" />
                                                                <circle cx="8" cy="11.5" r="0.8" />
                                                            </svg>
                                                        @else
                                                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                                stroke="currentColor" stroke-width="1.5">
                                                                <rect x="2" y="3" width="12" height="9"
                                                                    rx="1" />
                                                                <path d="M5 14h6" />
                                                            </svg>
                                                        @endif
                                                    </span>
                                                    <div>
                                                        <div class="font-semibold text-[12.5px]">{{ $browser }}
                                                            on {{ $os }}</div>
                                                        @if ($isCurrent)
                                                            <span
                                                                class="text-[10px] font-mono uppercase tracking-[0.14em] px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep border border-wa-green/40">{{ __('this session') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 font-mono text-[11px] text-ink-700">
                                                {{ $s->ip_address ?? '—' }}</td>
                                            <td class="px-3 py-3 font-mono text-[11px] text-ink-500"
                                                title="{{ \Carbon\Carbon::createFromTimestamp($s->last_activity)->toDateTimeString() }}">
                                                {{ \Carbon\Carbon::createFromTimestamp($s->last_activity)->diffForHumans() }}
                                            </td>
                                            <td class="px-3 py-3 font-mono text-[11px] text-ink-500">
                                                @if (($s->session_count ?? 1) > 1)
                                                    <span
                                                        title="{{ __('Login regenerates the session ID. Same browser, multiple rows merged.') }}">{{ $s->session_count }}
                                                        {{ __('merged') }}</span>
                                                @else
                                                    1
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                @if (!$isCurrent)
                                                    <form method="POST"
                                                        action="{{ route('user.settings.sessions.revoke', $s->id) }}"
                                                        class="inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit"
                                                            class="text-[11.5px] text-accent-coral font-semibold hover:underline">{{ __('Revoke') }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                            @if ($sessions->hasPages())
                                <div
                                    class="px-5 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between text-[11.5px] text-ink-500">
                                    <div class="font-mono">
                                        Showing {{ $sessions->firstItem() }}–{{ $sessions->lastItem() }} of
                                        {{ $sessions->total() }}
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        @if ($sessions->onFirstPage())
                                            <span
                                                class="px-3 py-1 rounded-full border border-paper-200 text-ink-500 cursor-not-allowed">{{ __('Prev') }}</span>
                                        @else
                                            <a href="{{ $sessions->previousPageUrl() }}#tab=security"
                                                class="px-3 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-ink-900 font-semibold">Prev</a>
                                        @endif
                                        <span class="font-mono px-2">{{ $sessions->currentPage() }} /
                                            {{ $sessions->lastPage() }}</span>
                                        @if ($sessions->hasMorePages())
                                            <a href="{{ $sessions->nextPageUrl() }}#tab=security"
                                                class="px-3 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-ink-900 font-semibold">Next</a>
                                        @else
                                            <span
                                                class="px-3 py-1 rounded-full border border-paper-200 text-ink-500 cursor-not-allowed">{{ __('Next') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- API & WEBHOOKS -->
                <div data-pane="api" class="space-y-5 hidden">
                    @php
                        $sheetsKeySuffix = $authUser->sheets_api_key_suffix ?? null;
                        $sheetsKeyCreated = $authUser->sheets_api_key_created_at ?? null;
                        $sheetsKeyLastUsed = $authUser->sheets_api_key_last_used_at ?? null;
                        $outboundWebhookCount = 0;
                        $inboundWebhookCount = 0;
                        try {
                            // Both tables are workspace-scoped (outbound_webhooks has
                            // no user_id column at all, hence the old query silently
                            // returned 0). Counting by workspace shows accurate totals
                            // and stays consistent when teammates switch workspaces.
                            $wsId = (int) ($authUser->current_workspace_id ?? 0);
                            $outboundWebhookCount = \Illuminate\Support\Facades\DB::table('outbound_webhooks')
                                ->where('workspace_id', $wsId)
                                ->count();
                            $inboundWebhookCount = \Illuminate\Support\Facades\DB::table('webhooks')
                                ->where('workspace_id', $wsId)
                                ->count();
                        } catch (\Throwable $e) {
                        }
                    @endphp

                    {{-- Sheets add-on API key (the ONLY personal API key WaDesk currently exposes) --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div
                            class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <h3 class="font-serif text-[20px]">{{ __('Google Sheets API key') }}</h3>
                                <p class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('Used by the :app Sheets add-on to push contacts and message rows into a sheet. The only personal token :app exposes today.', ['app' => brand_name()]) }}
                                </p>
                            </div>
                            <a href="{{ url('/account#sheets-api') }}"
                                class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Manage in account') }}</a>
                        </div>
                        <div class="px-5 py-4">
                            @if ($sheetsKeySuffix)
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span
                                        class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40">{{ __('Active') }}</span>
                                    <code
                                        class="font-mono text-[12px] bg-paper-50 px-2 py-1 rounded border border-paper-200">wsn_live_••••{{ $sheetsKeySuffix }}</code>
                                    <span class="text-[11px] text-ink-500 font-mono">
                                        generated {{ $sheetsKeyCreated?->diffForHumans() ?? '—' }}
                                        · last used
                                        {{ $sheetsKeyLastUsed ? $sheetsKeyLastUsed->diffForHumans() : 'never' }}
                                    </span>
                                </div>
                            @else
                                <div
                                    class="flex items-center justify-between gap-4 px-4 py-3 rounded-lg bg-paper-50 border border-paper-200">
                                    <div class="text-[12.5px] text-ink-700">
                                        No Sheets API key generated. Create one in <a
                                            href="{{ url('/account#sheets-api') }}"
                                            class="text-wa-deep font-semibold hover:underline">{{ __('your account → Sheets add-on') }}</a>
                                        to start pushing data into Google Sheets.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- General REST API status --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('General REST API') }}</h3>
                        <p class="text-[12.5px] text-ink-500">
                            {{ __("A scoped REST API (with `sk_live_*` style keys, rate-limits and per-key audit) isn't shipped yet. Until it lands you can drive :app through:", ['app' => brand_name()]) }}
                        </p>
                        <ul class="mt-3 space-y-1.5 text-[12.5px] text-ink-700 list-disc pl-5">
                            <li>{{ __('The') }} <strong>{{ __('Sheets add-on key') }}</strong> above —
                                read/write contacts and messages.</li>
                            <li>{{ __('The') }} <a href="{{ url('/webhooks') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('webhooks') }}</a> below
                                — receive every send/read/reply event as JSON.</li>
                            <li>{{ __('The') }} <a href="{{ url('/integrations') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('integrations') }}</a>
                                page — pre-built Shopify, WooCommerce, HubSpot, Calendar pipes.</li>
                        </ul>
                    </div>

                    {{-- Webhooks summary --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div
                            class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <h3 class="font-serif text-[20px]">{{ __('Webhooks') }}</h3>
                                <p class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('Push delivery, read, and reply events to any HTTPS endpoint.') }}</p>
                            </div>
                            <a href="{{ url('/webhooks') }}"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Manage webhooks') }}</a>
                        </div>
                        <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-[12.5px]">
                            <div class="border border-paper-200 rounded-xl p-4">
                                <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Outbound endpoints') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1">{{ $outboundWebhookCount }}
                                </div>
                                <div class="text-[11px] text-ink-500 mt-1">
                                    {{ __(':app → your server (CRM hooks, send / reply events).', ['app' => brand_name()]) }}
                                </div>
                            </div>
                            <div class="border border-paper-200 rounded-xl p-4">
                                <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Inbound webhooks') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1">{{ $inboundWebhookCount }}
                                </div>
                                <div class="text-[11px] text-ink-500 mt-1">
                                    {{ __('Your server → :app (incoming WABA / Unofficial API deliveries).', ['app' => brand_name()]) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DATA -->
                <div data-pane="data" class="space-y-5 hidden">
                    @php
                        // WaDesk legacy model: contacts.user_id (per-user), conversations.workspace_id.
                        // Workspace contacts = contacts whose user_id belongs to any
                        // user in this workspace (via workspace_user pivot).
                        $contactCount = 0;
                        $conversationCount = 0;
                        $messageCount = 0;
                        if ($workspace) {
                            try {
                                $wsUserIds = \Illuminate\Support\Facades\DB::table('workspace_user')
                                    ->where('workspace_id', $workspace->id)
                                    ->pluck('user_id');
                                $contactCount = \App\Models\Contact::whereIn('user_id', $wsUserIds)->count();
                                $conversationCount = \App\Models\Conversation::where(
                                    'workspace_id',
                                    $workspace->id,
                                )->count();
                                $messageCount = \Illuminate\Support\Facades\DB::table('inbox_messages')
                                    ->whereIn(
                                        'conversation_id',
                                        \App\Models\Conversation::where('workspace_id', $workspace->id)->pluck('id'),
                                    )
                                    ->count();
                            } catch (\Throwable $e) {
                            }
                        }
                    @endphp
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Data export') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-4">
                            {{ __('Download your workspace data as CSV. Each link streams a fresh export.') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <a href="{{ route('user.settings.export', 'contacts') }}"
                                class="border border-paper-200 rounded-xl p-4 text-left hover:border-wa-deep transition block">
                                <div class="font-semibold text-[13px]">{{ __('Contacts') }}</div>
                                <div class="text-[11px] text-ink-500">{{ number_format($contactCount) }}
                                    record{{ $contactCount === 1 ? '' : 's' }} · CSV</div>
                            </a>
                            <a href="{{ route('user.settings.export', 'conversations') }}"
                                class="border border-paper-200 rounded-xl p-4 text-left hover:border-wa-deep transition block">
                                <div class="font-semibold text-[13px]">{{ __('Conversations') }}</div>
                                <div class="text-[11px] text-ink-500">{{ number_format($conversationCount) }}
                                    thread{{ $conversationCount === 1 ? '' : 's' }} · CSV</div>
                            </a>
                            <a href="{{ route('user.settings.export', 'messages') }}"
                                class="border border-paper-200 rounded-xl p-4 text-left hover:border-wa-deep transition block">
                                <div class="font-semibold text-[13px]">{{ __('Messages') }}</div>
                                <div class="text-[11px] text-ink-500">{{ number_format($messageCount) }}
                                    message{{ $messageCount === 1 ? '' : 's' }} · CSV</div>
                            </a>
                        </div>
                    </div>

                    <div class="bg-paper-0 border-2 border-accent-coral/40 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1 text-accent-coral">{{ __('Delete workspace') }}</h3>
                        <p class="text-[12.5px] text-ink-700 mb-4">
                            {{ __('Permanently delete this workspace, all conversations, automations, and billing history.') }}
                            <strong>{{ __('Cannot be undone.') }}</strong></p>
                        @if ($isOwner)
                            <form method="POST" action="{{ route('user.settings.workspace.destroy') }}"
                                class="space-y-3"
                                onsubmit="return confirm('Type the workspace name to confirm. This is irreversible.')">
                                @csrf @method('DELETE')
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 block mb-1">{{ __('Type the workspace name:') }}
                                        <span class="font-mono">{{ $workspace?->name }}</span></label>
                                    <input type="text" name="confirm_name" required
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono" />
                                    @error('confirm_name')
                                        <p class="text-[11px] text-accent-coral mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label
                                        class="text-[11.5px] font-semibold text-ink-700 block mb-1">{{ __('Confirm with password') }}</label>
                                    <input type="password" name="current_password" required
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono" />
                                    @error('current_password')
                                        <p class="text-[11px] text-accent-coral mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-accent-coral hover:bg-[#C56B4F] text-paper-0 text-[12px] font-semibold">{{ __('Delete workspace permanently') }}</button>
                            </form>
                        @else
                            <p class="text-[12px] text-ink-500 italic">
                                {{ __('Only the workspace owner can delete the workspace.') }}</p>
                        @endif
                    </div>
                </div>

                <!-- APPEARANCE -->
                <div data-pane="appearance" class="space-y-5 hidden">
                    @php $theme = $authUser->theme_preference ?? 'paper'; @endphp
                    <form method="POST" action="{{ route('user.settings.appearance') }}"
                        class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        @csrf
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <h3 class="font-serif text-[20px] mb-1">{{ __('Theme') }}</h3>
                                <p class="text-[12px] text-ink-500">
                                    {{ __('Pick how :app looks on this account.', ['app' => brand_name()]) }}
                                </p>
                            </div>
                            <button type="submit"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="theme_preference" value="paper" class="sr-only peer"
                                    @checked($theme === 'paper') />
                                <div
                                    class="border-2 border-paper-200 rounded-xl p-4 peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 transition">
                                    <div class="h-16 rounded bg-paper-50 border border-paper-200 mb-2"></div>
                                    <div class="text-[12px] font-semibold text-center">{{ __('Paper (default)') }}
                                    </div>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="theme_preference" value="bright" class="sr-only peer"
                                    @checked($theme === 'bright') />
                                <div
                                    class="border-2 border-paper-200 rounded-xl p-4 peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 transition">
                                    <div class="h-16 rounded bg-white border border-paper-200 mb-2"></div>
                                    <div class="text-[12px] font-semibold text-center">{{ __('Bright white') }}</div>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="theme_preference" value="dark" class="sr-only peer"
                                    @checked($theme === 'dark') />
                                <div
                                    class="border-2 border-paper-200 rounded-xl p-4 peer-checked:border-wa-deep peer-checked:bg-wa-mint/30 transition">
                                    <div class="h-16 rounded bg-ink-900 mb-2"></div>
                                    <div class="text-[12px] font-semibold text-center">{{ __('Dark (beta)') }}</div>
                                </div>
                            </label>
                        </div>
                    </form>

                </div>

            </section>
        </div>
    </main>

    <div id="toast"
        style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#0B1F1C;color:#FBFAF6;padding:8px 14px;border-radius:999px;font-size:12px;font-weight:500;box-shadow:0 12px 28px -10px rgba(0,0,0,0.4);opacity:0;pointer-events:none;transition:opacity .18s, transform .18s;z-index:60">
    </div>

</x-layouts.user>
