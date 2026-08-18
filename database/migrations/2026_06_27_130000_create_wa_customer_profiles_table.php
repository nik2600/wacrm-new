<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jessica — pre-set customer info from the shop dashboard. A merchant can save a
 * customer's Name / Company / delivery Address by phone, so when that customer
 * orders on WhatsApp their address is shown automatically (they just reply YES)
 * — no re-typing. OrderingService::shippingFor() reads this BEFORE falling back
 * to the customer's last order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wa_customer_profiles')) return;
        Schema::create('wa_customer_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id')->index();
            $t->string('phone', 32)->index();      // digits-only, canonical
            $t->string('name')->nullable();
            $t->string('company')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'phone']);  // one profile per customer
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_profiles');
    }
};
