<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Natural-language ordering — the in-flight cart (Jessica customization, P3).
 *
 * When a customer DMs "I want 2 drumsticks and 3 eggs", the AI parses it, stock
 * is HELD (P2), and the parsed cart lands here as `pending` while we wait for
 * the customer to Confirm. On Confirm it becomes a real wa_orders row and the
 * holds commit; on Cancel/timeout the holds release. One open cart per customer.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wa_pending_orders')) {
            Schema::create('wa_pending_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->string('customer_phone', 32);
                $t->string('ref', 128)->index();         // = the InventoryService hold ref
                $t->json('items_json')->nullable();        // [{product_id,name,qty,price_minor,currency}]
                $t->json('unavailable_json')->nullable();  // [{name,qty,reason}]
                $t->unsignedBigInteger('total_minor')->default(0);
                $t->string('currency_code', 8)->nullable();
                $t->string('group_code', 48)->nullable();  // pin order to a group (P5)
                $t->string('status', 16)->default('pending'); // pending | confirmed | cancelled
                $t->unsignedBigInteger('order_id')->nullable(); // set once confirmed
                $t->timestamp('expires_at')->nullable()->index();
                $t->timestamps();
                $t->unique(['workspace_id', 'customer_phone']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_pending_orders');
    }
};
