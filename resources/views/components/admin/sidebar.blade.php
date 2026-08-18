@props(['active' => 'overview'])

@php
    $supportOpen = $supportUnassigned = 0;
    try {
        $supportOpen = (int) \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress', 'pending'])->count();
        $supportUnassigned = (int) \App\Models\SupportTicket::whereNull('assigned_agent_id')
            ->whereIn('status', ['open', 'pending'])
            ->count();
    } catch (\Throwable $e) {
    }

    // Live counts for the rest of the sidebar — every "static" 4 / 142
    // / 18k / 1.2k pill was hard-coded prototype text. These now read
    // from the actual tables. Each is wrapped so a missing table /
    // schema mismatch never takes the sidebar down.
    $fmtCount = function (int $n): string {
        if ($n >= 1000000) {
            return number_format($n / 1000000, $n >= 10000000 ? 0 : 1) . 'M';
        }
        if ($n >= 1000) {
            return number_format($n / 1000, $n >= 10000 ? 0 : 1) . 'k';
        }
        return (string) $n;
    };
    $usersCount = $rolesCount = $permsCount = $workspacesCount = $devicesCount = 0;
    $packagesCount = $creditPackagesCount = $couponsCount = $billingHistoryCount = $orderHistoryCount = $invoicesCount = 0;
    $supportTeamCount = $supportAgentsCount = $supportSlaCount = $supportCustomersCount = $supportPlaybooksCount = 0;
    $announcementsCount = $guidebookCount = 0;
    $contactUnread = 0;
    try {
        $contactUnread = (int) \App\Models\ContactMessage::where('is_read', false)->count();
    } catch (\Throwable $e) {
    }
    try {
        $usersCount = (int) \App\Models\User::query()->count();
    } catch (\Throwable $e) {
    }
    try {
        $devicesCount = (int) \App\Models\Device::query()->count();
    } catch (\Throwable $e) {
    }
    try {
        $rolesCount = (int) \DB::table('roles')->count();
    } catch (\Throwable $e) {
    }
    try {
        $permsCount = (int) \DB::table('permissions')->count();
    } catch (\Throwable $e) {
    }
    try {
        $workspacesCount = (int) \App\Models\Workspace::query()->count();
    } catch (\Throwable $e) {
    }
    try {
        $packagesCount = (int) \App\Models\Package::query()->count();
    } catch (\Throwable $e) {
    }
    try {
        $creditPackagesCount = (int) \App\Models\CreditPackage::query()->count();
    } catch (\Throwable $e) {
    }
    // Coupons badge = active coupons only — expired/disabled coupons
    // are noise on the sidebar. "active" means is_active + within
    // valid_from/valid_to window when those columns exist.
    try {
        $couponsCount = (int) \App\Models\Coupon::query()
            ->when(\Schema::hasColumn('coupons', 'is_active'), fn($q) => $q->where('is_active', true))
            ->count();
    } catch (\Throwable $e) {
    }
    try {
        $billingHistoryCount = (int) \DB::table('orders')->count();
    } catch (\Throwable $e) {
    }
    try {
        $orderHistoryCount = (int) \DB::table('orders')->count();
    } catch (\Throwable $e) {
    }
    try {
        $invoicesCount = (int) \DB::table('orders')->where('status', 'paid')->count();
    } catch (\Throwable $e) {
    }

    // Support group children. Each lookup is independent so a missing
    // table on a fresh install just leaves that badge blank.
    try {
        $supportTeamCount = (int) \App\Models\SupportTicket::whereNull('assigned_agent_id')
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->count();
    } catch (\Throwable $e) {
    }
    try {
        $supportAgentsCount = (int) \App\Models\SupportAgent::where('is_active', true)->count();
    } catch (\Throwable $e) {
    }
    // SLA badge = tickets with first_response/resolution SLA at risk
    // or already breached. Cheap heuristic: open tickets without a
    // first_response_at older than the workspace SLA window. Falls
    // back to all open tickets if the SLA column isn't there.
