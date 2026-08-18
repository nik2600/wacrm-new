<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original migration declared wa_provider_configs.credentials_json
 * as JSON, but the model stores an encrypted blob (Laravel's Crypt
 * output is a base64 string, not valid JSON). MySQL rejects it. Switch
 * to TEXT — the column is opaque ciphertext on disk; only the model's
 * Crypt::decrypt round-trip ever inspects its contents.
 */
return new class extends Migration {
    public function up(): void
    {
        // MySQL needs raw SQL for json→text on existing columns
        if (config('database.default') === 'mysql' || \DB::connection()->getDriverName() === 'mysql') {
            \DB::statement('ALTER TABLE wa_provider_configs MODIFY credentials_json TEXT NULL');
        } else {
            Schema::table('wa_provider_configs', function (Blueprint $table) {
                $table->text('credentials_json')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql' || \DB::connection()->getDriverName() === 'mysql') {
            \DB::statement('ALTER TABLE wa_provider_configs MODIFY credentials_json JSON NULL');
        } else {
            Schema::table('wa_provider_configs', function (Blueprint $table) {
                $table->json('credentials_json')->nullable()->change();
            });
        }
    }
};
