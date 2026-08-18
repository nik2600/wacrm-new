<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woocommerce_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');

            $table->string('store_url', 255);           // https://example.com — bare host
            $table->string('store_name', 191)->nullable();
            $table->string('store_currency', 8)->nullable();
            $table->string('store_country', 64)->nullable();
            $table->string('store_version', 32)->nullable();   // WC plugin version reported by /system_status

            // Per-store credentials. Encrypted at rest via Eloquent cast.
            $table->text('consumer_key');
            $table->text('consumer_secret');

            $table->string('status', 16)->default('active');   // active|inactive|error
            $table->string('webhook_secret', 64);              // We generate this and pass it to WC when registering each webhook
            $table->json('metadata')->nullable();              // webhook_ids etc.

            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'store_url']);
            $table->index('user_id');
            $table->index('webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_integrations');
    }
};
