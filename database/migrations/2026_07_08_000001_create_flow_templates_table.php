<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated Bot-Flow templates. The admin builds a standard flow (e.g. a
 * "Restaurant welcome + menu" flow), saves it here, and every tenant sees it in
 * a "Start from a template" gallery on /flows where they can clone it into their
 * own workspace with one click.
 *
 * flow_data is stored as PLAIN JSON (not encrypted like flows.flow_data): these
 * are global, admin-authored, shareable blueprints — there's nothing tenant-
 * private in them, and cloning/exporting them must stay trivial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // chat (default) | call | instagram — mirrors flows.flow_type so a
            // cloned template lands in the right builder palette.
            $table->string('flow_type', 16)->default('chat')->index();
            $table->string('category', 64)->nullable()->index();
            // { flowNodes:[], flowEdges:[], vars:{} } — same shape the builder saves.
            $table->longText('flow_data');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable(); // admin user id
            $table->unsignedInteger('clone_count')->default(0);   // popularity metric
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_templates');
    }
};
