<x-layouts.user :title="__('Create workspace')" nav-key="dashboard" page="user-workspaces-create">

    <style>
        .ts-control {
            {{-- --color-paper-200 is a hex, so wrapping it in rgb() produced
                 rgb(#E5DFD0) — invalid, so the browser dropped the declaration
                 and the TomSelect control rendered with no border at all. --}}
            border-color: var(--color-paper-200) !important;
            border-radius: 0.5rem;
            background: #fff !important;
            font-size: 13px !important;
            padding: 8px 10px !important;
            min-height: 42px !important;
        }

        .ts-wrapper.focus .ts-control {
            border-color: var(--color-wa-deep) !important;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-wa-deep) 10%, transparent) !important;
        }

        .ts-dropdown {
            font-size: 12.5px;
            border-radius: 0.5rem;
            border-color: var(--color-wa-deep);
        }

        .ts-dropdown .active {
            background: var(--color-wa-deep);
            color: #fff;
        }
    </style>

    <!-- Sub header -->
    <div class="border-b border-paper-200 bg-paper-0">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/dashboard') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to dashboard') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspaces / Create') }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">{{ __('Create a') }} <span
                            class="italic text-wa-deep">{{ __('new workspace') }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-[1180px] mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <!-- LEFT: form card -->
            <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-6">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                    {{ __('New workspace') }}</div>
                <h2 class="font-serif text-[28px] leading-tight tracking-[-0.01em]">{{ __('Set up a') }} <span
                        class="italic text-wa-deep">{{ __('workspace') }}</span>.</h2>
                <p class="text-[12.5px] text-ink-600 mt-1.5">
                    {{ __('Workspaces are sealed / each one keeps its own contacts, devices, broadcasts, flows, billing.') }}
                </p>

                {{-- Flashed server error — e.g. the plan-limit message from
                     store()'s PlanLimitReachedException, which was logged but
                     never rendered on this page before. --}}
                @if (session('error'))
                    <div
                        class="mt-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                        {{ session('error') }}
                    </div>
                @endif

                @if (!empty($limitReached))
                    {{-- Owner has hit workspaces_per_owner_limit → show an upgrade
                         prompt INSTEAD of the form (even on direct URL access). --}}
                    <div
                        class="mt-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[12.5px] text-[#A1431F]">
                        <div class="font-semibold mb-1">{{ __('Workspace limit reached') }}</div>
                        <div>{{ __('Your current plan doesn\'t include another workspace. Upgrade your plan to create more.') }}</div>
                        <a href="{{ url('/plans') }}"
                            class="inline-flex items-center gap-1.5 mt-3 px-3.5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-medium">{{ __('View plans') }}</a>
                    </div>
                @else

                @if ($errors->any())
                    <div
                        class="mt-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('workspaces.store') }}" class="space-y-3 mt-5">
                    @csrf
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Workspace name') }}</label>
                        <input required type="text" name="name" maxlength="120" value="{{ old('name') }}"
                            placeholder="{{ __('e.g. Bloomly Marketing') }}"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('A friendly label your team will see at the top of the app.') }}</div>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Mobile number') }}</label>
                        @php $__u = auth()->user(); @endphp
                        <div class="flex gap-2">
                            <input type="text" name="country_code" maxlength="8" value="{{ old('country_code', ($__u->country_code ?? '') ?: app_default_country()['code']) }}"
                                class="w-24 px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] text-center focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" placeholder="+91" />
                            <input required type="tel" name="mobile" value="{{ old('mobile', $__u->mobile ?? '') }}" placeholder="{{ __('Mobile number') }}"
                                class="flex-1 px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </div>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Required — we use this to reach you for verification and important updates.') }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Industry') }}</label>
                            <select name="industry"
                                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select industry') }}</option>
                                @foreach (['ecommerce', 'saas', 'agency', 'education', 'healthcare', 'finance', 'travel', 'hospitality', 'other'] as $opt)
                                    <option value="{{ $opt }}" @selected(old('industry') === $opt)>
                                        {{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Team size') }}</label>
                            <select name="size_range"
                                class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select team size') }}</option>
                                @foreach (['1', '2-5', '6-20', '21-100', '100+'] as $opt)
                                    <option value="{{ $opt }}" @selected(old('size_range') === $opt)>
                                        {{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block"
                            for="ws-timezone">{{ __('Timezone') }}</label>
                        <select id="ws-timezone" name="timezone" class="w-full">
                            @php $picked = old('timezone', 'Asia/Kolkata'); @endphp
                            <option value="{{ $picked }}" selected>{{ $picked }}</option>
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Type to search any IANA timezone (Asia/Kolkata, Europe/London, etc.).') }}</div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <a href="{{ url('/dashboard') }}"
                            class="px-4 py-2.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[13px] font-medium">{{ __('Cancel') }}</a>
                        <button type="submit"
                            class="flex-1 px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2">
                            Create workspace
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </button>
                    </div>
                </form>
                @endif
            </div>

            <!-- RIGHT: existing workspaces + tip -->
            <aside class="space-y-4">
                <div class="bg-paper-0 border border-paper-200 rounded-[14px] shadow-card p-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2.5">
                        {{ __('Your workspaces') }}</div>
                    @if ($existing->isEmpty())
                        <div class="text-[12px] text-ink-500">
                            {{ __('No workspaces yet. Your first one will land here.') }}</div>
                    @else
                        <div class="space-y-1">
                            @foreach ($existing as $w)
                                @php $isCurrent = auth()->user()->current_workspace_id === $w->id; @endphp
                                <form method="POST" action="{{ route('workspaces.switch', $w->id) }}" class="block">
                                    @csrf
                                    <button type="submit"
                                        class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-xl text-left {{ $isCurrent ? 'bg-wa-mint' : 'hover:bg-paper-50' }}">
                                        <span
                                            class="w-7 h-7 rounded-md text-paper-0 grid place-items-center text-[10.5px] font-semibold"
                                            style="background:{{ $w->brand_color ?: 'var(--color-wa-deep)' }};">{{ strtoupper(substr($w->name, 0, 2)) }}</span>
                                        <span class="flex-1 min-w-0">
                                            <span
                                                class="block text-[12px] font-semibold text-ink-900 truncate">{{ $w->name }}</span>
                                            <span
                                                class="block text-[10px] text-ink-500 font-mono truncate">{{ $w->slug }}</span>
                                        </span>
                                        @if ($isCurrent)
                                            <span
                                                class="text-[9.5px] font-mono px-1.5 py-0.5 rounded bg-wa-deep/10 text-wa-deep">{{ __('CURRENT') }}</span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="bg-wa-deep rounded-[14px] p-4 shadow-soft text-paper-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/60 mb-1">
                        {{ __('Tip') }}</div>
                    <div class="font-serif text-[18px] leading-tight">{{ __('Sealed by design') }}</div>
                    <p class="mt-1.5 text-[11.5px] text-paper-0/85 leading-relaxed">
                        {{ __('Each workspace runs on its own data set / separate contacts, devices, broadcasts, billing. Switch any time from the top-bar pill.') }}
                    </p>
                </div>
            </aside>

        </div>
    </main>

</x-layouts.user>