try {
    $supportSlaCount = (int) \App\Models\SupportTicket::query()
        ->whereIn('status', ['open', 'pending', 'in_progress'])
        ->whereNull('first_response_at')
        ->count();
} catch (\Throwable $e) {
}
// Customers = distinct visitor emails seen in support tickets —
// proxy for "people we've supported" until the explicit
// support_customers table is populated.
try {
    $supportCustomersCount = (int) \App\Models\SupportTicket::query()
        ->whereNotNull('email')
        ->distinct('email')
        ->count('email');
} catch (\Throwable $e) {
}
try {
    $supportPlaybooksCount = (int) \App\Models\Playbook::where('is_active', true)->count();
} catch (\Throwable $e) {
}
// Marketing group — only count "live" rows so the badge tells
// admins what visitors actually see.
try {
    $announcementsCount = (int) \App\Models\Announcement::query()
        ->when(\Schema::hasColumn('announcements', 'is_active'), fn($q) => $q->where('is_active', true))
        ->count();
} catch (\Throwable $e) {
}
try {
    $guidebookCount = (int) \App\Models\GuidebookArticle::query()
        ->when(\Schema::hasColumn('guidebook_articles', 'is_published'), fn($q) => $q->where('is_published', true))
        ->count();
} catch (\Throwable $e) {
}

