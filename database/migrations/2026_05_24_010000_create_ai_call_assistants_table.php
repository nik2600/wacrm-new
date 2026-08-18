<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_call_assistants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('slug', 140);
            $t->text('greeting_text')->nullable();
            // status: 'live' (handles calls) | 'draft' (not picked up) | 'paused' (manual stop)
            $t->string('status', 16)->default('draft');
            $t->boolean('is_active')->default(true);

            // AI Intelligence step
            $t->string('ai_provider', 32)->default('gemini');   // gemini|openai|anthropic
            $t->string('ai_model', 80)->default('gemini-2.5-flash-lite');
            // BYOK override — admin's `admin_ai_keys` provides the default
            // via AiKeyResolver; only populated when the workspace plan
            // grants BYOK + operator chose to use their own key.
            $t->text('ai_api_key_encrypted')->nullable();
            $t->text('ai_system_prompt')->nullable();
            $t->string('knowledge_base_url', 500)->nullable();
            $t->boolean('natural_conciseness')->default(true);

            // Voice & STT step
            $t->string('voice_provider', 32)->default('elevenlabs');
            $t->text('voice_api_key_encrypted')->nullable();
            $t->string('voice_id', 80)->nullable();              // ElevenLabs voice slug
            $t->json('voice_settings_json')->nullable();         // stability, similarity_boost, etc.
            $t->string('stt_provider', 32)->default('elevenlabs');
            $t->json('stt_settings_json')->nullable();

            // Connectivity / recording step
            $t->boolean('record_agent')->default(true);
            $t->boolean('record_user')->default(true);
            $t->boolean('auto_logging')->default(true);
            $t->json('exit_keywords_json')->nullable();          // ['bye', 'goodbye', ...]
            $t->text('last_greeting')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->unique(['workspace_id', 'slug']);
            $t->index(['workspace_id', 'is_active', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_assistants');
    }
};
