<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Address block — populated by the admin create/edit form.
            // country stores ISO-2 (e.g. "IN"), state stores ISO subdivision (e.g. "MH"),
            // city is free-text resolved against the country-state-city dataset.
            $table->text('address')->nullable()->after('mobile');
            $table->string('city', 120)->nullable()->after('address');
            $table->string('state', 60)->nullable()->after('city');
            $table->string('country', 8)->nullable()->after('state');
            $table->string('zip', 24)->nullable()->after('country');
            $table->text('notes')->nullable()->after('zip');

            // Admin-created accounts: forced rotation on first login.
            $table->boolean('force_password_change')->default(false)->after('notes');
            $table->timestamp('welcome_email_sent_at')->nullable()->after('force_password_change');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'address', 'city', 'state', 'country', 'zip', 'notes',
                'force_password_change', 'welcome_email_sent_at',
            ]);
        });
    }
};
