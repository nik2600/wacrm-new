<x-layouts.admin :title="__('API keys')" admin-key="api-keys" page="admin-api-keys-index">
    <header class="h-16 bg-paper-0 border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('AI / API keys') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · System · AI providers') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[34px] lg:text-[40px] leading-[1.0]">AI <span
                        class="italic text-wa-deep">{{ __('API keys') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-3xl">
                    {{ __('Global keys for every AI provider :app supports. Workspaces on plans without "Bring your own key" use these as the fallback. Encrypted at rest.', ['app' => brand_name()]) }}
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div
                class="rounded-2xl border border-accent-coral/30 bg-accent-coral/10 text-[#A1431F] px-4 py-2 text-[12.5px]">
                {{ session('error') }}</div>
        @endif

        {{-- KPI strip --}}
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Providers') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['total'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('seeded') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['active'] }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('enabled for fallback') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Ready') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ $stats['ready'] }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('active + has key') }}</div>
            </div>
            <div
                class="bg-paper-0 border {{ $stats['no_key'] > 0 ? 'border-accent-amber/40' : 'border-paper-200' }} rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('No key set') }}</div>
                <div
                    class="font-serif text-[34px] leading-none mt-1 {{ $stats['no_key'] > 0 ? 'text-accent-amber' : '' }}">
                    {{ $stats['no_key'] }}</div>
                <div class="text-[11px] {{ $stats['no_key'] > 0 ? 'text-accent-amber' : 'text-ink-500' }} mt-2">
                    {{ __('paste a key to ready') }}</div>
            </div>
        </section>

        {{-- Provider cards — one per row --}}
        @forelse ($providers as $p)
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center gap-3 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-serif text-[22px] leading-tight">{{ $p->name }}</h3>
                            <span
                                class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full {{ $p->is_active ? 'bg-wa-mint text-wa-deep border border-wa-green/40' : 'bg-paper-100 text-ink-600' }}">
                                {{ $p->is_active ? 'Active' : 'Disabled' }}
                            </span>
                            @if (!empty($p->api_key))
                                <span
                                    class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 border border-paper-200">{{ __('Key saved') }}</span>
                            @else
                                <span
                                    class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber">{{ __('No key') }}</span>
                            @endif
                        </div>
                        <div class="text-[11.5px] text-ink-500 mt-1 font-mono">provider: {{ $p->provider }} · default
                            model: {{ $p->default_model ?: '—' }}</div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.api-keys.toggle', $p->id) }}" class="inline">
                            @csrf
                            <button
                                class="px-3 py-1.5 rounded-full border {{ $p->is_active ? 'border-paper-200 hover:bg-paper-50' : 'border-wa-green/40 bg-wa-mint text-wa-deep hover:bg-wa-bubble' }} text-[11.5px] font-semibold">{{ $p->is_active ? 'Disable' : 'Activate' }}</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.api-keys.update', $p->id) }}" class="p-5 space-y-4">
                    @csrf @method('PATCH')

                    @if (!empty($p->fields_schema))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach ($p->fields_schema as $key => $spec)
                                @php
                                    // Prefill the decrypted API key so the eye-reveal toggle
                                    // can show the saved value. Same security posture as the
                                    // payment-gateways credentials — admin already has full
                                    // access; this just removes the "type it again to see it"
                                    // friction.
                                    $existingVal =
                                        $key === 'api_key'
                                            ? (string) ($p->api_key ?? '')
                                            : $p->extra_config_decoded[$key] ?? '';
                                    $hasApiKey = $key === 'api_key' && !empty($p->api_key);
                                @endphp
                                <label
                                    class="text-[12px] text-ink-700 block {{ ($spec['type'] ?? 'text') === 'textarea' ? 'col-span-2' : '' }}">
                                    <span
                                        class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ $spec['label'] ?? $key }}
                                        @if (!empty($spec['required']))
                                            <span class="text-accent-coral">*</span>
                                        @endif
                                    </span>
                                    @if (($spec['type'] ?? 'text') === 'password')
                                        {{-- Stored API key is NEVER preloaded into the DOM —
 even with admin already authenticated, a value
 attribute on a password input leaks the secret
 to anything that can read the page (browser
 extensions, screen-share recordings, dev-tools
 screenshots). Field stays empty; controller's
 save() merges blank-as-keep. --}}
                                        <input type="password"
                                            name="{{ $key === 'api_key' ? 'api_key' : 'extra[' . $key . ']' }}"
                                            value=""
                                            placeholder="{{ $hasApiKey ? '•••••••••• ' . __('(saved — leave blank to keep)') : 'paste key here' }}"
                                            autocomplete="new-password"
                                            class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @else
                                        <input type="text"
                                            name="{{ $key === 'api_key' ? 'api_key' : 'extra[' . $key . ']' }}"
                                            value="{{ $existingVal }}"
                                            class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                            @if (!empty($spec['placeholder'])) placeholder="{{ $spec['placeholder'] }}" @endif>
                                    @endif
                                    @if (!empty($spec['hint']))
                                        <span class="block text-[10.5px] text-ink-500 mt-1">{{ $spec['hint'] }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-paper-100 pt-4">
                        <label class="text-[12px] text-ink-700 block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Default model') }}</span>
                            <select name="default_model"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('— Pick a default model —') }}</option>
                                @foreach ($p->model_choices ?? [] as $model)
                                    <option value="{{ $model }}" @selected($p->default_model === $model)>
                                        {{ $model }}</option>
                                @endforeach
                                @if ($p->default_model && !in_array($p->default_model, $p->model_choices ?? [], true))
                                    <option value="{{ $p->default_model }}" selected>{{ $p->default_model }} (custom)
                                    </option>
                                @endif
                            </select>
                        </label>
                        <label class="text-[12px] text-ink-700 block">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Sort order') }}</span>
                            <input type="number" name="sort_order" value="{{ $p->sort_order }}" min="0"
                                class="mt-1.5 w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold">Save
                            {{ $p->name }}</button>
                    </div>
                </form>
            </section>
        @empty
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-12 text-center">
                <div class="font-serif text-[22px] text-ink-700">{{ __('No AI providers configured') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-2">{{ __('Run') }} <code
                        class="bg-paper-100 px-1.5 py-0.5 rounded font-mono text-[11px]">php artisan db:seed
                        --class=AdminAiKeySeeder</code> to populate.</p>
            </div>
        @endforelse

        {{-- Help footer card --}}
        <section class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-5">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep">
                {{ __('How these keys are used') }}</div>
            <h3 class="font-serif text-[20px] leading-tight mt-1 mb-2 text-wa-deep">{{ __('Fallback chain') }}</h3>
            <p class="text-[12.5px] text-ink-700 leading-relaxed max-w-4xl">
                When a workspace runs an AI feature, <code
                    class="font-mono text-[11px] bg-paper-0/60 px-1.5 py-0.5 rounded">AiKeyResolver</code> tries the
                workspace's own key first (only available on plans with BYOK enabled). If none is set, it falls back to
                the matching <strong>{{ __('Active') }}</strong> row here. Disabling a provider above means workspaces
                on non-BYOK plans simply can't use it.
            </p>
        </section>

    </main>

</x-layouts.admin>
