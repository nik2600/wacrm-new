<?php

namespace App\Support;

/**
 * Per-user "Quick access" dashboard shortcuts.
 *
 * The user pins up to 10 tiles (5×2 grid). Each saved entry is either a
 * catalog page key (`['key' => 'contacts']`) or a custom link
 * (`['label' => '…', 'url' => 'https://…']`). Stored on users.quick_access.
 */
class QuickAccess
{
    public const MAX = 10;

    /** Generic icon for custom links (16×16 viewBox path content). */
    private const CUSTOM_ICON = '<path d="M6.5 9.5l3-3M7 4.5l1-1a2.5 2.5 0 0 1 3.5 3.5l-1 1M9 11.5l-1 1A2.5 2.5 0 0 1 4.5 9l1-1"/>';

    /** Pickable in-app pages: key => [label, desc, url, icon]. */
    public const CATALOG = [
        'inbox'        => ['label' => 'Unified Inbox', 'desc' => 'One shared inbox for WhatsApp + Instagram',    'url' => '/team-inbox',       'icon' => '<path d="M2.5 5A2.5 2.5 0 0 1 5 2.5h6A2.5 2.5 0 0 1 13.5 5v3A2.5 2.5 0 0 1 11 10.5H6.5l-3 2.5v-2.5H5A2.5 2.5 0 0 1 2.5 8z"/>'],
        'contacts'     => ['label' => 'Contacts',      'desc' => 'Your saved audience and their details',        'url' => '/contacts',         'icon' => '<circle cx="6" cy="6" r="2.4"/><path d="M2 13c0-2.2 1.8-3.5 4-3.5S10 10.8 10 13"/><path d="M11 5.5a2 2 0 0 1 0 4M13.5 13c0-1.6-.9-2.7-2.2-3.2"/>'],
        'campaigns'    => ['label' => 'Campaigns',     'desc' => 'Bulk WhatsApp campaigns to your lists',        'url' => '/wa-campaigns',     'icon' => '<path d="M2 6.5l9-3.5v9l-9-3.5z"/><path d="M4 8v3.5"/>'],
        'broadcasts'   => ['label' => 'Broadcasts',    'desc' => 'One-off blasts with delivery tracking',        'url' => '/broadcasts',       'icon' => '<circle cx="8" cy="8" r="1.5"/><path d="M4.5 4.5a5 5 0 0 0 0 7M11.5 4.5a5 5 0 0 1 0 7"/>'],
        'templates'    => ['label' => 'Templates',     'desc' => 'Approved message templates for sending',       'url' => '/templates',        'icon' => '<rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/><path d="M2.5 6h11M6 6v7.5"/>'],
        'flows'        => ['label' => 'Flows',         'desc' => 'Automated chatbot journeys and replies',       'url' => '/flows',            'icon' => '<rect x="2" y="2.5" width="4" height="3" rx="1"/><rect x="10" y="10.5" width="4" height="3" rx="1"/><path d="M4 5.5v3a3 3 0 0 0 3 3h3"/>'],
        'scheduled'    => ['label' => 'Scheduled',     'desc' => 'Messages queued to send later',               'url' => '/scheduled',        'icon' => '<circle cx="8" cy="8.5" r="5"/><path d="M8 5.5v3l2 1.5M8 1.5v1"/>'],
        'catalog'      => ['label' => 'Catalog',       'desc' => 'Your product catalog for WhatsApp',            'url' => '/catalog',          'icon' => '<rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/><path d="M5 5.5h6M5 8h6M5 10.5h3"/>'],
        'store'        => ['label' => 'Store',         'desc' => 'Your storefront, orders and checkout',         'url' => '/store',            'icon' => '<path d="M3 6h10l-1 7H4z"/><path d="M5.5 6a2.5 2.5 0 0 1 5 0"/>'],
        'deals'        => ['label' => 'Sales Pipeline','desc' => 'Track deals through your sales stages',         'url' => '/deals',            'icon' => '<path d="M2.5 13V9M6 13V5M9.5 13V7M13 13V3"/>'],
        'analytics'    => ['label' => 'Analytics',     'desc' => 'Delivery, reply and engagement reports',       'url' => '/analytics',        'icon' => '<path d="M2.5 13.5h11"/><path d="M4.5 11V7M7.5 11V4M10.5 11V8"/>'],
        'auto-reply'   => ['label' => 'Auto-reply',    'desc' => 'Instant keyword-triggered replies',            'url' => '/auto-reply',       'icon' => '<path d="M2.5 4.5h11v6h-7l-3 2.5z"/><path d="M5.5 7.5h5"/>'],
        'devices'      => ['label' => 'Devices',       'desc' => 'Connect and manage your numbers',              'url' => '/devices',          'icon' => '<rect x="4.5" y="2" width="7" height="12" rx="1.5"/><path d="M7 12.5h2"/>'],
        'chatbots'     => ['label' => 'Chat Widgets',  'desc' => 'Website chat widgets for your site',           'url' => '/chatbot-widgets',  'icon' => '<rect x="2.5" y="3" width="11" height="8" rx="2"/><path d="M5 11.5l1.5-1.5"/><circle cx="6" cy="7" r=".6"/><circle cx="10" cy="7" r=".6"/>'],
        'ai'           => ['label' => 'AI Assistants', 'desc' => 'AI agents that answer for you',                'url' => '/ai-assistants',    'icon' => '<path d="M8 2.5l1.4 3 3.1.4-2.3 2.1.6 3-2.8-1.5L5.2 11l.6-3-2.3-2.1 3.1-.4z"/>'],
        'appointments' => ['label' => 'Appointments',  'desc' => 'Bookings and calendar scheduling',             'url' => '/appointments',     'icon' => '<rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5 2v3M11 2v3"/>'],
        'webhooks'     => ['label' => 'Webhooks',      'desc' => 'Push events to your own systems',              'url' => '/webhooks',         'icon' => '<circle cx="5" cy="5" r="2"/><path d="M6.5 6.5L11 11"/><circle cx="11.5" cy="11.5" r="2"/>'],
        'history'      => ['label' => 'Message Log',   'desc' => 'Full log of every message sent',               'url' => '/message-history',  'icon' => '<circle cx="8" cy="8" r="5.5"/><path d="M8 5v3l2 1"/>'],
        'account'      => ['label' => 'Account',       'desc' => 'Profile, plan and workspace settings',         'url' => '/account',          'icon' => '<circle cx="8" cy="6" r="2.6"/><path d="M3 13.5c0-2.6 2.2-4 5-4s5 1.4 5 4"/>'],
        'more'         => ['label' => 'More apps',     'desc' => 'Browse every app and feature',                 'url' => '/more',             'icon' => '<circle cx="4" cy="4" r="1.3"/><circle cx="8" cy="4" r="1.3"/><circle cx="12" cy="4" r="1.3"/><circle cx="4" cy="8" r="1.3"/><circle cx="8" cy="8" r="1.3"/><circle cx="12" cy="8" r="1.3"/>'],
    ];

