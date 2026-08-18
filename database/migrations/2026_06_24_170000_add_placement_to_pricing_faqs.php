<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each FAQ shows: 'pricing' (pricing page), 'home' (homepage slider),
 * or 'both'. Default 'pricing' so existing rows keep their current behaviour.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pricing_faqs', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_faqs', 'placement')) {
                $table->string('placement', 16)->default('pricing')->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_faqs', function (Blueprint $table) {
            if (Schema::hasColumn('pricing_faqs', 'placement')) {
                $table->dropColumn('placement');
            }
        });
    }
};
