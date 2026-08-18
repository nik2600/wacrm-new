<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $t) {
            // 'chat' = created in /chat composer (regular queues + 1-on-1 threads)
            // 'campaign' = created by WaCampaignsController::dispatchCampaignNow
            // The /chat conversations endpoint filters on origin='chat' so
            // campaign sends never pollute the chat inbox.
            $t->string('origin', 16)->default('chat')->index()->after('platform');
        });

        // Backfill: anything titled "Campaign · …" is from the campaign
        // pipeline and shouldn't show in /chat anymore.
        DB::table('conversations')
            ->where('title', 'like', 'Campaign · %')
            ->update(['origin' => 'campaign']);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $t) {
            $t->dropColumn('origin');
        });
    }
};
