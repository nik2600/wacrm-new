<x-layouts.admin :title="__('Storage')" admin-key="storage" page="admin-storage-index">

    @php
        $cur = old('provider', $cfg['provider'] ?? 's3');
        $pc = $cfg['providers'][$cur] ?? [];
        $val = fn($k, $d = '') => old('cfg.' . $k, $pc[$k] ?? $d);
        $hasSecret = !empty($pc['__has_secret']);
        $hasBunnyKey = !empty($pc['__has_access_key']);
        $vis = old('visibility', $cfg['visibility'] ?? 'private');
    @endphp

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Storage') }}</span>
        </div>
        <div class="relative flex-1 max-w-[520px] ml-4 hidden md:block">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" />
            </svg>
            <input class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition" placeholder="{{ __('Search inside settings...') }}" />
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin - Media storage') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Cloud') }} <span class="italic text-wa-deep">{{ __('storage') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Send all client media attachments to your own bucket — Amazon S3, Wasabi, Bunny.net, DigitalOcean Spaces, Cloudflare R2 or any S3-compatible store — instead of the app server. When off, media stays on the local disk, so nothing breaks until you turn it on.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <button type="submit" form="storage-form" formaction="{{ route('admin.storage.test') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save & test connection') }}</button>
                <button type="submit" form="storage-form"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>
        </div>

        <x-admin.flash />

        <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

            <form id="storage-form" method="POST" action="{{ route('admin.storage.save') }}" class="space-y-5 min-w-0">
                @csrf

                {{-- Status + master toggle --}}
                <section class="bg-paper-0 border {{ $enabled ? 'border-wa-green/40' : 'border-paper-200' }} rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('storage · status') }}</div>
                            <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Cloud media routing') }}</h2>
                            <p class="text-[12px] text-ink-600 mt-1 max-w-2xl">
                                @if ($enabled)
                                    <span class="text-wa-deep font-semibold">{{ __('Active') }} — {{ $activeLabel }}.</span> {{ __('New uploads go to your bucket.') }}
                                @elseif ($hasConfig)
                                    <span class="text-accent-amber font-semibold">{{ __('Configured but turned off.') }}</span> {{ __('Media still on the local disk. Test, then enable.') }}
                                @else
                                    {{ __('Local disk (no cloud provider set up yet).') }}
                                @endif
                            </p>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer shrink-0">
                            <span class="text-[12px] text-ink-700">{{ $enabled ? __('Enabled') : __('Disabled') }}</span>
                            <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $cfg['enabled'] ?? false)) class="sr-only peer">
                                <span class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="space-y-1.5 block">
                            <span class="text-[11.5px] font-semibold">{{ __('Provider') }}</span>
                            <select name="provider" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                @foreach ($providers as $key => $label)
                                    <option value="{{ $key }}" @selected($cur === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1.5 block">
                            <span class="text-[11.5px] font-semibold">{{ __('File visibility') }}</span>
                            <select name="visibility" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="private" @selected($vis === 'private')>{{ __('Private (signed URLs)') }}</option>
                                <option value="public" @selected($vis === 'public')>{{ __('Public') }}</option>
                            </select>
                        </label>
                    </div>
                </section>

                {{-- Credentials --}}
                <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('credentials') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Bucket connection') }}</h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Access key ID') }} <span class="text-accent-coral">*</span></span>
                                <input name="cfg[key]" value="{{ $val('key') }}" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('S3 / Wasabi / Spaces / R2 / MinIO.') }}</span></label>
                            <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Secret access key') }} <span class="text-accent-coral">*</span></span>
                                <input name="cfg[secret]" type="password" autocomplete="new-password" placeholder="{{ $hasSecret ? '••• stored, leave blank to keep' : 'paste secret' }}" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                <span class="text-[11px] text-ink-500">{{ __('Hidden after save; re-paste only to rotate.') }}</span></label>
                            <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Region') }}</span>
                                <input name="cfg[region]" value="{{ $val('region') }}" placeholder="us-east-1" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Bucket') }} <span class="text-accent-coral">*</span></span>
                                <input name="cfg[bucket]" value="{{ $val('bucket') }}" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5 sm:col-span-2"><span class="text-[11.5px] font-semibold">{{ __('Endpoint') }}</span>
                                <input name="cfg[endpoint]" value="{{ $val('endpoint') }}" placeholder="https://… (required for Spaces / R2 / MinIO; auto for Wasabi & Bunny)" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="space-y-1.5 sm:col-span-2"><span class="text-[11.5px] font-semibold">{{ __('Public / CDN URL') }} <span class="text-ink-500 font-normal">· {{ __('optional') }}</span></span>
                                <input name="cfg[cdn_url]" value="{{ $val('cdn_url') }}" placeholder="https://cdn.example.com" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            <label class="flex items-center gap-2 sm:col-span-2 text-[12.5px]">
                                <input type="checkbox" name="cfg[use_path_style_endpoint]" value="1" @checked($val('use_path_style_endpoint')) class="w-4 h-4 rounded accent-wa-deep">
                                {{ __('Use path-style endpoint (MinIO / some S3-compatible stores)') }}</label>
                        </div>

                        <div class="border-t border-paper-200 pt-4">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2.5">{{ __('Bunny.net only') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Storage zone') }}</span>
                                    <input name="cfg[storage_zone]" value="{{ $val('storage_zone') }}" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                                <label class="space-y-1.5"><span class="text-[11.5px] font-semibold">{{ __('Storage password (access key)') }}</span>
                                    <input name="cfg[access_key]" type="password" autocomplete="new-password" placeholder="{{ $hasBunnyKey ? '••• stored, leave blank to keep' : 'paste password' }}" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            </div>
                        </div>

                        <label class="space-y-1.5 block border-t border-paper-200 pt-4"><span class="text-[11.5px] font-semibold">{{ __('Base folder path') }} <span class="text-ink-500 font-normal">· {{ __('optional') }}</span></span>
                            <input name="base_path" value="{{ old('base_path', $cfg['base_path'] ?? '') }}" placeholder="wadesk" class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            <span class="text-[11px] text-ink-500">{{ __('Prefix prepended to every uploaded object.') }}</span></label>
                    </div>
                </section>
            </form>

            <aside class="space-y-4 lg:sticky lg:top-[88px]">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick guide') }}</div>
                        <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Set it up') }}</h3>
                    </div>
                    <div class="p-4 text-[12px] text-ink-700">
                        <ul class="space-y-2.5">
                            <li>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('S3 / Wasabi') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Access key + secret + region + bucket. Wasabi endpoint is auto from the region.') }}</p>
                            </li>
                            <li>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Bunny.net') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Use the Bunny fields — storage zone + password. Region is the zone region (de, ny, la…).') }}</p>
                            </li>
                            <li>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Spaces / R2 / MinIO') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Fill the S3 fields + paste the Endpoint URL. Tick path-style for MinIO.') }}</p>
                            </li>
                            <li>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Always test first') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('"Save & test connection" writes + deletes a probe file before you flip Enabled on.') }}</p>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                    <div class="font-semibold text-[12.5px]">{{ __('Safe by default') }}</div>
                    <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Existing files stay where they are. Only new uploads after you enable this go to the bucket — nothing is migrated or deleted.') }}</p>
                </div>
            </aside>

        </section>

    </main>

</x-layouts.admin>