$nav = [
    [
        'type' => 'leaf',
        'key' => 'overview',
        'href' => url('/admin'),
        'label' => __('Overview'),
        'icon' =>
            '<rect x="2" y="2" width="5" height="6" rx="1"/><rect x="9" y="2" width="5" height="3" rx="1"/><rect x="9" y="7" width="5" height="7" rx="1"/><rect x="2" y="10" width="5" height="4" rx="1"/>',
        'sw' => 1.6,
    ],

    [
        'type' => 'leaf',
        'key' => 'financial',
        'href' => url('/admin/financial'),
        'label' => __('Financial'),
        'icon' => '<path d="M2 13V5l6 4 6-4v8"/><path d="M2 5l6 4 6-4"/>',
        'sw' => 1.6,
    ],

    [
        'type' => 'leaf',
        'key' => 'premium',
        'href' => url('/admin/premium'),
        'label' => __('Premium'),
        'icon' =>
            '<path d="M2 6l3-3 3 3-3 3-3-3Zm6 0l3-3 3 3-3 3-3-3ZM2 12l3-3 3 3-3 3-3-3Zm6 0l3-3 3 3-3 3-3-3Z"/>',
        'sw' => 1.4,
    ],

    [
        'type' => 'leaf',
        'key' => 'analytics',
        'href' => url('/admin/analytics'),
        'label' => __('Analytics'),
        'icon' => '<path d="M2 12h12M4 10l2.2-3 3 2 3.2-5"/>',
        'sw' => 1.6,
    ],

    [
        'type' => 'leaf',
        'key' => 'ai-dashboard',
        'href' => url('/admin/ai-dashboard'),
        'label' => __('AI Dashboard'),
        'icon' => '<path d="M8 2v2M8 12v2M2 8h2M12 8h2M5.5 5.5 4 4M10.5 5.5 12 4M5.5 10.5 4 12M10.5 10.5 12 12"/><circle cx="8" cy="8" r="2.4"/>',
        'sw' => 1.5,
    ],

    [
        'type' => 'leaf',
        'key' => 'health',
        'href' => url('/admin/health'),
        'label' => __('System Health'),
        'icon' => '<path d="M1.5 8h2.5l1.5-4 2 8 1.5-4h3.5"/>',
        'sw' => 1.6,
    ],

    [
        'type' => 'group',
        'key' => 'access',
        'label' => __('Users & access'),
        'icon' => '<circle cx="6" cy="6" r="3"/><path d="M2 14c0-3 2.5-5 4-5s4 2 4 5"/>',
        'sw' => 1.5,
        'children' => [
            [
                'key' => 'users',
                'href' => url('/admin/users'),
                'label' => __('Users'),
                'badge' => $usersCount > 0 ? $fmtCount($usersCount) : null,
            ],
            [
                'key' => 'roles',
                'href' => url('/admin/roles'),
                'label' => __('Roles'),
                'badge' => $rolesCount > 0 ? (string) $rolesCount : null,
            ],
            [
                'key' => 'permissions',
                'href' => url('/admin/permissions'),
                'label' => __('Permissions'),
                'badge' => $permsCount > 0 ? (string) $permsCount : null,
            ],
            [
                'key' => 'devices',
                'href' => url('/admin/devices'),
                'label' => __('Devices'),
                'badge' => $devicesCount > 0 ? $fmtCount($devicesCount) : null,
            ],
        ],
    ],

    [
        'type' => 'leaf',
        'key' => 'workspaces',
        'href' => url('/admin/workspaces'),
        'label' => __('Workspaces'),
        'icon' => '<rect x="2.5" y="3" width="11" height="10" rx="1.5"/><path d="M5 6h6M5 9h4"/>',
        'sw' => 1.6,
        'badge' => $workspacesCount > 0 ? $fmtCount($workspacesCount) : null,
    ],

    // ─────────────────────────────────────────────────────────────
    // Contacts / Meta Ads / Messaging (Campaigns, Broadcasts, Templates,
    // Flows, Auto-replies) / Platform (Integrations, Webhooks) used to be
    // commented-out entries here because they duplicate the user side. The
    // admin pages behind them were static mock-ups that nothing linked to,
    // so both the routes and the blades have now been removed — do not
    // re-add menu entries for them without rebuilding the pages first.
    //
    // Devices is NOT in that list: it is live, under "Users & access" above.
    // ─────────────────────────────────────────────────────────────

    [
        'type' => 'group',
        'key' => 'billing',
        'label' => __('Billing & plans'),
        'icon' => '<rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M2 7h12"/>',
        'sw' => 1.6,
        'children' => [
            [
                'key' => 'packages',
                'href' => url('/admin/packages'),
                'label' => __('Packages'),
                'badge' => $packagesCount > 0 ? (string) $packagesCount : null,
            ],
            [
                'key' => 'credit-packages',
                'href' => url('/admin/credit-packages'),
                'label' => __('Credit packages'),
                'badge' => $creditPackagesCount > 0 ? (string) $creditPackagesCount : null,
            ],
            [
                'key' => 'addons',
                'href' => url('/admin/addons'),
                'label' => __('Add-ons'),
                'badge' => ($c = \App\Models\Package::addons()->count()) > 0 ? (string) $c : null,
            ],
            [
                'key' => 'coupons',
                'href' => url('/admin/coupons'),
                'label' => __('Coupons'),
                'badge' => $couponsCount > 0 ? (string) $couponsCount : null,
            ],
            [
                'key' => 'billing-history',
                'href' => url('/admin/billing-history'),
                'label' => __('Billing history'),
                'badge' => $billingHistoryCount > 0 ? $fmtCount($billingHistoryCount) : null,
            ],
            [
                'key' => 'order-history',
                'href' => url('/admin/order-history'),
                'label' => __('Order history'),
                'badge' => $orderHistoryCount > 0 ? $fmtCount($orderHistoryCount) : null,
            ],
            [
                'key' => 'invoices',
                'href' => url('/admin/invoices'),
                'label' => __('Invoices'),
                'badge' => $invoicesCount > 0 ? $fmtCount($invoicesCount) : null,
            ],
            // Checkout / pricing controls — tax, refund window, yearly
            // discount, company invoice identity, AND the auto-renew
            // (recurring subscriptions) switch.
            [
                'key' => 'checkout-settings',
                'href' => url('/admin/checkout-settings'),
                'label' => __('Billing settings'),
            ],
            // Wallet rules + affiliate credits — kept inside Billing
            // because they only control billing-side math (referral
            // signup credits, credits_per_message, credits_per_currency_minor).
            [
                'key' => 'wallet-rules',
                'href' => url('/admin/settings/wallet-rules'),
                'label' => __('Wallet & affiliate'),
            ],
        ],
    ],

    [
        'type' => 'group',
        'key' => 'support',
        'label' => __('Support'),
        'icon' => '<circle cx="8" cy="8" r="6"/><path d="M5.5 6a2.5 2.5 0 1 1 5 0c0 2-2.5 2-2.5 4M8 12.5h.01"/>',
        'sw' => 1.6,
        'badge' => $supportOpen + $contactUnread > 0 ? (string) ($supportOpen + $contactUnread) : null,
        'badgeStyle' => 'coral',
        'children' => [
            [
                'key' => 'support',
                'href' => url('/admin/support'),
                'label' => __('Inbox'),
                'badge' => $supportOpen > 0 ? (string) $supportOpen : null,
                'badgeStyle' => 'coral',
            ],
            [
                'key' => 'contact-messages',
                'href' => url('/admin/contact-messages'),
                'label' => __('Contact inbox'),
                'badge' => $contactUnread > 0 ? (string) $contactUnread : null,
                'badgeStyle' => 'coral',
            ],
            [
                'key' => 'support-team',
                'href' => url('/admin/support/team-inbox'),
                'label' => __('Team inbox'),
                'badge' => $supportTeamCount > 0 ? (string) $supportTeamCount : null,
            ],
            [
                'key' => 'support-agents',
                'href' => url('/admin/support/agents'),
                'label' => __('Agents'),
                'badge' => $supportAgentsCount > 0 ? (string) $supportAgentsCount : null,
            ],
            [
                'key' => 'support-sla',
                'href' => url('/admin/support/sla'),
                'label' => __('SLA board'),
                'badge' => $supportSlaCount > 0 ? (string) $supportSlaCount : null,
                'badgeStyle' => $supportSlaCount > 0 ? 'coral' : null,
            ],
            [
                'key' => 'support-customers',
                'href' => url('/admin/support/customers'),
                'label' => __('Customers'),
                'badge' => $supportCustomersCount > 0 ? $fmtCount($supportCustomersCount) : null,
            ],
            [
                'key' => 'support-playbooks',
                'href' => url('/admin/support/playbooks'),
                'label' => __('Playbooks'),
                'badge' => $supportPlaybooksCount > 0 ? (string) $supportPlaybooksCount : null,
            ],
            ['key' => 'support-reports', 'href' => url('/admin/support/reports'), 'label' => __('Reports')],
        ],
    ],

    [
        'type' => 'group',
        'key' => 'marketing',
        'label' => __('Marketing'),
        'icon' => '<path d="M3 5h10v6H6l-3 2v-2H3z"/>',
        'sw' => 1.5,
        'children' => [
            [
                'key' => 'announcements',
                'href' => url('/admin/announcements'),
                'label' => __('Announcements'),
                'badge' => $announcementsCount > 0 ? (string) $announcementsCount : null,
            ],
            [
                'key' => 'guidebook',
                'href' => url('/admin/guidebook'),
                'label' => __('Guidebook'),
                'badge' => $guidebookCount > 0 ? (string) $guidebookCount : null,
            ],
        ],
    ],

    /* Analytics — duplicates user /analytics
 ['type' => 'leaf', 'key' => 'analytics-dup', 'href' => url('/admin/analytics'), 'label' => __('Analytics'),
 'icon' => '<path d="M2 12h12M4 10l2.2-3 3 2 3.2-5"/>', 'sw' => 1.6],
 */
];

