<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woocommerce_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('event_type', 64);
            $table->string('status', 16)->default('received');
            $table->string('recipient', 64)->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration_id', 'created_at']);
            $table->index(['integration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_integration_logs');
    }
};
