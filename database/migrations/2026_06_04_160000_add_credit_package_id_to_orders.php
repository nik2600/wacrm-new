<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the SAME order + payment-gateway pipeline that sells plans also sell
 * wallet credit top-ups. When credit_package_id is set, the order is a wallet
 * top-up (CheckoutController::finalizeOrder credits the wallet instead of
 * applying a plan), so credit packages go through real gateways exactly like
 * plans — no more "self-confirm / free credits" path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'credit_package_id')) {
            return;
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_package_id')->nullable()->after('package_id')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'credit_package_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('credit_package_id');
            });
        }
    }
};
