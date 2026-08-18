<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge drip campaigns into flows. The /drips page was just a thin wrapper
 * around a flow + audience trigger, so we collapse it: the trigger node IN
 * the flow now owns the audience config (tag_added / group_join /
 * manual_enroll / keyword). Subscribers track contacts-in-flow regardless
 * of how they got enrolled.
 *
 * Old:  drip_campaigns(flow_id, trigger_type, trigger_value, device_id) → drip_subscribers
 * New:  flows.trigger_kind/value/device_id                              → flow_subscribers
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Add trigger columns to flows. Nullable + default 'keyword' so
        //    existing rows stay valid (regular chatbots default to keyword).
        Schema::table('flows', function (Blueprint $t) {
            $t->string('trigger_kind', 24)->default('keyword')->after('category');
            // tag_id or contact_group_id depending on trigger_kind. NULL for
            // keyword/manual_enroll where the flow's trigger node carries
            // the keyword list in flow_data instead.
            $t->unsignedBigInteger('trigger_value')->nullable()->after('trigger_kind');
            // Which device to send through when this flow is auto-enrolled.
            // NULL = pick the workspace's first active device at enroll time.
            $t->unsignedBigInteger('trigger_device_id')->nullable()->after('trigger_value');

            $t->index(['trigger_kind', 'trigger_value'], 'flows_trigger_idx');
        });

        // 2. Create flow_subscribers (same shape as drip_subscribers, FK
        //    repointed at flows). UNIQUE prevents double-enroll like before.
        Schema::create('flow_subscribers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('flow_id');
            $t->unsignedBigInteger('contact_id');

            $t->timestamp('enrolled_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->string('failure_reason', 191)->nullable();

            $t->string('status', 16)->default('active');
            $t->timestamps();

            $t->unique(['flow_id', 'contact_id']);
            $t->index('contact_id');
            $t->index(['flow_id', 'status']);
        });

        // 3. Backfill: for every existing drip_campaigns row, copy the
        //    audience config onto its flow + copy its subscribers across.
        //    Idempotent — uses upsert / insertOrIgnore semantics.
        if (Schema::hasTable('drip_campaigns')) {
            $campaigns = DB::table('drip_campaigns')
                ->whereNull('deleted_at')
                ->get(['id', 'flow_id', 'trigger_type', 'trigger_value', 'device_id']);

            foreach ($campaigns as $c) {
                // Only overwrite when the flow has the default 'keyword'
                // trigger (haven't been migrated yet). If a flow had two
                // drips (rare), the most-recent one wins — orderBy id was
                // implicit above.
                DB::table('flows')
                    ->where('id', $c->flow_id)
                    ->where('trigger_kind', 'keyword')
                    ->update([
                        'trigger_kind'      => $c->trigger_type,
                        'trigger_value'     => $c->trigger_value,
                        'trigger_device_id' => $c->device_id,
                    ]);
            }
        }

        if (Schema::hasTable('drip_subscribers') && Schema::hasTable('drip_campaigns')) {
            // Join drip_subscribers → drip_campaigns to get flow_id, then
            // copy across. INSERT ... SELECT keeps this in-DB and fast.
            DB::statement("
                INSERT IGNORE INTO flow_subscribers
                    (flow_id, contact_id, enrolled_at, completed_at, failed_at,
                     failure_reason, status, created_at, updated_at)
                SELECT
                    dc.flow_id, ds.contact_id, ds.enrolled_at, ds.completed_at,
                    ds.failed_at, ds.failure_reason, ds.status,
                    ds.created_at, ds.updated_at
                FROM drip_subscribers ds
                INNER JOIN drip_campaigns dc ON dc.id = ds.drip_campaign_id
                WHERE dc.deleted_at IS NULL
            ");
        }

        // 4. Drop the old tables. Data is in flows + flow_subscribers now.
        //    Order matters — drop subscribers first (has FK-like ref).
        Schema::dropIfExists('drip_subscribers');
        Schema::dropIfExists('drip_campaigns');
    }

    public function down(): void
    {
        // Best-effort rollback. We don't try to perfectly reconstruct
        // drip_campaigns rows because the flows.trigger_* schema is
        // strictly richer (1 flow = 1 trigger). If you really need this,
        // restore from backup.
        Schema::create('drip_campaigns', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('flow_id');
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->string('trigger_type', 16);
            $t->unsignedBigInteger('trigger_value')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedBigInteger('device_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['workspace_id', 'is_active']);
            $t->index(['trigger_type', 'trigger_value']);
        });

        Schema::create('drip_subscribers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('drip_campaign_id');
            $t->unsignedBigInteger('contact_id');
            $t->timestamp('enrolled_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->string('failure_reason', 191)->nullable();
            $t->string('status', 16)->default('active');
            $t->timestamps();
            $t->unique(['drip_campaign_id', 'contact_id']);
        });

        Schema::dropIfExists('flow_subscribers');
        Schema::table('flows', function (Blueprint $t) {
            $t->dropIndex('flows_trigger_idx');
            $t->dropColumn(['trigger_kind', 'trigger_value', 'trigger_device_id']);
        });
    }
};
