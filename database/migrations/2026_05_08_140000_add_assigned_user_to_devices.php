<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device "assigned to" — which team member owns / handles this
 * device. Defaults to the user who created it. Plus an
 * activate_after_pairing flag the add-device modal exposes as a
 * toggle ("route new sends to this device immediately").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_user_id')->nullable()->after('user_id')->index();
            $table->boolean('activate_after_pairing')->default(true)->after('active');
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn(['assigned_user_id', 'activate_after_pairing']);
        });
    }
};
