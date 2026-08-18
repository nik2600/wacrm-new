<x-layouts.user :title="__('Connect Integration')" nav-key="devices" page="user-connect-index">

    <!-- ========== TOP BAR (shared) ========== -->


    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

            <!-- ===== LEFT RAIL ===== -->
            <aside class="space-y-3">
                <!-- Platform info card -->
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-4 shadow-card">
                    <div id="logo-tile" class="w-12 h-12 rounded-xl mb-3"></div>
                    <div class="font-serif text-[18px] leading-tight" id="aside-platform-name">…</div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mt-1">
                        {{ __('Integration') }}</div>
                    <div class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[10px] font-mono bg-paper-50 border border-paper-200 text-ink-700"
                        id="aside-status">
                        <span class="w-1.5 h-1.5 rounded-full bg-paper-300"></span>
                        Not connected
                    </div>
                </div>

                <!-- Setup steps (sticky-ish progress) -->
                <div class="border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                        {{ __('Setup steps') }}</div>
                    <ol id="aside-steps" class="px-1 space-y-0.5"></ol>
                </div>

                <!-- Help card -->
                <div
                    class="border border-wa-green/30 rounded-2xl bg-wa-bubble/50 p-4 text-[12px] text-ink-700 leading-relaxed">
                    <div class="font-semibold text-ink-900 mb-1 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-wa-green"></span>
                        Need help?
                    </div>
                    Webhooks are auto-installed after connecting — no manual steps. Stuck on credentials? <a
                        href="{{ url('/support') }}"
                        class="text-wa-deep font-semibold underline">{{ __('Contact support') }}</a>.
                </div>
            </aside>

            <!-- ===== MAIN ===== -->
            <section class="space-y-5">

                <!-- Title row (matches wa-campaigns / contacts) -->
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                            <a href="{{ url('/integrations') }}" class="hover:text-wa-deep">{{ __('Integrations') }}</a>
                            <span class="mx-1.5 text-ink-500/60">/</span>
                            <span id="bc-platform">{{ __('Connect') }}</span>
                        </div>
                        <h1 class="font-serif font-normal tracking-tight text-[32px] sm:text-[38px] lg:text-[44px] leading-none">{{ __('Connect') }}
                            <span id="title-platform" class="italic text-wa-deep">…</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl" id="title-desc">
                            {{ __('Connect your store so order, customer, and cart events automatically trigger WhatsApp messages.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ url('/integrations') }}"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M10 4l-4 4 4 4" />
                            </svg>
                            Back
                        </a>
                        <a href="{{ url('/guidebook') }}"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('View docs') }}</a>
                    </div>
                </div>

                <!-- Two-column grid: form (left, wider) + FAQ (right) -->
                <div class="grid grid-cols-1 lg:grid-cols-[1fr_330px] gap-5 items-start">

                    <!-- Connection form card -->
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Step 1') }}
                        </div>
                        <h2 class="font-serif text-[22px] leading-tight mt-0.5 mb-4">{{ __('Store credentials') }}</h2>

                        <form id="connectForm" class="space-y-4" onsubmit="event.preventDefault(); saveConnect();">

                            <!-- Store URL -->
                            <div>
                                <label
                                    class="block text-[12px] font-semibold text-ink-700 mb-1.5">{{ __('Store URL') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input type="url" id="store_url" required
                                    class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="https://yourstore.com" />
                                <p class="text-[10.5px] text-ink-500 mt-1" id="store-hint">
                                    {{ __('The full URL to your store, including https://') }}</p>
                            </div>

                            <!-- Dynamic credential fields -->
                            <div id="cred-fields" class="space-y-4"></div>

                            <!-- Test result -->
                            <div id="testResult" class="hidden text-[12px] px-3 py-2 rounded-lg border"></div>

                            <!-- Webhook URL inline -->
                            <div class="rounded-lg bg-paper-50/60 border border-paper-200 p-3">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                                    {{ __('Webhook URL · auto-configured') }}</div>
                                <div class="font-mono text-[11px] text-ink-700 break-all" id="webhook-url">
                                    https://api.wadesk.app/webhooks/&lt;your-store&gt;</div>
                            </div>

                            <!-- Buttons -->
                            <div class="flex items-center gap-2 pt-2">
                                <button type="button" id="btnTest"
                                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path
                                            d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                    </svg>
                                    Test connection
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 ml-auto">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M2 8l5 5 7-9" />
                                    </svg>
                                    Save &amp; connect
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- FAQ + setup hints column -->
                    <aside class="space-y-4">
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Setup guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5 mb-3">
                                {{ __('Where to find these') }}</h3>
                            <ol id="guide-steps" class="space-y-3"></ol>
                        </div>

                    </aside>

                </div>
            </section>
        </div>
    </main>

</x-layouts.user>
