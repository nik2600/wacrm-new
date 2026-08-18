<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Cloud-API calling — per-call ledger.
 *
 *   wa_calls            — one row per call, regardless of direction
 *   wa_call_events      — every webhook + action row for forensics
 *   wa_call_permissions — Meta's user-grant cache (7-day TTL on Meta's
 *                         side; we mirror locally so the dial UI can
 *                         decide when to re-prompt without burning a
 *                         Meta API call)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('wa_provider_config_id')->nullable()->constrained('wa_provider_configs')->nullOnDelete();

            // Meta's call id (from call.connect webhook) — our handle
            // for every subsequent action POST. Unique so duplicate
            // webhook deliveries can't create ghost rows.
            $t->string('meta_call_id', 128)->nullable()->unique();
            // Our own correlation id, echoed in biz_opaque_callback_data.
            $t->uuid('correlation_id')->nullable()->unique();

            $t->enum('direction', ['USER_INITIATED', 'BUSINESS_INITIATED']);
            $t->string('from_phone', 32);
            $t->string('to_phone', 32);

            $t->foreignId('contact_id')->nullable();
            $t->foreignId('conversation_id')->nullable();

            // Who actually picked up. operator/ai_agent/voicemail/none.
            $t->enum('handler_type', ['operator', 'ai_agent', 'voicemail', 'none'])->nullable();
            $t->foreignId('handler_user_id')->nullable();
            $t->foreignId('handler_agent_id')->nullable();

            $t->enum('status', ['ringing', 'connecting', 'active', 'ended', 'failed'])->default('ringing');
            // COMPLETED / REJECTED / BUSY / NO_ANSWER / FAILED /
            // MISSED / CANCELLED. Mirrors Meta's terminate webhook.
            $t->string('end_reason', 32)->nullable();

            $t->timestamp('started_at')->nullable();
            $t->timestamp('answered_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->integer('duration_sec')->default(0);

            $t->string('recording_path', 255)->nullable();
            $t->text('transcript')->nullable();
            $t->json('error_payload')->nullable();
            $t->json('meta_payload')->nullable();
            $t->timestamps();

            $t->index(['workspace_id', 'status']);
            $t->index(['contact_id', 'started_at']);
        });

        Schema::create('wa_call_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wa_call_id')->constrained('wa_calls')->cascadeOnDelete();
            // connect / terminate / accept_sent / reject_sent /
            // terminate_sent / permission_update / ai_handoff / etc.
            $t->string('event_type', 48);
            $t->json('payload')->nullable();
            $t->timestamp('received_at')->useCurrent();

            $t->index(['wa_call_id', 'received_at']);
        });

        Schema::create('wa_call_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('wa_provider_config_id')->nullable()->constrained('wa_provider_configs')->nullOnDelete();
            $t->string('contact_phone', 32);

            $t->enum('status', ['granted', 'declined', 'expired'])->default('granted');
            $t->timestamp('granted_at')->nullable();
            $t->timestamp('expires_at')->nullable();

            $t->timestamps();

            $t->unique(['wa_provider_config_id', 'contact_phone'], 'uq_wa_perm_cfg_phone');
            $t->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_call_permissions');
        Schema::dropIfExists('wa_call_events');
        Schema::dropIfExists('wa_calls');
    }
};
