<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jessica #2 — capture ship-to (name / company / address) on the in-flight cart
 * so confirm() persists it onto the order (wa_orders already has customer_name /
 * customer_address; company rides in meta_json.ship_company). Repeat-customer
 * reuse reads the last order's address — no contact columns needed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wa_pending_orders')) return;
        Schema::table('wa_pending_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('wa_pending_orders', 'ship_name'))    $t->string('ship_name')->nullable()->after('customer_lang');
            if (!Schema::hasColumn('wa_pending_orders', 'ship_company')) $t->string('ship_company')->nullable()->after('ship_name');
            if (!Schema::hasColumn('wa_pending_orders', 'ship_address')) $t->text('ship_address')->nullable()->after('ship_company');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('wa_pending_orders')) return;
        Schema::table('wa_pending_orders', function (Blueprint $t) {
            foreach (['ship_name', 'ship_company', 'ship_address'] as $c) {
                if (Schema::hasColumn('wa_pending_orders', $c)) $t->dropColumn($c);
            }
        });
    }
};
