<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan', 32)->default('starter');
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 16)->default('en');
            $table->string('brand_color', 16)->default('#075E54');
            $table->string('industry', 64)->nullable();
            $table->string('size_range', 32)->nullable();        // 1-5 / 6-20 / 21-100 / 100+
            $table->boolean('status')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 32)->default('member');       // owner / admin / member
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        // Add additional columns to users for the new auth flow. Each add is
        // guarded so the migration is idempotent and never collides with
        // columns another migration already created — notably `role`, which
        // 2026_05_03_100000_add_role_to_users_table creates first (runs
        // earlier by timestamp). Without the guard a fresh install throws
        // "Duplicate column name 'role'" at this step.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'site_name')) {
                $table->string('site_name')->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 32)->nullable()->after('site_name');
            }
            if (! Schema::hasColumn('users', 'country_code')) {
                $table->string('country_code', 8)->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 4)->default('U')->after('country_code');
            }
            if (! Schema::hasColumn('users', 'current_workspace_id')) {
                $table->unsignedBigInteger('current_workspace_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'has_seen_intro')) {
                $table->boolean('has_seen_intro')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['site_name', 'mobile', 'country_code', 'role', 'current_workspace_id', 'has_seen_intro']);
        });
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
