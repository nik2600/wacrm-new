<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind each storefront to a Device row so the order-confirmation
 * sends know which WhatsApp number to use. Nullable because the
 * setup wizard lets users finish the store first and pick a sending
 * device later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_storefronts', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->after('workspace_id');
            $table->string('shop_name', 191)->nullable()->after('device_id');

            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_storefronts', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropIndex(['device_id']);
            $table->dropColumn(['device_id', 'shop_name']);
        });
    }
};
