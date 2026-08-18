<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * End-to-end checkout — add the columns CheckoutController::process()
 * needs to persist what the user typed on /checkout/{id}:
 *
 *   • Coupon → coupon_id (FK-ish) + coupon_code + discount_amount
 *   • Tax    → tax_amount + tax_rate
 *   • Subtotal lives in `amount` for backwards compat — total paid
 *     is amount - discount_amount + tax_amount = `total_amount`.
 *   • Billing → customer_name / customer_email + billing_*
 *
 * Nothing is required (nullable) so existing Order rows stay valid
 * and so a fresh install where the user skips billing fields still
 * works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Pricing breakdown.
            $table->unsignedBigInteger('coupon_id')->nullable()->after('package_id');
            $table->string('coupon_code', 64)->nullable()->after('coupon_id');
            $table->decimal('discount_amount', 12, 4)->default(0)->after('amount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 12, 4)->default(0)->after('tax_rate');
            $table->decimal('total_amount', 12, 4)->default(0)->after('tax_amount');

            // Customer + billing snapshot at order time.
            $table->string('customer_name', 191)->nullable()->after('total_amount');
            $table->string('customer_email', 191)->nullable()->after('customer_name');
            $table->string('billing_company', 191)->nullable()->after('customer_email');
            $table->string('billing_address', 255)->nullable()->after('billing_company');
            $table->string('billing_city', 120)->nullable()->after('billing_address');
            $table->string('billing_postal', 32)->nullable()->after('billing_city');
            $table->string('billing_country', 80)->nullable()->after('billing_postal');
            $table->string('billing_tax_id', 64)->nullable()->after('billing_country');

            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['coupon_id']);
            $table->dropColumn([
                'coupon_id', 'coupon_code',
                'discount_amount', 'tax_rate', 'tax_amount', 'total_amount',
                'customer_name', 'customer_email',
                'billing_company', 'billing_address', 'billing_city', 'billing_postal',
                'billing_country', 'billing_tax_id',
            ]);
        });
    }
};
