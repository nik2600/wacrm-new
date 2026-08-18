<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-app API auth support.
 *
 *  - users.passcode : hashed PIN the app's quick-unlock screen verifies
 *    (POST /auth/verify-passcode + POST /set-passcode).
 *  - user_otps      : single-row-per-user e-mail OTP store used by the
 *    app's 2FA verify flow (POST /2fa/send → /2fa/verify).
 *
 * password_reset_tokens (forgot-password OTP) + personal_access_tokens
 * (Sanctum) already exist, so they're not touched here.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'passcode')) {
                $t->string('passcode')->nullable()->after('password');
            }
        });

        if (! Schema::hasTable('user_otps')) {
            Schema::create('user_otps', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('otp', 10);
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();
                $t->unique('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'passcode')) {
                $t->dropColumn('passcode');
            }
        });
    }
};
