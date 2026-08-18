<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-variant send-time overrides for A/B campaigns.
 *
 * A/B was built with two of everything EXCEPT this: `template_id_a` /
 * `template_id_b` and `flow_id` / `flow_id_b` both exist, but there was only
 * one `template_overrides` blob. The send loop flips template, extras, media
 * and payload per recipient variant — then applied variant A's overrides to
 * whichever template it had just chosen.
 *
 * With two DIFFERENT templates that is not merely a missing form: variant B
 * was sent A's mappings against B's variables, so B's fields came out blank
 * or filled from the wrong slot. Same failure shape as the WABA `{{1}}`
 * header bug — a value resolved against the wrong schema.
 *
 * Nullable, no backfill: existing campaigns keep one blob and behave exactly
 * as before. Only an A/B campaign with a distinct B template uses this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wpcampaigns', function (Blueprint $t) {
            if (!Schema::hasColumn('wpcampaigns', 'template_overrides_b')) {
                $t->longText('template_overrides_b')->nullable()->after('template_overrides');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wpcampaigns', function (Blueprint $t) {
            if (Schema::hasColumn('wpcampaigns', 'template_overrides_b')) {
                $t->dropColumn('template_overrides_b');
            }
        });
    }
};
