<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the unique(workspace_id) constraint on wa_storefronts so a
 * single workspace can host multiple shops (e.g. different
 * brands or sub-stores). Slug stays globally unique so the public
 * /s/{slug} URL doesn't collide, and we add a non-unique index on
 * workspace_id so the dashboard listings remain fast.
 */
return new class extends Migration {
    public function up(): void
    {
        // MySQL won't let us drop a unique index that's backing a
        // foreign key — drop the FK first, drop the unique, then
        // re-add a plain index and re-create the FK pointing at it.
        Schema::table('wa_storefronts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropUnique('wa_storefronts_workspace_id_unique');
            $table->index('workspace_id', 'wa_storefronts_workspace_id_index');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wa_storefronts', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropIndex('wa_storefronts_workspace_id_index');
            $table->unique('workspace_id', 'wa_storefronts_workspace_id_unique');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }
};
