<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * WaDesk install wizard. 8 user-facing steps:
 *
 *   1. Welcome           — intro + "Begin"
 *   2. Requirements      — PHP version, ext, dirs, PDO drivers
 *   3. Database          — MySQL connection (tested via AJAX)
 *   4. Application       — name / URL / timezone / locale
 *   5. Admin              — first super-admin account
 *   6. Node bridge       — Node server URL + shared token + port
 *   7. Progress          — AJAX-driven multi-substep install run
 *   8. Complete          — success + admin link
 *
 * Substeps run inside step 6 (one POST per substep so the UI can show
 * per-step progress with timing + retry on failure):
 *
 *   1. Write .env
 *   2. Run database migrations
 *   3. Seed essential data
 *   4. Create admin user + private workspace
 *   5. Set file permissions / storage:link
 *   6. Finalize (clear caches, write `installed` flag)
 *
 * State machine: session('install_step') tracks the latest user-facing
 * step reached. Forward-jumps are blocked; back-jumps are fine.
 */
class InstallController extends Controller
{
    private const STEPS_TOTAL = 8;

    /* ===================== STEP GUARD ===================== */

    private function ensureStep(Request $request, int $required): ?RedirectResponse
    {
        if (((int) $request->session()->get('install_step', 1)) < $required) {
            return redirect()->route('install.welcome');
        }
        return null;
    }

    /* ===================== STEP 1: WELCOME ===================== */

    public function welcome(Request $request): View
    {
        $request->session()->put('install_step', 1);
        return view('install.welcome', ['currentStep' => 1]);
    }

    /* ===================== LICENCE (Envato purchase verify) ===================== */

