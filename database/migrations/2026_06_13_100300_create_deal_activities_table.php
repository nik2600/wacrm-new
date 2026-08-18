<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deal timeline + tasks. One row per event on a deal:
 *
 *   note          → free text the operator added
 *   stage_change  → moved column (meta = {from_stage_id, to_stage_id})
 *   call/message  → logged touchpoint
 *   task          → a to-do with due_at; done_at set when ticked
 *
 * `body` is encrypted at rest (it can hold customer-facing notes — same
 * pattern as ConversationNote).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 24)->default('note'); // note|stage_change|call|message|task
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['deal_id', 'created_at']);
            $table->index(['workspace_id', 'type']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_activities');
    }
};
