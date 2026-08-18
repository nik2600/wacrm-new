<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Natural-language ordering — inventory foundation (Jessica customization, P2).
 *
 *  - wa_products.aliases_json : multi-language aliases (CN / MY / EN …) so the
 *    AI order-parser can match "drumstick" / "鸡腿" / "ayam" → the right product.
 *  - wa_products.reserved_qty : running count of stock HELD by in-flight orders
 *    (anti-sellout). available = stock_qty - reserved_qty.
 *  - wa_stock_reservations    : the hold ledger — one row per held quantity, so
 *    a hold can be committed (on order Confirm) or released (on cancel/timeout).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_products', function (Blueprint $t) {
            if (!Schema::hasColumn('wa_products', 'aliases_json')) {
                $t->json('aliases_json')->nullable()->after('tags_json');
            }
            if (!Schema::hasColumn('wa_products', 'reserved_qty')) {
                $t->integer('reserved_qty')->default(0)->after('stock_qty');
            }
        });

        if (!Schema::hasTable('wa_stock_reservations')) {
            Schema::create('wa_stock_reservations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->unsignedBigInteger('product_id')->index();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                // ref = a stable key for a customer's in-flight order (flow
                // session key or phone) so we can release/commit all their holds.
                $t->string('ref', 128)->nullable()->index();
                $t->integer('qty');
                $t->string('status', 16)->default('held'); // held | committed | released
                $t->timestamp('expires_at')->nullable()->index();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_stock_reservations');
        Schema::table('wa_products', function (Blueprint $t) {
            if (Schema::hasColumn('wa_products', 'reserved_qty')) $t->dropColumn('reserved_qty');
            if (Schema::hasColumn('wa_products', 'aliases_json')) $t->dropColumn('aliases_json');
        });
    }
};
