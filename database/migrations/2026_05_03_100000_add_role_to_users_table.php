<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a `role` column to the users table so we can gate the
     * /admin/* surface with a simple middleware. Two values are
     * supported by App\Http\Middleware\EnsureUserIsAdmin and
     * App\Models\User::isAdmin(): 'user' | 'admin'.
     *
     * The column is added only if missing — earlier prototypes used
     * a single-letter encoding ('A'/'U') that shipped via the User
     * fillable list before this migration existed; calling migrate
     * a second time on those installs is a no-op.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('email');
            } else {
                // Earlier prototypes shipped a narrow varchar(4) role
                // column with 'U'/'A' values. Widen it so the new
                // 'user'/'admin' strings fit, and reset the default.
                $table->string('role', 32)->default('user')->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
