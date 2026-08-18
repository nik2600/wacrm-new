<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $t) {
            // Full Baileys JID for the remote party: e.g.
            // "919145808988@s.whatsapp.net" or "236588474851489@lid".
            // Outgoing replies route via this exact string so LID-only
            // chats reach the right inbox.
            $t->string('raw_jid', 191)->nullable()->after('contact_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $t) {
            $t->dropColumn('raw_jid');
        });
    }
};
