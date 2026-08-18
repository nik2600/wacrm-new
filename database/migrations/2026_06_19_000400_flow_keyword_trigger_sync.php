<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the flow builder's KEYWORD trigger actually fire the flow on inbound.
 *
 * The trigger node stores its keywords inside the (encrypted) flow_data JSON,
 * which the inbound keyword matcher (/api/keyword-replies → keyword_replies)
 * never sees. So a keyword set on the Trigger node never started the flow.
 *
 * Fix: mirror the keyword(s) onto flows.trigger_keywords (queryable), and on
 * every flow save sync a MANAGED keyword_replies row (reply_type='flow') that
 * the existing inbound matcher already understands. `is_flow_trigger` marks
 * those managed rows so the sync can replace them without touching the
 * operator's hand-made auto-replies.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $t) {
            if (!Schema::hasColumn('flows', 'trigger_keywords')) {
                $t->string('trigger_keywords', 1000)->nullable()->after('trigger_value');
            }
        });

        Schema::table('keyword_replies', function (Blueprint $t) {
            if (!Schema::hasColumn('keyword_replies', 'is_flow_trigger')) {
                $t->boolean('is_flow_trigger')->default(false)->after('flow_id');
                $t->index(['flow_id', 'is_flow_trigger']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('keyword_replies', function (Blueprint $t) {
            if (Schema::hasColumn('keyword_replies', 'is_flow_trigger')) {
                $t->dropIndex(['flow_id', 'is_flow_trigger']);
                $t->dropColumn('is_flow_trigger');
            }
        });
        Schema::table('flows', function (Blueprint $t) {
            if (Schema::hasColumn('flows', 'trigger_keywords')) {
                $t->dropColumn('trigger_keywords');
            }
        });
    }
};
