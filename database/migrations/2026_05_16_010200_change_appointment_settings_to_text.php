<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switch workspaces.appointment_settings from JSON to LONGTEXT.
 * Reason: the column carries an `encrypted:array` cast on the model,
 * which stores ciphertext (base64-encoded) — that doesn't satisfy
 * MySQL 8's JSON validity check, so saves fail with constraint 4025.
 * The model's cast handles serialization on the way in / out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->longText('appointment_settings')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('appointment_settings')->nullable()->change();
        });
    }
};
