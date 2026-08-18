<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_catalogs — per-workspace binding between our WaDesk workspace
 * and a Meta Commerce Catalog. One row per (workspace, provider)
 * pair. Meta allows ONE catalog per WABA, so this is effectively
 * one-to-one with the workspace's WaProviderConfig.
 *
 * access_token_enc is the long-lived system-user token with
 * `catalog_management` + `whatsapp_business_messaging` scopes,
 * encrypted at rest by Laravel's encrypted cast.
 *
 * provider:
 *   meta_cloud  — talks directly to graph.facebook.com
 *   dialog_360  — talks to waba-v2.360dialog.io (same payloads,
 *                 different auth header + host)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_catalogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('provider', 16); // 'meta_cloud' | 'dialog_360'
            $table->string('catalog_id', 64);           // Meta Commerce catalog ID
            $table->string('catalog_name', 191)->nullable();
            $table->string('waba_id', 64)->nullable();   // WhatsApp Business Account ID
            $table->string('phone_number_id', 64)->nullable(); // Meta phone number ID
            $table->text('access_token_enc')->nullable();
            $table->boolean('is_cart_enabled')->default(true);
            $table->boolean('is_catalog_visible')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta_json')->nullable(); // anything else we want to stash (business_id, owner, etc)
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
            $table->index('catalog_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_catalogs');
    }
};
