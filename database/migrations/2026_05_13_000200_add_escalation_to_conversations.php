<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // When set, the inbox:escalate command will re-route this
            // conversation if first_response_at is still null at or after
            // this timestamp. Cleared once an outbound reply is sent or
            // the conversation is resolved.
            $table->timestamp('escalation_due_at')->nullable()->index()->after('routing_meta');
            // What to do when the escalation fires. Stored as a single
            // action dict matching the RoutingEngine action format, e.g.
            // {"type":"assign_team","team_id":3}. Null means "no-op,
            // just clear the due_at".
            $table->json('escalation_action')->nullable()->after('escalation_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['escalation_due_at', 'escalation_action']);
        });
    }
};
