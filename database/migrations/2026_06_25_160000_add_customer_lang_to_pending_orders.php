<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jessica #1 — persist the customer's detected language on the in-flight cart so
 * the order confirmation AND the group @mention can be sent in their language.
 * (wa_orders carries it onward in meta_json.customer_lang — no column needed.)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('wa_pending_orders') && !Schema::hasColumn('wa_pending_orders', 'customer_lang')) {
            Schema::table('wa_pending_orders', function (Blueprint $t) {
                $t->string('customer_lang', 12)->nullable()->after('currency_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wa_pending_orders') && Schema::hasColumn('wa_pending_orders', 'customer_lang')) {
            Schema::table('wa_pending_orders', function (Blueprint $t) {
                $t->dropColumn('customer_lang');
            });
        }
    }
};
