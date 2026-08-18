<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Embeddable chat widget — pasted onto an external site via a one-line
 * snippet. Each row is one widget; a workspace can have many. The
 * widget either (a) opens a WhatsApp deeplink (`target_whatsapp_number`
 * set + `assistant_id` null), or (b) runs an AI chat in-browser
 * (`assistant_id` set), or both (visitor picks).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chatbot_widgets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assistant_id')->nullable()
                ->constrained('ai_chat_assistants')->nullOnDelete();

            $t->string('name', 120);
            $t->string('slug', 140);
            // Public-facing token used in /widget/{embed_token}/* URLs.
            // Long, unguessable; rotatable without breaking the row id.
            $t->string('embed_token', 64)->unique();

            // Mode toggles which behavior the widget exposes.
            $t->string('mode', 16)->default('ai');     // ai|whatsapp|both

            // WhatsApp deeplink (only used when mode is whatsapp/both)
            $t->string('target_whatsapp_cc', 8)->nullable();   // country code
            $t->string('target_whatsapp_number', 24)->nullable();
            $t->text('prefilled_message')->nullable();

            // --- Appearance step ---
            $t->string('position', 16)->default('bottom_right'); // bottom_right|bottom_left
            $t->string('button_color', 16)->default('#075E54');
            $t->string('button_image_url', 1024)->nullable();

            // --- Header step ---
            $t->string('header_title', 120)->default('Chat with us');
            $t->string('header_bg', 16)->default('#075E54');
            $t->string('header_text_color', 16)->default('#FFFFFF');

            // --- Body step ---
            $t->text('welcome_message')->nullable();
            $t->string('message_bubble_color', 16)->default('#FFFFFF');
            $t->string('message_text_color', 16)->default('#222222');
            $t->string('body_bg_kind', 8)->default('color');   // color|image
            $t->string('body_bg_color', 16)->default('#ECE5DD');
            $t->string('body_bg_image_url', 1024)->nullable();
            $t->boolean('auto_open')->default(false);

            // --- Action step ---
            $t->string('button_label', 80)->default('Start chat');
            $t->string('action_button_bg', 16)->default('#075E54');
            $t->string('action_button_text_color', 16)->default('#FFFFFF');

            // Optional intake — if true, first prompt collects name +
            // contact before the conversation begins (helps team-inbox
            // agents identify who they're talking to).
            $t->boolean('collect_name')->default(true);
            $t->boolean('collect_email')->default(false);
            $t->boolean('collect_phone')->default(false);

            $t->string('status', 16)->default('active'); // active|paused
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['workspace_id', 'slug']);
            $t->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_widgets');
    }
};
