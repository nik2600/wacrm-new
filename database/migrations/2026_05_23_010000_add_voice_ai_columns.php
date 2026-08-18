<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voice-AI feature schema.
 *
 * Adds:
 *   1. Voice-channel toggles + provider config on `ai_agents` so a single
 *      AiAgent row can power text replies, voice-note replies, and (later)
 *      voice-call answers from one configuration.
 *   2. ASR transcript cache + AI processing markers on `messages` so we
 *      don't re-transcribe identical audio and so the inbox can show
 *      "Replied by Voice AI" pills next to the rendered bubble.
 *   3. Daily usage counters in `ai_voice_usage_daily` for plan quotas
 *      and admin-side billing reports. Per-workspace + per-agent row
 *      so quotas can scope either way.
 *
 * Designed to be a no-op on workspaces that never enable voice: every
 * column defaults to off / null and only the inbound-message dispatcher
 * pays attention to them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $t) {
            // Per-channel kill-switches. Off by default; the operator
            // explicitly opts each AiAgent into each voice channel.
            $t->boolean('voice_note_enabled')->default(false)->after('use_saved_replies');
            $t->boolean('voice_call_enabled')->default(false)->after('voice_note_enabled');

            // TTS provider config. Keeping the provider as a short
            // string (not enum) so admins can add new drivers without
            // a migration — the driver factory in AiVoiceReplyService
            // gates whatever values are wired in code.
            $t->string('voice_provider', 32)->nullable()->after('voice_call_enabled');
            $t->string('voice_id', 96)->nullable()->after('voice_provider');
            $t->string('voice_language', 8)->default('en')->after('voice_id');

            // ASR provider config. Most workspaces will share OpenAI
            // (already configured for chat); the column lets advanced
            // setups use Deepgram for cheaper bulk transcription.
            $t->string('asr_provider', 32)->nullable()->after('voice_language');
            $t->string('asr_language', 8)->nullable()->after('asr_provider');

            // Daily safety cap. Default 200 voice replies / day so a
            // runaway loop on a free workspace can't drain credits.
            $t->integer('max_voice_notes_per_day')->default(200)->after('asr_language');
        });

        Schema::table('messages', function (Blueprint $t) {
            // ASR cache. Cheap text — store unencrypted; the audio body
            // it transcribes is already PII-encrypted on the row.
            $t->text('voice_transcript')->nullable()->after('media_type');
            $t->string('voice_transcript_lang', 8)->nullable()->after('voice_transcript');
            // Marker so we don't redispatch the AI for the same audio
            // (e.g. operator manually retriggers, or a webhook retry).
            $t->timestamp('ai_processed_at')->nullable()->after('voice_transcript_lang');
            // Outbound reply correlation — if the AI sent a voice reply,
            // this points to its own row so the inbox can render the
            // pair together and analytics can compute reply latency.
            $t->unsignedBigInteger('ai_reply_id')->nullable()->after('ai_processed_at');
            $t->index(['ai_processed_at']);
        });

        Schema::create('ai_voice_usage_daily', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $t->date('date');
            // Counters. Kept as discrete buckets instead of one rolled-up
            // "credits" total so the admin can audit each provider line
            // independently when reconciling vendor invoices.
            $t->integer('voice_notes_processed')->default(0);
            $t->integer('call_seconds')->default(0);
            $t->integer('asr_seconds')->default(0);
            $t->integer('tts_chars')->default(0);
            $t->integer('llm_input_tokens')->default(0);
            $t->integer('llm_output_tokens')->default(0);
            $t->timestamps();

            $t->unique(['workspace_id', 'ai_agent_id', 'date']);
            $t->index(['workspace_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_voice_usage_daily');
        Schema::table('messages', function (Blueprint $t) {
            $t->dropIndex(['ai_processed_at']);
            $t->dropColumn(['voice_transcript', 'voice_transcript_lang', 'ai_processed_at', 'ai_reply_id']);
        });
        Schema::table('ai_agents', function (Blueprint $t) {
            $t->dropColumn([
                'voice_note_enabled', 'voice_call_enabled',
                'voice_provider', 'voice_id', 'voice_language',
                'asr_provider', 'asr_language',
                'max_voice_notes_per_day',
            ]);
        });
    }
};
