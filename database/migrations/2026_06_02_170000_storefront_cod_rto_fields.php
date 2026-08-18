<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S4 — COD + RTO. Capture the chosen payment method at checkout and, for
 * cash-on-delivery orders, a return-to-origin risk score so merchants can
 * triage fake / high-risk COD before shipping.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_orders', 'payment_method')) {
                $table->string('payment_method', 16)->default('prepaid')->after('coupon_code'); // prepaid | cod
            }
            if (!Schema::hasColumn('wa_orders', 'rto_score')) {
                $table->unsignedTinyInteger('rto_score')->nullable()->after('payment_method'); // 0-100
            }
            if (!Schema::hasColumn('wa_orders', 'rto_band')) {
                $table->string('rto_band', 8)->nullable()->after('rto_score'); // low | medium | high
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_orders', function (Blueprint $table) {
            foreach (['payment_method', 'rto_score', 'rto_band'] as $col) {
                if (Schema::hasColumn('wa_orders', $col)) $table->dropColumn($col);
            }
        });
    }
};
