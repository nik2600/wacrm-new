<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the packages catalog with plan gates for every shipped user-facing
 * feature that wasn't yet covered:
 *
 *   - WABA / Cloud-API voice calling (controllers exist, no gate)
 *   - Call recording
 *   - AI voice agents (separate from chat AI)
 *   - AI chat assistants
 *   - AI training sources
 *   - Inline "Generate with AI" buttons (uses admin keys, platform-billed)
 *   - WA Storefront / catalogue
 *   - Commerce-aware flows
 *   - Chatbot website widgets
 *   - SLA policies
 *   - Multilingual translation
 *   - Data residency control
 *
 * Plus the matching numeric caps. Existing columns are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Feature toggles
            $table->boolean('access_waba_calling')->default(false);
            $table->boolean('access_call_recording')->default(false);
            $table->boolean('access_ai_voice_agent')->default(false);
            $table->boolean('access_ai_chat_assistant')->default(false);
            $table->boolean('access_ai_training')->default(false);
            $table->boolean('access_ai_generate')->default(false);
            $table->boolean('access_wa_storefront')->default(false);
            $table->boolean('access_flows_commerce')->default(false);
            $table->boolean('access_chatbot_widgets')->default(false);
            $table->boolean('access_sla_policies')->default(false);
            $table->boolean('access_translation')->default(false);
            $table->boolean('access_data_residency')->default(false);

            // Numeric limits (NULL = unlimited per existing convention).
            $table->unsignedInteger('waba_calling_minutes_monthly')->nullable();
            $table->unsignedInteger('ai_voice_minutes_monthly')->nullable();
            $table->unsignedInteger('ai_chat_messages_monthly')->nullable();
            $table->unsignedInteger('ai_training_sources_limit')->nullable();
            $table->unsignedInteger('chatbot_widgets_limit')->nullable();
            $table->unsignedInteger('storefronts_limit')->nullable();
            $table->unsignedInteger('sla_policies_limit')->nullable();
            $table->unsignedBigInteger('translation_chars_monthly')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'access_waba_calling', 'access_call_recording',
                'access_ai_voice_agent', 'access_ai_chat_assistant',
                'access_ai_training', 'access_ai_generate',
                'access_wa_storefront', 'access_flows_commerce',
                'access_chatbot_widgets', 'access_sla_policies',
                'access_translation', 'access_data_residency',
                'waba_calling_minutes_monthly', 'ai_voice_minutes_monthly',
                'ai_chat_messages_monthly', 'ai_training_sources_limit',
                'chatbot_widgets_limit', 'storefronts_limit',
                'sla_policies_limit', 'translation_chars_monthly',
            ]);
        });
    }
};
