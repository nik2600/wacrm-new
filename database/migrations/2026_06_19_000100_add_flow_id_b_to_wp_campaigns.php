<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A/B testing — Variant B flow id for flow campaigns. Templates have
 * template_id_a/template_id_b and custom text has custom_message/custom_message_b
 * for A/B variants; flows had no second slot, so an A/B flow campaign could only
 * ship the same flow to both halves. This column adds the missing variant.
 */
return new class extends Migration
{
    private string $table = 'wpcampaigns';

    public function up(): void
    {
        if (!Schema::hasColumn($this->table, 'flow_id_b')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->unsignedBigInteger('flow_id_b')->nullable()->after('flow_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn($this->table, 'flow_id_b')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->dropColumn('flow_id_b');
            });
        }
    }
};