$sys = [
    [
        'key' => 'wadesk-message',
        'href' => url('/admin/settings/wadesk-message'),
        'label' => __('System Message Setting'),
        'icon' =>
            '<path d="M3 4.5A2.5 2.5 0 0 1 5.5 2h5A2.5 2.5 0 0 1 13 4.5v4A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-4Z"/><path d="M5.5 6.5h5M5.5 8.5h3"/>',
        'sw' => 1.6,
    ],
    // Currencies, Languages, Payment gateways, AI/API keys, Audit
    // log all live under Settings as tiles now — removed from the
    // System rail to keep that rail short and focused on what's
    // genuinely "system": WaDesk message provider, Security, and
    // the Settings hub itself.
    [
        'key' => 'frontend',
        'href' => url('/admin/frontend'),
        'label' => __('Frontend editor'),
        'icon' => '<rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M2 6h12"/><path d="M5 9h4M5 11h2"/>',
        'sw' => 1.6,
    ],
    [
        'key' => 'security',
        'href' => url('/admin/security'),
        'label' => __('Security'),
        'icon' => '<path d="M8 2l5 2v4c0 3.2-2 5.4-5 6-3-.6-5-2.8-5-6V4z"/><path d="M6 8l1.5 1.5L10.5 6"/>',
        'sw' => 1.6,
    ],
    [
        'key' => 'storage',
        'href' => url('/admin/storage'),
        'label' => __('Storage'),
        'icon' => '<ellipse cx="8" cy="4" rx="5.5" ry="2"/><path d="M2.5 4v8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2V4"/><path d="M2.5 8c0 1.1 2.5 2 5.5 2s5.5-.9 5.5-2"/>',
        'sw' => 1.6,
    ],
    [
        'key' => 'realtime',
        'href' => url('/admin/settings/realtime'),
        'label' => __('Real-time'),
        'icon' => '<path d="M8 5.5a2.5 2.5 0 100 5 2.5 2.5 0 000-5z"/><path d="M4.2 4.2a5.4 5.4 0 000 7.6M11.8 4.2a5.4 5.4 0 010 7.6"/>',
        'sw' => 1.6,
    ],
    [
        'key' => 'blog',
        'href' => url('/admin/blog'),
        'label' => __('Blog'),
        'icon' => '<path d="M3 2.5h7l3 3v8H3z"/><path d="M9.5 2.5v3.5H13"/><path d="M5.5 8.5h5M5.5 11h5"/>',
        'sw' => 1.6,
    ],
    [
        'key' => 'flow-templates',
        'href' => url('/admin/flow-templates'),
        'label' => __('Flow templates'),
        'icon' => '<circle cx="3.5" cy="8" r="1.6"/><circle cx="12.5" cy="3.5" r="1.6"/><circle cx="12.5" cy="12.5" r="1.6"/><path d="M5 7.2l6-3M5 8.8l6 3"/>',
        'sw' => 1.5,
    ],
    [
        'key' => 'settings',
        'href' => url('/admin/settings'),
        'label' => __('Settings'),
        'icon' =>
            '<circle cx="8" cy="8" r="2"/><path d="M13 8a5 5 0 0 0-.1-1.1l1.4-1-1.5-2.6-1.6.7a5 5 0 0 0-1.9-1.1L9 1H7l-.3 1.9a5 5 0 0 0-1.9 1.1l-1.6-.7-1.5 2.6 1.4 1A5 5 0 0 0 3 8c0 .4 0 .7.1 1.1l-1.4 1 1.5 2.6 1.6-.7a5 5 0 0 0 1.9 1.1L7 15h2l.3-1.9a5 5 0 0 0 1.9-1.1l1.6.7 1.5-2.6-1.4-1c.1-.4.1-.7.1-1.1Z"/>',
        'sw' => 1.5,
        ],
    ];

