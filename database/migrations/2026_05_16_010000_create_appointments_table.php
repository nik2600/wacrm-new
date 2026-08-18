<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appointments table — one row per booked slot. Tied to a workspace,
 * a contact, and (optionally) a conversation. Stores the Google event
 * id so we can update / cancel the calendar event later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();

            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64)->default('UTC');

            // status: pending = awaiting calendar write, confirmed = on google,
            // cancelled = user/customer cancelled, completed = past + attended,
            // no_show = past + customer didn't show.
            $table->string('status', 16)->default('pending');

            $table->string('google_event_id', 128)->nullable();
            $table->string('google_calendar_id', 191)->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'starts_at']);
            $table->index(['workspace_id', 'status']);
            $table->index('contact_id');
            $table->index('conversation_id');
            $table->index('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
