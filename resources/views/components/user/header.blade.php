@props(['active' => 'dashboard'])

@php
    // Workspace-role gate for the top nav. Mirrors the route-level
    // `workspace.role:*` middleware so the UI never shows a button
    // that would 403/redirect when clicked.
    // - agent/viewer: Team Inbox only
    // - manager: + Chat, Templates, Analytics
    // - admin/owner: everything
    $wsRole = auth()->user()?->workspaceRole();
    $minTier = match ($wsRole) {
        'owner', 'admin' => 'admin',
        'manager' => 'manager',
        'agent', 'viewer' => 'agent',
        default => 'admin', // platform-only users with no workspace role: keep legacy view
    };
    $rank = ['agent' => 1, 'manager' => 2, 'admin' => 3];
    $userRank = $rank[$minTier] ?? 3;

    // Meta Ads (Click-to-WhatsApp Ads) is shown in ALL engine modes —
    // the page collects its own Meta Marketing API credentials, so it
    // no longer depends on the platform's active send engine.
$activeSendMethod = (string) \App\Models\SystemSetting::get('default_send_method', 'baileys');

$allNavItems = [
    [
        'key' => 'dashboard',
        'tier' => 'manager',
        'href' => url('/dashboard'),
        'label' => __('Dashboard'),
        'icon' =>
            '<rect x="2" y="2" width="5" height="6" rx="1"/><rect x="9" y="2" width="5" height="3" rx="1"/><rect x="9" y="7" width="5" height="7" rx="1"/><rect x="2" y="10" width="5" height="4" rx="1"/>',
        'sw' => 1.6,
    ],
    [
        // Promoted into the default header (takes Meta Ads' old slot).
        'key' => 'team-inbox',
        'tier' => 'admin',
        'href' => url('/team-inbox'),
        'label' => __('Unified Inbox'),
        'icon' => '<rect x="2" y="3" width="12" height="9" rx="1.5"/><path d="M2 6h12M5 9h2.5"/>',
        'sw' => 1.5,
    ],
    [
        // Moved OFF the default header into the /more page. Kept in the
        // catalogue (so an admin can promote it back via menu-order) but
        // `promo` keeps it out of the top bar unless explicitly placed.
        'key' => 'metaads',
        'tier' => 'admin',
        'href' => url('/meta-ads'),
        'label' => __('Meta Ads'),
        'icon' => '<path d="M2 4l12-2v12L2 12V4Z"/>',
        'sw' => 1.5,
        'feature' => 'access_ctwa',
        'promo' => true,
    ],
    [
        'key' => 'wa-campaigns',
        'tier' => 'admin',
        'href' => url('/wa-campaigns'),
        'label' => __('Campaigns'),
        'icon' =>
            '<path d="M3 4.5A2.5 2.5 0 0 1 5.5 2h5A2.5 2.5 0 0 1 13 4.5v4A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 2 8.5v-4Z"/><path d="M5.5 6.5h5M5.5 8.5h3"/>',
        'sw' => 1.5,
        'feature' => 'campaign',
    ],
    [
        'key' => 'flows',
        'tier' => 'admin',
        'href' => url('/flows'),
        'label' => __('Flows'),
        'icon' =>
            '<circle cx="3.5" cy="8" r="1.8"/><circle cx="12.5" cy="3.5" r="1.8"/><circle cx="12.5" cy="12.5" r="1.8"/><path d="M5 7l6-3M5 9l6 3"/>',
        'sw' => 1.5,
        'feature' => 'autoflow',
    ],
    [
        'key' => 'templates',
        'tier' => 'manager',
        'href' => url('/templates'),
        'label' => __('Templates'),
        'icon' => '<rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/><path d="M2.5 6h11M6 13.5V6"/>',
        'sw' => 1.5,
        'feature' => 'template',
    ],
    [
        'key' => 'devices',
        'tier' => 'admin',
        'href' => url('/devices'),
        'label' => __('Devices'),
        'icon' => '<rect x="3.5" y="2" width="9" height="12" rx="1.5"/><circle cx="8" cy="11.5" r="0.8"/>',
        'sw' => 1.6,
    ],
    // Promotable items — NOT in the top bar by default; an admin can move them
    // up via /admin/settings/menu-order. `promo` keeps them hidden unless the
    // saved config explicitly places them in the bar (back-compat safe).
    [
        'key' => 'broadcasts', 'tier' => 'admin', 'href' => url('/broadcasts'), 'label' => __('Broadcasts'),
        'icon' => '<path d="M2 6v4l8 3V3L2 6Z"/><path d="M10 5l3-1v8l-3-1"/>', 'sw' => 1.5, 'promo' => true,
    ],
    [
        'key' => 'contacts', 'tier' => 'manager', 'href' => url('/contacts'), 'label' => __('Contacts'),
        'icon' => '<circle cx="6" cy="5" r="2.2"/><path d="M2.5 13a3.5 3.5 0 0 1 7 0"/><path d="M11 4.7a2 2 0 0 1 0 3.6M13.5 13a3.4 3.4 0 0 0-2-3.1"/>', 'sw' => 1.5, 'promo' => true,
    ],
    [
        'key' => 'deals', 'tier' => 'admin', 'href' => url('/deals'), 'label' => __('Deals'),
        'icon' => '<path d="M8 2.5l5.5 4.2L11.4 13H4.6L2.5 6.7 8 2.5Z"/>', 'sw' => 1.5, 'promo' => true, 'feature' => 'access_sales_pipeline',
    ],
    [
        'key' => 'chat', 'tier' => 'manager', 'href' => url('/chat'), 'label' => __('Live Chat'),
        'icon' => '<path d="M2 4.5A2.5 2.5 0 0 1 4.5 2h7A2.5 2.5 0 0 1 14 4.5v4A2.5 2.5 0 0 1 11.5 11H6l-3 2v-2A2.5 2.5 0 0 1 2 8.5v-4Z"/>', 'sw' => 1.5, 'promo' => true,
    ],
    [
        'key' => 'analytics', 'tier' => 'manager', 'href' => url('/analytics'), 'label' => __('Analytics'),
        'icon' => '<path d="M2 13V3M2 13h12M5 11V7M8 11V4M11 11V8"/>', 'sw' => 1.6, 'promo' => true,
    ],
    [
        'key' => 'auto-reply', 'tier' => 'admin', 'href' => url('/auto-reply'), 'label' => __('Auto-reply'),
        'icon' => '<path d="M6 3 2 7l4 4M2.5 7H9a4 4 0 0 1 4 4v1"/>', 'sw' => 1.5, 'promo' => true,
    ],
    [
        'key' => 'scheduled', 'tier' => 'admin', 'href' => url('/scheduled'), 'label' => __('Scheduled'),
        'icon' => '<circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1"/>', 'sw' => 1.5, 'promo' => true,
    ],
    [
        'key' => 'message-history', 'tier' => 'manager', 'href' => url('/message-history'), 'label' => __('History'),
        'icon' => '<path d="M3 3v4h4"/><path d="M3.5 7a6 6 0 1 1 .3 2.4"/><path d="M8 5v3l2 1"/>', 'sw' => 1.5, 'promo' => true,
    ],
    [
        'key' => 'ai-assistants', 'tier' => 'admin', 'href' => url('/ai-assistants'), 'label' => __('AI Assistants'),
        'icon' => '<rect x="3" y="4" width="10" height="8" rx="2"/><circle cx="6.2" cy="8" r="0.9"/><circle cx="9.8" cy="8" r="0.9"/><path d="M8 2v2"/>', 'sw' => 1.4, 'promo' => true, 'feature' => 'access_ai_agents',
    ],
    [
        'key' => 'ai-training', 'tier' => 'admin', 'href' => url('/ai-training'), 'label' => __('AI Training'),
        'icon' => '<path d="M8 2l5 3v3c0 3-2.2 5-5 6-2.8-1-5-3-5-6V5l5-3Z"/>', 'sw' => 1.5, 'promo' => true, 'feature' => 'access_ai_training',
    ],
    [
        'key' => 'wa-links', 'tier' => 'manager', 'href' => url('/wa-links'), 'label' => __('WA Links'),
        'icon' => '<path d="M6.5 9.5a3 3 0 0 0 4 0l2-2a3 3 0 0 0-4-4l-1 1"/><path d="M9.5 6.5a3 3 0 0 0-4 0l-2 2a3 3 0 0 0 4 4l1-1"/>', 'sw' => 1.4, 'promo' => true,
    ],
    [
        'key' => 'chatbot-widgets', 'tier' => 'admin', 'href' => url('/chatbot-widgets'), 'label' => __('Chat Widget'),
        'icon' => '<rect x="3" y="3" width="10" height="8" rx="2"/><path d="M6 13l2-2h3"/><circle cx="6.5" cy="7" r="0.8"/><circle cx="9.5" cy="7" r="0.8"/>', 'sw' => 1.4, 'promo' => true, 'feature' => 'access_chatbot_widgets',
    ],
    [
        'key' => 'webhooks', 'tier' => 'admin', 'href' => url('/webhooks'), 'label' => __('Webhooks'),
        'icon' => '<circle cx="5" cy="5" r="2"/><circle cx="11" cy="6" r="2"/><circle cx="7" cy="12" r="2"/><path d="M6 6.6l-1 3.4M9.4 7l-2 3.4M6.8 5.5h3"/>', 'sw' => 1.3, 'promo' => true, 'feature' => 'access_outbound_webhooks',
    ],
    [
        'key' => 'integrations', 'tier' => 'admin', 'href' => url('/integrations'), 'label' => __('Integrations'),
        'icon' => '<rect x="2" y="6" width="5" height="5" rx="1"/><rect x="9" y="3" width="5" height="5" rx="1"/><path d="M7 8.5h2v-1"/>', 'sw' => 1.4, 'promo' => true,
    ],
    [
        'key' => 'warmer', 'tier' => 'admin', 'href' => url('/warmer'), 'label' => __('Warmer'),
        'icon' => '<path d="M8 2c2 2 1 4 0 5s-2 3 0 5"/><path d="M5 7c1 1 .5 2 0 2.6M11 7c-1 1-.5 2 0 2.6"/>', 'sw' => 1.4, 'promo' => true,
    ],
    [
        'key' => 'call-logs', 'tier' => 'admin', 'href' => url('/call-logs'), 'label' => __('Call Logs'),
        'icon' => '<path d="M3 3h2l1.4 3.4L5 8a8 8 0 0 0 3 3l1.6-1.4L13 11v2a1 1 0 0 1-1 1A11 11 0 0 1 2 4a1 1 0 0 1 1-1Z"/>', 'sw' => 1.3, 'promo' => true,
    ],
    [
        'key' => 'support', 'tier' => 'manager', 'href' => url('/support'), 'label' => __('Support'),
        'icon' => '<circle cx="8" cy="8" r="6"/><path d="M6.2 6.4a2 2 0 1 1 2.9 1.8c-.6.4-1.1.8-1.1 1.4M8 12h.01"/>', 'sw' => 1.4, 'promo' => true,
    ],
    [
        'key' => 'more',
        'tier' => 'admin',
        'href' => url('/more'),
        'label' => __('More'),
        'icon' => '<circle cx="3.5" cy="8" r="1"/><circle cx="8" cy="8" r="1"/><circle cx="12.5" cy="8" r="1"/>',
        'sw' => 1.6,
    ],
];

