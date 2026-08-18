<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Batch J / finding #21 — remove the already-exposed flow graph mirrors from
 * the public web root.
 *
 * Flow::saveFlowFile() now writes each flow's node graph under the NON-public
 * storage/app/flows directory, but any flow saved before this change left a
 * copy at public/uploads/flows/flow_{id}.json that the web server serves as a
 * static asset — enumerable cross-tenant with no auth. This one-time cleanup
 * MOVES those files into storage/app/flows (preserving data for any legacy
 * flow that has no flow_data DB column yet) and re-points flow_file_path rows
 * that still reference the old public location onto the new storage path. After
 * this runs, nothing under public/uploads/flows is web-served.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Move every exposed public JSON mirror into the non-public storage
        //    dir so the data is preserved but no longer web-served.
        $publicDir  = public_path('uploads/flows');
        $privateDir = storage_path('app/flows');
        if (is_dir($publicDir)) {
            if (! is_dir($privateDir)) {
                @mkdir($privateDir, 0755, true);
            }
            foreach (glob($publicDir . DIRECTORY_SEPARATOR . 'flow_*.json') ?: [] as $file) {
                $dest = $privateDir . DIRECTORY_SEPARATOR . basename($file);
                if (is_file($dest)) {
                    @unlink($file);            // private copy already exists
                } elseif (! @rename($file, $dest)) {
                    if (@copy($file, $dest)) { // cross-device fallback
                        @unlink($file);
                    }
                }
            }
        }

        // 2) Re-point any DB rows that still store the legacy public path so the
        //    file mirror (a fallback for the DB flow_data column) resolves to the
        //    new non-public location. Guarded so it is safe on older schemas.
        if (Schema::hasTable('flows') && Schema::hasColumn('flows', 'flow_file_path')) {
            foreach (DB::table('flows')
                ->where('flow_file_path', 'like', 'uploads/flows/%')
                ->get(['id']) as $flow) {
                DB::table('flows')
                    ->where('id', $flow->id)
                    ->update(['flow_file_path' => 'flows/flow_' . $flow->id . '.json']);
            }
        }
    }

    public function down(): void
    {
        // Irreversible: we never recreate publicly-served copies of flow graphs.
    }
};
