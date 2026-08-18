<x-layouts.admin :title="__('Footer settings')" admin-key="settings">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Footer') }}</span>
        </div>
        <div class="relative flex-1 min-w-0 max-w-[520px] ml-4">
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

    <form method="POST" action="{{ route('admin.settings.footer.update') }}">
        @csrf
        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

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

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin - Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Footer') }}
                        <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Footer title, copyright text, public description, and social URLs shown on the public site footer.') }}</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
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
                                {{ __('Footer content') }}</div>
                            <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('Footer content') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Footer title') }}</span><input
                                    name="footer_title" value="{{ old('footer_title', $settings['footer_title']) }}"
                                    placeholder="{{ brand_name() }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Copyright text') }}</span><input
                                    name="footer_copyright" value="{{ old('footer_copyright', $settings['footer_copyright']) }}"
                                    placeholder="© {{ now()->format('Y') }} {{ brand_name() }}. All rights reserved."
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5 sm:col-span-2"><span
                                    class="text-[11.5px] font-semibold">{{ __('Footer description') }}</span>
                                <textarea name="footer_description" rows="4" maxlength="500"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('footer_description', $settings['footer_description']) }}</textarea>
                            </label>
                        </div>
                    </section>

                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('socials') }}
                            </div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Social links') }}</h2>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Facebook URL') }}</span><input
                                    name="social_facebook" type="url" value="{{ old('social_facebook', $settings['social_facebook']) }}"
                                    placeholder="https://facebook.com/yourpage"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('LinkedIn URL') }}</span><input
                                    name="social_linkedin" type="url" value="{{ old('social_linkedin', $settings['social_linkedin']) }}"
                                    placeholder="https://linkedin.com/company/you"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Instagram URL') }}</span><input
                                    name="social_instagram" type="url" value="{{ old('social_instagram', $settings['social_instagram']) }}"
                                    placeholder="https://instagram.com/you"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span
                                    class="text-[11.5px] font-semibold">{{ __('Twitter / X URL') }}</span><input
                                    name="social_x" type="url" value="{{ old('social_x', $settings['social_x']) }}"
                                    placeholder="https://x.com/you"
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
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('What goes where') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Footer title') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Brand wordmark in the footer. Empty = the platform name is used.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Copyright text') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Bottom strip of every public page. Empty = auto “© year brand”.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Social URLs') }}</div>
                                <p class="text-ink-600 mt-0.5">
                                    {{ __('Empty fields hide the icon. These share the Site Settings social links — editing here updates them everywhere.') }}</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </section>
        </main>
    </form>

</x-layouts.admin>
