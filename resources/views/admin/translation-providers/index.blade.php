<x-layouts.admin :title="__('Translation providers')" page="admin-translation-providers-index">

    <main class="max-w-none mx-auto px-4 sm:px-7 py-7 space-y-5">

        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · System') }}
            </div>
            <h1 class="font-serif text-[30px] sm:text-[44px] leading-none">{{ __('Translation providers') }}</h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                {{ __("Multi-language auto-reply uses one of these providers to translate inbound messages and craft replies in the customer's language. MyMemory is active out-of-box (free, no key) — plug in DeepL / Google Cloud / LibreTranslate for higher quality or self-hosted privacy.") }}
            </p>
        </div>

        <x-admin.flash />

        @php
            $officialOnly = \App\Services\Translation\TranslationProviderManager::isOfficialOnlyMode();
            $unofficialSlugs = \App\Services\Translation\TranslationProviderManager::UNOFFICIAL_SLUGS;
        @endphp

        {{-- Official-only lockdown panel --}}
        <div
            class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card flex items-center justify-between gap-4 flex-wrap {{ $officialOnly ? 'border-wa-deep/40 bg-wa-mint/30' : '' }}">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-serif text-[18px]">{{ __('Compliance lockdown') }}</h3>
                    <span
                        class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full {{ $officialOnly ? 'bg-wa-deep text-paper-0' : 'bg-paper-100 text-ink-700' }}">{{ $officialOnly ? 'On' : 'Off' }}</span>
                </div>
                <p class="text-[12px] text-ink-500 mt-1 max-w-3xl">
                    When on, the fallback chain skips <span
                        class="font-mono">{{ implode(', ', $unofficialSlugs) }}</span> and routes auto-reply translation
                    only through official paid APIs (DeepL, Google Cloud). Required by some enterprise security policies
                    that prohibit unofficial 3rd-party endpoints.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.translation-providers.lockdown') }}" class="shrink-0"
                data-confirm="{{ $officialOnly ? 'Turn lockdown OFF? Free drivers will rejoin the fallback chain.' : 'Turn lockdown ON? Free drivers will be skipped — only paid APIs will translate.' }}"
                data-confirm-title="{{ $officialOnly ? 'Disable lockdown' : 'Enable lockdown' }}"
                data-confirm-text="{{ $officialOnly ? 'Disable lockdown' : 'Enable lockdown' }}">@csrf
                <button
                    class="px-4 py-2 rounded-full {{ $officialOnly ? 'bg-paper-0 border border-paper-200 hover:bg-paper-50 text-ink-900' : 'bg-wa-deep hover:bg-wa-teal text-paper-0' }} text-[12px] font-semibold">
                    {{ $officialOnly ? 'Disable lockdown' : 'Enable lockdown' }}
                </button>
            </form>
        </div>

        @forelse ($providers as $p)
            @php $isLockdownSkipped = $officialOnly && in_array($p->slug, $unofficialSlugs, true); @endphp
            <section
                class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden {{ $isLockdownSkipped ? 'opacity-60' : '' }}">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-serif text-[20px] leading-tight">{{ $p->name }}</h3>
                            <span
                                class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full {{ $p->is_active ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-700' }}">
                                {{ $p->is_active ? 'Active' : 'Disabled' }}
                            </span>
                            @if ($p->is_default)
                                <span
                                    class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-accent-amber/20 text-[#8B5A14] border border-accent-amber/40">{{ __('Default') }}</span>
                            @endif
                            @if ($isLockdownSkipped)
                                <span
                                    class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/40"
                                    title="{{ __('Skipped because compliance lockdown is on.') }}">{{ __('Lockdown · skipped') }}</span>
                            @endif
                        </div>
                        <div class="text-[11.5px] text-ink-500 mt-0.5 font-mono">slug:
                            {{ $p->slug }}{{ $p->description ? ' · ' . $p->description : '' }}</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if (!$p->is_default && $p->is_active)
                            <form method="POST" action="{{ route('admin.translation-providers.default', $p->id) }}"
                                class="inline">@csrf
                                <button
                                    class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold">{{ __('Make default') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.translation-providers.toggle', $p->id) }}"
                            class="inline">@csrf
                            <button
                                class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold">{{ $p->is_active ? 'Disable' : 'Activate' }}</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.translation-providers.update', $p->id) }}"
                    class="p-5 space-y-4">
                    @csrf @method('PATCH')

                    @if (!empty($p->credential_fields))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($p->credential_fields as $key => $spec)
                                @php $existingVal = $p->credentials_decrypted[$key] ?? ''; @endphp
                                <label
                                    class="text-[12px] text-ink-700 {{ ($spec['type'] ?? 'text') === 'textarea' ? 'col-span-2' : '' }}">
                                    {{ $spec['label'] ?? $key }} @if (!empty($spec['required']))
                                        <span class="text-accent-coral">*</span>
                                    @endif
                                    @if (($spec['type'] ?? 'text') === 'password')
                                        <input type="password" name="credentials[{{ $key }}]"
                                            placeholder="{{ $existingVal ? '••••••• (leave blank to keep)' : '' }}"
                                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono">
                                    @else
                                        <input type="text" name="credentials[{{ $key }}]"
                                            value="{{ $existingVal }}"
                                            class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono"
                                            @if (!empty($spec['placeholder'])) placeholder="{{ $spec['placeholder'] }}" @endif>
                                    @endif
                                    @if (!empty($spec['hint']))
                                        <span class="block text-[10px] text-ink-500 mt-0.5">{{ $spec['hint'] }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div
                            class="rounded-lg border border-dashed border-paper-200 bg-paper-50 px-4 py-3 text-[12px] text-ink-500">
                            {{ __('No credentials required — this provider works out of the box.') }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-paper-100 pt-4">
                        <label class="text-[12px] text-ink-700">{{ __('Sort order') }} <input type="number"
                                name="sort_order" value="{{ $p->sort_order }}" min="0"
                                class="mt-1 w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-white text-[13px] font-mono">
                            <span
                                class="block text-[10px] text-ink-500 mt-0.5">{{ __('Lower = higher priority in the fallback chain.') }}</span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">Save
                            {{ $p->name }}</button>
                    </div>
                </form>
            </section>
        @empty
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-12 text-center">
                <div class="font-serif text-[22px] text-ink-700">{{ __('No translation providers configured') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-2">{{ __('Run') }} <code
                        class="bg-paper-100 px-1.5 py-0.5 rounded font-mono text-[11px]">php artisan db:seed
                        --class=TranslationProviderSeeder</code> to populate.</p>
            </div>
        @endforelse

    </main>

</x-layouts.admin>
