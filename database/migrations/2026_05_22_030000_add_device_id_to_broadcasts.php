<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcasts didn't track a device id — every send went through
 * whichever device the dispatcher picked first. With multi-device
 * workspaces we need to pin a specific device per broadcast so the
 * sender number is deterministic. Multi-device picks fan out into
 * N broadcasts at create time (one per device), each with its own
 * device_id pin. Mirrors the /auto-reply per-device fan-out pattern.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
