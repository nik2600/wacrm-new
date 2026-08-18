<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            // openai | anthropic | gemini
            $table->string('provider', 32);
            // encrypted at rest
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_keys');
    }
};
