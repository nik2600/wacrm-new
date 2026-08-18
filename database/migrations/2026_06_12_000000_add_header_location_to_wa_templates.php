<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOCATION header support for templates.
 *
 * Holds the encrypted JSON {latitude, longitude, name, address} for a
 * template whose header is a location pin. TEXT (not JSON) because the
 * model casts it `encrypted:array` — the ciphertext is long.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_templates', 'header_location')) {
                $table->text('header_location')->nullable()->after('header');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (Schema::hasColumn('wa_templates', 'header_location')) {
                $table->dropColumn('header_location');
            }
        });
    }
};
