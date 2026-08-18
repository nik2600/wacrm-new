<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A drip campaign = (audience trigger) + (existing flow). The flow
     * supplies the message sequence + delays + branching — the campaign
     * row just maps "when X happens, run flow Y for that contact".
     *
     * No drip_steps table — flows already are the steps. No Laravel cron
     * either — the Node flow runtime owns timing once a session starts.
     */
    public function up(): void
    {
        Schema::create('drip_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('flow_id');

            $table->string('name', 191);
            $table->string('description', 500)->nullable();

            // 'manual'      → operator subscribes contacts via UI / bulk action
            // 'tag_added'   → auto-enroll when a tag attaches to a contact (trigger_value = tag_id)
            // 'group_join'  → auto-enroll when a contact joins a group (trigger_value = contact_group id)
            $table->string('trigger_type', 16);
            $table->unsignedBigInteger('trigger_value')->nullable();

            $table->boolean('is_active')->default(true);

            // Optional: which device to send through. NULL = pick the
            // workspace's primary device at enrollment time. Specified
            // here so multi-device workspaces can route specific drips
            // to specific numbers.
            $table->unsignedBigInteger('device_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'is_active']);
            $table->index(['trigger_type', 'trigger_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_campaigns');
    }
};
