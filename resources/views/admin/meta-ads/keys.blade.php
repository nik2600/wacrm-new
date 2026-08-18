<x-layouts.admin :title="__('Meta Ads keys')" admin-key="metaads" page="admin-meta-ads-keys">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Keys') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="max-w-none mx-auto px-4 sm:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Meta Ads') }}</div>
            <h1 class="font-serif text-[28px] sm:text-[40px] leading-none">{{ __('Meta Ads') }} <span
                    class="italic text-wa-deep">{{ __('fallback keys') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ __('Platform-wide Click-to-WhatsApp credentials. A workspace that connects its OWN Meta Ads keys (on /meta-ads) always uses those — these only fill in for workspaces that haven\'t. Token is encrypted at rest.') }}
            </p>
        </div>

        <x-admin.flash />

        <form method="POST" action="{{ route('admin.meta-ads.keys.update') }}" class="space-y-5 max-w-3xl">
            @csrf

            <section class="bg-paper-0 border border-paper-200 rounded-2xl p-6 shadow-card">
                <h2 class="font-serif text-[22px]">{{ __('Credentials') }}</h2>
                <p class="text-[12px] text-ink-500 mt-0.5 mb-4">
                    {{ __('From Meta Business Manager → Business Settings. The access token must be a System-User token with') }}
                    <code class="font-mono">ads_management</code> (+ <code class="font-mono">pages_manage_ads</code>).
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="text-[12px] text-ink-700 sm:col-span-2">{{ __('Access token (System User)') }}
                        <input type="password" name="token" autocomplete="new-password"
                            placeholder="{{ $hasToken ? __('•••••••••• (saved — leave blank to keep)') : 'EAAG...' }}"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                        @if ($hasToken)
                            <label class="mt-1.5 flex items-center gap-2 text-[11px] text-ink-500">
                                <input type="checkbox" name="clear_token" value="1"
                                    class="rounded border-paper-300"> {{ __('Clear the saved token') }}
                            </label>
                        @endif
                    </label>

                    <label class="text-[12px] text-ink-700">{{ __('Ad account ID') }}
                        <div class="mt-1 flex items-center rounded-lg border border-paper-200 bg-white overflow-hidden">
                            <span
                                class="px-2.5 py-1.5 text-[12.5px] font-mono text-ink-500 bg-paper-50 border-r border-paper-200">act_</span>
                            <input type="text" name="ad_account_id" value="{{ $adAccountId }}"
                                placeholder="1234567890"
                                class="flex-1 px-3 py-1.5 text-[13px] font-mono focus:outline-none" />
                        </div>
                    </label>

                    <label class="text-[12px] text-ink-700">{{ __('Facebook Page ID') }}
                        <input type="text" name="page_id" value="{{ $pageId }}" placeholder="1029384756"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                    </label>

                    <label class="text-[12px] text-ink-700">{{ __('WhatsApp number (E.164 digits)') }}
                        <input type="text" name="phone" value="{{ $phone }}" placeholder="{{ __('Number with country code') }}"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                        <span
                            class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Actual digits, NOT the phone_number_id. Used in promoted_object + the wa.me link.') }}</span>
                    </label>

                    <label class="text-[12px] text-ink-700">{{ __('WABA ID') }}
                        <input type="text" name="waba_id" value="{{ $wabaId }}" placeholder="{{ __('Meta WABA ID') }}"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                    </label>

                    <label class="text-[12px] text-ink-700">{{ __('Phone number ID (Cloud API)') }}
                        <input type="text" name="phone_number_id" value="{{ $phoneNumberId }}"
                            placeholder="{{ __('Meta phone number ID') }}"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                        <span
                            class="block text-[10.5px] text-ink-500 mt-0.5">{{ __("Meta's internal id — only for the messaging side.") }}</span>
                    </label>

                    <label class="text-[12px] text-ink-700">{{ __('Graph API version') }}
                        <input type="text" name="graph_version" value="{{ $graphVersion }}" placeholder="v23.0"
                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono" />
                    </label>
                </div>
            </section>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ url('/admin/settings') }}"
                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Back') }}</a>
                <button type="submit"
                    class="px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">{{ __('Save keys') }}</button>
            </div>
        </form>

    </main>
</x-layouts.admin>
