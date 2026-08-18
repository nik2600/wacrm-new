<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge fed into chat assistants. Each row is one piece of
 * training material — a URL, a file, a raw text snippet, or a
 * Q&A pair. When `assistant_id` is NULL the source is shared
 * across every chat assistant in the workspace; otherwise it's
 * scoped to a single assistant. V1 stitches the rendered text
 * directly into the system prompt; embeddings/RAG can replace
 * the join later without touching the schema.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_training_sources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Nullable = applies to all chat assistants in the workspace.
            $t->foreignId('assistant_id')->nullable()
                ->constrained('ai_chat_assistants')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kind', 16);    // url|text|file|qa
            $t->string('label', 200);
            $t->string('url', 1024)->nullable();
            $t->string('file_path', 512)->nullable();
            $t->longText('content')->nullable();      // rendered text the AI will see
            $t->longText('question')->nullable();     // for qa kind
            $t->longText('answer')->nullable();       // for qa kind

            // Status workflow: pending → ready (text extracted, ready
            // to inject) or pending → failed (extraction error).
            $t->string('status', 16)->default('ready');
            $t->unsignedInteger('tokens_estimate')->nullable();
            $t->text('error')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['workspace_id', 'assistant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_training_sources');
    }
};
