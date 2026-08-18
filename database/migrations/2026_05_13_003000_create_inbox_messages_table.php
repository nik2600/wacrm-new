<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inbox_messages — dedicated storage for team-inbox + 1-on-1 chat
 * conversation bubbles. Separated from `messages` (which carries
 * campaigns / broadcasts / scheduled blasts) so the inbox surface
 * isn't mixed with bulk-send data — cleaner schema, faster queries,
 * easier to evolve.
 *
 * Columns are a focused subset of `messages` — all inbox-relevant
 * fields kept, campaign-only fields dropped:
 *   - scheduled_at / scheduled_timezone → not used in inbox
 *   - template_id → kept (template-rendered replies happen in inbox)
 *   - contact_id → kept (contact-card sender lookup)
 *
 * Added cols that only the inbox uses:
 *   - agent_id (AI agent attribution)
 *   - reaction, pinned, starred (per-message actions)
 *   - quality_score, quality_note (AI self-rating)
 *   - meta (JSON: wa_message_id, target_jid, contact, forwarded, ptt, …)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                  ->constrained('conversations')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();

            $table->string('direction', 8)->default('out')->index();

            // Phone numbers + body are encrypted-at-rest (same pattern as messages).
            $table->text('to_number')->nullable();
            $table->text('from_number')->nullable();
            $table->text('body')->nullable();

            $table->string('media_path')->nullable();
            $table->string('media_type', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('status', 16)->default('pending')->index();
            $table->text('failure_reason')->nullable();

            // Inbox-specific bubble state.
            $table->boolean('pinned')->default(false)->index();
            $table->boolean('starred')->default(false)->index();
            $table->string('reaction', 16)->nullable();
            $table->tinyInteger('quality_score')->unsigned()->nullable();
            $table->string('quality_note', 191)->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();

            $table->timestamps();

            // Composite index — the queue uses
            //   WHERE conversation_id = ? ORDER BY id DESC LIMIT 80
            // all the time. The standalone conversation_id index would
            // do the lookup; this one also covers the ordering.
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
