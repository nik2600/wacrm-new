<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_call_assistants', function (Blueprint $t) {
            // Wider feature surface than the competitor's 5-step shape.
            // Stored as JSON so we don't have to migrate every time the
            // wizard grows a new field (persona preset, languages, etc).
            $t->json('meta_json')->nullable()->after('last_greeting');
        });
    }

    public function down(): void
    {
        Schema::table('ai_call_assistants', function (Blueprint $t) {
            $t->dropColumn('meta_json');
        });
    }
};
