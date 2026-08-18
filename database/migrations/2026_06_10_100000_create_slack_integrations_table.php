<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slack integration: one connected Slack workspace per WaDesk workspace.
 * The bot token + signing secret are encrypted at rest (model casts).
 * Mirrors the HubspotIntegration table shape.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('slack_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');

            $table->string('team_id', 64)->nullable();        // Slack workspace id (T…)
            $table->string('team_name', 191)->nullable();
            $table->string('bot_user_id', 64)->nullable();    // U… of our bot

            $table->text('bot_token');                        // xoxb- (encrypted)
            $table->text('signing_secret');                   // (encrypted)
            $table->string('slash_command', 32)->default('/wa');

            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique('workspace_id');
            $table->unique('team_id');
            $table->index('user_id');
        });

        Schema::create('slack_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('workspace_id');
            $table->string('event', 40)->default('command');  // command | reply | error
            $table->string('detail', 500)->nullable();
            $table->string('status', 16)->default('ok');       // ok | error | no_match
            $table->timestamps();

            $table->index('integration_id');
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_integration_logs');
        Schema::dropIfExists('slack_integrations');
    }
};
