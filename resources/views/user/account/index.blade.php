<x-layouts.user :title="__('Account')" nav-key="more" page="user-account-index">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6 items-start">

            @php
                $authUser = auth()->user();
                $initials = collect(preg_split('/\s+/', trim($authUser->name ?? '?')))
                    ->take(2)
                    ->map(fn($p) => mb_substr($p, 0, 1))
                    ->implode('');
                $initials = $initials !== '' ? mb_strtoupper($initials) : '?';
                $avatarUrl = $authUser->avatar_url;
            @endphp
            <!-- LEFT NAV -->
            <aside class="space-y-3 lg:sticky lg:top-6 self-start">
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card text-center">
                    <div class="relative inline-block">
                        {{-- Initials are the ALWAYS-visible base; the photo overlays on
                             top and removes itself if its URL is broken/missing, so the
                             circle is never blank. --}}
                        <span id="avatar-preview"
                            class="relative overflow-hidden w-20 h-20 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[28px] font-semibold grid place-items-center mx-auto">
                            <span>{{ $initials }}</span>
                            @if ($avatarUrl)
                                <img id="avatar-img" src="{{ $avatarUrl }}" alt=""
                                    class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
                            @endif
                        </span>
                        <button type="button"
                            class="absolute bottom-0 right-0 w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center border-2 border-paper-0"
                            onclick="document.getElementById('avatar-input').click()" title="{{ __('Change photo') }}">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M11 2l3 3-8 8H3v-3l8-8z" />
                            </svg>
                        </button>
                        <input id="avatar-input" type="file" accept="image/*" class="hidden"
                            onchange="onAvatar(event)" />
                    </div>
                    <div class="font-serif text-[18px] mt-2">{{ $authUser->name }}</div>
                    <div class="font-mono text-[10.5px] text-ink-500">{{ $authUser->email }}</div>
                    @php $planBadge = optional($authUser->currentWorkspace)->billingPackage()?->pname ?: __('Free'); @endphp
                    <span
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono bg-wa-mint text-wa-deep border border-wa-green/40 mt-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ $planBadge }}
                        {{ __('plan') }}</span>
                </div>

                <nav class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card space-y-0.5">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Account') }}</div>
                    <a data-tab="profile" href="?tab=profile" class="acc-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="6" r="3" />
                            <path d="M2 14c0-3 2.5-5 6-5s6 2 6 5" />
                        </svg>{{ __('Profile') }}</a>
                    <a data-tab="plan" href="?tab=plan" class="acc-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M2 8l6-5 6 5M3.5 7v6h9V7" />
                            <path d="M6.5 13V9.5h3V13" />
                        </svg>{{ __('Plan & usage') }}</a>
                    <a data-tab="orders" href="?tab=orders" class="acc-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M2 4h2l1.5 8h7l1-5H6" />
                            <circle cx="6" cy="13" r="1" />
                            <circle cx="11" cy="13" r="1" />
                        </svg>{{ __('Order history') }}</a>
                    <a data-tab="wallet" href="?tab=wallet" class="acc-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="4" width="12" height="9" rx="1.5" />
                            <circle cx="11" cy="9" r="1" />
                        </svg>{{ __('Wallet') }} <span
                            class="ml-auto text-[10px] font-mono text-wa-deep">{{ number_format((int) ($authUser->wallet_credits ?? 0)) }}
                            {{ __('credits') }}</span></a>
                    <a data-tab="addons" href="?tab=addons" class="acc-tab"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2.5" y="2.5" width="5" height="5" rx="1" />
                            <rect x="8.5" y="8.5" width="5" height="5" rx="1" />
                            <path d="M11 3v4M9 5h4" />
                        </svg>{{ __('Add-ons') }}</a>
                    <a data-tab="affiliate" href="?tab=affiliate" class="acc-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                        </svg>{{ __('Affiliate') }}</a>
                    <a data-tab="support" href="?tab=support" class="acc-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M5.5 6a2.5 2.5 0 1 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01" />
                        </svg>{{ __('Support history') }}</a>
                    <a data-tab="branding" href="?tab=branding" class="acc-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="2" y="3" width="12" height="9" rx="1.5" />
                            <path d="M2 9h12M5 13h6" />
                        </svg>{{ __('Branding') }}</a>
                    <a data-tab="translation" href="?tab=translation" class="acc-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="8" cy="8" r="6.5" />
                            <path d="M1.5 8h13M8 1.5c2 2 2 11 0 13M8 1.5c-2 2-2 11 0 13" />
                        </svg>{{ __('Translation') }}</a>

                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-3 pb-1.5">
                        {{ __('Security') }}</div>
                    <a data-tab="password" href="?tab=password" class="acc-tab"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="7" width="10" height="7" rx="1.5" />
                            <path d="M5 7V5a3 3 0 0 1 6 0v2" />
                        </svg>{{ __('Change password') }}</a>
                    <a data-tab="delete" href="?tab=delete" class="acc-tab danger"><svg viewBox="0 0 16 16"
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9" />
                        </svg>{{ __('Delete account') }}</a>
                </nav>

                <a href="{{ url('/account/plans') }}"
                    class="block border border-wa-green/30 bg-wa-bubble/50 rounded-2xl p-4 hover:border-wa-deep transition">
                    <div class="font-serif text-[14px] leading-tight text-wa-deep">{{ __('Upgrade to Growth') }}</div>
                    <p class="text-[11px] text-ink-700 mt-1 leading-snug">
                        {{ __('Unlock unlimited campaigns, 5 devices, and AI-assist.') }}</p>
                    <span
                        class="inline-flex items-center gap-1 mt-2 text-[11px] font-semibold text-wa-deep">{{ __('See plans →') }}</span>
                </a>
            </aside>

            <!-- MAIN -->
            <section class="space-y-5">

                <!-- Title -->
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ url('/more') }}" class="hover:text-wa-deep">{{ __('More') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span id="bc-tab">{{ __('Profile') }}</span>
                        </div>
                        <h1 id="page-title" class="font-serif font-normal tracking-tight text-[30px] sm:text-[38px] lg:text-[44px] leading-none">
                            {{ __('Profile') }} <span class="italic text-wa-deep">{{ __('settings') }}</span></h1>
                        <p id="page-desc" class="text-[13px] text-ink-600 mt-2">
                            {{ __('Update your photo, name, and contact details.') }}</p>
                    </div>
                </div>

                <!-- ============ PROFILE ============ -->
                @php
                    $u = auth()->user();
                    $ws = $u?->current_workspace;
                    $countries = [
                        'India',
                        'United States',
                        'United Kingdom',
                        'UAE',
                        'Australia',
                        'Canada',
                        'Germany',
                        'France',
                        'Singapore',
                    ];
                    $timezones = [
                        'Asia/Kolkata' => 'Asia / Kolkata (IST / GMT+5:30)',
                        'Asia/Dubai' => 'Asia / Dubai (GST / GMT+4)',
                        'Asia/Singapore' => 'Asia / Singapore (SGT / GMT+8)',
                        'Europe/London' => 'Europe / London (GMT)',
                        'Europe/Berlin' => 'Europe / Berlin (CET / GMT+1)',
                        'America/New_York' => 'America / New York (EST / GMT-5)',
                        'America/Los_Angeles' => 'America / Los Angeles (PST / GMT-8)',
                        'UTC' => 'UTC',
                    ];
                    $currentTz = old('timezone', $ws->timezone ?? 'Asia/Kolkata');
                @endphp
                <div data-pane="profile" class="space-y-5">
                    <form method="POST" action="{{ route('user.account.profile.update') }}"
                        class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        @csrf
                        <h3 class="font-serif text-[20px] mb-4">{{ __('Personal info') }}</h3>

                        @if (session('status'))
                            <div
                                class="mb-4 rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">
                                {{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div
                                class="mb-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                                @foreach ($errors->all() as $err)
                                    <div>{{ $err }}</div>
                                @endforeach
                            </div>
                        @endif

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="acc-name">{{ __('Full name') }}</label>
                                <input id="acc-name" name="name" type="text" required maxlength="120"
                                    value="{{ old('name', $u->name ?? '') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="acc-display">{{ __('Display name') }}</label>
                                <input id="acc-display" name="display_name" type="text" maxlength="120"
                                    value="{{ old('display_name', $u->display_name ?: \Illuminate\Support\Str::of($u->name ?? '')->before(' ')) }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div class="lg:col-span-2">
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="acc-email">{{ __('Email') }}</label>
                                <input id="acc-email" name="email" type="email" required maxlength="191"
                                    value="{{ old('email', $u->email ?? '') }}"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="acc-phone">{{ __('Phone') }}</label>
                                <div class="wa-iti-wrap">
                                    <input id="acc-phone" name="mobile" type="tel"
                                        value="{{ old('mobile', $u->mobile ?? '') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                    <input type="hidden" name="country_code" id="acc-country-code"
                                        value="{{ old('country_code', $u->country_code ?? app_default_country()['code']) }}" />
                                </div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="acc-tz">{{ __('Timezone') }} <span
                                        class="text-[10.5px] font-normal text-ink-500">{{ __('(workspace)') }}</span></label>
                                <select id="acc-tz" name="timezone" class="w-full">
                                    @php $current = old('timezone', $ws->timezone ?? 'Asia/Kolkata'); @endphp
                                    <option value="{{ $current }}" selected>{{ $current }}</option>
                                </select>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Applies to') }}
                                    <b>{{ $ws->name ?? 'this workspace' }}</b>. Only the owner can change it.</div>
                            </div>
                        </div>
                        <div class="mt-5 pt-4 border-t border-paper-200 flex items-center justify-end gap-2">
                            <button type="reset"
                                class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset') }}</button>
                            <button type="submit"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save changes') }}</button>
                        </div>
                    </form>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Notifications') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-4">{{ __('Choose what you want to hear from us.') }}</p>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" checked
                                    class="w-4 h-4 accent-wa-deep" />
                                <div>
                                    <div class="text-[13px] font-medium">{{ __('Daily campaign summary') }}</div>
                                    <div class="text-[11px] text-ink-500">
                                        {{ __("Yesterday's sent / delivered / read counts every morning at 9am.") }}
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" checked
                                    class="w-4 h-4 accent-wa-deep" />
                                <div>
                                    <div class="text-[13px] font-medium">{{ __('Device disconnection alerts') }}</div>
                                    <div class="text-[11px] text-ink-500">
                                        {{ __('Email when a paired WhatsApp number drops offline for >5 minutes.') }}
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox"
                                    class="w-4 h-4 accent-wa-deep" />
                                <div>
                                    <div class="text-[13px] font-medium">{{ __('Product news') }}</div>
                                    <div class="text-[11px] text-ink-500">
                                        {{ __('New features, beta invites, and tips.') }}</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ============ PLAN & USAGE ============ -->
                <div data-pane="plan" class="space-y-5 hidden">
                    {{-- Fully dynamic — plan, monthly usage, limit meters, unlocked vs locked features --}}
                    <x-plan-usage :workspace="$authUser->currentWorkspace" :detailed="true" />
                </div>

                <!-- ============ ORDER HISTORY ============ -->
                <div data-pane="orders" class="space-y-4 hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
                        <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                            <h3 class="font-serif text-[20px]">{{ __('Past orders') }}</h3>
                            @php
                                $paidCount = $orders->where('status', 'paid')->count();
                                // Show lifetime in the order's currency when all orders
// share one — otherwise fall back to the workspace
// currency. Common case is one currency per workspace.
$lifetimeCurrency =
    $orders->where('status', 'paid')->pluck('currency')->unique()->count() === 1
        ? $orders->where('status', 'paid')->first()?->currency
        : (optional($authUser->currentWorkspace)->currency ?:
        'USD');
                            @endphp
                            <span class="font-mono text-[10.5px] text-ink-500">
                                {{ $orders->count() }} order{{ $orders->count() === 1 ? '' : 's' }} ·
                                {!! \App\Support\FormatSettings::currency($ordersLifetimeAmount) !!} {{ __('lifetime') }}
                            </span>
                        </div>
                        @if ($orders->isEmpty())
                            <div class="px-5 py-10 text-center text-[12.5px] text-ink-500 italic">
                                No orders yet. <a href="{{ url('/account/plans') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('Browse plans →') }}</a>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px] min-w-[640px]">
                                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                    <tr>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                            {{ __('Invoice') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Plan') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Date') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Amount') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Status') }}</th>
                                        <th
                                            class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                            {{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200">
                                    @foreach ($orders as $o)
                                        @php
                                            $statusBadge = match ($o->status) {
                                                'paid' => 'bg-wa-mint text-wa-deep · bg-wa-green',
                                                'refunded' => 'bg-accent-coral/15 text-accent-coral · bg-accent-coral',
                                                'failed' => 'bg-accent-coral/15 text-accent-coral · bg-accent-coral',
                                                default => 'bg-paper-100 text-ink-700 · bg-paper-300',
                                            };
                                            [$badgeCls, $dotCls] = array_map('trim', explode('·', $statusBadge));
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 font-mono text-wa-deep">{{ $o->order_number }}</td>
                                            <td class="px-2 py-3">
                                                <div>{{ optional($o->package)->pname ?: 'Plan #' . $o->package_id }}
                                                </div>
                                                @if ($o->coupon_code)
                                                    <div class="text-[10px] font-mono text-ink-500 mt-0.5">
                                                        {{ __('coupon') }} <span
                                                            class="text-wa-deep">{{ $o->coupon_code }}</span></div>
                                                @endif
                                            </td>
                                            <td class="px-2 py-3 font-mono text-[11.5px]">
                                                {{ optional($o->created_at)->format('M j, Y') }}</td>
                                            <td class="px-2 py-3 font-semibold">
                                                {!! \App\Support\FormatSettings::display($o->amount, $o->currency) !!}
                                                @if ($o->discount_amount > 0 || $o->tax_amount > 0)
                                                    <div class="text-[10px] font-mono text-ink-500 mt-0.5">
                                                        @if ($o->discount_amount > 0)
                                                            −{!! \App\Support\FormatSettings::display($o->discount_amount, $o->currency) !!} disc
                                                        @endif
                                                        @if ($o->tax_amount > 0)
                                                            +{!! \App\Support\FormatSettings::display($o->tax_amount, $o->currency) !!} tax
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-2 py-3">
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $badgeCls }} text-[10.5px] font-mono">
                                                    <span
                                                        class="w-1.5 h-1.5 rounded-full {{ $dotCls }}"></span>{{ $o->status }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('user.invoice.download', $o->id) }}"
                                                    class="text-wa-deep font-semibold hover:underline text-[11.5px]">{{ __('Download invoice') }}</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ============ WALLET ============ -->
                @php
                    $credits = (int) ($authUser->wallet_credits ?? 0);
                    $currencyMinor = (int) ($authUser->wallet_currency_minor ?? 0);
                    $currencyCode = $authUser->wallet_currency_code ?? 'INR';
                    $currencyMajor = number_format($currencyMinor / 100, 2);
                    $messagesPossible = max(1, $creditsPerMessage ?? 1);
                    $estMessages = (int) floor($credits / $messagesPossible);

                    $spent30d = \App\Models\WalletTransaction::query()
                        ->forUser($authUser->id)
                        ->credit()
                        ->where('type', 'spend')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->sum('amount');
                    $spent30d = abs((int) $spent30d);
                    $earned30d = \App\Models\WalletTransaction::query()
                        ->forUser($authUser->id)
                        ->credit()
                        ->whereIn('type', ['earn', 'refund'])
                        ->where('created_at', '>=', now()->subDays(30))
                        ->sum('amount');
                @endphp
                <div data-pane="wallet" class="space-y-5 hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Available credits') }}</div>
                            <div class="font-serif text-[36px] leading-none mt-2">{{ number_format($credits) }}</div>
                            <div class="text-[11px] text-wa-deep mt-2">≈ {{ number_format($estMessages) }}
                                message{{ $estMessages === 1 ? '' : 's' }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Spent (30d)') }}</div>
                            <div class="font-serif text-[36px] leading-none mt-2">{{ number_format($spent30d) }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">
                                {{ number_format((int) floor($spent30d / $messagesPossible)) }} {{ __('messages') }}
                            </div>
                        </div>
                        <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Earned via affiliate (30d)') }}
                            </div>
                            <div class="font-serif text-[36px] leading-none mt-2">+{{ number_format($earned30d) }}
                            </div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('credits earned in the last 30 days') }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="?tab=affiliate"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2"><svg
                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M5.5 4.5 3 7l2.5 2.5M10.5 4.5 13 7l-2.5 2.5M7 12l2-8" />
                            </svg>{{ __('Earn credits via affiliate') }}</a>
                        <a href="#credit-bundles"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Top up credits') }}</a>
                        <span class="ml-auto text-[11px] text-ink-500 font-mono">{{ $creditsPerMessage }}
                            {{ $creditsPerMessage === 1 ? __('credit') : __('credits') }} / {{ __('message') }}</span>
                    </div>

                    <!-- How billing works — plan allowance first, wallet credits after -->
                    @php
                        $ws = $authUser->currentWorkspace;
                        $wsLimit = $ws ? (int) ($ws->effectiveLimit('monthly_messages_limit', 0) ?? 0) : 0;
                        $wsEndsAt = $ws?->plan_ends_at;
                        $wsActive = $ws ? $ws->planIsActive() : true;
                    @endphp
                    <div class="rounded-2xl bg-wa-mint/40 border border-wa-green/30 p-4 flex items-start gap-3">
                        <span
                            class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-deep grid place-items-center shrink-0 mt-0.5"><svg
                                viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 5.5v.5M7.5 8h.5v3h.5" />
                            </svg></span>
                        <div class="text-[12px] text-ink-700 leading-relaxed">
                            <div class="font-semibold text-[12.5px] text-ink-800 mb-0.5">{{ __('How billing works') }}
                            </div>
                            @php $cpm = max(1, (int) \App\Models\SystemSetting::get('credits_per_message', 1)); @endphp
                            @if ($ws && $wsLimit > 0)
                                {{ __('Your plan includes') }} <span
                                    class="font-semibold">{{ number_format($wsLimit) }}
                                    {{ __('messages/month') }}</span>{{ $wsEndsAt ? ' ' . __('until') . ' ' . $wsEndsAt->format('M j, Y') : '' }}.
                                {{ __('When the monthly allowance is used up while your plan is active, extra sends use wallet credits at') }}
                                <span class="font-semibold">{{ $cpm }}
                                    {{ $cpm === 1 ? __('credit') : __('credits') }}/{{ __('message') }}</span>.
                                {{ __('After your plan ends, sending pauses until you renew — credits do not extend an expired plan.') }}
                                @unless ($wsActive)
                                    <span
                                        class="block mt-1.5 text-accent-amber font-semibold">{{ __('Your plan has ended — sending is paused until you renew. Wallet credits do not extend an expired plan.') }}</span>
                                @endunless
                            @else
                                {{ __('Your plan includes a monthly message allowance until your plan end date. When the allowance is used up while your plan is active, extra sends use wallet credits at') }}
                                <span class="font-semibold">{{ $cpm }}
                                    {{ $cpm === 1 ? __('credit') : __('credits') }}/{{ __('message') }}</span>.
                                {{ __('After your plan ends, sending pauses until you renew.') }}
                            @endif
                        </div>
                    </div>

                    <!-- Credit packages — admin-curated bundles -->
                    @if ($creditPackages->isNotEmpty())
                        <div id="credit-bundles"
                            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden scroll-mt-24">
                            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                                <div>
                                    <h3 class="font-serif text-[20px] leading-tight">{{ __('Top up credits') }}</h3>
                                    <p class="text-[11.5px] text-ink-500 mt-0.5">
                                        {{ __('Bundles add directly to your wallet — pay once, no expiry.') }}</p>
                                </div>
                                <span
                                    class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ $creditPackages->count() }}
                                    bundle{{ $creditPackages->count() === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5 pt-7">
                                @foreach ($creditPackages as $pkg)
                                    @php $isFeatured = $pkg->is_featured; @endphp
                                    <div
                                        class="relative rounded-2xl p-5 flex flex-col gap-2 transition
 {{ $isFeatured
     ? 'bg-wa-mint/40 border-2 border-wa-deep shadow-[0_18px_50px_-25px_rgba(7,94,84,0.45)]'
     : 'bg-paper-0 border border-paper-200 hover:border-wa-deep/40' }}">
                                        @if ($pkg->badge)
                                            <span
                                                class="absolute -top-2.5 left-1/2 -translate-x-1/2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full
 {{ $isFeatured ? 'bg-wa-deep text-paper-0' : 'bg-accent-amber/30 text-[#7B5A14] border border-accent-amber/40' }}
 text-[10px] font-mono uppercase tracking-[0.12em] whitespace-nowrap">
                                                @if ($isFeatured)
                                                    <span
                                                        class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>
                                                @endif
                                                {{ $pkg->badge }}
                                            </span>
                                        @endif

                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                            {{ $pkg->name }}</div>
                                        <div class="mt-1 flex items-baseline gap-1">
                                            <span
                                                class="font-serif text-[36px] leading-none {{ $isFeatured ? 'text-wa-deep' : '' }}">{{ $pkg->price_display }}</span>
                                            <span
                                                class="text-[11px] text-ink-500 font-mono">{{ __('one-time') }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-[12.5px] text-ink-700 mt-0.5">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                                stroke="currentColor" stroke-width="1.6">
                                                <circle cx="8" cy="8" r="5.5" />
                                                <path d="M5.5 8h5M8 5.5v5" />
                                            </svg>
                                            <span
                                                class="font-mono font-semibold">{{ number_format($pkg->credits) }}</span>
                                            {{ __('credits') }}
                                        </div>
                                        @if ($pkg->description)
                                            <p class="text-[11.5px] text-ink-500 leading-snug mt-1">
                                                {{ $pkg->description }}</p>
                                        @endif
                                        <a href="{{ route('user.checkout.credits.show', $pkg->slug) }}"
                                            class="mt-auto inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-full text-[12px] font-semibold transition
 {{ $isFeatured
     ? 'bg-wa-deep hover:bg-wa-teal text-paper-0'
     : 'bg-paper-0 border border-paper-200 hover:border-wa-deep hover:bg-wa-mint/30 text-ink-900' }}">
                                            Get {{ number_format($pkg->credits) }} credits
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 8h10M9 4l4 4-4 4" />
                                            </svg>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <h3 class="font-serif text-[20px] leading-tight">{{ __('Wallet activity') }}</h3>
                            @if ($walletLedger->total() > 0)
                                <span class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                    Showing {{ $walletLedger->firstItem() }}–{{ $walletLedger->lastItem() }} of
                                    {{ number_format($walletLedger->total()) }}
                                </span>
                            @endif
                        </div>
                        @if ($walletLedger->total() === 0)
                            <div class="px-5 py-10 text-center">
                                @include('user.partials.empty-state', [
                                    'message' =>
                                        'No credit activity found. Earn credits via the affiliate program or top up to get started.',
                                    'resetHref' => url('/account'),
                                ])
                            </div>
                        @else
                            <div class="overflow-x-auto">
                            <table class="w-full text-[12.5px] min-w-[640px]">
                                <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                    <tr>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                            {{ __('Description') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Type') }}</th>
                                        <th
                                            class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                            {{ __('Date') }}</th>
                                        <th
                                            class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                            {{ __('Credits') }}</th>
                                        <th
                                            class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                            {{ __('Balance') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-paper-200">
                                    @foreach ($walletLedger as $tx)
                                        @php
                                            $isEarn = in_array($tx->type, ['earn', 'refund', 'topup']);
                                            $badgeCls = $isEarn
                                                ? 'bg-wa-mint border border-wa-green/30 text-wa-deep'
                                                : 'bg-paper-50 border border-paper-200 text-ink-700';
                                            $amtCls = $isEarn ? 'text-wa-deep' : 'text-accent-coral';
                                            $sign = $tx->amount > 0 ? '+' : '−';
                                            $abs = abs((int) $tx->amount);
                                        @endphp
                                        <tr class="hover:bg-paper-50/60">
                                            <td class="px-4 py-2.5">
                                                {{ $tx->description ?: ucfirst(str_replace('.', ' ', $tx->source ?? 'transaction')) }}
                                            </td>
                                            <td class="px-2 py-2.5"><span
                                                    class="text-[10.5px] font-mono px-2 py-0.5 rounded {{ $badgeCls }}">{{ $tx->type }}</span>
                                            </td>
                                            <td class="px-2 py-2.5 font-mono text-[11.5px] text-ink-500">
                                                {{ optional($tx->created_at)->format('M d, H:i') }}</td>
                                            <td class="px-4 py-2.5 text-right {{ $amtCls }} font-mono">
                                                {{ $sign }}{{ number_format($abs) }}</td>
                                            <td class="px-4 py-2.5 text-right font-mono">
                                                {{ number_format((int) $tx->balance_after) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>

                            @if ($walletLedger->hasPages())
                                <div
                                    class="px-5 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between text-[11.5px] text-ink-500">
                                    <div class="font-mono">{{ __('Page') }} {{ $walletLedger->currentPage() }}
                                        {{ __('of') }} {{ $walletLedger->lastPage() }}</div>
                                    <div class="flex items-center gap-1.5">
                                        @if ($walletLedger->onFirstPage())
                                            <span
                                                class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Prev') }}</span>
                                        @else
                                            <a href="{{ $walletLedger->previousPageUrl() }}"
                                                class="px-3 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-ink-900 font-semibold">{{ __('Prev') }}</a>
                                        @endif
                                        @php
                                            $pages = [];
                                            for (
                                                $i = max(1, $walletLedger->currentPage() - 2);
                                                $i <= min($walletLedger->lastPage(), $walletLedger->currentPage() + 2);
                                                $i++
                                            ) {
                                                $pages[] = $i;
                                            }
                                            if (!in_array(1, $pages, true)) {
                                                array_unshift($pages, 1);
                                            }
                                            if (!in_array($walletLedger->lastPage(), $pages, true)) {
                                                $pages[] = $walletLedger->lastPage();
                                            }
                                        @endphp
                                        @foreach ($pages as $p)
                                            @if ($p === $walletLedger->currentPage())
                                                <span
                                                    class="px-3 py-1 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold">{{ $p }}</span>
                                            @else
                                                <a href="{{ $walletLedger->url($p) }}"
                                                    class="px-3 py-1 rounded-full hover:bg-paper-50 text-ink-700">{{ $p }}</a>
                                            @endif
                                        @endforeach
                                        @if ($walletLedger->hasMorePages())
                                            <a href="{{ $walletLedger->nextPageUrl() }}"
                                                class="px-3 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-ink-900 font-semibold">{{ __('Next') }}</a>
                                        @else
                                            <span
                                                class="px-3 py-1 rounded-full border border-paper-200 text-ink-400 cursor-not-allowed">{{ __('Next') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <!-- ============ AFFILIATE ============ -->
                {{-- ADD-ONS tab — extra feature packs bought on top of the plan
                     (gated to active-plan workspaces, like credit top-ups). --}}
                <div data-pane="addons" class="space-y-5 hidden">
                    @php $addonCatalog = \App\Models\Package::featureCatalog(); @endphp
                    @if (!($hasActivePlan ?? false))
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-8 text-center">
                            <h3 class="font-serif text-[20px]">{{ __('Add-ons need an active plan') }}</h3>
                            <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-md mx-auto">{{ __('Add-ons are extra feature packs you buy on top of your subscription. Choose a plan first, then come back here to add features.') }}</p>
                            <a href="{{ route('account.plans') }}" class="mt-4 inline-flex px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('View plans') }}</a>
                        </div>
                    @elseif (empty($addons) || !$addons->count())
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-8 text-center">
                            <h3 class="font-serif text-[20px]">{{ __('No add-ons available') }}</h3>
                            <p class="text-[12.5px] text-ink-600 mt-1.5">{{ __('There are no add-ons offered right now. Check back later.') }}</p>
                        </div>
                    @else
                        {{-- Your active add-ons (purchased + unexpired). --}}
                        @if (($myAddons ?? collect())->isNotEmpty())
                            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl shadow-card overflow-hidden">
                                <div class="px-5 py-4 border-b border-paper-200">
                                    <h3 class="font-serif text-[20px] leading-tight">{{ __('Your add-ons') }}</h3>
                                    <p class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Active packs merged onto your current plan.') }}</p>
                                </div>
                                <div class="divide-y divide-paper-200">
                                    @foreach ($myAddons as $wa)
                                        @php $p = $wa->package; @endphp
                                        @if ($p)
                                            <div class="px-5 py-3 flex items-center justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-[13px] font-semibold text-ink-900 truncate">{{ $p->pname }}</div>
                                                    <div class="text-[11px] text-ink-500 font-mono">
                                                        @if ($wa->ends_at){{ __('Ends') }} {{ \Illuminate\Support\Carbon::parse($wa->ends_at)->format('M d, Y') }}@else{{ __('Lifetime') }}@endif
                                                    </div>
                                                </div>
                                                <span class="text-[10px] font-mono uppercase tracking-[0.14em] px-2 py-1 rounded-full bg-wa-mint text-wa-deep shrink-0">{{ __('Active') }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                                <div>
                                    <h3 class="font-serif text-[20px] leading-tight">{{ __('Add-ons') }}</h3>
                                    <p class="text-[11.5px] text-ink-500 mt-0.5">{{ __('Unlock extra features on top of your current plan — no plan change needed.') }}</p>
                                </div>
                                <span class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ $addons->count() }}
                                    add-on{{ $addons->count() === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
                                @foreach ($addons as $a)
                                    @php
                                        $aPrice  = ($a->free || (float) $a->plan_amount === 0.0) ? __('Free') : \App\Support\FormatSettings::currency($a->chargeableAmount());
                                        $aPeriod = $a->lifetime ? __('one-time') : ('/ ' . ($a->plan_unit ?: 'month'));
                                        $grants = [];
                                        foreach ($addonCatalog['capabilities'] as $k => $l) { if ($a->grantsFeature($k)) $grants[] = $l; }
                                        foreach ($addonCatalog['limits'] as $k => $l) { $d = $a->grantLimit($k); if ($d !== null) $grants[] = ($d === -1 ? __('Unlimited') . ' ' . $l : '+' . $d . ' ' . $l); }
                                        // Owned state — feature packs are one-time (show Active); quantity
                                        // packs (limit boosts) stack, so allow buying again.
                                        $owned     = (int) (($ownedCounts ?? collect())[$a->id] ?? 0);
                                        $stackable = $a->isStackable();
                                    @endphp
                                    <div class="relative rounded-2xl p-5 flex flex-col gap-2 bg-paper-0 border border-paper-200 hover:border-wa-deep/40 transition">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Add-on') }}</div>
                                        <div class="font-serif text-[18px] leading-tight">{{ $a->pname }}</div>
                                        @if ($a->subtitle)<p class="text-[11.5px] text-ink-500 leading-snug">{{ $a->subtitle }}</p>@endif
                                        <div class="mt-1 flex items-baseline gap-1">
                                            <span class="font-serif text-[30px] leading-none">{{ $aPrice }}</span>
                                            <span class="text-[11px] text-ink-500 font-mono">{{ $aPeriod }}</span>
                                        </div>
                                        @if (!empty($grants))
                                            <ul class="space-y-1 text-[12px] text-ink-700 mt-1">
                                                @foreach (array_slice($grants, 0, 5) as $g)
                                                    <li class="flex items-start gap-1.5">
                                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 mt-0.5 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3.5 3.5L13 5" /></svg>
                                                        <span>{{ $g }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if ($owned > 0 && !$stackable)
                                            {{-- One-time feature pack already owned — nothing to gain from re-buying. --}}
                                            <span class="mt-auto inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-full text-[12px] font-semibold bg-wa-mint text-wa-deep">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5l3.5 3.5L13 5" /></svg>
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <a href="{{ route('user.checkout.show', $a->id) }}"
                                                class="mt-auto inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-full text-[12px] font-semibold bg-wa-deep hover:bg-wa-teal text-paper-0 transition">
                                                {{ $owned > 0 ? __('Buy again') : ($a->cta_label ?: __('Add to plan')) }}
                                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8h10M9 4l4 4-4 4" /></svg>
                                            </a>
                                            @if ($owned > 0 && $stackable)
                                                <div class="text-[10.5px] text-ink-500 text-center mt-1">{{ __('Owned') }} &times;{{ $owned }} · {{ __('buying again adds more') }}</div>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div data-pane="affiliate" class="space-y-5 hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Your affiliate code') }}</div>
                                <h3 class="font-serif text-[28px] mt-1">{{ $authUser->referral_code ?: 'NO-CODE' }}
                                </h3>
                                <p class="text-[12.5px] text-ink-600 mt-2 max-w-md">
                                    {{ __('Share your unique link. Every new sign-up earns you') }} <span
                                        class="text-wa-deep font-semibold">{{ number_format($signupReward) }}
                                        {{ __('message credits') }}</span>, deposited straight into your wallet.</p>
                            </div>
                            <button
                                class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5"
                                onclick="copyText('{{ $referralUrl }}')">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3" y="3" width="9" height="9" rx="1.5" />
                                    <path d="M5.5 5.5h-2v9h9v-2" />
                                </svg>
                                {{ __('Copy link') }}
                            </button>
                        </div>
                        <div
                            class="mt-4 px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 font-mono text-[11.5px] text-ink-700 break-all">
                            {{ $referralUrl }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @php
                            $signups30d = $referrals->where('created_at', '>=', now()->subDays(30))->count();
                            $signupsTotal = $referrals->count();
                        @endphp
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Sign-ups (30d)') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($signups30d) }}
                            </div>
                            <div class="text-[11px] text-wa-deep mt-2">{{ __('via your link') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Sign-ups (total)') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($signupsTotal) }}
                            </div>
                            <div class="text-[11px] text-wa-deep mt-2">{{ __('lifetime') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Credits earned') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1">
                                {{ number_format($totalEarnedFromReferrals) }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">≈
                                {{ number_format((int) floor($totalEarnedFromReferrals / max(1, $creditsPerMessage))) }}
                                {{ __('messages') }}</div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <h3 class="font-serif text-[18px]">{{ __('Recent referrals') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px] min-w-[620px]">
                            <thead class="bg-paper-50 border-b border-paper-200 text-ink-500">
                                <tr>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                        {{ __('User') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Code used') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Joined') }}</th>
                                    <th
                                        class="text-left font-mono text-[10px] uppercase tracking-[0.14em] px-2 py-2.5">
                                        {{ __('Status') }}</th>
                                    <th
                                        class="text-right font-mono text-[10px] uppercase tracking-[0.14em] px-4 py-2.5">
                                        {{ __('Credits earned') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200">
                                @forelse ($referrals as $r)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium">
                                            {{ $r->referred?->name ?: $r->referred?->email ?: 'User #' . $r->referred_user_id }}
                                        </td>
                                        <td class="px-2 py-2.5 font-mono text-[11.5px]">{{ $r->code_used }}</td>
                                        <td class="px-2 py-2.5 font-mono text-[11.5px]">
                                            {{ optional($r->created_at)->format('M d, Y') }}</td>
                                        <td class="px-2 py-2.5"><span
                                                class="text-[10.5px] font-mono px-2 py-0.5 rounded bg-wa-mint text-wa-deep">{{ __('active') }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-semibold">
                                            +{{ number_format($r->credits_awarded) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-4">
                                            @include('user.partials.empty-state', [
                                                'message' =>
                                                    'No referrals found. Share your link and new signups will appear here.',
                                                'resetHref' => url('/account'),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <!-- ============ SUPPORT HISTORY ============ -->
                <div data-pane="support" class="space-y-5 hidden">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-1 bg-paper-50 rounded-full p-1 border border-paper-200"
                            data-support-tabs>
                            <button type="button" data-support-tab="open"
                                class="px-3 py-1.5 text-[11.5px] font-semibold rounded-full bg-wa-deep text-paper-0">{{ __('Open') }}
                                <span class="font-mono opacity-80">{{ $supportCounts['open'] ?? 0 }}</span></button>
                            <button type="button" data-support-tab="resolved"
                                class="px-3 py-1.5 text-[11.5px] text-ink-600 rounded-full">{{ __('Resolved') }}
                                <span
                                    class="font-mono opacity-80">{{ $supportCounts['resolved'] ?? 0 }}</span></button>
                            <button type="button" data-support-tab="all"
                                class="px-3 py-1.5 text-[11.5px] text-ink-600 rounded-full">{{ __('All') }} <span
                                    class="font-mono opacity-80">{{ $supportCounts['all'] ?? 0 }}</span></button>
                        </div>
                        <a href="{{ url('/support') }}"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            {{ __('New ticket') }}
                        </a>
                    </div>

                    <div class="space-y-3" data-support-list>
                        @forelse ($supportTickets ?? collect() as $tk)
                            @php
                                $isResolved = $tk->status === 'resolved';
                                $pill = match ($tk->status) {
                                    'awaiting_user' => [
                                        'bg' => 'bg-accent-amber/15',
                                        'text' => 'text-[#7B5A14]',
                                        'border' => 'border-accent-amber/40',
                                        'label' => 'your turn',
                                    ],
                                    'awaiting_support' => [
                                        'bg' => 'bg-wa-mint',
                                        'text' => 'text-wa-deep',
                                        'border' => 'border-wa-green/40',
                                        'label' => 'awaiting reply',
                                    ],
                                    'resolved' => [
                                        'bg' => 'bg-paper-50',
                                        'text' => 'text-ink-700',
                                        'border' => 'border-paper-200',
                                        'label' => 'resolved',
                                    ],
                                    default => [
                                        'bg' => 'bg-wa-mint',
                                        'text' => 'text-wa-deep',
                                        'border' => 'border-wa-green/40',
                                        'label' => 'open',
                                    ],
                                };
                            @endphp
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card hover:border-wa-deep transition {{ $isResolved ? 'opacity-70 hover:opacity-100' : '' }}"
                                data-support-ticket data-support-status="{{ $isResolved ? 'resolved' : 'open' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                class="font-mono text-[10.5px] text-ink-500">#{{ $tk->ticket_number }}</span>
                                            <span
                                                class="text-[10.5px] font-mono px-2 py-0.5 rounded-full {{ $pill['bg'] }} {{ $pill['text'] }} border {{ $pill['border'] }}">{{ $pill['label'] }}</span>
                                        </div>
                                        <h4 class="font-serif text-[16px] leading-tight">{{ $tk->subject }}</h4>
                                        <p class="text-[12px] text-ink-500 mt-1">
                                            @if ($tk->last_reply_at)
                                                Last reply {{ $tk->last_reply_at->diffForHumans() }}
                                            @else
                                                Submitted {{ $tk->created_at->diffForHumans() }}
                                            @endif
                                            · <span class="font-mono">{{ ucfirst($tk->reason) }}</span>
                                        </p>
                                    </div>
                                    <span
                                        class="font-mono text-[10.5px] text-ink-500">{{ $tk->created_at->format('M d') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 text-center">
                                <div class="font-serif text-[18px] mb-1">{{ __('No tickets yet') }}</div>
                                <p class="text-[12.5px] text-ink-500 mb-4">{{ __('When you open a ticket from') }} <a
                                        href="{{ url('/support') }}"
                                        class="text-wa-deep font-semibold underline">/support</a>, it'll show up here.
                                </p>
                                <a href="{{ url('/support') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.7">
                                        <path d="M8 3v10M3 8h10" />
                                    </svg>
                                    {{ __('Open your first ticket') }}
                                </a>
                            </div>
                        @endforelse
                    </div>

                    {{-- Open / Resolved / All filtering — pure DOM toggle, no AJAX.
 The full list is already in the markup; this just hides the
 rows that don't match the active filter. --}}
                    <script>
                        (function() {
                            const tabs = document.querySelector('[data-support-tabs]');
                            if (!tabs) return;
                            const buttons = tabs.querySelectorAll('[data-support-tab]');
                            const items = document.querySelectorAll('[data-support-ticket]');
                            buttons.forEach((btn) => btn.addEventListener('click', () => {
                                const want = btn.dataset.supportTab;
                                buttons.forEach((b) => {
                                    const active = b === btn;
                                    b.classList.toggle('bg-wa-deep', active);
                                    b.classList.toggle('text-paper-0', active);
                                    b.classList.toggle('font-semibold', active);
                                    b.classList.toggle('text-ink-600', !active);
                                });
                                items.forEach((row) => {
                                    const status = row.dataset.supportStatus || 'open';
                                    const hide = want !== 'all' && status !== want;
                                    row.classList.toggle('hidden', hide);
                                });
                            }));
                        })();
                    </script>
                </div>

                <!-- ============ BRANDING ============ -->
                @php
                    $brandingCanCustomize = \App\Services\PlanLimitGuard::hasFeature($ws, 'remove_branding');
                    $brandingPlatformFooter = (string) \App\Models\SystemSetting::get('platform_branding_footer', '');
                    $brandingCurrent = (string) ($ws->branding_footer ?? '');
                @endphp
                <div data-pane="branding" class="hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card max-w-2xl">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Message footer') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-5">
                            {{ __('The line that appears at the bottom of every plain text message + interactive bubble (buttons, list, CTA) sent from flows, scheduled sends, and team-inbox replies. Approved templates skip this — they carry their own template footer.') }}
                        </p>

                        @if (session('branding_status'))
                            <div
                                class="mb-4 rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">
                                {{ session('branding_status') }}</div>
                        @endif

                        @if ($brandingCanCustomize)
                            <form method="POST" action="{{ route('user.account.branding.update') }}"
                                class="space-y-4">
                                @csrf
                                <label class="block">
                                    <span
                                        class="block text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Custom footer (60 char max)') }}</span>
                                    <textarea name="branding_footer" maxlength="60" rows="2" data-branding-input
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep resize-none"
                                        placeholder="Sent via {{ $authUser->name ?? 'Your brand' }}">{{ $brandingCurrent }}</textarea>
                                    <div
                                        class="mt-1 flex items-center justify-between text-[10.5px] font-mono text-ink-500">
                                        <span>{{ __('Leave blank to send messages with no footer at all.') }}</span>
                                        <span><span
                                                data-branding-count>{{ mb_strlen($brandingCurrent) }}</span>/60</span>
                                    </div>
                                </label>

                                <div
                                    class="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] leading-snug">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                                        {{ __('Preview') }}</div>
                                    {{ __("Hello! Here's the info you asked for.") }}
                                    <div class="mt-1 italic text-ink-600" data-branding-preview>
                                        {{ $brandingCurrent !== '' ? '_' . $brandingCurrent . '_' : '(no footer)' }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="submit"
                                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save footer') }}</button>
                                    <button type="button" data-branding-clear
                                        class="px-3 py-2 rounded-full border border-paper-200 text-[12px] hover:bg-paper-50">{{ __('Clear') }}</button>
                                </div>
                            </form>

                            <script>
                                (function() {
                                    const input = document.querySelector('[data-branding-input]');
                                    const count = document.querySelector('[data-branding-count]');
                                    const prev = document.querySelector('[data-branding-preview]');
                                    const clear = document.querySelector('[data-branding-clear]');
                                    if (!input) return;
                                    const sync = () => {
                                        count.textContent = input.value.length;
                                        prev.textContent = input.value.trim() ? ('_' + input.value + '_') : '(no footer)';
                                    };
                                    input.addEventListener('input', sync);
                                    clear?.addEventListener('click', () => {
                                        input.value = '';
                                        sync();
                                        input.focus();
                                    });
                                })();
                            </script>
                        @else
                            <div class="space-y-4">
                                <div class="px-3 py-3 rounded-lg bg-paper-50 border border-paper-200 text-[12px]">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">
                                        {{ __('Current footer (platform default)') }}</div>
                                    <div class="italic text-ink-700">
                                        _{{ $brandingPlatformFooter ?: 'Sent via ' . brand_name() }}_</div>
                                </div>

                                <div
                                    class="rounded-lg border border-[#B45309]/30 bg-[#FFFBEB] text-[#92400E] px-4 py-3 text-[12.5px]">
                                    <strong
                                        class="font-semibold">{{ __('Upgrade to remove the platform footer.') }}</strong>
                                    Your current plan uses the platform's default footer on outbound messages. Premium
                                    plans unlock a custom footer (or no footer at all).
                                    <a href="{{ url('/account/plans') }}"
                                        class="ml-1 underline font-semibold">{{ __('See plans →') }}</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ============ TRANSLATION ============ -->
                @php
                    $xlateCan  = \App\Services\PlanLimitGuard::hasFeature($ws, 'access_translation');
                    $xlateLang = strtolower((string) ($ws->default_language ?: 'en'));
                    $xlateOn   = (bool) ($ws->inbox_translate ?? true);
                    $xlateLangs = ['en'=>'English','es'=>'Spanish','pt'=>'Portuguese','fr'=>'French','de'=>'German','it'=>'Italian','ar'=>'Arabic','hi'=>'Hindi','zh'=>'Chinese','id'=>'Indonesian','ms'=>'Malay','ru'=>'Russian','tr'=>'Turkish','ja'=>'Japanese','ko'=>'Korean','th'=>'Thai','vi'=>'Vietnamese','nl'=>'Dutch','bn'=>'Bengali','ur'=>'Urdu','ta'=>'Tamil','tl'=>'Filipino'];
                    // The translation ENGINE + keys are platform-wide (admin-managed). Users never enter keys.
                    $xlateProvider = \App\Models\TranslationProvider::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->first();
                    $xlateProviderName = $xlateProvider?->name ?: 'MyMemory (free)';
                    $xlateMonthStart = now()->startOfMonth();
                    $xlateChars = (int) \App\Models\TranslationUsage::query()->where('workspace_id', $ws->id)->where('called_at', '>=', $xlateMonthStart)->sum(\Illuminate\Support\Facades\DB::raw('chars_in + chars_out'));
                    $xlateCount = (int) \App\Models\TranslationUsage::query()->where('workspace_id', $ws->id)->where('called_at', '>=', $xlateMonthStart)->count();
                    $isPlatformAdmin = (bool) (auth()->user()->is_admin ?? false);
                @endphp
                <div data-pane="translation" class="hidden space-y-4">

                    {{-- How it works --}}
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Real-time translation') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-4">{{ __('Chat with customers in any language. Each side reads in their own language — automatically.') }}</p>
                        <div class="grid sm:grid-cols-3 gap-3">
                            @foreach ([
                                ['1', __('Customer writes (any language)'), __('Their message is auto-translated INTO your team language and shown to your agent, with the original one click away.')],
                                ['2', __('Agent replies (your language)'), __('Your reply is translated INTO the customer\'s language right before it is sent on WhatsApp.')],
                                ['3', __('AI + widget too'), __('The AI agent and the website chat widget reply natively in the customer\'s language.')],
                            ] as [$n, $t, $d])
                                <div class="rounded-xl bg-paper-50 border border-paper-200 p-3">
                                    <span class="w-6 h-6 rounded-full grid place-items-center text-[11px] font-mono bg-wa-deep text-paper-0 mb-2">{{ $n }}</span>
                                    <div class="text-[12.5px] font-semibold leading-tight">{{ $t }}</div>
                                    <div class="text-[11px] text-ink-500 mt-1 leading-snug">{{ $d }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if (session('translation_status'))
                        <div class="rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">{{ session('translation_status') }}</div>
                    @endif

                    @if ($xlateCan)
                        {{-- Engine + usage --}}
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Translation engine') }}</div>
                                <div class="text-[16px] font-semibold mt-1">{{ $xlateProviderName }}</div>
                                <div class="mt-2 text-[11.5px] text-ink-600 leading-snug">
                                    {{ __('You do NOT enter any API keys here. The engine + its keys are set up once, platform-wide, by your admin. The free engine works out of the box; admins can plug in DeepL / Google Translate for higher quality.') }}
                                </div>
                                @if ($isPlatformAdmin)
                                    <a href="{{ url('/admin/translation-providers') }}" class="inline-block mt-2 text-[11.5px] text-wa-deep font-semibold underline">{{ __('Configure providers + keys (admin) →') }}</a>
                                @endif
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('This month') }}</div>
                                <div class="text-[26px] font-serif text-wa-deep leading-tight mt-1">{{ number_format($xlateChars) }}</div>
                                <div class="text-[11px] text-ink-500">{{ __('characters translated') }} · {{ number_format($xlateCount) }} {{ __('messages') }}</div>
                            </div>
                        </div>

                        {{-- Settings --}}
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                            {{-- Resilient action: use the named route when present, else fall back
                                 to the literal path. A stale route cache on the live box (the
                                 translation route was added after route:cache ran) threw
                                 RouteNotFoundException and 500'd the WHOLE account page. --}}
                            <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('user.account.translation.update') ? route('user.account.translation.update') : url('/account/translation') }}" class="space-y-5">
                                @csrf
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" name="inbox_translate" value="1" {{ $xlateOn ? 'checked' : '' }} class="mt-0.5 w-4 h-4 accent-wa-deep">
                                    <span>
                                        <span class="block text-[13px] font-semibold">{{ __('Auto-translate conversations') }}</span>
                                        <span class="block text-[11.5px] text-ink-500">{{ __('Turn the whole thing on or off for this workspace.') }}</span>
                                    </span>
                                </label>
                                <label class="block">
                                    <span class="block text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Your team language') }}</span>
                                    <select name="default_language" class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                        @foreach ($xlateLangs as $code => $label)
                                            <option value="{{ $code }}" {{ $xlateLang === $code ? 'selected' : '' }}>{{ $label }} ({{ $code }})</option>
                                        @endforeach
                                    </select>
                                    <span class="block mt-1 text-[10.5px] text-ink-500">{{ __('The language YOUR agents read + write in. Customer messages are translated into this; your replies are translated out of it.') }}</span>
                                </label>
                                <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save translation settings') }}</button>
                            </form>
                        </div>
                    @else
                        <div class="rounded-lg border border-[#B45309]/30 bg-[#FFFBEB] text-[#92400E] px-4 py-3 text-[12.5px]">
                            <strong class="font-semibold">{{ __('Upgrade to unlock real-time translation.') }}</strong>
                            {{ __('Auto-translate WhatsApp conversations between your team\'s language and your customers\' languages.') }}
                            <a href="{{ url('/account/plans') }}" class="ml-1 underline font-semibold">{{ __('See plans →') }}</a>
                        </div>
                    @endif
                </div>

                <!-- ============ CHANGE PASSWORD ============ -->
                <div data-pane="password" class="hidden">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card max-w-2xl">
                        <h3 class="font-serif text-[20px] mb-1">{{ __('Change password') }}</h3>
                        <p class="text-[12px] text-ink-500 mb-5">
                            {{ __('Use a strong password — at least 12 characters with a mix of letters, numbers, and symbols.') }}
                        </p>
                        @if (session('password_status'))
                            <div
                                class="mb-4 rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[12px] text-wa-deep">
                                {{ session('password_status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div
                                class="mb-4 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                                @foreach ($errors->all() as $err)
                                    <div>{{ $err }}</div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('user.account.password.update') }}"
                            class="space-y-4">
                            @csrf
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Current password') }}</label>
                                <div class="relative">
                                    <input id="pw-current" name="current_password" type="password" required
                                        autocomplete="current-password"
                                        placeholder="{{ __('Enter your current password') }}"
                                        class="w-full px-3 py-2 pr-10 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                    <button type="button" data-pw-eye="pw-current"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900"
                                        title="{{ __('Show password') }}"><svg viewBox="0 0 16 16" class="w-4 h-4"
                                            fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                            <circle cx="8" cy="8" r="2" />
                                        </svg></button>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('New password') }}</label>
                                <div class="relative">
                                    <input id="pw-new" name="password" type="password" required minlength="8"
                                        autocomplete="new-password" placeholder="{{ __('At least 8 characters') }}"
                                        class="w-full px-3 py-2 pr-10 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                    <button type="button" data-pw-eye="pw-new"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900"
                                        title="{{ __('Show password') }}"><svg viewBox="0 0 16 16" class="w-4 h-4"
                                            fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                            <circle cx="8" cy="8" r="2" />
                                        </svg></button>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Confirm new password') }}</label>
                                <div class="relative">
                                    <input id="pw-confirm" name="password_confirmation" type="password" required
                                        minlength="8" autocomplete="new-password"
                                        placeholder="{{ __('Type it again') }}"
                                        class="w-full px-3 py-2 pr-10 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                    <button type="button" data-pw-eye="pw-confirm"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900"
                                        title="{{ __('Show password') }}"><svg viewBox="0 0 16 16" class="w-4 h-4"
                                            fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" />
                                            <circle cx="8" cy="8" r="2" />
                                        </svg></button>
                                </div>
                            </div>
                            <div
                                class="px-3 py-2 rounded-lg bg-paper-50 border border-paper-200 text-[11.5px] text-ink-700">
                                <div class="font-semibold mb-1">{{ __('Password tips') }}</div>
                                <ul class="list-disc pl-5 space-y-0.5 text-ink-600">
                                    <li>{{ __('Mix uppercase, lowercase, numbers, and symbols') }}</li>
                                    <li>{{ __("Don't reuse passwords from other sites") }}</li>
                                    <li>{{ __('Consider a password manager (1Password, Bitwarden)') }}</li>
                                </ul>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="reset"
                                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset') }}</button>
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Update password') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============ DELETE ACCOUNT ============ -->
                <div data-pane="delete" class="hidden">
                    <div class="bg-paper-0 border-2 border-accent-coral/40 rounded-2xl p-6 shadow-card max-w-2xl">
                        <div class="flex items-start gap-3">
                            <span
                                class="w-10 h-10 rounded-full bg-accent-coral/15 text-accent-coral grid place-items-center shrink-0">
                                <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path d="M8 2l7 12H1z" />
                                    <path d="M8 6.5v3M8 12h.01" />
                                </svg>
                            </span>
                            <div class="flex-1">
                                <h3 class="font-serif text-[22px] text-accent-coral leading-tight">
                                    {{ __('Delete your account') }}</h3>
                                <p class="text-[12.5px] text-ink-700 mt-2 leading-relaxed">
                                    {{ __('This permanently deletes your workspace, all conversations, automations, contacts, devices, and billing history.') }}
                                    <strong>{{ __('This cannot be undone.') }}</strong></p>

                                <div class="mt-4 px-4 py-3 rounded-lg bg-paper-50 border border-paper-200">
                                    <div class="font-semibold text-[13px] mb-2">
                                        {{ __('Before you delete, consider:') }}</div>
                                    <ul class="text-[11.5px] text-ink-700 list-disc pl-5 space-y-1">
                                        <li><a href="?tab=wallet"
                                                class="text-wa-deep font-semibold hover:underline">{{ __('Spend your remaining credits') }}</a>
                                            · {{ number_format((int) ($authUser->wallet_credits ?? 0)) }}
                                            {{ __('credits will be lost otherwise') }}</li>
                                        <li><a href="{{ url('/account/plans') }}"
                                                class="text-wa-deep font-semibold hover:underline">{{ __('Downgrade to free') }}</a>
                                            instead of deleting</li>
                                        <li><a href="{{ url('/support') }}"
                                                class="text-wa-deep font-semibold hover:underline">{{ __('Contact support') }}</a>
                                            if you're hitting an issue we can fix</li>
                                    </ul>
                                </div>

                                <form class="mt-5 space-y-3" onsubmit="event.preventDefault(); confirmDelete()">
                                    <div>
                                        <label
                                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Type') }}
                                            <code class="font-mono bg-paper-50 px-1.5 py-0.5 rounded">delete my
                                                account</code> to confirm</label>
                                        <input id="del-confirm" type="text" autocomplete="off"
                                            placeholder="{{ __('delete my account') }}"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-accent-coral focus:ring-4 focus:ring-accent-coral/10" />
                                    </div>
                                    <div>
                                        <label
                                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Why are you leaving? (optional)') }}</label>
                                        <textarea rows="2" placeholder="{{ __('Lets us improve') }}"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] resize-none focus:outline-none focus:border-wa-deep"></textarea>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <a href="?tab=profile"
                                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                                        <button type="submit"
                                            class="px-4 py-2 rounded-full bg-accent-coral hover:bg-[#C56B4F] text-paper-0 text-[12px] font-semibold">{{ __('Permanently delete account') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <div id="toast"
        style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#0B1F1C;color:#FBFAF6;padding:8px 14px;border-radius:999px;font-size:12px;font-weight:500;box-shadow:0 12px 28px -10px rgba(0,0,0,0.4);opacity:0;pointer-events:none;transition:opacity .18s, transform .18s;z-index:60">
    </div>

</x-layouts.user>
