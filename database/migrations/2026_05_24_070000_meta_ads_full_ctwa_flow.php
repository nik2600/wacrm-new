<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta Ads — full CTWA (Click-to-WhatsApp) hierarchy storage.
 *
 * Before: only `facebook_id` (the Campaign id) was stored. Meta
 * actually requires FIVE entities for a CTWA ad to run:
 *   1. Ad Image (returns `image_hash`)
 *   2. Campaign (returns campaign id)
 *   3. Ad Set (returns ad_set id, holds targeting + budget + destination_type=WHATSAPP)
 *   4. Ad Creative (returns creative id, holds object_story_spec.link_data with WHATSAPP_MESSAGE CTA)
 *   5. Ad (returns ad id, links creative + adset)
 *
 * Without storing each id we can't toggle/edit/delete on Meta's side
 * because the entities form a tree — deleting just the Campaign leaves
 * orphan ad sets, creatives, ads inside the customer's ad account.
 *
 * Also adds `meta_last_error` so partial-failure debugging surfaces
 * the actual Meta error string on the campaign card instead of "?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_campaigns', function (Blueprint $table) {
            $table->string('meta_adset_id', 64)->nullable()->after('facebook_id');
            $table->string('meta_creative_id', 64)->nullable()->after('meta_adset_id');
            $table->string('meta_ad_id', 64)->nullable()->after('meta_creative_id');
            $table->string('meta_image_hash', 191)->nullable()->after('meta_ad_id');
            $table->text('meta_last_error')->nullable()->after('meta_image_hash');
            $table->timestamp('meta_synced_at')->nullable()->after('meta_last_error');

            $table->index(['user_id', 'meta_ad_id']);
        });

        // Per-workspace Meta Ads connection. Stored on the same
        // wa_provider_configs row as the WABA connection because:
        //   - same merchant manages both Meta business assets
        //   - CTWA requires page_id + whatsapp_business_account_id
        //     to wire the ad to the WABA number — both already live
        //     in meta_json there
        //   - the access_token can be the SAME long-lived System User
        //     token if the user granted ads_management at OAuth time
        //
        // The migration just documents the convention; the columns
        // exist already (meta_json + credentials_json). Keep the
        // additional keys in meta_json:
        //   meta_json.fb_page_id         — Facebook Page that owns the ad
        //   meta_json.fb_ad_account_id   — act_XXXX without the prefix
        //   credentials_json.ads_token    — falls back to access_token if absent
        //
        // No schema change here — just intent documentation via
        // SystemSetting flag for the admin to know it's optional.
        if (Schema::hasTable('system_settings')) {
            \App\Models\SystemSetting::set('meta_ads_graph_api_version', 'v23.0', 'string', 'Meta Marketing API version. v23+ required from Q2 2025.');
            \App\Models\SystemSetting::set('meta_ads_enabled', false, 'bool', 'When ON, the CTWA 5-step flow (image + campaign + adset + creative + ad) fires on save. Default OFF preserves local-only campaign list.');
        }
    }

    public function down(): void
    {
        Schema::table('meta_campaigns', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'meta_ad_id']);
            $table->dropColumn([
                'meta_adset_id', 'meta_creative_id', 'meta_ad_id',
                'meta_image_hash', 'meta_last_error', 'meta_synced_at',
            ]);
        });
    }
};
