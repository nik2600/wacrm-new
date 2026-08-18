<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deal = one opportunity moving through a pipeline.
 *
 * Money is stored in MINOR units (paise/cents) the same way wa_orders does,
 * displayed through a Money accessor. `source` records how the deal was
 * created so the board can badge it (manual | inbox | order | shopify | woo
 * | form | api). `status` (open|won|lost) is derived from the stage's
 * is_won/is_lost when a card is dropped on a terminal column.
 *
 * contact_id / conversation_id are nullable so a deal can exist before it's
 * linked to a saved contact or chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('title', 191);
            $table->bigInteger('value_minor')->default(0);
            $table->string('currency', 10)->default('INR');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedBigInteger('owner_team_id')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('status', 16)->default('open');   // open | won | lost
            $table->string('lost_reason', 191)->nullable();
            $table->string('source', 24)->default('manual'); // manual|inbox|order|shopify|woo|form|api
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['pipeline_id', 'stage_id']);
            $table->index('contact_id');
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
