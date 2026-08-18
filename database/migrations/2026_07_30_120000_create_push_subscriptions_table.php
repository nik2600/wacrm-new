<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser Web-Push subscriptions for the Team-Inbox PWA. One row per agent per
 * device/browser; WebPushService sends to these so the inbox rings even when
 * the app is closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) return;

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            // Full endpoint (can be long → TEXT, un-indexed); hash carries the
            // uniqueness so re-subscribing the same browser updates in place.
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh');
            $table->string('auth');
            $table->string('ua')->nullable();
            $table->string('channel', 40)->default('team-inbox')->index();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
