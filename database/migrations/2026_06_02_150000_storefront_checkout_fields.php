<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side storefront checkout (S1). Until now a storefront order was
 * reconstructed by regex from whatever the customer typed into WhatsApp —
 * fragile, and we never captured the buyer's number up front. The new
 * checkout captures the order server-side BEFORE the WhatsApp hand-off, so
 * we store the structured delivery address + a clean shipping breakdown,
 * plus a tracking token the customer can use later (order-status page).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_orders', 'customer_address')) {
                $table->text('customer_address')->nullable()->after('customer_email');
            }
            if (!Schema::hasColumn('wa_orders', 'shipping_minor')) {
                $table->bigInteger('shipping_minor')->default(0)->after('total_minor');
            }
            if (!Schema::hasColumn('wa_orders', 'recovery_token')) {
                $table->string('recovery_token', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_orders', function (Blueprint $table) {
            foreach (['customer_address', 'shipping_minor', 'recovery_token'] as $col) {
                if (Schema::hasColumn('wa_orders', $col)) $table->dropColumn($col);
            }
        });
    }
};
