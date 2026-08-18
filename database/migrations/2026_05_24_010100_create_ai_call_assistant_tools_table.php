<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_call_assistant_tools', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assistant_id')->constrained('ai_call_assistants')->cascadeOnDelete();
            $t->string('function_name', 80);             // track_order, check_balance, etc.
            $t->json('trigger_keywords_json')->nullable();
            $t->string('http_method', 8)->default('GET');
            $t->string('http_url', 600);
            $t->json('headers_json')->nullable();         // [{key,value}]
            // Each parameter: { id (slug), label, description, required }
            // The AI is told to extract these from the conversation and
            // pass them as querystring / JSON body.
            $t->json('parameters_json')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index('assistant_id');
            $t->unique(['assistant_id', 'function_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_assistant_tools');
    }
};
