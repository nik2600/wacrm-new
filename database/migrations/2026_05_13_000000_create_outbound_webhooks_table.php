<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 128);
            $table->string('url', 1024);
            // events: array of subscribed events, e.g. ["conversation.replied", "conversation.resolved"]
            $table->json('events');
            // secret: optional HMAC secret. When set we send X-WaDesk-Signature
            // header with sha256-hmac of the request body.
            $table->string('secret', 128)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('fired_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->timestamp('last_fired_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_webhooks');
    }
};
