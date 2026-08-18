<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incoming (inbound) webhooks — the inverse of the outbound `webhooks`
 * table. The workspace GENERATES a unique URL here (/hooks/in/{token}),
 * hands it to any external service, and every request that service sends
 * is captured into incoming_webhook_events so the operator can inspect
 * the payload — and optionally relayed onward to a forward_url ("send to
 * their respected location").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name', 128)->nullable();
            $table->string('token', 64)->unique();        // the /hooks/in/{token} slug
            $table->text('forward_url')->nullable();       // encrypted — relay destination
            $table->boolean('forward_enabled')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('received_count')->default(0);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('incoming_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incoming_webhook_id')->index();
            $table->string('method', 8)->default('POST');
            $table->string('source_ip', 45)->nullable();
            $table->string('content_type', 191)->nullable();
            $table->longText('headers')->nullable();       // JSON
            $table->longText('payload')->nullable();       // raw body
            $table->boolean('forwarded')->default(false);
            $table->unsignedSmallInteger('forward_status')->nullable();
            $table->text('forward_error')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_webhook_events');
        Schema::dropIfExists('incoming_webhooks');
    }
};
