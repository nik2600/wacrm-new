<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per anonymous web visitor of a chatbot widget. Identified
 * by a long-lived browser cookie (visitor_uuid) so a returning
 * visitor stitches back to the same conversation. Conversation +
 * messages live in the existing `conversations`/`messages` tables
 * under `channel='chatbot_widget'`, so the team inbox renders them
 * alongside WhatsApp threads without any extra glue.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chatbot_widget_visitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('widget_id')->constrained('chatbot_widgets')->cascadeOnDelete();
            $t->foreignId('conversation_id')->nullable()
                ->constrained('conversations')->nullOnDelete();

            $t->string('visitor_uuid', 64)->unique();
            $t->string('name', 120)->nullable();
            $t->string('email', 191)->nullable();
            $t->string('phone', 32)->nullable();

            // Page where the widget was opened — useful context for
            // the agent ("they came in from /pricing").
            $t->string('referrer_url', 1024)->nullable();
            $t->string('user_agent', 512)->nullable();
            $t->string('ip', 64)->nullable();

            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();

            $t->timestamps();

            $t->index(['workspace_id', 'widget_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_widget_visitors');
    }
};
