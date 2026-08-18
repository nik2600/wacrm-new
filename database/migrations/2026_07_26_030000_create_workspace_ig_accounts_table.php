<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * workspace_ig_accounts — a workspace's MIRROR of an Instagram account that
 * physically lives on the linked Instaflow install. The real IG engine (Meta
 * OAuth, Graph, webhooks) stays on Instaflow; WaDesk stores only what it needs
 * to render the account as a channel on /devices and offer it as a sender:
 * the Instaflow account id + a cached profile snapshot. `instaflow_account_id`
 * is the Instaflow InstagramAccount row id (a string here so the id namespace
 * never collides with local device / provider ids). One row per
 * (workspace, instaflow_account_id). Idempotent for re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspace_ig_accounts')) return;

        Schema::create('workspace_ig_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            // Instaflow's InstagramAccount id (stringified — kept opaque).
            $table->string('instaflow_account_id', 64)->index();
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('avatar', 1024)->nullable();
            $table->string('status', 32)->default('connected');
            $table->unsignedInteger('followers')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // One mirror row per workspace + Instaflow account.
            $table->unique(['workspace_id', 'instaflow_account_id'], 'ws_ig_accounts_ws_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_ig_accounts');
    }
};
