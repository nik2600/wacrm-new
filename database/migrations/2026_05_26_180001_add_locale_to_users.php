<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Per-user UI locale. Resolution order in SetLocale middleware:
            //   users.locale → session 'app_locale' → workspace.default_language
            //   → system_settings.default_language → 'en'.
            // NULL means "follow workspace / platform default".
            if (! Schema::hasColumn('users', 'locale')) {
                $t->string('locale', 12)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'locale')) {
                $t->dropColumn('locale');
            }
        });
    }
};
