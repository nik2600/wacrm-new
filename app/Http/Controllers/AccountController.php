<?php

namespace App\Http\Controllers;

use App\Helpers\NotificationHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * /account — the signed-in user's own profile page.
 *
 * For now this only handles "Profile settings" (name / email / phone /
 * country / timezone). Other panes on the page (notifications, security,
 * delete account, billing) are still wired to the static prototype and
 * land here later.
 */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user()->fresh();

        // Wallet ledger — paginated 10/page to match the rest of the
        // app's table-with-Prev/Next pattern. The `wallet_page` query
        // param keeps it from clashing with any other paginator on
        // the same /account page.
        $walletLedger = \App\Models\WalletTransaction::query()
            ->forUser($user->id)
            ->credit()
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'wallet_page')
            ->appends(['tab' => $request->query('tab', 'wallet')]);

        $referrals = \App\Models\Referral::query()
            ->forReferrer($user->id)
            ->with('referred')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        $totalEarnedFromReferrals = \App\Models\Referral::query()
            ->forReferrer($user->id)
            ->sum('credits_awarded');

        $creditsPerMessage = max(1, (int) \App\Models\SystemSetting::get('credits_per_message', 1));
        $signupReward      = max(0, (int) \App\Models\SystemSetting::get('referral_signup_credits', 100));
        $creditsPerCurrencyMinor = (float) \App\Models\SystemSetting::get('credits_per_currency_minor', 0.1);

        $referralUrl = url('/register?ref=' . urlencode($user->referral_code ?? ''));

        $creditPackages = \App\Models\CreditPackage::query()->active()->ordered()->get();

        // Add-ons — à-la-carte feature packs. Like credit bundles, they only
        // appear ONCE the workspace is on an active (paid, unexpired) plan;
        // buying one grants its features on top of the current plan.
        $addons = \App\Models\Package::query()->addons()->where('status', 1)
            ->orderBy('sort_order')->orderBy('plan_amount')->get();
        $ws  = $user->currentWorkspace;
        $bp  = $ws?->billingPackage();
        $hasActivePlan = (bool) ($ws && $bp && !$bp->free && !$ws->planExpired());

        // Add-ons the workspace has ACTUALLY purchased (active + unexpired), so
        // the tab shows "Your add-ons" — not just the buy list. ownedCounts lets
        // the card show "Active" for feature packs and "owned ×N" for stackable
        // quantity packs (e.g. +1 number bought twice).
        $myAddons = ($ws)
            ? \App\Models\WorkspaceAddon::query()->active()->where('workspace_id', $ws->id)
                ->with('package')->orderByDesc('id')->get()
            : collect();
        $ownedCounts = $myAddons->groupBy('package_id')->map->count();

        // Real orders for the /account?tab=orders pane. Scoped to the
        // user_id (each workspace owner sees their own purchase
        // history). Lifetime totals computed off the SAME query so
        // the headline number matches the table below.
        $orders = \App\Models\Order::query()
            ->where('user_id', $user->id)
            ->with('package')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
        // Sum every paid order's total_amount AFTER converting each row
        // to the current workspace currency, so the lifetime number is
        // meaningful even when orders span multiple currencies (e.g. user
        // signed up paying USD, later switched workspace currency to INR).
        $targetCode = optional($user->currentWorkspace)->currency
            ?: (string) \App\Models\SystemSetting::get('default_currency', 'USD');
        $ordersLifetimeAmount = \App\Models\Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->get(['total_amount', 'currency'])
            ->sum(fn ($o) => \App\Support\FormatSettings::convert(
                (float) $o->total_amount,
                (string) ($o->currency ?: $targetCode),
                $targetCode,
            ));

        // Support tab — full ticket history for the signed-in user.
        // Same rows the /support page shows, just without the limit so
        // operators can browse everything they ever opened. Counts
        // surface in the segmented control above the list.
        $supportTickets = \App\Models\SupportTicket::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();
        $supportCounts  = [
            'open'     => $supportTickets->where('status', '!=', 'resolved')->count(),
            'resolved' => $supportTickets->where('status', 'resolved')->count(),
            'all'      => $supportTickets->count(),
        ];

        return view('user.account.index', [
            'authUser'                 => $user,
            'walletLedger'             => $walletLedger,
            'referrals'                => $referrals,
            'totalEarnedFromReferrals' => $totalEarnedFromReferrals,
            'creditsPerMessage'        => $creditsPerMessage,
            'signupReward'             => $signupReward,
            'creditsPerCurrencyMinor'  => $creditsPerCurrencyMinor,
            'referralUrl'              => $referralUrl,
            'creditPackages'           => $creditPackages,
            'addons'                   => $addons,
            'myAddons'                 => $myAddons,
            'ownedCounts'              => $ownedCounts,
            'hasActivePlan'            => $hasActivePlan,
            'orders'                   => $orders,
            'ordersLifetimeAmount'     => (float) $ordersLifetimeAmount,
            'supportTickets'           => $supportTickets,
            'supportCounts'            => $supportCounts,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'email'        => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile'       => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'timezone'     => ['nullable', 'string', 'max:64'],
        ]);

        // Sync the user row.
        $user->fill([
            'name'         => $data['name'],
            // display_name now has a real column (was validated but silently
            // dropped before). Blank → null (falls back to first name on read).
            'display_name' => trim((string) ($data['display_name'] ?? '')) ?: null,
            'email'        => $data['email'],
            'mobile'       => $data['mobile']       ?? null,
            'country_code' => $data['country_code'] ?? null,
        ])->save();

        // Display-name + timezone live on the active workspace (per-
        // workspace personalisation), and only the owner can rewrite
        // the workspace timezone — for non-owners we silently skip it.
        $ws = $user->current_workspace;
        if ($ws && (int) $ws->owner_user_id === (int) $user->id) {
            $ws->forceFill([
                'timezone' => $data['timezone'] ?? $ws->timezone,
            ])->save();
        }

        NotificationHelper::toUser(
            $user->id,
            'Profile updated',
            'Your profile details were saved.',
            ['category' => 'system', 'severity' => 'success']
        );

        return back()->with('status', 'Profile saved.');
    }

    /**
     * Generate (or rotate) the user's Google Sheets add-on API key.
     * Token is shown ONCE in the flash bag — we only store a hash so
     * we can't reveal it again. Rotation invalidates the old token.
     */
    public function generateSheetsKey(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $token = 'wsn_live_' . bin2hex(random_bytes(16));
        $user->forceFill([
            'sheets_api_key_hash'        => hash('sha256', $token),
            'sheets_api_key_suffix'      => substr($token, -8),
            'sheets_api_key_created_at'  => now(),
            'sheets_api_key_last_used_at'=> null,
        ])->save();

        return back()->with('sheets_key_once', $token)
            ->with('status', 'New Sheets API key generated. Copy it now — it won\'t be shown again.');
    }

    public function revokeSheetsKey(): RedirectResponse
    {
        $user = Auth::user();
        $user->forceFill([
            'sheets_api_key_hash'        => null,
            'sheets_api_key_suffix'      => null,
            'sheets_api_key_created_at'  => null,
            'sheets_api_key_last_used_at'=> null,
        ])->save();

        return back()->with('status', 'Sheets API key revoked. The add-on will stop working until you generate a new one.');
    }

    /**
     * /sheets-addon — the marketplace add-on setup landing page.
     * Walks the user through: install add-on, paste API key, sync.
     */
    public function sheetsAddon(): View
    {
        $user = Auth::user();
        $wsId = $user->current_workspace_id;
        $shops = $wsId
            ? \App\Models\WaStorefront::where('workspace_id', $wsId)->orderByDesc('id')->get()
            : collect();

        // Workspace-wide product count — surfaced on the page so the
        // user can verify their sheet sync landed in the right place.
        $productCount = $wsId ? \App\Models\WaProduct::where('workspace_id', $wsId)->count() : 0;

        // Recent products — last 5 created in this workspace. Useful
        // signal: "yes, my sync just landed" after closing the add-on.
        $recentProducts = $wsId
            ? \App\Models\WaProduct::where('workspace_id', $wsId)
                ->orderByDesc('created_at')->limit(5)->get(['id', 'name', 'price_minor', 'currency_code', 'image_url', 'created_at'])
            : collect();

        // Pre-load the Apps Script source files so the page can show
        // a "Copy"/"View" button next to each. Until the marketplace
        // listing is live, the user uploads these to script.google.com
        // manually.
        $addonDir = base_path('google-sheets-addon');
        $files = [
            'appsscript.json' => 'Manifest — OAuth scopes + editor add-on registration',
            'Code.gs'         => 'Server-side Apps Script — menu, sheet I/O, API client',
            'Dialog.html'     => 'Multi-step wizard modal (products → config → published)',
            'Settings.html'   => 'API-key paste/rotate/revoke modal',
            'Help.html'       => 'In-add-on help screen',
        ];
        $fileMeta = [];
        foreach ($files as $name => $desc) {
            $path = $addonDir . DIRECTORY_SEPARATOR . $name;
            $fileMeta[$name] = [
                'desc'    => $desc,
                'exists'  => file_exists($path),
                'size'    => file_exists($path) ? filesize($path) : 0,
                // The path-to-replace pattern lets us swap WADESK_BASE
                // in the served Code.gs to the user's actual origin so
                // the file works out of the box on their LAN.
                'preview' => file_exists($path) ? substr(file_get_contents($path), 0, 220) : '',
            ];
        }

        return view('user.integrations.sheets-addon', [
            'user'  => $user,
            'shops' => $shops,
            'productCount'   => $productCount,
            'recentProducts' => $recentProducts,
            'fileMeta' => $fileMeta,
            'marketplaceUrl' => config('services.sheets_addon.marketplace_url',
                'https://workspace.google.com/marketplace/'),
        ]);
    }

    /**
     * Serve a single Apps Script source file for download. We dynamically
     * swap the WADESK_BASE constant in Code.gs to the user's current
     * origin so the downloaded file works out of the box when uploaded
     * to script.google.com.
     */
    public function sheetsAddonFile(Request $request, string $file)
    {
        $allowed = ['Code.gs', 'Dialog.html', 'Settings.html', 'Help.html', 'appsscript.json', 'README.md'];
        if (!in_array($file, $allowed, true)) {
            abort(404);
        }
        $path = base_path('google-sheets-addon') . DIRECTORY_SEPARATOR . $file;
        if (!file_exists($path)) {
            abort(404);
        }
        $contents = file_get_contents($path);

        // Rewrite the WADESK_BASE constant in Code.gs so downloads
        // come pre-configured for THIS WaDesk deployment. The user
        // won't have to hand-edit the URL after uploading.
        if ($file === 'Code.gs') {
            $origin = $request->getSchemeAndHttpHost();
            $contents = preg_replace(
                "/const\s+WADESK_BASE\s*=\s*'[^']*';/",
                "const WADESK_BASE = '" . addslashes($origin) . "';",
                $contents,
                1
            );
        }

        // Plain-text MIME so the browser doesn't try to render HTML.
        $mime = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'json'    => 'application/json',
            'html'    => 'text/html',
            'gs'      => 'text/javascript',
            'md'      => 'text/markdown',
            default   => 'text/plain',
        };

        return response($contents, 200, [
            'Content-Type'        => $mime . '; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /account/branding — workspace footer override. Gated by the
     * remove_branding plan feature. Empty input is meaningful (= "no
     * footer") and stored as empty string; null/missing falls back to
     * the platform default.
     */
    public function updateBranding(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ws   = $user?->currentWorkspace;
        if (!$ws) return back()->withErrors(['branding_footer' => 'No workspace.']);

        // Plan gate — throws PlanLimitReachedException on locked plans.
        \App\Services\PlanLimitGuard::feature($ws, 'remove_branding');

        $data = $request->validate([
            'branding_footer' => ['nullable', 'string', 'max:60'],
        ]);

        $ws->update([
            // Empty string = explicit "no footer" (allowed on premium).
            // NULL = fall back to platform default. Treat blank input
            // as "" so the BrandingFooterService can return null and
            // the operator sees their intent respected.
            'branding_footer' => $data['branding_footer'] !== null
                ? trim((string) $data['branding_footer'])
                : '',
        ]);

        \App\Services\BrandingFooterService::flushCache();
        // Tell Node to drop its cached settings for this workspace's
        // devices so the new footer applies on the very next send.
        \App\Services\NodeCacheBuster::bustWorkspace((int) $ws->id);

        return back()->with('branding_status', 'Footer updated — applies on the next send.');
    }

    /**
     * Conversation translation — the team's working language (what inbound
     * messages get translated INTO and what agents type in) + the inbox
     * auto-translate master toggle. Gated on the access_translation plan
     * feature. When on, inbound customer messages are auto-translated for the
     * agent and operator replies are auto-translated into the customer's
     * language across the team inbox, AI agent, and chatbot widget.
     */
    public function updateTranslation(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ws   = $user?->currentWorkspace;
        if (!$ws) return back()->withErrors(['default_language' => 'No workspace.']);

        // Plan gate — throws PlanLimitReachedException on locked plans.
        \App\Services\PlanLimitGuard::feature($ws, 'access_translation');

        $data = $request->validate([
            'default_language' => ['nullable', 'string', 'max:12'],
            'inbox_translate'  => ['nullable', 'boolean'],
        ]);

        $ws->update([
            'default_language' => $data['default_language']
                ? strtolower(trim((string) $data['default_language']))
                : 'en',
            'inbox_translate'  => (bool) ($data['inbox_translate'] ?? false),
        ]);

        return back()->with('translation_status', 'Translation settings saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', \App\Support\PasswordPolicy::rule()],
        ]);

        $user = Auth::user();
        $user->forceFill([
            'password'              => Hash::make($request->input('password')),
            'password_changed_at'   => now(),
            'force_password_change' => false,
            // Rotate remember_token so any previously issued remember-me cookie
            // stops validating after the change.
            'remember_token'        => Str::random(60),
        ])->save();

        // Evict every OTHER active session and revoke API (Sanctum) tokens so a
        // stolen cookie / Bearer token can't survive the password change. The
        // caller's CURRENT session is preserved (filtered out below) so they
        // aren't bounced to /login for changing their own password.
        try { $user->tokens()->delete(); } catch (\Throwable $e) {}
        try {
            $currentSessionId = $request->session()->getId();
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        } catch (\Throwable $e) {}

        NotificationHelper::toUser(
            $user->id,
            'Password changed',
            'Your password was updated. If this wasn\'t you, reset immediately.',
            ['category' => 'system', 'severity' => 'warning', 'is_urgent' => true]
        );

        return redirect()->route('user.account', ['tab' => 'password'])
            ->with('password_status', 'Password updated.');
    }

    /**
     * Upload a profile photo. Accepts an image up to 2MB, stores it to
     * public/storage/avatars/{user_id}_{uniq}.{ext}, and stamps the
     * relative path on users.avatar_path. Returns JSON so the inline
     * preview swaps without a page reload.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $user = Auth::user();
        $file = $request->file('photo');
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $relativeDir  = 'avatars';
        $filename     = $user->id . '_' . uniqid() . '.' . $ext;

        // Store on the active media disk (cloud when enabled, else the local
        // `public` disk — identical result when cloud is OFF). `php artisan
        // storage:link` must be set up for the local case.
        $relativePath = $file->storeAs($relativeDir, $filename, media_disk());

        // Best-effort cleanup of the previous avatar so we don't leak
        // disk over time. Only stored-file paths live on the disk — a social
        // sign-in stores a full http(s) URL we must never try to delete.
        if ($user->avatar_path
            && ! \Illuminate\Support\Str::startsWith($user->avatar_path, ['http://', 'https://'])) {
            $old = ltrim($user->avatar_path, '/');
            try {
                if (media_storage()->exists($old)) media_storage()->delete($old);
            } catch (\Throwable $e) { /* best-effort cleanup */ }
        }

        $user->forceFill(['avatar_path' => $relativePath])->save();

        return response()->json([
            'ok'  => true,
            'url' => $user->fresh()->avatar_url,
        ]);
    }

    /** Remove the photo — file deleted, column cleared. */
    public function removePhoto(): RedirectResponse
    {
        $user = Auth::user();
        if ($user->avatar_path) {
            // Only stored-file paths live on the disk — a social sign-in
            // stores a full http(s) URL we must never try to delete.
            if (! \Illuminate\Support\Str::startsWith($user->avatar_path, ['http://', 'https://'])) {
                $rel = ltrim($user->avatar_path, '/');
                try {
                    if (media_storage()->exists($rel)) media_storage()->delete($rel);
                } catch (\Throwable $e) { /* best-effort cleanup */ }
            }
            $user->forceFill(['avatar_path' => null])->save();
        }
        return redirect()->route('user.account', ['tab' => 'profile'])->with('status', 'Photo removed.');
    }

    /**
     * Soft-delete the user's account.
     *
     * Safety rails:
     *  - User must type "DELETE my account" exactly (case-sensitive,
     *    matches the confirmation phrase rendered in the form).
     *  - Cannot delete if the user owns a workspace with more than one
     *    member — they have to transfer or empty it first.
     *
     * Soft-delete + PII scrub: name → "Deleted user", email replaced
     * with `deleted-{id}-{rand}@deleted.local` (still unique), mobile
     * cleared, avatar file removed.
     */
    public function destroyAccount(Request $request)
    {
        $request->validate([
            'confirmation' => ['required', 'string'],
        ]);
        if (trim((string) $request->input('confirmation')) !== 'DELETE my account') {
            return response()->json(['ok' => false, 'error' => 'confirmation_mismatch', 'message' => 'Type "DELETE my account" exactly to confirm.'], 422);
        }

        $user = Auth::user();

        // Guard: workspaces this user owns + has > 1 member can't be auto-purged.
        $blockingWs = \App\Models\Workspace::query()
            ->where('owner_user_id', $user->id)
            ->withCount('members')
            ->get()
            ->filter(fn ($w) => ($w->members_count ?? 0) > 1)
            ->values();
        if ($blockingWs->isNotEmpty()) {
            return response()->json([
                'ok' => false, 'error' => 'owner_of_active_workspaces',
                'message' => 'You own ' . $blockingWs->count() . ' workspace(s) with other members. Transfer ownership or remove members first.',
                'workspaces' => $blockingWs->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->all(),
            ], 422);
        }

        // Scrub PII and soft-delete. Only stored-file paths live on the disk —
        // a social sign-in stores a full http(s) URL we must never try to delete.
        if ($user->avatar_path
            && ! \Illuminate\Support\Str::startsWith($user->avatar_path, ['http://', 'https://'])) {
            $rel = ltrim($user->avatar_path, '/');
            try {
                if (media_storage()->exists($rel)) media_storage()->delete($rel);
            } catch (\Throwable $e) { /* best-effort cleanup */ }
        }
        $user->forceFill([
            'name'         => 'Deleted user',
            'email'        => 'deleted-' . $user->id . '-' . substr(md5(uniqid('', true)), 0, 8) . '@deleted.local',
            'mobile'       => null,
            'country_code' => null,
            'avatar_path'  => null,
        ])->save();
        $user->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => route('login') . '?account_deleted=1']);
    }
}