$navItems = array_values(
    array_filter($allNavItems, function ($it) use ($rank, $userRank, $activeSendMethod) {
        $needed = $rank[$it['tier']] ?? 3;
        if ($userRank < $needed) {
            return false;
        }
        // Engine gate: items with `requires_provider` only show when
        // that provider is the active platform engine.
        if (!empty($it['requires_provider']) && $it['requires_provider'] !== $activeSendMethod) {
                return false;
            }
            return true;
        }),
    );

    // Admin-configurable nav (/admin/settings/menu-order). Two formats:
    //   NEW  {"bar":[keys…]}  → ONLY these keys appear in the top bar, in this
    //        order. Everything else is reachable via the /more page. Lets an
    //        admin move an item out of the header AND promote deeper pages up.
    //   OLD  [keys…]          → legacy "reorder, show all" (kept for back-compat).
    // `promo` items only ever show when explicitly placed in the bar.
    $navCfg = json_decode((string) \App\Models\SystemSetting::get('user_nav_order', '[]'), true);

    if (is_array($navCfg) && isset($navCfg['bar']) && is_array($navCfg['bar'])) {
        // NEW placement format.
        $barKeys = array_values(array_filter($navCfg['bar'], 'is_string'));
        if (!in_array('more', $barKeys, true)) $barKeys[] = 'more';   // gateway always present
        $pos = array_flip($barKeys);
        $navItems = array_values(array_filter($navItems, fn ($it) => isset($pos[$it['key']])));
        usort($navItems, fn ($a, $b) => ($pos[$a['key']] ?? PHP_INT_MAX) <=> ($pos[$b['key']] ?? PHP_INT_MAX));
    } else {
        // Default / legacy: hide promo-only items so the top bar stays the
        // original set, then apply any legacy flat-array reorder.
        $navItems = array_values(array_filter($navItems, fn ($it) => empty($it['promo'])));
        if (is_array($navCfg) && $navCfg) {
            $navPos = array_flip(array_values($navCfg));
            usort($navItems, fn ($a, $b) => ($navPos[$a['key']] ?? PHP_INT_MAX) <=> ($navPos[$b['key']] ?? PHP_INT_MAX));
        }
    }
