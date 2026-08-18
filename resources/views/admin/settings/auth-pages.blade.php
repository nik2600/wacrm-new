{{--
 /admin/settings/auth-pages — edit the public Login / Register / Forgot-password
 pages INLINE. The real page loads in an editable frame: click any text to change
 or clear it, hover the big side panel to set an image / video / GIF. Saved live
 to SystemSetting auth.{page}.* (read by the auth blades via auth_cfg()).
--}}
<x-layouts.admin :title="__('Auth pages')" admin-key="settings">

    @php
        $tabs = ['login' => __('Login'), 'register' => __('Register'), 'forgot' => __('Forgot password')];
        $active = in_array($page ?? 'login', array_keys($tabs), true) ? $page : 'login';
        $accent = (string) ($cfg[$active]['accent'] ?? '#25D366');
        $accentHex = \Illuminate\Support\Str::startsWith($accent, '#') ? $accent : '#25D366';
    @endphp

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Auth pages') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-5 space-y-4">

        <div>
            <h1 class="font-serif text-[24px] leading-tight">{{ __('Edit your sign-in pages — live, on the page') }}</h1>
            <p class="text-[12.5px] text-ink-500 mt-1 max-w-3xl">{{ __('Click any text in the page below to change or clear it. Hover the big left panel to set an image, video or GIF. Everything saves instantly. The Terms / Privacy links live under') }}
                <a href="{{ route('admin.settings.privacy') }}" class="text-wa-deep font-semibold underline">{{ __('Privacy & ADA') }}</a>.</p>
        </div>

        {{-- Toolbar: page tabs + accent colour + actions --}}
        <div class="flex flex-wrap items-center gap-3 bg-paper-0 border border-paper-200 rounded-xl px-3 py-2 shadow-card">
            <div class="flex items-center gap-1">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('admin.settings.auth-pages', ['page' => $key]) }}"
                        class="px-3 py-1.5 rounded-lg text-[12.5px] font-medium {{ $active === $key ? 'bg-wa-deep text-paper-0' : 'text-ink-600 hover:bg-paper-50' }}">{{ $label }}</a>
                @endforeach
            </div>
            <span class="w-px h-6 bg-paper-200"></span>
            <label class="flex items-center gap-2 text-[12px] text-ink-600">
                <span class="font-mono uppercase tracking-[0.12em] text-[10.5px] text-ink-500">{{ __('Accent') }}</span>
                <input type="color" value="{{ $accentHex }}" data-accent-picker class="w-8 h-8 rounded-lg border border-paper-200 cursor-pointer p-0">
            </label>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" data-reload class="px-3 py-1.5 rounded-lg border border-paper-200 text-[12px] font-medium hover:bg-paper-50">{{ __('Reload') }}</button>
                <a href="{{ route('admin.settings.auth-pages.preview', $active) }}?fc_edit=1" target="_blank" rel="noopener"
                    class="px-3 py-1.5 rounded-lg border border-paper-200 text-[12px] font-medium hover:bg-paper-50">{{ __('Full screen') }}</a>
            </div>
        </div>

        {{-- Design template — 5 swappable layouts. Global (applies to login /
             register / forgot together). Saving reloads the live frame below. --}}
        @php
            $variant = (int) ($variant ?? 1);
            $variants = [
                1 => ['name' => __('Split showcase'), 'desc' => __('Feature panel left, form right')],
                2 => ['name' => __('Cinematic'),      'desc' => __('Full-bleed media, centred card')],
                3 => ['name' => __('Minimal'),        'desc' => __('Clean centred card, no art')],
                4 => ['name' => __('Brand spotlight'),'desc' => __('Accent panel left, form right')],
                5 => ['name' => __('Aurora'),         'desc' => __('Animated gradient, glass card')],
            ];
        @endphp
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Design template') }}</div>
                <span class="text-[11px] text-ink-400">· {{ __('applies to all 3 auth pages') }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3" data-variant-picker>
                @foreach ($variants as $v => $meta)
                    <button type="button" data-variant="{{ $v }}"
                        class="text-left rounded-xl border p-2.5 transition-all {{ $variant === $v ? 'border-wa-deep ring-4 ring-wa-deep/10 bg-paper-50' : 'border-paper-200 hover:border-wa-deep/40' }}">
                        {{-- mini schematic --}}
                        <div class="aspect-[16/10] rounded-lg overflow-hidden border border-paper-200 mb-2 flex">
                            @switch($v)
                                @case(2)
                                    <div class="w-full grid place-items-center" style="background:linear-gradient(135deg,#075E54,#13312D)"><span class="w-1/2 h-1/2 rounded bg-paper-0/90"></span></div>
                                    @break
                                @case(3)
                                    <div class="w-full bg-paper-50 grid place-items-center"><span class="w-1/2 h-2/3 rounded bg-paper-0 border border-paper-200"></span></div>
                                    @break
                                @case(4)
                                    <div class="w-1/2 h-full" style="background:linear-gradient(150deg,#25D366,#0B1F1C)"></div>
                                    <div class="w-1/2 h-full bg-paper-0 grid place-items-center"><span class="w-2/3 h-2/3 rounded bg-paper-100"></span></div>
                                    @break
                                @case(5)
                                    <div class="w-full grid place-items-center auth-aurora"><span class="w-1/2 h-1/2 rounded bg-paper-0/80"></span></div>
                                    @break
                                @default
                                    <div class="w-1/2 h-full auth-art"></div>
                                    <div class="w-1/2 h-full bg-paper-0 grid place-items-center"><span class="w-2/3 h-2/3 rounded bg-paper-100"></span></div>
                            @endswitch
                        </div>
                        <div class="text-[11.5px] font-semibold text-ink-900 leading-tight">{{ $meta['name'] }}</div>
                        <div class="text-[10px] text-ink-500 leading-snug mt-0.5">{{ $meta['desc'] }}</div>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- The real auth page, editable. Full content width so the desktop layout
             (with the big left panel) renders natively — no scaling. --}}
        <div class="rounded-2xl overflow-hidden border border-paper-200 bg-paper-50 shadow-card">
            <iframe data-frame src="{{ route('admin.settings.auth-pages.preview', $active) }}?fc_edit=1"
                class="w-full" style="height:78vh;min-height:560px;border:0;display:block;" title="{{ __('Auth page editor') }}"></iframe>
        </div>
    </main>

    <script>
        (function () {
            var frame = document.querySelector('[data-frame]');
            var reload = document.querySelector('[data-reload]');
            if (reload && frame) reload.addEventListener('click', function () { frame.src = frame.src; });

            // Accent colour saves to the same inline endpoint, then reloads the
            // frame so the coloured word updates.
            // Design template picker — saves the global variant, then reloads
            // the live frame so the new layout renders.
            var picker = document.querySelector('[data-variant-picker]');
            if (picker) {
                picker.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-variant]');
                    if (!btn) return;
                    var v = btn.getAttribute('data-variant');
                    picker.querySelectorAll('[data-variant]').forEach(function (b) {
                        b.classList.remove('border-wa-deep', 'ring-4', 'ring-wa-deep/10', 'bg-paper-50');
                        b.classList.add('border-paper-200');
                    });
                    btn.classList.remove('border-paper-200');
                    btn.classList.add('border-wa-deep', 'ring-4', 'ring-wa-deep/10', 'bg-paper-50');
                    fetch('{{ route('admin.settings.auth-pages.variant') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ variant: v }),
                    }).then(function () { if (frame) frame.src = frame.src; });
                });
            }

            var pick = document.querySelector('[data-accent-picker]');
            if (pick) {
                pick.addEventListener('change', function () {
                    fetch('{{ route('admin.settings.auth-pages.inline') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ key: '{{ $active }}.accent', value: pick.value }),
                    }).then(function () { if (frame) frame.src = frame.src; });
                });
            }
        })();
    </script>
</x-layouts.admin>
