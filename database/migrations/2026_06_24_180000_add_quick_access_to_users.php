<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user "Quick access" dashboard shortcuts — a list of catalog page keys
 * and/or custom {label,url} links the user pins to a 5×2 grid.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'quick_access')) {
                $table->json('quick_access')->nullable()->after('current_workspace_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'quick_access')) {
                $table->dropColumn('quick_access');
            }
        });
    }
};