@endphp

<header class="relative z-40 bg-paper-0 hairline-b border-b border-paper-200">
    <div class="max-w-none mx-auto px-2 h-16 flex items-center gap-3 2xl:gap-1">

        @php
            // Resolve brand logo for the current user's selected theme.
// Same fallback chain as the admin sidebar.
$brandTheme = \App\Support\Brand::activeTheme();
$brandLogo = \App\Support\Brand::logoUrl($brandTheme);
$brandName = (string) brand_name();
// Per-workspace white-label: the workspace's own uploaded logo (Settings →
// Branding) OVERRIDES the platform logo for that workspace. data-ws-logo tells
// wadesk.js NOT to swap it on theme change (it's a single image, not per-theme).
$__bw     = auth()->user()?->currentWorkspace;
$wsLogo   = $__bw && $__bw->brand_logo_path ? asset('storage/' . $__bw->brand_logo_path) : null;
$logoSrc  = $wsLogo ?: $brandLogo;
$logoName = $wsLogo ? ($__bw->name ?: $brandName) : $brandName;
        @endphp
        <a class="flex items-center gap-2 mr-2 shrink-0" href="{{ url('/dashboard') }}">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="{{ $logoName }}" data-brand-logo @if ($wsLogo) data-ws-logo @endif
                    class="h-8 w-auto max-w-[180px] object-contain">
            @else
                <span
                    class="relative inline-flex items-center justify-center w-8 h-8 rounded-md bg-wa-deep text-paper-0">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.07 14.07c-.21.6-1.22 1.14-1.7 1.21-.45.07-1.02.1-1.65-.1-.38-.12-.87-.28-1.49-.55-2.62-1.13-4.33-3.77-4.46-3.94-.13-.18-1.07-1.42-1.07-2.71 0-1.29.68-1.92.92-2.18.24-.27.52-.34.7-.34h.5c.16 0 .38-.06.59.45.21.51.71 1.76.77 1.89.06.13.1.28.02.45-.08.18-.12.28-.24.43-.12.15-.26.34-.37.46-.12.12-.25.26-.11.51.14.26.62 1.02 1.33 1.65.91.81 1.68 1.06 1.94 1.18.26.13.41.11.56-.06.15-.18.65-.76.83-1.02.18-.26.36-.21.6-.13.24.09 1.55.73 1.81.86.27.13.45.2.51.31.07.12.07.69-.14 1.29Z" />
                    </svg>
                </span>
                <span
                    class="font-serif font-normal tracking-[-0.01em] text-[24px] tracking-tight">{{ $brandName }}</span>
            @endif
        </a>

        {{-- (Removed) The "Switch to Instagram" header icon used to render whenever
             the Instagram add-on was installed. It's gone — Instagram is used
             inside WaDesk (Unified Inbox, dashboard card, flows), so there's no
             separate app to switch to, and the icon in the header was unwanted. --}}

        @php
            $authUser = auth()->user();
            $allWorkspaces = $authUser ? $authUser->workspaces()->orderByDesc('last_active_at')->get() : collect();
            $currentWs = $authUser ? $authUser->current_workspace : null;
            // Plan gate — hide every "Create new workspace" affordance once the
            // owner has hit their plan's workspaces_per_owner_limit (admins bypass).
            $canCreateWorkspace = $authUser ? $authUser->canCreateWorkspace() : false;
        @endphp
        @if ($authUser && $currentWs)
            {{-- Hidden on mobile/tablet — the lone avatar circle confused users
                 ("what is this?"). Below lg the workspace switcher lives inside
                 the hamburger menu instead (see [data-mobile-nav-pane]). --}}
            <div class="relative shrink-0 max-w-[210px] hidden lg:block" data-ws-switcher>
                {{-- Workspace switcher: BO tile + workspace name + chevron.
 The name is capped (max-w + truncate) so a long workspace
 name clips with an ellipsis instead of overflowing the
 header / pushing the nav off its row. --}}
                @php
                    $wsColor = workspace_brand_color($currentWs, '');
                    // The default workspace name is "{owner}'s workspace" (e.g.
                    // "Bohecil's workspace"). On the compact button we drop that
                    // possessive owner prefix → just "Workspace". Custom names
                    // (no "'s ") show unchanged. Full real name stays in the
                    // tooltip + dropdown so workspaces are still distinguishable.
                    $wsStripped = preg_replace('/^.+?[\'\x{2019}]s\s+/u', '', (string) $currentWs->name);
                    $wsLabel =
                        $wsStripped !== '' && $wsStripped !== (string) $currentWs->name
                            ? \Illuminate\Support\Str::ucfirst($wsStripped)
                            : $currentWs->name;
                @endphp
                <button type="button" data-ws-toggle
                    class="group flex items-center gap-1 sm:gap-2 pl-1 pr-1 sm:pr-2.5 py-1 rounded-full hairline border border-paper-200 bg-paper-50 hover:bg-paper-0 transition max-w-[80px] sm:max-w-[210px]"
                    title="{{ $currentWs->name }} — {{ __('Switch workspace') }}">
                    <span
                        class="w-7 h-7 rounded-full text-paper-0 grid place-items-center text-[11px] font-bold tracking-tight shrink-0 shadow-sm ring-1 ring-black/10 {{ $wsColor ? '' : 'bg-gradient-to-br from-wa-teal to-wa-deep' }}"
                        @if ($wsColor) style="background:{{ $wsColor }};" @endif>{{ strtoupper(substr($currentWs->name ?? 'W', 0, 2)) }}</span>
                    <span
                        class="hidden sm:block min-w-0 truncate text-[13px] font-semibold text-ink-700">{{ $wsLabel }}</span>
                    <svg viewBox="0 0 12 12"
                        class="hidden sm:block w-3 h-3 text-ink-500 group-hover:text-ink-700 transition shrink-0"
                        fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M3 5l3 3 3-3" />
                    </svg>
                </button>
                <div data-ws-menu
                    class="hidden absolute left-0 mt-2 w-[280px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft p-2 z-30">
                    <div class="px-2 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Your workspaces') }}</div>
                    <div class="space-y-0.5 max-h-[260px] overflow-y-auto">
                        @foreach ($allWorkspaces as $w)
                            @php $isActive = $currentWs && $w->id === $currentWs->id; @endphp
                            <form method="POST" action="{{ route('workspaces.switch', $w->id) }}" class="block">
                                @csrf
                                <button type="submit"
                                    class="w-full flex items-center gap-2 px-2 py-2 rounded-xl text-left {{ $isActive ? 'bg-wa-mint' : 'hover:bg-paper-50' }}">
                                    <span
                                        class="w-7 h-7 rounded-full text-paper-0 grid place-items-center text-[10.5px] font-semibold"
                                        style="background:{{ workspace_brand_color($w) }};">{{ strtoupper(substr($w->name, 0, 2)) }}</span>
                                    <span class="flex-1 min-w-0">
                                        <span
                                            class="block text-[12.5px] font-semibold text-ink-900 truncate">{{ $w->name }}</span>
                                        <span
                                            class="block text-[10.5px] text-ink-500 font-mono truncate">{{ $w->slug }}</span>
                                    </span>
                                    @if ($isActive)
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path d="M3 8l3 3 7-7" />
                                        </svg>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @if ($canCreateWorkspace)
                    <div class="border-t border-paper-200 mt-2 pt-2">
                        <a href="{{ route('workspaces.create') }}"
                            class="flex items-center gap-2 px-2 py-2 rounded-xl hover:bg-paper-50 text-[12.5px] font-semibold text-wa-deep">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                            Create new workspace
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        @endif

        <nav class="flex-1 min-w-0 hidden lg:flex items-center justify-center overflow-hidden" data-tour="navbar">
            <div class="flex items-center gap-0.5 2xl:gap-1">
                @foreach ($navItems as $item)
                    @php $isActive = $item['key'] === $active; @endphp
                    <div class="relative inline-flex shrink-0">
                        <a href="{{ $item['href'] }}" title="{{ $item['label'] }}" data-tour="nav-{{ $item['key'] }}" @class([
                            'inline-flex items-center gap-[6px] 2xl:gap-2 px-2.5 2xl:px-3.5 py-[7px] rounded-full text-[12.5px] 2xl:text-[13px] font-medium transition whitespace-nowrap',
                            'bg-wa-deep text-paper-0' => $isActive,
                            'text-ink-600 hover:bg-paper-50' => !$isActive,
                        ])>
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 2xl:w-4 2xl:h-4 shrink-0" fill="none"
                                stroke="currentColor" stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                            <span>{{ $item['label'] }}</span>
                        </a>
                        @if (!empty($item['feature']))
                            <span class="absolute -top-1 right-2 z-10 leading-none [&_.plan-crown]:ml-0"><x-plan-crown
                                    :feature="$item['feature']" size="sm" /></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </nav>

        <div class="flex-1 lg:hidden"></div>

        <div class="flex items-center gap-2 shrink-0">
            <div class="relative lg:hidden" data-mobile-nav>
                <button type="button" data-mobile-nav-toggle
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-paper-50 text-ink-700 hover:bg-paper-100">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div data-mobile-nav-pane
                    class="hidden absolute right-0 mt-2 w-[260px] max-w-[calc(100vw-1.5rem)] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft p-2 z-50">
                    {{-- Workspace switcher — moved here from the header so phones
                         don't show a mystery avatar circle. Labelled clearly. --}}
                    @if ($authUser && $currentWs)
                        <div class="px-1 pb-2 mb-1.5 border-b border-paper-200">
                            <div class="px-2 font-mono text-[9px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                                {{ __('Workspace') }}</div>
                            <div class="max-h-[180px] overflow-y-auto space-y-0.5">
                                @foreach ($allWorkspaces as $w)
                                    @php $isActiveWs = $currentWs && $w->id === $currentWs->id; @endphp
                                    <form method="POST" action="{{ route('workspaces.switch', $w->id) }}"
                                        class="block">
                                        @csrf
                                        <button type="submit"
                                            class="w-full flex items-center gap-2 px-2 py-1.5 rounded-xl text-left {{ $isActiveWs ? 'bg-wa-mint' : 'hover:bg-paper-50' }}">
                                            <span
                                                class="w-6 h-6 rounded-full text-paper-0 grid place-items-center text-[10px] font-semibold shrink-0"
                                                style="background:{{ workspace_brand_color($w) }};">{{ strtoupper(substr($w->name, 0, 2)) }}</span>
                                            <span
                                                class="flex-1 min-w-0 text-[12.5px] font-semibold text-ink-900 truncate">{{ $w->name }}</span>
                                            @if ($isActiveWs)
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep shrink-0"
                                                    fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M3 8l3 3 7-7" />
                                                </svg>
                                            @endif
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                            @if ($canCreateWorkspace)
                            <a href="{{ route('workspaces.create') }}"
                                class="flex items-center gap-1.5 px-2 py-1.5 mt-0.5 rounded-xl hover:bg-paper-50 text-[12px] font-semibold text-wa-deep">
                                <svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                {{ __('New workspace') }}
                            </a>
                            @endif
                        </div>
                    @endif
                    @foreach ($navItems as $item)
                        @php $isActive = $item['key'] === $active; @endphp
                        <a href="{{ $item['href'] }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ $isActive ? 'bg-wa-mint text-wa-deep font-semibold' : 'hover:bg-paper-50 text-ink-700' }}">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor"
                                stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                            <span class="text-[13px]">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <button
                class="hidden md:flex w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 items-center justify-center"
                title="{{ __('Search') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor"
                    stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
            </button>
            <div class="relative hidden md:block">
                <button id="wa-theme-btn"
                    class="w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Theme') }}">
                    <svg id="wa-theme-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none"
                        stroke="currentColor" stroke-width="1.5">
                        <path d="M8 1a7 7 0 1 0 7 7 5.5 5.5 0 0 1-7-7z" />
                    </svg>
                </button>
            </div>

            {{-- Language switcher dropdown. Single source: lang/<code>.json.
 POSTs to /locale → persists to users.locale + session. --}}
            <div class="hidden md:block">
                <x-locale-switcher />
            </div>

            {{-- Notification bell + dropdown. Feed is fetched from
 /notifications/recent on toggle and refreshed every 60s
 in the background. Mark-all-read + clear hit the
 existing controller endpoints. --}}
            <div class="relative" data-notif-wrap>
                <button id="notif-toggle" type="button"
                    class="relative w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Notifications') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M8 1.5a4 4 0 0 0-4 4v2.4L2.7 11h10.6L12 7.9V5.5a4 4 0 0 0-4-4z" />
                        <path d="M6.5 12.5a1.5 1.5 0 0 0 3 0" />
                    </svg>
                    <span id="notif-badge"
                        class="hidden absolute -top-0.5 -right-0.5 min-w-[16px] h-[16px] px-1 rounded-full bg-accent-coral text-paper-0 text-[9.5px] font-bold leading-[16px] text-center">0</span>
                </button>
                {{-- Mobile: pin to the viewport so the panel can't clip off the
                     screen edge. Desktop: the normal anchored dropdown. --}}
                <div id="notif-pane"
                    class="hidden fixed left-3 right-3 top-16 w-auto md:absolute md:inset-auto md:right-0 md:left-auto md:top-auto md:mt-2 md:w-[360px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft overflow-hidden z-50">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between gap-3">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Inbox') }}</div>
                            <div class="font-serif text-[16px] text-ink-900">{{ __('Notifications') }}</div>
                        </div>
                        <button id="notif-read-all" type="button"
                            class="text-[11px] font-semibold text-wa-deep hover:underline">{{ __('Mark all read') }}</button>
                    </div>
                    <div id="notif-list" class="max-h-[420px] overflow-y-auto divide-y divide-paper-100">
                        <div class="px-4 py-10 text-center text-[12px] text-ink-500">{{ __('Loading…') }}</div>
                    </div>
                    <div
                        class="px-4 py-2.5 border-t border-paper-200 flex items-center justify-between bg-paper-50/60">
                        <button id="notif-clear" type="button"
                            class="text-[11.5px] font-semibold text-accent-coral hover:underline">{{ __('Clear all') }}</button>
                        <a href="{{ url('/notifications') }}"
                            class="text-[11.5px] font-semibold text-wa-deep hover:underline">{{ __('View all →') }}</a>
                    </div>
                </div>
            </div>

            @if (\App\Support\PlatformPermissions::userHasPlatformAccess(auth()->user()))
                <a href="{{ url('/admin') }}"
                    class="hidden md:flex w-9 h-9 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 items-center justify-center"
                    title="{{ __('Admin') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <circle cx="8" cy="8" r="2" />
                        <path
                            d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z" />
                    </svg>
                </a>
            @endif
            @php
                $au = auth()->user();
                $base = $au ? ($au->name ?: $au->email) : '';
                $parts = preg_split('/\s+/', trim($base));
                $initials = $au ? strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)) : 'WA';
                $isAdminUser = $au && method_exists($au, 'isAdmin') ? $au->isAdmin() : false;
                $avatarUrl = $au?->avatar_url;
            @endphp
            <div class="relative ml-1" data-user-menu>
                <button type="button" data-user-toggle
                    class="flex items-center gap-1 md:gap-2 pl-1 md:pr-3 py-1 rounded-full hover:bg-paper-50">
                    {{-- Initials base + photo overlay that self-removes on a broken
                         URL, so the avatar is never an empty circle. --}}
                    <span class="relative overflow-hidden w-9 h-9 rounded-full ring-1 ring-paper-200 bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[12px] font-semibold flex items-center justify-center shrink-0">
                        <span>{{ $initials ?: 'WA' }}</span>
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt=""
                                class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
                        @endif
                    </span>
                    <svg class="w-3 h-3 text-ink-500 hidden md:block" viewBox="0 0 12 12" fill="none"
                        stroke="currentColor" stroke-width="1.5">
                        <path d="M3 5l3 3 3-3" />
                    </svg>
                </button>
                @if ($au)
                    <div data-user-pane
                        class="hidden absolute right-0 mt-2 w-[240px] bg-paper-0 border border-paper-200 rounded-2xl shadow-soft p-2 z-50">
                        <div class="px-2 py-1.5 text-[12px] text-ink-500 truncate">{{ $au->email }}</div>
                        <a href="{{ url('/account') }}"
                            class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('My account') }}</a>
                        <a href="{{ url('/settings') }}"
                            class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Settings') }}</a>
                        @if ($canCreateWorkspace)
                        <a href="{{ route('workspaces.create') }}"
                            class="block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Create workspace') }}</a>
                        @endif

                        @if ($isAdminUser)
                        <div class="md:hidden border-t border-paper-200 my-1"></div>
                        <a href="{{ url('/admin') }}"
                            class="md:hidden block px-2 py-2 rounded-xl hover:bg-paper-50 text-[13px]">{{ __('Admin') }}</a>
                        @endif

                        <div class="border-t border-paper-200 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="w-full text-left px-2 py-2 rounded-xl hover:bg-accent-coral/10 text-[13px] text-accent-coral font-semibold">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</header>
