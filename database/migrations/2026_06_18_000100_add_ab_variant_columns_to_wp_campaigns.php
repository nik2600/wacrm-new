<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A/B testing — Variant B body for custom-text campaigns.
 *
 * NOTE: the campaigns table is `wpcampaigns` (no underscore). The Variant A/B
 * TEMPLATE columns (template_id_a / template_id_b) + ab_testing / ab_split
 * already exist there. The only missing piece for full A/B is a Variant B
 * message body for custom-text campaigns, added here (idempotently).
 */
return new class extends Migration
{
    private string $table = 'wpcampaigns';

    public function up(): void
    {
        if (!Schema::hasColumn($this->table, 'custom_message_b')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->text('custom_message_b')->nullable()->after('custom_message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn($this->table, 'custom_message_b')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropColumn('custom_message_b');
            });
        }
    }
};
