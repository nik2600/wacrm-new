<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->boolean('pinned')->default(false)->after('status');
            $t->boolean('starred')->default(false)->after('pinned');
            $t->string('reaction', 16)->nullable()->after('starred');
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $t) {
            $t->dropColumn(['pinned', 'starred', 'reaction']);
            $t->dropSoftDeletes();
        });
    }
};