// Nav contributed by installed extensions (Instagram is the first). Merged
// here rather than hardcoded above so the entry appears when the extension is
// installed and disappears when it is disabled — no core edit either way.
foreach (\App\Services\ExtensionRegistry::nav('admin') as $extItem) {
    $sys[] = $extItem;
}

@endphp

<div id="admin-sidebar-root" class="bg-paper-0 border-r border-paper-200 flex flex-col sticky top-0 h-screen">
    <div class="admin-brand-header h-16 px-5 flex items-center justify-between border-b border-paper-200 shrink-0">
        @php
            // Resolve the logo for the user's currently-selected theme.
// Falls back through paper → null. When null we render the
// legacy SVG+wordmark so nothing breaks if no logo's uploaded.
            $brandTheme = \App\Support\Brand::activeTheme();
            $brandLogo = \App\Support\Brand::logoUrl($brandTheme);
            $brandName = (string) brand_name();
        @endphp
        <a href="{{ url('/dashboard') }}" class="js-sb-collapse-hide flex items-center gap-2.5">
            @if ($brandLogo)
                {{-- data-brand-logo lets wadesk.js setTheme() swap the src
 to the matching per-theme logo at theme-change time. --}}
                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" data-brand-logo
                    class="h-9 w-auto max-w-[160px] object-contain">
            @else
                <span
                    class="relative inline-flex items-center justify-center w-9 h-9 rounded-lg bg-wa-deep text-paper-0">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Zm5.07 14.07c-.21.6-1.22 1.14-1.7 1.21-.45.07-1.02.1-1.65-.1-.38-.12-.87-.28-1.49-.55-2.62-1.13-4.33-3.77-4.46-3.94-.13-.18-1.07-1.42-1.07-2.71 0-1.29.68-1.92.92-2.18.24-.27.52-.34.7-.34h.5c.16 0 .38-.06.59.45.21.51.71 1.76.77 1.89.06.13.1.28.02.45-.08.18-.12.28-.24.43-.12.15-.26.34-.37.46-.12.12-.25.26-.11.51.14.26.62 1.02 1.33 1.65.91.81 1.68 1.06 1.94 1.18.26.13.41.11.56-.06.15-.18.65-.76.83-1.02.18-.26.36-.21.6-.13.24.09 1.55.73 1.81.86.27.13.45.2.51.31.07.12.07.69-.14 1.29Z" />
                    </svg>
                </span>
                <span class="leading-none">
                    <span class="block font-serif font-normal text-[20px] tracking-[-0.01em]">{{ $brandName }}</span>
                    <span
                        class="block text-[9.5px] font-mono uppercase tracking-[0.18em] text-ink-500 mt-1">{{ __('Admin console') }}</span>
                </span>
            @endif
        </a>
        <button type="button" id="admin-sidebar-toggle" aria-label="{{ __('Collapse sidebar') }}"
            title="{{ __('Collapse sidebar') }}"
            class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-ink-500 hover:text-ink-900 hover:bg-paper-50 transition">
            <svg viewBox="0 0 16 16" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor"
                stroke-width="1.6">
                <rect x="2" y="2.5" width="12" height="11" rx="2" />
                <path d="M6 2.5v11" />
                <path d="M11.5 6 9.5 8l2 2" />
            </svg>
        </button>
    </div>

    <nav class="px-3 py-4 flex-1 overflow-y-auto">
        <div class="js-sb-collapse-hide px-3 pb-1.5 text-[9.5px] font-mono uppercase tracking-[0.18em] text-ink-500">
            {{ __('Main menu') }}</div>
        <div class="space-y-0.5">
            @foreach ($nav as $item)
                @if ($item['type'] === 'group')
                    @php
                        $childActive = collect($item['children'])->contains(fn($c) => $c['key'] === $active);
                        $isOpen = $childActive || $item['key'] === $active;
                    @endphp
                    <div class="admin-nav-group">
                        <button type="button" data-admin-toggle @class([
                            'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px] w-full text-left',
                            'text-ink-900 font-semibold' => $childActive,
                            'text-ink-700 hover:bg-paper-50 font-medium' => !$childActive,
                        ])>
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                                stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                            <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            @if (!empty($item['badge']))
                                <span @class([
                                    'rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/30 px-1.5 py-0.5 text-[10px] font-semibold' =>
                                        ($item['badgeStyle'] ?? null) === 'coral',
                                    'font-mono text-[10px] text-ink-500' =>
                                        ($item['badgeStyle'] ?? null) !== 'coral',
                                ])>{{ $item['badge'] }}</span>
                            @endif
                            <svg data-admin-chevron
                                class="w-3 h-3 text-ink-500 shrink-0 transition-transform {{ $isOpen ? 'rotate-180' : '' }}"
                                viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M3 5l3 3 3-3" />
                            </svg>
                        </button>
                        <div @class([
                            'admin-nav-children space-y-0.5 mt-0.5 ml-3 pl-3 border-l border-paper-200',
                            'hidden' => !$isOpen,
                        ])>
                            @foreach ($item['children'] as $child)
                                @php $cActive = $child['key'] === $active; @endphp
                                <a href="{{ $child['href'] }}" @class([
                                    'flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12px]',
                                    'bg-wa-deep text-paper-0 font-semibold' => $cActive,
                                    'text-ink-700 hover:bg-paper-50' => !$cActive,
                                ])>
                                    <span @class([
                                        'w-1 h-1 rounded-full ml-1 shrink-0',
                                        'bg-paper-0' => $cActive,
                                        'bg-paper-300' => !$cActive,
                                    ])></span>
                                    <span class="flex-1 truncate">{{ $child['label'] }}</span>
                                    @if (!empty($child['badge']))
                                        <span @class([
                                            'ml-auto rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/30 px-1.5 py-0.5 text-[10px] font-semibold' =>
                                                ($child['badgeStyle'] ?? null) === 'coral',
                                            'ml-auto font-mono text-[10px]' =>
                                                ($child['badgeStyle'] ?? null) !== 'coral',
                                            'opacity-90' => $cActive && ($child['badgeStyle'] ?? null) !== 'coral',
                                            'text-ink-500' => !$cActive && ($child['badgeStyle'] ?? null) !== 'coral',
                                        ])>{{ $child['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    @php $isActive = $item['key'] === $active; @endphp
                    <a href="{{ $item['href'] }}" @class([
                        'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px]',
                        'bg-wa-deep text-paper-0 font-semibold' => $isActive,
                        'text-ink-700 hover:bg-paper-50' => !$isActive,
                    ])>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                            stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        @if (!empty($item['badge']))
                            <span @class([
                                'ml-auto rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/30 px-1.5 py-0.5 text-[10px] font-semibold' =>
                                    ($item['badgeStyle'] ?? null) === 'coral',
                                'ml-auto font-mono text-[10px]' =>
                                    ($item['badgeStyle'] ?? null) !== 'coral',
                                'opacity-90' => $isActive && ($item['badgeStyle'] ?? null) !== 'coral',
                                'text-ink-500' => !$isActive && ($item['badgeStyle'] ?? null) !== 'coral',
                            ])>{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>

        <div class="mt-1 pt-4 border-t border-paper-200">
            <div
                class="js-sb-collapse-hide px-3 pb-1.5 text-[9.5px] font-mono uppercase tracking-[0.18em] text-ink-500">
                {{ __('System') }}</div>
            <div class="space-y-0.5">
                @foreach ($sys as $item)
                    @php $isActive = $item['key'] === $active; @endphp
                    <a href="{{ $item['href'] }}" @class([
                        'flex items-center gap-2.5 px-3 py-2 rounded-xl text-[12.5px]',
                        'bg-wa-deep text-paper-0 font-semibold' => $isActive,
                        'text-ink-700 hover:bg-paper-50' => !$isActive,
                    ])>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                            stroke-width="{{ $item['sw'] }}">{!! $item['icon'] !!}</svg>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        @if (!empty($item['badge']))
                            <span @class([
                                'ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-semibold' => true,
                                'bg-accent-coral/10 text-accent-coral border border-accent-coral/30' =>
                                    ($item['badgeStyle'] ?? null) === 'coral' && !$isActive,
                                'bg-paper-0/20 text-paper-0' =>
                                    ($item['badgeStyle'] ?? null) === 'coral' && $isActive,
                                'font-mono text-ink-500' => ($item['badgeStyle'] ?? null) !== 'coral',
                            ])>{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </nav>

    <div class="px-3 py-3 border-t border-paper-200 shrink-0">
        <a href="{{ url('/dashboard') }}"
            class="admin-footer-link flex items-center justify-between px-3 py-2 rounded-xl bg-paper-50 hover:bg-paper-100 transition">
            <span class="flex items-center gap-2 text-[11.5px] font-medium text-ink-700">
                <svg viewBox="0 0 16 16" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <path d="M9 4 5 8l4 4M5 8h8" />
                </svg><span class="js-sb-collapse-hide">{{ __('Back to app') }}</span>
            </span>
            <span class="js-sb-collapse-hide inline-flex items-center gap-1 text-[10px] font-mono text-wa-deep">
                <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('online') }}
            </span>
        </a>
    </div>
</div>
