<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages table — one row per actual sent/received message
 * (an individual outgoing-to-one-number or incoming reply).
 * Each row hangs off a single conversation; the conversation
 * owns the queue-level metadata (title, recipient count, etc).
 *
 * Status uses string enum values rather than the old project's
 * integer codes (0=pending, 1=sent, 2=failed) so the database
 * is self-describing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                  ->constrained('conversations')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();

            $table->string('direction', 8)->default('out');     // out | in

            // Phone numbers and message body are encrypted-at-rest, so they
            // can't be indexed (ciphertext is non-deterministic) and must
            // be TEXT (ciphertext exceeds 32 chars even for short numbers).
            $table->text('to_number')->nullable();
            $table->text('from_number')->nullable();
            $table->text('body')->nullable();

            $table->string('media_path')->nullable();
            $table->string('media_type', 16)->nullable();        // image | video | audio | document | location
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // pending | scheduled | sent | delivered | read | failed
            $table->string('status', 16)->default('pending')->index();
            $table->text('failure_reason')->nullable();

            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
