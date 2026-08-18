<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\SystemSetting;
use App\Support\Brand;
use App\Services\Push\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Team-Inbox Progressive Web App — a SEPARATE installable app scoped to
 * /team-inbox only, distinct from the app-wide PWA (/manifest.json).
 *
 * Agents install THIS to get a dedicated inbox icon on their phone that opens
 * straight to the shared inbox and — with push enabled — rings on new messages
 * even when the app is closed. The client just shares the /team-inbox link;
 * each agent logs in, installs, and turns on notifications.
 *
 * Endpoints:
 *   GET  /team-inbox-manifest.json        (public)  — the scoped manifest
 *   GET  /team-inbox/api/push/key         (authed)  — VAPID public key for JS
 *   POST /team-inbox/api/push/subscribe   (authed)  — store a browser push sub
 *   POST /team-inbox/api/push/unsubscribe (authed)  — drop it
 */
class TeamInboxPwaController extends Controller
{
    /**
     * GET /team-inbox-manifest.json — scoped manifest. Public so the browser
     * can fetch it during "Add to Home Screen" (no secrets in it).
     */
    public function manifest()
    {
        $brand = (string) brand_name();
        $name  = trim((string) SystemSetting::get('ti_pwa_name', '')) ?: ($brand . ' Inbox');
        $short = trim((string) SystemSetting::get('ti_pwa_short_name', '')) ?: 'Inbox';
        $theme = (string) (SystemSetting::get('ti_pwa_theme_color') ?: SystemSetting::get('pwa_theme_color', '#075E54'));
        $bg    = (string) (SystemSetting::get('ti_pwa_background_color') ?: SystemSetting::get('pwa_background_color', '#FBFAF6'));

        // Prefer REAL 192/512 PWA icons (dedicated Team-Inbox, else the app-wide
        // ones) and declare their true sizes. Only when NEITHER is configured do
        // we fall back to the brand favicon — declared sizes:'any' so the browser
        // doesn't warn "Resource size is not correct" (a favicon is rarely exactly
        // 192x192 / 512x512). The install still gets an icon either way.
        $icon192 = (string) (SystemSetting::get('ti_pwa_icon_192') ?: SystemSetting::get('pwa_icon_192') ?: '');
        $icon512 = (string) (SystemSetting::get('ti_pwa_icon_512') ?: SystemSetting::get('pwa_icon_512') ?: '');
        $icons = [];
        if ($icon192 !== '') $icons[] = ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'];
        if ($icon512 !== '') $icons[] = ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
        if (empty($icons)) {
            $fav = (string) (Brand::faviconUrl() ?: '');
            if ($fav !== '') {
                $ext  = strtolower((string) pathinfo((string) parse_url($fav, PHP_URL_PATH), PATHINFO_EXTENSION));
                $type = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'ico' ? 'image/x-icon' : 'image/png');
                $icons[] = ['src' => $fav, 'sizes' => 'any', 'type' => $type, 'purpose' => 'any'];
            }
        }

        return response()->json([
            'name'             => $name,
            'short_name'       => mb_substr($short, 0, 12),
            'description'      => (string) (SystemSetting::get('ti_pwa_description') ?: 'Your shared team inbox — chat with customers on the go.'),
            // Absolute URLs (honour a sub-folder deploy via url()). scope pins the
            // installed app to /team-inbox so it opens straight to the inbox and
            // out-of-scope links open in the normal browser.
            'start_url'        => url('/team-inbox'),
            'scope'            => url('/team-inbox'),
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'theme_color'      => $theme,
            'background_color' => $bg,
            'icons'            => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** GET /team-inbox/api/push/key — the VAPID public key the browser needs to subscribe. */
    public function pushKey(WebPushService $push)
    {
        return response()->json([
            'enabled'   => $push->isConfigured(),
            'publicKey' => $push->publicKey(),
        ]);
    }

    /**
     * POST /team-inbox/api/push/subscribe — persist a browser PushSubscription
     * for the signed-in agent + this workspace. Idempotent on the endpoint.
     */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'  => ['required', 'string', 'max:1024'],
            'keys'      => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
        ]);

        $user     = Auth::user();
        $endpoint = $data['endpoint'];
        // Key on a hash of the endpoint (endpoints can exceed MySQL's unique-index
        // length limit, so the raw endpoint is stored as TEXT, un-indexed). The
        // model auto-fills endpoint_hash from endpoint on save.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id'      => $user->id,
                'workspace_id' => $user->current_workspace_id,
                'endpoint'     => $endpoint,
                'p256dh'       => $data['keys']['p256dh'],
                'auth'         => $data['keys']['auth'],
                'ua'           => mb_substr((string) $request->userAgent(), 0, 255),
                'channel'      => 'team-inbox',
            ]
        );

        return response()->json(['ok' => true]);
    }

    /** POST /team-inbox/api/push/unsubscribe — drop a subscription by endpoint. */
    public function unsubscribe(Request $request)
    {
        $endpoint = (string) $request->input('endpoint', '');
        if ($endpoint !== '') {
            PushSubscription::query()
                ->where('endpoint_hash', hash('sha256', $endpoint))
                ->where('user_id', Auth::id())
                ->delete();
        }
        return response()->json(['ok' => true]);
    }
}