    public function license(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->ensureStep($request, 1)) {
            return $redirect;
        }
        return view('install.license', [
            'currentStep' => 1,
            'verified'    => (bool) $request->session()->get('install_purchase_ok'),
        ]);
    }

    public function verifyLicense(Request $request): RedirectResponse
    {
        $request->validate(['purchase_code' => ['required', 'string', 'max:120']]);

        $result = $this->envatoVerify((string) $request->input('purchase_code'));
        if (! $result['ok']) {
            return back()->withErrors(['purchase_code' => $result['message']])->withInput();
        }

        $request->session()->put('install_purchase_ok', true);
        $request->session()->put('install_purchase_code', trim((string) $request->input('purchase_code')));

        return redirect()->route('install.requirements');
    }

    /**
     * Verify a CodeCanyon purchase code against the Envato API. Runs BEFORE the
     * database exists, so it touches no DB — it reads the item id + author
     * token from config/version.php (env-overridable) and calls Envato directly.
     *
     * @return array{ok: bool, message: string}
     */
    private function envatoVerify(string $code): array
    {
        $code  = trim($code);
        $token = (string) config('version.envato.token');
        $item  = (string) config('version.envato.item_id');

        if ($code === '') {
            return ['ok' => false, 'message' => 'Enter your CodeCanyon purchase code.'];
        }
        if ($token === '') {
            return ['ok' => false, 'message' => 'Licence check is not configured (missing Envato token).'];
        }

        try {
            $res = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent'    => 'WaDesk-Installer',
                ])
                ->timeout(20)
                ->get('https://api.envato.com/v3/market/author/sale', ['code' => $code]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach Envato to verify the code. Check the server\'s internet connection.'];
        }

        if ($res->status() === 404) {
            return ['ok' => false, 'message' => 'Invalid purchase code — Envato has no sale for it.'];
        }
        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'Envato verification failed (HTTP ' . $res->status() . ').'];
        }

        $soldId = (string) data_get($res->json(), 'item.id', '');
        if ($item !== '' && $soldId !== '' && $soldId !== $item) {
            return ['ok' => false, 'message' => 'This purchase code is for a different product.'];
        }

        return ['ok' => true, 'message' => 'Purchase verified.'];
    }

    /* ===================== STEP 2: REQUIREMENTS ===================== */

    public function requirements(Request $request): View|RedirectResponse
    {
        // Licence gate — can't reach requirements (or anything past it) without
        // a verified CodeCanyon purchase code.
        if (! $request->session()->get('install_purchase_ok')) {
            return redirect()->route('install.license');
        }

        $phpVersion = PHP_VERSION;
        $phpOk      = version_compare($phpVersion, '8.2.0', '>=');

        $extensions = [
            'bcmath'    => extension_loaded('bcmath'),
            'ctype'     => extension_loaded('ctype'),
            'curl'      => extension_loaded('curl'),
            'dom'       => extension_loaded('dom'),
            'fileinfo'  => extension_loaded('fileinfo'),
            'gd'        => extension_loaded('gd'),
            'json'      => extension_loaded('json'),
            'mbstring'  => extension_loaded('mbstring'),
            'openssl'   => extension_loaded('openssl'),
            'pdo'       => extension_loaded('pdo'),
            'tokenizer' => extension_loaded('tokenizer'),
            'xml'       => extension_loaded('xml'),
            'zip'       => extension_loaded('zip'),
        ];

        $directories = [
            'storage'           => is_writable(storage_path()),
            'storage/app'       => is_writable(storage_path('app')),
            'storage/framework' => is_writable(storage_path('framework')),
            'bootstrap/cache'   => is_writable(base_path('bootstrap/cache')),
            'lang'              => is_dir(base_path('lang')) && is_readable(base_path('lang')),
            '.env writable'     => file_exists(base_path('.env'))
                ? is_writable(base_path('.env'))
                : is_writable(base_path()),
        ];

        $pdoDrivers   = \PDO::getAvailableDrivers();
        $hasPdoDriver = in_array('mysql', $pdoDrivers, true);

        $allPassed = $phpOk
            && ! in_array(false, $extensions, true)
            && ! in_array(false, $directories, true)
            && $hasPdoDriver;

        return view('install.requirements', [
            'currentStep'  => 2,
            'phpVersion'   => $phpVersion,
            'phpOk'        => $phpOk,
            'extensions'   => $extensions,
            'directories'  => $directories,
            'pdoDrivers'   => $pdoDrivers,
            'hasPdoDriver' => $hasPdoDriver,
            'allPassed'    => $allPassed,
        ]);
    }

    public function checkRequirements(Request $request): RedirectResponse
    {
        $request->session()->put('install_step', 2);
        return redirect()->route('install.database');
    }

    /* ===================== STEP 3: DATABASE ===================== */

    public function database(Request $request): View|RedirectResponse
    {
        if ($r = $this->ensureStep($request, 2)) return $r;

        return view('install.database', [
            'currentStep' => 3,
            'db'          => $request->session()->get('db', []),
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $request->validate([
            'host'     => 'required|string',
            'port'     => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
        ]);

        $r = $this->resolveConnection([
            'host'     => (string) $request->input('host'),
            'port'     => (string) $request->input('port'),
            'database' => (string) $request->input('database'),
            'username' => (string) $request->input('username'),
            'password' => (string) $request->input('password', ''),
        ]);

        if ($r['ok']) {
            // Keep the user-facing message clean — the resolved transport
            // (socket path / TCP) is an internal detail, not something to
            // surface in the UI.
            return response()->json(['success' => true, 'message' => 'Connection successful.']);
        }

        // The server WAS reached but rejected the credentials/database —
        // surface that exact message (it's actionable, not a transport block).
        if (!empty($r['authError'])) {
            return response()->json(['success' => false, 'message' => $r['authError']], 422);
        }

        // Every transport (socket + TCP) was blocked — almost always a
        // shared-hosting restriction, NOT a wrong password.
        $msg = 'Could not reach MySQL over any method (Unix socket or TCP). '
             . 'A "[2002] Operation not permitted" here is the host blocking the connection itself, not a bad password. '
             . 'Use the exact MySQL host your hosting panel shows (on Hostinger that is usually "localhost"), confirm the database and user exist and the user is added to the database with ALL privileges, then retry.';
        return response()->json([
            'success' => false,
            'message' => $msg,
            'tried'   => $r['tried'] ?? [],
        ], 422);
    }

    /**
     * Open a working PDO connection by trying a cascade of transports.
     * Shared hosts (Hostinger, etc.) frequently block ONE method while
     * allowing another: the default socket path PDO picks depends on the
     * domain's PHP build (a wrong one → "[2002] Operation not permitted"
     * on `localhost`), and TCP to 127.0.0.1:3306 is often firewalled
     * (same EPERM). We try the host as entered, then explicit common
     * socket paths, then loopback TCP, and return the FIRST that connects
     * so the rest of the install — and the written .env — uses exactly
     * that transport.
     *
     * @return array{ok:bool, via?:string, host?:?string, port?:?string, socket?:?string, pdo?:\PDO, tried?:array, authError?:string}
     */
    private function resolveConnection(array $db): array
    {
        $host = trim((string) ($db['host'] ?? '127.0.0.1'));
        $port = (string) ($db['port'] ?? '3306');
        $name = (string) ($db['database'] ?? '');
        $user = (string) ($db['username'] ?? '');
        $pass = (string) ($db['password'] ?? '');

        $opts  = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 4];
        $tried = [];

        // Candidate transports, in priority order.
        $candidates = [];
        // 1) Exactly what the user entered (socket if hostname, TCP if IP).
        $candidates[] = [
            'dsn' => "mysql:host={$host};port={$port};dbname={$name}",
            'via' => filter_var($host, FILTER_VALIDATE_IP) ? 'tcp' : 'host',
            'host' => $host, 'port' => $port, 'socket' => null, 'label' => "host={$host}",
        ];
        // 2) Explicit Unix sockets — the real fix when `localhost` gives 2002.
        $sockets = array_values(array_unique(array_filter([
            (string) ini_get('pdo_mysql.default_socket'),
            (string) ini_get('mysqli.default_socket'),
            '/var/run/mysqld/mysqld.sock',
            '/run/mysqld/mysqld.sock',
            '/var/lib/mysql/mysql.sock',
            '/tmp/mysql.sock',
            '/var/run/mysqld/mysqld10.sock',
        ])));
        foreach ($sockets as $sock) {
            $candidates[] = [
                'dsn' => "mysql:unix_socket={$sock};dbname={$name}",
                'via' => 'socket', 'host' => null, 'port' => null, 'socket' => $sock, 'label' => "socket={$sock}",
            ];
        }
        // 3) Force loopback TCP, then the bare `localhost` hostname.
        $candidates[] = ['dsn' => "mysql:host=127.0.0.1;port={$port};dbname={$name}", 'via' => 'tcp', 'host' => '127.0.0.1', 'port' => $port, 'socket' => null, 'label' => 'host=127.0.0.1'];
        $candidates[] = ['dsn' => "mysql:host=localhost;dbname={$name}",            'via' => 'host', 'host' => 'localhost', 'port' => $port, 'socket' => null, 'label' => 'host=localhost'];

        foreach ($candidates as $c) {
            try {
                $pdo = new \PDO($c['dsn'], $user, $pass, $opts);
                return ['ok' => true, 'via' => $c['via'], 'host' => $c['host'], 'port' => $c['port'], 'socket' => $c['socket'], 'pdo' => $pdo];
            } catch (\Throwable $e) {
                $tried[$c['label']] = $e->getMessage();
                // If the server answered with an AUTH / database error, the
                // transport works — stop and report it. Codes: 1045 access
                // denied, 1044 db access denied, 1049 unknown database,
                // 1698 auth plugin. Retrying other transports would only
                // mask the real (actionable) message.
                if (preg_match('/\[(1045|1044|1049|1698)\]/', $e->getMessage())) {
                    return ['ok' => false, 'tried' => $tried, 'authError' => $e->getMessage()];
                }
            }
        }
        return ['ok' => false, 'tried' => $tried];
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        $request->validate([
            'host'     => 'required|string',
            'port'     => 'required|numeric',
            'database' => 'required|string',
            'username' => 'required|string',
        ]);

        $request->session()->put('db', $request->only('host', 'port', 'database', 'username', 'password'));
        $request->session()->put('install_step', 3);
        return redirect()->route('install.application');
    }

    /* ===================== STEP 4: APPLICATION ===================== */

    public function application(Request $request): View|RedirectResponse
    {
        if ($r = $this->ensureStep($request, 3)) return $r;

        return view('install.application', [
            'currentStep' => 4,
            'app'         => $request->session()->get('app', []),
        ]);
    }

    public function saveApplication(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'url'      => 'required|url|max:255',
            'timezone' => 'required|string|max:100',
            'locale'   => 'required|string|max:12',
        ]);

        $request->session()->put('app', $request->only('name', 'url', 'timezone', 'locale'));
        $request->session()->put('install_step', 4);
        return redirect()->route('install.admin');
    }

    /* ===================== STEP 5: ADMIN ===================== */

    public function admin(Request $request): View|RedirectResponse
    {
        if ($r = $this->ensureStep($request, 4)) return $r;

        return view('install.admin', [
            'currentStep' => 5,
            'admin'       => $request->session()->get('admin_data', []),
        ]);
    }

    public function saveAdmin(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:191',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'workspace_name' => 'required|string|max:120',
        ]);

        $request->session()->put('admin_data', $request->only(
            'name', 'email', 'password', 'workspace_name'
        ));
        $request->session()->put('install_step', 5);
        return redirect()->route('install.node');
    }

    /* ===================== STEP 6: NODE BRIDGE ===================== */

    public function node(Request $request): View|RedirectResponse
    {
        if ($r = $this->ensureStep($request, 5)) return $r;

        $node = $request->session()->get('node', []);

        // Pre-fill a 64-hex token default if none chosen yet. If the
        // Laravel .env already carries a NODE_WEBHOOK_TOKEN, prefer that so
        // we don't needlessly rotate the shared secret on re-runs.
        if (empty($node['node_token'])) {
            $node['node_token'] = $this->existingNodeToken() ?: bin2hex(random_bytes(32));
        }

        return view('install.node', [
            'currentStep' => 6,
            'node'        => $node,
        ]);
    }

    public function saveNode(Request $request): RedirectResponse
    {
        $request->validate([
            'server_url' => 'required|url',
            'node_token' => 'required|string|min:16',
            'node_port'  => 'required|integer|min:1|max:65535',
        ]);

        $request->session()->put('node', $request->only('server_url', 'node_token', 'node_port'));
        $request->session()->put('install_step', 6);
        return redirect()->route('install.run');
    }

    /**
     * Read the NODE_WEBHOOK_TOKEN already present in the Laravel .env, if
     * any — used to pre-fill the Node step without rotating the secret.
     */
    private function existingNodeToken(): string
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) return '';
        $content = (string) file_get_contents($envPath);
        if (preg_match('/^NODE_WEBHOOK_TOKEN=(.+)$/m', $content, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /* ===================== STEP 7: RUN (AJAX) ===================== */

    public function run(Request $request): View|RedirectResponse
    {
        if ($r = $this->ensureStep($request, 6)) return $r;
        return view('install.progress', ['currentStep' => 7]);
    }

    /**
     * POST /install/execute — called once per substep (1..6) by the JS
     * runner on the progress page. Each substep is independent so a
     * failure can be retried without restarting prior work.
     */
    public function execute(Request $request): JsonResponse
    {
        $step = (int) $request->input('step', 0);

        // Remove ALL execution-time limits for install steps. Migrations and
        // seeders on a fresh DB (or a slow shared host) can easily exceed the
        // default 120s max_execution_time and get killed mid-run, leaving a
        // half-migrated database. set_time_limit(0) = unlimited; we also clear
        // max_execution_time and keep running even if the browser disconnects.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '-1');
        @ignore_user_abort(true);
        // Give big seeders headroom too (some hosts default to 128M).
        @ini_set('memory_limit', '512M');

        $db    = $request->session()->get('db', []);
        $app   = $request->session()->get('app', []);
        $admin = $request->session()->get('admin_data', []);
        $node  = $request->session()->get('node', []);

        if (empty($db) || empty($app) || empty($admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Installation state lost. Restart the wizard from /install.',
            ], 422);
        }

        // Substep ≥ 2 needs DB access — apply runtime config so it
        // picks up the values the user just entered, before any
        // Artisan/Eloquent call.
        if ($step >= 2) {
            $this->applyDatabaseConfig($db);
        }

        try {
            match ($step) {
                1 => $this->stepWriteEnv($db, $app, $node),
                2 => $this->stepRunMigrations(),
                3 => $this->stepSeedData(),
                4 => $this->stepCreateAdmin($admin, $app),
                5 => $this->stepFilePermissions(),
                6 => $this->stepFinalize($request, $admin, $app),
                default => throw new \InvalidArgumentException('Invalid step.'),
            };
            return response()->json(['success' => true, 'step' => $step]);
        } catch (\Throwable $e) {
            // Never echo the raw exception to the client — during DB steps a
            // PDO/SQLSTATE message can disclose the DB host, username and
            // driver internals to an unauthenticated pre-install visitor.
            // Log the detail server-side; return a generic message.
            Log::error('[INSTALL] step ' . $step . ' failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
            ]);
            return response()->json([
                'success' => false,
                'step'    => $step,
                'message' => 'Installation step failed. Please check your details and the server logs, then try again.',
            ], 500);
        }
    }

    /* ===================== SUBSTEPS ===================== */

    private function stepWriteEnv(array $db, array $app, array $node = []): void
    {
        $envPath = base_path('.env');
        $content = file_exists($envPath) ? file_get_contents($envPath) : '';

        // Generate APP_KEY if missing — we can't shell out to
        // `php artisan key:generate` because Laravel's running
        // process can't reliably refresh its own config mid-request.
        $appKey = $this->resolveAppKey($content);

        // The Node bridge token comes from the user-confirmed Node step.
        // Fall back to a freshly-generated secret only if the session value
        // is somehow empty (e.g. step skipped on a partial re-run).
        $nodeToken = trim((string) ($node['node_token'] ?? ''));
        if ($nodeToken === '') {
            $nodeToken = bin2hex(random_bytes(24));
        }
        $serverUrl = trim((string) ($node['server_url'] ?? ''));

        // Resolve the transport that actually connects so .env points at
        // the working method. On hosts where `localhost`/TCP are blocked
        // (SQLSTATE 2002), the resolver finds the real Unix socket and we
        // persist it as DB_SOCKET (Laravel's mysql config reads it).
        $conn     = $this->resolveConnection($db);
        $dbHost   = (string) ($db['host'] ?? '127.0.0.1');
        $dbPort   = (string) ($db['port'] ?? '3306');
        $dbSocket = '';
        if (($conn['ok'] ?? false) && ($conn['via'] ?? '') === 'socket') {
            $dbSocket = (string) $conn['socket'];
        } elseif (($conn['ok'] ?? false) && !empty($conn['host'])) {
            $dbHost = (string) $conn['host'];
            if (!empty($conn['port'])) $dbPort = (string) $conn['port'];
        }

        $updates = [
            'APP_NAME'         => '"' . str_replace('"', '\\"', (string) ($app['name'] ?? 'WaDesk')) . '"',
            'APP_ENV'          => 'production',
            'APP_KEY'          => $appKey,
            'APP_DEBUG'        => 'false',
            'APP_URL'          => (string) ($app['url'] ?? 'http://localhost'),
            'APP_TIMEZONE'     => (string) ($app['timezone'] ?? 'UTC'),
            'APP_LOCALE'       => (string) ($app['locale'] ?? 'en'),
            'APP_FALLBACK_LOCALE' => 'en',
            'DB_CONNECTION'    => 'mysql',
            'DB_HOST'          => $dbHost,
            'DB_PORT'          => $dbPort,
            'DB_SOCKET'        => $dbSocket,
            'DB_DATABASE'      => (string) ($db['database'] ?? ''),
            'DB_USERNAME'      => (string) ($db['username'] ?? ''),
            'DB_PASSWORD'      => $this->quoteForEnv((string) ($db['password'] ?? '')),
            'SESSION_DRIVER'   => 'database',
            'CACHE_STORE'      => 'database',
            'QUEUE_CONNECTION' => 'database',
            'BROADCAST_DRIVER' => 'log',
            'MAIL_MAILER'      => 'log',
            'SERVER_URL'       => $serverUrl,
            'NODE_WEBHOOK_TOKEN' => $nodeToken,
            'META_GRAPH_VERSION' => 'v23.0',
        ];

        foreach ($updates as $key => $value) {
            $line = "{$key}={$value}";
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace_callback(
                    "/^{$key}=.*/m",
                    static fn () => $line,
                    $content
                );
            } else {
                $content .= "\n{$line}";
            }
        }

        if (file_put_contents($envPath, $content) === false) {
            throw new \RuntimeException("Could not write .env at {$envPath}");
        }

        // Mirror the shared secret + app domain + port into the Node
        // bridge's own .env (node/.env). This must use base_path() so it
        // resolves wherever the app is uploaded — the node/ folder lives
        // INSIDE the Laravel app. A failure here is non-fatal: it surfaces
        // a warning the user can act on, but never aborts the install.
        $this->writeNodeEnv(
            (string) ($node['node_port'] ?? '8888'),
            (string) ($app['url'] ?? 'http://localhost'),
            $nodeToken,
            $serverUrl
        );

        // Bake the deployed app URL into the browser extension so users never
        // have to type a server URL — the downloaded extension points at THIS
        // install automatically. Non-fatal if the extension folder is absent.
        $this->writeExtensionUrl((string) ($app['url'] ?? 'http://localhost'));
    }

    /**
     * Bake the deployed app URL into the browser extension's content script
     * (`const WADESK_SERVER_URL = '…'`) so the extension targets THIS install
     * with no manual setup. Uses base_path('extension/content.js') so it
     * resolves wherever the app is uploaded. Never throws — the extension is
     * optional, so a missing/read-only file is just logged.
     */
    private function writeExtensionUrl(string $appUrl): void
    {
        $appUrl = rtrim(trim($appUrl), '/');
        // Strip any quote chars so we can't break the JS string literal.
        $appUrl = str_replace(['"', "'"], '', $appUrl);
        if ($appUrl === '') return;

        $path = base_path('extension/content.js');
        try {
            if (! is_file($path)) {
                Log::info('Install: extension/content.js not found — skipping URL bake-in');
                return;
            }
            if (! is_writable($path)) {
                Log::warning('Install: extension/content.js not writable — set the server URL in the extension manually');
                session()->flash('extension_url_warning', 'Could not write the extension URL automatically — set it in the extension popup.');
                return;
            }
            $src = (string) file_get_contents($path);
            $new = preg_replace(
                "/const\\s+WADESK_SERVER_URL\\s*=\\s*['\"][^'\"]*['\"]\\s*;/",
                "const WADESK_SERVER_URL = '{$appUrl}';",
                $src,
                1,
                $count
            );
            if (($count ?? 0) > 0 && $new !== null && $new !== $src) {
                @file_put_contents($path, $new);
                Log::info("Install: extension WADESK_SERVER_URL set to {$appUrl}");
            }
        } catch (\Throwable $e) {
            Log::warning('Install: extension URL bake-in skipped — ' . $e->getMessage());
        }
    }

    /**
     * Idempotently write base_path('node/.env'). Only PORT, APP_DOMAIN_NAME
     * and NODE_WEBHOOK_TOKEN are touched — every other key already present
     * in the file is preserved (read-merge-write). Creates the node/ dir
     * and the file if missing. Never throws: a missing dir or a read-only
     * path is logged + recorded so the run step can show a clear warning
     * the user can resolve manually (paste the three values), rather than
     * hard-crashing the whole installation.
     */
    private function writeNodeEnv(string $port, string $appUrl, string $token, string $serverUrl): void
    {
        $dir     = base_path('node');
        $envPath = base_path('node/.env');

        $updates = [
            'PORT'               => $port !== '' ? $port : '8888',
            'DOMAIN_NAME'        => $serverUrl !== '' ? rtrim($serverUrl, '/') : '',
            'APP_DOMAIN_NAME'    => $appUrl !== '' ? $appUrl : 'http://localhost',
            'NODE_WEBHOOK_TOKEN' => $token,
        ];

        try {
            if (! is_dir($dir)) {
                // base_path('node') — never an absolute path — so it lands
                // wherever the app was uploaded.
                if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    throw new \RuntimeException("Node directory missing and could not be created at {$dir}");
                }
                Log::info("Install: created Node bridge directory at {$dir}");
            }

            $content = file_exists($envPath) ? (string) file_get_contents($envPath) : '';

            foreach ($updates as $key => $value) {
                $line = "{$key}={$value}";
                if (preg_match("/^{$key}=.*/m", $content)) {
                    $content = preg_replace_callback("/^{$key}=.*/m", static fn () => $line, $content);
                } else {
                    $content = rtrim($content, "\r\n");
                    $content = ($content === '' ? '' : $content . "\n") . $line;
                }
            }

            if (! str_ends_with($content, "\n")) {
                $content .= "\n";
            }

            if (@file_put_contents($envPath, $content) === false) {
                throw new \RuntimeException("Node bridge env not writable at {$envPath}");
            }
        } catch (\Throwable $e) {
            // Non-fatal — record so the finalize step / logs surface it, but
            // let the rest of the install proceed.
            Log::warning('Install: node/.env write skipped — ' . $e->getMessage());
            session()->flash('node_env_warning', $e->getMessage());
        }
    }

    private function stepRunMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * Run the curated seeder list. Each seeder is idempotent
     * (updateOrCreate / firstOrCreate) so re-running this step on a
     * partial failure won't break the install.
     */
    private function stepSeedData(): void
    {
        $seeders = [
            \Database\Seeders\RolePermissionSeeder::class,
            \Database\Seeders\PackageSeeder::class,
            \Database\Seeders\CurrencySeeder::class,
            \Database\Seeders\PaymentGatewaySeeder::class,
            \Database\Seeders\TranslationProviderSeeder::class,
            \Database\Seeders\CheckoutDefaultsSeeder::class,
            \Database\Seeders\GuidebookArticleSeeder::class,
            \Database\Seeders\LegalPagesSeeder::class,
        ];
        foreach ($seeders as $class) {
            if (! class_exists($class)) continue;
            Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
        }
    }

    private function stepCreateAdmin(array $admin, array $app): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $admin['email']],
            [
                'name'              => $admin['name'],
                'password'          => Hash::make($admin['password']),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Spatie role — Super Admin / Admin. Soft-fail when the package
        // isn't seeded with that exact name in this install.
        try {
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles(['Super Admin']);
            }
        } catch (\Throwable $e) { /* role missing — non-fatal */ }

        // Auto-provision a workspace so the admin has somewhere to land
        // after first login. Plan resolves to the first available plan
        // (typically "Free" from PackageSeeder).
        $planId = null;
        try {
            $planId = optional(\App\Models\Package::query()->orderBy('sort_order')->first())->id;
        } catch (\Throwable $e) {}

        $wsName = (string) ($admin['workspace_name'] ?? ($admin['name'] . "'s workspace"));
        $ws = Workspace::query()->firstOrCreate(
            ['slug' => Str::slug($wsName) . '-' . Str::lower(Str::random(4))],
            [
                'owner_user_id'  => $user->id,
                'name'           => $wsName,
                'plan'           => $planId,
                'timezone'       => (string) ($app['timezone'] ?? 'UTC'),
                'locale'         => (string) ($app['locale']   ?? 'en'),
                'currency'       => 'USD',
                'status'         => true,
                'last_active_at' => now(),
            ]
        );

        // Pivot — workspace_user role=owner so middleware passes.
        try {
            if (! DB::table('workspace_user')->where('workspace_id', $ws->id)->where('user_id', $user->id)->exists()) {
                DB::table('workspace_user')->insert([
                    'workspace_id' => $ws->id,
                    'user_id'      => $user->id,
                    'role'         => 'owner',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        } catch (\Throwable $e) { /* pivot may not exist on stale schemas */ }

        $user->forceFill(['current_workspace_id' => $ws->id])->save();
    }

    private function stepFilePermissions(): void
    {
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
            // Symlink may already exist on shared hosting — non-fatal.
        }
        @mkdir(public_path('uploads/call-recordings'), 0775, true);
    }

    private function stepFinalize(Request $request, array $admin, array $app): void
    {
        // Persist the verified CodeCanyon purchase code (DB is live by now) so
        // the admin Updater remembers it without re-entry.
        try {
            $code = (string) $request->session()->get('install_purchase_code', '');
            if ($code !== '') {
                \App\Models\SystemSetting::set('envato_purchase_code', $code, 'string');
                \App\Models\SystemSetting::set('envato_verified_at', now()->toIso8601String(), 'string');
            }
        } catch (\Throwable $e) {
        }

        try { Artisan::call('config:clear'); } catch (\Throwable $e) {}
        try { Artisan::call('route:clear');  } catch (\Throwable $e) {}
        try { Artisan::call('view:clear');   } catch (\Throwable $e) {}

        $payload = [
            'installed_at' => now()->toIso8601String(),
            'version'      => '1.0.0',
            'admin_email'  => (string) ($admin['email'] ?? ''),
            'app_url'      => (string) ($app['url']     ?? url('/')),
        ];
        file_put_contents(storage_path('installed'), json_encode($payload, JSON_PRETTY_PRINT));

        $request->session()->put('install_step', 7);
        $request->session()->put('install_complete', [
            'email'     => $admin['email']     ?? '',
            'url'       => $app['url']         ?? url('/'),
            'workspace' => $admin['workspace_name'] ?? '',
        ]);

        // Clear sensitive form data from session.
        $request->session()->forget(['db', 'admin_data', 'node']);
    }

    /* ===================== HELPERS ===================== */

    private function resolveAppKey(string $envContent): string
    {
        if (preg_match('/^APP_KEY=(.+)$/m', $envContent, $m)) {
            $existing = trim($m[1]);
            if ($existing !== '' && $existing !== 'base64:') return $existing;
        }
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private function quoteForEnv(string $value): string
    {
        // Wrap the password in double quotes when it contains spaces
        // or shell-sensitive characters; otherwise return raw.
        if ($value === '') return '';
        if (preg_match('/[\s#"\'\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }

    private function applyDatabaseConfig(array $db): void
    {
        // Resolve the transport that actually connects (socket vs TCP),
        // so migrations/seeds use the same method the test step proved.
        $r = $this->resolveConnection($db);

        $cfg = [
            'database.default'                       => 'mysql',
            'database.connections.mysql.database'    => $db['database'] ?? '',
            'database.connections.mysql.username'    => $db['username'] ?? '',
            'database.connections.mysql.password'    => $db['password'] ?? '',
            'database.connections.mysql.unix_socket' => '',
        ];

        if (($r['ok'] ?? false) && ($r['via'] ?? '') === 'socket') {
            // Socket wins — Laravel ignores host/port when unix_socket is set.
            $cfg['database.connections.mysql.host']        = '127.0.0.1';
            $cfg['database.connections.mysql.port']        = $db['port'] ?? '3306';
            $cfg['database.connections.mysql.unix_socket'] = $r['socket'];
        } else {
            // TCP / hostname (resolved host if we found one that connects).
            $cfg['database.connections.mysql.host'] = ($r['ok'] ?? false) ? ($r['host'] ?? ($db['host'] ?? '127.0.0.1')) : ($db['host'] ?? '127.0.0.1');
            $cfg['database.connections.mysql.port'] = ($r['ok'] ?? false) ? ($r['port'] ?? ($db['port'] ?? '3306'))     : ($db['port'] ?? '3306');
        }

        config($cfg);
        DB::purge('mysql');
    }
}
