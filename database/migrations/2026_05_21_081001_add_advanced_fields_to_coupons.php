<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends coupons with the admin controls a real CRM/billing tool needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('per_user_limit')->nullable()->after('max_uses');
            $table->decimal('max_discount_amount', 14, 4)->nullable()->after('amount');
            $table->boolean('first_purchase_only')->default(false)->after('is_active');
            $table->boolean('stackable_with_other')->default(false)->after('first_purchase_only');
            $table->string('currency_code', 8)->nullable()->after('amount');
            $table->text('admin_note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn([
                'per_user_limit', 'max_discount_amount',
                'first_purchase_only', 'stackable_with_other',
                'currency_code', 'admin_note',
            ]);
        });
    }
};
