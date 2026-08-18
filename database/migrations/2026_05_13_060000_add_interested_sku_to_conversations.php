<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the last product a buyer asked about on each conversation.
 *
 * When Meta delivers an inbound text with `context.referred_product`
 * — which happens when the buyer taps a product card and replies —
 * we stash the SKU + product_id here so bots/agents can see at a
 * glance "this person was looking at the Red Hoodie."
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('interested_sku', 100)->nullable()->after('priority');
            $table->unsignedBigInteger('interested_product_id')->nullable()->after('interested_sku');
            $table->timestamp('interested_seen_at')->nullable()->after('interested_product_id');
            $table->index(['workspace_id', 'interested_sku'], 'conversations_ws_interested_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_ws_interested_idx');
            $table->dropColumn(['interested_sku', 'interested_product_id', 'interested_seen_at']);
        });
    }
};
