<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.avatar_path — relative path under public/storage to the
 * operator's uploaded profile picture (e.g. "avatars/2_6a06f.jpg").
 * Null means "no avatar uploaded yet" — the UI falls back to initials.
 *
 * Stored as a path (not a full URL) so we can move files / rename
 * the symlink target without rewriting rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path', 191)->nullable()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
