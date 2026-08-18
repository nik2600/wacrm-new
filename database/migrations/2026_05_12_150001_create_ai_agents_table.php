<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 191);
            // openai | anthropic | gemini
            $table->string('provider', 32)->default('openai');
            // gpt-4o / claude-sonnet-4-5 / gemini-1.5-pro etc.
            $table->string('model', 64)->default('gpt-4o-mini');
            $table->text('system_prompt')->nullable();
            // friendly | professional | concise | empathetic
            $table->string('tone', 32)->default('professional');
            // hex color for avatar bubble
            $table->string('avatar_color', 7)->default('#6366f1');
            $table->boolean('auto_respond')->default(true);
            // max tokens for response
            $table->unsignedSmallInteger('max_tokens')->default(512);
            // temperature 0-1 stored as int * 10 (e.g. 7 = 0.7)
            $table->unsignedTinyInteger('temperature')->default(7);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('messages_sent')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
