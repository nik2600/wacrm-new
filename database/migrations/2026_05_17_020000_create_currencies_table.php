<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed currency list (ported from SnapNest's pattern).
 *
 * `exchange_rate` is the value of 1 unit of this currency relative
 * to USD (the system base). Admin can fetch fresh rates from
 * https://open.er-api.com/v6/latest/USD (free, no key) via the
 * /admin/currencies/fetch-rates button.
 *
 * The active "default currency" lives in system_settings (key
 * `default_currency`). Per-workspace currency lives on the
 * `workspaces.currency` column and falls back to the default if null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 10)->unique();        // ISO code, normalized uppercase
            $table->string('symbol', 20)->nullable();
            $table->unsignedTinyInteger('precision')->default(2);   // decimal places
            $table->decimal('exchange_rate', 16, 6)->default(1.000000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
