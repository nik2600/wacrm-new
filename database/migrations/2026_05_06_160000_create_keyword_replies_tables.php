<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-reply / keyword reply.
 * Clean rewrite of the legacy `keyword_reply` + `keyword_reply_contents` +
 * `keyword_reply_messages` triplet from D:\wadesk_2806\New folder. The old
 * install accumulated three migrations of column churn — we collapse them
 * to two clean tables here.
 *
 * keyword_replies
 *   one row per trigger. The bot's matching path
 *   (/api/keyword-replies?keyword=…) reads these.
 *
 * keyword_reply_contents
 *   one row per reply variant. The form lets the operator queue several
 *   text/media/template options and pick one as `is_selected` (the bot
 *   uses the first selected variant ordered by `sort_order`). Stored as
 *   a separate table — not JSON column — because we need to store media
 *   file metadata and keep them queryable.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();

            // The trigger word stays plain text — we run SQL `WHERE keyword=`
            // and similarity comparisons on it. Encrypting it would make the
            // /api/keyword-replies match path impossible without hydrating
            // every row in the workspace per inbound message.
            $table->string('keyword', 255)->index();

            // matching_method drives how the bot's lookup matches: exact,
            // fuzzy (similar_text % >= fuzzy_similarity), or contains.
            $table->string('matching_method', 12)->default('exact');
            $table->unsignedTinyInteger('fuzzy_similarity')->default(80);

            // reply_type = 'custom' (use keyword_reply_contents) OR 'flow'
            // (run the linked flow when the keyword fires).
            $table->string('reply_type', 12)->default('custom');
            $table->unsignedBigInteger('flow_id')->nullable();

            // Anti-spam knobs. cooldown = seconds before the same contact can
            // re-trigger this reply. timeout = seconds the bot waits for the
            // user to keep responding in flow mode.
            $table->unsignedInteger('cooldown')->nullable();
            $table->unsignedInteger('timeout')->nullable();

            // Helps the index page render the right preview without joining
            // keyword_reply_contents on every row.
            $table->string('message_type', 12)->default('text');

            $table->boolean('status')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'keyword']);
        });

        Schema::create('keyword_reply_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('keyword_reply_id')->index();

            // text | image | video | document | template
            $table->string('content_type', 12)->default('text');

            // Reply body (text content OR media caption). PII the operator
            // wrote to a customer — encrypt at rest the same way we do
            // ConversationNote::body.
            $table->text('content')->nullable();

            // Media metadata. file_path is `uploads/auto-reply/<filename>`
            // relative to public; original_name is the upload's display name;
            // mime_type used to set Content-Type when the bot proxies the
            // file URL to WhatsApp.
            $table->string('file_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();

            // For content_type='template' — pointer into wa_templates.
            $table->unsignedBigInteger('template_id')->nullable();

            // Multiple variants per keyword reply; sort_order drives the
            // tiebreak when the bot picks one to send. is_selected gates
            // inactive draft variants.
            $table->boolean('is_selected')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_reply_contents');
        Schema::dropIfExists('keyword_replies');
    }
};
