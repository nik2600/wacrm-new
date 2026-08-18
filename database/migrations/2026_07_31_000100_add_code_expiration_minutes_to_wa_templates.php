<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentication (OTP) templates carry a "code validity" that Meta shows the
 * user ("This code expires in N minutes") and uses for one-tap autofill. It
 * was HARD-CODED to 10 in TemplatePayloadBuilder; this column lets each auth
 * template store its own value (Meta allows 1–90). NULL = fall back to 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_templates', 'code_expiration_minutes')) {
                $table->unsignedSmallInteger('code_expiration_minutes')->nullable()->after('footer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            if (Schema::hasColumn('wa_templates', 'code_expiration_minutes')) {
                $table->dropColumn('code_expiration_minutes');
            }
        });
    }
};
