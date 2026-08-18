<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace customizable footer + platform-default footer.
 *
 * When a workspace's plan grants `remove_branding`, the operator can put
 * their own footer in `workspaces.branding_footer` (or leave blank for
 * none). Otherwise the platform's admin-fixed footer applies — same
 * value across every workspace, single source of truth in system_settings.
 *
 * Templates are excluded — Meta-approved content can't be mutated post-
 * submission. The injection runs in the outbound dispatcher for plain
 * text + interactive messages only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            // 60 chars matches WABA's interactive footer cap. Plain text
            // bodies get this appended as a separate line so cap still
            // applies to keep prefixes consistent across surfaces.
            $t->string('branding_footer', 60)->nullable()->after('brand_favicon_path');
        });

        // Seed platform-fixed default. Pulls the app name out of
        // system_settings so it auto-tracks rebrands (admin renames
        // "WaDesk" → "MyChat" → the default footer follows). MUST use
        // the model's set() helper so the `type` column is 'string'
        // (raw insert with no type defaulted to 'int' on this schema,
        // which made get() cast "Sent via WaDesk" → 0).
        if (Schema::hasTable('system_settings')) {
            $existing = DB::table('system_settings')->where('key', 'platform_branding_footer')->first();
            $appName = (string) (DB::table('system_settings')->where('key', 'app_name')->value('value') ?: 'WaDesk');
            if (!$existing) {
                \App\Models\SystemSetting::set(
                    'platform_branding_footer',
                    'Sent via ' . $appName,
                    'string',
                    'Outbound message footer (applied when workspace plan does not grant remove_branding)',
                );
            } elseif ($existing->type !== 'string') {
                // Repair pre-existing row from an earlier migration draft
                // that inserted without specifying type.
                DB::table('system_settings')
                    ->where('key', 'platform_branding_footer')
                    ->update(['type' => 'string']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropColumn('branding_footer');
        });
        DB::table('system_settings')->where('key', 'platform_branding_footer')->delete();
    }
};
