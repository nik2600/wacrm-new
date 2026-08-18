<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHEN a contact opted out.
 *
 * `contacts.is_unsubscribed` already existed but carried no timestamp, so
 * there was no way to answer "when did this person withdraw consent?" — the
 * one question that matters if an opt-out is ever disputed or a regulator
 * asks. The campaign pivot (`wp_campaign_contacts`) has had `unsubscribed_at`
 * all along; this brings the contact-level record to parity.
 *
 * Nullable: NULL means still subscribed (or opted out before this column
 * existed — those rows keep is_unsubscribed=1 with an unknown date rather
 * than being back-filled with a fabricated one).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contacts')) return;
        if (Schema::hasColumn('contacts', 'unsubscribed_at')) return;

        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('unsubscribed_at')->nullable()->after('is_unsubscribed');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('contacts')) return;
        if (!Schema::hasColumn('contacts', 'unsubscribed_at')) return;

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('unsubscribed_at');
        });
    }
};
