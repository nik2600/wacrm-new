<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks COD (cash-on-delivery) order confirmations awaiting the
 * customer's WhatsApp Yes/No reply. The biggest RTO-saver for the
 * India market: a customer who never confirms is flagged so the
 * merchant can hold the shipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_cod_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('shopify_order_id', 32)->nullable();
            $table->string('order_name', 64)->nullable();
            $table->string('customer_phone', 32);
            $table->string('status', 16)->default('pending'); // pending | confirmed | cancelled
            $table->timestamps();
            $table->index(['workspace_id', 'customer_phone', 'status'], 'cod_ws_phone_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_cod_confirmations');
    }
};
