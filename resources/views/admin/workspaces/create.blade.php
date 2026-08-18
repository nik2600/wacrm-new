<x-layouts.admin :title="__('Admin · Create workspace')" admin-key="workspaces" page="admin-workspaces-create">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/workspaces') }}" class="hover:text-ink-900">{{ __('Workspaces') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ url('/admin/workspaces') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="wsForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Create workspace
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Workspaces · New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[32px] lg:text-[36px] leading-[1.0]">{{ __('Create a new') }}
                <span class="italic text-wa-deep">{{ __('workspace') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('Provision a workspace on behalf of a customer. The owner gets a magic-link login email and the plan starts immediately.') }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-6 lg:px-7 pb-7">
        @if ($errors->any())
            <div
                class="mb-4 rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="wsForm" action="{{ route('admin.workspaces.store') }}" method="POST"
            class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            @csrf

            <!-- Basics -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Workspace basics') }}</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="ws-name">{{ __('Workspace name') }} <span class="text-accent-coral">*</span></label>
                        <input id="ws-name" name="name" type="text" value="{{ old('name') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('e.g. Bloomly') }}" required>
                    </div>
                    @php
                        // Live host from APP_URL → used as the slug-subdomain hint.
                        $appHost = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
                    @endphp
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="ws-slug">{{ __('URL slug') }}</label>
                        <div
                            class="flex items-center border border-paper-200 rounded-lg bg-white focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10">
                            <input id="ws-slug" name="slug" type="text" value="{{ old('slug') }}"
                                class="flex-1 px-3 py-[7px] bg-transparent text-[12.5px] focus:outline-none"
                                placeholder="{{ __('auto from name') }}">
                            <span class="px-3 text-[11px] font-mono text-ink-500">.{{ $appHost }}</span>
                        </div>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __("Used as the workspace's default subdomain. Leave blank to auto-generate from the name.") }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="ws-domain">{{ __('Custom domain') }}</label>
                        <input id="ws-domain" name="custom_domain" type="text" value="{{ old('custom_domain') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('e.g. crm.acme.com') }}">
                        <button type="button" id="dns-help-btn"
                            class="text-[10.5px] text-wa-deep hover:underline mt-1 inline-flex items-center gap-1">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 7v4M8 5v.01" />
                            </svg>
                            How do I connect a custom domain?
                        </button>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="ws-industry">{{ __('Industry') }}</label>
                        @php $industries = ['Retail · D2C', 'Healthcare', 'Food & delivery', 'Education', 'Real estate', 'Finance', 'SaaS', 'Logistics', 'Other']; @endphp
                        <select id="ws-industry" name="industry"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">— select industry —</option>
                            @foreach ($industries as $ind)
                                <option value="{{ $ind }}" @selected(old('industry') === $ind)>{{ $ind }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="country">{{ __('Country') }}</label>
                            <select id="country" name="country" data-value="{{ old('country') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">— select country —</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="timezone">{{ __('Timezone') }}</label>
                            <select id="timezone" name="timezone"
                                data-value="{{ old('timezone', 'Asia/Kolkata') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">— select timezone —</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Workspace logo') }}</label>
                        <div
                            class="flex items-center gap-3 px-3 py-2.5 border border-dashed border-paper-300 rounded-lg bg-paper-0 hover:border-wa-deep transition cursor-pointer">
                            <span class="w-12 h-12 rounded-lg bg-paper-100 grid place-items-center text-ink-500"><svg
                                    viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                    <circle cx="6" cy="7" r="1.5" />
                                    <path d="M2 11l4-3 4 3 4-2" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-semibold">{{ __('Upload logo') }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono">
                                    {{ __('PNG/SVG · 256×256 recommended') }}</div>
                            </div>
                            <span
                                class="text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep">{{ __('Browse') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Owner & plan -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Owner & plan') }}</span>
                </div>
                <div class="space-y-3">
                    @php $ownerMode = old('owner_mode', 'existing'); @endphp
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Owner') }}</label>
                        <div class="flex items-center gap-2">
                            <label
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <input type="radio" name="owner_mode" value="existing" class="sr-only"
                                    @checked($ownerMode === 'existing') data-mode-existing>
                                <span class="text-[12px] font-medium">{{ __('Existing user') }}</span>
                            </label>
                            <label
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <input type="radio" name="owner_mode" value="invite" class="sr-only"
                                    @checked($ownerMode === 'invite') data-mode-invite>
                                <span class="text-[12px] font-medium">{{ __('Invite by email') }}</span>
                            </label>
                        </div>
                    </div>
                    <div data-owner-existing class="@if ($ownerMode !== 'existing') hidden @endif">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="owner">{{ __('Owner user') }} <span class="text-accent-coral">*</span></label>
                        <select id="owner" name="owner_user_id"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">— select existing user —</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(old('owner_user_id') == $u->id)>{{ $u->name }}
                                    · {{ $u->email }}</option>
                            @endforeach
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Owner has full control over the workspace.') }}</div>
                    </div>
                    <div data-owner-invite class="@if ($ownerMode !== 'invite') hidden @endif space-y-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="invite-name">{{ __('Owner name') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="invite-name" name="invite_name" type="text"
                                value="{{ old('invite_name') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Full name') }}">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="invite-email">{{ __('Owner email') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="invite-email" name="invite_email" type="email"
                                value="{{ old('invite_email') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="owner@company.com">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __("We'll create the user, generate a random password, and email them a magic-link login. They'll be asked to set a new password on first sign-in.") }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Plan') }}</label>
                        <div class="space-y-2">
                            <label
                                class="block px-3 py-2.5 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2"><input type="radio" name="plan"
                                            value="" class="rounded-full border-paper-300 text-wa-deep"
                                            @checked(old('plan', '') === '')><span
                                            class="font-semibold text-[12.5px]">{{ __('No plan / Free') }}</span></span>
                                    <span class="font-mono text-[11px] text-ink-500">$0</span>
                                </div>
                            </label>
                            @foreach ($plans as $p)
                                <label
                                    class="block px-3 py-2.5 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center gap-2">
                                            <input type="radio" name="plan" value="{{ $p->id }}"
                                                class="rounded-full border-paper-300 text-wa-deep"
                                                @checked((string) old('plan') === (string) $p->id)>
                                            <span class="font-semibold text-[12.5px]">{{ $p->pname }}</span>
                                        </span>
                                        @php
                                            $unit = $p->plan_unit ?: 'month';
                                            // chargeableAmount() = offer price when set, else plan_amount
                                            // (what the workspace actually pays). formatIn() prints it in the
                                            // package's OWN currency symbol — the old hardcoded "$" showed a
                                            // dollar sign on INR plans.
                                            $priceLabel = $p->free
                                                ? 'Free'
                                                : \App\Support\FormatSettings::formatIn($p->chargeableAmount(), $p->currency) .
                                                    ' / ' .
                                                    strtolower($unit);
                                        @endphp
                                        <span
                                            class="font-mono text-[11px] {{ $p->free ? 'text-ink-500' : 'text-wa-deep' }}">{{ $priceLabel }}</span>
                                    </div>
                                    <div class="text-[10.5px] text-ink-500 mt-1 ml-6">
                                        @php
                                            $bits = [];
                                            if ($p->monthly_messages_limit) {
                                                $bits[] = number_format($p->monthly_messages_limit) . ' msgs/mo';
                                            }
                                            if ($p->device_limit) {
                                                $bits[] = $p->device_limit . ' devices';
                                            }
                                            if ($p->user_seat_limit) {
                                                $bits[] = $p->user_seat_limit . ' users';
                                            }
                                        @endphp
                                        {{ $bits ? implode(' · ', $bits) : '—' }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="billing-cycle">{{ __('Billing cycle') }}</label>
                        @php $cycles = ['monthly' => 'Monthly · recurring', 'quarterly' => 'Quarterly · recurring', 'annual' => 'Annual · recurring (15% off)', 'custom' => 'Custom contract', 'trial' => 'Trial · no billing']; @endphp
                        <select id="billing-cycle" name="billing_cycle"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            @foreach ($cycles as $k => $label)
                                <option value="{{ $k }}" @selected(old('billing_cycle', 'monthly') === $k)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Limits & admin overrides -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Limits & admin overrides') }}</span>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="cap-monthly">{{ __('Monthly cap') }}</label>
                            <input id="cap-monthly" name="cap_monthly_messages" type="number"
                                value="{{ old('cap_monthly_messages') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('inherit from plan') }}">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Messages/month. Blank = inherit from plan.') }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="cap-daily">{{ __('Daily cap') }}</label>
                            <input id="cap-daily" name="cap_daily_messages" type="number"
                                value="{{ old('cap_daily_messages') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('optional') }}">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="cap-devices">{{ __('Max devices') }}</label>
                            <input id="cap-devices" name="cap_devices" type="number"
                                value="{{ old('cap_devices') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('inherit from plan') }}">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="cap-users">{{ __('Max users') }}</label>
                            <input id="cap-users" name="cap_users" type="number" value="{{ old('cap_users') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('inherit from plan') }}">
                        </div>
                    </div>
                    <div class="space-y-2 pt-1">
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Skip onboarding email') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('No welcome / setup email to owner') }}</span>
                            </span>
                            <input type="hidden" name="skip_onboarding_email" value="0">
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input class="peer opacity-0 w-0 h-0" type="checkbox" name="skip_onboarding_email"
                                    value="1" @checked(old('skip_onboarding_email') === '1')>
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Bill to platform credit') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Comp the first month from :app', ['app' => brand_name()]) }}</span>
                            </span>
                            <input type="hidden" name="bill_to_platform_credit" value="0">
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input class="peer opacity-0 w-0 h-0" type="checkbox" name="bill_to_platform_credit"
                                    value="1" @checked(old('bill_to_platform_credit') === '1')>
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Pre-seed sample data') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Demo contacts + templates') }}</span>
                            </span>
                            <input type="hidden" name="pre_seed_sample_data" value="0">
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input class="peer opacity-0 w-0 h-0" type="checkbox" name="pre_seed_sample_data"
                                    value="1" @checked(old('pre_seed_sample_data', '1') === '1')>
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="admin-note">{{ __('Admin note (internal)') }}</label>
                        <textarea id="admin-note" name="admin_note" rows="3"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('How was this workspace acquired? Sales lead / referral / partner deal?') }}">{{ old('admin_note') }}</textarea>
                    </div>
                    <div
                        class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-snug">
                        <b class="text-ink-900">Heads up:</b> creating a workspace bills the owner from day 1 unless
                        billing cycle is Trial. Annual contracts go to the contract review queue.
                    </div>
                </div>
            </div>

        </form>

        {{-- DNS-setup modal — opened by the "How do I connect a custom domain?" button. --}}
        <div id="dns-help-modal"
            class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-ink-900/40">
            <div
                class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card max-w-[560px] w-full max-h-[85vh] overflow-y-auto">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            {{ __('Custom domain · DNS setup') }}</div>
                        <h2 class="font-serif text-[20px] mt-1">
                            {{ __('Point your domain to :app', ['app' => brand_name()]) }}
                        </h2>
                    </div>
                    <button type="button" data-dns-close
                        class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-500">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 3l10 10M13 3 3 13" />
                        </svg>
                    </button>
                </div>
                <div class="p-5 text-[12.5px] text-ink-700 leading-relaxed space-y-4">
                    <p>{{ __('Add') }} <b>one</b> of these records on your domain registrar (GoDaddy, Cloudflare,
                        Namecheap, Route 53…) for the domain you typed above:</p>

                    <div class="rounded-xl border border-paper-200 overflow-hidden">
                        <div class="px-4 py-2.5 bg-paper-50 border-b border-paper-200 font-semibold text-[11.5px]">
                            {{ __('Option 1 — for a subdomain (e.g.') }} <code
                                class="bg-paper-100 px-1 rounded">crm.acme.com</code>)</div>
                        <div class="overflow-x-auto">
                        <table class="w-full text-[11.5px] min-w-[360px]">
                            <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                                <tr>
                                    <th class="text-left px-4 py-2">{{ __('Type') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('Name') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('Value') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('TTL') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="px-4 py-2 font-mono">{{ __('CNAME') }}</td>
                                    <td class="px-4 py-2 font-mono">{{ __('crm') }}</td>
                                    <td class="px-4 py-2 font-mono">cnames.{{ $appHost }}</td>
                                    <td class="px-4 py-2 font-mono">3600</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <div class="rounded-xl border border-paper-200 overflow-hidden">
                        <div class="px-4 py-2.5 bg-paper-50 border-b border-paper-200 font-semibold text-[11.5px]">
                            {{ __('Option 2 — for an apex / root domain (e.g.') }} <code
                                class="bg-paper-100 px-1 rounded">acme.com</code>)</div>
                        <div class="overflow-x-auto">
                        <table class="w-full text-[11.5px] min-w-[360px]">
                            <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                                <tr>
                                    <th class="text-left px-4 py-2">{{ __('Type') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('Name') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('Value') }}</th>
                                    <th class="text-left px-4 py-2">{{ __('TTL') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="px-4 py-2 font-mono">A</td>
                                    <td class="px-4 py-2 font-mono">@</td>
                                    <td class="px-4 py-2 font-mono">
                                        {{ request()->server('SERVER_ADDR', '203.0.113.10') }}</td>
                                    <td class="px-4 py-2 font-mono">3600</td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <ol class="list-decimal pl-5 space-y-1 text-[12px]">
                        <li>{{ __('Add the record at your DNS provider and save.') }}</li>
                        <li>{{ __('Wait 1-30 minutes for DNS propagation.') }}</li>
                        <li>{{ __('Hit') }} <b>Save</b> on this form —
                            {{ brand_name() }} will check
                            the CNAME and flag it <b>verified</b> on the workspace detail page.</li>
                        <li>{{ __("Once verified, SSL is auto-provisioned via Let's Encrypt (no action needed).") }}
                        </li>
                    </ol>
                    <div class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 text-[11.5px]">
                        <b>Tip:</b> if the verification fails after 30 min, try <code
                            class="bg-paper-100 px-1 rounded">dig CNAME your-domain</code> on a terminal — the value
                        should match exactly.
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-paper-200 flex justify-end">
                    <button type="button" data-dns-close
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal">{{ __('Got it') }}</button>
                </div>
            </div>
        </div>
    </main>

</x-layouts.admin>
