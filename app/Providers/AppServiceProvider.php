<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Conversation;
use App\Models\Coupon;
use App\Models\CreditPackage;
use App\Models\Order;
use App\Models\Package;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\AuditableObserver;
use App\Policies\ConversationPolicy;
use App\Policies\TeamPolicy;
use App\Policies\WorkspacePolicy;
use App\Services\Inbox\AuditLogger;
use App\Support\WorkspacePermissions;
use Illuminate\Auth\Events\Failed as AuthFailed;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Auth\Events\Logout as AuthLogout;
use Illuminate\Auth\Events\PasswordReset as AuthPasswordReset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // NOTE: logging is controlled in ONE place — LOG_LEVEL in .env (see
        // config/logging.php). The kill switch that used to live here was
        // removed so there is never a second, hidden reason for logs to be
        // missing. Set LOG_LEVEL=off to disable logging entirely.

        // One shared content store for the public marketing site live
        // editor — it caches the whole (small) frontend_content table per
        // request, so it must be a singleton.
        $this->app->singleton(\App\Services\Frontend\FrontendContentStore::class);

        // Global fc() / fc_editing() helpers used by the public Blade
        // components. require_once here avoids a composer "files" autoload
        // entry (and a dump-autoload step on install).
        require_once app_path('Support/fc_helpers.php');

        // Global mask_phone() display helper — masks customer/recipient
        // phone numbers shown in the dashboard (last 4 digits only). Same
        // require_once pattern; display-only, never alters stored data.
        require_once app_path('Support/security_helpers.php');

        // Suppress Scramble's default Stoplight UI route (/docs/api) — we serve
        // our own WaDesk-branded reference at /developers/docs and only need the
        // raw OpenAPI document. Must run in register() so it's set before
        // Scramble's provider boots. The JSON spec route is re-registered in
        // boot() so our page can fetch it.
        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::ignoreDefaultRoutes();
        }
    }

    public function boot(): void
    {
        // ── Node bridge auth: auto-attach X-Node-Token to EVERY outbound HTTP
        // request bound for the Node host. Node gates its whole /api surface —
        // a call is allowed only WITH the shared token OR from loopback. On a
        // co-located dev box the Laravel->Node call is 127.0.0.1 (loopback,
        // auto-trusted); on a client server the Node URL is a public host/port,
        // so without the token EVERY pairing / send / broadcast / flow call
        // 401s "unauthorized". Doing it here (one global middleware) covers
        // every call site at once — present and future — and never touches
        // Meta / Twilio (different hosts).
        \Illuminate\Support\Facades\Http::globalRequestMiddleware(function ($request) {
            static $node = null;
            if ($node === null) {
                try {
                    $base = function_exists('wd_node_url') ? (string) wd_node_url() : '';
                } catch (\Throwable $e) {
                    $base = '';
                }
                $node = $base !== ''
                    ? ['host' => strtolower((string) parse_url($base, PHP_URL_HOST)), 'port' => parse_url($base, PHP_URL_PORT)]
                    : ['host' => '', 'port' => null];
            }
            if ($node['host'] === '') {
                return $request;
            }
            try {
                $uri  = $request->getUri();
                $host = strtolower($uri->getHost());
                if ($host !== $node['host']) {
                    return $request;
                }
                if ($node['port'] !== null && $uri->getPort() !== null && (int) $uri->getPort() !== (int) $node['port']) {
                    return $request;
                }
                $tok = function_exists('node_token') ? node_token() : '';
                if ($tok !== '' && !$request->hasHeader('X-Node-Token')) {
                    return $request->withHeader('X-Node-Token', $tok);
                }
            } catch (\Throwable $e) {
                // never break an outbound call over auth-header injection
            }
            return $request;
        });

        // TEMP DIAGNOSTIC — a WABA number connects green, then flips to
        // disconnected seconds later. Rather than guess which writer does it,
        // hook the model itself: EVERY status change is logged with the call
        // site that made it, so the culprit names itself. Paired with
        // [WABA-connect] manual saved CONNECTED — grep both by `row`.
        \App\Models\WaProviderConfig::updating(function ($cfg) {
            if (! $cfg->isDirty('status')) {
                return;
            }
            // Skip vendor/framework frames so `by` is OUR code, not Eloquent's
            // internals — the whole point is naming the writer.
            $caller = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30))
                ->filter(fn ($f) => isset($f['file'])
                    && ! str_contains($f['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR))
                ->map(fn ($f) => basename($f['file']) . ':' . ($f['line'] ?? '?'))
                ->take(4)->values()->all();

            \Log::warning('[WABA-status-change] status flipped', [
                'build' => 'waba-connect-trace-v1',
                'row'   => $cfg->id,
                'from'  => $cfg->getOriginal('status'),
                'to'    => $cfg->status,
                'by'    => $caller,
                'url'   => request()?->fullUrl() ?: '(console/queue)',
                'ip'    => request()?->ip(),
            ]);
        });

        // Make every generated URL / asset / image honour APP_URL as its base
        // so the app works at a domain root OR under a sub-folder (e.g.
        // example.com/public/) without 404s. Runs first, before anything
        // renders a link.
        $this->configureBaseUrl();

        // Keep the raw OpenAPI document reachable (our /developers/docs page
        // fetches it) while the default Stoplight UI route stays suppressed.
        if (class_exists(\Dedoc\Scramble\Scramble::class)) {
            \Dedoc\Scramble\Scramble::registerJsonSpecificationRoute('docs/api.json');

            // White-label the generated OpenAPI document: every "WaDesk" string
            // — title, description, schema property descriptions sourced from
            // FormRequest docblocks like X-WaDesk-Signature — gets replaced
            // with the admin-configured brand name (General Settings → app_name).
            // Walks the whole spec recursively because Scramble reads docblocks
            // VERBATIM from PHP source; the AfterGen hook is the only place we
            // can rewrite those strings without editing every FormRequest
            // doc-comment individually. Runs only when the spec is generated
            // (the /docs/api.json request), so brand_name()'s DB read never
            // touches the hot path.
            \Dedoc\Scramble\Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
                $brand = brand_name();

                // Top-level title + description (the simple case).
                $openApi->info->title = $brand . ' API';
                $openApi->info->description = $brand . ' REST API. Authenticate with a workspace API key: send it as `Authorization: Bearer <key>`. Generate keys in the dashboard under Developers / API.';

                // Per-service OVERVIEW text. Scramble groups every endpoint by a
                // tag (the controller name minus "Controller" — Message,
                // Template, …) but never emits a root-level `tags` array, so the
                // reference page showed each service as a bare heading with no
                // explanation of what it's for or how to use it. We populate
                // `$openApi->tags` here (names MUST match the operation tags
                // above) and public/js/api-docs.js renders each description under
                // its service heading. Keep these customer-facing and concise.
                $tagOverviews = [
                    'Message'      => 'Send a single WhatsApp message — text, media (image / video / document / audio) or a location pin — to one number, then read its delivery status or your message history. A file can be attached directly as a multipart `media` field or referenced by a public URL.',
                    'Template'     => 'Create, list and manage your approved WhatsApp templates, and send a template to one number with your own header, body variables and button values. Use this for utility / marketing sends that need a pre-approved template — including one with a PDF / document header (pass the file\'s public URL as the header, e.g. from the Media endpoint).',
                    'Media'        => 'Upload a file once (multipart / form-data, field name `file`, up to 16 MB) and get back a hosted public URL you can reuse across sends — for example a PDF to use as a document-header on a template, or an image on a message.',
                    'Contact'      => 'Full create / read / update / delete over your contacts and leads, including search by number, tags and custom attributes.',
                    'ContactGroup' => 'Organise contacts into groups: create groups, list them, and add or remove members — handy for targeting broadcasts and campaigns.',
                    'Broadcast'    => 'Send the same message to many contacts at once and track each broadcast\'s progress and per-recipient delivery.',
                    'Campaign'     => 'Create, start, pause and monitor drip / marketing campaigns and their recipients.',
                    'Scheduled'    => 'Schedule a WhatsApp send for a future time, list upcoming scheduled messages, and cancel one before it goes out.',
                    'AutoReply'    => 'Manage keyword auto-replies — the automatic responses that fire when an inbound message matches a rule.',
                    'Flow'         => 'Enroll a contact into an automation flow (chatbot) and manage your flows through the API.',
                    'Device'       => 'List the WhatsApp numbers / devices connected to your workspace and check each one\'s connection status.',
                    'Webhook'      => 'Register and manage webhook endpoints so ' . $brand . ' POSTs real-time events (message received / sent / delivered / read / failed, plus campaign and contact events) to your server. When you set a signing secret each delivery carries an HMAC-SHA256 signature header you can verify.',
                    'Deal'         => 'Manage sales-pipeline deals — create, list, update and move deals between stages.',
                    'Conversation' => 'Read your workspace inbox: list chat threads (conversations) and fetch the messages inside a thread.',
                    'Account'      => 'Read your account details, plan, limits and message-credit usage.',
                ];
                $openApi->tags = array_map(
                    fn ($name, $desc) => new \Dedoc\Scramble\Support\Generator\Tag($name, $desc),
                    array_keys($tagOverviews),
                    array_values($tagOverviews),
                );

                // Skip the deep sweep when the live brand happens to still be
                // "WaDesk" (the project default) — nothing to rewrite, save the
                // walk-cost on every /docs/api.json hit.
                if ($brand === 'WaDesk') {
                    return;
                }

                // Walk the entire serialised spec, rewriting any string that
                // contains "WaDesk" → "<Brand>". Covers FormRequest docblocks
                // shipped as schema descriptions (e.g. StoreWebhookRequest's
                // X-WaDesk-Signature reference), summaries, examples — every
                // depth. Mutates strings in place inside arrays + objects so we
                // don't have to know Scramble's exact value-object shape.
                $rewrite = function (&$node) use (&$rewrite, $brand) {
                    if (is_string($node)) {
                        if (str_contains($node, 'WaDesk')) {
                            $node = str_replace('WaDesk', $brand, $node);
                        }
                        return;
                    }
                    if (is_array($node)) {
                        foreach ($node as &$child) {
                            $rewrite($child);
                        }
                        unset($child);
                        return;
                    }
                    if (is_object($node)) {
                        foreach (get_object_vars($node) as $k => $v) {
                            $rewrite($v);
                            $node->$k = $v;
                        }
                    }
                };

                // Paths + components carry every operation summary + schema
                // description; rewriting both covers the full doc surface.
                $rewrite($openApi->paths);
                $rewrite($openApi->components);
            });
        }

        // Make uploaded logos / images / attachments reachable: ensure
        // public/storage resolves, with a shared-hosting fallback when the
        // symlink can't be created. Without this, /storage/... 404s.
        $this->ensurePublicStorage();

        // Mail credentials saved at /admin/settings/mail override .env
        // for every Mailable in the codebase. Wrapped in try so a DB
        // outage at boot doesn't break the request.
        try {
            \App\Support\MailConfig::apply();
        } catch (\Throwable $e) { error_log('[MailConfig] boot failed: ' . $e->getMessage()); }

        // Real-time: if an admin saved Pusher keys on /admin/settings/realtime,
        // flip the broadcaster from `log` → `pusher` at runtime (no .env edit,
        // survives a cached config). No-op + DB-safe when not configured.
        try {
            \App\Support\RealtimeSettings::applyToConfig();
        } catch (\Throwable $e) { error_log('[Realtime] boot failed: ' . $e->getMessage()); }

        // Instagram add-on is a plug: folder present in addon/instagram/ = feature
        // on; folder gone = feature off, everywhere. The add-on, while installed,
        // self-wires three DB signals so WaDesk's existing Instagram UI lights up
        // (instagram_enabled + the self-pointed Instaflow connection + the
        // WorkspaceIgAccount mirror rows). Deleting the folder removes only the
        // engine code — it can't touch the DB — so every Instagram surface
        // (dashboard card, /more, flows, inbox) would keep showing with a dead
        // engine behind it. Reverse that self-wire the moment the folder is gone.
        // NATIVE mode only (instagram_enabled truthy); a remote-Instaflow install
        // has the flag off and is never touched. The real token store
        // (instagram_accounts) is left intact, so dropping the folder back in
        // rebuilds the mirrors on the next settings visit. Inlined (not a private
        // method) so an opcache split-state can never see the call without the body.
        try {
            if (! app()->runningInConsole()
                && (bool) \App\Models\SystemSetting::get('instagram_enabled', false)
                && ! is_dir(base_path('addon/instagram'))) {

                // CRITICAL: only tear down the NATIVE self-wire, NEVER a genuine
                // REMOTE InstaMagic/Instaflow connection. The native add-on points
                // instaflow_url at WaDesk ITSELF (config('app.url')); a remote
                // connection points it at an EXTERNAL host. If the URL is external,
                // Instagram is served by remote Instaflow — the missing addon/ folder
                // is irrelevant, so leave instaflow_connected + the account mirrors
                // completely alone (this is what preserves "connect InstaMagic OR
                // upload the zip, one at a time"). We still clear the native flag.
                $igUrl   = (string) \App\Models\SystemSetting::get('instaflow_url', '');
                $selfHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
                $igHost   = (string) parse_url($igUrl, PHP_URL_HOST);
                $isNativeSelfWire = $igUrl === ''
                    || $igHost === ''
                    || $igHost === $selfHost
                    || in_array($igHost, ['127.0.0.1', 'localhost'], true);

                \App\Models\SystemSetting::set('instagram_enabled', 0);

                if ($isNativeSelfWire) {
                    \App\Models\SystemSetting::set('instaflow_connected', 0); // → InstaflowClient::isConnected() false
                    if (class_exists(\App\Models\WorkspaceIgAccount::class)) {
                        \App\Models\WorkspaceIgAccount::query()->delete();     // → hasConnected() false
                    }
                    \Illuminate\Support\Facades\Log::info('[IG-ADDON] native addon folder removed → native wiring torn down, Instagram UI hidden');
                } else {
                    \Illuminate\Support\Facades\Log::info('[IG-ADDON] native flag cleared; REMOTE Instaflow connection preserved', ['host' => $igHost]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[IG-ADDON reconcile] ' . $e->getMessage());
        }

        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Team::class,         TeamPolicy::class);
        Gate::policy(Workspace::class,    WorkspacePolicy::class);

        // Lets blade do `@canWorkspace('inbox.assign')` against the user's
        // current workspace. Resolved against the auth user, no extra args.
        Blade::if('canWorkspace', function (string $permission, ?int $workspaceId = null) {
            return WorkspacePermissions::userCan(auth()->user(), $permission, $workspaceId);
        });

        // Auth events → audit_logs. The activity-log page reads these so
        // every sign-in / sign-out / failed attempt / password reset is
        // attributable to a user, IP, and user-agent. Using AuditLogger
        // keeps the writes consistent with the inbox audit pattern
        // (same table, same shape, same IP/UA capture).
        Event::listen(function (AuthLogin $event) {
            $user = $event->user;

            // Accept any PENDING team invitation on ANY successful login
            // (email+password, social, remember-me) — this event fires no
            // matter which controller performed the auth, so it's the reliable
            // place to flip an invited member from "pending" to active. A member
            // invited by email is attached with workspace_user.joined_at = NULL
            // ("pending"); signing in IS the accept step. Idempotent (whereNull).
            try {
                if ($user) {
                    $flipped = \Illuminate\Support\Facades\DB::table('workspace_user')
                        ->where('user_id', $user->id)
                        ->whereNull('joined_at')
                        ->update(['joined_at' => now()]);
                    \Illuminate\Support\Facades\Log::info('[invite-accept] via Login event', [
                        'user_id'      => $user->id,
                        'email'        => $user->email,
                        'rows_updated' => $flipped,
                    ]);
                }
            } catch (\Throwable $e) {
                error_log('[invite-accept] ' . $e->getMessage());
            }

            AuditLogger::workspace(
                'auth.login',
                $user?->id,
                $user?->current_workspace_id,
                'user',
                $user?->id,
                ['guard' => $event->guard, 'remember' => (bool) $event->remember]
            );

            // Compare to prior login → emit new-device / new-country email.
            // Runs after the audit row above so the listener can look up
            // the PREVIOUS auth.login row (skip 1, latest).
            try {
                (new \App\Listeners\NewLoginAlertListener)->handle($event);
            } catch (\Throwable $e) {
                error_log('[NewLoginAlert] ' . $e->getMessage());
            }

            // Platform admins need a private workspace so the rest of the
            // app has a scope that doesn't bleed customer data. Idempotent
            // — only creates on first login + only for admin-role users.
            try {
                if ($user) {
                    \App\Support\AdminWorkspaceProvisioner::ensureFor($user);
                }
            } catch (\Throwable $e) {
                error_log('[AdminWorkspaceProvisioner] ' . $e->getMessage());
            }
        });

        Event::listen(function (AuthLogout $event) {
            $user = $event->user;
            AuditLogger::workspace(
                'auth.logout',
                $user?->id,
                $user?->current_workspace_id,
                'user',
                $user?->id,
                ['guard' => $event->guard]
            );
        });

        Event::listen(function (AuthFailed $event) {
            // Don't log the credentials — only the email-ish identifier.
            $creds = $event->credentials ?? [];
            $ident = $creds['email'] ?? $creds['username'] ?? null;
            AuditLogger::workspace(
                'auth.failed',
                $event->user?->id,
                $event->user?->current_workspace_id,
                'user',
                $event->user?->id,
                ['guard' => $event->guard, 'identifier' => is_string($ident) ? mb_substr($ident, 0, 191) : null],
                'failure'
            );
        });

        Event::listen(function (AuthPasswordReset $event) {
            $user = $event->user;
            AuditLogger::workspace(
                'auth.password_reset',
                $user?->id,
                $user?->current_workspace_id ?? null,
                'user',
                $user?->id
            );
        });

        // Auto-audit observers on the 8 platform-level models. Every create /
        // update / delete writes a row. Resource label + changed fields go into
        // payload. See app/Observers/AuditableObserver.php.
        foreach ([
            Workspace::class,
            User::class,
            Package::class,
            Coupon::class,
            CreditPackage::class,
            Announcement::class,
            Order::class,
            \App\Models\Device::class,
        ] as $modelClass) {
            $modelClass::observe(AuditableObserver::class);
        }
    }

    /**
     * Force every generated URL (routes, assets, images, form actions) to carry
     * the correct base, so the same build runs at a domain root OR under a
     * sub-folder such as https://example.com/public/ without links — or form
     * POSTs — 404-ing.
     *
     * Zero-config by design: on shared hosting the front controller is
     * public/index.php, and when the app is reached at /public/ the host
     * reports SCRIPT_NAME=/public/index.php. We derive the sub-folder from that
     * automatically, so the operator does NOT have to edit APP_URL. An explicit
     * path in APP_URL (e.g. .../public) still wins if they want to pin it. The
     * host part always comes from the live request, so the install also works
     * on any domain / sub-domain it's uploaded to.
     */
    /**
     * Make `public/storage` resolve so uploaded logos / media / attachments
     * are reachable at /storage/... instead of 404-ing.
     *
     * Best case: the `storage:link` symlink. On shared hosting where symlinks
     * are blocked (or the link is missing after a zip upload), we fall back to
     * pointing the `public` filesystem disk at a REAL public/storage directory,
     * so new uploads land directly in the web-served path — no symlink needed.
     * Mirrors the SnapNest reference. Fails silently; never breaks a request.
     */
    private function ensurePublicStorage(): void
    {
        try {
            $link   = public_path('storage');
            $target = storage_path('app/public');

            // The `public` disk writes to $target by default. Make sure it
            // actually exists — otherwise every storeAs() silently returns
            // false (this is what broke team-inbox voice notes: media_path=false).
            if (! is_dir($target)) {
                @mkdir($target, 0775, true);
            }

            // A BROKEN symlink (target moved/removed after a restore or deploy)
            // still passes is_link(), so the old code returned early and left
            // the disk pointing at a dead root. Detect + clear it first.
            if (is_link($link) && ! file_exists($link)) {
                @unlink($link);
            }

            if (is_link($link)) {
                return; // healthy symlink → default root ($target) is correct
            }

            if (! file_exists($link)) {
                // Try the proper symlink first.
                try {
                    \Illuminate\Support\Facades\Artisan::call('storage:link');
                } catch (\Throwable $e) {
                    // ignore — handled by the fallback below
                }
            }

            // Symlinks blocked / still missing → serve from a real directory.
            // Point the public disk root at public/storage so uploads write
            // straight into the web root — but ONLY when that dir is actually
            // WRITABLE. Otherwise keep the default ($target) so uploads still
            // work; repointing at a non-writable dir is what made storeAs fail.
            if (! is_link($link)) {
                if (! is_dir($link)) {
                    @mkdir($link, 0775, true);
                }
                if (is_dir($link) && is_writable($link)) {
                    config(['filesystems.disks.public.root' => $link]);
                    $this->mirrorExistingPublicFiles($target, $link);
                }
            }
        } catch (\Throwable $e) {
            // never let storage wiring break the app
        }
    }

    /**
     * One-time best-effort copy of files already stored under
     * storage/app/public into public/storage (only when we've switched the
     * disk root to the real dir). Skips files that already exist; cheap when
     * there's nothing to move.
     */
    private function mirrorExistingPublicFiles(string $from, string $to): void
    {
        try {
            if (! is_dir($from) || realpath($from) === realpath($to)) {
                return;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $item) {
                $rel  = substr($item->getPathname(), strlen($from) + 1);
                $dest = $to . DIRECTORY_SEPARATOR . $rel;
                if ($item->isDir()) {
                    if (! is_dir($dest)) @mkdir($dest, 0755, true);
                } elseif (! file_exists($dest)) {
                    @copy($item->getPathname(), $dest);
                }
            }
        } catch (\Throwable $e) {
            // best effort only
        }
    }

    private function configureBaseUrl(): void
    {
        if (! app()->bound('request') || ! (($req = request()) instanceof \Illuminate\Http\Request)) {
            return; // CLI / queue: nothing to force.
        }

        // The sub-folder (e.g. "/public") comes from wd_base(), which reads the
        // server filesystem — never config — so it can't be defeated by a cached
        // config or stale APP_URL. The host stays the live request's, so the
        // same build works on any domain / sub-domain it's uploaded to.
        // Normalize to "" (domain root) or "/sub" — NEVER a bare "/", which
        // would make the forced root end in a slash and every url('/x') come
        // out as "//x" (double slash → broken links + unstyled page).
        $basePath = '/' . trim((string) wd_base(), '/');
        if ($basePath === '/') {
            $basePath = '';
        }

        // Never downgrade a TLS site (SSL often terminates at a proxy, so PHP
        // may see plain http). trustProxies already makes isSecure() reliable.
        $isHttps = $req->isSecure()
            || str_starts_with(strtolower((string) config('app.url')), 'https://')
            || strtolower((string) $req->server('HTTP_X_FORWARDED_PROTO')) === 'https'
            || (int) $req->server('SERVER_PORT') === 443;
        $scheme = $isHttps ? 'https' : $req->getScheme();

        // rtrim guards against any stray trailing slash from the host/basePath.
        $root = rtrim($scheme . '://' . $req->getHttpHost() . $basePath, '/');

        URL::forceRootUrl($root);
        URL::forceScheme($scheme);
        config(['filesystems.disks.public.url' => $root . '/storage']);
    }
}
