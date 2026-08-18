<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the alternate Baileys JID (typically the @lid form) alongside
 * the canonical `raw_jid` so a later inbound that arrives only with the
 * LID still resolves to the same conversation when the first inbound
 * happened to carry the real phone (and vice versa).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('alt_jid', 191)->nullable()->after('raw_jid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['alt_jid']);
            $table->dropColumn('alt_jid');
        });
    }
};
