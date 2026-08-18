<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('user_id')->index();
            $table->dropUnique(['user_id', 'attribute_key']);
        });

        // Backfill existing rows from each user's current workspace.
        DB::table('attributes')->whereNull('workspace_id')->orderBy('id')->each(function ($row) {
            $ws = DB::table('users')->where('id', $row->user_id)->value('current_workspace_id');
            if ($ws) DB::table('attributes')->where('id', $row->id)->update(['workspace_id' => $ws]);
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->unique(['workspace_id', 'attribute_key']);
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'attribute_key']);
            $table->dropColumn('workspace_id');
            $table->unique(['user_id', 'attribute_key']);
        });
    }
};
