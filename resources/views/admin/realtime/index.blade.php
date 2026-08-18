<x-layouts.admin :title="__('Real-time')" admin-key="realtime" page="admin-realtime-index">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Real-time') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.realtime.save') }}">
        @csrf

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Live broadcasting') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Real-time') }}
                        <span class="italic text-wa-deep">{{ __('notifications') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('One Pusher app powers the live Team Inbox for every workspace — new messages, status changes and snooze wake-ups appear instantly instead of waiting for the poll. Platform-wide; users never enter keys.') }}
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
                                    {{ __('broadcasting') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Live delivery') }}</h2>
                            </div>
                            <span
                                class="rounded-full {{ $enabled ? 'bg-wa-mint text-wa-deep border-wa-green/40' : 'bg-paper-100 text-ink-500 border-paper-200' }} border px-2.5 py-1 text-[11px] font-mono">{{ $enabled ? __('live') : __('polling') }}</span>
                        </div>
                        <div class="p-5">
                            <label class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between gap-3">
                                <span>
                                    <span class="block text-[12.5px] font-semibold">{{ __('Enable real-time') }}</span>
                                    <span class="block text-[10.5px] text-ink-500 mt-0.5">{{ __('Flips broadcasting from log → Pusher. Off = the inbox keeps polling.') }}</span>
                                </span>
                                <span class="toggle"><input type="hidden" name="realtime_enabled" value="0"><input
                                        type="checkbox" name="realtime_enabled" value="1"
                                        @checked(old('realtime_enabled', $enabled))><span class="track"></span><span
                                        class="thumb"></span></span>
                            </label>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('pusher-app') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Pusher credentials') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1">
                                {{ __('Get free keys at') }}
                                <a href="https://dashboard.pusher.com" target="_blank" rel="noopener"
                                    class="text-wa-deep font-semibold">dashboard.pusher.com</a>
                                {{ __('— create a Channels app, then copy its App ID, Key, Secret and Cluster here.') }}
                            </p>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('App ID') }}</span>
                                <input name="pusher_app_id" value="{{ old('pusher_app_id', $app_id) }}"
                                    placeholder="1234567"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Cluster') }}</span>
                                <input name="pusher_cluster" value="{{ old('pusher_cluster', $cluster) }}"
                                    placeholder="mt1"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Key') }} <span
                                        class="text-ink-500 font-normal">({{ __('public') }})</span></span>
                                <input name="pusher_app_key" value="{{ old('pusher_app_key', $key) }}"
                                    placeholder="abcdef1234567890"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Secret') }}</span>
                                <input name="pusher_app_secret" type="password" autocomplete="new-password"
                                    placeholder="{{ $hasSecret ? '••• ' . __('stored, leave blank to keep') : __('paste secret') }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="block text-[10.5px] text-ink-500">{{ __('Stored encrypted at rest. Never sent to the browser.') }}</span>
                            </label>
                        </div>
                    </section>

                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('How it works') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('One app, all workspaces') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('The server broadcasts with the secret; every page loads only the public key. Users get live updates automatically once this is on.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('What goes live') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Inbound messages (WhatsApp, WABA, Instagram) and operator replies push a lightweight signal — the inbox list + open thread refresh instantly.') }}
                                </p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Polling stays as fallback') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('If Pusher is off or unreachable, the inbox keeps its slower poll so nothing is ever missed.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Test it') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Use "Save & test connection" below to fire a live event to Pusher and confirm the credentials work before you rely on them.') }}
                        </p>
                    </div>
                </aside>

            </section>

            {{-- Sticky save bar — keeps Save + the live connection test reachable
 without scrolling back up. --}}
            <div
                class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply only after you save.') }}</span>
                <div class="flex items-center gap-2">
                    <button type="submit" formaction="{{ route('admin.settings.realtime.test') }}"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save & test connection') }}</button>
                    <button type="submit"
                        class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

        </main>

    </form>

</x-layouts.admin>
