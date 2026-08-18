<x-layouts.admin :title="__('Site info')" admin-key="settings" page="admin-site-settings">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Site info') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.site-settings.update') }}">
        @csrf

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Public site') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Site') }}
                        <span class="italic text-wa-deep">{{ __('info') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Contact details and social links shared across the footer, contact, and about pages — edit once, they update everywhere. Page text and colours are edited in the') }}
                        <a href="{{ url('/admin/frontend') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Frontend editor') }}</a>.</p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/contact-messages') }}"
                        class="relative px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">
                        {{ __('Contact inbox') }}
                        @if ($unreadCount > 0)
                            <span
                                class="ml-1 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full bg-accent-coral text-paper-0 text-[9.5px] font-bold align-middle">{{ $unreadCount }}</span>
                        @endif
                    </a>
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <x-admin.flash inline />
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            @php
                $groupMeta = [
                    'Company' => ['eyebrow' => 'company', 'pill' => 'identity'],
                    'Contact emails' => ['eyebrow' => 'emails', 'pill' => 'reachable'],
                    'Phone & WhatsApp' => ['eyebrow' => 'phone', 'pill' => 'numbers'],
                    'Social links' => ['eyebrow' => 'social', 'pill' => 'profiles'],
                ];
            @endphp

            <section class="space-y-5 max-w-[980px]">
                @foreach ($groups as $groupName => $fields)
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ $groupMeta[$groupName]['eyebrow'] ?? 'site' }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __($groupName) }}</h2>
                            </div>
                            <span
                                class="rounded-full bg-paper-100 text-ink-600 border border-paper-200 px-2.5 py-1 text-[11px] font-mono">{{ __($groupMeta[$groupName]['pill'] ?? 'site') }}</span>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($fields as [$key, $label, $type, $default, $placeholder])
                                <label class="space-y-1.5">
                                    <span class="text-[11.5px] font-semibold">{{ __($label) }}</span>
                                    <input
                                        type="{{ $type === 'url' ? 'url' : ($type === 'email' ? 'email' : 'text') }}"
                                        name="{{ $key }}" value="{{ old($key, $values[$key] ?? '') }}"
                                        placeholder="{{ $placeholder }}"
                                        class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] {{ in_array($type, ['email', 'url']) ? 'font-mono' : '' }} focus:outline-none focus:border-wa-deep">
                                    @error($key)
                                        <span class="text-[10.5px] text-accent-coral">{{ $message }}</span>
                                    @enderror
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </section>

        </main>
    </form>

</x-layouts.admin>
