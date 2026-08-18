<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (campaign, contact) pair. UNIQUE prevents a single
     * contact from re-entering the same drip if their tag is removed
     * and re-added — they pick up where they left off semantically by
     * NEVER starting over (existing flow session on Node has its own
     * lifecycle).
     */
    public function up(): void
    {
        Schema::create('drip_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('drip_campaign_id');
            $table->unsignedBigInteger('contact_id');

            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 191)->nullable();

            // 'active'   → flow session running on Node
            // 'paused'   → operator paused this subscriber manually
            // 'completed'→ flow reached an `end` node
            // 'failed'   → Node side rejected the start (device offline, bad creds, etc.)
            $table->string('status', 16)->default('active');

            $table->timestamps();

            $table->unique(['drip_campaign_id', 'contact_id']);
            $table->index('contact_id');
            $table->index(['drip_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_subscribers');
    }
};
