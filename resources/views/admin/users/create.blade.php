<x-layouts.admin :title="__('Admin · Add user')" admin-key="users" page="admin-users-create">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/users') }}" class="hover:text-ink-900">{{ __('Users') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ url('/admin/users') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="userForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Create user
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Users · New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[36px] leading-[1.0]">{{ __('Add a') }} <span
                    class="italic text-wa-deep">{{ __('user') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __('Create a new account and assign it to a workspace + role. The user will receive an email with a one-time login link.') }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">
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
        <form id="userForm" action="{{ route('admin.users.store') }}" method="POST"
            class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            @csrf

            <!-- Personal details -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Personal details') }}</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="name">{{ __('Full name') }} <span class="text-accent-coral">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Enter full name') }}" required>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="email">{{ __('Email') }} <span class="text-accent-coral">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="user@example.com" required>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Used for login and notifications.') }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="mobile">{{ __('Mobile') }} <span class="text-accent-coral">*</span></label>
                        <input id="mobile" name="mobile" type="tel" value="{{ old('mobile') }}"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Number with country code') }}">
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Avatar') }}</label>
                        <div
                            class="flex items-center gap-3 px-3 py-2.5 border border-dashed border-paper-300 rounded-lg bg-paper-0 hover:border-wa-deep transition cursor-pointer">
                            <span class="w-12 h-12 rounded-full bg-paper-100 grid place-items-center text-ink-500"><svg
                                    viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.5">
                                    <circle cx="8" cy="6" r="3" />
                                    <path d="M2 14c0-3 3-5 6-5s6 2 6 5" />
                                </svg></span>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-semibold">{{ __('Upload image') }}</div>
                                <div class="text-[10.5px] text-ink-500 font-mono">
                                    {{ __('PNG/JPG · 200×200 recommended') }}</div>
                            </div>
                            <span
                                class="text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep">{{ __('Browse') }}</span>
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Gender') }}</label>
                        <div class="flex items-center gap-2">
                            <label
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep transition has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <input type="radio" name="gender" value="m" class="sr-only" checked>
                                <span class="text-[12px] font-medium">{{ __('Male') }}</span>
                            </label>
                            <label
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep transition has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <input type="radio" name="gender" value="f" class="sr-only">
                                <span class="text-[12px] font-medium">{{ __('Female') }}</span>
                            </label>
                            <label
                                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-paper-200 cursor-pointer hover:border-wa-deep transition has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble">
                                <input type="radio" name="gender" value="o" class="sr-only">
                                <span class="text-[12px] font-medium">{{ __('Other') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Access & role -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Access & role') }}</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="workspace">{{ __('Assign to workspace') }}</label>
                        <select id="workspace" name="workspace_id"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            <option value="">{{ __('No workspace · platform admin') }}</option>
                            @foreach ($workspaces as $w)
                                <option value="{{ $w->id }}" @selected(old('workspace_id') == $w->id)>{{ $w->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Leave empty if user is a :app admin / staff.', ['app' => brand_name()]) }}
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="role">{{ __('Role') }} <span class="text-accent-coral">*</span></label>
                        <select id="role" name="role"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            required>
                            <option value="">{{ __('Select role') }}</option>
                            @foreach ($roles as $key => $label)
                                <option value="{{ $key }}" @selected(old('role', 'user') === $key)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="password">{{ __('Password') }} <span class="text-accent-coral">*</span></label>
                        <input id="password" name="password" type="password"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Min 8 characters') }}" required>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="password_confirm">{{ __('Confirm password') }} <span
                                class="text-accent-coral">*</span></label>
                        <input id="password_confirm" name="password_confirmation" type="password"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Repeat password') }}" required>
                    </div>
                    <div class="space-y-2 pt-1">
                        {{-- Toggle inputs use `value=1` + hidden 0 sibling so unchecked posts a falsy. --}}
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Send welcome email') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('With magic-link login') }}</span>
                            </span>
                            <input type="hidden" name="welcome_email" value="0">
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input class="peer opacity-0 w-0 h-0" type="checkbox" name="welcome_email"
                                    value="1" @checked(old('welcome_email', '1') === '1')>
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Force password change') }}</span>
                                <span class="block text-[10.5px] text-ink-500">{{ __('On first login') }}</span>
                            </span>
                            <input type="hidden" name="force_password_change" value="0">
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input class="peer opacity-0 w-0 h-0" type="checkbox" name="force_password_change"
                                    value="1" @checked(old('force_password_change', '1') === '1')>
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Active immediately') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Skip email verification') }}</span>
                            </span>
                            <input type="hidden" name="active" value="0">
                            <span class="relative inline-block w-[34px] h-5 shrink-0">
                                <input class="peer opacity-0 w-0 h-0" type="checkbox" name="active" value="1"
                                    @checked(old('active', '1') === '1')>
                                <span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Address & details -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                <div class="flex items-center gap-2.5 mb-4">
                    <span
                        class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                    <span class="font-serif text-[18px] leading-none">{{ __('Address & notes') }}</span>
                </div>
                <div class="space-y-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="address">{{ __('Address') }}</label>
                        <textarea id="address" name="address" rows="2"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Street, area') }}">{{ old('address') }}</textarea>
                    </div>
                    {{-- Country → state → city cascade. Options injected by admin-users-form.js using the country-state-city npm package. data-value preserves any old() value through validation re-renders. --}}
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
                                for="state">{{ __('State') }}</label>
                            <select id="state" name="state" data-value="{{ old('state') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">— select state —</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="city">{{ __('City') }}</label>
                            <select id="city" name="city" data-value="{{ old('city') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">— select city —</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="zip">{{ __('ZIP / PIN') }}</label>
                            <input id="zip" name="zip" type="text" value="{{ old('zip') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        </div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="notes">{{ __('Internal notes') }}</label>
                        <textarea id="notes" name="notes" rows="3"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Visible to admins only — context, vetting notes, etc.') }}">{{ old('notes') }}</textarea>
                    </div>
                    <div
                        class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-snug">
                        <b>Admin reminder:</b> Super admin and Admin roles can view billing, users, and audit logs.
                        Owners are limited to their own workspace.
                    </div>
                </div>
            </div>

        </form>
    </main>

</x-layouts.admin>
