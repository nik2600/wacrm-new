<x-layouts.admin :title="__('Message provider')" admin-key="settings">


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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Message provider') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4 hidden md:block">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search inside settings...') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">{{ __('CMD K') }}</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.message.update') }}">
        @csrf
        <main class="px-4 sm:px-7 py-7 space-y-5">

            @if (session('success'))
                <div class="rounded-2xl border border-accent-mint/40 bg-accent-mint/10 px-4 py-3 text-[13px] text-ink-800 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-mint shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7" /></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-3 text-[13px] text-ink-800">
                    {{ __('Please fix the highlighted fields.') }}
                </div>
            @endif

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin - Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                        {{ __('Message provider') }} <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Platform-level Twilio / Facebook / WhatsApp Business credentials. Note: the primary WhatsApp Cloud connection is configured under WhatsApp message providers, and per-workspace Twilio is connected on each Devices page.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('providers') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Provider toggles') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between cursor-pointer">
                                <span class="text-[13px] font-semibold">{{ __('Twilio enabled') }}</span>
                                <input type="checkbox" name="message_twilio_enabled" value="1"
                                    @checked(old('message_twilio_enabled', $settings['message_twilio_enabled']))
                                    class="w-4 h-4 rounded border-paper-300 text-wa-deep focus:ring-wa-deep/20">
                            </label>
                            <label class="rounded-2xl border border-wa-green/40 p-4 flex items-center justify-between cursor-pointer">
                                <span class="text-[13px] font-semibold">{{ __('WhatsApp Business API') }}</span>
                                <input type="checkbox" name="message_whatsapp_enabled" value="1"
                                    @checked(old('message_whatsapp_enabled', $settings['message_whatsapp_enabled']))
                                    class="w-4 h-4 rounded border-paper-300 text-wa-deep focus:ring-wa-deep/20">
                            </label>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('facebook') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Facebook credentials') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Facebook ID') }}</span><input
                                    name="message_facebook_id" value="{{ old('message_facebook_id', $settings['message_facebook_id']) }}"
                                    placeholder="666572953476"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Facebook auth token') }}</span><input
                                    type="password" name="message_facebook_token"
                                    placeholder="{{ $settings['message_has_facebook_token'] ? '••• stored, leave blank to keep' : 'paste token' }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('WhatsApp API version') }}</span><input
                                    name="message_whatsapp_api_version" value="{{ old('message_whatsapp_api_version', $settings['message_whatsapp_api_version']) }}"
                                    placeholder="v23.0"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('WhatsApp business ID') }}</span><input
                                    name="message_whatsapp_business_id" value="{{ old('message_whatsapp_business_id', $settings['message_whatsapp_business_id']) }}"
                                    placeholder="2455663413065406"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('twilio') }}
                            </div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Twilio credentials') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Account SID') }}</span><input
                                    name="message_twilio_sid" value="{{ old('message_twilio_sid', $settings['message_twilio_sid']) }}"
                                    placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Auth token') }}</span><input
                                    type="password" name="message_twilio_token"
                                    placeholder="{{ $settings['message_has_twilio_token'] ? '••• stored, leave blank to keep' : 'paste token' }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Sender number') }}</span><input
                                    name="message_twilio_sender" value="{{ old('message_twilio_sender', $settings['message_twilio_sender']) }}"
                                    placeholder="+14155238886"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Status webhook') }}</span><input
                                    name="message_twilio_webhook" type="url" value="{{ old('message_twilio_webhook', $settings['message_twilio_webhook']) }}"
                                    placeholder="{{ url('/webhooks/twilio') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-5 py-2.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                    </div>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Provider setup') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Facebook ID') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Open') }} <a
                                        href="https://developers.facebook.com/apps" target="_blank"
                                        class="text-wa-deep underline">{{ __('developers.facebook.com/apps') }}</a> → your
                                    app → Basic settings.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Twilio Account SID') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Find in') }} <a href="https://console.twilio.com"
                                        target="_blank" class="text-wa-deep underline">{{ __('Twilio Console') }}</a>
                                    dashboard. Pair with the auth token from the same panel.</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Secrets') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Tokens are stored hidden. Leave a token field blank to keep the saved value; re-paste only to rotate.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-accent-amber/10 border border-accent-amber/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px] text-[#7B5A14]">{{ __('Heads up') }}</div>
                        <p class="text-[11.5px] text-ink-700 mt-1">
                            {{ __('Switching providers mid-campaign can drop in-flight sends. Pause the queue first.') }}
                        </p>
                    </div>
                </aside>
            </section>

        </main>
    </form>

</x-layouts.admin>
