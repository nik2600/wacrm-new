<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

/**
 * Drives the admin Updater: verify purchase → backup → upload ZIP → apply
 * code → migrate → finalize, with rollback. Ported from the SnapNest updater
 * and extended with an Envato purchase-code verification step. NEVER touches
 * .env, storage, the database files, vendor/ or user-uploaded public assets.
 */
class UpdaterService
{
    private string $backupDir;
    private string $tempDir;

    /** Paths that must NEVER be overwritten by an update. */
    private const PROTECTED_PATHS = [
        '.env', 'storage', 'database/database.sqlite', 'vendor', 'node_modules',
        // Node bridge runtime data that MUST survive an update: its own env file,
        // installed deps, and the live WhatsApp (Unofficial API) login sessions.
        // The root entries above only match the root subtree (path-prefix), so
        // the node/ equivalents are listed explicitly — a mis-packaged update ZIP
        // must never overwrite node secrets or wipe connected-number sessions.
        'node/.env', 'node/node_modules', 'node/baileys_auth',
    ];

    /** File extensions in public/ that must never be touched (user assets). */
    private const PROTECTED_PUBLIC_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'tiff',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'mp4', 'mov', 'avi', 'mkv', 'mp3', 'wav',
        'zip', 'rar', 'tar', 'gz', 'ttf', 'otf', 'woff', 'woff2', 'eot',
    ];

    /** Paths that get updated (code only). */
    private const UPDATABLE_PATHS = [
        'app', 'config', 'database/migrations',
        'public/css', 'public/js', 'public/build',
        'resources', 'routes', 'node',
        // Framework bootstrap — listed as individual FILES (not the whole
        // bootstrap/ dir) so bootstrap/cache/ is never touched. app.php is the
        // one file that wires in-place add-on route/migration/view loading
        // (ModuleLoader::routeFiles); omitting it from updates left older
        // installs unable to load ANY add-on — the Instagram add-on 404'd
        // everywhere because its routes were never required. providers.php is
        // small and version-tracked too. Both are safe to overwrite (they carry
        // no per-install customisation).
        'bootstrap/app.php', 'bootstrap/providers.php',
    ];

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        $this->tempDir   = storage_path('app/temp/updater');
    }

    // ------------------------------------------------------------------
    //  STEP 0: Envato purchase verification
    // ------------------------------------------------------------------

    /**
     * Verify a CodeCanyon purchase code against the Envato API and confirm it
     * belongs to THIS item. On success the code is remembered so the buyer
     * doesn't have to re-enter it next time.
     *
     * @return array{ok: bool, message: string, buyer?: string, item?: string}
     */
    public function verifyPurchase(string $code): array
    {
        $code  = trim($code);
        $token = (string) config('version.envato.token');
        $item  = (string) config('version.envato.item_id');

        if ($code === '') {
            return ['ok' => false, 'message' => 'Enter your CodeCanyon purchase code.'];
        }
        if ($token === '') {
            return ['ok' => false, 'message' => 'Updater is not configured — the Envato token is missing from config/license.php.'];
        }

        try {
            $res = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent'    => 'WaDesk-Updater',
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
            return ['ok' => false, 'message' => 'Envato verification failed (HTTP ' . $res->status() . '). Check the author token.'];
        }

        $data   = $res->json();
        $soldId = (string) data_get($data, 'item.id', '');

        if ($item !== '' && $soldId !== '' && $soldId !== $item) {
            return ['ok' => false, 'message' => 'This purchase code is for a different product, not this one.'];
        }

        // Remembered for next time + audit.
        SystemSetting::set('envato_purchase_code', $code, 'string');
        SystemSetting::set('envato_verified_at', now()->toIso8601String(), 'string');

        return [
            'ok'      => true,
            'message' => 'Purchase verified — you can proceed with the update.',
            'buyer'   => (string) data_get($data, 'buyer', ''),
            'item'    => (string) data_get($data, 'item.name', ''),
        ];
    }

    // ------------------------------------------------------------------
    //  Version helpers
    // ------------------------------------------------------------------

    public function currentVersion(): string
    {
        return (string) config('version.version', '1.0.0');
    }

    public function currentBuild(): int
    {
        return (int) config('version.build', 0);
    }

    /** Read the version from config/version.php inside an uploaded ZIP. */
    public function getZipVersion(string $zipPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $candidates = ['config/version.php'];
        for ($i = 0; $i < min($zip->numFiles, 50); $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+)/config/version\.php$#', $name, $m)) {
                $candidates[] = $name;
                break;
            }
        }

        $version = null;
        foreach ($candidates as $candidate) {
            $content = $zip->getFromName($candidate);
            if ($content !== false) {
                if (preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m)) {
                    $version = $m[1];
                }
                break;
            }
        }

        $zip->close();

        return $version;
    }

    // ------------------------------------------------------------------
    //  STEP 1: Backup
    // ------------------------------------------------------------------

    /** @return array{code: string, database: string, dir: string} */
    public function createBackup(): array
    {
        // A full code + DB backup easily exceeds the default 30s max_execution_time
        // — the client hit "Maximum execution time of 30 seconds exceeded" here.
        // Lift the time cap for THIS request so it can finish (@-silenced in case a
        // host disabled the function).
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $version = $this->currentVersion();
        $ts  = now()->format('Y-m-d_His');
        $dir = $this->backupDir . "/v{$version}_{$ts}";

        // ── Diagnostics ────────────────────────────────────────────────────
        // Backup dies on some client servers with a bare 500 and NOTHING in
        // laravel.log. Reason: it's killed by a PHP FATAL (memory_limit
        // exhausted / max_execution_time) inside the DB dump, and a fatal is
        // not a Throwable — the controller's catch never runs and Laravel
        // never gets to write. So we log a breadcrumb BEFORE each step: the
        // LAST "[UPDATER-BACKUP]" line in the log tells you exactly where it
        // died. Grep the log for "[UPDATER-BACKUP]".
        //
        // The shutdown handler below is what actually captures the fatal
        // itself (error_get_last) — it is the only hook that still runs after
        // PHP aborts the request.
        $this->logFatalsForBackup();

        Log::info('[UPDATER-BACKUP] start', [
            'version'       => $version,
            'dir'           => $dir,
            'memory_limit'  => ini_get('memory_limit'),
            'max_exec_time' => ini_get('max_execution_time'),
            'mem_used_mb'   => round(memory_get_usage(true) / 1048576, 1),
        ]);

        File::ensureDirectoryExists($dir, 0755, true);

        $codeZip = $dir . '/code_backup.zip';
        Log::info('[UPDATER-BACKUP] zipping code …');
        $this->zipCodeFiles($codeZip);
        Log::info('[UPDATER-BACKUP] code zip done', [
            'zip_mb'      => is_file($codeZip) ? round(filesize($codeZip) / 1048576, 1) : null,
            'mem_used_mb' => round(memory_get_usage(true) / 1048576, 1),
        ]);

        $dbFile = $dir . '/database_backup.sql';
        Log::info('[UPDATER-BACKUP] dumping database …');
        $this->dumpDatabase($dbFile);
        Log::info('[UPDATER-BACKUP] database dump done', [
            'sql_mb'      => is_file($dbFile) ? round(filesize($dbFile) / 1048576, 1) : null,
            'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        File::put($dir . '/rollback.json', json_encode([
            'version'     => $version,
            'created_at'  => now()->toIso8601String(),
            'code_backup' => $codeZip,
            'db_backup'   => $dbFile,
        ], JSON_PRETTY_PRINT));

        Log::info('[UPDATER-BACKUP] complete', ['dir' => $dir]);

        return ['code' => $codeZip, 'database' => $dbFile, 'dir' => $dir];
    }

    /**
     * Log-only fatal capture for the backup step. A memory/timeout fatal is
     * NOT catchable by try/catch, so without this the request just dies and
     * laravel.log stays empty — which is exactly the "server error with
     * nothing to trace" the operator sees. Registered once per request;
     * writes to the log and changes no behaviour.
     */
    private function logFatalsForBackup(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;

        register_shutdown_function(function () {
            $e = error_get_last();
            if (! $e || ! in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            Log::error('[UPDATER-BACKUP] FATAL — request aborted by PHP', [
                'message'     => $e['message'] ?? '',
                'file'        => ($e['file'] ?? '') . ':' . ($e['line'] ?? ''),
                'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'memory_limit'=> ini_get('memory_limit'),
                'hint'        => 'Allowed memory exhausted / max execution time = the DB dump is too big for this server.',
            ]);
        });
    }

    // ------------------------------------------------------------------
    //  STEP 2: Upload
    // ------------------------------------------------------------------

    public function saveUploadedZip($uploadedFile): string
    {
        File::ensureDirectoryExists($this->tempDir, 0755, true);
        $path = $this->tempDir . '/update.zip';
        $uploadedFile->move($this->tempDir, 'update.zip');

        return $path;
    }

    // ------------------------------------------------------------------
    //  STEP 3: Extract & Apply
    // ------------------------------------------------------------------

    public function applyUpdate(string $zipPath): array
    {
        // Extracting the ZIP + copying the whole tree can also exceed the default
        // 30s max_execution_time — lift it for this request too.
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $stagingDir = $this->tempDir . '/staging';
        if (File::isDirectory($stagingDir)) {
            File::deleteDirectory($stagingDir);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open update ZIP.');
        }
        $zip->extractTo($stagingDir);
        $zip->close();

        // Step into a single root folder wrapper if present.
        $items = File::directories($stagingDir);
        if (count($items) === 1 && count(File::files($stagingDir)) === 0) {
            $stagingDir = $items[0];
        }

        $basePath = base_path();
        $updated  = [];

        foreach (self::UPDATABLE_PATHS as $relPath) {
            $srcPath  = $stagingDir . '/' . $relPath;
            $destPath = $basePath . '/' . $relPath;

            if (! File::exists($srcPath)) {
                continue;
            }

            if (File::isDirectory($srcPath)) {
                if (str_contains($relPath, 'migrations')) {
                    $this->mergeMigrations($srcPath, $destPath);
                } else {
                    $this->safeCopyDirectory($srcPath, $destPath, $relPath);
                }
            } elseif (! $this->isProtected($relPath)) {
                File::ensureDirectoryExists(dirname($destPath));
                File::copy($srcPath, $destPath);
            }

            $updated[] = $relPath;
        }

        foreach (['composer.json', 'composer.lock', '.env.example'] as $rootFile) {
            $srcFile = $stagingDir . '/' . $rootFile;
            if (File::exists($srcFile)) {
                File::copy($srcFile, $basePath . '/' . $rootFile);
            }
        }

        $newVersionFile = $stagingDir . '/config/version.php';
        if (File::exists($newVersionFile)) {
            File::copy($newVersionFile, config_path('version.php'));
        }

        return $updated;
    }

    // ------------------------------------------------------------------
    //  STEP 4: Migrate
    // ------------------------------------------------------------------

    public function runMigrations(): string
    {
        // Fast path: everything applies cleanly (the normal case). Laravel
        // already SKIPS any migration recorded in the `migrations` table, so a
        // re-run is a no-op for those.
        try {
            Artisan::call('migrate', ['--force' => true]);

            return Artisan::output();
        } catch (\Throwable $e) {
            $log = trim(Artisan::output()) . "\n" . $e->getMessage();
        }

        // Resilient path. The one gap Laravel does NOT cover is a brand-new
        // migration FILE whose schema change is ALREADY present in the database
        // (the table/column was added by a prior partial update, a manual
        // hot-fix, or a re-uploaded package). That throws "table/column already
        // exists" and aborts the WHOLE update. Step through each pending file on
        // its own so one such failure can't take the rest down: when a file
        // fails because its change already exists, mark it as run and carry on.
        $log .= "\n\nRetrying migrations one-by-one (skipping already-applied ones)…\n";

        $repository = app('migration.repository');
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
        $ran   = $repository->getRan();                 // names already recorded
        $batch = $repository->getNextBatchNumber();
        $dir   = database_path('migrations');

        foreach (glob($dir . '/*.php') ?: [] as $path) {
            $name = basename($path, '.php');
            if (in_array($name, $ran, true)) {
                continue;                                // truly already run
            }

            try {
                Artisan::call('migrate', [
                    '--path'  => 'database/migrations/' . basename($path),
                    '--force' => true,
                ]);
                $log .= '  migrated: ' . $name . "\n";
            } catch (\Throwable $ex) {
                if ($this->migrationAlreadyApplied($ex)) {
                    // The change is already in the DB — record the migration so
                    // it is never retried, and keep going.
                    $repository->log($name, $batch);
                    $log .= '  skipped (already applied): ' . $name . "\n";
                    continue;
                }
                // A genuine, unexpected failure — surface it so the admin sees it.
                throw new RuntimeException('Migration ' . $name . ' failed: ' . $ex->getMessage(), 0, $ex);
            }
        }

        return $log;
    }

    /**
     * True when a migration error means "this change is already in the database"
     * — i.e. the migration is effectively already applied and is safe to skip
     * rather than abort the update. Covers MySQL/MariaDB, Postgres and SQLite.
     */
    private function migrationAlreadyApplied(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        foreach ([
            'already exists',          // generic / Postgres / SQLite "table ... already exists"
            'duplicate column',        // MySQL: duplicate column
            'duplicate key name',      // MySQL: duplicate index
            'duplicate table',
            '1050',                    // MySQL: table already exists
            '1060',                    // MySQL: duplicate column name
            '1061',                    // MySQL: duplicate key name
        ] as $needle) {
            if (str_contains($msg, $needle)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    //  STEP 5: Finalize
    // ------------------------------------------------------------------

    public function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** @return array<string,bool> */
    public function healthCheck(): array
    {
        $results = [];

        try {
            DB::connection()->getPdo();
            $results['database'] = true;
        } catch (\Throwable $e) {
            $results['database'] = false;
        }

        $results['env_file']        = File::exists(base_path('.env'));
        $results['storage_writable'] = is_writable(storage_path());
        $results['uploads_exist']   = File::isDirectory(storage_path('app/public'));

        return $results;
    }

    // ------------------------------------------------------------------
    //  Rollback
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function listBackups(): array
    {
        if (! File::isDirectory($this->backupDir)) {
            return [];
        }

        $backups = [];
        foreach (File::directories($this->backupDir) as $dir) {
            $rollbackFile = $dir . '/rollback.json';
            if (File::exists($rollbackFile)) {
                $info = json_decode(File::get($rollbackFile), true) ?: [];
                $info['path'] = $dir;
                $backups[] = $info;
            }
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $backups;
    }

    public function rollback(string $backupDir): void
    {
        $rollbackFile = $backupDir . '/rollback.json';
        if (! File::exists($rollbackFile)) {
            throw new RuntimeException('Rollback info not found.');
        }

        $info = json_decode(File::get($rollbackFile), true);

        if (! empty($info['code_backup']) && File::exists($info['code_backup'])) {
            $zip = new ZipArchive();
            if ($zip->open($info['code_backup']) === true) {
                $zip->extractTo(base_path());
                $zip->close();
            }
        }

        if (! empty($info['db_backup']) && File::exists($info['db_backup'])) {
            $this->restoreDatabase($info['db_backup']);
        }

        $this->clearCaches();
    }

    public function cleanup(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }

    // ------------------------------------------------------------------
    //  Private helpers
    // ------------------------------------------------------------------

    private function isProtected(string $relPath): bool
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if ($relPath === $protected || str_starts_with($relPath, $protected . '/')) {
                return true;
            }
        }

        if (str_starts_with($relPath, 'public/')) {
            $allowedCodeDirs = ['public/css/', 'public/js/', 'public/build/'];
            $inCodeDir = false;
            foreach ($allowedCodeDirs as $dir) {
                if (str_starts_with($relPath, $dir)) {
                    $inCodeDir = true;
                    break;
                }
            }
            if (! $inCodeDir) {
                $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                if (in_array($ext, self::PROTECTED_PUBLIC_EXTENSIONS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function safeCopyDirectory(string $srcDir, string $destDir, string $baseRelPath): void
    {
        File::ensureDirectoryExists($destDir, 0755, true);
        foreach (File::allFiles($srcDir) as $file) {
            $relPath = $baseRelPath . '/' . $file->getRelativePathname();
            if ($this->isProtected($relPath)) {
                continue;
            }
            $dest = $destDir . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists(dirname($dest), 0755, true);
            File::copy($file->getPathname(), $dest);
        }
    }

    private function mergeMigrations(string $srcDir, string $destDir): void
    {
        File::ensureDirectoryExists($destDir, 0755, true);
        foreach (File::files($srcDir) as $file) {
            $dest = $destDir . '/' . $file->getFilename();
            if (! File::exists($dest)) {
                File::copy($file->getPathname(), $dest);
            }
        }
    }

    private function zipCodeFiles(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create backup ZIP.');
        }

        $basePath = base_path();
        foreach (self::UPDATABLE_PATHS as $relPath) {
            $fullPath = $basePath . '/' . $relPath;
            if (File::isDirectory($fullPath)) {
                foreach (File::allFiles($fullPath) as $file) {
                    $zip->addFile($file->getPathname(), $relPath . '/' . $file->getRelativePathname());
                }
            } elseif (File::exists($fullPath)) {
                $zip->addFile($fullPath, $relPath);
            }
        }

        $zip->close();
    }

    private function dumpDatabase(string $path): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            if (File::exists($dbPath)) {
                File::copy($dbPath, $path);
            }
            return;
        }

        if ($driver === 'mysql') {
            // mysqldump is unreliable on shared hosting — go straight to the
            // PHP dump which works everywhere.
            $this->dumpDatabasePhp($path);
            return;
        }

        File::put($path, "-- Database backup not supported for driver: {$driver}\n");
    }

    /**
     * Tables whose SCHEMA is backed up but whose ROWS are not.
     *
     * These are ephemeral runtime state — nothing in them is needed to roll an
     * update back, and they are exactly what used to kill the backup: on a live
     * install `notifications` alone reached 194,298 rows and blew a 512 MB
     * memory_limit (peak 510.6 MB) mid-dump, aborting the request with a PHP
     * FATAL that no try/catch could catch.
     *
     * The CREATE TABLE is still written, so a restore rebuilds them empty and
     * the app works normally — the queue refills, the cache re-warms, and the
     * notification feed starts fresh.
     */
    private const BACKUP_SKIP_ROWS = [
        'notifications',      // UI feed — grows unbounded, 194k rows seen live
        'jobs', 'job_batches', 'failed_jobs',   // queue state
        'cache', 'cache_locks',                 // ephemeral cache
        'sessions',                             // login sessions
    ];

    private function dumpDatabasePhp(string $path): void
    {
        // A big dump is slow rather than heavy now that it streams, so the
        // remaining risk is the execution-time ceiling. Best-effort lift.
        @set_time_limit(0);

        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.' . config('database.default') . '.database');
        $key = "Tables_in_{$dbName}";

        // STREAM to disk. The old version concatenated the ENTIRE dump into one
        // PHP string and only wrote at the end, so peak memory was ~the whole
        // database (plus PHP string overhead). Writing row-by-row keeps memory
        // flat regardless of database size.
        $fh = @fopen($path, 'w');
        if ($fh === false) {
            throw new RuntimeException('Cannot open backup file for writing: ' . $path);
        }

        fwrite($fh, "-- WaDesk Database Backup\n-- Date: " . now()->toIso8601String() . "\n\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        Log::info('[UPDATER-BACKUP] db dump: tables found', ['count' => count($tables)]);

        foreach ($tables as $table) {
            $tableName = $table->$key;
            $skipRows  = in_array($tableName, self::BACKUP_SKIP_ROWS, true);

            $create = DB::select("SHOW CREATE TABLE `{$tableName}`");
            fwrite($fh, "DROP TABLE IF EXISTS `{$tableName}`;\n");
            fwrite($fh, $create[0]->{'Create Table'} . ";\n\n");

            if ($skipRows) {
                fwrite($fh, "-- rows intentionally skipped (ephemeral runtime table)\n\n");
                Log::info('[UPDATER-BACKUP] db dump: table (schema only)', ['table' => $tableName]);
                continue;
            }

            $written = $this->writeTableRows($fh, $tableName);
            fwrite($fh, "\n");

            // Per-table breadcrumb — the LAST line before a crash names the
            // table that caused it. Memory should now stay flat across tables.
            Log::info('[UPDATER-BACKUP] db dump: table', [
                'table'       => $tableName,
                'rows'        => $written,
                'file_mb'     => round((int) @filesize($path) / 1048576, 1),
                'mem_used_mb' => round(memory_get_usage(true) / 1048576, 1),
            ]);
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    }

    /**
     * Write one table's rows as INSERT statements, reading in chunks so a
     * multi-hundred-thousand-row table never lands in memory at once.
     *
     * Chunks by primary key where there is one (keyset pagination — stays fast
     * on huge tables, unlike LIMIT/OFFSET). Falls back to an unbuffered cursor
     * when the table has no single-column PK.
     *
     * @param resource $fh
     */
    private function writeTableRows($fh, string $tableName): int
    {
        $pk = $this->primaryKeyColumn($tableName);
        $n  = 0;

        $writeRow = function ($row) use ($fh, $tableName, &$n): void {
            $values = collect((array) $row)->map(function ($val) {
                return is_null($val) ? 'NULL' : "'" . addslashes((string) $val) . "'";
            })->implode(', ');
            fwrite($fh, "INSERT INTO `{$tableName}` VALUES ({$values});\n");
            $n++;
        };

        if ($pk !== null) {
            $last = null;
            while (true) {
                $q = DB::table($tableName)->orderBy($pk)->limit(1000);
                if ($last !== null) {
                    $q->where($pk, '>', $last);
                }
                $rows = $q->get();
                if ($rows->isEmpty()) {
                    break;
                }
                foreach ($rows as $row) {
                    $writeRow($row);
                    $last = $row->{$pk};
                }
                unset($rows);   // release the chunk before fetching the next
            }

            return $n;
        }

        foreach (DB::table($tableName)->cursor() as $row) {
            $writeRow($row);
        }

        return $n;
    }

    /** Single-column PRIMARY KEY of a table, or null when there isn't one. */
    private function primaryKeyColumn(string $tableName): ?string
    {
        try {
            $keys = DB::select("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            if (count($keys) === 1 && isset($keys[0]->Column_name)) {
                return (string) $keys[0]->Column_name;
            }
        } catch (\Throwable $e) {
            // Fall through to the cursor path.
        }

        return null;
    }

    private function restoreDatabase(string $path): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            File::copy($path, config("database.connections.{$connection}.database"));
            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared(File::get($path));
        }
    }
}
