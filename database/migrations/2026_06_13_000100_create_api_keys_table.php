<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer REST API keys. Each key is scoped to ONE workspace and "acts as"
 * an owner user (so the existing forCurrentWorkspace() / Auth::id() logic in
 * every controller works unchanged). The raw key is shown to the customer
 * exactly once; we store only its SHA-256 hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->index();   // owner to act as
            $table->string('name')->nullable();
            $table->string('key_hash', 64)->unique();         // sha256 of the raw key
            $table->string('prefix', 16)->index();            // shown in the UI (e.g. wsk_ab12cd)
            $table->json('scopes')->nullable();               // null = full access
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
