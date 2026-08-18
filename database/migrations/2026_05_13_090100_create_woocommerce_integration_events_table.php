<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woocommerce_integration_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('event_type', 64);              // order.created, customer.created, ...
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('send_to', 16)->default('customer');
            $table->string('admin_number', 32)->nullable();
            $table->unsignedInteger('delay_seconds')->default(0);
            $table->timestamps();

            $table->unique(['integration_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_integration_events');
    }
};