<script>
    (function() {
        const wsBtn = document.querySelector('[data-ws-toggle]');
        const wsMenu = document.querySelector('[data-ws-menu]');
        wsBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            wsMenu?.classList.toggle('hidden');
        });

        const userBtn = document.querySelector('[data-user-toggle]');
        const userPane = document.querySelector('[data-user-pane]');
        userBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            userPane?.classList.toggle('hidden');
        });

        const mobileNavBtn = document.querySelector('[data-mobile-nav-toggle]');
        const mobileNavPane = document.querySelector('[data-mobile-nav-pane]');
        mobileNavBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            mobileNavPane?.classList.toggle('hidden');
        });

        document.addEventListener('click', () => {
            wsMenu?.classList.add('hidden');
            userPane?.classList.add('hidden');
            mobileNavPane?.classList.add('hidden');
            notifPane?.classList.add('hidden');
        });

        // ----- Notification bell dropdown -----
        const notifBtn = document.getElementById('notif-toggle');
        const notifPane = document.getElementById('notif-pane');
        const notifList = document.getElementById('notif-list');
        const notifBadge = document.getElementById('notif-badge');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [c]));

        function renderItems(items) {
            if (!items?.length) {
                notifList.innerHTML =
                    '<div class="px-4 py-10 text-center text-[12px] text-ink-500">No notifications yet.</div>';
                return;
            }
            notifList.innerHTML = items.map((n) => {
                const tone = n.severity === 'error' || n.severity === 'critical' ? 'text-accent-coral' :
                    n.severity === 'warning' ? 'text-[#7B5A14]' :
                    'text-ink-900';
                const dot = n.unread ?
                    '<span class="w-1.5 h-1.5 rounded-full bg-wa-deep flex-shrink-0 mt-1.5"></span>' :
                    '<span class="w-1.5 h-1.5 flex-shrink-0 mt-1.5"></span>';
                const href = n.action_url || '{{ url('/notifications') }}';
                return `
 <a href="${escapeHtml(href)}" data-notif-id="${n.id}" data-unread="${n.unread ? 1 : 0}" class="block px-4 py-3 hover:bg-paper-50 transition flex items-start gap-2.5">
 ${dot}
 <div class="flex-1 min-w-0">
 <div class="text-[13px] ${tone} font-semibold truncate">${escapeHtml(n.title || '(no title)')}</div>
 ${n.message ? `<div class="text-[11.5px] text-ink-500 mt-0.5 line-clamp-2">${escapeHtml(n.message)}</div>` : ''}
 <div class="text-[10.5px] text-ink-500 font-mono mt-1">${escapeHtml(n.time_ago)}</div>
 </div>
 </a>`;
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
                const res = await fetch('{{ route('user.notifications.recent') }}', {
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
        document.getElementById('notif-read-all')?.addEventListener('click', async (e) => {
            e.stopPropagation();
            await fetch('{{ route('user.notifications.read-all') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            });
            loadNotifs();
        });
        document.getElementById('notif-clear')?.addEventListener('click', async (e) => {
            e.stopPropagation();
            await fetch('{{ route('user.notifications.destroy-all') }}', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            });
            loadNotifs();
        });
        notifPane?.addEventListener('click', (e) => e.stopPropagation());

        // Mark a SINGLE notification read when its row is clicked. The row is an
        // <a> that navigates on click, so the POST uses keepalive (survives the
        // navigation) and we optimistically drop the badge by one when the clicked
        // item was unread. Previously only "Mark all read" cleared the count — a
        // single click navigated away without ever hitting the read endpoint.
        notifList?.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-notif-id]');
            if (!a) return;
            const id = a.dataset.notifId;
            if (!id) return;
            const wasUnread = a.dataset.unread === '1';
            fetch('{{ url('/notifications') }}/' + id + '/read', {
                method: 'POST',
                keepalive: true,
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                }
            }).catch(() => {});
            if (wasUnread) {
                a.dataset.unread = '0';
                const cur = (notifBadge && !notifBadge.classList.contains('hidden')) ?
                    (parseInt(notifBadge.textContent, 10) || 0) : 0;
                renderBadge(Math.max(0, cur - 1));
            }
        });

        // Initial badge fetch + 60s refresh so users see new notifs without reloading.
        loadNotifs();
        setInterval(() => {
            if (!document.hidden) loadNotifs();
        }, 60000);
    })();
</script>
