<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the WaDesk browser extension.
 *
 * The extension authenticates a user once (email + password) and then
 * calls the /api/ext/* endpoints with `Authorization: Bearer <token>`.
 * We hash the token at rest (sha256) so a DB leak can't be replayed.
 * A separate table (rather than a users.api_token column) lets a user
 * stay logged in on several browsers at once and lets us revoke one
 * session without touching the others.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();   // sha256 hex
            $table->string('label', 64)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_api_tokens');
    }
};
