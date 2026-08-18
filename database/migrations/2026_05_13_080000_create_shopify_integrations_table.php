<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');

            $table->string('store_url', 191);
            $table->string('store_name', 191)->nullable();
            $table->string('shop_id', 64)->nullable();
            $table->string('shop_email', 191)->nullable();
            $table->string('shop_owner', 191)->nullable();
            $table->string('shop_plan', 64)->nullable();
            $table->string('shop_currency', 8)->nullable();
            $table->string('shop_country', 64)->nullable();

            $table->text('access_token');         // Encrypted at rest via Eloquent cast
            $table->text('scopes')->nullable();

            $table->string('status', 16)->default('active');   // active|inactive|error
            $table->string('webhook_secret', 64);
            $table->json('metadata')->nullable();

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
        Schema::dropIfExists('shopify_integrations');
    }
};
