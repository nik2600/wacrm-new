<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient pivot for scheduled_messages.
 *
 * Until now `scheduled_messages` only tracked aggregate counters
 * (total_sent / total_delivered / total_failed). The /scheduled/{id}
 * detail page wants to show WHICH specific contacts succeeded or
 * failed, so we need a row per recipient with its own status.
 *
 * Mirrors the broadcast_contacts pattern. The Node bot will POST
 * per-recipient updates via /api/update-scheduled-contact-status as
 * each send result comes back from Baileys; aggregate counters on
 * scheduled_messages stay as a fast index for the list page.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduled_message_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('scheduled_message_id')
                ->constrained('scheduled_messages')
                ->cascadeOnDelete();
            // contact_id stays nullable — pasted phone-number-only sends
            // may not match any Contact row in the workspace.
            $t->foreignId('contact_id')->nullable();
            // Plain phone for fast lookups (encrypted on contacts table).
            // Digits only, no `+`. Indexed for the Node webhook lookup.
            $t->string('phone', 32);
            $t->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])
                ->default('pending');
            $t->string('error_message', 512)->nullable();
            // Whatsapp messageId Baileys returned — lets us correlate the
            // later delivery/read receipts the receipt forwarder pushes.
            $t->string('wa_message_id', 128)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamps();

            $t->index(['scheduled_message_id', 'status']);
            $t->index(['scheduled_message_id', 'phone']);
            $t->index('wa_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_message_contacts');
    }
};
