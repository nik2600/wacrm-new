<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `attributes.attribute_value` is `encrypted` — the ciphertext is far longer
 * than the plaintext (~210 chars of overhead), so a VARCHAR(255) column
 * overflowed on any value past ~30 plaintext chars (e.g. a tracking URL),
 * throwing "1406 Data too long". Widen it to TEXT so operators can store any
 * length. (`description` is already TEXT.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attributes') && Schema::hasColumn('attributes', 'attribute_value')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->text('attribute_value')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No safe revert — narrowing back to VARCHAR(255) would truncate any
        // long encrypted values already stored. Leave as TEXT.
    }
};
