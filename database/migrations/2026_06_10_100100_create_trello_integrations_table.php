<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trello integration: one watched board per WaDesk workspace. The API
 * key/secret/token are encrypted at rest (model casts). When connected we
 * register a Trello webhook on the board (webhook_id stored) so card
 * events POST to /webhooks/trello.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('trello_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');

            $table->text('api_key');        // (encrypted)
            $table->text('api_secret');     // app secret — used to verify X-Trello-Webhook (encrypted)
            $table->text('token');          // user token, expiration=never (encrypted)

            $table->string('board_id', 64);
            $table->string('board_name', 191)->nullable();
            $table->string('webhook_id', 64)->nullable();

            // which action.type values to notify on (assign always on)
            $table->json('events')->nullable();
            // who gets add/update/delete alerts: assignee | members | fixed
            $table->string('notify_mode', 16)->default('assignee');
            $table->text('notify_number')->nullable();   // for 'fixed' (encrypted)
            // { trelloMemberId: contactId } manual overrides for member→contact
            $table->json('member_map')->nullable();

            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'board_id']);
            $table->index('webhook_id');
            $table->index('user_id');
        });

        Schema::create('trello_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('workspace_id');
            $table->string('event', 40)->default('webhook');  // action type or 'error'
            $table->string('detail', 500)->nullable();
            $table->string('status', 16)->default('ok');
            $table->timestamps();

            $table->index('integration_id');
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_integration_logs');
        Schema::dropIfExists('trello_integrations');
    }
};
