<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices table — ported from D:\wadesk_2806\New folder
 * (original `devices` schema + UserDevicesController). The old
 * project stored `device_user`, `device_name`, `phone_number`,
 * `countrycode`, `status`, `active`. We keep the same semantic
 * fields, encrypt the operator-authored ones at rest (name +
 * phone), and add `last_seen_at` so the UI can show health.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Encrypted-at-rest — name + phone + country_code are
            // operator/customer authored. Ciphertext is non-
            // deterministic so they're TEXT and not indexable;
            // uniqueness is enforced in PHP via the controller.
            $table->text('device_name');
            $table->text('country_code')->nullable();
            $table->text('phone_number');

            // Plain categorical columns — used in WHERE / GROUP BY.
            $table->string('region', 16)->nullable()->index();   // IN / US / AE / GB …
            $table->boolean('active')->default(false)->index();
            $table->string('status', 16)->default('disconnected')->index();

            // Health snapshot (24h sent / failed counts; refreshed
            // by the connect bridge or the placeholder job).
            $table->unsignedInteger('sent_24h')->default(0);
            $table->unsignedInteger('failed_24h')->default(0);
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
