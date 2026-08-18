<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Custom-domain block. cname_verified is set true by the
            // CNAME-checker job once it resolves the expected target.
            $table->string('custom_domain', 191)->nullable()->after('slug');
            $table->boolean('cname_verified')->default(false)->after('custom_domain');

            // ISO-2 workspace country (separate from owner.country).
            $table->string('country', 8)->nullable()->after('industry');

            // Billing — cycle + admin-override caps.
            $table->string('billing_cycle', 20)->default('monthly')->after('plan'); // monthly|quarterly|annual|custom|trial
            $table->unsignedBigInteger('cap_monthly_messages')->nullable()->after('billing_cycle');
            $table->unsignedBigInteger('cap_daily_messages')->nullable()->after('cap_monthly_messages');
            $table->unsignedInteger('cap_devices')->nullable()->after('cap_daily_messages');
            $table->unsignedInteger('cap_users')->nullable()->after('cap_devices');

            // Provisioning flags toggled by admin during creation.
            $table->boolean('skip_onboarding_email')->default(false);
            $table->boolean('bill_to_platform_credit')->default(false);
            $table->boolean('pre_seed_sample_data')->default(false);

            // Internal sales/CSM note — visible only to admins.
            $table->text('admin_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'custom_domain', 'cname_verified', 'country',
                'billing_cycle', 'cap_monthly_messages', 'cap_daily_messages',
                'cap_devices', 'cap_users',
                'skip_onboarding_email', 'bill_to_platform_credit',
                'pre_seed_sample_data', 'admin_note',
            ]);
        });
    }
};
