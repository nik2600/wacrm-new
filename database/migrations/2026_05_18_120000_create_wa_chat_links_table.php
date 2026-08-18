<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trackable WhatsApp deep-links — the operator picks a number, drops in
 * a conversation-starter, and we mint /l/{slug} short links + a QR code.
 * Each click increments `click_count` and redirects to wa.me with the
 * pre-typed message in the query string.
 *
 * Distinct from chatbot widgets (which run an actual chat surface) and
 * wa-forms (which are Meta Flows). This is the plain wa.me deep-link
 * use case — landing pages, social bios, business cards.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_chat_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('name', 140);                 // internal label
            $t->string('slug', 64)->unique();        // /l/{slug}
            $t->string('country_code', 8);           // +91
            $t->string('phone_number', 24);          // national digits only
            $t->text('welcome_message')->nullable(); // pre-typed text

            // Optional UTM tagging — recorded on the chat link itself so
            // a future analytics surface can group clicks by campaign
            // without parsing the redirect URL.
            $t->string('utm_source', 80)->nullable();
            $t->string('utm_medium', 80)->nullable();
            $t->string('utm_campaign', 80)->nullable();

            $t->unsignedBigInteger('click_count')->default(0);
            $t->timestamp('last_clicked_at')->nullable();
            $t->timestamp('expires_at')->nullable();

            $t->string('status', 16)->default('active'); // active|paused

            $t->timestamps();
            $t->softDeletes();

            $t->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chat_links');
    }
};