    /** Default tiles for a user who hasn't customised yet. */
    public static function defaultEntries(): array
    {
        return array_map(fn ($k) => ['key' => $k], [
            'inbox', 'contacts', 'campaigns', 'broadcasts', 'templates',
            'flows', 'scheduled', 'catalog', 'deals', 'analytics',
        ]);
    }

    /** Catalog for the editor modal: [key => ['label','url','icon']]. */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::CATALOG as $k => $c) {
            $out[$k] = ['label' => $c['label'], 'url' => url($c['url']), 'icon' => $c['icon']];
        }
        return $out;
    }

    /** Resolved tiles for a user — each ['key','label','url','icon','custom']. */
    public static function forUser($user): array
    {
        $saved = is_array($user->quick_access ?? null) && !empty($user->quick_access)
            ? $user->quick_access
            : self::defaultEntries();

        $out = [];
        foreach ($saved as $e) {
            if (!is_array($e)) {
                continue;
            }
            if (!empty($e['key']) && isset(self::CATALOG[$e['key']])) {
                $c = self::CATALOG[$e['key']];
                $out[] = ['key' => $e['key'], 'label' => $c['label'], 'desc' => $c['desc'] ?? '', 'url' => url($c['url']), 'icon' => $c['icon'], 'custom' => false];
            } elseif (!empty($e['label']) && !empty($e['url'])) {
                $out[] = ['key' => null, 'label' => (string) $e['label'], 'desc' => 'Custom shortcut', 'url' => (string) $e['url'], 'icon' => self::CUSTOM_ICON, 'custom' => true];
            }
            if (count($out) >= self::MAX) {
                break;
            }
        }
        return $out;
    }

    /** Clean a posted list down to storable entries (cap MAX). */
    public static function sanitize(array $items): array
    {
        $out = [];
        foreach ($items as $e) {
            if (!is_array($e)) {
                continue;
            }
            $key = $e['key'] ?? null;
            if ($key && isset(self::CATALOG[$key])) {
                $out[] = ['key' => $key];
            } elseif (!empty($e['label']) && !empty($e['url'])) {
                $url = trim((string) $e['url']);
                // Allow internal paths or http(s) URLs only.
                if (str_starts_with($url, '/') || preg_match('#^https?://#i', $url)) {
                    $out[] = ['label' => mb_substr(trim((string) $e['label']), 0, 40), 'url' => mb_substr($url, 0, 255)];
                }
            }
            if (count($out) >= self::MAX) {
                break;
            }
        }
        return $out;
    }
}
