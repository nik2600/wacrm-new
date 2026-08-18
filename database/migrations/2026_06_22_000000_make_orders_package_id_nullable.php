<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.package_id must be NULLABLE.
 *
 * The original create_orders migration declared `package_id` as NOT NULL
 * (a plan subscription always had a package). But add-on packages and wallet
 * credit top-ups create orders with NO plan package — they set
 * `package_id => null` + `credit_package_id`/addon meta instead (see
 * CheckoutController + the addon/credit flows; OrderHistoryController even
 * filters add-ons with whereNull('package_id')).
 *
 * On servers that never got this column relaxed, those purchases fail with:
 *   SQLSTATE[23000] ... Column 'package_id' cannot be null
 *
 * Make it nullable so add-on / credit orders save correctly. (doctrine/dbal
 * is installed, so ->change() is available.) Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'package_id')) {
            return;
        }
        // Raw MySQL ALTER so this runs WITHOUT doctrine/dbal too (some installs
        // lack it). Falls back to the schema-builder ->change() if needed.
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE `orders` MODIFY `package_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            try {
                Schema::table('orders', function (Blueprint $table) {
                    $table->unsignedBigInteger('package_id')->nullable()->change();
                });
            } catch (\Throwable $e2) { /* leave as-is */ }
        }
    }

    public function down(): void
    {
        // Intentionally NOT reverting to NOT NULL: add-on / credit orders
        // legitimately carry a null package_id, so forcing NOT NULL again
        // would break those rows. Leave the column nullable.
    }
};
