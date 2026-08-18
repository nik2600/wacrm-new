<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat-mode AI assistant — distinct from `ai_call_assistants` (which
 * handles voice phone calls). Powers Chatbot Widget conversations and
 * any other text channel we add later (WhatsApp inbound AI auto-reply,
 * etc.). Keys live in `admin_ai_keys` — operator never sees them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_chat_assistants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('slug', 140);
            $t->text('greeting')->nullable();          // first message the assistant says when a chat opens
            $t->text('system_prompt')->nullable();     // persona / instructions
            $t->string('tone', 32)->default('helpful'); // helpful|friendly|formal|playful
            $t->string('language', 16)->default('en');

            // Provider routing — admin's `admin_ai_keys` resolves the actual key
            $t->string('ai_provider', 32)->default('openai'); // openai|anthropic|gemini
            $t->string('ai_model', 80)->default('gpt-4o-mini');
            $t->unsignedSmallInteger('reply_max_tokens')->default(400);
            $t->decimal('temperature', 4, 2)->default(0.7);

            // Behavior
            $t->text('fallback_message')->nullable();  // shown if AI fails / no key configured
            $t->boolean('handoff_enabled')->default(true);
            $t->string('handoff_keyword', 60)->nullable(); // e.g. "talk to human"
            $t->text('handoff_message')->nullable();

            $t->string('status', 16)->default('active'); // active|paused
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['workspace_id', 'slug']);
            $t->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_assistants');
    }
};
