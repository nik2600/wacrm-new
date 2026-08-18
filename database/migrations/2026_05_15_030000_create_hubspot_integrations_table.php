<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hubspot_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');

            $table->string('portal_id', 64)->nullable();
            $table->string('portal_name', 191)->nullable();
            $table->string('portal_email', 191)->nullable();

            // OAuth tokens encrypted at rest. Refresh on expiry.
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('access_token_expires_at')->nullable();
            $table->text('scopes')->nullable();

            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'portal_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubspot_integrations');
    }
};
