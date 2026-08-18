<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            // Most audit rows are routine "success" events.
            // Failure/warning is rarer but critical for the admin UI's red badges.
            $t->enum('result', ['success', 'failure', 'warning'])->default('success')->index()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropColumn('result');
        });
    }
};
