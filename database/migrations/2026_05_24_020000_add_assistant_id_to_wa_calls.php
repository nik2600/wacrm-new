<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_calls', function (Blueprint $t) {
            // Which AI Voice Assistant picked up the call (NULL = human
            // handled OR voicemail-only). Lets /call-logs filter calls
            // by assistant + ties the WABA call row to the assistant
            // config that ran during the conversation.
            $t->foreignId('assistant_id')->nullable()->after('handler_agent_id')
              ->constrained('ai_call_assistants')->nullOnDelete();
            $t->string('ai_call_log_id')->nullable()->after('assistant_id')
              ->comment('Mirror id in ai_call_logs for the /call-logs UI');
            $t->index(['workspace_id', 'assistant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_calls', function (Blueprint $t) {
            $t->dropForeign(['assistant_id']);
            $t->dropIndex(['workspace_id', 'assistant_id']);
            $t->dropColumn(['assistant_id', 'ai_call_log_id']);
        });
    }
};
