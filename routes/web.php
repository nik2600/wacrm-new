<?php

use App\Http\Controllers\AuthPagesController;
use App\Http\Controllers\BroadcastsController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CheckoutPagesController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\MetaAdsController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\TemplatesController;
use App\Http\Controllers\UserPagesController;
use App\Http\Controllers\WaCampaignsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (no app shell) — public, only for guests
|--------------------------------------------------------------------------
|
| Anyone can hit these. Already-authenticated users get bounced to the
| dashboard so the login/register pages aren't viewable post-auth.
*/
Route::middleware(['guest', \App\Http\Middleware\NoStoreResponse::class])->group(function () {
    Route::get('/login',                     [AuthPagesController::class, 'showLogin'])->name('login');
    Route::post('/login',                    [AuthPagesController::class, 'login']);

    Route::get('/register',                  [AuthPagesController::class, 'showRegister'])->name('register');
    Route::post('/register',                 [AuthPagesController::class, 'register']);

    // Registration WhatsApp OTP verify step (only reachable mid-signup; the
    // pending account lives in the session until the code is confirmed).
    Route::get('/register/verify',           [AuthPagesController::class, 'showRegisterOtp'])->name('register.verify');
    Route::post('/register/verify',          [AuthPagesController::class, 'verifyRegisterOtp'])->middleware('throttle:10,1');
    Route::post('/register/verify/resend',   [AuthPagesController::class, 'resendRegisterOtp'])
        ->middleware('throttle:5,1')->name('register.verify.resend');

    // Social sign-in (Google / Facebook) — SDK-free OAuth.
    Route::get('/auth/{provider}/redirect',  [\App\Http\Controllers\SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook'])->name('social.redirect');
    Route::get('/auth/{provider}/callback',  [\App\Http\Controllers\SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook'])->name('social.callback');

    Route::get('/forgot-password',           [AuthPagesController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password',          [AuthPagesController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}',    [AuthPagesController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password',           [AuthPagesController::class, 'resetPassword'])->name('password.update');
});

// Live CSRF token — matches the CURRENT session cookie. resources/js/csrf-guard.js
// fetches this on page load + bfcache restore and rewrites a cached/stale form
// _token (and the csrf meta) with it, so a login page served from browser/CDN
// cache still POSTs a valid token instead of 419-ing "session expired". Outside
// the guest group so it works for both signed-out and signed-in users; no-store
// so the token itself is never cached.
Route::get('/csrf-token', function () {
    return response()
        ->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
        ->header('Pragma', 'no-cache');
})->name('csrf.token');

// Email verification — the verify link is a signed URL so it works even
// when the user is not logged in (clicks from email client). The resend
// endpoint requires an authenticated session.
Route::get('/verify-email/{id}/{hash}',
    [\App\Http\Controllers\EmailVerificationController::class, 'verify']
)->whereNumber('id')->name('verification.verify');

Route::post('/email/verify/resend',
    [\App\Http\Controllers\EmailVerificationController::class, 'resend']
)->middleware('auth')->name('verification.send');

// Lock pages for suspended / verification-required users. Both render
// a standalone (no app shell) page with a logout link.
Route::middleware('auth')->group(function () {
    Route::view('/account/suspended', 'auth.suspended')->name('account.suspended');
    Route::view('/account/verify-email', 'auth.verify-email')->name('account.verify-email');
});

// POST logout per Laravel convention; GET is also accepted so any
// stray <a href="/logout"> or bookmark works without throwing a 405.
// CSRF risk on GET logout is benign (attacker can only sign you out),
// so we trade it for simpler UX. Both methods route to the same handler.
Route::match(['get', 'post'], '/logout', [AuthPagesController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Anyone hitting an unauthenticated route gets bounced to /login by the
// `auth` middleware. Anyone hitting `/` while not logged in falls into
// the `auth` group below and likewise lands on /login. Logged-in users
// hitting /login or /register get redirected to the dashboard by the
// `guest` middleware on the auth group above. Net effect: every URL
// in this app is gated; no anonymous data leaks.

/*
|--------------------------------------------------------------------------
| Public frontend (homepage / marketing surface) — no auth required
|--------------------------------------------------------------------------
| Three public pages share the <x-layouts.frontend> Blade layout:
|   /            → frontend.home      (the landing page; ex-prototype index)
|   /features    → frontend.features  (12-card feature bento)
|   /pricing     → frontend.pricing   (public Starter/Pro/Scale cards)
|
| The legacy /pricing endpoint moved to /account/plans (auth dashboard area)
| so PricingController + pricing.index blade can keep their existing wiring
| for the in-app upgrade flow without colliding with the new public page.
*/
// Public marketing HTML — wrapped in NoStoreResponse so a mobile browser (or a
// CDN edge) never serves a STALE cached copy of the page. A cached page still
// references the PREVIOUS build's hashed CSS/JS filenames; after a deploy those
// files are gone, so the stylesheet 404s and the page renders as raw, unstyled
// text — intermittently, and worst on mobile where HTML is cached hardest and
// hard-refresh is rare. Same class of bug (and same fix) as the auth pages.
// sitemap.xml / robots.txt are left cacheable — they carry no hashed-asset refs.
Route::middleware(\App\Http\Middleware\NoStoreResponse::class)->group(function () {
    Route::get('/',          [\App\Http\Controllers\FrontendController::class, 'home'])->name('frontend.home');
    Route::get('/features',  [\App\Http\Controllers\FrontendController::class, 'features'])->name('frontend.features');
    Route::get('/pricing',   [\App\Http\Controllers\FrontendController::class, 'pricing'])->name('frontend.pricing');
    Route::get('/about',     [\App\Http\Controllers\FrontendController::class, 'about'])->name('frontend.about');
    Route::get('/contact',   [\App\Http\Controllers\FrontendController::class, 'contact'])->name('frontend.contact');
    Route::get('/blog',          [\App\Http\Controllers\FrontendController::class, 'blogIndex'])->name('frontend.blog');
    Route::get('/blog/{slug}',   [\App\Http\Controllers\FrontendController::class, 'blogShow'])->name('frontend.blog.show');
});
Route::post('/contact',  [\App\Http\Controllers\ContactController::class, 'submit'])
    ->middleware('throttle:6,1')->name('frontend.contact.submit');

// SEO sitemap/robots — safe to cache (no hashed-asset references).
Route::get('/sitemap.xml',   [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt',    [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

// Public, WaDesk-branded REST API reference (renders the live OpenAPI spec).
// Replaces the default Scramble UI so it matches our dashboard look.
Route::view('/developers/docs', 'developers.docs')->name('developers.docs');
// Anyone landing on the old Stoplight URL is sent to our branded page.
Route::redirect('/docs/api', '/developers/docs');
Route::redirect('/docs', '/developers/docs');

// Legal pages — SaaS-CRM standard set. All share <x-frontend.legal-page>.
Route::prefix('legal')->name('frontend.legal.')->middleware(\App\Http\Middleware\NoStoreResponse::class)->group(function () {
    $fc = \App\Http\Controllers\FrontendController::class;
    Route::get('/terms',           [$fc, 'terms'])->name('terms');
    Route::get('/privacy',         [$fc, 'privacy'])->name('privacy');
    Route::get('/refund',          [$fc, 'refund'])->name('refund');
    Route::get('/cookies',         [$fc, 'cookies'])->name('cookies');
    Route::get('/acceptable-use',  [$fc, 'acceptableUse'])->name('acceptable-use');
});

// PWA manifest — generated from system_settings (admin → Settings → PWA).
Route::get('/manifest.json', [\App\Http\Controllers\AdminPagesController::class, 'pwaManifest'])->name('pwa.manifest');

// Team-Inbox PWA — the SEPARATE installable app scoped to /team-inbox (agents
// install it for background push). Public manifest so "Add to Home Screen"
// can fetch it. The push subscribe/key endpoints live under team-inbox/api.
Route::get('/team-inbox-manifest.json', [\App\Http\Controllers\TeamInboxPwaController::class, 'manifest'])->name('team-inbox.manifest');

// Public storefront — host-based + slug-based fallback. Resolves any
// `<slug>.STOREFRONT_HOST` or verified custom domain. The slug-based
// fallback `/s/{slug}` and `/s/{slug}/p/{productSlug}` always works
// even on dev environments without subdomain DNS.
Route::get('/s/{slug}',                [\App\Http\Controllers\StorefrontPublicController::class, 'index'])->name('storefront.public.index');
Route::get('/s/{slug}/p/{productSlug}', [\App\Http\Controllers\StorefrontPublicController::class, 'product'])->name('storefront.public.product');
// Server-side checkout — captures the order before the WhatsApp hand-off.
// CSRF-exempt (cross-domain storefronts have no Laravel session); prices
// are re-derived server-side and the route is rate-limited.
Route::post('/s/{slug}/checkout', [\App\Http\Controllers\StorefrontPublicController::class, 'checkout'])
    ->middleware('throttle:20,1')->name('storefront.public.checkout');
// Public, tokenised order-tracking page (no auth — the token is the key).
Route::get('/s/{slug}/order/{token}', [\App\Http\Controllers\StorefrontPublicController::class, 'orderStatus'])
    ->name('storefront.public.order');
// Coupon quote + review submission (public, rate-limited, CSRF-exempt).
Route::post('/s/{slug}/coupon', [\App\Http\Controllers\StorefrontPublicController::class, 'applyCoupon'])
    ->middleware('throttle:30,1')->name('storefront.public.coupon');
Route::post('/s/{slug}/review', [\App\Http\Controllers\StorefrontPublicController::class, 'submitReview'])
    ->middleware('throttle:10,1')->name('storefront.public.review');
// Abandoned-cart beacon (S3) — schedules a recovery nudge.
Route::post('/s/{slug}/abandon', [\App\Http\Controllers\StorefrontPublicController::class, 'abandon'])
    ->middleware('throttle:30,1')->name('storefront.public.abandon');
// Razorpay webhook for storefront payment links (auth = HMAC signature).
Route::post('/webhooks/storefront-pay', [\App\Http\Controllers\StorefrontPaymentController::class, 'razorpayWebhook'])
    ->name('storefront.pay.webhook');

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/',         [CheckoutPagesController::class, 'index'])->name('index');
    Route::post('/complete',[CheckoutPagesController::class, 'complete'])->name('complete');
    Route::get('/success',  [CheckoutPagesController::class, 'success'])->name('success');
    Route::get('/failed',   [CheckoutPagesController::class, 'failed'])->name('failed');
});

/*
|--------------------------------------------------------------------------
| Public webhooks — no auth, no CSRF
|--------------------------------------------------------------------------
| The Node.js scheduler service POSTs back here when a job fires. Path
| matches what the bot ships with — D:\app\whatsapp-bot\utils\helpers.js
| hits `appDomainName + "/api/update-schedule-status"` — so the existing
| Node code talks to this URL unchanged.
| Authenticated via the NODE_WEBHOOK_TOKEN env header instead of a session.
*/
// All /api/* node-bridge routes now live in routes/api.php under the
// `api` middleware group (no session lock contention).

// WhatsApp Cloud-API calling webhook. Meta POSTs here for every
// call.connect / call.terminate / call_permission_update event.
// Authed via X-Hub-Signature-256 (HMAC of body with app_secret).
// GET handler runs Meta's verify-token handshake when the webhook
// subscription is first registered.
// Language switcher — both authenticated + guest can flip locale via
// the header dropdown. Session-only for guests; persists to users.locale
// when authenticated. CSRF-protected as a normal web POST.
Route::post('/locale',
    [\App\Http\Controllers\LocaleController::class, 'update'])
    ->middleware('web')
    ->name('locale.update');

/* ───────── Install wizard ─────────
 * Gated by EnsureInstalled middleware (registered globally) so fresh
 * installs land here automatically. Once `storage/installed` exists
 * these routes 302 back to /. EnsureInstalled also pins the session
 * cookie name + file driver when not installed — that runs before
 * StartSession so we don't need a route-level session middleware.
 */
Route::prefix('install')->name('install.')->group(function () {
    $ic = \App\Http\Controllers\InstallController::class;
    Route::get ('/',                  [$ic, 'welcome'])->name('welcome');
    Route::get ('/license',           [$ic, 'license'])->name('license');
    Route::post('/license',           [$ic, 'verifyLicense'])->name('license.verify');
    Route::get ('/requirements',      [$ic, 'requirements'])->name('requirements');
    Route::post('/requirements',      [$ic, 'checkRequirements'])->name('requirements.check');
    Route::get ('/database',          [$ic, 'database'])->name('database');
    Route::post('/database',          [$ic, 'saveDatabase'])->name('database.save');
    Route::post('/database/test',     [$ic, 'testDatabase'])->name('database.test');
    Route::get ('/application',       [$ic, 'application'])->name('application');
    Route::post('/application',       [$ic, 'saveApplication'])->name('application.save');
    Route::get ('/admin',             [$ic, 'admin'])->name('admin');
    Route::post('/admin',             [$ic, 'saveAdmin'])->name('admin.save');
    Route::get ('/node',              [$ic, 'node'])->name('node');
    Route::post('/node',              [$ic, 'saveNode'])->name('node.save');
    Route::get ('/run',               [$ic, 'run'])->name('run');
    Route::post('/execute',           [$ic, 'execute'])->name('execute');
    // The old /complete page was removed — once the install marker is written
    // the EnsureInstalled middleware bounces /install/* to the app, so the
    // progress step now redirects straight to /login when finished.
});

Route::get('/webhooks/wa-calling',
    [\App\Http\Controllers\WaCallingWebhookController::class, 'verify'])
    ->name('wa-calling.webhook.verify');
Route::post('/webhooks/wa-calling',
    [\App\Http\Controllers\WaCallingWebhookController::class, 'receive'])
    ->name('wa-calling.webhook.receive');

// (Broadcast / campaign status callbacks moved to routes/api.php)

// Meta + Twilio inbound webhook — single endpoint, content-type detects shape
Route::get('/webhooks/whatsapp/inbound',  [\App\Http\Controllers\WaWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp/inbound', [\App\Http\Controllers\WaWebhookController::class, 'receive']);


// User-generated INCOMING webhooks. Any external service POSTs (or
// GET/PUT/PATCH) here; we capture the request + optionally relay it to the
// operator's forward_url. The random 40-char token is the access control;
// CSRF is exempted in bootstrap/app.php (hooks/in/*).
Route::match(['get', 'post', 'put', 'patch'], '/hooks/in/{token}',
    [\App\Http\Controllers\IncomingWebhookController::class, 'receive'])
    ->name('hooks.incoming.receive');

// Slack slash command (/wa send <name>: <msg>) → WhatsApp. Signature-verified
// (X-Slack-Signature) inside the controller; acks in <3s, sends async.
Route::post('/webhooks/slack/command', [\App\Http\Controllers\SlackWebhookController::class, 'command'])
    ->name('slack.webhook.command');

// Trello board webhook → WhatsApp. HEAD = Trello's creation handshake (returns
// 200); POST = signed card events (X-Trello-Webhook HMAC verified inside).
Route::match(['head', 'post'], '/webhooks/trello', [\App\Http\Controllers\TrelloWebhookController::class, 'receive'])
    ->name('trello.webhook');

// LinkTracker redirect — wraps template URL buttons + body links so
// we can attribute clicks to a recipient. Public, no auth, no CSRF.
// Token is opaque + per-recipient; expiry on the row hard-blocks
// stale clicks. Bot UAs (WhatsApp link-preview crawler, FB/Google
// previewers) get redirected but NOT counted.
Route::get('/r/{token}', [\App\Http\Controllers\LinkRedirectController::class, 'go'])
    ->where('token', '[A-Za-z0-9]{8,32}')->name('link.redirect');

// WhatsApp Link Generator — public redirect. /l/{slug} bumps the
// click counter on the WaChatLink row and 302s to wa.me with the
// pre-typed message in the query string. No auth, no CSRF — short
// links are operator-customisable and meant to be public.
Route::get('/l/{slug}', [\App\Http\Controllers\WaChatLinkController::class, 'publicRedirect'])
    ->where('slug', '[a-z0-9-]{1,64}')
    ->name('wa-links.public.redirect');

// Chatbot Widget public endpoints. Token-authed via the unguessable
// `embed_token` in the URL (rotatable on the operator's side). No
// session/CSRF — the snippet runs on the operator's site, not ours.
Route::prefix('widget/{token}')->where(['token' => '[A-Za-z0-9]{16,80}'])->group(function () {
    $w = \App\Http\Controllers\ChatbotWidgetPublicController::class;
    // `embed.js` and `chat` are loaded directly (script tag / iframe
    // src), not via XHR, so they don't need CORS preflight handling.
    // NOTE: many nginx deployments serve any request ending in `.js`
    // straight off disk (`location ~* \.js$ { try_files $uri =404; }`)
    // BEFORE it ever reaches PHP — so `/widget/{token}/embed.js` returned
    // nginx's own "404 Not Found" and the embed silently failed on the
    // customer's site (while /chat, which has no extension, worked). The
    // extension-less `/embed` alias below is the one the snippet points at
    // now: it falls through to index.php like every other Laravel route,
    // so embedding works regardless of the host's static-file rules. The
    // `.js` route stays for backward-compat with already-pasted snippets
    // on correctly-configured servers.
    Route::get('/embed',        [$w, 'embedJs'])->name('widget.public.embed');
    Route::get('/embed.js',     [$w, 'embedJs'])->name('widget.public.embed_js');
    Route::get('/chat',         [$w, 'chat'])->name('widget.public.chat');
    // /api/* — XHR-called from the embedded chat iframe. CORS
    // middleware echoes the request Origin back into
    // Access-Control-Allow-Origin when the widget's allowed_domains
    // list permits it, returns 403 otherwise. OPTIONS preflight is
    // handled by the middleware too, so we explicitly accept it here.
    Route::match(['get', 'options'], '/api/config',   [$w, 'config'])
        ->middleware('widget.cors')->name('widget.public.config');
    Route::match(['post', 'options'], '/api/message', [$w, 'message'])
        ->middleware(['widget.cors', 'throttle:60,1'])->name('widget.public.message');
    Route::match(['get', 'options'], '/api/history',  [$w, 'history'])
        ->middleware(['widget.cors', 'throttle:120,1'])->name('widget.public.history');
});

// (All Node-bridge /api/* routes — baileys callback, appointments,
//  commerce, flow-node side effects, Google flow-node bridge, WABA
//  creds + AI call bridge, flow show — moved to routes/api.php.)

// ─── Google Sheets add-on API ───
// Auth: Bearer token in the Authorization header. The user generates
// the token on /account and pastes it into the add-on sidebar.
Route::prefix('api/v1/sheets-addon')->name('api.sheets.')->group(function () {
    Route::get('/health', [\App\Http\Controllers\Api\SheetsAddonController::class, 'health'])->name('health');
    Route::get('/shops',  [\App\Http\Controllers\Api\SheetsAddonController::class, 'shops'])->name('shops');
    Route::post('/sync',  [\App\Http\Controllers\Api\SheetsAddonController::class, 'sync'])->name('sync');
});

// (Inbound message webhook, settings, scheduled-active, campaigns sync,
//  keyword replies — all moved to routes/api.php.)

// Shopify webhook receiver — unauthenticated by design, HMAC-verified.
// One URL per integration (the `secret` segment is per-row), so a leaked
// secret only affects that one store.
Route::post('/shopify/webhook/{secret}',
    [\App\Http\Controllers\ShopifyController::class, 'webhook'])
    ->where('secret', '[A-Za-z0-9]{20,80}')
    ->name('shopify.webhook');

// WooCommerce webhook receiver — same pattern as Shopify. Per-row
// secret + HMAC verification inside the controller.
Route::post('/woocommerce/webhook/{secret}',
    [\App\Http\Controllers\WoocommerceController::class, 'webhook'])
    ->where('secret', '[A-Za-z0-9]{20,80}')
    ->name('woocommerce.webhook');

// Payment gateway webhook receivers — signature verified per-gateway.
Route::post('/payment/webhook/{gateway}',
    [\App\Http\Controllers\CheckoutController::class, 'webhook'])
    ->name('payment.webhook');
Route::get('/payment/callback/{gateway}',
    [\App\Http\Controllers\CheckoutController::class, 'callback'])
    ->name('payment.callback');
Route::post('/payment/callback/{gateway}',
    [\App\Http\Controllers\CheckoutController::class, 'callback']);
// Paddle built-in checkout — renders our own Paddle.js overlay for a created
// transaction so payment works without depending on the Paddle dashboard
// "default payment link". Public (the buyer is redirected here mid-checkout).
Route::get('/payment/paddle/checkout/{order}',
    [\App\Http\Controllers\CheckoutController::class, 'paddleCheckout'])
    ->whereNumber('order')->name('payment.paddle.checkout');
// /pricing is owned by FrontendController (public marketing page) at the
// top of this file. The dashboard/auth pricing page lives at /account/plans
// and is served by PricingController. CheckoutController@pricing is no
// longer routed — anything that still wants the old "checkout plan picker"
// should link directly to /checkout instead.

/*
|--------------------------------------------------------------------------
| Operator-facing (workspace) routes — auth required
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {


    // Multi-step register continuation + workspace switching live on
    // the existing AuthController so nothing else has to change.
    Route::get('/register/workspace',  [\App\Http\Controllers\AuthController::class, 'showWorkspaceStep'])->name('register.workspace');
    Route::post('/register/workspace', [\App\Http\Controllers\AuthController::class, 'storeWorkspace'])->name('register.workspace.store');
    Route::get('/register/plan',       [\App\Http\Controllers\AuthController::class, 'showPlanStep'])->name('register.plan');
    Route::post('/register/plan/skip', [\App\Http\Controllers\AuthController::class, 'skipPlanStep'])->name('register.plan.skip');
    Route::get('/register/plan/skip',  [\App\Http\Controllers\AuthController::class, 'skipPlanStep']);

    Route::post('/workspaces/{id}/switch', [\App\Http\Controllers\AuthController::class, 'switchWorkspace'])
        ->whereNumber('id')->name('workspaces.switch');

    // Cross-page AI helpers wired into the <x-compose-textarea>
    // component. Auth-only — no workspace.role gate — because the
    // "review my draft" feature should work on every page where a
    // compose textarea is rendered.
    Route::get('/ai/models',       [\App\Http\Controllers\AiHelpersController::class, 'models'])->name('ai.models');
    Route::post('/ai/review-text', [\App\Http\Controllers\AiHelpersController::class, 'reviewText'])->name('ai.review-text');

    // Shopify OAuth callback — Shopify redirects the user's browser back
    // here after they grant the app access. Auth is required (the
    // integration row needs a workspace_id) but workspace.role middleware
    // is intentionally omitted — anyone in a workspace can complete OAuth
    // for that workspace.
    Route::get('/shopify/oauth/callback',
        [\App\Http\Controllers\ShopifyController::class, 'oauthCallback'])
        ->name('shopify.oauth.callback');

    Route::get('/hubspot/oauth/callback',
        [\App\Http\Controllers\HubspotController::class, 'oauthCallback'])
        ->name('hubspot.oauth.callback');

    // Google Calendar OAuth callback — same exemption rationale as
    // Shopify/HubSpot: anyone with workspace context can complete the
    // OAuth round-trip; the controller validates state + workspace id
    // from the session before persisting tokens.
    Route::get('/appointments/oauth/google/callback',
        [\App\Http\Controllers\GoogleCalendarOAuthController::class, 'callback'])
        ->name('appointments.gcal.callback');

    // In-app "Create new workspace" page (lives inside the user shell,
    // unlike /register/workspace which is part of onboarding).
    Route::get('/workspaces/create',  [\App\Http\Controllers\WorkspacesController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces',        [\App\Http\Controllers\WorkspacesController::class, 'store'])->name('workspaces.store');

    // In-app home router. Moved off `/` (which now serves the public
    // frontend landing page) to `/home`. Agent/Viewer don't have
    // dashboard access, so bounce them straight to the team-inbox
    // (their actual home). Platform admins always land on /dashboard
    // regardless of workspace role.
    Route::get('/home', function () {
        $user = auth()->user();
        $isPlatformAdmin = false;
        try {
            $isPlatformAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        } catch (\Throwable $e) {}
        if (!$isPlatformAdmin) {
            $isPlatformAdmin = in_array($user?->role ?? null, ['admin', 'super-admin', 'super_admin', 'platform-admin'], true);
        }
        if ($isPlatformAdmin) {
            return redirect('/admin');
        }
        $role = $user?->workspaceRole();
        if (in_array($role, ['agent', 'viewer'], true)) {
            return redirect('/team-inbox');
        }
        return redirect('/dashboard');
    })->name('home');
    // Dashboard is the workspace overview — admin-tier surface (KPIs, ad
    // spend, broadcast stats). Agents/Viewers don't need it; they get
    // bounced to /team-inbox by the workspace.role middleware.
    Route::get('/dashboard', [UserPagesController::class, 'dashboard'])
        ->middleware('workspace.role:manager')->name('user.dashboard');

    // Dashboard "Quick access" tiles — save the user's pinned shortcuts.
    Route::post('/quick-access', [\App\Http\Controllers\QuickAccessController::class, 'update'])
        ->name('quick-access.update');

    // Guided product tour — mark the current user as having seen the first-run
    // tour (users.has_seen_intro) so it never auto-runs again. Called by the
    // tour JS on finish/skip. Idempotent + tiny.
    Route::post('/tour/seen', function () {
        $u = auth()->user();
        if ($u && !$u->has_seen_intro) {
            $u->forceFill(['has_seen_intro' => true])->save();
        }
        return response()->json(['ok' => true]);
    })->name('user.tour.seen');

    // In-app pricing / plan upgrade page. Moved off the public /pricing
    // URL (now owned by FrontendController) so this controller + the
    // existing pricing/index.blade.php can keep their auth-scoped
    // $packages / yearly toggle / wallet-credits behaviour without
    // colliding with the public landing pricing.
    Route::get('/account/plans', [PricingController::class, 'index'])->name('account.plans');

    // Cancel an auto-renewing subscription. Stops the gateway from charging
    // again; the current paid period keeps running until plan_ends_at.
    Route::post('/account/subscription/cancel', [PricingController::class, 'cancelSubscription'])
        ->middleware('workspace.role:manager')->name('account.subscription.cancel');

    Route::name('user.')->group(function () {
        /*
         * Shared media upload for the template send-override panel
         * (resources/js/charts/template-live-mapping.js). Campaigns, broadcasts,
         * scheduled, team-inbox and chat all reuse that panel and POST the
         * picked/dropped file here; the returned public URL is dropped into the
         * send so WhatsApp can fetch the header media. Previously the panel
         * pointed at the flow-builder's /flows/api/upload-media (admin-only,
         * prefixed) which 404'd from every other surface. This top-level route
         * is workspace-scoped at manager+, the lowest role that reaches the panel.
         */
        Route::post('/api/upload-media', [\App\Http\Controllers\FlowsController::class, 'apiUploadMedia'])
            ->middleware('workspace.role:manager')->name('api.upload-media');

        /*
         * Chat — page render plus a JSON API the page consumes. The
         * page is at /chat; everything dynamic lives under /chat/api/*.
         */
        // /chat (1-on-1 customer view) is a manager+ surface. Agents/Viewers
        // work the team queue, not raw single-device chats.
        Route::get('/chat', [ChatController::class, 'index'])
            ->middleware('workspace.role:manager')->name('chat');

        Route::prefix('chat/api')->middleware('workspace.role:manager')->name('chat.api.')->group(function () {
            Route::get('/conversations',                  [ChatController::class, 'conversations'])->name('conversations');
            Route::post('/conversations',                 [ChatController::class, 'createConversation'])->name('create');
            Route::get('/conversations/{id}',             [ChatController::class, 'show'])->whereNumber('id')->name('show');
            Route::get('/conversations/{id}/details',     [ChatController::class, 'details'])->whereNumber('id')->name('details');
            Route::post('/conversations/{id}/messages',   [ChatController::class, 'sendMessage'])->whereNumber('id')->name('send');
            Route::post('/conversations/{id}/template',   [ChatController::class, 'sendTemplate'])->whereNumber('id')->name('send-template');
            Route::post('/conversations/{id}/archive',    [ChatController::class, 'archive'])->whereNumber('id')->name('archive');
            Route::post('/conversations/{id}/unarchive',  [ChatController::class, 'unarchive'])->whereNumber('id')->name('unarchive');
            Route::post('/conversations/{id}/send-now',   [ChatController::class, 'sendNow'])->whereNumber('id')->name('send-now');
            Route::post('/conversations/{id}/retry',      [ChatController::class, 'retry'])->whereNumber('id')->name('retry');
            Route::post('/conversations/{id}/resend',     [ChatController::class, 'resend'])->whereNumber('id')->name('resend');
            Route::delete('/conversations/{id}',          [ChatController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/conversations/{id}/ai',         [ChatController::class, 'aiAssist'])->whereNumber('id')->name('ai');

            // Per-message actions: WhatsApp-style hover menu support.
            Route::get('/conversations/{c}/messages/{m}/info',     [ChatController::class, 'messageInfo'])->whereNumber('c')->whereNumber('m')->name('msg.info');
            Route::post('/conversations/{c}/messages/{m}/react',   [ChatController::class, 'messageReact'])->whereNumber('c')->whereNumber('m')->name('msg.react');
            Route::patch('/conversations/{c}/messages/{m}/pin',    [ChatController::class, 'messageTogglePin'])->whereNumber('c')->whereNumber('m')->name('msg.pin');
            Route::patch('/conversations/{c}/messages/{m}/star',   [ChatController::class, 'messageToggleStar'])->whereNumber('c')->whereNumber('m')->name('msg.star');
            Route::delete('/conversations/{c}/messages/{m}',       [ChatController::class, 'messageDelete'])->whereNumber('c')->whereNumber('m')->name('msg.delete');
            Route::post('/conversations/{c}/messages/{m}/forward', [ChatController::class, 'messageForward'])->whereNumber('c')->whereNumber('m')->name('msg.forward');

            Route::get('/templates',                      [ChatController::class, 'templates'])->name('templates');
            Route::get('/search-numbers',                 [ChatController::class, 'searchNumbers'])->name('search-numbers');
        });

        /*
         * Contacts + contact groups — single ContactsController, no resource
         * helpers (so we never auto-generate create/edit routes pointing at
         * deleted Blade views).
         */
        Route::middleware('workspace.role:manager')->group(function () {
            Route::get('/contacts',                       [ContactsController::class, 'index'])->name('contacts');
            Route::post('/contacts',                      [ContactsController::class, 'store'])->name('contacts.store');
            Route::put('/contacts/{id}',                  [ContactsController::class, 'update'])->name('contacts.update');
            Route::delete('/contacts/{id}',               [ContactsController::class, 'destroy'])->name('contacts.destroy');
            // Contact-level tags — attaching one fires the flow `tag_added` trigger.
            Route::get('/contacts/{id}/tags',             [ContactsController::class, 'contactTags'])->whereNumber('id')->name('contacts.tags');
            Route::post('/contacts/{id}/tags',            [ContactsController::class, 'attachTag'])->whereNumber('id')->name('contacts.tags.attach');
            Route::delete('/contacts/{id}/tags/{tagId}',  [ContactsController::class, 'detachTag'])->whereNumber('id')->whereNumber('tagId')->name('contacts.tags.detach');
            Route::post('/contacts/bulk-delete',          [ContactsController::class, 'bulkDelete'])->name('contacts.bulk-delete');
            // Bulk actions on the selected contacts + one-click duplicate cleanup.
            Route::post('/contacts/bulk-export',          [ContactsController::class, 'bulkExport'])->name('contacts.bulk-export');
            Route::post('/contacts/bulk-group',           [ContactsController::class, 'bulkGroup'])->name('contacts.bulk-group');
            Route::post('/contacts/bulk-tag',             [ContactsController::class, 'bulkTag'])->name('contacts.bulk-tag');
            Route::post('/contacts/dedupe',               [ContactsController::class, 'dedupe'])->name('contacts.dedupe');
            Route::post('/contacts/import',               [ContactsController::class, 'import'])->name('contacts.import');
            // #25-30 — Preview endpoint for the new import UI: returns
            // first 5 rows + auto-detected column mapping. Operator
            // confirms then re-submits the file to /contacts/import
            // along with the apply-to-all Status/Source/Group/Tags.
            Route::post('/contacts/import-preview',       [ContactsController::class, 'importPreview'])->name('contacts.import-preview');
            // #4 — downloadable sample CSV so operators know the exact
            // header row the smart-detector matches against.
            Route::get('/contacts/sample-csv',            [ContactsController::class, 'sampleCsv'])->name('contacts.sample-csv');

            Route::get('/contact-groups',                 [ContactsController::class, 'groupIndex'])->name('contact-groups');
            Route::post('/contact-groups',                [ContactsController::class, 'groupStore'])->name('contact-groups.store');
            Route::put('/contact-groups/{id}',            [ContactsController::class, 'groupUpdate'])->name('contact-groups.update');
            Route::delete('/contact-groups/{id}',         [ContactsController::class, 'groupDestroy'])->name('contact-groups.destroy');
        });

        Route::prefix('broadcasts')->middleware('workspace.role:admin')->name('broadcasts.')->group(function () {
            Route::get('/',                  [BroadcastsController::class, 'index'])->name('index');
            Route::post('/',                 [BroadcastsController::class, 'store'])->name('store');
            Route::get('/create',            [BroadcastsController::class, 'create'])->name('create');
            Route::get('/{id}',              [BroadcastsController::class, 'show'])->whereNumber('id')->name('detail');
            Route::get('/{id}/statistics',   [BroadcastsController::class, 'statistics'])->whereNumber('id')->name('statistics');
            Route::get('/{id}/live-stats',   [BroadcastsController::class, 'liveStats'])->whereNumber('id')->name('live-stats');
            Route::post('/{id}/retry-failed', [BroadcastsController::class, 'retryFailed'])->whereNumber('id')->name('retry-failed');
            Route::delete('/{id}',           [BroadcastsController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        /*
         * Meta Ads — formerly /campaigns. The product is the Meta-Ads
         * launch surface, so the URL was renamed to match the nav
         * label. Analytics + CRUD endpoints live in the same group so
         * `user.meta-ads.*` is the single namespace.
         *
         * `whereNumber('id')` keeps `/meta-ads/analytics` and
         * `/meta-ads/sync` from being captured by `/{id}/edit`.
         */
        Route::prefix('meta-ads')->middleware('workspace.role:admin')->name('meta-ads.')->group(function () {
            Route::get('/',                 [MetaAdsController::class, 'index'])->name('index');
            Route::get('/create',           [MetaAdsController::class, 'create'])->name('create');
            Route::post('/',                [MetaAdsController::class, 'store'])->name('store');
            Route::get('/analytics',        [MetaAdsController::class, 'analytics'])->name('analytics');
            Route::post('/sync',            [MetaAdsController::class, 'sync'])->name('sync');
            // Pull existing campaigns from the connected Meta ad account into
            // WaDesk (ads created directly in Ads Manager) + their stats.
            Route::post('/import',          [MetaAdsController::class, 'importFromMeta'])->name('import');
            // Workspace's OWN Meta Ads credentials (stored on a dedicated
            // provider=meta_ads WaProviderConfig row). The create flow
            // gates on these; admin global keys are the fallback.
            Route::get('/connect',          [MetaAdsController::class, 'connect'])->name('connect');
            // Auto-detect the ad account + page from the token already granted
            // via WhatsApp embedded signup / coexistence — so the operator
            // doesn't have to paste raw IDs after connecting.
            Route::get('/discover',         [MetaAdsController::class, 'discover'])->name('discover');
            Route::post('/keys',            [MetaAdsController::class, 'saveKeys'])->name('keys.save');
            Route::delete('/keys',          [MetaAdsController::class, 'disconnect'])->name('keys.destroy');
            // Build-with-AI — generate ad copy + targeting from a brief.
            // Listed BEFORE the {id}/* routes so the whereNumber('id')
            // constraint doesn't try to capture "api".
            Route::get('/api/ai-models',    [MetaAdsController::class, 'apiAiModels'])->name('api.ai-models');
            Route::post('/api/ai-generate', [MetaAdsController::class, 'apiAiGenerate'])->name('api.ai-generate');
            // Advanced targeting — live autocomplete + saved-audience list.
            // Fixed paths, declared BEFORE the /{id} catch-all below.
            Route::get('/targeting-search', [MetaAdsController::class, 'targetingSearch'])->name('targeting-search');
            Route::get('/audiences',        [MetaAdsController::class, 'audiences'])->name('audiences');
            // Detail page + AJAX refresh/retry — listed BEFORE the
            // {id}/edit pattern so /meta-ads/{numeric} hits show first.
            Route::get('/{id}',             [MetaAdsController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}/refresh',    [MetaAdsController::class, 'refresh'])->whereNumber('id')->name('refresh');
            Route::get('/{id}/estimate',    [MetaAdsController::class, 'estimate'])->whereNumber('id')->name('estimate');
            Route::post('/{id}/retry',      [MetaAdsController::class, 'retry'])->whereNumber('id')->name('retry');
            Route::get('/{id}/edit',        [MetaAdsController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}',             [MetaAdsController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/toggle',     [MetaAdsController::class, 'toggleStatus'])->whereNumber('id')->name('toggle');
            Route::delete('/{id}',          [MetaAdsController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('wa-campaigns')->middleware('workspace.role:admin')->name('wa-campaigns.')->group(function () {
            Route::get('/',          [WaCampaignsController::class, 'index'])->name('index');
            Route::get('/create',    [WaCampaignsController::class, 'create'])->name('create');
            // #4 — downloadable sample CSV for the bulk-recipient upload.
            Route::get('/sample-csv', [WaCampaignsController::class, 'sampleCsv'])->name('sample-csv');
            Route::post('/',         [WaCampaignsController::class, 'store'])->name('store');
            Route::post('/bulk-delete',   [WaCampaignsController::class, 'bulkDelete'])->name('bulk-delete');
            // Build-with-AI — listed BEFORE the {id}/* routes so the
            // whereNumber('id') constraint doesn't try to capture "api".
            Route::get('/api/ai-models',    [WaCampaignsController::class, 'apiAiModels'])->name('api.ai-models');
            Route::post('/api/ai-generate', [WaCampaignsController::class, 'apiAiGenerate'])->name('api.ai-generate');
            Route::get('/{id}/edit', [WaCampaignsController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::get('/{id}/export', [WaCampaignsController::class, 'exportRecipients'])->whereNumber('id')->name('export');
            Route::get('/{id}',      [WaCampaignsController::class, 'show'])->whereNumber('id')->name('detail');
            Route::put('/{id}',      [WaCampaignsController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}',   [WaCampaignsController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/cancel',   [WaCampaignsController::class, 'cancel'])->whereNumber('id')->name('cancel');
            Route::post('/{id}/resume',   [WaCampaignsController::class, 'resume'])->whereNumber('id')->name('resume');
            Route::post('/{id}/send-now', [WaCampaignsController::class, 'sendNow'])->whereNumber('id')->name('send-now');
            Route::post('/{id}/resend',   [WaCampaignsController::class, 'resend'])->whereNumber('id')->name('resend');
        });

        Route::prefix('flows')->middleware('workspace.role:admin')->name('flows.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\FlowsController::class, 'index'])->name('index');
            Route::get('/builder',           [\App\Http\Controllers\FlowsController::class, 'builder'])->name('builder');
            Route::get('/builder/{id}',      [\App\Http\Controllers\FlowsController::class, 'builder'])->whereNumber('id')->name('builder.edit');
            // Import an existing Instaflow (Instagram) flow into WaDesk + open it
            // in the builder for editing. Round-trips back on save.
            Route::get('/import-ig/{igId}',  [\App\Http\Controllers\FlowsController::class, 'importIgFlow'])->whereNumber('igId')->name('import-ig');
            Route::delete('/{id}',           [\App\Http\Controllers\FlowsController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/duplicate',   [\App\Http\Controllers\FlowsController::class, 'duplicate'])->whereNumber('id')->name('duplicate');
            Route::post('/{id}/toggle',      [\App\Http\Controllers\FlowsController::class, 'toggle'])->whereNumber('id')->name('toggle');
            // Export a flow to a portable JSON file; import one back into this
            // workspace; clone an admin-curated starter template.
            Route::get('/{id}/export',       [\App\Http\Controllers\FlowsController::class, 'export'])->whereNumber('id')->name('export');
            Route::post('/import',           [\App\Http\Controllers\FlowsController::class, 'import'])->name('import');
            Route::post('/templates/{id}/clone', [\App\Http\Controllers\FlowsController::class, 'cloneTemplate'])->whereNumber('id')->name('templates.clone');
            // API endpoints used by the React builder.
            Route::post('/api/save',         [\App\Http\Controllers\FlowsController::class, 'apiSave'])->name('api.save');
            Route::post('/api/publish',      [\App\Http\Controllers\FlowsController::class, 'apiPublish'])->name('api.publish');
            Route::post('/api/unpublish',    [\App\Http\Controllers\FlowsController::class, 'apiUnpublish'])->name('api.unpublish');
            Route::post('/api/upload-media', [\App\Http\Controllers\FlowsController::class, 'apiUploadMedia'])->name('api.upload');
            // Test Runner — fire a Webhook node's real request from "Simulate a
            // chat" (SSRF-guarded), so operators can confirm their endpoint is hit.
            Route::post('/api/test-webhook', [\App\Http\Controllers\FlowsController::class, 'apiTestWebhook'])->name('api.test-webhook');
            Route::post('/api/connect',      [\App\Http\Controllers\FlowsController::class, 'connectDevice'])->name('api.connect');
            Route::post('/api/disconnect/{id}', [\App\Http\Controllers\FlowsController::class, 'disconnectDevice'])->whereNumber('id')->name('api.disconnect');
            Route::get('/api/list',          [\App\Http\Controllers\FlowsController::class, 'apiIndex'])->name('api.list');
            Route::get('/api/default',       [\App\Http\Controllers\FlowsController::class, 'apiDefault'])->name('api.default');
            Route::get('/api/ai-models',     [\App\Http\Controllers\FlowsController::class, 'apiAiModels'])->name('api.ai-models');
            // Trained chat assistants (/ai-training) for the AI node's
            // optional "knowledge base" attach.
            Route::get('/api/ai-assistants', [\App\Http\Controllers\FlowsController::class, 'apiAiAssistants'])->name('api.ai-assistants');
            Route::get('/api/call-assistants', [\App\Http\Controllers\FlowsController::class, 'apiCallAssistants'])->name('api.call-assistants');
            // Natural-language → flow JSON. Calls AiAgentService against
            // admin-enabled provider keys; the modal in the builder POSTs
            // here with { prompt, model, provider }.
            Route::post('/api/ai-generate',  [\App\Http\Controllers\FlowsController::class, 'apiAiGenerate'])->name('api.ai-generate');
            // Commerce-picker endpoints — feed the WhatsApp Shop /
            // WooCommerce / Shopify nodes' inspectors.
            Route::get('/api/commerce/stores',                       [\App\Http\Controllers\FlowsCommerceController::class, 'stores'])->name('api.commerce.stores');
            Route::get('/api/commerce/stores/{storeId}/products',    [\App\Http\Controllers\FlowsCommerceController::class, 'products'])->whereNumber('storeId')->name('api.commerce.products');
            Route::get('/api/{id}',          [\App\Http\Controllers\FlowsController::class, 'apiShow'])->whereNumber('id')->name('api.show');
            Route::delete('/api/{id}',       [\App\Http\Controllers\FlowsController::class, 'apiDestroy'])->whereNumber('id')->name('api.destroy');

            // Flow audience trigger + subscriber management — replaces /drips.
            // /flows/api/picker feeds the trigger node inspector (tags +
            // groups + devices). /flows/{id}/enroll lets the operator
            // manually push contacts into a flow (manual_enroll trigger).
            Route::get('/api/picker',                       [\App\Http\Controllers\FlowsController::class, 'apiPicker'])->name('api.picker');
            Route::post('/{id}/enroll',                     [\App\Http\Controllers\FlowsController::class, 'apiEnroll'])->whereNumber('id')->name('enroll');
            Route::get('/{id}/subscribers',                 [\App\Http\Controllers\FlowsController::class, 'apiSubscribers'])->whereNumber('id')->name('subscribers');
            Route::post('/{id}/subscribers/{cid}/pause',    [\App\Http\Controllers\FlowsController::class, 'apiSubscriberPause'])->whereNumber('id')->whereNumber('cid')->name('subscribers.pause');
            Route::post('/{id}/subscribers/{cid}/resume',   [\App\Http\Controllers\FlowsController::class, 'apiSubscriberResume'])->whereNumber('id')->whereNumber('cid')->name('subscribers.resume');
        });

        // (Node-bridge facing /api/commerce/checkout-link is registered
        // at the top-level routes/web.php so Node can call it without
        // a session — see line ~150.)

        Route::prefix('templates')->middleware('workspace.role:manager')->name('templates.')->group(function () {
            Route::get('/',                    [TemplatesController::class, 'index'])->name('index');
            Route::get('/create',              [TemplatesController::class, 'create'])->name('create');
            Route::post('/',                   [TemplatesController::class, 'store'])->name('store');
            // Pull Instagram templates from a REMOTE Instaflow into the library
            // (button shown only in remote-Instaflow mode — hidden for the native addon).
            Route::post('/sync-instaflow',     [TemplatesController::class, 'syncFromInstaflow'])->name('sync-instaflow');
            // Convenience views — mirror the old TemplatesWaDesk tabs.
            Route::get('/approved',            [TemplatesController::class, 'approved'])->name('approved');
            Route::get('/pending',             [TemplatesController::class, 'pending'])->name('pending');
            Route::get('/rejected',            [TemplatesController::class, 'rejected'])->name('rejected');
            Route::get('/mine',                [TemplatesController::class, 'myTemplates'])->name('mine');
            // JSON API for carousel import + preview rendering.
            Route::get('/api/list',            [TemplatesController::class, 'apiList'])->name('api.list');
            // Build-with-AI — list admin-enabled providers and generate a
            // ready-to-fill template payload from a structured brief +
            // optional free-form prompt. Mirrors the flow builder's
            // ai-models / ai-generate pair.
            Route::get('/api/ai-models',       [TemplatesController::class, 'apiAiModels'])->name('api.ai-models');
            Route::post('/api/ai-generate',    [TemplatesController::class, 'apiAiGenerate'])->name('api.ai-generate');
            Route::get('/api/{id}',            [TemplatesController::class, 'apiShow'])->whereNumber('id')->name('api.show');
            Route::post('/api/{id}/preview',   [TemplatesController::class, 'preview'])->whereNumber('id')->name('api.preview');
            // WABA Meta-sync — detail page + AJAX refresh (rate-limited 1/15 s).
            // sync-stale is the fire-and-forget sweep called by the index
            // page on load; debounced server-side to once-per-10-min per
            // workspace so multiple open tabs don't hammer Meta.
            Route::post('/sync-stale',         [TemplatesController::class, 'syncStale'])->name('sync-stale');
            // Pull templates that exist on the workspace's WABA but not in our DB
            // (created in Meta Business Manager / approved before connecting here).
            Route::post('/import-from-meta',   [TemplatesController::class, 'importFromMeta'])->name('import-from-meta');
            Route::get('/{id}',                [TemplatesController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}/refresh',       [TemplatesController::class, 'refresh'])->whereNumber('id')->name('refresh');
            // (Re)submit a local template to Meta — recovery for rows created
            // while the initial submit failed (pending, no meta_template_id).
            Route::post('/{id}/submit',        [TemplatesController::class, 'submitToMetaAction'])->whereNumber('id')->name('submit');
            // Per-row mutations.
            Route::get('/{id}/edit',           [TemplatesController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::put('/{id}',                [TemplatesController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/approve',       [TemplatesController::class, 'approve'])->whereNumber('id')->name('approve');
            Route::post('/{id}/reject',        [TemplatesController::class, 'reject'])->whereNumber('id')->name('reject');
            Route::delete('/{id}',             [TemplatesController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('devices')->middleware('workspace.role:admin')->name('devices.')->group(function () {
            Route::get('/',                       [DevicesController::class, 'index'])->name('index');
            Route::post('/',                      [DevicesController::class, 'store'])->name('store');
            Route::post('/check',                 [DevicesController::class, 'check'])->name('check');
            // Pairing flow — proxies the Node WhatsApp bridge endpoints
            // the old project's deviceadd.js called (generate-qr-code,
            // generate-pairing-code, get-device-status, kill-session,
            // update-device-status). Env-gated; demo response without
            // SERVER_URL so the modal still flows end-to-end in dev.
            // WABA multi-account actions. Per-row promote + disconnect
            // + two connect entrypoints (manual paste OR Embedded Signup).
            Route::post  ('/waba/verify-token',     [DevicesController::class, 'wabaVerifyToken'])->name('waba.verify-token');
            Route::post  ('/waba/exchange-token',   [DevicesController::class, 'wabaExchangeToken'])->name('waba.exchange-token');
            Route::post  ('/waba/connect/manual',   [DevicesController::class, 'wabaConnectManual'])->name('waba.connect.manual');
            Route::post  ('/waba/connect/embedded', [DevicesController::class, 'wabaConnectEmbedded'])->name('waba.connect.embedded');
            Route::post  ('/waba/{id}/primary',    [DevicesController::class, 'wabaSetPrimary'])->whereNumber('id')->name('waba.primary');
            Route::delete('/waba/{id}/disconnect', [DevicesController::class, 'wabaDisconnect'])->whereNumber('id')->name('waba.disconnect');
            Route::delete('/waba/{id}/remove',     [DevicesController::class, 'wabaRemove'])->whereNumber('id')->name('waba.remove');
            // Twilio account remove — mirrors waba.remove (both are wa_provider_configs rows).
            Route::delete('/twilio/{id}/remove',   [DevicesController::class, 'twilioRemove'])->whereNumber('id')->name('twilio.remove');
            // Instagram (via the linked Instaflow install). The real IG engine
            // stays on Instaflow; these manage this workspace's mirror rows:
            //   available     — IG accounts on Instaflow not yet linked (picker)
            //   link          — upsert a mirror row (also the per-row Refresh)
            //   connect-start — mint a popup ticket to connect a NEW account
            //   return        — no-opener fallback for the connect popup
            //   {id}/unlink   — drop this workspace's mirror row
            Route::get   ('/instagram/available',     [DevicesController::class, 'instagramAvailable'])->name('instagram.available');
            Route::post  ('/instagram/link',          [DevicesController::class, 'instagramLink'])->name('instagram.link');
            Route::post  ('/instagram/connect-start', [DevicesController::class, 'instagramConnectStart'])->name('instagram.connect-start');
            Route::get   ('/instagram/return',        [DevicesController::class, 'instagramReturn'])->name('instagram.return');
            Route::delete('/instagram/{id}/unlink',   [DevicesController::class, 'instagramUnlink'])->whereNumber('id')->name('instagram.unlink');
            // Re-point THIS number's Meta webhook (override_callback_uri) at our
            // current inbound URL, reusing the stored token — one-click fix for
            // inbound going dead after a domain change (no disconnect / re-paste).
            Route::post  ('/waba/{id}/resubscribe', [DevicesController::class, 'wabaResubscribe'])->whereNumber('id')->name('waba.resubscribe');
            // Register THIS number on the Cloud API — fixes sends failing with
            // (#133010) Account not registered on a manually/popup-connected number.
            Route::post  ('/waba/{id}/register',    [DevicesController::class, 'wabaRegister'])->whereNumber('id')->name('waba.register');
            Route::post  ('/waba/{id}/sync-history', [DevicesController::class, 'wabaSyncHistory'])->whereNumber('id')->name('waba.sync-history');
            // WABA account-health dashboard — live Meta diagnostics (status,
            // quality, limits, permissions, webhook, templates, blocks/errors).
            Route::get   ('/waba/{id}/health',      [DevicesController::class, 'wabaHealth'])->whereNumber('id')->name('waba.health');
            Route::get   ('/waba/{id}/health.json', [DevicesController::class, 'wabaHealthJson'])->whereNumber('id')->name('waba.health.json');
            // Paste / replace this number's Meta access token directly — fast fix
            // when a connect left it empty (verified against Meta before saving).
            Route::post  ('/waba/{id}/set-token',   [DevicesController::class, 'setWabaToken'])->whereNumber('id')->name('waba.set-token');
            Route::post  ('/waba/{id}/token-refresh', [DevicesController::class, 'wabaRefreshToken'])->whereNumber('id')->name('waba.token-refresh');
            // WhatsApp Business username — claim/reserve + release a public @handle
            // for this number via the Meta Cloud API (POST/DELETE {pnid}/username).
            Route::post  ('/waba/{id}/username',     [DevicesController::class, 'claimWabaUsername'])->whereNumber('id')->name('waba.username.claim');
            Route::delete('/waba/{id}/username',     [DevicesController::class, 'deleteWabaUsername'])->whereNumber('id')->name('waba.username.delete');
            // Message QR codes — click-to-chat QR + short link with a prefilled
            // message (Meta message_qrdls). Customers scan → chat opens pre-typed.
            Route::post  ('/waba/{id}/qr-codes',        [DevicesController::class, 'createWabaQrCode'])->whereNumber('id')->name('waba.qr.create');
            Route::delete('/waba/{id}/qr-codes/{code}', [DevicesController::class, 'deleteWabaQrCode'])->whereNumber('id')->where('code', '[A-Za-z0-9]+')->name('waba.qr.delete');
            Route::get('/{id}/qr-code',           [DevicesController::class, 'qrCode'])->whereNumber('id')->name('qr-code');
            Route::get('/{id}/pairing-code',      [DevicesController::class, 'pairingCode'])->whereNumber('id')->name('pairing-code');
            Route::get('/{id}/connection-status', [DevicesController::class, 'connectionStatus'])->whereNumber('id')->name('connection-status');
            Route::post('/{id}/kill-session',     [DevicesController::class, 'killSession'])->whereNumber('id')->name('kill-session');
            Route::post('/{id}/connection',       [DevicesController::class, 'updateDeviceStatus'])->whereNumber('id')->name('update-status');
            // Per-number proxy / IP isolation (Unofficial-API)
            Route::post('/{id}/proxy',            [DevicesController::class, 'saveProxy'])->whereNumber('id')->name('proxy.save');
            Route::post('/{id}/proxy/test',       [DevicesController::class, 'testProxy'])->whereNumber('id')->name('proxy.test');
            Route::get('/{id}',                   [DevicesController::class, 'show'])->whereNumber('id')->name('detail');
            Route::put('/{id}',                   [DevicesController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/toggle',           [DevicesController::class, 'toggle'])->whereNumber('id')->name('toggle');
            Route::delete('/{id}',                [DevicesController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
        Route::get('/connect', [UserPagesController::class, 'connect'])->name('connect');
        // wa-store onboarding wizard — single form that creates the
        // storefront row + binds a sending device.
        Route::post('/connect/wa-store/save',  [\App\Http\Controllers\WaStoreWizardController::class, 'save'])->name('connect.wa-store.save');
        Route::post('/connect/wa-store/reset', [\App\Http\Controllers\WaStoreWizardController::class, 'reset'])->name('connect.wa-store.reset');
        Route::post('/connect/wa-store/twilio',  [\App\Http\Controllers\WaConnectController::class, 'saveTwilio'])->name('connect.wa-store.twilio');
        Route::post('/connect/wa-store/baileys', [\App\Http\Controllers\WaConnectController::class, 'saveBaileys'])->name('connect.wa-store.baileys');
        Route::get('/api/baileys/qr/{configId}',     [\App\Http\Controllers\WaConnectController::class, 'pollQr'])->whereNumber('configId')->name('baileys.qr.poll');
        Route::get('/api/baileys/status/{configId}', [\App\Http\Controllers\WaConnectController::class, 'pollStatus'])->whereNumber('configId')->name('baileys.status.poll');
        Route::post('/connect/wa-store/waba',    [\App\Http\Controllers\WaConnectWabaController::class, 'complete'])->name('connect.wa-store.waba');
        Route::post('/connect/wa-store/disconnect', [\App\Http\Controllers\WaConnectController::class, 'disconnect'])->name('connect.wa-store.disconnect');

        // /store — full commerce dashboard
        Route::prefix('store')->middleware('workspace.role:admin')->name('store.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\StoreController::class, 'index'])->name('index');
            // Products
            Route::get('/products',          [\App\Http\Controllers\WaProductController::class, 'index'])->name('products.index');
            Route::get('/products/create',   [\App\Http\Controllers\WaProductController::class, 'create'])->name('products.create');
            Route::post('/products',         [\App\Http\Controllers\WaProductController::class, 'store'])->name('products.store');
            Route::get('/products/{id}/edit',[\App\Http\Controllers\WaProductController::class, 'edit'])->whereNumber('id')->name('products.edit');
            Route::put('/products/{id}',     [\App\Http\Controllers\WaProductController::class, 'update'])->whereNumber('id')->name('products.update');
            Route::delete('/products/{id}',  [\App\Http\Controllers\WaProductController::class, 'destroy'])->whereNumber('id')->name('products.destroy');
            Route::get('/products/{id}/share-links', [\App\Http\Controllers\WaProductController::class, 'shareLinks'])->whereNumber('id')->name('products.share');
            // JSON list for the catalog-send picker modal in /team-inbox + /chat
            Route::get('/products/api/list', [\App\Http\Controllers\WaProductController::class, 'apiList'])->name('products.api.list');
            // Storefront settings
            Route::get('/storefront',        [\App\Http\Controllers\WaStorefrontController::class, 'edit'])->name('storefront.edit');
            Route::put('/storefront',        [\App\Http\Controllers\WaStorefrontController::class, 'update'])->name('storefront.update');
            Route::post('/storefront/verify-domain', [\App\Http\Controllers\WaStorefrontController::class, 'verifyDomain'])->name('storefront.verify');
            // Orders
            Route::get('/orders',            [\App\Http\Controllers\WaOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{id}',       [\App\Http\Controllers\WaOrderController::class, 'show'])->whereNumber('id')->name('orders.show');
            Route::put('/orders/{id}',       [\App\Http\Controllers\WaOrderController::class, 'updateStatus'])->whereNumber('id')->name('orders.update');
            Route::post('/orders/{id}/payment-link', [\App\Http\Controllers\WaOrderController::class, 'sendPaymentLink'])->whereNumber('id')->name('orders.payment-link');
            Route::post('/orders/{id}/generate-payment-link', [\App\Http\Controllers\WaOrderController::class, 'generatePaymentLink'])->whereNumber('id')->name('orders.generate-payment-link');
            Route::delete('/orders/{id}',    [\App\Http\Controllers\WaOrderController::class, 'destroy'])->whereNumber('id')->name('orders.destroy');
            // Customers (pre-set delivery address → auto-fills in the WhatsApp ordering flow)
            Route::get('/customers',         [\App\Http\Controllers\WaCustomerProfileController::class, 'index'])->name('customers.index');
            Route::post('/customers',        [\App\Http\Controllers\WaCustomerProfileController::class, 'store'])->name('customers.store');
            Route::delete('/customers/{id}', [\App\Http\Controllers\WaCustomerProfileController::class, 'destroy'])->whereNumber('id')->name('customers.destroy');
            // WhatsApp Groups (group directory + ordering codes — Unofficial API)
            Route::get('/groups',            [\App\Http\Controllers\WaGroupController::class, 'index'])->name('groups.index');
            Route::put('/groups/{id}/code',  [\App\Http\Controllers\WaGroupController::class, 'updateCode'])->whereNumber('id')->name('groups.code');
            // Coupons (S5)
            Route::get('/coupons',           [\App\Http\Controllers\WaCouponController::class, 'index'])->name('coupons.index');
            Route::post('/coupons',          [\App\Http\Controllers\WaCouponController::class, 'store'])->name('coupons.store');
            Route::put('/coupons/{id}',      [\App\Http\Controllers\WaCouponController::class, 'update'])->whereNumber('id')->name('coupons.update');
            Route::delete('/coupons/{id}',   [\App\Http\Controllers\WaCouponController::class, 'destroy'])->whereNumber('id')->name('coupons.destroy');
            // Reviews moderation (S6)
            Route::get('/reviews',           [\App\Http\Controllers\WaReviewController::class, 'index'])->name('reviews.index');
            Route::put('/reviews/{id}',      [\App\Http\Controllers\WaReviewController::class, 'update'])->whereNumber('id')->name('reviews.update');
            Route::delete('/reviews/{id}',   [\App\Http\Controllers\WaReviewController::class, 'destroy'])->whereNumber('id')->name('reviews.destroy');
            // WhatsApp Pay (in-chat order_details payments — India region-gated)
            Route::get('/payments',          [\App\Http\Controllers\WhatsAppPayController::class, 'index'])->name('payments.index');
            Route::post('/payments',         [\App\Http\Controllers\WhatsAppPayController::class, 'store'])->name('payments.store');
            Route::delete('/payments/{id}',  [\App\Http\Controllers\WhatsAppPayController::class, 'destroy'])->whereNumber('id')->name('payments.destroy');
            Route::post('/orders/{id}/whatsapp-pay', [\App\Http\Controllers\WhatsAppPayController::class, 'requestPayment'])->whereNumber('id')->name('orders.whatsapp-pay');
        });

        Route::prefix('auto-reply')->middleware('workspace.role:admin')->name('auto-reply.')->group(function () {
            $ar = \App\Http\Controllers\AutoReplyController::class;
            Route::get('/',                [$ar, 'index'])->name('index');
            Route::get('/create',          [$ar, 'create'])->name('create');
            Route::get('/keyword',         [$ar, 'keyword'])->name('keyword');
            Route::get('/demo-csv',        [$ar, 'demoCsv'])->name('demo-csv');
            Route::post('/',               [$ar, 'store'])->name('store');
            Route::post('/import',         [$ar, 'import'])->name('import');
            Route::patch('/{id}',          [$ar, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/toggle',    [$ar, 'toggle'])->whereNumber('id')->name('toggle');
            Route::delete('/{id}',         [$ar, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/bulk',           [$ar, 'bulk'])->name('bulk');
            // Manual "Away mode" switch — flips workspaces.inbox_away so `away`
            // trigger-type auto-replies fire until it is switched off.
            Route::post('/away-mode',      [$ar, 'toggleAway'])->name('away-mode');
        });

        Route::prefix('scheduled')->middleware('workspace.role:manager')->name('scheduled.')->group(function () {
            $sc = \App\Http\Controllers\ScheduledController::class;
            Route::get('/',                [$sc, 'index'])->name('index');
            Route::get('/create',          [$sc, 'create'])->name('create');
            Route::post('/',               [$sc, 'store'])->name('store');
            Route::get('/{id}',            [$sc, 'detail'])->whereNumber('id')->name('detail');
            Route::patch('/{id}',          [$sc, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}',         [$sc, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/pause',     [$sc, 'pause'])->whereNumber('id')->name('pause');
            Route::post('/{id}/resume',    [$sc, 'resume'])->whereNumber('id')->name('resume');
            Route::post('/{id}/cancel',    [$sc, 'cancel'])->whereNumber('id')->name('cancel');
            Route::post('/{id}/run-now',   [$sc, 'runNow'])->whereNumber('id')->name('run-now');
            Route::post('/{id}/retry',     [$sc, 'retry'])->whereNumber('id')->name('retry');
        });

        // WhatsApp Cloud-API calling — operator-facing endpoints.
        // Settings toggles + the action surface (dial / accept / reject /
        // terminate / permission). Webhook receiver is unauthenticated +
        // CSRF-exempt; lives in the top-level route group below.
        Route::prefix('wa-calling')->middleware(['workspace.role:manager', 'plan:access_waba_calling'])->name('wa-calling.')->group(function () {
            $wc = \App\Http\Controllers\WaCallingController::class;
            Route::get('/status',                  [$wc, 'status'])->name('status');
            Route::post('/{id}/toggle',            [$wc, 'toggle'])->whereNumber('id')->name('toggle');
            // Action verbs against Meta's /calls endpoint, fronted by
            // WaCallingService. Each takes a wa_calls.id (our local row).
            Route::post('/calls/dial',             [$wc, 'dial'])->name('dial');
            Route::post('/calls/{id}/accept',      [$wc, 'accept'])->whereNumber('id')->name('accept');
            // Outbound dial-progress poll: browser waits here for Meta's
            // SDP answer (delivered via the call.connect webhook) so it
            // can apply it and open the audio path. Also reports terminal
            // status so a remote hang-up closes the operator's panel.
            Route::get('/calls/{id}/answer',       [$wc, 'dialProgress'])->whereNumber('id')->name('answer');
            Route::post('/calls/{id}/pre-accept',  [$wc, 'preAccept'])->whereNumber('id')->name('pre-accept');
            // Operator-initiated recording. Calls are NOT recorded automatically;
            // this starts capture mid-call from the moment it is pressed.
            Route::post('/calls/{id}/record',      [$wc, 'startRecording'])->whereNumber('id')->name('record');
            Route::post('/calls/{id}/reject',      [$wc, 'reject'])->whereNumber('id')->name('reject');
            Route::post('/calls/{id}/terminate',   [$wc, 'terminate'])->whereNumber('id')->name('terminate');
            // Browser-recorded call audio (mixed mic+remote) → attached to
            // the call-log row for playback under /call-logs.
            Route::post('/calls/{id}/recording',   [$wc, 'uploadRecording'])->whereNumber('id')->name('recording');
            Route::post('/permission-request',     [$wc, 'requestPermission'])->name('permission-request');
            // Pending-calls poll bridge — team-inbox JS reads this every
            // few seconds to surface incoming-call toasts. No queue/Reverb.
            Route::get('/pending',                 [$wc, 'pending'])->name('pending');
        });

        Route::prefix('webhooks')->middleware('workspace.role:admin')->name('webhooks.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\WebhooksController::class, 'index'])->name('index');
            Route::get('/create',          [\App\Http\Controllers\WebhooksController::class, 'create'])->name('create');
            Route::post('/',               [\App\Http\Controllers\WebhooksController::class, 'store'])->name('store');
            Route::get('/{id}',            [\App\Http\Controllers\WebhooksController::class, 'show'])->name('detail')->whereNumber('id');
            Route::get('/{id}/edit',       [\App\Http\Controllers\WebhooksController::class, 'edit'])->name('edit')->whereNumber('id');
            Route::put('/{id}',            [\App\Http\Controllers\WebhooksController::class, 'update'])->name('update')->whereNumber('id');
            Route::delete('/{id}',         [\App\Http\Controllers\WebhooksController::class, 'destroy'])->name('destroy')->whereNumber('id');
            Route::post('/{id}/toggle',    [\App\Http\Controllers\WebhooksController::class, 'toggle'])->name('toggle')->whereNumber('id');
            Route::post('/{id}/test-fire', [\App\Http\Controllers\WebhooksController::class, 'testFire'])->name('test-fire')->whereNumber('id');
            // Test-fire a DRAFT (unsaved) endpoint — used by the /webhooks/create
            // page's "Test fire" button so an operator can verify URL + secret
            // before saving (no DB row, no delivery history written).
            Route::post('/test-fire-draft',[\App\Http\Controllers\WebhooksController::class, 'testFireDraft'])->name('test-fire-draft');
            // Incoming (inbound) webhooks — generate a URL, capture what
            // arrives, inspect it, and optionally relay to a destination.
            // Literal `/incoming` segment never collides with the numeric
            // /{id} routes above (whereNumber).
            Route::get   ('/incoming',              [\App\Http\Controllers\IncomingWebhookController::class, 'index'])->name('incoming');
            Route::post  ('/incoming',              [\App\Http\Controllers\IncomingWebhookController::class, 'store'])->name('incoming.store');
            Route::post  ('/incoming/{id}/forward', [\App\Http\Controllers\IncomingWebhookController::class, 'forward'])->whereNumber('id')->name('incoming.forward');
            Route::post  ('/incoming/{id}/toggle',  [\App\Http\Controllers\IncomingWebhookController::class, 'toggle'])->whereNumber('id')->name('incoming.toggle');
            Route::post  ('/incoming/{id}/clear',   [\App\Http\Controllers\IncomingWebhookController::class, 'clear'])->whereNumber('id')->name('incoming.clear');
            Route::get   ('/incoming/{id}/events',  [\App\Http\Controllers\IncomingWebhookController::class, 'eventsJson'])->whereNumber('id')->name('incoming.events');
            Route::delete('/incoming/{id}',         [\App\Http\Controllers\IncomingWebhookController::class, 'destroy'])->whereNumber('id')->name('incoming.destroy');
        });

        // ─── WhatsApp Catalog (standalone, top-level) ───
        // Lives at /catalog/* — NOT inside /store. Catalog is a
        // first-class feature: set up Meta connection, sync products,
        // send products as carousel/SPM/MPM to any phone number, and
        // see activity. Buried-in-store made it hard to find.
        Route::prefix('catalog')->middleware(['workspace.role:admin', 'plan:access_wa_storefront'])->name('catalog.')->group(function () {
            $cc = \App\Http\Controllers\CatalogController::class;
            Route::get('/',                 [$cc, 'index'])->name('index');
            Route::get('/send',             [$cc, 'sendPage'])->name('send');
            Route::get('/collections',      [$cc, 'collectionsPage'])->name('collections');
            Route::get('/activity',         [$cc, 'activityPage'])->name('activity');
            Route::post('/connect',         [$cc, 'connect'])->name('connect');
            Route::post('/autodetect',      [$cc, 'autodetect'])->name('autodetect');
            Route::post('/disconnect',      [$cc, 'disconnect'])->name('disconnect');
            Route::post('/sync-chunk',      [$cc, 'syncChunk'])->name('sync-chunk');
            Route::post('/import-from-meta', [$cc, 'importFromMeta'])->name('import-from-meta');
            Route::post('/poll',            [$cc, 'pollBatches'])->name('poll');
            Route::post('/commerce',        [$cc, 'commerceSettings'])->name('commerce');
            Route::post('/automation',      [$cc, 'saveAutomation'])->name('automation');
            Route::post('/send-to-number',  [$cc, 'sendToNumber'])->name('send-to-number');
            // Product sets / collections — reusable named product groups.
            Route::post('/sets',            [$cc, 'storeSet'])->name('sets.store');
            Route::put('/sets/{id}',        [$cc, 'updateSet'])->whereNumber('id')->name('sets.update');
            Route::delete('/sets/{id}',     [$cc, 'deleteSet'])->whereNumber('id')->name('sets.destroy');
        });

        Route::middleware('workspace.role:admin')->group(function () {
            Route::get('/integrations',  [UserPagesController::class, 'integrations'])->name('integrations');

            // Shopify integration — live OAuth + webhook handling.
            $sc = \App\Http\Controllers\ShopifyController::class;
            Route::get('/shopify',                  [$sc, 'index'])->name('shopify');
            Route::post('/shopify/connect',         [$sc, 'startOAuth'])->name('shopify.connect');
            Route::post('/shopify/{id}/sync',       [$sc, 'sync'])->whereNumber('id')->name('shopify.sync');
            Route::post('/shopify/{id}/disconnect', [$sc, 'disconnect'])->whereNumber('id')->name('shopify.disconnect');
            Route::post('/shopify/{id}/events',     [$sc, 'saveEvents'])->whereNumber('id')->name('shopify.events');
            Route::post('/shopify/{id}/offer',      [$sc, 'sendOffer'])->whereNumber('id')->name('shopify.offer');
            Route::post('/shopify/{id}/winback',    [$sc, 'sendWinback'])->whereNumber('id')->name('shopify.winback');

            // WooCommerce integration — Basic-Auth REST + webhook handling.
            $wc = \App\Http\Controllers\WoocommerceController::class;
            Route::get('/woocommerce',                  [$wc, 'index'])->name('woocommerce');
            Route::post('/woocommerce/connect',         [$wc, 'connect'])->name('woocommerce.connect');
            Route::post('/woocommerce/test',            [$wc, 'test'])->name('woocommerce.test');
            Route::post('/woocommerce/{id}/sync',       [$wc, 'sync'])->whereNumber('id')->name('woocommerce.sync');
            Route::post('/woocommerce/{id}/disconnect', [$wc, 'disconnect'])->whereNumber('id')->name('woocommerce.disconnect');
            Route::post('/woocommerce/{id}/events',     [$wc, 'saveEvents'])->whereNumber('id')->name('woocommerce.events');
            Route::post('/woocommerce/{id}/offer',      [$wc, 'sendOffer'])->whereNumber('id')->name('woocommerce.offer');
            Route::post('/woocommerce/{id}/winback',    [$wc, 'sendWinback'])->whereNumber('id')->name('woocommerce.winback');
            Route::get('/woocommerce/{id}/plugin',      [$wc, 'downloadPlugin'])->whereNumber('id')->name('woocommerce.plugin');

            // HubSpot CRM integration — OAuth 2.0, /crm/v3 endpoints.
            // Pushes deal + contact when a wa_orders row is created or
            // conversations.interested_sku is set. Uses /batch/upsert
            // for the contact so the same customer doesn't fork into
            // multiple HubSpot records.
            $hs = \App\Http\Controllers\HubspotController::class;
            Route::get('/hubspot',                  [$hs, 'index'])->name('hubspot');
            Route::post('/hubspot/connect',         [$hs, 'startOAuth'])->name('hubspot.connect');
            Route::post('/hubspot/{id}/disconnect', [$hs, 'disconnect'])->whereNumber('id')->name('hubspot.disconnect');

            // Slack → WhatsApp: a `/wa send <name>: <msg>` slash command in
            // Slack sends a WhatsApp message via the workspace's device.
            Route::middleware('plan:integration_slack')->group(function () {
                $sl = \App\Http\Controllers\SlackController::class;
                Route::get ('/slack',                 [$sl, 'index'])->name('slack');
                Route::post('/slack/connect',         [$sl, 'connect'])->name('slack.connect');
                Route::post('/slack/{id}/settings',   [$sl, 'settings'])->whereNumber('id')->name('slack.settings');
                Route::post('/slack/{id}/disconnect', [$sl, 'disconnect'])->whereNumber('id')->name('slack.disconnect');
            });

            // Trello → WhatsApp: card assignments / changes on a watched board
            // fire WhatsApp notifications.
            Route::middleware('plan:integration_trello')->group(function () {
                $tr = \App\Http\Controllers\TrelloController::class;
                Route::get ('/trello',                 [$tr, 'index'])->name('trello');
                Route::post('/trello/connect',         [$tr, 'connect'])->name('trello.connect');
                Route::post('/trello/{id}/settings',   [$tr, 'updateSettings'])->whereNumber('id')->name('trello.settings');
                Route::post('/trello/{id}/register',   [$tr, 'registerWebhook'])->whereNumber('id')->name('trello.register');
                Route::post('/trello/{id}/disconnect', [$tr, 'disconnect'])->whereNumber('id')->name('trello.disconnect');
            });

            // (drip routes removed — the flow builder's trigger node now
            // owns audience triggers. See /flows + the trigger node's
            // tag_added / group_join / manual_enroll kinds. Subscribers
            // tracked in `flow_subscribers`.)

            // Appointment booking — Google Calendar OAuth, free/busy
            // checks, and Calendar event writes. The flow builder
            // surfaces "Book appointment" as a node type; the runtime
            // hits /api/appointments/slots + /api/appointments/book to
            // render slot lists and confirm bookings end-to-end.
            $ap  = \App\Http\Controllers\AppointmentController::class;
            $gco = \App\Http\Controllers\GoogleCalendarOAuthController::class;
            Route::get('/appointments',                 [$ap,  'index'])->name('appointments.index');
            Route::get('/appointments/settings',        [$ap,  'settings'])->name('appointments.settings');
            Route::post('/appointments/settings',       [$ap,  'saveSettings'])->name('appointments.settings.save');
            Route::post('/appointments/{id}/cancel',     [$ap,  'cancel'])->whereNumber('id')->name('appointments.cancel');
            Route::post('/appointments/{id}/reschedule', [$ap,  'reschedule'])->whereNumber('id')->name('appointments.reschedule');
            Route::post('/appointments/oauth/google/start',      [$gco, 'start'])->name('appointments.gcal.start');
            Route::post('/appointments/oauth/google/disconnect', [$gco, 'disconnect'])->name('appointments.gcal.disconnect');

            // Dedicated Google account page — same OAuth tokens power
            // BookAppointment, GoogleMeet node, and inbox composer.
            Route::get('/google-account', [\App\Http\Controllers\GoogleAccountController::class, 'index'])->name('google-account');

            // Flow-builder pickers — list the workspace's Sheets / Docs /
            // Forms so the node-config modals can render a dropdown.
            $gfn = \App\Http\Controllers\GoogleFlowNodeController::class;
            Route::get('/api/google/picker/{kind}', [$gfn, 'picker'])
                ->where('kind', 'sheets|docs|forms')
                ->name('api.google.picker');

            // Apps Script generator. The user downloads this .gs file from
            // the Google Form node config and pastes it into their form's
            // Script Editor to enable the submission → flow-resume webhook.
            Route::get('/google-account/forms/{formId}/apps-script.gs', [$gfn, 'appsScript'])
                ->where('formId', '[A-Za-z0-9_\-]{8,128}')
                ->name('google.apps-script');

            // ── AI Call Assistant — wizard + list, plus the JSON save
            // endpoint the wizard JS POSTs to. Real-time call handling
            // lives in the Node bridge; this is the config surface.
            $aica = \App\Http\Controllers\AiCallAssistantController::class;
            Route::middleware('plan:access_ai_voice_agent')->group(function () use ($aica) {
                Route::get('/ai-assistants',                     [$aica, 'index'])->name('ai-assistants.index');
                Route::get('/ai-assistants/create',              [$aica, 'create'])->name('ai-assistants.create');
                Route::get('/ai-assistants/{id}/edit',           [$aica, 'edit'])->whereNumber('id')->name('ai-assistants.edit');
                Route::post('/ai-assistants/api/save',           [$aica, 'apiSave'])->name('ai-assistants.api.save');
                Route::post('/ai-assistants/{id}/toggle',        [$aica, 'toggle'])->whereNumber('id')->name('ai-assistants.toggle');
                Route::post('/ai-assistants/{id}/duplicate',     [$aica, 'duplicate'])->whereNumber('id')->name('ai-assistants.duplicate');
                Route::delete('/ai-assistants/{id}',             [$aica, 'destroy'])->whereNumber('id')->name('ai-assistants.destroy');
            });

            // ── Call logs — read-only viewer. Writes come from Node
            // via the Twilio status webhook handler (separate route at
            // the top level, signed by X-Twilio-Signature).
            $clc = \App\Http\Controllers\CallLogsController::class;
            Route::middleware('plan:access_waba_calling')->group(function () use ($clc) {
                Route::get('/call-logs',          [$clc, 'index'])->name('call-logs.index');
                Route::get('/call-logs/{id}',     [$clc, 'show'])->whereNumber('id')->name('call-logs.show');
            });

            // ── WhatsApp Forms — builder + publish to Meta Flows API +
            // CRUD. The flow-builder `wa_form` node references published
            // rows by id.
            $waf = \App\Http\Controllers\WaFormController::class;
            Route::middleware('plan:access_wa_forms')->group(function () use ($waf) {
            Route::get('/wa-forms',                [$waf, 'index'])->name('wa-forms.index');
            Route::get('/wa-forms/create',         [$waf, 'create'])->name('wa-forms.create');
            Route::get('/wa-forms/{id}/edit',      [$waf, 'edit'])->whereNumber('id')->name('wa-forms.edit');
            Route::post('/wa-forms/api/save',      [$waf, 'apiSave'])->name('wa-forms.api.save');
            Route::get('/wa-forms/api/list',       [$waf, 'apiList'])->name('wa-forms.api.list');
            Route::post('/wa-forms/{id}/publish',  [$waf, 'publish'])->whereNumber('id')->name('wa-forms.publish');
            Route::get('/wa-forms/{id}/submissions',        [$waf, 'submissions'])->whereNumber('id')->name('wa-forms.submissions');
            Route::get('/wa-forms/{id}/submissions/export', [$waf, 'exportSubmissions'])->whereNumber('id')->name('wa-forms.submissions.export');
            Route::post('/wa-forms/{id}/duplicate',[$waf, 'duplicate'])->whereNumber('id')->name('wa-forms.duplicate');
            Route::delete('/wa-forms/{id}',        [$waf, 'destroy'])->whereNumber('id')->name('wa-forms.destroy');
            });

            // WhatsApp Link Generator — trackable wa.me deep-links. Each
            // saved row mints /l/{slug} public short link with click
            // analytics. The redirect endpoint itself is wired below,
            // outside the auth group.
            $wcl = \App\Http\Controllers\WaChatLinkController::class;
            Route::middleware('plan:access_wa_links')->group(function () use ($wcl) {
            Route::get('/wa-links',                  [$wcl, 'index'])->name('wa-links.index');
            Route::get('/wa-links/create',           [$wcl, 'create'])->name('wa-links.create');
            Route::get('/wa-links/{id}/edit',        [$wcl, 'edit'])->whereNumber('id')->name('wa-links.edit');
            Route::post('/wa-links/api/save',        [$wcl, 'apiSave'])->name('wa-links.api.save');
            Route::post('/wa-links/{id}/duplicate',  [$wcl, 'duplicate'])->whereNumber('id')->name('wa-links.duplicate');
            Route::delete('/wa-links/{id}',          [$wcl, 'destroy'])->whereNumber('id')->name('wa-links.destroy');
            });

            // ── Chatbot Widgets + AI Training. Two surfaces:
            //   /chatbot-widgets   — multi-widget builder + embed snippet
            //   /ai-training       — chat assistants + their training sources
            // Public widget endpoints live OUTSIDE the auth group below.
            $cbw = \App\Http\Controllers\ChatbotWidgetController::class;
            Route::get('/chatbot-widgets',                  [$cbw, 'index'])->name('chatbot-widgets.index');
            Route::get('/chatbot-widgets/create',           [$cbw, 'create'])->name('chatbot-widgets.create');
            Route::get('/chatbot-widgets/{id}/edit',        [$cbw, 'edit'])->whereNumber('id')->name('chatbot-widgets.edit');
            Route::post('/chatbot-widgets/api/save',        [$cbw, 'apiSave'])->name('chatbot-widgets.api.save');
            Route::post('/chatbot-widgets/{id}/duplicate',  [$cbw, 'duplicate'])->whereNumber('id')->name('chatbot-widgets.duplicate');
            Route::post('/chatbot-widgets/{id}/rotate-token',[$cbw, 'rotateToken'])->whereNumber('id')->name('chatbot-widgets.rotate-token');
            Route::delete('/chatbot-widgets/{id}',          [$cbw, 'destroy'])->whereNumber('id')->name('chatbot-widgets.destroy');

            // AI Chat Assistants + training sources — gated on the same
            // plan flag the chatbot widget + AiAgentService text replies
            // use, so a workspace without the AI chat tier can't sneak
            // around the gate by creating an assistant via the API
            // directly. The previous group had NO plan check, letting
            // any workspace burn provider tokens regardless of tier.
            $aitc = \App\Http\Controllers\AiTrainingController::class;
            Route::middleware('plan:access_ai_chat_assistant')->group(function () use ($aitc) {
                Route::get('/ai-training',                          [$aitc, 'index'])->name('ai-training.index');
                Route::post('/ai-training/responder-mode',          [$aitc, 'saveResponderMode'])->name('ai-training.responder-mode');
                Route::get('/ai-training/create',                   [$aitc, 'create'])->name('ai-training.create');
                Route::get('/ai-training/{id}/edit',                [$aitc, 'edit'])->whereNumber('id')->name('ai-training.edit');
                Route::post('/ai-training/{id}/duplicate',          [$aitc, 'duplicate'])->whereNumber('id')->name('ai-training.duplicate');
                Route::post('/ai-training/api/assistant',           [$aitc, 'apiSaveAssistant'])->name('ai-training.api.assistant.save');
                Route::delete('/ai-training/api/assistant/{id}',    [$aitc, 'apiDeleteAssistant'])->whereNumber('id')->name('ai-training.api.assistant.delete');
                Route::get('/ai-training/api/assistants',           [$aitc, 'apiListAssistants'])->name('ai-training.api.assistants.list');
                Route::get('/ai-training/api/sources',              [$aitc, 'apiListSources'])->name('ai-training.api.sources.list');
                Route::post('/ai-training/api/source',              [$aitc, 'apiAddSource'])->name('ai-training.api.source.add');
                Route::post('/ai-training/api/source/file',         [$aitc, 'apiUploadFile'])->name('ai-training.api.source.file');
                Route::delete('/ai-training/api/source/{id}',       [$aitc, 'apiDeleteSource'])->whereNumber('id')->name('ai-training.api.source.delete');
            });

            // Audio serving — lazily wraps the raw PCM dumped by Node
            // in a WAV header so the inbox <audio> player can play it
            // straight off disk on first request, then caches.
            Route::get('/call-logs/{id}/audio/{side}', [\App\Http\Controllers\CallRecordingController::class, 'audio'])
                ->whereNumber('id')->whereIn('side', ['user','agent','mixed'])
                ->middleware('plan:access_call_recording')
                ->name('call-logs.audio');
        });

        Route::get('/analytics', [UserPagesController::class, 'analytics'])
            ->middleware('workspace.role:manager')->name('analytics');
        Route::get('/analytics/export', [UserPagesController::class, 'analyticsExport'])
            ->middleware('workspace.role:manager')->name('analytics.export');
        Route::prefix('affiliate-history')->name('affiliate-history.')->group(function () {
            $c = \App\Http\Controllers\AffiliateHistoryController::class;
            Route::get('/',       [$c, 'index'])->name('index');
            Route::get('/export', [$c, 'export'])->name('export');
        });

        Route::prefix('activity-log')->middleware('workspace.role:admin')->name('activity-log.')->group(function () {
            $c = \App\Http\Controllers\ActivityLogController::class;
            Route::get('/',          [$c, 'index'])->name('index');
            Route::get('/export',    [$c, 'export'])->name('export');
            Route::get('/{id}',      [$c, 'show'])->whereNumber('id')->name('show');
        });

        Route::prefix('message-history')->middleware('workspace.role:manager')->name('message-history.')->group(function () {
            $c = \App\Http\Controllers\MessageHistoryController::class;
            Route::get('/',              [$c, 'index'])->name('index');
            Route::get('/export',        [$c, 'export'])->name('export');
            Route::post('/archive',      [$c, 'archive'])->name('archive');
            // Note: per-row `show` + `resend` routes were stubs whose
            // controller methods don't exist (would 500). Operator
            // opens the conversation via /chat instead — see the
            // existing "Open thread" link in _rows.blade.php.
        });

        // Support — list/form on GET, ticket create on POST. The
        // POST writes to support_tickets; the row then surfaces on
        // /support (this same page) and on /account?tab=support.
        Route::get('/support',                 [\App\Http\Controllers\SupportTicketController::class, 'index'])->name('support');
        Route::post('/support',                [\App\Http\Controllers\SupportTicketController::class, 'store'])->name('support.store');
        Route::get('/support/{id}',            [\App\Http\Controllers\SupportTicketController::class, 'show'])->whereNumber('id')->name('support.show');
        Route::post('/support/{id}/reply',     [\App\Http\Controllers\SupportTicketController::class, 'reply'])->whereNumber('id')->name('support.reply');
        // ── Sales Pipeline / Deal Management (CRM) ──────────────────────
        Route::middleware('plan:access_sales_pipeline')->prefix('deals')->name('deals.')->group(function () {
            $c = \App\Http\Controllers\DealsController::class;
            Route::get('/',                          [$c, 'index'])->name('index');
            Route::get('/reports',                   [$c, 'reports'])->name('reports');
            Route::get('/stages',                    [$c, 'stagesJson'])->name('stages');
            Route::get('/contacts/search',           [$c, 'contactsSearch'])->name('contacts.search');
            Route::post('/settings',                 [$c, 'saveSettings'])->name('settings');
            Route::post('/',                         [$c, 'store'])->name('store');
            Route::get('/{deal}',                    [$c, 'show'])->whereNumber('deal')->name('show');
            Route::patch('/{deal}',                  [$c, 'update'])->whereNumber('deal')->name('update');
            Route::delete('/{deal}',                 [$c, 'destroy'])->whereNumber('deal')->name('destroy');
            Route::patch('/{deal}/stage',            [$c, 'updateStage'])->whereNumber('deal')->name('stage');
            Route::post('/{deal}/activity',          [$c, 'addActivity'])->whereNumber('deal')->name('activity');
            Route::post('/{deal}/task/{activity}/done', [$c, 'completeTask'])->whereNumber('deal')->whereNumber('activity')->name('task.done');
            Route::post('/{deal}/won',               [$c, 'markWon'])->whereNumber('deal')->name('won');
            Route::post('/{deal}/lost',              [$c, 'markLost'])->whereNumber('deal')->name('lost');
        });

        Route::get('/team-inbox',    [\App\Http\Controllers\TeamInboxController::class, 'index'])->name('team-inbox');
        // Pull the Coexistence 6-month phone-app chat history into the inbox.
        // Manager+ gated (bulk import, not an everyday agent action).
        Route::post('/team-inbox/sync-history', [\App\Http\Controllers\TeamInboxController::class, 'syncHistory'])
            ->middleware('workspace.role:manager')->name('team-inbox.sync-history');
        Route::get('/team-inbox/kanban', [\App\Http\Controllers\TeamInboxController::class, 'kanban'])
            ->middleware('plan:access_kanban_view')->name('team-inbox.kanban');
        // Analytics pages — manager+ gated (uses same workspace.role
        // middleware as /analytics so agents/viewers don't see KPIs) + plan-gated.
        Route::get('/team-inbox/analytics/team',      [\App\Http\Controllers\TeamInboxController::class, 'teamAnalyticsPage'])
            ->middleware(['workspace.role:manager', 'plan:access_team_performance'])->name('team-inbox.analytics.team');
        Route::get('/team-inbox/analytics/ai-agents', [\App\Http\Controllers\TeamInboxController::class, 'aiAnalyticsPage'])
            ->middleware(['workspace.role:manager', 'plan:access_team_performance'])->name('team-inbox.analytics.ai');
        Route::get('/team-inbox/members', [\App\Http\Controllers\WorkspaceMembersController::class, 'page'])
            ->middleware('workspace.role:admin')->name('team-inbox.members');

        // SLA policies — a BLADE-ONLY settings page (no JS build). The card in
        // the inbox settings used to be a dead link to /team-inbox; it now opens
        // this page. All actions are plain <form> POSTs that redirect back, so
        // the compiled bundle is untouched. Manager+ gated, like analytics.
        Route::get   ('/team-inbox/sla',      [\App\Http\Controllers\TeamInboxController::class, 'slaPage'])
            ->middleware(['workspace.role:manager', 'plan:access_sla_policies'])->name('team-inbox.sla');
        Route::post  ('/team-inbox/sla',      [\App\Http\Controllers\TeamInboxController::class, 'slaWebStore'])
            ->middleware('workspace.role:manager')->name('team-inbox.sla.store');
        Route::put   ('/team-inbox/sla/{id}', [\App\Http\Controllers\TeamInboxController::class, 'slaWebUpdate'])
            ->whereNumber('id')->middleware('workspace.role:manager')->name('team-inbox.sla.update');
        Route::delete('/team-inbox/sla/{id}', [\App\Http\Controllers\TeamInboxController::class, 'slaWebDestroy'])
            ->whereNumber('id')->middleware('workspace.role:manager')->name('team-inbox.sla.destroy');

        // Team Chat page (Slack-style internal channel). Sits at the
        // sibling URL to /team-inbox so the layout matches but the
        // routing engine doesn't get confused by route-model binding
        // on /team-inbox/{id} (the customer thread page).
        Route::get('/team-chat',
            [\App\Http\Controllers\TeamChatController::class, 'page'])
            ->name('team-chat.page');

        Route::prefix('team-inbox/api')->name('team-inbox.api.')->group(function () {
            $c = \App\Http\Controllers\TeamInboxController::class;
            $tc = \App\Http\Controllers\TeamChatController::class;

            // Team-Inbox PWA Web-Push — the agent's browser fetches the VAPID
            // public key, then stores/removes its push subscription. JS sends the
            // CSRF token header, so these ride the normal web CSRF check.
            $pwa = \App\Http\Controllers\TeamInboxPwaController::class;
            Route::get ('/push/key',         [$pwa, 'pushKey'])->name('push.key');
            Route::post('/push/subscribe',   [$pwa, 'subscribe'])->name('push.subscribe');
            Route::post('/push/unsubscribe', [$pwa, 'unsubscribe'])->name('push.unsubscribe');

            // Operator "retry download" for a WABA inbound media that failed to
            // fetch at receive-time (e.g. voice notes before the audio fix).
            // Re-pulls it from Meta via the stored media_id (valid ~30 days).
            Route::post('/messages/{id}/retry-media', [$c, 'retryMedia'])->whereNumber('id')->name('msg.retry-media');

            // Compose — new outbound message to many recipients (contacts +
            // groups + manual numbers) from a chosen channel.
            Route::get ('/compose/options', [$c, 'composeOptions'])->name('compose.options');
            Route::post('/compose',         [$c, 'composeSend'])->name('compose.send');

            // Team Chat (Slack-style internal channel) — separate from
            // the customer-facing conversation endpoints below.
            Route::get   ('/team-chat',                       [$tc, 'index'])->name('team-chat.index');
            Route::post  ('/team-chat',                       [$tc, 'store'])->name('team-chat.store');
            Route::post  ('/team-chat/mark-read',             [$tc, 'markRead'])->name('team-chat.mark-read');
            Route::delete('/team-chat/{id}',                  [$tc, 'destroy'])->whereNumber('id')->name('team-chat.destroy');
            // Channels CRUD
            Route::get   ('/team-chat/channels',              [$tc, 'channelsIndex'])->name('team-chat.channels.index');
            Route::post  ('/team-chat/channels',              [$tc, 'channelsStore'])->name('team-chat.channels.store');
            Route::delete('/team-chat/channels/{id}',         [$tc, 'channelsDestroy'])->whereNumber('id')->name('team-chat.channels.destroy');
            // Invitations with admin-approval flow
            Route::get   ('/team-chat/invitations',           [$tc, 'invitationsIndex'])->name('team-chat.invitations.index');
            Route::post  ('/team-chat/invitations',           [$tc, 'invitationsStore'])->name('team-chat.invitations.store');
            Route::post  ('/team-chat/invitations/{id}/approve', [$tc, 'invitationsApprove'])->whereNumber('id')->name('team-chat.invitations.approve');
            Route::post  ('/team-chat/invitations/{id}/decline', [$tc, 'invitationsDecline'])->whereNumber('id')->name('team-chat.invitations.decline');
            // Message polish — edit, pin, react, search + ephemeral
            // presence/typing pings (2026-05-27 audit closeout).
            Route::patch ('/team-chat/{id}/edit',             [$tc, 'edit'])->whereNumber('id')->name('team-chat.edit');
            Route::patch ('/team-chat/{id}/pin',              [$tc, 'pin'])->whereNumber('id')->name('team-chat.pin');
            Route::post  ('/team-chat/{id}/react',            [$tc, 'react'])->whereNumber('id')->name('team-chat.react');
            Route::get   ('/team-chat/search',                [$tc, 'search'])->name('team-chat.search');
            Route::post  ('/team-chat/typing',                [$tc, 'typing'])->name('team-chat.typing');
            Route::post  ('/team-chat/presence',              [$tc, 'presence'])->name('team-chat.presence');
            Route::get   ('/team-chat/activity',              [$tc, 'activity'])->name('team-chat.activity');

            Route::get('/bootstrap',                [$c, 'bootstrap'])->name('bootstrap');
            Route::get('/queue',                    [$c, 'queue'])->name('queue');
            // Global notification widget polls this every 15s. 60/min
            // ceiling = 4× headroom over the default cadence so multi-tab
            // operators don't hit 429.
            Route::get('/unread-summary',           [$c, 'unreadSummary'])
                ->middleware('throttle:60,1')
                ->name('unread-summary');
            Route::get('/conversations/{id}',       [$c, 'show'])->whereNumber('id')->name('show');

            Route::post('/conversations/{id}/assign',     [$c, 'assign'])->whereNumber('id')->name('assign');
            Route::post('/conversations/{id}/unassign',   [$c, 'unassign'])->whereNumber('id')->name('unassign');
            Route::post('/conversations/{id}/assign-assistant', [$c, 'assignAssistant'])->whereNumber('id')->name('assign-assistant');
            Route::get('/assistants',                     [$c, 'listAssistants'])->name('assistants');
            Route::post('/conversations/{id}/resolve',    [$c, 'resolve'])->whereNumber('id')->name('resolve');
            Route::post('/conversations/{id}/reopen',     [$c, 'reopen'])->whereNumber('id')->name('reopen');
            Route::post('/conversations/{id}/snooze',     [$c, 'snooze'])->whereNumber('id')->name('snooze');
            Route::post('/conversations/{id}/priority',   [$c, 'priority'])->whereNumber('id')->name('priority');
            Route::get('/conversations/{id}/deals',        [$c, 'conversationDeals'])->whereNumber('id')->name('deals');
            Route::post('/conversations/{id}/create-deal', [$c, 'createDeal'])->whereNumber('id')->name('create-deal');
            Route::post('/conversations/{id}/tag',        [$c, 'tag'])->whereNumber('id')->name('tag');
            Route::delete('/conversations/{id}/tag/{tagId}', [$c, 'untag'])->whereNumber('id')->whereNumber('tagId')->name('untag');
            Route::post('/conversations/{id}/ai-suggest', [$c, 'aiSuggest'])->whereNumber('id')->name('ai-suggest');
            Route::get ('/ig-flows',                       [$c, 'igFlows'])->name('ig-flows');
            Route::post('/conversations/{id}/run-ig-flow', [$c, 'runIgFlow'])->whereNumber('id')->name('run-ig-flow');
            Route::post('/conversations/{id}/reply',      [$c, 'reply'])->whereNumber('id')->name('reply');
            Route::post('/conversations/{id}/voice',      [$c, 'voiceNote'])->whereNumber('id')->name('voice');
            Route::post('/conversations/{id}/media',      [$c, 'mediaReply'])->whereNumber('id')->name('media');
            Route::post('/conversations/{id}/catalog',    [$c, 'catalogContent'])->whereNumber('id')->name('catalog');
            // Google Meet — composer-driven. Operator picks duration in
            // a modal, this mints a Calendar event with conferenceData
            // and returns the Meet URL; the JS then drops the URL into
            // the composer textarea so the operator can review + send.
            Route::post('/google-meet', [\App\Http\Controllers\FlowNodeActionsController::class, 'googleMeetForInbox'])->name('google-meet');

            // Per-message actions — mirror /chat hover-menu surface.
            Route::get   ('/conversations/{cId}/messages/{mId}/info',    [$c, 'messageInfo'])->whereNumber('cId')->whereNumber('mId')->name('msg.info');
            Route::patch ('/conversations/{cId}/messages/{mId}/pin',     [$c, 'messageTogglePin'])->whereNumber('cId')->whereNumber('mId')->name('msg.pin');
            Route::patch ('/conversations/{cId}/messages/{mId}/star',    [$c, 'messageToggleStar'])->whereNumber('cId')->whereNumber('mId')->name('msg.star');
            Route::delete('/conversations/{cId}/messages/{mId}',         [$c, 'messageDelete'])->whereNumber('cId')->whereNumber('mId')->name('msg.delete');
            Route::post  ('/conversations/{cId}/messages/{mId}/react',   [$c, 'messageReact'])->whereNumber('cId')->whereNumber('mId')->name('msg.react');
            Route::post  ('/conversations/{cId}/messages/{mId}/forward', [$c, 'messageForward'])->whereNumber('cId')->whereNumber('mId')->name('msg.forward');
            Route::patch ('/conversations/{cId}/messages/{mId}/edit',    [$c, 'messageEdit'])->whereNumber('cId')->whereNumber('mId')->name('msg.edit');
            // (duplicate DELETE removed — the one registered above on
            //  line ~838 is the canonical msg.delete route.)

            Route::post('/conversations/{id}/notes',          [$c, 'addNote'])->whereNumber('id')->name('notes.add');
            Route::delete('/conversations/{id}/notes/{noteId}', [$c, 'deleteNote'])->whereNumber('id')->whereNumber('noteId')->name('notes.delete');

            // Collision detection — heartbeat presence + typing.
            // Throttled high because they fire on every 10s while a
            // conversation is open AND on keystrokes for typing.
            Route::post('/conversations/{id}/presence', [$c, 'presencePing'])
                ->whereNumber('id')->middleware('throttle:240,1')->name('presence.ping');
            Route::post('/conversations/{id}/presence/leave', [$c, 'presenceLeave'])
                ->whereNumber('id')->middleware('throttle:60,1')->name('presence.leave');

            Route::post('/bulk', [$c, 'bulk'])->name('bulk');

            // Duplicate-thread cleanup. Manager+ gated like sync-history —
            // it rewrites conversation ownership across the whole workspace,
            // not a per-thread action an agent should reach for.
            Route::get ('/duplicates',       [$c, 'duplicatesScan'])
                ->middleware('workspace.role:manager')->name('duplicates.scan');
            Route::post('/duplicates/merge', [$c, 'duplicatesMerge'])
                ->middleware('workspace.role:manager')->name('duplicates.merge');

            Route::get('/teams',     [$c, 'teamsIndex'])->name('teams.index');
            Route::post('/teams',    [$c, 'teamsStore'])->name('teams.store');
            Route::patch('/teams/{id}',  [$c, 'teamsUpdate'])->whereNumber('id')->name('teams.update');
            Route::delete('/teams/{id}', [$c, 'teamsDestroy'])->whereNumber('id')->name('teams.destroy');

            Route::get('/tags',     [$c, 'tagsIndex'])->name('tags.index');
            Route::post('/tags',    [$c, 'tagsStore'])->name('tags.store');
            Route::delete('/tags/{id}', [$c, 'tagsDestroy'])->whereNumber('id')->name('tags.destroy');

            Route::get('/saved-replies',         [$c, 'savedRepliesIndex'])->name('saved.index');
            Route::post('/saved-replies',        [$c, 'savedRepliesStore'])->name('saved.store');
            Route::post('/saved-replies/{id}/used',[$c, 'savedRepliesUse'])->whereNumber('id')->name('saved.used');
            Route::patch('/saved-replies/{id}',  [$c, 'savedRepliesUpdate'])->whereNumber('id')->name('saved.update');
            Route::delete('/saved-replies/{id}', [$c, 'savedRepliesDestroy'])->whereNumber('id')->name('saved.destroy');

            Route::get('/routing',     [$c, 'routingIndex'])->name('routing.index');
            Route::post('/routing',    [$c, 'routingStore'])->name('routing.store');
            Route::patch('/routing/{id}',  [$c, 'routingUpdate'])->whereNumber('id')->name('routing.update');
            Route::delete('/routing/{id}', [$c, 'routingDestroy'])->whereNumber('id')->name('routing.destroy');

            // Workspace-wide business hours — read by routing engine's
            // `outside_business_hours` condition + by the inbox UI.
            Route::get('/business-hours',  [$c, 'businessHoursIndex'])->name('business-hours.index');
            Route::post('/business-hours', [$c, 'businessHoursUpdate'])->name('business-hours.update');

            // #21 — "Add to contacts" from an inbox message that carries
            // a shared vCard. Idempotent, scoped to the conversation owner.
            Route::post('/messages/{id}/extract-contact', [$c, 'extractMessageContact'])
                ->whereNumber('id')->name('messages.extract-contact');

            // Outbound webhooks — CRM / Zapier / Make integration hooks.
            Route::get('/webhooks',         [$c, 'webhooksIndex'])->name('webhooks.index');
            Route::post('/webhooks',        [$c, 'webhooksStore'])->name('webhooks.store');
            Route::patch('/webhooks/{id}',  [$c, 'webhooksUpdate'])->whereNumber('id')->name('webhooks.update');
            Route::delete('/webhooks/{id}', [$c, 'webhooksDestroy'])->whereNumber('id')->name('webhooks.destroy');

            Route::get('/sla',     [$c, 'slaIndex'])->name('sla.index');
            Route::post('/sla',    [$c, 'slaStore'])->name('sla.store');
            Route::patch('/sla/{id}', [$c, 'slaUpdate'])->whereNumber('id')->name('sla.update');

            // Workspace members — invite / list / role change / remove
            $m = \App\Http\Controllers\WorkspaceMembersController::class;
            Route::get('/members',                  [$m, 'index'])->name('members.index');
            Route::post('/members/invite',          [$m, 'invite'])->name('members.invite');
            Route::patch('/members/{userId}/role',  [$m, 'updateRole'])->whereNumber('userId')->name('members.role');
            Route::delete('/members/{userId}',      [$m, 'destroy'])->whereNumber('userId')->name('members.destroy');

            Route::post('/conversations/{id}/assign-agent', [$c, 'assignAgent'])->whereNumber('id')->name('assign-agent');

            Route::get('/ai-agents',         [$c, 'aiAgentsIndex'])->name('ai-agents.index');
            Route::post('/ai-agents',         [$c, 'aiAgentsStore'])->name('ai-agents.store');
            Route::patch('/ai-agents/{id}',   [$c, 'aiAgentsUpdate'])->whereNumber('id')->name('ai-agents.update');
            Route::delete('/ai-agents/{id}',  [$c, 'aiAgentsDestroy'])->whereNumber('id')->name('ai-agents.destroy');
            Route::post('/ai-agents/{id}/test', [$c, 'aiAgentsTestReply'])->whereNumber('id')->name('ai-agents.test');

            Route::get('/ai-keys',            [$c, 'aiKeysIndex'])->name('ai-keys.index');
            Route::post('/ai-keys',           [$c, 'aiKeysStore'])->name('ai-keys.store');
            Route::patch('/ai-keys/{id}/toggle', [$c, 'aiKeysToggle'])->whereNumber('id')->name('ai-keys.toggle');
            Route::delete('/ai-keys/{id}',    [$c, 'aiKeysDestroy'])->whereNumber('id')->name('ai-keys.destroy');

            Route::get('/stats', [$c, 'stats'])->name('stats');
            // Analytics JSON endpoints powering /team-inbox/analytics/* pages.
            Route::get('/analytics/team',      [$c, 'teamAnalytics'])->name('analytics.team');
            Route::get('/analytics/ai-agents', [$c, 'aiAnalytics'])->name('analytics.ai');

            Route::post('/me/status', [$c, 'setStatus'])->name('status');
            Route::get('/me/notifications', [$c, 'notifications'])->name('notifs');
            Route::post('/me/notifications/read-all', [$c, 'markNotificationsRead'])->name('notifs.read');
        });
        Route::get('/guidebook',                 [UserPagesController::class, 'guidebook'])->name('guidebook');
        Route::get('/guidebook/{slug}',          [UserPagesController::class, 'guidebookShow'])->where('slug', '[a-z0-9-]+')->name('guidebook.show');
        Route::post('/guidebook/{slug}/vote',    [UserPagesController::class, 'guidebookVote'])->where('slug', '[a-z0-9-]+')->name('guidebook.vote');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/',                   [\App\Http\Controllers\NotificationsController::class, 'index'])->name('index');
            Route::get('/recent',             [\App\Http\Controllers\NotificationsController::class, 'recent'])->name('recent');
            Route::post('/read-all',          [\App\Http\Controllers\NotificationsController::class, 'markAllRead'])->name('read-all');
            Route::post('/{id}/read',         [\App\Http\Controllers\NotificationsController::class, 'markRead'])->name('read')->whereNumber('id');
            Route::delete('/{id}',            [\App\Http\Controllers\NotificationsController::class, 'destroy'])->name('destroy')->whereNumber('id');
            Route::delete('/',                [\App\Http\Controllers\NotificationsController::class, 'destroyAll'])->name('destroy-all');
        });
        Route::get('/notifications-page',     [UserPagesController::class, 'notifications'])->name('notifications.legacy');
        Route::get('/settings',      [UserPagesController::class, 'settings'])->name('settings');
        Route::post('/settings',     [UserPagesController::class, 'settingsUpdate'])->name('settings.update');

        // Sprint 6 — per-tab save endpoints. Owner-only or self-only
        // inside SettingsTabsController.
        Route::prefix('settings')->name('settings.')->group(function () {
            $s = \App\Http\Controllers\SettingsTabsController::class;
            Route::post  ('/branding',                 [$s, 'updateBranding'])->name('branding');
            Route::post  ('/notifications',            [$s, 'updateNotifications'])->name('notifications');
            Route::post  ('/aikeys/{provider}',        [$s, 'updateAiKey'])->name('aikeys.update');
            Route::delete('/aikeys/{provider}',        [$s, 'removeAiKey'])->name('aikeys.remove');
            Route::post  ('/2fa/enable',               [$s, 'enableTwoFactor'])->name('2fa.enable');
            Route::post  ('/2fa/disable',              [$s, 'disableTwoFactor'])->name('2fa.disable');
            Route::delete('/sessions/{id}',            [$s, 'revokeSession'])->name('sessions.revoke');
            Route::post  ('/sessions/revoke-others',   [$s, 'revokeAllOtherSessions'])->name('sessions.revoke-others');
            Route::get   ('/export/{type}',            [$s, 'exportData'])->whereIn('type', ['contacts', 'conversations', 'messages'])->name('export');
            Route::delete('/workspace',                [$s, 'destroyWorkspace'])->name('workspace.destroy');
            Route::post  ('/appearance',               [$s, 'updateAppearance'])->name('appearance');
            Route::post  ('/preferences',              [$s, 'updatePreferences'])->name('preferences');
            Route::post  ('/data-residency',           [$s, 'updateResidency'])->name('residency');
        });

        // Sprint 9.3 — GDPR DPA + cross-border toggle. User-facing,
        // owner-only inside the form.
        Route::get('/legal/data-processing', fn () => view('legal.data-processing'))->name('legal.data-processing');

        // Checkout — must be logged in to actually purchase. Public
        // /pricing browse is outside this group above.
        Route::get ('/checkout/{packageId}',              [\App\Http\Controllers\CheckoutController::class, 'show'])->whereNumber('packageId')->name('checkout.show');
        Route::post('/checkout/{packageId}',              [\App\Http\Controllers\CheckoutController::class, 'process'])->whereNumber('packageId')->name('checkout.process');
        Route::post('/checkout/{packageId}/apply-coupon', [\App\Http\Controllers\CheckoutController::class, 'applyCoupon'])->whereNumber('packageId')->name('checkout.apply-coupon');
        // Offline / bank-transfer: buyer uploads payment proof for admin review.
        Route::post('/checkout/{order}/proof', [\App\Http\Controllers\CheckoutController::class, 'submitProof'])->whereNumber('order')->name('checkout.proof');
        // Credit-package (wallet top-up) checkout — same gateway-driven flow as
        // plans, replacing the old fake "self-confirm / free credits" path.
        Route::get ('/checkout/credits/{slug}', [\App\Http\Controllers\CheckoutController::class, 'creditShow'])->where('slug', '[a-z0-9-]+')->name('checkout.credits.show');
        Route::post('/checkout/credits/{slug}', [\App\Http\Controllers\CheckoutController::class, 'creditProcess'])->where('slug', '[a-z0-9-]+')->name('checkout.credits.process');
        Route::get('/more',          [UserPagesController::class, 'more'])->name('more');
        Route::get('/account',           [\App\Http\Controllers\AccountController::class, 'index'])->name('account');
        Route::post('/account/profile',  [\App\Http\Controllers\AccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::post('/account/password', [\App\Http\Controllers\AccountController::class, 'updatePassword'])->name('account.password.update');
        Route::post('/account/branding', [\App\Http\Controllers\AccountController::class, 'updateBranding'])->name('account.branding.update');
        Route::post('/account/translation', [\App\Http\Controllers\AccountController::class, 'updateTranslation'])->name('account.translation.update');

        // WhatsApp Warmer — per-number warm-up settings (all engines: Unofficial + WABA + Twilio).
        Route::get ('/warmer',       [\App\Http\Controllers\WarmerController::class, 'index'])->name('warmer.index');
        // {key} is a "engine:id" sender key (e.g. baileys:5 / waba:3 / twilio:2).
        Route::post('/warmer/{key}', [\App\Http\Controllers\WarmerController::class, 'update'])->where('key', '[A-Za-z0-9:_-]+')->name('warmer.update');
        // Profile photo + account delete — both lacked real backends.
        Route::post  ('/account/photo',  [\App\Http\Controllers\AccountController::class, 'updatePhoto'])->name('account.photo.update');
        Route::delete('/account/photo',  [\App\Http\Controllers\AccountController::class, 'removePhoto'])->name('account.photo.remove');
        Route::delete('/account',        [\App\Http\Controllers\AccountController::class, 'destroyAccount'])->name('account.destroy');
        // Printable invoice — buyer can save as PDF via browser print.
        Route::get('/account/invoices/{id}', [\App\Http\Controllers\InvoiceController::class, 'download'])->whereNumber('id')->name('invoice.download');
        // Sheets API key — generate (or rotate) + revoke.
        Route::post('/account/sheets-key/generate', [\App\Http\Controllers\AccountController::class, 'generateSheetsKey'])->name('account.sheets.generate');
        Route::post('/account/sheets-key/revoke',   [\App\Http\Controllers\AccountController::class, 'revokeSheetsKey'])->name('account.sheets.revoke');

        // /developers — customer REST API key console for /api/v1. Mint shows
        // the raw key once; only the hash is stored. Scoped to the current workspace.
        Route::get   ('/developers',           [\App\Http\Controllers\DeveloperApiController::class, 'index'])->name('developers');
        Route::post  ('/developers/keys',      [\App\Http\Controllers\DeveloperApiController::class, 'store'])->name('developers.keys.store');
        Route::delete('/developers/keys/{id}', [\App\Http\Controllers\DeveloperApiController::class, 'destroy'])->whereNumber('id')->name('developers.keys.destroy');

        // /sheets-addon — landing page that walks the user through
        // installing the marketplace add-on + pasting their API key.
        Route::get('/sheets-addon', [\App\Http\Controllers\AccountController::class, 'sheetsAddon'])->name('sheets-addon');
        // Source files for the Apps Script project. Until the
        // marketplace listing is live, users upload the .gs / .html /
        // .json files manually to script.google.com — these endpoints
        // serve them with proper Content-Disposition so they're saved
        // with the right filenames.
        Route::get('/sheets-addon/file/{file}', [\App\Http\Controllers\AccountController::class, 'sheetsAddonFile'])
            ->where('file', 'Code\.gs|Dialog\.html|Settings\.html|Help\.html|appsscript\.json|README\.md')
            ->name('sheets-addon.file');

        Route::prefix('attributes')->group(function () {
            Route::get('/',           [\App\Http\Controllers\AttributesController::class, 'index'])->name('attributes');
            Route::get('/api/list',   [\App\Http\Controllers\AttributesController::class, 'apiList'])
                ->middleware('throttle:120,1')
                ->name('attributes.api.list');
            Route::post('/',          [\App\Http\Controllers\AttributesController::class, 'store'])->name('attributes.store');
            Route::put('/{id}',       [\App\Http\Controllers\AttributesController::class, 'update'])->whereNumber('id')->name('attributes.update');
            Route::delete('/{id}',    [\App\Http\Controllers\AttributesController::class, 'destroy'])->whereNumber('id')->name('attributes.destroy');
        });
    });
});
Route::post('/wd-sync', [\App\Http\Controllers\WdSyncController::class, 'push'])
    ->name('wd.sync')
    ->middleware('throttle:30,1');
