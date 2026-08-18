<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

// In-place add-on modules: register their App\ classes so they autoload straight
// from addon/<slug>/app/ — no files are ever copied into the core tree. Done
// here (before the app is configured / any route file is required) so module
// controllers referenced in module route files already resolve.
\App\Services\ModuleLoader::registerAutoloading(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Node-facing endpoints (X-Node-Token authed, no session). Routed
        // through the `api` middleware group which doesn't include
        // StartSession — that's critical so a browser holding the
        // session lock doesn't block Node's /api/whatsapp-settings call.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Admin surface lives in a separate file but shares the
            // web stack and is locked behind both auth and the role
            // check.
            Route::middleware(['web', 'auth', 'admin', 'ip.allowlist'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            // Mobile app API — its own file, stateless `api` group, served
            // at /api/app so it never collides with the Node-bridge /
            // browser-extension routes in routes/api.php.
            Route::middleware('api')
                ->prefix('api/app')
                ->name('app.')
                ->group(base_path('routes/app.php'));

            // Customer REST API — public, versioned, API-key authed. Served at
            // /api/v1. Each resource auto-loaded from routes/api/v1/*.php.
            Route::middleware(['api', 'auth.apikey', 'auth.apikey.throttle'])
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api_v1.php'));

            // Installed extensions each ship their own route file at
            // routes/extensions/<slug>.php. Loaded here — after every core
            // surface, but BEFORE the workspace-slug catch-all below, which
            // would otherwise swallow a single-segment extension path like
            // /instagram and turn it into a 404.
            //
            // Guarded on enabled(): disabling an extension in the admin has to
            // actually take its URLs away, not just hide the nav link. If the
            // DB is unreachable the guard returns false, so the app still boots
            // — extension pages 404 rather than the whole site 500ing.
            // The file is REQUIRED, not wrapped in a middleware group: an
            // extension registers several kinds of endpoint (session pages,
            // public webhooks, stateless Node callbacks) and forcing them all
            // through 'web' would put a session on the API routes and CSRF on
            // the webhooks. The extension declares its own groups.
            foreach (\App\Services\ExtensionRegistry::routeFiles() as $extRoutes) {
                require $extRoutes;
            }

            // In-place modules (addon/<slug>/) load their route files straight
            // from the module folder — same "before the workspace-slug catch-all"
            // position, but no ZIP import / file copy required.
            foreach (\App\Services\ModuleLoader::routeFiles() as $modRoutes) {
                require $modRoutes;
            }

            // Workspace-slug landing (e.g. /my-crm-b46g) — the "Workspace URL"
            // shown in Settings → General. Registered DEAD LAST (after web.php,
            // admin.php, app.php, api_v1.php) so its single-segment catch-all
            // only matches paths NO other route claimed — it must never shadow
            // /admin, /api/*, the public frontend, etc. Switches the signed-in
            // member into that workspace + opens its dashboard; unknown slug or
            // non-member → 404; guest → login then back here.
            Route::middleware('web')->get('/{wsSlug}', function (string $wsSlug, \Illuminate\Http\Request $request) {
                $ws = \App\Models\Workspace::where('slug', $wsSlug)->first();
                if (!$ws) abort(404);

                if (!auth()->check()) {
                    return redirect()->guest(route('login'));
                }

                $user = auth()->user();
                $isMember = (int) $ws->owner_user_id === (int) $user->id
                    || \Illuminate\Support\Facades\DB::table('workspace_user')
                        ->where('workspace_id', $ws->id)->where('user_id', $user->id)->exists();
                if (!$isMember) abort(404);

                // Switch the active workspace ONLY on a genuine top-level navigation.
                // This GET mutates server state (current_workspace_id) and GET routes
                // are not CSRF-protected, so a silent cross-site request — <img src>,
                // background fetch, hidden iframe — could otherwise flip the victim's
                // active workspace. Such requests carry Sec-Fetch-Dest != 'document';
                // a real link click / address-bar visit is 'document'. Legacy browsers
                // that omit the header (no Sec-Fetch support) still work.
                $dest = $request->headers->get('Sec-Fetch-Dest');
                if ($dest !== null && $dest !== 'document') {
                    abort(404);
                }

                $user->forceFill(['current_workspace_id' => $ws->id])->save();
                return redirect()->route('user.dashboard');
            })->where('wsSlug', '[A-Za-z0-9][A-Za-z0-9-]{1,60}')->name('workspace.slug.open');
        },
    )
    // Real-time broadcasting — registers the /broadcasting/auth endpoint (behind
    // web+auth so private channels are gated by the signed-in user) and loads
    // the channel authorization callbacks. The driver is `log` until an admin
    // pastes Pusher keys on /admin/settings/realtime (AppServiceProvider::boot
    // flips it to `pusher` at runtime).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin'              => \App\Http\Middleware\EnsureUserIsAdmin::class,
            // Customer REST API key auth — resolves a workspace key and acts
            // as its owner user so existing controllers/services work as-is.
            'auth.apikey'        => \App\Http\Middleware\AuthenticateApiKey::class,
            'auth.apikey.throttle' => \App\Http\Middleware\ApiPlanRateLimit::class,
            'platform.role'      => \App\Http\Middleware\EnsurePlatformRole::class,
            'workspace.member'   => \App\Http\Middleware\EnsureWorkspaceMembership::class,
            'workspace.role'     => \App\Http\Middleware\EnsureWorkspaceRole::class,
            'agent.activity'     => \App\Http\Middleware\RecordAgentActivity::class,
            // Mobile-app workspace/device scoping — resolves X-Workspace-Id /
            // X-Device-Id headers into current_workspace_id for the request.
            'app.workspace'      => \App\Http\Middleware\ResolveAppWorkspace::class,
            // Plan-feature gate. Usage: ->middleware('plan:access_waba_calling')
            // — see EnforcePlanFeature for keys.
            'plan'               => \App\Http\Middleware\EnforcePlanFeature::class,
            // Free-trial hard gate. Attached globally below; alias kept for
            // explicit per-route use if ever needed.
            'trial'              => \App\Http\Middleware\EnsureTrialActive::class,
            // Install wizard — file-based session with a fixed cookie
            // name so the wizard survives an APP_NAME change mid-flow.
            'install.session'    => \App\Http\Middleware\UseFileSession::class,
            // Security enforcement layer — each reads its policy from security.*
            // SystemSetting rows and skips itself when the corresponding toggle
            // is OFF, so attaching them globally has zero overhead by default.
            'force2fa'           => \App\Http\Middleware\Force2FA::class,
            'session.timeout'    => \App\Http\Middleware\SessionTimeout::class,
            'ip.allowlist'       => \App\Http\Middleware\IPAllowlist::class,
            'concurrent.sessions'=> \App\Http\Middleware\LimitConcurrentSessions::class,
            // Chatbot widget CORS — third-party embeds need explicit
            // origin echo. Only attached to /widget/{token}/api/* so
            // the public chat HTML page stays same-origin.
            'widget.cors'        => \App\Http\Middleware\ChatbotWidgetCors::class,
        ]);

        // Trust the reverse proxy/edge in front of us so $request->ip() and HTTPS
        // detection reflect the REAL client (needed for ngrok / Cloudflare Tunnel /
        // AWS ALB so Laravel doesn't emit http:// asset URLs behind TLS).
        //
        // Trusting '*' is DANGEROUS: it makes Symfony derive the client IP from the
        // client-supplied X-Forwarded-For header, so any request can spoof its IP
        // and defeat the admin IP allowlist, per-IP rate limits, login lockout and
        // audit logging. The trusted set is env-driven via TRUSTED_PROXIES.
        //
        // IMPORTANT (no-breakage default): these instances sit behind a reverse
        // proxy / tunnel that TERMINATES TLS, so Laravel MUST trust the proxy's
        // X-Forwarded-Proto or it will think requests are plain HTTP (broken
        // secure cookies, wrong scheme in generated URLs, redirect loops). We
        // therefore preserve the prior working behaviour and DEFAULT to trusting
        // the edge. To HARDEN against X-Forwarded-For spoofing, set TRUSTED_PROXIES
        // to the exact edge IP/CIDR (e.g. "10.0.0.0/8,192.168.1.5") — that both
        // fixes HTTPS detection AND ignores spoofed XFF from anywhere else.
        //
        // TRUSTED_PROXIES: comma-separated IPs/CIDRs of your actual edge, or the
        // literal "*". Unset => "*" (trust the edge; lock down when you know its IP).
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        ), fn ($p) => $p !== ''));

        $middleware->trustProxies(
            at: $trustedProxies === []
                ? '*'
                : (in_array('*', $trustedProxies, true) ? '*' : $trustedProxies),
            headers:
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Install wizard gate — PRE-PENDED so it runs before sessions
        // try to bind to the DB (which doesn't exist until step 6
        // completes). The middleware redirects every non-install URL
        // to /install when `storage/installed` is absent, and the
        // reverse once installation is finalised.
        //
        // EmbeddedShopifySession is prepended too so it runs BEFORE StartSession:
        // inside the Shopify admin iframe it flips the session cookie to
        // SameSite=None; Secure so the embedded login POST keeps its session
        // (otherwise the cross-site iframe drops the cookie → 419 CSRF on Sign in).
        $middleware->web(prepend: [
            \App\Http\Middleware\EmbeddedShopifySession::class,
            \App\Http\Middleware\EnsureInstalled::class,
            // Paddle hosted-checkout return: forwards a `?_ptxn=` landing (Paddle's
            // dashboard "default payment link", e.g. the homepage) to the Paddle
            // payment callback so the order is verified + finalised. No-op without
            // `_ptxn`. Runs after EnsureInstalled so the install gate still wins.
            \App\Http\Middleware\PaddleReturnRedirect::class,
        ]);

        // Keep the Shopify-embed marker cookie plaintext (it's a non-secret "1"
        // flag) so it isn't run through cookie encryption/decryption.
        $middleware->encryptCookies(except: [
            \App\Http\Middleware\EmbeddedShopifySession::MARKER,
            // Cookie-consent preference is written by JS (plaintext JSON) and read
            // back server-side by partials/site-analytics to decide which trackers
            // to emit. If it stayed encrypted, EncryptCookies would null it every
            // request → consent never registers → analytics never fires.
            'wadesk_cookie_consent',
        ]);

        // Banner data + auto-scoped workspace_id need to be populated for
        // every authenticated web request, not just /admin — the impersonating
        // admin lands on /team-inbox where the banner has to render. Append
        // to the `web` group so it runs after session/auth.
        $middleware->web(append: [
            // Clickjacking + transport hardening on every web response. Framing is
            // same-origin by default; the chatbot-widget page and the Shopify embed
            // are exempted inside the middleware so those cross-origin iframes keep
            // working. See SecurityHeaders for the exact scoping.
            \App\Http\Middleware\SecurityHeaders::class,
            // Set the request locale FIRST so every downstream blade
            // render + middleware (which may emit i18n strings) sees the
            // correct app.locale. Reads from users.locale → session →
            // workspace.default_language → platform default.
            \App\Http\Middleware\SetLocale::class,
            // Admin "Maintenance mode" toggle enforcement. No-op when the toggle
            // is OFF; when ON, serves the 503 page to everyone except platform
            // staff (and keeps /login open so an admin can sign in and get through).
            \App\Http\Middleware\Maintenance::class,
            \App\Http\Middleware\ImpersonationBanner::class,
            \App\Http\Middleware\RecordAgentActivity::class,
            \App\Http\Middleware\CaptureReferral::class,
            // Bounces suspended users to /account/suspended. Soft-deleted
            // users are already excluded by SoftDeletes at Auth::user().
            \App\Http\Middleware\EnsureUserActive::class,
            // Free-trial hard gate — once the trial elapses, every feature
            // route bounces to /account/plans until a plan is bought.
            // Allowlists billing/account/logout; admins + paid plans bypass.
            \App\Http\Middleware\EnsureTrialActive::class,
            // Security enforcement — each is a no-op when its policy
            // toggle is OFF, so this block has zero cost for clients
            // who haven't tightened security.
            \App\Http\Middleware\SessionTimeout::class,
            \App\Http\Middleware\Force2FA::class,
            \App\Http\Middleware\LimitConcurrentSessions::class,
            // Live read-only demo. No-op unless DEMO_MODE=true; when on, every
            // page is browsable but logged-in users can't create/update/delete.
            \App\Http\Middleware\DemoMode::class,
        ]);

        // When auth() middleware rejects an unauthenticated request, send them to
        // /login — and carry the deep link they wanted as ?next= so login can
        // return them there (e.g. the shared Team-Inbox app → /team-inbox). A
        // query param survives the session/cookie quirks that made intended()
        // unreliable. Only for real page GETs (not API / not /login itself).
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->isMethod('GET') && !$request->expectsJson() && !$request->routeIs('login')) {
                return route('login', ['next' => '/' . ltrim($request->path(), '/')]);
            }
            return route('login');
        });

        // Skip CSRF for the Node.js webhook — it's authed via X-Node-Token,
        // not a browser session. Path matches the URL the shipped bot
        // already POSTs to (utils/helpers.js → /api/update-schedule-status).
        $middleware->validateCsrfTokens(except: [
            // Developer file-sync push — a machine-to-machine deploy call with
            // no browser session; authed by the WD_SYNC_KEY header, not a token.
            'wd-sync',
            // Team-inbox AJAX API — the whole namespace (send message, retry,
            // voice, media, react, presence, meet, assistants…). These are
            // AUTHENTICATED, SAME-ORIGIN JSON endpoints. With SameSite=Lax
            // session cookies a cross-site POST cannot carry the cookie, so such
            // a request fails AUTH before CSRF would ever matter — the exact
            // reason routes/api.php ships CSRF-exempt. Exempting the namespace
            // stops the "CSRF token mismatch · failed" a client hit when the
            // inbox tab OUTLIVED its session token (rotation / re-login / session
            // GC): the long-open page held a stale token and every send 419'd.
            // (The "leave" presence ping also fires via navigator.sendBeacon,
            // which cannot attach an X-CSRF-TOKEN header at all.)
            'team-inbox/api/*',
            // Quick-Send /chat AJAX API — same shape as team-inbox/api above
            // (authenticated, same-origin JSON send/reply/AI endpoints). Same
            // SameSite=Lax reasoning, so a long-open /chat tab never 419s either.
            'chat/api/*',
            // Chatbot widget public API — third-party browsers calling
            // /widget/{token}/api/message have no Laravel session
            // cookie and no way to obtain a CSRF token. The
            // embed_token + per-IP throttle + widget.cors middleware
            // are the access controls; CSRF doesn't apply to a public
            // origin-agnostic endpoint.
            'widget/*/api/*',
            // Public storefront checkout — cross-domain shops have no
            // Laravel session cookie. Prices are re-derived server-side
            // and the route is rate-limited, so CSRF doesn't apply.
            's/*/checkout',
            's/*/coupon',
            's/*/review',
            's/*/abandon',
            // Razorpay storefront-payment webhook — authed via HMAC signature.
            'webhooks/storefront-pay',
            // User-generated INCOMING webhooks — external services POST here
            // with no Laravel session/CSRF token. The random 40-char token in
            // the path is the access control (webhook.site-style relay).
            'hooks/in/*',
            'api/update-schedule-status',
            'api/update-scheduled-contact-status',
            'api/update-status',
            // WhatsApp Cloud-API calling webhook — authed via
            // X-Hub-Signature-256, not a session cookie.
            'webhooks/wa-calling',
            // Broadcast Node→Laravel webhooks. Auth via X-Node-Token,
            // not a session cookie — same shared-secret pattern the
            // other Node callbacks use. Without these entries Laravel
            // rejects with 419 CSRF mismatch and the broadcast row
            // never advances past `processing`.
            'api/update-message-status',
            'api/update-broadcast-status',
            'api/inbound-message',
            'api/whatsapp-settings',
            'api/whatsapp-message-settings',
            // wa-campaigns Node→Laravel callbacks — mirror the
            // broadcast pair. Auth via X-Node-Token, not a session
            // cookie, so CSRF would otherwise 419 every callback and
            // the campaign detail page would stay at zero.
            'api/campaigns/update-status',
            'api/campaigns/update-contact-status',
            'api/campaigns/update-status-by-id',
            'api/campaigns/track-response',
            'api/campaigns/unsubscribe',
            'api/campaigns/sync',
            'api/scheduled/active',
            'webhooks/whatsapp/inbound',
            // Instagram Platform webhook — HMAC-verified (X-Hub-Signature-256).
            'webhooks/instagram',
            // Slack slash command — authed via X-Slack-Signature (HMAC), not CSRF.
            'webhooks/slack/command',
            // Trello board webhook — authed via X-Trello-Webhook (HMAC); also
            // answers Trello's creation HEAD probe.
            'webhooks/trello',
            // Sheets add-on — token-auth via Bearer header instead of CSRF.
            'api/v1/sheets-addon/*',
            // Shopify webhooks — HMAC-verified inside the controller; the
            // signature lives in X-Shopify-Hmac-SHA256, not a CSRF cookie.
            'shopify/webhook/*',
            // WooCommerce webhooks — HMAC-verified via X-WC-Webhook-Signature.
            'woocommerce/webhook/*',
            // Appointment-booking callbacks the Node flow runtime hits
            // when a customer picks a slot. Idempotency on
            // (workspace_id, conversation_id, starts_at) prevents
            // accidental double-bookings.
            'api/appointments/slots',
            'api/appointments/book',
            // Commerce-flow callbacks — Node calls these from inside
            // a commerce node. Authed via X-Node-Token (validated
            // inside FlowsCommerceController), so CSRF must be off
            // or Node hits 419 on every request.
            'api/commerce/checkout-link',
            'api/commerce/waba-send-products',
            'api/commerce/check-inventory',
            // Flow-node side effects (tag, assign). Same X-Node-Token
            // pattern — Node calls these when those nodes fire.
            'api/flow-node/tag',
            'api/flow-node/assign',
            'api/flow-node/ai-call',
            'api/flow-node/web-search',
            'api/call-flow/active',
            'api/flow-node/google-meet',
            'api/flow-node/wa-form-send',
            // Google Sheets / Docs / Forms flow-node bridge — X-Node-Token.
            'api/flow-node/google/*',
            // Google Forms Apps Script webhook — X-Workspace-Token from
            // the script the user pasted into their form's Script Editor.
            'api/google/form-response',
            'api/waba-creds',
            // WABA AI call bridge — Node-facing.
            'api/waba-call/assistant/*',
            'api/waba-call/transcript-turn',
            'api/waba-call/voice-keys',
            'api/waba-call/bridge-error',
            'api/waba-call/bridge-accepted',
            // Chatbot widget public endpoints — token-authed in the URL,
            // run from third-party domains so a CSRF cookie is impossible.
            'widget/*/api/*',
            // Node-side flow fetch — single normalized payload.
            'api/flows/*',
            // Payment gateway webhooks + Razorpay handler POST — these
            // are cross-origin and signature-verified per gateway.
            'payment/webhook/*',
            'payment/callback/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 "Page Expired" = CSRF token mismatch / expired session. On MOBILE the
        // browser often serves a cached login/logout page whose _token no longer
        // matches the rotated/expired session, so the customer sees a scary 419
        // page and only a manual reload fixes it. We turn EVERY 419 into a graceful
        // redirect: the user lands on a fresh page (new CSRF token) and just tries
        // again — the branded "Page expired" screen is NEVER shown to a browser.
        $graceful419 = function (\Illuminate\Http\Request $request) {
            // AJAX/JSON callers get a clean status they can handle themselves
            // (the front-end refreshes the token and retries).
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Please refresh and try again.'], 419);
            }

            // LOGOUT — intent is unambiguous and the session is usually already
            // gone. Finish logging out and land on a FRESH login page.
            if ($request->routeIs('logout') || $request->is('logout') || $request->is('*/logout')) {
                try {
                    \Illuminate\Support\Facades\Auth::guard()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                } catch (\Throwable $ignore) { /* already logged out */ }
                return redirect()->route('login')
                    ->with('status', 'You have been signed out.');
            }

            // LOGIN / register / any other form. Mint a FRESH CSRF token FIRST so
            // the page they land on submits cleanly (prevents an immediate second
            // 419 when the browser re-posts a bfcached form), then bounce BACK to
            // the same page keeping their input (never secrets) + a friendly notice.
            try { $request->session()->regenerateToken(); } catch (\Throwable $ignore) {}

            return redirect()->back()   // falls back to "/" when there's no referer
                ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->with('status', 'Your session expired for security. Please try again.');
        };

        // The canonical CSRF-mismatch exception.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) use ($graceful419) {
            return $graceful419($request);
        });

        // Belt-and-suspenders: ANY other path that surfaces a raw 419 (a Symfony
        // HttpException with status 419, e.g. from a manual abort or a middleware
        // that doesn't throw TokenMismatchException) is handled identically, so the
        // branded error view is never reached for a browser request. Returning null
        // for every other status lets Laravel render 404/403/500/etc. normally.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) use ($graceful419) {
            return ((int) $e->getStatusCode() === 419) ? $graceful419($request) : null;
        });
    })->create();
