<x-layouts.admin :title="__('Add-ons')" admin-key="settings" page="settings-extensions">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Add-ons') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5" id="wd-extensions" data-core-version="{{ $coreVersion }}">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Add-ons') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">
                    {{ __('Install an') }} <span class="italic text-wa-deep">{{ __('add-on') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Add-ons merge extra features into this installation — Instagram is the first. Verify your purchase code, upload the package, and it installs in place. Your data and settings are untouched.') }}
                </p>
            </div>
            <div class="text-[11px] font-mono text-ink-500 shrink-0 pb-1">
                {{ __('Core') }} <span class="text-ink-900">v{{ $coreVersion }}</span>
            </div>
        </div>

        {{-- Different thing from BILLING add-ons — say so, because both live in
             admin and an operator hunting for one should not land on the other. --}}
        <div class="bg-wa-bubble/40 border border-paper-200 rounded-lg px-4 py-2.5 text-[11.5px] text-ink-700">
            {{ __('Looking for the paid feature packs customers buy on top of a plan?') }}
            <a href="{{ route('admin.addons.index') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Those are plan add-ons (billing)') }}</a>{{ __('. This page installs code.') }}
        </div>

        {{-- ═══ Instagram (Instaflow) — connect by URL + secret ══════════════
             Instaflow is its OWN deployment. No package upload — the operator
             clicks Connect on this card, a modal takes the URL + shared secret,
             and we run a handshake so both sides prove they share the secret. --}}
        @php
            $instaflowError = session('error') || $errors->any();
            // ONE ENGINE AT A TIME: if the native Instagram add-on is installed
            // (addon/instagram/ present), the remote InstaMagic connect row is
            // BLOCKED — the operator must remove the add-on first. Matches the
            // server-side guard in ExtensionController::connectInstaflow().
            $nativeInstagramInstalled = is_dir(base_path('addon/instagram'));
        @endphp
        <section class="bg-paper-0 border border-paper-200 rounded-xl p-5">
            @if (session('status'))
                <div class="mb-3 text-[11.5px] rounded-lg px-3 py-2 bg-wa-deep/10 text-wa-deep">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-3 text-[11.5px] rounded-lg px-3 py-2 bg-accent-coral/10 text-[#A1431F]">{{ session('error') }}</div>
            @endif

            <div class="flex items-center gap-4 flex-wrap">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#833AB4] via-[#E1306C] to-[#F77737] grid place-items-center shrink-0">
                    <svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2.5" y="2.5" width="19" height="19" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-[14px] font-semibold flex items-center gap-2 flex-wrap">
                        {{ __('Instagram') }}
                        @if ($instaflowConnected)
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep">
                                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('Connected') }}
                            </span>
                        @elseif ($instaflowUrl)
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-accent-coral/10 text-[#A1431F]">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>{{ __('Not connected') }}
                            </span>
                        @endif
                        @if ($nativeInstagramInstalled)
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-paper-100 text-ink-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-ink-400"></span>{{ __('Blocked — native add-on active') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('Runs as a separate :igbrand deployment. Connect by URL + secret — no upload.', ['igbrand' => ig_brand_name()]) }}
                    </p>
                    @if ($instaflowUrl)
                        <div class="font-mono text-[10.5px] text-ink-500 mt-0.5 break-all">{{ $instaflowUrl }}</div>
                    @endif
                </div>
                @if ($nativeInstagramInstalled)
                    {{-- One engine at a time — remote connect disabled while the native add-on is installed. --}}
                    <div class="shrink-0 text-right max-w-[210px]">
                        <button type="button" disabled
                            class="px-4 py-2 rounded-lg bg-paper-100 text-ink-400 text-[12px] font-semibold cursor-not-allowed">
                            {{ __('Manage') }}
                        </button>
                        <div class="text-[10.5px] text-[#A1431F] mt-1">{{ __('Native Instagram add-on is installed. Remove it below to connect a remote :igbrand.', ['igbrand' => ig_brand_name()]) }}</div>
                    </div>
                @else
                    <button type="button" data-instaflow-open
                        class="px-4 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal shrink-0">
                        {{ $instaflowConnected || $instaflowUrl ? __('Manage') : __('Connect') }}
                    </button>
                @endif
            </div>
        </section>

        {{-- Connect modal — opened by the card's Connect button. --}}
        <div id="instaflow-modal" data-autoopen="{{ $instaflowError ? '1' : '0' }}"
            class="hidden fixed inset-0 z-[120] items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-sm" data-instaflow-close></div>
            <div class="relative bg-paper-0 border border-paper-200 rounded-2xl shadow-2xl w-full max-w-md p-5">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-[15px] font-semibold flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-gradient-to-br from-[#833AB4] via-[#E1306C] to-[#F77737] grid place-items-center">
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2.5" y="2.5" width="19" height="19" rx="5" /><circle cx="12" cy="12" r="4" /><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" /></svg>
                        </span>
                        {{ __('Connect Instagram') }}
                    </h3>
                    <button type="button" data-instaflow-close class="w-7 h-7 rounded-full grid place-items-center text-ink-400 hover:bg-paper-100 hover:text-ink-900">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8" /></svg>
                    </button>
                </div>
                <p class="text-[11.5px] text-ink-500 mb-4">
                    {{ __('Paste your :igbrand deployment URL and the shared secret token. WaDesk verifies the connection over API — nothing is uploaded.', ['igbrand' => ig_brand_name()]) }}
                </p>
                <form method="POST" action="{{ route('admin.extensions.instaflow.connect') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __(':igbrand URL', ['igbrand' => ig_brand_name()]) }}</label>
                        <input type="url" name="instaflow_url" required value="{{ old('instaflow_url', $instaflowUrl) }}"
                            placeholder="https://insta.yourdomain.com"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        @error('instaflow_url')<div class="text-[10.5px] text-accent-coral mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Display name') }}</label>
                        <input type="text" name="instaflow_brand" maxlength="40" value="{{ old('instaflow_brand', $instaflowBrand) }}"
                            placeholder="IgDesk"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        <p class="text-[10.5px] text-ink-500 mt-1">{{ __('Shown on every "Sync from …", "Manage on …" and "… URL" label across the app.') }}</p>
                        @error('instaflow_brand')<div class="text-[10.5px] text-accent-coral mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-ink-600 mb-1">{{ __('Shared secret token') }}</label>
                        <input type="password" name="instaflow_secret" autocomplete="off"
                            placeholder="{{ $instaflowHasSecret ? __('•••••••• saved — leave blank to keep') : __('paste the shared secret') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-1 flex-wrap">
                        <span class="text-[10.5px] text-ink-500 font-mono">
                            @if ($instaflowLastCheck) {{ __('Last checked') }}: {{ $instaflowLastCheck }} @endif
                        </span>
                        <div class="flex items-center gap-2">
                            @if ($instaflowConnected || $instaflowUrl)
                                {{-- Disconnect: same form, but this submit posts to the
                                     disconnect route (formaction) and skips the required-URL
                                     check (formnovalidate). Reuses the form's CSRF token. --}}
                                <button type="submit" formnovalidate formmethod="POST"
                                    formaction="{{ route('admin.extensions.instaflow.disconnect') }}"
                                    data-danger="1"
                                    data-confirm-title="{{ __('Disconnect Instagram?') }}"
                                    data-confirm-text="{{ __('Yes, disconnect') }}"
                                    data-confirm="{{ __('This disconnects Instagram from WaDesk. Instagram will immediately disappear from the dashboard, analytics, inbox, flows, templates and auto-replies for every workspace, and none of it can be used until you reconnect. Continue?') }}"
                                    class="px-3.5 py-2 rounded-lg border border-accent-coral/40 text-accent-coral text-[12px] font-medium hover:bg-accent-coral/10">{{ __('Disconnect') }}</button>
                            @endif
                            <button type="button" data-instaflow-close
                                class="px-3.5 py-2 rounded-lg border border-paper-200 text-[12px] font-medium hover:bg-paper-50">{{ __('Cancel') }}</button>
                            <button type="submit"
                                class="px-4 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save & connect') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ═══ STEP 1 — licence ═══════════════════════════════════════════ --}}
        <section class="bg-paper-0 border border-paper-200 rounded-xl p-5">
            <div class="flex items-start gap-3 mb-3">
                <div class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[11px] font-mono font-semibold shrink-0">1</div>
                <div>
                    <h2 class="text-[14px] font-semibold">{{ __('Verify your purchase code') }}</h2>
                    <p class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('The same WaDesk licence that unlocks updates. Add-ons are tied to it.') }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input id="ext-code" type="text" autocomplete="off" spellcheck="false"
                    placeholder="{{ __('e.g. 8f2c1a44-...') }}"
                    class="flex-1 min-w-[240px] px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                <button id="ext-verify" type="button"
                    class="px-4 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">
                    {{ __('Verify') }}
                </button>
            </div>
            <div id="ext-verify-msg" class="hidden mt-2 text-[11.5px] rounded-lg px-3 py-2"></div>
        </section>

        {{-- ═══ STEP 2 — package ═══════════════════════════════════════════ --}}
        {{-- Locked until step 1 passes. The server re-verifies on upload too —
             this is only a convenience, not the security boundary. --}}
        <section id="ext-step2" class="bg-paper-0 border border-paper-200 rounded-xl p-5 opacity-50 pointer-events-none transition">
            <div class="flex items-start gap-3 mb-3">
                <div class="w-7 h-7 rounded-full bg-paper-100 text-ink-600 grid place-items-center text-[11px] font-mono font-semibold shrink-0">2</div>
                <div>
                    <h2 class="text-[14px] font-semibold">{{ __('Upload the add-on package') }}</h2>
                    <p class="text-[11.5px] text-ink-500 mt-0.5">
                        {{ __('The ZIP is inspected before anything is written — a malformed or incompatible package is refused with your install untouched.') }}
                    </p>
                </div>
            </div>

            <label for="ext-file"
                class="block border border-dashed border-paper-200 rounded-xl px-4 py-8 text-center cursor-pointer hover:border-wa-deep hover:bg-paper-50/50 transition">
                <svg viewBox="0 0 16 16" class="w-6 h-6 mx-auto mb-2 text-ink-500" fill="none" stroke="currentColor"
                    stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 11V3" /><path d="M5 6l3-3 3 3" />
                    <path d="M2.5 11v1.5a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1V11" />
                </svg>
                <div class="text-[12.5px] font-medium" id="ext-file-label">{{ __('Choose an add-on .zip') }}</div>
                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Max 100 MB') }}</div>
                <input id="ext-file" type="file" accept=".zip" class="hidden">
            </label>

            <div class="flex items-center justify-end gap-2 mt-3">
                <button id="ext-install" type="button" disabled
                    class="px-4 py-2 rounded-lg bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ __('Install') }}
                </button>
            </div>
            <div id="ext-install-msg" class="hidden mt-2 text-[11.5px] rounded-lg px-3 py-2"></div>
        </section>

        {{-- ═══ Installed — WaDesk-style cards ═════════════════════════════ --}}
        @php
            // A ZIP-installed add-on now lives in addon/<slug>/ AND carries a DB
            // row, so it would otherwise appear twice (once as an in-place module,
            // once as a DB extension). The DB row is authoritative — it's the one
            // with the Remove action — so hide the in-place twin, leaving only
            // MANUALLY-dropped folders in the module list.
            $extSlugs    = $extensions->pluck('slug')->all();
            $inPlaceOnly = collect($modules ?? [])->reject(fn ($m, $slug) => in_array($slug, $extSlugs, true));
            $hasAny      = $extensions->count() > 0 || $inPlaceOnly->count() > 0;
        @endphp

        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[13px] font-semibold">{{ __('Installed add-ons') }}</h2>
                <span class="font-mono text-[10px] uppercase tracking-wider text-ink-500">{{ $extensions->count() + $inPlaceOnly->count() }}</span>
            </div>

            <div class="grid grid-cols-1 gap-3">
                {{-- ZIP-installed add-ons — DB-tracked, so they carry Remove. --}}
                @foreach ($extensions as $e)
                    <div class="rounded-2xl border border-paper-200 bg-paper-0 p-4 flex items-center gap-3.5" data-ext-row="{{ $e->id }}">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-wa-deep/10 text-wa-deep shrink-0">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7l8-4 8 4v10l-8 4-8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] font-semibold flex items-center gap-2 flex-wrap">
                                {{ $e->name }}
                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-full bg-paper-50 text-ink-600">v{{ $e->version }}</span>
                                @if ($e->isActive())
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep font-semibold">{{ __('Active') }}</span>
                                @else
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 font-semibold">{{ __('Disabled') }}</span>
                                @endif
                            </div>
                            <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">
                                {{ $e->slug }} · addon/{{ $e->slug }}/ · {{ count((array) $e->files) }} {{ __('files') }}
                                @if ($e->installed_at) · {{ $e->installed_at->diffForHumans() }} @endif
                            </div>
                        </div>
                        <button type="button" data-ext-toggle="{{ $e->id }}"
                            class="px-3 py-1.5 rounded-lg border border-paper-200 text-[11.5px] font-medium hover:bg-paper-50 shrink-0">
                            {{ $e->isActive() ? __('Disable') : __('Enable') }}
                        </button>
                        <button type="button" data-ext-remove="{{ $e->id }}" data-ext-name="{{ $e->name }}"
                            class="px-3 py-1.5 rounded-lg border border-accent-coral/40 text-[11.5px] font-semibold text-accent-coral hover:bg-accent-coral/5 shrink-0">
                            {{ __('Remove') }}
                        </button>
                    </div>
                @endforeach

                {{-- In-place modules: code in addon/<slug>/. "Remove" deactivates the
                     module (drops a hidden .disabled marker so it stops loading) —
                     the FILES are kept, so it can be re-activated any time without a
                     re-upload. It never deletes the folder. --}}
                @foreach ($inPlaceOnly as $slug => $m)
                    @php $off = ! empty($m['_disabled']); @endphp
                    <div class="rounded-2xl border {{ $off ? 'border-paper-200 bg-paper-50' : 'border-paper-200 bg-paper-0' }} p-4 flex items-center gap-3.5" data-mod-row="{{ $slug }}">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl {{ $off ? 'bg-paper-100 text-ink-400' : 'bg-paper-100 text-ink-500' }} shrink-0">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7l8-4 8 4v10l-8 4-8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] font-semibold flex items-center gap-2 flex-wrap">
                                {{ $m['name'] ?? $slug }}
                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-full bg-paper-50 text-ink-600">v{{ $m['version'] ?? '1.0.0' }}</span>
                                @if ($off)
                                    <span data-mod-badge class="text-[10px] px-1.5 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral font-semibold">{{ __('Inactive') }}</span>
                                @else
                                    <span data-mod-badge class="text-[10px] px-1.5 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep font-semibold">{{ __('Active') }}</span>
                                @endif
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 font-semibold">{{ __('In-place module') }}</span>
                            </div>
                            <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">{{ $slug }} · addon/{{ $slug }}/ · {{ __('files kept on disk') }}</div>
                        </div>
                        <button type="button" data-mod-toggle="{{ $slug }}" data-mod-off="{{ $off ? '1' : '0' }}" data-mod-name="{{ $m['name'] ?? $slug }}"
                            class="px-3 py-1.5 rounded-lg border text-[11.5px] font-semibold shrink-0 {{ $off ? 'border-wa-deep/40 text-wa-deep hover:bg-wa-deep/5' : 'border-accent-coral/40 text-accent-coral hover:bg-accent-coral/5' }}">
                            {{ $off ? __('Re-activate') : __('Remove') }}
                        </button>
                    </div>
                @endforeach

                @unless ($hasAny)
                    <div class="rounded-2xl border border-dashed border-paper-200 bg-paper-0 px-5 py-10 text-center text-[12px] text-ink-500">
                        {{ __('No add-ons installed yet. Verify your purchase code above, then upload a package.') }}
                    </div>
                @endunless
            </div>
        </section>

        <p class="text-[11px] text-ink-500">
            {{ __('Removing an add-on deletes only the files it installed — tracked from the moment it was added, so core files are never touched. Data stays in the database.') }}
        </p>

    </main>
</x-layouts.admin>
