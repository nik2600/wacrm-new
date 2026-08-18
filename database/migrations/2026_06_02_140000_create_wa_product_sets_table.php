<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable product collections for the WhatsApp catalog. A "set" is a
 * named, ordered group of wa_products an operator can fire as a Multi
 * Product Message (MPM) or reuse across broadcasts/flows — instead of
 * hand-picking the same products every send.
 *
 * Membership is stored as an ordered JSON array of wa_product ids
 * (product_ids). We resolve to live products / retailer_ids at send
 * time, so a product edited or removed later stays consistent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_product_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('description', 500)->nullable();
            $table->json('product_ids')->nullable();          // ordered wa_product ids
            $table->boolean('is_active')->default(true);
            $table->string('meta_set_id')->nullable();         // reserved: Meta product_set id for future sync
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_product_sets');
    }
};
