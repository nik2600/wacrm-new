<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace appointment booking config. JSON shape:
 * {
 *   google_oauth: {
 *     access_token, refresh_token, expires_at, scope, calendar_id, calendar_name
 *   },
 *   availability_windows: {
 *     mon: [{from:"09:00", to:"18:00"}],
 *     tue: [...], ...
 *   },
 *   slot_duration_minutes: 30,
 *   buffer_before_minutes: 0,
 *   buffer_after_minutes: 0,
 *   max_per_day: 16,
 *   advance_days: 14,             -- how far ahead bookings allowed
 *   confirmation_template_id: ?,  -- WhatsApp template fired on book
 *   reminder_minutes_before: 60,
 *   default_location: ""
 * }
 *
 * Tokens are encrypted inside the JSON via the model's encrypted cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('appointment_settings')->nullable()->after('plan_overrides');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('appointment_settings');
        });
    }
};
