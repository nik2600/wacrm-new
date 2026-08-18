<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add-ons v2 — an add-on now declares EXACTLY what it grants in `grants_json`
 * instead of borrowing the plan's limit columns (where a blank field became 0,
 * and 0 = "unlimited", so a Campaigns add-on silently granted unlimited caps it
 * never meant to). `grants_json` shape:
 *
 *   { "features": ["access_campaigns", ...],
 *     "limits":   { "device_limit": 2, ... } }   // deltas; absent = not granted
 *
 * Plans are untouched — they keep using the numeric columns exactly as before.
 * We backfill existing add-on rows from their current non-default columns so
 * live add-ons keep working and open cleanly in the new editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('packages', 'grants_json')) {
            Schema::table('packages', function (Blueprint $table) {
                // Nullable JSON — NULL means "legacy add-on / plan" (helpers fall
                // back to reading the raw columns with corrected semantics).
                $table->json('grants_json')->nullable()->after('type');
            });
        }

        // Backfill existing add-ons: features = toggles currently true; limits =
        // columns currently > 0 (a 0 was "blank", so it must NOT become a grant).
        $features = \App\Http\Controllers\AdminPagesController::planFeatureToggles();
        $limits   = \App\Http\Controllers\AdminPagesController::planLimitColumns();

        DB::table('packages')->where('type', 'addon')->whereNull('grants_json')
            ->orderBy('id')->chunkById(200, function ($rows) use ($features, $limits) {
                foreach ($rows as $row) {
                    $grantFeatures = [];
                    foreach ($features as $f) {
                        if (isset($row->$f) && (int) $row->$f === 1) $grantFeatures[] = $f;
                    }
                    $grantLimits = [];
                    foreach ($limits as $l) {
                        if (isset($row->$l) && (int) $row->$l > 0) $grantLimits[$l] = (int) $row->$l;
                    }
                    DB::table('packages')->where('id', $row->id)->update([
                        'grants_json' => json_encode([
                            'features' => array_values($grantFeatures),
                            'limits'   => $grantLimits,
                        ]),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('packages', 'grants_json')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('grants_json');
            });
        }
    }
};
