<x-layouts.admin :title="__('Menu layout')" admin-key="settings">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.settings.index') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Menu layout') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form id="menuForm" method="POST" action="{{ route('admin.settings.menu-order.update') }}">
        @csrf
        <div id="barInputs" class="hidden"></div>

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Navigation') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Menu') }}
                        <span class="italic text-wa-deep">{{ __('layout') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Choose what sits in the user’s top navigation bar and what lives on the More page. Move an item down to declutter the header, or pull a page up so it’s one click away. Drag to reorder.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}" class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    @if (session('success'))
                        <span class="px-3 py-1.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 text-[11.5px] font-mono">{{ session('success') }}</span>
                    @endif
                    <button type="submit" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save layout') }}</button>
                </div>
            </div>

            {{-- Live preview of the top bar --}}
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview · top bar') }}</div>
                    <span class="text-[11px] text-ink-400">{{ __('how the header will look') }}</span>
                </div>
                <div class="p-4 bg-paper-50">
                    <div class="bg-paper-0 border border-paper-200 rounded-xl px-3 h-12 flex items-center gap-1 overflow-x-auto" id="navPreview">
                        <span class="w-7 h-7 rounded-md bg-wa-deep text-paper-0 grid place-items-center shrink-0 mr-2">
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z"/></svg>
                        </span>
                    </div>
                </div>
            </section>

            @php
                $renderChip = function ($key) use ($items) {
                    $it = $items[$key] ?? null;
                    if (!$it) return '';
                    return view('admin.settings._menu_chip', ['key' => $key, 'it' => $it, 'locked' => !empty($it['locked'])])->render();
                };
            @endphp

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

                <div class="space-y-5 min-w-0">

                    {{-- TOP BAR zone --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('top-bar') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Top navigation bar') }}</h2>
                            </div>
                            <span class="rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 px-2.5 py-1 text-[11px] font-mono" data-count="bar">0</span>
                        </div>
                        <ul class="p-4 space-y-2 min-h-[64px] dropzone" data-zone="bar">
                            @foreach ($bar as $k) {!! $renderChip($k) !!} @endforeach
                        </ul>
                        <div class="px-5 pb-4 -mt-1 text-[11px] text-ink-400">{{ __('These appear in the header, left to right, in this order.') }}</div>
                    </section>

                    {{-- MORE zone --}}
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('more-page') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('More menu') }}</h2>
                            </div>
                            <span class="rounded-full bg-paper-50 text-ink-500 border border-paper-200 px-2.5 py-1 text-[11px] font-mono" data-count="more">0</span>
                        </div>
                        <ul class="p-4 space-y-2 min-h-[64px] dropzone" data-zone="more">
                            @foreach ($more as $k) {!! $renderChip($k) !!} @endforeach
                        </ul>
                        <div class="px-5 pb-4 -mt-1 text-[11px] text-ink-400">{{ __('Reached via the “More” page. Pull one up to put it in the header.') }}</div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('How it works') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div><div class="font-semibold text-[12.5px] text-ink-900">{{ __('Move between zones') }}</div><p class="text-ink-600 mt-0.5">{{ __('Use the move button on each item — or drag it — to send it to the other zone.') }}</p></div>
                            <div><div class="font-semibold text-[12.5px] text-ink-900">{{ __('Reorder') }}</div><p class="text-ink-600 mt-0.5">{{ __('Drag items up/down within the Top bar to set their left-to-right order.') }}</p></div>
                            <div><div class="font-semibold text-[12.5px] text-ink-900">{{ __('Applies to everyone') }}</div><p class="text-ink-600 mt-0.5">{{ __('Saving updates the header for all users on their next page load. Role + plan gates still apply per user.') }}</p></div>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-accent-coral/30 rounded-2xl shadow-card p-4">
                        <div class="text-[12.5px] font-semibold text-ink-900">{{ __('Reset to default') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Restore the original header (Dashboard, Campaigns, Flows, Templates, Meta Ads, Devices, More).') }}</p>
                        <button type="button" id="resetMenu" class="mt-3 w-full px-4 py-2 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[12.5px] font-semibold">{{ __('Reset layout') }}</button>
                    </div>
                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Tip') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Keep the top bar to ~6 items so it never wraps on smaller laptops. Everything else stays one tap away under More.') }}</p>
                    </div>
                </aside>

            </section>

            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Changes apply after you save.') }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/admin/settings') }}" class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save layout') }}</button>
                </div>
            </div>

        </main>
    </form>

    <script>
        (function () {
            const form = document.getElementById('menuForm');
            const zones = { bar: document.querySelector('[data-zone="bar"]'), more: document.querySelector('[data-zone="more"]') };
            const preview = document.getElementById('navPreview');
            let dragged = null;

            function counts() {
                document.querySelector('[data-count="bar"]').textContent = zones.bar.querySelectorAll('[data-key]').length;
                document.querySelector('[data-count="more"]').textContent = zones.more.querySelectorAll('[data-key]').length;
            }
            function refreshMoveBtns() {
                document.querySelectorAll('[data-key]').forEach(li => {
                    const btn = li.querySelector('[data-move] span');
                    if (!btn) return;
                    if (li.dataset.locked === '1') { li.querySelector('[data-move]').style.visibility = 'hidden'; return; }
                    const inBar = li.closest('[data-zone]').dataset.zone === 'bar';
                    btn.textContent = inBar ? '⤓' : '⤒';
                    li.querySelector('[data-move]').title = inBar ? '{{ __('Move to More menu') }}' : '{{ __('Move to Top bar') }}';
                });
            }
            function renderPreview() {
                preview.querySelectorAll('[data-prev]').forEach(n => n.remove());
                zones.bar.querySelectorAll('[data-key]').forEach(li => {
                    const chip = document.createElement('span');
                    chip.dataset.prev = '1';
                    chip.className = 'shrink-0 px-3 py-1.5 rounded-lg text-[12px] font-medium text-ink-700';
                    chip.textContent = li.dataset.label;
                    preview.appendChild(chip);
                });
            }
            function sync() { counts(); refreshMoveBtns(); renderPreview(); }

            form.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-move]');
                if (!btn) return;
                const li = btn.closest('[data-key]');
                if (li.dataset.locked === '1') return;
                const target = li.closest('[data-zone]').dataset.zone === 'bar' ? zones.more : zones.bar;
                target.appendChild(li);
                sync();
            });

            document.querySelectorAll('[data-key]').forEach(li => {
                li.addEventListener('dragstart', () => { dragged = li; li.classList.add('opacity-40'); });
                li.addEventListener('dragend', () => { if (dragged) dragged.classList.remove('opacity-40'); dragged = null; sync(); });
            });
            Object.values(zones).forEach(zone => {
                zone.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragged) return;
                    const after = [...zone.querySelectorAll('[data-key]:not(.opacity-40)')].find(el => e.clientY <= el.getBoundingClientRect().top + el.offsetHeight / 2);
                    if (after) zone.insertBefore(dragged, after); else zone.appendChild(dragged);
                });
            });

            document.getElementById('resetMenu').addEventListener('click', function () {
                const defaults = ['dashboard', 'wa-campaigns', 'flows', 'templates', 'metaads', 'devices', 'more'];
                defaults.forEach(k => { const li = document.querySelector('[data-key="' + k + '"]'); if (li) zones.bar.appendChild(li); });
                document.querySelectorAll('[data-key]').forEach(li => { if (!defaults.includes(li.dataset.key)) zones.more.appendChild(li); });
                sync();
            });

            form.addEventListener('submit', function () {
                const box = document.getElementById('barInputs');
                box.innerHTML = '';
                zones.bar.querySelectorAll('[data-key]').forEach(li => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'bar[]'; inp.value = li.dataset.key;
                    box.appendChild(inp);
                });
            });

            sync();
        })();
    </script>
</x-layouts.admin>
