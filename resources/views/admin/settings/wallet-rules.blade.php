<x-layouts.admin :title="__('Admin · Wallet rules')" admin-key="wallet-rules" page="admin-wallet-rules">


    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Wallet rules') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Affiliate & credits') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Wallet') }}
                    <span class="italic text-wa-deep">{{ __('rules') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('This defines the logic for referral rewards, messaging fees, and USD-to-credit conversion rates. Any configuration updates apply instantly to the next transaction or message sent.') }}
                </p>
            </div>
            <x-admin.flash inline />
        </div>

        @php
            $credPerSignup = (int) ($settings['referral_signup_credits'] ?? 100);
            $credPerMessage = (int) ($settings['credits_per_message'] ?? 1);
            $credPerRupee = (float) ($settings['credits_per_currency_minor'] ?? 0.1);
            $rupeeForCredits100 = $credPerRupee > 0 ? round(100 / $credPerRupee, 2) : 0;
            $msgsFor100Credits = $credPerMessage > 0 ? intdiv(100, $credPerMessage) : 0;
            $referrerWorth = $credPerSignup * $credPerMessage;
        @endphp

        {{-- KPI strip --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Signup reward') }}</div>
                <div class="font-serif text-[28px] leading-none mt-1">{{ $credPerSignup }} <span
                        class="text-[12px] text-ink-500 font-sans">{{ __('credits') }}</span></div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('per successful referral') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Cost per message') }}</div>
                <div class="font-serif text-[28px] leading-none mt-1">{{ $credPerMessage }} <span
                        class="text-[12px] text-ink-500 font-sans">credit{{ $credPerMessage > 1 ? 's' : '' }}</span>
                </div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('charged from wallet on every outbound send') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                @php
                    // Symbol of the platform's default currency — what one major
// unit of credit-conversion is denominated in. INR ⇒ ₹, USD ⇒ $.
$defaultCur = \App\Support\FormatSettings::currencyFor();
$defaultSym = $defaultCur?->symbol ?? ($defaultCur?->code ?? '');
                @endphp
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Top-up rate') }}</div>
                <div class="font-serif text-[28px] leading-none mt-1">
                    {{ rtrim(rtrim(number_format($credPerRupee, 2), '0'), '.') }} <span
                        class="text-[12px] text-ink-500 font-sans">cred / {{ $defaultSym }}</span></div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $defaultSym }}{{ $rupeeForCredits100 }} buys 100
                    credits</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Real spending') }}</div>
                <div class="font-serif text-[28px] leading-none mt-1">{{ number_format($msgsFor100Credits) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('messages a customer can send for 100 credits') }}
                </div>
            </div>
        </section>

        {{-- Settings form — full width --}}
        <form method="POST" action="{{ route('admin.settings.affiliate.update') }}"
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Rules') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Adjust credit economics') }}</h2>
                </div>
                <span
                    class="font-mono text-[10px] text-wa-deep px-2 py-1 rounded-full bg-wa-mint border border-wa-green/40">{{ __('live · 3 keys') }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
                <label class="flex flex-col gap-1.5">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Credits per signup') }}</span>
                    <input type="number" name="referral_signup_credits" min="0" max="1000000"
                        value="{{ old('referral_signup_credits', $credPerSignup) }}"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    <span
                        class="text-[10.5px] text-ink-500">{{ __('Awarded to the referrer the moment their referee finishes signup.') }}</span>
                </label>
                <label class="flex flex-col gap-1.5">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Credits per message') }}</span>
                    <input type="number" name="credits_per_message" min="1" max="1000"
                        value="{{ old('credits_per_message', $credPerMessage) }}"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    <span
                        class="text-[10.5px] text-ink-500">{{ __("Subtracted from a workspace's wallet for every outbound send.") }}</span>
                </label>
                <label class="flex flex-col gap-1.5">
                    <span
                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('How many credits per 1 of money paid') }}</span>
                    <input type="number" name="credits_per_currency_minor" min="0" max="100000" step="0.01"
                        value="{{ old('credits_per_currency_minor', $credPerRupee) }}"
                        class="px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    <span class="text-[10.5px] text-ink-500">
                        {{ __('When a user adds money to their wallet, this is how many credits each 1 unit buys.') }}
                        @if ($credPerRupee > 0)
                            <span class="text-wa-deep font-semibold">{{ __('So 1 credit = :amt paid.', ['amt' => rtrim(rtrim(number_format(1 / $credPerRupee, 2), '0'), '.')]) }}</span>
                        @endif
                    </span>
                </label>
            </div>

            <div class="px-5 py-4 border-t border-paper-200 bg-paper-50/40 flex flex-wrap items-center justify-between gap-3">
                <div class="text-[11.5px] text-ink-600">
                    With current values, <strong>{{ $referrerWorth }}</strong> credits flow into the referrer's wallet
                    — enough for <strong>{{ number_format($referrerWorth) }}</strong> messages.
                </div>
                <button type="submit"
                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
            </div>
        </form>

        {{-- Quick guide — 3 cards in a row BELOW the form so it never leaves a gap --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick guide') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('How wallet credits work') }}</h3>
                <div class="space-y-3 text-[12.5px] text-ink-700 leading-relaxed">
                    <p><strong>{{ __('The wallet:') }}</strong> every workspace has a credit balance (column <code
                            class="font-mono text-[11px] bg-paper-50 px-1 rounded">workspaces.wallet_credits</code>).
                        Credits are debited on sends, topped up by payments or referrals.</p>
                    <p><strong>{{ __('Cost per message:') }}</strong> the price tag on every outbound WhatsApp send —
                        chat reply, campaign, broadcast, scheduled, auto-reply. Higher value = each send burns more
                        credit.</p>
                    <p><strong>{{ __('Top-up conversion:') }}</strong> "Credits per {{ $defaultSym }}" decides how
                        many credits each unit of the platform currency buys. <span class="font-mono text-[11px]">0.1 =
                            {{ $defaultSym }}10/credit · 1.0 = {{ $defaultSym }}1/credit · 10 =
                            {{ $defaultSym }}0.10/credit</span>.</p>
                    <p><strong>{{ __('Referral reward:') }}</strong> the moment a referee finishes signup, referrer
                        wallet += <em>{{ __('credits per signup') }}</em>. This is the affiliate engine.</p>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Lifecycle') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('When credits move') }}</h3>
                <ol class="space-y-2.5 text-[12.5px] text-ink-700 list-decimal pl-4 leading-relaxed">
                    <li>
                        <strong>{{ __('Referral Signup:') }}</strong>
                        {{ __("User joins with a referral code → Referrer's wallet is credited.") }}
                    </li>
                    <li>
                        <strong>{{ __('Workspace Top-Up:') }}</strong>
                        {{ __('Workspace adds funds → Wallet balance increases by 1 credit per USD.') }}
                    </li>
                    <li>
                        <strong>{{ __('Message Sent:') }}</strong>
                        {{ __('Workspace sends a message → Credits are deducted per message.') }}
                    </li>
                    <li>
                        <strong>{{ __('Zero Balance:') }}</strong>
                        {{ __('Wallet hits 0 → Subsequent messages fail with an "insufficient credits" error until a top-up is completed.') }}
                    </li>
                </ol>
            </div>

            <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-5">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep">{{ __('Strategy tip') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-1 mb-3 text-wa-deep">{{ __('Break-even math') }}
                </h3>
                <p class="text-[12.5px] text-ink-700 leading-relaxed">
                    {{ __('Lower "cost per message" during promotional periods. The referral reward + cost-per-message together set the actual') }}
                    <strong>{{ __('break-even point') }}</strong> for affiliate marketing — too generous and your
                    wallet drains, too stingy and nobody refers.</p>
                <div class="mt-3 rounded-xl bg-paper-0/60 px-3 py-2 text-[11.5px] font-mono text-ink-700">
                    {{ __('Default: 100 signup × 1 msg = 100 free sends per referral.') }}
                </div>
            </div>
        </section>

        {{-- ───────────── Per-country credit pricing ───────────── --}}
        <section class="mt-8 bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
            <form method="POST" action="{{ route('admin.settings.message-rates.update') }}">
                @csrf
                <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                    <div>
                        <h2 class="font-serif text-[22px] leading-tight">{{ __('Per-country credit pricing') }}</h2>
                        <p class="text-[12.5px] text-ink-500 mt-1 max-w-2xl">
                            {{ __('Charge fairly per message by recipient country and category — the way Meta prices (a US marketing message costs ~12× one to India; service-window replies are free). Leave OFF to keep the single flat rate above.') }}
                        </p>
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer shrink-0">
                        <input type="checkbox" name="per_country_credits_enabled" value="1" class="peer sr-only"
                            @checked($perCountryEnabled ?? false)>
                        <span class="w-10 h-6 rounded-full bg-paper-200 peer-checked:bg-wa-deep relative transition
                            after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5
                            after:rounded-full after:bg-paper-0 after:transition peer-checked:after:translate-x-4"></span>
                        <span class="text-[12.5px] font-semibold">{{ __('Enable per-country pricing') }}</span>
                    </label>
                </div>

                @php
                    $cats = ['' => __('Any category'), 'marketing' => 'Marketing', 'utility' => 'Utility', 'authentication' => 'Authentication', 'service' => 'Service (free window)'];
                @endphp

                {{-- Plain-words guide so nobody has to ask "how does this work?" --}}
                <div class="rounded-xl bg-wa-mint/30 border border-wa-green/30 p-4 mb-4 text-[12.5px] text-ink-700 leading-relaxed">
                    <div class="flex items-center gap-2 font-serif text-[15px] text-wa-deep mb-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="8" cy="8" r="6.5" /><path d="M8 7.3v3.4M8 5.1h.01" />
                        </svg>
                        {{ __('How this works — in simple words') }}
                    </div>
                    <ol class="list-decimal pl-5 space-y-1">
                        <li>{{ __('One message can cost more than one credit. You decide how many — per country.') }}</li>
                        <li>{{ __('Leave the switch OFF and every message costs the single flat rate above. Nothing changes.') }}</li>
                        <li>{{ __('Turn it ON to charge fairly: cheap countries (like India) cost a few credits, costly ones (like the US) cost more — because WhatsApp itself charges you more there.') }}</li>
                        <li>{{ __('You do NOT need to add every country. Set the default once (blank country), then add only the few countries you want priced differently.') }}</li>
                        <li>{{ __('"Service" = a reply within 24 hours of the customer messaging you. Keep it at 0 to send those free, just like WhatsApp does.') }}</li>
                    </ol>
                    <div class="mt-3 rounded-lg bg-paper-0/70 px-3 py-2 font-mono text-[11px] text-ink-700">
                        {{ __('Example: India marketing = 1 credit · US marketing = 12 credits · any reply in the 24-hour window = 0.') }}
                    </div>
                    <p class="mt-2 text-[11px] text-ink-500 italic">
                        {{ __('Your customers never see this page — they only see "you have X credits left". This is just how you price each message behind the scenes.') }}
                    </p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-paper-200">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50 text-ink-500 text-[11px] uppercase tracking-wide">
                            <tr>
                                <th class="text-left font-semibold px-3 py-2">{{ __('Country (ISO-2)') }}</th>
                                <th class="text-left font-semibold px-3 py-2">{{ __('Category') }}</th>
                                <th class="text-left font-semibold px-3 py-2">{{ __('Credits / message') }}</th>
                                <th class="text-center font-semibold px-3 py-2">{{ __('Remove') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @foreach (($messageRates ?? collect()) as $r)
                                <tr>
                                    <td class="px-3 py-2">
                                        <select class="ts-country w-48" name="country_code[]" data-value="{{ $r->country_code }}">
                                            <option value="">{{ __('Any country (default)') }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select name="category[]" class="px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0">
                                            @foreach ($cats as $val => $label)
                                                <option value="{{ $val }}" @selected($r->category === $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" name="credits[]" min="0" max="100000" value="{{ $r->credits }}"
                                            class="w-24 px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0 font-mono">
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <input type="checkbox" name="delete[]" value="{{ $r->id }}" title="{{ __('Delete this rate on save') }}">
                                    </td>
                                </tr>
                            @endforeach
                            @for ($i = 0; $i < 2; $i++)
                                <tr class="bg-paper-50/40">
                                    <td class="px-3 py-2">
                                        <select class="ts-country w-48" name="country_code[]" data-value="">
                                            <option value="">{{ __('Any country (default)') }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select name="category[]" class="px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0">
                                            @foreach ($cats as $val => $label)
                                                <option value="{{ $val }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="number" name="credits[]" min="0" max="100000" placeholder="—"
                                            class="w-24 px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0 font-mono">
                                    </td>
                                    <td></td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>

                <p class="text-[11.5px] text-ink-500 mt-2 max-w-3xl">
                    {{ __('Most specific match wins: country + category → country (any category) → any country + category → the flat rate. A blank Country means "any country"; "Any category" sets a per-country default. Keeping Service = 0 leaves free-window replies free.') }}
                </p>

                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save rates') }}</button>
                    <label class="inline-flex items-center gap-2 text-[12px] text-ink-600 cursor-pointer">
                        <input type="checkbox" name="seed" value="1">
                        {{ __('Also re-seed the starter rate card (India/US/UK/BR/AE/MX)') }}
                    </label>
                </div>
            </form>
        </section>
    </main>
</x-layouts.admin>
