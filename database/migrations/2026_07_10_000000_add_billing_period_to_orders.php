<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which billing cycle the customer chose at checkout. 'monthly' is the
     * plan's own duration; 'yearly' means we charged 12 × the discounted
     * per-month rate up front and the plan is valid for a full year.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_period', 16)->default('monthly')->after('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
