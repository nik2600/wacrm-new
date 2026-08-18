<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_call_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assistant_id')->nullable()->constrained('ai_call_assistants')->nullOnDelete();
            // Link to /chat — every call appears as a conversation row so
            // the operator can pick up the thread in the team inbox.
            $t->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();

            $t->string('caller_phone', 32);
            $t->string('callee_phone', 32);
            $t->string('direction', 16)->default('inbound');     // inbound|outbound
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->unsignedInteger('duration_seconds')->default(0);

            // status: in-progress | completed | no-answer | failed | busy | declined
            $t->string('status', 16)->default('in-progress');
            $t->text('failure_reason')->nullable();

            $t->string('recording_url_agent', 500)->nullable();
            $t->string('recording_url_user', 500)->nullable();
            $t->string('recording_url_mixed', 500)->nullable();

            // Turn-by-turn transcript [{role:'agent'|'user', text, t (ms since start)}]
            $t->json('transcript_json')->nullable();
            // Tool calls fired mid-call [{name, args, response, t}]
            $t->json('tool_calls_json')->nullable();

            // Cost accounting — minor units to dodge floating point.
            $t->unsignedInteger('ai_tokens_in')->default(0);
            $t->unsignedInteger('ai_tokens_out')->default(0);
            $t->unsignedInteger('stt_seconds')->default(0);
            $t->unsignedInteger('tts_chars')->default(0);
            $t->unsignedInteger('cost_minor')->default(0);
            $t->string('currency_code', 8)->default('USD');

            $t->string('twilio_call_sid', 64)->nullable()->unique();
            $t->json('meta_json')->nullable();

            $t->timestamps();

            $t->index(['workspace_id', 'started_at']);
            $t->index(['workspace_id', 'assistant_id']);
            $t->index('caller_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_logs');
    }
};
