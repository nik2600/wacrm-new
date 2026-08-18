<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Pipeline — a board a workspace chases opportunities through.
 *
 * A workspace can keep several pipelines (Sales, Onboarding,
 * Support-escalation); one is flagged `is_default` and seeded with a
 * 6-stage ladder on the workspace's first /deals visit. `currency` is the
 * pipeline-level reporting currency (deals can still override per row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->string('currency', 10)->default('INR');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
