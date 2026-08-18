@props([
    'title' => 'Dashboard',
    'adminKey' => 'overview',
    'page' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ \App\Support\LocaleSettings::directionFor(app()->getLocale()) }}" @class([
        'admin-sidebar-collapsed' =>
            ($_COOKIE['wa_admin_sidebar'] ?? null) === 'collapsed',
    ])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Sub-folder base path (e.g. /public) so client-side AJAX honours the deploy location under a sub-directory. --}}
    <meta name="app-base" content="{{ wd_base() }}">
    @php $defCountry = app_default_country(); @endphp
    <meta name="default-country-code" content="{{ $defCountry['code'] }}">
    <meta name="default-country-iso"  content="{{ $defCountry['iso'] }}">
    {{-- Active platform currency symbol — read by chart JS so axes/tooltips
 follow the admin's default_currency instead of a hardcoded '$'. --}}
    <meta name="currency-symbol" content="{{ \App\Support\FormatSettings::symbol() }}">
    @php
        $faviconUrl = \App\Support\Brand::faviconUrl();
        $brandTitle = (string) brand_name();
    @endphp
    @if ($faviconUrl)
        <link rel="icon" type="image/x-icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @endif
    <title>{{ $title }} — {{ $brandTitle }} {{ __('Admin') }}</title>
    {{-- SEO meta block — read from /admin/settings/seo. Admin pages
 override the title above; per-page description/og overrides
 are supported via $seoOverrides if a page sets it. --}}
    @include('partials.seo-meta', ['seoOverrides' => ['title' => $title . ' — ' . $brandTitle]])
    @include('partials.pwa-meta')
    {{-- Visitor analytics scripts (GA, Pixel, Clarity, etc.) are NOT
 loaded inside admin — we don't track admins on their own
 management UI. They render on user + guest layouts only. --}}
    <x-theme-bootstrap />
    @php
        // Per-theme logo URLs uploaded by admin at /admin/settings/general.
        // wadesk.js setTheme() reads this map and swaps the brand <img> src
        // to the right variant whenever the user picks a different theme
        // — no reload needed.
        $brandLogos = [];
        foreach (['paper', 'bright', 'dark', 'doodle'] as $__t) {
            $brandLogos[$__t] = \App\Support\Brand::logoUrl($__t);
        }
    @endphp
    <script>
        window.WADESK_BRAND = {
            logos: @json($brandLogos),
            appName: @json($brandTitle)
        };
    </script>
    @auth
        <script>
            window.WADESK_USER = {
                name: @json(auth()->user()->name),
                email: @json(auth()->user()->email),
                role: @json(auth()->user()->role ?? 'user'),
                isAdmin: @json(auth()->user()->isAdmin()),
                initials: @json(\Illuminate\Support\Str::of(auth()->user()->name)->trim()->limit(2, '')->upper()->__toString()),
            };
        </script>
    @endauth
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.app-font')
    {{-- Admin-set dashboard theme colour overrides — LAST in head so they win --}}
    {!! theme_css() !!}
</head>

<body data-admin="{{ $adminKey }}" @if ($page) data-page="{{ $page }}" @endif
    class="min-h-screen font-sans antialiased bg-paper-50 text-ink-900 overflow-x-clip">
    <div class="admin-shell min-h-screen flex flex-col md:grid md:grid-cols-[260px_minmax(0,1fr)] relative">
        <div id="admin-sidebar-backdrop"
            class="fixed inset-0 bg-ink-900/50 z-40 hidden md:hidden transition-opacity opacity-0"></div>
        <aside id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-50 w-[260px] bg-paper-50 md:bg-transparent transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out md:transition-none flex flex-col">
            <x-admin.sidebar :active="$adminKey" />
        </aside>
        <div class="min-w-0 flex flex-col flex-1 w-full relative">
            {{ $slot }}
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-sidebar-backdrop');

            if (sidebar && backdrop) {
                backdrop.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    backdrop.classList.add('opacity-0');
                    setTimeout(() => backdrop.classList.add('hidden'), 300);
                    document.body.style.overflow = '';
                });
            }
        });
    </script>

    {{-- Global flash banner — every admin controller writes
 session('success' | 'status' | 'error') after a save; this
 pops a fixed toast so the admin sees confirmation regardless
 of which page they're on (some pages render their own banner
 inline, this is the fallback for pages that don't). Auto-
 dismisses after 4s; click X to dismiss early. --}}
    @php
        $flashMsg = session('success') ?: session('status') ?: null;
        $flashErr = session('error');
    @endphp
    @if ($flashMsg || $flashErr)
        <div id="admin-flash-toast"
            class="fixed top-5 right-5 z-50 max-w-md rounded-xl shadow-lg border px-4 py-3 text-[13px] font-medium flex items-start gap-3
 {{ $flashErr
     ? 'bg-paper-0 border-accent-coral/40 text-[#9C2A1A]'
     : 'bg-paper-0 border-wa-green/40 text-wa-deep' }}"
            role="status" aria-live="polite">
            <svg viewBox="0 0 16 16" class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                stroke-width="1.6">
                @if ($flashErr)
                    <circle cx="8" cy="8" r="6" />
                    <path d="M8 5v4M8 11h.01" />
                @else
                    <circle cx="8" cy="8" r="6" />
                    <path d="M5.5 8.5l1.8 1.8L10.5 6.5" />
                @endif
            </svg>
            <span class="flex-1">{{ $flashErr ?: $flashMsg }}</span>
            <button type="button" onclick="this.closest('#admin-flash-toast').remove()"
                class="text-ink-500 hover:text-ink-900 leading-none" aria-label="Dismiss">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
            </button>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('admin-flash-toast');
                if (el) {
                    el.style.transition = 'opacity .3s';
                    el.style.opacity = '0';
                    setTimeout(function() {
                        el.remove();
                    }, 320);
                }
            }, 4000);
        </script>
    @endif

    {{-- Cookie consent is intentionally NOT included in the admin
 layout. It's a visitor-facing GDPR/CCPA artifact for the user
 dashboard and public pages, not for the admins managing the
 platform. --}}
    @stack('scripts')

    {{-- Header-right control set rendered ONCE inline (not inside a
 template) so wadesk.js can find #wa-theme-btn etc on its
 DOMContentLoaded pass. The script below MOVES the element
 into the page's [data-admin-header-right] slot before paint
 so the user never sees it at the bottom. --}}
    <div id="admin-header-right-source" style="display:none">
        <x-admin.header-right />
    </div>
    <script>
        (function() {
            const ROUTES = {
                // Admin bell pulls the PLATFORM feed (signups, orders, tickets,
                // contact messages, new workspaces) — not the operator's
                // personal user-side notifications.
                notifRecent: @json(route('admin.notifications.recent')),
                notifReadAll: @json(route('admin.notifications.read-all')),
                notifClear: @json(route('admin.notifications.clear')),
                notifsPage: @json(url('/admin/notifications')),
            };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));

            function injectSearchBar() {
                // Add a global search input between breadcrumb and right slot
                // on every admin header that doesn't already have one. Same
                // visual as the static prototypes — ⌘K kbd hint opens the
                // wadesk.js search modal.
                const slot = document.querySelector('[data-admin-header-right]');
                if (!slot) return;
                const header = slot.closest('header');

                // Inject mobile hamburger toggle and hide breadcrumb on mobile
                if (header && !header.querySelector('.admin-mobile-toggle')) {
                    const breadcrumb = header.firstElementChild;
                    if (breadcrumb && breadcrumb !== slot && !breadcrumb.classList.contains('admin-mobile-toggle')) {
                        breadcrumb.classList.add('hidden', 'md:flex'); // Hide breadcrumb on mobile
                    }

                    const toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className =
                        'admin-mobile-toggle md:hidden w-9 h-9 flex items-center justify-center rounded-lg bg-paper-50 text-ink-700 hover:bg-paper-100 shrink-0 mr-2 -ml-2';
                    toggle.innerHTML =
                        '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';

                    const sidebar = document.getElementById('admin-sidebar');
                    const backdrop = document.getElementById('admin-sidebar-backdrop');
                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (sidebar && backdrop) {
                            sidebar.classList.remove('-translate-x-full');
                            backdrop.classList.remove('hidden');
                            setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
                            document.body.style.overflow = 'hidden';
                        }
                    });

                    header.insertBefore(toggle, header.firstChild);
                }

                if (!header || header.dataset.adminSearchFilled === '1') return;
                const existingSearch = header.querySelector('input[type="search"], input[placeholder*="earch"]');
                if (existingSearch) {
                    // The page already ships its own search box. Hide it on
                    // mobile — phones don't need it, and its min-content width
                    // was pushing the header (and page) past the viewport,
                    // leaving the empty strip on the right.
                    existingSearch.closest('div')?.classList.add('hidden', 'md:block');
                    header.dataset.adminSearchFilled = '1';
                    return;
                }
                const wrap = document.createElement('div');
                wrap.className = 'hidden md:block relative flex-1 max-w-[520px] ml-1 md:ml-4';
                wrap.innerHTML = `
 <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="5"/><path d="m11 11 3 3"/></svg>
 <input type="search" class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition" placeholder="Search admin..." />
 <kbd class="hidden md:block absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">CMD K</kbd>
 `;
                header.insertBefore(wrap, slot);
                header.dataset.adminSearchFilled = '1';
            }

            function injectHeaderRight() {
                injectSearchBar();
                const src = document.getElementById('admin-header-right-source');
                if (!src) return;
                const node = src.firstElementChild;
                if (!node) return;
                const slot = document.querySelector('[data-admin-header-right]');
                if (slot && slot.dataset.adminHeaderFilled !== '1') {
                    slot.appendChild(node); // move (not clone)
                    slot.dataset.adminHeaderFilled = '1';
                    src.remove(); // tidy up
                    src.style.display = 'none';
                }
                wireControls();
            }

            function wireControls() {
                // Avatar / user menu
                document.querySelectorAll('[data-user-toggle]').forEach((btn) => {
                    if (btn.__wired) return;
                    btn.__wired = true;
                    const pane = btn.parentElement?.querySelector('[data-user-pane]');
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        pane?.classList.toggle('hidden');
                    });
                });

                // Notification bell — pane lookup is scoped to the bell's wrap.
                const wrap = document.querySelector('[data-notif-wrap]');
                if (wrap && !wrap.__wired) {
                    wrap.__wired = true;
                    const notifBtn = wrap.querySelector('#notif-toggle');
                    const notifPane = wrap.querySelector('#notif-pane');
                    const notifList = wrap.querySelector('#notif-list');
                    const notifBadge = wrap.querySelector('#notif-badge');

                    function renderItems(items) {
                        if (!items?.length) {
                            notifList.innerHTML =
                                '<div class="px-4 py-10 text-center text-[12px] text-ink-500">No notifications yet.</div>';
                            return;
                        }
                        notifList.innerHTML = items.map((n) => {
                            const tone = n.severity === 'error' || n.severity === 'critical' ?
                                'text-accent-coral' :
                                n.severity === 'warning' ? 'text-[#7B5A14]' :
                                'text-ink-900';
                            const dot = n.unread ?
                                '<span class="w-1.5 h-1.5 rounded-full bg-wa-deep flex-shrink-0 mt-1.5"></span>' :
                                '<span class="w-1.5 h-1.5 flex-shrink-0 mt-1.5"></span>';
                            const href = n.action_url || ROUTES.notifsPage;
                            return `<a href="${escapeHtml(href)}" data-notif-id="${n.id}" class="block px-4 py-3 hover:bg-paper-50 transition flex items-start gap-2.5">${dot}<div class="flex-1 min-w-0"><div class="text-[13px] ${tone} font-semibold truncate">${escapeHtml(n.title || '(no title)')}</div>${n.message ? `<div class="text-[11.5px] text-ink-500 mt-0.5 line-clamp-2">${escapeHtml(n.message)}</div>` : ''}<div class="text-[10.5px] text-ink-500 font-mono mt-1">${escapeHtml(n.time_ago)}</div></div></a>`;
                        }).join('');
                    }

                    function renderBadge(unread) {
                        if (!notifBadge) return;
                        if (unread > 0) {
                            notifBadge.textContent = unread > 99 ? '99+' : String(unread);
                            notifBadge.classList.remove('hidden');
                        } else {
                            notifBadge.classList.add('hidden');
                        }
                    }
                    async function loadNotifs() {
                        try {
                            const res = await fetch(ROUTES.notifRecent, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            if (!res.ok) return;
                            const data = await res.json();
                            renderItems(data.items || []);
                            renderBadge(Number(data.unread || 0));
                        } catch (e) {
                            /* silent */ }
                    }
                    notifBtn?.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const willOpen = notifPane?.classList.contains('hidden');
                        notifPane?.classList.toggle('hidden');
                        if (willOpen) loadNotifs();
                    });
                    wrap.querySelector('#notif-read-all')?.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        await fetch(ROUTES.notifReadAll, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json'
                            }
                        });
                        loadNotifs();
                    });
                    wrap.querySelector('#notif-clear')?.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        await fetch(ROUTES.notifClear, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json'
                            }
                        });
                        loadNotifs();
                    });
                    notifPane?.addEventListener('click', (e) => e.stopPropagation());

                    loadNotifs();
                    setInterval(() => {
                        if (!document.hidden) loadNotifs();
                    }, 60000);
                }

                // Close menus on outside click.
                document.addEventListener('click', () => {
                    document.querySelectorAll('[data-user-pane]:not(.hidden)').forEach((p) => p.classList.add(
                        'hidden'));
                    document.querySelectorAll('#notif-pane:not(.hidden)').forEach((p) => p.classList.add(
                        'hidden'));
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', injectHeaderRight);
            } else {
                injectHeaderRight();
            }
        })();
    </script>

    @auth
        <form id="logoutForm" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    @endauth
</body>

</html>
