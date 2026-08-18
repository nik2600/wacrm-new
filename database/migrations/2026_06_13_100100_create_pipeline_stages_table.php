<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline stages = the Kanban columns. `sort_order` is left→right order.
 * `probability` (0–100) drives the weighted forecast (value × probability).
 * `is_won` / `is_lost` mark the two terminal columns — dropping a card there
 * flips the deal's status and stamps won_at / lost_at.
 *
 * `workspace_id` is denormalised from the parent pipeline so the global
 * forCurrentWorkspace scope works without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('color', 16)->default('#25D366');
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->unsignedTinyInteger('probability')->default(0); // 0–100
            $table->timestamps();

            $table->index(['pipeline_id', 'sort_order']);
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
