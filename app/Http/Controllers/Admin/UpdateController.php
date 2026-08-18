<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdaterService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin → Updater. Verify CodeCanyon purchase → backup → upload package →
 * apply code → migrate → finalize, with one-click rollback. The admin route
 * group already enforces platform-admin access; we additionally require the
 * Super Admin role for these high-impact, file-system-mutating actions.
 */
class UpdateController extends Controller
{
    public function __construct(private UpdaterService $updater)
    {
        // Access is already gated by the admin route group (every /admin/*
        // route requires a platform admin), so no extra middleware here.
    }

    public function index()
    {
        return view('admin.update.index', [
            'currentVersion' => $this->updater->currentVersion(),
            'currentBuild'   => $this->updater->currentBuild(),
            'backups'        => $this->updater->listBackups(),
            'verified'       => (bool) \App\Models\SystemSetting::get('envato_purchase_code', ''),
        ]);
    }

    /** Step 0: verify CodeCanyon purchase code against Envato. */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['purchase_code' => ['required', 'string', 'max:120']]);

        $result = $this->updater->verifyPurchase((string) $request->input('purchase_code'));

        Audit::log('admin.updater.verify', [
            'result' => $result['ok'] ? 'success' : 'failure',
            'meta'   => ['item' => $result['item'] ?? null],
        ]);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'buyer'   => $result['buyer'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Step 1: backup files + database. */
    public function backup(): JsonResponse
    {
        try {
            $result = $this->updater->createBackup();
            Audit::log('admin.updater.backup', ['meta' => ['dir' => $result['dir'] ?? null]]);

            return response()->json(['success' => true, 'message' => 'Backup created successfully.', 'backup' => $result]);
        } catch (\Throwable $e) {
            // Grep "[UPDATER-BACKUP]" in storage/logs/laravel.log. NOTE: a
            // memory/timeout FATAL never reaches here (it isn't a Throwable) —
            // that case is captured by the shutdown handler in UpdaterService.
            Log::error('[UPDATER-BACKUP] failed', [
                'error'       => $e->getMessage(),
                'file'        => $e->getFile() . ':' . $e->getLine(),
                'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);

            return response()->json(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 2: upload + validate the update ZIP. */
    public function upload(Request $request): JsonResponse
    {
        // ---- Diagnostics: log the request shape so a failed upload can be
        // traced in storage/logs/laravel.log (grep "[UPDATER-UPLOAD]"). This
        // is where "Upload failed" with no visible reason gets explained —
        // server limits, missing file, bad mime, version gate, or exception.
        $file          = $request->file('file');
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        Log::info('[UPDATER-UPLOAD] received', [
            'ip'                  => $request->ip(),
            'user_id'             => optional($request->user())->id,
            'content_length'      => $contentLength,
            'content_length_mb'   => round($contentLength / 1048576, 2),
            'has_file'            => $file !== null,
            'files_superglobal'   => array_keys($_FILES),
            'file_error_code'     => $file ? null : ($_FILES['file']['error'] ?? 'no-file-key'),
            'client_name'         => $file?->getClientOriginalName(),
            'client_size_mb'      => $file ? round($file->getSize() / 1048576, 2) : null,
            'client_mime'         => $file?->getClientMimeType(),
            'is_valid'            => $file?->isValid(),
            'php_post_max_size'   => ini_get('post_max_size'),
            'php_upload_max'      => ini_get('upload_max_filesize'),
            'php_max_file_uploads'=> ini_get('max_file_uploads'),
        ]);

        // When the ZIP is bigger than PHP's post_max_size, PHP silently drops
        // the whole body: $_POST + $_FILES arrive EMPTY even though the browser
        // sent bytes (CONTENT_LENGTH > 0). Laravel would then just say "the file
        // field is required" — useless. Detect it and return the REAL reason +
        // the exact server limits to raise.
        if ($contentLength > 0 && empty($_FILES) && $file === null) {
            $postMax = ini_get('post_max_size') ?: '?';
            $upMax   = ini_get('upload_max_filesize') ?: '?';
            Log::warning('[UPDATER-UPLOAD] rejected: body dropped by PHP post_max_size', [
                'content_length_mb' => round($contentLength / 1048576, 2),
                'post_max_size'     => $postMax,
                'upload_max'        => $upMax,
            ]);
            return response()->json([
                'success' => false,
                'message' => "Upload exceeded the server limit (sent ~" . round($contentLength / 1048576, 1) . " MB; PHP post_max_size={$postMax}, upload_max_filesize={$upMax}). Raise post_max_size + upload_max_filesize to 64M in php.ini (and nginx client_max_body_size 64M), restart php-fpm + reload nginx, then retry.",
            ], 413);
        }

        // A present-but-invalid upload = a PHP upload error code (e.g.
        // UPLOAD_ERR_INI_SIZE when it beat post_max but blew upload_max_filesize,
        // or a partial/interrupted transfer). Surface the exact code.
        if ($file !== null && ! $file->isValid()) {
            Log::warning('[UPDATER-UPLOAD] rejected: invalid upload', [
                'error_code'    => $file->getError(),
                'error_message' => $file->getErrorMessage(),
                'upload_max'    => ini_get('upload_max_filesize'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Upload did not complete: ' . $file->getErrorMessage() . ' Check PHP upload_max_filesize / post_max_size (raise to 64M) and retry.',
            ], 422);
        }

        try {
            $request->validate(['file' => ['required', 'file', 'mimes:zip', 'max:512000']]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[UPDATER-UPLOAD] rejected: validation failed', [
                'errors'      => $e->errors(),
                'has_file'    => $file !== null,
                'client_mime' => $file?->getClientMimeType(),
            ]);
            throw $e;
        }

        try {
            $path = $this->updater->saveUploadedZip($request->file('file'));
            $zipVersion = $this->updater->getZipVersion($path);
            Log::info('[UPDATER-UPLOAD] saved + parsed', ['path' => $path, 'zip_version' => $zipVersion]);

            if (! $zipVersion) {
                $this->updater->cleanup();
                Log::warning('[UPDATER-UPLOAD] rejected: no version in ZIP', ['path' => $path]);
                return response()->json(['success' => false, 'message' => 'Invalid update package — no version info found (config/version.php missing in ZIP).'], 422);
            }

            $current = $this->updater->currentVersion();
            if (version_compare($zipVersion, $current, '<=')) {
                $this->updater->cleanup();
                Log::warning('[UPDATER-UPLOAD] rejected: version not newer', ['zip' => $zipVersion, 'current' => $current]);
                return response()->json(['success' => false, 'message' => "ZIP contains v{$zipVersion} but you already have v{$current}. Upload a newer version."], 422);
            }

            Log::info('[UPDATER-UPLOAD] accepted', ['zip_version' => $zipVersion]);
            return response()->json(['success' => true, 'message' => "Update package v{$zipVersion} ready to install.", 'zip_version' => $zipVersion]);
        } catch (\Throwable $e) {
            Log::error('[UPDATER-UPLOAD] exception while saving/parsing ZIP', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 3: extract + apply code files. */
    public function apply(Request $request): JsonResponse
    {
        $zipPath = storage_path('app/temp/updater/update.zip');
        if (! file_exists($zipPath)) {
            return response()->json(['success' => false, 'message' => 'No update ZIP found. Please upload first.'], 400);
        }

        try {
            $updated = $this->updater->applyUpdate($zipPath);
            Audit::log('admin.updater.apply', ['meta' => ['paths' => $updated]]);

            return response()->json(['success' => true, 'message' => 'Files updated successfully.', 'updated' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Apply failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 4: run new migrations. */
    public function migrate(): JsonResponse
    {
        try {
            $output = $this->updater->runMigrations();
            Audit::log('admin.updater.migrate');

            return response()->json(['success' => true, 'message' => 'Migrations completed.', 'output' => $output]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 5: clear caches + health check + cleanup. */
    public function finalize(): JsonResponse
    {
        try {
            $this->updater->clearCaches();
            $health = $this->updater->healthCheck();
            $this->updater->cleanup();

            $allGood = ! in_array(false, $health, true);
            Audit::log('admin.updater.finalize', ['meta' => ['health' => $health]]);

            return response()->json([
                'success'     => $allGood,
                'message'     => $allGood ? 'Update completed successfully.' : 'Update done, but the health check raised warnings.',
                'health'      => $health,
                'new_version' => config('version.version'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Finalize failed: ' . $e->getMessage()], 500);
        }
    }

    /** Restore a previous backup. */
    public function rollback(Request $request): JsonResponse
    {
        $request->validate(['backup_dir' => ['required', 'string']]);

        $backupBase = realpath(storage_path('app/backups'));
        $targetDir  = realpath($request->input('backup_dir'));
        if (! $targetDir || ! $backupBase || ! str_starts_with($targetDir, $backupBase)) {
            return response()->json(['success' => false, 'message' => 'Invalid backup path.'], 403);
        }

        try {
            $this->updater->rollback($targetDir);
            Audit::log('admin.updater.rollback', ['meta' => ['dir' => $targetDir]]);

            return response()->json(['success' => true, 'message' => 'Rollback completed. Version restored.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Rollback failed: ' . $e->getMessage()], 500);
        }
    }
}
