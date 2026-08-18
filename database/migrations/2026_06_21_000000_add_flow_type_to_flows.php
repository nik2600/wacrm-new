<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call Flow builder — P0. Adds `flow_type` so the SAME flow builder canvas
 * can hold either a chat flow (WhatsApp messages) or a CALL flow (AI voice
 * IVR on WABA Business Calling). Defaults to 'chat' so every existing flow
 * is untouched. The call runtime (callFlowRuntime.js) only walks flows
 * where flow_type='call'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            if (!Schema::hasColumn('flows', 'flow_type')) {
                $table->string('flow_type', 16)->default('chat')->after('provider')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            if (Schema::hasColumn('flows', 'flow_type')) {
                $table->dropColumn('flow_type');
            }
        });
    }
};
