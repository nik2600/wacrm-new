<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'social_provider')) {
                $table->string('social_provider', 20)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'social_provider_id')) {
                $table->string('social_provider_id', 191)->nullable()->after('social_provider');
                $table->index(['social_provider', 'social_provider_id'], 'users_social_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'social_provider_id')) {
                $table->dropIndex('users_social_idx');
                $table->dropColumn('social_provider_id');
            }
            if (Schema::hasColumn('users', 'social_provider')) {
                $table->dropColumn('social_provider');
            }
        });
    }
};
